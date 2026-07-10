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
 * 💎 Go2My.Link — Feature Entitlement / Premium-Tier Gating Layer (#146)
 * ============================================================================
 *
 * Turns the already-modelled subscription tiers (tblSubscriptionTiers,
 * tblOrganisations.tierID) into an enforceable, provider-agnostic entitlement
 * layer. Ships BEFORE billing (owner decision: gate first, bill later —
 * GlobalAdmin assigns tiers manually today via updateOrganisation()).
 *
 * Functions:
 *   - g2ml_getOrgTier()        — resolve an org's effective tier (request-cached)
 *   - g2ml_canUseFeature()     — check a has* feature flag
 *   - g2ml_checkLimit()        — check a max* numeric limit against a current count
 *   - g2ml_clearOrgTierCache() — invalidate the request cache (called by
 *                                updateOrganisation() when tierID changes)
 *
 * 🛡️ Non-negotiable robustness (per #146): FAIL OPEN on any entitlement-SYSTEM
 * error (a DB/query failure resolving a tier must NEVER block create/redirect/
 * login) — every DB read here is wrapped so a failure resolves to the
 * "unlimited" sentinel tier rather than throwing or returning a blocking
 * result. Fail CLOSED only when a limit is genuinely, provably exceeded.
 *
 * GlobalAdmin (any org) and the platform `[default]` org are ALWAYS resolved
 * to the unlimited sentinel — they are never gated, regardless of any tier
 * row that may exist.
 *
 * Dependencies: db_query.php (dbSelectOne), auth.php (getCurrentUser(),
 *               hasMinimumRole()) — both loaded earlier in page_init.php's
 *               Layer 3. Safe to require standalone (as tests do) since this
 *               file only DEFINES functions/constants; it never calls them at
 *               include time.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.5.0 — Phase 11 (#146)
 *
 * 📖 References:
 *     - tblSubscriptionTiers schema: web/_sql/schema/011_core_subscription_tiers.sql
 *     - tblOrganisations schema:     web/_sql/schema/012_core_organisations.sql (tierID column)
 *     - Seed tiers (GBP):            web/_sql/seeds/001_subscription_tiers.sql
 *     - GitHub issue:                #146
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 📋 Whitelists — the ONLY column names g2ml_canUseFeature()/g2ml_checkLimit()
// will ever interpolate into a lookup. Any other value is rejected.
// ============================================================================
if (!defined('G2ML_ENTITLEMENT_FEATURE_FLAGS'))
{
    define('G2ML_ENTITLEMENT_FEATURE_FLAGS', [
        'hasAdvancedRedirects',
        'hasAnalytics',
        'hasQRCodes',
        'hasAPIAccess',
        'hasPrioritySupport',
    ]);
}

if (!defined('G2ML_ENTITLEMENT_LIMIT_FIELDS'))
{
    define('G2ML_ENTITLEMENT_LIMIT_FIELDS', [
        'maxLinks',
        'maxCustomDomains',
        'maxAPIRequestsPerDay',
        'maxLinksPages',
    ]);
}

// ============================================================================
// 🗄️ Test-only lookup overrides (mirrors org.php's
// $GLOBALS['g2ml_dns_txt_lookup_override'] idiom) — let tests simulate an org
// row / fallback-tier row (including a system FAILURE, `false`) without a live
// database. Unset in normal runtime, where the real dbSelectOne() call runs.
// ============================================================================

/**
 * Fetch the raw org→tier JOIN row used to resolve an org's entitlements.
 *
 * Test-only override: set $GLOBALS['g2ml_entitlements_tier_lookup_override']
 * to a callable of shape `(string $orgHandle): array|null|false` to simulate
 * a lookup result — including a system failure (`false`) — deterministically.
 *
 * @param  string $orgHandle
 * @return array|null|false  The joined row, null when not found, false on a
 *                            DB/query system error.
 */
function _g2ml_fetchOrgTierRow(string $orgHandle): array|null|false
{
    if (isset($GLOBALS['g2ml_entitlements_tier_lookup_override'])
        && is_callable($GLOBALS['g2ml_entitlements_tier_lookup_override']))
    {
        $override = $GLOBALS['g2ml_entitlements_tier_lookup_override'];

        return $override($orgHandle);
    }

    return dbSelectOne(
        "SELECT t.tierID, t.tierName, t.maxLinks, t.maxCustomDomains,
                t.maxAPIRequestsPerDay, t.maxLinksPages, t.hasAdvancedRedirects,
                t.hasAnalytics, t.hasQRCodes, t.hasAPIAccess, t.hasPrioritySupport,
                t.isActive
         FROM tblOrganisations o
         INNER JOIN tblSubscriptionTiers t ON o.tierID = t.tierID
         WHERE o.orgHandle = ?
         LIMIT 1",
        's',
        [$orgHandle]
    );
}

/**
 * Fetch the lowest-sortOrder ACTIVE tier row (the "Free" fallback tier).
 *
 * Test-only override: set $GLOBALS['g2ml_entitlements_fallback_lookup_override']
 * to a callable of shape `(): array|null|false` to simulate a result —
 * including a system failure (`false`) — deterministically.
 *
 * @return array|null|false  The fallback tier row, null when no tier is
 *                            active at all (misconfigured install), false on
 *                            a DB/query system error.
 */
function _g2ml_fetchFallbackTierRow(): array|null|false
{
    if (isset($GLOBALS['g2ml_entitlements_fallback_lookup_override'])
        && is_callable($GLOBALS['g2ml_entitlements_fallback_lookup_override']))
    {
        $override = $GLOBALS['g2ml_entitlements_fallback_lookup_override'];

        return $override();
    }

    return dbSelectOne(
        "SELECT tierID, tierName, maxLinks, maxCustomDomains, maxAPIRequestsPerDay,
                maxLinksPages, hasAdvancedRedirects, hasAnalytics, hasQRCodes,
                hasAPIAccess, hasPrioritySupport, isActive
         FROM tblSubscriptionTiers
         WHERE isActive = 1
         ORDER BY sortOrder ASC
         LIMIT 1",
        '',
        []
    );
}

// ============================================================================
// 🔧 Internal helpers — context detection, normalisation, sentinels
// ============================================================================

/**
 * Is the CURRENT acting context a GlobalAdmin?
 *
 * Defensive: works even if auth.php has not been loaded (returns false rather
 * than a fatal error) so this file never hard-depends on load order.
 *
 * @return bool
 */
function _g2ml_entitlementsIsGlobalAdminContext(): bool
{
    if (!function_exists('getCurrentUser') || !function_exists('hasMinimumRole'))
    {
        return false;
    }

    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        return false;
    }

    return hasMinimumRole($currentUser['role'], 'GlobalAdmin');
}

/**
 * Coerce a nullable numeric DB value to a nullable int (NULL = unlimited).
 *
 * @param  mixed $value
 * @return int|null
 */
function _g2ml_entitlementsNormaliseNullableInt(mixed $value): ?int
{
    if ($value === null)
    {
        return null;
    }

    return (int) $value;
}

/**
 * Coerce a TINYINT(1) DB value to a strict bool.
 *
 * @param  mixed $value
 * @return bool
 */
function _g2ml_entitlementsNormaliseBool(mixed $value): bool
{
    if ((int) $value === 1)
    {
        return true;
    }

    return false;
}

/**
 * Build the normalised tier array from a real tblSubscriptionTiers-shaped row.
 *
 * @param  array  $row     Row with tierID/tierName/max-limit/has-flag columns.
 * @param  string $source  Debug/log tag for how this tier was resolved.
 * @return array
 */
function _g2ml_entitlementsNormaliseTierRow(array $row, string $source): array
{
    return [
        'tierID'               => (string) ($row['tierID'] ?? ''),
        'tierName'             => (string) ($row['tierName'] ?? ''),
        'maxLinks'             => _g2ml_entitlementsNormaliseNullableInt($row['maxLinks'] ?? null),
        'maxCustomDomains'     => _g2ml_entitlementsNormaliseNullableInt($row['maxCustomDomains'] ?? null),
        'maxAPIRequestsPerDay' => _g2ml_entitlementsNormaliseNullableInt($row['maxAPIRequestsPerDay'] ?? null),
        'maxLinksPages'        => _g2ml_entitlementsNormaliseNullableInt($row['maxLinksPages'] ?? null),
        'hasAdvancedRedirects' => _g2ml_entitlementsNormaliseBool($row['hasAdvancedRedirects'] ?? 0),
        'hasAnalytics'         => _g2ml_entitlementsNormaliseBool($row['hasAnalytics'] ?? 0),
        'hasQRCodes'           => _g2ml_entitlementsNormaliseBool($row['hasQRCodes'] ?? 0),
        'hasAPIAccess'         => _g2ml_entitlementsNormaliseBool($row['hasAPIAccess'] ?? 0),
        'hasPrioritySupport'   => _g2ml_entitlementsNormaliseBool($row['hasPrioritySupport'] ?? 0),
        'unlimited'            => false,
        'source'               => $source,
    ];
}

/**
 * Build the "unlimited" sentinel tier — every limit NULL, every flag true.
 *
 * Returned for: the platform `[default]` org, a GlobalAdmin acting context,
 * and EVERY entitlement-system failure path (fail OPEN).
 *
 * @param  string $source  Debug/log tag for why this sentinel was returned.
 * @return array
 */
function _g2ml_entitlementsUnlimitedTier(string $source): array
{
    return [
        'tierID'               => 'unlimited',
        'tierName'             => 'Unlimited',
        'maxLinks'             => null,
        'maxCustomDomains'     => null,
        'maxAPIRequestsPerDay' => null,
        'maxLinksPages'        => null,
        'hasAdvancedRedirects' => true,
        'hasAnalytics'         => true,
        'hasQRCodes'           => true,
        'hasAPIAccess'         => true,
        'hasPrioritySupport'   => true,
        'unlimited'            => true,
        'source'               => $source,
    ];
}

/**
 * Resolve an org's tier WITHOUT the request cache (the cache wrapper is
 * g2ml_getOrgTier() below). Every branch that can fail is guarded so a
 * DB/query error or any unexpected exception resolves to the unlimited
 * sentinel (fail OPEN) rather than propagating.
 *
 * @param  string $orgHandle
 * @return array  Normalised tier array — see _g2ml_entitlementsNormaliseTierRow().
 */
function _g2ml_resolveOrgTier(string $orgHandle): array
{
    try
    {
        // The platform's own catch-all org is never gated.
        if ($orgHandle === '[default]')
        {
            return _g2ml_entitlementsUnlimitedTier('default_org');
        }

        // A GlobalAdmin acting context is never gated, regardless of which
        // org they are operating on behalf of.
        if (_g2ml_entitlementsIsGlobalAdminContext() === true)
        {
            return _g2ml_entitlementsUnlimitedTier('globaladmin');
        }

        $row = _g2ml_fetchOrgTierRow($orgHandle);

        if ($row === false)
        {
            // A DB/query SYSTEM error must never block a legitimate action.
            error_log(
                '[Go2My.Link] ERROR: entitlements — org tier lookup failed for org "'
                . $orgHandle . '" — failing OPEN (unlimited) so create/redirect/login are never blocked by this system.'
            );

            return _g2ml_entitlementsUnlimitedTier('fail_open_lookup_error');
        }

        if ($row !== null && (int) ($row['isActive'] ?? 0) === 1)
        {
            return _g2ml_entitlementsNormaliseTierRow($row, 'tier');
        }

        // No org row / no resolvable tier / an INACTIVE tier — fall back to
        // the lowest-sortOrder ACTIVE tier (the "Free" tier per seed 001).
        $fallbackRow = _g2ml_fetchFallbackTierRow();

        if ($fallbackRow === false)
        {
            error_log(
                '[Go2My.Link] ERROR: entitlements — fallback Free-tier lookup failed (resolving org "'
                . $orgHandle . '") — failing OPEN (unlimited).'
            );

            return _g2ml_entitlementsUnlimitedTier('fail_open_fallback_lookup_error');
        }

        if ($fallbackRow === null)
        {
            // No active tier exists at all — a misconfigured install, not a
            // legitimate "over limit" state. Fail OPEN.
            error_log(
                '[Go2My.Link] ERROR: entitlements — no active subscription tier exists (misconfigured tblSubscriptionTiers) '
                . 'resolving org "' . $orgHandle . '" — failing OPEN (unlimited).'
            );

            return _g2ml_entitlementsUnlimitedTier('fail_open_no_active_tier');
        }

        return _g2ml_entitlementsNormaliseTierRow($fallbackRow, 'fallback_free');
    }
    catch (Throwable $unexpectedError)
    {
        // Belt-and-braces: an entitlement bug must NEVER be able to break
        // create/redirect/login. Any unforeseen exception also fails OPEN.
        error_log(
            '[Go2My.Link] ERROR: entitlements — unexpected exception resolving tier for org "'
            . $orgHandle . '": ' . $unexpectedError->getMessage() . ' — failing OPEN (unlimited).'
        );

        return _g2ml_entitlementsUnlimitedTier('fail_open_exception');
    }
}

// ============================================================================
// 🌐 Public API
// ============================================================================

/**
 * Resolve an organisation's effective entitlement tier — request-cached.
 *
 * Resolution order:
 *   1. The platform `[default]` org             → unlimited sentinel.
 *   2. A GlobalAdmin acting context              → unlimited sentinel.
 *   3. The org's own ACTIVE tier (JOIN)           → that tier, normalised.
 *   4. No org/tier row, or an INACTIVE tier       → lowest-sortOrder ACTIVE
 *                                                    tier (Free), normalised.
 *   5. ANY entitlement-system failure at any step → unlimited sentinel
 *                                                    (fail OPEN), logged.
 *
 * @param  string $orgHandle
 * @return array  Normalised tier: tierID, tierName, maxLinks, maxCustomDomains,
 *                maxAPIRequestsPerDay, maxLinksPages (int|null, NULL=unlimited),
 *                hasAdvancedRedirects, hasAnalytics, hasQRCodes, hasAPIAccess,
 *                hasPrioritySupport (bool), unlimited (bool), source (string).
 *
 * Usage example:
 *   $tier = g2ml_getOrgTier('acme-corp');
 *   if ($tier['maxLinks'] === null) { // unlimited }
 */
function g2ml_getOrgTier(string $orgHandle): array
{
    if (!isset($GLOBALS['g2ml_entitlements_org_tier_cache'])
        || !is_array($GLOBALS['g2ml_entitlements_org_tier_cache']))
    {
        $GLOBALS['g2ml_entitlements_org_tier_cache'] = [];
    }

    if (array_key_exists($orgHandle, $GLOBALS['g2ml_entitlements_org_tier_cache']))
    {
        return $GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle];
    }

    $tier = _g2ml_resolveOrgTier($orgHandle);

    $GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle] = $tier;

    return $tier;
}

/**
 * Clear the request-scoped org→tier cache.
 *
 * Called by updateOrganisation() (org.php) whenever an org's tierID changes,
 * so a GlobalAdmin who reassigns a tier and then immediately acts on that org
 * within the SAME request sees the new tier rather than a stale cached one.
 * Also used by tests to reset state between cases.
 *
 * @param  string|null $orgHandle  A single org to evict, or null to clear everything.
 * @return void
 */
function g2ml_clearOrgTierCache(?string $orgHandle = null): void
{
    if ($orgHandle === null)
    {
        $GLOBALS['g2ml_entitlements_org_tier_cache'] = [];

        return;
    }

    if (isset($GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle]))
    {
        unset($GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle]);
    }
}

/**
 * Check whether an org's tier includes a given `has*` feature flag.
 *
 * $flag is whitelisted to exactly the 5 tblSubscriptionTiers `has*` columns
 * (G2ML_ENTITLEMENT_FEATURE_FLAGS); any other value is rejected (denied)
 * rather than reaching a lookup. NOT currently used to hard-403 any existing
 * access flow (rollout decision, #146) — see the "hard gate would go here"
 * comments at web/_functions/analytics.php (analytics), the CueRCode QR
 * create path (web/Go2My.Link/_functions/shorturl_create.php), and the
 * advanced-redirects roadmap (#Phase 9) — this is exposed today for UI/API
 * callers to build upgrade CTAs and usage meters against.
 *
 * @param  string $orgHandle
 * @param  string $flag  One of G2ML_ENTITLEMENT_FEATURE_FLAGS.
 * @return bool
 *
 * Usage example:
 *   if (g2ml_canUseFeature($orgHandle, 'hasAnalytics')) { // show upgrade CTA if false }
 */
function g2ml_canUseFeature(string $orgHandle, string $flag): bool
{
    if (!in_array($flag, G2ML_ENTITLEMENT_FEATURE_FLAGS, true))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_canUseFeature() called with an unrecognised flag "'
            . $flag . '" — denying.'
        );

        return false;
    }

    $tier = g2ml_getOrgTier($orgHandle);

    if (($tier[$flag] ?? false) === true)
    {
        return true;
    }

    return false;
}

/**
 * Check a `max*` numeric limit against a caller-supplied current count.
 *
 * $limit is whitelisted to exactly the 4 tblSubscriptionTiers `max*` columns
 * (G2ML_ENTITLEMENT_LIMIT_FIELDS). An unrecognised $limit fails OPEN
 * (allowed, unlimited) rather than blocking — the same fail-open contract as
 * every other error path in this file, since a caller-supplied typo is an
 * entitlement-SYSTEM defect, not a genuine over-limit condition.
 *
 * A NULL limit value (the tier column itself, or the whitelist-miss fallback
 * above) always means "allowed, unlimited" — 'limit' and 'remaining' are both
 * returned as null.
 *
 * @param  string $orgHandle
 * @param  string $limit        One of G2ML_ENTITLEMENT_LIMIT_FIELDS.
 * @param  int    $currentCount The caller's current usage count (negative
 *                               values are treated as 0).
 * @return array  ['allowed' => bool, 'limit' => int|null, 'remaining' => int|null]
 *
 * Usage example:
 *   $check = g2ml_checkLimit($orgHandle, 'maxLinks', $activeLinkCount);
 *   if (!$check['allowed']) { // block the create }
 */
function g2ml_checkLimit(string $orgHandle, string $limit, int $currentCount): array
{
    if (!in_array($limit, G2ML_ENTITLEMENT_LIMIT_FIELDS, true))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_checkLimit() called with an unrecognised limit field "'
            . $limit . '" — failing OPEN (allowed, unlimited).'
        );

        return [
            'allowed'   => true,
            'limit'     => null,
            'remaining' => null,
        ];
    }

    $tier       = g2ml_getOrgTier($orgHandle);
    $limitValue = $tier[$limit] ?? null;

    if ($limitValue === null)
    {
        return [
            'allowed'   => true,
            'limit'     => null,
            'remaining' => null,
        ];
    }

    $limitValueInt = (int) $limitValue;

    if ($currentCount < 0)
    {
        $currentCountSafe = 0;
    }
    else
    {
        $currentCountSafe = $currentCount;
    }

    if ($currentCountSafe < $limitValueInt)
    {
        $allowed = true;
    }
    else
    {
        $allowed = false;
    }

    $remaining = $limitValueInt - $currentCountSafe;

    if ($remaining < 0)
    {
        $remaining = 0;
    }

    return [
        'allowed'   => $allowed,
        'limit'     => $limitValueInt,
        'remaining' => $remaining,
    ];
}
