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
 * 🧪 Integration tests — no secret from a web address reaches a log table (#323)
 * ============================================================================
 *
 * Drives the real g2ml_logError(), logActivity() and g2ml_apiLogRequest() with
 * made-up secrets in the address, the referrer and the details, then reads the
 * rows back and checks that no secret was stored. Also checks that the
 * campaign values recorded for click statistics are stored unchanged.
 *
 * Every row made here carries a marker and is deleted again.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #323
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
$g2mlLogUrlDbSettings = array(
    'DB_HOST' => array('G2ML_TEST_DB_HOST', '127.0.0.1'),
    'DB_PORT' => array('G2ML_TEST_DB_PORT', '3306'),
    'DB_USER' => array('G2ML_TEST_DB_USER', 'root'),
    'DB_PASS' => array('G2ML_TEST_DB_PASS', ''),
    'DB_NAME' => array('G2ML_TEST_DB_NAME', 'mwtools_Go2MyLink'),
);

foreach ($g2mlLogUrlDbSettings as $g2mlLogUrlDbConstant => $g2mlLogUrlDbSource)
{
    if (!defined($g2mlLogUrlDbConstant))
    {
        $g2mlLogUrlDbValue = getenv($g2mlLogUrlDbSource[0]);

        if ($g2mlLogUrlDbValue === false || $g2mlLogUrlDbValue === '')
        {
            $g2mlLogUrlDbValue = $g2mlLogUrlDbSource[1];
        }

        if ($g2mlLogUrlDbConstant === 'DB_PORT')
        {
            $g2mlLogUrlDbValue = (int) $g2mlLogUrlDbValue;
        }

        define($g2mlLogUrlDbConstant, $g2mlLogUrlDbValue);
    }
}

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_connect.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_query.php';
require_once dirname(__DIR__, 2) . '/web/_functions/settings.php';
require_once dirname(__DIR__, 2) . '/web/_functions/error_handler.php';
require_once dirname(__DIR__, 2) . '/web/_functions/activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';

/**
 * Fetch, then delete, the rows of one log table whose marker column matches.
 * The table and column names are fixed values from the tests below.
 *
 * @return array<int, array<string, mixed>>
 */
function g2mlLogUrlDbTest_take(mysqli $db, string $table, string $column, string $marker): array
{
    $pattern = '%' . $marker . '%';

    $select = $db->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '` LIKE ?');
    $select->bind_param('s', $pattern);
    $select->execute();
    $rows = $select->get_result()->fetch_all(MYSQLI_ASSOC);
    $select->close();

    $delete = $db->prepare('DELETE FROM `' . $table . '` WHERE `' . $column . '` LIKE ?');
    $delete->bind_param('s', $pattern);
    $delete->execute();
    $delete->close();

    return $rows;
}

/**
 * Run a callback with a pretend request whose address and referrer hold secrets.
 */
function g2mlLogUrlDbTest_request(callable $callback): void
{
    $server = $_SERVER;

    $_SERVER['REQUEST_URI']    = '/reset-password?token=s3cret-uri&utm_source=mail';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST']      = 'go2my.link';
    $_SERVER['HTTP_REFERER']   = 'https://go2my.link/verify-email?token=s3cret-referrer';
    $_SERVER['REMOTE_ADDR']    = '192.0.2.23';
    unset($_SERVER['HTTP_DNT'], $_SERVER['HTTP_SEC_GPC']);

    try
    {
        $callback();
    }
    finally
    {
        $_SERVER = $server;
    }
}

test('log tables: the error log stores no token from the address, referrer or message (#323)', function () use ($db): void
{
    g2mlLogUrlDbTest_request(function (): void
    {
        g2ml_logError(
            'warning',
            323,
            'g2ml323err at /invite?token=s3cret-title',
            'See https://go2my.link/reset-password?token=s3cret-detail',
            '/srv/a.php',
            1,
            '#0 /srv/a.php(1): go() /verify-email?token=s3cret-trace'
        );
    });

    $rows = g2mlLogUrlDbTest_take($db, 'tblErrorLog', 'errorTitle', 'g2ml323err');

    assert_same(1, count($rows));
    assert_true(strpos((string) json_encode($rows[0]), 's3cret') === false, (string) json_encode($rows[0]));
    assert_same('/reset-password?token=[redacted]&utm_source=mail', $rows[0]['requestURL']);

    $headers = json_decode((string) $rows[0]['requestHeaders'], true);

    assert_same('https://go2my.link/verify-email?token=[redacted]', $headers['HTTP_REFERER']);
});

test('log tables: the activity log stores no token, and keeps tracking values (#323)', function () use ($db): void
{
    g2mlLogUrlDbTest_request(function (): void
    {
        assert_true(logActivity('consent_recorded', 'success', 200, [
            'shortCode' => 'g2ml323act',
            'logData'   => [
                'note'           => '/invite?token=s3cret-data',
                'trackingParams' => ['utm_source' => 'mail'],
            ],
        ]));
    });

    $rows = g2mlLogUrlDbTest_take($db, 'tblActivityLog', 'shortCode', 'g2ml323act');

    assert_same(1, count($rows));
    assert_true(strpos((string) json_encode($rows[0]), 's3cret') === false, (string) json_encode($rows[0]));
    assert_same('/reset-password?token=[redacted]&utm_source=mail', $rows[0]['requestPath']);
    assert_same('https://go2my.link/verify-email?token=[redacted]', $rows[0]['requestReferer']);

    $logData = json_decode((string) $rows[0]['logData'], true);

    assert_same('mail', $logData['trackingParams']['utm_source']);
});

test('log tables: the API request log stores no token from the route or the body (#323)', function () use ($db): void
{
    g2ml_apiLogRequest(
        null,
        '/api/v1/g2ml323api/g2ml_AbCdEfGh_' . str_repeat('s3cret', 7),
        'POST',
        404,
        1,
        '192.0.2.23',
        'test',
        ['longURL' => 'https://example.com/join?token=s3cret-body']
    );

    $rows = g2mlLogUrlDbTest_take($db, 'tblAPIRequestLog', 'endpoint', 'g2ml323api');

    assert_same(1, count($rows));
    assert_true(strpos((string) json_encode($rows[0]), 's3cret') === false, (string) json_encode($rows[0]));
    assert_same('/api/v1/g2ml323api/[redacted]', $rows[0]['endpoint']);
});
