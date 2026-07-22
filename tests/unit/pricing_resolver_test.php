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
 * 🧪 Unit tests — flexible pricing/entitlement engine resolver (scaffold)
 * ============================================================================
 *
 * Pure, DB-free tests for web/_functions/pricing.php AND the guarded hook it
 * adds to web/_functions/entitlements.php's _g2ml_resolveOrgTier(). Every case
 * that would otherwise touch the database drives the lookup-override seams:
 *
 *   - $GLOBALS['g2ml_entitlements_tier_lookup_override'] /
 *     ['g2ml_entitlements_fallback_lookup_override']  (already defined by
 *     entitlements.php — see tests/unit/entitlements_test.php)
 *   - $GLOBALS['g2ml_pricing_features_override'] /
 *     ['g2ml_pricing_tier_features_override'] /
 *     ['g2ml_pricing_org_overrides_override']          (defined by pricing.php)
 *
 * Because every override intercepts BEFORE any real DB call, this file never
 * requires db_connect.php/db_query.php and never needs a live database.
 *
 * ⚠️ settings.php is DELIBERATELY NOT required by this file (mirroring
 * tests/unit/geolocation_test.php's own callout) and this file never defines
 * a global getSetting() — doing so would leak into every OTHER unit test file
 * sharing this single PHP process (tests/run.php requires every tests/unit/
 * file into one execution) and was found, during development of this file, to
 * break tests/unit/security_ssrf_host_guard_test.php's
 * "settings unavailable (fail closed)" case, which asserts
 * function_exists('getSetting') === false. Instead, pricing.php's
 * _g2ml_pricingReadSetting() provides a dedicated test-only override seam
 * ($GLOBALS['g2ml_pricing_setting_override']), driven here by
 * $GLOBALS['g2ml_pr_unit_settings'], so g2ml_pricingEngineEnabled() and
 * g2ml_pricingMeterUsage() can be tested deterministically without touching
 * the global function table at all.
 *
 * The one path this file CANNOT cover is a genuine tblUsageCounters/
 * tblUsageEvents DB write from g2ml_pricingMeterUsage() — that requires a real
 * (or DB-overridden) connection and is left to integration testing, matching
 * this codebase's convention that DB-touching behaviour is proven against a
 * real schema instead of being faked (see tests/unit/entitlements_test.php's
 * own header comment). What IS proven here is that a metering attempt with no
 * DB layer loaded degrades gracefully (returns false, never throws).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.6.0 — Pricing Engine phase (scaffold)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/entitlements.php';
require_once dirname(__DIR__, 2) . '/web/_functions/pricing.php';

// ============================================================================
// 🧰 Settings override — installs $GLOBALS['g2ml_pricing_setting_override']
// (pricing.php's own test seam) backed by $GLOBALS['g2ml_pr_unit_settings'].
// See header note above for why this file never defines getSetting() itself.
// ============================================================================
$GLOBALS['g2ml_pricing_setting_override'] = function (string $settingID, mixed $default): mixed
{
    if (isset($GLOBALS['g2ml_pr_unit_settings'])
        && is_array($GLOBALS['g2ml_pr_unit_settings'])
        && array_key_exists($settingID, $GLOBALS['g2ml_pr_unit_settings']))
    {
        return $GLOBALS['g2ml_pr_unit_settings'][$settingID];
    }

    return $default;
};

// ============================================================================
// 🔧 Test helpers — reset, fixture builders, engine/metering toggles
// ============================================================================

/**
 * Reset all pricing + entitlements test state between cases.
 *
 * @return void
 */
function g2ml_pr_unit_reset(): void
{
    g2ml_clearOrgTierCache();
    g2ml_clearPricingCache();
    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    unset($GLOBALS['g2ml_entitlements_fallback_lookup_override']);
    unset($GLOBALS['g2ml_pricing_features_override']);
    unset($GLOBALS['g2ml_pricing_tier_features_override']);
    unset($GLOBALS['g2ml_pricing_org_overrides_override']);
    unset($GLOBALS['g2ml_pr_unit_settings']);
    unset($GLOBALS['g2ml_pricing_engine_enabled_cache']);
}

/**
 * Force the master pricing-engine switch on/off for the next resolution,
 * bypassing the request cache so the change takes effect immediately.
 *
 * @param  bool $enabled
 * @return void
 */
function g2ml_pr_unit_force_engine(bool $enabled): void
{
    if (!isset($GLOBALS['g2ml_pr_unit_settings']) || !is_array($GLOBALS['g2ml_pr_unit_settings']))
    {
        $GLOBALS['g2ml_pr_unit_settings'] = [];
    }

    if ($enabled === true)
    {
        $GLOBALS['g2ml_pr_unit_settings']['billing.pricing_engine_enabled'] = '1';
    }
    else
    {
        $GLOBALS['g2ml_pr_unit_settings']['billing.pricing_engine_enabled'] = '0';
    }

    unset($GLOBALS['g2ml_pricing_engine_enabled_cache']);
}

/**
 * Force the usage-metering switch on/off.
 *
 * @param  bool $enabled
 * @return void
 */
function g2ml_pr_unit_force_metering(bool $enabled): void
{
    if (!isset($GLOBALS['g2ml_pr_unit_settings']) || !is_array($GLOBALS['g2ml_pr_unit_settings']))
    {
        $GLOBALS['g2ml_pr_unit_settings'] = [];
    }

    if ($enabled === true)
    {
        $GLOBALS['g2ml_pr_unit_settings']['billing.usage_metering_enabled'] = '1';
    }
    else
    {
        $GLOBALS['g2ml_pr_unit_settings']['billing.usage_metering_enabled'] = '0';
    }
}

/**
 * Build one tblFeatures-shaped row.
 *
 * @param  int    $featureUID
 * @param  string $slug
 * @param  string $valueType
 * @param  array  $overrides
 * @return array
 */
function g2ml_pr_unit_feature_row(int $featureUID, string $slug, string $valueType, array $overrides = []): array
{
    $defaults = [
        'featureUID'          => $featureUID,
        'featureSlug'         => $slug,
        'featureName'         => $slug,
        'valueType'           => $valueType,
        'valueUnit'           => null,
        'quotaPeriod'         => null,
        'defaultValueBoolean' => 0,
        'defaultValueInt'     => 0,
        'defaultValueString'  => null,
        'defaultValueJSON'    => null,
        'defaultIsUnlimited'  => 0,
        'category'            => 'general',
        'isMeterable'         => 0,
        'legacyColumn'        => null,
        'sortOrder'           => 0,
    ];

    return array_merge($defaults, $overrides);
}

/**
 * The fixture feature registry used throughout this file — mirrors
 * web/_sql/seeds/018_pricing_feature_registry.sql's legacy-mapped rows plus
 * one granular non-legacy feature (redirects.geo) for the umbrella-rule test.
 *
 * @return array
 */
function g2ml_pr_unit_features(): array
{
    return [
        g2ml_pr_unit_feature_row(1, 'links.max', 'limit', ['legacyColumn' => 'maxLinks', 'isMeterable' => 1]),
        g2ml_pr_unit_feature_row(2, 'domains.custom_max', 'limit', ['legacyColumn' => 'maxCustomDomains']),
        g2ml_pr_unit_feature_row(3, 'api.requests_per_day', 'quota', ['legacyColumn' => 'maxAPIRequestsPerDay', 'isMeterable' => 1]),
        g2ml_pr_unit_feature_row(4, 'linkspage.pages_max', 'limit', ['legacyColumn' => 'maxLinksPages']),
        g2ml_pr_unit_feature_row(5, 'redirects.advanced', 'boolean', ['legacyColumn' => 'hasAdvancedRedirects']),
        g2ml_pr_unit_feature_row(6, 'analytics.enabled', 'boolean', ['legacyColumn' => 'hasAnalytics']),
        g2ml_pr_unit_feature_row(7, 'qr.dynamic', 'boolean', ['legacyColumn' => 'hasQRCodes']),
        g2ml_pr_unit_feature_row(8, 'api.access', 'boolean', ['legacyColumn' => 'hasAPIAccess']),
        g2ml_pr_unit_feature_row(9, 'support.priority', 'boolean', ['legacyColumn' => 'hasPrioritySupport']),
        g2ml_pr_unit_feature_row(10, 'linkspage.custom_html', 'boolean', ['legacyColumn' => 'hasCustomHTML']),
        g2ml_pr_unit_feature_row(11, 'redirects.geo', 'boolean', []),
    ];
}

/**
 * Build one tblTierFeatures-shaped row.
 *
 * @param  int   $featureUID
 * @param  array $overrides
 * @return array
 */
function g2ml_pr_unit_tier_feature_row(int $featureUID, array $overrides = []): array
{
    $defaults = [
        'tierFeatureUID' => 1,
        'tierID'         => 'pr-unit-tier',
        'featureUID'     => $featureUID,
        'valueBoolean'   => null,
        'valueInt'       => null,
        'valueString'    => null,
        'valueJSON'      => null,
        'isUnlimited'    => 0,
        'effectiveFrom'  => null,
        'effectiveUntil' => null,
    ];

    return array_merge($defaults, $overrides);
}

/**
 * Build one tblOrgFeatureOverrides-shaped row.
 *
 * @param  int    $overrideUID
 * @param  int    $featureUID
 * @param  string $mode
 * @param  array  $overrides
 * @return array
 */
function g2ml_pr_unit_org_override_row(int $overrideUID, int $featureUID, string $mode, array $overrides = []): array
{
    $defaults = [
        'overrideUID'    => $overrideUID,
        'orgHandle'      => 'pr-unit-org',
        'featureUID'     => $featureUID,
        'overrideMode'   => $mode,
        'valueBoolean'   => null,
        'valueInt'       => null,
        'valueString'    => null,
        'valueJSON'      => null,
        'isUnlimited'    => 0,
        'adjustDelta'    => null,
        'effectiveFrom'  => null,
        'effectiveUntil' => null,
    ];

    return array_merge($defaults, $overrides);
}

/**
 * Build one legacy tblSubscriptionTiers-shaped row, as consumed by
 * entitlements.php's _g2ml_fetchOrgTierRow()/_g2ml_fetchFallbackTierRow().
 *
 * @param  array $overrides
 * @return array
 */
function g2ml_pr_unit_legacy_tier_row(array $overrides = []): array
{
    $defaults = [
        'tierID'               => 'pr-unit-tier',
        'tierName'             => 'Pricing Unit Test Tier',
        'maxLinks'             => 10,
        'maxCustomDomains'     => 2,
        'maxAPIRequestsPerDay' => 1000,
        'maxLinksPages'        => 3,
        'hasAdvancedRedirects' => 0,
        'hasAnalytics'         => 1,
        'hasQRCodes'           => 1,
        'hasAPIAccess'         => 0,
        'hasPrioritySupport'   => 0,
        'hasCustomHTML'        => 0,
        'isActive'             => 1,
    ];

    return array_merge($defaults, $overrides);
}

/**
 * Install the three pricing-fetch overrides in one call for convenience.
 *
 * @param  array $features
 * @param  array $tierFeatures
 * @param  array $orgOverrides
 * @return void
 */
function g2ml_pr_unit_install_pricing_overrides(array $features, array $tierFeatures, array $orgOverrides): void
{
    $GLOBALS['g2ml_pricing_features_override'] = function () use ($features): array
    {
        return $features;
    };

    $GLOBALS['g2ml_pricing_tier_features_override'] = function (string $tierID) use ($tierFeatures): array
    {
        return $tierFeatures;
    };

    $GLOBALS['g2ml_pricing_org_overrides_override'] = function (string $orgHandle) use ($orgOverrides): array
    {
        return $orgOverrides;
    };
}

// ============================================================================
// 🔌 g2ml_pricingEngineEnabled() — reads getSetting(), request-cached
// ============================================================================

test('pricingEngineEnabled: reflects the master switch and is request-cached', function (): void
{
    g2ml_pr_unit_reset();

    g2ml_pr_unit_force_engine(false);
    assert_false(g2ml_pricingEngineEnabled(), 'OFF (0) reports disabled');

    g2ml_pr_unit_force_engine(true);
    assert_true(g2ml_pricingEngineEnabled(), 'ON (1) reports enabled');

    // Change the underlying setting WITHOUT clearing the cache — the cached
    // value must still be returned.
    $GLOBALS['g2ml_pr_unit_settings']['billing.pricing_engine_enabled'] = '0';
    assert_true(g2ml_pricingEngineEnabled(), 'Request-cached: a later setting change is not seen until the cache is cleared');

    g2ml_clearPricingCache();
    assert_false(g2ml_pricingEngineEnabled(), 'After clearing the cache, the new (now-disabled) value is seen');
});

// ============================================================================
// 🚫 Hook OFF — entitlements.php ignores pricing.php entirely
// ============================================================================

test('hook OFF: g2ml_getOrgTier() resolves via the legacy path, unaffected by pricing.php being loaded', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(false);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row(['tierID' => 'legacy-only']);
    };

    $tier = g2ml_getOrgTier('org-hook-off');

    assert_same('tier', $tier['source'], 'Source is the legacy tier path, not pricing_engine');
    assert_same(10, $tier['maxLinks'], 'Legacy maxLinks value is used, untouched by pricing.php');
    assert_same('legacy-only', $tier['tierID']);
});

// ============================================================================
// 🧩 g2ml_pricingResolveOrgTier() is flag-agnostic by design — only the
// entitlements.php hook and the granular canUse()/getLimit() helpers gate on
// the master switch; calling the resolver directly always attempts a full
// resolution (this is what canUse()/getLimit() rely on once THEY have
// already checked the flag themselves).
// ============================================================================

test('pricingResolveOrgTier: does not itself gate on the engine flag', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(false);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row(['tierID' => 'engine-agnostic']);
    };

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), [], []);

    $resolved = g2ml_pricingResolveOrgTier('org-direct-call');

    assert_true(is_array($resolved), 'A direct call still resolves even though the engine flag is OFF');
    assert_same('pricing_engine', $resolved['source']);
});

// ============================================================================
// 🎯 Parity — legacy-shaped output matches the tier's configured values
// ============================================================================

test('pricingResolveOrgTier: legacy-shaped fields match the tier fixture through the merge (default -> tier)', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row(['tierID' => 'parity-tier']);
    };

    $tierFeatures = [
        g2ml_pr_unit_tier_feature_row(1, ['valueInt' => 10]),
        g2ml_pr_unit_tier_feature_row(2, ['valueInt' => 2]),
        g2ml_pr_unit_tier_feature_row(3, ['valueInt' => 1000]),
        g2ml_pr_unit_tier_feature_row(4, ['valueInt' => 3]),
        g2ml_pr_unit_tier_feature_row(5, ['valueBoolean' => 0]),
        g2ml_pr_unit_tier_feature_row(6, ['valueBoolean' => 1]),
        g2ml_pr_unit_tier_feature_row(7, ['valueBoolean' => 1]),
        g2ml_pr_unit_tier_feature_row(8, ['valueBoolean' => 0]),
        g2ml_pr_unit_tier_feature_row(9, ['valueBoolean' => 0]),
        g2ml_pr_unit_tier_feature_row(10, ['valueBoolean' => 0]),
    ];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    $resolved = g2ml_pricingResolveOrgTier('parity-org');

    assert_same(10, $resolved['maxLinks']);
    assert_same(2, $resolved['maxCustomDomains']);
    assert_same(1000, $resolved['maxAPIRequestsPerDay']);
    assert_same(3, $resolved['maxLinksPages']);
    assert_false($resolved['hasAdvancedRedirects']);
    assert_true($resolved['hasAnalytics']);
    assert_true($resolved['hasQRCodes']);
    assert_false($resolved['hasAPIAccess']);
    assert_false($resolved['hasPrioritySupport']);
    assert_false($resolved['hasCustomHTML']);
    assert_false($resolved['unlimited']);
    assert_same('pricing_engine', $resolved['source']);
    assert_false($resolved['features']['redirects.geo'], 'A feature with no tier row falls back to its safe-floor default (false)');
});

// ============================================================================
// 🔀 Merge order
// ============================================================================

test('merge order: the safe-floor default applies when the tier has no row for a feature', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), [], []);

    $resolved = g2ml_pricingResolveOrgTier('org-default-floor');

    assert_same(0, $resolved['maxLinks'], 'links.max default (0) is used when the tier has no tblTierFeatures row');
    assert_same(0, $resolved['features']['links.max']);
});

test('merge order: the tier value beats the default', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [g2ml_pr_unit_tier_feature_row(1, ['valueInt' => 500])];
    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    $resolved = g2ml_pricingResolveOrgTier('org-tier-beats-default');

    assert_same(500, $resolved['maxLinks']);
});

test('merge order: deny beats a tier grant', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [g2ml_pr_unit_tier_feature_row(6, ['valueBoolean' => 1])];
    $orgOverrides = [g2ml_pr_unit_org_override_row(1, 6, 'deny')];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, $orgOverrides);

    $resolved = g2ml_pricingResolveOrgTier('org-deny-beats-grant');

    assert_false($resolved['hasAnalytics'], 'A deny override forces the feature off even though the tier grants it');
    assert_false($resolved['features']['analytics.enabled']);
});

test('merge order: set replaces the value and isUnlimited', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [
        g2ml_pr_unit_tier_feature_row(1, ['valueInt' => 10]),
        g2ml_pr_unit_tier_feature_row(2, ['valueInt' => 5]),
    ];
    $orgOverrides = [
        g2ml_pr_unit_org_override_row(1, 1, 'set', ['valueInt' => 100000]),
        g2ml_pr_unit_org_override_row(2, 2, 'set', ['isUnlimited' => 1]),
    ];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, $orgOverrides);

    $resolved = g2ml_pricingResolveOrgTier('org-set-replaces');

    assert_same(100000, $resolved['maxLinks'], 'set replaces the tier value outright');
    assert_same(null, $resolved['maxCustomDomains'], 'set with isUnlimited=1 maps to legacy NULL (unlimited)');
});

test('merge order: two adjust rows accumulate, and adjust floors at 0', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [g2ml_pr_unit_tier_feature_row(2, ['valueInt' => 5])];
    $orgOverrides = [
        g2ml_pr_unit_org_override_row(1, 2, 'adjust', ['adjustDelta' => 5]),
        g2ml_pr_unit_org_override_row(2, 2, 'adjust', ['adjustDelta' => 5]),
    ];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, $orgOverrides);

    $resolved = g2ml_pricingResolveOrgTier('org-adjust-accumulate');

    assert_same(15, $resolved['maxCustomDomains'], 'Two +5 adjust rows accumulate on top of the tier value of 5');

    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeaturesFloor = [g2ml_pr_unit_tier_feature_row(2, ['valueInt' => 3])];
    $orgOverridesFloor = [g2ml_pr_unit_org_override_row(1, 2, 'adjust', ['adjustDelta' => -10])];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeaturesFloor, $orgOverridesFloor);

    $resolvedFloor = g2ml_pricingResolveOrgTier('org-adjust-floor');

    assert_same(0, $resolvedFloor['maxCustomDomains'], 'A large negative adjust never goes below 0');
});

// ============================================================================
// 📅 Effective-dating: the latest-effectiveFrom-wins reduction
// ============================================================================

test('reduceLatestEffective: a dated row always beats an undated one, and the latest date wins among dated rows', function (): void
{
    $rows = [
        ['featureUID' => 1, 'effectiveFrom' => null, 'value' => 'undated'],
        ['featureUID' => 1, 'effectiveFrom' => '2026-01-01 00:00:00', 'value' => 'january'],
        ['featureUID' => 1, 'effectiveFrom' => '2026-06-01 00:00:00', 'value' => 'june'],
    ];

    $winners = _g2ml_pricingReduceLatestEffective($rows);

    assert_same('june', $winners['1']['value'], 'The latest-dated row wins over both the undated row and the earlier-dated row');

    $rowsReordered = [
        ['featureUID' => 2, 'effectiveFrom' => '2026-06-01 00:00:00', 'value' => 'june'],
        ['featureUID' => 2, 'effectiveFrom' => null, 'value' => 'undated'],
    ];

    $winnersReordered = _g2ml_pricingReduceLatestEffective($rowsReordered);

    assert_same('june', $winnersReordered['2']['value'], 'An undated row arriving AFTER a dated row still never displaces it');
});

// ============================================================================
// ♾️ Unlimited mapping flows through entitlements.php's g2ml_checkLimit()
// ============================================================================

test('unlimited mapping: isUnlimited on a tier row maps to legacy NULL, and g2ml_checkLimit() reports unlimited', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row(['tierID' => 'unlimited-parity']);
    };

    $tierFeatures = [g2ml_pr_unit_tier_feature_row(1, ['isUnlimited' => 1, 'valueInt' => null])];
    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    $tier = g2ml_getOrgTier('unlimited-org');

    assert_same(null, $tier['maxLinks'], 'isUnlimited=1 maps to legacy NULL');
    assert_same('pricing_engine', $tier['source'], 'Precondition: the engine hook actually fired');

    $check = g2ml_checkLimit('unlimited-org', 'maxLinks', 999999999);

    assert_true($check['allowed'], 'g2ml_checkLimit() reads the pricing-engine-produced NULL as unlimited');
    assert_same(null, $check['limit']);
});

// ============================================================================
// ☂️ Umbrella rule
// ============================================================================

test('umbrella rule: any granular redirects.* flag true forces hasAdvancedRedirects true', function (): void
{
    g2ml_pr_unit_reset();

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [
        g2ml_pr_unit_tier_feature_row(5, ['valueBoolean' => 0]),
        g2ml_pr_unit_tier_feature_row(11, ['valueBoolean' => 1]),
    ];

    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    $resolved = g2ml_pricingResolveOrgTier('org-umbrella');

    assert_true($resolved['hasAdvancedRedirects'], 'The granular redirects.geo=true forces the umbrella flag true even though the umbrella row itself is off');
    assert_true($resolved['features']['redirects.geo']);
});

// ============================================================================
// 🛡️ Fail-open
// ============================================================================

test('fail-open: a pricing-fetch failure falls back to the legacy path via the entitlements.php hook', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row(['tierID' => 'fail-open-features']);
    };

    $GLOBALS['g2ml_pricing_features_override'] = function (): bool
    {
        return false;
    };

    $tier = g2ml_getOrgTier('fail-open-org');

    assert_same('tier', $tier['source'], 'The pricing engine failed internally, so entitlements.php fell back to the legacy tier resolution');
    assert_same(10, $tier['maxLinks']);
});

test('fail-open: a shared org/tier lookup failure yields the unlimited sentinel regardless of the engine flag', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): bool
    {
        return false;
    };

    $tier = g2ml_getOrgTier('outage-org');

    assert_true($tier['unlimited'], 'A system failure resolving the org/tier row fails OPEN regardless of the pricing-engine flag');
    assert_same('fail_open_lookup_error', $tier['source']);
});

// ============================================================================
// 🚩 g2ml_pricingCanUse()
// ============================================================================

test('pricingCanUse: an unrecognised slug is denied; engine OFF denies even a real slug', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [
        g2ml_pr_unit_tier_feature_row(6, ['valueBoolean' => 1]),
        g2ml_pr_unit_tier_feature_row(8, ['valueBoolean' => 0]),
    ];
    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    assert_false(g2ml_pricingCanUse('org-can-use', 'not.a.real.slug'), 'An unrecognised slug is denied');
    assert_true(g2ml_pricingCanUse('org-can-use', 'analytics.enabled'), 'A granted feature reflects true');
    assert_false(g2ml_pricingCanUse('org-can-use', 'api.access'), 'A denied/default feature reflects false');

    g2ml_pr_unit_force_engine(false);
    assert_false(g2ml_pricingCanUse('org-can-use', 'analytics.enabled'), 'Engine OFF always denies, regardless of the underlying data');
});

test('pricingCanUse: a resolution failure denies (engine ON, resolver fails internally)', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $GLOBALS['g2ml_pricing_features_override'] = function (): bool
    {
        return false;
    };

    assert_false(g2ml_pricingCanUse('org-can-use-fail', 'analytics.enabled'));
});

// ============================================================================
// 🔢 g2ml_pricingGetLimit()
// ============================================================================

test('pricingGetLimit: an unrecognised slug fails OPEN; a real limit computes correctly; engine OFF fails OPEN', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $tierFeatures = [g2ml_pr_unit_tier_feature_row(1, ['valueInt' => 5])];
    g2ml_pr_unit_install_pricing_overrides(g2ml_pr_unit_features(), $tierFeatures, []);

    $unknown = g2ml_pricingGetLimit('org-get-limit', 'not.a.real.limit', 999999);
    assert_true($unknown['allowed'], 'An unrecognised slug fails OPEN');
    assert_same(null, $unknown['limit']);

    $under = g2ml_pricingGetLimit('org-get-limit', 'links.max', 3);
    assert_true($under['allowed']);
    assert_same(5, $under['limit']);
    assert_same(2, $under['remaining']);

    $over = g2ml_pricingGetLimit('org-get-limit', 'links.max', 10);
    assert_false($over['allowed']);
    assert_same(0, $over['remaining']);

    g2ml_pr_unit_force_engine(false);
    $engineOff = g2ml_pricingGetLimit('org-get-limit', 'links.max', 999999999);
    assert_true($engineOff['allowed'], 'Engine OFF fails OPEN regardless of the underlying data');
    assert_same(null, $engineOff['limit']);
});

test('pricingGetLimit: a resolution failure fails OPEN (engine ON, resolver fails internally)', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_engine(true);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array
    {
        return g2ml_pr_unit_legacy_tier_row();
    };

    $GLOBALS['g2ml_pricing_features_override'] = function (): bool
    {
        return false;
    };

    $result = g2ml_pricingGetLimit('org-get-limit-fail', 'links.max', 999999999);

    assert_true($result['allowed']);
    assert_same(null, $result['limit']);
});

// ============================================================================
// 📊 g2ml_pricingMeterUsage()
// ============================================================================

test('pricingMeterUsage: metering OFF is a no-op that returns true', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_metering(false);

    assert_true(g2ml_pricingMeterUsage('org-meter-off', 'links.max', 1));
});

test('pricingMeterUsage: metering ON with an unrecognised or non-meterable slug returns false', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_metering(true);

    $GLOBALS['g2ml_pricing_features_override'] = function (): array
    {
        return g2ml_pr_unit_features();
    };

    assert_false(g2ml_pricingMeterUsage('org-meter-unknown', 'not.a.real.slug'), 'An unrecognised feature slug is never metered');
    assert_false(g2ml_pricingMeterUsage('org-meter-non-meterable', 'support.priority'), 'A feature with isMeterable=0 is never metered');
});

test('pricingMeterUsage: metering ON for a real meterable feature degrades gracefully with no DB layer loaded (never throws)', function (): void
{
    g2ml_pr_unit_reset();
    g2ml_pr_unit_force_metering(true);

    $GLOBALS['g2ml_pricing_features_override'] = function (): array
    {
        return g2ml_pr_unit_features();
    };

    // db_query.php's dbInsert() is deliberately NOT loaded in this DB-free
    // unit file, so the attempt fails internally (caught by
    // g2ml_pricingMeterUsage()'s own try/catch) and returns false rather than
    // throwing — proving the fail-safe wrapper. The actual DB write is
    // covered by integration testing.
    assert_false(g2ml_pricingMeterUsage('org-meter-real', 'links.max', 1));
});
