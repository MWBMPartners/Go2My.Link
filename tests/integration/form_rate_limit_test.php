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
 * 🧪 Integration tests — tblFormAttempts rate limiting (#212)
 * ============================================================================
 *
 * Drives the REAL g2ml_formRateLimitTake() and g2ml_formRateLimitCount()
 * against tblFormAttempts: counting per address and form, refusal at the
 * maximum, no limit at 0, the database lock, the one-hour window and the
 * deletion of day-old rows.
 *
 * Each test uses its own IP address (from the RFC 5737 TEST-NET-3
 * documentation range, 203.0.113.0/24 — already this codebase's convention,
 * see tests/unit/security_clientip_test.php) so the four cases cannot
 * contaminate one another's counts even though they share one table and run
 * in the same process.
 *
 * Registration model: like entitlements_test.php and auth_register_test.php,
 * this file registers its cases at INCLUDE time using the $db handle from
 * run_integration.php's script scope. With no reachable test DB the runner
 * skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#212)
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
// Point the application DB layer (getDB()) at the SAME throwaway server that
// $db is already connected to, mirroring auth_register_test.php's / entitle-
// ments_test.php's own env resolution. Guarded so this composes with any
// other integration file that already defined these.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlFormRateLimitTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlFormRateLimitTestHost === false || $g2mlFormRateLimitTestHost === '')
    {
        $g2mlFormRateLimitTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlFormRateLimitTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlFormRateLimitTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlFormRateLimitTestPortRaw === false || $g2mlFormRateLimitTestPortRaw === '')
    {
        $g2mlFormRateLimitTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlFormRateLimitTestPortRaw);
}

if (!defined('DB_NAME'))
{
    $g2mlFormRateLimitTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlFormRateLimitTestName === false || $g2mlFormRateLimitTestName === '')
    {
        $g2mlFormRateLimitTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlFormRateLimitTestName);
}

if (!defined('DB_USER'))
{
    $g2mlFormRateLimitTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlFormRateLimitTestUser === false || $g2mlFormRateLimitTestUser === '')
    {
        $g2mlFormRateLimitTestUser = 'root';
    }

    define('DB_USER', $g2mlFormRateLimitTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlFormRateLimitTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlFormRateLimitTestPass === false)
    {
        $g2mlFormRateLimitTestPass = '';
    }

    define('DB_PASS', $g2mlFormRateLimitTestPass);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('ENCRYPTION_KEY_SECONDARY'))
{
    // Same throwaway 64-char hex value tests/run.php defines for the
    // DB-free unit suite — this file needs it too, because
    // g2ml_formRateLimitIpHash() uses it as the HMAC key.
    define('ENCRYPTION_KEY_SECONDARY', str_repeat('9f8e7d6c', 8));
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// require_once is idempotent, so this is safe even if a sibling test file
// already loaded some of these.
// ----------------------------------------------------------------------------
$g2mlFormRateLimitFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlFormRateLimitFunctionsDir . '/db_connect.php';
require_once $g2mlFormRateLimitFunctionsDir . '/db_query.php';
require_once $g2mlFormRateLimitFunctionsDir . '/security.php';
require_once $g2mlFormRateLimitFunctionsDir . '/form_rate_limit.php';

/**
 * Insert one tblFormAttempts row with a caller-chosen createdAt, bypassing
 * g2ml_formRateLimitTake() (which always stamps CURRENT_TIMESTAMP) so a
 * test can create a row that is deliberately OUTSIDE the counting window.
 *
 * @param  mysqli $db
 * @param  string $formKey
 * @param  string $ipHash
 * @param  string $createdAtSql  A SQL expression for createdAt, e.g.
 *                                'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)'
 * @return void
 */
function g2ml_formratelimit_test_insertBackdated(mysqli $db, string $formKey, string $ipHash, string $createdAtSql): void
{
    // $createdAtSql is a fixed literal chosen by the test below, never
    // request input, so building it into the SQL text is safe here — the
    // two genuinely untrusted values (formKey, ipHash) are still bound as
    // prepared-statement parameters.
    $sql = "INSERT INTO `tblFormAttempts` (`formKey`, `ipHash`, `createdAt`) VALUES (?, ?, " . $createdAtSql . ")";

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (insert backdated attempt) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $formKey, $ipHash);

    $executed = mysqli_stmt_execute($statement);

    mysqli_stmt_close($statement);

    if ($executed === false)
    {
        throw new RuntimeException('Execute (insert backdated attempt) failed: ' . mysqli_error($db));
    }
}

/**
 * Delete every tblFormAttempts row for one (formKey, ipHash) pair, so each
 * test below starts and ends with a clean slate for its own IP address.
 *
 * @param  mysqli $db
 * @param  string $formKey
 * @param  string $ipHash
 * @return void
 */
function g2ml_formratelimit_test_cleanup(mysqli $db, string $formKey, string $ipHash): void
{
    $statement = mysqli_prepare($db, "DELETE FROM `tblFormAttempts` WHERE `formKey` = ? AND `ipHash` = ?");

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (cleanup) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $formKey, $ipHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Taking attempts records them, per address and per form.
// ----------------------------------------------------------------------------
test('g2ml_formRateLimitTake: records allowed attempts, separately per address and per form', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
    $otherIpHash            = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $otherIpHash);

    $_SERVER['REMOTE_ADDR'] = '203.0.113.41';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
    g2ml_formratelimit_test_cleanup($db, 'register', $ipHash);

    for ($attempt = 1; $attempt <= 3; $attempt++)
    {
        assert_same('allowed', g2ml_formRateLimitTake('contact', 3), 'Attempt ' . $attempt . ' of 3 must be allowed');
    }

    assert_same('limited', g2ml_formRateLimitTake('contact', 3), 'The attempt after the maximum must be refused');
    assert_same(3, g2ml_formRateLimitCount('contact', 3600), 'A refused attempt must not be recorded');
    assert_same(0, g2ml_formRateLimitCount('register', 3600), 'Another form must have its own count');

    $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
    assert_same(0, g2ml_formRateLimitCount('contact', 3600), 'Another address must have its own count');

    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
});

test('g2ml_formRateLimitTake: 0 means no limit and stores nothing', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.44';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'register', $ipHash);

    assert_same('allowed', g2ml_formRateLimitTake('register', 0), 'A limit of 0 must allow');
    assert_same(0, g2ml_formRateLimitCount('register', 3600), 'Nothing is stored when there is no limit');
});

test('g2ml_formRateLimitTake: an unknown form is refused', function (): void
{
    assert_same('unavailable', g2ml_formRateLimitTake('not_a_form', 5), 'An unknown form key must be refused');
});

// A second database connection holds the lock, standing in for a request that
// is part-way through taking an attempt.
test('g2ml_formRateLimitTake: waits for the lock and refuses if it cannot get it', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.46';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    $lockName = 'g2ml_form_' . substr(hash('sha256', 'contact|' . $ipHash), 0, 40);
    $other    = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $held     = mysqli_query($other, "SELECT GET_LOCK('" . $lockName . "', 0) AS `gotLock`")->fetch_assoc();
    assert_same(1, (int) $held['gotLock'], 'The test must be holding the lock');

    assert_same('unavailable', g2ml_formRateLimitTake('contact', 5), 'While another request holds the lock, this one must not go ahead');
    assert_same(0, g2ml_formRateLimitCount('contact', 3600), 'Nothing may be recorded without the lock');

    mysqli_close($other);
    assert_same('allowed', g2ml_formRateLimitTake('contact', 5), 'Once the lock is free the attempt goes ahead');

    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
});

// ----------------------------------------------------------------------------
// A row older than the counting window is ignored.
// ----------------------------------------------------------------------------
test('g2ml_formRateLimitCount: a row older than the window does not count', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.45';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)');
    assert_same('allowed', g2ml_formRateLimitTake('contact', 1), 'A 2-hour-old row must not use up the limit');
    assert_same(1, g2ml_formRateLimitCount('contact', 3600), 'Only the new row is inside the window');
    assert_same(2, g2ml_formRateLimitCount('contact', 3 * 3600), 'A wider window sees both rows');

    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
});

test('g2ml_formRateLimitTake: deletes rows older than a day', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.47';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
    assert_same('allowed', g2ml_formRateLimitTake('contact', 5), 'The attempt must be allowed');
    assert_same(1, g2ml_formRateLimitCount('contact', 7 * 86400), 'The 2-day-old row must be gone');

    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
});

// ----------------------------------------------------------------------------
// The retention clean-up removes old rows even when no form is submitted.
// ----------------------------------------------------------------------------
require_once dirname(__DIR__, 2) . '/web/_functions/cron.php';

test('g2ml_retentionPurgeFormAttempts: removes day-old rows and keeps newer ones', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.48';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)');

    $report = g2ml_retentionPurgeFormAttempts(100, microtime(true) + 10);

    assert_same(false, $report['partial'], 'The sweep must finish inside its time budget');
    assert_same(1, g2ml_formRateLimitCount('contact', 7 * 86400), 'Only the 2-hour-old row must remain');

    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);
});

test('g2ml_cronRunRetentionJob: clears old form attempts even with retention switched off', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.49';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
    setSetting('retention.enforcement_enabled', false, 'System');

    $report = g2ml_cronRunRetentionJob(10, 100);

    assert_same(false, $report['enabled'], 'The other sweeps stay off');
    assert_true($report['forms']['swept'] >= 1, 'The old form attempt was swept');
    assert_same(0, g2ml_formRateLimitCount('contact', 7 * 86400), 'No old row remains');
});

test('g2ml_cronMaybeRunProbabilisticRetention: an ordinary page visit clears old form attempts with retention off', function () use ($db): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
    $ipHash                 = g2ml_formRateLimitIpHash();
    g2ml_formratelimit_test_cleanup($db, 'contact', $ipHash);

    g2ml_formratelimit_test_insertBackdated($db, 'contact', $ipHash, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
    setSetting('retention.enforcement_enabled', false, 'System');
    $oldDivisor = getSetting('retention.probabilistic_divisor', 500);
    setSetting('retention.probabilistic_divisor', 1, 'System');

    g2ml_cronMaybeRunProbabilisticRetention();

    setSetting('retention.probabilistic_divisor', $oldDivisor, 'System');
    assert_same(0, g2ml_formRateLimitCount('contact', 7 * 86400), 'No old row remains');
});
