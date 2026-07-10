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
 * 🧪 Integration tests — API audit-log write is robust to oversized input
 * ============================================================================
 *
 * SECURITY REGRESSION (API adversarial audit): tblAPIRequestLog.endpoint is a
 * VARCHAR(255) that embeds the RAW, unsanitised `_apiroute`, and httpMethod is
 * a VARCHAR(10). Before the fix, g2ml_apiLogRequest() bound these values
 * straight into the INSERT with no length cap, so a request carrying a
 * >255-character route made the INSERT throw under MySQL strict mode. That
 * exception is swallowed (logging must never break a request), silently
 * dropping the audit row.
 *
 * That drop is the exploit: the DB-backed rate limiter AND the pre-auth IP
 * failed-auth backoff both COUNT rows in this table, so an unlogged request is
 * an UNCOUNTED one. An attacker could flood invalid-key requests (each still
 * paying the full g2ml_apiVerifyKey() cost) carrying an over-length route, and
 * none of the resulting 401s would ever be logged OR count toward the backoff
 * — defeating the exact control that exists to throttle that flood.
 *
 * These tests drive the REAL g2ml_apiLogRequest() / g2ml_apiCheckPreAuthBackoff()
 * against a freshly imported schema and prove: (1) an oversized endpoint/method
 * now writes a bounded row instead of dropping it, and (2) those 401s DO count
 * toward the pre-auth backoff (the bypass is closed).
 *
 * Registration model mirrors api_v1_urls_test.php: cases register at INCLUDE
 * time using the $db handle from run_integration.php's script scope. Helper
 * names are prefixed g2ml_apilog_test_* to avoid redeclaration alongside
 * sibling integration files. With no reachable test DB the runner skips before
 * this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (API adversarial audit)
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
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlApiLogTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlApiLogTestHost === false || $g2mlApiLogTestHost === '')
    {
        $g2mlApiLogTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlApiLogTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlApiLogTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlApiLogTestPortRaw === false || $g2mlApiLogTestPortRaw === '')
    {
        $g2mlApiLogTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlApiLogTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlApiLogTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlApiLogTestUser === false || $g2mlApiLogTestUser === '')
    {
        $g2mlApiLogTestUser = 'root';
    }

    define('DB_USER', $g2mlApiLogTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlApiLogTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlApiLogTestPass === false)
    {
        $g2mlApiLogTestPass = '';
    }

    define('DB_PASS', $g2mlApiLogTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlApiLogTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlApiLogTestName === false || $g2mlApiLogTestName === '')
    {
        $g2mlApiLogTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlApiLogTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlApiLogFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlApiLogFunctionsDir . '/db_connect.php';
require_once $g2mlApiLogFunctionsDir . '/db_query.php';
require_once $g2mlApiLogFunctionsDir . '/security.php';
require_once $g2mlApiLogFunctionsDir . '/settings.php';
require_once $g2mlApiLogFunctionsDir . '/api_ratelimit.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Delete all tblAPIRequestLog rows for an IP (cleanup between tests).
 *
 * @param  mysqli $db
 * @param  string $ipAddress
 * @return void
 */
function g2ml_apilog_test_clear(mysqli $db, string $ipAddress): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblAPIRequestLog` WHERE `ipAddress` = ?');
    mysqli_stmt_bind_param($statement, 's', $ipAddress);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Count / read back the single stored row for an IP.
 *
 * @param  mysqli $db
 * @param  string $ipAddress
 * @return array|null  ['cnt'=>int, 'endpointLen'=>int, 'methodLen'=>int] or null.
 */
function g2ml_apilog_test_read(mysqli $db, string $ipAddress): ?array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT COUNT(*) AS cnt, MAX(CHAR_LENGTH(`endpoint`)) AS endpointLen, '
        . 'MAX(CHAR_LENGTH(`httpMethod`)) AS methodLen '
        . 'FROM `tblAPIRequestLog` WHERE `ipAddress` = ?'
    );

    mysqli_stmt_bind_param($statement, 's', $ipAddress);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return null;
    }

    return [
        'cnt'         => (int) $row['cnt'],
        'endpointLen' => (int) $row['endpointLen'],
        'methodLen'   => (int) $row['methodLen'],
    ];
}

// ============================================================================
// (1) An oversized endpoint/method must write a BOUNDED row, never drop it.
// ============================================================================

test('apiLogRequest: an oversized endpoint + method writes a bounded audit row instead of silently dropping it', function () use ($db): void
{
    $ip = '198.51.100.11';
    g2ml_apilog_test_clear($db, $ip);
    loadSettingsCache();

    // The exact attack shape: a >255-char route and a >10-char HTTP method.
    $oversizedEndpoint = '/api/v1/' . str_repeat('A', 400);
    $oversizedMethod   = str_repeat('G', 40);

    g2ml_apiLogRequest(null, $oversizedEndpoint, $oversizedMethod, 401, 5, $ip, 'audit-test-UA', null);

    $stored = g2ml_apilog_test_read($db, $ip);

    assert_true(is_array($stored), 'A stored row must be readable');
    assert_same(1, $stored['cnt'], 'The audit row MUST be written (before the fix the INSERT threw and the row was dropped)');
    assert_true($stored['endpointLen'] <= 255, 'The stored endpoint must be bounded to the VARCHAR(255) column width');
    assert_true($stored['methodLen'] <= 10, 'The stored httpMethod must be bounded to the VARCHAR(10) column width');

    g2ml_apilog_test_clear($db, $ip);
});

// ============================================================================
// (2) Oversized-endpoint 401s must STILL count toward the pre-auth backoff —
//     i.e. the failed-auth-flood bypass is closed.
// ============================================================================

test('apiLogRequest: oversized-endpoint 401s are logged and DO count toward the pre-auth backoff (bypass closed)', function () use ($db): void
{
    $ip = '198.51.100.12';
    g2ml_apilog_test_clear($db, $ip);

    // Lower the threshold to 3 for a deterministic test, then restore it.
    mysqli_query($db, "UPDATE `tblSettings` SET `settingValue` = '3' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    // Precondition: with no logged 401s the IP is allowed.
    $before = g2ml_apiCheckPreAuthBackoff($ip);
    assert_true($before['allowed'], 'Precondition: an IP with no logged 401s must be allowed');

    // Three failed-auth attempts, each carrying an OVER-LENGTH route — exactly
    // the shape that used to escape logging (and therefore the backoff count).
    $oversizedEndpoint = '/api/v1/' . str_repeat('B', 500);

    for ($attempt = 0; $attempt < 3; $attempt++)
    {
        g2ml_apiLogRequest(null, $oversizedEndpoint, 'GET', 401, 3, $ip, 'flood-UA', null);
    }

    $after = g2ml_apiCheckPreAuthBackoff($ip);
    assert_false($after['allowed'], 'Three oversized-endpoint 401s MUST be logged and counted — the backoff must now trip (before the fix they were dropped and never counted)');
    assert_same(60, $after['retryAfter'], 'A tripped backoff must report a 60s Retry-After');

    // Restore the seeded default and clean up so this never leaks into siblings.
    mysqli_query($db, "UPDATE `tblSettings` SET `settingValue` = '20' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();
    g2ml_apilog_test_clear($db, $ip);
});
