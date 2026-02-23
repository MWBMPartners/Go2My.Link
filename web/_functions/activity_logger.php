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
 * GeoIP columns are left NULL until Phase 6 (MaxMind GeoLite2 library).
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
 *                                   apiKeyUID, logData (JSON-encodable array)
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

    try
    {
        $sql = "INSERT INTO tblActivityLog (
                    logAction, logStatus, statusCode,
                    orgHandle, userUID, shortCode, destinationURL,
                    requestDomain, requestPath, requestMethod,
                    requestReferer, requestUserAgent,
                    browserName, browserVersion, osName, osVersion, deviceType,
                    ipAddress, isBot,
                    apiKeyUID, logData
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?
                )";

        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            error_log('[Go2My.Link] ERROR: logActivity prepare failed: ' . $db->error);
            return false;
        }

        $stmt->bind_param(
            'ssisissssssssssssiis',
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
            $logData
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

    // 📖 Reference: https://www.php.net/manual/en/function.preg-match.php
    $botRegex = '/' . implode('|', array_map('preg_quote', $botPatterns)) . '/i';

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
