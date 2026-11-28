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
 *   - Since #291, showing the same form name again does not break the copy
 *     already shown (several tokens are kept per form name).
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

// ============================================================================
// 🗂️ Same form name shown more than once (#291)
// ============================================================================
// Until #291, g2ml_generateCSRFToken() kept ONE token per form name and
// replaced it on every call, so showing a form a second time (another tab,
// or the browser fetching the page in the background) broke the first copy.
// The two tests that pinned that behaviour were replaced by these, which pin
// the new rule: several tokens per form name, each usable once. The full set
// of checks for the new scheme is in tests/unit/csrf_token_pool_test.php.
//
// Pages that render one action per table row still name each row's form
// after the row (#147): five tokens for a shared name would only make the
// last five rows work. See web/_functions/linkspage_manage.php's admin pages
// (#48), web/Go2My.Link/_admin/public_html/pages/linkspage/index.php and
// pages/linkspage/edit/index.php.
// ============================================================================

test('CSRF #291: showing ONE form name twice keeps the FIRST token valid', function (): void
{
    $firstToken  = g2ml_csrfField('shared_repeated_form_alpha');
    $secondToken = g2ml_csrfField('shared_repeated_form_alpha');

    preg_match('/value="([^"]+)"/', $firstToken, $firstMatches);
    preg_match('/value="([^"]+)"/', $secondToken, $secondMatches);

    $firstValue = $firstMatches[1];

    assert_true($firstValue !== $secondMatches[1], 'Setup: the two calls must produce different token values');
    assert_true(g2ml_validateCSRFToken($firstValue, 'shared_repeated_form_alpha'), 'The first copy of the form must still work after a second copy was shown');
});

test('CSRF #291: showing ONE form name twice keeps the SECOND token valid too, after the first is used', function (): void
{
    $firstToken  = g2ml_csrfField('shared_repeated_form_beta');
    $secondToken = g2ml_csrfField('shared_repeated_form_beta');

    preg_match('/value="([^"]+)"/', $firstToken, $firstMatches);
    preg_match('/value="([^"]+)"/', $secondToken, $secondMatches);

    assert_true(g2ml_validateCSRFToken($firstMatches[1], 'shared_repeated_form_beta'), 'The first copy works');
    assert_true(g2ml_validateCSRFToken($secondMatches[1], 'shared_repeated_form_beta'), 'Using the first copy must not use up the second');
    assert_false(g2ml_validateCSRFToken($firstMatches[1], 'shared_repeated_form_beta'), 'Each token still works once only');
});

test('CSRF fix pattern: namespacing the form name by a row/item ID keeps BOTH tokens independently valid', function (): void
{
    $rowOneToken = g2ml_csrfField('linkspage_item_delete_101');
    $rowTwoToken = g2ml_csrfField('linkspage_item_delete_202');

    preg_match('/value="([^"]+)"/', $rowOneToken, $rowOneMatches);
    preg_match('/value="([^"]+)"/', $rowTwoToken, $rowTwoMatches);

    $rowOneValue = $rowOneMatches[1];
    $rowTwoValue = $rowTwoMatches[1];

    assert_true(g2ml_validateCSRFToken($rowOneValue, 'linkspage_item_delete_101'), 'Row one\'s own-ID-namespaced token must still validate, even after row two\'s form rendered afterwards');
    assert_true(g2ml_validateCSRFToken($rowTwoValue, 'linkspage_item_delete_202'), 'Row two\'s own-ID-namespaced token must also validate');
});

// ============================================================================
// 🐛 #147 — Regression coverage for the multi-row-per-page CSRF fix
// ============================================================================
// The pages fixed for #147 (api-keys, links, org/domains) each render a
// per-row action form whose name is now namespaced by that row's own primary
// key (e.g. 'revoke_key_<apiKeyUID>', 'delete_link_<urlUID>',
// 'verify_domain_<domainUID>' / 'remove_domain_<domainUID>'), instead of
// reusing one shared form name across every row. The property those pages
// depend on is exactly the one already demonstrated generically above for
// 'linkspage_item_delete_101' / '_202': generating a token for one namespaced
// form name must NOT invalidate a token already issued for a DIFFERENT
// namespaced form name, even when the two forms are rendered back-to-back in
// the same request/response (as they are, once per table row). These tests
// pin that property using the ACTUAL form-name scheme adopted by the fixed
// pages, so a future regression in that scheme fails here directly.
// ============================================================================

test('#147 fix: api-keys revoke forms — two different apiKeyUID rows keep independently-valid tokens', function (): void
{
    $keyOneToken = g2ml_csrfField('revoke_key_1');
    $keyTwoToken = g2ml_csrfField('revoke_key_2');

    preg_match('/value="([^"]+)"/', $keyOneToken, $keyOneMatches);
    preg_match('/value="([^"]+)"/', $keyTwoToken, $keyTwoMatches);

    $keyOneValue = $keyOneMatches[1];
    $keyTwoValue = $keyTwoMatches[1];

    assert_true(g2ml_validateCSRFToken($keyOneValue, 'revoke_key_1'), 'Generating a token for revoke_key_2 must not invalidate revoke_key_1\'s token');
    assert_true(g2ml_validateCSRFToken($keyTwoValue, 'revoke_key_2'), 'revoke_key_2\'s own token must also still validate');
});

test('#147 fix: api-keys revoke vs create — distinct stable form name does not collide with per-row tokens', function (): void
{
    $revokeToken = g2ml_csrfField('revoke_key_5');
    $createToken = g2ml_csrfField('api_keys_create');

    preg_match('/value="([^"]+)"/', $revokeToken, $revokeMatches);
    preg_match('/value="([^"]+)"/', $createToken, $createMatches);

    assert_true(g2ml_validateCSRFToken($revokeMatches[1], 'revoke_key_5'), 'The per-row revoke token must still validate after the create form (a different form name) is rendered');
    assert_true(g2ml_validateCSRFToken($createMatches[1], 'api_keys_create'), 'The create form\'s own token must also validate');
});

test('#147 fix: links delete forms — two different urlUID rows keep independently-valid tokens', function (): void
{
    $linkOneToken = g2ml_csrfField('delete_link_10');
    $linkTwoToken = g2ml_csrfField('delete_link_20');

    preg_match('/value="([^"]+)"/', $linkOneToken, $linkOneMatches);
    preg_match('/value="([^"]+)"/', $linkTwoToken, $linkTwoMatches);

    assert_true(g2ml_validateCSRFToken($linkOneMatches[1], 'delete_link_10'), 'Generating a token for delete_link_20 must not invalidate delete_link_10\'s token');
    assert_true(g2ml_validateCSRFToken($linkTwoMatches[1], 'delete_link_20'), 'delete_link_20\'s own token must also still validate');
});

test('#147 fix: org/domains verify + remove forms — per-domainUID tokens for BOTH actions on the SAME row stay independent', function (): void
{
    // A single domain row renders TWO forms (verify + remove); each action
    // gets its own form-name prefix, so even same-row tokens must not collide.
    $verifyToken = g2ml_csrfField('verify_domain_7');
    $removeToken = g2ml_csrfField('remove_domain_7');

    preg_match('/value="([^"]+)"/', $verifyToken, $verifyMatches);
    preg_match('/value="([^"]+)"/', $removeToken, $removeMatches);

    assert_true(g2ml_validateCSRFToken($verifyMatches[1], 'verify_domain_7'), 'The verify form\'s token for domainUID 7 must still validate after the remove form (same row, different action) renders');
    assert_true(g2ml_validateCSRFToken($removeMatches[1], 'remove_domain_7'), 'The remove form\'s own token for domainUID 7 must also validate');
});

test('#147 fix: org/domains — two different domainUID rows keep independently-valid remove tokens', function (): void
{
    $domainOneToken = g2ml_csrfField('remove_domain_1');
    $domainTwoToken = g2ml_csrfField('remove_domain_2');

    preg_match('/value="([^"]+)"/', $domainOneToken, $domainOneMatches);
    preg_match('/value="([^"]+)"/', $domainTwoToken, $domainTwoMatches);

    assert_true(g2ml_validateCSRFToken($domainOneMatches[1], 'remove_domain_1'), 'Generating a token for remove_domain_2 must not invalidate remove_domain_1\'s token');
    assert_true(g2ml_validateCSRFToken($domainTwoMatches[1], 'remove_domain_2'), 'remove_domain_2\'s own token must also still validate');
});
