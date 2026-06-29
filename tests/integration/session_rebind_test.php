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
 * 🧪 Integration test — validateUserSession() re-binds identity from the DB
 * ============================================================================
 *
 * Covers finding F-007: validateUserSession() must (re)bind the in-memory user
 * identity ($_SESSION['user_uid']) from the authoritative tblUserSessions row,
 * not from whatever value happened to be present in the PHP session. This is a
 * defence-in-depth measure against session confusion / fixation where the
 * token and the stored identity could otherwise diverge.
 *
 * The test:
 *   1. seeds a [default] organisation, a throwaway user, and an active,
 *      unexpired session row whose sessionToken is the SHA-256 hash of a known
 *      plaintext token;
 *   2. deliberately poisons $_SESSION['user_uid'] with a wrong value;
 *   3. calls validateUserSession();
 *   4. asserts it returned true AND that $_SESSION['user_uid'] now equals the
 *      authoritative userUID from the seeded session row.
 *
 * Registration model: unlike shorturl_lookup_test.php (which uses the
 * g2ml_register_integration_tests() hook), this file registers its cases at
 * include time. run_integration.php require_once's every test file in the
 * script scope BEFORE g2ml_test_run_all() runs, so the connected $db handle is
 * in scope here and the closures execute after seeding. This avoids a fatal
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
function g2ml_session_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user via a prepared statement and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $username
 * @param  string $email
 * @return int
 */
function g2ml_session_test_insert_user(mysqli $db, string $username, string $email): int
{
    $sql = 'INSERT INTO `tblUsers` '
        . '(`orgHandle`, `username`, `email`, `passwordHash`, `role`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?)';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (user) failed: ' . mysqli_error($db));
    }

    $orgHandle    = '[default]';
    $passwordHash = 'x';
    $role         = 'User';
    $isActive     = 1;

    mysqli_stmt_bind_param(
        $statement,
        'sssssi',
        $orgHandle,
        $username,
        $email,
        $passwordHash,
        $role,
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

/**
 * Insert an active, unexpired session row via a prepared statement.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $tokenHash
 * @return void
 */
function g2ml_session_test_insert_session(mysqli $db, int $userUID, string $tokenHash): void
{
    $sql = 'INSERT INTO `tblUserSessions` '
        . '(`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (session) failed: ' . mysqli_error($db));
    }

    $ipAddress  = '203.0.113.7';
    $userAgent  = 'IntegrationTest/1.0';
    $deviceInfo = 'Test on Test';
    $expiresAt  = date('Y-m-d H:i:s', time() + 3600);
    $isActive   = 1;

    mysqli_stmt_bind_param(
        $statement,
        'isssssi',
        $userUID,
        $tokenHash,
        $ipAddress,
        $userAgent,
        $deviceInfo,
        $expiresAt,
        $isActive
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (session) failed: ' . $error);
    }

    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Make the application's getDB() resolve to the SAME test database that the
// runner connected to, by defining the DB_* constants from the same
// environment variables run_integration.php uses. validateUserSession() runs
// its queries through getDB() (db_query.php → db_connect.php), so this binds
// the function under test to the seeded data.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlSessionTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlSessionTestHost === false || $g2mlSessionTestHost === '')
    {
        $g2mlSessionTestHost = '127.0.0.1';
    }

    $g2mlSessionTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlSessionTestPortRaw === false || $g2mlSessionTestPortRaw === '')
    {
        $g2mlSessionTestPortRaw = '3306';
    }

    $g2mlSessionTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlSessionTestName === false || $g2mlSessionTestName === '')
    {
        $g2mlSessionTestName = 'mwtools_Go2MyLink';
    }

    $g2mlSessionTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlSessionTestUser === false || $g2mlSessionTestUser === '')
    {
        $g2mlSessionTestUser = 'root';
    }

    $g2mlSessionTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlSessionTestPass === false)
    {
        $g2mlSessionTestPass = '';
    }

    define('DB_HOST', $g2mlSessionTestHost);
    define('DB_PORT', (int) $g2mlSessionTestPortRaw);
    define('DB_NAME', $g2mlSessionTestName);
    define('DB_USER', $g2mlSessionTestUser);
    define('DB_PASS', $g2mlSessionTestPass);
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the application code under test and its DB layer.
// ----------------------------------------------------------------------------
$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlFunctionsDir . '/db_connect.php';
require_once $g2mlFunctionsDir . '/db_query.php';
require_once $g2mlFunctionsDir . '/session.php';

// ----------------------------------------------------------------------------
// Register the test. Seeding happens inside the closure so it only runs when a
// test DB is present (the runner has already proven $db is connected).
// ----------------------------------------------------------------------------
test('validateUserSession: re-binds $_SESSION[user_uid] from the authoritative DB session row', function () use ($db): void
{
    // Ensure the [default] org exists (FK target for tblUsers).
    g2ml_session_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_session_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Use a unique marker so reruns do not collide on UNIQUE constraints.
    $marker   = 'sessreb_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $username = $marker;
    $email    = $marker . '@example.test';

    $userUID = g2ml_session_test_insert_user($db, $username, $email);

    // Plaintext token lives only in $_SESSION; the DB stores its SHA-256 hash.
    $plainToken = 'plain_' . $marker;
    $tokenHash  = hash('sha256', $plainToken);

    g2ml_session_test_insert_session($db, $userUID, $tokenHash);

    // Poison the in-memory identity to a deliberately wrong value, then place
    // the valid token. A correct re-bind must overwrite the poisoned value.
    $_SESSION['session_token'] = $plainToken;
    $_SESSION['user_uid']      = $userUID + 999999;

    $isValid = validateUserSession();

    assert_same(true, $isValid, 'A live, active, unexpired session validates as true');
    assert_same($userUID, ($_SESSION['user_uid'] ?? null), 'user_uid is re-bound from the authoritative DB session row');

    // Cleanup so the suite is repeatable (session row first — FK to user).
    g2ml_session_test_exec($db, "DELETE FROM `tblUserSessions` WHERE `userUID` = " . (int) $userUID);
    g2ml_session_test_exec($db, "DELETE FROM `tblUsers` WHERE `userUID` = " . (int) $userUID);
});
