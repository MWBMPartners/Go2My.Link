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
 * 🔀 Go2My.Link — Redirect Resolver (Component B)
 * ============================================================================
 *
 * Core redirect resolution logic for the shortlink engine. Wraps the
 * sp_lookupShortURL stored procedure and provides destination validation
 * and redirect response building.
 *
 * Functions:
 *   - resolveShortCode()        — Resolve a short code to its destination
 *   - validateDestination()     — HTTP HEAD check for destination URL
 *   - buildRedirectResponse()   — Issue the HTTP redirect and exit
 *
 * Dependencies: db_query.php, settings.php (loaded via page_init.php)
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
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
// 🔀 resolveShortCode — Resolve a short code to its destination URL
// ============================================================================
// Wraps the sp_lookupShortURL stored procedure which handles:
//   - Domain-to-org mapping
//   - Alias chain resolution (max 3 hops)
//   - Date range validation (startDate / endDate)
//   - Active status check (isActive flag)
//   - Fallback URL provision
//
// 📖 Reference: web/_sql/procedures/sp_lookupShortURL.sql
// 📖 Reference: https://www.php.net/manual/en/mysqli.quickstart.stored-procedures.php
//
// @param  string $domain  The requesting domain (e.g., 'g2my.link', 'camsda.link')
// @param  string $code    The short code to resolve (e.g., 'abc123')
// @return array           ['destination' => ?string, 'status' => string, 'orgHandle' => ?string]
//                         Status values: success, not_found, inactive, expired,
//                         not_yet_active, no_destination, max_hops_exceeded, error
// ============================================================================
function resolveShortCode(string $domain, string $code): array
{
    // Call the stored procedure via the framework's dbCallProcedure wrapper
    // 📖 Reference: web/_functions/db_query.php — dbCallProcedure()
    $result = dbCallProcedure(
        'sp_lookupShortURL',
        [$domain, $code],
        'ss',
        ['@outputDestination', '@outputStatus', '@outputOrgHandle']
    );

    // Handle SP call failure (database error, connection issue, etc.)
    if ($result === false)
    {
        error_log(
            '[Go2My.Link] ERROR: sp_lookupShortURL call failed'
            . ' — domain: ' . $domain
            . ', code: ' . $code
        );

        return [
            'destination' => null,
            'status'      => 'error',
            'orgHandle'   => null,
        ];
    }

    // Extract and return the SP output parameters
    return [
        'destination' => $result['@outputDestination'] ?? null,
        'status'      => $result['@outputStatus'] ?? 'not_found',
        'orgHandle'   => $result['@outputOrgHandle'] ?? null,
    ];
}

// ============================================================================
// ✅ validateDestination — Check if a destination URL is accessible
// ============================================================================
// Performs an HTTP HEAD request to verify the destination URL returns a
// successful status code (200–399). Uses PHP streams (no cURL dependency)
// for shared hosting compatibility.
//
// Results are cached in the session to avoid re-validating the same URL
// within a single user session (configurable TTL).
//
// ⚠️ This adds 100–500ms latency per redirect — OFF by default (see
//     redirect.validate_destination setting). Only enable if needed.
//
// 📖 Reference: https://www.php.net/manual/en/function.get-headers.php
// 📖 Reference: https://www.php.net/manual/en/function.stream-context-create.php
//
// @param  string $url      The destination URL to validate
// @param  int    $timeout  Connection timeout in seconds (default: 5)
// @return array            ['valid' => bool, 'statusCode' => ?int, 'error' => ?string]
// ============================================================================
function validateDestination(string $url, int $timeout = 5): array
{
    // ============================================================================
    // 📋 Step 1: Check session cache for previously validated URLs
    // ============================================================================
    $cacheKey  = 'g2ml_validated_' . md5($url);
    $cacheTTL  = 300; // 5 minutes

    if (isset($_SESSION[$cacheKey]))
    {
        $cached = $_SESSION[$cacheKey];

        if (isset($cached['timestamp']) && (time() - $cached['timestamp']) < $cacheTTL)
        {
            return $cached['result'];
        }

        // Cache expired — remove it
        unset($_SESSION[$cacheKey]);
    }

    // ============================================================================
    // 📋 Step 2: Perform HTTP HEAD request
    // ============================================================================
    $result = [
        'valid'      => false,
        'statusCode' => null,
        'error'      => null,
    ];

    // Build the stream context for the HEAD request
    // 📖 Reference: https://www.php.net/manual/en/context.http.php
    $contextOptions = [
        'http' => [
            'method'           => 'HEAD',
            'timeout'          => $timeout,
            'follow_location'  => 0,       // Don't follow redirects — just check initial response
            'max_redirects'    => 0,
            'ignore_errors'    => true,     // Return headers even for error status codes
            'user_agent'       => 'Go2My.Link URL Validator/1.0',
            'header'           => "Accept: */*\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ];

    $context = stream_context_create($contextOptions);

    try
    {
        // PHP 8.4+ supports passing context to get_headers()
        // 📖 Reference: https://www.php.net/manual/en/function.get-headers.php
        if (version_compare(PHP_VERSION, '8.4.0', '>='))
        {
            $headers = @get_headers($url, true, $context);
        }
        else
        {
            // For PHP < 8.4: set the default stream context temporarily
            // 📖 Reference: https://www.php.net/manual/en/function.stream-context-set-default.php
            $previousContext = stream_context_get_default();
            stream_context_set_default($contextOptions);
            $headers = @get_headers($url, true);
            stream_context_set_default(stream_context_get_options($previousContext));
        }

        if ($headers === false)
        {
            $result['error'] = 'Connection failed or timed out';
        }
        else
        {
            // Extract status code from the first header line
            // Format: "HTTP/1.1 200 OK" or "HTTP/2 200"
            // 📖 Reference: https://www.php.net/manual/en/function.preg-match.php
            if (is_array($headers)) {
                $statusLine = ($headers[0] ?? '');
            } else {
                $statusLine = '';
            }

            if (preg_match('/HTTP\/[\d.]+\s+(\d{3})/', $statusLine, $matches))
            {
                $statusCode = (int) $matches[1];
                $result['statusCode'] = $statusCode;

                // Status 200–399 is considered valid (includes redirects)
                $result['valid'] = ($statusCode >= 200 && $statusCode < 400);

                if (!$result['valid'])
                {
                    $result['error'] = 'Destination returned HTTP ' . $statusCode;
                }
            }
            else
            {
                $result['error'] = 'Could not parse response status';
            }
        }
    }
    catch (\Throwable $e)
    {
        $result['error'] = 'Validation error: ' . $e->getMessage();
    }

    // ============================================================================
    // 📋 Step 3: Cache the result in session
    // ============================================================================
    if (isset($_SESSION))
    {
        $_SESSION[$cacheKey] = [
            'result'    => $result,
            'timestamp' => time(),
        ];
    }

    return $result;
}

// ============================================================================
// 🚀 buildRedirectResponse — Issue the HTTP redirect and exit
// ============================================================================
// Sends the Location header with the appropriate status code and terminates
// script execution. Ensures no output has been sent before the header.
//
// 📖 Reference: https://www.php.net/manual/en/function.header.php
// 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/302
//
// @param  string $destination  The URL to redirect to
// @param  int    $statusCode   HTTP status code (default: 302 Found)
// @return void                 This function does not return — it calls exit()
// ============================================================================
function buildRedirectResponse(string $destination, int $statusCode = 302): void
{
    // Validate the status code is a valid redirect code
    $validRedirectCodes = [301, 302, 303, 307, 308];

    if (!in_array($statusCode, $validRedirectCodes, true))
    {
        $statusCode = 302; // Default to 302 Found
    }

    // Ensure no prior output (headers already sent check)
    // 📖 Reference: https://www.php.net/manual/en/function.headers-sent.php
    if (headers_sent($file, $line))
    {
        error_log(
            '[Go2My.Link] WARNING: Headers already sent in '
            . $file . ':' . $line
            . ' — cannot redirect to: ' . $destination
        );
        return;
    }

    // Defence-in-depth: verify the destination URL uses a safe scheme
    // Prevents javascript: or data: URLs from being issued as redirect headers
    if (!preg_match('/^https?:\/\//i', $destination))
    {
        error_log('[Go2My.Link] WARNING: Rejected redirect to non-HTTP URL: ' . $destination);
        http_response_code(404);
        return;
    }

    header('Location: ' . $destination, true, $statusCode);
    exit;
}
