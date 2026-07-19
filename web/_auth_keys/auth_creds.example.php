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
 * 🔐 Go2My.Link — Server-Wide Authentication Credentials (TEMPLATE)
 * ============================================================================
 *
 * This is the TRACKED reference template for web/_auth_keys/auth_creds.php.
 * It is committed to version control so the required constant set is
 * documented and reviewable; the REAL file (same directory, without the
 * ".example" segment in its filename) is gitignored and must never be
 * committed.
 *
 * This file stores sensitive database connection credentials and encryption
 * keys used across ALL THREE components of the Go2My.Link service
 * (Go2My.Link, G2My.Link, Lnks.page). Each component's own thin
 * "<Component>/_auth_keys/auth_creds.php" include requires this shared file
 * in — see web/Go2My.Link/_auth_keys/auth_creds.php,
 * web/G2My.Link/_auth_keys/auth_creds.php, and
 * web/Lnks.page/_auth_keys/auth_creds.php for the include pattern.
 *
 * ⚠️  HOW TO USE THIS TEMPLATE:
 *     1. Copy this file to web/_auth_keys/auth_creds.php (drop the
 *        ".example" segment from the filename).
 *     2. Replace every placeholder value below with the real value for
 *        your environment.
 *     3. Ensure the resulting web/_auth_keys/auth_creds.php is chmod 0600
 *        and lives OUTSIDE any web-accessible directory.
 *     4. Never commit the real file — it is excluded via .gitignore
 *        (the "auth_creds.php" pattern, with this template re-included via
 *        the "!auth_creds.example.php" negation).
 *
 *     In production, the web installer (web/Go2My.Link/public_html/install/
 *     index.php) generates and writes the real auth_creds.php for you —
 *     including generating ENCRYPTION_SALT / ENCRYPTION_KEY_SECONDARY and
 *     setting 0600 permissions — so manual copying of this template is only
 *     needed for local development or manual/non-installer setups.
 *
 * ⚠️  SECURITY NOTICE:
 *     - This file MUST be stored OUTSIDE of any web-accessible directory.
 *     - The real auth_creds.php MUST NOT be committed to version control
 *       (excluded via .gitignore).
 *     - If accessed directly via a browser, it will redirect to the main
 *       site.
 *     - This template file contains NO real secrets — every value below is
 *       a safe placeholder.
 *
 * 📖 Reference:
 *     - PHP Constants: https://www.php.net/manual/en/language.constants.php
 *     - MySQLi: https://www.php.net/manual/en/book.mysqli.php
 *     - OpenSSL: https://www.php.net/manual/en/book.openssl.php
 *
 * @package    Go2My.Link
 * @subpackage Configuration
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.1.0
 * @since      Phase 0
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// If this file is accessed directly via a web browser (rather than being
// included/required by another PHP script), redirect to the main website.
// This prevents exposure of credentials if the file is accidentally placed
// in a web-accessible location.
//
// 📖 Reference: https://www.php.net/manual/en/reserved.variables.server.php
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    // 🔀 Redirect to main website and terminate execution immediately
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🗄️ Database Connection Credentials
// ============================================================================
// These constants are used by web/_functions/db_connect.php to establish
// a MySQLi connection to the backend MySQL database.
//
// 📖 Reference: https://www.php.net/manual/en/mysqli.construct.php
// ============================================================================

/**
 * Database host address.
 * On Dreamhost shared hosting, this is typically mysql.yourdomain.com
 *
 * @var string
 */
if (!defined('DB_HOST'))
{
    define('DB_HOST', 'localhost');
}

/**
 * Database username for MySQLi connection.
 *
 * @var string
 */
if (!defined('DB_USER'))
{
    define('DB_USER', 'your_db_username');
}

/**
 * Database password for MySQLi connection.
 *
 * @var string
 */
if (!defined('DB_PASS'))
{
    define('DB_PASS', 'your_db_password');
}

/**
 * Database name.
 * The new Go2My.Link database (InnoDB, utf8mb4).
 *
 * @var string
 */
if (!defined('DB_NAME'))
{
    define('DB_NAME', 'mwtools_Go2MyLink');
}

/**
 * Database character set.
 * Using utf8mb4 for full Unicode support (including emojis).
 *
 * 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-utf8mb4.html
 *
 * @var string
 */
if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * Database port.
 * Default MySQL port is 3306. Only change if your host uses a non-standard port.
 *
 * @var int
 */
if (!defined('DB_PORT'))
{
    define('DB_PORT', 3306);
}

// ============================================================================
// 🔑 Encryption Salt & Keys
// ============================================================================
// Used by web/_functions/security.php for AES-256-GCM encryption/decryption
// of sensitive values stored in the database (settings marked as isSensitive,
// API keys, OAuth tokens, etc.).
//
// ⚠️  IMPORTANT: Generate a strong, unique random string for production use.
//     You can generate one with: php -r "echo bin2hex(random_bytes(32));"
//
// 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
// ============================================================================

/**
 * Primary encryption salt used for AES-256-GCM encryption of sensitive
 * database values. Must be exactly 64 hexadecimal characters (32 bytes).
 *
 * ⚠️  NEVER change this value after initial deployment, or all encrypted
 *     data in the database will become unrecoverable.
 *
 * @var string
 */
if (!defined('ENCRYPTION_SALT'))
{
    define('ENCRYPTION_SALT', 'CHANGE_ME_TO_A_64_CHAR_HEX_STRING_GENERATED_WITH_RANDOM_BYTES_32');
}

/**
 * Secondary encryption key used for additional layers of encryption
 * (e.g., session tokens, CSRF tokens).
 *
 * @var string
 */
if (!defined('ENCRYPTION_KEY_SECONDARY'))
{
    define('ENCRYPTION_KEY_SECONDARY', 'CHANGE_ME_TO_ANOTHER_64_CHAR_HEX_STRING');
}

// ============================================================================
// 🔐 Third-Party Service Credentials (Fallback)
// ============================================================================
// Primary storage for third-party credentials is in the database
// (tblSettings with isSensitive = 1, encrypted). These fallback constants
// are used ONLY if the database is unreachable during initial setup or
// critical failures.
//
// Leave empty ('') if not using the fallback mechanism.
// ============================================================================

/**
 * Microsoft 365 / Azure AD credentials (fallback).
 * Primary storage: tblSettings (settingsID: 'ms365ClientID', 'ms365ClientSecret')
 */
if (!defined('MS365_CLIENT_ID'))
{
    define('MS365_CLIENT_ID', '');
}

if (!defined('MS365_CLIENT_SECRET'))
{
    define('MS365_CLIENT_SECRET', '');
}

/**
 * Google Workspace / OAuth credentials (fallback).
 * Primary storage: tblSettings (settingsID: 'googleClientID', 'googleClientSecret')
 */
if (!defined('GOOGLE_CLIENT_ID'))
{
    define('GOOGLE_CLIENT_ID', '');
}

if (!defined('GOOGLE_CLIENT_SECRET'))
{
    define('GOOGLE_CLIENT_SECRET', '');
}

/**
 * reCAPTCHA credentials (fallback).
 * Primary storage: tblSettings (settingsID: 'recaptchaSiteKey', 'recaptchaSecretKey')
 */
if (!defined('RECAPTCHA_SITE_KEY'))
{
    define('RECAPTCHA_SITE_KEY', '');
}

if (!defined('RECAPTCHA_SECRET_KEY'))
{
    define('RECAPTCHA_SECRET_KEY', '');
}

/**
 * CloudFlare Turnstile credentials (fallback).
 * Primary storage: tblSettings (settingsID: 'turnstileSiteKey', 'turnstileSecretKey')
 */
if (!defined('TURNSTILE_SITE_KEY'))
{
    define('TURNSTILE_SITE_KEY', '');
}

if (!defined('TURNSTILE_SECRET_KEY'))
{
    define('TURNSTILE_SECRET_KEY', '');
}
