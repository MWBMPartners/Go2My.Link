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
 * 🧪 Integration tests — GDPR data-export download IDOR guard (#162)
 * ============================================================================
 *
 * Issue #162: the GDPR data-export flow renders a download link of the form
 * /privacy/export?download=<requestUID>, but nothing served it — and,
 * critically, requestUID must NEVER be trusted on its own (an IDOR would let
 * any authenticated user download ANY other user's export by guessing/
 * enumerating requestUID values).
 *
 * The fix (web/_functions/data_rights.php) enforces ownership by matching
 * BOTH requestUID AND the session's own userUID in ONE prepared statement
 * (g2ml_findExportRequestForDownload()). This file drives that REAL function
 * against a freshly imported test database (see tests/README.md), seeding
 * TWO separate users and ONE completed export request owned by the first,
 * then proving:
 *
 *   (1) the OWNING user's (requestUID, userUID) pair resolves the row;
 *   (2) a DIFFERENT user's userUID paired with that SAME requestUID resolves
 *       to null — the exact cross-user (IDOR) rejection this issue demands;
 *   (3) the owner's resolved row is downloadable per
 *       g2ml_isExportRequestDownloadable() (completed + unexpired);
 *   (4) an EXPIRED request owned by the same user still resolves (ownership
 *       is separate from state) but is NOT downloadable.
 *
 * The pure decision function itself (g2ml_isExportRequestDownloadable()) has
 * its own exhaustive DB-free coverage in
 * tests/unit/data_export_download_test.php; this file exists to prove the
 * real SQL ownership scoping — not fully exercisable without a live schema.
 *
 * Registration model mirrors data_export_test.php / settings_scope_dedupe_test.php:
 * this file registers its cases at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed
 * g2ml_edl_test_* to stay unique alongside sibling integration files. With no
 * reachable test DB the runner skips before this file is ever included. A
 * fresh random marker is used for every run, and every seeded row is removed
 * on both entry and exit of the case (issue #148's idempotent-cleanup lesson).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      launch-prep/2026-07-09 (#162)
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
    $g2mlEdlTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlEdlTestHost === false || $g2mlEdlTestHost === '')
    {
        $g2mlEdlTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlEdlTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlEdlTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlEdlTestPortRaw === false || $g2mlEdlTestPortRaw === '')
    {
        $g2mlEdlTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlEdlTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlEdlTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlEdlTestUser === false || $g2mlEdlTestUser === '')
    {
        $g2mlEdlTestUser = 'root';
    }

    define('DB_USER', $g2mlEdlTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlEdlTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlEdlTestPass === false)
    {
        $g2mlEdlTestPass = '';
    }

    define('DB_PASS', $g2mlEdlTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlEdlTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlEdlTestName === false || $g2mlEdlTestName === '')
    {
        $g2mlEdlTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlEdlTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// g2ml_streamExportDownload() (not exercised here) reads under
// G2ML_UPLOADS . '/exports/' — defined for composability with sibling
// integration files even though this file never streams a file itself.
// ----------------------------------------------------------------------------
if (!defined('G2ML_UPLOADS'))
{
    $g2mlEdlTestUploadsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_it_uploads';

    if (!is_dir($g2mlEdlTestUploadsDir))
    {
        mkdir($g2mlEdlTestUploadsDir, 0750, true);
    }

    define('G2ML_UPLOADS', $g2mlEdlTestUploadsDir);
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlEdlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlEdlFunctionsDir . '/db_connect.php';
require_once $g2mlEdlFunctionsDir . '/data_rights.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Execute a setup/teardown query, aborting loudly on failure (setup faults
 * are not characterised behaviour and must not masquerade as assertion
 * failures).
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_edl_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user for the given org and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_edl_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@dataexportdl162.test';
    $passwordHash = 'x';
    $firstName    = 'ExportDL';
    $lastName     = 'Tester';

    mysqli_stmt_bind_param($statement, 'ssssss', $orgHandle, $username, $email, $passwordHash, $firstName, $lastName);
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
 * Insert a throwaway export request row directly (bypassing
 * g2ml_requestDataExport() — this file is only exercising the DOWNLOAD half,
 * not the generation half already covered by data_export_test.php).
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $status
 * @param  string|null $exportExpiresAt  A 'Y-m-d H:i:s' string, or null.
 * @return int  The new requestUID.
 */
function g2ml_edl_test_insert_export_request(mysqli $db, int $userUID, string $status, ?string $exportExpiresAt): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblDataDeletionRequests` '
        . '(`userUID`, `requestType`, `status`, `exportFilePath`, `exportExpiresAt`) '
        . "VALUES (?, 'export', ?, ?, ?)"
    );

    $exportFilePath = '/tmp/g2ml_edl_test_export_' . $userUID . '.json';

    mysqli_stmt_bind_param($statement, 'isss', $userUID, $status, $exportFilePath, $exportExpiresAt);
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
 * Delete every row seeded for the given userUIDs, in FK-safe order. Safe to
 * call even when some rows were never created.
 *
 * @param  mysqli            $db
 * @param  array<int, int>   $userUIDs
 * @return void
 */
function g2ml_edl_test_cleanup(mysqli $db, array $userUIDs): void
{
    foreach ($userUIDs as $userUID)
    {
        $deleteRequestsStatement = mysqli_prepare($db, 'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ?');
        mysqli_stmt_bind_param($deleteRequestsStatement, 'i', $userUID);
        mysqli_stmt_execute($deleteRequestsStatement);
        mysqli_stmt_close($deleteRequestsStatement);

        $deleteUserStatement = mysqli_prepare($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?');
        mysqli_stmt_bind_param($deleteUserStatement, 'i', $userUID);
        mysqli_stmt_execute($deleteUserStatement);
        mysqli_stmt_close($deleteUserStatement);
    }
}

// ----------------------------------------------------------------------------
// Register the test. Seeding happens inside the closure so it only runs when
// a test DB is present (the runner has already proven $db is connected).
// ----------------------------------------------------------------------------
test('g2ml_findExportRequestForDownload (#162): a requestUID belonging to a DIFFERENT user resolves to null (IDOR guard)', function () use ($db): void
{
    // Ensure the [default] org + free tier exist (FK targets for tblUsers).
    g2ml_edl_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_edl_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Fresh markers per run so a rerun never collides on a UNIQUE constraint.
    $marker       = 'edl162_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $ownerMarker  = $marker . '_owner';
    $otherMarker  = $marker . '_other';

    $ownerUserUID = g2ml_edl_test_insert_user($db, '[default]', $ownerMarker);
    $otherUserUID = g2ml_edl_test_insert_user($db, '[default]', $otherMarker);

    $futureExpiry = date('Y-m-d H:i:s', time() + 3600);
    $pastExpiry   = date('Y-m-d H:i:s', time() - 3600);

    $completedRequestUID = g2ml_edl_test_insert_export_request($db, $ownerUserUID, 'completed', $futureExpiry);
    $expiredRequestUID   = g2ml_edl_test_insert_export_request($db, $ownerUserUID, 'completed', $pastExpiry);

    // ---- (1) The OWNING user's (requestUID, userUID) pair resolves. ----
    $ownedRow = g2ml_findExportRequestForDownload($completedRequestUID, $ownerUserUID);

    assert_true(is_array($ownedRow), 'The owning user must be able to resolve their own export request');
    assert_same($completedRequestUID, (int) $ownedRow['requestUID'], 'The resolved row is the correct request');
    assert_same('completed', $ownedRow['status'], 'The resolved row carries its real status');

    // ---- (2) THE IDOR GUARD — a different user's userUID, same requestUID. ----
    $crossUserRow = g2ml_findExportRequestForDownload($completedRequestUID, $otherUserUID);

    assert_true(
        $crossUserRow === null,
        'A requestUID owned by a DIFFERENT user must resolve to null — never the row, never an error, never a distinguishable signal (#162 IDOR guard)'
    );

    // ---- (3) The owner's row is genuinely downloadable (completed + unexpired). ----
    assert_true(
        g2ml_isExportRequestDownloadable($ownedRow),
        'A completed, unexpired request owned by the caller must be downloadable'
    );

    // ---- (4) An EXPIRED request owned by the SAME user still resolves (ownership
    //          is independent of state) but must NOT be downloadable. ----
    $expiredRow = g2ml_findExportRequestForDownload($expiredRequestUID, $ownerUserUID);

    assert_true(is_array($expiredRow), 'An expired request still resolves for its own owner (ownership check passes)');
    assert_false(
        g2ml_isExportRequestDownloadable($expiredRow),
        'An expired request must never be downloadable, even for its rightful owner'
    );

    // ---- (5) The IDOR guard also holds for the expired request. ----
    $crossUserExpiredRow = g2ml_findExportRequestForDownload($expiredRequestUID, $otherUserUID);

    assert_true(
        $crossUserExpiredRow === null,
        'The expired request must ALSO resolve to null for a different user (ownership is checked before state)'
    );

    g2ml_edl_test_cleanup($db, [$ownerUserUID, $otherUserUID]);
});
