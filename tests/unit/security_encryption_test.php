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
 * 🧪 Characterization tests — AES-256-GCM encryption (security.php)
 * ============================================================================
 *
 * g2ml_encrypt() / g2ml_decrypt() exist in security.php and implement
 * AES-256-GCM with a random 12-byte IV and a 16-byte tag, returning
 * base64(iv . tag . ciphertext). The unit runner defines a valid throwaway
 * ENCRYPTION_SALT so the configured-key guard passes.
 *
 * CURRENT behaviour captured:
 *   - encrypt → decrypt round-trips back to the original plaintext.
 *   - The ciphertext differs from the plaintext.
 *   - Two encryptions of the same value differ (random IV).
 *   - decrypt() returns false on garbage / too-short input.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

test('AES: g2ml_encrypt and g2ml_decrypt functions exist', function (): void
{
    assert_true(function_exists('g2ml_encrypt'), 'g2ml_encrypt must be defined');
    assert_true(function_exists('g2ml_decrypt'), 'g2ml_decrypt must be defined');
});

test('AES: encrypt → decrypt round-trips to the original plaintext', function (): void
{
    $plaintext = 'a sensitive setting value — £ 2026 ✓';
    $encrypted = g2ml_encrypt($plaintext);

    assert_not_same(false, $encrypted, 'Encryption should succeed with a valid key');
    assert_same($plaintext, g2ml_decrypt($encrypted), 'Decryption must return the original plaintext');
});

test('AES: ciphertext differs from the plaintext', function (): void
{
    $plaintext = 'plain-value';
    $encrypted = g2ml_encrypt($plaintext);

    assert_not_same($plaintext, $encrypted, 'The encrypted blob must not equal the plaintext');
});

test('AES: two encryptions of the same value differ (random IV)', function (): void
{
    $plaintext = 'repeat-me';
    $one       = g2ml_encrypt($plaintext);
    $two       = g2ml_encrypt($plaintext);

    assert_not_same($one, $two, 'A fresh random IV should make repeated ciphertexts differ');

    // Both must still decrypt back to the same plaintext.
    assert_same($plaintext, g2ml_decrypt($one), 'First ciphertext decrypts correctly');
    assert_same($plaintext, g2ml_decrypt($two), 'Second ciphertext decrypts correctly');
});

test('AES: g2ml_decrypt returns false on invalid input', function (): void
{
    // Too short / not a valid base64 GCM payload — characterised as false.
    assert_same(false, g2ml_decrypt('not-a-valid-payload'), 'Garbage input must not decrypt');
});

test('AES: decrypting with the wrong key returns false', function (): void
{
    $encrypted = g2ml_encrypt('keyed-secret', 'key-one-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

    assert_same(false, g2ml_decrypt($encrypted, 'key-two-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'), 'A wrong key must fail authentication');
});
