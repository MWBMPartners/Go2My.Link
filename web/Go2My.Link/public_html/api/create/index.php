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
 * 📡 Go2My.Link — Internal API: Create Short URL (Component A)
 * ============================================================================
 *
 * JSON API endpoint for creating short URLs. Accepts POST requests with
 * a longURL parameter and returns the created short URL.
 *
 * This endpoint handles:
 *   - CSRF token validation (for form submissions)
 *   - Bot protection verification (Turnstile/reCAPTCHA, conditional)
 *   - IP-based rate limiting
 *   - URL validation and creation
 *   - No-JS fallback (redirect with query params for non-AJAX requests)
 *
 * Foundation for Phase 6 public API (will add API key auth as alternative
 * to CSRF tokens).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

// ============================================================================
// 📦 Step 1: Bootstrap the Application
// ============================================================================
// Same auth/bootstrap pattern as the main index.php entry point.
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
    error_log('[Go2My.Link] CRITICAL: Component A auth_creds.php not found');
}

// This is a standalone HTTP entry point (never require()'d by another
// script), so — matching every other entry point in the codebase (e.g.
// web/Go2My.Link/public_html/index.php, web/Go2My.Link/public_html/api/v1/index.php)
// — these constants are defined unconditionally rather than guarded behind
// a defined() check that could never actually be true in practice.
define('G2ML_COMPONENT',        'A');
define('G2ML_COMPONENT_NAME',   'Main Website');
define('G2ML_COMPONENT_DOMAIN', 'go2my.link');
define('G2ML_ROOT', dirname(__DIR__, 3));

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// Load the short URL creation functions
$componentFunctionsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '_functions';
require_once $componentFunctionsDir . DIRECTORY_SEPARATOR . 'shorturl_create.php';

// ============================================================================
// 🔀 Step 2: Determine if this is an AJAX or standard form POST
// ============================================================================
// For non-AJAX requests (no-JS fallback), we redirect back to the homepage
// with the result as query parameters.
//
// 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest
// ============================================================================

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ============================================================================
// 🔎 Step 2b: Determine whether to emit a STRUCTURED response
// ============================================================================
// A structured response (JSON by default, XML on request) is returned to any
// XHR/fetch caller (X-Requested-With) AND to any API client that explicitly
// asks for XML via "?format=xml" or an "Accept: application/xml" header, even
// when X-Requested-With is absent. Everything else keeps the no-JS redirect
// fallback. Format selection itself lives in g2ml_apiRespond().
//
// 📖 Reference: web/_functions/api_response.php — g2ml_apiWantsXml()
// ============================================================================

$acceptHeaderValue = '';

if (isset($_SERVER['HTTP_ACCEPT']) && is_string($_SERVER['HTTP_ACCEPT']))
{
    $acceptHeaderValue = $_SERVER['HTTP_ACCEPT'];
}

$isStructuredRequest = $isAjax;

if (g2ml_apiWantsXml($_GET, $acceptHeaderValue))
{
    $isStructuredRequest = true;
}

// ============================================================================
// 🛡️ Step 3: Enforce POST method
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    if ($isStructuredRequest)
    {
        header('Allow: POST');
        g2ml_apiRespond(['success' => false, 'error' => 'Method not allowed.'], 405);
    }
    else
    {
        // Redirect non-POST to homepage
        header('Location: /', true, 302);
    }
    exit;
}

// ============================================================================
// 🔒 Step 4: Validate CSRF Token
// ============================================================================
// 📖 Reference: web/_functions/security.php — g2ml_validateCSRFToken()
// ============================================================================

$csrfToken = $_POST['_csrf_token'] ?? '';

if (!g2ml_validateCSRFToken($csrfToken, 'shorten_url'))
{
    $errorMsg = 'Your session has expired. Please reload the page and try again.';

    if ($isStructuredRequest)
    {
        g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 403);
    }
    else
    {
        header('Location: /?error=' . urlencode($errorMsg), true, 303);
    }
    exit;
}

// ============================================================================
// 🤖 Step 5: Verify Bot Protection (Conditional)
// ============================================================================
// Only verify if CAPTCHA keys are configured in settings.
// Turnstile is checked first; if not configured, reCAPTCHA is checked.
// If neither is configured, bot protection is skipped entirely.
//
// 📖 Reference: web/Go2My.Link/_functions/shorturl_create.php — verifyCaptcha()
// ============================================================================

$turnstileSecretKey = getSetting('captcha.turnstile_secret_key', '');
$recaptchaSecretKey = getSetting('captcha.recaptcha_secret_key', '');

if ($turnstileSecretKey !== '')
{
    // Cloudflare Turnstile verification
    $captchaResponse = $_POST['cf-turnstile-response'] ?? '';

    if ($captchaResponse === '')
    {
        $errorMsg = 'Please complete the CAPTCHA verification.';

        if ($isStructuredRequest)
        {
            g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 422);
        }
        else
        {
            header('Location: /?error=' . urlencode($errorMsg), true, 303);
        }
        exit;
    }

    if (!verifyCaptcha('turnstile', $captchaResponse, $turnstileSecretKey))
    {
        $errorMsg = 'CAPTCHA verification failed. Please try again.';

        if ($isStructuredRequest)
        {
            g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 403);
        }
        else
        {
            header('Location: /?error=' . urlencode($errorMsg), true, 303);
        }
        exit;
    }
}
elseif ($recaptchaSecretKey !== '')
{
    // Google reCAPTCHA verification
    $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if ($captchaResponse === '')
    {
        $errorMsg = 'Please complete the CAPTCHA verification.';

        if ($isStructuredRequest)
        {
            g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 422);
        }
        else
        {
            header('Location: /?error=' . urlencode($errorMsg), true, 303);
        }
        exit;
    }

    if (!verifyCaptcha('recaptcha', $captchaResponse, $recaptchaSecretKey))
    {
        $errorMsg = 'CAPTCHA verification failed. Please try again.';

        if ($isStructuredRequest)
        {
            g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 403);
        }
        else
        {
            header('Location: /?error=' . urlencode($errorMsg), true, 303);
        }
        exit;
    }
}
// If neither is configured, skip CAPTCHA entirely (rate limiting is the safety net)

// ============================================================================
// ⏱️ Step 6: Check Rate Limit
// ============================================================================
// 📖 Reference: web/Go2My.Link/_functions/shorturl_create.php — checkAnonymousRateLimit()
// ============================================================================

$clientIP  = g2ml_getClientIP();
$rateCheck = checkAnonymousRateLimit($clientIP);

if (!$rateCheck['allowed'])
{
    $errorMsg = 'Rate limit exceeded. Please try again later.';

    logActivity('create_link', 'rate_limited', 429, [
        'logData' => [
            'hourlyCount' => $rateCheck['hourlyCount'],
            'dailyCount'  => $rateCheck['dailyCount'],
        ],
    ]);

    if ($isStructuredRequest)
    {
        header('Retry-After: 3600');
        g2ml_apiRespond([
            'success'   => false,
            'error'     => $errorMsg,
            'remaining' => 0,
        ], 429);
    }
    else
    {
        header('Location: /?error=' . urlencode($errorMsg), true, 303);
    }
    exit;
}

// ============================================================================
// 📋 Step 7: Extract and Validate Input
// ============================================================================

$longURL = trim($_POST['longURL'] ?? '');

if ($longURL === '')
{
    $errorMsg = 'Please enter a URL to shorten.';

    if ($isStructuredRequest)
    {
        g2ml_apiRespond(['success' => false, 'error' => $errorMsg], 422);
    }
    else
    {
        header('Location: /?error=' . urlencode($errorMsg), true, 303);
    }
    exit;
}

// ============================================================================
// ✨ Step 8: Create the Short URL
// ============================================================================
// 📖 Reference: web/Go2My.Link/_functions/shorturl_create.php — createShortURL()
// ============================================================================

$result = createShortURL($longURL, [
    'orgHandle' => '[default]',
    'userUID'   => $_SESSION['user_uid'] ?? null, // null for anonymous users
]);

// ============================================================================
// 📤 Step 9: Return the Response
// ============================================================================

if ($result['success'])
{
    if ($isStructuredRequest)
    {
        g2ml_apiRespond([
            'success'   => true,
            'shortURL'  => $result['shortURL'],
            'shortCode' => $result['shortCode'],
        ], 201);
    }
    else
    {
        // No-JS fallback: redirect back to homepage with the created URL
        header('Location: /?created=' . urlencode($result['shortURL']), true, 303);
    }
}
else
{
    if ($isStructuredRequest)
    {
        g2ml_apiRespond([
            'success' => false,
            'error'   => $result['error'],
        ], 422);
    }
    else
    {
        // No-JS fallback: redirect back with error
        header('Location: /?error=' . urlencode($result['error']), true, 303);
    }
}
exit;
