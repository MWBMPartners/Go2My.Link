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
 * 🧪 Integration tests — links that do not open show a page (#290, D-006)
 * ============================================================================
 *
 * Makes real web requests to g2my.link's and lnks.page's own entry points and
 * reads the status, headers and page that come back.
 *
 * How: the web folder is copied to a temporary folder, a credentials file
 * pointing at the test database is written there, and PHP's built-in web
 * server is started on it. A second server is given a database port nothing
 * listens on, to see what visitors get when the database is down.
 *
 * WHAT THESE TESTS CANNOT DO
 *
 * The built-in server does not read .htaccess, so the Apache rules that turn
 * g2my.link/CODE into index.php?code=CODE are not exercised. The requests use
 * the ?code= form those rules produce. No browser draws the pages.
 *
 * Helper names start g2ml_dlp_ so they cannot clash with other test files.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#290)
 * ============================================================================
 */

declare(strict_types=1);

// $db and the connection settings come from tests/run_integration.php, which
// includes this file at its top level.
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

$GLOBALS['g2ml_dlp'] = [
    'settings' => [
        'socket'   => (string) $socket,
        'host'     => $host,
        'port'     => $port,
        'database' => $database,
        'user'     => $user,
        'password' => $password,
    ],
    'folders'   => [],
    'processes' => [],
    'servers'   => [],
];

/**
 * Run a prepared statement that returns no rows.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @param  array  $params  All bound as strings (NULL stays NULL).
 * @return void
 */
function g2ml_dlp_run(mysqli $db, string $sql, array $params = []): void
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if (count($params) > 0)
    {
        mysqli_stmt_bind_param($statement, str_repeat('s', count($params)), ...$params);
    }

    if (mysqli_stmt_execute($statement) === false)
    {
        throw new RuntimeException('Execute failed: ' . mysqli_stmt_error($statement) . ' — SQL: ' . $sql);
    }

    mysqli_stmt_close($statement);
}

/**
 * The default organisation's fallback address as stored now.
 *
 * @param  mysqli $db
 * @return string|null
 */
function g2ml_dlp_default_fallback(mysqli $db): ?string
{
    $result = mysqli_query($db, "SELECT `orgFallbackURL` FROM `tblOrganisations` WHERE `orgHandle` = '[default]'");
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return $row['orgFallbackURL'];
}

/**
 * Run migration 048 against the test database.
 *
 * @param  mysqli $db
 * @param  string $database
 * @return void
 */
function g2ml_dlp_migrate(mysqli $db, string $database): void
{
    $path = dirname(__DIR__, 2) . '/web/_sql/migrations/048_default_org_dead_link_page.sql';

    if (!is_file($path))
    {
        throw new RuntimeException('Migration 048 is missing');
    }

    $sql = str_replace('USE `mwtools_Go2MyLink`;', 'USE `' . $database . '`;', (string) file_get_contents($path));

    if (mysqli_multi_query($db, $sql) === false)
    {
        throw new RuntimeException('Migration 048 failed: ' . mysqli_error($db));
    }

    while (mysqli_more_results($db))
    {
        if (mysqli_next_result($db) === false)
        {
            throw new RuntimeException('Migration 048 failed: ' . mysqli_error($db));
        }
    }
}

/**
 * Create the organisations, domains, links and page the tests use. The default
 * organisation is put in the state an older install is in, then migrated.
 *
 * @param  mysqli $db
 * @return void
 */
function g2ml_dlp_seed(mysqli $db): void
{
    g2ml_dlp_clean($db);

    // Remember what the shared default organisation held, so that
    // g2ml_dlp_clean() can put it back for the test files that follow.
    $GLOBALS['g2ml_dlp']['defaultFallback'] = [g2ml_dlp_default_fallback($db)];

    g2ml_dlp_run($db, "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) VALUES ('free', 'Free') ON DUPLICATE KEY UPDATE `tierID` = `tierID`");

    g2ml_dlp_run(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) VALUES "
        . "('[default]', 'Default Test Org', 'https://go2my.link', 'free', 1), "
        . "('dlporg', 'Dead link org with fallback', 'https://example.com/dlp-fallback', 'free', 1), "
        . "('dlpnofb', 'Dead link org without fallback', NULL, 'free', 1) "
        . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `isActive` = 1"
    );

    g2ml_dlp_run(
        $db,
        "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`) VALUES "
        . "('[default]', 'g2my.link', 1, 1, 'verified'), "
        . "('dlporg', 'dlp-own.test', 1, 1, 'verified'), "
        . "('dlpnofb', 'dlp-unverified.test', 1, 1, 'pending') "
        . "ON DUPLICATE KEY UPDATE `isActive` = 1, `verificationStatus` = VALUES(`verificationStatus`)"
    );

    // code, owner, destination, active, start, end, alias
    $links = [
        ['dlpok',      '[default]', 'https://example.com/dlp-ok',     '1', null, null, null],
        ['dlpoff',     '[default]', 'https://example.com/dlp-off',    '0', null, null, null],
        ['dlpold',     '[default]', 'https://example.com/dlp-old',    '1', null, '2001-01-01 00:00:00', null],
        ['dlpsoon',    '[default]', 'https://example.com/dlp-soon',   '1', '2099-01-01 00:00:00', null, null],
        ['dlpempty',   '[default]', '',                               '1', null, null, null],
        ['dlphop1',    '[default]', '',                               '1', null, null, 'dlphop2'],
        ['dlphop2',    '[default]', '',                               '1', null, null, 'dlphop3'],
        ['dlphop3',    '[default]', '',                               '1', null, null, 'dlphop4'],
        ['dlphop4',    '[default]', 'https://example.com/dlp-hops',   '1', null, null, null],
        ['dlporgoff',  'dlporg',    'https://example.com/dlp-orgoff', '0', null, null, null],
        ['dlpnofboff', 'dlpnofb',   'https://example.com/dlp-nofb',   '0', null, null, null],
    ];

    foreach ($links as $link)
    {
        g2ml_dlp_run(
            $db,
            'INSERT INTO `tblShortURLs` (`shortCode`, `sharedShortCode`, `orgHandle`, `destinationURL`, `destinationType`, `isActive`, `startDate`, `endDate`, `redirectAlias`) '
            . "VALUES (?, ?, ?, ?, 'url', ?, ?, ?, ?)",
            [$link[0], $link[0], $link[1], $link[2], $link[3], $link[4], $link[5], $link[6]]
        );
    }

    g2ml_dlp_run($db, "INSERT INTO `tblLinksPages` (`slug`, `pageTitle`, `isPublished`, `isActive`) VALUES ('dlp-page', 'Dead link test page', 1, 1)");

    g2ml_dlp_migrate($db, $GLOBALS['g2ml_dlp']['settings']['database']);
}

/**
 * Remove the rows g2ml_dlp_seed() added, and put the default organisation's
 * fallback address back as it was. The shared default organisation and
 * g2my.link rows stay, as other test files use them. Every test calls this
 * from "finally", so a failing test cannot leave its changes behind.
 *
 * @param  mysqli $db
 * @return void
 */
function g2ml_dlp_clean(mysqli $db): void
{
    // Held inside an array, because the address itself may be NULL.
    if (isset($GLOBALS['g2ml_dlp']['defaultFallback']))
    {
        g2ml_dlp_run(
            $db,
            "UPDATE `tblOrganisations` SET `orgFallbackURL` = ? WHERE `orgHandle` = '[default]'",
            $GLOBALS['g2ml_dlp']['defaultFallback']
        );
        unset($GLOBALS['g2ml_dlp']['defaultFallback']);
    }

    g2ml_dlp_run($db, "DELETE FROM `tblShortURLs` WHERE `shortCode` LIKE 'dlp%'");
    g2ml_dlp_run($db, "DELETE FROM `tblLinksPages` WHERE `slug` = 'dlp-page'");
    g2ml_dlp_run($db, "DELETE FROM `tblOrgShortDomains` WHERE `shortDomain` LIKE 'dlp-%'");
    g2ml_dlp_run($db, "DELETE FROM `tblOrganisations` WHERE `orgHandle` IN ('dlporg', 'dlpnofb')");
}

/**
 * Copy a folder and everything in it.
 *
 * @param  string $from
 * @param  string $to
 * @return void
 */
function g2ml_dlp_copy(string $from, string $to): void
{
    if (!is_dir($to) && !mkdir($to, 0700, true))
    {
        throw new RuntimeException('Cannot create ' . $to);
    }

    foreach (new FilesystemIterator($from, FilesystemIterator::SKIP_DOTS) as $entry)
    {
        $target = $to . '/' . $entry->getFilename();

        if ($entry->isDir())
        {
            g2ml_dlp_copy($entry->getPathname(), $target);
        }
        else
        {
            copy($entry->getPathname(), $target);
        }
    }
}

/**
 * Start (once) a web server for one site, and return its address.
 *
 * @param  string $component     'G2My.Link' or 'Lnks.page'.
 * @param  bool   $databaseDown  True: the credentials point where nothing listens.
 * @return string                host:port
 */
function g2ml_dlp_server(string $component, bool $databaseDown = false): string
{
    $name = $component . '|' . var_export($databaseDown, true);

    if (isset($GLOBALS['g2ml_dlp']['servers'][$name]))
    {
        return $GLOBALS['g2ml_dlp']['servers'][$name];
    }

    $settings = $GLOBALS['g2ml_dlp']['settings'];
    $root     = sys_get_temp_dir() . '/g2ml-dlp-' . bin2hex(random_bytes(6));
    $web      = dirname(__DIR__, 2) . '/web';

    $GLOBALS['g2ml_dlp']['folders'][] = $root;

    foreach (['_functions', '_includes', 'G2My.Link/_functions', 'G2My.Link/public_html', 'Lnks.page/_functions', 'Lnks.page/public_html'] as $folder)
    {
        g2ml_dlp_copy($web . '/' . $folder, $root . '/web/' . $folder);
    }

    $dbHost  = $settings['host'];
    $dbPort  = $settings['port'];
    $options = [];

    if ($settings['socket'] !== '')
    {
        $dbHost  = 'localhost';
        $options = ['-d', 'mysqli.default_socket=' . $settings['socket']];
    }

    if ($databaseDown)
    {
        $dbHost = '127.0.0.1';
        $dbPort = 1;
    }

    $constants = [
        'DB_HOST'                  => $dbHost,
        'DB_PORT'                  => $dbPort,
        'DB_USER'                  => $settings['user'],
        'DB_PASS'                  => $settings['password'],
        'DB_NAME'                  => $settings['database'],
        'DB_CHARSET'               => 'utf8mb4',
        'ENCRYPTION_SALT'          => str_repeat('a1b2c3d4', 8),
        'ENCRYPTION_KEY_SECONDARY' => str_repeat('9f8e7d6c', 8),
    ];

    $credentials = "<?php\n";

    foreach ($constants as $constant => $value)
    {
        $credentials .= 'define(' . var_export($constant, true) . ', ' . var_export($value, true) . ");\n";
    }

    mkdir($root . '/web/' . $component . '/.auth', 0700, true);
    file_put_contents($root . '/web/' . $component . '/.auth/auth_creds.php', $credentials);

    // Ask the system for a free port, then hand it to the web server.
    $probe   = stream_socket_server('tcp://127.0.0.1:0');
    $address = (string) stream_socket_get_name($probe, false);
    fclose($probe);

    $command = array_merge(
        [PHP_BINARY],
        $options,
        ['-S', $address, '-t', $root . '/web/' . $component . '/public_html']
    );
    $pipes   = [];
    $process = proc_open($command, [1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']], $pipes);

    if (!is_resource($process))
    {
        throw new RuntimeException('Could not start the web server.');
    }

    $GLOBALS['g2ml_dlp']['processes'][] = $process;

    for ($attempt = 0; $attempt < 100; $attempt++)
    {
        $connection = @fsockopen('tcp://' . $address);

        if ($connection !== false)
        {
            fclose($connection);
            $GLOBALS['g2ml_dlp']['servers'][$name] = $address;

            return $address;
        }

        usleep(100000);
    }

    throw new RuntimeException('The web server did not start: ' . file_get_contents($root . '/server.log'));
}

/**
 * GET one address without following redirects.
 *
 * @param  string $server    From g2ml_dlp_server().
 * @param  string $hostName  The Host header.
 * @param  string $path      Path and query.
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function g2ml_dlp_get(string $server, string $hostName, string $path): array
{
    $context = stream_context_create([
        'http' => [
            'header'          => 'Host: ' . $hostName . "\r\n",
            'follow_location' => 0,
            'ignore_errors'   => true,
            'timeout'         => 30,
        ],
    ]);

    $body = file_get_contents('http://' . $server . $path, false, $context);

    if ($body === false)
    {
        throw new RuntimeException('The request failed: ' . $path);
    }

    $lines   = http_get_last_response_headers();
    $status  = 0;
    $headers = [];

    foreach ($lines as $line)
    {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $matches) === 1)
        {
            $status = (int) $matches[1];
        }
        elseif (str_contains($line, ':'))
        {
            [$headerName, $headerValue] = explode(':', $line, 2);
            $headers[strtolower($headerName)] = trim($headerValue);
        }
    }

    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

/**
 * Check one answer is an explanation page and not a redirect.
 *
 * @param  array  $answer  From g2ml_dlp_get().
 * @param  int    $status  The expected HTTP status.
 * @param  string $title   The expected heading.
 * @return void
 */
function g2ml_dlp_assert_page(array $answer, int $status, string $title): void
{
    assert_same($status, $answer['status'], $title . ': status');
    assert_false(isset($answer['headers']['location']), $title . ': must not redirect');
    assert_contains('<h1 class="h3 mb-3">' . $title . '</h1>', $answer['body'], $title . ': heading');
    assert_contains('href="https://go2my.link"', $answer['body'], $title . ': homepage link');
    assert_contains('Go to the ', $answer['body'], $title . ': homepage link words');
    assert_contains('<meta name="robots" content="noindex, nofollow">', $answer['body'], $title . ': noindex');
    assert_false(str_contains($answer['body'], 'http-equiv="refresh"'), $title . ': no timed move');
    assert_false(str_contains($answer['body'], 'window.location'), $title . ': no scripted move');
    assert_same(0, preg_match('/>\s*(shortlink|error)\.[a-z0-9_]+/', $answer['body']), $title . ': a key name is showing');
    assert_same(0, preg_match('/(Warning|Notice|Deprecated|Fatal error)<\/b>:/', $answer['body']), $title . ': PHP message on the page');
}

register_shutdown_function(function (): void
{
    foreach ($GLOBALS['g2ml_dlp']['processes'] as $process)
    {
        proc_terminate($process);
        proc_close($process);
    }

    foreach ($GLOBALS['g2ml_dlp']['folders'] as $folder)
    {
        if (!is_dir($folder))
        {
            continue;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry)
        {
            if ($entry->isDir())
            {
                rmdir($entry->getPathname());
            }
            else
            {
                unlink($entry->getPathname());
            }
        }

        rmdir($folder);
    }
});

test('#290: migration 048 clears the seeded address, keeps a chosen one, and is safe to repeat', function () use ($db, $database): void
{
    $set    = "UPDATE `tblOrganisations` SET `orgFallbackURL` = ? WHERE `orgHandle` = '[default]'";

    try
    {
        g2ml_dlp_seed($db);

        foreach (['https://go2my.link', 'https://go2my.link/'] as $seeded)
        {
            g2ml_dlp_run($db, $set, [$seeded]);
            g2ml_dlp_migrate($db, $database);
            assert_same(null, g2ml_dlp_default_fallback($db), 'Cleared: ' . $seeded);
            g2ml_dlp_migrate($db, $database);
            assert_same(null, g2ml_dlp_default_fallback($db), 'Still clear after a second run');
        }

        foreach (['https://example.com/chosen', 'https://go2my.link/help'] as $chosen)
        {
            g2ml_dlp_run($db, $set, [$chosen]);
            g2ml_dlp_migrate($db, $database);
            assert_same($chosen, g2ml_dlp_default_fallback($db), 'An owner\'s choice is kept');
        }

        $result = mysqli_query($db, "SELECT `orgFallbackURL` FROM `tblOrganisations` WHERE `orgHandle` = 'dlporg'");
        assert_same('https://example.com/dlp-fallback', mysqli_fetch_assoc($result)['orgFallbackURL'], 'Other organisations are untouched');
        mysqli_free_result($result);
    }
    finally
    {
        g2ml_dlp_clean($db);
    }
});

test('#290: each kind of link that does not open shows its own page with the right status', function () use ($db): void
{
    try
    {
        g2ml_dlp_seed($db);

        $server = g2ml_dlp_server('G2My.Link');

        $cases = [
            'dlpnosuchcode' => [404, 'Link Not Found'],
            'dlpoff'        => [410, 'Link Switched Off'],
            'dlpold'        => [410, 'Link Expired'],
            'dlpsoon'       => [404, 'Link Not Yet Active'],
            'dlpempty'      => [404, 'Link Not Configured'],
            'dlphop1'       => [404, 'Link Cannot Be Followed'],
            'dlpnofboff'    => [410, 'Link Switched Off'],
        ];

        foreach ($cases as $code => $expected)
        {
            $answer = g2ml_dlp_get($server, 'g2my.link', '/?code=' . $code);

            g2ml_dlp_assert_page($answer, $expected[0], $expected[1]);
            assert_contains('no-store', $answer['headers']['cache-control'] ?? '', $code . ': the answer must not be cached');
        }

        g2ml_dlp_assert_page(g2ml_dlp_get($server, 'dlp-unverified.test', '/?code=dlpok'), 404, 'Domain Not Configured');
        g2ml_dlp_assert_page(g2ml_dlp_get($server, 'g2my.link', '/?http_error=404'), 404, 'Link Not Found');
    }
    finally
    {
        g2ml_dlp_clean($db);
    }
});

test('#290: an organisation that set its own fallback address still sends visitors there', function () use ($db): void
{
    try
    {
        g2ml_dlp_seed($db);

        $server = g2ml_dlp_server('G2My.Link');

        foreach ([['g2my.link', 'dlporgoff'], ['dlp-own.test', 'dlpnosuchcode']] as $case)
        {
            $answer = g2ml_dlp_get($server, $case[0], '/?code=' . $case[1]);

            assert_same(302, $answer['status'], $case[1] . ' on ' . $case[0]);
            assert_same('https://example.com/dlp-fallback', $answer['headers']['location'] ?? '', 'The organisation\'s own address');
        }
    }
    finally
    {
        g2ml_dlp_clean($db);
    }
});

test('#290: a slash on the end of a code or a LinksPage name makes no difference', function () use ($db): void
{
    try
    {
        g2ml_dlp_seed($db);

        $server = g2ml_dlp_server('G2My.Link');

        foreach (['dlpok', 'dlpok/', 'dlpok//'] as $code)
        {
            $answer = g2ml_dlp_get($server, 'g2my.link', '/?code=' . $code);

            assert_same(302, $answer['status'], $code);
            assert_same('https://example.com/dlp-ok', $answer['headers']['location'] ?? '', $code);
        }

        $pages = g2ml_dlp_server('Lnks.page');

        foreach (['dlp-page', 'dlp-page/'] as $slug)
        {
            $answer = g2ml_dlp_get($pages, 'lnks.page', '/?slug=' . $slug);

            assert_same(200, $answer['status'], $slug);
            assert_contains('Dead link test page', $answer['body'], $slug);
        }

        assert_same(404, g2ml_dlp_get($pages, 'lnks.page', '/?slug=dlp-none/')['status'], 'A missing page is still missing');
    }
    finally
    {
        g2ml_dlp_clean($db);
    }
});

test('#290: the page shows the translated words from the database', function () use ($db): void
{
    try
    {
        g2ml_dlp_seed($db);

        $server = g2ml_dlp_server('G2My.Link');
        $select = "SELECT `translationValue` FROM `tblTranslations` WHERE `localeCode` = 'en-GB' AND `translationKey` = 'shortlink.expired_title'";
        $update = "UPDATE `tblTranslations` SET `translationValue` = ? WHERE `localeCode` = 'en-GB' AND `translationKey` = 'shortlink.expired_title'";

        $result = mysqli_query($db, $select);
        $row    = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        assert_true($row !== null, 'Seed 081 has been imported');

        g2ml_dlp_run($db, $update, ['Words from the database']);
        $answer = g2ml_dlp_get($server, 'g2my.link', '/?code=dlpold');
        g2ml_dlp_run($db, $update, [$row['translationValue']]);

        g2ml_dlp_assert_page($answer, 410, 'Words from the database');
    }
    finally
    {
        g2ml_dlp_clean($db);
    }
});

test('#290: the two fallback settings are described as they now behave', function () use ($db): void
{
    $expected = [
        'redirect.fallback_url'   => 'Where a visit to a short domain with no code goes',
        'redirect.fallback_delay' => 'Seconds counted down on the destination safety check page',
    ];

    foreach ($expected as $setting => $start)
    {
        $result = mysqli_query($db, "SELECT `settingDescription` FROM `tblSettings` WHERE `settingID` = '" . $setting . "'");
        $rows   = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);

        assert_true(count($rows) > 0, $setting . ' exists');

        foreach ($rows as $row)
        {
            assert_true(str_starts_with((string) $row['settingDescription'], $start), $setting . ': ' . $row['settingDescription']);
        }
    }
});

test('#290: with the database down, g2my.link answers 503 with Retry-After, in words', function (): void
{
    $server = g2ml_dlp_server('G2My.Link', true);
    $answer = g2ml_dlp_get($server, 'g2my.link', '/?code=dlpok');

    g2ml_dlp_assert_page($answer, 503, 'Service Temporarily Unavailable');
    assert_same('120', $answer['headers']['retry-after'] ?? '', 'Retry-After says when to try again');
});
