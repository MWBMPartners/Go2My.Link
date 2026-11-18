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
 * 🧪 Integration test — getDB() notices a dead connection and reconnects
 * ============================================================================
 *
 * getDB() keeps one connection for the whole request. Before it hands that
 * connection back it checks the connection is still alive, and if it is not
 * it opens a new one. The check used to be mysqli::ping(), which PHP has
 * marked as deprecated; it is now g2ml_dbConnectionIsAlive() (runs "DO 1").
 *
 * This test describes the behaviour that must stay the same whichever check
 * is used:
 *   1. Two getDB() calls in a row return the very same connection.
 *   2. When the server side of that connection is killed, the check says the
 *      old connection is dead, and the next getDB() call returns a new,
 *      working connection (a different MySQL thread id).
 *
 * The connection is killed with "KILL <id>" sent from the test runner's own
 * separate $db connection.
 *
 * Registration model: registers its cases at include time, using the $db
 * handle from run_integration.php's script scope (see activity_log_test.php).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// Point getDB() at the same test database the runner uses.
if (!defined('DB_HOST'))
{
    $g2mlAliveTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlAliveTestHost === false || $g2mlAliveTestHost === '')
    {
        $g2mlAliveTestHost = '127.0.0.1';
    }

    $g2mlAliveTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlAliveTestPortRaw === false || $g2mlAliveTestPortRaw === '')
    {
        $g2mlAliveTestPortRaw = '3306';
    }

    $g2mlAliveTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlAliveTestName === false || $g2mlAliveTestName === '')
    {
        $g2mlAliveTestName = 'mwtools_Go2MyLink';
    }

    $g2mlAliveTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlAliveTestUser === false || $g2mlAliveTestUser === '')
    {
        $g2mlAliveTestUser = 'root';
    }

    $g2mlAliveTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlAliveTestPass === false)
    {
        $g2mlAliveTestPass = '';
    }

    define('DB_HOST', $g2mlAliveTestHost);
    define('DB_PORT', (int) $g2mlAliveTestPortRaw);
    define('DB_NAME', $g2mlAliveTestName);
    define('DB_USER', $g2mlAliveTestUser);
    define('DB_PASS', $g2mlAliveTestPass);
    define('DB_CHARSET', 'utf8mb4');
}

require_once dirname(__DIR__, 2) . '/web/_functions/db_connect.php';

/**
 * Ask whether a connection is alive, using whatever check getDB() uses.
 *
 * @param  mysqli $connection
 * @return bool
 */
function g2ml_alivetest_is_alive(mysqli $connection): bool
{
    if (function_exists('g2ml_dbConnectionIsAlive'))
    {
        return g2ml_dbConnectionIsAlive($connection);
    }

    // Older code, before the helper existed: the deprecated ping.
    return @$connection->ping();
}

/**
 * Read the MySQL thread id of a connection.
 *
 * @param  mysqli $connection
 * @return int
 */
function g2ml_alivetest_thread_id(mysqli $connection): int
{
    return (int) $connection->thread_id;
}

test('getDB: two calls in a row return the same connection', function (): void
{
    $first  = getDB();
    $second = getDB();

    assert_true($first instanceof mysqli, 'getDB() returns a connection');
    assert_true($second instanceof mysqli, 'getDB() returns a connection the second time too');
    assert_true($first === $second, 'The second call reuses the first connection');
    assert_true(g2ml_alivetest_is_alive($first), 'A healthy connection is reported alive');
});

test('getDB: a killed connection is noticed and replaced by a working one', function () use ($db): void
{
    $old = getDB();
    assert_true($old instanceof mysqli, 'Setup: getDB() returns a connection');

    $oldThreadId = g2ml_alivetest_thread_id($old);
    assert_true($oldThreadId > 0, 'Setup: the connection has a real thread id');

    // Kill it from the runner's own, separate connection.
    $killed = mysqli_query($db, 'KILL ' . $oldThreadId);
    assert_true($killed !== false, 'Setup: the KILL statement was accepted');

    // Give the server a moment to close the connection.
    usleep(200000);

    assert_same(false, g2ml_alivetest_is_alive($old), 'The old connection is reported dead');

    $new = getDB();
    assert_true($new instanceof mysqli, 'getDB() returns a connection after the old one died');
    assert_true($new !== $old, 'It is a new connection object');
    assert_true(g2ml_alivetest_thread_id($new) !== $oldThreadId, 'It is a different MySQL thread');

    $answer = $new->query('DO 1');
    assert_true($answer !== false, 'The new connection answers a query');
    assert_true(g2ml_alivetest_is_alive($new), 'The new connection is reported alive');
});

test('g2ml_dbConnectionIsAlive: an error that is not a lost connection is thrown, not hidden', function (): void
{
    if (!function_exists('g2ml_dbConnectionIsAlive'))
    {
        // Older code, before the helper existed: nothing to check.
        assert_true(true);

        return;
    }

    // A private connection, so the shared one is left alone.
    $private = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);

    // Leave a result unread. The next statement then fails with
    // "Commands out of sync" (error 2014): the connection is fine, the
    // caller has made a mistake, and it must not look like a dead connection.
    $unread = $private->query('SELECT 1 UNION SELECT 2', MYSQLI_USE_RESULT);
    assert_true($unread instanceof mysqli_result, 'Setup: an unbuffered result was left unread');

    $thrown = null;

    try
    {
        g2ml_dbConnectionIsAlive($private);
    }
    catch (mysqli_sql_exception $exception)
    {
        $thrown = $exception;
    }

    $unread->free();
    $private->close();

    assert_true($thrown instanceof mysqli_sql_exception, 'The helper throws instead of returning false');
    assert_same(2014, $thrown->getCode(), 'It is the "Commands out of sync" error');
});
