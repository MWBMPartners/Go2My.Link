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
 * 🧪 Unit tests — which pages must not be stored by the browser (#213)
 * ============================================================================
 *
 * The decision itself (web/_functions/cache_headers.php), and a check that no
 * file under web/ replaces it with a weaker Cache-Control header. What a real
 * request is sent is tested in
 * tests/integration/private_cache_headers_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#213)
 * ============================================================================
 */

declare(strict_types=1);

$g2mlCacheHeadersFile = dirname(__DIR__, 2) . '/web/_functions/cache_headers.php';

if (file_exists($g2mlCacheHeadersFile))
{
    require_once $g2mlCacheHeadersFile;
}

/**
 * The two headers every private page must carry.
 */
function g2mlCacheTest_private(): array
{
    return array('Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache');
}

test('cache headers (#213): a signed-in visitor is private on every component', function (): void
{
    foreach (array('A', 'B', 'C', 'Admin', '') as $component)
    {
        assert_same(g2mlCacheTest_private(), g2ml_privatePageCacheHeaders(true, $component), $component);
    }
});

test('cache headers (#213): the dashboard is private even when signed out', function (): void
{
    assert_same(g2mlCacheTest_private(), g2ml_privatePageCacheHeaders(false, 'Admin'));
});

test('cache headers (#213): a public page for a signed-out visitor is left alone', function (): void
{
    foreach (array('A', 'B', 'C', '') as $component)
    {
        assert_same(array(), g2ml_privatePageCacheHeaders(false, $component), $component);
    }

    foreach (array(array('route' => 'about'), array('redirect' => '/links'), array('redirect' => array('token=')), array('code' => 'abc')) as $query)
    {
        assert_same(array(), g2ml_privatePageCacheHeaders(false, 'A', $query), var_export($query, true));
    }
});

test('cache headers (#213): an address carrying a one-time token is private', function (): void
{
    $queries = array(
        array('route' => 'reset-password', 'token' => 'abc'),
        array('route' => 'verify-email', 'token' => ''),
        array('route' => 'invite', 'token' => array('abc')),
        array('route' => 'reset-password', 'forced' => '1'),
        array('route' => 'login', 'redirect' => '/invite?token=abc'),
    );

    foreach ($queries as $query)
    {
        assert_same(g2mlCacheTest_private(), g2ml_privatePageCacheHeaders(false, 'A', $query), var_export($query, true));
    }
});

test('cache headers (#213): no file under web/ sends a Cache-Control that lets a private answer be stored', function (): void
{
    // The only public values allowed: files anyone may keep, served by scripts
    // on the short-link domain that show nothing about the visitor.
    $publicFiles = array('G2My.Link/public_html/favicon.php', 'G2My.Link/public_html/robots.php');

    $web      = dirname(__DIR__, 2) . '/web';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS));
    $checked  = 0;

    foreach ($iterator as $file)
    {
        if ($file->getExtension() !== 'php')
        {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($web) + 1));
        $found    = preg_match_all('/header\(\s*[\'"]Cache-Control:\s*([^\'"]*)[\'"]/i', (string) file_get_contents($file->getPathname()), $matches);

        if ($found === false || $found === 0)
        {
            continue;
        }

        foreach ($matches[1] as $value)
        {
            if (in_array($relative, $publicFiles, true))
            {
                assert_same('public, max-age=86400', $value, $relative);
            }
            else
            {
                assert_same('no-store, private', $value, $relative);
            }

            $checked++;
        }
    }

    assert_true($checked >= 7, 'Expected to find the downloads, the API and the public files');
});

test('cache headers (#213): page_init sends them after session_start()', function (): void
{
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_includes/page_init.php');
    $start  = strpos($source, "    session_start();");
    $send   = strpos($source, "    g2ml_sendPrivatePageCacheHeaders();");

    assert_true($start !== false, 'session_start() not found');
    assert_true($send !== false, 'The headers are not sent');
    assert_true($send > $start, 'session_start() would replace the headers');
});
