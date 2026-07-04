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
 * 🧪 Regression tests — interstitial redirect scheme guard (F-003 / #99)
 * ============================================================================
 *
 * Component B's interstitials (validating.php, expired.php) emit a
 * destination/fallback URL into an <a href>, a <meta http-equiv="refresh">,
 * and a JS window.location.href. Before F-003 those sinks received the raw
 * URL, so a migrated legacy URL carrying a javascript:/data:/vbscript: scheme
 * would become a live XSS / open-redirect vector.
 *
 * The fix routes every URL through g2ml_sanitiseURL() (an http(s)-only guard
 * that returns false for any other scheme) and substitutes a safe fallback
 * when the guard rejects the URL. This file pins down the guard's decision so
 * a future refactor cannot silently widen the accepted scheme set.
 *
 * It models the exact substitution the interstitials perform:
 *   - a rejected DESTINATION becomes '' (no clickable / JS link emitted),
 *   - a rejected FALLBACK becomes the safe main site URL.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

// ----------------------------------------------------------------------------
// Helpers mirroring the interstitial substitution logic exactly.
// ----------------------------------------------------------------------------

/**
 * Resolve the destination as the interstitial does: keep it only when it is a
 * valid http(s) URL, otherwise drop it to '' (so no link / JS target emits).
 *
 * @param  string $destination
 * @return string
 */
function g2ml_test_guard_destination(string $destination): string
{
    $safe = g2ml_sanitiseURL($destination);

    if ($safe === false)
    {
        return '';
    }

    return $safe;
}

/**
 * Resolve the fallback as the interstitial does: keep it only when it is a
 * valid http(s) URL, otherwise drop it to the safe main site URL.
 *
 * @param  string $fallback
 * @param  string $mainSiteURL
 * @return string
 */
function g2ml_test_guard_fallback(string $fallback, string $mainSiteURL): string
{
    $safe = g2ml_sanitiseURL($fallback);

    if ($safe === false)
    {
        return $mainSiteURL;
    }

    return $safe;
}

// ----------------------------------------------------------------------------
// Destination guard
// ----------------------------------------------------------------------------

test('scheme guard: javascript: destination is dropped to empty', function (): void
{
    assert_same('', g2ml_test_guard_destination('javascript:alert(1)'), 'javascript: must not survive as a destination');
});

test('scheme guard: data: destination is dropped to empty', function (): void
{
    assert_same('', g2ml_test_guard_destination('data:text/html,<script>alert(1)</script>'), 'data: must not survive as a destination');
});

test('scheme guard: vbscript: destination is dropped to empty', function (): void
{
    assert_same('', g2ml_test_guard_destination('vbscript:msgbox(1)'), 'vbscript: must not survive as a destination');
});

test('scheme guard: a benign https destination survives unchanged', function (): void
{
    assert_same('https://example.com/x', g2ml_test_guard_destination('https://example.com/x'), 'https destinations are emitted as-is');
});

test('scheme guard: a benign http destination survives unchanged', function (): void
{
    assert_same('http://example.com/path', g2ml_test_guard_destination('http://example.com/path'), 'http destinations are emitted as-is');
});

// ----------------------------------------------------------------------------
// Fallback guard
// ----------------------------------------------------------------------------

test('scheme guard: javascript: fallback is replaced by the main site', function (): void
{
    assert_same('https://go2my.link', g2ml_test_guard_fallback('javascript:alert(document.domain)', 'https://go2my.link'), 'A malicious fallback must drop to the safe main site');
});

test('scheme guard: data: fallback is replaced by the main site', function (): void
{
    assert_same('https://go2my.link', g2ml_test_guard_fallback('data:text/html,x', 'https://go2my.link'), 'A data: fallback must drop to the safe main site');
});

test('scheme guard: a benign https fallback survives unchanged', function (): void
{
    assert_same('https://example.com/org-fallback', g2ml_test_guard_fallback('https://example.com/org-fallback', 'https://go2my.link'), 'A valid org fallback is emitted as-is');
});
