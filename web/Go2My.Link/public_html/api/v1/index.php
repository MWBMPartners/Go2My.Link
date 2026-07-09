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
 * 🚪 Go2My.Link — Public API v1 Front Controller (#38)
 * ============================================================================
 *
 * Entry point for every /api/v1/* request. The component .htaccess rewrites
 * ^api/v1/(.*)$ here with the remainder of the path in ?_apiroute=.
 *
 * Request pipeline (each step's failure short-circuits to a response — every
 * exit point still logs the request via g2ml_apiLogRequest() so the audit
 * trail is complete regardless of outcome):
 *
 *   1. HTTPS enforcement            -> 403
 *   2. Route sanitisation            -> 404 (malformed route treated as unknown)
 *   3. api.public_enabled gate       -> 503
 *   4. Bearer/X-API-Key extraction   -> 401 (missing)
 *   5. g2ml_apiVerifyKey()           -> 401 (invalid/expired/revoked/suspended)
 *   6. g2ml_apiCheckRateLimit()      -> 429 + Retry-After
 *   7. Route + scope dispatch        -> 404 (unknown) / 403 (missing scope)
 *   8. Handler invocation (try/catch)-> 500 (never leaks the underlying cause)
 *
 * This issue (#38) ships ONLY the framework and two exerciser endpoints
 * (ping, account). The full CRUD surface (urls/analytics/domains) is #39.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#38)
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Bootstrap the Application
// ============================================================================
// Same bootstrap pattern as api/consent/index.php: component constants ->
// auth_creds.php -> page_init.php (which loads api_auth.php + api_ratelimit.php
// as part of its Layer 2 function load order).
// ============================================================================
define('G2ML_COMPONENT', 'A');
define('G2ML_COMPONENT_NAME', 'Main Website');
define('G2ML_COMPONENT_DOMAIN', 'go2my.link');

// api/v1/index.php -> v1 -> api -> public_html -> Go2My.Link -> web
define('G2ML_ROOT', dirname(__DIR__, 4));

$g2mlApiAuthCredsPath = G2ML_ROOT . DIRECTORY_SEPARATOR . '_auth_keys' . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($g2mlApiAuthCredsPath))
{
    require_once $g2mlApiAuthCredsPath;
}
else
{
    error_log('[Go2My.Link] CRITICAL: api/v1 auth_creds.php not found');
}

require_once G2ML_ROOT . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'page_init.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'handlers' . DIRECTORY_SEPARATOR . 'ping.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'handlers' . DIRECTORY_SEPARATOR . 'account.php';

// ============================================================================
// ⏱️ Step 2: Start the response-time clock
// ============================================================================
$g2mlApiRequestStart = microtime(true);

// ============================================================================
// 🧾 Step 3: Every /api/v1 response is uncacheable (it may be authenticated)
// ============================================================================
header('Cache-Control: no-store');

// ============================================================================
// 📝 Helper: log the request, emit the envelope, and terminate.
// ============================================================================
// Centralises the "always log, then respond" contract so every exit path
// below is a single call instead of repeated boilerplate.
// ============================================================================

/**
 * Log this request and emit the given envelope, then terminate.
 *
 * @param  array       $envelope       The envelope array (success or error shape).
 * @param  int         $statusCode     The HTTP status code to send.
 * @param  int|null    $apiKeyUID      The authenticated key's UID, or null.
 * @param  string      $endpoint       The endpoint path being logged.
 * @param  string      $httpMethod     The HTTP method being logged.
 * @param  float       $startTime      microtime(true) captured at request start.
 * @param  array|null  $requestBody    The decoded request body to log (redacted), or null.
 * @param  array<string,string>|null $extraHeaders  Extra headers to send before the body (e.g. Retry-After).
 * @return never
 */
function g2mlApiV1FinishRequest(array $envelope, int $statusCode, ?int $apiKeyUID, string $endpoint, string $httpMethod, float $startTime, ?array $requestBody, ?array $extraHeaders = null): never
{
    $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

    $userAgent = null;

    if (isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']))
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
    }

    g2ml_apiLogRequest(
        $apiKeyUID,
        $endpoint,
        $httpMethod,
        $statusCode,
        $responseTimeMs,
        g2ml_getClientIP(),
        $userAgent,
        $requestBody
    );

    if ($extraHeaders !== null)
    {
        foreach ($extraHeaders as $headerName => $headerValue)
        {
            header($headerName . ': ' . $headerValue);
        }
    }

    g2ml_apiRespond($envelope, $statusCode);
}

// ============================================================================
// 🌐 Step 4: Resolve request basics
// ============================================================================
$g2mlApiMethod = 'GET';

if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']))
{
    $g2mlApiMethod = strtoupper($_SERVER['REQUEST_METHOD']);
}

$g2mlApiRawRoute = '';

if (isset($_GET['_apiroute']) && is_string($_GET['_apiroute']))
{
    $g2mlApiRawRoute = $_GET['_apiroute'];
}

$g2mlApiSanitisedRoute = g2ml_apiSanitiseRoute($g2mlApiRawRoute);

$g2mlApiEndpointForLog = '/api/v1/' . $g2mlApiRawRoute;

// ============================================================================
// 🔒 Step 5: Enforce HTTPS
// ============================================================================
// Dreamhost's .htaccess already 301-redirects HTTP -> HTTPS before PHP ever
// runs; this is defence-in-depth for any path that reaches this script over
// plain HTTP regardless (e.g. a misconfigured proxy). Local/alpha/beta dev
// environments are exempt, mirroring the session-cookie 'secure' flag
// precedent in _includes/page_init.php.
// ============================================================================
$g2mlApiHttpsValue = '';

if (isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS']))
{
    $g2mlApiHttpsValue = strtolower($_SERVER['HTTPS']);
}

$g2mlApiIsSecure = ($g2mlApiHttpsValue !== '' && $g2mlApiHttpsValue !== 'off');

if (!$g2mlApiIsSecure && G2ML_ENVIRONMENT !== 'local')
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(403, 'HTTPS is required.'),
        403,
        null,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

// ============================================================================
// 🧯 Step 6: api.public_enabled master switch
// ============================================================================
$g2mlApiPublicEnabled = getSetting('api.public_enabled', true);

if ($g2mlApiPublicEnabled === false)
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(503, 'The public API is temporarily unavailable.'),
        503,
        null,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

// ============================================================================
// 🔑 Step 7: Authenticate
// ============================================================================
$g2mlApiAuthorizationHeader = null;

if (isset($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION']))
{
    $g2mlApiAuthorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
}
elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
{
    // Some FastCGI/PHP-FPM configurations strip the Authorization header
    // unless it is re-exposed under this REDIRECT_ prefix.
    $g2mlApiAuthorizationHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$g2mlApiKeyHeader = null;

if (isset($_SERVER['HTTP_X_API_KEY']) && is_string($_SERVER['HTTP_X_API_KEY']))
{
    $g2mlApiKeyHeader = $_SERVER['HTTP_X_API_KEY'];
}

$g2mlApiPresentedKey = g2ml_apiExtractBearerToken($g2mlApiAuthorizationHeader, $g2mlApiKeyHeader);

if ($g2mlApiPresentedKey === null)
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(401, 'An API key is required (Authorization: Bearer <key> or X-API-Key).'),
        401,
        null,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

$g2mlApiKeyRow = g2ml_apiVerifyKey($g2mlApiPresentedKey);

if ($g2mlApiKeyRow === null)
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(401, 'Invalid or expired API key.'),
        401,
        null,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

$g2mlApiKeyUID = (int) $g2mlApiKeyRow['apiKeyUID'];

// ============================================================================
// ⏳ Step 8: Rate limit
// ============================================================================
$g2mlApiRateLimit = g2ml_apiCheckRateLimit($g2mlApiKeyRow);

if ($g2mlApiRateLimit['allowed'] === false)
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(429, 'Rate limit exceeded.'),
        429,
        $g2mlApiKeyUID,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null,
        ['Retry-After' => (string) $g2mlApiRateLimit['retryAfter']]
    );
}

// ============================================================================
// 🗺️ Step 9: Route + scope dispatch
// ============================================================================
// A tiny, explicit table — deliberately not a generic auto-loader — because
// this issue ships exactly two endpoints. #39 introduces the full CRUD
// surface and should extend this table rather than replace the pattern.
// ============================================================================
$g2mlApiRouteTable = [
    'GET ping' => [
        'scope'   => null,
        'handler' => 'g2ml_apiHandlePing',
    ],
    'GET account' => [
        'scope'   => 'account:read',
        'handler' => 'g2ml_apiHandleAccount',
    ],
];

if ($g2mlApiSanitisedRoute === null)
{
    $g2mlApiRouteKey = '';
}
else
{
    $g2mlApiRouteKey = $g2mlApiMethod . ' ' . $g2mlApiSanitisedRoute;
}

if (!isset($g2mlApiRouteTable[$g2mlApiRouteKey]))
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(404, 'The requested endpoint does not exist.'),
        404,
        $g2mlApiKeyUID,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

$g2mlApiRoute = $g2mlApiRouteTable[$g2mlApiRouteKey];

if ($g2mlApiRoute['scope'] !== null && !g2ml_apiKeyHasScope($g2mlApiKeyRow, $g2mlApiRoute['scope']))
{
    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(403, 'This API key does not have the required scope.'),
        403,
        $g2mlApiKeyUID,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}

// ============================================================================
// 🎯 Step 10: Invoke the handler
// ============================================================================
$g2mlApiRequestId = g2ml_generateToken(8);
$g2mlApiContext   = ['requestId' => $g2mlApiRequestId];

try
{
    $g2mlApiResponseData = call_user_func($g2mlApiRoute['handler'], $g2mlApiKeyRow, $g2mlApiContext);

    $g2mlApiEnvelope = g2ml_apiEnvelope($g2mlApiResponseData, [
        'requestId' => $g2mlApiRequestId,
        'rateLimit' => [
            'limit'     => $g2mlApiRateLimit['limit'],
            'remaining' => $g2mlApiRateLimit['remaining'],
            'resetAt'   => $g2mlApiRateLimit['resetAt'],
        ],
    ]);

    g2mlApiV1FinishRequest(
        $g2mlApiEnvelope,
        200,
        $g2mlApiKeyUID,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}
catch (Throwable $g2mlApiHandlerError)
{
    // Never leak the underlying cause to the client — log it server-side only.
    error_log('[Go2My.Link] API v1 handler exception on ' . $g2mlApiEndpointForLog . ': ' . $g2mlApiHandlerError->getMessage());

    g2mlApiV1FinishRequest(
        g2ml_apiErrorBody(500, 'An unexpected error occurred.'),
        500,
        $g2mlApiKeyUID,
        $g2mlApiEndpointForLog,
        $g2mlApiMethod,
        $g2mlApiRequestStart,
        null
    );
}
