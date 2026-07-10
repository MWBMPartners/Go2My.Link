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
 * 🧪 Integration tests — Analytics data layer + /api/v1/analytics (#41)
 * ============================================================================
 *
 * Drives the REAL functions from web/_functions/analytics.php and
 * web/Go2My.Link/public_html/api/v1/handlers/analytics.php against a freshly
 * imported test database (schema + procedures + seeds + migration 014),
 * covering:
 *
 *   - g2ml_analyticsClicksOverTime() day-bucket time series.
 *   - g2ml_analyticsTotals() total/bot/human/unique-IP split, and that a
 *     FAILED resolution (logStatus != 'success') is excluded from every count.
 *   - g2ml_analyticsTopLinks() ranking, org-wide.
 *   - g2ml_analyticsBreakdown() grouped by the browserName dimension.
 *   - g2ml_analyticsScanSources() CueRCode scan attribution counts (#145).
 *   - g2ml_analyticsApiKeyUsage() per-key request counts, org-scoped via the
 *     tblAPIKeys join (a key belonging to a different org yields nothing).
 *   - ORG-SCOPING: every one of the above, called for org A, NEVER reflects
 *     org B's (much larger) click volume — the core BOLA guard this file
 *     exists to prove.
 *   - g2ml_apiHandleAnalyticsGet() happy path (totals/clicks_over_time/
 *     scan_sources/breakdown) and the cross-org 404 (org B's key requesting
 *     org A's code).
 *   - g2ml_apiHandleAnalyticsSummary() happy path (totals/top_links),
 *     confirming org B's code never appears in org A's top_links.
 *
 * Handler functions are called DIRECTLY (not over HTTP) — mirrors the
 * established pattern in tests/integration/api_v1_urls_test.php. Missing-
 * scope 403 is a front-controller concern (scope enforcement happens BEFORE
 * a handler is ever invoked) and is covered at the unit level by
 * tests/unit/analytics_validation_test.php's g2ml_apiKeyHasScope() checks,
 * exactly as urls:write/urls:delete/org:read are covered by
 * tests/unit/api_v1_routing_test.php rather than re-tested here.
 *
 * Registration model: like api_v1_urls_test.php, cases register at INCLUDE
 * time using the $db handle from run_integration.php's script scope. Helper
 * names are prefixed g2ml_analyticstest_* to avoid redeclaration alongside
 * sibling integration files. With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#41)
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
// Align this raw connection's session time zone with the app layer's own
// convention — see the identical block/comment in api_v1_urls_test.php for
// the full rationale (web/_functions/db_connect.php forces UTC on getDB()).
// ----------------------------------------------------------------------------
mysqli_query($db, "SET time_zone = '+00:00'");

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlAnalyticsTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlAnalyticsTestHost === false || $g2mlAnalyticsTestHost === '')
    {
        $g2mlAnalyticsTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlAnalyticsTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlAnalyticsTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlAnalyticsTestPortRaw === false || $g2mlAnalyticsTestPortRaw === '')
    {
        $g2mlAnalyticsTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlAnalyticsTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlAnalyticsTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlAnalyticsTestUser === false || $g2mlAnalyticsTestUser === '')
    {
        $g2mlAnalyticsTestUser = 'root';
    }

    define('DB_USER', $g2mlAnalyticsTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlAnalyticsTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlAnalyticsTestPass === false)
    {
        $g2mlAnalyticsTestPass = '';
    }

    define('DB_PASS', $g2mlAnalyticsTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlAnalyticsTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlAnalyticsTestName === false || $g2mlAnalyticsTestName === '')
    {
        $g2mlAnalyticsTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlAnalyticsTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlAnalyticsFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlAnalyticsHandlersDir  = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers';

require_once $g2mlAnalyticsFunctionsDir . '/db_connect.php';
require_once $g2mlAnalyticsFunctionsDir . '/db_query.php';
require_once $g2mlAnalyticsFunctionsDir . '/security.php';
require_once $g2mlAnalyticsFunctionsDir . '/settings.php';
require_once $g2mlAnalyticsFunctionsDir . '/activity_logger.php';
require_once $g2mlAnalyticsFunctionsDir . '/analytics.php';
require_once $g2mlAnalyticsFunctionsDir . '/api_response.php';
require_once $g2mlAnalyticsFunctionsDir . '/api_auth.php';
require_once $g2mlAnalyticsFunctionsDir . '/api_ratelimit.php';
require_once $g2mlAnalyticsFunctionsDir . '/org.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once $g2mlAnalyticsHandlersDir . '/analytics.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Execute a setup query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_analyticstest_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user via a prepared statement and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_analyticstest_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`, `isSuspended`) '
        . 'VALUES (?, ?, ?, ?, 1, 0)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (user) failed: ' . mysqli_error($db));
    }

    $username     = $marker;
    $email        = $marker . '@analyticstest.test';
    $passwordHash = 'x';

    mysqli_stmt_bind_param($statement, 'ssss', $orgHandle, $username, $email, $passwordHash);
    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (user) failed: ' . $error);
    }

    $userUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $userUID;
}

/**
 * Mint a real API key and return the VERIFIED key row exactly as the front
 * controller would produce it via g2ml_apiVerifyKey().
 *
 * @param  string   $orgHandle
 * @param  int      $userUID
 * @param  string[] $scopes
 * @return array
 */
function g2ml_analyticstest_make_key(string $orgHandle, int $userUID, array $scopes): array
{
    $created = g2ml_apiGenerateKey($userUID, $orgHandle, 'analytics-test key', $scopes, null);

    if ($created['success'] !== true)
    {
        throw new RuntimeException('Test key generation failed.');
    }

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    if (!is_array($verified))
    {
        throw new RuntimeException('Test key failed to verify immediately after creation.');
    }

    return $verified;
}

/**
 * Build a handler $context array.
 *
 * @param  array $routeParams
 * @return array
 */
function g2ml_analyticstest_context(array $routeParams = []): array
{
    return [
        'requestId'   => 'analytics-test-request',
        'routeParams' => $routeParams,
        'body'        => [],
    ];
}

/**
 * Seed one tblActivityLog row via the app's own dbInsert() wrapper. The
 * column list and bind-type list are BOTH driven from a single ordered map
 * (rather than a hand-counted type string) so the two can never silently
 * drift out of sync — the exact class of bug
 * tests/integration/activity_log_test.php regression-tests logActivity()
 * itself against (B-042: a mismatched bind_param count silently dropped
 * every write).
 *
 * @param  array $overrides  Any of the default column values below.
 * @return void
 */
function g2ml_analyticstest_seedClick(array $overrides = []): void
{
    $defaults = [
        'logAction'      => 'redirect',
        'logStatus'      => 'success',
        'statusCode'     => 302,
        'orgHandle'      => '[default]',
        'shortCode'      => 'analyticstestplaceholder',
        'destinationURL' => 'https://example.com/analyticstestplaceholder',
        'requestReferer' => null,
        'browserName'    => 'Chrome',
        'osName'         => 'Windows',
        'deviceType'     => 'desktop',
        'ipAddress'      => '198.51.100.10',
        'isBot'          => 0,
        'scanSource'     => null,
        'countryCode'    => null,
        'createdAt'      => '2026-06-01 12:00:00',
    ];

    $row = array_merge($defaults, $overrides);

    $columnTypes = [
        'logAction'      => 's',
        'logStatus'      => 's',
        'statusCode'     => 'i',
        'orgHandle'      => 's',
        'shortCode'      => 's',
        'destinationURL' => 's',
        'requestReferer' => 's',
        'browserName'    => 's',
        'osName'         => 's',
        'deviceType'     => 's',
        'ipAddress'      => 's',
        'isBot'          => 'i',
        'scanSource'     => 's',
        'countryCode'    => 's',
        'createdAt'      => 's',
    ];

    $columnNames = array_keys($columnTypes);
    $types       = implode('', array_values($columnTypes));
    $params      = [];

    foreach ($columnNames as $columnName)
    {
        $params[] = $row[$columnName];
    }

    $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
    $columnList   = implode(', ', $columnNames);

    $inserted = dbInsert(
        'INSERT INTO tblActivityLog (' . $columnList . ') VALUES (' . $placeholders . ')',
        $types,
        $params
    );

    if ($inserted === false)
    {
        throw new RuntimeException('Seed activity row insert failed: errno ' . dbLastErrno());
    }
}

/**
 * Seed one tblAPIRequestLog row via the app's own dbInsert() wrapper.
 *
 * @param  int    $apiKeyUID
 * @param  int    $responseCode
 * @param  string $createdAt  'Y-m-d H:i:s'
 * @return void
 */
function g2ml_analyticstest_seedApiRequest(int $apiKeyUID, int $responseCode, string $createdAt): void
{
    $inserted = dbInsert(
        'INSERT INTO tblAPIRequestLog (apiKeyUID, endpoint, httpMethod, responseCode, ipAddress, createdAt) '
        . 'VALUES (?, ?, ?, ?, ?, ?)',
        'ississ',
        [$apiKeyUID, '/api/v1/urls', 'GET', $responseCode, '198.51.100.90', $createdAt]
    );

    if ($inserted === false)
    {
        throw new RuntimeException('Seed API request row insert failed: errno ' . dbLastErrno());
    }
}

/**
 * Delete every tblActivityLog row for a short code (cleanup — repeatable suite).
 *
 * @param  string $shortCode
 * @return void
 */
function g2ml_analyticstest_clearActivity(string $shortCode): void
{
    dbDelete('DELETE FROM tblActivityLog WHERE shortCode = ?', 's', [$shortCode]);
}

/**
 * Delete every tblShortURLs row for a short code across all orgs (cleanup).
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_analyticstest_deleteCode(mysqli $db, string $shortCode): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ?');
    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier (already seeded), two organisations
// (A = [default], B = orgbanalytics), and three short codes: two in org A
// (analyticsA1 high-traffic, analyticsA2 low-traffic) and one in org B
// (analyticsB1, seeded with a MUCH LARGER click volume than either of org
// A's codes) — so a broken org-scope filter would be immediately obvious
// rather than accidentally passing on small numbers.
// ----------------------------------------------------------------------------
g2ml_analyticstest_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgbanalytics', 'Org B (analytics isolation test)', 'https://go2my.link/orgb', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlAnalyticsUserA = g2ml_analyticstest_insert_user($db, '[default]', 'analyticsA_' . substr(hash('sha256', (string) microtime(true)), 0, 10));
$g2mlAnalyticsUserB = g2ml_analyticstest_insert_user($db, 'orgbanalytics', 'analyticsB_' . substr(hash('sha256', (string) microtime(true) . 'b'), 0, 10));

$g2mlAnalyticsKeyA = g2ml_analyticstest_make_key('[default]', $g2mlAnalyticsUserA, ['analytics:read']);
$g2mlAnalyticsKeyB = g2ml_analyticstest_make_key('orgbanalytics', $g2mlAnalyticsUserB, ['analytics:read']);

g2ml_analyticstest_deleteCode($db, 'analyticsA1');
g2ml_analyticstest_deleteCode($db, 'analyticsA2');
g2ml_analyticstest_deleteCode($db, 'analyticsB1');

$g2mlAnalyticsCreateA1 = createShortURL('https://example.com/analyticsA1', ['orgHandle' => '[default]', 'userUID' => $g2mlAnalyticsUserA, 'customCode' => 'analyticsA1']);
$g2mlAnalyticsCreateA2 = createShortURL('https://example.com/analyticsA2', ['orgHandle' => '[default]', 'userUID' => $g2mlAnalyticsUserA, 'customCode' => 'analyticsA2']);
$g2mlAnalyticsCreateB1 = createShortURL('https://example.com/analyticsB1', ['orgHandle' => 'orgbanalytics', 'userUID' => $g2mlAnalyticsUserB, 'customCode' => 'analyticsB1']);

if ($g2mlAnalyticsCreateA1['success'] !== true || $g2mlAnalyticsCreateA2['success'] !== true || $g2mlAnalyticsCreateB1['success'] !== true)
{
    throw new RuntimeException('Setup: failed to create one of the analytics test short codes.');
}

g2ml_analyticstest_clearActivity('analyticsA1');
g2ml_analyticstest_clearActivity('analyticsA2');
g2ml_analyticstest_clearActivity('analyticsB1');

// --- analyticsA1 (org A) — 5 successful clicks across two days, plus one
//     FAILED resolution that must be excluded from every aggregate.
//     countryCode (#43) is set on 4 of the 5 rows (3x US, 1x GB) and left
//     NULL on one — mirroring how a real deploy would look with geolocation
//     enabled only partway through the seeded window, or a lookup miss. ---
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-01 09:00:00', 'browserName' => 'Chrome', 'isBot' => 0, 'ipAddress' => '198.51.100.10', 'countryCode' => 'US']);
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-01 10:00:00', 'browserName' => 'Chrome', 'isBot' => 0, 'ipAddress' => '198.51.100.11', 'countryCode' => 'US']);
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-01 11:00:00', 'browserName' => 'Firefox', 'isBot' => 1, 'ipAddress' => '198.51.100.10', 'scanSource' => 'qr', 'countryCode' => 'US']);
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-02 09:00:00', 'browserName' => 'Chrome', 'isBot' => 0, 'ipAddress' => '198.51.100.12', 'countryCode' => 'GB']);
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-02 10:00:00', 'browserName' => 'Safari', 'isBot' => 0, 'ipAddress' => '198.51.100.13', 'scanSource' => 'qr']);
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA1', 'createdAt' => '2026-06-01 08:00:00', 'logStatus' => 'not_found', 'statusCode' => 404]);

// --- analyticsA2 (org A) — 1 successful click (deliberately fewer than A1). ---
g2ml_analyticstest_seedClick(['shortCode' => 'analyticsA2', 'createdAt' => '2026-06-01 09:30:00', 'browserName' => 'Chrome', 'isBot' => 0, 'ipAddress' => '198.51.100.20']);

// --- analyticsB1 (org B) — 10 successful clicks, deliberately MORE than
//     org A's combined total, so a broken org filter is unmistakable. ---
for ($g2mlAnalyticsB1Index = 0; $g2mlAnalyticsB1Index < 10; $g2mlAnalyticsB1Index++)
{
    g2ml_analyticstest_seedClick([
        'orgHandle' => 'orgbanalytics',
        'shortCode' => 'analyticsB1',
        'createdAt' => '2026-06-01 12:' . str_pad((string) $g2mlAnalyticsB1Index, 2, '0', STR_PAD_LEFT) . ':00',
        'browserName' => 'Edge',
        'isBot'       => 0,
        'ipAddress'   => '198.51.100.30',
    ]);
}

$g2mlAnalyticsFrom = '2026-06-01 00:00:00';
$g2mlAnalyticsTo   = '2026-06-03 23:59:59';

// ============================================================================
// 📈 g2ml_analyticsClicksOverTime()
// ============================================================================

test('analyticsClicksOverTime: day-bucket series matches the seeded per-day counts, excludes the failed resolution', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $series = g2ml_analyticsClicksOverTime('[default]', 'analyticsA1', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 'day');

    assert_same(2, count($series), 'Two distinct days have successful clicks for analyticsA1');
    assert_same('2026-06-01', $series[0]['bucket'], 'First bucket is 2026-06-01 (ascending order)');
    assert_same(3, $series[0]['clicks'], '2026-06-01 has 3 successful clicks (the not_found row is excluded)');
    assert_same('2026-06-02', $series[1]['bucket'], 'Second bucket is 2026-06-02');
    assert_same(2, $series[1]['clicks'], '2026-06-02 has 2 successful clicks');
});

test('analyticsClicksOverTime: org-wide (shortCode=null) sums across analyticsA1 and analyticsA2, never analyticsB1', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $series = g2ml_analyticsClicksOverTime('[default]', null, $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 'day');

    $totalAcrossBuckets = 0;

    foreach ($series as $point)
    {
        $totalAcrossBuckets = $totalAcrossBuckets + $point['clicks'];
    }

    assert_same(6, $totalAcrossBuckets, 'Org A total across all buckets is 5 (A1) + 1 (A2) = 6, never including B1\'s 10');
});

// ============================================================================
// 🔢 g2ml_analyticsTotals()
// ============================================================================

test('analyticsTotals: analyticsA1 totals — 5 clicks, 1 bot, 4 human, 4 unique IPs', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $totals = g2ml_analyticsTotals('[default]', 'analyticsA1', $g2mlAnalyticsFrom, $g2mlAnalyticsTo);

    assert_same(5, $totals['totalClicks'], 'The failed (not_found) row must be excluded — 5, not 6');
    assert_same(1, $totals['botClicks'], 'Exactly one seeded row has isBot=1');
    assert_same(4, $totals['humanClicks'], 'The other four seeded rows have isBot=0');
    assert_same(0, $totals['unknownClicks'], 'Every seeded row has isBot explicitly set (no NULL)');
    assert_same(4, $totals['uniqueIPs'], 'Distinct IPs: .10, .11, .10 (repeat), .12, .13 => 4 unique');
});

test('analyticsTotals: org-wide totals for org A never include org B\'s 10 clicks', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $totalsA = g2ml_analyticsTotals('[default]', null, $g2mlAnalyticsFrom, $g2mlAnalyticsTo);
    assert_same(6, $totalsA['totalClicks'], 'Org A total is 5 + 1 = 6, org-scoped away from B\'s 10');

    $totalsB = g2ml_analyticsTotals('orgbanalytics', null, $g2mlAnalyticsFrom, $g2mlAnalyticsTo);
    assert_same(10, $totalsB['totalClicks'], 'Org B total is its own 10 clicks, org-scoped away from A\'s 6');
});

// ============================================================================
// 🏆 g2ml_analyticsTopLinks()
// ============================================================================

test('analyticsTopLinks: org A ranks analyticsA1 above analyticsA2 and never surfaces analyticsB1', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $topLinks = g2ml_analyticsTopLinks('[default]', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 10);

    assert_same(2, count($topLinks), 'Org A has exactly two codes with clicks in this window');
    assert_same('analyticsA1', $topLinks[0]['shortCode'], 'analyticsA1 (5 clicks) ranks first');
    assert_same(5, $topLinks[0]['clicks']);
    assert_same('analyticsA2', $topLinks[1]['shortCode'], 'analyticsA2 (1 click) ranks second');
    assert_same(1, $topLinks[1]['clicks']);

    foreach ($topLinks as $entry)
    {
        assert_not_same('analyticsB1', $entry['shortCode'], 'Org B\'s code (10 clicks — would otherwise dominate the ranking) must never appear in org A\'s top links');
    }
});

// ============================================================================
// 🧩 g2ml_analyticsBreakdown()
// ============================================================================

test('analyticsBreakdown: browserName groups analyticsA1\'s clicks correctly', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $breakdown = g2ml_analyticsBreakdown('[default]', 'analyticsA1', 'browserName', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 10);

    $byBrowser = [];

    foreach ($breakdown as $entry)
    {
        $byBrowser[$entry['value']] = $entry['clicks'];
    }

    assert_same(3, count($byBrowser), 'Three distinct browsers appear (Chrome, Firefox, Safari)');
    assert_same(3, $byBrowser['Chrome'], 'Chrome has 3 clicks');
    assert_same(1, $byBrowser['Firefox'], 'Firefox has 1 click');
    assert_same(1, $byBrowser['Safari'], 'Safari has 1 click');
    assert_same('Chrome', $breakdown[0]['value'], 'The uniquely highest count (Chrome) sorts first');
});

test('analyticsBreakdown: an unwhitelisted dimension returns an empty array rather than ever reaching SQL', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $breakdown = g2ml_analyticsBreakdown('[default]', 'analyticsA1', 'ipAddress', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 10);
    assert_same(0, count($breakdown), 'ipAddress is a real column, but is not on the dimension whitelist — must return empty, not error');

    $injectionAttempt = g2ml_analyticsBreakdown('[default]', 'analyticsA1', 'shortCode`; DROP TABLE tblActivityLog; --', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 10);
    assert_same(0, count($injectionAttempt), 'An injection-shaped dimension must be rejected identically to any other unrecognised value');
});

test('analyticsBreakdown: (#43) countryCode groups analyticsA1\'s clicks correctly, NULL rows excluded', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $breakdown = g2ml_analyticsBreakdown('[default]', 'analyticsA1', 'countryCode', $g2mlAnalyticsFrom, $g2mlAnalyticsTo, 10);

    $byCountry = [];

    foreach ($breakdown as $entry)
    {
        $byCountry[$entry['value']] = $entry['clicks'];
    }

    assert_same(2, count($byCountry), 'Two distinct countries appear (US, GB) — the NULL-countryCode row is excluded, not a third bucket');
    assert_same(3, $byCountry['US'], 'US has 3 clicks');
    assert_same(1, $byCountry['GB'], 'GB has 1 click');
    assert_same('US', $breakdown[0]['value'], 'The uniquely highest count (US) sorts first');
});

// ============================================================================
// 🔗 g2ml_analyticsScanSources()
// ============================================================================

test('analyticsScanSources: only the two "qr" scans count, NULL scanSource rows are excluded', function () use ($g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $scanSources = g2ml_analyticsScanSources('[default]', 'analyticsA1', $g2mlAnalyticsFrom, $g2mlAnalyticsTo);

    assert_same(1, count($scanSources), 'Only one distinct non-NULL scanSource value exists ("qr")');
    assert_same('qr', $scanSources[0]['scanSource']);
    assert_same(2, $scanSources[0]['scans'], 'Two of the five successful clicks carried scanSource=qr');
});

// ============================================================================
// 🔑 g2ml_analyticsApiKeyUsage()
// ============================================================================

test('analyticsApiKeyUsage: counts requestCount/successCount/errorCount for the calling key, org-scoped', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsKeyB): void
{
    $keyAUID = (int) $g2mlAnalyticsKeyA['apiKeyUID'];
    $keyBUID = (int) $g2mlAnalyticsKeyB['apiKeyUID'];

    g2ml_analyticstest_seedApiRequest($keyAUID, 200, '2026-06-01 09:00:00');
    g2ml_analyticstest_seedApiRequest($keyAUID, 200, '2026-06-01 09:05:00');
    g2ml_analyticstest_seedApiRequest($keyAUID, 404, '2026-06-01 09:10:00');
    g2ml_analyticstest_seedApiRequest($keyBUID, 200, '2026-06-01 09:00:00');

    $usageA = g2ml_analyticsApiKeyUsage('[default]', null, '2026-06-01 00:00:00', '2026-06-01 23:59:59');

    assert_same(1, count($usageA), 'Only one key (A) has requests within org A');
    assert_same($keyAUID, $usageA[0]['apiKeyUID']);
    assert_same(3, $usageA[0]['requestCount'], 'Three requests were logged for key A');
    assert_same(2, $usageA[0]['successCount'], 'Two of the three were 2xx');
    assert_same(1, $usageA[0]['errorCount'], 'One of the three was 4xx');

    // Org-scoping: asking org A about key B's own UID must return nothing —
    // the INNER JOIN on tblAPIKeys.orgHandle excludes a foreign key entirely.
    $crossOrgAttempt = g2ml_analyticsApiKeyUsage('[default]', $keyBUID, '2026-06-01 00:00:00', '2026-06-01 23:59:59');
    assert_same(0, count($crossOrgAttempt), 'A key belonging to a DIFFERENT org must yield zero rows, never leak org B\'s usage');

    $usageB = g2ml_analyticsApiKeyUsage('orgbanalytics', null, '2026-06-01 00:00:00', '2026-06-01 23:59:59');
    assert_same(1, count($usageB), 'Org B sees only its own key\'s usage');
    assert_same(1, $usageB[0]['requestCount']);
});

// ============================================================================
// 🔍 g2ml_apiHandleAnalyticsGet() — GET /api/v1/analytics/{code}
// ============================================================================

test('apiHandleAnalyticsGet: happy path returns totals/clicks_over_time/scan_sources for analyticsA1', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $_GET = [
        'from'   => $g2mlAnalyticsFrom,
        'to'     => $g2mlAnalyticsTo,
        'bucket' => 'day',
    ];

    $result = g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyA, g2ml_analyticstest_context(['code' => 'analyticsA1']));

    assert_same('analyticsA1', $result['short_code']);
    assert_same('day', $result['period']['bucket']);
    assert_same(5, $result['totals']['total_clicks']);
    assert_same(1, $result['totals']['bot_clicks']);
    assert_same(4, $result['totals']['human_clicks']);
    assert_same(2, count($result['clicks_over_time']), 'Two day-buckets in the response');
    assert_same(1, count($result['scan_sources']));
    assert_same('qr', $result['scan_sources'][0]['scan_source']);
    assert_same(2, $result['scan_sources'][0]['scans']);
    assert_false(isset($result['breakdown']), 'breakdown must be ABSENT when ?dimension= was not supplied');

    $_GET = [];
});

test('apiHandleAnalyticsGet: ?dimension=browserName adds a breakdown block', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $_GET = [
        'from'      => $g2mlAnalyticsFrom,
        'to'        => $g2mlAnalyticsTo,
        'dimension' => 'browserName',
    ];

    $result = g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyA, g2ml_analyticstest_context(['code' => 'analyticsA1']));

    assert_true(isset($result['breakdown']), 'breakdown must be PRESENT when ?dimension= was supplied');
    assert_same('browserName', $result['breakdown']['dimension']);
    assert_same(3, count($result['breakdown']['items']));

    $_GET = [];
});

test('apiHandleAnalyticsGet: ?dimension=countryCode (#43) adds a breakdown block', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $_GET = [
        'from'      => $g2mlAnalyticsFrom,
        'to'        => $g2mlAnalyticsTo,
        'dimension' => 'countryCode',
    ];

    $result = g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyA, g2ml_analyticstest_context(['code' => 'analyticsA1']));

    assert_true(isset($result['breakdown']), 'breakdown must be PRESENT when ?dimension=countryCode was supplied');
    assert_same('countryCode', $result['breakdown']['dimension']);
    assert_same(2, count($result['breakdown']['items']), 'US and GB — the NULL-countryCode row is excluded');

    $_GET = [];
});

test('apiHandleAnalyticsGet: an invalid ?dimension= throws a 422', function () use ($g2mlAnalyticsKeyA): void
{
    $_GET = ['dimension' => 'ipAddress'];

    assert_throws(function () use ($g2mlAnalyticsKeyA): void
    {
        g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyA, g2ml_analyticstest_context(['code' => 'analyticsA1']));
    }, G2mlApiHandlerException::class, 'An unrecognised dimension must be rejected with a 422');

    $_GET = [];
});

test('apiHandleAnalyticsGet: org B\'s key requesting org A\'s code gets a generic 404 (BOLA guard)', function () use ($g2mlAnalyticsKeyB): void
{
    $_GET = [];

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyB, g2ml_analyticstest_context(['code' => 'analyticsA1']));
    }
    catch (G2mlApiHandlerException $notFoundException)
    {
        $threw      = true;
        $statusCode = $notFoundException->getHttpStatusCode();
    }

    assert_true($threw, 'Org B\'s key must never be able to read org A\'s analytics');
    assert_same(404, $statusCode, 'A cross-org code lookup is a generic 404, identical to a non-existent code');
});

test('apiHandleAnalyticsGet: a genuinely non-existent code also 404s (identical to the cross-org case)', function () use ($g2mlAnalyticsKeyA): void
{
    assert_throws(function () use ($g2mlAnalyticsKeyA): void
    {
        g2ml_apiHandleAnalyticsGet($g2mlAnalyticsKeyA, g2ml_analyticstest_context(['code' => 'nosuchcodeatall']));
    }, G2mlApiHandlerException::class, 'A code that has never existed must 404 the same way as a cross-org code');
});

// ============================================================================
// 📋 g2ml_apiHandleAnalyticsSummary() — GET /api/v1/analytics
// ============================================================================

test('apiHandleAnalyticsSummary: org A totals + top_links never include org B\'s code or clicks', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $_GET = [
        'from' => $g2mlAnalyticsFrom,
        'to'   => $g2mlAnalyticsTo,
    ];

    $result = g2ml_apiHandleAnalyticsSummary($g2mlAnalyticsKeyA, g2ml_analyticstest_context());

    assert_same(6, $result['totals']['total_clicks'], 'Org A summary totals must be 6, never 16 (which would include B\'s 10)');
    assert_same(2, count($result['top_links']));
    assert_same('analyticsA1', $result['top_links'][0]['short_code']);
    assert_same(5, $result['top_links'][0]['clicks']);
    assert_same('analyticsA2', $result['top_links'][1]['short_code']);

    foreach ($result['top_links'] as $entry)
    {
        assert_not_same('analyticsB1', $entry['short_code']);
    }

    $_GET = [];
});

test('apiHandleAnalyticsSummary: ?limit=1 caps top_links to the single highest link', function () use ($g2mlAnalyticsKeyA, $g2mlAnalyticsFrom, $g2mlAnalyticsTo): void
{
    $_GET = [
        'from'  => $g2mlAnalyticsFrom,
        'to'    => $g2mlAnalyticsTo,
        'limit' => '1',
    ];

    $result = g2ml_apiHandleAnalyticsSummary($g2mlAnalyticsKeyA, g2ml_analyticstest_context());

    assert_same(1, count($result['top_links']));
    assert_same('analyticsA1', $result['top_links'][0]['short_code']);

    $_GET = [];
});
