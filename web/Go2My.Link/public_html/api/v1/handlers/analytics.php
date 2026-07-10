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
 * 📊 Go2My.Link — API v1 Handlers: /api/v1/analytics (#41)
 * ============================================================================
 *
 * Implements:
 *   - GET /api/v1/analytics         g2ml_apiHandleAnalyticsSummary()  scope analytics:read
 *   - GET /api/v1/analytics/{code}  g2ml_apiHandleAnalyticsGet()      scope analytics:read
 *
 * Both handlers are thin: every aggregate is computed by
 * web/_functions/analytics.php (prepared statements, whitelisted
 * dimension/bucket strings, org-scoped SQL). This file's own job is
 * request-shape validation (query parameters) and org-scoping the {code}
 * route parameter, mirroring the pattern established by
 * web/Go2My.Link/public_html/api/v1/handlers/urls.php (#39).
 *
 * ----------------------------------------------------------------------------
 * BOLA / org-scoping (OWASP API1:2023)
 * ----------------------------------------------------------------------------
 * GET /analytics/{code} first confirms the code exists in the AUTHENTICATED
 * KEY'S OWN org (`s.orgHandle = ?` bound from $keyRow, never from client
 * input) before running a single aggregate query — a code that exists but
 * belongs to a different organisation is indistinguishable from one that
 * does not exist at all: both return the identical generic 404, exactly like
 * g2ml_apiHandleUrlsGet(). GET /analytics (the org summary) never accepts a
 * client-suppliable identifier at all — every aggregate is scoped to
 * $keyRow['orgHandle'] alone.
 *
 * ----------------------------------------------------------------------------
 * Query parameters (both endpoints)
 * ----------------------------------------------------------------------------
 *   ?from=, ?to=    'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS' (a 'T' separator is
 *                   also accepted). Defaults: to=now (UTC), from=to-30 days.
 *                   A date-only 'from' is treated as 00:00:00 that day; a
 *                   date-only 'to' is treated as 23:59:59 that day (an
 *                   inclusive whole-day range).
 *   ?bucket=        {code} only. One of day|week|month (default day).
 *   ?dimension=     {code} only. One of browserName|osName|deviceType|
 *                   requestReferer|scanSource — when supplied, adds a
 *                   `breakdown` block to the response.
 *   ?limit=         Caps top_links (org summary) / breakdown items ({code}),
 *                   default 10, clamped 1-100.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#41)
 *
 * 📖 References:
 *     - web/_functions/analytics.php — the data layer this file calls into
 *     - web/Go2My.Link/public_html/api/v1/handlers/urls.php — the #39 handler pattern mirrored here
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔧 Internal helpers (shared by both handlers in this file)
// ============================================================================

/**
 * Extract and RE-validate the {code} route parameter for the
 * analytics/{code} route — mirrors _g2ml_apiUrlsRequireRouteCode() in
 * urls.php (defence in depth: never trust a route parameter even though the
 * front controller's g2ml_apiMatchParamRoute() already validated it).
 *
 * @param  array $context
 * @return string
 *
 * @throws RuntimeException  Should never happen in normal operation — maps
 *                             to the front controller's generic 500 path.
 */
function _g2ml_apiAnalyticsRequireRouteCode(array $context): string
{
    if (!isset($context['routeParams']) || !is_array($context['routeParams']) || !isset($context['routeParams']['code']))
    {
        throw new RuntimeException('Route code parameter missing.');
    }

    $code = (string) $context['routeParams']['code'];

    if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $code) !== 1)
    {
        throw new RuntimeException('Route code parameter failed re-validation.');
    }

    return $code;
}

/**
 * Validate a single 'from'/'to' query parameter into a normalised
 * 'Y-m-d H:i:s' string, or null when the raw value is absent/empty.
 *
 * Accepts 'YYYY-MM-DD' (expanded to the start or end of that day, per
 * $endOfDayWhenDateOnly) or 'YYYY-MM-DD HH:MM:SS'/'YYYY-MM-DDTHH:MM:SS'.
 *
 * @param  string|null $raw                    The raw $_GET value, or null when absent.
 * @param  string      $fieldName              Used in the error message/field ('from' or 'to').
 * @param  bool        $endOfDayWhenDateOnly   True for 'to' (23:59:59), false for 'from' (00:00:00).
 * @return string|null
 *
 * @throws G2mlApiHandlerException  422 when the value is present but malformed.
 */
function _g2ml_apiAnalyticsValidateDateParam(?string $raw, string $fieldName, bool $endOfDayWhenDateOnly): ?string
{
    if ($raw === null)
    {
        return null;
    }

    $trimmedValue = trim($raw);

    if ($trimmedValue === '')
    {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmedValue) === 1)
    {
        if ($endOfDayWhenDateOnly === true)
        {
            return $trimmedValue . ' 23:59:59';
        }

        return $trimmedValue . ' 00:00:00';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $trimmedValue) === 1)
    {
        return str_replace('T', ' ', $trimmedValue);
    }

    throw new G2mlApiHandlerException(422, $fieldName . ' must be a date (YYYY-MM-DD) or datetime (YYYY-MM-DD HH:MM:SS).', $fieldName);
}

/**
 * Resolve the ?from=/?to= query parameters into a validated, defaulted
 * ['from' => ..., 'to' => ...] pair. Defaults: to=now (UTC), from=to-30 days.
 *
 * @return array{from: string, to: string}
 *
 * @throws G2mlApiHandlerException  422 (malformed date, or from after to).
 */
function _g2ml_apiAnalyticsResolveRange(): array
{
    $rawFrom = null;

    if (isset($_GET['from']) && is_string($_GET['from']))
    {
        $rawFrom = $_GET['from'];
    }

    $rawTo = null;

    if (isset($_GET['to']) && is_string($_GET['to']))
    {
        $rawTo = $_GET['to'];
    }

    $to = _g2ml_apiAnalyticsValidateDateParam($rawTo, 'to', true);

    if ($to === null)
    {
        $to = gmdate('Y-m-d H:i:s');
    }

    $from = _g2ml_apiAnalyticsValidateDateParam($rawFrom, 'from', false);

    if ($from === null)
    {
        $defaultRangeSeconds = 30 * 24 * 60 * 60;
        $fromTimestamp       = strtotime($to) - $defaultRangeSeconds;
        $from                = gmdate('Y-m-d H:i:s', $fromTimestamp);
    }

    if (strtotime($from) > strtotime($to))
    {
        throw new G2mlApiHandlerException(422, 'from must not be after to.', 'from');
    }

    return [
        'from' => $from,
        'to'   => $to,
    ];
}

/**
 * Validate the ?bucket= query parameter against the fixed whitelist,
 * defaulting to 'day' when absent.
 *
 * @param  string|null $raw
 * @return string
 *
 * @throws G2mlApiHandlerException  422 when present but not day|week|month.
 */
function _g2ml_apiAnalyticsValidateBucket(?string $raw): string
{
    if ($raw === null || $raw === '')
    {
        return 'day';
    }

    $validBuckets = ['day', 'week', 'month'];

    if (!in_array($raw, $validBuckets, true))
    {
        throw new G2mlApiHandlerException(422, 'bucket must be one of: day, week, month.', 'bucket');
    }

    return $raw;
}

/**
 * Validate the ?dimension= query parameter against
 * g2ml_analyticsValidDimensions(), returning null when absent (breakdown is
 * optional).
 *
 * @param  string|null $raw
 * @return string|null
 *
 * @throws G2mlApiHandlerException  422 when present but not on the whitelist.
 */
function _g2ml_apiAnalyticsValidateDimension(?string $raw): ?string
{
    if ($raw === null || $raw === '')
    {
        return null;
    }

    $validDimensions = g2ml_analyticsValidDimensions();

    if (!in_array($raw, $validDimensions, true))
    {
        throw new G2mlApiHandlerException(422, 'dimension must be one of: ' . implode(', ', $validDimensions) . '.', 'dimension');
    }

    return $raw;
}

/**
 * Validate the ?limit= query parameter, defaulting and clamping.
 *
 * @param  string|null $raw
 * @param  int         $default
 * @param  int         $max
 * @return int
 *
 * @throws G2mlApiHandlerException  422 when present but not a positive integer.
 */
function _g2ml_apiAnalyticsValidateLimit(?string $raw, int $default, int $max): int
{
    if ($raw === null || $raw === '')
    {
        return $default;
    }

    if (preg_match('/^[0-9]+$/', $raw) !== 1)
    {
        throw new G2mlApiHandlerException(422, 'limit must be a positive integer.', 'limit');
    }

    $limitValue = (int) $raw;

    if ($limitValue <= 0)
    {
        return $default;
    }

    if ($limitValue > $max)
    {
        return $max;
    }

    return $limitValue;
}

/**
 * Format a g2ml_analyticsTotals() row into the API's snake_case totals shape.
 *
 * @param  array $totals
 * @return array
 */
function _g2ml_apiAnalyticsFormatTotals(array $totals): array
{
    return [
        'total_clicks'   => $totals['totalClicks'],
        'unique_ips'     => $totals['uniqueIPs'],
        'bot_clicks'     => $totals['botClicks'],
        'human_clicks'   => $totals['humanClicks'],
        'unknown_clicks' => $totals['unknownClicks'],
    ];
}

// ============================================================================
// 🔍 GET /api/v1/analytics/{code} — single short URL (scope analytics:read)
// ============================================================================

/**
 * Handle GET /api/v1/analytics/{code}.
 *
 * @param  array $keyRow   The verified API key row.
 * @param  array $context  Carries routeParams.code.
 * @return array            {short_code, period, totals, clicks_over_time,
 *                           scan_sources, breakdown?}
 *
 * @throws G2mlApiHandlerException  404 (code not in this org), 422 (bad query params).
 */
function g2ml_apiHandleAnalyticsGet(array $keyRow, array $context): array
{
    $code      = _g2ml_apiAnalyticsRequireRouteCode($context);
    $orgHandle = (string) $keyRow['orgHandle'];

    // ------------------------------------------------------------------------
    // Existence + org-scoping check BEFORE running any aggregate — a code
    // belonging to a different org must 404 identically to one that does not
    // exist, exactly like g2ml_apiHandleUrlsGet() (urls.php).
    // ------------------------------------------------------------------------
    $existing = dbSelectOne(
        'SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
        'ss',
        [$code, $orgHandle]
    );

    if ($existing === null || $existing === false)
    {
        throw new G2mlApiHandlerException(404, 'No such short URL in this account.');
    }

    $range = _g2ml_apiAnalyticsResolveRange();

    $rawBucket = null;

    if (isset($_GET['bucket']) && is_string($_GET['bucket']))
    {
        $rawBucket = $_GET['bucket'];
    }

    $bucket = _g2ml_apiAnalyticsValidateBucket($rawBucket);

    $rawDimension = null;

    if (isset($_GET['dimension']) && is_string($_GET['dimension']))
    {
        $rawDimension = $_GET['dimension'];
    }

    $dimension = _g2ml_apiAnalyticsValidateDimension($rawDimension);

    $totals         = g2ml_analyticsTotals($orgHandle, $code, $range['from'], $range['to']);
    $clicksOverTime = g2ml_analyticsClicksOverTime($orgHandle, $code, $range['from'], $range['to'], $bucket);
    $scanSources    = g2ml_analyticsScanSources($orgHandle, $code, $range['from'], $range['to']);

    $formattedClicksOverTime = [];

    foreach ($clicksOverTime as $point)
    {
        $formattedClicksOverTime[] = [
            'bucket' => $point['bucket'],
            'clicks' => $point['clicks'],
        ];
    }

    $formattedScanSources = [];

    foreach ($scanSources as $entry)
    {
        $formattedScanSources[] = [
            'scan_source' => $entry['scanSource'],
            'scans'       => $entry['scans'],
        ];
    }

    $result = [
        'short_code' => $code,
        'period'     => [
            'from'   => $range['from'],
            'to'     => $range['to'],
            'bucket' => $bucket,
        ],
        'totals'           => _g2ml_apiAnalyticsFormatTotals($totals),
        'clicks_over_time' => $formattedClicksOverTime,
        'scan_sources'     => $formattedScanSources,
    ];

    if ($dimension !== null)
    {
        $rawLimit = null;

        if (isset($_GET['limit']) && is_string($_GET['limit']))
        {
            $rawLimit = $_GET['limit'];
        }

        $limit     = _g2ml_apiAnalyticsValidateLimit($rawLimit, 10, 100);
        $breakdown = g2ml_analyticsBreakdown($orgHandle, $code, $dimension, $range['from'], $range['to'], $limit);

        $formattedBreakdownItems = [];

        foreach ($breakdown as $entry)
        {
            $formattedBreakdownItems[] = [
                'value'  => $entry['value'],
                'clicks' => $entry['clicks'],
            ];
        }

        $result['breakdown'] = [
            'dimension' => $dimension,
            'items'     => $formattedBreakdownItems,
        ];
    }

    return $result;
}

// ============================================================================
// 📋 GET /api/v1/analytics — organisation summary (scope analytics:read)
// ============================================================================

/**
 * Handle GET /api/v1/analytics.
 *
 * Self-scoped: every value is derived from $keyRow['orgHandle'] alone — there
 * is no client-suppliable identifier anywhere in this handler, mirroring
 * g2ml_apiHandleOrgRead() (org.php, #39).
 *
 * @param  array $keyRow   The verified API key row.
 * @param  array $context  Unused — this endpoint reads $_GET directly, like
 *                          g2ml_apiHandleUrlsList()'s GET-only, body-free pattern.
 * @return array            {period, totals, top_links}
 *
 * @throws G2mlApiHandlerException  422 on a malformed query parameter.
 */
function g2ml_apiHandleAnalyticsSummary(array $keyRow, array $context): array
{
    $orgHandle = (string) $keyRow['orgHandle'];

    $range = _g2ml_apiAnalyticsResolveRange();

    $rawLimit = null;

    if (isset($_GET['limit']) && is_string($_GET['limit']))
    {
        $rawLimit = $_GET['limit'];
    }

    $limit = _g2ml_apiAnalyticsValidateLimit($rawLimit, 10, 100);

    $totals   = g2ml_analyticsTotals($orgHandle, null, $range['from'], $range['to']);
    $topLinks = g2ml_analyticsTopLinks($orgHandle, $range['from'], $range['to'], $limit);

    $formattedTopLinks = [];

    foreach ($topLinks as $entry)
    {
        $formattedTopLinks[] = [
            'short_code' => $entry['shortCode'],
            'clicks'     => $entry['clicks'],
        ];
    }

    return [
        'period' => [
            'from' => $range['from'],
            'to'   => $range['to'],
        ],
        'totals'    => _g2ml_apiAnalyticsFormatTotals($totals),
        'top_links' => $formattedTopLinks,
    ];
}
