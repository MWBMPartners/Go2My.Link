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
 * 🧪 Unit tests — LinksPage management pure validators (Component C.2, #48)
 * ============================================================================
 *
 * Pure, DB-free tests for the validators in web/_functions/linkspage_manage.php:
 *   - g2ml_linkspageManageIsValidSlug()
 *   - g2ml_linkspageManageValidateHexColour()
 *   - g2ml_linkspageManageValidateFontFamily()
 *   - g2ml_linkspageManageValidateSocialLinks()
 *
 * These are DELIBERATE mirrors of the public renderer's own validators
 * (web/Lnks.page/_functions/linkspage_resolver.php /
 * linkspage_renderer.php — see #45's tests/unit/linkspage_render_test.php).
 * Both files are loaded here TOGETHER so this suite can directly cross-check
 * parity: any value the management layer accepts must also be accepted (and
 * behave identically) in the public renderer, and vice versa.
 *
 * web/_functions/security.php is loaded FIRST (mirroring
 * linkspage_render_test.php) so g2ml_linkspageManageValidateSocialLinks()
 * exercises the REAL g2ml_sanitiseURL() integration, not a fallback.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#48)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_resolver.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_renderer.php';

// linkspage_manage.php's direct-access guard + dbSelect()/dbSelectOne() calls
// (in functions this suite does not exercise) mean it is safe to load here
// without a database connection — only the pure validator functions below
// are invoked, and PHP does not execute a function body until it is called.
require_once dirname(__DIR__, 2) . '/web/_functions/linkspage_manage.php';

// ============================================================================
// 🔤 g2ml_linkspageManageIsValidSlug — parity with the public resolver
// ============================================================================

test('manage slug: alphanumeric with hyphens and underscores is valid', function (): void
{
    assert_true(g2ml_linkspageManageIsValidSlug('jane-doe_92'), 'A normal slug shape must be accepted');
});

test('manage slug: empty string is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug(''), 'An empty slug must be rejected');
});

test('manage slug: a slash (path traversal shape) is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug('../etc/passwd'), 'A slug containing slashes must be rejected');
});

test('manage slug: a slug containing spaces is invalid', function (): void
{
    assert_false(g2ml_linkspageManageIsValidSlug('jane doe'), 'A slug containing spaces must be rejected');
});

test('manage slug: exactly 100 characters is valid, 101 is not', function (): void
{
    $hundred    = str_repeat('a', 100);
    $hundredOne = str_repeat('a', 101);

    assert_true(g2ml_linkspageManageIsValidSlug($hundred), 'A 100-character slug must be accepted (matches VARCHAR(100))');
    assert_false(g2ml_linkspageManageIsValidSlug($hundredOne), 'A 101-character slug must be rejected');
});

test('manage slug: parity — every value accepted here is accepted by the public resolver, and vice versa', function (): void
{
    $samples = ['jane-doe_92', '', '../etc/passwd', 'jane doe', str_repeat('a', 100), str_repeat('a', 101), 'A1_b-2'];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageIsValidSlug($sample),
            g2ml_linkspageManageIsValidSlug($sample),
            'Slug validation must agree between the manage layer and the public resolver for: ' . $sample
        );
    }
});

// ============================================================================
// 🎨 g2ml_linkspageManageValidateHexColour — parity with the public renderer
// ============================================================================

test('manage hexColour: a 6-digit hex colour is valid', function (): void
{
    assert_same('#1e88e5', g2ml_linkspageManageValidateHexColour('#1e88e5'), 'A well-formed 6-digit hex colour must be accepted');
});

test('manage hexColour: a 3-digit hex colour is valid', function (): void
{
    assert_same('#fff', g2ml_linkspageManageValidateHexColour('#fff'), 'A well-formed 3-digit hex colour must be accepted');
});

test('manage hexColour: missing the leading # is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour('1e88e5'), 'A hex value without a leading # must be rejected');
});

test('manage hexColour: a CSS-injection payload is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour('#fff; } body { display:none'), 'A CSS-breakout payload must be rejected outright');
});

test('manage hexColour: null is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateHexColour(null), 'A null colour must be rejected');
});

test('manage hexColour: parity with the public renderer', function (): void
{
    $samples = ['#1e88e5', '#fff', '1e88e5', '#ZZZZZZ', '#fff; } body { display:none', null];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageValidateHexColour($sample),
            g2ml_linkspageManageValidateHexColour($sample),
            'Hex colour validation must agree between the manage layer and the public renderer'
        );
    }
});

// ============================================================================
// 🔤 g2ml_linkspageManageValidateFontFamily — parity with the public renderer
// ============================================================================

test('manage fontFamily: a normal font stack is valid', function (): void
{
    assert_same('"Segoe UI", Roboto, sans-serif', g2ml_linkspageManageValidateFontFamily('"Segoe UI", Roboto, sans-serif'), 'A normal quoted font stack must be accepted');
});

test('manage fontFamily: a semicolon (rule-breakout attempt) is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily('Arial; } body { background:red'), 'A semicolon must be rejected — it could close the declaration');
});

test('manage fontFamily: a url() expression is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily('Arial, url(javascript:alert(1))'), 'Parentheses must be rejected — url(...) can never form');
});

test('manage fontFamily: an empty string is rejected', function (): void
{
    assert_false(g2ml_linkspageManageValidateFontFamily(''), 'An empty font family must be rejected');
});

test('manage fontFamily: over 100 characters is rejected', function (): void
{
    $tooLong = str_repeat('a', 101);
    assert_false(g2ml_linkspageManageValidateFontFamily($tooLong), 'A font family over 100 characters must be rejected');
});

test('manage fontFamily: parity with the public renderer', function (): void
{
    $samples = ['"Segoe UI", Roboto, sans-serif', 'Arial; } body { background:red', 'Arial, url(javascript:alert(1))', '', str_repeat('a', 101)];

    foreach ($samples as $sample)
    {
        assert_same(
            g2ml_linkspageValidateFontFamily($sample),
            g2ml_linkspageManageValidateFontFamily($sample),
            'Font-family validation must agree between the manage layer and the public renderer'
        );
    }
});

// ============================================================================
// 📢 g2ml_linkspageManageValidateSocialLinks — closed allowlist + URL scheme
// ============================================================================

test('manage socialLinks: an allowlisted network with a benign https URL is kept', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => 'https://twitter.com/example']);
    assert_same(['twitter' => 'https://twitter.com/example'], $result, 'A benign, allowlisted network URL must be kept as-is');
});

test('manage socialLinks: a non-allowlisted network key is silently dropped', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['myspace' => 'https://myspace.com/example']);
    assert_same([], $result, 'A network key not on the allowlist must never be kept');
});

test('manage socialLinks: a javascript: URL is dropped, not saved', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => 'javascript:alert(1)']);
    assert_same([], $result, 'An unsafe scheme must be dropped rather than stored');
});

test('manage socialLinks: a blank value for an allowlisted key is dropped', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks(['twitter' => '   ']);
    assert_same([], $result, 'A blank/whitespace-only value must be dropped');
});

test('manage socialLinks: multiple allowlisted networks are all kept independently', function (): void
{
    $result = g2ml_linkspageManageValidateSocialLinks([
        'twitter'   => 'https://twitter.com/example',
        'instagram' => 'https://instagram.com/example',
        'myspace'   => 'https://myspace.com/example',
        'facebook'  => 'javascript:alert(1)',
    ]);

    assert_same(
        ['twitter' => 'https://twitter.com/example', 'instagram' => 'https://instagram.com/example'],
        $result,
        'Only the safe, allowlisted entries must survive validation'
    );
});

test('manage socialLinks: an empty input array yields an empty result', function (): void
{
    assert_same([], g2ml_linkspageManageValidateSocialLinks([]), 'An empty submission must validate to an empty array');
});
