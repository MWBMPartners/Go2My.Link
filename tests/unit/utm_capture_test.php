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
 * 🧪 Unit tests — g2ml_extractTrackingParams() tracking-param capture (#92)
 * ============================================================================
 *
 * Pure-function, DB-free coverage for the redirect-hot-path helper that
 * whitelist-extracts tracking query params from the INCOMING (untrusted)
 * visit query string, for storage in tblActivityLog.logData. This is the
 * "capture" half of #92 (analytics.capture_tracking_params, OFF by default).
 *
 * The input to this function is attacker-controlled ($_GET at the call
 * site), so these tests focus on what must NEVER happen:
 *   - a non-whitelisted key must never be captured, no matter its name;
 *   - a non-scalar value (e.g. an array-style parameter) must never be
 *     coerced into a string;
 *   - an over-length value must be capped, not passed through in full;
 *   - the combined captured size must be capped.
 *
 * The companion database-backed proof — that a value extracted here actually
 * round-trips through logActivity() into tblActivityLog.logData — lives in
 * tests/integration/activity_log_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#92)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

// ----------------------------------------------------------------------------
// No-op / empty-input cases.
// ----------------------------------------------------------------------------

test('extractTrackingParams: an empty query array returns an empty array (a normal redirect)', function (): void
{
    $result = g2ml_extractTrackingParams([]);
    assert_same([], $result, 'No query params at all must produce an empty capture');
});

test('extractTrackingParams: a query array with only non-whitelisted params returns an empty array', function (): void
{
    $result = g2ml_extractTrackingParams(['foo' => 'bar', 'page' => '2']);
    assert_same([], $result, 'Only whitelisted keys are ever inspected — everything else is ignored');
});

// ----------------------------------------------------------------------------
// Whitelist enforcement — every documented key is captured, nothing else.
// ----------------------------------------------------------------------------

test('extractTrackingParams: all five standard UTM keys are captured', function (): void
{
    $query = [
        'utm_source'   => 'twitter',
        'utm_medium'   => 'social',
        'utm_campaign' => 'launch',
        'utm_term'     => 'shortlinks',
        'utm_content'  => 'variant-a',
    ];
    $result = g2ml_extractTrackingParams($query);
    assert_same($query, $result, 'All five UTM keys pass through verbatim when within limits');
});

test('extractTrackingParams: the three click-id keys (gclid, fbclid, msclkid) are captured', function (): void
{
    $query = [
        'gclid'   => 'g-abc123',
        'fbclid'  => 'fb-def456',
        'msclkid' => 'ms-ghi789',
    ];
    $result = g2ml_extractTrackingParams($query);
    assert_same($query, $result, 'All three click-id keys are whitelisted');
});

test('extractTrackingParams: a non-whitelisted click-id (e.g. ttclid) is ignored', function (): void
{
    $result = g2ml_extractTrackingParams(['ttclid' => 'tt-123']);
    assert_same([], $result, 'Only the exact documented whitelist is inspected — ttclid is deliberately NOT included');
});

test('extractTrackingParams: an unrelated param with a UTM-like name is not captured (exact key match only)', function (): void
{
    $result = g2ml_extractTrackingParams(['UTM_SOURCE' => 'twitter']);
    assert_same([], $result, 'Key matching is exact/case-sensitive — UTM_SOURCE is not utm_source');
});

// ----------------------------------------------------------------------------
// Non-scalar / empty value handling.
// ----------------------------------------------------------------------------

test('extractTrackingParams: an array-style value (utm_source[]=x) is ignored, not coerced', function (): void
{
    $result = g2ml_extractTrackingParams(['utm_source' => ['twitter', 'evil']]);
    assert_same([], $result, 'A non-scalar value must never be captured or coerced to a string');
});

test('extractTrackingParams: an empty-string value is not captured', function (): void
{
    $result = g2ml_extractTrackingParams(['utm_source' => '']);
    assert_same([], $result, 'An empty value carries no information and is dropped');
});

test('extractTrackingParams: an integer value is cast to a string', function (): void
{
    $result = g2ml_extractTrackingParams(['gclid' => 12345]);
    assert_same(['gclid' => '12345'], $result, 'A scalar non-string value is cast to string, not rejected');
});

// ----------------------------------------------------------------------------
// Length caps.
// ----------------------------------------------------------------------------

test('extractTrackingParams: an over-length value is capped to 255 characters', function (): void
{
    $longValue = str_repeat('a', 400);
    $result    = g2ml_extractTrackingParams(['utm_campaign' => $longValue]);

    assert_same(255, strlen($result['utm_campaign']), 'A value longer than 255 chars must be capped to 255');
    assert_same(str_repeat('a', 255), $result['utm_campaign'], 'The capped value must be a straight truncation, not corrupted');
});

test('extractTrackingParams: a value at exactly the 255-char cap is captured whole, unmodified', function (): void
{
    $exactValue = str_repeat('b', 255);
    $result     = g2ml_extractTrackingParams(['utm_term' => $exactValue]);
    assert_same($exactValue, $result['utm_term'], 'A value exactly at the cap is not truncated further');
});

test('extractTrackingParams: once the combined captured size would exceed the total cap, remaining keys are dropped whole', function (): void
{
    // Eight whitelisted keys at 255 chars each = 2040 chars, comfortably over
    // a 2000-char total cap — the later keys (by whitelist declaration order)
    // must be dropped entirely rather than partially truncated.
    $query = [
        'utm_source'   => str_repeat('1', 255),
        'utm_medium'   => str_repeat('2', 255),
        'utm_campaign' => str_repeat('3', 255),
        'utm_term'     => str_repeat('4', 255),
        'utm_content'  => str_repeat('5', 255),
        'gclid'        => str_repeat('6', 255),
        'fbclid'       => str_repeat('7', 255),
        'msclkid'      => str_repeat('8', 255),
    ];
    $result = g2ml_extractTrackingParams($query);

    $totalCapturedLength = 0;

    foreach ($result as $capturedValue)
    {
        $totalCapturedLength = $totalCapturedLength + strlen($capturedValue);
    }

    assert_true($totalCapturedLength <= 2000, 'The combined captured size never exceeds the total cap');
    assert_true(count($result) < count($query), 'At least one whitelisted key must have been dropped once the cap was reached');

    foreach ($result as $capturedKey => $capturedValue)
    {
        assert_same(255, strlen($capturedValue), 'Every key that WAS captured must be whole (255 chars), never partially truncated by the total cap: ' . $capturedKey);
    }
});

test('extractTrackingParams: a normal small set of values is comfortably under both caps and fully preserved', function (): void
{
    $query  = ['utm_source' => 'newsletter', 'utm_campaign' => 'spring-sale'];
    $result = g2ml_extractTrackingParams($query);
    assert_same($query, $result, 'Small, realistic values pass through completely unaffected by either cap');
});
