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
 * 🧪 Integration tests — request text can never make a log row fail (#331)
 * ============================================================================
 *
 * The database refuses a whole INSERT when a value is too long for its column
 * or is not valid UTF-8. A visitor controls the browser name, host name,
 * address and referring page, so before #331 they could stop a row being
 * written. Rows in these tables are what the API's failed-sign-in backoff, the
 * failed-login CAPTCHA and the anonymous link limit count.
 *
 * These tests call the real functions against the real schema.
 *
 * @package    Go2My.Link
 * @subpackage Tests
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
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlLogFitTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLogFitTestHost === false || $g2mlLogFitTestHost === '')
    {
        $g2mlLogFitTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLogFitTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLogFitTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLogFitTestPortRaw === false || $g2mlLogFitTestPortRaw === '')
    {
        $g2mlLogFitTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlLogFitTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlLogFitTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLogFitTestUser === false || $g2mlLogFitTestUser === '')
    {
        $g2mlLogFitTestUser = 'root';
    }

    define('DB_USER', $g2mlLogFitTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLogFitTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLogFitTestPass === false)
    {
        $g2mlLogFitTestPass = '';
    }

    define('DB_PASS', $g2mlLogFitTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLogFitTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLogFitTestName === false || $g2mlLogFitTestName === '')
    {
        $g2mlLogFitTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLogFitTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlLogFitFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlLogFitFunctionsDir . '/db_connect.php';
require_once $g2mlLogFitFunctionsDir . '/db_query.php';
require_once $g2mlLogFitFunctionsDir . '/security.php';
require_once $g2mlLogFitFunctionsDir . '/settings.php';
require_once $g2mlLogFitFunctionsDir . '/api_ratelimit.php';
require_once $g2mlLogFitFunctionsDir . '/activity_logger.php';
require_once $g2mlLogFitFunctionsDir . '/cookie_consent.php';
require_once $g2mlLogFitFunctionsDir . '/session.php';
require_once $g2mlLogFitFunctionsDir . '/error_handler.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Run a callback with the given request values in $_SERVER, then put $_SERVER
 * back. PHP's error log is sent to a temporary file meanwhile.
 *
 * @param  array<string, string> $server
 * @param  callable              $callback
 * @return mixed  Whatever the callback returns
 */
function g2ml_logfit_test_request(array $server, callable $callback): mixed
{
    $savedServer = $_SERVER;
    $savedLog    = (string) ini_get('error_log');
    $logFile     = (string) tempnam(sys_get_temp_dir(), 'g2ml_logfit_');

    foreach ($server as $key => $value)
    {
        $_SERVER[$key] = $value;
    }

    ini_set('error_log', $logFile);

    try
    {
        return $callback();
    }
    finally
    {
        $_SERVER = $savedServer;
        ini_set('error_log', $savedLog);
        @unlink($logFile);
    }
}

/**
 * Read every row of one log table for an IP address, oldest first.
 *
 * @param  mysqli $db
 * @param  string $table      One of the fixed table names used below
 * @param  string $ipAddress
 * @return array<int, array<string, mixed>>
 */
function g2ml_logfit_test_rows(mysqli $db, string $table, string $ipAddress): array
{
    $statement = $db->prepare('SELECT * FROM `' . $table . '` WHERE `ipAddress` = ?');
    $statement->bind_param('s', $ipAddress);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $rows;
}

/**
 * Delete every row of one log table for an IP address.
 */
function g2ml_logfit_test_clear(mysqli $db, string $table, string $ipAddress): void
{
    $statement = $db->prepare('DELETE FROM `' . $table . '` WHERE `ipAddress` = ?');
    $statement->bind_param('s', $ipAddress);
    $statement->execute();
    $statement->close();
}

/**
 * Browser names that made the old code's INSERT fail: stray bytes, and a
 * two-byte letter lying across the 500th byte.
 *
 * @return array<string, string>
 */
function g2ml_logfit_test_agents(): array
{
    return [
        'stray bytes'              => "agent \xFF\xC3 end",
        'letter across the cut'    => str_repeat('a', 499) . str_repeat('é', 20),
        'four-byte letters'        => str_repeat('🔒', 700),
    ];
}

// ============================================================================
// API request log
// ============================================================================

test('API log (#331): a bad browser name or route still writes the row, and the failed-sign-in backoff counts it', function () use ($db): void
{
    $ip = '198.51.100.31';
    g2ml_logfit_test_clear($db, 'tblAPIRequestLog', $ip);
    mysqli_query($db, "UPDATE `tblSettings` SET `settingValue` = '5' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    try
    {
        $attempts = [];

        foreach (g2ml_logfit_test_agents() as $agent)
        {
            $attempts[] = ['/api/v1/urls', 'GET', $agent];
        }

        // Stray bytes in the route, and a route cut part way through a letter
        $attempts[] = ["/api/v1/\xFF\xFE", 'GET', 'ua'];
        $attempts[] = ['/api/v1/' . str_repeat('é', 400), "G\xFF", 'ua'];

        foreach ($attempts as $index => $attempt)
        {
            g2ml_logfit_test_request([], function () use ($attempt, $ip): void
            {
                g2ml_apiLogRequest(null, $attempt[0], $attempt[1], 401, 3, $ip, $attempt[2], null);
            });

            assert_same($index + 1, count(g2ml_logfit_test_rows($db, 'tblAPIRequestLog', $ip)), 'Attempt ' . $index . ' must write its row');
        }

        $rows = g2ml_logfit_test_rows($db, 'tblAPIRequestLog', $ip);
        $longRoutes = 0;

        foreach ($rows as $row)
        {
            assert_true(mb_check_encoding((string) $row['userAgent'], 'UTF-8'));
            assert_true(mb_strlen((string) $row['userAgent'], 'UTF-8') <= 500);

            if (mb_strlen((string) $row['endpoint'], 'UTF-8') === 255)
            {
                $longRoutes++;
            }
        }

        assert_same(1, $longRoutes, 'The long route is cut to 255 characters');
        assert_false(g2ml_apiCheckPreAuthBackoff($ip)['allowed'], 'All five failed sign-ins must count');
    }
    finally
    {
        mysqli_query($db, "UPDATE `tblSettings` SET `settingValue` = '20' WHERE `settingID` = 'api.preauth_fail_per_minute'");
        loadSettingsCache();
        g2ml_logfit_test_clear($db, 'tblAPIRequestLog', $ip);
    }
});

// ============================================================================
// Activity log
// ============================================================================

test('activity log (#331): over-long or badly encoded request values still write the row', function () use ($db): void
{
    $ip = '198.51.100.32';
    g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    loadSettingsCache();

    $cases = [
        'long host name'        => ['HTTP_HOST' => str_repeat('h', 600)],
        'bad host name'         => ['HTTP_HOST' => "host\xFF.test"],
        'long method'           => ['REQUEST_METHOD' => str_repeat('M', 60)],
        'bad address'           => ['REQUEST_URI' => "/\xFF/" . str_repeat('é', 600)],
        'bad referring page'    => ['HTTP_REFERER' => "https://example.test/\xC3" . str_repeat('é', 600)],
        'long browser version'  => ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT ' . str_repeat('9', 90) . ') Chrome/' . str_repeat('1', 90) . ' Safari/1'],
    ];

    foreach (g2ml_logfit_test_agents() as $name => $agent)
    {
        $cases[$name] = ['HTTP_USER_AGENT' => $agent];
    }

    try
    {
        $expected = 0;

        foreach ($cases as $name => $server)
        {
            $server['REMOTE_ADDR'] = $ip;

            // The two actions that security controls count
            foreach ([['login', 'wrong_password'], ['create_link', 'success']] as $event)
            {
                $saved = g2ml_logfit_test_request($server, function () use ($event): bool
                {
                    return logActivity($event[0], $event[1], 401);
                });

                $expected++;
                assert_true($saved, $name . ': ' . $event[0] . ' must be saved');
            }
        }

        assert_same($expected, count(g2ml_logfit_test_rows($db, 'tblActivityLog', $ip)));
    }
    finally
    {
        g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    }
});

test('activity log (#331): over-long or badly encoded context values are cut to their columns', function () use ($db): void
{
    $ip = '198.51.100.33';
    g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    loadSettingsCache();

    try
    {
        $saved = g2ml_logfit_test_request(['REMOTE_ADDR' => $ip], function (): bool
        {
            return logActivity(str_repeat('é', 150), str_repeat('é', 80), 404, [
                'orgHandle'      => str_repeat('é', 80),
                'shortCode'      => "\xFF" . str_repeat('é', 80),
                'destinationURL' => "https://example.test/\xFF" . str_repeat('é', 40000),
                'scanSource'     => str_repeat('é', 80),
                'countryCode'    => 'ééé',
                'regionCode'     => str_repeat('é', 20),
                'cityName'       => str_repeat('é', 300),
            ]);
        });

        assert_true($saved);

        $rows = g2ml_logfit_test_rows($db, 'tblActivityLog', $ip);
        assert_same(1, count($rows));

        $limits = [
            'logAction' => 100, 'logStatus' => 50, 'orgHandle' => 50, 'shortCode' => 50,
            'scanSource' => 50, 'countryCode' => 2, 'regionCode' => 10, 'cityName' => 255,
        ];

        foreach ($limits as $column => $limit)
        {
            assert_same($limit, mb_strlen((string) $rows[0][$column], 'UTF-8'), $column);
        }

        assert_true(strlen((string) $rows[0]['destinationURL']) <= 65535);
        assert_true(mb_check_encoding((string) $rows[0]['destinationURL'], 'UTF-8'));
    }
    finally
    {
        g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    }
});

test('activity log (#331): details nested too deeply or too large are replaced by a note, and the row is kept', function () use ($db): void
{
    $ip = '198.51.100.34';
    g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    loadSettingsCache();

    $nested = function (int $levels): array
    {
        $data = ['a' => 'end'];

        for ($level = 1; $level < $levels; $level++)
        {
            $data = ['a' => $data];
        }

        return $data;
    };

    $deepNote  = ['_note' => 'log data omitted from activity log (nested too deeply)'];
    $largeNote = ['_note' => 'log data omitted from activity log (too large)'];
    $cases     = [
        [$nested(31), $nested(31)],
        [$nested(32), $deepNote],
        [$nested(120), $deepNote],
        [['big' => str_repeat('a', 70000)], $largeNote],
    ];

    try
    {
        foreach ($cases as $index => $case)
        {
            g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);

            $saved = g2ml_logfit_test_request(['REMOTE_ADDR' => $ip], function () use ($case): bool
            {
                return logActivity('login', 'wrong_password', 401, ['logData' => $case[0]]);
            });

            assert_true($saved, 'Case ' . $index . ' must be saved');
            $rows = g2ml_logfit_test_rows($db, 'tblActivityLog', $ip);
            assert_same(1, count($rows));
            assert_json_same($case[1], $rows[0]['logData'], 'Case ' . $index);
        }
    }
    finally
    {
        g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    }
});

// ============================================================================
// Consent records and sessions
// ============================================================================

test('consent record (#331): a bad browser name still records the choice', function () use ($db): void
{
    $ip = '198.51.100.35';
    g2ml_logfit_test_clear($db, 'tblConsentRecords', $ip);
    loadSettingsCache();
    $savedSession = $_SESSION;
    $_SESSION     = [];

    try
    {
        $expected = 0;

        foreach (g2ml_logfit_test_agents() as $name => $agent)
        {
            $saved = g2ml_logfit_test_request(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $agent], function (): bool
            {
                return g2ml_recordConsent('analytics', false, 'banner');
            });

            $expected++;
            assert_true($saved, $name);
        }

        assert_same($expected, count(g2ml_logfit_test_rows($db, 'tblConsentRecords', $ip)));
    }
    finally
    {
        $_SESSION = $savedSession;
        g2ml_logfit_test_clear($db, 'tblConsentRecords', $ip);
        g2ml_logfit_test_clear($db, 'tblActivityLog', $ip);
    }
});

test('session (#331): a bad browser name still creates the signed-in session', function () use ($db): void
{
    $ip     = '198.51.100.36';
    $marker = 'logfit331_' . bin2hex(random_bytes(6));
    $email  = $marker . '@example.test';

    mysqli_query($db, "INSERT IGNORE INTO `tblSubscriptionTiers` (`tierID`, `tierName`) VALUES ('free', 'Free')");
    mysqli_query($db, "INSERT IGNORE INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) VALUES ('[default]', 'Default Test Org', 'free', 1)");

    $insert = $db->prepare("INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`) VALUES ('[default]', ?, ?, 'x')");
    $insert->bind_param('ss', $marker, $email);
    $insert->execute();
    $userUID = (int) $insert->insert_id;
    $insert->close();

    $savedSession = $_SESSION;

    try
    {
        foreach (g2ml_logfit_test_agents() as $name => $agent)
        {
            $token = g2ml_logfit_test_request(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $agent], function () use ($userUID): string|false
            {
                return createUserSession($userUID);
            });

            assert_true(is_string($token), $name);
        }

        assert_same(3, count(g2ml_logfit_test_rows($db, 'tblUserSessions', $ip)));
    }
    finally
    {
        $_SESSION = $savedSession;
        g2ml_logfit_test_clear($db, 'tblUserSessions', $ip);
        mysqli_query($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
    }
});

// ============================================================================
// Error log
// ============================================================================

test('error log (#331): very long or badly encoded headers are each cut, and none is lost', function () use ($db): void
{
    $ip    = '198.51.100.37';
    $names = [
        'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'HTTP_ACCEPT',
        'HTTP_ACCEPT_LANGUAGE', 'CONTENT_TYPE', 'CONTENT_LENGTH',
    ];

    // Stray bytes, and control characters (six characters each in JSON)
    foreach (["\xFF", "\x01"] as $filler)
    {
        g2ml_logfit_test_clear($db, 'tblErrorLog', $ip);
        $server = ['REMOTE_ADDR' => $ip];

        foreach ($names as $name)
        {
            $server[$name] = $name . str_repeat($filler, 12000);
        }

        g2ml_logfit_test_request($server, function (): void
        {
            g2ml_logError('warning', 1, 'g2ml331 headers');
        });

        $rows = g2ml_logfit_test_rows($db, 'tblErrorLog', $ip);
        assert_same(1, count($rows), 'The row must be saved');

        $headers = json_decode((string) $rows[0]['requestHeaders'], true);
        assert_true(is_array($headers));

        foreach ($names as $name)
        {
            assert_true(isset($headers[$name]), $name . ' must be kept');
            assert_true(strpos($headers[$name], $name) === 0, $name . ' keeps its start');
            assert_true(strlen($headers[$name]) <= 1000, $name . ' is cut');
        }
    }

    g2ml_logfit_test_clear($db, 'tblErrorLog', $ip);
});
