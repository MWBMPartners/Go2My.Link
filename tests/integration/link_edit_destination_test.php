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
 * 🧪 Integration tests — the shared destination check, driven like an edit (#205)
 * ============================================================================
 *
 * Before #205 the dashboard's edit-link page (web/Go2My.Link/_admin/
 * public_html/pages/links/edit/index.php) ran only g2ml_sanitiseURL() on a
 * new destination, so an EXISTING link could be edited to point at
 * 127.0.0.1 or at one of our own short domains — destinations that
 * createShortURL() would already have refused at CREATE time. The edit page
 * now calls g2ml_validateLinkDestination() (web/Go2My.Link/_functions/
 * shorturl_create.php), the exact function this file exercises.
 *
 * This file does not drive the edit PAGE itself (that needs a real HTTP
 * request, session, and CSRF token). api_v1_urls_test.php extends this
 * item's coverage at a different level — it calls the API's PUT handler
 * function directly, not over HTTP. This file calls the SAME function the
 * edit page calls, against a real link row, and checks the row the way
 * the page's own logic does: on a failed check, no UPDATE runs, so the
 * stored destinationURL must be exactly what it was before the attempt.
 *
 * Registration model: like custom_alias_create_test.php (and unlike
 * shorturl_lookup_test.php, which owns the g2ml_register_integration_tests()
 * hook), this file registers its cases at INCLUDE time — run_integration.php
 * require_once's every test file in the script scope BEFORE
 * g2ml_test_run_all() runs, so the connected $db handle is in scope here.
 * With no reachable test DB the runner skips before this file is included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #205
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
// integration runner connected to. Mirrors custom_alias_create_test.php's own
// resolution; the `!defined()` guards make this safe to repeat across files
// loaded in the same process.
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
// Load the real application function files g2ml_validateLinkDestination()
// depends on (require_once makes repeating this across files harmless).
// ----------------------------------------------------------------------------
$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFunctionsDir . 'db_connect.php';
require_once $g2mlFunctionsDir . 'db_query.php';
require_once $g2mlFunctionsDir . 'security.php';
require_once $g2mlFunctionsDir . 'settings.php';
require_once $g2mlFunctionsDir . 'activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Execute a setup/teardown query, aborting loudly on failure (a setup fault
 * is not characterised behaviour and must not masquerade as an assertion
 * failure).
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_linkeditdest_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Delete a short-URL row by code so the suite is repeatable.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_linkeditdest_test_delete_code(mysqli $db, string $shortCode): void
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
// (createShortURL → getDefaultShortDomain). Mirrors
// custom_alias_create_test.php's own setup; ON DUPLICATE KEY UPDATE makes it
// safe to repeat.
// ----------------------------------------------------------------------------
g2ml_linkeditdest_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_linkeditdest_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_linkeditdest_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

// A SECOND, distinctive custom short domain — not one of the three built-in
// ones — registered for [default] so the "own_domain via the live database
// read" case below exercises the real tblOrgShortDomains query, not the
// unit suite's $GLOBALS override test seam.
g2ml_linkeditdest_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'sx205custom.example', 0, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

// This suite's short codes — clean any leftovers so it is repeatable.
g2ml_linkeditdest_test_delete_code($db, 'sx205edit1');
g2ml_linkeditdest_test_delete_code($db, 'sx205edit2');

// ----------------------------------------------------------------------------
// Editing to a blocked internal host is refused; the row is unchanged.
// ----------------------------------------------------------------------------
test('SX-205: editing a link to http://127.0.0.1/ is refused by the same check the edit page calls; the row is unchanged', function () use ($db): void
{
    g2ml_linkeditdest_test_delete_code($db, 'sx205edit1');

    $created = createShortURL('https://example.com/sx205-original', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'sx205edit1',
    ]);

    assert_true($created['success'], 'Setup: creating the original link must succeed');

    // This is the exact call the edit page makes once it has read $_POST
    // (web/Go2My.Link/_admin/public_html/pages/links/edit/index.php).
    $check = g2ml_validateLinkDestination('http://127.0.0.1/');

    assert_false($check['ok'], 'A loopback destination must be refused on edit, exactly as on create');
    assert_same('blocked_destination', $check['errorCode'], 'errorCode identifies the SSRF-guard failure');

    // The edit page never runs its UPDATE when the check fails (see the
    // page's own `if ($destinationCheck['ok'] === false)` branch) — so
    // nothing here issues an UPDATE either, and the stored row must still
    // hold the ORIGINAL destination.
    $row = dbSelectOne('SELECT destinationURL FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['sx205edit1', '[default]']);
    assert_same('https://example.com/sx205-original', $row['destinationURL'], 'A refused edit must never change the stored destination');

    g2ml_linkeditdest_test_delete_code($db, 'sx205edit1');
});

// ----------------------------------------------------------------------------
// Editing to one of our own BUILT-IN short domains is refused; row unchanged.
// ----------------------------------------------------------------------------
test('SX-205: editing a link to https://g2my.link/... is refused by the same check the edit page calls; the row is unchanged', function () use ($db): void
{
    g2ml_linkeditdest_test_delete_code($db, 'sx205edit2');

    $created = createShortURL('https://example.com/sx205-original-2', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'sx205edit2',
    ]);

    assert_true($created['success'], 'Setup: creating the original link must succeed');

    $check = g2ml_validateLinkDestination('https://g2my.link/loop-attempt');

    assert_false($check['ok'], 'A link back to g2my.link must be refused on edit, exactly as on create');
    assert_same('own_domain', $check['errorCode'], 'errorCode identifies the own-domain failure');

    $row = dbSelectOne('SELECT destinationURL FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['sx205edit2', '[default]']);
    assert_same('https://example.com/sx205-original-2', $row['destinationURL'], 'A refused edit must never change the stored destination');

    g2ml_linkeditdest_test_delete_code($db, 'sx205edit2');
});

// ----------------------------------------------------------------------------
// own_domain via the LIVE tblOrgShortDomains read (no $GLOBALS override set).
// ----------------------------------------------------------------------------
test('SX-205: a registered custom short domain read from the real database is own_domain', function (): void
{
    // Deliberately UNSET — proves this goes through the live dbSelect() read
    // in g2ml_validateLinkDestination(), not the unit suite's test seam.
    unset($GLOBALS['g2ml_link_destination_custom_domains_override']);

    $check = g2ml_validateLinkDestination('https://sx205custom.example/whatever');

    assert_false($check['ok'], 'A registered custom short domain read from the database must be refused');
    assert_same('own_domain', $check['errorCode'], 'errorCode identifies the own-domain failure');
});
