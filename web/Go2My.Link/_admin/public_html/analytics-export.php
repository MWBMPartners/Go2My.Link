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
 * 📥 Go2My.Link — Analytics CSV Export Endpoint (Admin Dashboard) (#44)
 * ============================================================================
 *
 * A GET-only file download endpoint alongside
 * pages/analytics/index.php (#42) — streams the SAME #41 data-layer
 * aggregates the dashboard renders as charts/tables, as a CSV file.
 *
 * ----------------------------------------------------------------------------
 * Why this is a standalone script, not a pages/analytics/* route
 * ----------------------------------------------------------------------------
 * Every route the file-based router (index.php's Step 5/6) resolves is
 * required AFTER header.php + nav.php have ALREADY been required — and, by
 * the time a page file runs, header.php has already echoed the HTML
 * doctype/<head>/opening <body> tag. A page file therefore cannot send its
 * own Content-Type/Content-Disposition headers (see pages/analytics/
 * index.php's own docblock, which documents the same "headers already sent"
 * trap for a plain header('Location: ...') redirect).
 *
 * This file is instead a second, independent entry point placed directly in
 * public_html — exactly like favicon.php in Component B
 * (web/G2My.Link/public_html/favicon.php) — which the .htaccess "Clean URLs"
 * rule serves directly (RewriteCond %{REQUEST_FILENAME} !-f) without ever
 * routing through index.php, header.php, or nav.php. It runs its own minimal
 * bootstrap (mirroring index.php's Steps 1–4) and never requires header.php.
 *
 * ----------------------------------------------------------------------------
 * Authentication & org-scoping (BOLA / IDOR discipline)
 * ----------------------------------------------------------------------------
 * requireAuth('User') enforces session authentication exactly like
 * index.php's own Step 4. orgHandle is then resolved from
 * getCurrentUser()['orgHandle'] — i.e. the AUTHENTICATED SESSION — never from
 * any client-supplied parameter; there is no "orgHandle" or "org" query
 * parameter accepted anywhere in this file. This is the exact same
 * org-resolution pattern pages/analytics/index.php uses (see that file's own
 * docblock), including the same '[default]' shared-bucket-org block (an
 * org-wide export for the shared "no org yet" bucket would otherwise leak
 * clicks belonging to OTHER users who also have not joined an organisation).
 *
 * The optional ?code= drill-down is re-validated by shape and then confirmed
 * to exist WITHIN THE CALLER'S OWN ORG before it is ever passed to an
 * aggregate function — copied verbatim from pages/analytics/index.php's own
 * ?code= handling (same regex, same query, same non-disclosure fallback: an
 * unrecognised shape and a code owned by a different org are indistinguishable
 * from the response — both just fall back to the org-wide export).
 *
 * ----------------------------------------------------------------------------
 * Parameters
 * ----------------------------------------------------------------------------
 *   ?type=       REQUIRED. One of 'top-links' | 'clicks-over-time' |
 *                'breakdown' | 'scan-sources' | 'totals'. Any other value is
 *                rejected (400) rather than silently falling back — unlike
 *                the dashboard page's own tolerant-degradation style, an
 *                export the caller cannot see the result of must not guess.
 *   ?dimension=  REQUIRED when type=breakdown; validated against
 *                g2ml_analyticsValidDimensions() — an unrecognised dimension
 *                is rejected (400), exactly like the type check above.
 *   ?range=/?from=/?to=  Same as the dashboard —
 *                resolved via g2ml_analyticsDashboardResolveRange(), which
 *                never throws (bad input degrades to the 30-day default,
 *                matching the dashboard's own tolerant style for the parts of
 *                the request that are not security-relevant).
 *   ?bucket=     Optional, for type=clicks-over-time only. One of 'day' |
 *                'week' | 'month'; an absent/unrecognised value falls back to
 *                g2ml_analyticsDashboardBucketForRangeDays() — the same
 *                auto-bucket the dashboard's own chart uses for the same
 *                range.
 *   ?limit=      Optional, for top-links/breakdown. A non-digit value is
 *                ignored; the underlying #41 functions apply their own
 *                default/clamp (_g2ml_analyticsClampLimit(), 1–100) exactly
 *                as they do for every other caller.
 *   ?code=       Optional short-code drill-down (see above).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#44)
 *
 * 📖 References:
 *     - Data layer:      web/_functions/analytics.php (#41, #42)
 *     - CSV helpers:     web/_functions/export.php (#44)
 *     - Mirrored from:   pages/analytics/index.php (#42) — org-resolution,
 *                        ?code= drill-down, date-range handling
 *     - Standalone-entry-point precedent: web/G2My.Link/public_html/favicon.php
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials (mirrors index.php Step 1)
// ============================================================================

$componentAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '.auth'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}
else
{
    error_log('[Go2My.Link] CRITICAL: Component A auth_creds.php not found at: ' . $componentAuthPath);
}

// ============================================================================
// 🏷️ Step 2: Define Component Constants (mirrors index.php Step 2)
// ============================================================================

define('G2ML_COMPONENT',        'Admin');
define('G2ML_COMPONENT_NAME',   'Admin Dashboard');
define('G2ML_COMPONENT_DOMAIN', 'admin.go2my.link');
define('G2ML_ROOT', dirname(__DIR__, 3));

// ============================================================================
// 🚀 Step 3: Bootstrap the Application (mirrors index.php Step 3)
// ============================================================================

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'export.php';

// ============================================================================
// 🔐 Step 4: Authentication Check (mirrors index.php Step 4)
// ============================================================================

requireAuth('User');

// ============================================================================
// 🏢 Step 5: Resolve the organisation from the SESSION — never from a
// client-supplied parameter (BOLA guard). Identical to
// pages/analytics/index.php's own org resolution.
// ============================================================================

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]')
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Analytics export is not available for accounts that are not yet part of an organisation.';
    exit;
}

// ============================================================================
// 🔎 Step 6: Validate ?type= — reject anything not on the whitelist (400)
// ============================================================================

$validExportTypes = ['top-links', 'clicks-over-time', 'breakdown', 'scan-sources', 'totals'];

$rawType = '';

if (isset($_GET['type']) && is_string($_GET['type']))
{
    $rawType = trim($_GET['type']);
}

if (!in_array($rawType, $validExportTypes, true))
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or missing "type" parameter. Expected one of: ' . implode(', ', $validExportTypes) . '.';
    exit;
}

$exportType = $rawType;

// ============================================================================
// 🔗 Step 7: ?code= drill-down — validate shape, then confirm org ownership.
// Copied verbatim from pages/analytics/index.php's own ?code= handling.
// ============================================================================

$shortCode = null;

$rawCode = '';

if (isset($_GET['code']) && is_string($_GET['code']))
{
    $rawCode = trim($_GET['code']);
}

if ($rawCode !== '')
{
    if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $rawCode) === 1)
    {
        $codeRow = dbSelectOne(
            'SELECT shortCode FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
            'ss',
            [$rawCode, $orgHandle]
        );

        if ($codeRow !== null && $codeRow !== false)
        {
            $shortCode = (string) $codeRow['shortCode'];
        }
    }

    // An unrecognised shape, or a code that does not exist in THIS org, is
    // NOT distinguished any further — $shortCode simply stays null and the
    // export falls back to the org-wide aggregate, same as the dashboard.
}

// ============================================================================
// 🧩 Step 8: ?dimension= — REQUIRED (and validated) only for type=breakdown
// ============================================================================

$rawDimension = '';

if (isset($_GET['dimension']) && is_string($_GET['dimension']))
{
    $rawDimension = trim($_GET['dimension']);
}

if ($exportType === 'breakdown' && !in_array($rawDimension, g2ml_analyticsValidDimensions(), true))
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or missing "dimension" parameter for type=breakdown. Expected one of: '
        . implode(', ', g2ml_analyticsValidDimensions()) . '.';
    exit;
}

$dimension = $rawDimension;

// ============================================================================
// 📆 Step 9: Date range — same preset/custom resolution as the dashboard.
// ============================================================================

$rawRangeParam = null;

if (isset($_GET['range']) && is_string($_GET['range']))
{
    $rawRangeParam = $_GET['range'];
}

$rawFromParam = null;

if (isset($_GET['from']) && is_string($_GET['from']))
{
    $rawFromParam = $_GET['from'];
}

$rawToParam = null;

if (isset($_GET['to']) && is_string($_GET['to']))
{
    $rawToParam = $_GET['to'];
}

$range = g2ml_analyticsDashboardResolveRange($rawRangeParam, $rawFromParam, $rawToParam);

// ============================================================================
// 🪣 Step 10: ?bucket= — only meaningful for type=clicks-over-time; falls
// back to the same auto-bucket the dashboard's own chart uses.
// ============================================================================

$validBuckets = ['day', 'week', 'month'];
$rawBucket    = '';

if (isset($_GET['bucket']) && is_string($_GET['bucket']))
{
    $rawBucket = trim($_GET['bucket']);
}

if (in_array($rawBucket, $validBuckets, true))
{
    $bucket = $rawBucket;
}
else
{
    $bucket = g2ml_analyticsDashboardBucketForRangeDays($range['rangeDays']);
}

// ============================================================================
// 🔢 Step 11: ?limit= — a non-digit value is ignored (0 signals "no
// caller-supplied limit"); the #41 functions apply their own clamp/default.
// ============================================================================

$rawLimit = 0;

if (isset($_GET['limit']) && is_string($_GET['limit']) && ctype_digit($_GET['limit']))
{
    $rawLimit = (int) $_GET['limit'];
}

// ============================================================================
// 📊 Step 12: Fetch the requested aggregate + build the CSV rows/headers.
// Every branch calls one of the already-tested #41 data-layer functions —
// this file adds NO new SQL of its own (mirrors pages/analytics/index.php).
// ============================================================================

$csvHeaders   = [];
$csvRows      = [];
$filenameSlug = $exportType;

if ($exportType === 'top-links')
{
    $topLinks = g2ml_analyticsTopLinks($orgHandle, $range['from'], $range['to'], $rawLimit);

    $csvHeaders = [g2ml_csvHumaniseHeader('shortCode'), g2ml_csvHumaniseHeader('clicks')];

    foreach ($topLinks as $topLinkRow)
    {
        $csvRows[] = [$topLinkRow['shortCode'], $topLinkRow['clicks']];
    }
}
elseif ($exportType === 'clicks-over-time')
{
    $clicksSeries = g2ml_analyticsClicksOverTime($orgHandle, $shortCode, $range['from'], $range['to'], $bucket);

    $csvHeaders = [g2ml_csvHumaniseHeader('bucket'), g2ml_csvHumaniseHeader('clicks')];

    foreach ($clicksSeries as $seriesRow)
    {
        $csvRows[] = [$seriesRow['bucket'], $seriesRow['clicks']];
    }
}
elseif ($exportType === 'breakdown')
{
    $breakdownRows = g2ml_analyticsBreakdown($orgHandle, $shortCode, $dimension, $range['from'], $range['to'], $rawLimit);

    $csvHeaders   = [g2ml_csvHumaniseHeader($dimension), g2ml_csvHumaniseHeader('clicks')];
    $filenameSlug = $filenameSlug . '-' . $dimension;

    foreach ($breakdownRows as $breakdownRow)
    {
        $csvRows[] = [$breakdownRow['value'], $breakdownRow['clicks']];
    }
}
elseif ($exportType === 'scan-sources')
{
    $scanSourceRows = g2ml_analyticsScanSources($orgHandle, $shortCode, $range['from'], $range['to']);

    $csvHeaders = [g2ml_csvHumaniseHeader('scanSource'), g2ml_csvHumaniseHeader('scans')];

    foreach ($scanSourceRows as $scanSourceRow)
    {
        $csvRows[] = [$scanSourceRow['scanSource'], $scanSourceRow['scans']];
    }
}
else
{
    // 'totals' — a single summary row (validated above; this is the only
    // remaining branch of $validExportTypes).
    $totalsRow = g2ml_analyticsTotals($orgHandle, $shortCode, $range['from'], $range['to']);

    foreach (array_keys($totalsRow) as $totalsKey)
    {
        $csvHeaders[] = g2ml_csvHumaniseHeader($totalsKey);
    }

    $csvRows[] = array_values($totalsRow);
}

// ============================================================================
// 📤 Step 13: Build the CSV, then stream it. exit afterwards — this script
// never requires header.php/nav.php/footer.php.
// ============================================================================

$csv = g2ml_buildCsv($csvRows, $csvHeaders);

$exportFilename = 'analytics-' . $filenameSlug . '-' . gmdate('Ymd-His') . '.csv';

g2ml_streamCsvDownload($csv, $exportFilename);

exit;
