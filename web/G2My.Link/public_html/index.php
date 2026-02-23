<?php
/**
 * ============================================================================
 * 🔗 Go2My.Link — Shortlink Redirect Entry Point (Component B)
 * ============================================================================
 *
 * Entry point for g2my.link — the default short URL redirect domain.
 * Also handles custom org domains (camsda.link, tyney.link, etc.).
 *
 * This is the PERFORMANCE-CRITICAL component. Every short URL redirect
 * passes through this file. Minimal overhead, no template rendering.
 *
 * Flow:
 *   1. Extract short code from ?code= (set by .htaccess)
 *   2. Resolve the short code via resolver functions (→ sp_lookupShortURL)
 *   3. Optionally validate destination URL accessibility
 *   4. Log the activity (respecting DNT and analytics setting)
 *   5. Issue 302 redirect (or show branded error page)
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 2 (refactored Phase 3)
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials
// ============================================================================

$componentAuthPath = dirname(__DIR__)
    . DIRECTORY_SEPARATOR . '_auth_keys'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}
else
{
    error_log('[Go2My.Link] CRITICAL: Component B auth_creds.php not found at: ' . $componentAuthPath);
}

// ============================================================================
// 🏷️ Step 2: Define Component Constants
// ============================================================================

define('G2ML_COMPONENT',        'B');
define('G2ML_COMPONENT_NAME',   'Shortlink Redirect');
define('G2ML_COMPONENT_DOMAIN', 'g2my.link');

// Define G2ML_ROOT before page_init.php (web/ directory)
define('G2ML_ROOT', dirname(__DIR__, 2));

// ============================================================================
// 🚀 Step 3: Bootstrap the Application
// ============================================================================

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// ============================================================================
// 📦 Step 4: Load Component B Function Files
// ============================================================================

$componentFunctionsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_functions';

require_once $componentFunctionsDir
    . DIRECTORY_SEPARATOR . 'domain_resolver.php';

require_once $componentFunctionsDir
    . DIRECTORY_SEPARATOR . 'redirect_resolver.php';

// ============================================================================
// 🔒 Step 5: Determine Logging Behaviour
// ============================================================================
// Respect the Do Not Track (DNT) header when the analytics.respect_dnt
// setting is enabled. Click counts are still updated (aggregate, not PII).
//
// 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/DNT
// ============================================================================

$shouldLog = getSetting('analytics.log_activity', true);
$respectDNT = getSetting('analytics.respect_dnt', true);
$isDNT = (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1');

if ($respectDNT && $isDNT)
{
    $shouldLog = false;
}

// ============================================================================
// 🔀 Step 6: Extract Short Code
// ============================================================================

$shortCode = trim($_GET['code'] ?? '');

// No short code provided — redirect to main website
if ($shortCode === '')
{
    $fallbackURL = getSetting('redirect.fallback_url', 'https://go2my.link');

    if ($shouldLog)
    {
        logActivity('redirect', 'no_code', 302, [
            'logData' => ['reason' => 'Empty short code'],
        ]);
    }

    buildRedirectResponse($fallbackURL, 302);
    // buildRedirectResponse calls exit — execution stops here
}

// ============================================================================
// 🌐 Step 7: Determine Requesting Domain
// ============================================================================
// Custom org domains (camsda.link, tyney.link, etc.) are resolved to their
// org handle by the sp_lookupShortURL stored procedure.
// ============================================================================

$requestDomain = $_SERVER['HTTP_HOST'] ?? 'g2my.link';

// Strip port number if present (e.g., localhost:8080)
// 📖 Reference: https://www.php.net/manual/en/function.strpos.php
if (strpos($requestDomain, ':') !== false)
{
    $requestDomain = explode(':', $requestDomain)[0];
}

// ============================================================================
// 📞 Step 8: Resolve the Short Code
// ============================================================================
// The resolveShortCode() function wraps sp_lookupShortURL which handles:
//   - Domain-to-org mapping
//   - Alias chain resolution (max 3 hops)
//   - Date range validation (startDate / endDate)
//   - Active status check (isActive flag)
//   - Fallback URL provision
//
// 📖 Reference: web/G2My.Link/_functions/redirect_resolver.php
// 📖 Reference: web/_sql/procedures/sp_lookupShortURL.sql
// ============================================================================

$result = resolveShortCode($requestDomain, $shortCode);

$destination = $result['destination'];
$status      = $result['status'];
$orgHandle   = $result['orgHandle'];

// ============================================================================
// ❌ Handle SP Error (database failure)
// ============================================================================

if ($status === 'error')
{
    if ($shouldLog)
    {
        logActivity('redirect', 'error', 500, [
            'shortCode' => $shortCode,
            'logData'   => ['reason' => 'SP call failed'],
        ]);
    }

    http_response_code(500);
    $errorTitle   = 'Service Temporarily Unavailable';
    $errorMessage = 'Please try again in a few moments.';
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

// ============================================================================
// ✅ Step 9: Handle Successful Resolution
// ============================================================================

if ($status === 'success' && $destination !== null && $destination !== '')
{
    // 🔍 Optional: Validate destination URL accessibility
    // Gated behind setting — OFF by default for performance
    // 📖 Reference: web/G2My.Link/_functions/redirect_resolver.php — validateDestination()
    $shouldValidate = getSetting('redirect.validate_destination', false);

    if ($shouldValidate)
    {
        $validation = validateDestination($destination);

        if (!$validation['valid'])
        {
            if ($shouldLog)
            {
                logActivity('redirect', 'validation_failed', 503, [
                    'orgHandle'      => $orgHandle,
                    'shortCode'      => $shortCode,
                    'destinationURL' => $destination,
                    'logData'        => [
                        'validationError' => $validation['error'],
                        'statusCode'      => $validation['statusCode'],
                    ],
                ]);
            }

            // Show the validation page with countdown to fallback
            require __DIR__ . DIRECTORY_SEPARATOR . 'validating.php';
            exit;
        }
    }

    // 📊 Log the successful redirect
    $redirectCode = (int) getSetting('redirect.default_code', 302);

    if ($shouldLog)
    {
        logActivity('redirect', 'success', $redirectCode, [
            'orgHandle'      => $orgHandle,
            'shortCode'      => $shortCode,
            'destinationURL' => $destination,
        ]);
    }

    // 📈 Update click count and last click timestamp (fire-and-forget)
    // This is aggregate data (not PII) so it runs even when DNT is set
    dbUpdate(
        "UPDATE tblShortURLs
         SET clickCount = clickCount + 1,
             lastClickAt = NOW()
         WHERE shortCode = ?
           AND orgHandle = ?",
        'ss',
        [$shortCode, $orgHandle]
    );

    // 🚀 Issue the redirect
    buildRedirectResponse($destination, $redirectCode);
    // buildRedirectResponse calls exit — execution stops here
}

// ============================================================================
// ❌ Step 10: Handle Error States
// ============================================================================

// Determine HTTP status code and error messaging
switch ($status)
{
    case 'not_found':
        http_response_code(404);
        $errorTitle   = '404 — Link Not Found';
        $errorMessage = 'The short link you requested does not exist or has been removed.';
        break;

    case 'inactive':
        http_response_code(410);
        $errorTitle   = 'Link Disabled';
        $errorMessage = 'This short link has been disabled by its owner.';
        break;

    case 'not_yet_active':
        http_response_code(404);
        $errorTitle   = 'Link Not Yet Active';
        $errorMessage = 'This short link is not yet active. Please try again later.';
        break;

    case 'expired':
        http_response_code(410);
        $errorTitle   = 'Link Expired';
        $errorMessage = 'This short link has expired and is no longer available.';
        break;

    case 'max_hops_exceeded':
        http_response_code(500);
        $errorTitle   = 'Redirect Error';
        $errorMessage = 'This link could not be resolved due to a configuration issue.';
        break;

    case 'no_destination':
        http_response_code(404);
        $errorTitle   = 'Link Not Configured';
        $errorMessage = 'This short link has no destination URL configured.';
        break;

    default:
        http_response_code(404);
        $errorTitle   = 'Link Not Found';
        $errorMessage = 'The requested link could not be found.';
        break;
}

// Log the error
if ($shouldLog)
{
    $httpCode = http_response_code();
    logActivity('redirect', $status, $httpCode, [
        'orgHandle' => $orgHandle,
        'shortCode' => $shortCode,
        'logData'   => ['spStatus' => $status],
    ]);
}

// If the SP returned a fallback destination, redirect to it
if ($destination !== null && $destination !== '')
{
    buildRedirectResponse($destination, 302);
    // buildRedirectResponse calls exit
}

// Show the appropriate branded error page
// Variables $errorTitle, $errorMessage, $orgHandle, $status are available
// to the included error page templates
switch ($status)
{
    case 'expired':
    case 'not_yet_active':
        require __DIR__ . DIRECTORY_SEPARATOR . 'expired.php';
        break;

    default:
        require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
        break;
}
exit;
