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
 * 🧪 Integration tests — the cache headers real requests are sent (#213)
 * ============================================================================
 *
 * Asks for real pages and downloads over HTTP and reads the headers back,
 * because PHP's session_start() and the download functions send cache headers
 * of their own that can replace ours.
 *
 * Uses the copy of the site, the web servers and the signed-in visitors made by
 * debug_access_test.php (the g2mlDebugTest_* helpers). The runner loads every
 * test file before running any test, so they are available here.
 *
 * Not covered: the .htaccess files (the built-in server does not read them),
 * so static files and Apache's own redirects are not exercised.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#213)
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

/**
 * Every value a response gave for one header.
 *
 * @return array<int, string>
 */
function g2mlCacheHttpTest_values(array $response, string $name): array
{
    $values = array();

    foreach (explode("\n", $response['headers']) as $line)
    {
        if (stripos($line, $name . ':') === 0)
        {
            $values[] = trim(substr($line, strlen($name) + 1));
        }
    }

    return $values;
}

/**
 * Fail unless the response says it must not be stored.
 */
function g2mlCacheHttpTest_assertPrivate(array $response, string $label): void
{
    assert_same(array('no-store, private'), g2mlCacheHttpTest_values($response, 'Cache-Control'), $label);
    assert_same(array('no-cache'), g2mlCacheHttpTest_values($response, 'Pragma'), $label);
}

/**
 * The user number behind a session made by g2mlDebugTest_signIn().
 */
function g2mlCacheHttpTest_userUID(mysqli $db, string $marker): int
{
    $statement = $db->prepare('SELECT `userUID` FROM `tblUsers` WHERE `username` = ?');
    $statement->bind_param('s', $marker);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return (int) $row['userUID'];
}

test('cache headers (#213): signed-in pages on the main site and the dashboard are not stored', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'User', 'User');

    foreach (array('about', 'pricing', 'login', 'no-such-page') as $route)
    {
        $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=' . $route, $sessionID);
        g2mlCacheHttpTest_assertPrivate($response, 'main ' . $route);
    }

    foreach (array('', 'links', 'api-keys', 'analytics', 'profile', 'profile/sessions', 'privacy/export', 'no-such-page') as $route)
    {
        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', '/index.php?route=' . $route, $sessionID);
        g2mlCacheHttpTest_assertPrivate($response, 'dashboard ' . $route);
    }

    $response = g2mlDebugTest_get('main', 'go2my.link', '/api/create/index.php', $sessionID);
    g2mlCacheHttpTest_assertPrivate($response, 'api/create');

    $response = g2mlDebugTest_get('main', 'go2my.link', '/api/v1/index.php', $sessionID);
    g2mlCacheHttpTest_assertPrivate($response, 'api/v1');
});

test('cache headers (#213): the security page and an access refusal are not stored', function () use ($db): void
{
    $admin = g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin');
    $user  = g2mlDebugTest_signIn($db, 'User', 'User');

    foreach (array($admin, $user) as $sessionID)
    {
        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', '/index.php?route=security/breach-response', $sessionID);
        g2mlCacheHttpTest_assertPrivate($response, 'breach response');
    }
});

test('cache headers (#213): dashboard answers to a signed-out visitor are not stored', function (): void
{
    $addresses = array(
        '/index.php',
        '/index.php?route=api-keys',
        '/analytics-export.php?type=totals',
        '/cron.php',
        '/offline.php',
        '/api/consent/index.php',
    );

    foreach ($addresses as $address)
    {
        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', $address);
        g2mlCacheHttpTest_assertPrivate($response, $address);
    }
});

test('cache headers (#213): the analytics CSV download is not stored', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'User', 'User');
    $response  = g2mlDebugTest_get('admin', 'admin.go2my.link', '/analytics-export.php?type=totals', $sessionID);

    assert_contains('200', $response['status']);
    assert_contains('text/csv', $response['headers']);
    g2mlCacheHttpTest_assertPrivate($response, 'analytics CSV');

    $response = g2mlDebugTest_get('admin', 'admin.go2my.link', '/analytics-export.php?type=nonsense', $sessionID);

    assert_contains('400', $response['status']);
    g2mlCacheHttpTest_assertPrivate($response, 'analytics CSV refusal');
});

test('cache headers (#213): the data export download is not stored', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'User', 'User');
    $userUID   = g2mlCacheHttpTest_userUID($db, $sessionID);
    $site      = g2mlDebugTest_site();
    $folder    = $site['web'] . '/_uploads/exports';

    if (!is_dir($folder))
    {
        mkdir($folder, 0700, true);
    }

    $path = $folder . '/cache213.json';
    file_put_contents($path, '{"marker":"EXPORT-213"}');

    $statement = $db->prepare(
        'INSERT INTO `tblDataDeletionRequests` (`userUID`, `requestType`, `status`, `exportFilePath`, `exportExpiresAt`) '
        . "VALUES (?, 'export', 'completed', ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY))"
    );
    $statement->bind_param('is', $userUID, $path);

    if ($statement->execute() === false)
    {
        throw new RuntimeException('Could not create an export request: ' . $statement->error);
    }

    $requestUID = (int) $statement->insert_id;
    $statement->close();

    try
    {
        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', '/index.php?route=privacy/export&download=' . $requestUID, $sessionID);

        assert_contains('200', $response['status']);
        assert_contains('EXPORT-213', $response['body']);
        g2mlCacheHttpTest_assertPrivate($response, 'data export');
    }
    finally
    {
        $delete = $db->prepare('DELETE FROM `tblDataDeletionRequests` WHERE `requestUID` = ?');
        $delete->bind_param('i', $requestUID);
        $delete->execute();
        $delete->close();
        unlink($path);
    }
});

test('cache headers (#213): pages opened with a one-time token are not stored', function (): void
{
    $addresses = array(
        '/index.php?route=reset-password&token=abc213',
        '/index.php?route=reset-password&forced=1',
        '/index.php?route=verify-email&token=abc213',
        '/index.php?route=invite&token=abc213',
        '/index.php?route=login&redirect=' . urlencode('/invite?token=abc213'),
    );

    foreach ($addresses as $address)
    {
        $response = g2mlDebugTest_get('main', 'go2my.link', $address);
        g2mlCacheHttpTest_assertPrivate($response, $address);
    }
});

test('cache headers (#213): public pages are sent as before', function (): void
{
    foreach (array('about', 'pricing', 'login') as $route)
    {
        $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=' . $route);

        // What PHP's session cache limiter sends by default.
        assert_same(array('no-store, no-cache, must-revalidate'), g2mlCacheHttpTest_values($response, 'Cache-Control'), $route);
    }
});

test('cache headers (#213): a server set to let pages be cached cannot make a private page storable', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'User', 'User');
    $site      = g2mlDebugTest_site();
    $database  = getenv('G2ML_TEST_DB_HOST');

    if ($database === false || $database === '')
    {
        $database = '127.0.0.1';
    }

    try
    {
        g2mlDebugTest_credentials($site['web'], $database, "ini_set('session.cache_limiter', 'public');\n");

        $public = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');
        assert_contains('public', implode(',', g2mlCacheHttpTest_values($public, 'Cache-Control')), 'The setting did not take effect');

        $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about', $sessionID);
        g2mlCacheHttpTest_assertPrivate($response, 'signed in');

        $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=reset-password&token=abc213');
        g2mlCacheHttpTest_assertPrivate($response, 'token page');

        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', '/index.php');
        g2mlCacheHttpTest_assertPrivate($response, 'dashboard');
    }
    finally
    {
        g2mlDebugTest_credentials($site['web'], $database);
    }
});
