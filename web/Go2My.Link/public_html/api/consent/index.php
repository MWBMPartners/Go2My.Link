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
 * 🍪 Go2My.Link — Cookie Consent API Endpoint
 * ============================================================================
 *
 * POST /api/consent/
 *
 * Records cookie consent preferences. Accepts both JSON (AJAX) and form-encoded
 * (no-JS fallback) POST requests. CSRF-protected, no auth required.
 *
 * Request body:
 *   {
 *     "csrf_token": "...",
 *     "essential":  true,        // Always true (informational)
 *     "analytics":  true|false,
 *     "functional": true|false,
 *     "marketing":  true|false
 *   }
 *
 * Without JavaScript (#291) the banner's own form is sent instead, form-encoded,
 * with csrf_token and consent_choice ("all" or "essential", from the button
 * that was pressed) in place of the three yes/no answers. The script's
 * fallback form sends csrf_token and the three answers as "1" or "0".
 *
 * Response:
 *   { "success": true } or { "success": false, "error": "..." }
 *   A form-encoded request is sent back to the page it came from, whether it
 *   succeeded or its security token was refused (nothing is recorded then).
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

// Show no PHP errors on screen, even if this file stops before the application
// has started. A signed-in global administrator gets them back later (#319).
if (function_exists('ini_set'))
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Do not name the PHP version in the response headers either.
if (function_exists('header_remove'))
{
    header_remove('X-Powered-By');
}

// ============================================================================
// 🚀 Bootstrap (load page_init without template rendering)
// ============================================================================
define('G2ML_COMPONENT', 'A');
define('G2ML_COMPONENT_NAME', 'Main Website');
define('G2ML_COMPONENT_DOMAIN', 'go2my.link');

// Calculate G2ML_ROOT: api/consent/index.php → public_html → Go2My.Link → web
define('G2ML_ROOT', dirname(__DIR__, 4));

// Load auth credentials
$authCredsPath = G2ML_ROOT . DIRECTORY_SEPARATOR . '_auth_keys' . DIRECTORY_SEPARATOR . 'auth_creds.php';
if (file_exists($authCredsPath))
{
    require_once $authCredsPath;
}

// Load page initialisation (functions, session, settings, etc.)
require_once G2ML_ROOT . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'page_init.php';

// ============================================================================
// 📋 Handle Request
// ============================================================================

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Determine content type (JSON or form-encoded)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJSON      = stripos($contentType, 'application/json') !== false;

if ($isJSON)
{
    $rawBody = file_get_contents('php://input');
    $data    = json_decode($rawBody, true);

    if (!is_array($data))
    {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }
}
else
{
    $data = $_POST;
}

// ============================================================================
// ↩️ Where a form-encoded request is sent back to
// ============================================================================
// The page the form came from, as a path on THIS host only (for example
// "/pricing"), or the home page "/" when the Referer is missing, names
// another host, or looks like a trick ("/\evil.com", "https:evil.com",
// "user@host"). The banner posts to "/api/consent/" on the site it is
// shown on, so the page it came from is always on the same host. Until
// #291 any full address on a list of our hosts was used as it was.
// 📖 Reference: security.php → g2ml_safeSameHostReturnPath()
$refererHeader = null;

if (isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER']))
{
    $refererHeader = $_SERVER['HTTP_REFERER'];
}

$requestHost = '';

if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']))
{
    $requestHost = $_SERVER['HTTP_HOST'];
}

if (function_exists('g2ml_safeSameHostReturnPath'))
{
    $referer = g2ml_safeSameHostReturnPath($refererHeader, $requestHost);
}
else
{
    $referer = '/';
}

// ============================================================================
// 🔒 CSRF Validation
// ============================================================================
$csrfToken = $data['csrf_token'] ?? '';

// A token that is not text (a list or a number in the JSON, say) is treated
// as no token, rather than stopping with a type error.
if (!is_string($csrfToken))
{
    $csrfToken = '';
}

if (!function_exists('g2ml_validateCSRFToken') || !g2ml_validateCSRFToken($csrfToken, 'cookie_consent'))
{
    if (function_exists('logActivity'))
    {
        logActivity('csrf_failure', 'error', 403, [
            'logData' => ['endpoint' => 'api/consent'],
        ]);
    }

    // A form sent by a browser (no JavaScript, or the script's fallback) is
    // sent back to its page rather than shown a page of raw JSON (#291).
    // Nothing is recorded. If consent is still needed there, the banner
    // comes back with a fresh token and the visitor can choose again; if
    // another request has just recorded it, the banner is simply gone.
    if (!$isJSON)
    {
        header('Location: ' . $referer, true, 303);
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);

    exit;
}

// ============================================================================
// 📝 Record Consent Preferences
// ============================================================================
$categories = ['analytics', 'functional', 'marketing'];
$results    = [];
$allSuccess = true;

// The banner's own buttons, used without JavaScript (#291), name the whole
// choice in one word instead of sending the three answers. Any other value
// is ignored, and the answers below are read as usual.
$bannerChoice = '';

if (isset($data['consent_choice']) && is_string($data['consent_choice']))
{
    $bannerChoice = $data['consent_choice'];
}

// Always record essential consent
g2ml_recordConsent('essential', true, 'banner');

foreach ($categories as $category)
{
    $given = false;

    if ($bannerChoice === 'all')
    {
        $given = true;
    }
    elseif ($bannerChoice === 'essential')
    {
        $given = false;
    }
    elseif (isset($data[$category]))
    {
        $given = filter_var($data[$category], FILTER_VALIDATE_BOOLEAN);
    }

    $result = g2ml_recordConsent($category, $given, 'banner');

    $results[$category] = $given;

    if (!$result)
    {
        $allSuccess = false;
    }
}

// ============================================================================
// 📤 Response
// ============================================================================
if ($isJSON)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $allSuccess,
        'consent' => $results,
    ]);
    exit;
}

// Form POST fallback — redirect back to the previous page (a same-host
// path, worked out above)
header('Location: ' . $referer);
exit;
