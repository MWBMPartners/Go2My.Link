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
 * 🧪 Integration tests — who sees the debug panel and error details (#319)
 * ============================================================================
 *
 * D-011: on every web address only a signed-in global administrator sees the
 * debug panel or error details. These tests ask for real pages over HTTP.
 *
 * A copy of web/ is made in a temporary folder, with credentials pointing at
 * the test database and a few deliberately broken pages added. PHP's built-in
 * web server serves it with display_errors switched ON, as a badly set up host
 * would, so the application itself has to keep errors off the screen. Signed-in
 * visitors are made by writing a PHP session file and the matching database
 * session row.
 *
 * The built-in server does not read .htaccess or .user.ini files, so those are
 * checked separately (tests/unit/debug_access_test.php).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#319)
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

/**
 * Copy a folder and everything in it.
 */
function g2mlDebugTest_copy(string $from, string $to): void
{
    mkdir($to, 0700, true);

    foreach (scandir($from) as $name)
    {
        if ($name === '.' || $name === '..' || $name === '_uploads' || $name === '_backups')
        {
            continue;
        }

        $source = $from . '/' . $name;

        if (is_dir($source))
        {
            g2mlDebugTest_copy($source, $to . '/' . $name);
        }
        else
        {
            copy($source, $to . '/' . $name);
        }
    }
}

/**
 * Delete a folder and everything in it.
 */
function g2mlDebugTest_remove(string $folder): void
{
    foreach (scandir($folder) as $name)
    {
        if ($name === '.' || $name === '..')
        {
            continue;
        }

        $path = $folder . '/' . $name;

        if (is_dir($path))
        {
            g2mlDebugTest_remove($path);
        }
        else
        {
            unlink($path);
        }
    }

    rmdir($folder);
}

/**
 * Write the credentials file every component of the copy will load.
 */
function g2mlDebugTest_credentials(string $web, string $databaseHost, string $extra = ''): void
{
    $database = getenv('G2ML_TEST_DB_NAME');

    if ($database === false || $database === '')
    {
        $database = 'mwtools_Go2MyLink';
    }

    $user = getenv('G2ML_TEST_DB_USER');

    if ($user === false || $user === '')
    {
        $user = 'root';
    }

    $password = getenv('G2ML_TEST_DB_PASS');

    if ($password === false)
    {
        $password = '';
    }

    $port = getenv('G2ML_TEST_DB_PORT');

    if ($port === false || $port === '')
    {
        $port = '3306';
    }

    $constants = array(
        'DB_HOST'                  => $databaseHost,
        'DB_PORT'                  => (int) $port,
        'DB_NAME'                  => $database,
        'DB_USER'                  => $user,
        'DB_PASS'                  => $password,
        'DB_CHARSET'               => 'utf8mb4',
        'ENCRYPTION_SALT'          => str_repeat('a1b2c3d4', 8),
        'ENCRYPTION_KEY_SECONDARY' => str_repeat('9f8e7d6c', 8),
    );

    $file = "<?php\n";

    foreach ($constants as $name => $value)
    {
        $file .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ");\n";
    }

    $file .= $extra;

    foreach (array('Go2My.Link', 'G2My.Link', 'Lnks.page') as $component)
    {
        if (!is_dir($web . '/' . $component . '/.auth'))
        {
            mkdir($web . '/' . $component . '/.auth', 0700);
        }

        file_put_contents($web . '/' . $component . '/.auth/auth_creds.php', $file);
    }

    file_put_contents($web . '/_auth_keys/auth_creds.php', $file);
}

/**
 * Make the copy of the site once per test run and start one web server for each
 * web root. Everything is removed when the test run ends.
 *
 * @return array{web: string, sessions: string, ports: array<string, int>}
 */
function g2mlDebugTest_site(): array
{
    static $site = null;

    if ($site !== null)
    {
        return $site;
    }

    $base     = sys_get_temp_dir() . '/g2ml_debug319_' . bin2hex(random_bytes(6));
    $web      = $base . '/web';
    $sessions = $base . '/sessions';

    g2mlDebugTest_copy(dirname(__DIR__, 2) . '/web', $web);
    mkdir($sessions, 0700);

    $databaseHost = getenv('G2ML_TEST_DB_HOST');

    if ($databaseHost === false || $databaseHost === '')
    {
        $databaseHost = '127.0.0.1';
    }

    g2mlDebugTest_credentials($web, $databaseHost);

    // Deliberately broken pages, only ever in this temporary copy.
    $broken = array(
        'zzthrow' => "<?php\nthrow new RuntimeException('SECRET-DETAIL-319');\n",
        'zzfatal' => "<?php\ng2ml_no_such_function_SECRET319();\n",
        'zzwarn'  => "<?php\necho 'PAGE-BODY-319';\ntrigger_error('SECRET-DETAIL-319', E_USER_WARNING);\n",
    );

    foreach ($broken as $name => $code)
    {
        mkdir($web . '/Go2My.Link/public_html/pages/' . $name);
        file_put_contents($web . '/Go2My.Link/public_html/pages/' . $name . '/index.php', $code);
    }

    $roots = array(
        'main'  => $web . '/Go2My.Link/public_html',
        'admin' => $web . '/Go2My.Link/_admin/public_html',
    );

    $ports     = array();
    $processes = array();

    foreach ($roots as $name => $root)
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port  = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $process = proc_open(
            array(
                PHP_BINARY,
                '-d', 'display_errors=1',
                '-d', 'display_startup_errors=1',
                '-d', 'html_errors=0',
                '-d', 'expose_php=1',
                '-d', 'session.save_path=' . $sessions,
                // Some tests rewrite a file in the copy; read it afresh.
                '-d', 'opcache.enable=0',
                '-S', '127.0.0.1:' . $port,
                '-t', $root,
            ),
            array(
                0 => array('pipe', 'r'),
                1 => array('file', $base . '/' . $name . '.out', 'w'),
                2 => array('file', $base . '/' . $name . '.err', 'w'),
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

        $ports[$name] = $port;
        $processes[]  = $process;
    }

    register_shutdown_function(function () use ($processes, $base): void
    {
        foreach ($processes as $process)
        {
            proc_terminate($process);
            proc_close($process);
        }

        g2mlDebugTest_remove($base);
    });

    $site = array('web' => $web, 'sessions' => $sessions, 'ports' => $ports);

    return $site;
}

/**
 * Ask for one page.
 *
 * @return array{status: string, headers: string, body: string}
 */
function g2mlDebugTest_get(string $root, string $host, string $path, string $sessionID = ''): array
{
    $site    = g2mlDebugTest_site();
    $headers = 'Host: ' . $host . "\r\n";

    if ($sessionID !== '')
    {
        $headers .= 'Cookie: G2ML_SESSION=' . $sessionID . "\r\n";
    }

    $context = stream_context_create(array('http' => array(
        'header'          => $headers,
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 20,
    )));

    $stream = fopen('http://127.0.0.1:' . $site['ports'][$root] . $path, 'r', false, $context);

    if ($stream === false)
    {
        throw new RuntimeException('No answer for ' . $path);
    }

    $meta = stream_get_meta_data($stream);
    $body = (string) stream_get_contents($stream);
    fclose($stream);

    return array(
        'status'  => (string) $meta['wrapper_data'][0],
        'headers' => implode("\n", $meta['wrapper_data']),
        'body'    => $body,
    );
}

/**
 * Create a user with the given role and sign them in. Returns the PHP session
 * ID to send as a cookie. The user is removed when the test run ends.
 *
 * @param  string $databaseRole  The role stored in tblUsers
 * @param  string $sessionRole   The role copied into the PHP session
 */
function g2mlDebugTest_signIn(mysqli $db, string $databaseRole, string $sessionRole, int $active = 1, int $suspended = 0, int $sessionActive = 1): string
{
    $site   = g2mlDebugTest_site();
    $marker = 'dbg319' . bin2hex(random_bytes(6));
    $email  = $marker . '@debug319.test';

    $statement = $db->prepare(
        "INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `role`, `isActive`, `isSuspended`) "
        . "VALUES ('[default]', ?, ?, 'x', 'Debug', 'Tester', ?, ?, ?)"
    );
    $statement->bind_param('sssii', $marker, $email, $databaseRole, $active, $suspended);

    if ($statement->execute() === false)
    {
        throw new RuntimeException('Could not create a user: ' . $statement->error);
    }

    $userUID = (int) $statement->insert_id;
    $statement->close();

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $statement = $db->prepare(
        'INSERT INTO `tblUserSessions` (`userUID`, `sessionToken`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), ?)'
    );
    $statement->bind_param('isi', $userUID, $tokenHash, $sessionActive);

    if ($statement->execute() === false)
    {
        throw new RuntimeException('Could not create a session: ' . $statement->error);
    }

    $statement->close();

    register_shutdown_function(function () use ($userUID): void
    {
        $connection = g2ml_test_connect(
            getenv('G2ML_TEST_DB_SOCKET'),
            g2ml_test_env('G2ML_TEST_DB_HOST', '127.0.0.1'),
            (int) g2ml_test_env('G2ML_TEST_DB_PORT', '3306'),
            g2ml_test_env('G2ML_TEST_DB_NAME', 'mwtools_Go2MyLink'),
            g2ml_test_env('G2ML_TEST_DB_USER', 'root'),
            g2ml_test_env('G2ML_TEST_DB_PASS', '')
        );

        if ($connection !== null)
        {
            $connection->query('DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
            $connection->close();
        }
    });

    $values = array(
        'user_uid'              => $userUID,
        'session_token'         => $token,
        'user_role'             => $sessionRole,
        'user_email'            => $email,
        'user_first_name'       => 'Debug',
        'user_org_handle'       => '[default]',
        '_g2ml_session_created' => time(),
    );

    $data = '';

    foreach ($values as $key => $value)
    {
        $data .= $key . '|' . serialize($value);
    }

    file_put_contents($site['sessions'] . '/sess_' . $marker, $data);

    // The marker is letters and digits only, so it is also a valid session ID.
    return $marker;
}

/**
 * Fail if a response holds anything that only an administrator may see.
 */
function g2mlDebugTest_assertNothingLeaked(array $response, string $label, bool $checkHeaders = true): void
{
    $everything = $response['body'];

    if ($checkHeaders)
    {
        $everything = $response['headers'] . "\n" . $response['body'];
    }

    foreach (array('g2ml-debug-panel', 'SECRET-DETAIL-319', 'SECRET319', 'Stack Trace:', 'Fatal error', 'X-Powered-By', 'PHP ' . PHP_VERSION, 'FROM tblSettings', '/g2ml_debug319_') as $forbidden)
    {
        assert_true(strpos($everything, $forbidden) === false, $label . ' showed "' . $forbidden . '"');
    }
}

test('debug output (#319): a visitor who is not signed in sees no panel on any host', function (): void
{
    foreach (array('alpha.go2my.link', 'beta.go2my.link', 'localhost', 'go2my.local', 'go2my.link') as $host)
    {
        $response = g2mlDebugTest_get('main', $host, '/index.php?route=about&debug=true');

        assert_contains('200', $response['status'], $host);
        assert_contains('</html>', $response['body'], $host);
        g2mlDebugTest_assertNothingLeaked($response, $host);
    }
});

test('debug output (#319): signed-in users and organisation admins see no panel', function () use ($db): void
{
    foreach (array('User', 'Admin') as $role)
    {
        $sessionID = g2mlDebugTest_signIn($db, $role, $role);

        foreach (array('alpha.go2my.link', 'go2my.link') as $host)
        {
            $response = g2mlDebugTest_get('main', $host, '/index.php?route=about&debug=true', $sessionID);

            assert_contains('200', $response['status']);
            g2mlDebugTest_assertNothingLeaked($response, $role . ' on ' . $host);
        }

        $response = g2mlDebugTest_get('admin', 'alpha.go2my.link', '/index.php?debug=true', $sessionID);
        g2mlDebugTest_assertNothingLeaked($response, $role . ' on the dashboard');
    }
});

test('debug output (#319): a signed-in global administrator sees the panel on test and local hosts', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin');

    foreach (array('alpha.go2my.link', 'beta.go2my.link', 'localhost') as $host)
    {
        $response = g2mlDebugTest_get('main', $host, '/index.php?route=about', $sessionID);

        assert_contains('g2ml-debug-panel', $response['body'], $host);
        assert_contains('PHP ' . PHP_VERSION, $response['body'], $host);
        assert_contains('tblSettings', $response['body'], $host);
    }

    $response = g2mlDebugTest_get('admin', 'alpha.go2my.link', '/index.php', $sessionID);
    assert_contains('g2ml-debug-panel', $response['body'], 'dashboard');
});

test('debug output (#319): on the live site a global administrator has to ask with ?debug=true', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin');

    $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about', $sessionID);
    assert_true(strpos($response['body'], 'g2ml-debug-panel') === false, 'Not asked for, so not shown');

    $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about&debug=true', $sessionID);
    assert_contains('g2ml-debug-panel', $response['body']);
});

test('debug output (#319): a demoted, switched-off, suspended or signed-out administrator sees nothing', function () use ($db): void
{
    $cases = array(
        'demoted'    => g2mlDebugTest_signIn($db, 'User', 'GlobalAdmin'),
        'inactive'   => g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin', 0),
        'suspended'  => g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin', 1, 1),
        'signed out' => g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin', 1, 0, 0),
    );

    foreach ($cases as $label => $sessionID)
    {
        $response = g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=about', $sessionID);
        g2mlDebugTest_assertNothingLeaked($response, $label);

        $response = g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=zzthrow', $sessionID);
        g2mlDebugTest_assertNothingLeaked($response, $label . ' (error page)');
    }
});

test('debug output (#319): errors show no details to others, and do to a global administrator', function () use ($db): void
{
    $userSession  = g2mlDebugTest_signIn($db, 'User', 'User');
    $adminSession = g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin');

    foreach (array('alpha.go2my.link', 'localhost', 'go2my.link') as $host)
    {
        foreach (array('', $userSession) as $sessionID)
        {
            foreach (array('zzthrow', 'zzfatal', 'zzwarn') as $page)
            {
                $response = g2mlDebugTest_get('main', $host, '/index.php?route=' . $page . '&debug=true', $sessionID);
                g2mlDebugTest_assertNothingLeaked($response, $page . ' on ' . $host);
            }
        }
    }

    $response = g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=zzthrow', $userSession);
    assert_contains('500', $response['status']);
    assert_contains('Something went wrong', $response['body']);

    $response = g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=zzthrow', $adminSession);
    assert_contains('Stack Trace:', $response['body']);
    assert_contains('SECRET-DETAIL-319', $response['body']);
});

test('debug output (#319): with the database down, even an administrator sees nothing', function () use ($db): void
{
    $sessionID = g2mlDebugTest_signIn($db, 'GlobalAdmin', 'GlobalAdmin');
    $site      = g2mlDebugTest_site();
    $original  = (string) file_get_contents($site['web'] . '/Go2My.Link/.auth/auth_creds.php');

    // Port 1 refuses the connection at once.
    g2mlDebugTest_credentials($site['web'], '127.0.0.1');
    $down = str_replace("define('DB_PORT', ", "define('DB_PORT', 1); // ", (string) file_get_contents($site['web'] . '/Go2My.Link/.auth/auth_creds.php'));
    file_put_contents($site['web'] . '/Go2My.Link/.auth/auth_creds.php', $down);

    try
    {
        foreach (array('about', 'zzthrow', 'zzfatal', 'zzwarn') as $page)
        {
            $response = g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=' . $page, $sessionID);
            g2mlDebugTest_assertNothingLeaked($response, $page . ' with no database');
        }
    }
    finally
    {
        file_put_contents($site['web'] . '/Go2My.Link/.auth/auth_creds.php', $original);
    }
});

test('debug output (#319): an error before the application has started shows nothing', function (): void
{
    $site     = g2mlDebugTest_site();
    $path     = $site['web'] . '/Go2My.Link/.auth/auth_creds.php';
    $original = (string) file_get_contents($path);

    $broken = array(
        'a warning'          => $original . "trigger_error('SECRET-DETAIL-319', E_USER_WARNING);\n",
        'an exception'       => $original . "throw new RuntimeException('SECRET-DETAIL-319');\n",
        'a file that breaks' => "<?php\nthis is not PHP SECRET319(;\n",
    );

    try
    {
        foreach ($broken as $label => $code)
        {
            file_put_contents($path, $code);

            foreach (array('/index.php?route=about', '/api/v1/index.php', '/api/create/index.php', '/api/consent/index.php', '/offline.php') as $address)
            {
                // api/v1 and api/consent load the shared credentials file.
                file_put_contents($site['web'] . '/_auth_keys/auth_creds.php', $code);

                $response = g2mlDebugTest_get('main', 'alpha.go2my.link', $address);
                // No PHP code can remove the X-Powered-By header when a file
                // does not parse; .htaccess does that on the real server, and
                // the built-in test server does not read it.
                g2mlDebugTest_assertNothingLeaked($response, $label . ' on ' . $address, $label !== 'a file that breaks');
            }
        }
    }
    finally
    {
        file_put_contents($path, $original);
        file_put_contents($site['web'] . '/_auth_keys/auth_creds.php', $original);
    }
});
