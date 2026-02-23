<?php
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
 * Response:
 *   { "success": true } or { "success": false, "error": "..." }
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

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
// 🔒 CSRF Validation
// ============================================================================
$csrfToken = $data['csrf_token'] ?? '';

if (!function_exists('g2ml_validateCSRFToken') || !g2ml_validateCSRFToken($csrfToken, 'cookie_consent'))
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);

    if (function_exists('logActivity'))
    {
        logActivity('csrf_failure', 'error', 403, [
            'logData' => ['endpoint' => 'api/consent'],
        ]);
    }

    exit;
}

// ============================================================================
// 📝 Record Consent Preferences
// ============================================================================
$categories = ['analytics', 'functional', 'marketing'];
$results    = [];
$allSuccess = true;

// Always record essential consent
g2ml_recordConsent('essential', true, 'banner');

foreach ($categories as $category)
{
    $given = false;

    if (isset($data[$category]))
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

// Form POST fallback — redirect back to previous page (validated to same origin)
$referer      = $_SERVER['HTTP_REFERER'] ?? '/';
$refererHost  = parse_url($referer, PHP_URL_HOST);
$allowedHosts = ['go2my.link', 'www.go2my.link', 'admin.go2my.link', 'g2my.link', 'lnks.page'];

if ($refererHost !== null && !in_array(strtolower($refererHost), $allowedHosts, true))
{
    $referer = '/'; // Fall back to homepage for unrecognised origins
}

header('Location: ' . $referer);
exit;
