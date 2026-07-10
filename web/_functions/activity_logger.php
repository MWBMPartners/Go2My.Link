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
 * 📊 Go2My.Link — Activity Logger
 * ============================================================================
 *
 * Logs request/redirect activity to tblActivityLog with basic User-Agent
 * parsing. Uses a direct INSERT (not the sp_logActivity stored procedure)
 * to populate all columns including parsed UA fields in one query.
 *
 * GeoIP columns (countryCode/regionCode/cityName) are populated from the
 * caller's $context (#43 — see web/_functions/geolocation.php) and default
 * to NULL when the caller omits them, exactly like scanSource/
 * qrCodeExternalID (#145) below.
 *
 * Dependencies: db_connect.php (getDB()), security.php (g2ml_getClientIP())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 2 (DNT support added Phase 6)
 *
 * 📖 References:
 *     - tblActivityLog schema: web/_sql/schema/030_analytics.sql
 *     - UA parsing: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
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
// 📝 Log Activity
// ============================================================================

/**
 * Log an activity event to tblActivityLog.
 *
 * Populates all request metadata (domain, path, method, referer, UA) and
 * parses the User-Agent string into structured fields using basic regex.
 *
 * @param  string      $action       Action type (e.g., 'redirect', 'create_link', 'login', 'page_view')
 * @param  string|null $status       Outcome status (e.g., 'success', 'error', 'not_found')
 * @param  int|null    $statusCode   HTTP status code or custom code
 * @param  array       $context      Additional context: orgHandle, userUID, shortCode, destinationURL,
 *                                   apiKeyUID, logData (JSON-encodable array), scanSource,
 *                                   qrCodeExternalID (#145 — CueRCode scan attribution; both default
 *                                   to NULL when absent, so every pre-#145 caller is unaffected),
 *                                   countryCode, regionCode, cityName (#43 — IP geolocation; all
 *                                   three default to NULL when absent, so every pre-#43 caller is
 *                                   unaffected)
 * @return bool                      True if the log was written successfully
 *
 * Usage example:
 *   logActivity('redirect', 'success', 302, [
 *       'orgHandle'      => 'myorg',
 *       'shortCode'      => 'abc123',
 *       'destinationURL' => 'https://example.com',
 *   ]);
 *
 *   logActivity('page_view', 'success', 200, [
 *       'logData' => ['page' => 'homepage'],
 *   ]);
 */
function logActivity(string $action, ?string $status = null, ?int $statusCode = null, array $context = []): bool
{
    // Check if activity logging is enabled
    if (function_exists('getSetting'))
    {
        $loggingEnabled = getSetting('analytics.log_activity', true);

        if ($loggingEnabled === false)
        {
            return true; // Logging disabled — silently succeed
        }
    }

    // DNT/GPC check — skip non-critical logging if user has opted out of tracking.
    // Critical security actions are always logged regardless of DNT preference.
    // 📖 Reference: web/_functions/dnt.php
    if (function_exists('g2ml_shouldTrack') && !g2ml_shouldTrack())
    {
        $alwaysLogActions = [
            'login_failed', 'login_blocked', 'csrf_failure',
            'rate_limited', 'consent_recorded', 'account_locked',
            'password_reset_requested', 'data_deletion_requested',
        ];

        if (!in_array($action, $alwaysLogActions, true))
        {
            return true; // DNT active — skip non-critical logging
        }
    }

    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] WARNING: logActivity failed — no database connection.');
        return false;
    }

    // Extract context values with defaults
    $orgHandle      = $context['orgHandle'] ?? null;
    $userUID        = $context['userUID'] ?? ($_SESSION['user_uid'] ?? null);
    $shortCode      = $context['shortCode'] ?? null;
    $destinationURL = $context['destinationURL'] ?? null;
    $apiKeyUID      = $context['apiKeyUID'] ?? null;
    if (isset($context['logData'])) {
        $logData = json_encode($context['logData']);
    } else {
        $logData = null;
    }

    // CueRCode scan attribution (#145) — both default to NULL when absent, so
    // every caller that predates this option (every redirect/action logged
    // before #145, and any non-redirect action today) is completely
    // unaffected. The caller (the Component B redirect hot path) is
    // responsible for only ever passing a value here after it has already
    // verified the scan-source param against the operator-configured
    // expected value AND looked qrCodeExternalID up from the STORED short
    // URL row — logActivity() itself just binds whatever it is given.
    $scanSource       = $context['scanSource'] ?? null;
    $qrCodeExternalID = $context['qrCodeExternalID'] ?? null;

    if ($qrCodeExternalID !== null)
    {
        $qrCodeExternalID = (int) $qrCodeExternalID;
    }

    // IP geolocation (#43) — both default to NULL when absent, so every
    // caller that predates this option is completely unaffected. The caller
    // (the Component B redirect hot path) is responsible for only ever
    // passing a value here after g2ml_geolocationAvailable() confirmed
    // geolocation is enabled and a database is present — logActivity()
    // itself just binds whatever it is given, with no lookup of its own.
    $countryCode = $context['countryCode'] ?? null;
    $regionCode  = $context['regionCode'] ?? null;
    $cityName    = $context['cityName'] ?? null;

    // Gather request metadata
    $requestDomain  = $_SERVER['HTTP_HOST'] ?? null;
    $requestPath    = $_SERVER['REQUEST_URI'] ?? null;
    $requestMethod  = $_SERVER['REQUEST_METHOD'] ?? null;
    $requestReferer = $_SERVER['HTTP_REFERER'] ?? null;
    $requestUA      = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (function_exists('g2ml_getClientIP')) {
        $ipAddress = g2ml_getClientIP();
    } else {
        $ipAddress = ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    // Parse User-Agent for structured fields
    $uaParsed = _g2ml_parseUserAgent($requestUA);

    // Truncate long fields to match column limits
    if ($requestPath !== null && strlen($requestPath) > 500)
    {
        $requestPath = substr($requestPath, 0, 500);
    }

    if ($requestReferer !== null && strlen($requestReferer) > 500)
    {
        $requestReferer = substr($requestReferer, 0, 500);
    }

    if ($requestUA !== null && strlen($requestUA) > 500)
    {
        $requestUA = substr($requestUA, 0, 500);
    }

    // scanSource is untrusted-input-derived (#145) — cap it to the
    // tblActivityLog.scanSource VARCHAR(50) column width defensively, even
    // though the caller is expected to have already bounded it to a known
    // configured value rather than raw query-string text.
    if ($scanSource !== null && strlen($scanSource) > 50)
    {
        $scanSource = substr($scanSource, 0, 50);
    }

    // Geolocation fields (#43) are derived from the vendored MaxMind reader
    // (web/_functions/geolocation.php), not raw request input, but are
    // capped defensively to their column widths anyway — the same
    // defence-in-depth posture as scanSource above.
    if ($countryCode !== null && strlen($countryCode) > 2)
    {
        $countryCode = substr($countryCode, 0, 2);
    }

    if ($regionCode !== null && strlen($regionCode) > 10)
    {
        $regionCode = substr($regionCode, 0, 10);
    }

    if ($cityName !== null && strlen($cityName) > 255)
    {
        $cityName = substr($cityName, 0, 255);
    }

    try
    {
        $sql = "INSERT INTO tblActivityLog (
                    logAction, logStatus, statusCode,
                    orgHandle, userUID, shortCode, destinationURL,
                    requestDomain, requestPath, requestMethod,
                    requestReferer, requestUserAgent,
                    browserName, browserVersion, osName, osVersion, deviceType,
                    ipAddress, isBot,
                    apiKeyUID, logData,
                    scanSource, qrCodeExternalID,
                    countryCode, regionCode, cityName
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?,
                    ?, ?,
                    ?, ?, ?
                )";

        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            error_log('[Go2My.Link] ERROR: logActivity prepare failed: ' . $db->error);
            return false;
        }

        // Type string MUST have exactly one character per bound variable (26,
        // extended from 23 by #43 — the three new TRAILING columns below),
        // each matching its column: s=string, i=int. RECOUNT placeholders vs
        // columns vs this type string on every future change to this INSERT.
        //   1  s  logAction        (VARCHAR)
        //   2  s  logStatus        (VARCHAR)
        //   3  i  statusCode       (SMALLINT UNSIGNED)
        //   4  s  orgHandle        (VARCHAR)
        //   5  i  userUID          (BIGINT UNSIGNED)
        //   6  s  shortCode        (VARCHAR)
        //   7  s  destinationURL   (TEXT)
        //   8  s  requestDomain    (VARCHAR)
        //   9  s  requestPath      (VARCHAR)
        //   10 s  requestMethod    (VARCHAR)
        //   11 s  requestReferer   (VARCHAR)
        //   12 s  requestUserAgent (VARCHAR)
        //   13 s  browserName      (VARCHAR)
        //   14 s  browserVersion   (VARCHAR)
        //   15 s  osName           (VARCHAR)
        //   16 s  osVersion        (VARCHAR)
        //   17 s  deviceType       (VARCHAR)
        //   18 s  ipAddress        (VARCHAR — NOT an int)
        //   19 i  isBot            (TINYINT UNSIGNED)
        //   20 i  apiKeyUID        (BIGINT UNSIGNED)
        //   21 s  logData          (JSON, bound as a string)
        //   22 s  scanSource       (VARCHAR(50) — #145, NULL when absent)
        //   23 i  qrCodeExternalID (BIGINT UNSIGNED — #145, NULL when absent)
        //   24 s  countryCode      (CHAR(2) — #43, NULL when absent/unknown)
        //   25 s  regionCode       (VARCHAR(10) — #43, NULL when absent/unknown)
        //   26 s  cityName         (VARCHAR(255) — #43, NULL when absent/unknown)
        $stmt->bind_param(
            'ssisisssssssssssssiississs',
            $action,
            $status,
            $statusCode,
            $orgHandle,
            $userUID,
            $shortCode,
            $destinationURL,
            $requestDomain,
            $requestPath,
            $requestMethod,
            $requestReferer,
            $requestUA,
            $uaParsed['browserName'],
            $uaParsed['browserVersion'],
            $uaParsed['osName'],
            $uaParsed['osVersion'],
            $uaParsed['deviceType'],
            $ipAddress,
            $uaParsed['isBot'],
            $apiKeyUID,
            $logData,
            $scanSource,
            $qrCodeExternalID,
            $countryCode,
            $regionCode,
            $cityName
        );

        $stmt->execute();
        $stmt->close();

        return true;
    }
    catch (\Throwable $e)
    {
        // Don't let activity logging failures break the application
        error_log('[Go2My.Link] ERROR: logActivity exception: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// 🔍 Basic User-Agent Parser
// ============================================================================
// Provides basic browser, OS, and device type detection using regex patterns.
// This is a lightweight parser for Phase 2 — will be replaced with the
// WhichBrowser library in Phase 6 for more accurate detection.
//
// 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
// ============================================================================

/**
 * Parse a User-Agent string into structured components.
 *
 * @param  string|null $userAgent  The raw User-Agent string
 * @return array                   Associative array with keys:
 *                                 browserName, browserVersion, osName, osVersion,
 *                                 deviceType, isBot
 */
function _g2ml_parseUserAgent(?string $userAgent): array
{
    $result = [
        'browserName'    => null,
        'browserVersion' => null,
        'osName'         => null,
        'osVersion'      => null,
        'deviceType'     => null,
        'isBot'          => null,
    ];

    if ($userAgent === null || $userAgent === '')
    {
        return $result;
    }

    // ========================================================================
    // Bot detection — check first since bots fake other UA strings
    // ========================================================================
    $botPatterns = [
        'Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider',
        'YandexBot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot',
        'WhatsApp', 'TelegramBot', 'Discordbot', 'Applebot',
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests',
        'Go-http-client', 'Java/', 'axios', 'node-fetch', 'PostmanRuntime',
    ];

    // Escape each pattern for the '/' delimiter — several patterns (e.g. 'Java/')
    // contain a slash that would otherwise close the delimiter early and raise a
    // PCRE "Unknown modifier" warning, silently breaking bot detection (B-043).
    // 📖 Reference: https://www.php.net/manual/en/function.preg-quote.php
    // 📖 Reference: https://www.php.net/manual/en/function.preg-match.php
    $quotedBotPatterns = array_map(
        function (string $botPattern): string
        {
            return preg_quote($botPattern, '/');
        },
        $botPatterns
    );
    $botRegex = '/' . implode('|', $quotedBotPatterns) . '/i';

    if (preg_match($botRegex, $userAgent))
    {
        $result['isBot']       = 1;
        $result['deviceType']  = 'bot';
        $result['browserName'] = 'Bot';

        // Try to extract specific bot name
        if (preg_match('/([A-Za-z]+bot|crawler|spider)/i', $userAgent, $matches))
        {
            $result['browserName'] = $matches[1];
        }

        return $result;
    }

    $result['isBot'] = 0;

    // ========================================================================
    // Browser detection (order matters — check specific before generic)
    // ========================================================================
    $browsers = [
        // Edge must come before Chrome (Edge contains "Chrome" in UA)
        ['pattern' => '/Edg(?:e|A|iOS)?\/(\d+[\.\d]*)/', 'name' => 'Edge'],
        // Opera must come before Chrome (Opera contains "Chrome" in UA)
        ['pattern' => '/OPR\/(\d+[\.\d]*)/',              'name' => 'Opera'],
        // Samsung Internet must come before Chrome
        ['pattern' => '/SamsungBrowser\/(\d+[\.\d]*)/',    'name' => 'Samsung Internet'],
        // Vivaldi must come before Chrome
        ['pattern' => '/Vivaldi\/(\d+[\.\d]*)/',           'name' => 'Vivaldi'],
        // Brave doesn't always identify itself; check before Chrome
        ['pattern' => '/Brave\/(\d+[\.\d]*)/',             'name' => 'Brave'],
        // Standard browsers
        ['pattern' => '/Firefox\/(\d+[\.\d]*)/',           'name' => 'Firefox'],
        ['pattern' => '/Chrome\/(\d+[\.\d]*)/',            'name' => 'Chrome'],
        ['pattern' => '/Safari\/(\d+[\.\d]*)/',            'name' => 'Safari'],
        // IE detection
        ['pattern' => '/MSIE (\d+[\.\d]*)/',              'name' => 'Internet Explorer'],
        ['pattern' => '/Trident.*rv:(\d+[\.\d]*)/',        'name' => 'Internet Explorer'],
    ];

    foreach ($browsers as $browser)
    {
        if (preg_match($browser['pattern'], $userAgent, $matches))
        {
            $result['browserName']    = $browser['name'];
            $result['browserVersion'] = $matches[1];

            // Special case: Safari version is in "Version/" not "Safari/"
            if ($browser['name'] === 'Safari' && preg_match('/Version\/(\d+[\.\d]*)/', $userAgent, $versionMatches))
            {
                $result['browserVersion'] = $versionMatches[1];
            }

            break;
        }
    }

    // ========================================================================
    // OS detection
    // ========================================================================
    if (preg_match('/Windows NT (\d+\.\d+)/', $userAgent, $matches))
    {
        $result['osName'] = 'Windows';

        // Map Windows NT versions to marketing names
        $result['osVersion'] = match ($matches[1])
        {
            '10.0' => '10/11', // Windows 10 and 11 share NT 10.0
            '6.3'  => '8.1',
            '6.2'  => '8',
            '6.1'  => '7',
            default => $matches[1],
        };
    }
    elseif (preg_match('/Mac OS X (\d+[._]\d+[._]?\d*)/', $userAgent, $matches))
    {
        $result['osName']    = 'macOS';
        $result['osVersion'] = str_replace('_', '.', $matches[1]);
    }
    elseif (preg_match('/iPhone OS (\d+[._]\d+)/', $userAgent, $matches))
    {
        $result['osName']    = 'iOS';
        $result['osVersion'] = str_replace('_', '.', $matches[1]);
    }
    elseif (preg_match('/Android (\d+[\.\d]*)/', $userAgent, $matches))
    {
        $result['osName']    = 'Android';
        $result['osVersion'] = $matches[1];
    }
    elseif (preg_match('/CrOS/', $userAgent))
    {
        $result['osName'] = 'Chrome OS';
    }
    elseif (preg_match('/Linux/', $userAgent))
    {
        $result['osName'] = 'Linux';
    }

    // ========================================================================
    // Device type detection
    // ========================================================================
    if (preg_match('/iPad/', $userAgent))
    {
        $result['deviceType'] = 'tablet';
    }
    elseif (preg_match('/iPhone|iPod/', $userAgent))
    {
        $result['deviceType'] = 'mobile';
    }
    elseif (preg_match('/Android/', $userAgent))
    {
        // Android tablets typically don't have "Mobile" in the UA
        if (preg_match('/Mobile/', $userAgent))
        {
            $result['deviceType'] = 'mobile';
        }
        else
        {
            $result['deviceType'] = 'tablet';
        }
    }
    elseif (preg_match('/Windows Phone/', $userAgent))
    {
        $result['deviceType'] = 'mobile';
    }
    else
    {
        $result['deviceType'] = 'desktop';
    }

    return $result;
}
