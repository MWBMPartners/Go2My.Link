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
 * 🧪 Integration tests — API access depends on the plan (#328, D-025)
 * ============================================================================
 *
 * Covers making a key and calling the API, against the seeded plans. The calls
 * are real HTTP requests to a temporary copy of the site, served by PHP's
 * built-in web server. The copy and its credentials are made with the helpers
 * in debug_access_test.php, but this file has its own copy and server: that
 * file rewrites its copy during its tests.
 *
 * The test picks plans by their hasAPIAccess value, never by name.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #328
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_connect.php';
require_once dirname(__DIR__, 2) . '/web/_functions/db_query.php';
require_once dirname(__DIR__, 2) . '/web/_functions/settings.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_auth.php';
require_once dirname(__DIR__, 2) . '/web/_functions/pricing.php';
require_once dirname(__DIR__, 2) . '/web/_functions/entitlements.php';
require_once dirname(__DIR__, 2) . '/web/_functions/activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/_functions/session.php';
require_once dirname(__DIR__, 2) . '/web/_functions/auth.php';
require_once dirname(__DIR__, 2) . '/web/_functions/account_types.php';
require_once dirname(__DIR__, 2) . '/web/_functions/org.php';

/**
 * The tierID of the first seeded active plan with the given hasAPIAccess value.
 */
function g2mlApiPlanTest_plan(int $hasAPIAccess): string
{
    $row = dbSelectOne(
        'SELECT `tierID` FROM `tblSubscriptionTiers` WHERE `isActive` = 1 AND `hasAPIAccess` = ? ORDER BY `sortOrder` ASC LIMIT 1',
        'i',
        [$hasAPIAccess]
    );

    if (!is_array($row))
    {
        throw new RuntimeException('No seeded plan with hasAPIAccess = ' . $hasAPIAccess);
    }

    return (string) $row['tierID'];
}

/**
 * Make an organisation on a plan (or move it to that plan) and forget cached plans.
 */
function g2mlApiPlanTest_org(string $orgHandle, string $tierID): void
{
    $done = dbUpdate(
        'INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) VALUES (?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE `tierID` = VALUES(`tierID`)',
        'sss',
        [$orgHandle, 'API plan test ' . $orgHandle, $tierID]
    );

    if ($done === false)
    {
        throw new RuntimeException('Could not make organisation ' . $orgHandle);
    }

    g2ml_clearOrgTierCache();
}

/**
 * Make a user with a role in an organisation.
 */
function g2mlApiPlanTest_user(string $orgHandle, string $role): int
{
    $marker = 'plan328' . bin2hex(random_bytes(6));

    $userUID = dbInsert(
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `role`, `isActive`, `isSuspended`) '
        . "VALUES (?, ?, ?, 'x', ?, 1, 0)",
        'ssss',
        [$orgHandle, $marker, $marker . '@plan328.test', $role]
    );

    if ($userUID === false)
    {
        throw new RuntimeException('Could not make a user');
    }

    return (int) $userUID;
}

/**
 * How many keys a user owns.
 */
function g2mlApiPlanTest_keyCount(int $userUID): int
{
    $row = dbSelectOne('SELECT COUNT(*) AS `total` FROM `tblAPIKeys` WHERE `userUID` = ?', 'i', [$userUID]);

    return (int) $row['total'];
}

/**
 * Copy the site once per test run and start a web server on it. Both are
 * removed when the test run ends.
 *
 * @return int  The port the server listens on.
 */
function g2mlApiPlanTest_port(): int
{
    static $port = null;

    if ($port !== null)
    {
        return $port;
    }

    $base = sys_get_temp_dir() . '/g2ml_plan328_' . bin2hex(random_bytes(6));
    $web  = $base . '/web';

    g2mlDebugTest_copy(dirname(__DIR__, 2) . '/web', $web);

    $databaseHost = getenv('G2ML_TEST_DB_HOST');

    if ($databaseHost === false || $databaseHost === '')
    {
        $databaseHost = '127.0.0.1';
    }

    g2mlDebugTest_credentials($web, $databaseHost);

    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port  = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);

    $process = proc_open(
        array(PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $web . '/Go2My.Link/public_html'),
        array(
            0 => array('pipe', 'r'),
            1 => array('file', $base . '/server.out', 'w'),
            2 => array('file', $base . '/server.err', 'w'),
        ),
        $pipes
    );

    $tries = 0;

    while ($tries < 100)
    {
        $connection = @fsockopen('127.0.0.1', $port);

        if ($connection !== false)
        {
            fclose($connection);
            break;
        }

        usleep(50000);
        $tries++;
    }

    register_shutdown_function(function () use ($process, $base): void
    {
        proc_terminate($process);
        proc_close($process);
        g2mlDebugTest_remove($base);
    });

    return $port;
}

/**
 * Call the real API front controller over HTTP.
 *
 * @return array{status: int, headers: string, body: array<string, mixed>}
 */
function g2mlApiPlanTest_call(string $key, string $route, string $method = 'GET', string $body = ''): array
{
    $port = g2mlApiPlanTest_port();

    $context = stream_context_create(array('http' => array(
        'method'        => $method,
        'header'        => "Host: localhost\r\nX-API-Key: " . $key . "\r\nContent-Type: application/json\r\n",
        'content'       => $body,
        'ignore_errors' => true,
        'timeout'       => 20,
    )));

    $stream = fopen('http://127.0.0.1:' . $port . '/api/v1/index.php?_apiroute=' . $route, 'r', false, $context);

    if ($stream === false)
    {
        throw new RuntimeException('No answer from the API');
    }

    $meta = stream_get_meta_data($stream);
    $text = (string) stream_get_contents($stream);
    fclose($stream);

    preg_match('/ (\d{3})/', (string) $meta['wrapper_data'][0], $match);

    $decoded = json_decode($text, true);

    if (!is_array($decoded))
    {
        throw new RuntimeException('Not a JSON answer: ' . $text);
    }

    return array(
        'status'  => (int) $match[1],
        'headers' => implode("\n", $meta['wrapper_data']),
        'body'    => $decoded,
    );
}

$g2mlApiPlanFree = g2mlApiPlanTest_plan(0);
$g2mlApiPlanPaid = g2mlApiPlanTest_plan(1);

test('API plan (#328): a key cannot be made on a plan without API access, with a message that offers no upgrade', function () use ($g2mlApiPlanFree): void
{
    g2mlApiPlanTest_org('plan328free', $g2mlApiPlanFree);
    $userUID = g2mlApiPlanTest_user('plan328free', 'Admin');

    $result = g2ml_apiGenerateKey($userUID, 'plan328free', 'No', ['urls:read'], null);

    assert_false($result['success']);
    assert_contains('does not include API access', $result['error']);
    assert_false(stripos($result['error'], 'upgrade') !== false);
    assert_same(0, g2mlApiPlanTest_keyCount($userUID), 'No row may be written');
});

test('API plan (#328): a key can be made on a plan with API access', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328paid', $g2mlApiPlanPaid);
    $userUID = g2mlApiPlanTest_user('plan328paid', 'Admin');

    assert_true(g2ml_apiGenerateKey($userUID, 'plan328paid', 'Yes', ['urls:read'], null)['success']);
});

test('API plan (#328): people in the default organisation count as Free', function (): void
{
    $userUID = g2mlApiPlanTest_user('[default]', 'User');

    assert_same('not_included', g2ml_apiPlanAccess($userUID, '[default]'));
    assert_false(g2ml_apiGenerateKey($userUID, '[default]', 'No', ['urls:read'], null)['success']);
});

test('API plan (#328): a global administrator who owns the key is never limited', function () use ($g2mlApiPlanFree): void
{
    g2mlApiPlanTest_org('plan328free', $g2mlApiPlanFree);
    $userUID = g2mlApiPlanTest_user('plan328free', 'GlobalAdmin');

    assert_same('allowed', g2ml_apiPlanAccess($userUID, 'plan328free'));
    assert_same('allowed', g2ml_apiPlanAccess($userUID, '[default]'));
});

test('API plan (#328): a signed-in global administrator does not lend access to another person\'s key', function () use ($g2mlApiPlanFree): void
{
    g2mlApiPlanTest_org('plan328free', $g2mlApiPlanFree);
    $userUID = g2mlApiPlanTest_user('plan328free', 'Admin');
    $saved   = $_SESSION;

    $_SESSION['user_uid']  = 1;
    $_SESSION['user_role'] = 'GlobalAdmin';
    $answer                = g2ml_apiPlanAccess($userUID, 'plan328free');
    $_SESSION              = $saved;
    g2ml_clearOrgTierCache();

    assert_same('not_included', $answer);
});

test('API plan (#328): a switched-off plan counts as the first active plan', function () use ($g2mlApiPlanPaid): void
{
    dbUpdate(
        'INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `hasAPIAccess`, `sortOrder`, `isActive`) '
        . "VALUES ('plan328off', 'Off', 1, 998, 0) ON DUPLICATE KEY UPDATE `isActive` = 0",
        '',
        []
    );
    g2mlApiPlanTest_org('plan328offorg', 'plan328off');
    $userUID = g2mlApiPlanTest_user('plan328offorg', 'Admin');

    assert_same('not_included', g2ml_apiPlanAccess($userUID, 'plan328offorg'));

    dbUpdate("UPDATE `tblOrganisations` SET `tierID` = ? WHERE `orgHandle` = 'plan328offorg'", 's', [$g2mlApiPlanPaid]);
});

test('API plan (#328): every failed lookup refuses', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328paid', $g2mlApiPlanPaid);
    $userUID = g2mlApiPlanTest_user('plan328paid', 'Admin');

    assert_same('check_failed', g2ml_apiPlanAccess(999999999, 'plan328paid'), 'Owner not found');

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array|null|false
    {
        return false;
    };
    $first   = g2ml_apiPlanAccess($userUID, 'plan328paid');
    $created = g2ml_apiGenerateKey($userUID, 'plan328paid', 'No', ['urls:read'], null);

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array|null|false
    {
        return null;
    };
    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): array|null|false
    {
        return false;
    };
    $second = g2ml_apiPlanAccess($userUID, 'plan328paid');

    $GLOBALS['g2ml_entitlements_fallback_lookup_override'] = function (): array|null|false
    {
        return null;
    };
    $third = g2ml_apiPlanAccess($userUID, 'plan328paid');

    unset($GLOBALS['g2ml_entitlements_tier_lookup_override'], $GLOBALS['g2ml_entitlements_fallback_lookup_override']);

    assert_same('check_failed', $first);
    assert_false($created['success']);
    assert_contains('could not check', $created['error']);
    assert_same('check_failed', $second);
    assert_same('check_failed', $third);
    assert_same(0, g2mlApiPlanTest_keyCount($userUID));
});

test('API plan (#328): with the pricing engine on, its answer counts, including a grant to one organisation; a failure refuses', function () use ($g2mlApiPlanFree, $g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328free', $g2mlApiPlanFree);
    g2mlApiPlanTest_org('plan328paid', $g2mlApiPlanPaid);
    $freeUser = g2mlApiPlanTest_user('plan328free', 'Admin');
    $paidUser = g2mlApiPlanTest_user('plan328paid', 'Admin');

    dbUpdate(
        'INSERT INTO `tblOrgFeatureOverrides` (`orgHandle`, `featureUID`, `overrideMode`, `valueBoolean`) '
        . "SELECT 'plan328free', `featureUID`, 'grant', 1 FROM `tblFeatures` WHERE `featureSlug` = 'api.access'",
        '',
        []
    );

    $offGranted = g2ml_apiPlanAccess($freeUser, 'plan328free');

    $GLOBALS['g2ml_pricing_engine_enabled_cache'] = true;
    g2ml_clearOrgTierCache();
    $onGranted = g2ml_apiPlanAccess($freeUser, 'plan328free');
    $onPaid    = g2ml_apiPlanAccess($paidUser, 'plan328paid');
    $onDefault = g2ml_apiPlanAccess(g2mlApiPlanTest_user('[default]', 'User'), '[default]');

    $GLOBALS['g2ml_pricing_features_override'] = function (): array|false
    {
        return false;
    };
    $onBroken = g2ml_apiPlanAccess($paidUser, 'plan328paid');

    unset($GLOBALS['g2ml_pricing_features_override'], $GLOBALS['g2ml_pricing_engine_enabled_cache']);
    dbUpdate("DELETE FROM `tblOrgFeatureOverrides` WHERE `orgHandle` = 'plan328free'", '', []);
    g2ml_clearOrgTierCache();

    assert_same('not_included', $offGranted, 'The engine-only grant does nothing while the engine is off');
    assert_same('allowed', $onGranted);
    assert_same('allowed', $onPaid);
    assert_same('not_included', $onDefault);
    assert_same('check_failed', $onBroken);
});

test('API plan (#328): over HTTP, an existing key stops working when the plan has no API access, and works again when it does', function () use ($g2mlApiPlanFree, $g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328http', $g2mlApiPlanPaid);
    $userUID = g2mlApiPlanTest_user('plan328http', 'Admin');
    $created = g2ml_apiGenerateKey($userUID, 'plan328http', 'Existing', ['urls:read', 'urls:write'], null);

    assert_true($created['success']);
    assert_same(200, g2mlApiPlanTest_call($created['plaintextKey'], 'ping')['status']);

    g2mlApiPlanTest_org('plan328http', $g2mlApiPlanFree);

    $ping   = g2mlApiPlanTest_call($created['plaintextKey'], 'ping');
    $create = g2mlApiPlanTest_call($created['plaintextKey'], 'urls', 'POST', '{"destination_url":"https://example.com/plan328"}');
    $wrong  = g2mlApiPlanTest_call($created['plaintextKey'] . 'x', 'ping');

    assert_same(403, $ping['status']);
    assert_contains('does not include API access', $ping['body']['error']['message']);
    assert_false(stripos($ping['body']['error']['message'], 'upgrade') !== false);
    assert_same(403, $create['status']);
    assert_same(401, $wrong['status'], 'A wrong key still learns nothing about the plan');

    $links = dbSelectOne("SELECT COUNT(*) AS `total` FROM `tblShortURLs` WHERE `orgHandle` = 'plan328http'", '', []);
    assert_same(0, (int) $links['total'], 'A refused call creates nothing');

    $logged = dbSelectOne(
        'SELECT COUNT(*) AS `total` FROM `tblAPIRequestLog` WHERE `apiKeyUID` = ? AND `responseCode` = 403',
        'i',
        [$created['apiKeyUID']]
    );
    assert_same(2, (int) $logged['total'], 'Refused calls are in the request log');

    g2mlApiPlanTest_org('plan328http', $g2mlApiPlanPaid);
    assert_same(200, g2mlApiPlanTest_call($created['plaintextKey'], 'ping')['status']);
});

test('API plan (#328): over HTTP, a plan that cannot be read answers 503 with Retry-After', function () use ($g2mlApiPlanPaid, $db): void
{
    g2mlApiPlanTest_org('plan328http', $g2mlApiPlanPaid);
    $userUID = g2mlApiPlanTest_user('plan328http', 'Admin');
    $created = g2ml_apiGenerateKey($userUID, 'plan328http', 'Broken lookup', ['urls:read'], null);

    // Make the pricing engine fail for real: switch it on and hide one of the
    // tables it reads. Both are put back whatever happens.
    $setting = "UPDATE `tblSettings` SET `settingValue` = '%s' WHERE `settingID` = 'billing.pricing_engine_enabled'";

    assert_true($db->query(sprintf($setting, '1')) !== false);

    try
    {
        assert_true($db->query('RENAME TABLE `tblOrgFeatureOverrides` TO `tblOrgFeatureOverrides_plan328`') !== false);
        $answer = g2mlApiPlanTest_call($created['plaintextKey'], 'ping');
    }
    finally
    {
        $db->query('RENAME TABLE `tblOrgFeatureOverrides_plan328` TO `tblOrgFeatureOverrides`');
        $db->query(sprintf($setting, '0'));
    }

    assert_same(503, $answer['status']);
    assert_contains('Retry-After: 60', $answer['headers']);
    assert_contains('could not check', $answer['body']['error']['message']);
});

/**
 * Run something as a signed-in global administrator, then put the session back.
 */
function g2mlApiPlanTest_asGlobalAdmin(int $userUID, string $orgHandle, callable $action): mixed
{
    dbUpdate("UPDATE `tblUsers` SET `role` = 'GlobalAdmin' WHERE `userUID` = ?", 'i', [$userUID]);

    $token = bin2hex(random_bytes(32));
    dbInsert(
        'INSERT INTO `tblUserSessions` (`userUID`, `sessionToken`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), 1)',
        'is',
        [$userUID, hash('sha256', $token)]
    );

    $saved                       = $_SESSION;
    $_SESSION['user_uid']        = $userUID;
    $_SESSION['session_token']   = $token;
    $_SESSION['user_role']       = 'GlobalAdmin';
    $_SESSION['user_org_handle'] = $orgHandle;

    try
    {
        return $action();
    }
    finally
    {
        $_SESSION = $saved;
        g2ml_clearOrgTierCache();
    }
}

/**
 * The ownerBelongs value g2ml_apiListKeys() reports for one key.
 */
function g2mlApiPlanTest_ownerBelongs(string $orgHandle, int $apiKeyUID): int
{
    foreach (g2ml_apiListKeys($orgHandle) as $listed)
    {
        if ((int) $listed['apiKeyUID'] === $apiKeyUID)
        {
            return (int) $listed['ownerBelongs'];
        }
    }

    throw new RuntimeException('Key not listed');
}

test('API keys (#328): a removed member\'s key is refused, and one who rejoins has it back', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328member', $g2mlApiPlanPaid);
    g2mlApiPlanTest_org('plan328other', $g2mlApiPlanPaid);
    $adminUID  = g2mlApiPlanTest_user('plan328member', 'Admin');
    $memberUID = g2mlApiPlanTest_user('plan328member', 'Admin');
    $created   = g2ml_apiGenerateKey($memberUID, 'plan328member', 'Member', ['urls:read'], null);
    $key       = $created['plaintextKey'];

    assert_true(is_array(g2ml_apiVerifyKey($key)));
    assert_same(200, g2mlApiPlanTest_call($key, 'urls')['status']);
    assert_same(1, g2mlApiPlanTest_ownerBelongs('plan328member', $created['apiKeyUID']));

    // The real removal, made by a signed-in global administrator.
    $removed = g2mlApiPlanTest_asGlobalAdmin($adminUID, 'plan328member', function () use ($memberUID): array
    {
        return removeMember('plan328member', $memberUID);
    });

    assert_true($removed['success'], 'removeMember must succeed');
    assert_same(null, g2ml_apiVerifyKey($key), 'Removed to the default organisation');
    assert_same(401, g2mlApiPlanTest_call($key, 'urls')['status']);
    assert_same(0, g2mlApiPlanTest_ownerBelongs('plan328member', $created['apiKeyUID']));

    // Joining a different organisation (what accepting an invitation does).
    dbUpdate('UPDATE `tblUsers` SET `orgHandle` = ?, `role` = ? WHERE `userUID` = ?', 'ssi', ['plan328other', 'Admin', $memberUID]);
    assert_same(401, g2mlApiPlanTest_call($key, 'urls')['status']);

    dbUpdate('UPDATE `tblUsers` SET `orgHandle` = ? WHERE `userUID` = ?', 'si', ['plan328member', $memberUID]);
    assert_same(200, g2mlApiPlanTest_call($key, 'urls')['status']);
});

test('API keys (#328): a global administrator\'s key keeps working after they move organisation, and stops if they lose the role', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328member', $g2mlApiPlanPaid);
    g2mlApiPlanTest_org('plan328other', $g2mlApiPlanPaid);
    $userUID = g2mlApiPlanTest_user('plan328member', 'GlobalAdmin');
    $created = g2ml_apiGenerateKey($userUID, 'plan328member', 'Admin', ['urls:read'], null);

    dbUpdate("UPDATE `tblUsers` SET `orgHandle` = 'plan328other' WHERE `userUID` = ?", 'i', [$userUID]);

    assert_true(is_array(g2ml_apiVerifyKey($created['plaintextKey'])));
    assert_same(1, g2mlApiPlanTest_ownerBelongs('plan328member', $created['apiKeyUID']));

    dbUpdate("UPDATE `tblUsers` SET `role` = 'Admin' WHERE `userUID` = ?", 'i', [$userUID]);

    assert_same(null, g2ml_apiVerifyKey($created['plaintextKey']));
    assert_same(0, g2mlApiPlanTest_ownerBelongs('plan328member', $created['apiKeyUID']));
});

test('API keys (#328, D-033): a demoted administrator\'s key is refused on every call and works again when the role is restored', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328role', $g2mlApiPlanPaid);
    $actingUID = g2mlApiPlanTest_user('plan328role', 'Admin');
    $memberUID = g2mlApiPlanTest_user('plan328role', 'Admin');

    // One account type only, as migration 008 left every existing user.
    dbInsert(
        "INSERT INTO `tblUserAccountTypes` (`userUID`, `orgHandle`, `accountTypeID`, `isActive`) VALUES (?, 'plan328role', 'admin', 1)",
        'i',
        [$memberUID]
    );

    $created = g2ml_apiGenerateKey($memberUID, 'plan328role', 'Role', ['urls:read'], null);
    $key     = $created['plaintextKey'];

    assert_same(200, g2mlApiPlanTest_call($key, 'urls')['status']);

    $change = function (string $role) use ($actingUID, $memberUID): array
    {
        return g2mlApiPlanTest_asGlobalAdmin($actingUID, 'plan328role', function () use ($memberUID, $role): array
        {
            return changeMemberRole('plan328role', $memberUID, $role);
        });
    };

    assert_true($change('User')['success']);

    $row = dbSelectOne('SELECT `role` FROM `tblUsers` WHERE `userUID` = ?', 'i', [$memberUID]);
    assert_same('User', $row['role'], 'The demotion must stick');
    assert_same(401, g2mlApiPlanTest_call($key, 'urls')['status']);
    assert_same(401, g2mlApiPlanTest_call($key, 'ping')['status']);
    assert_false(g2ml_apiGenerateKey($memberUID, 'plan328role', 'No', ['urls:read'], null)['success'], 'No new key either');

    foreach (g2ml_apiListKeys('plan328role') as $listed)
    {
        assert_same(1, (int) $listed['ownerBelongs']);
        assert_same(0, (int) $listed['ownerIsAdmin']);
    }

    assert_true($change('Admin')['success']);

    $row = dbSelectOne('SELECT `role` FROM `tblUsers` WHERE `userUID` = ?', 'i', [$memberUID]);
    assert_same('Admin', $row['role']);
    assert_same(200, g2mlApiPlanTest_call($key, 'urls')['status']);
});

/**
 * Change a member's role through the real function, as a global administrator.
 */
function g2mlApiPlanTest_changeRole(string $orgHandle, int $memberUID, string $role): array
{
    $actingUID = g2mlApiPlanTest_user($orgHandle, 'Admin');

    return g2mlApiPlanTest_asGlobalAdmin($actingUID, $orgHandle, function () use ($orgHandle, $memberUID, $role): array
    {
        return changeMemberRole($orgHandle, $memberUID, $role);
    });
}

/**
 * A user's role and active account types, as "Role|type,type".
 */
function g2mlApiPlanTest_standing(int $userUID): string
{
    $row = dbSelectOne('SELECT `role` FROM `tblUsers` WHERE `userUID` = ?', 'i', [$userUID]);

    $types = [];

    foreach (dbSelect('SELECT `accountTypeID` FROM `tblUserAccountTypes` WHERE `userUID` = ? AND `isActive` = 1 ORDER BY `accountTypeID`', 'i', [$userUID]) as $type)
    {
        $types[] = $type['accountTypeID'];
    }

    return $row['role'] . '|' . implode(',', $types);
}

/**
 * Give a user an active account type.
 */
function g2mlApiPlanTest_type(int $userUID, string $orgHandle, string $accountTypeID): void
{
    dbInsert(
        'INSERT INTO `tblUserAccountTypes` (`userUID`, `orgHandle`, `accountTypeID`, `isActive`) VALUES (?, ?, ?, 1)',
        'iss',
        [$userUID, $orgHandle, $accountTypeID]
    );
}

test('changeMemberRole (#328): a global administrator who already holds a user type is refused and keeps the role', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328role', $g2mlApiPlanPaid);
    $memberUID = g2mlApiPlanTest_user('plan328role', 'GlobalAdmin');
    g2mlApiPlanTest_type($memberUID, '[default]', 'global-admin');
    g2mlApiPlanTest_type($memberUID, 'plan328role', 'user');

    foreach (['User', 'Admin'] as $role)
    {
        $result = g2mlApiPlanTest_changeRole('plan328role', $memberUID, $role);

        assert_false($result['success']);
        assert_contains('global administrator', $result['error']);
        assert_same('GlobalAdmin|global-admin,user', g2mlApiPlanTest_standing($memberUID));
    }
});

test('changeMemberRole (#328): a demotion works when the member already holds a user type', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328role', $g2mlApiPlanPaid);
    $memberUID = g2mlApiPlanTest_user('plan328role', 'Admin');
    g2mlApiPlanTest_type($memberUID, 'plan328role', 'admin');
    g2mlApiPlanTest_type($memberUID, 'plan328role', 'user');

    assert_true(g2mlApiPlanTest_changeRole('plan328role', $memberUID, 'User')['success']);
    assert_same('User|user', g2mlApiPlanTest_standing($memberUID));
});

test('changeMemberRole (#328): if the new type cannot be given, nothing changes and the error is returned', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328role', $g2mlApiPlanPaid);

    foreach ([['User', 'user', 'Admin', 'admin'], ['Admin', 'admin', 'User', 'user']] as [$from, $fromType, $to, $toType])
    {
        $memberUID = g2mlApiPlanTest_user('plan328role', $from);
        g2mlApiPlanTest_type($memberUID, 'plan328role', $fromType);
        // A second type, so that the old one COULD be revoked.
        g2mlApiPlanTest_type($memberUID, '[default]', $fromType);

        dbUpdate('UPDATE `tblAccountTypes` SET `isActive` = 0 WHERE `accountTypeID` = ?', 's', [$toType]);

        try
        {
            $result = g2mlApiPlanTest_changeRole('plan328role', $memberUID, $to);
        }
        finally
        {
            dbUpdate('UPDATE `tblAccountTypes` SET `isActive` = 1 WHERE `accountTypeID` = ?', 's', [$toType]);
        }

        assert_false($result['success'], $from . ' to ' . $to);
        assert_contains('not found or inactive', $result['error']);
        assert_same($from . '|' . $fromType . ',' . $fromType, g2mlApiPlanTest_standing($memberUID));
    }
});

test('changeMemberRole (#328): a demotion undone by another admin type is reported, not called a success', function () use ($g2mlApiPlanPaid): void
{
    g2mlApiPlanTest_org('plan328role', $g2mlApiPlanPaid);
    g2mlApiPlanTest_org('plan328other', $g2mlApiPlanPaid);
    $memberUID = g2mlApiPlanTest_user('plan328role', 'Admin');
    g2mlApiPlanTest_type($memberUID, 'plan328role', 'admin');
    g2mlApiPlanTest_type($memberUID, 'plan328other', 'admin');

    assert_false(g2mlApiPlanTest_changeRole('plan328role', $memberUID, 'User')['success']);
});

test('API plan (#328): the new words are seeded', function (): void
{
    $row = dbSelectOne(
        'SELECT COUNT(*) AS `total` FROM `tblTranslations` WHERE `localeCode` = \'en-GB\' AND `translationKey` IN '
        . "('api.error_plan_check_failed', 'api.error_plan_not_included', 'apikeys.empty_next_step', 'apikeys.plan_check_heading', "
        . "'apikeys.plan_check_retry', 'apikeys.plan_contact_button', 'apikeys.plan_heading', 'apikeys.plan_links_button', "
        . "'apikeys.plan_message', 'apikeys.plan_next_step', 'apikeys.status_not_checked', 'apikeys.status_not_in_plan', 'apikeys.status_owner_left', 'help.api.s3_plan', 'apikeys.error_not_admin', 'apikeys.status_owner_not_admin', 'help.api.s3_admin')",
        '',
        []
    );

    assert_same(17, (int) $row['total']);
});
