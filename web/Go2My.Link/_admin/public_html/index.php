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
 * 🛠️ Go2My.Link — Admin Dashboard Entry Point (Component A — Admin)
 * ============================================================================
 *
 * Entry point for admin.go2my.link — the user/org dashboard.
 * Uses file-based routing via ?route= parameter (set by .htaccess).
 *
 * Authentication is required for all admin pages. Unauthenticated users
 * are redirected to the login page on go2my.link.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.5.0
 * @since      Phase 2 (auth added Phase 4)
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials
// ============================================================================
// Admin uses Component A's auth_creds.php (same database).
// Path: Go2My.Link/.auth/auth_creds.php (two levels up from _admin/public_html)
// ============================================================================

$componentAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '.auth'
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

define('G2ML_COMPONENT',        'Admin');
define('G2ML_COMPONENT_NAME',   'Admin Dashboard');
define('G2ML_COMPONENT_DOMAIN', 'admin.go2my.link');

// Define G2ML_ROOT before page_init.php (web/ directory)
// From _admin/public_html → _admin → Go2My.Link → web
define('G2ML_ROOT', dirname(__DIR__, 3));

// ============================================================================
// 🚀 Step 3: Bootstrap the Application
// ============================================================================

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// Load accessibility helpers
require_once G2ML_INCLUDES
    . DIRECTORY_SEPARATOR . 'accessibility.php';

// ============================================================================
// 🔐 Step 4: Authentication Check
// ============================================================================
// All admin pages require at least the 'User' role. Unauthenticated users
// are redirected to the login page on the main go2my.link domain.
// ============================================================================

requireAuth('User');

// ============================================================================
// 🔀 Step 5: Route the Request
// ============================================================================

$route    = $_GET['route'] ?? '';
$pagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'pages';
$resolved = resolveRoute($route, $pagesDir);

// ============================================================================
// 📥 Step 5b: GDPR Data Export Download (#162)
// ============================================================================
// A GET ?download=<requestUID> request against /privacy/export must send its
// own Content-Type/Content-Disposition headers and exit — but Step 6 below
// ALWAYS requires header.php + nav.php (which echo real HTML; there is no
// output buffering anywhere in this app) before the resolved page file ever
// runs. By that point a header() call would silently fail ("headers already
// sent"), exactly as documented in pages/analytics/index.php's own docblock
// and the reason analytics-export.php (#44) is a standalone entry point
// rather than a pages/ file. Intercepting here — BEFORE Step 6 — is the
// equivalent fix for this route, without needing a second entry point or a
// different URL: see web/_functions/data_rights.php's own section docblock
// for the full rationale.
// ============================================================================

if ($resolved['segments'] === ['privacy', 'export'] && isset($_GET['download']))
{
    g2ml_handleDataExportDownloadRequest();
    exit;
}

// ============================================================================
// 📄 Step 6: Render the Page
// ============================================================================

if ($resolved['file'] !== null)
{
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';
    require $resolved['file'];
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
}
else
{
    // 404 or show admin dashboard home
    if ($route === '')
    {
        // No route — render the dashboard home page
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';
        require __DIR__ . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home.php';
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
    }
    else
    {
        // 404
        http_response_code(404);

        logActivity('page_view', 'not_found', 404, [
            'logData' => ['route' => $route],
        ]);

        $pageTitle = '404 — Page Not Found';

        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';
        echo '<div class="container py-5 text-center">';
        echo '<h1 class="display-4">404</h1>';
        echo '<p class="lead">The page you requested was not found.</p>';
        echo '<a href="/" class="btn btn-primary">Back to Dashboard</a>';
        echo '</div>';
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
    }
}
