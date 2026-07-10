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
 * 📋 Go2My.Link — LinksPage Entry Point (Component C)
 * ============================================================================
 *
 * Entry point for lnks.page — the LinkTree-like link listing service.
 * Routes requests via ?slug= (set by .htaccess) to render user/organisation
 * LinksPages.
 *
 * Flow:
 *   1. Bootstrap (auth_creds, component constants, page_init).
 *   2. Load the LinksPage resolver + renderer (web/Lnks.page/_functions/).
 *   3. No slug — show the (self-contained) "coming soon" page.
 *   4. A slug is present:
 *      a. Validate its charset/length BEFORE ever touching the database
 *         (g2ml_linkspageIsValidSlug()).
 *      b. Resolve it (g2ml_resolveLinksPage()) — published + active page,
 *         its SYSTEM template, and its active items, ordered by sortOrder.
 *      c. Not found / unpublished / malformed → branded 404 (404.php),
 *         never a raw stack trace.
 *      d. Found → render (g2ml_renderLinksPage()) and emit the HTML.
 *
 * 🔒 SECURITY: customHTML/customCSS are NEVER read here (deferred to C.6/#49
 *    — see linkspage_resolver.php / linkspage_renderer.php file headers).
 *    Only SYSTEM templates are ever consumed.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 2 (renderer built Phase 8 / #45)
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

// ============================================================================
// 📦 Step 4: Load Component C Function Files
// ============================================================================

$componentFunctionsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_functions';

require_once $componentFunctionsDir
    . DIRECTORY_SEPARATOR . 'linkspage_resolver.php';

require_once $componentFunctionsDir
    . DIRECTORY_SEPARATOR . 'linkspage_renderer.php';

// ============================================================================
// 📋 Step 5: Read & Trim the Slug
// ============================================================================

$slug = $_GET['slug'] ?? '';
$slug = trim($slug);

// ============================================================================
// 🏠 Step 6: No Slug — Self-Contained "Coming Soon" Homepage
// ============================================================================
// Deliberately self-contained (no external CDN scripts/stylesheets) so it
// renders correctly under this component's strict Content-Security-Policy
// (see web/Lnks.page/public_html/.htaccess).
// ============================================================================

if ($slug === '')
{
    if (function_exists('getSetting'))
    {
        $siteName = getSetting('site.name', 'Go2My.Link');
    }
    else
    {
        $siteName = 'Go2My.Link';
    }

    if (function_exists('__'))
    {
        $comingSoonMessage = __('linkspage.coming_soon');
        $learnMoreLabel    = __('linkspage.learn_more');
    }
    else
    {
        $comingSoonMessage = 'LinksPage is coming soon. Create your own customisable link listing page.';
        $learnMoreLabel    = 'Learn More';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="index, follow">
        <title>LinksPage — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f8f9fa;
                color: #212529;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .lp-home-container {
                max-width: 560px;
                text-align: center;
                padding: 2rem 1.5rem;
            }
            .lp-home-title {
                font-size: 2rem;
                font-weight: 800;
                margin: 0 0 0.25rem;
            }
            .lp-home-subtitle {
                font-size: 1.1rem;
                color: #6c757d;
                margin: 0 0 1rem;
            }
            .lp-home-message {
                font-size: 1rem;
                color: #495057;
                margin: 0 0 1.75rem;
            }
            .lp-home-cta {
                display: inline-block;
                padding: 0.65rem 1.5rem;
                background: #1E88E5;
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
            }
            .lp-home-cta:hover,
            .lp-home-cta:focus {
                background: #1668ab;
            }
        </style>
    </head>
    <body>
        <main class="lp-home-container">
            <h1 class="lp-home-title"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="lp-home-subtitle">LinksPage</p>
            <p class="lp-home-message"><?php echo htmlspecialchars($comingSoonMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            <a class="lp-home-cta" href="https://go2my.link">
                <?php echo htmlspecialchars($learnMoreLabel, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================================
// 🔤 Step 7: Validate the Slug Shape BEFORE Touching the Database
// ============================================================================

if (!g2ml_linkspageIsValidSlug($slug))
{
    logActivity('linkspage_view', 'invalid_slug', 404, [
        'logData' => ['slug' => $slug],
    ]);

    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

// ============================================================================
// 📞 Step 8: Resolve the Slug
// ============================================================================

$pageModel = g2ml_resolveLinksPage($slug);

if ($pageModel === null)
{
    logActivity('linkspage_view', 'not_found', 404, [
        'logData' => ['slug' => $slug],
    ]);

    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

// ============================================================================
// ✅ Step 9: Render the Resolved Page
// ============================================================================

$pageOrgHandle = null;

if (isset($pageModel['page']['orgHandle']) && is_string($pageModel['page']['orgHandle']))
{
    $pageOrgHandle = $pageModel['page']['orgHandle'];
}

logActivity('linkspage_view', 'success', 200, [
    'orgHandle' => $pageOrgHandle,
    'logData'   => [
        'slug'    => $slug,
        'pageUID' => $pageModel['page']['pageUID'] ?? null,
    ],
]);

echo g2ml_renderLinksPage($pageModel);
exit;
