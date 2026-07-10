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
 * 🚀 Go2My.Link — Page Initialisation (Master Bootstrap)
 * ============================================================================
 *
 * This file is included by every entry point (index.php) AFTER the component's
 * auth_creds.php has been loaded. It bootstraps the entire application:
 *
 *   1. Direct access guard
 *   2. Define path constants
 *   3. Environment detection (alpha/beta/local/production)
 *   4. Debug mode detection
 *   5. Load all _functions/*.php in dependency order
 *   6. Register error/exception/shutdown handlers
 *   7. Start session (secure config)
 *   8. Load settings cache (Default + System)
 *   9. Detect and set locale
 *  10. Record start time/memory for debug panel
 *
 * Dependencies: auth_creds.php constants must be defined before including this file.
 *               Component constants (G2ML_COMPONENT, etc.) must be defined before including.
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 2 (updated Phase 4, Phase 6)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// ⏱️ Step 10 (early): Record start time and memory for debug panel
// ============================================================================
// We record these at the very top so the debug panel shows accurate totals.
// ============================================================================
define('G2ML_START_TIME', microtime(true));
define('G2ML_START_MEMORY', memory_get_usage());

// ============================================================================
// 📁 Step 2: Define Path Constants
// ============================================================================
// All paths are derived from the web/ directory structure.
// These constants are used throughout the application for includes and file access.
//
// 📖 Reference: https://www.php.net/manual/en/function.dirname.php
// ============================================================================

// G2ML_ROOT is the web/ directory (parent of _functions, _includes, _auth_keys, etc.)
// The entry points are at web/{Component}/public_html/index.php
// So web/ is 3 levels up from public_html: public_html → Component → web
if (!defined('G2ML_ROOT'))
{
    // Component index.php should define this, but calculate as fallback
    define('G2ML_ROOT', dirname(__DIR__));
}

// Shared directories
define('G2ML_FUNCTIONS', G2ML_ROOT . DIRECTORY_SEPARATOR . '_functions');
define('G2ML_INCLUDES',  G2ML_ROOT . DIRECTORY_SEPARATOR . '_includes');
define('G2ML_LIBRARIES', G2ML_ROOT . DIRECTORY_SEPARATOR . '_libraries');
define('G2ML_AUTH_KEYS', G2ML_ROOT . DIRECTORY_SEPARATOR . '_auth_keys');
define('G2ML_UPLOADS',   G2ML_ROOT . DIRECTORY_SEPARATOR . '_uploads');
define('G2ML_SQL',       G2ML_ROOT . DIRECTORY_SEPARATOR . '_sql');

// ============================================================================
// 🌍 Step 3: Environment Detection
// ============================================================================
// Determines the running environment from the hostname.
// This affects debug mode, error display, and feature availability.
//
// 📖 Reference: https://www.php.net/manual/en/reserved.variables.server.php
// ============================================================================

$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (preg_match('/^alpha\./i', $hostname))
{
    define('G2ML_ENVIRONMENT', 'alpha');
}
elseif (preg_match('/^beta\./i', $hostname))
{
    define('G2ML_ENVIRONMENT', 'beta');
}
elseif (in_array($hostname, ['localhost', '127.0.0.1', '::1'], true) || preg_match('/\.local$/', $hostname))
{
    define('G2ML_ENVIRONMENT', 'local');
}
else
{
    define('G2ML_ENVIRONMENT', 'production');
}

// ============================================================================
// 🐛 Step 4: Debug Mode Detection
// ============================================================================
// Debug mode is enabled if:
//   - Environment is alpha, beta, or local (always enabled)
//   - ?debug=true in URL AND environment is production AND user is admin (later)
//
// In production, debug mode requires authentication (Phase 5).
// For now, only alpha/beta/local get debug mode.
// ============================================================================

$isDebugEnvironment = in_array(G2ML_ENVIRONMENT, ['alpha', 'beta', 'local'], true);

// In production, debug mode requires GlobalAdmin authentication.
// Unauthenticated ?debug=true is ignored to prevent information leakage.
$isDebugRequested = false;
if (isset($_GET['debug']) && $_GET['debug'] === 'true')
{
    if ($isDebugEnvironment)
    {
        $isDebugRequested = true;
    }
    elseif (function_exists('isAuthenticated') && isAuthenticated()
            && function_exists('hasMinimumRole') && hasMinimumRole($_SESSION['user_role'] ?? 'Anonymous', 'GlobalAdmin'))
    {
        $isDebugRequested = true;
    }
}

define('G2ML_DEBUG', $isDebugEnvironment || $isDebugRequested);

// Configure PHP error display based on environment
if (G2ML_DEBUG)
{
    // 📖 Reference: https://www.php.net/manual/en/function.ini-set.php
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
else
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// ============================================================================
// 📦 Step 5: Load Function Files (Dependency Order)
// ============================================================================
// Layer 1 (zero dependencies) → Layer 2 (depends on Layer 1) → Layer 3
// (depends on Layers 1+2)
//
// 📖 Reference: https://www.php.net/manual/en/function.require-once.php
// ============================================================================

// Layer 1 — Zero dependencies (depend only on auth_creds.php constants)
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'security.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'html_sanitiser.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'db_connect.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'db_query.php';

// Layer 2 — Core services (depend on Layer 1)
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'error_handler.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'settings.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'api_response.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'api_auth.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'api_ratelimit.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'dnt.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'adult_content.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'geolocation.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'activity_logger.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'analytics.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'i18n.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'router.php';

// Layer 3 — Authentication & Email (depend on Layers 1+2)
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'email.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'session.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'auth.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'org.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'account_types.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'entitlements.php';

// Layer 4 — Compliance, Privacy & Security Response (depend on Layers 1+2+3)
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'cookie_consent.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'data_rights.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'breach_response.php';
require_once G2ML_FUNCTIONS . DIRECTORY_SEPARATOR . 'linkspage_manage.php';

// ============================================================================
// ⚠️ Step 6: Register Error/Exception/Shutdown Handlers
// ============================================================================
// Must be registered AFTER loading error_handler.php but BEFORE any application
// code runs, so all errors are caught.
//
// 📖 Reference: https://www.php.net/manual/en/function.set-error-handler.php
// ============================================================================

set_error_handler('g2ml_errorHandler');
set_exception_handler('g2ml_exceptionHandler');
register_shutdown_function('g2ml_shutdownHandler');

// ============================================================================
// 🔒 Step 7: Start Session
// ============================================================================
// Secure session configuration for all components.
//
// 📖 Reference: https://www.php.net/manual/en/session.configuration.php
// ============================================================================

if (session_status() === PHP_SESSION_NONE)
{
    // Session name unique to the application (prevents conflicts on shared hosts)
    session_name('G2ML_SESSION');

    // Configure session security
    // Domain is set to .go2my.link in production for cross-subdomain sharing
    // (go2my.link ↔ admin.go2my.link). In local/dev, left empty.
    // 📖 Reference: https://www.php.net/manual/en/function.session-set-cookie-params.php
    if ((G2ML_ENVIRONMENT === 'production')) {
        $sessionDomain = '.go2my.link';
    } else {
        $sessionDomain = '';
    }

    // Enforce strict session mode regardless of SAPI (mod_php, FPM, CGI)
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,                                   // Session cookie (expires when browser closes)
        'path'     => '/',
        'domain'   => $sessionDomain,                      // Cross-subdomain in production
        'secure'   => (G2ML_ENVIRONMENT === 'production'), // HTTPS only in production
        'httponly'  => true,                                // Not accessible via JavaScript
        'samesite' => 'Lax',                               // CSRF protection
    ]);

    session_start();

    // Regenerate session ID periodically to prevent session fixation
    // 📖 Reference: https://www.php.net/manual/en/function.session-regenerate-id.php
    if (!isset($_SESSION['_g2ml_session_created']))
    {
        $_SESSION['_g2ml_session_created'] = time();
    }
    elseif (time() - $_SESSION['_g2ml_session_created'] > 1800) // 30 minutes
    {
        session_regenerate_id(true);
        $_SESSION['_g2ml_session_created'] = time();
    }
}

// Validate and refresh authenticated sessions against the database
// 📖 Reference: session.php → validateUserSession(), refreshUserSession()
if (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] > 0)
{
    if (function_exists('validateUserSession') && !validateUserSession())
    {
        // DB session is invalid/expired — clear PHP session user data
        unset($_SESSION['user_uid'], $_SESSION['user_email'], $_SESSION['user_display_name']);
        unset($_SESSION['user_first_name'], $_SESSION['user_last_name'], $_SESSION['user_role']);
        unset($_SESSION['user_org_handle'], $_SESSION['user_avatar'], $_SESSION['user_timezone']);
        unset($_SESSION['email_verified'], $_SESSION['session_token']);
    }
    elseif (function_exists('refreshUserSession'))
    {
        refreshUserSession();
    }
}

// Probabilistic session cleanup (1% of requests)
// 📖 Reference: session.php → cleanExpiredSessions()
if (function_exists('cleanExpiredSessions') && mt_rand(1, 100) === 1)
{
    cleanExpiredSessions();
}

// ============================================================================
// ⚙️ Step 8: Load Settings Cache
// ============================================================================
// Loads Default and System scope settings into memory.
// Org/User settings are loaded lazily on first getSetting() call.
// ============================================================================

loadSettingsCache();

// ============================================================================
// 🌍 Step 9: Detect and Set Locale
// ============================================================================
// Resolves the user's preferred locale from URL, session, cookie,
// Accept-Language header, or default setting.
// ============================================================================

detectLocale();

// ============================================================================
// 📋 Step 10 (continued): Debug Info Collection
// ============================================================================
// Provide a function that the debug panel (in footer.php) can call to get
// debug information gathered during the request.
// ============================================================================

/**
 * Get debug information for the current request.
 *
 * @return array  Debug data including timing, memory, queries, settings, etc.
 */
function g2ml_getDebugInfo(): array
{
    if (defined('G2ML_COMPONENT')) {
        $debugComponent = G2ML_COMPONENT;
    } else {
        $debugComponent = 'unknown';
    }
    if (defined('G2ML_COMPONENT_NAME')) {
        $debugComponentName = G2ML_COMPONENT_NAME;
    } else {
        $debugComponentName = 'unknown';
    }

    return [
        'environment'    => G2ML_ENVIRONMENT,
        'component'      => $debugComponent,
        'componentName'  => $debugComponentName,
        'locale'         => getLocale(),
        'textDirection'  => getTextDirection(),
        'phpVersion'     => PHP_VERSION,
        'executionTime'  => round((microtime(true) - G2ML_START_TIME) * 1000, 2) . ' ms',
        'peakMemory'     => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
        'queryCount'     => count($GLOBALS['_g2ml_query_log'] ?? []),
        'queries'        => $GLOBALS['_g2ml_query_log'] ?? [],
        'sessionActive'  => (session_status() === PHP_SESSION_ACTIVE),
        'requestURI'     => $_SERVER['REQUEST_URI'] ?? '',
        'requestMethod'  => $_SERVER['REQUEST_METHOD'] ?? '',
    ];
}
