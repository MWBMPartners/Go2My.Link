<?php
/**
 * ============================================================================
 * 📋 Go2My.Link — LinksPage Entry Point (Component C)
 * ============================================================================
 *
 * Entry point for lnks.page — the LinkTree-like link listing service.
 * Routes requests via ?slug= parameter (set by .htaccess) to render
 * user/organisation LinksPages.
 *
 * Full LinksPage rendering is implemented in Phase 7. This is a placeholder
 * that sets up the bootstrap and shows a "coming soon" or 404 response.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
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
    error_log('[Go2My.Link] CRITICAL: Component C auth_creds.php not found at: ' . $componentAuthPath);
}

// ============================================================================
// 🏷️ Step 2: Define Component Constants
// ============================================================================

define('G2ML_COMPONENT',        'C');
define('G2ML_COMPONENT_NAME',   'LinksPage');
define('G2ML_COMPONENT_DOMAIN', 'lnks.page');

// Define G2ML_ROOT before page_init.php (web/ directory)
define('G2ML_ROOT', dirname(__DIR__, 2));

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
// 📋 Step 4: Handle Slug Routing
// ============================================================================
// Full implementation in Phase 7. For now, show a placeholder page.
// ============================================================================

$slug = $_GET['slug'] ?? '';
$slug = trim($slug);

// Homepage (no slug)
if ($slug === '')
{
    $pageTitle = 'LinksPage — ' . getSetting('site.name', 'Go2My.Link');
    $pageDesc  = 'Create your own customisable link listing page.';

    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
    ?>
    <div class="container py-5 text-center">
        <h1 class="display-4"><?php echo htmlspecialchars(getSetting('site.name', 'Go2My.Link'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <h2 class="text-muted">LinksPage</h2>
        <p class="lead mt-3">
            <?php if (function_exists('__')) { echo __('linkspage.coming_soon'); } else { echo 'LinksPage is coming soon. Create your own customisable link listing page.'; } ?>
        </p>
        <a href="https://go2my.link" class="btn btn-primary btn-lg mt-3">
            <?php if (function_exists('__')) { echo __('linkspage.learn_more'); } else { echo 'Learn More'; } ?>
        </a>
    </div>
    <?php
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
    exit;
}

// ============================================================================
// Slug provided — attempt to look up LinksPage (placeholder)
// Full implementation in Phase 7
// ============================================================================

// Log the page view attempt
logActivity('linkspage_view', 'not_implemented', 404, [
    'logData' => ['slug' => $slug],
]);

http_response_code(404);
$pageTitle = '404 — Page Not Found';

require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';
?>
<div class="container py-5 text-center">
    <h1 class="display-4 text-muted">404</h1>
    <p class="lead">
        <?php if (function_exists('__')) { echo __('linkspage.not_found'); } else { echo 'This LinksPage does not exist or has not been set up yet.'; } ?>
    </p>
    <a href="/" class="btn btn-primary mt-3">
        <?php if (function_exists('__')) { echo __('error.back_home'); } else { echo 'Back to Home'; } ?>
    </a>
</div>
<?php
require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'footer.php';
