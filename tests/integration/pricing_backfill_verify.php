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
 * 🧪 Integration tests — pricing engine backfill verification (scaffold)
 * ============================================================================
 *
 * Drives the REAL DB-backed pieces of the flexible pricing engine against a
 * freshly imported test database (see tests/README.md):
 *
 *   (1) MIGRATION VERIFICATION — for every REAL seeded tier
 *       (web/_sql/seeds/001_subscription_tiers.sql: free/basic/premium/
 *       enterprise), vwSubscriptionTierEntitlements (schema 036) must match
 *       tblSubscriptionTiers' legacy has* and max* columns column-for-column —
 *       proving the seed-018 backfill reproduced the legacy model exactly.
 *   (2) The nine billing.* engine-switch settings (seed 019) are present and
 *       ALL seeded OFF ('0'), except the two non-switch display defaults
 *       (currency/region) and the informational retention-days number.
 *   (3) All 10 new tables (schema 036) exist.
 *   (4) DISABLED-BY-DEFAULT: a freshly INSERTed tblPricePlans/tblCoupons row
 *       that does not specify isActive lands isActive=0 (the launch-safe
 *       posture), proven against the real column default rather than PHP.
 *   (5) RESOLVER PARITY (end to end) — for a real org assigned to a real
 *       seeded tier, web/_functions/pricing.php's g2ml_pricingResolveOrgTier()
 *       (driven against the REAL database, no test overrides) produces the
 *       SAME legacy-shaped values as entitlements.php's own legacy resolution
 *       for that same org — proving the PHP resolver, not just the SQL view,
 *       reproduces the legacy model exactly. This does NOT flip
 *       billing.pricing_engine_enabled — it calls the resolver function
 *       directly, exactly as tests/unit/pricing_resolver_test.php does.
 *
 * Registration model mirrors tests/integration/entitlements_test.php: cases
 * register at INCLUDE time using the $db handle from run_integration.php's
 * script scope. Helper names are prefixed g2ml_prbv_ to stay unique alongside
 * the other integration files. With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.6.0 — Pricing Engine phase (scaffold)
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
    $g2mlPrbvEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlPrbvEnvHost === false || $g2mlPrbvEnvHost === '')
    {
        $g2mlPrbvEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlPrbvEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlPrbvEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlPrbvEnvPort === false || $g2mlPrbvEnvPort === '')
    {
        $g2mlPrbvEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlPrbvEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlPrbvEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlPrbvEnvUser === false || $g2mlPrbvEnvUser === '')
    {
        $g2mlPrbvEnvUser = 'root';
    }

    define('DB_USER', $g2mlPrbvEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlPrbvEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlPrbvEnvPass === false)
    {
        $g2mlPrbvEnvPass = '';
    }

    define('DB_PASS', $g2mlPrbvEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlPrbvEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlPrbvEnvName === false || $g2mlPrbvEnvName === '')
    {
        $g2mlPrbvEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlPrbvEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlPrbvFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlPrbvFunctionsDir . 'db_connect.php';
require_once $g2mlPrbvFunctionsDir . 'db_query.php';
require_once $g2mlPrbvFunctionsDir . 'security.php';
require_once $g2mlPrbvFunctionsDir . 'settings.php';
require_once $g2mlPrbvFunctionsDir . 'entitlements.php';
require_once $g2mlPrbvFunctionsDir . 'pricing.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Execute a setup/teardown query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_prbv_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Fetch the tblSubscriptionTiers rows for the REAL seeded tiers (free/basic/
 * premium/enterprise — see web/_sql/seeds/001_subscription_tiers.sql)
 * alongside their vwSubscriptionTierEntitlements counterpart, one associative
 * row per tier.
 *
 * Deliberately scoped to ONLY those four tierIDs rather than every row in
 * tblSubscriptionTiers: several OTHER integration test files (e.g.
 * tests/integration/api_key_test.php) insert their OWN throwaway test tiers
 * directly into tblSubscriptionTiers for unrelated purposes. Those ad-hoc
 * tiers are never backfilled into tblTierFeatures (the seed-018 backfill runs
 * once, at import time, over whatever tiers existed then) — which is CORRECT
 * behaviour (the legacy columns stay authoritative for them while the engine
 * is off), not a pricing-engine defect, so this parity check must not treat
 * their absence from the view as a mismatch.
 *
 * @param  mysqli $db
 * @return array
 */
function g2ml_prbv_fetch_tier_vs_view(mysqli $db): array
{
    $result = mysqli_query(
        $db,
        'SELECT '
        . 't.tierID, '
        . 't.maxLinks AS legacyMaxLinks, v.maxLinks AS viewMaxLinks, '
        . 't.maxCustomDomains AS legacyMaxCustomDomains, v.maxCustomDomains AS viewMaxCustomDomains, '
        . 't.maxAPIRequestsPerDay AS legacyMaxAPIRequestsPerDay, v.maxAPIRequestsPerDay AS viewMaxAPIRequestsPerDay, '
        . 't.maxLinksPages AS legacyMaxLinksPages, v.maxLinksPages AS viewMaxLinksPages, '
        . 't.hasAdvancedRedirects AS legacyHasAdvancedRedirects, v.hasAdvancedRedirects AS viewHasAdvancedRedirects, '
        . 't.hasAnalytics AS legacyHasAnalytics, v.hasAnalytics AS viewHasAnalytics, '
        . 't.hasQRCodes AS legacyHasQRCodes, v.hasQRCodes AS viewHasQRCodes, '
        . 't.hasAPIAccess AS legacyHasAPIAccess, v.hasAPIAccess AS viewHasAPIAccess, '
        . 't.hasPrioritySupport AS legacyHasPrioritySupport, v.hasPrioritySupport AS viewHasPrioritySupport, '
        . 't.hasCustomHTML AS legacyHasCustomHTML, v.hasCustomHTML AS viewHasCustomHTML '
        . 'FROM tblSubscriptionTiers t '
        . 'LEFT JOIN vwSubscriptionTierEntitlements v USING (tierID) '
        . "WHERE t.tierID IN ('free', 'basic', 'premium', 'enterprise') "
        . 'ORDER BY t.tierID ASC'
    );

    if ($result === false)
    {
        throw new RuntimeException('Query failed: ' . mysqli_error($db));
    }

    $rows = [];

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $rows[] = $row;
    }

    mysqli_free_result($result);

    return $rows;
}

// ----------------------------------------------------------------------------
// 1. Migration verification — backfill parity via vwSubscriptionTierEntitlements.
// ----------------------------------------------------------------------------
test('pricing backfill: vwSubscriptionTierEntitlements matches tblSubscriptionTiers column-for-column for every real seeded tier', function () use ($db): void
{
    $rows = g2ml_prbv_fetch_tier_vs_view($db);

    assert_same(4, count($rows), 'All 4 real seeded tiers (free/basic/premium/enterprise) are present');

    foreach ($rows as $row)
    {
        $tierID = (string) $row['tierID'];

        assert_same($row['legacyMaxLinks'], $row['viewMaxLinks'], 'maxLinks matches for tier ' . $tierID);
        assert_same($row['legacyMaxCustomDomains'], $row['viewMaxCustomDomains'], 'maxCustomDomains matches for tier ' . $tierID);
        assert_same($row['legacyMaxAPIRequestsPerDay'], $row['viewMaxAPIRequestsPerDay'], 'maxAPIRequestsPerDay matches for tier ' . $tierID);
        assert_same($row['legacyMaxLinksPages'], $row['viewMaxLinksPages'], 'maxLinksPages matches for tier ' . $tierID);
        assert_same($row['legacyHasAdvancedRedirects'], $row['viewHasAdvancedRedirects'], 'hasAdvancedRedirects matches for tier ' . $tierID);
        assert_same($row['legacyHasAnalytics'], $row['viewHasAnalytics'], 'hasAnalytics matches for tier ' . $tierID);
        assert_same($row['legacyHasQRCodes'], $row['viewHasQRCodes'], 'hasQRCodes matches for tier ' . $tierID);
        assert_same($row['legacyHasAPIAccess'], $row['viewHasAPIAccess'], 'hasAPIAccess matches for tier ' . $tierID);
        assert_same($row['legacyHasPrioritySupport'], $row['viewHasPrioritySupport'], 'hasPrioritySupport matches for tier ' . $tierID);
        assert_same($row['legacyHasCustomHTML'], $row['viewHasCustomHTML'], 'hasCustomHTML matches for tier ' . $tierID);
    }
});

// ----------------------------------------------------------------------------
// 2. All billing.* settings exist and are seeded OFF (except display defaults).
// ----------------------------------------------------------------------------
test('pricing settings: every billing.* switch seeded OFF, except the currency/region display defaults', function () use ($db): void
{
    $result = mysqli_query($db, "SELECT settingID, settingValue FROM tblSettings WHERE settingID LIKE 'billing.%'");

    if ($result === false)
    {
        throw new RuntimeException('Query failed: ' . mysqli_error($db));
    }

    $settings = [];

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $settings[$row['settingID']] = $row['settingValue'];
    }

    mysqli_free_result($result);

    $expectedOff = [
        'billing.pricing_engine_enabled',
        'billing.payg_enabled',
        'billing.usage_metering_enabled',
        'billing.usage_event_log_enabled',
        'billing.lifetime_plans_enabled',
        'billing.coupon_stacking_enabled',
    ];

    foreach ($expectedOff as $settingID)
    {
        assert_true(array_key_exists($settingID, $settings), $settingID . ' is seeded');
        assert_same('0', $settings[$settingID], $settingID . ' is seeded OFF');
    }

    assert_same('GBP', $settings['billing.default_currency'] ?? null, 'default_currency display default is GBP');
    assert_same('GB', $settings['billing.default_region'] ?? null, 'default_region display default is GB');
    assert_same('90', $settings['billing.usage_event_retention_days'] ?? null, 'usage_event_retention_days default is 90');
});

// ----------------------------------------------------------------------------
// 3. All 10 new tables exist.
// ----------------------------------------------------------------------------
test('pricing schema: all 10 new tables exist', function () use ($db): void
{
    $expectedTables = [
        'tblFeatures',
        'tblPricePlans',
        'tblTierFeatures',
        'tblOrgFeatureOverrides',
        'tblCoupons',
        'tblCouponRedemptions',
        'tblSubscriptionPlans',
        'tblUsageCounters',
        'tblUsageCredits',
        'tblUsageEvents',
    ];

    foreach ($expectedTables as $tableName)
    {
        $result = mysqli_query($db, 'SHOW TABLES LIKE \'' . $tableName . '\'');

        if ($result === false)
        {
            throw new RuntimeException('Query failed: ' . mysqli_error($db));
        }

        $found = (mysqli_num_rows($result) === 1);
        mysqli_free_result($result);

        assert_true($found, $tableName . ' exists');
    }
});

// ----------------------------------------------------------------------------
// 4. DISABLED-BY-DEFAULT: a plan/coupon row lands isActive=0 without the
//    caller specifying it.
// ----------------------------------------------------------------------------
test('pricing disabled-by-default: a new tblPricePlans/tblCoupons row defaults isActive=0', function () use ($db): void
{
    g2ml_prbv_exec($db, "INSERT INTO `tblPricePlans` (`planSlug`, `planName`) VALUES ('prbv-test-plan', 'PRBV Test Plan') ON DUPLICATE KEY UPDATE `planName` = VALUES(`planName`)");
    g2ml_prbv_exec($db, "INSERT INTO `tblCoupons` (`couponName`) VALUES ('PRBV Test Coupon')");

    $planResult = mysqli_query($db, "SELECT isActive FROM tblPricePlans WHERE planSlug = 'prbv-test-plan'");

    if ($planResult === false)
    {
        throw new RuntimeException('Query failed: ' . mysqli_error($db));
    }

    $planRow = mysqli_fetch_assoc($planResult);
    mysqli_free_result($planResult);

    assert_same(0, (int) $planRow['isActive'], 'A newly inserted price plan defaults isActive=0');

    $couponResult = mysqli_query($db, "SELECT isActive FROM tblCoupons WHERE couponName = 'PRBV Test Coupon'");

    if ($couponResult === false)
    {
        throw new RuntimeException('Query failed: ' . mysqli_error($db));
    }

    $couponRow = mysqli_fetch_assoc($couponResult);
    mysqli_free_result($couponResult);

    assert_same(0, (int) $couponRow['isActive'], 'A newly inserted coupon defaults isActive=0');

    g2ml_prbv_exec($db, "DELETE FROM `tblPricePlans` WHERE `planSlug` = 'prbv-test-plan'");
    g2ml_prbv_exec($db, "DELETE FROM `tblCoupons` WHERE `couponName` = 'PRBV Test Coupon'");
});

// ----------------------------------------------------------------------------
// 5. Resolver parity, end to end, against the REAL database (no overrides):
//    g2ml_pricingResolveOrgTier() reproduces the legacy tier values for a real
//    org/tier assignment.
// ----------------------------------------------------------------------------
test('pricing resolver parity: g2ml_pricingResolveOrgTier() matches the legacy tier for a real org, against the real database', function () use ($db): void
{
    g2ml_prbv_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('prbv-test-org', 'PRBV Resolver Parity Org', 'https://go2my.link/prbv', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `tierID` = VALUES(`tierID`), `isActive` = VALUES(`isActive`)"
    );

    if (function_exists('g2ml_clearOrgTierCache'))
    {
        g2ml_clearOrgTierCache('prbv-test-org');
    }

    if (function_exists('g2ml_clearPricingCache'))
    {
        g2ml_clearPricingCache('prbv-test-org');
    }

    $legacyTier   = g2ml_getOrgTier('prbv-test-org');
    $resolvedTier = g2ml_pricingResolveOrgTier('prbv-test-org');

    assert_true(is_array($resolvedTier), 'The pricing engine resolver succeeds against the real database');
    assert_same('pricing_engine', $resolvedTier['source'], 'Precondition: resolvedTier really came from the pricing engine, not a fallback');
    assert_same('tier', $legacyTier['source'], 'Precondition: legacyTier really came from the legacy tier lookup');

    assert_same($legacyTier['tierID'], $resolvedTier['tierID'], 'tierID matches');
    assert_same($legacyTier['maxLinks'], $resolvedTier['maxLinks'], 'maxLinks matches');
    assert_same($legacyTier['maxCustomDomains'], $resolvedTier['maxCustomDomains'], 'maxCustomDomains matches');
    assert_same($legacyTier['maxAPIRequestsPerDay'], $resolvedTier['maxAPIRequestsPerDay'], 'maxAPIRequestsPerDay matches');
    assert_same($legacyTier['maxLinksPages'], $resolvedTier['maxLinksPages'], 'maxLinksPages matches');
    assert_same($legacyTier['hasAdvancedRedirects'], $resolvedTier['hasAdvancedRedirects'], 'hasAdvancedRedirects matches');
    assert_same($legacyTier['hasAnalytics'], $resolvedTier['hasAnalytics'], 'hasAnalytics matches');
    assert_same($legacyTier['hasQRCodes'], $resolvedTier['hasQRCodes'], 'hasQRCodes matches');
    assert_same($legacyTier['hasAPIAccess'], $resolvedTier['hasAPIAccess'], 'hasAPIAccess matches');
    assert_same($legacyTier['hasPrioritySupport'], $resolvedTier['hasPrioritySupport'], 'hasPrioritySupport matches');
    assert_same($legacyTier['hasCustomHTML'], $resolvedTier['hasCustomHTML'], 'hasCustomHTML matches');

    g2ml_prbv_exec($db, "DELETE FROM `tblOrganisations` WHERE `orgHandle` = 'prbv-test-org'");

    if (function_exists('g2ml_clearOrgTierCache'))
    {
        g2ml_clearOrgTierCache('prbv-test-org');
    }

    if (function_exists('g2ml_clearPricingCache'))
    {
        g2ml_clearPricingCache('prbv-test-org');
    }
});
