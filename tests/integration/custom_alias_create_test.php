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
 * 🧪 Integration tests — authenticated custom alias create flow (FG-001)
 * ============================================================================
 *
 * Exercises the real createShortURL() against a freshly imported test database
 * (see tests/README.md), driving the application's own DB layer (getDB()) so
 * the full path — sp_generateShortCode, the UQ_shortcode_org unique key, and
 * the custom-alias branch — is covered end to end.
 *
 * Registration model: like session_rebind_test.php (and unlike
 * shorturl_lookup_test.php, which owns the g2ml_register_integration_tests()
 * hook), this file registers its cases at INCLUDE time. run_integration.php
 * require_once's every test file in the script scope BEFORE g2ml_test_run_all()
 * runs, so the connected $db handle is in scope here and the closures execute
 * after seeding. This avoids a fatal redeclaration of
 * g2ml_register_integration_tests(). With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * createShortURL() connects through getDB(), which reads the DB_* constants. We
 * define those from the SAME G2ML_TEST_DB_* environment the runner used, so the
 * app layer talks to the throwaway server. The direct-access guards in the
 * function files key off SCRIPT_FILENAME (the CLI runner script, never the
 * guarded file), so requiring them here is safe.
 *
 * Characterised behaviour (the FG-001 acceptance criteria):
 *   (1) a valid free custom alias produces that exact short code;
 *   (2) a taken alias is rejected with a clear error and writes NO second row
 *       (no random fallback);
 *   (3) a reserved word and a badly formatted alias are both rejected, writing
 *       no row;
 *   (4) no custom alias yields a random code as before;
 *   (5) an empty customCode behaves exactly like the public path (random code).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-001)
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
// Point the application DB layer (getDB) at the same throwaway server the
// integration runner connected to. These mirror run_integration.php's env
// resolution. Defined before the function files load so getDB() can connect.
// getDB() uses a TCP host/port, so the throwaway server must be reachable over
// TCP for these tests (a socket-only server cannot drive getDB()).
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
// Load the real application function files that createShortURL() depends on.
// ----------------------------------------------------------------------------
$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFunctionsDir . 'db_connect.php';
require_once $g2mlFunctionsDir . 'db_query.php';
require_once $g2mlFunctionsDir . 'security.php';
require_once $g2mlFunctionsDir . 'settings.php';
require_once $g2mlFunctionsDir . 'activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Execute a setup query, aborting loudly on failure (setup faults are not
 * characterised behaviour and must not masquerade as assertion failures).
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_alias_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Count rows in tblShortURLs for a given short code (verification helper).
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return int
 */
function g2ml_alias_test_count(mysqli $db, string $shortCode): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM `tblShortURLs` WHERE `shortCode` = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare COUNT failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Delete a short-URL row by code so the suite is repeatable.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_alias_test_delete(mysqli $db, string $shortCode): void
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

// ----------------------------------------------------------------------------
// Shared setup: the [default] org and its default short domain must exist
// (createShortURL → getDefaultShortDomain). The free tier is required by the
// tblOrganisations FK. We seed the minimum and tolerate prior runs.
// ----------------------------------------------------------------------------
g2ml_alias_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_alias_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_alias_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

// Aliases this suite uses — clean any leftovers so it is repeatable.
g2ml_alias_test_delete($db, 'fg001free');
g2ml_alias_test_delete($db, 'fg001dup');

// ----------------------------------------------------------------------------
// (1) A valid, free custom alias produces that EXACT short code.
// ----------------------------------------------------------------------------
test('FG-001 create: a valid free custom alias produces that exact short code', function () use ($db): void
{
    g2ml_alias_test_delete($db, 'fg001free');

    $result = createShortURL('https://example.com/fg001-free', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg001free',
    ]);

    assert_true($result['success'], 'A free, valid alias should create successfully');
    assert_same('fg001free', $result['shortCode'], 'The created short code must equal the requested alias verbatim');
    assert_same(1, g2ml_alias_test_count($db, 'fg001free'), 'Exactly one row should exist for the alias');

    g2ml_alias_test_delete($db, 'fg001free');
});

// ----------------------------------------------------------------------------
// (2) A taken alias is rejected — clear error, NO second row, NO fallback.
// ----------------------------------------------------------------------------
test('FG-001 create: a duplicate custom alias is rejected with no second row and no random fallback', function () use ($db): void
{
    g2ml_alias_test_delete($db, 'fg001dup');

    // First create claims the alias.
    $first = createShortURL('https://example.com/fg001-dup-1', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg001dup',
    ]);

    assert_true($first['success'], 'The first create of the alias should succeed');
    assert_same(1, g2ml_alias_test_count($db, 'fg001dup'), 'One row should exist after the first create');

    $rowsBefore = g2ml_alias_test_count($db, 'fg001dup');

    // Second create with the SAME alias must be rejected.
    $second = createShortURL('https://example.com/fg001-dup-2', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg001dup',
    ]);

    assert_false($second['success'], 'A duplicate alias must not create a second row');
    assert_same(null, $second['shortCode'], 'A rejected duplicate returns no short code (no random fallback)');
    assert_contains('already taken', $second['error'], 'The error must clearly state the alias is already taken');
    assert_same($rowsBefore, g2ml_alias_test_count($db, 'fg001dup'), 'The row count must be unchanged after the rejected duplicate');

    g2ml_alias_test_delete($db, 'fg001dup');
});

// ----------------------------------------------------------------------------
// (3a) A reserved word is rejected and writes no row.
// ----------------------------------------------------------------------------
test('FG-001 create: a reserved-word alias is rejected and writes no row', function () use ($db): void
{
    $before = g2ml_alias_test_count($db, 'admin');

    $result = createShortURL('https://example.com/fg001-reserved', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'admin',
    ]);

    assert_false($result['success'], 'A reserved alias must be rejected');
    assert_same(null, $result['shortCode'], 'A rejected reserved alias returns no short code');
    assert_same($before, g2ml_alias_test_count($db, 'admin'), 'No row should be written for a reserved alias');
});

// ----------------------------------------------------------------------------
// (3b) A badly formatted alias is rejected and writes no row.
// ----------------------------------------------------------------------------
test('FG-001 create: a malformed alias is rejected and writes no row', function () use ($db): void
{
    // Contains a disallowed space and is below the 3-char minimum — either
    // alone fails the format regex.
    $badAlias = 'a b';
    $before   = g2ml_alias_test_count($db, $badAlias);

    $result = createShortURL('https://example.com/fg001-bad', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => $badAlias,
    ]);

    assert_false($result['success'], 'A malformed alias must be rejected');
    assert_same(null, $result['shortCode'], 'A rejected malformed alias returns no short code');
    assert_same($before, g2ml_alias_test_count($db, $badAlias), 'No row should be written for a malformed alias');
});

// ----------------------------------------------------------------------------
// (4) No custom alias → a random code is generated as before.
// ----------------------------------------------------------------------------
test('FG-001 create: without a custom alias a random short code is generated', function () use ($db): void
{
    $result = createShortURL('https://example.com/fg001-random', [
        'userUID'   => null,
        'orgHandle' => '[default]',
    ]);

    assert_true($result['success'], 'A create without an alias should succeed');
    assert_true(is_string($result['shortCode']) && $result['shortCode'] !== '', 'A random short code should be returned');
    assert_same(1, g2ml_alias_test_count($db, $result['shortCode']), 'The generated random code should have exactly one row');

    if (is_string($result['shortCode']))
    {
        g2ml_alias_test_delete($db, $result['shortCode']);
    }
});

// ----------------------------------------------------------------------------
// (5) An empty customCode behaves exactly like omitting it (random code). This
//     guards the "anonymous/public path unaffected" criterion: the public
//     create never sets customCode, which is exactly an empty value.
// ----------------------------------------------------------------------------
test('FG-001 create: an empty customCode behaves exactly like omitting it (random code)', function () use ($db): void
{
    $result = createShortURL('https://example.com/fg001-empty', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => '',
    ]);

    assert_true($result['success'], 'An empty customCode should fall through to random generation');
    assert_true(is_string($result['shortCode']) && $result['shortCode'] !== '', 'A random short code should be returned for an empty customCode');

    if (is_string($result['shortCode']))
    {
        g2ml_alias_test_delete($db, $result['shortCode']);
    }
});
