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
 * 📊 Go2My.Link — Analytics Data Layer (#41)
 * ============================================================================
 *
 * Org-scoped aggregate query functions over tblActivityLog (click/redirect
 * analytics) and tblAPIRequestLog (per-API-key usage). Every function takes
 * an $orgHandle that MUST be bound from a TRUSTED source — the caller's own
 * authenticated session orgHandle, or a verified API key row's orgHandle —
 * NEVER from raw, unchecked client input. The short-code-specific functions
 * additionally bind BOTH shortCode AND orgHandle together, mirroring the
 * BOLA/IDOR discipline already used throughout
 * web/Go2My.Link/public_html/api/v1/handlers/urls.php: a short code
 * belonging to a different organisation can never leak another tenant's
 * click data through these functions.
 *
 * ----------------------------------------------------------------------------
 * What counts as a "click"
 * ----------------------------------------------------------------------------
 * A click is a tblActivityLog row with logAction = 'redirect' AND
 * logStatus = 'success' — confirmed by reading the actual write site,
 * web/G2My.Link/public_html/index.php (the successful-redirect branch calls
 * logActivity('redirect', 'success', $redirectCode, [...])). Every other
 * logStatus value written against the 'redirect' action ('not_found',
 * 'expired', 'inactive', 'not_yet_active', 'no_destination',
 * 'domain_not_configured', 'validation_failed', 'error', etc. — see
 * web/G2My.Link/public_html/index.php) is a FAILED resolution attempt, not a
 * click, and is excluded from every aggregate in this file.
 *
 * ----------------------------------------------------------------------------
 * Out of scope
 * ----------------------------------------------------------------------------
 * Geographic/country breakdowns are OUT OF SCOPE here. tblActivityLog does
 * carry countryCode/regionCode/cityName/latitude/longitude columns, but
 * nothing populates them yet (GeoIP integration is tracked separately as
 * #43) — do not add a "country" dimension to this file until that lands.
 *
 * ----------------------------------------------------------------------------
 * Performance (#125 — tblActivityLog has 429K+ rows in production)
 * ----------------------------------------------------------------------------
 * Every query here is bounded by orgHandle (or shortCode+orgHandle) AND a
 * createdAt range, and aggregates entirely in SQL (COUNT/SUM/GROUP BY) —
 * never pulling raw rows into PHP for aggregation. Two composite indexes
 * back these queries (added by
 * web/_sql/migrations/014_activitylog_analytics_indexes.sql and folded into
 * web/_sql/schema/030_analytics.sql for fresh installs):
 *   - IDX_log_shortcode_created (shortCode, createdAt) — the per-code
 *     date-range queries (every function below when $shortCode is supplied).
 *   - IDX_log_org_created (orgHandle, createdAt) — the whole-org date-range
 *     queries (every function below when $shortCode is null, plus
 *     g2ml_analyticsTopLinks(), which is always org-wide).
 * The pre-existing single-column IDX_log_org/IDX_log_shortcode indexes are
 * left in place for now rather than dropped — see the migration file's
 * header comment for the reasoning (a documented "keep for now, revisit"
 * decision, per #125's "review whether standalone indexes remain
 * justified" ask).
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#41)
 *
 * 📖 References:
 *     - tblActivityLog schema:   web/_sql/schema/030_analytics.sql
 *     - tblAPIRequestLog schema: web/_sql/schema/031_api.sql
 *     - Composite indexes:       web/_sql/migrations/014_activitylog_analytics_indexes.sql
 *     - Consumer:                web/Go2My.Link/public_html/api/v1/handlers/analytics.php
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
// 🔒 Whitelists — dimension/bucket string => fixed, literal SQL fragment
// ============================================================================
// NEVER build a column or table name directly from client input anywhere in
// this file. Every place a "dimension" reaches SQL text has FIRST been
// looked up in g2ml_analyticsValidDimensions()/_g2ml_analyticsDimensionColumn()
// below; an unrecognised value always falls back to a safe default (or an
// empty result) rather than ever reaching string concatenation with the raw
// input. The "bucket" string works the same way via
// _g2ml_analyticsBucketFormat() — it maps to one of exactly three literal
// DATE_FORMAT() pattern strings, which are then bound as an ordinary VALUE
// parameter (DATE_FORMAT(createdAt, ?)), never concatenated into the SQL text.
// ============================================================================

/**
 * The canonical, whitelisted set of breakdown dimensions this file supports.
 *
 * Each entry is BOTH the client-facing dimension name and the exact
 * tblActivityLog column name — see _g2ml_analyticsDimensionColumn(), the
 * only place this list is consulted before a column name ever reaches SQL.
 *
 * @return array<int, string>
 */
function g2ml_analyticsValidDimensions(): array
{
    return [
        'browserName',
        'osName',
        'deviceType',
        'requestReferer',
        'scanSource',
    ];
}

/**
 * Resolve a client-facing dimension name to its tblActivityLog column name,
 * or null when the dimension is not on the whitelist.
 *
 * @param  string $dimension
 * @return string|null
 */
function _g2ml_analyticsDimensionColumn(string $dimension): ?string
{
    $allowedDimensions = g2ml_analyticsValidDimensions();

    if (in_array($dimension, $allowedDimensions, true))
    {
        // Every whitelisted dimension name IS its own column name.
        return $dimension;
    }

    return null;
}

/**
 * Resolve a bucket name to a MySQL DATE_FORMAT() pattern string.
 *
 * The returned pattern is bound as an ordinary parameter value
 * (DATE_FORMAT(createdAt, ?)) — it never touches SQL text directly. An
 * unrecognised bucket name falls back to 'day' rather than erroring, so this
 * function is safe to call defensively even after the API handler has
 * already validated the bucket against the same whitelist.
 *
 * @param  string $bucket  One of 'day', 'week', 'month'.
 * @return string          A DATE_FORMAT() pattern string.
 */
function _g2ml_analyticsBucketFormat(string $bucket): string
{
    $bucketFormats = [
        'day'   => '%Y-%m-%d',
        'week'  => '%x-W%v',
        'month' => '%Y-%m',
    ];

    if (isset($bucketFormats[$bucket]))
    {
        return $bucketFormats[$bucket];
    }

    return $bucketFormats['day'];
}

/**
 * Clamp a caller-supplied LIMIT to a sane range, defensively — even though
 * the API handler validates its own ?limit= parameter before calling in
 * here, this file must never trust that every future caller (e.g. a
 * dashboard page, #42) will have already done so.
 *
 * @param  int $limit    The requested limit (may be <= 0 or absurdly large).
 * @param  int $default  The value to use when $limit is not positive.
 * @param  int $max      The hard ceiling.
 * @return int
 */
function _g2ml_analyticsClampLimit(int $limit, int $default, int $max): int
{
    if ($limit <= 0)
    {
        return $default;
    }

    if ($limit > $max)
    {
        return $max;
    }

    return $limit;
}

// ============================================================================
// 🧱 Internal — shared WHERE-clause builder for the "click" queries
// ============================================================================

/**
 * Build the shared WHERE-clause fragment, bound types, and bound params for
 * every "click" query in this file (logAction='redirect' AND
 * logStatus='success', scoped to orgHandle + a createdAt range, and
 * optionally ALSO a specific shortCode within that org).
 *
 * @param  string      $orgHandle  The organisation to scope to (trusted source only).
 * @param  string|null $shortCode  A specific short code within that org, or null for org-wide.
 * @param  string      $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string      $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @return array{where: string, types: string, params: array<int, mixed>}
 */
function _g2ml_analyticsClickWhere(string $orgHandle, ?string $shortCode, string $from, string $to): array
{
    $conditions = [
        "logAction = 'redirect'",
        "logStatus = 'success'",
        'orgHandle = ?',
        'createdAt >= ?',
        'createdAt <= ?',
    ];

    $types  = 'sss';
    $params = [$orgHandle, $from, $to];

    if ($shortCode !== null && $shortCode !== '')
    {
        $conditions[] = 'shortCode = ?';
        $types        = $types . 's';
        $params[]     = $shortCode;
    }

    return [
        'where'  => implode(' AND ', $conditions),
        'types'  => $types,
        'params' => $params,
    ];
}

// ============================================================================
// 📈 g2ml_analyticsClicksOverTime() — time series
// ============================================================================

/**
 * Aggregate click counts into a time series, bucketed by day/week/month.
 *
 * @param  string      $orgHandle  The organisation to scope to.
 * @param  string|null $shortCode  A specific short code within that org, or null for org-wide.
 * @param  string      $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string      $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @param  string      $bucket     'day' (default), 'week', or 'month'.
 * @return array<int, array{bucket: string, clicks: int}>  Ordered ascending by bucket.
 */
function g2ml_analyticsClicksOverTime(string $orgHandle, ?string $shortCode, string $from, string $to, string $bucket = 'day'): array
{
    $bucketFormat = _g2ml_analyticsBucketFormat($bucket);
    $whereClause  = _g2ml_analyticsClickWhere($orgHandle, $shortCode, $from, $to);

    $sql = 'SELECT DATE_FORMAT(createdAt, ?) AS bucketLabel, COUNT(*) AS clicks '
        . 'FROM tblActivityLog '
        . 'WHERE ' . $whereClause['where'] . ' '
        . 'GROUP BY bucketLabel '
        . 'ORDER BY bucketLabel ASC';

    $types  = 's' . $whereClause['types'];
    $params = array_merge([$bucketFormat], $whereClause['params']);

    $rows = dbSelect($sql, $types, $params);

    if ($rows === false)
    {
        return [];
    }

    $series = [];

    foreach ($rows as $row)
    {
        $series[] = [
            'bucket' => (string) $row['bucketLabel'],
            'clicks' => (int) $row['clicks'],
        ];
    }

    return $series;
}

// ============================================================================
// 🔢 g2ml_analyticsTotals() — total clicks + distinct-IP approx + bot/human split
// ============================================================================

/**
 * Aggregate total clicks, a distinct-IP approximation, and a bot-vs-human
 * split over a date range.
 *
 * "Distinct-IP approx" is a COUNT(DISTINCT ipAddress) over the SAME rows the
 * other totals count — it is an approximation of unique visitors, not a
 * deduplicated-visitor metric (no visitor/device fingerprinting exists in
 * this schema), and is named accordingly in the return shape.
 *
 * @param  string      $orgHandle  The organisation to scope to.
 * @param  string|null $shortCode  A specific short code within that org, or null for org-wide.
 * @param  string      $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string      $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @return array{totalClicks: int, uniqueIPs: int, botClicks: int, humanClicks: int, unknownClicks: int}
 */
function g2ml_analyticsTotals(string $orgHandle, ?string $shortCode, string $from, string $to): array
{
    $whereClause = _g2ml_analyticsClickWhere($orgHandle, $shortCode, $from, $to);

    $sql = 'SELECT '
        . 'COUNT(*) AS totalClicks, '
        . 'COUNT(DISTINCT ipAddress) AS uniqueIPs, '
        . 'SUM(CASE WHEN isBot = 1 THEN 1 ELSE 0 END) AS botClicks, '
        . 'SUM(CASE WHEN isBot = 0 THEN 1 ELSE 0 END) AS humanClicks, '
        . 'SUM(CASE WHEN isBot IS NULL THEN 1 ELSE 0 END) AS unknownClicks '
        . 'FROM tblActivityLog '
        . 'WHERE ' . $whereClause['where'];

    $row = dbSelectOne($sql, $whereClause['types'], $whereClause['params']);

    if ($row === null || $row === false)
    {
        return [
            'totalClicks'   => 0,
            'uniqueIPs'     => 0,
            'botClicks'     => 0,
            'humanClicks'   => 0,
            'unknownClicks' => 0,
        ];
    }

    return [
        'totalClicks'   => (int) $row['totalClicks'],
        'uniqueIPs'     => (int) $row['uniqueIPs'],
        'botClicks'     => (int) $row['botClicks'],
        'humanClicks'   => (int) $row['humanClicks'],
        'unknownClicks' => (int) $row['unknownClicks'],
    ];
}

// ============================================================================
// 🏆 g2ml_analyticsTopLinks() — top short codes by clicks, org-wide
// ============================================================================

/**
 * Rank an organisation's short codes by click count over a date range.
 *
 * Always org-wide (there is no $shortCode parameter — ranking a single code
 * against itself is meaningless); use g2ml_analyticsTotals() for a single
 * code's own totals.
 *
 * @param  string $orgHandle  The organisation to scope to.
 * @param  string $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @param  int    $limit      Maximum rows to return (default 10, clamped 1-100).
 * @return array<int, array{shortCode: string, clicks: int}>  Ordered descending by clicks.
 */
function g2ml_analyticsTopLinks(string $orgHandle, string $from, string $to, int $limit = 10): array
{
    $clampedLimit = _g2ml_analyticsClampLimit($limit, 10, 100);

    $sql = 'SELECT shortCode, COUNT(*) AS clicks '
        . 'FROM tblActivityLog '
        . "WHERE logAction = 'redirect' AND logStatus = 'success' "
        . 'AND orgHandle = ? AND createdAt >= ? AND createdAt <= ? '
        . 'AND shortCode IS NOT NULL '
        . 'GROUP BY shortCode '
        . 'ORDER BY clicks DESC '
        . 'LIMIT ?';

    $rows = dbSelect($sql, 'sssi', [$orgHandle, $from, $to, $clampedLimit]);

    if ($rows === false)
    {
        return [];
    }

    $topLinks = [];

    foreach ($rows as $row)
    {
        $topLinks[] = [
            'shortCode' => (string) $row['shortCode'],
            'clicks'    => (int) $row['clicks'],
        ];
    }

    return $topLinks;
}

// ============================================================================
// 🧩 g2ml_analyticsBreakdown() — group by a whitelisted dimension
// ============================================================================

/**
 * Break clicks down by a single whitelisted dimension (browser, OS, device
 * type, referrer, or scan source).
 *
 * The dimension name is resolved through _g2ml_analyticsDimensionColumn()
 * FIRST; an unrecognised dimension returns an empty array rather than ever
 * reaching SQL text. Once resolved, the column name is one of exactly five
 * fixed literal strings — it is never derived from arbitrary client input.
 *
 * @param  string      $orgHandle  The organisation to scope to.
 * @param  string|null $shortCode  A specific short code within that org, or null for org-wide.
 * @param  string      $dimension  One of g2ml_analyticsValidDimensions().
 * @param  string      $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string      $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @param  int         $limit      Maximum distinct values to return (clamped 1-100).
 * @return array<int, array{value: string, clicks: int}>  Ordered descending by clicks; empty when $dimension is not whitelisted.
 */
function g2ml_analyticsBreakdown(string $orgHandle, ?string $shortCode, string $dimension, string $from, string $to, int $limit = 10): array
{
    $column = _g2ml_analyticsDimensionColumn($dimension);

    if ($column === null)
    {
        return [];
    }

    $clampedLimit = _g2ml_analyticsClampLimit($limit, 10, 100);
    $whereClause  = _g2ml_analyticsClickWhere($orgHandle, $shortCode, $from, $to);

    $sql = 'SELECT `' . $column . '` AS dimensionValue, COUNT(*) AS clicks '
        . 'FROM tblActivityLog '
        . 'WHERE ' . $whereClause['where'] . ' '
        . 'AND `' . $column . '` IS NOT NULL '
        . 'GROUP BY `' . $column . '` '
        . 'ORDER BY clicks DESC '
        . 'LIMIT ?';

    $types  = $whereClause['types'] . 'i';
    $params = array_merge($whereClause['params'], [$clampedLimit]);

    $rows = dbSelect($sql, $types, $params);

    if ($rows === false)
    {
        return [];
    }

    $breakdown = [];

    foreach ($rows as $row)
    {
        $breakdown[] = [
            'value'  => (string) $row['dimensionValue'],
            'clicks' => (int) $row['clicks'],
        ];
    }

    return $breakdown;
}

// ============================================================================
// 🔗 g2ml_analyticsScanSources() — CueRCode dynamic-QR scan attribution (#145)
// ============================================================================

/**
 * Count clicks attributed to each scan source (currently only 'qr', written
 * by the Component B redirect hot path when a CueRCode-tagged scan is
 * detected — see web/G2My.Link/_functions/redirect_resolver.php and
 * web/G2My.Link/public_html/index.php). A normal (non-QR) click has
 * scanSource = NULL and is excluded here, same as g2ml_analyticsBreakdown()
 * with dimension='scanSource' would exclude it.
 *
 * @param  string      $orgHandle  The organisation to scope to.
 * @param  string|null $shortCode  A specific short code within that org, or null for org-wide.
 * @param  string      $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string      $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @return array<int, array{scanSource: string, scans: int}>  Ordered descending by scans.
 */
function g2ml_analyticsScanSources(string $orgHandle, ?string $shortCode, string $from, string $to): array
{
    $whereClause = _g2ml_analyticsClickWhere($orgHandle, $shortCode, $from, $to);

    $sql = 'SELECT scanSource, COUNT(*) AS scans '
        . 'FROM tblActivityLog '
        . 'WHERE ' . $whereClause['where'] . ' '
        . 'AND scanSource IS NOT NULL '
        . 'GROUP BY scanSource '
        . 'ORDER BY scans DESC';

    $rows = dbSelect($sql, $whereClause['types'], $whereClause['params']);

    if ($rows === false)
    {
        return [];
    }

    $scanSources = [];

    foreach ($rows as $row)
    {
        $scanSources[] = [
            'scanSource' => (string) $row['scanSource'],
            'scans'      => (int) $row['scans'],
        ];
    }

    return $scanSources;
}

// ============================================================================
// 🔑 g2ml_analyticsApiKeyUsage() — per-API-key request counts (#40 descope)
// ============================================================================

/**
 * Count tblAPIRequestLog rows per API key, scoped to an organisation.
 *
 * Org-scoped via an INNER JOIN to tblAPIKeys on k.orgHandle = ? — this means
 * an $apiKeyUID belonging to a DIFFERENT organisation than $orgHandle can
 * never leak that other org's usage: the join simply returns zero rows,
 * mirroring the BOLA guard used by g2ml_apiRevokeKey() (api_auth.php).
 *
 * @param  string   $orgHandle  The organisation to scope to.
 * @param  int|null $apiKeyUID  A specific key within that org, or null for every key in the org.
 * @param  string   $from       Inclusive range start, 'Y-m-d H:i:s'.
 * @param  string   $to         Inclusive range end, 'Y-m-d H:i:s'.
 * @return array<int, array{apiKeyUID: int, keyName: string, apiKeyPrefix: string, requestCount: int, successCount: int, errorCount: int}>
 *                              Ordered descending by requestCount.
 */
function g2ml_analyticsApiKeyUsage(string $orgHandle, ?int $apiKeyUID, string $from, string $to): array
{
    $conditions = [
        'k.orgHandle = ?',
        'r.createdAt >= ?',
        'r.createdAt <= ?',
    ];

    $types  = 'sss';
    $params = [$orgHandle, $from, $to];

    if ($apiKeyUID !== null && $apiKeyUID > 0)
    {
        $conditions[] = 'k.apiKeyUID = ?';
        $types        = $types . 'i';
        $params[]     = $apiKeyUID;
    }

    $whereSQL = implode(' AND ', $conditions);

    $sql = 'SELECT k.apiKeyUID, k.keyName, k.apiKeyPrefix, '
        . 'COUNT(r.requestUID) AS requestCount, '
        . 'SUM(CASE WHEN r.responseCode >= 200 AND r.responseCode < 300 THEN 1 ELSE 0 END) AS successCount, '
        . 'SUM(CASE WHEN r.responseCode >= 400 THEN 1 ELSE 0 END) AS errorCount '
        . 'FROM tblAPIKeys k '
        . 'INNER JOIN tblAPIRequestLog r ON r.apiKeyUID = k.apiKeyUID '
        . 'WHERE ' . $whereSQL . ' '
        . 'GROUP BY k.apiKeyUID, k.keyName, k.apiKeyPrefix '
        . 'ORDER BY requestCount DESC';

    $rows = dbSelect($sql, $types, $params);

    if ($rows === false)
    {
        return [];
    }

    $usage = [];

    foreach ($rows as $row)
    {
        $usage[] = [
            'apiKeyUID'    => (int) $row['apiKeyUID'],
            'keyName'      => (string) $row['keyName'],
            'apiKeyPrefix' => (string) $row['apiKeyPrefix'],
            'requestCount' => (int) $row['requestCount'],
            'successCount' => (int) $row['successCount'],
            'errorCount'   => (int) $row['errorCount'],
        ];
    }

    return $usage;
}
