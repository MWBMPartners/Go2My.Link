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
 * 🧪 Integration — staff switch a short link off and on (#325)
 * ============================================================================
 *
 * Against a real database: the redirect procedure refuses a link staff have
 * switched off (direct, through a forwarding code, and on an organisation's own
 * domain), the owner's edit page and the API cannot switch it on again, the
 * action is recorded, and migration 046 adds what it says.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #325
 * ============================================================================
 */

declare(strict_types=1);

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

$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFunctionsDir . 'db_connect.php';
require_once $g2mlFunctionsDir . 'db_query.php';
require_once $g2mlFunctionsDir . 'security.php';
require_once $g2mlFunctionsDir . 'settings.php';
require_once $g2mlFunctionsDir . 'dnt.php';
require_once $g2mlFunctionsDir . 'activity_logger.php';
require_once $g2mlFunctionsDir . 'api_response.php';
require_once $g2mlFunctionsDir . 'api_auth.php';
require_once $g2mlFunctionsDir . 'api_ratelimit.php';
require_once $g2mlFunctionsDir . 'org.php';
require_once $g2mlFunctionsDir . 'data_rights.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/urls.php';

if (is_file(dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/staff_links.php'))
{
    require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/staff_links.php';
}

function g2ml_stafflink_test_run(string $sql, string $types = '', array $params = []): void
{
    if (dbUpdate($sql, $types, $params) === false)
    {
        throw new RuntimeException('Query failed: ' . $sql);
    }
}

/**
 * A test "world": two users (an owner and a staff member), one organisation
 * with a verified short domain, and codes that start with one marker.
 *
 * @return array{marker: string, owner: int, staff: int, org: string, domain: string}
 */
function g2ml_stafflink_test_world(): array
{
    g2ml_stafflink_test_run("INSERT IGNORE INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) VALUES ('[default]', 'g2my.link', 1, 1)");
    g2ml_stafflink_test_run("UPDATE `tblOrgShortDomains` SET `verificationStatus` = 'verified', `isActive` = 1 WHERE `shortDomain` = 'g2my.link'");

    $marker = 'sl' . bin2hex(random_bytes(4));
    $world  = ['marker' => $marker, 'org' => $marker . 'org', 'domain' => $marker . '.example'];

    g2ml_stafflink_test_run('INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) VALUES (?, \'Staff Test Org\', \'free\', 1)', 's', [$world['org']]);
    g2ml_stafflink_test_run("INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`) VALUES (?, ?, 1, 1, 'verified')", 'ss', [$world['org'], $world['domain']]);

    foreach (['owner', 'staff'] as $who)
    {
        $uid = dbInsert(
            'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) VALUES (\'[default]\', ?, ?, \'x\', \'Staff\', \'Tester\', 1)',
            'ss',
            [$marker . $who, $marker . $who . '@staff325.test']
        );
        $world[$who] = (int) $uid;
    }

    return $world;
}

function g2ml_stafflink_test_cleanup(array $world): void
{
    $like = $world['marker'] . '%';
    g2ml_stafflink_test_run('DELETE FROM `tblActivityLog` WHERE `shortCode` LIKE ?', 's', [$like]);
    g2ml_stafflink_test_run('DELETE FROM `tblShortURLs` WHERE `shortCode` LIKE ?', 's', [$like]);
    g2ml_stafflink_test_run('DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = ?', 's', [$world['org']]);
    g2ml_stafflink_test_run('DELETE FROM `tblUsers` WHERE `username` LIKE ?', 's', [$like]);
    g2ml_stafflink_test_run('DELETE FROM `tblOrganisations` WHERE `orgHandle` = ?', 's', [$world['org']]);
}

/**
 * Create a link with the real creation function and return its row number.
 */
function g2ml_stafflink_test_link(array $world, string $suffix, string $orgHandle = '[default]'): int
{
    $code    = $world['marker'] . $suffix;
    $created = createShortURL('https://example.com/' . $code, [
        'userUID'    => $world['owner'],
        'orgHandle'  => $orgHandle,
        'customCode' => $code,
    ]);
    assert_true($created['success'], 'Setup: creating ' . $code . ' must succeed: ' . (string) ($created['error'] ?? ''));

    $row = dbSelectOne('SELECT `urlUID` FROM `tblShortURLs` WHERE `shortCode` = ? AND `orgHandle` = ?', 'ss', [$code, $orgHandle]);

    return (int) $row['urlUID'];
}

/**
 * What a visitor gets from the real redirect procedure.
 */
function g2ml_stafflink_test_status(string $code, string $domain = 'g2my.link'): string
{
    $result = dbCallProcedure(
        'sp_lookupShortURL',
        [$domain, $code],
        'ss',
        ['@slDest', '@slStatus', '@slOrg', '@slS', '@slM', '@slC', '@slT', '@slN']
    );

    return (string) $result['@slStatus'];
}

function g2ml_stafflink_test_row(int $urlUID): array
{
    return dbSelectOne('SELECT * FROM `tblShortURLs` WHERE `urlUID` = ?', 'i', [$urlUID]);
}

/**
 * Run a real dashboard page in a clean child process against the test database,
 * as the given signed-in person.
 *
 * @return string  The page's HTML.
 */
function g2ml_stafflink_test_page(string $page, int $userUID, array $get, array $post = [], string $csrfName = ''): string
{
    $pagePath = 'Go2My.Link/_admin/public_html/pages/' . $page;
    $signedIn = 'true';

    // The public information page, for a visitor who may not be signed in.
    if ($page === 'info/index.php')
    {
        $pagePath = 'Go2My.Link/public_html/pages/' . $page;

        if ($userUID === 0)
        {
            $signedIn = 'false';
        }
    }

    $root   = dirname(__DIR__, 2);
    $script = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";

    foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_CHARSET'] as $constant)
    {
        $script .= 'define(' . var_export($constant, true) . ', ' . var_export(constant($constant), true) . ');' . "\n";
    }

    $script .= '$_SESSION = array(\'user_uid\' => ' . $userUID . ');' . "\n";
    $script .= 'function getCurrentUser(): array { return array(\'userUID\' => ' . $userUID . ', \'orgHandle\' => \'[default]\'); }' . "\n";

    if ($page === 'info/index.php')
    {
        $script .= 'function isAuthenticated(): bool { return ' . $signedIn . '; }' . "\n";
    }

    foreach (['_functions/db_connect.php', '_functions/db_query.php', '_functions/security.php', '_functions/settings.php', '_functions/activity_logger.php', '_includes/accessibility.php', 'Go2My.Link/_functions/shorturl_create.php', 'Go2My.Link/_functions/info_display.php'] as $file)
    {
        $script .= 'require_once ' . var_export($root . '/web/' . $file, true) . ';' . "\n";
    }

    $method = 'GET';

    if ($csrfName !== '')
    {
        $method = 'POST';
    }

    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'' . $method . '\';' . "\n";
    $script .= '$_GET = ' . var_export($get, true) . '; $_POST = ' . var_export($post, true) . ';' . "\n";

    if ($csrfName !== '')
    {
        $script .= '$_POST[\'_csrf_token\'] = g2ml_generateCSRFToken(' . var_export($csrfName, true) . ');' . "\n";
    }

    $script .= 'require ' . var_export($root . '/web/' . $pagePath, true) . ';' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_stafflink_');
    file_put_contents($tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1');
    unlink($tempFile);

    return (string) $output;
}

test('#325: switching a link off stops the redirect, records who and why, and cannot be repeated', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid  = g2ml_stafflink_test_link($world, 'a');
        $code = $world['marker'] . 'a';
        assert_same('success', g2ml_stafflink_test_status($code));

        $result = g2ml_staffSetLinkState($uid, 'off', $world['staff'], str_repeat('é', 600));
        assert_true($result['success'], $result['errorCode']);
        assert_true($result['logged']);
        assert_same('staff_disabled', g2ml_stafflink_test_status($code));

        $row = g2ml_stafflink_test_row($uid);
        assert_same(0, (int) $row['isActive']);
        assert_true($row['staffDisabledAt'] !== null);
        assert_same($world['staff'], (int) $row['staffDisabledByUserUID']);
        assert_same(500, mb_strlen($row['staffDisableReason'], 'UTF-8'));

        $log = dbSelectOne("SELECT * FROM `tblActivityLog` WHERE `shortCode` = ? AND `logAction` = 'staff_link_disabled'", 's', [$code]);
        assert_true($log !== null, 'An activity-log row must exist');
        assert_same($world['staff'], (int) $log['userUID']);
        assert_same('[default]', $log['orgHandle']);
        assert_same($uid, (int) json_decode($log['logData'], true)['urlUID']);

        assert_same('no_change', g2ml_staffSetLinkState($uid, 'off', $world['staff'])['errorCode']);
        assert_same('invalid', g2ml_staffSetLinkState($uid, 'delete', $world['staff'])['errorCode']);
        assert_same('not_found', g2ml_staffSetLinkState(999999999, 'off', $world['staff'])['errorCode']);

        $unblocked = g2ml_staffSetLinkState($uid, 'unblock', $world['staff'], 'cleared');
        assert_true($unblocked['success']);
        assert_true($unblocked['restoredActive'], 'It was on before the block');
        assert_same('success', g2ml_stafflink_test_status($code));
        $row = g2ml_stafflink_test_row($uid);
        assert_same(null, $row['staffDisabledAt']);
        assert_same(null, $row['staffDisabledByUserUID']);
        assert_same(null, $row['staffDisableReason']);
        $log = dbSelectOne("SELECT `userUID` FROM `tblActivityLog` WHERE `shortCode` = ? AND `logAction` = 'staff_link_unblocked'", 's', [$code]);
        assert_same($world['staff'], (int) $log['userUID']);
        assert_same('no_change', g2ml_staffSetLinkState($uid, 'unblock', $world['staff'])['errorCode']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the record is kept even with activity logging off and Do Not Track on', function (): void
{
    $world = g2ml_stafflink_test_world();
    getSetting('analytics.log_activity', true);
    $savedCache = $GLOBALS['_g2ml_settings_cache'];

    try
    {
        $GLOBALS['_g2ml_settings_cache']['System']['analytics.log_activity'] = false;
        $GLOBALS['_g2ml_settings_cache']['System']['compliance.always_assume_dnt'] = true;

        $uid = g2ml_stafflink_test_link($world, 'a');
        assert_true(g2ml_staffSetLinkState($uid, 'off', $world['staff'])['logged']);

        $row = dbSelectOne('SELECT COUNT(*) AS `cnt` FROM `tblActivityLog` WHERE `shortCode` = ? AND `logAction` = \'staff_link_disabled\'', 's', [$world['marker'] . 'a']);
        assert_same(1, (int) $row['cnt']);
    }
    finally
    {
        $GLOBALS['_g2ml_settings_cache'] = $savedCache;
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: a link its owner switched off can be locked by staff', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid = g2ml_stafflink_test_link($world, 'a');
        g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `isActive` = 0 WHERE `urlUID` = ?', 'i', [$uid]);

        assert_true(g2ml_staffSetLinkState($uid, 'off', $world['staff'])['success']);
        assert_true(g2ml_stafflink_test_row($uid)['staffDisabledAt'] !== null);

        $unblocked = g2ml_staffSetLinkState($uid, 'unblock', $world['staff']);
        assert_true($unblocked['success']);
        assert_false($unblocked['restoredActive'], 'Its owner had switched it off, so it stays off (D-026)');
        $row = g2ml_stafflink_test_row($uid);
        assert_same(0, (int) $row['isActive']);
        assert_same(null, $row['staffPreviousIsActive']);
        assert_same('inactive', g2ml_stafflink_test_status($world['marker'] . 'a'));
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: forwarding codes stop too, and switching off a forwarding code leaves its target working', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $targetUID = g2ml_stafflink_test_link($world, 't');
        $aliasUID  = g2ml_stafflink_test_link($world, 'f');
        $target    = $world['marker'] . 't';
        $alias     = $world['marker'] . 'f';
        g2ml_stafflink_test_run("UPDATE `tblShortURLs` SET `redirectAlias` = ?, `destinationType` = 'alias' WHERE `urlUID` = ?", 'si', [$target, $aliasUID]);
        assert_same('success', g2ml_stafflink_test_status($alias));

        g2ml_staffSetLinkState($targetUID, 'off', $world['staff']);
        assert_same('staff_disabled', g2ml_stafflink_test_status($alias));

        g2ml_staffSetLinkState($targetUID, 'unblock', $world['staff']);
        g2ml_staffSetLinkState($aliasUID, 'off', $world['staff']);
        assert_same('staff_disabled', g2ml_stafflink_test_status($alias));
        assert_same('success', g2ml_stafflink_test_status($target));
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the same code in two organisations is listed twice, narrowed by an organisation address, and switched separately', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $code       = $world['marker'] . 'a';
        $defaultUID = g2ml_stafflink_test_link($world, 'a');
        // Inserted directly: this link lives on the organisation's own domain.
        g2ml_stafflink_test_run(
            'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `createdByUserUID`) VALUES (?, ?, \'https://example.com/org\', ?)',
            'ssi',
            [$world['org'], $code, $world['owner']]
        );

        $all = g2ml_staffFindLinks($code);
        assert_same(2, count($all['links']));
        $viaDefault = g2ml_staffFindLinks($code, 'g2my.link');
        assert_same(2, count($viaDefault['links']), 'The shared address lists every match');

        $viaOrg = g2ml_staffFindLinks($code, $world['domain']);
        assert_same(1, count($viaOrg['links']));
        assert_same($world['org'], $viaOrg['narrowedTo']);
        assert_same($world['org'], $viaOrg['links'][0]['orgHandle']);
        assert_same($world['marker'] . 'owner@staff325.test', $viaOrg['links'][0]['ownerEmail']);

        g2ml_staffSetLinkState((int) $viaOrg['links'][0]['urlUID'], 'off', $world['staff']);
        assert_same('staff_disabled', g2ml_stafflink_test_status($code, $world['domain']));
        assert_same('success', g2ml_stafflink_test_status($code));
        assert_same(null, g2ml_stafflink_test_row($defaultUID)['staffDisabledAt']);

        assert_same([], g2ml_staffFindLinks($world['marker'] . 'none')['links']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the owner cannot switch a staff-disabled link on from the edit page', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid  = g2ml_stafflink_test_link($world, 'a');
        $code = $world['marker'] . 'a';
        g2ml_staffSetLinkState($uid, 'off', $world['staff'], 'secret reason');

        $post = ['destination_url' => 'https://example.com/edited', 'title' => 'Edited', 'is_active' => '1'];
        $html = g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post, 'edit_link_form');

        $row = g2ml_stafflink_test_row($uid);
        assert_same('Edited', $row['title'], 'The rest of the edit is saved: ' . substr($html, 0, 600));
        assert_same(0, (int) $row['isActive']);
        assert_true($row['staffDisabledAt'] !== null);
        assert_same('staff_disabled', g2ml_stafflink_test_status($code));
        assert_contains('staff have switched this link off', $html);
        assert_true(preg_match('/id="is-active"[^>]*disabled="disabled"/s', $html) === 1, 'The switch must be disabled');
        assert_true(preg_match('/id="is-active"[^>]*\schecked/s', $html) !== 1, 'The switch must not be shown as on');
        assert_false(str_contains($html, 'secret reason'), 'The owner is not shown the reason');

        $list = g2ml_stafflink_test_page('links/index.php', $world['owner'], []);
        assert_contains('Switched off by staff', $list);

        // Saving the blocked page (its switch is disabled, so nothing is
        // posted for it) does not count as switching the link off.
        unset($post['is_active']);
        g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post, 'edit_link_form');
        assert_same(1, (int) g2ml_stafflink_test_row($uid)['staffPreviousIsActive']);

        // A form opened before the block, with the switch left off, does.
        g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post + ['active_switch_shown' => '1'], 'edit_link_form');
        assert_same(0, (int) g2ml_stafflink_test_row($uid)['staffPreviousIsActive']);

        // After staff remove the block the owner's switch works again.
        g2ml_staffSetLinkState($uid, 'unblock', $world['staff']);
        assert_same(0, (int) g2ml_stafflink_test_row($uid)['isActive']);
        g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post, 'edit_link_form');
        assert_same(0, (int) g2ml_stafflink_test_row($uid)['isActive']);
        $post['is_active'] = '1';
        g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post, 'edit_link_form');
        assert_same(1, (int) g2ml_stafflink_test_row($uid)['isActive']);

        // A form opened during a block (no switch posted) and saved after the
        // block was removed leaves a restored link on.
        unset($post['is_active']);
        g2ml_stafflink_test_page('links/edit/index.php', $world['owner'], ['id' => $code], $post, 'edit_link_form');
        assert_same(1, (int) g2ml_stafflink_test_row($uid)['isActive']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the database refuses any other attempt to switch a staff-disabled link on', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid = g2ml_stafflink_test_link($world, 'a');
        g2ml_staffSetLinkState($uid, 'off', $world['staff']);

        assert_same(false, dbUpdate('UPDATE `tblShortURLs` SET `isActive` = 1 WHERE `urlUID` = ?', 'i', [$uid]));
        assert_same('staff_disabled', g2ml_stafflink_test_status($world['marker'] . 'a'));
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: a staff-disabled link never sends the visitor to the organisation\'s own fallback address', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $code = $world['marker'] . 'a';
        g2ml_stafflink_test_run('UPDATE `tblOrganisations` SET `orgFallbackURL` = \'https://fallback.example/\' WHERE `orgHandle` = ?', 's', [$world['org']]);
        g2ml_stafflink_test_run(
            'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`) VALUES (?, ?, \'https://example.com/org\')',
            'ss',
            [$world['org'], $code]
        );
        $uid    = (int) g2ml_staffFindLinks($code, $world['domain'])['links'][0]['urlUID'];
        $lookup = static function () use ($world, $code): array
        {
            return dbCallProcedure('sp_lookupShortURL', [$world['domain'], $code], 'ss', ['@slDest', '@slStatus', '@slOrg', '@slS', '@slM', '@slC', '@slT', '@slN']);
        };

        // Switched off by its owner: the fallback is used, as before.
        g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `isActive` = 0 WHERE `urlUID` = ?', 'i', [$uid]);
        assert_same('https://fallback.example/', $lookup()['@slDest']);

        g2ml_staffSetLinkState($uid, 'off', $world['staff']);
        assert_same('staff_disabled', $lookup()['@slStatus']);
        assert_same(null, $lookup()['@slDest']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: a g2my.link link of an organisation with its own fallback sends nobody there while blocked', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        // Without its own short domain, the organisation's link lives in the
        // shared g2my.link codes (#289).
        g2ml_stafflink_test_run('DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = ?', 's', [$world['org']]);
        g2ml_stafflink_test_run('UPDATE `tblOrganisations` SET `orgFallbackURL` = \'https://fallback.example/\' WHERE `orgHandle` = ?', 's', [$world['org']]);
        $uid  = g2ml_stafflink_test_link($world, 'a', $world['org']);
        $code = $world['marker'] . 'a';
        assert_same($code, g2ml_stafflink_test_row($uid)['sharedShortCode']);
        $lookup = static function () use ($code): array
        {
            return dbCallProcedure('sp_lookupShortURL', ['g2my.link', $code], 'ss', ['@slDest', '@slStatus', '@slOrg', '@slS', '@slM', '@slC', '@slT', '@slN']);
        };

        g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `isActive` = 0 WHERE `urlUID` = ?', 'i', [$uid]);
        assert_same('https://fallback.example/', $lookup()['@slDest']);

        g2ml_staffSetLinkState($uid, 'off', $world['staff']);
        $blocked = $lookup();
        assert_same('staff_disabled', $blocked['@slStatus']);
        assert_same(null, $blocked['@slDest']);
        assert_same($world['org'], $blocked['@slOrg']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the API cannot switch a blocked link on, and its delete keeps it off after the block is removed', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid     = g2ml_stafflink_test_link($world, 'a');
        $code    = $world['marker'] . 'a';
        $keyRow  = ['orgHandle' => '[default]', 'userUID' => $world['owner'], 'apiKeyUID' => 0];
        $context = static function (array $body) use ($code): array
        {
            return ['requestId' => 'test-request', 'routeParams' => ['code' => $code], 'body' => $body];
        };
        g2ml_staffSetLinkState($uid, 'off', $world['staff']);

        $updated = g2ml_apiHandleUrlsUpdate($keyRow, $context(['title' => 'Changed', 'is_active' => true]));
        assert_false($updated['is_active']);
        $row = g2ml_stafflink_test_row($uid);
        assert_same('Changed', $row['title']);
        assert_same(0, (int) $row['isActive']);
        assert_same(1, (int) $row['staffPreviousIsActive']);

        g2ml_apiHandleUrlsDelete($keyRow, $context([]));
        assert_false(g2ml_staffSetLinkState($uid, 'unblock', $world['staff'])['restoredActive']);
        assert_same(0, (int) g2ml_stafflink_test_row($uid)['isActive']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: switching a blocked link off in My Links keeps it off after the block is removed', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid  = g2ml_stafflink_test_link($world, 'a');
        $code = $world['marker'] . 'a';
        g2ml_staffSetLinkState($uid, 'off', $world['staff']);

        g2ml_stafflink_test_page('links/index.php', $world['owner'], [], ['action' => 'delete', 'url_uid' => (string) $uid, 'short_code' => $code], 'delete_link_' . $uid);

        assert_false(g2ml_staffSetLinkState($uid, 'unblock', $world['staff'])['restoredActive']);
        assert_same(0, (int) g2ml_stafflink_test_row($uid)['isActive']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: account erasure while a link is blocked still applies when the block is removed', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $offUID     = g2ml_stafflink_test_link($world, 'a');
        $periodUID  = g2ml_stafflink_test_link($world, 'b');
        $foreverUID = g2ml_stafflink_test_link($world, 'c');

        // One erasure per choice, each on one blocked link of the same owner.
        foreach ([[$offUID, 'off'], [$periodUID, '30_days'], [$foreverUID, 'forever']] as $case)
        {
            g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `createdByUserUID` = NULL WHERE `shortCode` LIKE ?', 's', [$world['marker'] . '%']);
            g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `createdByUserUID` = ? WHERE `urlUID` = ?', 'ii', [$world['owner'], $case[0]]);
            g2ml_staffSetLinkState($case[0], 'off', $world['staff']);

            mysqli_begin_transaction(getDB());
            g2ml_applyLinkChoiceOnErasure(getDB(), $world['owner'], $case[1]);
            mysqli_commit(getDB());
        }

        assert_false(g2ml_staffSetLinkState($offUID, 'unblock', $world['staff'])['restoredActive']);
        assert_same('inactive', g2ml_stafflink_test_status($world['marker'] . 'a'));

        assert_true(g2ml_staffSetLinkState($periodUID, 'unblock', $world['staff'])['restoredActive']);
        assert_true(g2ml_stafflink_test_row($periodUID)['endDate'] !== null, 'The 30-day end date applies');

        assert_true(g2ml_staffSetLinkState($foreverUID, 'unblock', $world['staff'])['restoredActive']);
        assert_same(null, g2ml_stafflink_test_row($foreverUID)['endDate']);
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

test('#325: the information page shows no destination for a blocked link, signed in or not (D-027)', function (): void
{
    $world = g2ml_stafflink_test_world();

    try
    {
        $uid  = g2ml_stafflink_test_link($world, 'a');
        $code = $world['marker'] . 'a';
        g2ml_stafflink_test_run('UPDATE `tblShortURLs` SET `destinationURL` = \'https://harmful-site.example/path\', `title` = \'See harmful-site.example\' WHERE `urlUID` = ?', 'i', [$uid]);

        $before = g2ml_stafflink_test_page('info/index.php', $world['owner'], ['code' => $code]);
        assert_contains('https://harmful-site.example/path', $before);

        g2ml_staffSetLinkState($uid, 'off', $world['staff'], 'private reason');

        foreach ([0, $world['owner']] as $viewer)
        {
            $html = g2ml_stafflink_test_page('info/index.php', $viewer, ['code' => $code]);
            assert_contains('staff have switched this link off, so its destination is not shown', $html, substr($html, 0, 600));
            assert_contains('Switched off by staff', $html);
            assert_false(str_contains($html, 'harmful-site'), 'No domain, address or title');
            assert_false(str_contains($html, 'private reason'), 'No reason');
            assert_false(str_contains($html, 'Log in to see the full destination'), 'Signing in would not show it either');
        }
    }
    finally
    {
        g2ml_stafflink_test_cleanup($world);
    }
});

/**
 * Run a migration file, honouring its DELIMITER lines.
 */
function g2ml_stafflink_test_migrate(mysqli $db, string $path): void
{
    $delimiter = ';';
    $statement = '';

    foreach (explode("\n", (string) file_get_contents($path)) as $line)
    {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--'))
        {
            continue;
        }

        if (str_starts_with($trimmed, 'DELIMITER '))
        {
            $delimiter = trim(substr($trimmed, 10));
            continue;
        }

        $statement .= $line . "\n";

        if (str_ends_with($trimmed, $delimiter))
        {
            $sql       = substr(rtrim($statement), 0, -strlen($delimiter));
            $statement = '';

            if (mysqli_query($db, $sql) === false)
            {
                throw new RuntimeException('Migration failed: ' . mysqli_error($db));
            }
        }
    }
}

test('#325: migration 046 adds the columns and the check to an older table, and can be run again', function () use ($db): void
{
    $path  = dirname(__DIR__, 2) . '/web/_sql/migrations/046_staff_link_switch.sql';
    $count = static function (): array
    {
        $columns = dbSelectOne("SELECT COUNT(*) AS `cnt` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tblShortURLs' AND `COLUMN_NAME` IN ('staffDisabledAt', 'staffDisabledByUserUID', 'staffDisableReason', 'staffPreviousIsActive')");
        $checks  = dbSelectOne("SELECT COUNT(*) AS `cnt` FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tblShortURLs' AND `CONSTRAINT_NAME` = 'CK_url_staff_disabled_off'");

        return [(int) $columns['cnt'], (int) $checks['cnt']];
    };

    assert_true(is_file($path), 'Migration 046 must exist');
    assert_same([4, 1], $count(), 'A fresh install has the columns and the check');

    // Make the table look as it did before #325.
    // DROP CONSTRAINT works on both engines; MariaDB, which the live host
    // runs, does not know DROP CHECK (#330).
    g2ml_stafflink_test_run('ALTER TABLE `tblShortURLs` DROP CONSTRAINT `CK_url_staff_disabled_off`');
    g2ml_stafflink_test_run('ALTER TABLE `tblShortURLs` DROP COLUMN `staffDisabledAt`, DROP COLUMN `staffDisabledByUserUID`, DROP COLUMN `staffDisableReason`, DROP COLUMN `staffPreviousIsActive`');
    assert_same([0, 0], $count());

    g2ml_stafflink_test_migrate($db, $path);
    assert_same([4, 1], $count());
    g2ml_stafflink_test_migrate($db, $path);
    assert_same([4, 1], $count());
});
