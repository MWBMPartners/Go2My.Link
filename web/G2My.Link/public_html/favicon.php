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
 * 🎨 Go2My.Link — Dynamic Favicon Handler (Component B)
 * ============================================================================
 *
 * Serves favicon.ico dynamically — org-specific favicons for custom domains,
 * or the default Go2My.Link favicon for g2my.link.
 *
 * Settings:
 *   - indexer.allow_favicon  — Whether to serve favicon (else 404)
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

// ============================================================================
// 📦 Bootstrap — Minimal application loading
// ============================================================================

$componentAuthPath = dirname(__DIR__)
    . DIRECTORY_SEPARATOR . '_auth_keys'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}

define('G2ML_COMPONENT',        'B');
define('G2ML_COMPONENT_NAME',   'Shortlink Redirect');
define('G2ML_COMPONENT_DOMAIN', 'g2my.link');
define('G2ML_ROOT', dirname(__DIR__, 2));

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// Load domain resolver for org-specific favicon lookup
$componentFunctionsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_functions';
require_once $componentFunctionsDir . DIRECTORY_SEPARATOR . 'domain_resolver.php';

// ============================================================================
// 🎨 Serve Favicon
// ============================================================================

// Check if favicon serving is enabled
$allowFavicon = getSetting('indexer.allow_favicon', true);

if (!$allowFavicon)
{
    http_response_code(404);
    exit;
}

// ============================================================================
// 🌐 Determine the requesting domain's organisation
// ============================================================================

$requestDomain = $_SERVER['HTTP_HOST'] ?? 'g2my.link';

// Strip port number if present
// 📖 Reference: https://www.php.net/manual/en/function.strpos.php
if (strpos($requestDomain, ':') !== false)
{
    $requestDomain = explode(':', $requestDomain)[0];
}

$orgHandle = getOrgByDomain($requestDomain);

// ============================================================================
// 🏢 Try org-specific favicon
// ============================================================================

if ($orgHandle !== '[default]')
{
    $orgFaviconPath = getOrgFavicon($orgHandle);

    if ($orgFaviconPath !== null)
    {
        // Build the full filesystem path to the org's favicon
        // Org favicons are stored in the _uploads/ directory
        $uploadsDir  = G2ML_ROOT . DIRECTORY_SEPARATOR . '_uploads';
        $faviconFile = $uploadsDir . DIRECTORY_SEPARATOR . $orgFaviconPath;

        if (file_exists($faviconFile) && is_file($faviconFile))
        {
            // Detect MIME type
            // 📖 Reference: https://www.php.net/manual/en/function.mime-content-type.php
            $mimeType = mime_content_type($faviconFile);

            if ($mimeType === false)
            {
                $mimeType = 'image/x-icon';
            }

            header('Content-Type: ' . $mimeType);
            header('Cache-Control: public, max-age=86400'); // 24 hours
            header('Content-Length: ' . filesize($faviconFile));
            readfile($faviconFile);
            exit;
        }
    }
}

// ============================================================================
// 🔗 Serve default favicon
// ============================================================================

$defaultFavicon = __DIR__ . DIRECTORY_SEPARATOR . 'favicon_default.ico';

if (file_exists($defaultFavicon) && is_file($defaultFavicon))
{
    header('Content-Type: image/x-icon');
    header('Cache-Control: public, max-age=86400'); // 24 hours
    header('Content-Length: ' . filesize($defaultFavicon));
    readfile($defaultFavicon);
    exit;
}

// ============================================================================
// ❌ No favicon available
// ============================================================================

http_response_code(404);
exit;
