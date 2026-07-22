<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 🧪 Unit tests — feature-entitlement / premium-tier gating layer (#146)
 * ============================================================================
 *
 * Pure, DB-free tests for web/_functions/entitlements.php. Every case that
 * would otherwise touch the database drives the SAME lookup seams
 * ($GLOBALS['g2ml_entitlements_tier_lookup_override'] /
 * ['g2ml_entitlements_fallback_lookup_override']) that
 * _g2ml_fetchOrgTierRow()/_g2ml_fetchFallbackTierRow() check BEFORE ever
 * calling dbSelectOne() — mirroring org.php's existing
 * $GLOBALS['g2ml_dns_txt_lookup_override'] idiom (see
 * tests/integration/custom_domain_verification_test.php). Because the
 * override intercepts BEFORE any DB call, this file never requires
 * db_connect.php/db_query.php and never needs a live database.
 *
 * The one path this file CANNOT cover is the GlobalAdmin acting-context
 * short-circuit: that depends on getCurrentUser() -> isAuthenticated() ->
 * validateUserSession(), which is itself DB-backed (a real session row) and
 * therefore always resolves to "not authenticated" under this DB-free
 * runner — by design, matching this codebase's convention that DB-touching
 * behaviour is proven against a real schema instead of being faked (see
 * tests/unit/api_auth_test.php's own header comment). That path is fully
 * covered by tests/integration/entitlements_test.php instead.
 *
 * entitlements.php only DEFINES functions/constants at include time (guarded
 * direct-access, no top-level side effects, and every function_exists() guard
 * inside it degrades gracefully when auth.php is absent), so it is safe to
 * require standalone here — proving that graceful degradation as a side
 * effect of every case below.
 *
 * DB-touching functions built ON TOP of g2ml_getOrgTier() — createShortURL()'s
 * maxLinks gate, g2ml_apiCheckRateLimit()'s tier-aware daily limit, and
 * addOrgShortDomain()'s tier∩setting reconciliation — are covered by
 * tests/integration/entitlements_test.php, tests/integration/api_key_test.php,
 * and tests/integration/custom_domain_verification_test.php respectively.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.5.0 — Phase 11 (#146)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/entitlements.php';

/**
 * Reset all entitlements test state between cases: the request cache and
 * both lookup override hooks.
 *
 * @return void
 */
function g2ml_ent_unit_reset(): void
{
    g2ml_clearOrgTierCache();
    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    unset($GLOBALS['g2ml_entitlements_fallback_lookup_override']);
}

/**
 * Build a real, tblSubscriptionTiers-shaped row for override callables.
 *
 * @param  array $overrides  Column => value pairs to override the defaults.
 * @return array
 */
function g2ml_ent_unit_make_row(array $overrides = []): array
{
    $defaults = [
        'tierID'               => 'ent-unit-tier',
        'tierName'              => 'Entitlements Unit Test Tier',
        'maxLinks'              => 10,
        'maxCustomDomains'      => 2,
        'maxAPIRequestsPerDay'  => 1000,
        'maxLinksPages'         => 3,
        'hasAdvancedRedirects'  => 0,
        'hasAnalytics'          => 1,
        'hasQRCodes'            => 1,
        'hasAPIAccess'          => 0,
        'hasPrioritySupport'    => 0,
        'isActive'              => 1,
    ];

    return array_merge($defaults, $overrides);
}

// ============================================================================
// 🏠 [default] — always the unlimited sentinel, no lookup, no override needed
// ============================================================================

test('getOrgTier: the [default] org is always the unlimited sentinel — no lookup performed', function (): void
{
    g2ml_ent_unit_reset();

    // Deliberately NO override installed — if the code tried to reach the DB
    // path it would fall through to the real dbSelectOne(), which is not
    // loaded here and would fatal. Reaching a clean assertion proves the
    // '[default]' short-circuit never attempts a lookup at all.
    $tier = g2ml_getOrgTier('[default]');

    assert_true($tier['unlimited'], 'The [default] org resolves to the unlimited sentinel');
    assert_same(null, $tier['maxLinks'], 'maxLinks is NULL (unlimited)');
    assert_same(null, $tier['maxCustomDomains'], 'maxCustomDomains is NULL (unlimited)');
    assert_same(null, $tier['maxAPIRequestsPerDay'], 'maxAPIRequestsPerDay is NULL (unlimited)');
    assert_same(null, $tier['maxLinksPages'], 'maxLinksPages is NULL (unlimited)');
    assert_true($tier['hasAdvancedRedirects'], 'Every has* flag is true on the unlimited sentinel');
    assert_true($tier['hasAnalytics'], 'Every has* flag is true on the unlimited sentinel');
    assert_true($tier['hasQRCodes'], 'Every has* flag is true on the unlimited sentinel');
    assert_true($tier['hasAPIAccess'], 'Every has* flag is true on the unlimited sentinel');
    assert_true($tier['hasPrioritySupport'], 'Every has* flag is true on the unlimited sentinel');
    assert_same('default_org', $tier['source'], 'The resolution source is tagged default_org');
});

// ============================================================================
// 🧩 Normal org resolution via the override seam (a real tier row)
// ============================================================================

test('getOrgTier: a real tier row (via override) is normalised correctly', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['tierID' => 'acme-tier']);
    };

    $tier = g2ml_getOrgTier('acme-corp');

    assert_false($tier['unlimited'], 'A real, active tier is never the unlimited sentinel');
    assert_same('acme-tier', $tier['tierID'], 'tierID is carried through');
    assert_same(10, $tier['maxLinks'], 'A finite int limit is coerced to int');
    assert_same(2, $tier['maxCustomDomains'], 'A finite int limit is coerced to int');
    assert_true($tier['hasAnalytics'], 'A TINYINT(1) is coerced to strict true');
    assert_false($tier['hasAdvancedRedirects'], 'A TINYINT(0) is coerced to strict false');
    assert_same('tier', $tier['source'], 'The resolution source is tagged tier');
});

test('getOrgTier: a NULL limit column normalises to null (unlimited for that one limit)', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['maxLinks' => null]);
    };

    $tier = g2ml_getOrgTier('acme-corp-2');

    assert_same(null, $tier['maxLinks'], 'A NULL tier column stays null after normalisation');
    assert_same(2, $tier['maxCustomDomains'], 'Sibling finite columns are unaffected');
});

// ============================================================================
// 💾 Request cache
// ============================================================================

test('getOrgTier: is request-cached — the lookup override runs only once per org', function (): void
{
    g2ml_ent_unit_reset();

    $callCount = 0;

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle) use (&$callCount): array
    {
        $callCount++;

        return g2ml_ent_unit_make_row();
    };

    $first  = g2ml_getOrgTier('cached-org');
    $second = g2ml_getOrgTier('cached-org');

    assert_same(1, $callCount, 'The underlying lookup must run exactly once for two calls with the same orgHandle');
    assert_same($first['tierID'], $second['tierID'], 'Both calls return the same resolved tier');
});

test('clearOrgTierCache: evicts a single org so the next lookup runs again', function (): void
{
    g2ml_ent_unit_reset();

    $callCount = 0;

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle) use (&$callCount): array
    {
        $callCount++;

        return g2ml_ent_unit_make_row();
    };

    g2ml_getOrgTier('evict-me');
    g2ml_clearOrgTierCache('evict-me');
    g2ml_getOrgTier('evict-me');

    assert_same(2, $callCount, 'Clearing the cache for this org forces the lookup to run again');
});

test('clearOrgTierCache: with no argument clears every cached org', function (): void
{
    g2ml_ent_unit_reset();

    $callCount = 0;

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle) use (&$callCount): array
    {
        $callCount++;

        return g2ml_ent_unit_make_row();
    };

    g2ml_getOrgTier('org-a');
    g2ml_getOrgTier('org-b');
    assert_same(2, $callCount, 'Precondition: both distinct orgs triggered a lookup');

    g2ml_clearOrgTierCache();

    g2ml_getOrgTier('org-a');
    g2ml_getOrgTier('org-b');

    assert_same(4, $callCount, 'Clearing with no argument forces BOTH orgs to look up again');
});

// ============================================================================
// 🆓 Fallback to the lowest-sortOrder ACTIVE tier ("Free")
// ============================================================================

test('getOrgTier: an org with no resolvable tier (override returns null) falls back to the Free-shaped row', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): ?array
    {
        return null;
    };

    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): array
    {
        return g2ml_ent_unit_make_row(['tierID' => 'free', 'maxLinks' => 50, 'maxCustomDomains' => 0]);
    };

    $tier = g2ml_getOrgTier('no-tier-org');

    assert_same('free', $tier['tierID'], 'The fallback tier (Free) is resolved');
    assert_same(50, $tier['maxLinks'], 'The fallback tier\'s own limits are normalised correctly');
    assert_same(0, $tier['maxCustomDomains'], 'A genuine finite 0 limit is preserved (NOT treated as unlimited)');
    assert_false($tier['unlimited'], 'The Free fallback is a real tier, never the unlimited sentinel');
    assert_same('fallback_free', $tier['source'], 'The resolution source is tagged fallback_free');
});

test('getOrgTier: an INACTIVE tier (isActive=0) is treated the same as no tier — falls back to Free', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['tierID' => 'discontinued-tier', 'isActive' => 0]);
    };

    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): array
    {
        return g2ml_ent_unit_make_row(['tierID' => 'free']);
    };

    $tier = g2ml_getOrgTier('org-on-discontinued-tier');

    assert_same('free', $tier['tierID'], 'An inactive tier is never used — the Free fallback is resolved instead');
    assert_same('fallback_free', $tier['source'], 'The resolution source is tagged fallback_free');
});

// ============================================================================
// 🛡️ Fail OPEN — the non-negotiable robustness contract
// ============================================================================

test('getOrgTier: a simulated org-lookup failure (false) fails OPEN to the unlimited sentinel', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): bool
    {
        return false;
    };

    $tier = g2ml_getOrgTier('org-during-outage');

    assert_true($tier['unlimited'], 'A lookup failure fails OPEN to the unlimited sentinel — never blocks a legitimate action');
    assert_same('fail_open_lookup_error', $tier['source'], 'The resolution source is tagged fail_open_lookup_error');
});

test('getOrgTier: a simulated FALLBACK-lookup failure (false) fails OPEN to the unlimited sentinel', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): ?array
    {
        return null;
    };

    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): bool
    {
        return false;
    };

    $tier = g2ml_getOrgTier('org-needing-fallback-during-outage');

    assert_true($tier['unlimited'], 'A fallback lookup failure ALSO fails OPEN to the unlimited sentinel');
    assert_same('fail_open_fallback_lookup_error', $tier['source'], 'The resolution source is tagged fail_open_fallback_lookup_error');
});

test('getOrgTier: no active tier existing at all (misconfigured install) fails OPEN to the unlimited sentinel', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): ?array
    {
        return null;
    };

    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): ?array
    {
        return null;
    };

    $tier = g2ml_getOrgTier('org-in-misconfigured-install');

    assert_true($tier['unlimited'], 'No active tier anywhere fails OPEN — a misconfigured install must never block a legitimate action');
    assert_same('fail_open_no_active_tier', $tier['source'], 'The resolution source is tagged fail_open_no_active_tier');
});

test('getOrgTier: an unexpected exception during resolution fails OPEN to the unlimited sentinel', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        throw new RuntimeException('simulated unexpected failure');
    };

    $tier = g2ml_getOrgTier('org-that-throws');

    assert_true($tier['unlimited'], 'An unexpected exception fails OPEN — an entitlement bug must never break the caller');
    assert_same('fail_open_exception', $tier['source'], 'The resolution source is tagged fail_open_exception');
});

// ============================================================================
// 🚩 g2ml_canUseFeature — whitelist + flag reflection
// ============================================================================

test('canUseFeature: an unrecognised flag is denied WITHOUT performing any lookup', function (): void
{
    g2ml_ent_unit_reset();

    // No override installed — if this reached g2ml_getOrgTier() it would fall
    // through to the real dbSelectOne() and fatal. A clean false proves the
    // whitelist check runs first and short-circuits.
    $result = g2ml_canUseFeature('some-org', 'hasNotARealColumn');

    assert_false($result, 'An unrecognised flag must be denied, not merely default to false by accident');
});

test('canUseFeature: reflects a true flag from the resolved tier', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['hasQRCodes' => 1]);
    };

    assert_true(g2ml_canUseFeature('acme-corp', 'hasQRCodes'), 'A tier flag of 1 must be usable');
});

test('canUseFeature: reflects a false flag from the resolved tier', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['hasPrioritySupport' => 0]);
    };

    assert_false(g2ml_canUseFeature('acme-corp', 'hasPrioritySupport'), 'A tier flag of 0 must not be usable');
});

test('canUseFeature: the [default] org can use every feature (unlimited sentinel)', function (): void
{
    g2ml_ent_unit_reset();

    assert_true(g2ml_canUseFeature('[default]', 'hasAdvancedRedirects'), '[default] can use every feature');
});

// ============================================================================
// 🔢 g2ml_checkLimit — whitelist + limit maths
// ============================================================================

test('checkLimit: an unrecognised limit field fails OPEN (allowed, unlimited) WITHOUT performing any lookup', function (): void
{
    g2ml_ent_unit_reset();

    $result = g2ml_checkLimit('some-org', 'maxNotARealColumn', 999999);

    assert_true($result['allowed'], 'An unrecognised limit field must fail OPEN, never block');
    assert_same(null, $result['limit'], 'limit is null for an unrecognised field');
    assert_same(null, $result['remaining'], 'remaining is null for an unrecognised field');
});

test('checkLimit: a NULL tier limit is always allowed with limit/remaining both null', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['maxLinks' => null]);
    };

    $result = g2ml_checkLimit('acme-corp', 'maxLinks', 1000000);

    assert_true($result['allowed'], 'A NULL limit means unlimited — always allowed regardless of currentCount');
    assert_same(null, $result['limit'], 'limit is null (unlimited)');
    assert_same(null, $result['remaining'], 'remaining is null (unlimited)');
});

test('checkLimit: a finite limit — under, at, and over the count', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['maxLinks' => 5]);
    };

    $under = g2ml_checkLimit('acme-corp', 'maxLinks', 3);
    assert_true($under['allowed'], 'Under the limit (3 of 5) is allowed');
    assert_same(5, $under['limit'], 'limit reflects the tier value');
    assert_same(2, $under['remaining'], 'remaining is limit - currentCount');

    $atLimit = g2ml_checkLimit('acme-corp', 'maxLinks', 5);
    assert_false($atLimit['allowed'], 'AT the limit (5 of 5) is NOT allowed — a 6th would exceed it');
    assert_same(0, $atLimit['remaining'], 'remaining is 0 once the count meets the limit');

    $over = g2ml_checkLimit('acme-corp', 'maxLinks', 9);
    assert_false($over['allowed'], 'Over the limit is not allowed');
    assert_same(0, $over['remaining'], 'remaining never goes negative');
});

test('checkLimit: a negative currentCount is treated as 0', function (): void
{
    g2ml_ent_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_ent_unit_make_row(['maxLinks' => 5]);
    };

    $result = g2ml_checkLimit('acme-corp', 'maxLinks', -3);

    assert_true($result['allowed'], 'A negative currentCount is clamped to 0, which is always under a positive limit');
    assert_same(5, $result['remaining'], 'remaining is computed from the clamped 0, not the negative input');
});

test('checkLimit: the [default] org is never blocked (unlimited sentinel)', function (): void
{
    g2ml_ent_unit_reset();

    $result = g2ml_checkLimit('[default]', 'maxLinks', 999999999);

    assert_true($result['allowed'], '[default] is never blocked regardless of currentCount');
    assert_same(null, $result['limit'], 'limit is null for the unlimited sentinel');
});
