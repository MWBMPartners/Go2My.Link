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
 * 🧪 Go2My.Link — Integration Test Runner (DB-backed)
 * ============================================================================
 *
 * Discovers and runs every tests/integration/*.php against a MySQL server
 * described by environment variables:
 *
 *   G2ML_TEST_DB_SOCKET   Unix socket path (preferred on a local throwaway).
 *   G2ML_TEST_DB_HOST     Host name (used when no socket is given).
 *   G2ML_TEST_DB_PORT     Port (defaults to 3306 when a host is used).
 *   G2ML_TEST_DB_NAME     Database/schema name (defaults to mwtools_Go2MyLink).
 *   G2ML_TEST_DB_USER     User (defaults to 'root').
 *   G2ML_TEST_DB_PASS     Password (defaults to empty).
 *
 * If no database is reachable, the runner prints "SKIPPED (no test DB)" and
 * exits 0. It NEVER hard-fails merely because a test DB is unconfigured, so
 * an unconfigured CI environment stays green. A reachable DB whose tests fail
 * DOES exit non-zero.
 *
 * Each integration test file is expected to define a function
 * g2ml_register_integration_tests(mysqli $db) that registers test() cases
 * using the bootstrap harness.
 *
 * Usage:  php tests/run_integration.php
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      Phase v1.0.0 (autopilot STABILIZE)
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$_SESSION = array();

/**
 * Read an environment variable, returning a default when it is unset or empty.
 *
 * @param  string $name
 * @param  string $default
 * @return string
 */
function g2ml_test_env(string $name, string $default): string
{
    $value = getenv($name);

    if ($value === false)
    {
        return $default;
    }

    if ($value === '')
    {
        return $default;
    }

    return $value;
}

// ----------------------------------------------------------------------------
// Resolve connection parameters from the environment.
// ----------------------------------------------------------------------------
$socket   = getenv('G2ML_TEST_DB_SOCKET');
$host     = g2ml_test_env('G2ML_TEST_DB_HOST', '127.0.0.1');
$portRaw  = g2ml_test_env('G2ML_TEST_DB_PORT', '3306');
$port     = (int) $portRaw;
$database = g2ml_test_env('G2ML_TEST_DB_NAME', 'mwtools_Go2MyLink');
$user     = g2ml_test_env('G2ML_TEST_DB_USER', 'root');
$password = g2ml_test_env('G2ML_TEST_DB_PASS', '');

// Suppress mysqli exceptions/warnings so we can detect "no DB" gracefully.
mysqli_report(MYSQLI_REPORT_OFF);

/**
 * Attempt a connection. Returns a mysqli instance or null if unreachable.
 *
 * @param  string|false $socket
 * @param  string       $host
 * @param  int          $port
 * @param  string       $database
 * @param  string       $user
 * @param  string       $password
 * @return mysqli|null
 */
function g2ml_test_connect(string|false $socket, string $host, int $port, string $database, string $user, string $password): ?mysqli
{
    $connection = @mysqli_init();

    if ($connection === false)
    {
        return null;
    }

    if ($socket !== false && $socket !== '')
    {
        $opened = @mysqli_real_connect($connection, null, $user, $password, $database, 0, $socket);
    }
    else
    {
        $opened = @mysqli_real_connect($connection, $host, $user, $password, $database, $port);
    }

    if ($opened === false)
    {
        return null;
    }

    return $connection;
}

$db = g2ml_test_connect($socket, $host, $port, $database, $user, $password);

if ($db === null)
{
    echo 'SKIPPED (no test DB)' . PHP_EOL;
    exit(0);
}

mysqli_set_charset($db, 'utf8mb4');

// ----------------------------------------------------------------------------
// Discover and register integration tests.
// ----------------------------------------------------------------------------
$integrationDirectory = __DIR__ . '/integration';
$pattern              = $integrationDirectory . '/*.php';
$testFiles            = glob($pattern);

if ($testFiles === false || count($testFiles) === 0)
{
    echo 'No integration tests found in ' . $integrationDirectory . PHP_EOL;
    mysqli_close($db);
    exit(0);
}

sort($testFiles);

foreach ($testFiles as $testFile)
{
    require_once $testFile;
}

if (function_exists('g2ml_register_integration_tests'))
{
    g2ml_register_integration_tests($db);
}

// ----------------------------------------------------------------------------
// Run and gate.
// ----------------------------------------------------------------------------
$failureCount = g2ml_test_run_all();

mysqli_close($db);

if ($failureCount > 0)
{
    exit(1);
}

exit(0);
