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
 *   - resolveShortCode()               — Resolve a short code to its destination
 *   - g2ml_resolveScanSource()         — Detect a CueRCode QR-scan marker (#145)
 *   - g2ml_appendUtmToDestination()    — Forward the link's UTM params onto the destination (#92)
 *   - g2ml_extractTrackingParams()     — Whitelist-extract tracking params from the incoming request (#92)
 *   - g2ml_buildPinnedDestinationUrl() — Rebuild a URL against a resolved IP
 *   - validateDestination()           — HTTP HEAD check for destination URL
 *   - buildRedirectResponse()         — Issue the HTTP redirect and exit
 *
 * Dependencies: db_query.php, settings.php (loaded via page_init.php)
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.5.0
 * @since      Phase 3 (UTM capture/forward added v1.1.0 / #92)
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
// @return array           ['destination' => ?string, 'status' => string, 'orgHandle' => ?string,
//                          'utmSource' => ?string, 'utmMedium' => ?string, 'utmCampaign' => ?string,
//                          'utmTerm' => ?string, 'utmContent' => ?string]
//                         Status values: success, not_found, inactive, expired,
//                         not_yet_active, no_destination, max_hops_exceeded,
//                         domain_not_configured, error
//
//                         domain_not_configured (#91): the requesting Host has
//                         been claimed as an org's custom short domain but has
//                         NOT passed DNS-TXT ownership verification yet (or has
//                         since failed re-verification). orgHandle is always
//                         null in this case — the caller must NOT treat it as
//                         '[default]' or resolve anything, only show a branded
//                         "domain not configured" page.
//
//                         utmSource/utmMedium/utmCampaign/utmTerm/utmContent
//                         (#92): the link's OWN configured tblShortURLs.utm*
//                         columns (never visitor-supplied). Only ever non-null
//                         when status === 'success' — every other status
//                         returns all five as null. The caller decides whether
//                         to actually forward them onto the destination (see
//                         g2ml_appendUtmToDestination() below), gated by the
//                         redirect.forward_utm_params setting; this function
//                         always returns whatever the stored link has.
// ============================================================================
function resolveShortCode(string $domain, string $code): array
{
    // Call the stored procedure via the framework's dbCallProcedure wrapper
    // 📖 Reference: web/_functions/db_query.php — dbCallProcedure()
    $result = dbCallProcedure(
        'sp_lookupShortURL',
        [$domain, $code],
        'ss',
        [
            '@outputDestination',
            '@outputStatus',
            '@outputOrgHandle',
            '@outputUtmSource',
            '@outputUtmMedium',
            '@outputUtmCampaign',
            '@outputUtmTerm',
            '@outputUtmContent',
        ]
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
            'utmSource'   => null,
            'utmMedium'   => null,
            'utmCampaign' => null,
            'utmTerm'     => null,
            'utmContent'  => null,
        ];
    }

    // Extract and return the SP output parameters
    return [
        'destination' => $result['@outputDestination'] ?? null,
        'status'      => $result['@outputStatus'] ?? 'not_found',
        'orgHandle'   => $result['@outputOrgHandle'] ?? null,
        'utmSource'   => $result['@outputUtmSource'] ?? null,
        'utmMedium'   => $result['@outputUtmMedium'] ?? null,
        'utmCampaign' => $result['@outputUtmCampaign'] ?? null,
        'utmTerm'     => $result['@outputUtmTerm'] ?? null,
        'utmContent'  => $result['@outputUtmContent'] ?? null,
    ];
}

// ============================================================================
// 🔗 g2ml_resolveScanSource — Detect a CueRCode QR-scan marker (#145)
// ============================================================================
// Pure function — no database access, no state — so it stays unit-testable
// without a database connection. Deliberately separate from the
// qrCodeExternalID lookup (which DOES need the database): the caller only
// pays for that lookup when this function actually reports a scan.
//
// SECURITY (#145): the scan-source query-string parameter is UNTRUSTED input.
// This function never returns the raw query-string value — once it matches
// the operator-configured expected value, it returns THAT configured value
// (capped to the tblActivityLog.scanSource VARCHAR(50) column width), never
// anything an attacker supplied. A mismatch (or either setting being blank)
// returns null, so a normal redirect is unaffected.
//
// 📖 Reference: web/_sql/seeds/013_cuercode_settings.sql — cuercode.scan_source_param / cuercode.scan_source_value
// 📖 Reference: web/_functions/activity_logger.php — logActivity() scanSource column (VARCHAR(50))
//
// @param  string $scanSourceParam          getSetting('cuercode.scan_source_param'), e.g. 'src'.
// @param  string $scanSourceExpectedValue  getSetting('cuercode.scan_source_value'), e.g. 'qr'.
// @param  array  $queryParams              Typically $_GET.
// @return string|null                      The scan-source value to log, or null when this
//                                          request is not a marked QR scan.
// ============================================================================
function g2ml_resolveScanSource(string $scanSourceParam, string $scanSourceExpectedValue, array $queryParams): ?string
{
    if ($scanSourceParam === '' || $scanSourceExpectedValue === '')
    {
        return null;
    }

    if (!isset($queryParams[$scanSourceParam]) || !is_string($queryParams[$scanSourceParam]))
    {
        return null;
    }

    if ($queryParams[$scanSourceParam] !== $scanSourceExpectedValue)
    {
        return null;
    }

    $scanSource = $scanSourceExpectedValue;

    if (strlen($scanSource) > 50)
    {
        $scanSource = substr($scanSource, 0, 50);
    }

    return $scanSource;
}

// ============================================================================
// 🔗 g2ml_appendUtmToDestination — Forward the link's UTM params onto the
//    destination URL (#92)
// ============================================================================
// Pure function — no database access, no state — so it stays unit-testable
// without a database connection. Appends the link owner's OWN configured
// tblShortURLs.utm* values (NEVER visitor-supplied query-string data — that is
// g2ml_extractTrackingParams()'s job, and it is a completely separate,
// untrusted data path) onto the destination URL's query string.
//
// OVERRIDE SEMANTICS: if the destination already has a same-named query
// parameter (e.g. the owner's destination URL itself contains
// "?utm_source=direct"), the LINK's configured value wins and replaces it.
// This is deliberate — the link owner configured this value on this specific
// short link on purpose, so it is treated as the more specific, more recent
// intent versus whatever happens to already be baked into the destination.
//
// Every other part of the destination (scheme, user/pass, host, port, path,
// fragment) is preserved byte-for-byte from the original — only the query
// string is rebuilt. http_build_query() is used (never string concatenation)
// so every appended value is correctly percent-encoded, which also means a
// value that somehow contained CR/LF or other control characters can never
// break out of the query string or inject additional header lines into the
// eventual `Location:` response.
//
// 📖 Reference: https://www.php.net/manual/en/function.parse-url.php
// 📖 Reference: https://www.php.net/manual/en/function.http-build-query.php
// 📖 Reference: https://www.php.net/manual/en/function.parse-str.php
//
// @param  string $destinationURL  The link's stored destination URL.
// @param  array  $linkUtm         Map with the (possibly absent/empty) keys
//                                 'utmSource', 'utmMedium', 'utmCampaign',
//                                 'utmTerm', 'utmContent' — the SAME shape
//                                 resolveShortCode() returns.
// @return string                  The destination URL with the link's non-empty
//                                 UTM values appended/overridden onto its query
//                                 string. Returns $destinationURL UNCHANGED
//                                 (byte-for-byte) when it cannot be safely
//                                 parsed, or when $linkUtm has no non-empty
//                                 values to apply.
// ============================================================================
function g2ml_appendUtmToDestination(string $destinationURL, array $linkUtm): string
{
    $urlParts = parse_url($destinationURL);

    // An unparseable destination, or one missing a scheme/host, is left
    // completely untouched rather than risk emitting a corrupted redirect
    // target — this mirrors the defensive posture already used by
    // g2ml_buildPinnedDestinationUrl() below.
    // (parse_url() only ever returns array|false, so once false is
    // eliminated the remaining value is guaranteed to be an array —
    // no separate is_array() check is needed.)
    if ($urlParts === false)
    {
        return $destinationURL;
    }

    if (!isset($urlParts['scheme']) || !isset($urlParts['host']))
    {
        return $destinationURL;
    }

    // Map the tblShortURLs column names (as returned by resolveShortCode())
    // onto the query-string parameter names marketers actually expect.
    $utmColumnToQueryParam = [
        'utmSource'   => 'utm_source',
        'utmMedium'   => 'utm_medium',
        'utmCampaign' => 'utm_campaign',
        'utmTerm'     => 'utm_term',
        'utmContent'  => 'utm_content',
    ];

    $utmValuesToApply = [];

    foreach ($utmColumnToQueryParam as $linkColumnName => $queryParamName)
    {
        if (!isset($linkUtm[$linkColumnName]))
        {
            continue;
        }

        $linkColumnValue = $linkUtm[$linkColumnName];

        if (!is_string($linkColumnValue))
        {
            continue;
        }

        if ($linkColumnValue === '')
        {
            continue;
        }

        $utmValuesToApply[$queryParamName] = $linkColumnValue;
    }

    // Nothing configured on this link — return the destination untouched.
    if (count($utmValuesToApply) === 0)
    {
        return $destinationURL;
    }

    $existingQueryParams = [];

    if (isset($urlParts['query']) && $urlParts['query'] !== '')
    {
        parse_str($urlParts['query'], $existingQueryParams);
    }

    // The link's configured value deliberately overrides a same-named
    // parameter already present on the destination (see the override
    // semantics note in the function header above).
    $mergedQueryParams = array_merge($existingQueryParams, $utmValuesToApply);

    $rebuiltQuery = http_build_query($mergedQueryParams, '', '&', PHP_QUERY_RFC3986);

    $rebuiltUrl = $urlParts['scheme'] . '://';

    if (isset($urlParts['user']))
    {
        $rebuiltUrl = $rebuiltUrl . $urlParts['user'];

        if (isset($urlParts['pass']))
        {
            $rebuiltUrl = $rebuiltUrl . ':' . $urlParts['pass'];
        }

        $rebuiltUrl = $rebuiltUrl . '@';
    }

    $rebuiltUrl = $rebuiltUrl . $urlParts['host'];

    if (isset($urlParts['port']))
    {
        $rebuiltUrl = $rebuiltUrl . ':' . $urlParts['port'];
    }

    if (isset($urlParts['path']))
    {
        $rebuiltUrl = $rebuiltUrl . $urlParts['path'];
    }

    if ($rebuiltQuery !== '')
    {
        $rebuiltUrl = $rebuiltUrl . '?' . $rebuiltQuery;
    }

    if (isset($urlParts['fragment']))
    {
        $rebuiltUrl = $rebuiltUrl . '#' . $urlParts['fragment'];
    }

    return $rebuiltUrl;
}

// ============================================================================
// 📥 g2ml_extractTrackingParams — Whitelist-extract tracking params from the
//    INCOMING visit query string (#92)
// ============================================================================
// Pure function — no database access, no state, no rendering — so it stays
// unit-testable without a database connection. The input ($_GET at the
// caller) is UNTRUSTED visitor-controlled data, so this function is
// deliberately conservative:
//
//   - Only a fixed WHITELIST of tracking-related keys is ever inspected —
//     every other query-string parameter is silently ignored, regardless of
//     name or value.
//   - Only scalar (string/int/float/bool) values are accepted; an
//     array-style parameter (e.g. "utm_source[]=x") is ignored rather than
//     coerced, so a crafted request can never smuggle an array into the
//     eventual json_encode() call in logActivity().
//   - Each accepted value is cast to a string and capped to $maxValueLength
//     characters.
//   - The combined size of all captured values is capped to $maxTotalLength;
//     once accumulating further values would exceed that cap, remaining
//     whitelisted keys are dropped (not further truncated) so what IS stored
//     is always a whole, un-mangled value.
//
// The caller is responsible for storing the result ONLY (e.g. inside
// logActivity()'s logData JSON column) — this function performs no escaping
// for HTML/attribute contexts, because it never renders anything itself; the
// analytics dashboard that later displays this data is responsible for
// escaping on output.
//
// 📖 Reference: web/_functions/activity_logger.php — logActivity() 'logData' context key
//
// @param  array $queryParams  Typically $_GET.
// @return array               Associative array of whitelisted key => capped
//                             string value. Empty when nothing whitelisted is
//                             present (a normal redirect with no tracking
//                             params at all).
// ============================================================================
function g2ml_extractTrackingParams(array $queryParams): array
{
    $allowedTrackingKeys = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'msclkid',
    ];

    $maxValueLength = 255;
    $maxTotalLength = 2000;

    $extractedParams     = [];
    $accumulatedLength   = 0;

    foreach ($allowedTrackingKeys as $trackingKey)
    {
        if (!isset($queryParams[$trackingKey]))
        {
            continue;
        }

        $rawValue = $queryParams[$trackingKey];

        // Reject non-scalar values (e.g. utm_source[]=x arrives as an array)
        // outright rather than coercing them — untrusted input must never
        // reach json_encode() as anything other than a plain capped string.
        if (!is_scalar($rawValue))
        {
            continue;
        }

        $stringValue = (string) $rawValue;

        if ($stringValue === '')
        {
            continue;
        }

        if (strlen($stringValue) > $maxValueLength)
        {
            $stringValue = substr($stringValue, 0, $maxValueLength);
        }

        // Stop accumulating once the total captured size would exceed the
        // cap. Remaining whitelisted keys (if any) are dropped rather than
        // further truncated, so every value that IS stored is left whole.
        if (($accumulatedLength + strlen($stringValue)) > $maxTotalLength)
        {
            break;
        }

        $extractedParams[$trackingKey] = $stringValue;
        $accumulatedLength              = $accumulatedLength + strlen($stringValue);
    }

    return $extractedParams;
}

// ============================================================================
// 🧷 g2ml_buildPinnedDestinationUrl — Rebuild a URL against a resolved IP
// ============================================================================
// Reconstructs a request URL with the host component replaced by an already
// resolved-and-validated IP address, keeping the original scheme, port,
// path, and query string. Used by validateDestination() to pin the outbound
// HEAD request to the exact address that was vetted, closing the
// DNS-rebinding TOCTOU window between the SSRF check and the fetch.
//
// @param  array  $urlParts  parse_url() output for the original destination URL
// @param  string $pinnedIp  The validated IP address to connect to
// @return string            The rebuilt URL, with $pinnedIp as its host
// ============================================================================
function g2ml_buildPinnedDestinationUrl(array $urlParts, string $pinnedIp): string
{
    $scheme = 'http';

    if (isset($urlParts['scheme']))
    {
        $scheme = strtolower($urlParts['scheme']);
    }

    // Bracket an IPv6 literal so it is a valid URL host component.
    $hostComponent = $pinnedIp;

    if (strpos($pinnedIp, ':') !== false)
    {
        $hostComponent = '[' . $pinnedIp . ']';
    }

    $pinnedUrl = $scheme . '://' . $hostComponent;

    if (isset($urlParts['port']))
    {
        $pinnedUrl .= ':' . $urlParts['port'];
    }

    $pinnedUrl .= $urlParts['path'] ?? '/';

    if (isset($urlParts['query']) && $urlParts['query'] !== '')
    {
        $pinnedUrl .= '?' . $urlParts['query'];
    }

    return $pinnedUrl;
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

    // ------------------------------------------------------------------------
    // 🛡️ SSRF guard (F-004 / #100): never fetch internal / loopback /
    // link-local / cloud-metadata hosts. Vet the host BEFORE the request so a
    // malicious destination can never make the server issue an outbound HEAD
    // to an internal address. On rejection we return the existing
    // validation-failed shape without touching the network.
    //
    // 📖 Reference: web/_functions/security.php — g2ml_resolveAllowedDestinationIps()
    // ------------------------------------------------------------------------
    $validatedDestinationIPs = g2ml_resolveAllowedDestinationIps($url);

    if ($validatedDestinationIPs === false)
    {
        $result['error'] = 'Destination host is not permitted';

        if (isset($_SESSION))
        {
            $_SESSION[$cacheKey] = [
                'result'    => $result,
                'timestamp' => time(),
            ];
        }

        return $result;
    }

    // ------------------------------------------------------------------------
    // 🛡️ DNS-rebinding guard: pin the outbound HEAD request to the EXACT IP
    // just validated above, rather than letting get_headers() perform its own
    // independent DNS resolution. Without this, an attacker controlling DNS
    // for the destination host (e.g. a TTL=0 record) could answer with a
    // public address for the check above and a private/internal one moments
    // later for the actual fetch (TOCTOU). We resolve once, validate once,
    // and connect to that exact address — sending the original Host header
    // and TLS SNI/peer name so virtual-hosted destinations still resolve and
    // verify correctly.
    //
    // 📖 Reference: web/G2My.Link/_functions/redirect_resolver.php — g2ml_buildPinnedDestinationUrl()
    // ------------------------------------------------------------------------
    $urlParts = parse_url($url);

    // (parse_url() only ever returns array|false, so once false is
    // eliminated the remaining value is guaranteed to be an array —
    // no separate is_array() check is needed.)
    if ($urlParts === false || !isset($urlParts['host']) || $urlParts['host'] === '')
    {
        $result['error'] = 'Destination host is not permitted';

        if (isset($_SESSION))
        {
            $_SESSION[$cacheKey] = [
                'result'    => $result,
                'timestamp' => time(),
            ];
        }

        return $result;
    }

    $originalHost = $urlParts['host'];

    // A bracketed IPv6 literal arrives as "[::1]"; strip the brackets for use
    // as the Host header value and TLS peer name.
    if (strlen($originalHost) >= 2 && $originalHost[0] === '[' && $originalHost[strlen($originalHost) - 1] === ']')
    {
        $originalHost = substr($originalHost, 1, -1);
    }

    $hostHeaderValue = $originalHost;

    if (isset($urlParts['port']))
    {
        $hostHeaderValue .= ':' . $urlParts['port'];
    }

    // Any one of the returned addresses was already required to be allowed —
    // pin to the first.
    $pinnedIp  = $validatedDestinationIPs[0];
    $pinnedUrl = g2ml_buildPinnedDestinationUrl($urlParts, $pinnedIp);

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
            // A custom Host header here overrides the one the stream wrapper
            // would otherwise derive from the (now IP-literal) request URL.
            'header'           => "Accept: */*\r\nHost: " . $hostHeaderValue . "\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            // Verify the certificate against — and send SNI for — the
            // ORIGINAL hostname, even though the connection target below is
            // the pinned IP address.
            'peer_name'        => $originalHost,
            'SNI_enabled'      => true,
        ],
    ];

    $context = stream_context_create($contextOptions);

    try
    {
        // PHP 8.4+ supports passing context to get_headers()
        // 📖 Reference: https://www.php.net/manual/en/function.get-headers.php
        if (version_compare(PHP_VERSION, '8.4.0', '>='))
        {
            $headers = @get_headers($pinnedUrl, true, $context);
        }
        else
        {
            // For PHP < 8.4: set the default stream context temporarily
            // 📖 Reference: https://www.php.net/manual/en/function.stream-context-set-default.php
            $previousContext = stream_context_get_default();
            stream_context_set_default($contextOptions);
            $headers = @get_headers($pinnedUrl, true);
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
            // ($headers is guaranteed to be an array here — get_headers()
            // only ever returns array|false, and the false case was already
            // handled above.)
            $statusLine = $headers[0] ?? '';

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
