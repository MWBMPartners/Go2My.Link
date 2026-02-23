<?php
/**
 * ============================================================================
 * 🏠 Go2My.Link — Main Website Entry Point (Component A)
 * ============================================================================
 *
 * Entry point for go2my.link — the public-facing main website.
 * Routes requests via ?route= parameter (set by .htaccess) to PHP files
 * in the pages/ directory using the file-based router.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 2 (updated Phase 3)
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials
// ============================================================================
// Component-specific auth_creds.php (chains to server-wide via require_once).
// Must be loaded BEFORE page_init.php so DB constants are available.
//
// 📖 Reference: https://www.php.net/manual/en/function.require-once.php
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
    error_log('[Go2My.Link] CRITICAL: Component A auth_creds.php not found at: ' . $componentAuthPath);
}

// ============================================================================
// 🏷️ Step 2: Define Component Constants
// ============================================================================
// These constants identify the current component throughout the application.
// ============================================================================

define('G2ML_COMPONENT',        'A');
define('G2ML_COMPONENT_NAME',   'Main Website');
define('G2ML_COMPONENT_DOMAIN', 'go2my.link');

// Define G2ML_ROOT before page_init.php (web/ directory)
define('G2ML_ROOT', dirname(__DIR__, 2));

// ============================================================================
// 🚀 Step 3: Bootstrap the Application
// ============================================================================
// page_init.php loads all functions, starts session, loads settings, etc.
// ============================================================================

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// Load accessibility helpers
require_once G2ML_INCLUDES
    . DIRECTORY_SEPARATOR . 'accessibility.php';

// ============================================================================
// 📦 Step 3b: Load Component A Function Files
// ============================================================================
// Component-specific functions for URL creation, etc.
// ============================================================================

$componentFunctionsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_functions';

if (is_dir($componentFunctionsDir))
{
    $componentFunctionFiles = glob(
        $componentFunctionsDir . DIRECTORY_SEPARATOR . '*.php'
    );

    if ($componentFunctionFiles !== false)
    {
        foreach ($componentFunctionFiles as $funcFile)
        {
            require_once $funcFile;
        }
    }
}

// ============================================================================
// 🔀 Step 4: Route the Request
// ============================================================================
// The .htaccess file rewrites clean URLs to index.php?route=path
// The router maps route segments to files in the pages/ directory.
// ============================================================================

$route    = $_GET['route'] ?? '';
$pagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'pages';
$resolved = resolveRoute($route, $pagesDir);

// ============================================================================
// 📄 Step 5: Render the Page
// ============================================================================

if ($resolved['file'] !== null)
{
    // Page found — render with header/nav/footer
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';
    require $resolved['file'];
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
}
else
{
    // 404 — Page not found
    http_response_code(404);

    // Log the 404
    logActivity('page_view', 'not_found', 404, [
        'logData' => ['route' => $route],
    ]);

    $pageTitle = '404 — Page Not Found';

    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';

    // Check for custom 404 page
    $custom404 = $pagesDir . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . '404.php';

    if (file_exists($custom404))
    {
        require $custom404;
    }
    else
    {
        // Minimal 404 fallback (replaced with branded page in Phase 4)
        echo '<div class="container py-5 text-center">';
        echo '<h1 class="display-4">404</h1>';
        echo '<p class="lead">' . __('error.404.message') . '</p>';
        echo '<a href="/" class="btn btn-primary">' . __('error.back_home') . '</a>';
        echo '</div>';
    }

    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
}
