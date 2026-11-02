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
 * 🧪 Check — page fragments cannot be opened directly (#285)
 * ============================================================================
 *
 * The files under pages/ (on the main site and on the dashboard) are pieces of
 * a page: the front controller (index.php) puts each one between the shared
 * header and footer. They are included by PHP, not requested over the web. But
 * the web server would still hand a request for /pages/about/ to the file in
 * that folder. The About page then came out unstyled, and /pages/login/ and
 * every dashboard page stopped with a blank 500 error and a PHP fatal error
 * in the log.
 *
 * Both .htaccess files now answer a request for /pages/ or below with 404.
 * Their "ErrorDocument 404" line hands that answer to the front controller,
 * which shows the normal branded 404 page. This check fails if:
 *   - either .htaccess loses that rule, or the rule moves below the rules that
 *     would otherwise act first (the ".php" rewrite and the catch-all route);
 *   - the rule's pattern stops matching /pages and everything below it, or
 *     starts to match other addresses such as /pagesx or /about;
 *   - the "ErrorDocument 404" line stops pointing at the front controller's
 *     errors/404 route, or that route stops finding the branded 404 page;
 *   - a front controller stops loading page files with a PHP include (a
 *     require is not a web request, so the rule cannot get in its way).
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads the .htaccess files; it does not run Apache. It therefore cannot
 * show how Apache orders or applies the rules.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/router.php';

/**
 * The two web roots that use the file-based router.
 *
 * @return array<string, string>  Label => path from the repository root.
 */
function _g2mlPagesBlockedRoots(): array
{
    return array(
        'main site' => 'web/Go2My.Link/public_html',
        'dashboard' => 'web/Go2My.Link/_admin/public_html',
    );
}

/**
 * Read a file from the repository.
 *
 * @param  string $relative
 * @return string
 */
function _g2mlPagesBlockedRead(string $relative): string
{
    $full = dirname(__DIR__, 2) . '/' . $relative;

    assert_true(is_file($full), 'Expected file not found: ' . $relative);

    return (string) file_get_contents($full);
}

/**
 * The rule that blocks /pages/: its pattern and where it sits in the file, or
 * null when it is missing.
 *
 * @param  string $htaccess
 * @return array{pattern: string, position: int}|null
 */
function _g2mlPagesBlockedRule(string $htaccess): ?array
{
    $matches = array();

    if (preg_match('/^RewriteRule (\^pages\(\/\|\$\)) - \[R=404,L\]$/m', $htaccess, $matches, PREG_OFFSET_CAPTURE) !== 1)
    {
        return null;
    }

    return array(
        'pattern'  => $matches[1][0],
        'position' => (int) $matches[0][1],
    );
}

foreach (_g2mlPagesBlockedRoots() as $g2mlPagesBlockedLabel => $g2mlPagesBlockedRoot)
{
    test('pages folder: the ' . $g2mlPagesBlockedLabel . ' .htaccess blocks direct requests to /pages/ and sits above the rules that would act first (#285)', function () use ($g2mlPagesBlockedRoot): void
    {
        $htaccess = _g2mlPagesBlockedRead($g2mlPagesBlockedRoot . '/.htaccess');
        $rule     = _g2mlPagesBlockedRule($htaccess);

        assert_true($rule !== null, $g2mlPagesBlockedRoot . '/.htaccess has no rule sending /pages/ to the front controller as an unknown route');

        $phpRule  = strpos($htaccess, 'RewriteRule ^(.+)$ $1.php [L]');
        $catchAll = strpos($htaccess, 'RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]');

        assert_true($phpRule !== false, 'The ".php" rewrite rule was not found');
        assert_true($catchAll !== false, 'The catch-all route rule was not found');
        assert_true($rule['position'] < $phpRule, 'The /pages/ rule must come before the ".php" rewrite, or /pages/home would be rewritten to the file first');
        assert_true($rule['position'] < $catchAll, 'The /pages/ rule must come before the catch-all route');
    });

    test('pages folder: the ' . $g2mlPagesBlockedLabel . ' ErrorDocument 404 line sends the answer to the front controller\'s branded 404 page (#285)', function () use ($g2mlPagesBlockedRoot): void
    {
        $htaccess = _g2mlPagesBlockedRead($g2mlPagesBlockedRoot . '/.htaccess');

        assert_contains('ErrorDocument 404 /index.php?route=errors/404', $htaccess);

        $source = _g2mlPagesBlockedRead($g2mlPagesBlockedRoot . '/index.php');

        // The main site's router finds the page file itself; the dashboard's
        // front controller shows the main site's page for these four routes.
        if ($g2mlPagesBlockedRoot === 'web/Go2My.Link/public_html')
        {
            $resolved = resolveRoute('errors/404', dirname(__DIR__, 2) . '/' . $g2mlPagesBlockedRoot . '/pages');

            assert_true($resolved['file'] !== null, 'The route errors/404 no longer finds the branded 404 page');
        }
        else
        {
            assert_contains("\$errorRoutes = ['errors/400', 'errors/403', 'errors/404', 'errors/500'];", $source);
        }
    });

    test('pages folder: the ' . $g2mlPagesBlockedLabel . ' front controller loads page files with a PHP include, which the rule does not touch (#285)', function () use ($g2mlPagesBlockedRoot): void
    {
        $source = _g2mlPagesBlockedRead($g2mlPagesBlockedRoot . '/index.php');

        assert_contains("require \$resolved['file'];", $source);
    });
}

test('pages folder: the rule\'s pattern matches /pages and everything below it, and nothing else (#285)', function (): void
{
    $rule = _g2mlPagesBlockedRule(_g2mlPagesBlockedRead('web/Go2My.Link/public_html/.htaccess'));

    assert_true($rule !== null, 'The /pages/ rule is missing');

    // Apache gives a per-directory rule the path without its leading slash.
    $pattern = '#' . $rule['pattern'] . '#';

    foreach (array('pages', 'pages/', 'pages/about/', 'pages/login/index.php', 'pages/errors/404/', 'pages/home') as $blocked)
    {
        assert_same(1, preg_match($pattern, $blocked), $blocked . ' should be blocked');
    }

    foreach (array('', 'about', 'about/pages', 'pagesx', 'pages-x/', 'help/pages', 'api/v1/pages') as $allowed)
    {
        assert_same(0, preg_match($pattern, $allowed), $allowed . ' should not be blocked');
    }
});

test('pages folder: both .htaccess files carry the same rule (#285)', function (): void
{
    $main      = _g2mlPagesBlockedRule(_g2mlPagesBlockedRead('web/Go2My.Link/public_html/.htaccess'));
    $dashboard = _g2mlPagesBlockedRule(_g2mlPagesBlockedRead('web/Go2My.Link/_admin/public_html/.htaccess'));

    assert_true($main !== null && $dashboard !== null, 'One of the .htaccess files has no /pages/ rule');
    assert_same($main['pattern'], $dashboard['pattern']);
});
