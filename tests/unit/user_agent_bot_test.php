<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 */

/**
 * ============================================================================
 * Regression test — _g2ml_parseUserAgent() bot detection (B-043)
 * ============================================================================
 * The bot-pattern list contains entries with a slash (e.g. 'Java/'). Before the
 * fix, preg_quote() was called without the '/' delimiter argument, so the
 * unescaped slash closed the /.../ delimiter early and raised
 * "preg_match(): Unknown modifier '|'" — silently breaking bot detection so
 * isBot was never set to 1 for those user agents.
 * ============================================================================
 */

require_once __DIR__ . '/../../web/_functions/activity_logger.php';

test('parseUserAgent: no PCRE warning and detects a Java/ bot (B-043)', function ()
{
    $capturedErrors = [];

    set_error_handler(function (int $errno, string $errstr) use (&$capturedErrors): bool
    {
        $capturedErrors[] = $errstr;
        return true;
    });

    $result = _g2ml_parseUserAgent('Java/1.8.0_292');

    restore_error_handler();

    assert_same([], $capturedErrors, 'no PCRE warning should be emitted');
    assert_same(1, $result['isBot'], 'a Java/ user agent must be detected as a bot');
});

test('parseUserAgent: detects Googlebot', function ()
{
    $result = _g2ml_parseUserAgent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    assert_same(1, $result['isBot'], 'Googlebot must be detected as a bot');
});

test('parseUserAgent: a normal Chrome user agent is not a bot', function ()
{
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $result = _g2ml_parseUserAgent($userAgent);
    assert_same(0, $result['isBot'], 'a normal browser UA must not be flagged as a bot');
});
