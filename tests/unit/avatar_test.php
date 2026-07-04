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
 * 🧪 Unit tests — avatar priority cascade (FG-005)
 * ============================================================================
 *
 * Pure, DB-free tests for web/_functions/avatar.php:
 *
 *   - g2ml_avatarInitials()       — 1–2 uppercase, multibyte-safe initials.
 *   - g2ml_avatarColour()         — deterministic accessible palette colour.
 *   - g2ml_avatarGravatarHash()   — md5(strtolower(trim(email))).
 *   - g2ml_resolveAvatar()        — the structured priority cascade.
 *   - g2ml_renderAvatar()         — safe HTML output (escaping verified).
 *
 * The function file is safe to require here: its direct-access guard only
 * fires when executed directly, and none of the exercised helpers touch a
 * database. The Gravatar tier is treated as disabled because getSetting() is
 * intentionally NOT defined in this DB-free harness (avatar.php guards on
 * function_exists('getSetting')), so g2ml_resolveAvatar() never needs a DB.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-005)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/avatar.php';

// ============================================================================
// 🔤 g2ml_avatarInitials — derivation rules
// ============================================================================

test('initials: first + last name give both initials, uppercased', function (): void
{
    $user = ['firstName' => 'john', 'lastName' => 'smith'];
    assert_same('JS', g2ml_avatarInitials($user), 'First+last name should yield two uppercase initials');
});

test('initials: only a first name yields a single initial', function (): void
{
    $user = ['firstName' => 'Madonna', 'lastName' => ''];
    assert_same('M', g2ml_avatarInitials($user), 'A lone first name should yield one initial');
});

test('initials: display name with two words uses the first letter of each', function (): void
{
    $user = ['displayName' => 'Ada Lovelace'];
    assert_same('AL', g2ml_avatarInitials($user), 'A two-word display name gives one initial per word');
});

test('initials: single-word display name uses its first two letters', function (): void
{
    $user = ['displayName' => 'netscape'];
    assert_same('NE', g2ml_avatarInitials($user), 'A one-word display name gives its first two letters');
});

test('initials: email-only falls back to the first letter of the local-part', function (): void
{
    $user = ['email' => 'zoe@example.com'];
    assert_same('Z', g2ml_avatarInitials($user), 'Email-only should use the first local-part letter');
});

test('initials: name takes priority over display name and email', function (): void
{
    $user = [
        'firstName'   => 'Grace',
        'lastName'    => 'Hopper',
        'displayName' => 'Amazing Grace',
        'email'       => 'zzz@example.com',
    ];
    assert_same('GH', g2ml_avatarInitials($user), 'The name tier must win the cascade');
});

test('initials: an empty user yields an empty string', function (): void
{
    assert_same('', g2ml_avatarInitials([]), 'Nothing usable must return an empty string (caller substitutes U)');
});

test('initials: whitespace-only fields are treated as absent', function (): void
{
    $user = ['firstName' => '   ', 'lastName' => "\t", 'displayName' => '  '];
    assert_same('', g2ml_avatarInitials($user), 'Whitespace-only fields are not usable');
});

test('initials: multibyte accented names are handled without corruption', function (): void
{
    $user = ['firstName' => 'ángel', 'lastName' => 'ñoño'];
    assert_same(mb_strtoupper('áñ', 'UTF-8'), g2ml_avatarInitials($user), 'Accented initials must uppercase correctly (multibyte-safe)');
});

test('initials: never exceed two characters', function (): void
{
    // Includes characters that EXPAND when upper-cased (ß -> SS, ligatures
    // ﬀ -> FF, ﬁ -> FI, ﬂ -> FL, ﬃ -> FFI, ﬄ -> FFL). These previously
    // overflowed because the two-character cap was applied before uppercasing.
    $cases = [
        ['displayName' => 'One Two Three Four'],
        ['firstName' => 'ß', 'lastName' => 'ß'],
        ['firstName' => 'ﬄ', 'lastName' => 'ﬃ'],
        ['displayName' => 'ﬃ'],
        ['displayName' => 'ﬃx'],
        ['email' => 'ﬃ@example.com'],
    ];

    foreach ($cases as $index => $user)
    {
        $initials = g2ml_avatarInitials($user);
        assert_true(
            mb_strlen($initials, 'UTF-8') <= 2,
            'Initials must never exceed two characters (case ' . $index . ', got "' . $initials . '")'
        );
    }
});

// ============================================================================
// 🎯 g2ml_avatarColour — deterministic, in-palette, accessible
// ============================================================================

test('colour: the same seed always maps to the same colour', function (): void
{
    assert_same(
        g2ml_avatarColour('lance@mwmail.me'),
        g2ml_avatarColour('lance@mwmail.me'),
        'Colour selection must be deterministic for a given seed'
    );
});

test('colour: the result is always a member of the accessible palette', function (): void
{
    $palette = g2ml_avatarPalette();
    $colour  = g2ml_avatarColour('some-arbitrary-seed');
    assert_true(in_array($colour, $palette, true), 'The chosen colour must come from the fixed palette');
});

test('colour: every palette entry is a 6-digit hex colour', function (): void
{
    foreach (g2ml_avatarPalette() as $entry)
    {
        assert_same(1, preg_match('/^#[0-9a-f]{6}$/', $entry), 'Palette entry ' . $entry . ' must be a valid hex colour');
    }
});

test('colour: every palette entry meets 4.5:1 contrast against white', function (): void
{
    // Recompute the WCAG contrast ratio here so the accessibility guarantee is
    // enforced by the test suite, not just asserted in a comment.
    $relativeLuminance = function (string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channel = function (int $value): float
        {
            $srgb = $value / 255;
            if ($srgb <= 0.03928)
            {
                return $srgb / 12.92;
            }
            return pow(($srgb + 0.055) / 1.055, 2.4);
        };
        $r = $channel((int) hexdec(substr($hex, 0, 2)));
        $g = $channel((int) hexdec(substr($hex, 2, 2)));
        $b = $channel((int) hexdec(substr($hex, 4, 2)));
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };

    foreach (g2ml_avatarPalette() as $entry)
    {
        $ratio = 1.05 / ($relativeLuminance($entry) + 0.05);
        assert_true($ratio >= 4.5, 'Palette entry ' . $entry . ' must meet 4.5:1 vs white (got ' . round($ratio, 2) . ')');
    }
});

// ============================================================================
// 🔗 g2ml_avatarGravatarHash — Gravatar hashing contract
// ============================================================================

test('gravatar hash: equals md5 of the trimmed, lower-cased email', function (): void
{
    $expected = md5('lance@mwmail.me');
    assert_same($expected, g2ml_avatarGravatarHash('  Lance@MWMail.me  '), 'Gravatar hash must normalise (trim + lowercase) before md5');
});

// ============================================================================
// 🧭 g2ml_resolveAvatar — the priority cascade
// ============================================================================

test('resolve: a stored avatarURL wins as a local image', function (): void
{
    $user     = ['avatarURL' => 'https://cdn.example.com/a.png', 'email' => 'x@example.com'];
    $resolved = g2ml_resolveAvatar($user);

    assert_same('image', $resolved['type'], 'A stored avatar resolves to an image');
    assert_same('local', $resolved['source'], 'A stored avatarURL is the local source');
    assert_same('https://cdn.example.com/a.png', $resolved['url'], 'The stored URL is passed through');
});

test('resolve: the legacy avatar key is also honoured for the local image', function (): void
{
    $user     = ['avatar' => 'https://cdn.example.com/legacy.png'];
    $resolved = g2ml_resolveAvatar($user);

    assert_same('image', $resolved['type'], 'The avatar key also resolves to an image');
    assert_same('local', $resolved['source'], 'The avatar key is a local source');
});

test('resolve: with nothing stored and Gravatar disabled, falls back to initials', function (): void
{
    // getSetting() is undefined in this harness, so the Gravatar tier is off.
    $user     = ['firstName' => 'Grace', 'lastName' => 'Hopper', 'email' => 'grace@example.com'];
    $resolved = g2ml_resolveAvatar($user);

    assert_same('initials', $resolved['type'], 'No image and no Gravatar must resolve to initials');
    assert_same('placeholder', $resolved['source'], 'The monogram source is the placeholder');
    assert_same('GH', $resolved['text'], 'The monogram text is the derived initials');
    assert_true(in_array($resolved['colour'], g2ml_avatarPalette(), true), 'The monogram colour is from the palette');
});

test('resolve: an empty user resolves to a U monogram', function (): void
{
    $resolved = g2ml_resolveAvatar([]);

    assert_same('initials', $resolved['type'], 'An empty user still yields a monogram');
    assert_same('U', $resolved['text'], 'The universal fallback initial is U');
});

test('resolve: an empty avatarURL is ignored and does not become a local image', function (): void
{
    $user     = ['avatarURL' => '   ', 'displayName' => 'Test User'];
    $resolved = g2ml_resolveAvatar($user);

    assert_same('initials', $resolved['type'], 'A blank avatarURL must not resolve to an image');
});

// ============================================================================
// 🧱 g2ml_renderAvatar — safe HTML output
// ============================================================================

test('render: an initials monogram contains the escaped initials and a palette colour', function (): void
{
    $user = ['firstName' => 'Grace', 'lastName' => 'Hopper'];
    $html = g2ml_renderAvatar($user, 24, 'me-1');

    assert_contains('>GH<', $html, 'The monogram must contain the derived initials');
    assert_contains('aria-hidden="true"', $html, 'The monogram is decorative and aria-hidden');
    assert_contains('me-1', $html, 'Extra classes must be applied');
    assert_contains('width:24px', $html, 'The monogram width must match the requested size');
});

test('render: a hostile display name is escaped and never emitted raw', function (): void
{
    $user = ['displayName' => '<script>alert(1)</script>'];
    $html = g2ml_renderAvatar($user, 24, 'me-1');

    assert_true(str_contains($html, '<script>') === false, 'A raw <script> tag must never appear in the output');
    assert_contains('&lt;', $html, 'Angle brackets from the initials must be HTML-escaped');
});

test('render: a hostile stored avatar URL cannot break out of the src attribute', function (): void
{
    $user = ['avatarURL' => 'https://x/"><script>alert(1)</script>'];
    $html = g2ml_renderAvatar($user, 24);

    assert_same('image', g2ml_resolveAvatar($user)['type'], 'A stored URL resolves to an image branch');
    assert_true(str_contains($html, '"><script>') === false, 'The attribute-breaking payload must be escaped');
    assert_contains('&quot;&gt;', $html, 'The quote and angle bracket must be entity-encoded in the attribute');
});
