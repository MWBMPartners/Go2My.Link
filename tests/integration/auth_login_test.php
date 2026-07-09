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
 * 🧪 Integration test — loginUser() against the real tblUsers schema (#135)
 * ============================================================================
 *
 * Regression coverage for issue #135 (P0 login blocker): loginUser() SELECTed
 * a non-existent `avatarURL` column from tblUsers. The schema column has
 * always been `avatarPath` (web/_sql/schema/013_core_users.sql:74 and
 * 032_linkspage.sql:83) — `avatarURL` never existed. On a freshly imported
 * schema the prepared SELECT failed at PREPARE, dbSelectOne() returned false,
 * and loginUser() treated every login attempt as "user not found", so nobody
 * could log in on a fresh install. The same stale column name had leaked into
 * data_rights.php, avatar.php, nav.php, and the admin profile page.
 *
 * This test drives the REAL loginUser() from web/_functions/auth.php against a
 * freshly imported test database (see tests/README.md), seeding a user directly
 * via a prepared statement (mirroring session_rebind_test.php) rather than via
 * registerUser(), because registerUser()'s INSERT does not populate the
 * NOT NULL `username` column — a separate, pre-existing gap unrelated to #135
 * that would otherwise mask the assertion this test exists to make.
 *
 * The test:
 *   1. seeds the [default] organisation and a throwaway user with a known
 *      Argon2id password hash (via the real g2ml_hashPassword());
 *   2. calls the real loginUser() with the matching plaintext password;
 *   3. asserts login SUCCEEDS (proving the avatarPath SELECT prepares and
 *      executes cleanly — before the fix this would have failed here with
 *      'Invalid email or password.' even though the credentials are correct);
 *   4. asserts $_SESSION['user_avatar'] is populated (from avatarPath) without
 *      ever surfacing a SQL error, and that the rest of the session shape
 *      (user_uid, user_email) is bound to the authoritative DB row.
 *
 * Registration model: like session_rebind_test.php and activity_log_test.php
 * (and unlike shorturl_lookup_test.php, which owns the
 * g2ml_register_integration_tests() hook), this file registers its case at
 * INCLUDE time. run_integration.php require_once's every test file in the
 * script scope BEFORE g2ml_test_run_all() runs, so the connected $db handle is
 * in scope here and the closure executes after seeding. This avoids a fatal
 * redeclaration of g2ml_register_integration_tests().
 *
 * With no reachable test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
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

/**
 * Execute a setup query, aborting loudly on failure (setup faults are not
 * characterised behaviour and must not masquerade as assertion failures).
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_authlogin_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user (with a real Argon2id password hash) via a prepared
 * statement and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $username
 * @param  string $email
 * @param  string $passwordHash
 * @return int
 */
function g2ml_authlogin_test_insert_user(mysqli $db, string $username, string $email, string $passwordHash): int
{
    $sql = 'INSERT INTO `tblUsers` '
        . '(`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `displayName`, `role`, `emailVerified`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (user) failed: ' . mysqli_error($db));
    }

    $orgHandle     = '[default]';
    $firstName     = 'Ada';
    $lastName      = 'Lovelace';
    $displayName   = 'Ada Lovelace';
    $role          = 'User';
    $emailVerified = 1;
    $isActive      = 1;

    mysqli_stmt_bind_param(
        $statement,
        'ssssssssii',
        $orgHandle,
        $username,
        $email,
        $passwordHash,
        $firstName,
        $lastName,
        $displayName,
        $role,
        $emailVerified,
        $isActive
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (user) failed: ' . $error);
    }

    $userUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $userUID;
}

// ----------------------------------------------------------------------------
// Make the application's getDB() resolve to the SAME test database that the
// runner connected to, by defining the DB_* constants from the same
// environment variables run_integration.php uses. loginUser() runs its queries
// through getDB() (db_query.php → db_connect.php), so this binds the function
// under test to the seeded data. Each constant is guarded individually so this
// file is safe to load whether or not a sibling test file has already defined
// them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlAuthLoginTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlAuthLoginTestHost === false || $g2mlAuthLoginTestHost === '')
    {
        $g2mlAuthLoginTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlAuthLoginTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlAuthLoginTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlAuthLoginTestPortRaw === false || $g2mlAuthLoginTestPortRaw === '')
    {
        $g2mlAuthLoginTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlAuthLoginTestPortRaw);
}

if (!defined('DB_NAME'))
{
    $g2mlAuthLoginTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlAuthLoginTestName === false || $g2mlAuthLoginTestName === '')
    {
        $g2mlAuthLoginTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlAuthLoginTestName);
}

if (!defined('DB_USER'))
{
    $g2mlAuthLoginTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlAuthLoginTestUser === false || $g2mlAuthLoginTestUser === '')
    {
        $g2mlAuthLoginTestUser = 'root';
    }

    define('DB_USER', $g2mlAuthLoginTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlAuthLoginTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlAuthLoginTestPass === false)
    {
        $g2mlAuthLoginTestPass = '';
    }

    define('DB_PASS', $g2mlAuthLoginTestPass);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// require_once is idempotent, so this is safe even if a sibling test file
// already loaded some of these.
// ----------------------------------------------------------------------------
$g2mlAuthLoginFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlAuthLoginFunctionsDir . '/db_connect.php';
require_once $g2mlAuthLoginFunctionsDir . '/db_query.php';
require_once $g2mlAuthLoginFunctionsDir . '/security.php';
require_once $g2mlAuthLoginFunctionsDir . '/settings.php';
require_once $g2mlAuthLoginFunctionsDir . '/activity_logger.php';
require_once $g2mlAuthLoginFunctionsDir . '/session.php';
require_once $g2mlAuthLoginFunctionsDir . '/auth.php';

// ----------------------------------------------------------------------------
// Register the test. Seeding happens inside the closure so it only runs when a
// test DB is present (the runner has already proven $db is connected).
// ----------------------------------------------------------------------------
test('loginUser: succeeds against the real schema and populates the session avatar from avatarPath', function () use ($db): void
{
    // Ensure the [default] org exists (FK target for tblUsers).
    g2ml_authlogin_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_authlogin_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Use a unique marker so reruns do not collide on UNIQUE constraints.
    $marker   = 'authlogin_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $username = $marker;
    $email    = $marker . '@example.test';

    $plainPassword = 'Correct-Horse-9';
    $passwordHash  = g2ml_hashPassword($plainPassword);

    $userUID = g2ml_authlogin_test_insert_user($db, $username, $email, $passwordHash);

    // Reset session state before each login attempt so prior test runs cannot
    // leak stale keys into this assertion.
    $_SESSION = array();

    $result = loginUser($email, $plainPassword);

    assert_same(true, $result['success'], 'A correct password against the real tblUsers row must log in successfully (this fails pre-fix because the avatarPath SELECT breaks at PREPARE)');
    assert_same('', $result['error'], 'A successful login carries no error message');

    assert_same($userUID, ($_SESSION['user_uid'] ?? null), '$_SESSION[user_uid] is bound to the authoritative userUID');
    assert_same($email, ($_SESSION['user_email'] ?? null), '$_SESSION[user_email] matches the logged-in account');
    assert_true(array_key_exists('user_avatar', $_SESSION), '$_SESSION[user_avatar] is populated from avatarPath without a SQL error');
    assert_same('', $_SESSION['user_avatar'], 'A user with no stored avatarPath yields an empty string, not a fatal SELECT failure');

    // Cleanup so the suite is repeatable (session rows first — FK to user).
    g2ml_authlogin_test_exec($db, "DELETE FROM `tblUserSessions` WHERE `userUID` = " . (int) $userUID);
    g2ml_authlogin_test_exec($db, "DELETE FROM `tblUsers` WHERE `userUID` = " . (int) $userUID);
});
