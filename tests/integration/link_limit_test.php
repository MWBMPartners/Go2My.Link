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
 * 🧪 Integration — the limit on switched-on links (#327, D-024)
 * ============================================================================
 *
 * Against a real database: people in no organisation are held to the Free
 * plan's number, switching a link back on is refused at the limit, two requests
 * at the same moment cannot both take the last place, and what is deliberately
 * not limited stays unlimited.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #327
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

foreach (['db_connect', 'db_query', 'security', 'settings', 'activity_logger', 'entitlements', 'pricing', 'i18n', 'api_response', 'api_auth'] as $g2mlLimitFile)
{
    require_once $g2mlFunctionsDir . $g2mlLimitFile . '.php';
}

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/urls.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/urls_bulk.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/staff_links.php';

function g2ml_limit_test_run(string $sql, string $types = '', array $params = []): void
{
    if (dbUpdate($sql, $types, $params) === false)
    {
        throw new RuntimeException('Query failed: ' . $sql);
    }
}

/**
 * Forget who is signed in and every cached plan answer.
 */
function g2ml_limit_test_reset(): void
{
    unset($GLOBALS['g2ml_entitlements_tier_lookup_override'], $GLOBALS['g2ml_entitlements_fallback_lookup_override'], $GLOBALS['g2ml_pricing_setting_override']);
    unset($_SESSION['session_token'], $_SESSION['user_uid'], $_SESSION['user_role'], $_SESSION['user_org_handle']);
    g2ml_clearPricingCache();
}

/**
 * Two ordinary people and one organisation on the Free plan, all new.
 *
 * @return array{marker: string, person: int, other: int, org: string}
 */
function g2ml_limit_test_world(): array
{
    g2ml_limit_test_reset();

    $marker = 'll' . bin2hex(random_bytes(4));
    $world  = ['marker' => $marker, 'org' => $marker . 'org'];

    g2ml_limit_test_run("INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) VALUES (?, 'Limit Test Org', 'free', 1)", 's', [$world['org']]);

    foreach (['person', 'other'] as $who)
    {
        $uid = dbInsert(
            'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) VALUES (\'[default]\', ?, ?, \'x\', \'Limit\', \'Tester\', 1)',
            'ss',
            [$marker . $who, $marker . $who . '@limit327.test']
        );
        $world[$who] = (int) $uid;
    }

    return $world;
}

function g2ml_limit_test_cleanup(array $world): void
{
    $like = $world['marker'] . '%';
    g2ml_limit_test_reset();
    g2ml_limit_test_run('DELETE FROM `tblShortURLs` WHERE `createdByUserUID` IN (?, ?) OR `orgHandle` = ? OR `shortCode` LIKE ?', 'iiss', [$world['person'], $world['other'], $world['org'], $like]);
    g2ml_limit_test_run('DELETE FROM `tblActivityLog` WHERE `shortCode` LIKE ? OR `orgHandle` = ?', 'ss', [$like, $world['org']]);
    g2ml_limit_test_run('DELETE FROM `tblUsers` WHERE `username` LIKE ?', 's', [$like]);
    g2ml_limit_test_run('DELETE FROM `tblOrganisations` WHERE `orgHandle` = ?', 's', [$world['org']]);
}

/**
 * Put links straight into the table, and give back the last row number.
 */
function g2ml_limit_test_fill(array $world, int $count, ?int $owner, string $orgHandle = '[default]', int $isActive = 1): int
{
    static $number = 0;
    $uid = 0;

    for ($made = 0; $made < $count; $made++)
    {
        $number++;
        $uid = (int) dbInsert(
            'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `createdByUserUID`, `isActive`) VALUES (?, ?, \'https://example.com/filled\', ?, ?)',
            'ssii',
            [$orgHandle, $world['marker'] . 'f' . $number, $owner, $isActive]
        );
        assert_true($uid > 0, 'Setup: could not add a link');
    }

    return $uid;
}

function g2ml_limit_test_active(int $owner): int
{
    return (int) dbSelectOne("SELECT COUNT(*) AS `cnt` FROM `tblShortURLs` WHERE `createdByUserUID` = ? AND `orgHandle` = '[default]' AND `isActive` = 1", 'i', [$owner])['cnt'];
}

function g2ml_limit_test_row(int $urlUID): array
{
    return (array) dbSelectOne('SELECT * FROM `tblShortURLs` WHERE `urlUID` = ?', 'i', [$urlUID]);
}

function g2ml_limit_test_create(?int $owner, string $orgHandle = '[default]', array $more = []): array
{
    $options = ['orgHandle' => $orgHandle, 'userUID' => $owner] + $more;

    return createShortURL('https://example.com/limit327', $options);
}

function g2ml_limit_test_words(string $key, int $limit): string
{
    $row = dbSelectOne("SELECT `translationValue` FROM `tblTranslations` WHERE `localeCode` = 'en-GB' AND `translationKey` = ?", 's', [$key]);
    assert_true(is_array($row), 'The seeds have no ' . $key);

    return str_replace('{limit}', (string) $limit, (string) $row['translationValue']);
}

/**
 * Save the real edit page in a separate PHP process, as a signed-in owner.
 */
function g2ml_limit_test_edit(array $link, int $userUID, bool $active, string $title = 'Edited'): string
{
    $root   = dirname(__DIR__, 2);
    $script = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";

    foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_CHARSET'] as $constant)
    {
        $script .= 'define(' . var_export($constant, true) . ', ' . var_export(constant($constant), true) . ');' . "\n";
    }

    $script .= '$_SESSION = array(\'user_uid\' => ' . $userUID . ');' . "\n";
    $script .= 'function getCurrentUser(): array { return array(\'userUID\' => ' . $userUID . ', \'orgHandle\' => \'[default]\', \'role\' => \'User\'); }' . "\n";
    $script .= 'function hasMinimumRole(string $role, string $needed): bool { return false; }' . "\n";

    foreach (['_functions/db_connect.php', '_functions/db_query.php', '_functions/security.php', '_functions/settings.php', '_functions/activity_logger.php', '_functions/entitlements.php', '_includes/accessibility.php', 'Go2My.Link/_functions/shorturl_create.php'] as $file)
    {
        $script .= 'require_once ' . var_export($root . '/web/' . $file, true) . ';' . "\n";
    }

    $post = ['destination_url' => 'https://example.com/edited', 'title' => $title, 'active_switch_shown' => '1'];

    if ($active)
    {
        $post['is_active'] = '1';
    }

    $script .= '$_SERVER[\'REQUEST_METHOD\'] = \'POST\';' . "\n";
    $script .= '$_GET = ' . var_export(['id' => $link['shortCode']], true) . '; $_POST = ' . var_export($post, true) . ';' . "\n";
    $script .= '$_POST[\'_csrf_token\'] = g2ml_generateCSRFToken(\'edit_link_form\');' . "\n";
    $script .= 'require ' . var_export($root . '/web/Go2My.Link/_admin/public_html/pages/links/edit/index.php', true) . ';' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_limit_');
    file_put_contents($tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1');
    unlink($tempFile);

    return (string) $output;
}

/**
 * Run several worker processes that all act at the same moment.
 *
 * @param  array<int, array<int, string>> $jobs  [action, then its arguments]
 * @return array<string, int>                    outcome => how many
 */
function g2ml_limit_test_race(array $jobs): array
{
    $startAt   = number_format(microtime(true) + 1.5, 4, '.', '');
    $processes = [];

    foreach ($jobs as $job)
    {
        $command = array_merge(
            [PHP_BINARY, dirname(__DIR__) . '/fixtures/link_limit_worker.php', $job[0], $startAt],
            array_slice($job, 1)
        );
        $pipes   = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        assert_true(is_resource($process), 'Could not start a worker');
        $processes[] = [$process, $pipes[1]];
    }

    $outcomes = [];

    foreach ($processes as [$process, $pipe])
    {
        $outcome = trim((string) stream_get_contents($pipe));
        fclose($pipe);
        proc_close($process);

        if (!isset($outcomes[$outcome]))
        {
            $outcomes[$outcome] = 0;
        }

        $outcomes[$outcome]++;
    }

    ksort($outcomes);

    return $outcomes;
}

test('#327: a person in no organisation gets a 50th link and is refused the 51st, in words from seed 084', function (): void
{
    $world = g2ml_limit_test_world();
    loadSettingsCache();

    try
    {
        g2ml_limit_test_fill($world, 49, $world['person']);
        // A past end date still counts while the link is switched on.
        g2ml_limit_test_run("UPDATE `tblShortURLs` SET `endDate` = '2020-01-01 00:00:00' WHERE `createdByUserUID` = ? LIMIT 1", 'i', [$world['person']]);
        // Switched-off links do not count.
        g2ml_limit_test_fill($world, 3, $world['person'], '[default]', 0);

        $fiftieth   = g2ml_limit_test_create($world['person']);
        $fiftyFirst = g2ml_limit_test_create($world['person']);
        $asOff      = g2ml_limit_test_create($world['person'], '[default]', ['isActive' => 0]);

        assert_true($fiftieth['success'], (string) $fiftieth['error']);
        assert_false($fiftyFirst['success'], 'The 51st link is refused');
        assert_true($fiftyFirst['limitReached'] ?? false);
        assert_same(g2ml_limit_test_words('links.error_personal_limit', 50), $fiftyFirst['error']);
        assert_false($asOff['success'], 'A new link is refused at the limit even when created switched off');
        assert_same(50, g2ml_limit_test_active($world['person']));

        // Counted per person, not for everybody in the default organisation.
        assert_true(g2ml_limit_test_create($world['other'])['success'], 'Another person is not affected');

        // Switching one off makes room.
        g2ml_limit_test_run('UPDATE `tblShortURLs` SET `isActive` = 0 WHERE `createdByUserUID` = ? AND `isActive` = 1 LIMIT 1', 'i', [$world['person']]);
        assert_true(g2ml_limit_test_create($world['person'])['success']);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: no owner, a global administrator and an organisation are counted as before', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        g2ml_limit_test_fill($world, 50, $world['person']);

        // Home page without an account: unchanged.
        $anonymous = g2ml_limit_test_create(null);
        assert_true($anonymous['success'], (string) $anonymous['error']);
        g2ml_limit_test_run('DELETE FROM `tblShortURLs` WHERE `shortCode` = ?', 's', [$anonymous['shortCode']]);

        // A person's own links do not count against their organisation, and
        // the organisation's links do not count against the person.
        assert_true(g2ml_limit_test_create($world['person'], $world['org'])['success']);
        g2ml_limit_test_fill($world, 50, $world['other'], $world['org']);
        $refused = g2ml_limit_test_create($world['other'], $world['org']);
        assert_false($refused['success']);
        assert_same(g2ml_limit_test_words('links.error_plan_limit', 50), $refused['error']);
        assert_true(g2ml_limit_test_create($world['other'])['success']);

        // A global administrator has no limit, with no session (as in the API).
        g2ml_limit_test_run("UPDATE `tblUsers` SET `role` = 'GlobalAdmin' WHERE `userUID` = ?", 'i', [$world['person']]);
        assert_true(g2ml_limit_test_create($world['person'])['success']);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: a global administrator\'s API create in an organisation at its limit is not refused', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        g2ml_limit_test_fill($world, 50, $world['other'], $world['org']);
        $key = dbInsert(
            'INSERT INTO `tblAPIKeys` (`orgHandle`, `userUID`, `apiKey`, `apiKeyPrefix`, `keyName`) VALUES (?, ?, ?, \'limit327g\', \'limit test\')',
            'sis',
            [$world['org'], $world['person'], hash('sha256', $world['marker'] . 'g')]
        );
        assert_true($key !== false, 'Setup: could not create an API key');
        $keyRow  = ['orgHandle' => $world['org'], 'userUID' => $world['person'], 'apiKeyUID' => (int) $key];
        $request = ['body' => ['destination_url' => 'https://example.com/staff-api']];
        $status  = 0;

        // An ordinary member's key is refused.
        try
        {
            g2ml_apiHandleUrlsCreate($keyRow, $request);
        }
        catch (G2mlApiHandlerException $refusal)
        {
            $status = $refusal->getHttpStatusCode();
        }

        assert_same(403, $status);

        // The same key, once its owner is a global administrator, is not.
        g2ml_limit_test_run("UPDATE `tblUsers` SET `role` = 'GlobalAdmin' WHERE `userUID` = ?", 'i', [$world['person']]);
        $created = g2ml_apiHandleUrlsCreate($keyRow, $request);
        assert_true(($created['short_code'] ?? '') !== '');
    }
    finally
    {
        g2ml_limit_test_run('DELETE FROM `tblAPIKeys` WHERE `userUID` = ?', 'i', [$world['person']]);
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: the number comes from the plan data, with the pricing engine off or on', function () use ($db): void
{
    $world    = g2ml_limit_test_world();
    $setting  = "SELECT `settingValue` AS `v` FROM `tblSettings` WHERE `settingID` = 'billing.pricing_engine_enabled' AND `settingScope` = 'System'";
    $feature  = "SELECT `tf`.`tierFeatureUID` AS `id`, `tf`.`valueInt` AS `v` FROM `tblTierFeatures` AS `tf` INNER JOIN `tblFeatures` AS `f` ON `f`.`featureUID` = `tf`.`featureUID` WHERE `tf`.`tierID` = 'free' AND `f`.`legacyColumn` = 'maxLinks'";
    $wasOn    = (string) dbSelectOne($setting)['v'];
    $planRow  = dbSelectOne($feature);

    try
    {
        assert_true(is_array($planRow), 'Setup: the Free plan has a registry row for maxLinks');
        g2ml_limit_test_run("UPDATE `tblSubscriptionTiers` SET `maxLinks` = 2 WHERE `tierID` = 'free'");
        g2ml_limit_test_run('UPDATE `tblTierFeatures` SET `valueInt` = 3 WHERE `tierFeatureUID` = ?', 'i', [(int) $planRow['id']]);

        foreach (['0' => 2, '1' => 3] as $switch => $limit)
        {
            g2ml_limit_test_run('DELETE FROM `tblShortURLs` WHERE `createdByUserUID` = ?', 'i', [$world['person']]);
            g2ml_limit_test_run("UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = 'billing.pricing_engine_enabled' AND `settingScope` = 'System'", 's', [(string) $switch]);
            loadSettingsCache();
            g2ml_limit_test_reset();

            g2ml_limit_test_fill($world, $limit - 1, $world['person']);
            assert_true(g2ml_limit_test_create($world['person'])['success'], 'Engine ' . $switch . ': link ' . $limit . ' is accepted');
            $refused = g2ml_limit_test_create($world['person']);
            assert_false($refused['success'], 'Engine ' . $switch . ': one more is refused');
            assert_same(g2ml_limit_test_words('links.error_personal_limit', $limit), $refused['error']);
        }
    }
    finally
    {
        g2ml_limit_test_run("UPDATE `tblSubscriptionTiers` SET `maxLinks` = 50 WHERE `tierID` = 'free'");

        if (is_array($planRow))
        {
            g2ml_limit_test_run('UPDATE `tblTierFeatures` SET `valueInt` = ? WHERE `tierFeatureUID` = ?', 'ii', [(int) $planRow['v'], (int) $planRow['id']]);
        }

        g2ml_limit_test_run("UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = 'billing.pricing_engine_enabled' AND `settingScope` = 'System'", 's', [$wasOn]);
        loadSettingsCache();
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: the API refuses at the limit: one create gives 403, a bulk create fails row by row', function (): void
{
    $world  = g2ml_limit_test_world();
    $keyRow = ['orgHandle' => '[default]', 'userUID' => $world['person'], 'apiKeyUID' => null];

    try
    {
        g2ml_limit_test_fill($world, 49, $world['person']);
        $key = dbInsert(
            "INSERT INTO `tblAPIKeys` (`orgHandle`, `userUID`, `apiKey`, `apiKeyPrefix`, `keyName`) VALUES ('[default]', ?, ?, 'limit327', 'limit test')",
            'is',
            [$world['person'], hash('sha256', $world['marker'])]
        );
        assert_true($key !== false, 'Setup: could not create an API key');
        $keyRow['apiKeyUID'] = (int) $key;

        $bulk = g2ml_apiHandleUrlsBulkCreate($keyRow, ['body' => ['items' => [
            ['destination_url' => 'https://example.com/bulk-1'],
            ['destination_url' => 'https://example.com/bulk-2'],
        ]]]);
        assert_true($bulk['results'][0]['success']);
        assert_false($bulk['results'][1]['success']);
        assert_same(g2ml_limit_test_words('links.error_personal_limit', 50), $bulk['results'][1]['error']);

        $status = 0;

        try
        {
            g2ml_apiHandleUrlsCreate($keyRow, ['body' => ['destination_url' => 'https://example.com/single']]);
        }
        catch (G2mlApiHandlerException $refusal)
        {
            $status = $refusal->getHttpStatusCode();
        }

        assert_same(403, $status);
        assert_same(50, g2ml_limit_test_active($world['person']));
    }
    finally
    {
        g2ml_limit_test_run('DELETE FROM `tblAPIKeys` WHERE `userUID` = ?', 'i', [$world['person']]);
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: the edit page refuses to switch a link on at the limit, and saves the other changes', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        $offUID = g2ml_limit_test_fill($world, 1, $world['person'], '[default]', 0);
        $onUID  = g2ml_limit_test_fill($world, 50, $world['person']);

        $html = g2ml_limit_test_edit(g2ml_limit_test_row($offUID), $world['person'], true);
        $row  = g2ml_limit_test_row($offUID);
        assert_same(0, (int) $row['isActive'], 'The link stays off');
        assert_same('Edited', $row['title'], 'The other changes are saved: ' . substr($html, 0, 600));
        assert_contains('the link was not switched on', $html);
        assert_false(str_contains($html, 'Link updated successfully'));
        assert_true(preg_match('/id="is-active"[^>]*\schecked/s', $html) !== 1, 'The switch is not shown as on');

        // Saving a link that is already on, at and above the limit, keeps it on.
        g2ml_limit_test_fill($world, 2, $world['person']);
        $html = g2ml_limit_test_edit(g2ml_limit_test_row($onUID), $world['person'], true, 'Still on');
        $row  = g2ml_limit_test_row($onUID);
        assert_same(1, (int) $row['isActive']);
        assert_same('Still on', $row['title']);
        assert_contains('Link updated successfully', $html);

        // Switching off is always allowed.
        g2ml_limit_test_edit($row, $world['person'], false);
        assert_same(0, (int) g2ml_limit_test_row($onUID)['isActive']);

        // With one free place it can be switched on.
        g2ml_limit_test_run('UPDATE `tblShortURLs` SET `isActive` = 0 WHERE `createdByUserUID` = ? AND `isActive` = 1 LIMIT 2', 'i', [$world['person']]);
        assert_same(49, g2ml_limit_test_active($world['person']));
        $html = g2ml_limit_test_edit(g2ml_limit_test_row($offUID), $world['person'], true);
        assert_same(1, (int) g2ml_limit_test_row($offUID)['isActive'], substr($html, 0, 600));
        assert_contains('Link updated successfully', $html);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: an organisation at its limit cannot switch a link back on', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        $offUID = g2ml_limit_test_fill($world, 1, $world['person'], $world['org'], 0);
        g2ml_limit_test_fill($world, 50, $world['other'], $world['org']);

        $html = g2ml_limit_test_edit(g2ml_limit_test_row($offUID), $world['person'], true);
        assert_same(0, (int) g2ml_limit_test_row($offUID)['isActive']);
        assert_contains('organisation has reached', $html);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: several creates at the same moment cannot pass the limit', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        g2ml_limit_test_fill($world, 49, $world['person']);
        $outcomes = g2ml_limit_test_race(array_fill(0, 8, ['create', '[default]', (string) $world['person']]));
        assert_same(['limit' => 7, 'ok' => 1], $outcomes);
        assert_same(50, g2ml_limit_test_active($world['person']));

        g2ml_limit_test_fill($world, 49, $world['other'], $world['org']);
        $outcomes = g2ml_limit_test_race(array_fill(0, 8, ['create', $world['org'], (string) $world['other']]));
        assert_same(['limit' => 7, 'ok' => 1], $outcomes);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: several links switched on at the same moment cannot pass the limit', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        $jobs = [];

        for ($number = 0; $number < 8; $number++)
        {
            $jobs[] = ['switch_on', (string) g2ml_limit_test_fill($world, 1, $world['person'], '[default]', 0)];
        }

        g2ml_limit_test_fill($world, 49, $world['person']);
        assert_same(['limit' => 7, 'ok' => 1], g2ml_limit_test_race($jobs));
        assert_same(50, g2ml_limit_test_active($world['person']));
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: while another request holds the lock, a create and a switch-on say "try again"', function (): void
{
    $world  = g2ml_limit_test_world();
    $holder = null;

    try
    {
        $offUID = g2ml_limit_test_fill($world, 1, $world['person'], '[default]', 0);
        $holder = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $scope  = 'user|' . $world['person'];
        $stmt   = $holder->prepare('SELECT GET_LOCK(' . G2ML_LINK_LOCK_NAME_SQL . ', 0) AS `gotLock`');
        $stmt->bind_param('s', $scope);
        $stmt->execute();
        assert_same(1, (int) $stmt->get_result()->fetch_assoc()['gotLock']);
        $stmt->close();

        $refused = g2ml_limit_test_create($world['person']);
        assert_false($refused['success']);
        assert_true($refused['limitBusy'] ?? false);
        assert_same(g2ml_limit_test_words('links.error_limit_busy', 50), $refused['error']);

        $gate = g2ml_linkLimitOpenForSwitchOn($offUID);
        assert_false($gate['switchOn']);
        assert_same('busy', $gate['reason']);

        mysqli_close($holder);
        $holder = null;
        assert_true(g2ml_limit_test_create($world['person'])['success']);
        // The lock was let go: another connection can take it at once.
        $free = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $stmt = $free->prepare('SELECT IS_FREE_LOCK(' . G2ML_LINK_LOCK_NAME_SQL . ') AS `isFree`');
        $stmt->bind_param('s', $scope);
        $stmt->execute();
        assert_same(1, (int) $stmt->get_result()->fetch_assoc()['isFree']);
        $stmt->close();
        mysqli_close($free);
    }
    finally
    {
        if ($holder !== null)
        {
            mysqli_close($holder);
        }

        g2ml_limit_test_cleanup($world);
    }
});

test('#327: when the plan cannot be read, people and organisations are treated alike: allowed, and logged', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        g2ml_limit_test_fill($world, 50, $world['person']);
        g2ml_limit_test_fill($world, 50, $world['other'], $world['org']);
        $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): bool
        {
            return false;
        };
        g2ml_clearOrgTierCache();

        assert_true(g2ml_limit_test_create($world['person'])['success']);
        assert_true(g2ml_limit_test_create($world['other'], $world['org'])['success']);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});

test('#327: removing a staff block is never refused, and can leave an owner above the limit (D-036)', function (): void
{
    $world = g2ml_limit_test_world();

    try
    {
        $uid = g2ml_limit_test_fill($world, 50, $world['person']);
        assert_true(g2ml_staffSetLinkState($uid, 'off', $world['other'])['success']);
        assert_true(g2ml_limit_test_create($world['person'])['success'], 'A blocked link does not count');

        $result = g2ml_staffSetLinkState($uid, 'unblock', $world['other']);
        assert_true($result['success'], $result['errorCode']);
        assert_true($result['restoredActive']);
        assert_same(51, g2ml_limit_test_active($world['person']));
        assert_false(g2ml_limit_test_create($world['person'])['success']);
    }
    finally
    {
        g2ml_limit_test_cleanup($world);
    }
});
