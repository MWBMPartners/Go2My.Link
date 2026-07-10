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
 * ⏱️ Go2My.Link — Public API: DB-backed Rate Limiting + Request Log (#38)
 * ============================================================================
 *
 * No background daemon is available on Dreamhost shared hosting, so the rate
 * limiter counts recent rows in tblAPIRequestLog directly (the same pattern
 * already used by checkAnonymousRateLimit() against tblActivityLog — see
 * web/Go2My.Link/_functions/shorturl_create.php). Every API request writes
 * exactly one tblAPIRequestLog row, which doubles as the rate-limit source
 * and the audit trail.
 *
 * NEVER stored here: the Authorization header, the presented API key, or any
 * password/token-shaped field in a request body — g2ml_apiRedactRequestBody()
 * strips them before the row is written.
 *
 * Dependencies: db_query.php, settings.php (getSetting()).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#38)
 *
 * 📖 References:
 *     - tblAPIRequestLog schema: web/_sql/schema/031_api.sql
 *     - Composite index:         web/_sql/migrations/011_api_request_log_index.sql
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
// 🚦 Rate limit check
// ============================================================================

/**
 * Check a verified key against its daily and per-minute (burst) rate limits.
 *
 * Counts tblAPIRequestLog rows for this apiKeyUID over two windows (24 hours
 * and 60 seconds) via IDX_apireq_key_created, a single composite-index range
 * scan per window. The daily limit is resolved in this priority order (#146):
 *
 *   1. The key's own `rateLimitOverride`, when set (a per-key override always
 *      wins — an operator who set one explicitly wants it honoured verbatim).
 *   2. The owning org's tier `maxAPIRequestsPerDay` (via g2ml_getOrgTier() —
 *      NULL on an unlimited/GlobalAdmin/'[default]' tier means no cap here).
 *   3. The `api.default_daily_limit` setting, when the tier ALSO has no cap
 *      (NULL) — preserves pre-#146 behaviour for every key on an unlimited tier.
 *
 * The burst limit is always `api.default_per_minute` (unaffected by #146 —
 * tiers do not model a burst rate).
 *
 * @param  array $keyRow  A verified key row (see g2ml_apiVerifyKey()); reads
 *                         `apiKeyUID`, `rateLimitOverride`, and `orgHandle`.
 * @return array           ['allowed'=>bool, 'limit'=>int, 'remaining'=>int,
 *                          'resetAt'=>string (ISO8601 UTC), 'retryAfter'=>int (seconds, 0 when allowed)]
 *
 * Usage example:
 *   $rateLimit = g2ml_apiCheckRateLimit($keyRow);
 *   if (!$rateLimit['allowed']) {
 *       header('Retry-After: ' . $rateLimit['retryAfter']);
 *       // ... 429 ...
 *   }
 */
function g2ml_apiCheckRateLimit(array $keyRow): array
{
    $apiKeyUID = (int) $keyRow['apiKeyUID'];

    if (isset($keyRow['rateLimitOverride']) && $keyRow['rateLimitOverride'] !== null)
    {
        $dailyLimit = (int) $keyRow['rateLimitOverride'];
    }
    else
    {
        // No per-key override — defer to the owning org's tier (#146). A
        // missing/blank orgHandle on the key row falls back to '[default]',
        // which g2ml_getOrgTier() always resolves as unlimited, exactly
        // preserving the pre-#146 "use the setting" behaviour for such rows.
        $tierDailyLimit = null;

        if (function_exists('g2ml_getOrgTier'))
        {
            $keyOrgHandle = '[default]';

            if (isset($keyRow['orgHandle']) && is_string($keyRow['orgHandle']) && $keyRow['orgHandle'] !== '')
            {
                $keyOrgHandle = $keyRow['orgHandle'];
            }

            $tier           = g2ml_getOrgTier($keyOrgHandle);
            $tierDailyLimit = $tier['maxAPIRequestsPerDay'] ?? null;
        }

        if ($tierDailyLimit !== null)
        {
            $dailyLimit = (int) $tierDailyLimit;
        }
        else
        {
            $dailyLimit = (int) getSetting('api.default_daily_limit', 5000);
        }
    }

    $burstLimit = (int) getSetting('api.default_per_minute', 60);

    $dailyRow = dbSelectOne(
        'SELECT COUNT(*) AS cnt FROM tblAPIRequestLog '
        . 'WHERE apiKeyUID = ? AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
        'i',
        [$apiKeyUID]
    );

    if ($dailyRow !== null && $dailyRow !== false)
    {
        $dailyCount = (int) $dailyRow['cnt'];
    }
    else
    {
        $dailyCount = 0;
    }

    $burstRow = dbSelectOne(
        'SELECT COUNT(*) AS cnt FROM tblAPIRequestLog '
        . 'WHERE apiKeyUID = ? AND createdAt >= DATE_SUB(NOW(), INTERVAL 60 SECOND)',
        'i',
        [$apiKeyUID]
    );

    if ($burstRow !== null && $burstRow !== false)
    {
        $burstCount = (int) $burstRow['cnt'];
    }
    else
    {
        $burstCount = 0;
    }

    $dailyExceeded = $dailyCount >= $dailyLimit;
    $burstExceeded = $burstCount >= $burstLimit;

    if ($dailyExceeded)
    {
        return [
            'allowed'    => false,
            'limit'      => $dailyLimit,
            'remaining'  => 0,
            'resetAt'    => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
            'retryAfter' => 86400,
        ];
    }

    if ($burstExceeded)
    {
        return [
            'allowed'    => false,
            'limit'      => $burstLimit,
            'remaining'  => 0,
            'resetAt'    => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
            'retryAfter' => 60,
        ];
    }

    $remaining = min($dailyLimit - $dailyCount, $burstLimit - $burstCount);

    if ($remaining < 0)
    {
        $remaining = 0;
    }

    return [
        'allowed'    => true,
        'limit'      => $dailyLimit,
        'remaining'  => $remaining,
        'resetAt'    => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
        'retryAfter' => 0,
    ];
}

// ============================================================================
// 🧯 Pre-authentication IP failed-auth backoff (#39 — residual #1 from #38 review)
// ============================================================================

/**
 * Check a client IP against the failed-authentication backoff BEFORE the
 * front controller ever calls g2ml_apiVerifyKey().
 *
 * Counts tblAPIRequestLog rows for the given IP with responseCode = 401 in
 * the last 60 seconds (via IDX_apireq_ip_created — see
 * web/_sql/migrations/012_api_request_log_ip_index.sql / the folded-in index
 * in 031_api.sql). If at or above api.preauth_fail_per_minute, the caller
 * must short-circuit with 429 (+ Retry-After) BEFORE attempting verification,
 * so a flood of missing/invalid keys cannot cheaply grow the audit log or
 * repeatedly pay the cost of the prefix lookup + hash compare + owner/org
 * lookups inside g2ml_apiVerifyKey(). This check itself is a single indexed
 * COUNT(*) — far cheaper than the verification path it is gating.
 *
 * Self-limiting by construction: once the backoff trips, subsequent requests
 * from that IP receive 429 (responseCode 429, not 401), so they stop adding
 * to the very count that trips the gate — the window simply has to elapse.
 *
 * A non-positive `api.preauth_fail_per_minute` setting disables the guard
 * entirely (an operator who sets it to 0 wants it OFF, not "block everything").
 *
 * @param  string $ipAddress  The client IP (g2ml_getClientIP()).
 * @return array               ['allowed'=>bool, 'retryAfter'=>int (seconds, 0 when allowed)]
 *
 * Usage example:
 *   $backoff = g2ml_apiCheckPreAuthBackoff(g2ml_getClientIP());
 *   if (!$backoff['allowed']) {
 *       header('Retry-After: ' . $backoff['retryAfter']);
 *       // ... 429, WITHOUT ever calling g2ml_apiVerifyKey() ...
 *   }
 */
function g2ml_apiCheckPreAuthBackoff(string $ipAddress): array
{
    $failLimit = (int) getSetting('api.preauth_fail_per_minute', 20);

    if ($failLimit <= 0)
    {
        return [
            'allowed'    => true,
            'retryAfter' => 0,
        ];
    }

    $failRow = dbSelectOne(
        'SELECT COUNT(*) AS cnt FROM tblAPIRequestLog '
        . 'WHERE ipAddress = ? AND responseCode = 401 AND createdAt >= DATE_SUB(NOW(), INTERVAL 60 SECOND)',
        's',
        [$ipAddress]
    );

    if ($failRow !== null && $failRow !== false)
    {
        $failCount = (int) $failRow['cnt'];
    }
    else
    {
        $failCount = 0;
    }

    if ($failCount >= $failLimit)
    {
        return [
            'allowed'    => false,
            'retryAfter' => 60,
        ];
    }

    return [
        'allowed'    => true,
        'retryAfter' => 0,
    ];
}

// ============================================================================
// 🙈 Request body redaction
// ============================================================================

/**
 * Recursively redact any key whose name looks credential-shaped.
 *
 * Applied to every request body before it is written to tblAPIRequestLog.
 * Matching is substring/case-insensitive on the KEY name (not the value), so
 * "password", "new_password", "apiKey", "x-api-key" etc. are all caught.
 *
 * @param  array $body  The decoded request body.
 * @return array         The same structure with sensitive values replaced.
 */
function g2ml_apiRedactRequestBody(array $body): array
{
    $sensitiveNeedles = [
        'authorization', 'apikey', 'api_key', 'api-key', 'password',
        'passwordhash', 'secret', 'token', 'plaintextkey', 'bearer',
        'csrf', 'sessiontoken', 'session_token',
    ];

    $redacted = [];

    foreach ($body as $key => $value)
    {
        $lowerKey = '';

        if (is_string($key))
        {
            $lowerKey = strtolower($key);
        }

        $isSensitive = false;

        foreach ($sensitiveNeedles as $needle)
        {
            if ($lowerKey !== '' && str_contains($lowerKey, $needle))
            {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive)
        {
            $redacted[$key] = '[REDACTED]';
        }
        elseif (is_array($value))
        {
            $redacted[$key] = g2ml_apiRedactRequestBody($value);
        }
        else
        {
            $redacted[$key] = $value;
        }
    }

    return $redacted;
}

// ============================================================================
// ✂️ Audit-column bounding (security — an oversized field must NEVER be able to
// fail the audit-log INSERT)
// ============================================================================

/**
 * Bound an untrusted string to a destination column's width, stripping any
 * control characters (including CR/LF) first.
 *
 * Pure and DB-free so it is directly unit-testable.
 *
 * SECURITY: tblAPIRequestLog.endpoint (VARCHAR 255), httpMethod (VARCHAR 10)
 * and ipAddress (VARCHAR 45) are written from request-derived values — and the
 * endpoint in particular embeds the RAW, unsanitised `_apiroute` so malformed
 * attempts stay auditable. Under MySQL strict mode an over-length value makes
 * the whole INSERT throw, which g2ml_apiLogRequest() swallows — silently
 * dropping the audit row. Because the DB-backed rate limiter AND the pre-auth
 * IP failed-auth backoff both COUNT rows in this same table, a dropped row is
 * also an uncounted request: an attacker could otherwise send a flood of
 * invalid-key requests (each still paying the full g2ml_apiVerifyKey() cost)
 * carrying a >255-character route, none of which would ever be logged or count
 * toward the backoff — defeating the very control that exists to stop that
 * flood. Bounding every such field here makes the audit write robust against
 * that sabotage. Stripping control characters additionally prevents a raw
 * newline in a percent-decoded route from injecting a forged line into the
 * server error log at any caller that also error_log()s the value.
 *
 * @param  string|null $value      The untrusted value, or null.
 * @param  int         $maxLength  The destination column width (bytes).
 * @return string|null             The stripped, length-capped value, or null.
 */
function g2ml_apiBoundLogValue(?string $value, int $maxLength): ?string
{
    if ($value === null)
    {
        return null;
    }

    $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

    if ($stripped === null)
    {
        $stripped = '';
    }

    if (strlen($stripped) > $maxLength)
    {
        $stripped = substr($stripped, 0, $maxLength);
    }

    return $stripped;
}

// ============================================================================
// 📝 Request logging
// ============================================================================

/**
 * Write one audit-trail row to tblAPIRequestLog. Called on EVERY request the
 * front controller handles — authenticated or not, successful or not — so
 * the log doubles as both the rate-limit source and the security audit
 * trail (failed-auth attempts, scope denials, 5xx, etc. are all captured).
 *
 * @param  int|null   $apiKeyUID      The authenticated key, or null (auth failed / not yet known).
 * @param  string     $endpoint       The requested endpoint path, e.g. '/api/v1/account'.
 * @param  string     $httpMethod     The HTTP method, e.g. 'GET'.
 * @param  int        $responseCode   The HTTP status code returned.
 * @param  int|null   $responseTimeMs Wall-clock time spent handling the request, in milliseconds.
 * @param  string     $ipAddress      The client IP (tblAPIRequestLog.ipAddress is NOT NULL —
 *                                     always pass g2ml_getClientIP()).
 * @param  string|null $userAgent     The raw User-Agent header, truncated to fit the column.
 * @param  array|null  $requestBody   The decoded request body, or null for bodiless requests.
 *                                     Redacted before storage — see g2ml_apiRedactRequestBody().
 * @return void
 */
function g2ml_apiLogRequest(?int $apiKeyUID, string $endpoint, string $httpMethod, int $responseCode, ?int $responseTimeMs, string $ipAddress, ?string $userAgent, ?array $requestBody): void
{
    $encodedBody = null;

    if ($requestBody !== null)
    {
        $redactedBody = g2ml_apiRedactRequestBody($requestBody);
        $encodedCandidate = json_encode($redactedBody);

        if ($encodedCandidate !== false)
        {
            // The requestBody column is JSON, so it must stay VALID JSON — an
            // over-length body is replaced with a small valid-JSON marker
            // rather than truncated mid-structure (which would fail the JSON
            // column's validation and drop the whole audit row).
            if (strlen($encodedCandidate) > 60000)
            {
                $encodedBody = '{"_note":"request body omitted from audit log (too large)"}';
            }
            else
            {
                $encodedBody = $encodedCandidate;
            }
        }
    }

    $truncatedUserAgent = null;

    if ($userAgent !== null)
    {
        $truncatedUserAgent = substr($userAgent, 0, 500);
    }

    // Bound every request-derived string to its column width (stripping control
    // characters) so an oversized/hostile value can never fail the INSERT and
    // silently drop the audit row — see g2ml_apiBoundLogValue() for why that
    // would also let a failed-auth flood evade the pre-auth backoff.
    $boundedEndpoint   = g2ml_apiBoundLogValue($endpoint, 255);
    $boundedHttpMethod = g2ml_apiBoundLogValue($httpMethod, 10);
    $boundedIpAddress  = g2ml_apiBoundLogValue($ipAddress, 45);

    dbInsert(
        'INSERT INTO tblAPIRequestLog '
        . '(apiKeyUID, endpoint, httpMethod, responseCode, responseTimeMs, ipAddress, userAgent, requestBody) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'issiisss',
        [$apiKeyUID, $boundedEndpoint, $boundedHttpMethod, $responseCode, $responseTimeMs, $boundedIpAddress, $truncatedUserAgent, $encodedBody]
    );

    // Probabilistic prune — Dreamhost cron is limited, so old rows are swept
    // opportunistically (mirrors the same 1-in-100 pattern used for session
    // cleanup in _includes/page_init.php) rather than via a scheduled job.
    if (mt_rand(1, 100) === 1)
    {
        $retentionDays = (int) getSetting('api.request_log_retention_days', 30);

        if ($retentionDays > 0)
        {
            dbDelete(
                'DELETE FROM tblAPIRequestLog WHERE createdAt < DATE_SUB(NOW(), INTERVAL ? DAY)',
                'i',
                [$retentionDays]
            );
        }
    }
}
