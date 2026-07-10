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
 * 🧪 Unit tests — LinksPage age verification (Component C.5, #50)
 * ============================================================================
 *
 * Pure, DB-free tests for:
 *   - web/_functions/adult_content.php — g2ml_isAdultDomain(), the signed
 *     age-gate token build/verify pair, and the cookie read/write helpers.
 *   - web/Lnks.page/_functions/linkspage_resolver.php —
 *     g2ml_linkspageHasAgeGatedItems().
 *   - web/Lnks.page/_functions/linkspage_renderer.php —
 *     g2ml_linkspageRenderAgeGateInterstitial().
 *
 * web/_functions/security.php is loaded FIRST (mirroring
 * linkspage_render_test.php) so g2ml_linkspageEscape()/g2ml_csrfField()
 * exercise the REAL integration, not a fallback. ENCRYPTION_KEY_SECONDARY is
 * defined by tests/bootstrap.php before this file is required.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#50)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/adult_content.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_resolver.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_renderer.php';

// ============================================================================
// 🌐 g2ml_isAdultDomain — curated allowlist matching (no DB — falls back to
// the built-in default list, since getSetting() is not loaded here).
// ============================================================================

test('isAdultDomain: an exact match against the curated default list is true', function (): void
{
    assert_true(g2ml_isAdultDomain('https://onlyfans.com/someuser'), 'A known adult domain must match');
});

test('isAdultDomain: a subdomain of a curated domain matches', function (): void
{
    assert_true(g2ml_isAdultDomain('https://cams.chaturbate.com/room'), 'A subdomain of a curated adult domain must match');
});

test('isAdultDomain: a www. prefix is stripped before matching', function (): void
{
    assert_true(g2ml_isAdultDomain('https://www.pornhub.com/somepage'), 'A www.-prefixed curated domain must still match');
});

test('isAdultDomain: an unrelated domain does not match', function (): void
{
    assert_false(g2ml_isAdultDomain('https://example.com/page'), 'An unrelated domain must never match');
});

test('isAdultDomain: null is rejected without matching', function (): void
{
    assert_false(g2ml_isAdultDomain(null), 'A null URL must never match');
});

test('isAdultDomain: a URL with no parseable host does not match, and never throws', function (): void
{
    assert_false(g2ml_isAdultDomain('not a url at all'), 'A malformed/schemeless value must never match');
});

test('isAdultDomain: a domain that merely CONTAINS a curated domain as a substring (not a subdomain) does not match', function (): void
{
    assert_false(g2ml_isAdultDomain('https://notonlyfans.com/x'), 'A look-alike domain that is not an actual subdomain must not match');
});

// ============================================================================
// 🔏 g2ml_ageGateBuildToken / g2ml_ageGateVerifyToken — signed round trip
// ============================================================================

test('ageGate token: a freshly built token verifies as valid', function (): void
{
    $token = g2ml_ageGateBuildToken(time() + 3600);
    assert_true(g2ml_ageGateVerifyToken($token), 'A freshly built, unexpired token must verify');
});

test('ageGate token: an expired token is rejected even with a correct signature', function (): void
{
    $expiredToken = g2ml_ageGateBuildToken(time() - 10);
    assert_false(g2ml_ageGateVerifyToken($expiredToken), 'An expired token must be rejected, even though its signature is valid');
});

test('ageGate token: a tampered expiry (signature no longer matches) is rejected', function (): void
{
    $token = g2ml_ageGateBuildToken(time() + 3600);
    $parts = explode('.', $token);

    // Bump the expiry forward by a huge amount without re-signing — the
    // signature was computed over the ORIGINAL expiry string.
    $forgedToken = $parts[0] . '.' . ((string) ((int) $parts[1] + 999999999)) . '.' . $parts[2];

    assert_false(g2ml_ageGateVerifyToken($forgedToken), 'A tampered expiry must invalidate the signature');
});

test('ageGate token: a tampered signature is rejected', function (): void
{
    $token = g2ml_ageGateBuildToken(time() + 3600);
    $parts = explode('.', $token);

    $forgedToken = $parts[0] . '.' . $parts[1] . '.' . 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

    assert_false(g2ml_ageGateVerifyToken($forgedToken), 'A wrong signature must be rejected');
});

test('ageGate token: a wrong version prefix is rejected', function (): void
{
    $forgedToken = 'v99.' . (string) (time() + 3600) . '.' . str_repeat('a', 64);
    assert_false(g2ml_ageGateVerifyToken($forgedToken), 'A token with the wrong version must be rejected outright');
});

test('ageGate token: a malformed token (wrong number of parts) is rejected', function (): void
{
    assert_false(g2ml_ageGateVerifyToken('not-a-real-token'), 'A malformed token must be rejected, never throw');
    assert_false(g2ml_ageGateVerifyToken('v1.123'), 'A token missing its signature part must be rejected');
});

test('ageGate token: a non-numeric expiry is rejected', function (): void
{
    $forgedToken = 'v1.not-a-number.' . str_repeat('a', 64);
    assert_false(g2ml_ageGateVerifyToken($forgedToken), 'A non-numeric expiry must be rejected');
});

test('ageGate token: null and empty strings are rejected', function (): void
{
    assert_false(g2ml_ageGateVerifyToken(null), 'A null token must be rejected');
    assert_false(g2ml_ageGateVerifyToken(''), 'An empty token must be rejected');
});

// ============================================================================
// 🍪 g2ml_ageGateHasValidCookie — reads $_COOKIE, delegates to verify
// ============================================================================

test('ageGate cookie: a valid signed cookie value unlocks', function (): void
{
    $_COOKIE[G2ML_AGEGATE_COOKIE_NAME] = g2ml_ageGateBuildToken(time() + 3600);
    assert_true(g2ml_ageGateHasValidCookie(), 'A valid signed cookie must report as unlocked');
    unset($_COOKIE[G2ML_AGEGATE_COOKIE_NAME]);
});

test('ageGate cookie: a forged cookie value does NOT unlock', function (): void
{
    $_COOKIE[G2ML_AGEGATE_COOKIE_NAME] = 'v1.' . (string) (time() + 3600) . '.' . str_repeat('f', 64);
    assert_false(g2ml_ageGateHasValidCookie(), 'A forged cookie value must never unlock the gate');
    unset($_COOKIE[G2ML_AGEGATE_COOKIE_NAME]);
});

test('ageGate cookie: no cookie at all does NOT unlock', function (): void
{
    unset($_COOKIE[G2ML_AGEGATE_COOKIE_NAME]);
    assert_false(g2ml_ageGateHasValidCookie(), 'An absent cookie must never unlock the gate');
});

// ============================================================================
// 🔞 g2ml_linkspageHasAgeGatedItems — whole-page gate detection
// ============================================================================

test('hasAgeGatedItems: a page with one gated item reports true', function (): void
{
    $items = [
        ['itemTitle' => 'Normal Link', 'requiresAgeGate' => 0],
        ['itemTitle' => 'Adult Link', 'requiresAgeGate' => 1],
    ];

    assert_true(g2ml_linkspageHasAgeGatedItems($items), 'A page with at least one gated item must report true');
});

test('hasAgeGatedItems: a page with no gated items reports false', function (): void
{
    $items = [
        ['itemTitle' => 'Normal Link One', 'requiresAgeGate' => 0],
        ['itemTitle' => 'Normal Link Two', 'requiresAgeGate' => 0],
    ];

    assert_false(g2ml_linkspageHasAgeGatedItems($items), 'A page with no gated items must report false — normal pages are unaffected');
});

test('hasAgeGatedItems: an empty items array reports false', function (): void
{
    assert_false(g2ml_linkspageHasAgeGatedItems([]), 'A page with no items at all must report false');
});

// ============================================================================
// 🖥️ g2ml_linkspageRenderAgeGateInterstitial — the good-faith gate markup
// ============================================================================

test('renderAgeGateInterstitial: contains the heading, a same-origin form, and a fixed leave link — no item data at all', function (): void
{
    $_SESSION = [];
    $html = g2ml_linkspageRenderAgeGateInterstitial('example-slug');

    assert_contains('<!DOCTYPE html>', $html, 'A full HTML document must be returned');
    assert_contains('Age Verification Required', $html, 'The heading must appear');
    assert_contains('<main', $html, 'A <main> landmark must be present (WCAG)');
    assert_contains('aria-labelledby="lp-agegate-heading"', $html, 'The main landmark must be labelled by the visible heading');
    assert_contains('method="POST"', $html, 'The confirm action must be a POST form');
    assert_contains('action="/example-slug"', $html, 'The form must post back to the SAME slug (same-origin)');
    assert_contains('name="g2ml_agegate_confirm"', $html, 'The confirm marker field must be present');
    assert_contains('name="_csrf_token"', $html, 'A CSRF token field must be present (session-backed, same mechanism as the rest of the app)');
    assert_contains('href="https://go2my.link"', $html, 'The Leave action must be a FIXED URL, never derived from request input');
    assert_false(str_contains($html, '<script'), 'No inline/external <script> is permitted — this component ships zero JS');
});

test('renderAgeGateInterstitial: the slug is escaped in the form action', function (): void
{
    $_SESSION = [];
    $html = g2ml_linkspageRenderAgeGateInterstitial('<script>alert(1)</script>');

    assert_false(str_contains($html, '<script>alert(1)</script>'), 'A raw script payload in the slug must never appear unescaped');
});
