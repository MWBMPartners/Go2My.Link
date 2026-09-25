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
 *   - g2ml_canUseFeature()     — check a has* feature flag (legacy features)
 *   - g2ml_checkLimit()        — check a max* numeric limit against a current
 *                                count (legacy features)
 *   - g2ml_featureAllowed()    — check a yes/no feature by its registry name
 *                                (NEW features, LP-01 #216 — see below)
 *   - g2ml_featureLimit()      — check a numeric limit by its registry name
 *                                (NEW features, LP-01 #216 — see below)
 *   - g2ml_clearOrgTierCache() — invalidate the request caches (called by
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
 * 🧩 TWO KINDS OF FEATURE, TWO PAIRS OF FUNCTIONS (LP-01, #216)
 *
 *   LEGACY features are the fixed has* and max* columns on tblSubscriptionTiers.
 *   g2ml_canUseFeature() and g2ml_checkLimit() read them and accept ONLY those
 *   column names. Nothing about them changed in LP-01.
 *
 *   NEW features (everything the LinksPage programme adds — hiding the
 *   branding line, SEO controls, scheduled links, statistics history and so
 *   on) have no column. Each one is a row in the feature registry
 *   (tblFeatures) with one row per plan in tblTierFeatures, and is checked
 *   with g2ml_featureAllowed() or g2ml_featureLimit() using its registry name
 *   (for example 'linkspage.hide_branding'). The owner moves a feature to a
 *   different plan by changing one tblTierFeatures row — no code change, no
 *   schema change, no deploy. The rows are seeded by
 *   web/_sql/seeds/023_linkspage_feature_registry.sql (fresh installs) and
 *   web/_sql/migrations/021_linkspage_feature_registry.sql (live databases).
 *
 *   The new pair gives the SAME answer whether the pricing engine's master
 *   switch (billing.pricing_engine_enabled) is off or on. With the switch on,
 *   g2ml_getOrgTier() already carries the engine's resolved 'features' list;
 *   with it off, they ask web/_functions/pricing.php's
 *   g2ml_pricingResolveOrgTier() directly, which reads the same rows with the
 *   same merge rules and deliberately ignores the switch.
 *
 *   The new pair is DELIBERATELY STRICTER than the legacy pair when something
 *   goes wrong:
 *     - g2ml_featureAllowed() FAILS CLOSED: a system error, or a feature name
 *       that is not in the registry (or is switched off there), answers "no"
 *       and writes one error_log line naming the feature and the org. Every
 *       feature behind it is an optional extra on a page that still works
 *       without it, so "no" can only ever hide a paid extra — it can never
 *       block creating a link, a redirect or a login. The opposite choice
 *       (fail open, like g2ml_canUseFeature()) would hand every paid extra to
 *       every free account whenever the database hiccuped.
 *     - g2ml_featureLimit() FAILS OPEN (allowed, unlimited), exactly like
 *       g2ml_checkLimit(), because a limit only ever blocks a create-type
 *       action and the house rule is that an entitlement-system fault never
 *       blocks a legitimate action.
 *   Neither function can tell you WHERE the answer came from; call
 *   g2ml_getOrgTier() when you need that.
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
 *     - Feature registry tables:     web/_sql/schema/036_pricing_engine.sql
 *     - LinksPage registry rows:     web/_sql/seeds/023_linkspage_feature_registry.sql
 *     - Registry resolver:           web/_functions/pricing.php (g2ml_pricingResolveOrgTier())
 *     - GitHub issues:               #146 (legacy gating), #216 (LP-01 registry gate)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

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
        'hasCustomHTML',
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
                t.hasCustomHTML, t.isActive
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
                hasAPIAccess, hasPrioritySupport, hasCustomHTML, isActive
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
        'hasCustomHTML'        => _g2ml_entitlementsNormaliseBool($row['hasCustomHTML'] ?? 0),
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
        'hasCustomHTML'        => true,
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

        // -------------------------------------------------------------------
        // 💷 OPTIONAL pricing-engine hook: when web/_functions/pricing.php is
        // loaded AND the operator has switched 'billing.pricing_engine_enabled'
        // ON, resolve entitlements from the flexible model instead. Any failure
        // (false/non-array) falls through to the legacy resolution below —
        // fail OPEN to OLD behaviour, never to a block. With the setting at
        // its seeded '0' this block is inert and the function behaves exactly
        // as before (see web/_sql/seeds/019_pricing_settings.sql).
        // -------------------------------------------------------------------
        if (function_exists('g2ml_pricingEngineEnabled')
            && function_exists('g2ml_pricingResolveOrgTier'))
        {
            if (g2ml_pricingEngineEnabled() === true)
            {
                $engineTier = g2ml_pricingResolveOrgTier($orgHandle);

                if (is_array($engineTier))
                {
                    return $engineTier;
                }
            }
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
 * Clear the request-scoped org→tier cache AND the registry feature-values
 * cache that g2ml_featureAllowed() / g2ml_featureLimit() use.
 *
 * Called by updateOrganisation() (org.php) whenever an org's tierID changes,
 * so a GlobalAdmin who reassigns a tier and then immediately acts on that org
 * within the SAME request sees the new tier rather than a stale cached one.
 * Also used by tests to reset state between cases.
 *
 * WHY BOTH CACHES (LP-01, #216): the feature-values cache is worked out FROM
 * the tier, so clearing only the tier cache would leave g2ml_featureAllowed()
 * answering from the org's OLD plan for the rest of the request. Every
 * existing caller (org.php, g2ml_clearPricingCache(), and every test file)
 * already calls this function to reset, so clearing both here means none of
 * them needs to learn about the second cache.
 *
 * @param  string|null $orgHandle  A single org to evict, or null to clear everything.
 * @return void
 */
function g2ml_clearOrgTierCache(?string $orgHandle = null): void
{
    if ($orgHandle === null)
    {
        $GLOBALS['g2ml_entitlements_org_tier_cache'] = [];
        $GLOBALS['g2ml_feature_values_cache']        = [];

        return;
    }

    if (isset($GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle]))
    {
        unset($GLOBALS['g2ml_entitlements_org_tier_cache'][$orgHandle]);
    }

    // array_key_exists() rather than isset(): a cached system-error result is
    // stored as `false`, and although isset() is true for false, being
    // explicit here avoids anyone later "simplifying" it into a check that
    // skips falsy entries.
    if (isset($GLOBALS['g2ml_feature_values_cache'])
        && is_array($GLOBALS['g2ml_feature_values_cache'])
        && array_key_exists($orgHandle, $GLOBALS['g2ml_feature_values_cache']))
    {
        unset($GLOBALS['g2ml_feature_values_cache'][$orgHandle]);
    }
}

/**
 * Check whether an org's tier includes a given `has*` feature flag.
 *
 * $flag is whitelisted to exactly the 6 tblSubscriptionTiers `has*` columns
 * (G2ML_ENTITLEMENT_FEATURE_FLAGS); any other value is rejected (denied)
 * rather than reaching a lookup. (This comment used to say 5; hasCustomHTML
 * was added for #49 and the count was never updated.) A NEW feature that has
 * no has* column is checked with g2ml_featureAllowed() below, never by
 * adding a column here. NOT currently used to hard-403 any existing
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

// ============================================================================
// 🧩 Registry-driven feature gate — for NEW features only (LP-01, #216)
// ============================================================================
//
// WHAT THIS IS FOR. Every feature the LinksPage programme adds (hiding the
// "Powered by Lnks.page" line, SEO controls, scheduled links, statistics
// history and so on) is a row in the feature registry, tblFeatures, with one
// row per plan in tblTierFeatures. Callers ask g2ml_featureAllowed() or
// g2ml_featureLimit() using the feature's registry name. There is no new
// column on tblSubscriptionTiers for any of them, and there must not be:
// moving a feature between plans is meant to be a one-row UPDATE, not a
// schema change and a deploy.
//
// WHAT WAS WRONG BEFORE, AND WHAT WAS REJECTED.
//   - g2ml_canUseFeature() / g2ml_checkLimit() above only accept the fixed
//     has* and max* column names, so a new feature could not go through them
//     without a new column per feature. Rejected: that is the exact problem
//     the registry was built to remove.
//   - pricing.php's g2ml_pricingCanUse() reads registry names, but it answers
//     "no" for EVERYONE while the pricing engine's master switch is off (it
//     ships off), and it skips the '[default]' org and GlobalAdmin "never
//     gated" rules. Rejected: using it would have switched every new feature
//     off for every customer.
//   - Turning the pricing engine on so g2ml_pricingCanUse() works. Rejected
//     for this purpose: switching the engine on is a separate owner decision
//     that also changes how the LEGACY features are resolved. The functions
//     below work with the switch off or on and give the same answer.
//
// HOW IT WORKS. _g2ml_resolveFeatureValues() starts from g2ml_getOrgTier(),
// so the '[default]' org rule, the GlobalAdmin rule and the system-error
// detection all apply exactly as they do for the legacy features. If the
// engine is on, g2ml_getOrgTier() has already resolved every registry value
// (its 'features' list) and that is used as it is. If the engine is off,
// pricing.php's g2ml_pricingResolveOrgTier() is called directly: it never
// checks the switch itself, reuses this file's own org-to-tier lookups, and
// merges the SAME rows in the SAME order (registry default, then the plan's
// row, then any per-org override). One row, one merge routine, both modes.
//
// WHAT IT CANNOT DO.
//   - It does not enforce anything by itself. Callers must check on the
//     server when a setting is SAVED and again when the page is SHOWN, using
//     the page's own orgHandle (an admin-screen check is only for showing an
//     upgrade note). A page whose org row was deleted has a NULL orgHandle:
//     pass '' — it resolves to the Free fallback plan — never skip the check.
//   - It does not tell you whether a plan simply has no row for a feature.
//     A missing tblTierFeatures row falls back to the registry default, which
//     is "off" / the seeded number for every LinksPage feature, and that is
//     answered silently (it is a normal "not on this plan", not a fault).
//   - It does not know about the legacy has* / max* columns. For those, keep
//     using g2ml_canUseFeature() / g2ml_checkLimit().
// ============================================================================

/**
 * Work out every registry feature value for an org, WITHOUT the request cache
 * (the cache wrapper is _g2ml_resolveFeatureValues() below).
 *
 * Returns one of three shapes:
 *   - ['unlimited' => true,  'features' => []]
 *       The platform '[default]' org or a GlobalAdmin acting context. Never
 *       gated, as for the legacy features.
 *   - ['unlimited' => false, 'features' => ['linkspage.seo' => true, ...]]
 *       A normal org. Every ACTIVE registry feature is present, keyed by its
 *       registry name: a yes/no feature holds true or false, a limit holds an
 *       int, or null for unlimited.
 *   - false
 *       A system error: a failed database read, a missing pricing.php, or an
 *       unexpected exception. The caller decides what that means (the
 *       yes/no check denies, the limit check allows).
 *
 * @param  string $orgHandle  The org whose plan decides; '' for a page with no org.
 * @return array|false
 */
function _g2ml_resolveFeatureValuesUncached(string $orgHandle): array|false
{
    try
    {
        // -------------------------------------------------------------------
        // a. Start from the org's tier, so the never-gated and system-error
        //    rules are exactly the ones the legacy features use.
        // -------------------------------------------------------------------
        $tier = g2ml_getOrgTier($orgHandle);

        $tierSource = '';

        if (isset($tier['source']))
        {
            $tierSource = (string) $tier['source'];
        }

        if (($tier['unlimited'] ?? false) === true)
        {
            // ---------------------------------------------------------------
            // b. '[default]' and GlobalAdmin: never gated.
            // ---------------------------------------------------------------
            if ($tierSource === 'default_org' || $tierSource === 'globaladmin')
            {
                return [
                    'unlimited' => true,
                    'features'  => [],
                ];
            }

            // ---------------------------------------------------------------
            // c. Every other "unlimited" answer from g2ml_getOrgTier() is its
            //    fail-OPEN sentinel for a system error (source 'fail_open_…').
            //    For the legacy features that sentinel means "allow". Here it
            //    must NOT: it would give every paid extra to every free org
            //    for as long as the database was unwell. So it is reported
            //    as a system error. An unlimited answer with any source we do
            //    not recognise is treated the same way (the safe direction)
            //    and logged, so a future new source is noticed.
            // ---------------------------------------------------------------
            if (str_starts_with($tierSource, 'fail_open') === false)
            {
                error_log(
                    '[Go2My.Link] ERROR: entitlements — feature gate got an unrecognised unlimited tier source "'
                    . $tierSource . '" for org "' . $orgHandle . '" — treating it as a system error.'
                );
            }

            return false;
        }

        // -------------------------------------------------------------------
        // d. Engine ON and it resolved: g2ml_getOrgTier() already carries
        //    every registry value. Use it as it is.
        // -------------------------------------------------------------------
        if (isset($tier['features']) && is_array($tier['features']))
        {
            return [
                'unlimited' => false,
                'features'  => $tier['features'],
            ];
        }

        // -------------------------------------------------------------------
        // e. Engine OFF (or ON but its resolution failed, in which case
        //    g2ml_getOrgTier() fell back to the legacy columns and has no
        //    'features' list): ask the registry resolver directly. It does
        //    not check the master switch and uses the same merge rules.
        // -------------------------------------------------------------------
        if (!function_exists('g2ml_pricingResolveOrgTier'))
        {
            error_log(
                '[Go2My.Link] ERROR: entitlements — web/_functions/pricing.php is not loaded, so the feature registry cannot be read for org "'
                . $orgHandle . '" — treating it as a system error.'
            );

            return false;
        }

        $resolvedTier = g2ml_pricingResolveOrgTier($orgHandle);

        if (!is_array($resolvedTier)
            || !isset($resolvedTier['features'])
            || !is_array($resolvedTier['features']))
        {
            // g2ml_pricingResolveOrgTier() has already written its own
            // error_log line saying which lookup failed.
            return false;
        }

        return [
            'unlimited' => false,
            'features'  => $resolvedTier['features'],
        ];
    }
    catch (Throwable $unexpectedError)
    {
        error_log(
            '[Go2My.Link] ERROR: entitlements — unexpected exception reading the feature registry for org "'
            . $orgHandle . '": ' . $unexpectedError->getMessage() . ' — treating it as a system error.'
        );

        return false;
    }
}

/**
 * Work out every registry feature value for an org — request-cached per org.
 *
 * The cache lives in $GLOBALS['g2ml_feature_values_cache'][$orgHandle], and
 * g2ml_clearOrgTierCache() clears it together with the tier cache. A system
 * error (false) is cached too, deliberately: g2ml_getOrgTier() already caches
 * its own fail-open sentinel for the rest of the request, so retrying here
 * could not succeed, and it would repeat the same failed queries and log
 * lines for every feature checked on the page.
 *
 * @param  string $orgHandle
 * @return array|false  See _g2ml_resolveFeatureValuesUncached().
 */
function _g2ml_resolveFeatureValues(string $orgHandle): array|false
{
    if (!isset($GLOBALS['g2ml_feature_values_cache'])
        || !is_array($GLOBALS['g2ml_feature_values_cache']))
    {
        $GLOBALS['g2ml_feature_values_cache'] = [];
    }

    if (array_key_exists($orgHandle, $GLOBALS['g2ml_feature_values_cache']))
    {
        return $GLOBALS['g2ml_feature_values_cache'][$orgHandle];
    }

    $featureValues = _g2ml_resolveFeatureValuesUncached($orgHandle);

    $GLOBALS['g2ml_feature_values_cache'][$orgHandle] = $featureValues;

    return $featureValues;
}

/**
 * May this org use this yes/no feature? — for NEW features in the registry.
 *
 * Answers from the org's plan row in tblTierFeatures (merged with the
 * registry default and any per-org override), identically whether the
 * pricing engine's master switch is off or on.
 *
 * FAILS CLOSED. Each of these answers false:
 *   - a system error working out the org's features (one error_log line
 *     naming the feature and the org);
 *   - a feature name that is not in tblFeatures, or is switched off there
 *     (isActive = 0) — almost always a misspelt name (one error_log line);
 *   - a name that belongs to a limit rather than a yes/no feature (one
 *     error_log line — use g2ml_featureLimit() for those);
 *   - a plan with no row for the feature: the registry default applies,
 *     which is "off" for every LinksPage feature (no log line; this is an
 *     ordinary "not on your plan").
 * This is deliberately the opposite of g2ml_canUseFeature()'s fail-open rule.
 * Every feature behind this function is an optional extra on a page that
 * still works without it, so a "no" can only ever hide a paid extra; it can
 * never block creating a link, a redirect or a login.
 *
 * ALWAYS ALLOWED: the '[default]' org and a GlobalAdmin acting context, as
 * for the legacy features. (For them an unknown name is also allowed and not
 * logged — the registry is not read at all for them.)
 *
 * @param  string $orgHandle    The org whose plan decides. For a LinksPage,
 *                              the PAGE's orgHandle; '' if that is NULL.
 * @param  string $featureSlug  The registry name, e.g. 'linkspage.hide_branding'.
 * @return bool
 *
 * Usage example:
 *   if (g2ml_featureAllowed($pageOrgHandle, 'linkspage.hide_branding'))
 *   {
 *       // leave the branding line out
 *   }
 */
function g2ml_featureAllowed(string $orgHandle, string $featureSlug): bool
{
    $featureValues = _g2ml_resolveFeatureValues($orgHandle);

    if ($featureValues === false)
    {
        error_log(
            '[Go2My.Link] ERROR: entitlements — g2ml_featureAllowed() could not work out the features for org "'
            . $orgHandle . '" while checking "' . $featureSlug
            . '" (a system error; see the earlier log line for the cause) — denying. Fail closed: this can only hide an optional extra.'
        );

        return false;
    }

    if ($featureValues['unlimited'] === true)
    {
        return true;
    }

    if (!array_key_exists($featureSlug, $featureValues['features']))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_featureAllowed() called with an unrecognised or inactive feature slug "'
            . $featureSlug . '" for org "' . $orgHandle
            . '" — denying. Check the name against tblFeatures (web/_sql/seeds/023_linkspage_feature_registry.sql).'
        );

        return false;
    }

    $featureValue = $featureValues['features'][$featureSlug];

    if (!is_bool($featureValue))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_featureAllowed() called with "' . $featureSlug
            . '", which is not a yes/no feature (use g2ml_featureLimit() for limits), for org "' . $orgHandle . '" — denying.'
        );

        return false;
    }

    if ($featureValue === true)
    {
        return true;
    }

    return false;
}

/**
 * Check a numeric limit for this org — for NEW features in the registry.
 *
 * Same result shape as g2ml_checkLimit():
 *   ['allowed' => bool, 'limit' => int|null, 'remaining' => int|null]
 * where 'limit' null means unlimited. Answers identically whether the pricing
 * engine's master switch is off or on.
 *
 * To READ a limit rather than test a count against it (for example how many
 * days of statistics this plan may see), call it with a count of 0 and use
 * ['limit'], treating null as unlimited:
 *   $retentionDays = g2ml_featureLimit($org, 'linkspage.analytics_retention_days', 0)['limit'];
 *
 * FAILS OPEN (allowed, unlimited), exactly like g2ml_checkLimit(), for: a
 * system error, a name not in the registry (or switched off there), a name
 * that is not a limit, and an unlimited value. A limit only ever blocks a
 * create-type action, and the house rule (#146) is that a fault in the
 * entitlement system must never block a legitimate action. The first three
 * of those also write one error_log line so the fault is visible. Callers
 * that use ['limit'] as a WINDOW (statistics history) must therefore treat
 * null as "show everything", never as "show nothing".
 *
 * ALWAYS UNLIMITED: the '[default]' org and a GlobalAdmin acting context.
 *
 * @param  string $orgHandle     The org whose plan decides; '' for a page with no org.
 * @param  string $featureSlug   The registry name, e.g. 'linkspage.analytics_retention_days'.
 * @param  int    $currentCount  The caller's current usage count (negative
 *                               values are treated as 0).
 * @return array  ['allowed' => bool, 'limit' => int|null, 'remaining' => int|null]
 */
function g2ml_featureLimit(string $orgHandle, string $featureSlug, int $currentCount): array
{
    $unlimitedResult = [
        'allowed'   => true,
        'limit'     => null,
        'remaining' => null,
    ];

    $featureValues = _g2ml_resolveFeatureValues($orgHandle);

    if ($featureValues === false)
    {
        error_log(
            '[Go2My.Link] ERROR: entitlements — g2ml_featureLimit() could not work out the features for org "'
            . $orgHandle . '" while checking "' . $featureSlug
            . '" (a system error; see the earlier log line for the cause) — failing OPEN (allowed, unlimited).'
        );

        return $unlimitedResult;
    }

    if ($featureValues['unlimited'] === true)
    {
        return $unlimitedResult;
    }

    if (!array_key_exists($featureSlug, $featureValues['features']))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_featureLimit() called with an unrecognised or inactive feature slug "'
            . $featureSlug . '" for org "' . $orgHandle
            . '" — failing OPEN (allowed, unlimited). Check the name against tblFeatures (web/_sql/seeds/023_linkspage_feature_registry.sql).'
        );

        return $unlimitedResult;
    }

    $limitValue = $featureValues['features'][$featureSlug];

    // null is how the registry resolver reports "unlimited" (isUnlimited = 1).
    if ($limitValue === null)
    {
        return $unlimitedResult;
    }

    if (!is_int($limitValue))
    {
        error_log(
            '[Go2My.Link] WARNING: entitlements — g2ml_featureLimit() called with "' . $featureSlug
            . '", which is not a limit (use g2ml_featureAllowed() for yes/no features), for org "' . $orgHandle
            . '" — failing OPEN (allowed, unlimited).'
        );

        return $unlimitedResult;
    }

    // The comparison below is the same as g2ml_checkLimit()'s, on purpose, so
    // the two limit checks can never disagree about where the line falls.
    $limitValueInt = $limitValue;

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
