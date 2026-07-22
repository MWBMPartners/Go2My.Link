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
 * 🧪 Integration tests — #167 retention sweeps (web/_functions/cron.php)
 * ============================================================================
 *
 * Drives the REAL g2ml_cronRunRetentionJob() and its sub-sweeps against a
 * freshly imported test database:
 *
 *   - the master gate (retention.enforcement_enabled) is a true no-op when off;
 *   - the activity-log ANONYMISE sweep sets ipAddress/requestUserAgent on rows
 *     past the age threshold, leaves recent rows untouched, and converges to
 *     zero on a re-run (idempotent);
 *   - the anonymise sweep loops correctly across multiple small batches;
 *   - the activity-log PURGE sweep stays off by default and, when enabled with
 *     a window narrower than the anonymise window, skips itself with a
 *     logged warning rather than destroying not-yet-anonymised rows;
 *   - the expired-export sweep unlinks an expired, PATH-CONFINED file and
 *     NULLs its DB path, leaves an unexpired row+file alone, and — for a
 *     tampered/escaping path — NEVER unlinks the file but still NULLs the
 *     path;
 *   - the session sweep removes an expired session row;
 *   - the advisory lock genuinely blocks a concurrent acquire from a SECOND
 *     connection, and releases cleanly afterwards.
 *
 * Registration model mirrors cron_deletion_job_test.php / settings_scope_
 * dedupe_test.php: cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed
 * g2ml_cronret_test_* to stay unique alongside sibling integration files
 * (including cron_deletion_job_test.php's own g2ml_cronjob_test_* helpers).
 * With no reachable test DB the runner skips before this file is ever
 * included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.7.0 — GDPR Scheduled Jobs (#163, #167)
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

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlCronRetTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlCronRetTestHost === false || $g2mlCronRetTestHost === '')
    {
        $g2mlCronRetTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlCronRetTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlCronRetTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlCronRetTestPortRaw === false || $g2mlCronRetTestPortRaw === '')
    {
        $g2mlCronRetTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlCronRetTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlCronRetTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlCronRetTestUser === false || $g2mlCronRetTestUser === '')
    {
        $g2mlCronRetTestUser = 'root';
    }

    define('DB_USER', $g2mlCronRetTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlCronRetTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlCronRetTestPass === false)
    {
        $g2mlCronRetTestPass = '';
    }

    define('DB_PASS', $g2mlCronRetTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlCronRetTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlCronRetTestName === false || $g2mlCronRetTestName === '')
    {
        $g2mlCronRetTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlCronRetTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// g2ml_retentionPurgeExpiredExports() writes/reads under
// G2ML_UPLOADS . '/exports/'. Point it at a throwaway directory under the
// system temp path, guarded so this composes with any sibling integration
// file (e.g. data_export_test.php) that defines it first.
// ----------------------------------------------------------------------------
if (!defined('G2ML_UPLOADS'))
{
    $g2mlCronRetUploadsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_it_uploads';

    if (!is_dir($g2mlCronRetUploadsDir))
    {
        mkdir($g2mlCronRetUploadsDir, 0750, true);
    }

    define('G2ML_UPLOADS', $g2mlCronRetUploadsDir);
}

$g2mlCronRetExportsDir = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';

if (!is_dir($g2mlCronRetExportsDir))
{
    mkdir($g2mlCronRetExportsDir, 0750, true);
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlCronRetFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlCronRetFunctionsDir . '/db_connect.php';
require_once $g2mlCronRetFunctionsDir . '/db_query.php';
require_once $g2mlCronRetFunctionsDir . '/security.php';
require_once $g2mlCronRetFunctionsDir . '/settings.php';
require_once $g2mlCronRetFunctionsDir . '/activity_logger.php';
require_once $g2mlCronRetFunctionsDir . '/data_rights.php';
require_once $g2mlCronRetFunctionsDir . '/session.php';
require_once $g2mlCronRetFunctionsDir . '/cron.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Insert a throwaway, active user in the [default] org and return its userUID.
 * (The retention sweeps under test do not read tblUsers, but tblUserSessions
 * carries a NOT NULL FK to it.)
 *
 * @param  mysqli $db
 * @param  string $marker
 * @return int
 */
function g2ml_cronret_test_insert_user(mysqli $db, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) '
        . 'VALUES (\'[default]\', ?, ?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@cronret167.test';
    $passwordHash = 'x';
    $firstName    = 'Cron';
    $lastName     = 'Retention';

    mysqli_stmt_bind_param($statement, 'sssss', $username, $email, $passwordHash, $firstName, $lastName);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
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
 * Insert a tblActivityLog row with an explicit createdAt (days ago), ipAddress,
 * and requestUserAgent, for a given userUID. Returns the logUID.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  int    $daysAgo
 * @param  string $ipAddress
 * @param  string $userAgent
 * @return int
 */
function g2ml_cronret_test_insert_activity(mysqli $db, int $userUID, int $daysAgo, string $ipAddress, string $userAgent): int
{
    $createdAt = gmdate('Y-m-d H:i:s', time() - ($daysAgo * 86400));
    $logAction = 'cronret167_test_hit';

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblActivityLog` (`logAction`, `userUID`, `ipAddress`, `requestUserAgent`, `createdAt`) '
        . 'VALUES (?, ?, ?, ?, ?)'
    );

    mysqli_stmt_bind_param($statement, 'sisss', $logAction, $userUID, $ipAddress, $userAgent, $createdAt);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (activity log) failed: ' . $error);
    }

    $logUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $logUID;
}

/**
 * Read back a tblActivityLog row's ipAddress/requestUserAgent by logUID.
 *
 * @param  mysqli $db
 * @param  int    $logUID
 * @return array<string, mixed>|null
 */
function g2ml_cronret_test_fetch_activity(mysqli $db, int $logUID): ?array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `ipAddress`, `requestUserAgent` FROM `tblActivityLog` WHERE `logUID` = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $logUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return null;
    }

    return $row;
}

/**
 * Delete every tblActivityLog row for a userUID. Idempotent.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return void
 */
function g2ml_cronret_test_cleanup_activity(mysqli $db, int $userUID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblActivityLog` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $userUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Delete a user (and, via FK cascade, its sessions/consent rows). Idempotent.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return void
 */
function g2ml_cronret_test_cleanup_user(mysqli $db, int $userUID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $userUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Insert a tblDataDeletionRequests export-type row with a caller-controlled
 * exportFilePath and exportExpiresAt (expressed as "hours from now", negative
 * for already-expired). Returns the requestUID.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $exportFilePath
 * @param  int    $expiresInHours
 * @return int
 */
function g2ml_cronret_test_insert_export_request(mysqli $db, int $userUID, string $exportFilePath, int $expiresInHours): int
{
    $exportExpiresAt = gmdate('Y-m-d H:i:s', time() + ($expiresInHours * 3600));

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblDataDeletionRequests` (`userUID`, `requestType`, `status`, `exportFilePath`, `exportExpiresAt`) '
        . 'VALUES (?, \'export\', \'completed\', ?, ?)'
    );

    mysqli_stmt_bind_param($statement, 'iss', $userUID, $exportFilePath, $exportExpiresAt);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (export request) failed: ' . $error);
    }

    $requestUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $requestUID;
}

/**
 * Read back a tblDataDeletionRequests row's exportFilePath by requestUID.
 *
 * @param  mysqli $db
 * @param  int    $requestUID
 * @return array<string, mixed>|null
 */
function g2ml_cronret_test_fetch_export_request(mysqli $db, int $requestUID): ?array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `exportFilePath` FROM `tblDataDeletionRequests` WHERE `requestUID` = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $requestUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return null;
    }

    return $row;
}

/**
 * Insert an expired (or active) tblUserSessions row for a userUID.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $marker
 * @param  int    $expiresInPastSeconds  Positive = already expired.
 * @return int
 */
function g2ml_cronret_test_insert_session(mysqli $db, int $userUID, string $marker, int $expiresInPastSeconds): int
{
    $sessionToken = hash('sha256', $marker);
    $ipAddress    = '203.0.113.167';
    $expiresAt    = gmdate('Y-m-d H:i:s', time() - $expiresInPastSeconds);

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUserSessions` (`userUID`, `sessionToken`, `ipAddress`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, 1)'
    );

    mysqli_stmt_bind_param($statement, 'isss', $userUID, $sessionToken, $ipAddress, $expiresAt);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (session) failed: ' . $error);
    }

    $sessionUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $sessionUID;
}

/**
 * Count tblUserSessions rows for a given sessionUID.
 *
 * @param  mysqli $db
 * @param  int    $sessionUID
 * @return int
 */
function g2ml_cronret_test_count_session(mysqli $db, int $sessionUID): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM `tblUserSessions` WHERE `sessionUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $sessionUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

// ----------------------------------------------------------------------------
// Ensure the [default] org + free tier exist (FK targets for tblUsers).
// ----------------------------------------------------------------------------
$g2mlCronRetSetupResult = mysqli_query(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

if ($g2mlCronRetSetupResult === false)
{
    throw new RuntimeException('Setup query failed (tblSubscriptionTiers): ' . mysqli_error($db));
}

$g2mlCronRetSetupResult = mysqli_query(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

if ($g2mlCronRetSetupResult === false)
{
    throw new RuntimeException('Setup query failed (tblOrganisations): ' . mysqli_error($db));
}

// ============================================================================
// (1) Master gate — a total no-op when off.
// ============================================================================
test('cron retention (#167): master gate off — report {enabled:false}, rows untouched', function () use ($db): void
{
    $marker  = 'cronret167_gate_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_cronret_test_insert_user($db, $marker);
    $logUID  = g2ml_cronret_test_insert_activity($db, $userUID, 200, '198.51.100.10', 'TestAgent/1.0');

    setSetting('retention.enforcement_enabled', false, 'System');

    $report = g2ml_cronRunRetentionJob(30, 500);

    assert_same(false, $report['enabled'], 'A disabled retention job reports enabled:false');

    $row = g2ml_cronret_test_fetch_activity($db, $logUID);
    assert_same('198.51.100.10', $row['ipAddress'], 'ipAddress untouched while the master gate is off');

    g2ml_cronret_test_cleanup_activity($db, $userUID);
    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (2) Anonymise sweep — old row anonymised, recent row untouched, idempotent.
// ============================================================================
test('cron retention (#167): anonymise sweep treats old rows, leaves recent rows alone, and converges on re-run', function () use ($db): void
{
    $marker    = 'cronret167_anon_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID   = g2ml_cronret_test_insert_user($db, $marker);
    $oldLogUID = g2ml_cronret_test_insert_activity($db, $userUID, 91, '198.51.100.20', 'OldAgent/1.0');
    $newLogUID = g2ml_cronret_test_insert_activity($db, $userUID, 89, '198.51.100.21', 'NewAgent/1.0');

    setSetting('retention.enforcement_enabled', true, 'System');
    setSetting('retention.activity_log_anonymise_days', 90, 'System');
    setSetting('retention.activity_log_purge_days', 0, 'System');
    setSetting('retention.batch_size', 500, 'System');

    $firstReport = g2ml_cronRunRetentionJob(30, 500);

    assert_true($firstReport['anonymise']['swept'] >= 1, 'At least the one old row was swept');

    $oldRow = g2ml_cronret_test_fetch_activity($db, $oldLogUID);
    assert_same('0.0.0.0', $oldRow['ipAddress'], 'The 91-day-old row is anonymised');
    assert_same(null, $oldRow['requestUserAgent'], 'The 91-day-old row loses its user agent');

    $newRow = g2ml_cronret_test_fetch_activity($db, $newLogUID);
    assert_same('198.51.100.21', $newRow['ipAddress'], 'The 89-day-old row is left completely alone');
    assert_same('NewAgent/1.0', $newRow['requestUserAgent'], 'The 89-day-old row keeps its user agent');

    $secondReport = g2ml_cronRunRetentionJob(30, 500);
    assert_same(0, $secondReport['anonymise']['swept'], 'A re-run sweeps nothing further — idempotent');

    g2ml_cronret_test_cleanup_activity($db, $userUID);
    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (3) Batching — a small batch size loops across multiple chunks and
//     terminates having swept every eligible row.
// ============================================================================
test('cron retention (#167): the anonymise sweep loops across small batches and terminates', function () use ($db): void
{
    $marker   = 'cronret167_batch_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID  = g2ml_cronret_test_insert_user($db, $marker);
    $logUIDs  = [];

    for ($index = 0; $index < 3; $index++)
    {
        $logUIDs[] = g2ml_cronret_test_insert_activity($db, $userUID, 95, '198.51.100.3' . $index, 'BatchAgent/1.0');
    }

    $deadline = microtime(true) + 30;
    $report   = g2ml_retentionAnonymiseActivityLog(90, 1, $deadline);

    assert_same(3, $report['swept'], 'All three eligible rows were swept across multiple 1-row batches');
    assert_true($report['batches'] >= 3, 'The loop actually iterated multiple times (batch size 1, 3 rows)');
    assert_same(false, $report['partial'], 'The loop terminated on its own, not on the time budget');

    foreach ($logUIDs as $logUID)
    {
        $row = g2ml_cronret_test_fetch_activity($db, $logUID);
        assert_same('0.0.0.0', $row['ipAddress'], 'Every row in the batch was anonymised');
    }

    g2ml_cronret_test_cleanup_activity($db, $userUID);
    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (4) Purge default-off — rows remain (anonymised only), never deleted.
// ============================================================================
test('cron retention (#167): the purge sweep stays off by default — rows remain', function () use ($db): void
{
    $marker  = 'cronret167_purgeoff_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_cronret_test_insert_user($db, $marker);
    $logUID  = g2ml_cronret_test_insert_activity($db, $userUID, 200, '198.51.100.40', 'PurgeOffAgent/1.0');

    setSetting('retention.enforcement_enabled', true, 'System');
    setSetting('retention.activity_log_anonymise_days', 90, 'System');
    setSetting('retention.activity_log_purge_days', 0, 'System');
    setSetting('retention.batch_size', 500, 'System');

    $report = g2ml_cronRunRetentionJob(30, 500);

    assert_same('disabled', $report['purge']['skipped'], 'The purge sweep reports itself skipped:disabled');

    $row = g2ml_cronret_test_fetch_activity($db, $logUID);
    assert_true($row !== null, 'The 200-day-old row still exists — only anonymised, never purged');
    assert_same('0.0.0.0', $row['ipAddress'], 'The row was anonymised by the (still-active) anonymise sweep');

    g2ml_cronret_test_cleanup_activity($db, $userUID);
    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (5) Purge misconfiguration — a purge window narrower than the anonymise
//     window is refused rather than destroying not-yet-anonymised rows.
// ============================================================================
test('cron retention (#167): a misconfigured purge window (< anonymise window) is skipped', function () use ($db): void
{
    $marker  = 'cronret167_misconfig_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_cronret_test_insert_user($db, $marker);
    $logUID  = g2ml_cronret_test_insert_activity($db, $userUID, 200, '198.51.100.50', 'MisconfigAgent/1.0');

    setSetting('retention.enforcement_enabled', true, 'System');
    setSetting('retention.activity_log_anonymise_days', 90, 'System');
    setSetting('retention.activity_log_purge_days', 30, 'System');
    setSetting('retention.batch_size', 500, 'System');

    $report = g2ml_cronRunRetentionJob(30, 500);

    assert_same('misconfigured', $report['purge']['skipped'], 'purge_days (30) < anonymise_days (90) must be refused');

    $row = g2ml_cronret_test_fetch_activity($db, $logUID);
    assert_true($row !== null, 'The row was not deleted by the refused purge');

    g2ml_cronret_test_cleanup_activity($db, $userUID);
    g2ml_cronret_test_cleanup_user($db, $userUID);

    // Reset to a safe default for any later test in this process.
    setSetting('retention.activity_log_purge_days', 0, 'System');
});

// ============================================================================
// (6) Expired export sweep — confined file unlinked + path NULLed; unexpired
//     row + file left completely alone.
// ============================================================================
test('cron retention (#167): expired export sweep unlinks a confined file and NULLs the path; unexpired pair untouched', function () use ($db): void
{
    $marker  = 'cronret167_export_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_cronret_test_insert_user($db, $marker);

    $exportsDir = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';

    $expiredFilePath = $exportsDir . DIRECTORY_SEPARATOR . 'export_' . $marker . '_expired.json';
    file_put_contents($expiredFilePath, '{"marker":"' . $marker . '"}');
    $expiredRequestUID = g2ml_cronret_test_insert_export_request($db, $userUID, $expiredFilePath, -1);

    $activeFilePath = $exportsDir . DIRECTORY_SEPARATOR . 'export_' . $marker . '_active.json';
    file_put_contents($activeFilePath, '{"marker":"' . $marker . '"}');
    $activeRequestUID = g2ml_cronret_test_insert_export_request($db, $userUID, $activeFilePath, 48);

    $deadline = microtime(true) + 30;
    $report   = g2ml_retentionPurgeExpiredExports($deadline);

    assert_true($report['swept'] >= 1, 'At least the one expired export row was swept');

    assert_false(is_file($expiredFilePath), 'The expired, confined file was unlinked');
    $expiredRow = g2ml_cronret_test_fetch_export_request($db, $expiredRequestUID);
    assert_true($expiredRow !== null, 'The expired request ROW itself remains (audit trail)');
    assert_same(null, $expiredRow['exportFilePath'], 'exportFilePath is NULLed on the expired row');

    assert_true(is_file($activeFilePath), 'The unexpired file is left completely alone');
    $activeRow = g2ml_cronret_test_fetch_export_request($db, $activeRequestUID);
    assert_same($activeFilePath, $activeRow['exportFilePath'], 'The unexpired row keeps its exportFilePath');

    if (is_file($activeFilePath))
    {
        unlink($activeFilePath);
    }

    $cleanupStatement = mysqli_prepare($db, 'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($cleanupStatement, 'i', $userUID);
    mysqli_stmt_execute($cleanupStatement);
    mysqli_stmt_close($cleanupStatement);

    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (7) Path confinement — a tampered/escaping exportFilePath is NEVER
//     unlinked, but the DB path is still NULLed.
// ============================================================================
test('cron retention (#167): a path escaping the exports directory is never unlinked, only NULLed', function () use ($db): void
{
    $marker  = 'cronret167_escape_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_cronret_test_insert_user($db, $marker);

    $evilFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_it_evil_' . $marker . '.json';
    file_put_contents($evilFilePath, '{"marker":"' . $marker . '"}');

    $requestUID = g2ml_cronret_test_insert_export_request($db, $userUID, $evilFilePath, -1);

    $deadline = microtime(true) + 30;
    g2ml_retentionPurgeExpiredExports($deadline);

    assert_true(is_file($evilFilePath), 'A path outside the exports directory is NEVER unlinked');

    $row = g2ml_cronret_test_fetch_export_request($db, $requestUID);
    assert_same(null, $row['exportFilePath'], 'The DB path is still NULLed even though the file was left alone');

    unlink($evilFilePath);

    $cleanupStatement = mysqli_prepare($db, 'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($cleanupStatement, 'i', $userUID);
    mysqli_stmt_execute($cleanupStatement);
    mysqli_stmt_close($cleanupStatement);

    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (8) Sessions — an expired session row is removed and counted.
// ============================================================================
test('cron retention (#167): the session sweep removes an expired session', function () use ($db): void
{
    $marker     = 'cronret167_session_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronret_test_insert_user($db, $marker);
    $sessionUID = g2ml_cronret_test_insert_session($db, $userUID, $marker, 3600);

    assert_same(1, g2ml_cronret_test_count_session($db, $sessionUID), 'The expired session row exists before the sweep');

    $report = g2ml_retentionCleanSessions();

    assert_true($report['swept'] >= 1, 'At least the one expired session was swept');
    assert_same(0, g2ml_cronret_test_count_session($db, $sessionUID), 'The expired session row is gone');

    g2ml_cronret_test_cleanup_user($db, $userUID);
});

// ============================================================================
// (9) Advisory lock — a concurrent holder on a SECOND connection genuinely
//     blocks g2ml_cronAcquireLock(), and release makes it available again.
// ============================================================================
test('cron retention (#167): the advisory lock blocks a concurrent acquire and releases cleanly', function (): void
{
    $secondConnection = @mysqli_init();

    if ($secondConnection === false)
    {
        throw new RuntimeException('Could not initialise a second mysqli connection for the lock test');
    }

    $opened = @mysqli_real_connect($secondConnection, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($opened === false)
    {
        throw new RuntimeException('Could not open a second connection for the lock test: ' . mysqli_connect_error());
    }

    $lockResult = mysqli_query($secondConnection, "SELECT GET_LOCK(CONCAT(DATABASE(), ':g2ml_cron'), 5) AS lockResult");
    $lockRow    = mysqli_fetch_assoc($lockResult);

    assert_same(1, (int) $lockRow['lockResult'], 'The second connection must acquire the lock first');

    assert_false(g2ml_cronAcquireLock(), 'The application connection must NOT be able to acquire an already-held lock');

    mysqli_query($secondConnection, "SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':g2ml_cron'))");
    mysqli_close($secondConnection);

    assert_true(g2ml_cronAcquireLock(), 'Once released, the application connection can acquire the lock');

    g2ml_cronReleaseLock();
});
