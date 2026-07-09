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
 * scan per window. The daily limit is the key's own `rateLimitOverride` when
 * set, otherwise the `api.default_daily_limit` setting; the burst limit is
 * always `api.default_per_minute`.
 *
 * @param  array $keyRow  A verified key row (see g2ml_apiVerifyKey()); only
 *                         `apiKeyUID` and `rateLimitOverride` are read.
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
        $dailyLimit = (int) getSetting('api.default_daily_limit', 5000);
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
            $encodedBody = $encodedCandidate;
        }
    }

    $truncatedUserAgent = null;

    if ($userAgent !== null)
    {
        $truncatedUserAgent = substr($userAgent, 0, 500);
    }

    dbInsert(
        'INSERT INTO tblAPIRequestLog '
        . '(apiKeyUID, endpoint, httpMethod, responseCode, responseTimeMs, ipAddress, userAgent, requestBody) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'issiisss',
        [$apiKeyUID, $endpoint, $httpMethod, $responseCode, $responseTimeMs, $ipAddress, $truncatedUserAgent, $encodedBody]
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
