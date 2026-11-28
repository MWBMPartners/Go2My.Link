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
 * 🧪 Unit tests — who sees the debug panel and error details (#319, D-011)
 * ============================================================================
 *
 * The database-free half. It checks the decision functions and the settings
 * that live in files a test web server does not read (.htaccess, .user.ini).
 * The end-to-end half is tests/integration/debug_access_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#319)
 * ============================================================================
 */

declare(strict_types=1);

$g2mlDebugAccessFile = dirname(__DIR__, 2) . '/web/_functions/debug_access.php';

if (file_exists($g2mlDebugAccessFile))
{
    require_once $g2mlDebugAccessFile;
}

/**
 * The web roots that serve the application.
 *
 * @return array<int, string>
 */
function g2mlDebugUnitTest_webRoots(): array
{
    $web = dirname(__DIR__, 2) . '/web';

    return array(
        $web . '/Go2My.Link/public_html',
        $web . '/Go2My.Link/_admin/public_html',
        $web . '/G2My.Link/public_html',
        $web . '/Lnks.page/public_html',
    );
}

test('debug access (#319): test and local hosts want debug output; the live site only when asked', function (): void
{
    foreach (array('alpha', 'beta', 'rc', 'local') as $environment)
    {
        assert_true(g2ml_debugWanted($environment, null), $environment);
    }

    assert_false(g2ml_debugWanted('production', null));
    assert_false(g2ml_debugWanted('production', '1'));
    assert_false(g2ml_debugWanted('production', array('true')));
    assert_true(g2ml_debugWanted('production', 'true'));
});

test('debug access (#319): with no sign-in session nobody counts as a global administrator', function (): void
{
    $saved = $_SESSION;

    try
    {
        $_SESSION = array();
        assert_same(0, g2ml_signedInGlobalAdminUID());

        // A role in the session proves nothing without a sign-in token.
        $_SESSION = array('user_uid' => 1, 'user_role' => 'GlobalAdmin');
        assert_same(0, g2ml_signedInGlobalAdminUID());

        $_SESSION = array('user_uid' => 1, 'session_token' => array('x'), 'user_role' => 'GlobalAdmin');
        assert_same(0, g2ml_signedInGlobalAdminUID());
    }
    finally
    {
        $_SESSION = $saved;
    }
});

test('debug access (#319): nothing may be printed before debug mode has been decided', function (): void
{
    // The unit run never calls g2ml_applyDebugMode(), as an early error never would.
    assert_false(g2ml_debugOutputAllowed());
});

test('debug access (#319): the footer and the error page both ask before printing', function (): void
{
    $web = dirname(__DIR__, 2) . '/web';

    foreach (array('/_includes/footer.php', '/_functions/error_handler.php') as $file)
    {
        assert_contains('g2ml_debugOutputAllowed()', (string) file_get_contents($web . $file), $file);
    }
});

test('debug access (#319): bound query values are never recorded', function (): void
{
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_functions/db_query.php');

    assert_true(strpos($source, "'params'   => \$params") === false, 'db_query.php still records bound values');
});

test('debug access (#319): every web root switches on-screen errors off for CGI and for Apache', function (): void
{
    foreach (g2mlDebugUnitTest_webRoots() as $root)
    {
        assert_true(file_exists($root . '/.user.ini'), 'Missing ' . $root . '/.user.ini');

        $settings = parse_ini_file($root . '/.user.ini', false, INI_SCANNER_RAW);
        assert_same('Off', $settings['display_errors'] ?? null, $root);
        assert_same('Off', $settings['display_startup_errors'] ?? null, $root);

        $htaccess = (string) file_get_contents($root . '/.htaccess');
        assert_contains('php_flag display_errors Off', $htaccess, $root);
        assert_contains('php_flag display_startup_errors Off', $htaccess, $root);
        assert_contains('Header unset X-Powered-By', $htaccess, $root);

        // .user.ini must not be downloadable.
        assert_contains('<FilesMatch "^\.">', $htaccess, $root);
    }
});

test('debug access (#319): every file that starts the application switches errors off first', function (): void
{
    $checked = 0;

    foreach (g2mlDebugUnitTest_webRoots() as $root)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file)
        {
            $path = $file->getPathname();

            if (substr($path, -4) !== '.php' || strpos($path, '/pages/') !== false)
            {
                continue;
            }

            $source = (string) file_get_contents($path);

            if (preg_match("/^define\\('G2ML_COMPONENT'|^\\\$mainSiteConsentEndpoint/m", $source) !== 1)
            {
                continue;
            }

            $off     = strpos($source, "ini_set('display_errors', '0');");
            $matched = preg_match('/^[ \t]*require(_once)? /m', $source, $found, PREG_OFFSET_CAPTURE);

            assert_true($off !== false && $matched === 1 && $off < $found[0][1], $path . ' must switch errors off before it loads anything');
            $checked++;
        }
    }

    assert_same(14, $checked);
});

test('debug access (#319): nothing turns on-screen errors on except the administrator check', function (): void
{
    $web   = dirname(__DIR__, 2) . '/web';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS));
    $found = array();

    foreach ($files as $file)
    {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php' || strpos($path, '/_libraries/') !== false)
        {
            continue;
        }

        if (preg_match("/ini_set\\(\\s*'display(_startup)?_errors',\\s*'(?!0')/", (string) file_get_contents($path)) === 1)
        {
            $found[] = basename($path);
        }
    }

    assert_same(array('debug_access.php'), $found);
});
