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
 * 🧪 Characterization tests — CSRF token management (security.php)
 * ============================================================================
 *
 * The CSRF helpers store per-form tokens in $_SESSION['_csrf_tokens']. The
 * unit runner initialises $_SESSION as a plain array so these helpers work
 * without an active PHP session.
 *
 * CURRENT behaviour captured here:
 *   - A generated token validates true for the same form name.
 *   - Tokens are single-use: a second validation of the same token is false.
 *   - A token validated against the WRONG form name returns false.
 *   - A tampered token value returns false.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

test('g2ml_generateCSRFToken + g2ml_validateCSRFToken: round-trip is true', function (): void
{
    $token = g2ml_generateCSRFToken('login');

    assert_true(g2ml_validateCSRFToken($token, 'login'), 'A freshly generated token must validate for its form');
});

test('g2ml_validateCSRFToken: tokens are single-use (second validation is false)', function (): void
{
    $token = g2ml_generateCSRFToken('single_use_form');

    $first  = g2ml_validateCSRFToken($token, 'single_use_form');
    $second = g2ml_validateCSRFToken($token, 'single_use_form');

    assert_true($first, 'First validation succeeds');
    assert_false($second, 'Token is consumed, so the second validation fails');
});

test('g2ml_validateCSRFToken: wrong form name returns false', function (): void
{
    $token = g2ml_generateCSRFToken('form_alpha');

    assert_false(g2ml_validateCSRFToken($token, 'form_beta'), 'A token for one form must not validate for another');
});

test('g2ml_validateCSRFToken: tampered token value returns false', function (): void
{
    $token   = g2ml_generateCSRFToken('tamper_form');
    $tampered = $token . 'x';

    assert_false(g2ml_validateCSRFToken($tampered, 'tamper_form'), 'A modified token must not validate');
});

test('g2ml_validateCSRFToken: unknown form name returns false', function (): void
{
    assert_false(g2ml_validateCSRFToken('any-token', 'never_generated_form'), 'No stored token means no validation');
});

test('g2ml_csrfField: produces a hidden input carrying a token', function (): void
{
    $html = g2ml_csrfField('field_form');

    assert_contains('type="hidden"', $html, 'Output is a hidden input');
    assert_contains('name="_csrf_token"', $html, 'Field is named _csrf_token');
});
