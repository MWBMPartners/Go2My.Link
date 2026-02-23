<?php
/**
 * ============================================================================
 * ✨ GoToMyLink — Short URL Creation Logic (Component A)
 * ============================================================================
 *
 * Provides functions for creating short URLs, rate limiting anonymous
 * creation, and verifying CAPTCHA responses.
 *
 * Functions:
 *   - createShortURL()           — Create a new short URL
 *   - checkAnonymousRateLimit()  — Check IP-based rate limits
 *   - getDefaultShortDomain()    — Get the default short domain for an org
 *   - verifyCaptcha()            — Verify Turnstile/reCAPTCHA response
 *
 * Dependencies: db_query.php, settings.php, security.php, activity_logger.php
 *               (all loaded via page_init.php)
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
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
// ✨ createShortURL — Create a new short URL
// ============================================================================
// Creates a new short URL record in tblShortURLs. Generates a unique random
// short code via the sp_generateShortCode stored procedure, validates the
// destination URL, and returns the complete short URL.
//
// Anonymous users have restrictions: no custom suffix, no date ranges,
// no notes, no categories.
//
// 📖 Reference: web/_sql/procedures/sp_generateShortCode.sql
// 📖 Reference: https://www.php.net/manual/en/function.filter-var.php
//
// @param  string $longURL   The destination URL to shorten
// @param  array  $options   Optional overrides:
//   - orgHandle:   (string) Org handle (default: '[default]')
//   - codeLength:  (int) Short code length (default: from settings)
//   - userUID:     (int|null) Creating user's UID (null for anonymous)
//   - title:       (string|null) Link title
//   - categoryID:  (string|null) Category ID
//   - startDate:   (string|null) Start date (ISO 8601 format)
//   - endDate:     (string|null) End date (ISO 8601 format)
//   - notes:       (string|null) Internal notes
// @return array  ['success' => bool, 'shortCode' => ?string,
//                 'shortURL' => ?string, 'error' => ?string]
// ============================================================================
function createShortURL(string $longURL, array $options = []): array
{
    // ========================================================================
    // 📋 Step 1: Validate and sanitise the URL
    // ========================================================================
    // 📖 Reference: web/_functions/security.php — g2ml_sanitiseURL()
    $longURL = trim($longURL);

    if ($longURL === '')
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'URL is required.',
        ];
    }

    // Validate URL format (must be http:// or https://)
    $sanitisedURL = g2ml_sanitiseURL($longURL);

    if ($sanitisedURL === false)
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Invalid URL format. Please enter a valid HTTP or HTTPS URL.',
        ];
    }

    // ========================================================================
    // 🛡️ Step 2: Block self-referencing URLs
    // ========================================================================
    // Prevent users from shortening URLs that point to our own short domains,
    // which would create redirect loops.
    //
    // 📖 Reference: https://www.php.net/manual/en/function.parse-url.php
    // ========================================================================
    $parsed = parse_url($sanitisedURL);
    $urlHost = strtolower($parsed['host'] ?? '');

    // Strip www. prefix for comparison
    if (strpos($urlHost, 'www.') === 0)
    {
        $urlHost = substr($urlHost, 4);
    }

    // Built-in blocked domains
    $blockedDomains = ['g2my.link', 'go2my.link', 'lnks.page'];

    // Also check registered custom short domains from the database
    $customDomains = dbSelect(
        "SELECT shortDomain FROM tblOrgShortDomains WHERE isActive = 1",
        '',
        []
    );

    if ($customDomains !== false && is_array($customDomains))
    {
        foreach ($customDomains as $row)
        {
            $blockedDomains[] = strtolower($row['shortDomain']);
        }
    }

    if (in_array($urlHost, $blockedDomains, true))
    {
        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Cannot shorten URLs that point to this service.',
        ];
    }

    // ========================================================================
    // 📋 Step 3: Extract options
    // ========================================================================
    $orgHandle  = $options['orgHandle'] ?? '[default]';
    $userUID    = $options['userUID'] ?? null;
    $title      = $options['title'] ?? null;
    $categoryID = $options['categoryID'] ?? null;
    $startDate  = $options['startDate'] ?? null;
    $endDate    = $options['endDate'] ?? null;
    $notes      = $options['notes'] ?? null;

    // Determine code length from options or settings
    $codeLength = $options['codeLength']
        ?? (int) getSetting('shortcode.anonymous_length',
            getSetting('shortcode.default_length', 7));

    // ========================================================================
    // 🎲 Step 4: Generate a unique short code
    // ========================================================================
    // 📖 Reference: web/_sql/procedures/sp_generateShortCode.sql
    $spResult = dbCallProcedure(
        'sp_generateShortCode',
        [$orgHandle, $codeLength],
        'si',
        ['@outputCode']
    );

    if ($spResult === false || empty($spResult['@outputCode']))
    {
        error_log('[GoToMyLink] ERROR: sp_generateShortCode failed for org: ' . $orgHandle);

        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Failed to generate a unique short code. Please try again.',
        ];
    }

    $shortCode = $spResult['@outputCode'];

    // ========================================================================
    // 💾 Step 5: Insert the short URL record
    // ========================================================================
    // 📖 Reference: web/_functions/db_query.php — dbInsert()
    $insertSQL = "INSERT INTO tblShortURLs
        (orgHandle, shortCode, destinationURL, destinationType,
         createdByUserUID, title, categoryID, urlNotes,
         startDate, endDate, isActive, createdAt, updatedAt)
        VALUES
        (?, ?, ?, 'url',
         ?, ?, ?, ?,
         ?, ?, 1, NOW(), NOW())";

    // Build the types string and params array
    // s = string, i = integer (nullable userUID handled as string to support NULL)
    $types  = 'sss';
    $params = [$orgHandle, $shortCode, $sanitisedURL];

    // createdByUserUID (nullable integer)
    if ($userUID !== null)
    {
        $types   .= 'i';
        $params[] = (int) $userUID;
    }
    else
    {
        $types   .= 's';
        $params[] = null;
    }

    // title (nullable string)
    $types   .= 's';
    $params[] = $title;

    // categoryID (nullable string)
    $types   .= 's';
    $params[] = $categoryID;

    // urlNotes (nullable string)
    $types   .= 's';
    $params[] = $notes;

    // startDate (nullable string)
    $types   .= 's';
    $params[] = $startDate;

    // endDate (nullable string)
    $types   .= 's';
    $params[] = $endDate;

    $insertResult = dbInsert($insertSQL, $types, $params);

    if ($insertResult === false)
    {
        error_log('[GoToMyLink] ERROR: Failed to insert short URL — code: ' . $shortCode . ', org: ' . $orgHandle);

        return [
            'success'   => false,
            'shortCode' => null,
            'shortURL'  => null,
            'error'     => 'Failed to create the short URL. Please try again.',
        ];
    }

    // ========================================================================
    // 🔗 Step 6: Build the full short URL
    // ========================================================================
    $shortDomain = getDefaultShortDomain($orgHandle);
    $shortURL    = 'https://' . $shortDomain . '/' . $shortCode;

    // ========================================================================
    // 📊 Step 7: Log the creation activity
    // ========================================================================
    logActivity('create_link', 'success', 201, [
        'orgHandle'      => $orgHandle,
        'shortCode'      => $shortCode,
        'destinationURL' => $sanitisedURL,
    ]);

    // ========================================================================
    // ✅ Step 8: Return success
    // ========================================================================
    return [
        'success'   => true,
        'shortCode' => $shortCode,
        'shortURL'  => $shortURL,
        'error'     => null,
    ];
}

// ============================================================================
// ⏱️ checkAnonymousRateLimit — Check IP-based rate limits
// ============================================================================
// Uses tblActivityLog to count recent 'create_link' actions from the same IP.
// No Redis needed — pure MySQL query against indexed columns.
//
// 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html
//
// @param  string $ipAddress  The client IP address
// @return array              ['allowed' => bool, 'remaining' => int,
//                             'hourlyCount' => int, 'dailyCount' => int]
// ============================================================================
function checkAnonymousRateLimit(string $ipAddress): array
{
    // Get rate limit settings
    $perHour = (int) getSetting('ratelimit.anonymous_per_hour', 10);
    $perDay  = (int) getSetting('ratelimit.anonymous_per_day', 50);

    // Count recent creations from this IP (hourly window)
    // Uses IDX_log_ip, IDX_log_action, IDX_log_created indexes
    $hourlyRow = dbSelectOne(
        "SELECT COUNT(*) AS cnt
         FROM tblActivityLog
         WHERE logAction = 'create_link'
           AND ipAddress = ?
           AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        's',
        [$ipAddress]
    );

    $hourlyCount = ($hourlyRow !== null && $hourlyRow !== false)
        ? (int) $hourlyRow['cnt']
        : 0;

    // Count recent creations from this IP (daily window)
    $dailyRow = dbSelectOne(
        "SELECT COUNT(*) AS cnt
         FROM tblActivityLog
         WHERE logAction = 'create_link'
           AND ipAddress = ?
           AND createdAt >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        's',
        [$ipAddress]
    );

    $dailyCount = ($dailyRow !== null && $dailyRow !== false)
        ? (int) $dailyRow['cnt']
        : 0;

    // Check if limits are exceeded
    $allowed   = ($hourlyCount < $perHour) && ($dailyCount < $perDay);
    $remaining = min($perHour - $hourlyCount, $perDay - $dailyCount);

    if ($remaining < 0)
    {
        $remaining = 0;
    }

    return [
        'allowed'     => $allowed,
        'remaining'   => $remaining,
        'hourlyCount' => $hourlyCount,
        'dailyCount'  => $dailyCount,
    ];
}

// ============================================================================
// 🌐 getDefaultShortDomain — Get the default short domain for an org
// ============================================================================
// Queries tblOrgShortDomains for the default active short domain of the
// given organisation. Falls back to 'g2my.link'.
//
// @param  string $orgHandle  The organisation handle
// @return string             The short domain (e.g., 'g2my.link', 'camsda.link')
// ============================================================================
function getDefaultShortDomain(string $orgHandle): string
{
    $row = dbSelectOne(
        "SELECT shortDomain
         FROM tblOrgShortDomains
         WHERE orgHandle = ?
           AND isDefault = 1
           AND isActive = 1
         LIMIT 1",
        's',
        [$orgHandle]
    );

    if ($row !== null && $row !== false && !empty($row['shortDomain']))
    {
        return $row['shortDomain'];
    }

    // Fallback to the default domain
    return 'g2my.link';
}

// ============================================================================
// 🤖 verifyCaptcha — Verify a Turnstile or reCAPTCHA response
// ============================================================================
// Performs server-side verification of the CAPTCHA response by posting to
// the provider's verify endpoint. Uses PHP streams (no cURL dependency)
// for shared hosting compatibility.
//
// 📖 Reference: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
// 📖 Reference: https://developers.google.com/recaptcha/docs/verify
// 📖 Reference: https://www.php.net/manual/en/function.file-get-contents.php
//
// @param  string $type       'turnstile' or 'recaptcha'
// @param  string $response   The CAPTCHA response token from the client
// @param  string $secretKey  The server-side secret key
// @return bool               True if verification succeeded
// ============================================================================
function verifyCaptcha(string $type, string $response, string $secretKey): bool
{
    if ($response === '' || $secretKey === '')
    {
        return false;
    }

    // Determine the verify endpoint URL
    switch ($type)
    {
        case 'turnstile':
            $verifyURL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
            break;

        case 'recaptcha':
            $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
            break;

        default:
            return false;
    }

    // Build the POST data
    // 📖 Reference: https://www.php.net/manual/en/function.http-build-query.php
    $postData = http_build_query([
        'secret'   => $secretKey,
        'response' => $response,
        'remoteip' => g2ml_getClientIP(),
    ]);

    // Create the stream context for the POST request
    // 📖 Reference: https://www.php.net/manual/en/function.stream-context-create.php
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
                       . 'Content-Length: ' . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 10, // 10 second timeout
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    // Make the verification request
    $responseBody = @file_get_contents($verifyURL, false, $context);

    if ($responseBody === false)
    {
        error_log('[GoToMyLink] WARNING: CAPTCHA verification request failed for type: ' . $type);
        return false;
    }

    // Parse the JSON response
    // 📖 Reference: https://www.php.net/manual/en/function.json-decode.php
    $result = json_decode($responseBody, true);

    if (!is_array($result))
    {
        error_log('[GoToMyLink] WARNING: CAPTCHA verification returned invalid JSON');
        return false;
    }

    return isset($result['success']) && $result['success'] === true;
}
