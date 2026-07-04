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
 * 🧪 Characterization tests — password hashing (security.php)
 * ============================================================================
 *
 * Captures the CURRENT behaviour of g2ml_hashPassword / g2ml_verifyPassword.
 * These are characterization tests: they describe what the code does today so
 * future refactors are caught — they are not aspirational specifications.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

test('g2ml_hashPassword + g2ml_verifyPassword: correct password round-trips to true', function (): void
{
    $plaintext = 'CorrectHorseBatteryStaple!2026';
    $hash      = g2ml_hashPassword($plaintext);

    assert_true(g2ml_verifyPassword($plaintext, $hash), 'A freshly hashed password must verify against its own hash');
});

test('g2ml_verifyPassword: wrong password returns false', function (): void
{
    $hash = g2ml_hashPassword('the-real-password');

    assert_false(g2ml_verifyPassword('a-different-password', $hash), 'An incorrect password must not verify');
});

test('g2ml_hashPassword: hash differs from the plaintext', function (): void
{
    $plaintext = 'plaintext-secret';
    $hash      = g2ml_hashPassword($plaintext);

    assert_not_same($plaintext, $hash, 'The stored hash must never equal the plaintext');
});

test('g2ml_hashPassword: two hashes of the same password differ (random salt)', function (): void
{
    $plaintext = 'same-input-twice';
    $hashOne   = g2ml_hashPassword($plaintext);
    $hashTwo   = g2ml_hashPassword($plaintext);

    assert_not_same($hashOne, $hashTwo, 'Each hash should use a fresh random salt, so two hashes differ');
});

test('g2ml_hashPassword: produces an Argon2id hash on this PHP build', function (): void
{
    // Characterization: PHP 8.x ships PASSWORD_ARGON2ID, so the Argon2id branch
    // is taken and the hash carries the $argon2id$ identifier. Documents the
    // current algorithm choice on the supported platform.
    if (defined('PASSWORD_ARGON2ID'))
    {
        $hash = g2ml_hashPassword('whatever');
        assert_contains('$argon2id$', $hash, 'On a PHP build with Argon2id, hashes should be Argon2id');
    }
    else
    {
        // bcrypt fallback path; recorded for completeness.
        $hash = g2ml_hashPassword('whatever');
        assert_contains('$2y$', $hash, 'Without Argon2id, the bcrypt fallback should be used');
    }
});
