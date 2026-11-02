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
 * 🧪 Unit tests — links to main-site pages from the shared layout (#279)
 * ============================================================================
 *
 * The page layout (nav.php, footer.php, the cookie banner and the main-site
 * error pages) is shared by go2my.link and admin.go2my.link. Pages such as
 * /about and /login exist only on the main site, so a plain "/about" link
 * worked there but gave "page not found" on the dashboard, including the
 * Log Out link.
 *
 * Part 1 tests g2ml_mainSiteURLForComponent(), the function that decides
 * what such a link looks like. It lives in web/_functions/main_site_url.php
 * and takes the component as an argument, so it runs here without loading
 * page_init.php (which needs a database and a session). The wrapper that
 * supplies the current component, g2ml_mainSiteURL() in page_init.php, is
 * not run here; part 2 only checks its source text.
 *
 * Part 2 reads the layout files as text and fails if one of the listed
 * links goes back to a plain relative address. It cannot show that the
 * right address comes out on a real page; it only stops the plain link
 * from coming back unnoticed.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Dashboard fixes (#279)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/main_site_url.php';

// ============================================================================
// Part 1 — the decision
// ============================================================================

test('main site url: the main site (A) keeps a relative link', function (): void
{
    assert_same('/about', g2ml_mainSiteURLForComponent('/about', 'A'));
    assert_same('/legal/terms', g2ml_mainSiteURLForComponent('/legal/terms', 'A'));
    assert_same('/', g2ml_mainSiteURLForComponent('/', 'A'));
});

test('main site url: the dashboard (Admin) gets the full main-site address', function (): void
{
    assert_same('https://go2my.link/about', g2ml_mainSiteURLForComponent('/about', 'Admin'));
    assert_same('https://go2my.link/logout', g2ml_mainSiteURLForComponent('/logout', 'Admin'));
    assert_same('https://go2my.link/legal/acceptable-use', g2ml_mainSiteURLForComponent('/legal/acceptable-use', 'Admin'));
});

test('main site url: components B and C also get the full address, because they have no such pages', function (): void
{
    assert_same('https://go2my.link/contact', g2ml_mainSiteURLForComponent('/contact', 'B'));
    assert_same('https://go2my.link/contact', g2ml_mainSiteURLForComponent('/contact', 'C'));
});

test('main site url: the path is passed through untouched, including a query string', function (): void
{
    assert_same('https://go2my.link/login?next=%2Forg', g2ml_mainSiteURLForComponent('/login?next=%2Forg', 'Admin'));
    assert_same('/login?next=%2Forg', g2ml_mainSiteURLForComponent('/login?next=%2Forg', 'A'));
});

// ============================================================================
// Part 2 — the layout files must not link to these pages with a plain path
// ============================================================================

/**
 * Read a repository file as text, failing the test with a clear message if
 * it cannot be read.
 *
 * @param  string $relativePath  Path from the repository root, using "/".
 * @return string
 */
function _g2mlMainSiteUrlTestRead(string $relativePath): string
{
    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    assert_true(is_file($fullPath), 'Expected file not found: ' . $relativePath);

    $text = file_get_contents($fullPath);

    assert_true($text !== false, 'Could not read the file: ' . $relativePath);

    return $text;
}

test('page_init.php defines g2ml_mainSiteURL() and hands the current component to the tested function', function (): void
{
    $source = _g2mlMainSiteUrlTestRead('web/_includes/page_init.php');

    assert_contains('function g2ml_mainSiteURL(string $path): string', $source);
    assert_contains('return g2ml_mainSiteURLForComponent($path, g2ml_getComponent());', $source);
    assert_contains("'main_site_url.php'", $source, 'page_init.php must load web/_functions/main_site_url.php');
});

test('nav.php sends main-site pages through g2ml_mainSiteURL(), and keeps Home and the logo at "/"', function (): void
{
    $source = _g2mlMainSiteUrlTestRead('web/_includes/nav.php');

    $mainSitePaths = array('/about', '/features', '/pricing', '/help', '/logout', '/login', '/register');

    foreach ($mainSitePaths as $path)
    {
        assert_false(
            strpos($source, 'href="' . $path . '"') !== false,
            'nav.php links to ' . $path . ' with a plain path; use g2ml_mainSiteURL(\'' . $path . '\')'
        );
        assert_contains("g2ml_mainSiteURL('" . $path . "')", $source, 'nav.php should call g2ml_mainSiteURL for ' . $path);
    }

    // The brand logo and the "Home" link are the dashboard's own home page
    // there, so they must stay as "/".
    assert_same(2, substr_count($source, 'href="/"'), 'nav.php should have exactly two links to "/": the logo and Home');
});

test('footer.php sends main-site pages through g2ml_mainSiteURL()', function (): void
{
    $source = _g2mlMainSiteUrlTestRead('web/_includes/footer.php');

    $mainSitePaths = array(
        '/about', '/features', '/pricing', '/help', '/contact',
        '/legal/terms', '/legal/privacy', '/legal/cookies', '/legal/acceptable-use', '/legal/copyright',
    );

    foreach ($mainSitePaths as $path)
    {
        assert_false(
            strpos($source, 'href="' . $path . '"') !== false,
            'footer.php links to ' . $path . ' with a plain path; use g2ml_mainSiteURL(\'' . $path . '\')'
        );
        assert_contains("g2ml_mainSiteURL('" . $path . "')", $source, 'footer.php should call g2ml_mainSiteURL for ' . $path);
    }
});

test('the cookie banner sends its Cookie Policy link through g2ml_mainSiteURL()', function (): void
{
    $source = _g2mlMainSiteUrlTestRead('web/_includes/cookie_banner.php');

    assert_false(strpos($source, 'href="/legal/cookies"') !== false, 'cookie_banner.php links to /legal/cookies with a plain path');
    assert_contains("g2ml_mainSiteURL('/legal/cookies')", $source);
});

test('the main-site error pages send their Contact and Log In buttons through g2ml_mainSiteURL()', function (): void
{
    $pages = array(
        '400' => '/contact',
        '403' => '/login',
        '404' => '/contact',
        '500' => '/contact',
    );

    foreach ($pages as $code => $path)
    {
        $relativePath = 'web/Go2My.Link/public_html/pages/errors/' . $code . '/index.php';
        $source       = _g2mlMainSiteUrlTestRead($relativePath);

        assert_false(
            strpos($source, 'href="' . $path . '"') !== false,
            $relativePath . ' links to ' . $path . ' with a plain path'
        );
        assert_contains("g2ml_mainSiteURL('" . $path . "')", $source, $relativePath . ' should call g2ml_mainSiteURL');
    }
});
