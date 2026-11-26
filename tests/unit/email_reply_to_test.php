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
 * 🧪 Unit tests — g2ml_sendEmail() Reply-To override + send test seam (#212)
 * ============================================================================
 *
 * Drives the REAL g2ml_sendEmail() from web/_functions/email.php, using the
 * $GLOBALS['g2ml_email_send_override'] test seam that function now supports
 * instead of the real mail() — the CLI test runner has no working mail
 * transport, and these tests must not attempt to send real email on every
 * run. The seam captures the exact headers string g2ml_sendEmail() built, so
 * these tests can check the Reply-To line directly rather than trusting the
 * function's own report of success.
 *
 * Uses the real 'password_reset' template (web/_includes/email_templates/
 * password_reset.php) as a stand-in body — its content is not what these
 * tests are about, only the headers are.
 *
 * NO DATABASE NEEDED: getSetting() and logActivity() are both called through
 * function_exists() guards inside email.php, so with settings.php not
 * loaded, g2ml_sendEmail() falls back to its own hard-coded defaults
 * (fromAddress 'noreply@go2my.link', replyTo 'support@go2my.link', …)
 * exactly as it does on any request where getSetting() is unavailable.
 * db_connect.php IS loaded below, even though this suite is DB-free,
 * because logActivity() (from activity_logger.php) calls getDB()
 * UNCONDITIONALLY, with no function_exists() guard of its own — and since
 * every unit test file is require_once'd into the SAME process before any
 * test runs (tests/run.php), another file elsewhere in the suite loading
 * activity_logger.php makes function_exists('logActivity') true here too,
 * whichever order the files happen to sort in. Without db_connect.php
 * loaded, that call to logActivity() would fatal on "Call to undefined
 * function getDB()" rather than testing anything about Reply-To at all.
 * With DB_HOST and friends left undefined (as this DB-free runner always
 * leaves them), getDB() itself degrades gracefully — it logs a warning and
 * returns null — so logActivity() simply reports failure and this test
 * still runs 100% DB-free.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#212)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/db_connect.php';
require_once dirname(__DIR__, 1) . '/../web/_functions/email.php';

/**
 * Send one test email through g2ml_sendEmail() with the given extra headers,
 * capturing the exact headers string it built via the test seam.
 *
 * @param  array<string, string> $extraHeaders
 * @return array{sent: bool, headers: string}
 */
function g2ml_emailReplyToTest_send(array $extraHeaders): array
{
    $capturedHeaders = '';

    $GLOBALS['g2ml_email_send_override'] = function (string $to, string $subject, string $emailBody, string $headers) use (&$capturedHeaders): bool
    {
        $capturedHeaders = $headers;

        return true;
    };

    $sent = g2ml_sendEmail(
        'recipient@example.test',
        'Test subject',
        'password_reset',
        [
            'firstName' => 'Test',
            'resetURL'  => 'https://go2my.link/reset-password?token=abc123',
        ],
        '',
        $extraHeaders
    );

    unset($GLOBALS['g2ml_email_send_override']);

    return ['sent' => $sent, 'headers' => $capturedHeaders];
}

test('g2ml_sendEmail: a valid Reply-To override REPLACES the default, not adds a second header', function (): void
{
    $result = g2ml_emailReplyToTest_send(['Reply-To' => 'visitor@example.test']);

    assert_true($result['sent'], 'The test seam reports success, so g2ml_sendEmail must report success too');
    assert_same(1, substr_count($result['headers'], 'Reply-To:'), 'Exactly one Reply-To header line, not one default plus one appended override');
    assert_true(strpos($result['headers'], "Reply-To: visitor@example.test\r\n") !== false, 'The single Reply-To line carries the OVERRIDE address');
    assert_true(strpos($result['headers'], 'support@go2my.link') === false, 'The default address must not still be present once it has been replaced');
});

test('g2ml_sendEmail: the Reply-To key match is case-insensitive', function (): void
{
    $result = g2ml_emailReplyToTest_send(['reply-to' => 'lowercase@example.test']);

    assert_same(1, substr_count($result['headers'], 'Reply-To:'), 'Still exactly one Reply-To header line');
    assert_true(strpos($result['headers'], "Reply-To: lowercase@example.test\r\n") !== false, 'A lower-case "reply-to" key is honoured the same as "Reply-To"');
});

test('g2ml_sendEmail: a Reply-To value containing CR/LF is dropped, keeping the default', function (): void
{
    // After the function strips CR/LF (its CRLF-injection defence), this
    // value no longer parses as a valid email address, so it must be
    // rejected — the same outcome an attacker-supplied injection attempt
    // would get, which is the point of the test.
    $result = g2ml_emailReplyToTest_send(['Reply-To' => "attacker@example.test\r\nBcc: victim@example.test"]);

    assert_true($result['sent'], 'A rejected Reply-To override must not stop the email from sending');
    assert_same(1, substr_count($result['headers'], 'Reply-To:'), 'Exactly one Reply-To header line even when the override is rejected');
    assert_true(strpos($result['headers'], 'attacker@example.test') === false, 'The rejected override value must not appear anywhere in the sent headers');
    assert_true(strpos($result['headers'], "Bcc: victim@example.test") === false, 'No attacker-supplied header injection survives into the sent headers');
    assert_true(strpos($result['headers'], "Reply-To: support@go2my.link\r\n") !== false, 'The default Reply-To is kept when the override is invalid');
});

test('g2ml_sendEmail: an empty-string Reply-To override is dropped, keeping the default', function (): void
{
    $result = g2ml_emailReplyToTest_send(['Reply-To' => '']);

    assert_same(1, substr_count($result['headers'], 'Reply-To:'), 'Exactly one Reply-To header line');
    assert_true(strpos($result['headers'], "Reply-To: support@go2my.link\r\n") !== false, 'An empty override falls back to the default rather than sending a blank Reply-To');
});

test('g2ml_sendEmail: with no Reply-To in $extraHeaders, the default is used exactly as before', function (): void
{
    $result = g2ml_emailReplyToTest_send([]);

    assert_same(1, substr_count($result['headers'], 'Reply-To:'), 'Exactly one Reply-To header line');
    assert_true(strpos($result['headers'], "Reply-To: support@go2my.link\r\n") !== false, 'The default Reply-To is unchanged when no override is given at all');
});
