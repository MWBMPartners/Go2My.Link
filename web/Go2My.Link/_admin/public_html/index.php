<?php
/**
 * ============================================================================
 * 🛠️ GoToMyLink — Admin Dashboard Entry Point (Component A — Admin)
 * ============================================================================
 *
 * Entry point for admin.go2my.link — the user/org dashboard.
 * Uses file-based routing via ?route= parameter (set by .htaccess).
 *
 * Authentication is required for all admin pages (implemented in Phase 5).
 * For now, routes to pages/ directory with an auth placeholder.
 *
 * @package    GoToMyLink
 * @subpackage ComponentA_Admin
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials
// ============================================================================
// Admin uses Component A's auth_creds.php (same database).
// Path: Go2My.Link/_auth_keys/auth_creds.php (two levels up from _admin/public_html)
// ============================================================================

$componentAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '_auth_keys'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}
else
{
    error_log('[GoToMyLink] CRITICAL: Component A auth_creds.php not found at: ' . $componentAuthPath);
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
// 🔐 Step 4: Authentication Check (Placeholder — Phase 5)
// ============================================================================
// All admin pages require authentication. In Phase 5, this will check for
// a valid session and redirect to the login page if not authenticated.
//
// For now, we allow access for framework testing purposes.
// ============================================================================

// TODO: Phase 5 — Implement authentication check
// if (!isset($_SESSION['user_uid']) || $_SESSION['user_uid'] <= 0) {
//     header('Location: https://go2my.link/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
//     exit;
// }

// ============================================================================
// 🔀 Step 5: Route the Request
// ============================================================================

$route    = $_GET['route'] ?? '';
$pagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'pages';
$resolved = resolveRoute($route, $pagesDir);

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
        // No route — show admin dashboard placeholder
        $pageTitle = 'Dashboard';

        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
        require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'nav.php';
        ?>
        <div class="container py-5">
            <h1><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard</h1>
            <p class="lead text-muted">
                Admin dashboard — coming in Phase 5 (User System).
            </p>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card text-center p-4">
                        <i class="fas fa-link fa-3x text-primary mb-3" aria-hidden="true"></i>
                        <h5>My Links</h5>
                        <p class="text-muted small">Manage your short links</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-4">
                        <i class="fas fa-chart-line fa-3x text-success mb-3" aria-hidden="true"></i>
                        <h5>Analytics</h5>
                        <p class="text-muted small">View click analytics</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-4">
                        <i class="fas fa-cog fa-3x text-secondary mb-3" aria-hidden="true"></i>
                        <h5>Settings</h5>
                        <p class="text-muted small">Account & organisation settings</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
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
