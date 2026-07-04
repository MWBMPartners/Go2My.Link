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
 * 🧪 Integration test — logActivity() writes a row to tblActivityLog (B-042)
 * ============================================================================
 *
 * Regression coverage for bug B-042: the INSERT in logActivity() bound 21
 * variables against a 20-character type-definition string, so every call threw
 * mysqli_sql_exception ("number of elements in the type definition string must
 * match the number of bind variables"). The exception was swallowed, so audit
 * rows were silently never written AND the IP rate-limiter — which counts
 * 'create_link' rows in tblActivityLog — could never trip.
 *
 * The test:
 *   1. calls the REAL logActivity() from web/_functions/activity_logger.php
 *      against a freshly imported test database;
 *   2. asserts it returns true and that exactly one row was inserted for a
 *      unique short-code marker;
 *   3. asserts the stored column values match the context that was logged
 *      (proving the bind order, not merely that a row exists);
 *   4. logs the same 'create_link' action N times and asserts the COUNT(*)
 *      the rate-limiter uses now equals N (the abuse-prevention gap is closed).
 *
 * Registration model: like session_rebind_test.php (and unlike
 * shorturl_lookup_test.php, which owns the g2ml_register_integration_tests()
 * hook), this file registers its cases at include time. run_integration.php
 * require_once's every test file in the script scope BEFORE g2ml_test_run_all()
 * runs, so the connected $db handle is in scope here. This avoids a fatal
 * redeclaration of g2ml_register_integration_tests().
 *
 * With no reachable test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
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

/**
 * Count tblActivityLog rows for a given short-code marker via a prepared
 * statement.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return int
 */
function g2ml_activitylog_test_count(mysqli $db, string $shortCode): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM `tblActivityLog` WHERE `shortCode` = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (count) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);

    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Fetch the single most recent tblActivityLog row for a short-code marker.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return array<string, string|null>
 */
function g2ml_activitylog_test_fetch(mysqli $db, string $shortCode): array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `logAction`, `logStatus`, `statusCode`, `orgHandle`, `shortCode`, '
        . '`destinationURL`, `requestDomain`, `requestMethod`, `ipAddress`, `logData` '
        . 'FROM `tblActivityLog` WHERE `shortCode` = ? ORDER BY `logUID` DESC LIMIT 1'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (fetch) failed: ' . mysqli_error($db));
    }

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

// ----------------------------------------------------------------------------
// Bind the application's getDB() to the SAME test database the runner uses, by
// defining the DB_* constants from the same environment variables. logActivity()
// runs its INSERT through getDB() (db_connect.php).
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlActivityTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlActivityTestHost === false || $g2mlActivityTestHost === '')
    {
        $g2mlActivityTestHost = '127.0.0.1';
    }

    $g2mlActivityTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlActivityTestPortRaw === false || $g2mlActivityTestPortRaw === '')
    {
        $g2mlActivityTestPortRaw = '3306';
    }

    $g2mlActivityTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlActivityTestName === false || $g2mlActivityTestName === '')
    {
        $g2mlActivityTestName = 'mwtools_Go2MyLink';
    }

    $g2mlActivityTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlActivityTestUser === false || $g2mlActivityTestUser === '')
    {
        $g2mlActivityTestUser = 'root';
    }

    $g2mlActivityTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlActivityTestPass === false)
    {
        $g2mlActivityTestPass = '';
    }

    define('DB_HOST', $g2mlActivityTestHost);
    define('DB_PORT', (int) $g2mlActivityTestPortRaw);
    define('DB_NAME', $g2mlActivityTestName);
    define('DB_USER', $g2mlActivityTestUser);
    define('DB_PASS', $g2mlActivityTestPass);
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the application code under test and its DB layer.
// ----------------------------------------------------------------------------
$g2mlActivityFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlActivityFunctionsDir . '/db_connect.php';
require_once $g2mlActivityFunctionsDir . '/db_query.php';
require_once $g2mlActivityFunctionsDir . '/security.php';
require_once $g2mlActivityFunctionsDir . '/activity_logger.php';

// ----------------------------------------------------------------------------
// (a) A single logActivity() call inserts exactly one row whose stored values
//     match the logged context (proves the 21-variable bind order and types).
// ----------------------------------------------------------------------------
test('logActivity: a create_link event writes exactly one row with matching column values', function () use ($db): void
{
    // A deterministic request context. No DNT/GPC headers so tracking is on.
    unset($_SERVER['HTTP_DNT']);
    unset($_SERVER['HTTP_SEC_GPC']);
    $_SERVER['REMOTE_ADDR']     = '198.51.100.77';
    $_SERVER['HTTP_HOST']       = 'go2my.link';
    $_SERVER['REQUEST_URI']     = '/create';
    $_SERVER['REQUEST_METHOD']  = 'POST';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    // Unique marker so the suite is repeatable and isolated from other rows.
    $marker         = 'itlog_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $destinationURL = 'https://example.com/' . $marker;

    $countBefore = g2ml_activitylog_test_count($db, $marker);
    assert_same(0, $countBefore, 'No pre-existing rows for the unique marker');

    $written = logActivity('create_link', 'success', 200, array(
        'orgHandle'      => '[default]',
        'shortCode'      => $marker,
        'destinationURL' => $destinationURL,
        'logData'        => array('source' => 'integration_test'),
    ));

    assert_same(true, $written, 'logActivity returns true when the row is written (no swallowed bind error)');

    $countAfter = g2ml_activitylog_test_count($db, $marker);
    assert_same(1, $countAfter, 'Exactly one activity-log row was inserted');

    $row = g2ml_activitylog_test_fetch($db, $marker);

    assert_same('create_link', $row['logAction'], 'logAction stored verbatim');
    assert_same('success', $row['logStatus'], 'logStatus stored verbatim');
    assert_same(200, (int) $row['statusCode'], 'statusCode stored as the integer 200');
    assert_same('[default]', $row['orgHandle'], 'orgHandle stored verbatim');
    assert_same($marker, $row['shortCode'], 'shortCode stored verbatim');
    assert_same($destinationURL, $row['destinationURL'], 'destinationURL stored verbatim');
    assert_same('go2my.link', $row['requestDomain'], 'requestDomain captured from HTTP_HOST');
    assert_same('POST', $row['requestMethod'], 'requestMethod captured from REQUEST_METHOD');
    assert_same('198.51.100.77', $row['ipAddress'], 'ipAddress captured');
    assert_same('{"source": "integration_test"}', $row['logData'], 'logData JSON stored verbatim');

    // Cleanup so the suite is repeatable.
    $cleanup = mysqli_prepare($db, 'DELETE FROM `tblActivityLog` WHERE `shortCode` = ?');
    mysqli_stmt_bind_param($cleanup, 's', $marker);
    mysqli_stmt_execute($cleanup);
    mysqli_stmt_close($cleanup);
});

// ----------------------------------------------------------------------------
// (b) N create_link events produce N countable rows, so the rate-limiter's
//     COUNT(*) is accurate (B-042's abuse-prevention impact is resolved).
// ----------------------------------------------------------------------------
test('logActivity: N create_link events produce N rows the rate-limiter can count', function () use ($db): void
{
    unset($_SERVER['HTTP_DNT']);
    unset($_SERVER['HTTP_SEC_GPC']);
    $_SERVER['REMOTE_ADDR']     = '198.51.100.78';
    $_SERVER['HTTP_HOST']       = 'go2my.link';
    $_SERVER['REQUEST_METHOD']  = 'POST';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

    $marker      = 'itrl_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $eventCount  = 4;

    for ($index = 0; $index < $eventCount; $index++)
    {
        $written = logActivity('create_link', 'success', 200, array(
            'orgHandle' => '[default]',
            'shortCode' => $marker,
        ));

        assert_same(true, $written, 'Each create_link event is logged');
    }

    $counted = g2ml_activitylog_test_count($db, $marker);
    assert_same($eventCount, $counted, 'The rate-limiter COUNT(*) sees one row per event — no undercount');

    // Cleanup.
    $cleanup = mysqli_prepare($db, 'DELETE FROM `tblActivityLog` WHERE `shortCode` = ?');
    mysqli_stmt_bind_param($cleanup, 's', $marker);
    mysqli_stmt_execute($cleanup);
    mysqli_stmt_close($cleanup);
});
