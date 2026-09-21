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
 * 🧪 Unit tests — registry-driven feature gate (LP-01, #216)
 * ============================================================================
 *
 * Path: tests/unit/feature_gate_test.php
 *
 * Proves the two functions every new LinksPage feature is gated through —
 * g2ml_featureAllowed() and g2ml_featureLimit() in
 * web/_functions/entitlements.php — against fixed fixtures, with no database.
 *
 * THE CENTRAL CLAIM: for the same data, both functions give the SAME answer
 * whether the pricing engine's master switch (billing.pricing_engine_enabled)
 * reads as '0', '1', false or true. The string forms are what a test or a
 * default value produces; the boolean forms are what getSetting() really
 * returns for a 'boolean' setting. Each "identical" case below also checks
 * WHICH route g2ml_getOrgTier() took ('tier' with the switch off,
 * 'pricing_engine' with it on), so a pass cannot come from both runs quietly
 * taking the same route.
 *
 * Also covered: '[default]' and GlobalAdmin are never gated; a system error
 * denies a yes/no feature but allows a limit (and logs exactly one line
 * naming the feature); an unknown name denies and logs; a plan with no row
 * falls back silently to the registry default; a per-org 'deny' or 'grant'
 * override wins in both modes; an unlimited limit reports null; a feature of
 * the wrong type is refused; and g2ml_clearOrgTierCache() really clears the
 * new cache.
 *
 * HOW THE DATABASE IS AVOIDED. Every lookup goes through an existing test
 * seam that is checked BEFORE any real query:
 *   - $GLOBALS['g2ml_entitlements_tier_lookup_override']  (entitlements.php)
 *   - $GLOBALS['g2ml_pricing_features_override']          (pricing.php)
 *   - $GLOBALS['g2ml_pricing_tier_features_override']     (pricing.php)
 *   - $GLOBALS['g2ml_pricing_org_overrides_override']     (pricing.php)
 *   - $GLOBALS['g2ml_pricing_setting_override']           (pricing.php)
 *
 * ⚠️ SHARED-PROCESS CARE. tests/run.php loads EVERY unit file into one PHP
 * process first and only then runs the tests. tests/unit/pricing_resolver_test.php
 * installs its own $GLOBALS['g2ml_pricing_setting_override'] when it is
 * loaded, and its cases rely on that override still being in place when they
 * run (after these, alphabetically). So this file never installs its override
 * at load time: g2ml_fg_unit_run() installs it for the length of one case and
 * puts the previous one back afterwards, even if the case fails. For the same
 * reason this file never defines getSetting() (see that file's header for the
 * breakage that caused).
 *
 * WHAT THIS FILE CANNOT PROVE. A real GlobalAdmin session is database-backed,
 * so here the GlobalAdmin case plants the tier g2ml_getOrgTier() would return
 * for one; tests/integration/feature_gate_test.php logs in a real GlobalAdmin.
 * The real seeded plan values are likewise proven only by that integration
 * file.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      2026-09-21 — LinksPage programme, LP-01 (#216)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/entitlements.php';
require_once dirname(__DIR__, 2) . '/web/_functions/pricing.php';

// ============================================================================
// 🔧 Test helpers — isolation, switch control, fixtures, log capture
// ============================================================================

/**
 * Reset every piece of entitlement and pricing state these tests touch.
 * Deliberately does NOT touch $GLOBALS['g2ml_pricing_setting_override'] —
 * g2ml_fg_unit_run() owns that (see the header's shared-process note).
 *
 * @return void
 */
function g2ml_fg_unit_reset(): void
{
    g2ml_clearOrgTierCache();
    g2ml_clearPricingCache();
    unset($GLOBALS['g2ml_feature_values_cache']);
    unset($GLOBALS['g2ml_entitlements_org_tier_cache']);
    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    unset($GLOBALS['g2ml_entitlements_fallback_lookup_override']);
    unset($GLOBALS['g2ml_pricing_features_override']);
    unset($GLOBALS['g2ml_pricing_tier_features_override']);
    unset($GLOBALS['g2ml_pricing_org_overrides_override']);
    unset($GLOBALS['g2ml_pricing_engine_enabled_cache']);
    unset($GLOBALS['g2ml_fg_unit_settings']);
}

/**
 * Run one test body with this file's settings override installed, then put
 * back whatever override was there before — even when the body fails.
 *
 * @param  callable $body
 * @return void
 */
function g2ml_fg_unit_run(callable $body): void
{
    $hadPreviousOverride = array_key_exists('g2ml_pricing_setting_override', $GLOBALS);
    $previousOverride    = null;

    if ($hadPreviousOverride === true)
    {
        $previousOverride = $GLOBALS['g2ml_pricing_setting_override'];
    }

    g2ml_fg_unit_reset();

    $GLOBALS['g2ml_pricing_setting_override'] = function (string $settingID, mixed $default): mixed
    {
        if (isset($GLOBALS['g2ml_fg_unit_settings'])
            && is_array($GLOBALS['g2ml_fg_unit_settings'])
            && array_key_exists($settingID, $GLOBALS['g2ml_fg_unit_settings']))
        {
            return $GLOBALS['g2ml_fg_unit_settings'][$settingID];
        }

        return $default;
    };

    try
    {
        $body();
    }
    finally
    {
        g2ml_fg_unit_reset();

        if ($hadPreviousOverride === true)
        {
            $GLOBALS['g2ml_pricing_setting_override'] = $previousOverride;
        }
        else
        {
            unset($GLOBALS['g2ml_pricing_setting_override']);
        }
    }
}

/**
 * Set the master switch to an exact raw value ('0', '1', false or true) and
 * clear every cache so the next check really re-reads it.
 *
 * @param  mixed $rawValue
 * @return void
 */
function g2ml_fg_unit_set_switch(mixed $rawValue): void
{
    if (!isset($GLOBALS['g2ml_fg_unit_settings']) || !is_array($GLOBALS['g2ml_fg_unit_settings']))
    {
        $GLOBALS['g2ml_fg_unit_settings'] = [];
    }

    $GLOBALS['g2ml_fg_unit_settings']['billing.pricing_engine_enabled'] = $rawValue;

    unset($GLOBALS['g2ml_pricing_engine_enabled_cache']);
    g2ml_clearOrgTierCache();
}

/**
 * The four raw forms of the master switch every "identical" case runs under,
 * with the route g2ml_getOrgTier() must take for each.
 *
 * @return array<int, array{label: string, value: mixed, expectedSource: string}>
 */
function g2ml_fg_unit_switch_modes(): array
{
    return [
        ['label' => "switch '0'",   'value' => '0',   'expectedSource' => 'tier'],
        ['label' => "switch '1'",   'value' => '1',   'expectedSource' => 'pricing_engine'],
        ['label' => 'switch false', 'value' => false, 'expectedSource' => 'tier'],
        ['label' => 'switch true',  'value' => true,  'expectedSource' => 'pricing_engine'],
    ];
}

/**
 * Build one tblFeatures-shaped row (same shape as pricing_resolver_test.php).
 *
 * @param  int    $featureUID
 * @param  string $slug
 * @param  string $valueType
 * @param  array  $overrides
 * @return array
 */
function g2ml_fg_unit_feature_row(int $featureUID, string $slug, string $valueType, array $overrides = []): array
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
 * The fixture registry: the ten legacy-mapped rows (as in seed 018) plus two
 * LinksPage rows shaped like seed 023's — one yes/no feature and one limit.
 *
 * @return array
 */
function g2ml_fg_unit_features(): array
{
    return [
        g2ml_fg_unit_feature_row(1, 'links.max', 'limit', ['legacyColumn' => 'maxLinks']),
        g2ml_fg_unit_feature_row(2, 'domains.custom_max', 'limit', ['legacyColumn' => 'maxCustomDomains']),
        g2ml_fg_unit_feature_row(3, 'api.requests_per_day', 'quota', ['legacyColumn' => 'maxAPIRequestsPerDay']),
        g2ml_fg_unit_feature_row(4, 'linkspage.pages_max', 'limit', ['legacyColumn' => 'maxLinksPages']),
        g2ml_fg_unit_feature_row(5, 'redirects.advanced', 'boolean', ['legacyColumn' => 'hasAdvancedRedirects']),
        g2ml_fg_unit_feature_row(6, 'analytics.enabled', 'boolean', ['legacyColumn' => 'hasAnalytics']),
        g2ml_fg_unit_feature_row(7, 'qr.dynamic', 'boolean', ['legacyColumn' => 'hasQRCodes']),
        g2ml_fg_unit_feature_row(8, 'api.access', 'boolean', ['legacyColumn' => 'hasAPIAccess']),
        g2ml_fg_unit_feature_row(9, 'support.priority', 'boolean', ['legacyColumn' => 'hasPrioritySupport']),
        g2ml_fg_unit_feature_row(10, 'linkspage.custom_html', 'boolean', ['legacyColumn' => 'hasCustomHTML']),
        g2ml_fg_unit_feature_row(20, 'linkspage.hide_branding', 'boolean', ['category' => 'linkspage', 'defaultValueBoolean' => 0]),
        g2ml_fg_unit_feature_row(21, 'linkspage.analytics_retention_days', 'limit', ['category' => 'linkspage', 'valueUnit' => 'days', 'defaultValueInt' => 30]),
    ];
}

/**
 * Build one tblTierFeatures-shaped row.
 *
 * @param  string $tierID
 * @param  int    $featureUID
 * @param  array  $overrides
 * @return array
 */
function g2ml_fg_unit_tier_feature_row(string $tierID, int $featureUID, array $overrides = []): array
{
    $defaults = [
        'tierFeatureUID' => $featureUID,
        'tierID'         => $tierID,
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
 * The fixture plan rows. 'free' / 'basic' / 'enterprise' carry explicit rows
 * for both LinksPage features, like seed 023; 'bare' has NO rows at all, so
 * every feature falls back to its registry default.
 *
 * @param  string $tierID
 * @return array
 */
function g2ml_fg_unit_tier_features(string $tierID): array
{
    $legacyRows = [
        g2ml_fg_unit_tier_feature_row($tierID, 1, ['valueInt' => 10]),
        g2ml_fg_unit_tier_feature_row($tierID, 6, ['valueBoolean' => 1]),
    ];

    if ($tierID === 'free')
    {
        return array_merge($legacyRows, [
            g2ml_fg_unit_tier_feature_row($tierID, 20, ['valueBoolean' => 0]),
            g2ml_fg_unit_tier_feature_row($tierID, 21, ['valueInt' => 30]),
        ]);
    }

    if ($tierID === 'basic')
    {
        return array_merge($legacyRows, [
            g2ml_fg_unit_tier_feature_row($tierID, 20, ['valueBoolean' => 1]),
            g2ml_fg_unit_tier_feature_row($tierID, 21, ['valueInt' => 90]),
        ]);
    }

    if ($tierID === 'enterprise')
    {
        return array_merge($legacyRows, [
            g2ml_fg_unit_tier_feature_row($tierID, 20, ['valueBoolean' => 1]),
            g2ml_fg_unit_tier_feature_row($tierID, 21, ['valueInt' => null, 'isUnlimited' => 1]),
        ]);
    }

    return [];
}

/**
 * Build the legacy tblSubscriptionTiers-shaped row the org-to-tier lookup
 * returns for the fixture org.
 *
 * @param  string $tierID
 * @return array
 */
function g2ml_fg_unit_legacy_tier_row(string $tierID): array
{
    return [
        'tierID'               => $tierID,
        'tierName'             => 'Feature Gate Fixture ' . $tierID,
        'maxLinks'             => 10,
        'maxCustomDomains'     => 0,
        'maxAPIRequestsPerDay' => 100,
        'maxLinksPages'        => 1,
        'hasAdvancedRedirects' => 0,
        'hasAnalytics'         => 1,
        'hasQRCodes'           => 1,
        'hasAPIAccess'         => 0,
        'hasPrioritySupport'   => 0,
        'hasCustomHTML'        => 0,
        'isActive'             => 1,
    ];
}

/**
 * Point every lookup seam at the fixtures, with the org on the given plan.
 *
 * @param  string $tierID
 * @param  array  $orgOverrideRows  tblOrgFeatureOverrides-shaped rows.
 * @return void
 */
function g2ml_fg_unit_install_fixture(string $tierID, array $orgOverrideRows = []): void
{
    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle) use ($tierID): array
    {
        return g2ml_fg_unit_legacy_tier_row($tierID);
    };

    $GLOBALS['g2ml_pricing_features_override'] = function (): array
    {
        return g2ml_fg_unit_features();
    };

    $GLOBALS['g2ml_pricing_tier_features_override'] = function (string $requestedTierID): array
    {
        return g2ml_fg_unit_tier_features($requestedTierID);
    };

    $GLOBALS['g2ml_pricing_org_overrides_override'] = function (string $orgHandle) use ($orgOverrideRows): array
    {
        return $orgOverrideRows;
    };
}

/**
 * Build one tblOrgFeatureOverrides-shaped row.
 *
 * @param  int    $overrideUID
 * @param  int    $featureUID
 * @param  string $mode  deny | grant | set | adjust
 * @return array
 */
function g2ml_fg_unit_org_override_row(int $overrideUID, int $featureUID, string $mode): array
{
    return [
        'overrideUID'    => $overrideUID,
        'orgHandle'      => 'fg-unit-org',
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
}

/**
 * Run $body with error_log() pointed at a temporary file, and return the
 * lines it wrote. Used to prove "exactly one log line naming the feature".
 *
 * @param  callable $body
 * @return array<int, string>
 */
function g2ml_fg_unit_capture_log(callable $body): array
{
    $logFile = tempnam(sys_get_temp_dir(), 'g2ml_fg_');

    if ($logFile === false)
    {
        throw new RuntimeException('Could not create a temporary file to capture error_log output.');
    }

    $previousLogSetting = ini_get('error_log');

    if ($previousLogSetting === false)
    {
        $previousLogSetting = '';
    }

    ini_set('error_log', $logFile);

    try
    {
        $body();
    }
    finally
    {
        ini_set('error_log', $previousLogSetting);
    }

    $contents = file_get_contents($logFile);
    unlink($logFile);

    $lines = [];

    if ($contents === false)
    {
        return $lines;
    }

    foreach (explode("\n", $contents) as $line)
    {
        if (trim($line) !== '')
        {
            $lines[] = $line;
        }
    }

    return $lines;
}

/**
 * Count the captured log lines that mention a given piece of text.
 *
 * @param  array  $lines
 * @param  string $needle
 * @return int
 */
function g2ml_fg_unit_count_lines_containing(array $lines, string $needle): int
{
    $count = 0;

    foreach ($lines as $line)
    {
        if (str_contains($line, $needle))
        {
            $count = $count + 1;
        }
    }

    return $count;
}

// ============================================================================
// 🟰 The central claim — identical answers with the switch off and on
// ============================================================================

test('feature gate: a plan that HAS the feature gets identical answers with the switch \'0\', \'1\', false and true', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('basic');
            g2ml_fg_unit_set_switch($mode['value']);

            $tier = g2ml_getOrgTier('fg-unit-org');
            assert_same($mode['expectedSource'], $tier['source'], $mode['label'] . ': precondition — g2ml_getOrgTier() took the expected route');

            assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': Basic has hide_branding');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 0);
            assert_true($limit['allowed'], $mode['label'] . ': a count of 0 is within the limit');
            assert_same(90, $limit['limit'], $mode['label'] . ': Basic retention is 90 days');
            assert_same(90, $limit['remaining'], $mode['label'] . ': remaining is the whole limit at a count of 0');
        }
    });
});

test('feature gate: a plan WITHOUT the feature gets identical answers with the switch \'0\', \'1\', false and true', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('free');
            g2ml_fg_unit_set_switch($mode['value']);

            $tier = g2ml_getOrgTier('fg-unit-org');
            assert_same($mode['expectedSource'], $tier['source'], $mode['label'] . ': precondition — g2ml_getOrgTier() took the expected route');

            assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': Free does not have hide_branding');

            $under = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 10);
            assert_true($under['allowed'], $mode['label'] . ': 10 is under the Free limit of 30');
            assert_same(30, $under['limit'], $mode['label'] . ': Free retention is 30 days');
            assert_same(20, $under['remaining'], $mode['label'] . ': 20 remaining');

            $atLimit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 30);
            assert_false($atLimit['allowed'], $mode['label'] . ': a count equal to the limit is not allowed (same rule as g2ml_checkLimit())');
            assert_same(0, $atLimit['remaining'], $mode['label'] . ': nothing remaining at the limit');

            $negative = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', -5);
            assert_same(30, $negative['remaining'], $mode['label'] . ': a negative count is treated as 0');
        }
    });
});

test('feature gate: an unlimited limit (isUnlimited = 1) reports null in every switch mode', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('enterprise');
            g2ml_fg_unit_set_switch($mode['value']);

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 999999);

            assert_true($limit['allowed'], $mode['label'] . ': unlimited always allows');
            assert_same(null, $limit['limit'], $mode['label'] . ': unlimited is reported as a null limit');
            assert_same(null, $limit['remaining'], $mode['label'] . ': and a null remaining');
            assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': Enterprise has hide_branding');
        }
    });
});

// ============================================================================
// 🏠 Never gated — '[default]' and GlobalAdmin
// ============================================================================

test('feature gate: the [default] org is always allowed and unlimited, with no lookup at all', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            // Deliberately NO fixture installed. If the gate tried to read the
            // registry or the database for '[default]' it would hit the real
            // dbSelect(), which is not loaded here, and the case would fail.
            g2ml_fg_unit_set_switch($mode['value']);

            assert_true(g2ml_featureAllowed('[default]', 'linkspage.hide_branding'), $mode['label'] . ': [default] is allowed');

            $limit = g2ml_featureLimit('[default]', 'linkspage.analytics_retention_days', 999999);
            assert_true($limit['allowed'], $mode['label'] . ': [default] is never limited');
            assert_same(null, $limit['limit'], $mode['label'] . ': [default] has no limit');
        }
    });
});

test('feature gate: a GlobalAdmin acting context is always allowed and unlimited, even for an org whose plan denies', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            // The org's own plan (Free) would deny hide_branding ...
            g2ml_fg_unit_install_fixture('free');
            g2ml_fg_unit_set_switch($mode['value']);

            // ... but a GlobalAdmin is acting. A real GlobalAdmin session is
            // database-backed, so plant exactly what g2ml_getOrgTier() returns
            // for one (the integration test logs in a real GlobalAdmin).
            $GLOBALS['g2ml_entitlements_org_tier_cache']['fg-unit-org'] = _g2ml_entitlementsUnlimitedTier('globaladmin');

            assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': GlobalAdmin is allowed');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 999999);
            assert_true($limit['allowed'], $mode['label'] . ': GlobalAdmin is never limited');
            assert_same(null, $limit['limit'], $mode['label'] . ': GlobalAdmin has no limit');
        }
    });
});

// ============================================================================
// 🛡️ Fail-safe rules — closed for yes/no, open for limits
// ============================================================================

test('feature gate: a system error denies a yes/no feature (one log line naming it) but allows a limit, in every switch mode', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('basic');

            // The org-to-tier lookup fails outright (a database outage).
            $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): bool
            {
                return false;
            };

            g2ml_fg_unit_set_switch($mode['value']);

            $allowed = null;

            $logLines = g2ml_fg_unit_capture_log(function () use (&$allowed): void
            {
                $allowed = g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding');
            });

            assert_false($allowed, $mode['label'] . ': a system error denies (fail closed) even though Basic would allow it');
            assert_same(1, g2ml_fg_unit_count_lines_containing($logLines, 'linkspage.hide_branding'), $mode['label'] . ': exactly one log line names the feature');
            assert_same(1, g2ml_fg_unit_count_lines_containing($logLines, 'g2ml_featureAllowed()'), $mode['label'] . ': and it comes from g2ml_featureAllowed()');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 999999);
            assert_true($limit['allowed'], $mode['label'] . ': a system error allows a limit (fail open, like g2ml_checkLimit())');
            assert_same(null, $limit['limit'], $mode['label'] . ': and reports it as unlimited');

            // The contrast that makes the difference deliberate: the LEGACY
            // check fails OPEN for the very same org and the very same outage.
            assert_true(g2ml_canUseFeature('fg-unit-org', 'hasAPIAccess'), $mode['label'] . ': the legacy g2ml_canUseFeature() still fails open, unchanged');
        }
    });
});

test('feature gate: a registry read failure denies a yes/no feature and allows a limit, in every switch mode', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('basic');

            // The org's plan resolves, but tblFeatures cannot be read. With
            // the switch on, the engine gives up and g2ml_getOrgTier() falls
            // back to the legacy columns (no feature list); with it off, the
            // direct registry read fails. Both must end in "system error".
            $GLOBALS['g2ml_pricing_features_override'] = function (): bool
            {
                return false;
            };

            g2ml_fg_unit_set_switch($mode['value']);

            assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': denied');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 999999);
            assert_true($limit['allowed'], $mode['label'] . ': limit allowed');
            assert_same(null, $limit['limit'], $mode['label'] . ': limit unlimited');
        }
    });
});

test('feature gate: an unknown feature name denies (with one log line naming it) and a limit with an unknown name is unlimited', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('enterprise');
            g2ml_fg_unit_set_switch($mode['value']);

            $allowed = null;

            $logLines = g2ml_fg_unit_capture_log(function () use (&$allowed): void
            {
                $allowed = g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_brandng');
            });

            assert_false($allowed, $mode['label'] . ': a misspelt name is denied even on the top plan');
            assert_same(1, g2ml_fg_unit_count_lines_containing($logLines, 'linkspage.hide_brandng'), $mode['label'] . ': exactly one log line names the misspelt feature');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.not_a_limit', 999999);
            assert_true($limit['allowed'], $mode['label'] . ': an unknown limit name fails open');
            assert_same(null, $limit['limit'], $mode['label'] . ': and is reported as unlimited');
        }
    });
});

test('feature gate: a plan with no row for a feature falls back to the registry default, silently', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('bare');
            g2ml_fg_unit_set_switch($mode['value']);

            $allowed = null;
            $limit   = null;

            $logLines = g2ml_fg_unit_capture_log(function () use (&$allowed, &$limit): void
            {
                $allowed = g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding');
                $limit   = g2ml_featureLimit('fg-unit-org', 'linkspage.analytics_retention_days', 0);
            });

            assert_false($allowed, $mode['label'] . ': no plan row means the registry default (off)');
            assert_same(30, $limit['limit'], $mode['label'] . ': no plan row means the registry default (30 days)');
            assert_same(0, g2ml_fg_unit_count_lines_containing($logLines, 'linkspage.'), $mode['label'] . ': an ordinary "not on your plan" writes no log line');
        }
    });
});

test('feature gate: a name of the wrong type is refused — a limit name denies, a yes/no name is unlimited', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('basic');
            g2ml_fg_unit_set_switch($mode['value']);

            assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.analytics_retention_days'), $mode['label'] . ': a limit is never read as a yes');

            $limit = g2ml_featureLimit('fg-unit-org', 'linkspage.hide_branding', 5);
            assert_true($limit['allowed'], $mode['label'] . ': a yes/no feature is never read as a number');
            assert_same(null, $limit['limit'], $mode['label'] . ': and is reported as unlimited');
        }
    });
});

// ============================================================================
// 🏢 Per-org overrides win in both modes
// ============================================================================

test('feature gate: a per-org deny beats the plan, and a per-org grant beats the plan, in every switch mode', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        foreach (g2ml_fg_unit_switch_modes() as $mode)
        {
            g2ml_fg_unit_install_fixture('basic', [g2ml_fg_unit_org_override_row(1, 20, 'deny')]);
            g2ml_fg_unit_set_switch($mode['value']);

            assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': a deny override removes a feature the plan includes');

            g2ml_fg_unit_install_fixture('free', [g2ml_fg_unit_org_override_row(2, 20, 'grant')]);
            g2ml_clearOrgTierCache();

            assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), $mode['label'] . ': a grant override adds a feature the plan lacks');
        }
    });
});

// ============================================================================
// 🗃️ The request cache
// ============================================================================

test('feature gate: answers are cached per org, and g2ml_clearOrgTierCache() clears that cache', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        g2ml_fg_unit_set_switch('0');
        g2ml_fg_unit_install_fixture('basic');

        assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), 'Basic: allowed');
        assert_true(isset($GLOBALS['g2ml_feature_values_cache']['fg-unit-org']), 'The answer is now in the feature-values cache');

        // Move the org to Free WITHOUT clearing anything: the cached answer
        // is still used for the rest of this request.
        g2ml_fg_unit_install_fixture('free');
        assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), 'Still the cached Basic answer before the cache is cleared');

        // Clearing ONE org (what updateOrganisation() does on a plan change).
        g2ml_clearOrgTierCache('fg-unit-org');
        assert_false(array_key_exists('fg-unit-org', $GLOBALS['g2ml_feature_values_cache']), 'Clearing one org removes its feature-values entry');
        assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), 'After clearing that org, the new Free answer is seen');

        // Clearing ONE org leaves the others alone.
        g2ml_featureAllowed('fg-unit-other-org', 'linkspage.hide_branding');
        g2ml_clearOrgTierCache('fg-unit-org');
        assert_true(array_key_exists('fg-unit-other-org', $GLOBALS['g2ml_feature_values_cache']), 'Clearing one org leaves another org\'s entry in place');

        // Clearing everything (null), and via g2ml_clearPricingCache().
        g2ml_clearOrgTierCache();
        assert_same([], $GLOBALS['g2ml_feature_values_cache'], 'g2ml_clearOrgTierCache() with no org empties the feature-values cache');

        g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding');
        g2ml_clearPricingCache();
        assert_same([], $GLOBALS['g2ml_feature_values_cache'], 'g2ml_clearPricingCache() empties it too (it calls g2ml_clearOrgTierCache())');
    });
});

test('feature gate: a cached system error is cleared by g2ml_clearOrgTierCache() so a recovered lookup is seen', function (): void
{
    g2ml_fg_unit_run(function (): void
    {
        g2ml_fg_unit_set_switch('0');
        g2ml_fg_unit_install_fixture('basic');

        $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): bool
        {
            return false;
        };

        assert_false(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), 'Denied during the outage');
        assert_same(false, $GLOBALS['g2ml_feature_values_cache']['fg-unit-org'], 'The system error itself is cached (false) for the rest of the request');

        g2ml_fg_unit_install_fixture('basic');
        g2ml_clearOrgTierCache('fg-unit-org');

        assert_true(g2ml_featureAllowed('fg-unit-org', 'linkspage.hide_branding'), 'After clearing, the recovered lookup gives the real answer');
    });
});
