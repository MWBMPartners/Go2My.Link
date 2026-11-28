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
 * 🧪 Unit tests — secrets in web addresses are not logged (#323)
 * ============================================================================
 *
 * Covers web/_functions/log_redaction.php, what g2ml_logError() writes to
 * PHP's error log (the fallback when there is no database), and the
 * Referrer-Policy each web root sends. The database rows themselves are
 * checked by tests/integration/log_url_redaction_db_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #323
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/error_handler.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';

/** A made-up token with the shape of a real one (64 hexadecimal characters). */
function g2mlLogUrlTest_token(): string
{
    return str_repeat('4f9ac1d2', 8);
}

test('log redaction: token pages keep their path and lose the token (#323)', function (): void
{
    foreach (['/reset-password', '/verify-email', '/invite', '/unsubscribe'] as $path)
    {
        assert_same(
            $path . '?token=[redacted]',
            g2ml_redactLogURL($path . '?token=' . g2mlLogUrlTest_token())
        );
    }

    assert_same(
        'https://go2my.link/reset-password?token=[redacted]&forced=1',
        g2ml_redactLogURL('https://go2my.link/reset-password?token=abc&forced=1')
    );
});

test('log redaction: secrets under other names, nested addresses and unknown parameters are replaced (#323)', function (): void
{
    $cases = [
        '/cron.php?job=all&token=s3cret'           => '/cron.php?job=all&token=[redacted]',
        '/api/v1/urls?api_key=s3cret&limit=5'      => '/api/v1/urls?api_key=[redacted]&limit=5',
        '/x?TOKEN=s3cret'                          => '/x?TOKEN=[redacted]',
        '/x?tok%65n=s3cret'                        => '/x?tok%65n=[redacted]',
        '/login?redirect=%2Finvite%3Ftoken%3Ds3cret' => '/login?redirect=[redacted]',
        '/x?code=s3cret&email=a%40example.com'     => '/x?code=[redacted]&email=[redacted]',
        '/x?s3cret'                                => '/x?[redacted]',
        '/x?token[]=s3cret'                        => '/x?token[]=[redacted]',
        '/x?page=s3cret'                           => '/x?page=[redacted]',
        '/x?utm_source=%3Ftoken%3Ds3cret'          => '/x?utm_source=[redacted]',
        '/x?lang=' . g2mlLogUrlTest_token()        => '/x?lang=[redacted]',
        '/x#access_token=s3cret'                   => '/x#[redacted]',
        'https://bob:s3cret@example.com/a'         => 'https://[redacted]@example.com/a',
    ];

    foreach ($cases as $input => $expected)
    {
        assert_same($expected, g2ml_redactLogURL($input), $input);
    }
});

test('log redaction: tokens written as part of a path are replaced (#323)', function (): void
{
    assert_same('/invite/[redacted]', g2ml_redactLogURL('/invite/s3cret'));
    assert_same('/reset-password/[redacted]/[redacted]', g2ml_redactLogURL('/reset-password/s3/cret'));
    assert_same('/a/[redacted]/b', g2ml_redactLogURL('/a/' . g2mlLogUrlTest_token() . '/b'));
    assert_same('/api/v1/[redacted]', g2ml_redactLogURL('/api/v1/g2ml_AbCdEfGh_' . str_repeat('Zz9', 14)));
    assert_same('/x/[redacted]', g2ml_redactLogURL('/x/%3Ftoken%3Ds3cret'));
});

test('log redaction: harmless addresses and click tracking values are unchanged (#323)', function (): void
{
    $unchanged = [
        '/',
        '/abc123',
        '/org/members/invite',
        '/info/my-link',
        '/analytics?range=30d&from=2026-01-01&to=2026-01-31',
        '/links?page=2&filter=active',
        '/abc?utm_source=newsletter&utm_medium=e-mail&utm_campaign=spring%20sale&src=qr',
        'https://example.com/some/page',
    ];

    foreach ($unchanged as $url)
    {
        assert_same($url, g2ml_redactLogURL($url));
    }

    assert_same(null, g2ml_redactLogURL(null));
    assert_same('', g2ml_redactLogURL(''));
});

test('log redaction: addresses inside messages are cleaned and file paths are kept (#323)', function (): void
{
    $text = 'Failed for https://go2my.link/reset-password?token=s3cret-one and '
        . '"/verify-email?x=1&token=s3cret-two", token=s3cret-three, /invite/s3cret-four, '
        . 'key g2ml_AbCdEfGh_' . str_repeat('Zz9', 14);
    $safe = g2ml_redactLogText($text);

    assert_true(strpos($safe, 's3cret') === false, $safe);
    assert_true(strpos($safe, 'Zz9Zz9') === false, $safe);
    assert_contains('https://go2my.link/reset-password?token=[redacted]', $safe);

    $kept = "#0 /srv/web/public_html/pages/invite/index.php(42): acceptInvitation()\n"
        . "SELECT * FROM `t` WHERE `token` = ? AND token=?";

    assert_same($kept, g2ml_redactLogText($kept));
    assert_same($safe, g2ml_redactLogText($safe), 'Cleaning twice must change nothing');
});

test('log redaction: log details are cleaned, but tracking values are left alone (#323)', function (): void
{
    $safe = g2ml_redactLogData([
        'nested'         => ['url' => 'https://example.com/?token=s3cret', 'count' => 3],
        'trackingParams' => ['utm_source' => 'a?b=c'],
    ]);

    assert_same('https://example.com/?token=[redacted]', $safe['nested']['url']);
    assert_same(3, $safe['nested']['count']);
    assert_same('a?b=c', $safe['trackingParams']['utm_source']);
});

test('log redaction: an address inside an API request body is cleaned (#323)', function (): void
{
    $safe = g2ml_apiRedactRequestBody(['longURL' => 'https://example.com/join?code=s3cret']);

    assert_same('https://example.com/join?code=[redacted]', $safe['longURL']);
});

test('log redaction: PHP error log fallback receives no token (#323)', function (): void
{
    $server  = $_SERVER;
    $logFile = (string) tempnam(sys_get_temp_dir(), 'g2ml_redact_');
    $before  = (string) ini_get('error_log');

    ini_set('error_log', $logFile);
    $_SERVER['REQUEST_URI'] = '/reset-password?token=s3cret';

    try
    {
        // No database is configured in the unit suite, so this uses the fallback
        g2ml_logError(
            'warning',
            1,
            'Bad address /reset-password?token=s3cret-title',
            'See https://go2my.link/invite?token=s3cret-detail',
            '/srv/a.php',
            1
        );
    }
    finally
    {
        $_SERVER = $server;
        ini_set('error_log', $before);
    }

    $written = (string) file_get_contents($logFile);
    @unlink($logFile);

    assert_contains('token=[redacted]', $written);
    assert_true(strpos($written, 's3cret') === false, $written);
});

test('referrer policy: no web root sends a full address to another site (#323)', function (): void
{
    $web   = dirname(__DIR__, 2) . '/web/';
    $files = [
        'Go2My.Link/public_html/.htaccess',
        'Go2My.Link/_admin/public_html/.htaccess',
        'G2My.Link/public_html/.htaccess',
        'Lnks.page/public_html/.htaccess',
    ];

    foreach ($files as $file)
    {
        assert_contains(
            'Header set Referrer-Policy "strict-origin-when-cross-origin"',
            (string) file_get_contents($web . $file),
            $file
        );
    }
});
