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
 * 🧪 Unit tests — session cookie Domain resolution (#217)
 * ============================================================================
 *
 * Pure, DB-free tests for g2ml_sessionCookieDomain() in
 * web/_functions/session.php. That function decides the Domain attribute
 * page_init.php Step 7 puts on the session cookie.
 *
 * WHY THIS MATTERS ENOUGH TO HAVE ITS OWN TEST FILE: before #217 the cookie
 * Domain was hard-coded to '.go2my.link' for every component in production.
 * A browser refuses a cookie whose Domain is not the current page's own host
 * or a parent of it, and go2my.link is not a parent of g2my.link or
 * lnks.page — they are separate registrable domains. So on those two hosts
 * the cookie was silently dropped, no session was ever kept, and anything
 * reading $_SESSION (the LinksPage age-gate's CSRF token check among them)
 * always failed, looping the visitor back to the interstitial forever. These
 * tests exist so that fault cannot come back unnoticed: they cover all four
 * real component domains plus a non-production environment, and are the
 * proof this fix actually changes the previously-wrong cases while leaving
 * the previously-right ones (go2my.link / admin.go2my.link) unchanged.
 *
 * session.php only DEFINES functions at include time (guarded direct-access,
 * no top-level side effects), so it is safe to require standalone here —
 * exactly like tests/unit/entitlements_test.php does for entitlements.php.
 * This file is NOT the place to prove a real browser accepts or rejects the
 * resulting cookie — that would need an actual HTTP round trip, which this
 * DB-free, browser-free unit runner cannot do. It has NOT been verified in a
 * real browser; only the code's own comparison logic is proven here.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#217)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/session.php';

// ----------------------------------------------------------------------------
// Production — the four real G2ML_COMPONENT_DOMAIN values in use today.
// ----------------------------------------------------------------------------

test('session cookie domain: production + go2my.link (Component A) shares the cookie', function (): void
{
    $result = g2ml_sessionCookieDomain('production', 'go2my.link');

    assert_same('.go2my.link', $result, 'The main site keeps the wide cookie it has always had');
});

test('session cookie domain: production + admin.go2my.link (Admin) shares the cookie', function (): void
{
    $result = g2ml_sessionCookieDomain('production', 'admin.go2my.link');

    assert_same('.go2my.link', $result, 'Admin must keep sharing the login cookie with the main site');
});

test('session cookie domain: production + g2my.link (Component B) is host-only', function (): void
{
    // This is the case that used to be wrong: g2my.link is a different
    // registrable domain from go2my.link, so a '.go2my.link' cookie would
    // be refused by the browser outright.
    $result = g2ml_sessionCookieDomain('production', 'g2my.link');

    assert_same('', $result, 'g2my.link must get a host-only cookie, not one scoped to go2my.link');
});

test('session cookie domain: production + lnks.page (Component C) is host-only', function (): void
{
    // The exact fault reported in #217: lnks.page never held a session in
    // production because the cookie was refused, which broke the age-gate
    // CSRF check and looped the confirmation page forever.
    $result = g2ml_sessionCookieDomain('production', 'lnks.page');

    assert_same('', $result, 'lnks.page must get a host-only cookie so the browser actually keeps it');
});

// ----------------------------------------------------------------------------
// Non-production — always host-only, regardless of component domain.
// ----------------------------------------------------------------------------

test('session cookie domain: development + go2my.link is host-only', function (): void
{
    // Non-production environments have always been host-only. Passing the
    // one domain that DOES get the wide cookie in production proves the
    // environment check is checked first, not the domain.
    $result = g2ml_sessionCookieDomain('development', 'go2my.link');

    assert_same('', $result, 'Non-production must never widen the cookie, even for go2my.link itself');
});

test('session cookie domain: alpha + lnks.page is host-only', function (): void
{
    $result = g2ml_sessionCookieDomain('alpha', 'lnks.page');

    assert_same('', $result, 'Alpha must stay host-only, matching every component today');
});

// ----------------------------------------------------------------------------
// Normalisation — case and surrounding whitespace must not change the answer.
// ----------------------------------------------------------------------------

test('session cookie domain: production + uppercase/padded "GO2MY.LINK " still shares the cookie', function (): void
{
    // G2ML_COMPONENT_DOMAIN is only ever set to a lowercase literal today,
    // but the comparison itself must not silently rely on that — this
    // proves the normalisation (strtolower + trim) actually runs.
    $result = g2ml_sessionCookieDomain('production', 'GO2MY.LINK ');

    assert_same('.go2my.link', $result, 'Case and whitespace must not change a domain that IS under go2my.link');
});

test('session cookie domain: production + an unrelated domain is host-only, not an error', function (): void
{
    // An unrecognised value (typo, future component) must fail SAFE to the
    // narrower, host-only cookie rather than widening it or throwing.
    $result = g2ml_sessionCookieDomain('production', 'example.com');

    assert_same('', $result, 'An unrecognised domain must fall through to the safe, host-only default');
});
