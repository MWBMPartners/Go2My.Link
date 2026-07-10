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
 * 🧪 Unit tests — LinksPage renderer pure helpers (Component C, #45)
 * ============================================================================
 *
 * Pure, DB-free tests for:
 *   - web/Lnks.page/_functions/linkspage_resolver.php — g2ml_linkspageIsValidSlug()
 *   - web/Lnks.page/_functions/linkspage_renderer.php — hex/font validation,
 *     escaping, href sanitisation, item/social rendering, the placeholder
 *     substitution engine, and the top-level g2ml_renderLinksPage().
 *
 * web/_functions/security.php is deliberately required FIRST so these tests
 * exercise the REAL g2ml_sanitiseOutput()/g2ml_sanitiseURL() integration (the
 * same functions index.php uses in production via page_init.php), not just
 * the DB-free fallback paths inside linkspage_renderer.php. Neither function
 * file touches a database — both resolver and renderer guard their DB-only
 * pieces (dbSelect/getSetting) behind function_exists()/isolated callers, and
 * this suite only exercises the pure helpers.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#45)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_resolver.php';
require_once dirname(__DIR__, 2) . '/web/Lnks.page/_functions/linkspage_renderer.php';

// ============================================================================
// 🔤 g2ml_linkspageIsValidSlug — charset/length guard
// ============================================================================

test('slug: alphanumeric with hyphens and underscores is valid', function (): void
{
    assert_true(g2ml_linkspageIsValidSlug('john-doe_92'), 'A normal slug shape must be accepted');
});

test('slug: empty string is invalid', function (): void
{
    assert_false(g2ml_linkspageIsValidSlug(''), 'An empty slug must be rejected');
});

test('slug: a slash (path traversal shape) is invalid', function (): void
{
    assert_false(g2ml_linkspageIsValidSlug('../etc/passwd'), 'A slug containing slashes must be rejected');
});

test('slug: a slug containing spaces is invalid', function (): void
{
    assert_false(g2ml_linkspageIsValidSlug('john doe'), 'A slug containing spaces must be rejected');
});

test('slug: exactly 100 characters is valid, 101 is not', function (): void
{
    $hundred     = str_repeat('a', 100);
    $hundredOne  = str_repeat('a', 101);

    assert_true(g2ml_linkspageIsValidSlug($hundred), 'A 100-character slug must be accepted (matches VARCHAR(100))');
    assert_false(g2ml_linkspageIsValidSlug($hundredOne), 'A 101-character slug must be rejected');
});

// ============================================================================
// 🎨 g2ml_linkspageValidateHexColour
// ============================================================================

test('hexColour: a 6-digit hex colour is valid', function (): void
{
    assert_same('#1e88e5', g2ml_linkspageValidateHexColour('#1e88e5'), 'A well-formed 6-digit hex colour must be accepted');
});

test('hexColour: a 3-digit hex colour is valid', function (): void
{
    assert_same('#fff', g2ml_linkspageValidateHexColour('#fff'), 'A well-formed 3-digit hex colour must be accepted');
});

test('hexColour: missing the leading # is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateHexColour('1e88e5'), 'A hex value without a leading # must be rejected');
});

test('hexColour: non-hex characters are rejected', function (): void
{
    assert_false(g2ml_linkspageValidateHexColour('#ZZZZZZ'), 'Non-hex characters must be rejected');
});

test('hexColour: a CSS-injection payload is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateHexColour('#fff; } body { display:none'), 'A CSS-breakout payload must be rejected outright');
});

test('hexColour: null is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateHexColour(null), 'A null colour must be rejected');
});

// ============================================================================
// 🔤 g2ml_linkspageValidateFontFamily
// ============================================================================

test('fontFamily: a normal font stack is valid', function (): void
{
    assert_same('"Segoe UI", Roboto, sans-serif', g2ml_linkspageValidateFontFamily('"Segoe UI", Roboto, sans-serif'), 'A normal quoted font stack must be accepted');
});

test('fontFamily: a semicolon (rule-breakout attempt) is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateFontFamily('Arial; } body { background:red'), 'A semicolon must be rejected — it could close the declaration');
});

test('fontFamily: braces (rule-injection attempt) are rejected', function (): void
{
    assert_false(g2ml_linkspageValidateFontFamily('Arial} .evil{color:red'), 'Braces must be rejected — they could inject a new rule');
});

test('fontFamily: a url() expression is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateFontFamily('Arial, url(javascript:alert(1))'), 'Parentheses must be rejected — url(...) can never form');
});

test('fontFamily: an expression() payload is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateFontFamily('expression(alert(1))'), 'Parentheses must be rejected — expression(...) can never form');
});

test('fontFamily: an empty string is rejected', function (): void
{
    assert_false(g2ml_linkspageValidateFontFamily(''), 'An empty font family must be rejected');
});

// ============================================================================
// 🧹 g2ml_linkspageEscape / g2ml_linkspageSanitiseHref (real security.php integration)
// ============================================================================

test('escape: an XSS payload is HTML-escaped', function (): void
{
    assert_same('&lt;script&gt;alert(1)&lt;/script&gt;', g2ml_linkspageEscape('<script>alert(1)</script>'), 'A <script> payload must be fully escaped');
});

test('escape: null becomes an empty string', function (): void
{
    assert_same('', g2ml_linkspageEscape(null), 'A null value must escape to an empty string');
});

test('sanitiseHref: a javascript: URL is rejected', function (): void
{
    assert_false(g2ml_linkspageSanitiseHref('javascript:alert(1)'), 'A javascript: URL must be rejected');
});

test('sanitiseHref: a data: URL is rejected', function (): void
{
    assert_false(g2ml_linkspageSanitiseHref('data:text/html,<script>alert(1)</script>'), 'A data: URL must be rejected');
});

test('sanitiseHref: a benign https URL is accepted', function (): void
{
    assert_same('https://example.com/x', g2ml_linkspageSanitiseHref('https://example.com/x'), 'A benign https URL must be accepted unchanged');
});

test('sanitiseHref: null is rejected', function (): void
{
    assert_false(g2ml_linkspageSanitiseHref(null), 'A null URL must be rejected');
});

// ============================================================================
// 🖼️ Avatar / icon fallbacks
// ============================================================================

test('avatarSrc: null avatarPath falls back to the default avatar', function (): void
{
    assert_same(g2ml_linkspageDefaultAvatarSrc(), g2ml_linkspageResolveAvatarSrc(null), 'A null avatarPath must fall back to the default avatar');
});

test('avatarSrc: an unsafe scheme falls back to the default avatar', function (): void
{
    assert_same(g2ml_linkspageDefaultAvatarSrc(), g2ml_linkspageResolveAvatarSrc('javascript:alert(1)'), 'An unsafe avatarPath must fall back to the default avatar');
});

test('avatarSrc: a benign https avatarPath is used as-is', function (): void
{
    assert_same('https://example.com/me.png', g2ml_linkspageResolveAvatarSrc('https://example.com/me.png'), 'A benign avatarPath must be used unchanged');
});

test('itemIcon: with no itemIcon/faviconCacheURL, the default icon data: URI is used', function (): void
{
    $iconHTML = g2ml_linkspageRenderItemIcon(['itemIcon' => null, 'faviconCacheURL' => null]);
    assert_contains(g2ml_linkspageDefaultItemIconSrc(), $iconHTML, 'The default icon must be used when no icon is stored');
});

// ============================================================================
// 🔗 g2ml_linkspageRenderItem — one item's <a> markup
// ============================================================================

test('renderItem: a benign item renders title and href', function (): void
{
    $html = g2ml_linkspageRenderItem([
        'itemTitle'       => 'My Website',
        'itemURL'         => 'https://example.com',
        'itemDescription' => null,
        'itemIcon'        => null,
        'faviconCacheURL' => null,
        'requiresAgeGate' => 0,
    ]);

    assert_contains('href="https://example.com"', $html, 'A benign item must render its href');
    assert_contains('My Website', $html, 'A benign item must render its title');
});

test('renderItem: a javascript: itemURL yields an empty string (item omitted)', function (): void
{
    $html = g2ml_linkspageRenderItem([
        'itemTitle'       => 'Evil Link',
        'itemURL'         => 'javascript:alert(1)',
        'itemDescription' => null,
        'itemIcon'        => null,
        'faviconCacheURL' => null,
        'requiresAgeGate' => 0,
    ]);

    assert_same('', $html, 'An item with an unsafe URL must render as nothing at all');
});

test('renderItem: an XSS payload in itemTitle is escaped', function (): void
{
    $html = g2ml_linkspageRenderItem([
        'itemTitle'       => '<b onmouseover=alert(1)>Hover</b>',
        'itemURL'         => 'https://example.com',
        'itemDescription' => null,
        'itemIcon'        => null,
        'faviconCacheURL' => null,
        'requiresAgeGate' => 0,
    ]);

    assert_false(str_contains($html, '<b onmouseover=alert(1)>'), 'The raw payload must never appear');
    assert_contains('&lt;b onmouseover=alert(1)&gt;', $html, 'The payload must be escaped');
});

test('renderItem: requiresAgeGate=1 adds the data-age-gate hook but still renders', function (): void
{
    $html = g2ml_linkspageRenderItem([
        'itemTitle'       => 'Adult Site',
        'itemURL'         => 'https://example.com/adult',
        'itemDescription' => null,
        'itemIcon'        => null,
        'faviconCacheURL' => null,
        'requiresAgeGate' => 1,
    ]);

    assert_contains('data-age-gate="1"', $html, 'A requiresAgeGate item must carry the C.5 hook attribute');
    assert_contains('Adult Site', $html, 'C.1 renders ALL active items unconditionally — age-gating is C.5, not built here');
});

// ============================================================================
// 📢 g2ml_linkspageRenderSocial — allowlisted social icons
// ============================================================================

test('renderSocial: showSocialIcons=false renders nothing, even with valid links', function (): void
{
    $html = g2ml_linkspageRenderSocial(json_encode(['twitter' => 'https://twitter.com/example']), false);
    assert_same('', $html, 'showSocialIcons=false must suppress all social icons');
});

test('renderSocial: an allowlisted network with a benign URL renders', function (): void
{
    $html = g2ml_linkspageRenderSocial(json_encode(['twitter' => 'https://twitter.com/example']), true);
    assert_contains('https://twitter.com/example', $html, 'An allowlisted network with a safe URL must render');
});

test('renderSocial: a non-allowlisted network key is silently skipped', function (): void
{
    $html = g2ml_linkspageRenderSocial(json_encode(['myspace' => 'https://myspace.com/example']), true);
    assert_same('', $html, 'A network not on the allowlist must never render');
});

test('renderSocial: a javascript: social URL is rejected', function (): void
{
    $html = g2ml_linkspageRenderSocial(json_encode(['twitter' => 'javascript:alert(1)']), true);
    assert_same('', $html, 'A social URL with an unsafe scheme must never render');
});

test('renderSocial: malformed JSON renders nothing', function (): void
{
    $html = g2ml_linkspageRenderSocial('{not valid json', true);
    assert_same('', $html, 'Malformed socialLinks JSON must never throw or render anything');
});

// ============================================================================
// 🧩 g2ml_linkspageApplyTemplate — the 7-placeholder substitution engine
// ============================================================================

test('applyTemplate: all 7 placeholders are substituted, none left behind', function (): void
{
    $template = '{{avatar}}|{{name}}|{{bio}}|{{links}}|{{social}}|{{theme}}|{{background}}';

    $result = g2ml_linkspageApplyTemplate($template, [
        'avatar'     => 'A',
        'name'       => 'B',
        'bio'        => 'C',
        'links'      => 'D',
        'social'     => 'E',
        'theme'      => 'F',
        'background' => 'G',
    ]);

    assert_same('A|B|C|D|E|F|G', $result, 'Every placeholder must be substituted with its corresponding value');
});

test('applyTemplate: a value that itself contains a placeholder-shaped string is not re-substituted', function (): void
{
    // strtr() performs a single left-to-right pass over the SOURCE only — a
    // substituted VALUE containing "{{name}}" must not be re-scanned.
    $template = '{{bio}}{{name}}';

    $result = g2ml_linkspageApplyTemplate($template, [
        'bio'  => '{{name}}',
        'name' => 'RealName',
    ]);

    assert_same('{{name}}RealName', $result, 'A substituted value must never be re-scanned for further placeholder substitution');
});

// ============================================================================
// 🚀 g2ml_renderLinksPage — full document assembly (pure, no DB)
// ============================================================================

test('renderLinksPage: a synthetic page model renders a complete, escaped document', function (): void
{
    $pageModel = [
        'page' => [
            'pageTitle'        => 'Jane Doe',
            'pageDescription'  => "Line one\nLine two",
            'avatarPath'       => null,
            'themeColour'      => '#123abc',
            'backgroundColour' => '#ffffff',
            'fontFamily'       => null,
            'showSocialIcons'  => 0,
            'socialLinks'      => null,
            // No customHTML on this model — this test exercises the SYSTEM
            // template path (C.1). The C.6 (#49) custom-HTML path is only ever
            // taken when the resolver ATTACHES permitted, gated customHTML to
            // the model (see linkspage_resolver.php's
            // _g2ml_linkspageMaybeAttachCustomHtml() and the integration tests
            // in tests/integration/linkspage_custom_html_test.php).
        ],
        'template' => [
            'templateHTML' => '<div class="lp-x"><img src="{{avatar}}" alt="{{name}}"><h1>{{name}}</h1><p>{{bio}}</p><div>{{links}}</div><div>{{social}}</div></div>',
            'templateCSS'  => '.lp-x { background: {{background}}; color: {{theme}}; }',
        ],
        'items' => [
            ['itemUID' => 1, 'itemTitle' => 'Link One', 'itemURL' => 'https://example.com/one', 'itemDescription' => null, 'itemIcon' => null, 'faviconCacheURL' => null, 'requiresAgeGate' => 0],
        ],
    ];

    $html = g2ml_renderLinksPage($pageModel);

    assert_contains('<!DOCTYPE html>', $html, 'A full HTML document must be returned');
    assert_contains('Jane Doe', $html, 'The name must appear');
    assert_contains('Line one<br', $html, 'The bio must be nl2br-converted');
    assert_contains('#123abc', $html, 'The valid theme colour must be injected');
    assert_contains('Link One', $html, 'The item must render');
    assert_contains('https://example.com/one', $html, 'The item URL must render as an href');
});

// ============================================================================
// 🧨 C.6 (#49) — custom-HTML render path (renderer contract)
// ============================================================================
// NB: tests/unit/html_sanitiser_test.php loads web/_functions/html_sanitiser.php,
// so g2ml_sanitiseUserHTML() IS defined when the whole unit suite runs — which
// is exactly the production condition under which the renderer takes the custom
// path. These tests assert the renderer's contract: when a model carries
// PERMITTED custom HTML (the resolver only attaches it when authorised), it is
// rendered (re-sanitised) INSTEAD OF the system template.

test('renderLinksPage: a model with permitted customHTML renders the sanitised custom document, not the system template', function (): void
{
    if (!function_exists('g2ml_sanitiseUserHTML'))
    {
        // Defensive: without the sanitiser loaded the renderer must fall back
        // to the system template (safe). Nothing to assert about the custom
        // path in that configuration.
        return;
    }

    $pageModel = [
        'page' => [
            'pageTitle'       => 'Custom Jane',
            'pageDescription' => 'bio',
            'customHTML'      => '<h1>Hello</h1><script>alert(1)</script><p>World</p>',
            'customCSS'       => 'h1 { color: #ff0000; } .x { background: url(javascript:alert(1)); }',
        ],
        'template' => [
            'templateHTML' => '<div class="SYSTEM-TEMPLATE-MARKER">{{name}}</div>',
            'templateCSS'  => '.system {}',
        ],
        'items' => [],
    ];

    assert_true(g2ml_linkspageModelUsesCustomHTML($pageModel), 'A non-empty customHTML in the model signals the custom path');

    $html = g2ml_renderLinksPage($pageModel);

    assert_contains('<!DOCTYPE html>', $html, 'A full document is returned');
    assert_contains('<h1>Hello</h1>', $html, 'The benign custom HTML is rendered');
    assert_contains('World', $html, 'Custom body text is rendered');
    assert_false(str_contains($html, 'SYSTEM-TEMPLATE-MARKER'), 'The system template must NOT be used when custom HTML is present');
    assert_false(str_contains($html, '<script'), 'The re-sanitiser strips <script on output');
    assert_false(str_contains($html, 'javascript:'), 'The CSS re-sanitiser strips url(javascript:) on output');
});

test('renderLinksPage: an empty customHTML falls back to the system template', function (): void
{
    $pageModel = [
        'page' => [
            'pageTitle'  => 'Jane',
            'customHTML' => '   ',
        ],
        'template' => [
            'templateHTML' => '<div class="SYSTEM-TEMPLATE-MARKER">{{name}}</div>',
            'templateCSS'  => '',
        ],
        'items' => [],
    ];

    assert_false(g2ml_linkspageModelUsesCustomHTML($pageModel), 'A blank customHTML does not trigger the custom path');

    $html = g2ml_renderLinksPage($pageModel);
    assert_contains('SYSTEM-TEMPLATE-MARKER', $html, 'The system template is used when customHTML is blank');
});

test('renderLinksPage: an invalid theme/background colour falls back to the safe defaults', function (): void
{
    $pageModel = [
        'page' => [
            'pageTitle'        => 'Bad Colours',
            'pageDescription'  => null,
            'avatarPath'       => null,
            'themeColour'      => 'not-hex',
            'backgroundColour' => 'also-not-hex',
            'fontFamily'       => null,
            'showSocialIcons'  => 0,
            'socialLinks'      => null,
        ],
        'template' => [
            'templateHTML' => '<div style="background:{{background}};color:{{theme}}">{{name}}{{links}}{{social}}{{avatar}}{{bio}}</div>',
            'templateCSS'  => '',
        ],
        'items' => [],
    ];

    $html = g2ml_renderLinksPage($pageModel);

    assert_false(str_contains($html, 'not-hex'), 'An invalid theme colour must never be injected');
    assert_false(str_contains($html, 'also-not-hex'), 'An invalid background colour must never be injected');
    assert_contains(g2ml_linkspageDefaultThemeColour(), $html, 'The safe default theme colour must be used');
    assert_contains(g2ml_linkspageDefaultBackgroundColour(), $html, 'The safe default background colour must be used');
});
