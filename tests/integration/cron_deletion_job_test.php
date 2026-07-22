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
 * 🧪 Integration tests — #163 GDPR deletion executor (web/_functions/cron.php)
 * ============================================================================
 *
 * Drives the REAL g2ml_cronRunDeletionJob() (and, through it, the existing,
 * UNMODIFIED g2ml_processDataDeletion()/g2ml_anonymiseUserData() from
 * data_rights.php) against a freshly imported test database, proving every
 * safety interlock (I1–I11, see cron.php's own docblock) actually holds at
 * runtime, not merely in the pure unit tests:
 *
 *   - the master enable gate is a true no-op when off (row untouched);
 *   - dry-run mode logs candidates and touches NOTHING (no anonymisation, no
 *     status change);
 *   - a live run anonymises the subject, marks the request completed, and
 *     stamps the configured actor + timestamp;
 *   - re-running a completed request is a safe no-op (idempotency, I8);
 *   - a request still inside its grace window is never selected (I6);
 *   - an unset/invalid actor UID refuses the ENTIRE run before touching any
 *     row (I5);
 *   - the batch cap bounds a single run (I10);
 *   - an 'export'-type request is never selected, no matter how old (I6's
 *     WHERE clause, not post-filtering).
 *
 * Registration model mirrors settings_scope_dedupe_test.php / activity_log_
 * test.php: this file registers its cases at INCLUDE time using the $db
 * handle from run_integration.php's script scope. Helper names are prefixed
 * g2ml_cronjob_test_* to stay unique alongside sibling integration files.
 * With no reachable test DB the runner skips before this file is ever
 * included.
 *
 * Every scenario creates its own fresh, uniquely-marked user(s) and request
 * row(s), and cleans them up at the end — repeatable against a persisted DB,
 * and independent of any other test file's rows (#148's lesson).
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
    $g2mlCronJobTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlCronJobTestHost === false || $g2mlCronJobTestHost === '')
    {
        $g2mlCronJobTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlCronJobTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlCronJobTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlCronJobTestPortRaw === false || $g2mlCronJobTestPortRaw === '')
    {
        $g2mlCronJobTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlCronJobTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlCronJobTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlCronJobTestUser === false || $g2mlCronJobTestUser === '')
    {
        $g2mlCronJobTestUser = 'root';
    }

    define('DB_USER', $g2mlCronJobTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlCronJobTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlCronJobTestPass === false)
    {
        $g2mlCronJobTestPass = '';
    }

    define('DB_PASS', $g2mlCronJobTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlCronJobTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlCronJobTestName === false || $g2mlCronJobTestName === '')
    {
        $g2mlCronJobTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlCronJobTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// cron.php's own functions call getSetting()/logActivity() UNGUARDED (unlike
// data_export_test.php's target, which guards every optional collaborator),
// so settings.php and activity_logger.php MUST both be loaded here.
// ----------------------------------------------------------------------------
$g2mlCronJobFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlCronJobFunctionsDir . '/db_connect.php';
require_once $g2mlCronJobFunctionsDir . '/db_query.php';
require_once $g2mlCronJobFunctionsDir . '/security.php';
require_once $g2mlCronJobFunctionsDir . '/settings.php';
require_once $g2mlCronJobFunctionsDir . '/activity_logger.php';
require_once $g2mlCronJobFunctionsDir . '/data_rights.php';
require_once $g2mlCronJobFunctionsDir . '/cron.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Execute a setup/teardown query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_cronjob_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway, active user in the [default] org and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $marker
 * @return int
 */
function g2ml_cronjob_test_insert_user(mysqli $db, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) '
        . 'VALUES (\'[default]\', ?, ?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@cronjob163.test';
    $passwordHash = 'x';
    $firstName    = 'Cron';
    $lastName     = 'Tester';

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
 * Insert a tblDataDeletionRequests row with a caller-controlled createdAt
 * (expressed as "days ago" so tests can place a row inside or outside the
 * grace window) and return its requestUID.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $requestType  'deletion' | 'export'.
 * @param  string $status       'pending' | 'processing' | 'completed' | 'rejected'.
 * @param  int    $daysAgo      How many days in the past createdAt is set to.
 * @return int
 */
function g2ml_cronjob_test_insert_request(mysqli $db, int $userUID, string $requestType, string $status, int $daysAgo): int
{
    $createdAt = gmdate('Y-m-d H:i:s', time() - ($daysAgo * 86400));

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblDataDeletionRequests` (`userUID`, `requestType`, `status`, `createdAt`) '
        . 'VALUES (?, ?, ?, ?)'
    );

    mysqli_stmt_bind_param($statement, 'isss', $userUID, $requestType, $status, $createdAt);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (deletion request) failed: ' . $error);
    }

    $requestUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $requestUID;
}

/**
 * Read back a tblDataDeletionRequests row by requestUID.
 *
 * @param  mysqli $db
 * @param  int    $requestUID
 * @return array<string, mixed>|null
 */
function g2ml_cronjob_test_fetch_request(mysqli $db, int $requestUID): ?array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `status`, `processedByUserUID`, `processedAt` FROM `tblDataDeletionRequests` WHERE `requestUID` = ? LIMIT 1'
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
 * Read back a tblUsers row by userUID (the fields g2ml_anonymiseUserData()
 * mutates).
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return array<string, mixed>|null
 */
function g2ml_cronjob_test_fetch_user(mysqli $db, int $userUID): ?array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `email`, `firstName`, `lastName`, `isActive` FROM `tblUsers` WHERE `userUID` = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($statement, 'i', $userUID);
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
 * Count tblActivityLog rows for a given logAction + userUID.
 *
 * @param  mysqli $db
 * @param  string $logAction
 * @param  int    $userUID
 * @return int
 */
function g2ml_cronjob_test_count_activity(mysqli $db, string $logAction, int $userUID): int
{
    $statement = mysqli_prepare(
        $db,
        'SELECT COUNT(*) AS cnt FROM `tblActivityLog` WHERE `logAction` = ? AND `userUID` = ?'
    );
    mysqli_stmt_bind_param($statement, 'si', $logAction, $userUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Delete every row this test file may have created for a userUID, in
 * FK-safe order. Safe to call even when some rows were never created.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return void
 */
function g2ml_cronjob_test_cleanup(mysqli $db, int $userUID): void
{
    $deleteRequestsStatement = mysqli_prepare(
        $db,
        'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ? OR `processedByUserUID` = ?'
    );
    mysqli_stmt_bind_param($deleteRequestsStatement, 'ii', $userUID, $userUID);
    mysqli_stmt_execute($deleteRequestsStatement);
    mysqli_stmt_close($deleteRequestsStatement);

    $deleteActivityStatement = mysqli_prepare($db, 'DELETE FROM `tblActivityLog` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteActivityStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteActivityStatement);
    mysqli_stmt_close($deleteActivityStatement);

    $deleteSessionsStatement = mysqli_prepare($db, 'DELETE FROM `tblUserSessions` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteSessionsStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteSessionsStatement);
    mysqli_stmt_close($deleteSessionsStatement);

    $deleteConsentStatement = mysqli_prepare($db, 'DELETE FROM `tblConsentRecords` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteConsentStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteConsentStatement);
    mysqli_stmt_close($deleteConsentStatement);

    $deleteUserStatement = mysqli_prepare($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteUserStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteUserStatement);
    mysqli_stmt_close($deleteUserStatement);
}

// ----------------------------------------------------------------------------
// Ensure the [default] org + free tier exist (FK targets for tblUsers).
// ----------------------------------------------------------------------------
g2ml_cronjob_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_cronjob_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

// ============================================================================
// (1) Disabled gate — the job is a total no-op.
// ============================================================================
test('cron deletion job (#163): disabled gate — report {enabled:false}, row untouched', function () use ($db): void
{
    $marker     = 'cronjob163_disabled_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);

    setSetting('gdpr.deletion_job_enabled', false, 'System');
    setSetting('gdpr.deletion_job_dry_run', true, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same(false, $report['enabled'], 'A disabled job reports enabled:false');

    $requestRow = g2ml_cronjob_test_fetch_request($db, $requestUID);
    assert_same('pending', $requestRow['status'], 'The request row is completely untouched while disabled');

    g2ml_cronjob_test_cleanup($db, $userUID);
});

// ============================================================================
// (2) Dry-run — logs candidates, touches NOTHING.
// ============================================================================
test('cron deletion job (#163): dry-run logs the candidate and changes nothing', function () use ($db): void
{
    $marker     = 'cronjob163_dryrun_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', true, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same(true, $report['enabled'], 'Dry-run is still "enabled"');
    assert_same('dry-run', $report['mode'], 'Mode must be dry-run');
    assert_true(in_array($requestUID, $report['wouldProcess'], true), 'The due request appears in wouldProcess');

    $requestRow = g2ml_cronjob_test_fetch_request($db, $requestUID);
    assert_same('pending', $requestRow['status'], 'Dry-run must NOT change the request status');

    $userRow = g2ml_cronjob_test_fetch_user($db, $userUID);
    assert_same(1, (int) $userRow['isActive'], 'Dry-run must NOT anonymise the user (isActive unchanged)');
    assert_false(str_contains($userRow['email'], 'anonymised.go2my.link'), 'Dry-run must NOT touch the email');

    $dryRunLogCount = g2ml_cronjob_test_count_activity($db, 'data_deletion_due_dryrun', $userUID);
    assert_true($dryRunLogCount >= 1, 'A data_deletion_due_dryrun activity row was written');

    g2ml_cronjob_test_cleanup($db, $userUID);
});

// ============================================================================
// (3) Live happy path — anonymises the subject, completes the request.
// ============================================================================
test('cron deletion job (#163): live run anonymises the subject and completes the request', function () use ($db): void
{
    $marker      = 'cronjob163_live_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $actorMarker = 'cronjob163_actor_' . substr(hash('sha256', (string) microtime(true) . 'a'), 0, 12);

    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $actorUID   = g2ml_cronjob_test_insert_user($db, $actorMarker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', false, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');
    setSetting('gdpr.deletion_job_actor_uid', $actorUID, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same('live', $report['mode'], 'Mode must be live');
    assert_same(1, $report['processed'], 'Exactly one request processed');
    assert_same(0, $report['failed'], 'No failures');

    $userRow = g2ml_cronjob_test_fetch_user($db, $userUID);
    assert_same('[DELETED]', $userRow['firstName'], 'firstName anonymised');
    assert_same('[DELETED]', $userRow['lastName'], 'lastName anonymised');
    assert_same('deleted_' . $userUID . '@anonymised.go2my.link', $userRow['email'], 'email anonymised to the expected sentinel');
    assert_same(0, (int) $userRow['isActive'], 'isActive turned off');

    $requestRow = g2ml_cronjob_test_fetch_request($db, $requestUID);
    assert_same('completed', $requestRow['status'], 'The request row is marked completed');
    assert_same($actorUID, (int) $requestRow['processedByUserUID'], 'processedByUserUID is the configured actor');
    assert_true($requestRow['processedAt'] !== null, 'processedAt is stamped');

    $executedLogCount = g2ml_cronjob_test_count_activity($db, 'data_deletion_executed', $userUID);
    assert_true($executedLogCount >= 1, 'A data_deletion_executed activity row was written');

    g2ml_cronjob_test_cleanup($db, $userUID);
    g2ml_cronjob_test_cleanup($db, $actorUID);
});

// ============================================================================
// (4) Idempotency — running live twice does not reprocess a completed row.
// ============================================================================
test('cron deletion job (#163): a second live run is a safe no-op (idempotent)', function () use ($db): void
{
    $marker      = 'cronjob163_idem_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $actorMarker = 'cronjob163_idemactor_' . substr(hash('sha256', (string) microtime(true) . 'a'), 0, 12);

    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $actorUID   = g2ml_cronjob_test_insert_user($db, $actorMarker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', false, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');
    setSetting('gdpr.deletion_job_actor_uid', $actorUID, 'System');

    $firstReport = g2ml_cronRunDeletionJob(30);
    assert_same(1, $firstReport['processed'], 'First run processes the one due request');

    $secondReport = g2ml_cronRunDeletionJob(30);
    assert_same(0, $secondReport['due'], 'Second run finds nothing due — the row is no longer pending');
    assert_same(0, $secondReport['processed'], 'Second run processes nothing');
    assert_same(0, $secondReport['failed'], 'Second run has no failures');

    g2ml_cronjob_test_cleanup($db, $userUID);
    g2ml_cronjob_test_cleanup($db, $actorUID);
});

// ============================================================================
// (5) Grace window — a freshly-created request is never selected.
// ============================================================================
test('cron deletion job (#163): a request still inside its grace window is never selected', function () use ($db): void
{
    $marker     = 'cronjob163_grace_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 0);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', true, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');
    setSetting('compliance.data_deletion_grace_days', 30, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_false(in_array($requestUID, $report['wouldProcess'], true), 'A request created "now" must not be due under a 30-day grace window');

    $requestRow = g2ml_cronjob_test_fetch_request($db, $requestUID);
    assert_same('pending', $requestRow['status'], 'The request row remains untouched');

    g2ml_cronjob_test_cleanup($db, $userUID);
});

// ============================================================================
// (6) Actor unset/invalid — live run refuses to start, zero rows touched.
// ============================================================================
test('cron deletion job (#163): an unset actor UID refuses the entire live run', function () use ($db): void
{
    $marker     = 'cronjob163_noactor_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', false, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');
    setSetting('gdpr.deletion_job_actor_uid', null, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same('actor_not_configured', $report['error'], 'The report surfaces the actor_not_configured error');

    $requestRow = g2ml_cronjob_test_fetch_request($db, $requestUID);
    assert_same('pending', $requestRow['status'], 'Zero rows touched when the actor is unset');

    $userRow = g2ml_cronjob_test_fetch_user($db, $userUID);
    assert_same(1, (int) $userRow['isActive'], 'The user was never anonymised');

    g2ml_cronjob_test_cleanup($db, $userUID);
});

// ============================================================================
// (7) Batch cap — bounds a single run.
// ============================================================================
test('cron deletion job (#163): the batch cap bounds a single run to exactly one processed row', function () use ($db): void
{
    $markerPrefix = 'cronjob163_batch_' . substr(hash('sha256', (string) microtime(true)), 0, 10);
    $actorMarker  = 'cronjob163_batchactor_' . substr(hash('sha256', (string) microtime(true) . 'a'), 0, 12);

    $actorUID = g2ml_cronjob_test_insert_user($db, $actorMarker);

    $userUIDs = [];

    for ($index = 0; $index < 3; $index++)
    {
        $userUID              = g2ml_cronjob_test_insert_user($db, $markerPrefix . '_' . $index);
        $userUIDs[]            = $userUID;
        g2ml_cronjob_test_insert_request($db, $userUID, 'deletion', 'pending', 35);
    }

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', false, 'System');
    setSetting('gdpr.deletion_job_batch', 1, 'System');
    setSetting('gdpr.deletion_job_actor_uid', $actorUID, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same(1, $report['processed'], 'Exactly one row processed when the batch cap is 1');

    $pendingRemaining = 0;

    foreach ($userUIDs as $userUID)
    {
        $userRow = g2ml_cronjob_test_fetch_user($db, $userUID);

        if ((int) $userRow['isActive'] === 1)
        {
            $pendingRemaining = $pendingRemaining + 1;
        }
    }

    assert_same(2, $pendingRemaining, 'The other two subjects remain untouched (batch cap respected)');

    foreach ($userUIDs as $userUID)
    {
        g2ml_cronjob_test_cleanup($db, $userUID);
    }

    g2ml_cronjob_test_cleanup($db, $actorUID);
});

// ============================================================================
// (8) Export rows are immune — never selected, no matter how old.
// ============================================================================
test('cron deletion job (#163): an export-type request is never selected', function () use ($db): void
{
    $marker     = 'cronjob163_export_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID    = g2ml_cronjob_test_insert_user($db, $marker);
    $requestUID = g2ml_cronjob_test_insert_request($db, $userUID, 'export', 'pending', 365);

    setSetting('gdpr.deletion_job_enabled', true, 'System');
    setSetting('gdpr.deletion_job_dry_run', true, 'System');
    setSetting('gdpr.deletion_job_batch', 10, 'System');

    $report = g2ml_cronRunDeletionJob(30);

    assert_same(0, $report['due'], 'An export-type row must never be selected by the deletion executor, regardless of age');

    g2ml_cronjob_test_cleanup($db, $userUID);
});
