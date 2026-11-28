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
 * 🧪 Integration tests — error log rows are saved by a real database (#312)
 * ============================================================================
 *
 * tblErrorLog.errorTitle is VARCHAR(500) and the application's database
 * session runs in strict mode, so a long error message used to make the INSERT
 * fail. These tests drive the real g2ml_logError() and read the row back. They
 * also check that function argument values are not stored.
 *
 * Every row made here has a title starting with a marker, and is deleted again.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #312
 * ============================================================================
 */

declare(strict_types=1);

// $db is provided by run_integration.php's script scope (a connected mysqli).
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// Point the application database layer (getDB) at the same test server. Each
// constant is guarded so this file works beside the other integration files.
$g2mlErrorLogTestSettings = array(
    'DB_HOST' => array('G2ML_TEST_DB_HOST', '127.0.0.1'),
    'DB_PORT' => array('G2ML_TEST_DB_PORT', '3306'),
    'DB_USER' => array('G2ML_TEST_DB_USER', 'root'),
    'DB_PASS' => array('G2ML_TEST_DB_PASS', ''),
    'DB_NAME' => array('G2ML_TEST_DB_NAME', 'mwtools_Go2MyLink'),
);

foreach ($g2mlErrorLogTestSettings as $g2mlErrorLogTestConstant => $g2mlErrorLogTestSource)
{
    if (!defined($g2mlErrorLogTestConstant))
    {
        $g2mlErrorLogTestValue = getenv($g2mlErrorLogTestSource[0]);

        if ($g2mlErrorLogTestValue === false || $g2mlErrorLogTestValue === '')
        {
            $g2mlErrorLogTestValue = $g2mlErrorLogTestSource[1];
        }

        if ($g2mlErrorLogTestConstant === 'DB_PORT')
        {
            $g2mlErrorLogTestValue = (int) $g2mlErrorLogTestValue;
        }

        define($g2mlErrorLogTestConstant, $g2mlErrorLogTestValue);
    }
}

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_connect.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_query.php';
require_once dirname(__DIR__, 2) . '/web/_functions/error_handler.php';

/**
 * Fetch, then delete, the rows whose title starts with the marker.
 *
 * @return array<int, array<string, mixed>>
 */
function g2mlErrorLogSaveTest_take(mysqli $db, string $marker): array
{
    $pattern = $marker . '%';

    $select = $db->prepare('SELECT * FROM `tblErrorLog` WHERE `errorTitle` LIKE ?');
    $select->bind_param('s', $pattern);
    $select->execute();
    $rows = $select->get_result()->fetch_all(MYSQLI_ASSOC);
    $select->close();

    $delete = $db->prepare('DELETE FROM `tblErrorLog` WHERE `errorTitle` LIKE ?');
    $delete->bind_param('s', $pattern);
    $delete->execute();
    $delete->close();

    return $rows;
}

/**
 * Call g2ml_logError() with PHP's error log sent to a temporary file, and
 * return what was written there.
 */
function g2mlErrorLogSaveTest_log(callable $callback): string
{
    $logFile = (string) tempnam(sys_get_temp_dir(), 'g2ml_errlog_');
    $before  = (string) ini_get('error_log');

    ini_set('error_log', $logFile);

    try
    {
        $callback();
    }
    finally
    {
        ini_set('error_log', $before);
    }

    $written = (string) file_get_contents($logFile);
    @unlink($logFile);

    return $written;
}

test('error log (database): a long multi-byte title is saved, cut to 500 characters (#312)', function () use ($db): void
{
    $marker = 'g2ml312long ';
    $title  = $marker . str_repeat('🔒é', 600) . 'END-OF-TITLE';

    $written = g2mlErrorLogSaveTest_log(function () use ($title): void
    {
        g2ml_logError('error', 1, $title);
    });

    $rows = g2mlErrorLogSaveTest_take($db, $marker);

    assert_same(1, count($rows), 'The row was not saved. Log: ' . $written);
    assert_same(mb_substr($title, 0, 500, 'UTF-8'), $rows[0]['errorTitle']);
    assert_contains('END-OF-TITLE', $written, 'The full title must still reach the log file');
});

test('error log (database): other over-long or badly encoded values do not lose the row (#312)', function () use ($db): void
{
    $marker = 'g2ml312other ';
    $server = $_SERVER;

    $_SERVER['REQUEST_URI']     = '/' . str_repeat('é', 900);
    $_SERVER['REQUEST_METHOD']  = str_repeat('M', 40);
    $_SERVER['HTTP_USER_AGENT'] = "agent \xFF";

    try
    {
        $written = g2mlErrorLogSaveTest_log(function () use ($marker): void
        {
            g2ml_logError(
                'warning',
                99999999999,
                $marker . "bad bytes \xC3 \xFF",
                str_repeat('é', 40000),
                str_repeat('f', 900),
                7,
                str_repeat('é', 9000)
            );
        });
    }
    finally
    {
        $_SERVER = $server;
    }

    $rows = g2mlErrorLogSaveTest_take($db, $marker);

    assert_same(1, count($rows), 'The row was not saved. Log: ' . substr($written, 0, 400));
    assert_same(500, mb_strlen((string) $rows[0]['requestURL'], 'UTF-8'));
    assert_same(500, strlen((string) $rows[0]['errorFile']));
    assert_same(10, strlen((string) $rows[0]['requestMethod']));
    assert_true(strlen((string) $rows[0]['errorDetail']) <= 65535);
    assert_contains('[truncated]', (string) $rows[0]['errorBacktrace']);
});

test('error log (database): an exception message holding a trace is stored without argument values (#312)', function () use ($db): void
{
    $marker = 'g2ml312secret ';
    $title  = $marker . "Uncaught Exception: x\nStack trace:\n#0 /srv/a.php(3): login('hunter2-pw')\n#1 {main}";

    g2mlErrorLogSaveTest_log(function () use ($title): void
    {
        g2ml_logError('exception', 0, $title, $title, '/srv/a.php', 3, $title);
    });

    $rows = g2mlErrorLogSaveTest_take($db, $marker);

    assert_same(1, count($rows));
    assert_true(strpos(serialize($rows[0]), 'hunter2') === false, 'The secret was stored');
    assert_contains('login()', (string) $rows[0]['errorTitle']);
    assert_contains('login()', (string) $rows[0]['errorDetail']);
    assert_contains('login()', (string) $rows[0]['errorBacktrace']);
});
