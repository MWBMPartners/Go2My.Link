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
 * 🧪 Unit tests — custom short-code alias validation (FG-001)
 * ============================================================================
 *
 * Pure, DB-free tests for the two building blocks of the authenticated custom
 * alias feature, both defined in web/Go2My.Link/_functions/shorturl_create.php:
 *
 *   (1) G2ML_CUSTOM_CODE_PATTERN — the anchored format regex that bounds an
 *       alias to 3–50 characters of [A-Za-z0-9_-].
 *   (2) g2ml_isReservedShortCode() — case-insensitive block-list of codes that
 *       would shadow a Component B handler route.
 *
 * The function file is safe to require here: its direct-access guard only fires
 * when the script is executed directly, and createShortURL()'s dependencies are
 * referenced inside that function body (never at load time), so they are not
 * needed merely to load the constant and the helper.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-001)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Convenience wrapper: does a candidate match the custom-alias format regex?
 *
 * @param  string $code
 * @return bool
 */
function g2ml_test_alias_format_ok(string $code): bool
{
    return preg_match(G2ML_CUSTOM_CODE_PATTERN, $code) === 1;
}

// ============================================================================
// ✅ Format regex — valid aliases pass
// ============================================================================

test('custom alias format: a minimal 3-character code is accepted', function (): void
{
    assert_true(g2ml_test_alias_format_ok('abc'), 'Three letters is the minimum and should pass');
});

test('custom alias format: letters, numbers, hyphen, and underscore are accepted', function (): void
{
    assert_true(g2ml_test_alias_format_ok('my-campaign_2026'), 'A mixed but allowed-character code should pass');
});

test('custom alias format: a 50-character code is accepted', function (): void
{
    assert_true(g2ml_test_alias_format_ok(str_repeat('a', 50)), 'Exactly 50 characters is the maximum and should pass');
});

test('custom alias format: mixed-case is accepted', function (): void
{
    assert_true(g2ml_test_alias_format_ok('CamelCase'), 'Upper and lower case letters should pass');
});

// ============================================================================
// 🚫 Format regex — invalid aliases are rejected
// ============================================================================

test('custom alias format: a 2-character code is rejected (below minimum)', function (): void
{
    assert_false(g2ml_test_alias_format_ok('ab'), 'Two characters is below the 3-character minimum');
});

test('custom alias format: an empty code is rejected', function (): void
{
    assert_false(g2ml_test_alias_format_ok(''), 'An empty string must not match');
});

test('custom alias format: a 51-character code is rejected (above maximum)', function (): void
{
    assert_false(g2ml_test_alias_format_ok(str_repeat('a', 51)), '51 characters exceeds the VARCHAR(50) bound');
});

test('custom alias format: a space is rejected', function (): void
{
    assert_false(g2ml_test_alias_format_ok('my code'), 'Spaces are not permitted');
});

test('custom alias format: a slash is rejected', function (): void
{
    assert_false(g2ml_test_alias_format_ok('a/b'), 'Path separators are not permitted');
});

test('custom alias format: a dot is rejected', function (): void
{
    assert_false(g2ml_test_alias_format_ok('robots.txt'), 'A dot is outside the allowed character set');
});

test('custom alias format: a non-ASCII letter is rejected', function (): void
{
    assert_false(g2ml_test_alias_format_ok('café'), 'Only ASCII letters, digits, hyphen, and underscore are allowed');
});

test('custom alias format: an embedded newline is rejected (anchors are absolute)', function (): void
{
    assert_false(g2ml_test_alias_format_ok("abc\nadmin"), 'A newline injection must not slip past the anchors');
});

// ============================================================================
// 🚫 Reserved-word block-list
// ============================================================================

test('reserved short code: a normal code is not reserved', function (): void
{
    assert_false(g2ml_isReservedShortCode('mylink'), 'An ordinary code should not be flagged as reserved');
});

test('reserved short code: "admin" is reserved', function (): void
{
    assert_true(g2ml_isReservedShortCode('admin'), 'admin shadows the admin tree and must be blocked');
});

test('reserved short code: "api" is reserved', function (): void
{
    assert_true(g2ml_isReservedShortCode('api'), 'api shadows the API tree and must be blocked');
});

test('reserved short code: "robots.txt" is reserved', function (): void
{
    assert_true(g2ml_isReservedShortCode('robots.txt'), 'robots.txt shadows the robots handler and must be blocked');
});

test('reserved short code: "favicon.ico" is reserved', function (): void
{
    assert_true(g2ml_isReservedShortCode('favicon.ico'), 'favicon.ico shadows the favicon handler and must be blocked');
});

test('reserved short code: matching is case-insensitive', function (): void
{
    assert_true(g2ml_isReservedShortCode('Admin'), 'Reserved matching must ignore case');
    assert_true(g2ml_isReservedShortCode('ROBOTS'), 'Reserved matching must ignore case');
});

test('reserved short code: surrounding whitespace is ignored', function (): void
{
    assert_true(g2ml_isReservedShortCode('  install  '), 'A padded reserved word must still be blocked');
});

test('reserved short code: every documented reserved word is blocked', function (): void
{
    $reserved = [
        'robots',
        'robots.txt',
        'favicon',
        'favicon.ico',
        'index',
        'sitemap',
        'sitemap.xml',
        'validating',
        'expired',
        '404',
        'api',
        'admin',
        'install',
        'www',
    ];

    foreach ($reserved as $word)
    {
        assert_true(g2ml_isReservedShortCode($word), 'Reserved word should be blocked: ' . $word);
    }
});
