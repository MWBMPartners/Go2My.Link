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
 * 🧪 Unit tests — Analytics data-layer whitelists + /api/v1/analytics query
 * parameter validation (#41)
 * ============================================================================
 *
 * Covers the DB-FREE pieces of the analytics feature:
 *   (1) g2ml_analyticsValidDimensions() — the fixed dimension whitelist that
 *       stands between client input and a SQL column name
 *       (web/_functions/analytics.php).
 *   (2) The query-parameter validators in
 *       web/Go2My.Link/public_html/api/v1/handlers/analytics.php:
 *       _g2ml_apiAnalyticsValidateDateParam(), _g2ml_apiAnalyticsResolveRange(),
 *       _g2ml_apiAnalyticsValidateBucket(), _g2ml_apiAnalyticsValidateDimension(),
 *       _g2ml_apiAnalyticsValidateLimit().
 *
 * DB-touching behaviour (the actual aggregate queries, org-scoping, the
 * {code} existence check, and the full handler happy/error paths) is
 * covered by tests/integration/analytics_test.php against a real schema.
 *
 * Requiring the handler file here is safe under the test harness: its
 * direct-access guard compares $_SERVER['SCRIPT_FILENAME'] (the CLI runner,
 * tests/run.php) against basename(__FILE__) (analytics.php) — they never
 * match under the CLI SAPI, mirroring every other handler file included by
 * this suite's tests.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#41)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';
require_once dirname(__DIR__, 2) . '/web/_functions/analytics.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/analytics.php';

// ============================================================================
// 🔒 g2ml_analyticsValidDimensions() — the fixed whitelist
// ============================================================================

test('analyticsValidDimensions: returns exactly the six documented dimensions, no more, no less', function (): void
{
    $dimensions = g2ml_analyticsValidDimensions();

    assert_same(6, count($dimensions), 'Exactly six dimensions are whitelisted');
    assert_true(in_array('browserName', $dimensions, true), 'browserName must be whitelisted');
    assert_true(in_array('osName', $dimensions, true), 'osName must be whitelisted');
    assert_true(in_array('deviceType', $dimensions, true), 'deviceType must be whitelisted');
    assert_true(in_array('requestReferer', $dimensions, true), 'requestReferer must be whitelisted');
    assert_true(in_array('scanSource', $dimensions, true), 'scanSource must be whitelisted');
    assert_true(in_array('countryCode', $dimensions, true), 'countryCode must be whitelisted — IP geolocation (#43) is now in scope');
});

// ============================================================================
// 📅 _g2ml_apiAnalyticsValidateDateParam()
// ============================================================================

test('validateDateParam: null/empty input returns null (caller applies its own default)', function (): void
{
    assert_same(null, _g2ml_apiAnalyticsValidateDateParam(null, 'from', false));
    assert_same(null, _g2ml_apiAnalyticsValidateDateParam('', 'from', false));
    assert_same(null, _g2ml_apiAnalyticsValidateDateParam('   ', 'from', false));
});

test('validateDateParam: a date-only "from" value expands to the START of that day', function (): void
{
    $result = _g2ml_apiAnalyticsValidateDateParam('2026-07-01', 'from', false);
    assert_same('2026-07-01 00:00:00', $result, 'A date-only "from" must expand to 00:00:00');
});

test('validateDateParam: a date-only "to" value expands to the END of that day', function (): void
{
    $result = _g2ml_apiAnalyticsValidateDateParam('2026-07-01', 'to', true);
    assert_same('2026-07-01 23:59:59', $result, 'A date-only "to" must expand to 23:59:59');
});

test('validateDateParam: a full datetime value is normalised (T -> space) and passed through', function (): void
{
    assert_same('2026-07-01 14:30:00', _g2ml_apiAnalyticsValidateDateParam('2026-07-01 14:30:00', 'from', false));
    assert_same('2026-07-01 14:30:00', _g2ml_apiAnalyticsValidateDateParam('2026-07-01T14:30:00', 'from', false));
});

test('validateDateParam: a malformed value throws a 422 G2mlApiHandlerException naming the field', function (): void
{
    assert_throws(function (): void
    {
        _g2ml_apiAnalyticsValidateDateParam('not-a-date', 'from', false);
    }, G2mlApiHandlerException::class, 'A malformed date must throw G2mlApiHandlerException');

    try
    {
        _g2ml_apiAnalyticsValidateDateParam('2026/07/01', 'to', true);
        assert_true(false, 'Expected an exception for a slash-separated date');
    }
    catch (G2mlApiHandlerException $caught)
    {
        assert_same(422, $caught->getHttpStatusCode(), 'A malformed date is a 422');
        assert_same('to', $caught->getField(), 'The field name must be the one passed in');
    }
});

// ============================================================================
// 📆 _g2ml_apiAnalyticsResolveRange()
// ============================================================================

test('resolveRange: explicit from/to are validated and returned verbatim (date-only expanded)', function (): void
{
    $_GET = ['from' => '2026-06-01', 'to' => '2026-06-30'];

    $range = _g2ml_apiAnalyticsResolveRange();

    assert_same('2026-06-01 00:00:00', $range['from'], 'from expands to start-of-day');
    assert_same('2026-06-30 23:59:59', $range['to'], 'to expands to end-of-day');

    $_GET = [];
});

test('resolveRange: with neither from nor to supplied, defaults to a 30-day window ending now', function (): void
{
    $_GET = [];

    $range = _g2ml_apiAnalyticsResolveRange();

    $toTimestamp   = strtotime($range['to']);
    $fromTimestamp = strtotime($range['from']);
    $nowTimestamp  = time();

    assert_true(($nowTimestamp - $toTimestamp) < 5, 'to must default to approximately now (within 5 seconds)');

    $expectedSpanSeconds = 30 * 24 * 60 * 60;
    $actualSpanSeconds    = $toTimestamp - $fromTimestamp;

    assert_true(abs($actualSpanSeconds - $expectedSpanSeconds) < 5, 'The default window must span exactly 30 days');
});

test('resolveRange: "from" after "to" throws a 422 naming the "from" field', function (): void
{
    $_GET = ['from' => '2026-07-10', 'to' => '2026-07-01'];

    assert_throws(function (): void
    {
        _g2ml_apiAnalyticsResolveRange();
    }, G2mlApiHandlerException::class, 'from > to must be rejected');

    $_GET = [];
});

// ============================================================================
// 🪣 _g2ml_apiAnalyticsValidateBucket()
// ============================================================================

test('validateBucket: null/empty defaults to "day"', function (): void
{
    assert_same('day', _g2ml_apiAnalyticsValidateBucket(null));
    assert_same('day', _g2ml_apiAnalyticsValidateBucket(''));
});

test('validateBucket: day/week/month all pass through verbatim', function (): void
{
    assert_same('day', _g2ml_apiAnalyticsValidateBucket('day'));
    assert_same('week', _g2ml_apiAnalyticsValidateBucket('week'));
    assert_same('month', _g2ml_apiAnalyticsValidateBucket('month'));
});

test('validateBucket: an unrecognised bucket throws a 422 naming the "bucket" field', function (): void
{
    try
    {
        _g2ml_apiAnalyticsValidateBucket('year');
        assert_true(false, 'Expected an exception for an unrecognised bucket');
    }
    catch (G2mlApiHandlerException $caught)
    {
        assert_same(422, $caught->getHttpStatusCode());
        assert_same('bucket', $caught->getField());
    }
});

// ============================================================================
// 🧩 _g2ml_apiAnalyticsValidateDimension()
// ============================================================================

test('validateDimension: null/empty returns null (no breakdown requested)', function (): void
{
    assert_same(null, _g2ml_apiAnalyticsValidateDimension(null));
    assert_same(null, _g2ml_apiAnalyticsValidateDimension(''));
});

test('validateDimension: every whitelisted dimension passes through verbatim', function (): void
{
    foreach (g2ml_analyticsValidDimensions() as $dimension)
    {
        assert_same($dimension, _g2ml_apiAnalyticsValidateDimension($dimension));
    }
});

test('validateDimension: an unrecognised or SQL-injection-shaped dimension throws a 422', function (): void
{
    try
    {
        _g2ml_apiAnalyticsValidateDimension('ipAddress');
        assert_true(false, 'Expected an exception for a non-whitelisted (but real-looking) column name');
    }
    catch (G2mlApiHandlerException $caught)
    {
        assert_same(422, $caught->getHttpStatusCode());
        assert_same('dimension', $caught->getField());
    }

    assert_throws(function (): void
    {
        _g2ml_apiAnalyticsValidateDimension('shortCode`; DROP TABLE tblActivityLog; --');
    }, G2mlApiHandlerException::class, 'An injection-shaped dimension must be rejected the same as any other unrecognised value');
});

// ============================================================================
// 🔢 _g2ml_apiAnalyticsValidateLimit()
// ============================================================================

test('validateLimit: null/empty returns the caller-supplied default', function (): void
{
    assert_same(10, _g2ml_apiAnalyticsValidateLimit(null, 10, 100));
    assert_same(25, _g2ml_apiAnalyticsValidateLimit('', 25, 100));
});

test('validateLimit: a valid positive integer within range passes through as an int', function (): void
{
    assert_same(42, _g2ml_apiAnalyticsValidateLimit('42', 10, 100));
});

test('validateLimit: a value above the max is clamped to the max', function (): void
{
    assert_same(100, _g2ml_apiAnalyticsValidateLimit('9999', 10, 100));
});

test('validateLimit: zero falls back to the default', function (): void
{
    assert_same(10, _g2ml_apiAnalyticsValidateLimit('0', 10, 100));
});

test('validateLimit: a non-numeric value throws a 422 naming the "limit" field', function (): void
{
    try
    {
        _g2ml_apiAnalyticsValidateLimit('abc', 10, 100);
        assert_true(false, 'Expected an exception for a non-numeric limit');
    }
    catch (G2mlApiHandlerException $caught)
    {
        assert_same(422, $caught->getHttpStatusCode());
        assert_same('limit', $caught->getField());
    }
});

test('validateLimit: a negative value throws a 422 (it fails the digit-only pattern)', function (): void
{
    assert_throws(function (): void
    {
        _g2ml_apiAnalyticsValidateLimit('-5', 10, 100);
    }, G2mlApiHandlerException::class, 'A negative limit must be rejected');
});
