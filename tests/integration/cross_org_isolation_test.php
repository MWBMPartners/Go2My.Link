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
 * 🧪 Integration tests — link-CRUD correctness + cross-org isolation
 * ============================================================================
 *
 * Regression guards for the two must-fix defects the VERIFY pass surfaced on
 * the link-CRUD path (both DB-only, invisible to the unit suite):
 *
 *   CR-1 (correctness): the edit-page load SELECT named a non-existent column
 *        (s.shortURLUID; the PK is s.urlUID), so it failed at PREPARE and link
 *        editing was 100% broken. This test runs the exact edit-load statement
 *        and asserts it prepares and returns the row.
 *
 *   SEC-RECHECK-01 (security / tenant isolation): the create page ran a
 *        post-insert "UPDATE tblShortURLs SET isActive = 0 WHERE shortCode = ?"
 *        scoped ONLY by shortCode. Because UQ_shortcode_org is per-org and
 *        FG-001 lets a user mint a custom alias duplicating another org's public
 *        code, that UPDATE deactivated the OTHER org's live link. The fix binds
 *        isActive into createShortURL()'s INSERT and deletes the UPDATE. This
 *        test creates an inactive alias in org B equal to org A's active code
 *        and asserts org A's link stays active.
 *
 * Registration model mirrors custom_alias_create_test.php: cases register at
 * INCLUDE time using the $db handle from run_integration.php's script scope.
 * Helper names are unique (g2ml_iso_*) to avoid redeclaration alongside the
 * other integration files. With no reachable test DB the runner skips before
 * this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot VERIFY (CR-1 / SEC-RECHECK-01)
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
// Point the application DB layer (getDB) at the same throwaway server. These
// mirror run_integration.php's env resolution and are guarded so they compose
// with any other integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlEnvHost === false || $g2mlEnvHost === '')
    {
        $g2mlEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlEnvPort === false || $g2mlEnvPort === '')
    {
        $g2mlEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlEnvUser === false || $g2mlEnvUser === '')
    {
        $g2mlEnvUser = 'root';
    }

    define('DB_USER', $g2mlEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlEnvPass === false)
    {
        $g2mlEnvPass = '';
    }

    define('DB_PASS', $g2mlEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlEnvName === false || $g2mlEnvName === '')
    {
        $g2mlEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files createShortURL() depends on.
// ----------------------------------------------------------------------------
$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFunctionsDir . 'db_connect.php';
require_once $g2mlFunctionsDir . 'db_query.php';
require_once $g2mlFunctionsDir . 'security.php';
require_once $g2mlFunctionsDir . 'settings.php';
require_once $g2mlFunctionsDir . 'activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Execute a setup query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_iso_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Delete every row for a short code across all orgs so the suite is repeatable.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_iso_delete(mysqli $db, string $shortCode): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare DELETE failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Directly insert a short-URL row (bypassing the app layer) for a given org,
 * code and active flag. Used to seed a pre-existing "victim" link.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $shortCode
 * @param  int    $createdByUserUID
 * @param  int    $isActive
 * @return void
 */
function g2ml_iso_seed_link(mysqli $db, string $orgHandle, string $shortCode, ?int $createdByUserUID, int $isActive): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `createdByUserUID`, `isActive`, `createdAt`, `updatedAt`) '
        . "VALUES (?, ?, ?, 'url', ?, ?, NOW(), NOW())"
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare seed INSERT failed: ' . mysqli_error($db));
    }

    $destinationURL = 'https://example.com/' . $orgHandle . '/' . $shortCode;
    // A null creator binds as SQL NULL under the 'i' type (FK_url_creator is
    // ON DELETE SET NULL, so the column is nullable).
    mysqli_stmt_bind_param($statement, 'sssii', $orgHandle, $shortCode, $destinationURL, $createdByUserUID, $isActive);
    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Seed INSERT failed: ' . mysqli_error($db));
    }
}

/**
 * Seed a minimal, valid user so a link can carry a non-null createdByUserUID
 * (needed to exercise the edit-page WHERE createdByUserUID = ? filter). Only
 * the NOT-NULL/no-default columns are set; the rest take their defaults.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $orgHandle
 * @return void
 */
function g2ml_iso_seed_user(mysqli $db, int $userUID, string $orgHandle): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`userUID`, `orgHandle`, `username`, `email`, `passwordHash`) '
        . 'VALUES (?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE `orgHandle` = VALUES(`orgHandle`)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare seed-user INSERT failed: ' . mysqli_error($db));
    }

    $username     = 'isouser' . $userUID;
    $email        = 'iso' . $userUID . '@iso.test';
    $passwordHash = 'x';
    mysqli_stmt_bind_param($statement, 'issss', $userUID, $orgHandle, $username, $email, $passwordHash);
    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Seed-user INSERT failed: ' . mysqli_error($db));
    }
}

/**
 * Read the isActive flag for a specific (org, code). Returns -1 if absent.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $shortCode
 * @return int
 */
function g2ml_iso_get_active(mysqli $db, string $orgHandle, string $shortCode): int
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `isActive` FROM `tblShortURLs` WHERE `orgHandle` = ? AND `shortCode` = ? LIMIT 1'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare active-lookup failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $orgHandle, $shortCode);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return -1;
    }

    return (int) $row['isActive'];
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier, two organisations (A = [default], B = orgbiso),
// and a default short domain for A. createShortURL falls back to 'g2my.link'
// for an org without its own short domain, so B needs no domain row.
// ----------------------------------------------------------------------------
g2ml_iso_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_iso_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_iso_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgbiso', 'Org B (isolation test)', 'https://go2my.link/orgb', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_iso_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

// Clean any leftovers so the suite is repeatable.
g2ml_iso_delete($db, 'editload');
g2ml_iso_delete($db, 'crossiso');
g2ml_iso_delete($db, 'defactive');

// ----------------------------------------------------------------------------
// (CR-1) The edit-page load SELECT must PREPARE and return the row. This is the
// EXACT statement from links/edit/index.php — if the selected column set ever
// regresses to a non-existent column again, prepare fails and this test fails.
// ----------------------------------------------------------------------------
test('CR-1 edit-load: the edit-page SELECT prepares and returns the link row', function () use ($db): void
{
    g2ml_iso_delete($db, 'editload');
    g2ml_iso_seed_user($db, 9001, '[default]');
    g2ml_iso_seed_link($db, '[default]', 'editload', 9001, 1);

    $row = dbSelectOne(
        "SELECT s.urlUID, s.shortCode, s.destinationURL, s.title,
                s.urlNotes AS notes, s.categoryID, s.startDate, s.endDate,
                s.isActive, s.createdAt, s.clickCount, s.orgHandle
         FROM tblShortURLs s
         WHERE s.shortCode = ? AND s.createdByUserUID = ?
         LIMIT 1",
        'si',
        ['editload', 9001]
    );

    assert_true(is_array($row), 'The edit-load SELECT must prepare and return a row (not false/null)');
    assert_same('editload', $row['shortCode'], 'The loaded row must be the requested link');
    assert_true(isset($row['urlUID']) && (int) $row['urlUID'] > 0, 'urlUID (the real PK) must be selected and populated');

    g2ml_iso_delete($db, 'editload');
});

// ----------------------------------------------------------------------------
// (SEC-RECHECK-01) An inactive custom alias created in org B must NOT deactivate
// org A's live link that shares the same code (UQ_shortcode_org is per-org).
// ----------------------------------------------------------------------------
test('SEC-RECHECK-01: creating an inactive alias in org B leaves org A active link untouched', function () use ($db): void
{
    g2ml_iso_delete($db, 'crossiso');

    // Org A ([default]) owns an ACTIVE public link 'crossiso' (anonymous creator).
    g2ml_iso_seed_link($db, '[default]', 'crossiso', null, 1);
    assert_same(1, g2ml_iso_get_active($db, '[default]', 'crossiso'), 'Precondition: org A link is active');

    // Org B mints the same code as a custom alias, explicitly INACTIVE.
    $result = createShortURL('https://example.com/orgb-crossiso', [
        'userUID'    => null,
        'orgHandle'  => 'orgbiso',
        'customCode' => 'crossiso',
        'isActive'   => false,
    ]);

    assert_true($result['success'], 'Per-org uniqueness permits org B to reuse the code as its own alias');

    // The crown-jewel assertion: org A's link is STILL active.
    assert_same(1, g2ml_iso_get_active($db, '[default]', 'crossiso'), 'Org A link must remain active (no cross-tenant deactivation)');
    // And org B's own link is inactive, as requested.
    assert_same(0, g2ml_iso_get_active($db, 'orgbiso', 'crossiso'), 'Org B link must be created inactive as requested');

    g2ml_iso_delete($db, 'crossiso');
});

// ----------------------------------------------------------------------------
// (isActive option) Omitting isActive preserves the historical default: active.
// ----------------------------------------------------------------------------
test('createShortURL: omitting isActive defaults the new link to active', function () use ($db): void
{
    g2ml_iso_delete($db, 'defactive');

    $result = createShortURL('https://example.com/def-active', [
        'userUID'    => null,
        'orgHandle'  => 'orgbiso',
        'customCode' => 'defactive',
    ]);

    assert_true($result['success'], 'Create without isActive should succeed');
    assert_same(1, g2ml_iso_get_active($db, 'orgbiso', 'defactive'), 'A link created without an isActive option must default to active');

    g2ml_iso_delete($db, 'defactive');
});
