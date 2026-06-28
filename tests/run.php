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
 * 🧪 Go2My.Link — Unit Test Runner (DB-FREE)
 * ============================================================================
 *
 * Discovers and runs every tests/unit/*.php file, prints a PASS/FAIL line per
 * test plus a summary, and exits non-zero if anything failed (so CI can gate).
 *
 * Usage:  php tests/run.php
 *
 * This runner is intentionally DB-FREE. It defines throwaway encryption
 * constants of valid length BEFORE any application file is loaded, and starts
 * $_SESSION as a plain array so CSRF tests can exercise the session-backed
 * token store without an active PHP session. It does NOT touch
 * web/_auth_keys.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      Phase v1.0.0 (autopilot STABILIZE)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🔧 Test environment constants — defined BEFORE loading any app file.
// ============================================================================
// g2ml_encrypt / g2ml_decrypt expect ENCRYPTION_SALT to be a non-empty hex
// string that is not the install placeholder. A 64-char hex string is used to
// mirror a real deployment key. These values are throwaway and exist only for
// the duration of this process.
if (!defined('ENCRYPTION_SALT'))
{
    define('ENCRYPTION_SALT', str_repeat('a1b2c3d4', 8));
}

if (!defined('ENCRYPTION_KEY_SECONDARY'))
{
    define('ENCRYPTION_KEY_SECONDARY', str_repeat('9f8e7d6c', 8));
}

// ============================================================================
// 🗂️ Session as a plain array (no real session is started under CLI).
// ============================================================================
// The CSRF helpers read and write $_SESSION['_csrf_tokens']. Initialising it
// as an empty array lets those helpers work without session_start().
$_SESSION = array();

// ============================================================================
// 🧰 Load the harness, then discover and load unit tests.
// ============================================================================
require_once __DIR__ . '/bootstrap.php';

$unitDirectory = __DIR__ . '/unit';
$pattern       = $unitDirectory . '/*.php';
$testFiles     = glob($pattern);

if ($testFiles === false)
{
    echo 'ERROR: could not read the unit test directory: ' . $unitDirectory . PHP_EOL;
    exit(1);
}

sort($testFiles);

if (count($testFiles) === 0)
{
    echo 'No unit tests found in ' . $unitDirectory . PHP_EOL;
    exit(0);
}

foreach ($testFiles as $testFile)
{
    require_once $testFile;
}

// ============================================================================
// 🏃 Run and gate.
// ============================================================================
$failureCount = g2ml_test_run_all();

if ($failureCount > 0)
{
    exit(1);
}

exit(0);
