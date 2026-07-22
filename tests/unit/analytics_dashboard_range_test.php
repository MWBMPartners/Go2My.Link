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
 * 🧪 Unit tests — Analytics DASHBOARD date-range helpers (#42)
 * ============================================================================
 *
 * Covers the DB-FREE, pure helpers added to web/_functions/analytics.php for
 * the #42 analytics dashboard page:
 *   - g2ml_analyticsDashboardResolveRange()
 *   - g2ml_analyticsDashboardBucketForRangeDays()
 *
 * These are distinct from (and do not replace) the already-tested #41 data
 * layer or the #41 API's _g2ml_apiAnalyticsResolveRange() — see
 * tests/unit/analytics_validation_test.php for those. Unlike the API
 * validator, the dashboard resolver never throws: bad input degrades to a
 * safe default instead of erroring, which is exactly what this suite checks.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#42)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/analytics.php';

// ============================================================================
// 📆 g2ml_analyticsDashboardResolveRange() — presets
// ============================================================================

test('dashboardResolveRange: no params at all defaults to the 30-day preset ending now', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange(null, null, null);

    assert_same('30', $range['rangeLabel'], 'Default preset label must be "30"');
    assert_same(30, $range['rangeDays'], 'Default range span must be 30 days');

    $toTimestamp   = strtotime($range['to']);
    $fromTimestamp = strtotime($range['from']);
    $nowTimestamp  = time();

    assert_true(($nowTimestamp - $toTimestamp) < 5, '"to" must be approximately now');

    $expectedSpanSeconds = 30 * 24 * 60 * 60;
    $actualSpanSeconds    = $toTimestamp - $fromTimestamp;

    assert_true(abs($actualSpanSeconds - $expectedSpanSeconds) < 5, 'The 30-day window must span exactly 30 days');
});

test('dashboardResolveRange: ?range=7 selects the 7-day preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', null, null);

    assert_same('7', $range['rangeLabel']);
    assert_same(7, $range['rangeDays']);
});

test('dashboardResolveRange: ?range=90 selects the 90-day preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('90', null, null);

    assert_same('90', $range['rangeLabel']);
    assert_same(90, $range['rangeDays']);
});

test('dashboardResolveRange: an unrecognised ?range= value silently falls back to 30 (never throws)', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('365', null, null);

    assert_same('30', $range['rangeLabel'], 'An unrecognised preset must fall back to the default, not error');
    assert_same(30, $range['rangeDays']);
});

test('dashboardResolveRange: an injection-shaped ?range= value is treated the same as any other unrecognised value', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange("7' OR '1'='1", null, null);

    assert_same('30', $range['rangeLabel']);
});

// ============================================================================
// 📅 g2ml_analyticsDashboardResolveRange() — custom from/to
// ============================================================================

test('dashboardResolveRange: a valid custom from/to overrides the preset and expands to whole-day bounds', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('30', '2026-06-01', '2026-06-10');

    assert_same('custom', $range['rangeLabel']);
    assert_same('2026-06-01 00:00:00', $range['from'], 'A date-only "from" must expand to 00:00:00');
    assert_same('2026-06-10 23:59:59', $range['to'], 'A date-only "to" must expand to 23:59:59');
    assert_same(10, $range['rangeDays']);
});

test('dashboardResolveRange: only "from" supplied (no "to") ignores the custom range and applies the preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', '2026-06-01', null);

    assert_same('7', $range['rangeLabel'], 'A one-sided custom range must be ignored entirely');
    assert_same(7, $range['rangeDays']);
});

test('dashboardResolveRange: only "to" supplied (no "from") ignores the custom range and applies the preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', null, '2026-06-10');

    assert_same('7', $range['rangeLabel']);
});

test('dashboardResolveRange: "from" after "to" ignores the custom range and applies the preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', '2026-07-10', '2026-07-01');

    assert_same('7', $range['rangeLabel'], 'An inverted custom range must be ignored, not silently swapped');
});

test('dashboardResolveRange: a malformed custom date ignores the custom range and applies the preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', 'not-a-date', '2026-07-01');

    assert_same('7', $range['rangeLabel']);

    $rangeSlash = g2ml_analyticsDashboardResolveRange('7', '2026/07/01', '2026/07/10');

    assert_same('7', $rangeSlash['rangeLabel'], 'A slash-separated date must be rejected, not partially parsed');
});

test('dashboardResolveRange: an impossible calendar date (e.g. 2026-02-30) ignores the custom range and applies the preset', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange('7', '2026-02-30', '2026-03-01');

    assert_same('7', $range['rangeLabel'], 'checkdate() must reject a non-existent calendar date');
});

test('dashboardResolveRange: a custom span over 366 days is clamped to 366 days, anchored at "to"', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange(null, '2020-01-01', '2026-07-01');

    assert_same('custom', $range['rangeLabel']);
    assert_same('2026-07-01 23:59:59', $range['to'], 'The "to" anchor must be preserved exactly');
    assert_same(366, $range['rangeDays'], 'The span must be clamped to 366 days');

    $expectedFromTimestamp = strtotime('2026-07-01 23:59:59') - (366 * 24 * 60 * 60);

    assert_same(gmdate('Y-m-d H:i:s', $expectedFromTimestamp), $range['from'], '"from" must be recomputed from the clamped span');
});

test('dashboardResolveRange: a single-day custom range (from === to) returns rangeDays 1', function (): void
{
    $range = g2ml_analyticsDashboardResolveRange(null, '2026-07-01', '2026-07-01');

    assert_same('custom', $range['rangeLabel']);
    assert_same(1, $range['rangeDays']);
});

// ============================================================================
// 🪣 g2ml_analyticsDashboardBucketForRangeDays()
// ============================================================================

test('dashboardBucketForRangeDays: short spans (<= 45 days) use the "day" bucket', function (): void
{
    assert_same('day', g2ml_analyticsDashboardBucketForRangeDays(1));
    assert_same('day', g2ml_analyticsDashboardBucketForRangeDays(7));
    assert_same('day', g2ml_analyticsDashboardBucketForRangeDays(30));
    assert_same('day', g2ml_analyticsDashboardBucketForRangeDays(45));
});

test('dashboardBucketForRangeDays: medium spans (46-180 days) use the "week" bucket', function (): void
{
    assert_same('week', g2ml_analyticsDashboardBucketForRangeDays(46));
    assert_same('week', g2ml_analyticsDashboardBucketForRangeDays(90));
    assert_same('week', g2ml_analyticsDashboardBucketForRangeDays(180));
});

test('dashboardBucketForRangeDays: long spans (> 180 days) use the "month" bucket', function (): void
{
    assert_same('month', g2ml_analyticsDashboardBucketForRangeDays(181));
    assert_same('month', g2ml_analyticsDashboardBucketForRangeDays(366));
});
