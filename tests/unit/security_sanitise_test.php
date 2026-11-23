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
 * 🧪 Characterization tests — input/output/URL sanitisation (security.php)
 * ============================================================================
 *
 * Captures CURRENT behaviour exactly as observed. Notably:
 *
 *   - g2ml_sanitiseInput() === trim(strip_tags($input)). It strips HTML/PHP
 *     tags and trims leading/trailing whitespace (spaces, tabs, CR, LF). It
 *     does NOT strip CR/LF that appear in the MIDDLE of the string — the
 *     contact-form CRLF-injection fix removes those at the call site, not
 *     inside this function. These tests pin that down so a refactor cannot
 *     silently change it.
 *
 *   - g2ml_sanitiseURL() accepts http/https and returns false for
 *     javascript:, data:, ftp:, mailto:, relative paths, malformed input,
 *     empty string and null.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

// ----------------------------------------------------------------------------
// g2ml_sanitiseInput
// ----------------------------------------------------------------------------

test('g2ml_sanitiseInput: strips HTML tags and trims surrounding whitespace', function (): void
{
    assert_same('hello world', g2ml_sanitiseInput('  <b>hello</b> world  '), 'Tags are removed and ends trimmed');
});

test('g2ml_sanitiseInput: removes script tags but keeps inner text (strip_tags behaviour)', function (): void
{
    // strip_tags removes the <script> markup but leaves the text between/after.
    assert_same('alert(1)safe', g2ml_sanitiseInput('<script>alert(1)</script>safe'), 'strip_tags keeps tag contents');
});

test('g2ml_sanitiseInput: CURRENT behaviour PRESERVES internal newlines (no CRLF strip here)', function (): void
{
    // Characterization note: an embedded CRLF is NOT removed by this function.
    // The CR is kept and the LF is kept; only leading/trailing whitespace is
    // trimmed. CRLF-injection defence happens at the call site.
    $result = g2ml_sanitiseInput("line1\r\nline2");
    assert_same("line1\r\nline2", $result, 'Internal CR/LF must survive sanitiseInput unchanged');
});

test('g2ml_sanitiseInput: trims leading/trailing CR, LF and tabs', function (): void
{
    // The newline characters at both ends are part of trim()'s default set,
    // so they are removed; the inner content is preserved.
    assert_same('middle', g2ml_sanitiseInput("\n\rmiddle\t\n"), 'trim() removes surrounding whitespace incl. CR/LF/tab');
});

test('g2ml_sanitiseInput: a string of only newlines collapses to empty', function (): void
{
    assert_same('', g2ml_sanitiseInput("\r\n\r\n"), 'All-whitespace input trims to empty string');
});

test('g2ml_sanitiseInput: null input returns empty string', function (): void
{
    assert_same('', g2ml_sanitiseInput(null), 'null is normalised to an empty string');
});

// ----------------------------------------------------------------------------
// g2ml_sanitiseOutput
// ----------------------------------------------------------------------------

test('g2ml_sanitiseOutput: escapes &, <, >, double-quote and single-quote', function (): void
{
    $escaped = g2ml_sanitiseOutput('<a href="x">A & B \' "</a>');

    assert_contains('&lt;', $escaped, 'Less-than is escaped');
    assert_contains('&gt;', $escaped, 'Greater-than is escaped');
    assert_contains('&amp;', $escaped, 'Ampersand is escaped');
    assert_contains('&quot;', $escaped, 'Double-quote is escaped (ENT_QUOTES)');
    assert_contains('&#039;', $escaped, 'Single-quote is escaped (ENT_QUOTES)');
});

test('g2ml_sanitiseOutput: null input returns empty string', function (): void
{
    assert_same('', g2ml_sanitiseOutput(null), 'null is normalised to an empty string');
});

// ----------------------------------------------------------------------------
// g2ml_sanitiseURL
// ----------------------------------------------------------------------------

test('g2ml_sanitiseURL: accepts a valid http URL unchanged', function (): void
{
    assert_same('http://example.com/path', g2ml_sanitiseURL('http://example.com/path'), 'http is allowed');
});

test('g2ml_sanitiseURL: accepts a valid https URL unchanged', function (): void
{
    assert_same('https://example.com', g2ml_sanitiseURL('https://example.com'), 'https is allowed');
});

test('g2ml_sanitiseURL: rejects javascript: scheme', function (): void
{
    assert_same(false, g2ml_sanitiseURL('javascript:alert(1)'), 'javascript: must be rejected');
});

test('g2ml_sanitiseURL: rejects data: scheme', function (): void
{
    assert_same(false, g2ml_sanitiseURL('data:text/html,<b>x</b>'), 'data: must be rejected');
});

test('g2ml_sanitiseURL: rejects ftp: scheme (only http/https allowed)', function (): void
{
    assert_same(false, g2ml_sanitiseURL('ftp://example.com'), 'ftp: is not in the allow-list');
});

test('g2ml_sanitiseURL: rejects mailto: scheme', function (): void
{
    assert_same(false, g2ml_sanitiseURL('mailto:a@b.com'), 'mailto: is not in the allow-list');
});

test('g2ml_sanitiseURL: rejects a relative path (FILTER_VALIDATE_URL fails)', function (): void
{
    assert_same(false, g2ml_sanitiseURL('/just/a/path'), 'Relative paths are not valid absolute URLs');
});

test('g2ml_sanitiseURL: rejects obviously malformed input', function (): void
{
    assert_same(false, g2ml_sanitiseURL('not a url'), 'Malformed input is rejected');
});

test('g2ml_sanitiseURL: rejects empty string', function (): void
{
    assert_same(false, g2ml_sanitiseURL(''), 'Empty string returns false');
});

test('g2ml_sanitiseURL: rejects null', function (): void
{
    assert_same(false, g2ml_sanitiseURL(null), 'null returns false');
});

// ----------------------------------------------------------------------------
// g2ml_sanitiseURL — non-English letters and spaces (#309)
// ----------------------------------------------------------------------------
// The link cleaner used to delete every non-English letter, so a link to
// bücher.de was saved as bcher.de, a different website. The full table of
// cases is in url_ascii_test.php; these are the headline cases.

test('g2ml_sanitiseURL: bücher.de becomes its xn-- form instead of losing the letter', function (): void
{
    assert_same('https://xn--bcher-kva.de/', g2ml_sanitiseURL('https://bücher.de/'), 'The host is converted, not cut');
});

test('g2ml_sanitiseURL: non-English letters in the query are percent-encoded, not deleted', function (): void
{
    assert_same(
        'https://example.com/?q=caf%C3%A9&x=%C3%BC',
        g2ml_sanitiseURL('https://example.com/?q=café&x=ü'),
        'Each non-ASCII byte becomes %XX'
    );
});

test('g2ml_sanitiseURL: an emoji host is refused rather than turned into a different site', function (): void
{
    assert_same(false, g2ml_sanitiseURL('https://i❤.ws/'), 'Emoji hosts are refused');
});

test('g2ml_sanitiseURL: broken UTF-8 is refused rather than silently cut', function (): void
{
    assert_same(false, g2ml_sanitiseURL("https://example.com/caf\xC3"), 'Broken UTF-8 is refused');
});

test('g2ml_sanitiseURL: full-width letters and dots are read as the real host', function (): void
{
    assert_same('https://g2my.link/x', g2ml_sanitiseURL('https://g２my．link/x'), 'The own-domain check will see g2my.link');
});

test('g2ml_sanitiseURL: a space in the path becomes %20, and a space in the host is refused', function (): void
{
    assert_same('https://example.com/a%20b', g2ml_sanitiseURL('https://example.com/a b'), 'A space in the path becomes %20');
    assert_same(false, g2ml_sanitiseURL('https://exa mple.com/'), 'A space in the host is refused');
});
