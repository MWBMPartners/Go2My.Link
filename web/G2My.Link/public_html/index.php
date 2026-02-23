<?php
/**
 * ============================================================================
 * 🔗 GoToMyLink — Shortlink Redirect Entry Point (Component B)
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
 *   2. Call sp_lookupShortURL to resolve the destination
 *   3. Log the activity
 *   4. Issue 302 redirect (or show error page)
 *
 * @package    GoToMyLink
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
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
    error_log('[GoToMyLink] CRITICAL: Component B auth_creds.php not found at: ' . $componentAuthPath);
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
// 🔀 Step 4: Extract Short Code and Resolve
// ============================================================================

$shortCode = $_GET['code'] ?? '';
$shortCode = trim($shortCode);

// No short code provided — redirect to main website
if ($shortCode === '')
{
    $fallbackURL = getSetting('redirect.fallback_url', 'https://go2my.link');

    logActivity('redirect', 'no_code', 302, [
        'logData' => ['reason' => 'Empty short code'],
    ]);

    header('Location: ' . $fallbackURL, true, 302);
    exit;
}

// Get the requesting domain (for custom org domain resolution)
$requestDomain = $_SERVER['HTTP_HOST'] ?? 'g2my.link';

// Strip port number if present
if (strpos($requestDomain, ':') !== false)
{
    $requestDomain = explode(':', $requestDomain)[0];
}

// ============================================================================
// 📞 Step 5: Call sp_lookupShortURL
// ============================================================================
// The stored procedure handles:
//   - Domain-to-org mapping
//   - Alias chain resolution (max 3 hops)
//   - Date range validation
//   - Active status check
//   - Fallback URL provision
//
// 📖 Reference: web/_sql/procedures/sp_lookupShortURL.sql
// ============================================================================

$result = dbCallProcedure(
    'sp_lookupShortURL',
    [$requestDomain, $shortCode],
    'ss',
    ['@outputDestination', '@outputStatus', '@outputOrgHandle']
);

if ($result === false)
{
    // Database error — fall back gracefully
    error_log('[GoToMyLink] ERROR: sp_lookupShortURL call failed for code: ' . $shortCode);

    logActivity('redirect', 'error', 500, [
        'shortCode' => $shortCode,
        'logData'   => ['reason' => 'SP call failed'],
    ]);

    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title></head>';
    echo '<body><h1>Service Temporarily Unavailable</h1>';
    echo '<p>Please try again in a few moments.</p></body></html>';
    exit;
}

$destination = $result['@outputDestination'] ?? null;
$status      = $result['@outputStatus'] ?? 'not_found';
$orgHandle   = $result['@outputOrgHandle'] ?? null;

// ============================================================================
// 📊 Step 6: Log Activity and Issue Redirect
// ============================================================================

$redirectCode = (int) getSetting('redirect.default_code', 302);

if ($status === 'success' && $destination !== null && $destination !== '')
{
    // ✅ Successful redirect
    logActivity('redirect', 'success', $redirectCode, [
        'orgHandle'      => $orgHandle,
        'shortCode'      => $shortCode,
        'destinationURL' => $destination,
    ]);

    // Update click count (fire-and-forget)
    dbUpdate(
        "UPDATE tblShortURLs SET clickCount = clickCount + 1 WHERE shortCode = ? AND orgHandle = ?",
        'ss',
        [$shortCode, $orgHandle]
    );

    header('Location: ' . $destination, true, $redirectCode);
    exit;
}

// ============================================================================
// ❌ Error States
// ============================================================================

logActivity('redirect', $status, 404, [
    'orgHandle' => $orgHandle,
    'shortCode' => $shortCode,
    'logData'   => ['spStatus' => $status],
]);

// Determine what to show the user
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

    default:
        http_response_code(404);
        $errorTitle   = 'Link Not Found';
        $errorMessage = 'The requested link could not be found.';
        break;
}

// If there's a fallback destination from the SP, redirect to it
if ($destination !== null && $destination !== '')
{
    header('Location: ' . $destination, true, 302);
    exit;
}

// Otherwise show a minimal error page
// (Branded error pages will be built in Phase 3)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — GoToMyLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container py-5 text-center">
        <h1 class="display-4 text-muted"><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="https://go2my.link" class="btn btn-primary mt-3">Go to GoToMyLink</a>
    </div>
</body>
</html>
