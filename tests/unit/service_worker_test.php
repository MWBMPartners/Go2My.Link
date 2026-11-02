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
 * 🧪 Check — service workers cache static files only (#283)
 * ============================================================================
 *
 * The old workers saved every page they fetched, including sign-in pages,
 * one-time links with their token in the address and signed-in dashboard
 * pages, and precached "/", whose cookie banner issued a fresh form token.
 * This check reads both workers (main site and dashboard) and fails if:
 *   - the precache list holds anything but listed static files and /offline,
 *     or names a file that does not exist in that web root;
 *   - the worker stores a response anywhere except the one static-file branch,
 *     or loses one of its guards: GET only, same site only, no query string,
 *     listed files only, /offline as the offline page, old caches deleted,
 *     fresh copies asked of the server rather than the browser's cache;
 *   - pages (navigation requests) are not sent to the network first;
 *   - the cache name is still the old "-v1" one, which held stored pages;
 *   - either /offline entry point loads the other site's web root instead of
 *     the shared page in web/_includes/ (the deploy copies the two web roots
 *     on different schedules), or the shared page shows a key name when a
 *     translation is missing (run in a child process with a stand-in __()).
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads the JavaScript as text; it does not run it in a browser. The
 * offline page check needs PHP's shell_exec(), which some hosts switch off.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The quoted entries of a worker's STATIC_FILES list.
 *
 * @param  string $source
 * @return array<int, string>
 */
function _g2mlServiceWorkerTestStaticFiles(string $source): array
{
    $block = array();

    if (preg_match('/const STATIC_FILES = \[(.*?)\];/s', $source, $block) !== 1)
    {
        return array();
    }

    $entries = array();
    preg_match_all("/'([^']+)'/", $block[1], $entries);

    return $entries[1];
}

test('service workers: only static files and /offline are precached, and each exists (#283)', function (): void
{
    $roots = array('web/Go2My.Link/public_html', 'web/Go2My.Link/_admin/public_html');

    foreach ($roots as $root)
    {
        $folder = dirname(__DIR__, 2) . '/' . $root;
        $source = (string) file_get_contents($folder . '/sw.js');
        $files  = _g2mlServiceWorkerTestStaticFiles($source);

        assert_true(count($files) > 0, $root . '/sw.js has no STATIC_FILES list');

        foreach ($files as $file)
        {
            if ($file === '/offline')
            {
                assert_true(is_file($folder . '/offline.php'), $root . ' precaches /offline but has no offline.php');
                continue;
            }

            assert_true(preg_match('#^/(css|js|icons|img)/[^?]+$|^/manifest\.json$#', $file) === 1, $root . '/sw.js precaches something that is not a static file: ' . $file);
            assert_true(is_file($folder . $file), $root . '/sw.js precaches a file that does not exist: ' . $file);
        }
    }
});

test('service workers: pages go to the network, only static files are stored, and the old cache is retired (#283)', function (): void
{
    $roots = array('web/Go2My.Link/public_html', 'web/Go2My.Link/_admin/public_html');

    foreach ($roots as $root)
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $root . '/sw.js');

        assert_same(1, substr_count($source, '.put('), $root . '/sw.js should store responses in exactly one place (the static-file branch)');
        assert_same(1, substr_count($source, '.addAll('), $root . '/sw.js should precache in exactly one place (install)');
        assert_same(0, substr_count($source, '.add('), $root . '/sw.js should not add single responses to the cache');
        assert_true(strpos($source, "request.mode === 'navigate'") !== false, $root . '/sw.js should handle pages separately');

        $guards = array(
            "if (request.method !== 'GET')"                                  => 'only GET requests are handled',
            'if (url.origin !== self.location.origin)'                       => 'other sites are left alone',
            "if (url.search !== '' || STATIC_FILES.indexOf(url.pathname) === -1)" => 'query strings and unlisted files are left alone',
            "const OFFLINE_PAGE = '/offline';"                               => '/offline is the offline page',
            'return caches.delete(name);'                                    => 'old caches are deleted on activation',
            "new Request(path, { cache: 'reload' })"                         => 'install asks the server, not the browser cache',
            "fetch(request, { cache: 'no-cache' })"                          => 'the background refresh asks the server',
        );

        foreach ($guards as $fragment => $meaning)
        {
            assert_true(strpos($source, $fragment) !== false, $root . '/sw.js has lost a guard (' . $meaning . '): ' . $fragment);
        }

        assert_true(in_array('/offline', _g2mlServiceWorkerTestStaticFiles($source), true), $root . '/sw.js should precache /offline');
        assert_false(preg_match("/const CACHE_NAME = '[^']*-v1';/", $source) === 1, $root . '/sw.js still uses the old v1 cache, which held stored pages');
    }
});

test('offline page: each /offline sets up its own site and prints the shared page (#283)', function (): void
{
    $entryPoints = array(
        'web/Go2My.Link/public_html/offline.php'        => "define('G2ML_COMPONENT',        'A');",
        'web/Go2My.Link/_admin/public_html/offline.php' => "define('G2ML_COMPONENT',        'Admin');",
    );

    foreach ($entryPoints as $file => $componentLine)
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        assert_true(strpos($source, $componentLine) !== false, $file . ' should set its own component');
        assert_true(strpos($source, "'_includes' . DIRECTORY_SEPARATOR . 'offline_page.php'") !== false, $file . ' should print the shared offline page');
        assert_false(strpos($source, "'public_html' . DIRECTORY_SEPARATOR . 'offline.php'") !== false, $file . ' must not load the other web root (the two deploy on different schedules)');
    }
});

/**
 * Print web/_includes/offline_page.php in a clean child process, with a
 * stand-in __() that either translates (adds "T:" in front) or hands back
 * the key, as the real one does when a translation is missing.
 *
 * @param  bool $translationsMissing
 * @return string|null  The page, or null if the child failed.
 */
function _g2mlServiceWorkerTestOfflinePage(bool $translationsMissing): ?string
{
    $script  = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= 'function __(string $key, array $replacements = []): string' . "\n" . '{' . "\n";

    if ($translationsMissing === true)
    {
        $script .= '    return $key;' . "\n";
    }
    else
    {
        $script .= '    return \'T:\' . $key;' . "\n";
    }

    $script .= '}' . "\n";
    $script .= 'ob_start();' . "\n";
    $script .= 'require ' . var_export(dirname(__DIR__, 2) . '/web/_includes/offline_page.php', true) . ';' . "\n";
    $script .= 'echo ob_get_clean();' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_offline_');

    if ($tempFile === false)
    {
        return null;
    }

    file_put_contents($tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>/dev/null');
    unlink($tempFile);

    if (!is_string($output) || strpos($output, '<h1>') === false)
    {
        return null;
    }

    return $output;
}

test('offline page: a missing translation shows English, never a key name (#283)', function (): void
{
    $missing = _g2mlServiceWorkerTestOfflinePage(true);

    assert_true($missing !== null, 'offline_page.php did not run in the child process');
    assert_contains('<h1>You are offline</h1>', (string) $missing);
    assert_contains('This page needs an internet connection. Check your connection, then try again.', (string) $missing);
    assert_contains('>Try again</a>', (string) $missing);
    assert_false(strpos((string) $missing, 'offline.title') !== false, 'The page shows a key name');

    $translated = _g2mlServiceWorkerTestOfflinePage(false);

    assert_true($translated !== null, 'offline_page.php did not run in the child process');
    assert_contains('<h1>T:offline.title</h1>', (string) $translated);
});
