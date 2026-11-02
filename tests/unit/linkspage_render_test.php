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
require_once dirname(__DIR__, 2) . '/web/_functions/html_sanitiser.php';
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

test('itemIcon: with no itemIcon/faviconCacheURL, the default icon is an inline SVG that takes the link text colour', function (): void
{
    $iconHTML = g2ml_linkspageRenderItemIcon(['itemIcon' => null, 'faviconCacheURL' => null]);

    assert_same(g2ml_linkspageDefaultItemIconHTML(), $iconHTML, 'The default icon must be used when no icon is stored');
    assert_true(str_starts_with($iconHTML, '<svg class="lp-link-icon"'), 'The default icon must be inline SVG, not an <img>');
    assert_contains('stroke="currentColor"', $iconHTML, 'The icon must be drawn in the surrounding text colour');
    assert_contains('aria-hidden="true"', $iconHTML, 'The icon is decoration, so it must be hidden from screen readers');
    assert_false(str_contains($iconHTML, '<img'), 'An <img> cannot read the text colour and is drawn black');
    assert_false(str_contains($iconHTML, 'data:'), 'The default icon needs no data: address');
});

test('itemIcon: a stored icon address is still shown as a picture, hidden from screen readers', function (): void
{
    $iconHTML = g2ml_linkspageRenderItemIcon(['itemIcon' => 'https://example.com/icon.png', 'faviconCacheURL' => null]);

    assert_contains('<img class="lp-link-icon" src="https://example.com/icon.png" alt="" aria-hidden="true"', $iconHTML, 'A stored icon must be shown as an <img> with an empty alt text');
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


// ============================================================================
// 🖼️ #287 — the default avatar is a square picture, and decoration only
// ============================================================================

test('defaultAvatar: it is an inline, square SVG showing the first letter of the name, not the logo', function (): void
{
    $src = g2ml_linkspageDefaultAvatarSrc('jane doe');

    assert_true(str_starts_with($src, 'data:image/svg+xml;base64,'), 'The default avatar must be an inline picture, so there is no file to find');
    assert_false(str_contains($src, 'logo.png'), 'The site logo (white on a transparent background) is not an avatar');

    $svg = (string) base64_decode(substr($src, strlen('data:image/svg+xml;base64,')), true);

    assert_same(1, preg_match('/viewBox="0 0 (\d+) (\d+)" width="(\d+)" height="(\d+)"/', $svg, $size), 'The picture must state its size');
    assert_same($size[1], $size[2], 'The picture must be square');
    assert_same($size[3], $size[4], 'The picture must be square');
    assert_contains('>J<', $svg, 'The first letter, capitalised, must be shown');
});

test('defaultAvatar: the letter is white on a colour that reaches 4.5:1', function (): void
{
    $svg = (string) base64_decode(substr(g2ml_linkspageDefaultAvatarSrc('Zed'), strlen('data:image/svg+xml;base64,')), true);

    assert_same(1, preg_match('/<rect width="96" height="96" fill="(#[0-9A-Fa-f]{6})"\/>/', $svg, $rect), 'The square must be filled with a colour');
    assert_same(1, preg_match('/fill="(#[0-9A-Fa-f]{6})">Z</', $svg, $letter), 'The letter must have a colour');

    $ratio = g2ml_linkspageContrastRatio(g2ml_linkspageHexToRgb($letter[1]), g2ml_linkspageHexToRgb($rect[1]));

    assert_true($ratio >= 4.5, 'The letter on the square is ' . round($ratio, 2) . ':1');
});

test('defaultAvatar: a name with no letter or digit first gets a head-and-shoulders shape, and markup in a name is never copied in', function (): void
{
    foreach (['', '   ', '!!!', '<script>alert(1)</script>', '"><svg onload=alert(1)>'] as $name)
    {
        $svg = (string) base64_decode(substr(g2ml_linkspageDefaultAvatarSrc($name), strlen('data:image/svg+xml;base64,')), true);

        assert_contains('<circle', $svg, 'A name starting with "' . $name . '" has no usable first letter, so it must show a plain shape');
        assert_false(str_contains($svg, '<text'), 'No letter is drawn when there is none');
        assert_false(str_contains($svg, 'alert'), 'Nothing from the name may reach the picture');
        assert_false(str_contains($svg, 'onload'), 'Nothing from the name may reach the picture');
    }
});

test('defaultAvatar: a letter with an accent, a digit and a non-Latin letter are kept, capitalised', function (): void
{
    $cases = ['élan' => 'É', '7up' => '7', 'ñandú' => 'Ñ', 'βήτα' => 'Β'];

    foreach ($cases as $name => $expected)
    {
        $svg = (string) base64_decode(substr(g2ml_linkspageDefaultAvatarSrc((string) $name), strlen('data:image/svg+xml;base64,')), true);

        assert_contains('>' . $expected . '<', $svg, 'The first character of "' . $name . '" must be shown as ' . $expected);
    }
});

test('defaultAvatar: the resolved source uses the name, but a real avatar address is used as it is', function (): void
{
    assert_same(g2ml_linkspageDefaultAvatarSrc('Jane'), g2ml_linkspageResolveAvatarSrc(null, 'Jane'), 'No avatar: the default for this name');
    assert_same(g2ml_linkspageDefaultAvatarSrc('Jane'), g2ml_linkspageResolveAvatarSrc('javascript:alert(1)', 'Jane'), 'An unsafe address: the default for this name');
    assert_same('https://example.com/me.png', g2ml_linkspageResolveAvatarSrc('https://example.com/me.png', 'Jane'), 'A real address wins over the default');
});

test('avatarAlt: empty for the default avatar, a short text that is not the name for a real one', function (): void
{
    assert_same('', g2ml_linkspageAvatarAlt(null), 'No avatar: it is decoration, so the alt text is empty');
    assert_same('', g2ml_linkspageAvatarAlt('   '), 'A blank address counts as no avatar');
    assert_same('', g2ml_linkspageAvatarAlt('javascript:alert(1)'), 'An unsafe address is replaced by the default, so it is decoration too');
    assert_same('Profile picture', g2ml_linkspageAvatarAlt('https://example.com/me.png'), 'A real picture is described in a few words');
});

test('renderLinksPage: the avatar alt text never repeats the page name', function (): void
{
    $template = ['templateHTML' => '<main><img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}"><h1>{{name}}</h1></main>', 'templateCSS' => ''];
    $page     = ['pageTitle' => 'Jane Doe', 'pageDescription' => null, 'showSocialIcons' => 0, 'socialLinks' => null];

    $noAvatar = g2ml_renderLinksPage(['page' => $page + ['avatarPath' => null], 'template' => $template, 'items' => []]);
    $photo    = g2ml_renderLinksPage(['page' => $page + ['avatarPath' => 'https://example.com/me.png'], 'template' => $template, 'items' => []]);

    assert_contains('" alt=""><h1>Jane Doe</h1>', $noAvatar, 'The default avatar must have an empty alt text');
    assert_contains('src="https://example.com/me.png" alt="Profile picture"><h1>Jane Doe</h1>', $photo, 'A real avatar must say "Profile picture"');
    assert_false(str_contains($photo, 'alt="Jane Doe"'), 'The alt text must not repeat the heading beside it');
});

test('translate: without a translation the English fallback is used, never the raw key', function (): void
{
    assert_same('Profile picture', g2ml_linkspageTranslate('linkspage.no_such_key_for_this_test', 'Profile picture'), 'A missing translation must fall back to the English text');
});

test('translate: when __() exists but hands back the key (no seed 073), the English fallback is used', function (): void
{
    // The real __() returns the key itself when a translation is missing.
    // That case can only be tried where __() exists, so it runs in a clean
    // child process with a stand-in __() that does exactly that.
    $root    = dirname(__DIR__, 2);
    $script  = '<?php' . "\n" . 'declare(strict_types=1);' . "\n";
    $script .= 'function __(string $key, array $replacements = []): string' . "\n" . '{' . "\n";
    $script .= '    if ($key === \'linkspage.translated_for_this_test\')' . "\n" . '    {' . "\n";
    $script .= '        return \'Photo de profil\';' . "\n" . '    }' . "\n";
    $script .= '    return $key;' . "\n" . '}' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/security.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/_functions/html_sanitiser.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/Lnks.page/_functions/linkspage_resolver.php', true) . ';' . "\n";
    $script .= 'require_once ' . var_export($root . '/web/Lnks.page/_functions/linkspage_renderer.php', true) . ';' . "\n";
    $script .= 'echo json_encode(array(' . "\n";
    $script .= '    g2ml_linkspageTranslate(\'linkspage.avatar_alt\', \'Profile picture\'),' . "\n";
    $script .= '    g2ml_linkspageTranslate(\'linkspage.translated_for_this_test\', \'Profile picture\'),' . "\n";
    $script .= '));' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_lp_translate_');
    assert_true($tempFile !== false, 'Could not make a temporary file');

    file_put_contents((string) $tempFile, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg((string) $tempFile) . ' 2>/dev/null');
    unlink((string) $tempFile);

    $decoded = json_decode((string) $output, true);

    assert_true(is_array($decoded), 'The child process did not run: ' . (string) $output);
    assert_same('Profile picture', $decoded[0], 'A key handed back by __() must fall back to the English text');
    assert_same('Photo de profil', $decoded[1], 'A real translation must be used');
});

// ============================================================================
// 🔗 #287 — link title and description are separate lines
// ============================================================================

test('renderItem: the title and description sit in one text box, with a space between them in the markup', function (): void
{
    $html = g2ml_linkspageRenderItem([
        'itemTitle'       => 'My portfolio',
        'itemURL'         => 'https://example.com',
        'itemDescription' => 'Photos from the last ten years',
        'itemIcon'        => null,
        'faviconCacheURL' => null,
        'requiresAgeGate' => 0,
    ]);

    assert_contains('<span class="lp-link-text"><span class="lp-link-title">My portfolio</span> <span class="lp-link-desc">Photos from the last ten years</span></span></a>', $html, 'The title and description must be in one box, separated by a space so a screen reader does not read "portfolioPhotos"');
    assert_true(strpos($html, '<svg class="lp-link-icon"') !== false && strpos($html, '<svg class="lp-link-icon"') < strpos($html, '<span class="lp-link-text">'), 'The icon comes before the text box');
});

// ============================================================================
// 📐 #287 — the shared layout rules
// ============================================================================

test('baseCSS: long words break, titles and descriptions are their own lines, social links wrap', function (): void
{
    $css = g2ml_linkspageBaseCSS();

    assert_contains('.lp-container { overflow-wrap: anywhere; }', $css, 'A long word must be allowed to break anywhere');
    assert_contains('.lp-link-title { display: block; }', $css, 'The title must be its own line');
    assert_contains('.lp-link-desc { display: block;', $css, 'The description must be its own line');
    assert_contains('.lp-link-text { flex: 1 1 auto; min-width: 0; }', $css, 'The text box must be allowed to shrink so a long word wraps inside it');
    assert_contains('.lp-social { display: flex; flex-wrap: wrap;', $css, 'Social links must sit in a wrapping row');
    assert_contains('.lp-social:empty { display: none; }', $css, 'An empty social row must take no space');
    assert_false(str_contains($css, 'color'), 'The shared rules must not set a colour: the right one depends on what the template draws behind the text');
});

test('renderLinksPage: the shared layout rules are printed in front of the template CSS', function (): void
{
    $html = g2ml_renderLinksPage([
        'page'     => ['pageTitle' => 'Jane'],
        'template' => ['templateHTML' => '<main>{{name}}</main>', 'templateCSS' => '.lp-template-marker { color: red; }'],
        'items'    => [],
    ]);

    assert_true(strpos($html, 'overflow-wrap: anywhere') !== false && strpos($html, 'overflow-wrap: anywhere') < strpos($html, '.lp-template-marker'), 'The shared rules must come first so a template can override them');
});

// ============================================================================
// 🌗 #287 — readable text colour
// ============================================================================

test('contrast: the formula gives the known WCAG values', function (): void
{
    $white = [255, 255, 255];
    $black = [0, 0, 0];

    assert_same(21.0, round(g2ml_linkspageContrastRatio($white, $black), 2), 'Black on white is 21:1');
    assert_same(1.0, round(g2ml_linkspageContrastRatio($white, $white), 2), 'Identical colours are 1:1');
    assert_same(3.68, round(g2ml_linkspageContrastRatio($white, g2ml_linkspageHexToRgb('#1E88E5')), 2), 'White on #1E88E5 is 3.68:1 (the old Bold template)');
    assert_same(3.54, round(g2ml_linkspageContrastRatio(g2ml_linkspageHexToRgb('#888'), $white), 2), '#888 on white is 3.54:1 (the old Minimal bio)');
    assert_same(5.74, round(g2ml_linkspageContrastRatio(g2ml_linkspageHexToRgb('#666666'), $white), 2), '#666 on white is 5.74:1');
});

test('readableTextColour: white on dark colours, black on light and mid colours', function (): void
{
    assert_same('#000000', g2ml_linkspageReadableTextColour('#1E88E5'), 'White is only 3.68:1 on the default theme colour, so black is used (5.71:1)');
    assert_same('#FFFFFF', g2ml_linkspageReadableTextColour('#0D47A1'), 'White is 8.63:1 on a dark blue');
    assert_same('#000000', g2ml_linkspageReadableTextColour('#FFFFFF'), 'Black on white');
    assert_same('#FFFFFF', g2ml_linkspageReadableTextColour('#000'), 'White on black, from a 3-digit colour');
    assert_same('#000000', g2ml_linkspageReadableTextColour('#FFEB3B'), 'Black on yellow');
});

test('readableTextColour: a 4-digit colour is judged as the see-through colour it is, a 5-digit one as the white behind it', function (): void
{
    assert_same('#000000', g2ml_linkspageReadableTextColour('#0004'), 'Black at 27% over white is light grey, so black text');
    assert_same('#FFFFFF', g2ml_linkspageReadableTextColour('#000F'), 'Opaque black, so white text');
    assert_same('#000000', g2ml_linkspageReadableTextColour('#12345'), 'A 5-digit value is not a colour; the browser ignores it and white shows through');
});

test('readableTextColour: whatever the colour, the text reaches 4.5:1 (every colour in steps of 8)', function (): void
{
    for ($red = 0; $red <= 255; $red = $red + 8)
    {
        for ($green = 0; $green <= 255; $green = $green + 8)
        {
            for ($blue = 0; $blue <= 255; $blue = $blue + 8)
            {
                $background = sprintf('#%02X%02X%02X', $red, $green, $blue);
                $text       = g2ml_linkspageReadableTextColour($background);
                $ratio      = g2ml_linkspageContrastRatio(g2ml_linkspageHexToRgb($text), [$red, $green, $blue]);

                if ($ratio < 4.5)
                {
                    assert_true(false, $text . ' on ' . $background . ' is ' . round($ratio, 2) . ':1');
                }
            }
        }
    }

    assert_true(true, 'Every sampled colour reached 4.5:1');
});

test('renderLinksPage: {{themeText}} follows the theme colour', function (): void
{
    $template = ['templateHTML' => '<main>{{name}}</main>', 'templateCSS' => '.x { background: {{theme}}; color: {{themeText}}; }'];

    $blue = g2ml_renderLinksPage(['page' => ['pageTitle' => 'A', 'themeColour' => '#1E88E5'], 'template' => $template, 'items' => []]);
    $navy = g2ml_renderLinksPage(['page' => ['pageTitle' => 'A', 'themeColour' => '#0D47A1'], 'template' => $template, 'items' => []]);
    $none = g2ml_renderLinksPage(['page' => ['pageTitle' => 'A', 'themeColour' => 'not-a-colour'], 'template' => $template, 'items' => []]);

    assert_contains('background: #1E88E5; color: #000000;', $blue, 'Black on the default blue');
    assert_contains('background: #0D47A1; color: #FFFFFF;', $navy, 'White on dark blue');
    assert_contains('background: #1E88E5; color: #000000;', $none, 'An invalid colour falls back to the default theme colour, with its readable text');
});

test('applyTemplate: {{avatarAlt}} and {{themeText}} are substituted too', function (): void
{
    $result = g2ml_linkspageApplyTemplate('{{avatarAlt}}|{{themeText}}', ['avatarAlt' => 'P', 'themeText' => '#000000']);

    assert_same('P|#000000', $result, 'The two new placeholders must be substituted');
});

// ============================================================================
// 🧨 #287 — the custom-HTML page has one <main> and wraps long words
// ============================================================================

test('customPage: the wrapper is the one <main>, and long words and wide pictures cannot widen the page', function (): void
{
    $html = g2ml_linkspageBuildCustomDocument(['pageTitle' => 'Custom', 'customHTML' => '<h1>Hello</h1><p>World</p>', 'customCSS' => 'h1 { color: #123456; }']);

    assert_same(1, substr_count($html, '<main'), 'The page must have exactly one <main>');
    assert_same(1, substr_count($html, '</main>'), 'The page must have exactly one </main>');
    assert_contains('<main class="g2ml-lp-custom">', $html, 'The wrapper must be the <main>');
    assert_contains('.g2ml-lp-custom { overflow-wrap: anywhere; }', $html, 'A long word must break instead of widening the page');
    assert_contains(':where(.g2ml-lp-custom) img { max-width: 100%; height: auto; }', $html, 'A wide picture must shrink to the screen');
    assert_true(strpos($html, 'overflow-wrap: anywhere') !== false && strpos($html, 'overflow-wrap: anywhere') < strpos($html, 'h1 { color: #123456; }'), 'The owner\'s own CSS comes after ours, so it can override');
});

test('customPage: if the owner already wrote a <main>, the wrapper becomes a <div> so there is still only one', function (): void
{
    $html = g2ml_linkspageBuildCustomDocument(['pageTitle' => 'Custom', 'customHTML' => '<main><h1>Own</h1></main>', 'customCSS' => '']);

    assert_same(1, substr_count($html, '<main'), 'Two <main> elements on one page is invalid');
    assert_contains('<div class="g2ml-lp-custom">', $html, 'The wrapper must step aside');
});
