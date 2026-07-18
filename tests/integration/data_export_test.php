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
 * 🧪 Integration test — g2ml_requestDataExport() regression guard (#138)
 * ============================================================================
 *
 * Regression coverage for issue #138 (P0 GDPR/CCPA compliance blocker):
 * g2ml_requestDataExport() in web/_functions/data_rights.php SELECTed columns
 * that do not exist — `expiresAt` on `tblShortURLs` (the schema uses
 * `startDate`/`endDate`) and `deviceType`/`browserName`/`osName` on
 * `tblUserSessions` (the schema uses `deviceInfo`). Because db_connect.php's
 * getDB() sets MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, prepare() threw on
 * the very first affected SELECT, and the function's own broad try/catch
 * swallowed the exception, returning
 * ['success' => false, 'error' => 'Export failed. Please try again later.']
 * for every user, every time. GDPR Art. 20 / CCPA right-to-know was therefore
 * non-functional. Fixed in commit 602573e (SELECTs realigned to the real
 * schema columns); this file is the outstanding regression test tracked by
 * that commit's follow-up.
 *
 * This test drives the REAL g2ml_requestDataExport() from
 * web/_functions/data_rights.php against a freshly imported test database
 * (see tests/README.md). It:
 *
 *   1. seeds the [default] organisation + free tier (FK targets, mirrors
 *      sibling integration files), a throwaway user (tblUsers), one short
 *      URL owned by that user (tblShortURLs — exercises the previously
 *      broken startDate/endDate SELECT), and one active session for that
 *      user (tblUserSessions — exercises the previously broken deviceInfo
 *      SELECT);
 *   2. calls the real g2ml_requestDataExport($userUID);
 *   3. asserts success === true and a positive requestUID — proving every
 *      prepare()/execute() in the function completed without a MySQLi
 *      strict-mode error (the exact failure mode of the original bug);
 *   4. reads back the written export JSON file (its path is not returned by
 *      the function itself — it lives in
 *      tblDataDeletionRequests.exportFilePath — mirroring
 *      auth_register_test.php's read-back-via-direct-SELECT pattern) and
 *      asserts the payload actually contains the seeded user's profile, the
 *      seeded short URL with the schema-valid startDate/endDate keys (NOT
 *      the non-existent expiresAt), and the seeded session with the
 *      schema-valid deviceInfo key (NOT the non-existent deviceType /
 *      browserName / osName).
 *
 * Only db_connect.php (getDB()) and data_rights.php itself are loaded.
 * g2ml_requestDataExport()'s other collaborators — getSetting(),
 * logActivity(), g2ml_sendEmail() — are ALL called behind function_exists()
 * guards inside the function under test, so leaving them unloaded exercises
 * the function's own defined fallbacks (a fixed 48-hour expiry, no activity
 * log row, no email) rather than pulling in unrelated systems this test does
 * not need.
 *
 * Registration model mirrors auth_register_test.php / activity_log_test.php:
 * this file registers its case at INCLUDE time using the $db handle from
 * run_integration.php's script scope, rather than the
 * g2ml_register_integration_tests() hook (owned by shorturl_lookup_test.php).
 * Helper names are prefixed g2ml_dataexport_test_* to stay unique alongside
 * sibling integration files. With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * A fresh random marker is used for every run (mirrors auth_register_test.php),
 * so a rerun never collides with a previous run's rows on a UNIQUE
 * constraint — whether or not that previous run reached its own cleanup —
 * and every seeded row plus the written export file is removed at the end
 * of the case (issue #148's idempotent-cleanup lesson).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#138)
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
    $g2mlDataExportTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlDataExportTestHost === false || $g2mlDataExportTestHost === '')
    {
        $g2mlDataExportTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlDataExportTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlDataExportTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlDataExportTestPortRaw === false || $g2mlDataExportTestPortRaw === '')
    {
        $g2mlDataExportTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlDataExportTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlDataExportTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlDataExportTestUser === false || $g2mlDataExportTestUser === '')
    {
        $g2mlDataExportTestUser = 'root';
    }

    define('DB_USER', $g2mlDataExportTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlDataExportTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlDataExportTestPass === false)
    {
        $g2mlDataExportTestPass = '';
    }

    define('DB_PASS', $g2mlDataExportTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlDataExportTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlDataExportTestName === false || $g2mlDataExportTestName === '')
    {
        $g2mlDataExportTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlDataExportTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// g2ml_requestDataExport() writes its JSON export under
// G2ML_UPLOADS . '/exports/'. The real constant is defined by
// web/_includes/page_init.php from the running component's document root,
// which this CLI harness never loads, so point it at a throwaway directory
// under the system temp path instead. Guarded so this composes with any
// sibling integration file that defines it first.
// ----------------------------------------------------------------------------
if (!defined('G2ML_UPLOADS'))
{
    $g2mlDataExportTestUploadsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_it_uploads';

    if (!is_dir($g2mlDataExportTestUploadsDir))
    {
        mkdir($g2mlDataExportTestUploadsDir, 0750, true);
    }

    define('G2ML_UPLOADS', $g2mlDataExportTestUploadsDir);
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlDataExportFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlDataExportFunctionsDir . '/db_connect.php';
require_once $g2mlDataExportFunctionsDir . '/data_rights.php';

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
function g2ml_dataexport_test_exec(mysqli $db, string $sql): void
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
function g2ml_dataexport_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@dataexport138.test';
    $passwordHash = 'x';
    $firstName    = 'Export';
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
 * Insert a throwaway short URL owned by the given user. Sets startDate and
 * endDate — the exact tblShortURLs columns the original bug's SELECT
 * referenced by the non-existent name `expiresAt` — so the export payload
 * can be checked for the schema-valid keys.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_dataexport_test_insert_shorturl(mysqli $db, int $userUID, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `title`, `createdByUserUID`, `isActive`, `startDate`, `endDate`) '
        . 'VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
    );

    $shortCode      = $marker;
    $destinationURL = 'https://example.com/' . $marker;
    $title          = 'Data export test link';
    $startDate      = date('Y-m-d H:i:s', time() - 3600);
    $endDate        = date('Y-m-d H:i:s', time() + 3600);

    mysqli_stmt_bind_param(
        $statement,
        'ssssiss',
        $orgHandle,
        $shortCode,
        $destinationURL,
        $title,
        $userUID,
        $startDate,
        $endDate
    );

    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (short URL) failed: ' . $error);
    }

    $urlUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $urlUID;
}

/**
 * Insert a throwaway active session for the given user. Sets deviceInfo —
 * the exact tblUserSessions column the original bug's SELECT referenced by
 * the non-existent names `deviceType`/`browserName`/`osName` — so the
 * export payload can be checked for the schema-valid key.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $marker
 * @return int
 */
function g2ml_dataexport_test_insert_session(mysqli $db, int $userUID, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUserSessions` '
        . '(`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    $sessionToken = hash('sha256', $marker);
    $ipAddress    = '203.0.113.138';
    $userAgent    = 'IntegrationTest/1.0';
    $deviceInfo   = 'Chrome 120 on macOS';
    $expiresAt    = date('Y-m-d H:i:s', time() + 3600);

    mysqli_stmt_bind_param(
        $statement,
        'isssss',
        $userUID,
        $sessionToken,
        $ipAddress,
        $userAgent,
        $deviceInfo,
        $expiresAt
    );

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
 * Delete every row seeded/written for a userUID, in FK-safe order, plus the
 * export file it wrote (if any). Safe to call even when some rows or the
 * file were never created, so a partially-failed prior run never blocks a
 * fresh one (a fresh random marker per run already prevents any UNIQUE
 * collision regardless).
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return void
 */
function g2ml_dataexport_test_cleanup(mysqli $db, int $userUID): void
{
    $selectStatement = mysqli_prepare(
        $db,
        'SELECT `exportFilePath` FROM `tblDataDeletionRequests` WHERE `userUID` = ? AND `requestType` = \'export\''
    );
    mysqli_stmt_bind_param($selectStatement, 'i', $userUID);
    mysqli_stmt_execute($selectStatement);
    $result = mysqli_stmt_get_result($selectStatement);

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $exportFilePath = $row['exportFilePath'];

        if (is_string($exportFilePath) && $exportFilePath !== '' && is_file($exportFilePath))
        {
            unlink($exportFilePath);
        }
    }

    mysqli_stmt_close($selectStatement);

    $deleteRequestsStatement = mysqli_prepare($db, 'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteRequestsStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteRequestsStatement);
    mysqli_stmt_close($deleteRequestsStatement);

    $deleteSessionsStatement = mysqli_prepare($db, 'DELETE FROM `tblUserSessions` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteSessionsStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteSessionsStatement);
    mysqli_stmt_close($deleteSessionsStatement);

    $deleteConsentStatement = mysqli_prepare($db, 'DELETE FROM `tblConsentRecords` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteConsentStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteConsentStatement);
    mysqli_stmt_close($deleteConsentStatement);

    $deleteShortUrlsStatement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `createdByUserUID` = ?');
    mysqli_stmt_bind_param($deleteShortUrlsStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteShortUrlsStatement);
    mysqli_stmt_close($deleteShortUrlsStatement);

    $deleteUserStatement = mysqli_prepare($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?');
    mysqli_stmt_bind_param($deleteUserStatement, 'i', $userUID);
    mysqli_stmt_execute($deleteUserStatement);
    mysqli_stmt_close($deleteUserStatement);
}

// ----------------------------------------------------------------------------
// Register the test. Seeding happens inside the closure so it only runs when
// a test DB is present (the runner has already proven $db is connected).
// ----------------------------------------------------------------------------
test('g2ml_requestDataExport (#138): succeeds against the real schema and exports the seeded user, short URL, and session', function () use ($db): void
{
    // Ensure the [default] org + free tier exist (FK targets for tblUsers).
    // ON DUPLICATE KEY UPDATE makes this idempotent regardless of run order,
    // mirroring auth_register_test.php / entitlements_test.php.
    g2ml_dataexport_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_dataexport_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Use a unique marker so reruns never collide on a UNIQUE constraint
    // (username/email/shortCode), regardless of whether a previous run's
    // cleanup ever ran.
    $marker = 'dataexport138_' . substr(hash('sha256', (string) microtime(true)), 0, 12);

    $userUID    = g2ml_dataexport_test_insert_user($db, '[default]', $marker);
    $urlUID     = g2ml_dataexport_test_insert_shorturl($db, $userUID, '[default]', $marker);
    $sessionUID = g2ml_dataexport_test_insert_session($db, $userUID, $marker);

    $result = g2ml_requestDataExport($userUID);

    assert_true(
        $result['success'],
        'Export must succeed against the real schema (this fails pre-fix: the '
        . 'expiresAt/deviceType/browserName/osName SELECTs throw under '
        . 'MYSQLI_REPORT_STRICT and are swallowed as "Export failed")'
    );
    assert_true(($result['requestUID'] ?? 0) > 0, 'A successful export returns a positive requestUID');

    $requestUID = (int) $result['requestUID'];

    // The function does not return the export file path — it lives on the
    // authoritative tblDataDeletionRequests row — mirroring
    // auth_register_test.php's read-back-via-direct-SELECT pattern.
    $pathStatement = mysqli_prepare(
        $db,
        'SELECT `exportFilePath` FROM `tblDataDeletionRequests` WHERE `requestUID` = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($pathStatement, 'i', $requestUID);
    mysqli_stmt_execute($pathStatement);
    $requestRow = mysqli_stmt_get_result($pathStatement)->fetch_assoc();
    mysqli_stmt_close($pathStatement);

    assert_true($requestRow !== null, 'The export request row was written');

    $exportFilePath = $requestRow['exportFilePath'];

    assert_true(is_string($exportFilePath) && $exportFilePath !== '', 'The export request row stores an export file path');
    assert_true(is_file($exportFilePath), 'The export JSON file actually exists on disk');

    $exportJson = file_get_contents($exportFilePath);
    $exportData = json_decode($exportJson, true);

    assert_true(is_array($exportData), 'The export file contains valid, decodable JSON');
    assert_true(isset($exportData['data']['profile']), 'The export payload has a profile section');
    assert_same($userUID, (int) $exportData['data']['profile']['userUID'], 'The export payload profile is the seeded user');

    // ---- The previously-broken tblShortURLs SELECT. ----
    $shortUrls = $exportData['data']['short_urls'];

    assert_true(is_array($shortUrls) && count($shortUrls) >= 1, 'The seeded short URL appears in the export');

    $matchedUrl = null;

    foreach ($shortUrls as $urlRow)
    {
        if ($urlRow['shortCode'] === $marker)
        {
            $matchedUrl = $urlRow;
        }
    }

    assert_true($matchedUrl !== null, 'The seeded short URL is present in the export by shortCode');
    assert_true(array_key_exists('startDate', $matchedUrl), 'The export uses the schema-valid startDate column');
    assert_true(array_key_exists('endDate', $matchedUrl), 'The export uses the schema-valid endDate column');
    assert_false(array_key_exists('expiresAt', $matchedUrl), 'The non-existent expiresAt column from the original bug is not present');

    // ---- The previously-broken tblUserSessions SELECT. ----
    $sessions = $exportData['data']['sessions'];

    assert_true(is_array($sessions) && count($sessions) >= 1, 'The seeded session appears in the export');

    $matchedSession = null;

    foreach ($sessions as $sessionRow)
    {
        if ((int) $sessionRow['sessionUID'] === $sessionUID)
        {
            $matchedSession = $sessionRow;
        }
    }

    assert_true($matchedSession !== null, 'The seeded session is present in the export by sessionUID');
    assert_true(array_key_exists('deviceInfo', $matchedSession), 'The export uses the schema-valid deviceInfo column');
    assert_same('Chrome 120 on macOS', $matchedSession['deviceInfo'], 'The seeded deviceInfo value round-trips through the export');
    assert_false(array_key_exists('deviceType', $matchedSession), 'The non-existent deviceType column from the original bug is not present');
    assert_false(array_key_exists('browserName', $matchedSession), 'The non-existent browserName column from the original bug is not present');
    assert_false(array_key_exists('osName', $matchedSession), 'The non-existent osName column from the original bug is not present');

    // ---- The untouched-but-present tblConsentRecords SELECT still completes cleanly. ----
    assert_true(is_array($exportData['data']['consent_records']), 'The consent_records section is present and array-shaped, even with none seeded');

    // Cleanup — idempotent, safe on rerun (see file docblock).
    g2ml_dataexport_test_cleanup($db, $userUID);
});
