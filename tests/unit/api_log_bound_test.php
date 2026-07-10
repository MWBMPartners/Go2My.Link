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
 * 🧪 Unit tests — API audit-log field bounding (adversarial audit fix)
 * ============================================================================
 *
 * Covers the DB-free g2ml_apiBoundLogValue() helper added to
 * web/_functions/api_ratelimit.php as part of the API adversarial audit.
 *
 * SECURITY REGRESSION: tblAPIRequestLog.endpoint (VARCHAR 255) embeds the RAW,
 * unsanitised `_apiroute`, and httpMethod (VARCHAR 10) / ipAddress (VARCHAR 45)
 * are likewise request-derived. Before the fix, an over-length value made the
 * whole audit-log INSERT throw under MySQL strict mode; g2ml_apiLogRequest()
 * swallows that, silently dropping the row. Because the DB-backed rate limiter
 * AND the pre-auth IP failed-auth backoff both COUNT rows in that same table,
 * a dropped row is also an UNCOUNTED request — so a flood of invalid-key
 * requests carrying a >255-character route would evade the backoff entirely
 * (each still paying the full key-verification cost). These cases lock in the
 * bounding + control-character stripping that closes that hole.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (API adversarial audit)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';

// ============================================================================
// ✂️ g2ml_apiBoundLogValue — length capping
// ============================================================================

test('boundLogValue: null passes straight through as null', function (): void
{
    assert_same(null, g2ml_apiBoundLogValue(null, 255));
});

test('boundLogValue: a value under the limit is returned unchanged', function (): void
{
    assert_same('/api/v1/ping', g2ml_apiBoundLogValue('/api/v1/ping', 255));
});

test('boundLogValue: a value exactly at the limit is unchanged', function (): void
{
    $exact = str_repeat('a', 255);
    assert_same($exact, g2ml_apiBoundLogValue($exact, 255));
});

test('boundLogValue: an over-length endpoint is capped to the column width (255)', function (): void
{
    // The exact attack input: '/api/v1/' followed by a 400-char route.
    $oversizedEndpoint = '/api/v1/' . str_repeat('A', 400);
    $bounded           = g2ml_apiBoundLogValue($oversizedEndpoint, 255);

    assert_true(is_string($bounded), 'A bounded endpoint must be a string, never null');
    assert_same(255, strlen($bounded), 'An over-length endpoint must be capped to exactly 255 bytes so the VARCHAR(255) INSERT can never overflow');
});

test('boundLogValue: an over-length HTTP method is capped to 10', function (): void
{
    $bounded = g2ml_apiBoundLogValue(str_repeat('G', 40), 10);
    assert_same(10, strlen($bounded), 'An over-length method must be capped to the VARCHAR(10) column width');
});

// ============================================================================
// 🧼 g2ml_apiBoundLogValue — control-character stripping (log-injection guard)
// ============================================================================

test('boundLogValue: CR/LF are stripped so a percent-decoded route cannot inject a forged log line', function (): void
{
    $withNewlines = "/api/v1/ping\r\nFORGED LOG LINE";
    $bounded      = g2ml_apiBoundLogValue($withNewlines, 255);

    assert_false(str_contains($bounded, "\r"), 'A carriage return must never survive into the logged value');
    assert_false(str_contains($bounded, "\n"), 'A line feed must never survive into the logged value');
    assert_same('/api/v1/pingFORGED LOG LINE', $bounded, 'Only the control characters are removed; the visible text is otherwise preserved');
});

test('boundLogValue: assorted control characters (NUL, TAB, DEL) are all stripped', function (): void
{
    $withControls = "GET\x00\x09\x7Fping";
    $bounded      = g2ml_apiBoundLogValue($withControls, 255);
    assert_same('GETping', $bounded, 'NUL, TAB and DEL must all be stripped');
});

test('boundLogValue: control stripping runs BEFORE the length cap', function (): void
{
    // 300 control characters plus 10 visible chars: after stripping, only the
    // 10 visible characters remain and are well under the cap.
    $value   = str_repeat("\n", 300) . 'visible-10';
    $bounded = g2ml_apiBoundLogValue($value, 255);
    assert_same('visible-10', $bounded, 'Stripping first means a control-char flood cannot consume the length budget');
});
