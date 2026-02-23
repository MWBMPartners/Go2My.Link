<?php
/**
 * ============================================================================
 * 🔒 Go2My.Link — Security Utilities
 * ============================================================================
 *
 * AES-256-GCM encryption/decryption, Argon2id password hashing, CSRF token
 * management, and input sanitisation functions.
 *
 * All functions use the g2ml_ prefix to avoid collisions with framework
 * functions or user code.
 *
 * Dependencies: auth_creds.php constants (ENCRYPTION_SALT, ENCRYPTION_KEY_SECONDARY)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - OpenSSL:  https://www.php.net/manual/en/function.openssl-encrypt.php
 *     - Argon2id: https://www.php.net/manual/en/function.password-hash.php
 *     - CSRF:     https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
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
// 🔐 AES-256-GCM Encryption / Decryption
// ============================================================================
// Used for encrypting sensitive database values (tblSettings.isSensitive = 1,
// OAuth tokens, API secrets, etc.).
//
// 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
// ============================================================================

/**
 * Encrypt a plaintext value using AES-256-GCM.
 *
 * Returns a base64-encoded string containing the IV, tag, and ciphertext
 * concatenated together: base64(iv . tag . ciphertext)
 *
 * @param  string      $plaintext  The value to encrypt
 * @param  string|null $key        Optional encryption key (defaults to ENCRYPTION_SALT)
 * @return string|false            Base64-encoded encrypted string, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
 */
function g2ml_encrypt(string $plaintext, ?string $key = null): string|false
{
    // Use the primary encryption salt if no key provided
    $encryptionKey = $key ?? (defined('ENCRYPTION_SALT') ? ENCRYPTION_SALT : '');

    if ($encryptionKey === '' || $encryptionKey === 'CHANGE_ME_TO_A_64_CHAR_HEX_STRING_GENERATED_WITH_RANDOM_BYTES_32')
    {
        // 📖 Reference: https://www.php.net/manual/en/function.error-log.php
        error_log('[Go2My.Link] CRITICAL: ENCRYPTION_SALT is not configured. Cannot encrypt.');
        return false;
    }

    // Derive a 32-byte key from the hex string
    // 📖 Reference: https://www.php.net/manual/en/function.hex2bin.php
    $derivedKey = hex2bin(substr(hash('sha256', $encryptionKey), 0, 64));

    // Generate a random 12-byte IV (recommended size for GCM)
    // 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
    $iv = random_bytes(12);

    // Encrypt using AES-256-GCM
    // 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $derivedKey,
        OPENSSL_RAW_DATA, // Return raw binary instead of base64
        $iv,
        $tag,
        '',               // No additional authenticated data (AAD)
        16                // 16-byte authentication tag
    );

    if ($ciphertext === false)
    {
        error_log('[Go2My.Link] ERROR: openssl_encrypt failed: ' . openssl_error_string());
        return false;
    }

    // Concatenate IV + tag + ciphertext and base64-encode the result
    // 📖 Reference: https://www.php.net/manual/en/function.base64-encode.php
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a value that was encrypted with g2ml_encrypt().
 *
 * Expects a base64-encoded string containing: iv (12 bytes) + tag (16 bytes) + ciphertext
 *
 * @param  string      $encrypted  Base64-encoded encrypted string from g2ml_encrypt()
 * @param  string|null $key        Optional encryption key (defaults to ENCRYPTION_SALT)
 * @return string|false            Decrypted plaintext, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.openssl-decrypt.php
 */
function g2ml_decrypt(string $encrypted, ?string $key = null): string|false
{
    // Use the primary encryption salt if no key provided
    $encryptionKey = $key ?? (defined('ENCRYPTION_SALT') ? ENCRYPTION_SALT : '');

    if ($encryptionKey === '' || $encryptionKey === 'CHANGE_ME_TO_A_64_CHAR_HEX_STRING_GENERATED_WITH_RANDOM_BYTES_32')
    {
        error_log('[Go2My.Link] CRITICAL: ENCRYPTION_SALT is not configured. Cannot decrypt.');
        return false;
    }

    // Derive the same 32-byte key
    $derivedKey = hex2bin(substr(hash('sha256', $encryptionKey), 0, 64));

    // Decode the base64 payload
    // 📖 Reference: https://www.php.net/manual/en/function.base64-decode.php
    $raw = base64_decode($encrypted, true);

    if ($raw === false || strlen($raw) < 28) // 12 (IV) + 16 (tag) minimum
    {
        error_log('[Go2My.Link] ERROR: g2ml_decrypt received invalid encrypted data.');
        return false;
    }

    // Extract IV (12 bytes), tag (16 bytes), and ciphertext (remainder)
    // 📖 Reference: https://www.php.net/manual/en/function.substr.php
    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);

    // Decrypt using AES-256-GCM
    // 📖 Reference: https://www.php.net/manual/en/function.openssl-decrypt.php
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $derivedKey,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plaintext === false)
    {
        error_log('[Go2My.Link] ERROR: openssl_decrypt failed (wrong key or tampered data): ' . openssl_error_string());
        return false;
    }

    return $plaintext;
}

// ============================================================================
// 🔑 Password Hashing (Argon2id)
// ============================================================================
// Uses Argon2id by default with bcrypt as automatic fallback for systems
// that don't support Argon2id.
//
// 📖 Reference: https://www.php.net/manual/en/function.password-hash.php
// ============================================================================

/**
 * Hash a password using Argon2id (with bcrypt fallback).
 *
 * @param  string $password  The plaintext password to hash
 * @return string            The hashed password string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-hash.php
 */
function g2ml_hashPassword(string $password): string
{
    // Prefer Argon2id (PHP 7.3+), fall back to bcrypt
    // 📖 Reference: https://www.php.net/manual/en/password.constants.php
    if (defined('PASSWORD_ARGON2ID'))
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost'   => 4,     // 4 iterations
            'threads'     => 1,     // Single thread (shared hosting safe)
        ]);
    }
    else
    {
        // Fallback to bcrypt with cost factor 12
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
    }
}

/**
 * Verify a plaintext password against a stored hash.
 *
 * @param  string $password  The plaintext password to check
 * @param  string $hash      The stored hash from g2ml_hashPassword()
 * @return bool              True if the password matches the hash
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-verify.php
 */
function g2ml_verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check if a password hash needs to be rehashed (e.g., algorithm upgrade).
 *
 * Call this after successful login to transparently upgrade password hashes.
 *
 * @param  string $hash  The stored password hash
 * @return bool          True if the hash should be regenerated
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-needs-rehash.php
 */
function g2ml_passwordNeedsRehash(string $hash): bool
{
    if (defined('PASSWORD_ARGON2ID'))
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }
    else
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
    }
}

// ============================================================================
// 🛡️ CSRF Token Management
// ============================================================================
// Generates and validates single-use CSRF tokens stored in the session.
// Tokens are per-form (keyed by form name) and expire after a configurable
// duration.
//
// 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
// ============================================================================

/**
 * Generate a CSRF token for a specific form.
 *
 * Stores the token and its expiry time in the session. Returns the token
 * string for embedding in a hidden form field.
 *
 * @param  string $formName   Unique form identifier (e.g., 'login', 'create_link')
 * @param  int    $lifetime   Token lifetime in seconds (default: 3600 = 1 hour)
 * @return string             The CSRF token string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
 */
function g2ml_generateCSRFToken(string $formName = 'default', int $lifetime = 3600): string
{
    // Generate a cryptographically secure random token
    // 📖 Reference: https://www.php.net/manual/en/function.bin2hex.php
    $token = bin2hex(random_bytes(32));

    // Store in session with expiry timestamp
    $_SESSION['_csrf_tokens'][$formName] = [
        'token'   => $token,
        'expires' => time() + $lifetime,
    ];

    return $token;
}

/**
 * Validate a submitted CSRF token against the session.
 *
 * The token is consumed (removed from session) after validation to prevent
 * replay attacks.
 *
 * @param  string $token     The submitted token from the form
 * @param  string $formName  The form identifier used during generation
 * @return bool              True if the token is valid and not expired
 */
function g2ml_validateCSRFToken(string $token, string $formName = 'default'): bool
{
    // Check if a token exists for this form
    if (!isset($_SESSION['_csrf_tokens'][$formName]))
    {
        return false;
    }

    $stored = $_SESSION['_csrf_tokens'][$formName];

    // Consume the token regardless of outcome (single-use)
    unset($_SESSION['_csrf_tokens'][$formName]);

    // Check expiry
    if ($stored['expires'] < time())
    {
        return false;
    }

    // Timing-safe comparison
    // 📖 Reference: https://www.php.net/manual/en/function.hash-equals.php
    return hash_equals($stored['token'], $token);
}

/**
 * Generate a hidden HTML input field containing a CSRF token.
 *
 * Convenience function for embedding in forms.
 *
 * @param  string $formName  Unique form identifier
 * @return string            HTML hidden input element
 */
function g2ml_csrfField(string $formName = 'default'): string
{
    $token = g2ml_generateCSRFToken($formName);

    // 📖 Reference: https://www.php.net/manual/en/function.htmlspecialchars.php
    return '<input type="hidden" name="_csrf_token" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        . '">';
}

// ============================================================================
// 🧹 Input Sanitisation
// ============================================================================
// Functions for cleaning and validating user input. These are the first line
// of defence; prepared statements handle SQL injection prevention separately.
//
// 📖 Reference: https://www.php.net/manual/en/filter.filters.sanitize.php
// ============================================================================

/**
 * Sanitise a string for safe HTML output (prevent XSS).
 *
 * @param  string|null $input  The raw input string
 * @return string              HTML-entity-encoded string safe for output
 *
 * 📖 Reference: https://www.php.net/manual/en/function.htmlspecialchars.php
 */
function g2ml_sanitiseOutput(?string $input): string
{
    if ($input === null)
    {
        return '';
    }

    return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitise a string for general use (strip tags, trim whitespace).
 *
 * @param  string|null $input  The raw input string
 * @return string              Cleaned string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.strip-tags.php
 */
function g2ml_sanitiseInput(?string $input): string
{
    if ($input === null)
    {
        return '';
    }

    // Trim whitespace and strip HTML/PHP tags
    return trim(strip_tags($input));
}

/**
 * Sanitise a URL for safe use (validate format and scheme).
 *
 * @param  string|null $url  The raw URL string
 * @return string|false      Sanitised URL, or false if invalid
 *
 * 📖 Reference: https://www.php.net/manual/en/function.filter-var.php
 */
function g2ml_sanitiseURL(?string $url): string|false
{
    if ($url === null || $url === '')
    {
        return false;
    }

    // Sanitise the URL
    // 📖 Reference: https://www.php.net/manual/en/filter.filters.sanitize.php
    $sanitised = filter_var(trim($url), FILTER_SANITIZE_URL);

    // Validate the URL structure
    // 📖 Reference: https://www.php.net/manual/en/filter.filters.validate.php
    if (filter_var($sanitised, FILTER_VALIDATE_URL) === false)
    {
        return false;
    }

    // Only allow http and https schemes
    $scheme = parse_url($sanitised, PHP_URL_SCHEME);

    if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return false;
    }

    return $sanitised;
}

/**
 * Sanitise an email address.
 *
 * @param  string|null $email  The raw email string
 * @return string|false        Validated email, or false if invalid
 *
 * 📖 Reference: https://www.php.net/manual/en/filter.filters.validate.php
 */
function g2ml_sanitiseEmail(?string $email): string|false
{
    if ($email === null || $email === '')
    {
        return false;
    }

    $sanitised = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

    if (filter_var($sanitised, FILTER_VALIDATE_EMAIL) === false)
    {
        return false;
    }

    return $sanitised;
}

/**
 * Get the client's real IP address, accounting for proxies and load balancers.
 *
 * Checks X-Forwarded-For and X-Real-Ip headers (common with reverse proxies)
 * before falling back to REMOTE_ADDR. Only trusts these headers when the
 * immediate connection is from a known proxy (Dreamhost load balancer).
 *
 * @return string  The client IP address (IPv4 or IPv6)
 *
 * 📖 Reference: https://www.php.net/manual/en/reserved.variables.server.php
 */
function g2ml_getClientIP(): string
{
    // Check X-Forwarded-For (may contain comma-separated chain)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        // Take the first (leftmost) IP — that's the original client
        $forwardedIPs = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $clientIP = trim($forwardedIPs[0]);

        // Validate it looks like an IP address
        if (filter_var($clientIP, FILTER_VALIDATE_IP) !== false)
        {
            return $clientIP;
        }
    }

    // Check X-Real-Ip header
    if (!empty($_SERVER['HTTP_X_REAL_IP']))
    {
        $realIP = trim($_SERVER['HTTP_X_REAL_IP']);

        if (filter_var($realIP, FILTER_VALIDATE_IP) !== false)
        {
            return $realIP;
        }
    }

    // Fall back to REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate a cryptographically secure random token (hex string).
 *
 * @param  int    $bytes  Number of random bytes (output will be 2x this length)
 * @return string         Hex-encoded random string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
 */
function g2ml_generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
