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
 * 🧪 Integration tests — registry-driven feature gate (LP-01, #216)
 * ============================================================================
 *
 * Path: tests/integration/feature_gate_test.php
 *
 * Drives the REAL g2ml_featureAllowed() / g2ml_featureLimit() from
 * web/_functions/entitlements.php against a freshly imported database (see
 * tests/README.md), with no test overrides for the data itself:
 *
 *   (1) Seed 023 really imported: every LinksPage feature is registered and
 *       active, and each of the four shipped plans holds exactly the value
 *       proposed in issue #216.
 *   (2) With the pricing engine's switch OFF, read through the REAL settings
 *       layer (tblSettings -> loadSettingsCache() -> getSetting()), the four
 *       plans get the proposed answers.
 *   (3) The SAME answers with the switch ON — set to '1' in tblSettings and
 *       read through that same real settings layer. This is the end-to-end
 *       proof of the switch fix: getSetting() returns a real PHP true for a
 *       'boolean' setting, and before LP-01 pricing.php compared it with
 *       `=== '1'`, so this case could never have reached the engine.
 *   (4) A throwaway plan with no rows gets the registry default; adding ONE
 *       tblTierFeatures row changes the answer, and one UPDATE moves it back —
 *       the "move a feature between plans with no code change" promise.
 *   (5) An org with no organisation row, and an empty orgHandle (what a
 *       LinksPage whose org was deleted passes), both resolve to Free.
 *   (6) A real GlobalAdmin session and the '[default]' org are never gated.
 *   (7) A real per-org 'deny' override removes a feature the plan includes.
 *   (8) An unknown feature name is denied against the real registry.
 *
 * Every case creates and removes its own rows. The one shared row it changes
 * — the billing.pricing_engine_enabled setting in case (3) — is put back in a
 * `finally` block, and the settings cache is reloaded, so a failure cannot
 * leave the engine switched on for the integration files that run after it.
 *
 * Registration model mirrors tests/integration/entitlements_test.php: cases
 * register at INCLUDE time using the $db handle from run_integration.php's
 * script scope. Helper names are prefixed g2ml_fg_int_ to stay unique. With
 * no reachable test database the runner prints "SKIPPED (no test DB)" and
 * exits before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      2026-09-21 — LinksPage programme, LP-01 (#216)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. These
// mirror run_integration.php's env resolution and are guarded so they compose
// with any other integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlFgIntEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlFgIntEnvHost === false || $g2mlFgIntEnvHost === '')
    {
        $g2mlFgIntEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlFgIntEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlFgIntEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlFgIntEnvPort === false || $g2mlFgIntEnvPort === '')
    {
        $g2mlFgIntEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlFgIntEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlFgIntEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlFgIntEnvUser === false || $g2mlFgIntEnvUser === '')
    {
        $g2mlFgIntEnvUser = 'root';
    }

    define('DB_USER', $g2mlFgIntEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlFgIntEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlFgIntEnvPass === false)
    {
        $g2mlFgIntEnvPass = '';
    }

    define('DB_PASS', $g2mlFgIntEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlFgIntEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlFgIntEnvName === false || $g2mlFgIntEnvName === '')
    {
        $g2mlFgIntEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlFgIntEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// pricing.php is loaded before entitlements.php, as page_init.php does.
// ----------------------------------------------------------------------------
$g2mlFgIntFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFgIntFunctionsDir . 'db_connect.php';
require_once $g2mlFgIntFunctionsDir . 'db_query.php';
require_once $g2mlFgIntFunctionsDir . 'security.php';
require_once $g2mlFgIntFunctionsDir . 'settings.php';
require_once $g2mlFgIntFunctionsDir . 'activity_logger.php';
require_once $g2mlFgIntFunctionsDir . 'session.php';
require_once $g2mlFgIntFunctionsDir . 'auth.php';
require_once $g2mlFgIntFunctionsDir . 'org.php';
require_once $g2mlFgIntFunctionsDir . 'pricing.php';
require_once $g2mlFgIntFunctionsDir . 'entitlements.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements for everything that takes a value).
// ----------------------------------------------------------------------------

/**
 * Run one prepared statement, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @param  string $types   mysqli bind types ('' for none).
 * @param  array  $params
 * @return void
 */
function g2ml_fg_int_exec(mysqli $db, string $sql, string $types = '', array $params = []): void
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if ($types !== '')
    {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Query failed: ' . $error . ' — SQL: ' . $sql);
    }

    mysqli_stmt_close($statement);
}

/**
 * Run one prepared SELECT and return every row.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @param  string $types
 * @param  array  $params
 * @return array
 */
function g2ml_fg_int_select(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if ($types !== '')
    {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $rows   = [];

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $rows[] = $row;
    }

    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return $rows;
}

/**
 * Create (or re-point) a throwaway organisation on a given plan.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $tierID
 * @return void
 */
function g2ml_fg_int_put_org(mysqli $db, string $orgHandle, string $tierID): void
{
    $orgName     = 'Feature Gate Test ' . $orgHandle;
    $fallbackURL = 'https://go2my.link/' . $orgHandle;
    $isActive    = 1;

    g2ml_fg_int_exec(
        $db,
        'INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE `tierID` = VALUES(`tierID`), `isActive` = VALUES(`isActive`)',
        'ssssi',
        [$orgHandle, $orgName, $fallbackURL, $tierID, $isActive]
    );
}

/**
 * Remove a throwaway organisation (its tblOrgFeatureOverrides rows go with it
 * through the ON DELETE CASCADE foreign key).
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @return void
 */
function g2ml_fg_int_delete_org(mysqli $db, string $orgHandle): void
{
    g2ml_fg_int_exec($db, 'DELETE FROM `tblOrganisations` WHERE `orgHandle` = ?', 's', [$orgHandle]);
}

/**
 * Look up a feature's featureUID by its registry name.
 *
 * @param  mysqli $db
 * @param  string $featureSlug
 * @return int
 */
function g2ml_fg_int_feature_uid(mysqli $db, string $featureSlug): int
{
    $rows = g2ml_fg_int_select($db, 'SELECT `featureUID` FROM `tblFeatures` WHERE `featureSlug` = ?', 's', [$featureSlug]);

    if (count($rows) !== 1)
    {
        throw new RuntimeException('Setup: feature "' . $featureSlug . '" is not registered exactly once (found ' . count($rows) . ').');
    }

    return (int) $rows[0]['featureUID'];
}

/**
 * Clear every request cache the gate uses, and log out any simulated user.
 *
 * @return void
 */
function g2ml_fg_int_reset(): void
{
    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    unset($GLOBALS['g2ml_entitlements_fallback_lookup_override']);
    unset($GLOBALS['g2ml_pricing_setting_override']);
    unset($_SESSION['session_token'], $_SESSION['user_uid'], $_SESSION['user_role'], $_SESSION['user_org_handle']);

    // g2ml_clearPricingCache() clears the engine-switch cache and then calls
    // g2ml_clearOrgTierCache(), which empties both the tier cache and the
    // feature-values cache.
    g2ml_clearPricingCache();
}

/**
 * Store a value for billing.pricing_engine_enabled in tblSettings, reload the
 * real settings cache exactly as page_init.php does, run $body, and ALWAYS
 * put the original value back afterwards (then reload again).
 *
 * @param  mysqli   $db
 * @param  string   $storedValue  '0' or '1' — what an operator would store.
 * @param  callable $body
 * @return void
 */
function g2ml_fg_int_with_stored_switch(mysqli $db, string $storedValue, callable $body): void
{
    $settingID = 'billing.pricing_engine_enabled';

    $originalRows = g2ml_fg_int_select(
        $db,
        "SELECT `settingValue` FROM `tblSettings` WHERE `settingID` = ? AND `settingScope` = 'System'",
        's',
        [$settingID]
    );

    if (count($originalRows) !== 1)
    {
        throw new RuntimeException('Setup: expected exactly one System row for ' . $settingID . ' (seed 019), found ' . count($originalRows) . '.');
    }

    $originalValue = (string) $originalRows[0]['settingValue'];

    try
    {
        // A plain UPDATE, not an upsert: see tests/integration/api_key_test.php
        // for why an INSERT … ON DUPLICATE KEY UPDATE could add a second row.
        g2ml_fg_int_exec(
            $db,
            "UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = ? AND `settingScope` = 'System'",
            'ss',
            [$storedValue, $settingID]
        );

        loadSettingsCache();
        g2ml_fg_int_reset();

        $body();
    }
    finally
    {
        g2ml_fg_int_exec(
            $db,
            "UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = ? AND `settingScope` = 'System'",
            'ss',
            [$originalValue, $settingID]
        );

        loadSettingsCache();
        g2ml_fg_int_reset();
    }
}

/**
 * Check the proposed answers for the four shipped plans. Shared by the
 * switch-off and switch-on cases so both are held to the identical list.
 *
 * @param  mysqli $db
 * @param  string $expectedSource  The route g2ml_getOrgTier() must take.
 * @param  string $label           Prefix for assertion messages.
 * @return void
 */
function g2ml_fg_int_assert_shipped_plans(mysqli $db, string $expectedSource, string $label): void
{
    $plans = [
        ['org' => 'fg-free-org',  'tier' => 'free',       'allowed' => false, 'retention' => 30],
        ['org' => 'fg-basic-org', 'tier' => 'basic',      'allowed' => true,  'retention' => 90],
        ['org' => 'fg-prem-org',  'tier' => 'premium',    'allowed' => true,  'retention' => 365],
        ['org' => 'fg-ent-org',   'tier' => 'enterprise', 'allowed' => true,  'retention' => null],
    ];

    foreach ($plans as $plan)
    {
        g2ml_fg_int_put_org($db, $plan['org'], $plan['tier']);
    }

    try
    {
        foreach ($plans as $plan)
        {
            $tier = g2ml_getOrgTier($plan['org']);
            assert_same($expectedSource, $tier['source'], $label . ' ' . $plan['tier'] . ': precondition — g2ml_getOrgTier() took the expected route');

            assert_same(
                $plan['allowed'],
                g2ml_featureAllowed($plan['org'], 'linkspage.hide_branding'),
                $label . ' ' . $plan['tier'] . ': linkspage.hide_branding'
            );

            $retention = g2ml_featureLimit($plan['org'], 'linkspage.analytics_retention_days', 0);
            assert_same($plan['retention'], $retention['limit'], $label . ' ' . $plan['tier'] . ': linkspage.analytics_retention_days');

            assert_true(g2ml_featureAllowed($plan['org'], 'linkspage.click_tracking'), $label . ' ' . $plan['tier'] . ': linkspage.click_tracking is on for every plan');
        }

        assert_false(g2ml_featureAllowed('fg-basic-org', 'linkspage.password_protect'), $label . ' basic: linkspage.password_protect is Premium and up');
        assert_true(g2ml_featureAllowed('fg-prem-org', 'linkspage.password_protect'), $label . ' premium: linkspage.password_protect');
    }
    finally
    {
        foreach ($plans as $plan)
        {
            g2ml_fg_int_delete_org($db, $plan['org']);
        }

        g2ml_fg_int_reset();
    }
}

// ============================================================================
// (1) Seed 023 imported, with exactly the proposed values.
// ============================================================================
test('seed 023 (#216): every LinksPage feature is registered, active, and each shipped plan holds the proposed value', function () use ($db): void
{
    // valueBoolean for yes/no features; for the retention limit, the number of
    // days, with the string 'unlimited' standing for isUnlimited = 1.
    $expected = [
        'linkspage.hide_branding'            => ['free' => 0, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.seo'                      => ['free' => 0, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.click_tracking'           => ['free' => 1, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.analytics_retention_days' => ['free' => 30, 'basic' => 90, 'premium' => 365, 'enterprise' => 'unlimited'],
        'linkspage.scheduled_links'          => ['free' => 0, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.password_protect'         => ['free' => 0, 'basic' => 0, 'premium' => 1, 'enterprise' => 1],
        'linkspage.image_upload'             => ['free' => 0, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.verified_badge'           => ['free' => 0, 'basic' => 0, 'premium' => 1, 'enterprise' => 1],
        'linkspage.lead_capture'             => ['free' => 0, 'basic' => 0, 'premium' => 1, 'enterprise' => 1],
        'linkspage.tracking_pixels'          => ['free' => 0, 'basic' => 0, 'premium' => 1, 'enterprise' => 1],
        'linkspage.embeds'                   => ['free' => 0, 'basic' => 0, 'premium' => 1, 'enterprise' => 1],
        'linkspage.all_templates'            => ['free' => 1, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.agegate'                  => ['free' => 1, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
        'linkspage.custom_domain'            => ['free' => 0, 'basic' => 1, 'premium' => 1, 'enterprise' => 1],
    ];

    foreach ($expected as $featureSlug => $valuesByTier)
    {
        $featureRows = g2ml_fg_int_select(
            $db,
            'SELECT `featureUID`, `valueType`, `isActive`, `category`, `legacyColumn` FROM `tblFeatures` WHERE `featureSlug` = ?',
            's',
            [$featureSlug]
        );

        assert_same(1, count($featureRows), $featureSlug . ' is registered exactly once');
        assert_same(1, (int) $featureRows[0]['isActive'], $featureSlug . ' is active');
        assert_same('linkspage', $featureRows[0]['category'], $featureSlug . ' is in the linkspage category');
        assert_same(null, $featureRows[0]['legacyColumn'], $featureSlug . ' has no legacy has*/max* column');

        foreach ($valuesByTier as $tierID => $expectedValue)
        {
            $tierRows = g2ml_fg_int_select(
                $db,
                'SELECT `valueBoolean`, `valueInt`, `isUnlimited` FROM `tblTierFeatures` '
                . 'WHERE `tierID` = ? AND `featureUID` = ? AND `effectiveFrom` IS NULL',
                'si',
                [$tierID, (int) $featureRows[0]['featureUID']]
            );

            assert_same(1, count($tierRows), $featureSlug . ' has exactly one undated row for ' . $tierID);

            if ($featureSlug === 'linkspage.analytics_retention_days')
            {
                if ($expectedValue === 'unlimited')
                {
                    assert_same(1, (int) $tierRows[0]['isUnlimited'], $featureSlug . ' is unlimited for ' . $tierID);
                    assert_same(null, $tierRows[0]['valueInt'], $featureSlug . ' stores no number when unlimited, for ' . $tierID);
                }
                else
                {
                    assert_same(0, (int) $tierRows[0]['isUnlimited'], $featureSlug . ' is not unlimited for ' . $tierID);
                    assert_same($expectedValue, (int) $tierRows[0]['valueInt'], $featureSlug . ' days for ' . $tierID);
                }
            }
            else
            {
                assert_same($expectedValue, (int) $tierRows[0]['valueBoolean'], $featureSlug . ' value for ' . $tierID);
            }
        }
    }
});

// ============================================================================
// (2) and (3) The same answers with the switch OFF and ON, both read through
// the real settings layer.
// ============================================================================
test('feature gate (#216): shipped plans get the proposed answers with the switch stored OFF in tblSettings', function () use ($db): void
{
    g2ml_fg_int_with_stored_switch($db, '0', function () use ($db): void
    {
        assert_same(false, getSetting('billing.pricing_engine_enabled'), 'Precondition: the real settings layer returns a PHP false');
        assert_false(g2ml_pricingEngineEnabled(), 'Precondition: the engine is off');

        g2ml_fg_int_assert_shipped_plans($db, 'tier', 'switch off:');
    });
});

test('feature gate (#216): the SAME answers with the switch stored ON in tblSettings (the switch now really works)', function () use ($db): void
{
    g2ml_fg_int_with_stored_switch($db, '1', function () use ($db): void
    {
        assert_same(true, getSetting('billing.pricing_engine_enabled'), 'Precondition: the real settings layer returns a PHP true, not the text 1');
        assert_true(g2ml_pricingEngineEnabled(), 'The engine switch now turns on from a real stored value (before LP-01 it could not)');

        g2ml_fg_int_assert_shipped_plans($db, 'pricing_engine', 'switch on:');
    });

    assert_false(g2ml_pricingEngineEnabled(), 'Teardown: the switch is back off for the files that run after this one');
});

// ============================================================================
// (4) One row decides: a throwaway plan with no rows, then one INSERT, then
// one UPDATE.
// ============================================================================
test('feature gate (#216): a plan with no row gets the default; one tblTierFeatures row changes the answer; one UPDATE moves it back', function () use ($db): void
{
    g2ml_fg_int_reset();

    // sortOrder 999 so this throwaway plan never becomes the lowest-sortOrder
    // "Free fallback" that other integration files rely on.
    g2ml_fg_int_exec(
        $db,
        'INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `sortOrder`, `isActive`) VALUES (?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE `sortOrder` = VALUES(`sortOrder`), `isActive` = VALUES(`isActive`)',
        'ssii',
        ['fgtight', 'Feature Gate Test Plan', 999, 1]
    );

    g2ml_fg_int_put_org($db, 'fg-tight-org', 'fgtight');

    try
    {
        $hideBrandingUID = g2ml_fg_int_feature_uid($db, 'linkspage.hide_branding');

        assert_false(g2ml_featureAllowed('fg-tight-org', 'linkspage.hide_branding'), 'No row for this plan: the registry default (off) applies');
        assert_same(30, g2ml_featureLimit('fg-tight-org', 'linkspage.analytics_retention_days', 0)['limit'], 'No row for this plan: the registry default (30 days) applies');

        g2ml_fg_int_exec(
            $db,
            'INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`) VALUES (?, ?, ?)',
            'sii',
            ['fgtight', $hideBrandingUID, 1]
        );

        g2ml_clearOrgTierCache('fg-tight-org');
        assert_true(g2ml_featureAllowed('fg-tight-org', 'linkspage.hide_branding'), 'One explicit row turns the feature on for this plan, with no code change');

        g2ml_fg_int_exec(
            $db,
            'UPDATE `tblTierFeatures` SET `valueBoolean` = 0 WHERE `tierID` = ? AND `featureUID` = ?',
            'si',
            ['fgtight', $hideBrandingUID]
        );

        g2ml_clearOrgTierCache('fg-tight-org');
        assert_false(g2ml_featureAllowed('fg-tight-org', 'linkspage.hide_branding'), 'One UPDATE moves it back off');
    }
    finally
    {
        g2ml_fg_int_delete_org($db, 'fg-tight-org');
        g2ml_fg_int_exec($db, 'DELETE FROM `tblTierFeatures` WHERE `tierID` = ?', 's', ['fgtight']);
        g2ml_fg_int_exec($db, 'DELETE FROM `tblSubscriptionTiers` WHERE `tierID` = ?', 's', ['fgtight']);
        g2ml_fg_int_reset();
    }
});

// ============================================================================
// (5) No organisation row, and an empty orgHandle, both resolve to Free.
// ============================================================================
test('feature gate (#216): an org with no row, and an empty orgHandle (a page whose org was deleted), resolve to the Free plan', function () use ($db): void
{
    g2ml_fg_int_reset();

    assert_false(g2ml_featureAllowed('fg-no-such-org', 'linkspage.hide_branding'), 'An org with no row falls back to Free: denied');
    assert_same(30, g2ml_featureLimit('fg-no-such-org', 'linkspage.analytics_retention_days', 0)['limit'], 'An org with no row falls back to Free: 30 days');

    assert_false(g2ml_featureAllowed('', 'linkspage.hide_branding'), 'An empty orgHandle falls back to Free: denied');
    assert_same(30, g2ml_featureLimit('', 'linkspage.analytics_retention_days', 0)['limit'], 'An empty orgHandle falls back to Free: 30 days');
    assert_true(g2ml_featureAllowed('', 'linkspage.click_tracking'), 'An empty orgHandle still gets what Free includes');

    g2ml_fg_int_reset();
});

// ============================================================================
// (6) A real GlobalAdmin session and the '[default]' org are never gated.
// ============================================================================
test('feature gate (#216): a real GlobalAdmin session and the [default] org are never gated', function () use ($db): void
{
    g2ml_fg_int_reset();
    g2ml_fg_int_put_org($db, 'fg-free-org', 'free');

    $marker  = 'fgint' . substr(hash('sha256', (string) microtime(true)), 0, 10);
    $userUID = 0;

    try
    {
        assert_false(g2ml_featureAllowed('fg-free-org', 'linkspage.hide_branding'), 'Control: an anonymous context on the Free plan is denied');

        // A real, database-backed GlobalAdmin session (same shape as
        // tests/integration/entitlements_test.php's g2ml_ent_login_as()).
        g2ml_fg_int_exec(
            $db,
            'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `role`, `isActive`) VALUES (?, ?, ?, ?, ?, ?)',
            'sssssi',
            ['[default]', $marker, $marker . '@featuregate.test', 'x', 'GlobalAdmin', 1]
        );

        $userUID    = (int) mysqli_insert_id($db);
        $plainToken = 'plain_' . $marker;

        g2ml_fg_int_exec(
            $db,
            'INSERT INTO `tblUserSessions` (`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            'isssssi',
            [$userUID, hash('sha256', $plainToken), '203.0.113.216', 'IntegrationTest/1.0', 'Test on Test', date('Y-m-d H:i:s', time() + 3600), 1]
        );

        $_SESSION['session_token']   = $plainToken;
        $_SESSION['user_uid']        = $userUID;
        $_SESSION['user_role']       = 'GlobalAdmin';
        $_SESSION['user_org_handle'] = '[default]';

        g2ml_clearOrgTierCache();

        assert_true(g2ml_featureAllowed('fg-free-org', 'linkspage.hide_branding'), 'A GlobalAdmin acting on a Free org is allowed');
        assert_same(null, g2ml_featureLimit('fg-free-org', 'linkspage.analytics_retention_days', 0)['limit'], 'A GlobalAdmin is never limited');

        unset($_SESSION['session_token'], $_SESSION['user_uid'], $_SESSION['user_role'], $_SESSION['user_org_handle']);
        g2ml_clearOrgTierCache();

        assert_true(g2ml_featureAllowed('[default]', 'linkspage.hide_branding'), 'The [default] org is allowed');
        assert_same(null, g2ml_featureLimit('[default]', 'linkspage.analytics_retention_days', 0)['limit'], 'The [default] org is never limited');
    }
    finally
    {
        if ($userUID > 0)
        {
            g2ml_fg_int_exec($db, 'DELETE FROM `tblUserSessions` WHERE `userUID` = ?', 'i', [$userUID]);
            g2ml_fg_int_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?', 'i', [$userUID]);
        }

        g2ml_fg_int_delete_org($db, 'fg-free-org');
        g2ml_fg_int_reset();
    }
});

// ============================================================================
// (7) A real per-org 'deny' override wins over the plan.
// ============================================================================
test('feature gate (#216): a real per-org deny override removes a feature the plan includes', function () use ($db): void
{
    g2ml_fg_int_reset();
    g2ml_fg_int_put_org($db, 'fg-prem-org', 'premium');

    try
    {
        assert_true(g2ml_featureAllowed('fg-prem-org', 'linkspage.hide_branding'), 'Control: Premium includes hide_branding');

        g2ml_fg_int_exec(
            $db,
            'INSERT INTO `tblOrgFeatureOverrides` (`orgHandle`, `featureUID`, `overrideMode`, `sourceType`, `notes`) VALUES (?, ?, ?, ?, ?)',
            'sisss',
            ['fg-prem-org', g2ml_fg_int_feature_uid($db, 'linkspage.hide_branding'), 'deny', 'support', 'LP-01 integration test']
        );

        g2ml_clearOrgTierCache('fg-prem-org');
        assert_false(g2ml_featureAllowed('fg-prem-org', 'linkspage.hide_branding'), 'A deny override for this one org removes it');
    }
    finally
    {
        // Deleting the org removes its override rows (ON DELETE CASCADE).
        g2ml_fg_int_delete_org($db, 'fg-prem-org');
        g2ml_fg_int_reset();
    }
});

// ============================================================================
// (8) An unknown feature name is denied against the real registry.
// ============================================================================
test('feature gate (#216): an unknown feature name is denied, and an unknown limit name is unlimited', function () use ($db): void
{
    g2ml_fg_int_reset();
    g2ml_fg_int_put_org($db, 'fg-ent-org', 'enterprise');

    try
    {
        assert_false(g2ml_featureAllowed('fg-ent-org', 'linkspage.hide_brandng'), 'A misspelt name is denied even on Enterprise');
        assert_same(null, g2ml_featureLimit('fg-ent-org', 'linkspage.no_such_limit', 999999)['limit'], 'An unknown limit name fails open');
    }
    finally
    {
        g2ml_fg_int_delete_org($db, 'fg-ent-org');
        g2ml_fg_int_reset();
    }
});
