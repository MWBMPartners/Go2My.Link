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
 * 🧪 Unit tests — form rate-limit whitelist and IP hashing (#212)
 * ============================================================================
 *
 * Covers the DB-FREE parts of web/_functions/form_rate_limit.php:
 *
 *   - g2ml_formRateLimitKeyIsValid() — the exact whitelist of form keys.
 *   - g2ml_formRateLimitTake() called with an UNRECOGNISED form key —
 *     this returns before ever touching the database, so it is safe to
 *     exercise here without a test database (a database-backed count for a
 *     RECOGNISED key is covered by tests/integration/form_rate_limit_test.
 *     php instead, where a real tblFormAttempts table exists to count
 *     against).
 *   - g2ml_formRateLimitIpHash() — a 64-character lower-case hex digest that
 *     differs between two different REMOTE_ADDR values.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#212)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';
require_once dirname(__DIR__, 1) . '/../web/_functions/form_rate_limit.php';

// ----------------------------------------------------------------------------
// g2ml_formRateLimitKeyIsValid()
// ----------------------------------------------------------------------------

test('g2ml_formRateLimitKeyIsValid: accepts exactly the three known form keys', function (): void
{
    assert_true(g2ml_formRateLimitKeyIsValid('register'), 'register is a recognised form key');
    assert_true(g2ml_formRateLimitKeyIsValid('contact'), 'contact is a recognised form key');
    assert_true(g2ml_formRateLimitKeyIsValid('password_reset'), 'password_reset is a recognised form key');
});

test('g2ml_formRateLimitKeyIsValid: refuses anything else, including a near-miss', function (): void
{
    assert_false(g2ml_formRateLimitKeyIsValid('login'), 'login is not one of the three protected forms');
    assert_false(g2ml_formRateLimitKeyIsValid('Register'), 'Matching is exact, not case-insensitive');
    assert_false(g2ml_formRateLimitKeyIsValid(''), 'An empty string is not a valid form key');
    assert_false(g2ml_formRateLimitKeyIsValid('register '), 'Trailing whitespace makes it a different string, not a valid key');
});

// ----------------------------------------------------------------------------
// g2ml_formRateLimitTake() — the unrecognised-key path (no DB touched)
// ----------------------------------------------------------------------------

test('g2ml_formRateLimitTake: an unrecognised form key is refused, not counted', function (): void
{
    // This checks only the outcome (refused). The whitelist itself is
    // covered by the g2ml_formRateLimitKeyIsValid() tests above.
    assert_same('unavailable', g2ml_formRateLimitTake('not_a_real_form', 10), 'An unrecognised formKey is refused rather than silently allowed or counted against the wrong bucket');
});

// ----------------------------------------------------------------------------
// g2ml_formRateLimitIpHash()
// ----------------------------------------------------------------------------

test('g2ml_formRateLimitIpHash: returns a 64-character lower-case hex digest', function (): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

    $hash = g2ml_formRateLimitIpHash();

    assert_same(64, strlen($hash), 'A SHA-256 HMAC digest in hex is always 64 characters');
    assert_same(1, preg_match('/^[0-9a-f]{64}$/', $hash), 'The digest is lower-case hexadecimal only');
});

test('g2ml_formRateLimitIpHash: two different visitor addresses hash to two different values', function (): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $firstHash = g2ml_formRateLimitIpHash();

    $_SERVER['REMOTE_ADDR'] = '203.0.113.20';
    $secondHash = g2ml_formRateLimitIpHash();

    assert_not_same($firstHash, $secondHash, 'Different IP addresses must not collide onto the same rate-limit bucket');
});

test('g2ml_formRateLimitIpHash: the SAME visitor address hashes to the SAME value twice', function (): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.30';
    $firstCall  = g2ml_formRateLimitIpHash();
    $secondCall = g2ml_formRateLimitIpHash();

    assert_same($firstCall, $secondCall, 'The same address must hash the same way on a repeat visit, or counting attempts would never work');
});

test('g2ml_formRateLimitNormaliseIp: one IPv6 block counts as one visitor', function (): void
{
    assert_same(
        g2ml_formRateLimitNormaliseIp('2001:db8:1:2::1'),
        g2ml_formRateLimitNormaliseIp('2001:db8:1:2:aaaa:bbbb:cccc:dddd'),
        'Addresses sharing the first 64 bits must group together'
    );
    assert_true(
        g2ml_formRateLimitNormaliseIp('2001:db8:1:2::1') !== g2ml_formRateLimitNormaliseIp('2001:db8:1:3::1'),
        'A different block must stay separate'
    );
    assert_same('192.0.2.7', g2ml_formRateLimitNormaliseIp('::ffff:192.0.2.7'), 'IPv4 written as IPv6 becomes IPv4');
    assert_same('192.0.2.7', g2ml_formRateLimitNormaliseIp('192.0.2.7'), 'Plain IPv4 is unchanged');
    assert_same('unknown', g2ml_formRateLimitNormaliseIp('unknown'), 'A non-address is unchanged');
});
