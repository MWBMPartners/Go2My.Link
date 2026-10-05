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
 * 🧪 Check — sign-in redirects and one-time messages (#281)
 * ============================================================================
 *
 * g2ml_safePostLoginRedirect() decides where someone goes after signing in.
 * It must accept a path on this site and a full dashboard address, and refuse
 * everything that could send the person to another site.
 *
 * g2ml_flashSet() / g2ml_flashTake() carry the homepage's no-JavaScript
 * shorten result in the session, because reading it from the address let
 * anyone put their own text and link on our homepage.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';
require_once dirname(__DIR__, 1) . '/../web/_functions/session.php';

test('post-login redirect: paths on this site and dashboard addresses are accepted', function (): void
{
    $accepted = array(
        '/',
        '/links',
        '/invite?token=abc123',
        'https://admin.go2my.link/',
        'https://admin.go2my.link/links?filter=active',
    );

    foreach ($accepted as $value)
    {
        assert_same($value, g2ml_safePostLoginRedirect($value), 'Should be accepted: ' . $value);
    }
});

test('post-login redirect: anything that could leave our sites is refused', function (): void
{
    $refused = array(
        '',
        '//evil.com',
        '/\\evil.com',
        "/\t/evil.com",
        "/links\r\nSet-Cookie: x=1",
        'https://evil.com/',
        'http://admin.go2my.link/',
        'https://admin.go2my.link.evil.com/',
        'https://admin.go2my.link@evil.com/',
        'https://admin.go2my.link:8443/',
        'https://user:pass@admin.go2my.link/',
        'https://ADMIN.go2my.link/',
        'https://admin.go2my.link\\@evil.com/',
        'https://evil.com/https://admin.go2my.link/',
        'javascript:alert(1)',
        'links',
    );

    foreach ($refused as $value)
    {
        assert_same('', g2ml_safePostLoginRedirect($value), 'Should be refused: ' . json_encode($value));
    }
});

test('flash: a message is read once, then gone; a missing message is an empty string', function (): void
{
    $savedSession = $_SESSION;
    $_SESSION     = array();

    g2ml_flashSet('shorten_error', 'Please enter a web address.');

    assert_same('Please enter a web address.', g2ml_flashTake('shorten_error'), 'First read returns the message');
    assert_same('', g2ml_flashTake('shorten_error'), 'Second read returns nothing');
    assert_same('', g2ml_flashTake('never_set'), 'An unknown key returns nothing');

    $_SESSION = $savedSession;
});

test('homepage: the shorten result is never read from the address (#281)', function (): void
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/pages/home.php');
    assert_true($source !== false, 'Could not read home.php');

    assert_false(strpos((string) $source, "\$_GET['created']") !== false, "home.php must not read \$_GET['created']");
    assert_false(strpos((string) $source, "\$_GET['error']") !== false, "home.php must not read \$_GET['error']");
});

test('post-login redirect: the login page, the dashboard sign-in check and .htaccess use the #281 protections', function (): void
{
    $repository = dirname(__DIR__, 2);
    $checks     = array(
        'web/Go2My.Link/public_html/pages/login/index.php' => array(
            'g2ml_safePostLoginRedirect(g2ml_sanitiseInput($_GET[\'redirect\']))',
            'g2ml_safePostLoginRedirect($_POST[\'redirect\'])',
        ),
        'web/_functions/auth.php' => array(
            '$redirectURL = \'https://admin.go2my.link\' . $redirectURL;',
        ),
        'web/Go2My.Link/public_html/.htaccess' => array(
            'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
        ),
    );

    foreach ($checks as $file => $fragments)
    {
        $source = file_get_contents($repository . '/' . $file);
        assert_true($source !== false, 'Could not read ' . $file);

        foreach ($fragments as $fragment)
        {
            assert_true(strpos((string) $source, $fragment) !== false, $file . ' should contain: ' . $fragment);
        }
    }
});
