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
 * 🧪 Unit tests — which copy of the site an address is (#158, D-029, D-032)
 * ============================================================================
 *
 * The test copies share the live database, so an address wrongly read as the
 * live site would run clean-ups on live data from test code. These tests pin
 * the host names to environments. The behaviour through real web requests is in
 * tests/integration/test_copies_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/environment.php';
require_once dirname(__DIR__, 2) . '/web/_functions/session.php';

/**
 * Check that each host name gives the expected environment.
 *
 * @param array<string, string> $expected  Host name => environment.
 */
function g2mlEnvironmentTest_check(array $expected): void
{
    foreach ($expected as $host => $environment)
    {
        assert_same($environment, g2ml_environmentForHost($host), $host);
    }
}

test('environment: rc addresses on our own domains are the release-candidate test copy', function (): void
{
    g2mlEnvironmentTest_check(array(
        'rc.go2my.link'       => 'rc',
        'rc.admin.go2my.link' => 'rc',
        'admin.rc.go2my.link' => 'rc',
        'rc.g2my.link'        => 'rc',
        'rc.lnks.page'        => 'rc',
        'RC.Go2My.Link'       => 'rc',
        'rc.go2my.link:443'   => 'rc',
        'rc.go2my.link.'      => 'rc',
    ));
});

test('environment: alpha and beta addresses, including dashboard ones in either order', function (): void
{
    g2mlEnvironmentTest_check(array(
        'alpha.go2my.link'       => 'alpha',
        'alpha.admin.go2my.link' => 'alpha',
        'admin.alpha.go2my.link' => 'alpha',
        'beta.g2my.link'         => 'beta',
        'admin.beta.go2my.link'  => 'beta',
        'beta.lnks.page'         => 'beta',
        'beta.localhost'         => 'beta',
        'alpha.localhost:8000'   => 'alpha',
        'rc.go2my.local'         => 'rc',
    ));
});

test('environment: live and other addresses are not read as test copies', function (): void
{
    g2mlEnvironmentTest_check(array(
        'go2my.link'            => 'production',
        'www.go2my.link'        => 'production',
        'admin.go2my.link'      => 'production',
        'g2my.link'             => 'production',
        'lnks.page'             => 'production',
        'arc.go2my.link'        => 'production',
        'alphabet.go2my.link'   => 'production',
        'alpha-admin.go2my.link' => 'production',
        'evilgo2my.link'        => 'production',
        'rc.evilgo2my.link'     => 'production',
        'rc.example.com'        => 'production',
        'alpha.example.com'     => 'production',
        'beta.acme.co.uk'       => 'production',
        'admin.alpha.example.com' => 'production',
        'links.beta.com'        => 'production',
        'go2my.link.rc.example.com' => 'production',
        'localhost:8000'        => 'production',
        ''                      => 'production',
    ));
});

test('environment: local addresses are still local', function (): void
{
    g2mlEnvironmentTest_check(array(
        'localhost'   => 'local',
        '127.0.0.1'   => 'local',
        '::1'         => 'local',
        'go2my.local' => 'local',
    ));
});

test('environment: only alpha, beta and rc are test environments', function (): void
{
    assert_true(g2ml_isTestEnvironment('alpha'));
    assert_true(g2ml_isTestEnvironment('beta'));
    assert_true(g2ml_isTestEnvironment('rc'));
    assert_false(g2ml_isTestEnvironment('production'));
    assert_false(g2ml_isTestEnvironment('local'));
});

test('environment: rc gets a host-only sign-in cookie, like beta', function (): void
{
    assert_same('', g2ml_sessionCookieDomain('rc', 'go2my.link'));
    assert_same('', g2ml_sessionCookieDomain('rc', 'admin.go2my.link'));
});

test('environment: each test copy has its own session cookie name; live and local keep the old one', function (): void
{
    assert_same('G2ML_SESSION', g2ml_sessionCookieName('production'));
    assert_same('G2ML_SESSION', g2ml_sessionCookieName('local'));
    assert_same('G2ML_SESSION_ALPHA', g2ml_sessionCookieName('alpha'));
    assert_same('G2ML_SESSION_BETA', g2ml_sessionCookieName('beta'));
    assert_same('G2ML_SESSION_RC', g2ml_sessionCookieName('rc'));
});

test('environment: the session cookie is Secure on live, and on test copies over HTTPS', function (): void
{
    assert_true(g2ml_sessionCookieSecure('production', array()));
    assert_false(g2ml_sessionCookieSecure('local', array('HTTPS' => 'on')));

    foreach (array('alpha', 'beta', 'rc') as $environment)
    {
        assert_true(g2ml_sessionCookieSecure($environment, array('HTTPS' => 'on')), $environment);
        assert_true(g2ml_sessionCookieSecure($environment, array('HTTP_X_FORWARDED_PROTO' => 'https')), $environment);
        assert_false(g2ml_sessionCookieSecure($environment, array('HTTPS' => 'off')), $environment);
        assert_false(g2ml_sessionCookieSecure($environment, array()), $environment);
    }
});

test('environment: the copy is read from its folder name, then its marker file, else unknown', function (): void
{
    $base = sys_get_temp_dir() . '/g2ml_channel_' . bin2hex(random_bytes(6));

    mkdir($base, 0700);

    try
    {
        foreach (array('web', 'live', 'alpha', 'beta', 'rc') as $folder)
        {
            mkdir($base . '/' . $folder, 0700);
        }

        // No marker: only the test folder names say anything.
        assert_same('unknown', g2ml_copyChannel($base . '/web'));
        assert_same('unknown', g2ml_copyChannel($base . '/live'));
        assert_same('alpha', g2ml_copyChannel($base . '/alpha'));
        assert_same('beta', g2ml_copyChannel($base . '/beta'));
        assert_same('rc', g2ml_copyChannel($base . '/rc'));

        foreach (array("live\n" => 'live', 'rc' => 'rc', "alpha\r\n" => 'alpha', '' => 'unknown', 'Live site' => 'unknown') as $content => $expected)
        {
            file_put_contents($base . '/web/.g2ml-channel', (string) $content);
            assert_same($expected, g2ml_copyChannel($base . '/web'), 'marker: ' . trim((string) $content));
        }

        // A live marker copied into a test folder does not make it live.
        file_put_contents($base . '/beta/.g2ml-channel', "live\n");
        assert_same('beta', g2ml_copyChannel($base . '/beta'));
    }
    finally
    {
        foreach (array('web', 'beta') as $folder)
        {
            if (is_file($base . '/' . $folder . '/.g2ml-channel'))
            {
                unlink($base . '/' . $folder . '/.g2ml-channel');
            }
        }

        foreach (array('web', 'live', 'alpha', 'beta', 'rc') as $folder)
        {
            rmdir($base . '/' . $folder);
        }

        rmdir($base);
    }
});

test('environment: a test copy stays a test copy on any address', function (): void
{
    foreach (array('go2my.link', 'unknown.example.com', 'localhost', '') as $host)
    {
        assert_same('rc', g2ml_resolveEnvironment('rc', $host), $host);
    }

    assert_same('production', g2ml_resolveEnvironment('live', 'go2my.link'));
    assert_same('alpha', g2ml_resolveEnvironment('live', 'alpha.go2my.link'));
    assert_same('local', g2ml_resolveEnvironment('unknown', 'localhost'));
});

test('environment: only a known live copy on a non-test address runs jobs and clean-ups', function (): void
{
    assert_true(g2ml_jobsAllowed('live', 'production'));
    assert_true(g2ml_jobsAllowed('live', 'local'));
    assert_false(g2ml_jobsAllowed('unknown', 'production'));
    assert_false(g2ml_jobsAllowed('unknown', 'local'));

    foreach (array('alpha', 'beta', 'rc') as $test)
    {
        assert_false(g2ml_jobsAllowed($test, 'production'), $test);
        assert_false(g2ml_jobsAllowed('live', $test), $test);
    }
});

test('environment: every automatic clean-up asks first', function (): void
{
    $web = dirname(__DIR__, 2) . '/web/_functions/';

    assert_contains('if (g2ml_cleanupAllowed() && mt_rand(1, 100) === 1)', (string) file_get_contents($web . 'api_ratelimit.php'));
    assert_contains('if (g2ml_cleanupAllowed() && mt_rand(1, 100) === 1)', (string) file_get_contents($web . 'pricing.php'));
    assert_true(
        preg_match('/if \(g2ml_cleanupAllowed\(\)\)\s*\{\s*dbDelete\(\s*"DELETE FROM `tblFormAttempts`/', (string) file_get_contents($web . 'form_rate_limit.php')) === 1,
        'The form attempt sweep must be guarded'
    );
});

test('environment: page_init uses the shared rule and skips session clean-up on test copies', function (): void
{
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/web/_includes/page_init.php');

    assert_contains("define('G2ML_ENVIRONMENT', g2ml_resolveEnvironment(G2ML_COPY_CHANNEL, \$hostname));", $source);
    assert_contains('session_name(g2ml_sessionCookieName(g2ml_getEnvironment()));', $source);
    assert_contains("'secure'   => g2ml_sessionCookieSecure(g2ml_getEnvironment(), \$_SERVER),", $source);
    assert_true(
        preg_match('/&& g2ml_cleanupAllowed\(\)\s*&& mt_rand\(1, 100\) === 1\s*\)\s*\{\s*cleanExpiredSessions\(\);/', $source) === 1,
        'The 1-in-100 session clean-up must only run on the live copy'
    );
    assert_same(1, substr_count($source, 'cleanExpiredSessions();'), 'One guarded call only');
});

test('environment: the installer marks a copy live only in a live folder or on a live address', function (): void
{
    $base = sys_get_temp_dir() . '/g2ml_mark_' . bin2hex(random_bytes(6));

    mkdir($base, 0700);

    $cases = array(
        // folder, host, expected channel afterwards
        array('web', 'go2my.link', 'live'),
        array('web', 'GO2MY.LINK:443', 'live'),
        array('web', 'localhost', 'unknown'),
        array('web', 'staging.go2my.link', 'unknown'),
        array('web', 'rc.go2my.link', 'unknown'),
        array('web', 'example.com', 'unknown'),
        array('web', '', 'unknown'),
        array('live', 'example.com', 'live'),
        array('beta', 'go2my.link', 'beta'),
    );

    try
    {
        foreach ($cases as $case)
        {
            $root   = $base . '/' . $case[0];
            $marker = $root . '/.g2ml-channel';

            mkdir($root, 0700);

            $notice = g2ml_installerMarkCopy($root, $case[1]);
            $label  = $case[0] . ' on "' . $case[1] . '"';

            assert_same($case[2], g2ml_copyChannel($root), $label);
            assert_same($case[2] === 'unknown', $notice !== '', 'A notice exactly when nothing was identified: ' . $label);

            if (is_file($marker))
            {
                unlink($marker);
            }

            rmdir($root);
        }

        // An existing mark is never changed.
        mkdir($base . '/web', 0700);
        file_put_contents($base . '/web/.g2ml-channel', "alpha\n");
        assert_same('', g2ml_installerMarkCopy($base . '/web', 'go2my.link'));
        assert_same('alpha', g2ml_copyChannel($base . '/web'));
        unlink($base . '/web/.g2ml-channel');
        rmdir($base . '/web');
    }
    finally
    {
        rmdir($base);
    }
});

test('environment: the installer, the cookie page and page_init use the shared rules', function (): void
{
    $web = dirname(__DIR__, 2) . '/web';

    $installer = (string) file_get_contents($web . '/Go2My.Link/public_html/install/index.php');
    assert_contains('g2ml_installerMarkCopy($copyRoot, ', $installer);
    assert_contains("\$result['notices'][] = \$markNotice;", $installer);

    $cookiePage = (string) file_get_contents($web . '/Go2My.Link/public_html/pages/legal/cookies/index.php');
    assert_contains('g2ml_sessionCookieName(g2ml_getEnvironment())', $cookiePage);

    $pageInit = (string) file_get_contents($web . '/_includes/page_init.php');
    assert_contains('g2ml_cronReportUnknownCopy(g2ml_getEnvironment(), g2ml_currentCopyChannel());', $pageInit);
});
