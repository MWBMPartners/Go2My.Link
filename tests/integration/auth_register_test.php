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
 * 🧪 Integration test — registerUser() against the real tblUsers schema (#136)
 * ============================================================================
 *
 * Regression coverage for issue #136 (P0 registration blocker): registerUser()
 * INSERTed into tblUsers without a `username` value, but the schema column is
 * `username VARCHAR(50) NOT NULL` with no default and `UNIQUE KEY UQ_username`
 * (web/_sql/schema/013_core_users.sql:32,129). Under MySQL strict mode the
 * INSERT failed at execute time, so no one could register on a fresh install.
 * The registration form collects only email/password/firstName/lastName —
 * there is no username field — so the fix auto-derives a unique username from
 * the email's local-part inside registerUser() itself
 * (g2ml_deriveUsernameBase() / g2ml_generateUniqueUsername() in
 * web/_functions/auth.php).
 *
 * This test drives the REAL registerUser() from web/_functions/auth.php
 * against a freshly imported test database (see tests/README.md).
 *
 * The test:
 *   1. seeds the [default] organisation (FK target for tblUsers);
 *   2. calls the real registerUser() for a first email whose local-part is a
 *      unique test marker (e.g. "sam<marker>@a.example.test");
 *   3. asserts registration SUCCEEDS (proving the INSERT — including the
 *      auto-derived username — prepares and executes cleanly; before the fix
 *      this would fail with "An error occurred during registration." even
 *      though every input was valid);
 *   4. reads back the stored username via a direct prepared SELECT (the
 *      return value of registerUser() does not include it) and asserts it is
 *      populated and matches ^[A-Za-z0-9._-]{3,50}$;
 *   5. calls registerUser() again for a SECOND email that shares the same
 *      local-part but a different domain (e.g. "sam<marker>@b.example.test"),
 *      mirroring the acceptance criterion's sam@a.com / sam@b.com example;
 *   6. asserts the second registration also SUCCEEDS and that its stored
 *      username DIFFERS from the first — proving the uniqueness/collision
 *      handling (UQ_username) works rather than merely deriving a base once.
 *
 * Registration model: like auth_login_test.php, session_rebind_test.php, and
 * activity_log_test.php (and unlike shorturl_lookup_test.php, which owns the
 * g2ml_register_integration_tests() hook), this file registers its cases at
 * INCLUDE time. run_integration.php require_once's every test file in the
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
function g2ml_authregister_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Read back the stored username for a userUID via a prepared statement.
 * registerUser() does not return the username in its result array, so tests
 * that need to inspect it must query the authoritative row directly.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @return string|null
 */
function g2ml_authregister_test_fetch_username(mysqli $db, int $userUID): ?string
{
    $sql = 'SELECT `username` FROM `tblUsers` WHERE `userUID` = ? LIMIT 1';

    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (fetch username) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'i', $userUID);

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Execute (fetch username) failed: ' . $error);
    }

    $result = mysqli_stmt_get_result($statement);

    if ($result === false)
    {
        mysqli_stmt_close($statement);
        throw new RuntimeException('get_result (fetch username) failed: ' . mysqli_error($db));
    }

    $row = mysqli_fetch_assoc($result);

    mysqli_stmt_close($statement);

    if ($row === null || $row === false)
    {
        return null;
    }

    return (string) $row['username'];
}

// ----------------------------------------------------------------------------
// Make the application's getDB() resolve to the SAME test database that the
// runner connected to, by defining the DB_* constants from the same
// environment variables run_integration.php uses. registerUser() runs its
// queries through getDB() (db_query.php → db_connect.php), so this binds the
// function under test to the seeded data. Each constant is guarded
// individually so this file is safe to load whether or not a sibling test
// file (e.g. auth_login_test.php) has already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlAuthRegisterTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlAuthRegisterTestHost === false || $g2mlAuthRegisterTestHost === '')
    {
        $g2mlAuthRegisterTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlAuthRegisterTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlAuthRegisterTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlAuthRegisterTestPortRaw === false || $g2mlAuthRegisterTestPortRaw === '')
    {
        $g2mlAuthRegisterTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlAuthRegisterTestPortRaw);
}

if (!defined('DB_NAME'))
{
    $g2mlAuthRegisterTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlAuthRegisterTestName === false || $g2mlAuthRegisterTestName === '')
    {
        $g2mlAuthRegisterTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlAuthRegisterTestName);
}

if (!defined('DB_USER'))
{
    $g2mlAuthRegisterTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlAuthRegisterTestUser === false || $g2mlAuthRegisterTestUser === '')
    {
        $g2mlAuthRegisterTestUser = 'root';
    }

    define('DB_USER', $g2mlAuthRegisterTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlAuthRegisterTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlAuthRegisterTestPass === false)
    {
        $g2mlAuthRegisterTestPass = '';
    }

    define('DB_PASS', $g2mlAuthRegisterTestPass);
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
$g2mlAuthRegisterFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlAuthRegisterFunctionsDir . '/db_connect.php';
require_once $g2mlAuthRegisterFunctionsDir . '/db_query.php';
require_once $g2mlAuthRegisterFunctionsDir . '/security.php';
require_once $g2mlAuthRegisterFunctionsDir . '/settings.php';
require_once $g2mlAuthRegisterFunctionsDir . '/activity_logger.php';
require_once $g2mlAuthRegisterFunctionsDir . '/session.php';
require_once $g2mlAuthRegisterFunctionsDir . '/auth.php';

// ----------------------------------------------------------------------------
// Register the tests. Seeding happens inside the closures so it only runs
// when a test DB is present (the runner has already proven $db is connected).
// ----------------------------------------------------------------------------
test('registerUser: succeeds against the real schema and populates a valid, unique username', function () use ($db): void
{
    // Ensure the [default] org exists (FK target for tblUsers).
    g2ml_authregister_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
        . "VALUES ('free', 'Free') "
        . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
    );

    g2ml_authregister_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
    );

    // Use a unique marker so reruns do not collide on UNIQUE constraints
    // (UQ_email). Both emails below share this local-part, mirroring the
    // acceptance criterion's sam@a.com / sam@b.com example.
    $marker      = 'authreg_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $localPart   = 'sam' . $marker;
    $firstEmail  = $localPart . '@a.example.test';
    $secondEmail = $localPart . '@b.example.test';

    $plainPassword = 'Correct-Horse-9';

    $firstResult = registerUser($firstEmail, $plainPassword, 'Sam', 'One');

    assert_same(true, $firstResult['success'], 'Registration must succeed on a strict-mode fresh schema (this fails pre-fix because the username-less INSERT violates the NOT NULL constraint)');
    assert_same('', $firstResult['error'], 'A successful registration carries no error message');
    assert_true(($firstResult['userUID'] ?? 0) > 0, 'A successful registration returns a positive userUID');

    $firstUserUID  = (int) $firstResult['userUID'];
    $firstUsername = g2ml_authregister_test_fetch_username($db, $firstUserUID);

    assert_true($firstUsername !== null, 'The inserted row has a stored username');
    assert_same(1, preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $firstUsername), 'The auto-derived username matches the schema/installer charset and length rule (^[A-Za-z0-9._-]{3,50}$)');

    // Second registration with the SAME email local-part but a different
    // domain: the derived username base is identical, so this only succeeds
    // with distinct usernames if the collision handling actually works.
    $secondResult = registerUser($secondEmail, $plainPassword, 'Sam', 'Two');

    assert_same(true, $secondResult['success'], 'A second registration sharing the first email\'s local-part must also succeed');
    assert_same('', $secondResult['error'], 'A successful second registration carries no error message');

    $secondUserUID  = (int) $secondResult['userUID'];
    $secondUsername = g2ml_authregister_test_fetch_username($db, $secondUserUID);

    assert_true($secondUsername !== null, 'The second inserted row has a stored username');
    assert_same(1, preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $secondUsername), 'The second auto-derived username also matches the charset/length rule');
    assert_not_same($firstUsername, $secondUsername, 'Two registrations whose emails share a local-part receive DISTINCT usernames (UQ_username collision handling)');

    // Cleanup so the suite is repeatable.
    g2ml_authregister_test_exec($db, "DELETE FROM `tblUserSessions` WHERE `userUID` IN (" . $firstUserUID . ", " . $secondUserUID . ")");
    g2ml_authregister_test_exec($db, "DELETE FROM `tblUsers` WHERE `userUID` IN (" . $firstUserUID . ", " . $secondUserUID . ")");
});
