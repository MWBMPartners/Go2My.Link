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
 * 🧪 Integration tests — IP geolocation on the redirect hot path (#43)
 * ============================================================================
 *
 * Exercises the REAL, DB-backed resolveShortCode() (→ sp_lookupShortURL),
 * g2ml_geolocationAvailable(), g2ml_geolocateIP(), and logActivity() together,
 * composed in the SAME sequence web/G2My.Link/public_html/index.php uses on
 * its success branch:
 *
 *   $geoCountryCode = null; $geoRegionCode = null; $geoCityName = null;
 *   if (g2ml_geolocationAvailable()) {
 *       $geoResult = g2ml_geolocateIP(g2ml_getClientIP());
 *       $geoCountryCode = $geoResult['countryCode']; ...
 *   }
 *   logActivity('redirect', 'success', $redirectCode, [ ..., 'countryCode' => $geoCountryCode, ... ]);
 *
 * ⚠️  COUPLING NOTE: mirrors index.php's composition rather than executing
 * index.php itself — see the identical note in utm_redirect_test.php for why
 * (index.php calls exit() and depends on the gitignored, developer-machine
 * auth_creds.php). If index.php's geolocation gating is ever restructured,
 * re-diff this file against it.
 *
 * Proves the two headline guarantees from #43:
 *   (a) with analytics.geolocation_enabled at its seeded default (off), a
 *       redirect logs countryCode/regionCode/cityName as NULL and is
 *       otherwise byte-identical to a pre-#43 redirect — even when a mocked
 *       reader override is ALSO installed (proving the setting gate, not
 *       just the file-existence gate, is checked first);
 *   (b) with the setting on AND a `.mmdb` file present (a throwaway EMPTY
 *       file is enough — g2ml_geolocationAvailable() only checks
 *       is_file()/is_readable(), it never parses the file itself) AND a
 *       mocked $GLOBALS['g2ml_geoip_reader_override'] standing in for the
 *       real MaxMind reader, the redirect logs the mocked
 *       countryCode/regionCode/cityName;
 *   (c) with the setting on but NO database file present at all, the
 *       redirect degrades to the SAME NULL/unchanged behaviour as (a) — the
 *       setting alone is never enough, exactly matching
 *       g2ml_geolocationAvailable()'s AND semantics.
 *
 * Registration model mirrors activity_log_test.php / utm_redirect_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are unique
 * (g2ml_georedirect_*) to avoid redeclaration alongside the other
 * integration files. With no reachable test DB the runner skips before this
 * file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#43)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// If it is somehow unavailable, register nothing rather than fataling.
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the SAME throwaway server the
// runner uses. Guarded so this composes with any other integration file that
// already defined these constants — mirrors activity_log_test.php /
// utm_redirect_test.php.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlGeoRedirectTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlGeoRedirectTestHost === false || $g2mlGeoRedirectTestHost === '')
    {
        $g2mlGeoRedirectTestHost = '127.0.0.1';
    }

    $g2mlGeoRedirectTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlGeoRedirectTestPortRaw === false || $g2mlGeoRedirectTestPortRaw === '')
    {
        $g2mlGeoRedirectTestPortRaw = '3306';
    }

    $g2mlGeoRedirectTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlGeoRedirectTestName === false || $g2mlGeoRedirectTestName === '')
    {
        $g2mlGeoRedirectTestName = 'mwtools_Go2MyLink';
    }

    $g2mlGeoRedirectTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlGeoRedirectTestUser === false || $g2mlGeoRedirectTestUser === '')
    {
        $g2mlGeoRedirectTestUser = 'root';
    }

    $g2mlGeoRedirectTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlGeoRedirectTestPass === false)
    {
        $g2mlGeoRedirectTestPass = '';
    }

    define('DB_HOST', $g2mlGeoRedirectTestHost);
    define('DB_PORT', (int) $g2mlGeoRedirectTestPortRaw);
    define('DB_NAME', $g2mlGeoRedirectTestName);
    define('DB_USER', $g2mlGeoRedirectTestUser);
    define('DB_PASS', $g2mlGeoRedirectTestPass);
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the application code under test.
// ----------------------------------------------------------------------------
$g2mlGeoRedirectFunctionsDir   = dirname(__DIR__, 2) . '/web/_functions';
$g2mlGeoRedirectComponentBDir  = dirname(__DIR__, 2) . '/web/G2My.Link/_functions';

require_once $g2mlGeoRedirectFunctionsDir . '/db_connect.php';
require_once $g2mlGeoRedirectFunctionsDir . '/db_query.php';
require_once $g2mlGeoRedirectFunctionsDir . '/security.php';
require_once $g2mlGeoRedirectFunctionsDir . '/settings.php';
require_once $g2mlGeoRedirectFunctionsDir . '/geolocation.php';
require_once $g2mlGeoRedirectFunctionsDir . '/activity_logger.php';
require_once $g2mlGeoRedirectComponentBDir . '/redirect_resolver.php';

// ----------------------------------------------------------------------------
// Helpers.
// ----------------------------------------------------------------------------

/**
 * Run a query and abort the suite loudly if the DB rejects it — setup errors
 * are not characterised behaviour.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_georedirect_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert (or refresh) a test short-URL row using a prepared statement.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @param  string $destinationURL
 * @return void
 */
function g2ml_georedirect_seed_link(mysqli $db, string $shortCode, string $destinationURL): void
{
    $deleteStatement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ? AND `orgHandle` = ?');
    $orgHandle       = '[default]';
    mysqli_stmt_bind_param($deleteStatement, 'ss', $shortCode, $orgHandle);
    mysqli_stmt_execute($deleteStatement);
    mysqli_stmt_close($deleteStatement);

    $sql = 'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?)';

    $statement       = mysqli_prepare($db, $sql);
    $destinationType = 'url';
    $isActive        = 1;

    mysqli_stmt_bind_param(
        $statement,
        'ssssi',
        $orgHandle,
        $shortCode,
        $destinationURL,
        $destinationType,
        $isActive
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert failed: ' . $error);
    }

    mysqli_stmt_close($statement);
}

/**
 * Fetch the single most recent tblActivityLog row for a short-code marker.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return array<string, string|null>
 */
function g2ml_georedirect_fetch_log(mysqli $db, string $shortCode): array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `logAction`, `logStatus`, `destinationURL`, `countryCode`, `regionCode`, `cityName` '
        . 'FROM `tblActivityLog` WHERE `shortCode` = ? ORDER BY `logUID` DESC LIMIT 1'
    );

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);

    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        throw new RuntimeException('Expected an activity-log row for shortCode ' . $shortCode . ' but found none');
    }

    return $row;
}

/**
 * Run the EXACT geolocation + logActivity sequence
 * web/G2My.Link/public_html/index.php's success branch runs — see this
 * file's own docblock for the literal mirrored snippet.
 *
 * @param  string $shortCode
 * @param  string $destination
 * @return void
 */
function g2ml_georedirect_runHotPathSequence(string $shortCode, string $destination): void
{
    $geoCountryCode = null;
    $geoRegionCode  = null;
    $geoCityName    = null;

    if (function_exists('g2ml_geolocationAvailable') && g2ml_geolocationAvailable())
    {
        $geoResult      = g2ml_geolocateIP(g2ml_getClientIP());
        $geoCountryCode = $geoResult['countryCode'];
        $geoRegionCode  = $geoResult['regionCode'];
        $geoCityName    = $geoResult['cityName'];
    }

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => $shortCode,
        'destinationURL' => $destination,
        'countryCode'    => $geoCountryCode,
        'regionCode'     => $geoRegionCode,
        'cityName'       => $geoCityName,
    ]);
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier and the [default] org (idempotent — mirrors
// utm_redirect_test.php), plus a single test short-URL row reused across the
// scenarios below.
// ----------------------------------------------------------------------------
g2ml_georedirect_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_georedirect_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlGeoRedirectShortCode   = 'itgeowire';
$g2mlGeoRedirectDestination = 'https://example.com/geo-wire-destination';

g2ml_georedirect_seed_link($db, $g2mlGeoRedirectShortCode, $g2mlGeoRedirectDestination);

// Ensure loadSettingsCache() has run once so getSetting() has a Default/System
// cache to fall back against (mirrors page_init.php Step 8). Both #43 settings
// were seeded OFF/unset by web/_sql/seeds/017_geolocation_settings.sql.
loadSettingsCache();

// A throwaway file path used ONLY to satisfy g2ml_geolocationAvailable()'s
// is_file()/is_readable() check in scenario (b) below — it is never actually
// parsed as a MaxMind DB, because $GLOBALS['g2ml_geoip_reader_override'] is
// also set, and the override is consulted BEFORE any real file is opened.
$g2mlGeoRedirectFakeDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_geoip_test_' . getmypid() . '.mmdb';

// ----------------------------------------------------------------------------
// (a) analytics.geolocation_enabled at its seeded default (off): the redirect
//     must log NULL geo columns — even with a mocked reader override ALSO
//     installed, proving the SETTING gate (not merely file-absence) is what
//     is actually being checked.
// ----------------------------------------------------------------------------
test('geolocation redirect (#43): setting off — countryCode/regionCode/cityName are NULL, byte-identical to pre-#43 behaviour', function () use ($db, $g2mlGeoRedirectShortCode, $g2mlGeoRedirectDestination): void
{
    setSetting('analytics.geolocation_enabled', false, 'System');

    // A mocked reader IS installed, to prove it is never even consulted.
    $overrideWasCalled = false;

    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip) use (&$overrideWasCalled): ?array
    {
        $overrideWasCalled = true;

        return ['country' => ['iso_code' => 'US']];
    };

    unset($GLOBALS['_g2ml_geoip_reader_cache']);

    $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

    $resolved = resolveShortCode('g2my.link', $g2mlGeoRedirectShortCode);
    assert_same('success', $resolved['status'], 'The seeded link resolves successfully');
    assert_same($g2mlGeoRedirectDestination, $resolved['destination'], 'sp_lookupShortURL returns the raw stored destination');

    g2ml_georedirect_runHotPathSequence($g2mlGeoRedirectShortCode, $resolved['destination']);

    assert_false($overrideWasCalled, 'The mocked reader must NEVER be consulted while the setting is off');

    $row = g2ml_georedirect_fetch_log($db, $g2mlGeoRedirectShortCode);
    assert_same($g2mlGeoRedirectDestination, $row['destinationURL'], 'The logged destinationURL is the raw stored value — unaffected');
    assert_same(null, $row['countryCode'], 'countryCode must be NULL when the setting is off');
    assert_same(null, $row['regionCode'], 'regionCode must be NULL when the setting is off');
    assert_same(null, $row['cityName'], 'cityName must be NULL when the setting is off');

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

// ----------------------------------------------------------------------------
// (b) Setting ON, a `.mmdb` FILE present (empty — never actually parsed), and
//     a mocked reader override: the redirect logs the mocked
//     countryCode/regionCode/cityName.
// ----------------------------------------------------------------------------
test('geolocation redirect (#43): setting on + database present (mocked reader) — countryCode/regionCode/cityName are logged', function () use ($db, $g2mlGeoRedirectShortCode, $g2mlGeoRedirectDestination, $g2mlGeoRedirectFakeDbPath): void
{
    setSetting('analytics.geolocation_enabled', true, 'System');
    setSetting('analytics.geoip_db_path', $g2mlGeoRedirectFakeDbPath, 'System');

    // Satisfy is_file()/is_readable() — this file is NEVER actually parsed,
    // because the override below is consulted first.
    file_put_contents($g2mlGeoRedirectFakeDbPath, '');

    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip): ?array
    {
        if ($ip === '8.8.4.4')
        {
            return [
                'country'      => ['iso_code' => 'GB'],
                'subdivisions' => [['iso_code' => 'ENG']],
                'city'         => ['names' => ['en' => 'London']],
            ];
        }

        return null;
    };

    unset($GLOBALS['_g2ml_geoip_reader_cache']);

    $_SERVER['REMOTE_ADDR'] = '8.8.4.4';

    assert_true(g2ml_geolocationAvailable(), 'Both the setting and the file must now report available');

    $resolved = resolveShortCode('g2my.link', $g2mlGeoRedirectShortCode);
    assert_same('success', $resolved['status']);

    g2ml_georedirect_runHotPathSequence($g2mlGeoRedirectShortCode, $resolved['destination']);

    $row = g2ml_georedirect_fetch_log($db, $g2mlGeoRedirectShortCode);
    assert_same($g2mlGeoRedirectDestination, $row['destinationURL'], 'The destination itself is untouched by geolocation — only the log row gains geo columns');
    assert_same('GB', $row['countryCode'], 'countryCode is the mocked reader\'s value');
    assert_same('ENG', $row['regionCode'], 'regionCode is the mocked reader\'s value');
    assert_same('London', $row['cityName'], 'cityName is the mocked reader\'s value');

    unset($GLOBALS['g2ml_geoip_reader_override']);

    if (is_file($g2mlGeoRedirectFakeDbPath))
    {
        unlink($g2mlGeoRedirectFakeDbPath);
    }
});

// ----------------------------------------------------------------------------
// (c) Setting ON but NO database file present at all: g2ml_geolocationAvailable()
//     must report false (the AND semantics), so the redirect degrades to the
//     SAME NULL/unchanged behaviour as scenario (a).
// ----------------------------------------------------------------------------
test('geolocation redirect (#43): setting on but database ABSENT — degrades to NULL, unchanged (no throw)', function () use ($db, $g2mlGeoRedirectShortCode, $g2mlGeoRedirectDestination, $g2mlGeoRedirectFakeDbPath): void
{
    setSetting('analytics.geolocation_enabled', true, 'System');
    setSetting('analytics.geoip_db_path', $g2mlGeoRedirectFakeDbPath, 'System');

    // Deliberately do NOT create the file this time (and confirm scenario (b)
    // already cleaned its own copy up).
    assert_false(is_file($g2mlGeoRedirectFakeDbPath), 'Precondition: the fake database file must not exist for this scenario');

    unset($GLOBALS['g2ml_geoip_reader_override']);
    unset($GLOBALS['_g2ml_geoip_reader_cache']);

    $_SERVER['REMOTE_ADDR'] = '9.9.9.9';

    assert_false(g2ml_geolocationAvailable(), 'The setting alone is never enough — a missing file must report unavailable');

    $resolved = resolveShortCode('g2my.link', $g2mlGeoRedirectShortCode);
    assert_same('success', $resolved['status']);

    g2ml_georedirect_runHotPathSequence($g2mlGeoRedirectShortCode, $resolved['destination']);

    $row = g2ml_georedirect_fetch_log($db, $g2mlGeoRedirectShortCode);
    assert_same(null, $row['countryCode'], 'countryCode must be NULL when the database file is absent, even with the setting on');
    assert_same(null, $row['regionCode'], 'regionCode must be NULL when the database file is absent, even with the setting on');
    assert_same(null, $row['cityName'], 'cityName must be NULL when the database file is absent, even with the setting on');
});

// ----------------------------------------------------------------------------
// Restore both settings to their off/unset default so this file never leaves
// the shared settings cache in a non-default state for any test that runs
// after it in the same process — mirrors utm_redirect_test.php's own cleanup.
// ----------------------------------------------------------------------------
test('geolocation redirect (#43): cleanup — settings are restored to off/unset', function (): void
{
    setSetting('analytics.geolocation_enabled', false, 'System');
    setSetting('analytics.geoip_db_path', null, 'System');

    assert_same(false, getSetting('analytics.geolocation_enabled', false), 'analytics.geolocation_enabled is restored to off');

    unset($GLOBALS['g2ml_geoip_reader_override']);
    unset($GLOBALS['_g2ml_geoip_reader_cache']);
});
