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
 * 🧪 Unit tests — tag slug/normalisation helpers (FG-002)
 * ============================================================================
 *
 * Pure, DB-free tests for the two building blocks of the link-tagging feature,
 * both defined in web/Go2My.Link/_functions/shorturl_create.php:
 *
 *   (1) g2ml_slugifyTag()   — derive a URL-safe slug: lowercase, non-alphanumeric
 *       runs collapse to a single hyphen, leading/trailing hyphens trimmed,
 *       bounded to VARCHAR(100).
 *   (2) g2ml_normaliseTags() — parse a comma-separated string OR an array into an
 *       ordered list of ['name' => ..., 'slug' => ...] entries: empties skipped,
 *       de-duplicated by slug, display names bounded, list capped.
 *
 * The function file is safe to require here: its direct-access guard only fires
 * when the script is executed directly, and the tag helpers referenced below do
 * no database work at load time or in their own bodies (only g2ml_findOrCreateTag
 * / g2ml_attachTagsToShortURL touch the DB, and those are not exercised here).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-002)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

// ============================================================================
// 🏷️ g2ml_slugifyTag — slug derivation rules
// ============================================================================

test('slugify: a simple word is lowercased', function (): void
{
    assert_same('marketing', g2ml_slugifyTag('Marketing'), 'A plain word should lowercase to its slug');
});

test('slugify: internal whitespace becomes a single hyphen', function (): void
{
    assert_same('summer-sale', g2ml_slugifyTag('Summer Sale'), 'A space should become a single hyphen');
});

test('slugify: runs of non-alphanumerics collapse to one hyphen', function (): void
{
    assert_same('a-b', g2ml_slugifyTag('a   ///  b'), 'Multiple non-alphanumeric characters must collapse to a single hyphen');
});

test('slugify: leading and trailing hyphens are trimmed', function (): void
{
    assert_same('q3', g2ml_slugifyTag('  --Q3--  '), 'Surrounding punctuation/whitespace must be trimmed from the slug');
});

test('slugify: digits are preserved', function (): void
{
    assert_same('q3-2026', g2ml_slugifyTag('Q3 2026'), 'Digits are valid slug characters');
});

test('slugify: a punctuation-only tag yields an empty slug', function (): void
{
    assert_same('', g2ml_slugifyTag('!!!'), 'A tag with no alphanumeric content has no usable slug');
});

test('slugify: an empty string yields an empty slug', function (): void
{
    assert_same('', g2ml_slugifyTag('   '), 'Whitespace-only input has no usable slug');
});

test('slugify: non-ASCII letters are dropped from the slug', function (): void
{
    // "café" — the accented byte-sequence is non-[a-z0-9] so it collapses to a
    // hyphen which is then trimmed, leaving the ASCII stem.
    assert_same('caf', g2ml_slugifyTag('Café'), 'Non-ASCII letters are not part of the URL-safe slug');
});

test('slugify: the slug never exceeds the VARCHAR(100) bound', function (): void
{
    $slug = g2ml_slugifyTag(str_repeat('a', 150));
    assert_true(strlen($slug) <= 100, 'The slug must be bounded to 100 characters');
    assert_same(str_repeat('a', 100), $slug, 'A 150-character alphanumeric tag truncates to exactly 100 slug characters');
});

// ============================================================================
// 🏷️ g2ml_normaliseTags — parsing, de-duplication, and capping
// ============================================================================

test('normalise: a comma-separated string produces one entry per tag', function (): void
{
    $result = g2ml_normaliseTags('Marketing, Q3');

    assert_same(2, count($result), 'Two comma-separated tags should yield two entries');
    assert_same('Marketing', $result[0]['name'], 'The first display name is preserved as typed (trimmed)');
    assert_same('marketing', $result[0]['slug'], 'The first slug is the lowercased word');
    assert_same('Q3', $result[1]['name'], 'The second display name is preserved as typed (trimmed)');
    assert_same('q3', $result[1]['slug'], 'The second slug is the lowercased word');
});

test('normalise: an array input is accepted just like a string', function (): void
{
    $result = g2ml_normaliseTags(['Marketing', 'Q3']);

    assert_same(2, count($result), 'An array of two tags should yield two entries');
    assert_same('marketing', $result[0]['slug'], 'Array entries slugify the same way as string entries');
    assert_same('q3', $result[1]['slug'], 'Array entries slugify the same way as string entries');
});

test('normalise: empty and whitespace-only tags are skipped', function (): void
{
    $result = g2ml_normaliseTags('Marketing, , ,   , Q3');

    assert_same(2, count($result), 'Empty segments between commas must be skipped');
    assert_same('marketing', $result[0]['slug'], 'Only real tags survive');
    assert_same('q3', $result[1]['slug'], 'Only real tags survive');
});

test('normalise: tags that slugify to nothing are skipped', function (): void
{
    $result = g2ml_normaliseTags('!!!, Marketing');

    assert_same(1, count($result), 'A punctuation-only tag has no slug and is dropped');
    assert_same('marketing', $result[0]['slug'], 'The remaining real tag survives');
});

test('normalise: tags are de-duplicated by slug (case-insensitive)', function (): void
{
    $result = g2ml_normaliseTags('Marketing, marketing, MARKETING');

    assert_same(1, count($result), 'Three case variants of one tag collapse to a single entry');
    assert_same('Marketing', $result[0]['name'], 'The first occurrence wins the display name');
    assert_same('marketing', $result[0]['slug'], 'All three share one slug');
});

test('normalise: whitespace around each tag is trimmed', function (): void
{
    $result = g2ml_normaliseTags('   Summer Sale   ');

    assert_same(1, count($result), 'A single padded tag yields one entry');
    assert_same('Summer Sale', $result[0]['name'], 'Surrounding whitespace is trimmed from the display name');
    assert_same('summer-sale', $result[0]['slug'], 'The slug reflects the internal space as a hyphen');
});

test('normalise: the tag count is capped at the documented maximum', function (): void
{
    // Twelve distinct tags; the cap is 10.
    $input  = 'a1, a2, a3, a4, a5, a6, a7, a8, a9, a10, a11, a12';
    $result = g2ml_normaliseTags($input);

    assert_same(10, count($result), 'No more than the maximum number of tags should be kept');
    assert_same('a1', $result[0]['slug'], 'The earliest tags are the ones retained');
    assert_same('a10', $result[9]['slug'], 'The cap keeps the first ten in order');
});

test('normalise: a custom, smaller cap is honoured', function (): void
{
    $result = g2ml_normaliseTags('a, b, c, d', 2);

    assert_same(2, count($result), 'An explicit cap of two must be respected');
    assert_same('a', $result[0]['slug'], 'The first two tags in order are kept');
    assert_same('b', $result[1]['slug'], 'The first two tags in order are kept');
});

test('normalise: a long display name is bounded to 100 characters', function (): void
{
    $longName = str_repeat('x', 130);
    $result   = g2ml_normaliseTags($longName);

    assert_same(1, count($result), 'A single long tag yields one entry');
    assert_true(mb_strlen($result[0]['name'], 'UTF-8') <= 100, 'The display name must be bounded to 100 characters');
    assert_true(strlen($result[0]['slug']) <= 100, 'The slug must be bounded to 100 characters');
});

test('normalise: an empty input yields an empty list', function (): void
{
    assert_same(0, count(g2ml_normaliseTags('')), 'An empty string produces no tags');
    assert_same(0, count(g2ml_normaliseTags([])), 'An empty array produces no tags');
    assert_same(0, count(g2ml_normaliseTags('  ,  ,  ')), 'A string of only separators produces no tags');
});
