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
 * 📊 Go2My.Link — Analytics Dashboard (Admin Dashboard) (#42)
 * ============================================================================
 *
 * Org-wide (or single-link drill-down) click analytics: totals, a
 * clicks-over-time line chart, a top-links ranking, browser/OS/device-type/
 * country breakdowns, top referrers, and CueRCode QR scan-source
 * attribution. The country breakdown (#43) is populated only when IP
 * geolocation is enabled AND a GeoIP database is deployed — otherwise it
 * renders the same empty "no data" state as any other zero-row breakdown.
 *
 * Every aggregate is computed by the already-tested #41 data layer
 * (web/_functions/analytics.php) — this page adds NO new SQL. Its own job is
 * the date-range UI (?range=/?from=/?to=, resolved by the #42 pure helpers
 * appended to the bottom of that same file), the ?code= drill-down's
 * validation/org-scoping, and rendering (Chart.js + an accessible data table
 * per chart).
 *
 * ----------------------------------------------------------------------------
 * Authentication & org-scoping
 * ----------------------------------------------------------------------------
 * Authentication itself is enforced once, globally, by the router
 * (requireAuth('User') in web/Go2My.Link/_admin/public_html/index.php)
 * before ANY page file — including this one — is required; this page does
 * not call requireAuth() again, exactly like
 * web/Go2My.Link/_admin/public_html/pages/links/index.php.
 *
 * Every analytics function takes an $orgHandle, so — unlike links/index.php,
 * which scopes to createdByUserUID — this page scopes to
 * getCurrentUser()['orgHandle'], mirroring
 * web/Go2My.Link/_admin/public_html/pages/api-keys/index.php. orgHandle is
 * ALWAYS read from the authenticated session, never from client input.
 *
 * The shared "[default]" bucket org (every user who has not yet joined a
 * real organisation) is deliberately BLOCKED here, unlike the rest of the
 * dashboard: every user left unassigned shares that same orgHandle, so an
 * org-wide aggregate for '[default]' would show clicks from OTHER users'
 * short links — a cross-user data leak the #41 data layer has no way to
 * prevent (it only takes an orgHandle, not a userUID). This mirrors the
 * '[default]' guard already used by api-keys/index.php and
 * org/domains/index.php, except this page does NOT also require
 * canManageOrg() — viewing your own organisation's analytics is a
 * member-level capability, not an admin-only one.
 *
 * ----------------------------------------------------------------------------
 * ?code= drill-down (BOLA / IDOR discipline)
 * ----------------------------------------------------------------------------
 * ?code= is re-validated by shape (same [A-Za-z0-9_-]{1,50} pattern the #41
 * API handler re-checks a route parameter with) and then confirmed to exist
 * WITHIN THE CALLER'S OWN ORG before any aggregate runs — a code that exists
 * but belongs to a different organisation is treated identically to one
 * that does not exist at all: both silently fall back to the org-wide view
 * (never disclosing which case applied), the same non-disclosure the
 * generic-404 in web/Go2My.Link/public_html/api/v1/handlers/analytics.php
 * (g2ml_apiHandleAnalyticsGet()) achieves via an HTTP redirect.
 *
 * This page renders the fallback IN PLACE rather than issuing an HTTP
 * redirect: by the time a page file runs, the router
 * (web/Go2My.Link/_admin/public_html/index.php) has already required
 * header.php + nav.php, which have already written HTML — there is no
 * output buffering in this app, so a header('Location: ...') call here
 * would fail with "headers already sent" (the router's own 404 branch only
 * gets away with http_response_code(404) because it calls it BEFORE
 * requiring header.php). Falling back silently avoids that trap entirely
 * and matches the tolerant-degradation style already used by
 * links/index.php's own ?filter= handling (an unrecognised value there
 * doesn't redirect either — it just doesn't filter).
 *
 * ----------------------------------------------------------------------------
 * Chart rendering (CSP-safe)
 * ----------------------------------------------------------------------------
 * The admin CSP (web/Go2My.Link/_admin/public_html/.htaccess) is
 * script-src 'self' 'unsafe-inline' cdn.jsdelivr.net code.jquery.com
 * cdnjs.cloudflare.com — but this page deliberately does NOT rely on
 * 'unsafe-inline' or any CDN for Chart.js: every chart is instantiated by a
 * same-origin external file, js/analytics.js, reading a
 * <script type="application/json"> data island this page writes via
 * json_encode() (JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
 * JSON_HEX_QUOT — belt-and-braces against a "</script>"-shaped value ever
 * breaking out of the block, on top of json_encode()'s own default "/" →
 * "\/" escaping). Chart.js itself is vendored locally at
 * js/vendor/chart.umd.min.js (copied from the shared
 * web/_libraries/chartjs/chart.umd.min.js — the shared _libraries/ tree
 * deploys OUTSIDE every component's web root per docs/DEPLOYMENT.md, so a
 * page-servable copy has to live inside public_html itself; this mirrors
 * the existing vendor/ convention at
 * web/Go2My.Link/public_html/api/docs/vendor/redoc.standalone.js). No
 * external CDN is used for either file.
 *
 * ----------------------------------------------------------------------------
 * Accessibility (WCAG 2.1 AA)
 * ----------------------------------------------------------------------------
 * Every chart is wrapped in a <figure> with an aria-label'd <canvas> and a
 * <figcaption>, and is followed by a native <details>/<summary> disclosure
 * containing the SAME numbers as a plain HTML <table> — this works with NO
 * JavaScript at all (native browser behaviour), is keyboard-operable by
 * default, and means the chart's information is never lost if Chart.js
 * fails to load. Categorical colours come from the validated
 * --g2ml-chart-series-* palette (css/analytics.css) rather than being
 * eyeballed; three light-mode slots sit in the "relief band" (sub-3:1
 * contrast) per that palette's own validation notes, which is exactly why
 * the data table is not optional. Chart.js animation is disabled outright
 * under prefers-reduced-motion (see js/analytics.js).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.1.0
 * @since      v1.1.0 — Phase 7 (#42)
 *
 * 📖 References:
 *     - Data layer:   web/_functions/analytics.php (#41, #42 date-range helpers appended)
 *     - Modelled on:  web/Go2My.Link/_admin/public_html/pages/api-keys/index.php (#40)
 *     - API precedent for BOLA discipline: web/Go2My.Link/public_html/api/v1/handlers/analytics.php (#41)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('analytics.title');
} else {
    $pageTitle = 'Analytics';
}
if (function_exists('__')) {
    $pageDesc = __('analytics.description');
} else {
    $pageDesc = 'View click analytics and performance for your organisation\'s short links.';
}

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

// ============================================================================
// 🔒 Local helpers — link-building only (no SQL, no external calls)
// ============================================================================

/**
 * Build the query args that preserve the CURRENTLY ACTIVE date-range
 * selection, for links that only change the ?code= drill-down (a Top Links
 * row, or "back to overview").
 *
 * @param  array $range  The resolved range from g2ml_analyticsDashboardResolveRange().
 * @return array<string, string>
 */
function _analyticsCurrentRangeQueryArgs(array $range): array
{
    if ($range['rangeLabel'] === 'custom')
    {
        return [
            'from' => substr($range['from'], 0, 10),
            'to'   => substr($range['to'], 0, 10),
        ];
    }

    return [
        'range' => $range['rangeLabel'],
    ];
}

/**
 * Build a same-page analytics URL for a given short code (or the org-wide
 * view when null) plus a set of extra query args, safely encoded.
 *
 * @param  string|null           $shortCode
 * @param  array<string, string> $extraArgs
 * @return string
 */
function _analyticsPageURL(?string $shortCode, array $extraArgs): string
{
    $args = $extraArgs;

    if ($shortCode !== null)
    {
        $args['code'] = $shortCode;
    }

    if (count($args) === 0)
    {
        return '/analytics';
    }

    return '/analytics?' . http_build_query($args);
}

// ============================================================================
// 🚫 Block the shared "[default]" bucket org — see docblock above
// ============================================================================

if ($orgHandle === '[default]')
{
    echo '<div class="container py-5"><div class="alert alert-warning">';
    echo '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ';
    if (function_exists('__')) {
        echo g2ml_sanitiseOutput(__('analytics.error_unavailable'));
    } else {
        echo 'Analytics is not available for accounts that are not yet part of an organisation.';
    }
    echo '</div></div>';
    return;
}

// ============================================================================
// 🔗 ?code= drill-down — validate shape, then confirm org ownership
// ============================================================================

$shortCode      = null;
$shortCodeTitle = '';

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
            'SELECT shortCode, title FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ? LIMIT 1',
            'ss',
            [$rawCode, $orgHandle]
        );

        if ($codeRow !== null && $codeRow !== false)
        {
            $shortCode      = (string) $codeRow['shortCode'];
            $shortCodeTitle = (string) ($codeRow['title'] ?? '');
        }
    }

    // An unrecognised shape, or a code that does not exist in THIS org, is
    // NOT distinguished any further — $shortCode simply stays null and the
    // rest of this page renders its normal org-wide view (see the docblock
    // above for why this is a silent in-place fallback rather than an HTTP
    // redirect).
}

// ============================================================================
// 📆 Date range — presets (7/30/90, default 30) or a validated custom range
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

$range  = g2ml_analyticsDashboardResolveRange($rawRangeParam, $rawFromParam, $rawToParam);
$bucket = g2ml_analyticsDashboardBucketForRangeDays($range['rangeDays']);

// ============================================================================
// 📊 Fetch every aggregate from the #41 data layer — org-scoped throughout
// ============================================================================

$totals         = g2ml_analyticsTotals($orgHandle, $shortCode, $range['from'], $range['to']);
$clicksOverTime = g2ml_analyticsClicksOverTime($orgHandle, $shortCode, $range['from'], $range['to'], $bucket);
$scanSources    = g2ml_analyticsScanSources($orgHandle, $shortCode, $range['from'], $range['to']);

$topLinks = [];

if ($shortCode === null)
{
    // Ranking a single code against itself is meaningless — org-wide only,
    // exactly like g2ml_analyticsTopLinks()'s own contract.
    $topLinks = g2ml_analyticsTopLinks($orgHandle, $range['from'], $range['to'], 8);
}

$browserBreakdown  = g2ml_analyticsBreakdown($orgHandle, $shortCode, 'browserName', $range['from'], $range['to'], 6);
$osBreakdown       = g2ml_analyticsBreakdown($orgHandle, $shortCode, 'osName', $range['from'], $range['to'], 6);
$deviceBreakdown   = g2ml_analyticsBreakdown($orgHandle, $shortCode, 'deviceType', $range['from'], $range['to'], 6);
$refererBreakdown  = g2ml_analyticsBreakdown($orgHandle, $shortCode, 'requestReferer', $range['from'], $range['to'], 10);

// countryCode (#43) — empty whenever geolocation has never run (the default,
// until an operator turns analytics.geolocation_enabled on AND deploys a
// GeoIP database); the widget below renders the same "no data" state as any
// other breakdown with zero rows, so no extra handling is needed here.
$countryBreakdown  = g2ml_analyticsBreakdown($orgHandle, $shortCode, 'countryCode', $range['from'], $range['to'], 8);

// ============================================================================
// 🧮 Human/bot percentages for the totals cards (guard divide-by-zero)
// ============================================================================

if ($totals['totalClicks'] > 0)
{
    $humanPercent = round(($totals['humanClicks'] / $totals['totalClicks']) * 100, 1);
    $botPercent   = round(($totals['botClicks'] / $totals['totalClicks']) * 100, 1);
}
else
{
    $humanPercent = 0.0;
    $botPercent   = 0.0;
}

// ============================================================================
// 🔗 Get default short domain (for a friendly link label in Top Links)
// ============================================================================

if (function_exists('getDefaultShortDomain')) {
    $shortDomain = getDefaultShortDomain($orgHandle);
} else {
    $shortDomain = 'g2my.link';
}

// ============================================================================
// 📦 JSON data island for js/analytics.js — json_encode() only, never
// string-concatenated. HEX_TAG/HEX_AMP/HEX_APOS/HEX_QUOT close off any
// "</script>"-shaped value in attacker-influenced fields (requestReferer,
// browserName, osName, deviceType, scanSource all ultimately derive from a
// request's User-Agent/Referer headers).
// ============================================================================

$chartPayload = [
    'clicksOverTime' => $clicksOverTime,
    'topLinks'       => $topLinks,
    'breakdown'      => [
        'browserName' => $browserBreakdown,
        'osName'      => $osBreakdown,
        'deviceType'  => $deviceBreakdown,
        'countryCode' => $countryBreakdown,
    ],
    'scanSources' => $scanSources,
];

$chartPayloadJSON = json_encode(
    $chartPayload,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

if ($chartPayloadJSON === false)
{
    $chartPayloadJSON = '{}';
}
?>

<link rel="stylesheet" href="/css/analytics.css">

<!-- ====================================================================== -->
<!-- Analytics Header                                                        -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="analytics-heading">
    <div class="container">

        <?php if ($shortCode !== null) { ?>
        <p class="mb-2">
            <a href="<?php echo g2ml_sanitiseOutput(_analyticsPageURL(null, _analyticsCurrentRangeQueryArgs($range))); ?>" class="text-decoration-none">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('analytics.back_to_overview'); } else { echo 'Back to all links'; } ?>
            </a>
        </p>
        <?php } ?>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 id="analytics-heading" class="h2 mb-0">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('analytics.heading'); } else { echo 'Analytics'; } ?>
            </h1>
        </div>

        <?php if ($shortCode !== null) { ?>
        <p class="text-body-secondary mb-4">
            <?php
            if (function_exists('__')) {
                echo g2ml_sanitiseOutput(__('analytics.viewing_link', ['code' => $shortDomain . '/' . $shortCode]));
            } else {
                echo 'Viewing analytics for ' . g2ml_sanitiseOutput($shortDomain . '/' . $shortCode);
            }
            if ($shortCodeTitle !== '') {
                echo ' — ' . g2ml_sanitiseOutput($shortCodeTitle);
            }
            ?>
        </p>
        <?php } else { ?>
        <p class="text-body-secondary mb-4">
            <?php if (function_exists('__')) { echo __('analytics.subheading_org'); } else { echo 'Org-wide performance across all your short links.'; } ?>
        </p>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- Date Range Selector                                             -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <h2 class="h6 mb-2">
                    <?php if (function_exists('__')) { echo __('analytics.range_heading'); } else { echo 'Date range'; } ?>
                </h2>

                <div class="btn-group mb-2" role="group" aria-label="<?php if (function_exists('__')) { echo g2ml_sanitiseOutput(__('analytics.range_heading')); } else { echo 'Date range'; } ?>">
                    <?php
                    $presetLabels = [
                        '7'  => 'analytics.range_7',
                        '30' => 'analytics.range_30',
                        '90' => 'analytics.range_90',
                    ];
                    $presetFallbacks = [
                        '7'  => 'Last 7 days',
                        '30' => 'Last 30 days',
                        '90' => 'Last 90 days',
                    ];

                    foreach ($presetLabels as $presetValue => $presetKey)
                    {
                        $isActivePreset = ($range['rangeLabel'] === $presetValue);

                        $presetClass = 'btn btn-outline-primary';

                        if ($isActivePreset)
                        {
                            $presetClass = 'btn btn-primary';
                        }

                        echo '<a href="' . g2ml_sanitiseOutput(_analyticsPageURL($shortCode, ['range' => $presetValue])) . '"';
                        echo ' class="' . $presetClass . '"';

                        if ($isActivePreset)
                        {
                            echo ' aria-current="true"';
                        }

                        echo '>';

                        if (function_exists('__')) {
                            echo g2ml_sanitiseOutput(__($presetKey));
                        } else {
                            echo g2ml_sanitiseOutput($presetFallbacks[$presetValue]);
                        }

                        echo '</a>';
                    }
                    ?>
                </div>

                <details class="mt-1">
                    <summary>
                        <?php if (function_exists('__')) { echo __('analytics.range_custom_toggle'); } else { echo 'Custom range'; } ?>
                    </summary>
                    <form method="GET" action="/analytics" class="row g-2 align-items-end mt-2">
                        <?php if ($shortCode !== null) { ?>
                        <input type="hidden" name="code" value="<?php echo g2ml_sanitiseOutput($shortCode); ?>">
                        <?php } ?>
                        <div class="col-auto">
                            <label for="analytics-range-from" class="form-label">
                                <?php if (function_exists('__')) { echo __('analytics.range_from'); } else { echo 'From'; } ?>
                            </label>
                            <input type="date" class="form-control" id="analytics-range-from" name="from"
                                   value="<?php echo g2ml_sanitiseOutput(substr($range['from'], 0, 10)); ?>">
                        </div>
                        <div class="col-auto">
                            <label for="analytics-range-to" class="form-label">
                                <?php if (function_exists('__')) { echo __('analytics.range_to'); } else { echo 'To'; } ?>
                            </label>
                            <input type="date" class="form-control" id="analytics-range-to" name="to"
                                   value="<?php echo g2ml_sanitiseOutput(substr($range['to'], 0, 10)); ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-primary">
                                <?php if (function_exists('__')) { echo __('analytics.range_apply'); } else { echo 'Apply'; } ?>
                            </button>
                        </div>
                    </form>
                </details>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Totals Cards                                                    -->
        <!-- ============================================================== -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-mouse-pointer fa-2x text-primary mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totals['totalClicks']); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('analytics.stat_total_clicks'); } else { echo 'Total Clicks'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-user fa-2x text-success mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totals['humanClicks']); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('analytics.stat_human_clicks'); } else { echo 'Human Clicks'; } ?>
                            (<?php echo g2ml_sanitiseOutput((string) $humanPercent); ?>%)
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-robot fa-2x text-secondary mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totals['botClicks']); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('analytics.stat_bot_clicks'); } else { echo 'Bot Clicks'; } ?>
                            (<?php echo g2ml_sanitiseOutput((string) $botPercent); ?>%)
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x text-info mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totals['uniqueIPs']); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('analytics.stat_unique_ips'); } else { echo 'Unique Visitors (approx.)'; } ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Clicks Over Time                                                -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <figure class="g2ml-chart-figure">
                    <h2 class="h5">
                        <?php if (function_exists('__')) { echo __('analytics.chart_clicks_over_time'); } else { echo 'Clicks Over Time'; } ?>
                    </h2>
                    <figcaption class="g2ml-chart-figcaption text-body-secondary mb-2">
                        <?php if (function_exists('__')) { echo __('analytics.chart_clicks_over_time_desc'); } else { echo 'Line chart of click counts across the selected date range.'; } ?>
                    </figcaption>
                    <?php if (count($clicksOverTime) === 0) { ?>
                    <p class="text-body-secondary">
                        <?php if (function_exists('__')) { echo __('analytics.no_data'); } else { echo 'No data for this period.'; } ?>
                    </p>
                    <?php } else { ?>
                    <div class="g2ml-chart-canvas-wrap">
                        <canvas id="g2ml-chart-clicks-over-time"
                                role="img"
                                aria-label="<?php if (function_exists('__')) { echo g2ml_sanitiseOutput(__('analytics.chart_clicks_over_time')); } else { echo 'Clicks over time line chart'; } ?>"></canvas>
                    </div>
                    <?php } ?>
                </figure>

                <?php if (count($clicksOverTime) > 0) { ?>
                <details class="g2ml-chart-datatable-toggle">
                    <summary>
                        <?php if (function_exists('__')) { echo __('analytics.table_toggle'); } else { echo 'View data table'; } ?>
                    </summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('analytics.table_col_period'); } else { echo 'Period'; } ?></th>
                                    <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.table_col_clicks'); } else { echo 'Clicks'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clicksOverTime as $point) { ?>
                                <tr>
                                    <td><?php echo g2ml_sanitiseOutput($point['bucket']); ?></td>
                                    <td class="text-end"><?php echo number_format($point['clicks']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php } ?>
            </div>
        </div>

        <?php if ($shortCode === null) { ?>
        <!-- ============================================================== -->
        <!-- Top Links                                                       -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <figure class="g2ml-chart-figure">
                    <h2 class="h5">
                        <?php if (function_exists('__')) { echo __('analytics.chart_top_links'); } else { echo 'Top Links'; } ?>
                    </h2>
                    <figcaption class="g2ml-chart-figcaption text-body-secondary mb-2">
                        <?php if (function_exists('__')) { echo __('analytics.chart_top_links_desc'); } else { echo 'Bar chart ranking your organisation\'s short links by click count in the selected date range.'; } ?>
                    </figcaption>
                    <?php if (count($topLinks) === 0) { ?>
                    <p class="text-body-secondary">
                        <?php if (function_exists('__')) { echo __('analytics.no_data'); } else { echo 'No data for this period.'; } ?>
                    </p>
                    <?php } else { ?>
                    <div class="g2ml-chart-canvas-wrap">
                        <canvas id="g2ml-chart-top-links"
                                role="img"
                                aria-label="<?php if (function_exists('__')) { echo g2ml_sanitiseOutput(__('analytics.chart_top_links')); } else { echo 'Top links bar chart'; } ?>"></canvas>
                    </div>
                    <?php } ?>
                </figure>

                <?php if (count($topLinks) > 0) { ?>
                <details class="g2ml-chart-datatable-toggle" open>
                    <summary>
                        <?php if (function_exists('__')) { echo __('analytics.table_toggle'); } else { echo 'View data table'; } ?>
                    </summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('analytics.table_col_short_code'); } else { echo 'Short Code'; } ?></th>
                                    <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.table_col_clicks'); } else { echo 'Clicks'; } ?></th>
                                    <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.view_link'); } else { echo 'View'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topLinks as $topLink) { ?>
                                <tr>
                                    <td><?php echo g2ml_sanitiseOutput($shortDomain . '/' . $topLink['shortCode']); ?></td>
                                    <td class="text-end"><?php echo number_format($topLink['clicks']); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo g2ml_sanitiseOutput(_analyticsPageURL($topLink['shortCode'], _analyticsCurrentRangeQueryArgs($range))); ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <?php if (function_exists('__')) { echo __('analytics.view_link'); } else { echo 'View'; } ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- Breakdown Charts — Browser / OS / Device                        -->
        <!-- ============================================================== -->
        <div class="row g-4 mb-4">
            <?php
            $breakdownWidgets = [
                [
                    'canvasID'    => 'g2ml-chart-browser',
                    'items'       => $browserBreakdown,
                    'headingKey'  => 'analytics.chart_browser',
                    'headingText' => 'Browser Breakdown',
                    'descKey'     => 'analytics.chart_browser_desc',
                    'descText'    => 'Doughnut chart of clicks grouped by browser.',
                ],
                [
                    'canvasID'    => 'g2ml-chart-os',
                    'items'       => $osBreakdown,
                    'headingKey'  => 'analytics.chart_os',
                    'headingText' => 'Operating System Breakdown',
                    'descKey'     => 'analytics.chart_os_desc',
                    'descText'    => 'Doughnut chart of clicks grouped by operating system.',
                ],
                [
                    'canvasID'    => 'g2ml-chart-device',
                    'items'       => $deviceBreakdown,
                    'headingKey'  => 'analytics.chart_device',
                    'headingText' => 'Device Type Breakdown',
                    'descKey'     => 'analytics.chart_device_desc',
                    'descText'    => 'Doughnut chart of clicks grouped by device type.',
                ],
                [
                    'canvasID'    => 'g2ml-chart-country',
                    'items'       => $countryBreakdown,
                    'headingKey'  => 'analytics.chart_country',
                    'headingText' => 'Country Breakdown',
                    'descKey'     => 'analytics.chart_country_desc',
                    'descText'    => 'Doughnut chart of clicks grouped by country (requires IP geolocation to be enabled).',
                ],
            ];

            foreach ($breakdownWidgets as $widget)
            {
                if (function_exists('__')) {
                    $widgetHeading = __($widget['headingKey']);
                } else {
                    $widgetHeading = $widget['headingText'];
                }
                if (function_exists('__')) {
                    $widgetDesc = __($widget['descKey']);
                } else {
                    $widgetDesc = $widget['descText'];
                }
                ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <figure class="g2ml-chart-figure">
                                <h2 class="h6"><?php echo g2ml_sanitiseOutput($widgetHeading); ?></h2>
                                <figcaption class="g2ml-chart-figcaption text-body-secondary mb-2">
                                    <?php echo g2ml_sanitiseOutput($widgetDesc); ?>
                                </figcaption>
                                <?php if (count($widget['items']) === 0) { ?>
                                <p class="text-body-secondary small">
                                    <?php if (function_exists('__')) { echo __('analytics.no_data'); } else { echo 'No data for this period.'; } ?>
                                </p>
                                <?php } else { ?>
                                <div class="g2ml-chart-canvas-wrap" style="height:220px;">
                                    <canvas id="<?php echo g2ml_sanitiseOutput($widget['canvasID']); ?>"
                                            role="img"
                                            aria-label="<?php echo g2ml_sanitiseOutput($widgetHeading); ?>"></canvas>
                                </div>
                                <?php } ?>
                            </figure>

                            <?php if (count($widget['items']) > 0) { ?>
                            <details class="g2ml-chart-datatable-toggle">
                                <summary>
                                    <?php if (function_exists('__')) { echo __('analytics.table_toggle'); } else { echo 'View data table'; } ?>
                                </summary>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col"><?php if (function_exists('__')) { echo __('analytics.table_col_value'); } else { echo 'Value'; } ?></th>
                                                <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.table_col_clicks'); } else { echo 'Clicks'; } ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($widget['items'] as $breakdownItem) { ?>
                                            <tr>
                                                <td><?php echo g2ml_sanitiseOutput($breakdownItem['value']); ?></td>
                                                <td class="text-end"><?php echo number_format($breakdownItem['clicks']); ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <!-- ============================================================== -->
        <!-- Top Referrers (table only — long, variable-length URL values    -->
        <!-- do not read well as chart labels/legend entries)                -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">
                    <?php if (function_exists('__')) { echo __('analytics.chart_referrers'); } else { echo 'Top Referrers'; } ?>
                </h2>
                <?php if (count($refererBreakdown) === 0) { ?>
                <p class="text-body-secondary mb-0">
                    <?php if (function_exists('__')) { echo __('analytics.no_referrers'); } else { echo 'No referrer data for this period.'; } ?>
                </p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php if (function_exists('__')) { echo __('analytics.table_col_referrer'); } else { echo 'Referrer'; } ?></th>
                                <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.table_col_clicks'); } else { echo 'Clicks'; } ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refererBreakdown as $refererItem) { ?>
                            <tr>
                                <td class="text-break"><?php echo g2ml_sanitiseOutput($refererItem['value']); ?></td>
                                <td class="text-end"><?php echo number_format($refererItem['clicks']); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- CueRCode Scan Sources                                           -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <figure class="g2ml-chart-figure">
                    <h2 class="h5">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('analytics.chart_scan_sources'); } else { echo 'QR Scan Sources'; } ?>
                    </h2>
                    <figcaption class="g2ml-chart-figcaption text-body-secondary mb-2">
                        <?php if (function_exists('__')) { echo __('analytics.chart_scan_sources_desc'); } else { echo 'Bar chart of clicks attributed to CueRCode QR scans, grouped by source.'; } ?>
                    </figcaption>
                    <?php if (count($scanSources) === 0) { ?>
                    <p class="text-body-secondary">
                        <?php if (function_exists('__')) { echo __('analytics.no_scan_sources'); } else { echo 'No QR scans recorded in this period.'; } ?>
                    </p>
                    <?php } else { ?>
                    <div class="g2ml-chart-canvas-wrap" style="height:200px;">
                        <canvas id="g2ml-chart-scan-sources"
                                role="img"
                                aria-label="<?php if (function_exists('__')) { echo g2ml_sanitiseOutput(__('analytics.chart_scan_sources')); } else { echo 'QR scan sources bar chart'; } ?>"></canvas>
                    </div>
                    <?php } ?>
                </figure>

                <?php if (count($scanSources) > 0) { ?>
                <details class="g2ml-chart-datatable-toggle">
                    <summary>
                        <?php if (function_exists('__')) { echo __('analytics.table_toggle'); } else { echo 'View data table'; } ?>
                    </summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('analytics.table_col_source'); } else { echo 'Source'; } ?></th>
                                    <th scope="col" class="text-end"><?php if (function_exists('__')) { echo __('analytics.table_col_scans'); } else { echo 'Scans'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scanSources as $scanSourceItem) { ?>
                                <tr>
                                    <td><?php echo g2ml_sanitiseOutput($scanSourceItem['scanSource']); ?></td>
                                    <td class="text-end"><?php echo number_format($scanSourceItem['scans']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </details>
                <?php } ?>
            </div>
        </div>

    </div>
</section>

<!-- ====================================================================== -->
<!-- Chart data (JSON island, read by js/analytics.js) + chart scripts       -->
<!-- ====================================================================== -->
<script type="application/json" id="g2ml-analytics-data"><?php echo $chartPayloadJSON; ?></script>
<script src="/js/vendor/chart.umd.min.js" defer></script>
<script src="/js/analytics.js" defer></script>
