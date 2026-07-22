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
 * 🧪 Integration tests — System-scope settings deduplication (#150)
 * ============================================================================
 *
 * Guards the structural fix for #150: because MySQL treats NULLs as DISTINCT
 * inside a UNIQUE index, the old UQ_setting_scope (settingID, settingScope,
 * settingScopeRef) never deduplicated System-scope rows (settingScopeRef IS
 * NULL), so duplicate (settingID, 'System', NULL) rows could accumulate. The
 * fix adds a STORED generated column settingScopeRefKey = COALESCE(
 * settingScopeRef, '') and rebuilds the unique key on it (see
 * web/_sql/schema/010_core_settings.sql + migration 019).
 *
 * This file drives the REAL setSetting() from web/_functions/settings.php and
 * a raw prepared INSERT against a freshly imported test database (see
 * tests/README.md), asserting:
 *
 *   (1) Calling setSetting() twice for the same System-scope setting leaves
 *       exactly ONE row (its own NULL-safe SELECT-then-write dedupes).
 *   (2) A direct duplicate INSERT of the same (settingID, 'System', NULL) is
 *       rejected by the unique key with a duplicate-key error (errno 1062) —
 *       proving the database-level structural guarantee, independent of any
 *       application code path.
 *
 * Registration model mirrors entitlements_test.php: cases register at INCLUDE
 * time using the $db handle from run_integration.php's script scope. Helper
 * names are prefixed g2ml_ssd_* to stay unique alongside the other integration
 * files. With no reachable test DB the runner skips before this file is ever
 * included. Every case cleans up its own rows on BOTH entry and exit, so the
 * file is fully idempotent against a persisted DB (issue #148's lesson).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      launch-prep/2026-07-09 (#150)
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
    $g2mlSsdEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlSsdEnvHost === false || $g2mlSsdEnvHost === '')
    {
        $g2mlSsdEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlSsdEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlSsdEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlSsdEnvPort === false || $g2mlSsdEnvPort === '')
    {
        $g2mlSsdEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlSsdEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlSsdEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlSsdEnvUser === false || $g2mlSsdEnvUser === '')
    {
        $g2mlSsdEnvUser = 'root';
    }

    define('DB_USER', $g2mlSsdEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlSsdEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlSsdEnvPass === false)
    {
        $g2mlSsdEnvPass = '';
    }

    define('DB_PASS', $g2mlSsdEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlSsdEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlSsdEnvName === false || $g2mlSsdEnvName === '')
    {
        $g2mlSsdEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlSsdEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlSsdFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlSsdFunctionsDir . 'db_connect.php';
require_once $g2mlSsdFunctionsDir . 'db_query.php';
require_once $g2mlSsdFunctionsDir . 'security.php';
require_once $g2mlSsdFunctionsDir . 'settings.php';

// ----------------------------------------------------------------------------
// Test-only setting keys (never present in any seed) so cleanup can safely
// delete every row for the key without disturbing real settings.
// ----------------------------------------------------------------------------
if (!defined('G2ML_SSD_KEY_SETSETTING'))
{
    define('G2ML_SSD_KEY_SETSETTING', 'g2ml.test.ssd.setsetting');
}

if (!defined('G2ML_SSD_KEY_UNIQUEKEY'))
{
    define('G2ML_SSD_KEY_UNIQUEKEY', 'g2ml.test.ssd.uniquekey');
}

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Remove every tblSettings row for a test-only settingID. Idempotent: safe to
 * call whether or not any rows exist, on both entry and exit of a case.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @return void
 */
function g2ml_ssd_cleanup(mysqli $db, string $settingID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblSettings` WHERE `settingID` = ?');
    mysqli_stmt_bind_param($statement, 's', $settingID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Count System-scope rows (settingScopeRef IS NULL) for a settingID — the exact
 * shape that the pre-#150 unique key failed to deduplicate.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @return int
 */
function g2ml_ssd_count_system_rows(mysqli $db, string $settingID): int
{
    $statement = mysqli_prepare(
        $db,
        "SELECT COUNT(*) AS `cnt` FROM `tblSettings` "
        . "WHERE `settingID` = ? AND `settingScope` = 'System' AND `settingScopeRef` IS NULL"
    );
    mysqli_stmt_bind_param($statement, 's', $settingID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Read the settingValue of the System-scope row for a settingID, or null when
 * no such row exists.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @return string|null
 */
function g2ml_ssd_read_system_value(mysqli $db, string $settingID): ?string
{
    $statement = mysqli_prepare(
        $db,
        "SELECT `settingValue` FROM `tblSettings` "
        . "WHERE `settingID` = ? AND `settingScope` = 'System' AND `settingScopeRef` IS NULL LIMIT 1"
    );
    mysqli_stmt_bind_param($statement, 's', $settingID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return null;
    }

    return $row['settingValue'];
}

/**
 * Insert a System-scope row (settingScopeRef = NULL) directly, bypassing
 * setSetting(). Returns the MySQL error number: 0 on success, 1062 on a
 * duplicate-key violation. Robust to both mysqli report modes — getDB() flips
 * the process into MYSQLI_REPORT_ERROR|STRICT (exception) mode, so a duplicate
 * may surface either as a thrown mysqli_sql_exception or as an execute() that
 * returns false with errno 1062.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @param  string $settingValue
 * @return int
 */
function g2ml_ssd_direct_insert_errno(mysqli $db, string $settingID, string $settingValue): int
{
    $scope = 'System';

    try
    {
        $statement = mysqli_prepare(
            $db,
            'INSERT INTO `tblSettings` (`settingID`, `settingScope`, `settingScopeRef`, `settingValue`) '
            . 'VALUES (?, ?, NULL, ?)'
        );

        if ($statement === false)
        {
            return (int) mysqli_errno($db);
        }

        mysqli_stmt_bind_param($statement, 'sss', $settingID, $scope, $settingValue);
        $ok    = mysqli_stmt_execute($statement);
        $errno = (int) mysqli_stmt_errno($statement);
        mysqli_stmt_close($statement);

        if ($ok === false)
        {
            return $errno;
        }

        return 0;
    }
    catch (mysqli_sql_exception $exception)
    {
        return (int) $exception->getCode();
    }
}

// ============================================================================
// (1) setSetting() twice for a System-scope key leaves exactly ONE row.
// ============================================================================
test('settings dedupe (#150): setSetting() twice for a System-scope key yields exactly one row', function () use ($db): void
{
    g2ml_ssd_cleanup($db, G2ML_SSD_KEY_SETSETTING);

    $firstOk = setSetting(G2ML_SSD_KEY_SETSETTING, 'value-1', 'System');
    assert_true($firstOk, 'The first System-scope write must succeed');
    assert_same(1, g2ml_ssd_count_system_rows($db, G2ML_SSD_KEY_SETSETTING), 'One row exists after the first write');

    $secondOk = setSetting(G2ML_SSD_KEY_SETSETTING, 'value-2', 'System');
    assert_true($secondOk, 'The second System-scope write must succeed');

    assert_same(1, g2ml_ssd_count_system_rows($db, G2ML_SSD_KEY_SETSETTING), 'Still exactly ONE row after the second write — no System-scope duplicate accumulated');
    assert_same('value-2', g2ml_ssd_read_system_value($db, G2ML_SSD_KEY_SETSETTING), 'The single row holds the latest value (the second write updated in place)');

    g2ml_ssd_cleanup($db, G2ML_SSD_KEY_SETSETTING);
});

// ============================================================================
// (2) A direct duplicate INSERT of (settingID, 'System', NULL) is rejected by
//     the rebuilt unique key — the database-level structural guarantee.
// ============================================================================
test('settings dedupe (#150): a duplicate (settingID, System, NULL) INSERT is rejected by the unique key (errno 1062)', function () use ($db): void
{
    g2ml_ssd_cleanup($db, G2ML_SSD_KEY_UNIQUEKEY);

    $firstErrno = g2ml_ssd_direct_insert_errno($db, G2ML_SSD_KEY_UNIQUEKEY, 'first');
    assert_same(0, $firstErrno, 'The first direct System-scope (NULL scopeRef) INSERT succeeds');
    assert_same(1, g2ml_ssd_count_system_rows($db, G2ML_SSD_KEY_UNIQUEKEY), 'Exactly one row exists after the first INSERT');

    $duplicateErrno = g2ml_ssd_direct_insert_errno($db, G2ML_SSD_KEY_UNIQUEKEY, 'second');
    assert_same(1062, $duplicateErrno, 'A second identical (settingID, System, NULL) INSERT is rejected with a duplicate-key error — settingScopeRefKey collapses NULL to "" so the unique key fires');
    assert_same(1, g2ml_ssd_count_system_rows($db, G2ML_SSD_KEY_UNIQUEKEY), 'The rejected INSERT wrote no row — still exactly one');

    g2ml_ssd_cleanup($db, G2ML_SSD_KEY_UNIQUEKEY);
});
