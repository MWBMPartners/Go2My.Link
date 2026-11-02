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
 * 🧪 Unit tests — the dashboard's own copies of the site's files (#279)
 * ============================================================================
 *
 * The dashboard (admin.go2my.link) and the main site (go2my.link) are two
 * separate web roots. The shared page layout asks for /css/style.css,
 * /js/theme.js, /js/app.js and /js/cookie-consent.js on whichever site it is
 * running on, but those four files used to exist only in the main site's
 * web root. On the dashboard they were "not found", so the pages had no
 * brand styles, the theme switch did nothing and the cookie banner did
 * nothing.
 *
 * The fix is a copy of each file in the dashboard's web root (the project
 * already keeps per-root copies of img/ and icons/). A copy can fall behind
 * when someone edits only one of the two, so the first group of tests below
 * fails, naming the file, if a copy differs from the original.
 *
 * The other groups check the rest of the same fix: the dashboard's consent
 * endpoint reaches the main site's one, the dashboard's front controller
 * sends the four error routes to the main site's error pages, its .htaccess
 * has the error pages and the "do not index" header, and robots.txt asks
 * crawlers to stay away.
 *
 * These tests read files only. They cannot show how Apache behaves with the
 * .htaccess file or what a browser does with the pages.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Dashboard fixes (#279)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * The folder holding the main site's web root.
 *
 * @return string
 */
function _g2mlDashboardAssetsTestMainRoot(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'Go2My.Link' . DIRECTORY_SEPARATOR . 'public_html';
}

/**
 * The folder holding the dashboard's web root.
 *
 * @return string
 */
function _g2mlDashboardAssetsTestAdminRoot(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'Go2My.Link' . DIRECTORY_SEPARATOR . '_admin' . DIRECTORY_SEPARATOR . 'public_html';
}

/**
 * Read a file as text, failing the test with a clear message if it cannot
 * be read.
 *
 * @param  string $fullPath  Absolute path.
 * @param  string $label     Name to show in a failure message.
 * @return string
 */
function _g2mlDashboardAssetsTestRead(string $fullPath, string $label): string
{
    assert_true(is_file($fullPath), 'Expected file not found: ' . $label);

    $text = file_get_contents($fullPath);

    assert_true($text !== false, 'Could not read the file: ' . $label);

    return $text;
}

// ============================================================================
// 📋 The four copied files must match the main site's byte for byte
// ============================================================================

$g2mlDashboardAssetsTestCopiedFiles = array(
    'css/style.css',
    'js/theme.js',
    'js/app.js',
    'js/cookie-consent.js',
);

foreach ($g2mlDashboardAssetsTestCopiedFiles as $g2mlDashboardAssetsTestCopiedFile)
{
    test('dashboard copy of ' . $g2mlDashboardAssetsTestCopiedFile . ' matches the main-site file byte for byte', function () use ($g2mlDashboardAssetsTestCopiedFile): void
    {
        $original = _g2mlDashboardAssetsTestMainRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $g2mlDashboardAssetsTestCopiedFile);
        $copy     = _g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $g2mlDashboardAssetsTestCopiedFile);

        $originalLabel = 'web/Go2My.Link/public_html/' . $g2mlDashboardAssetsTestCopiedFile;
        $copyLabel     = 'web/Go2My.Link/_admin/public_html/' . $g2mlDashboardAssetsTestCopiedFile;

        $originalText = _g2mlDashboardAssetsTestRead($original, $originalLabel);
        $copyText     = _g2mlDashboardAssetsTestRead($copy, $copyLabel);

        assert_true(
            $originalText === $copyText,
            $copyLabel . ' is different from ' . $originalLabel . '. Edit the main-site file, then copy it over the dashboard one.'
        );
    });
}

// ============================================================================
// 🍪 The dashboard's consent endpoint must reach the main site's one
// ============================================================================

test('dashboard consent endpoint: the main-site file it requires exists at the depth it counts', function (): void
{
    $adminEndpoint = _g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'index.php';
    $source        = _g2mlDashboardAssetsTestRead($adminEndpoint, 'web/Go2My.Link/_admin/public_html/api/consent/index.php');

    // The endpoint counts four folders up from its own folder. Work out
    // where that lands from the real folder it sits in, so the count is
    // checked and not just the text.
    assert_contains('dirname(__DIR__, 4)', $source);

    $adminEndpointFolder = dirname($adminEndpoint);
    $landing             = dirname($adminEndpointFolder, 4)
        . DIRECTORY_SEPARATOR . 'public_html'
        . DIRECTORY_SEPARATOR . 'api'
        . DIRECTORY_SEPARATOR . 'consent'
        . DIRECTORY_SEPARATOR . 'index.php';

    $mainEndpoint = _g2mlDashboardAssetsTestMainRoot() . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'index.php';

    assert_true(is_file($landing), 'Four folders up from the dashboard endpoint does not reach the main site\'s consent endpoint: ' . $landing);
    assert_same(realpath($mainEndpoint), realpath($landing));
});

test('dashboard consent endpoint: it defines nothing before the main-site file does', function (): void
{
    $adminEndpoint = _g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'index.php';
    $source        = _g2mlDashboardAssetsTestRead($adminEndpoint, 'web/Go2My.Link/_admin/public_html/api/consent/index.php');

    // The main-site file calls define() for G2ML_COMPONENT, G2ML_ROOT and so
    // on. If the dashboard file defined them first, PHP would warn that the
    // constant was already defined.
    assert_false(strpos($source, "define('G2ML_") !== false, 'The dashboard consent endpoint must not define G2ML_ constants');
});

// ============================================================================
// 🚦 The dashboard front controller shows the main site's error pages
// ============================================================================

test('dashboard front controller: the four error routes show the main site\'s error pages', function (): void
{
    $source = _g2mlDashboardAssetsTestRead(
        _g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . 'index.php',
        'web/Go2My.Link/_admin/public_html/index.php'
    );

    // Without this list every ErrorDocument line would quietly become a 404.
    assert_contains("\$errorRoutes = ['errors/400', 'errors/403', 'errors/404', 'errors/500'];", $source);
    assert_contains("if (in_array(\$cleanRoute, \$errorRoutes, true))", $source);
    assert_contains("\$errorCode = substr(\$cleanRoute, strlen('errors/'));", $source);
    assert_contains("\$errorPageFile = G2ML_ROOT\n            . DIRECTORY_SEPARATOR . 'Go2My.Link'\n            . DIRECTORY_SEPARATOR . 'public_html'", $source);

    foreach (array('400', '403', '404', '500') as $errorCode)
    {
        $errorPage = _g2mlDashboardAssetsTestMainRoot() . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . $errorCode . DIRECTORY_SEPARATOR . 'index.php';
        assert_true(is_file($errorPage), 'The main site has no error page for ' . $errorCode);
    }
});

// ============================================================================
// 🛠️ The dashboard's .htaccess and robots.txt
// ============================================================================

test('dashboard .htaccess: the four error documents match the main site\'s', function (): void
{
    $source = _g2mlDashboardAssetsTestRead(_g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . '.htaccess', 'web/Go2My.Link/_admin/public_html/.htaccess');

    $lines = array(
        'ErrorDocument 400 /index.php?route=errors/400',
        'ErrorDocument 403 /index.php?route=errors/403',
        'ErrorDocument 404 /index.php?route=errors/404',
        'ErrorDocument 500 /index.php?route=errors/500',
    );

    $mainSource = _g2mlDashboardAssetsTestRead(_g2mlDashboardAssetsTestMainRoot() . DIRECTORY_SEPARATOR . '.htaccess', 'web/Go2My.Link/public_html/.htaccess');

    foreach ($lines as $line)
    {
        assert_contains($line, $source, 'The dashboard .htaccess is missing: ' . $line);
        assert_contains($line, $mainSource, 'The main-site .htaccess no longer has: ' . $line);
    }
});

test('dashboard .htaccess: the "do not index" header sits inside the mod_headers block', function (): void
{
    $source = _g2mlDashboardAssetsTestRead(_g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . '.htaccess', 'web/Go2My.Link/_admin/public_html/.htaccess');

    $blockStart = strpos($source, '<IfModule mod_headers.c>');

    assert_true($blockStart !== false, 'Expected a <IfModule mod_headers.c> block');

    $blockEnd = strpos($source, '</IfModule>', (int) $blockStart);

    assert_true($blockEnd !== false, 'The mod_headers block is never closed');

    $block = substr($source, (int) $blockStart, (int) $blockEnd - (int) $blockStart);

    assert_contains('Header always set X-Robots-Tag "noindex, nofollow"', $block);
});

test('dashboard .htaccess: a real folder is served as itself, not sent to the front controller', function (): void
{
    $source = _g2mlDashboardAssetsTestRead(_g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . '.htaccess', 'web/Go2My.Link/_admin/public_html/.htaccess');

    // /api/consent/ is a real folder with its own index.php, so it relies on
    // the catch-all rule skipping real folders (the !-d condition).
    $matched = preg_match(
        '/RewriteCond %\{REQUEST_FILENAME\} !-f\s+RewriteCond %\{REQUEST_FILENAME\} !-d\s+RewriteRule \^\(\.\*\)\$ index\.php\?route=\$1 \[QSA,L\]/',
        $source
    );

    assert_same(1, $matched, 'The catch-all route to index.php must skip real files and real folders');
});

test('dashboard robots.txt asks every crawler to stay out', function (): void
{
    $text = _g2mlDashboardAssetsTestRead(_g2mlDashboardAssetsTestAdminRoot() . DIRECTORY_SEPARATOR . 'robots.txt', 'web/Go2My.Link/_admin/public_html/robots.txt');

    $lines = preg_split('/\R/', $text);

    assert_true(is_array($lines), 'Could not split robots.txt into lines');
    assert_true(in_array('User-agent: *', $lines, true), 'robots.txt needs the line: User-agent: *');
    assert_true(in_array('Disallow: /', $lines, true), 'robots.txt needs the line: Disallow: /');
});
