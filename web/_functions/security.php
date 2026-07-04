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
    if ($key !== null) {
        $encryptionKey = $key;
    } elseif (defined('ENCRYPTION_SALT')) {
        $encryptionKey = ENCRYPTION_SALT;
    } else {
        $encryptionKey = '';
    }

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
    if ($key !== null) {
        $encryptionKey = $key;
    } elseif (defined('ENCRYPTION_SALT')) {
        $encryptionKey = ENCRYPTION_SALT;
    } else {
        $encryptionKey = '';
    }

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

// ============================================================================
// 🛡️ Destination Host Safety / Anti-SSRF
// ============================================================================
// Guards every server-side fetch of, and every stored, user-supplied
// destination URL against Server-Side Request Forgery (SSRF). The policy
// blocks loopback, link-local (incl. the 169.254.169.254 cloud-metadata
// endpoint), unspecified/reserved, and — by default — RFC1918 / IPv6-ULA
// private ranges, after resolving the host's A/AAAA records. URLs carrying
// userinfo (user:pass@) are rejected outright, and only http/https schemes
// are considered.
//
// Used by:
//   - Component B  validateDestination()  (F-004 / #100)
//   - Component A  createShortURL()        (F-005 / #100)
//
// 📖 Reference: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
// 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
// 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
// ============================================================================

/**
 * Determine whether an IP address is private, reserved, loopback, link-local,
 * or otherwise unsafe as an outbound destination.
 *
 * Always-unsafe (never overridable): loopback (127.0.0.0/8, ::1), link-local
 * incl. the 169.254.169.254 cloud-metadata endpoint (169.254.0.0/16,
 * fe80::/10), the unspecified address (0.0.0.0, ::), IPv4-mapped/compat IPv6,
 * and assorted reserved ranges.
 *
 * RFC1918 private (10/8, 172.16/12, 192.168/16) and IPv6 Unique-Local
 * (fc00::/7) are reported as private here; the CALLER decides whether to allow
 * them (see g2ml_destinationHostIsAllowed and redirect.allow_private_destinations).
 *
 * @param  string $ip  The IP literal to classify (IPv4 or IPv6).
 * @return bool        True when the IP must NOT be used as an outbound target.
 *
 * 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
 */
function g2ml_isPrivateOrReservedIp(string $ip): bool
{
    $ip = trim($ip);

    if ($ip === '')
    {
        return true;
    }

    $packed = @inet_pton($ip);

    if ($packed === false)
    {
        // Not a parseable IP literal — treat as unsafe.
        return true;
    }

    // ------------------------------------------------------------------------
    // IPv4 (4-byte packed form).
    // ------------------------------------------------------------------------
    if (strlen($packed) === 4)
    {
        $alwaysBlockedV4 = [
            '0.0.0.0/8',        // "this" network / unspecified
            '127.0.0.0/8',      // loopback
            '169.254.0.0/16',   // link-local incl. 169.254.169.254 metadata
            '100.64.0.0/10',    // carrier-grade NAT (RFC 6598)
            '192.0.0.0/24',     // IETF protocol assignments
            '192.0.2.0/24',     // TEST-NET-1 (documentation)
            '198.18.0.0/15',    // benchmarking (RFC 2544)
            '198.51.100.0/24',  // TEST-NET-2 (documentation)
            '203.0.113.0/24',   // TEST-NET-3 (documentation)
            '224.0.0.0/4',      // multicast
            '240.0.0.0/4',      // reserved (incl. 255.255.255.255 broadcast)
        ];

        foreach ($alwaysBlockedV4 as $range)
        {
            if (g2ml_ipInRange($ip, $range) === true)
            {
                return true;
            }
        }

        $privateV4 = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ];

        foreach ($privateV4 as $range)
        {
            if (g2ml_ipInRange($ip, $range) === true)
            {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------------
    // IPv6 (16-byte packed form).
    // ------------------------------------------------------------------------
    // An IPv4-mapped (::ffff:0:0/96) or IPv4-compatible address can be used to
    // smuggle a private IPv4 target past an IPv6-only check, so unwrap it and
    // re-classify the embedded IPv4 value first.
    if (g2ml_ipInRange($ip, '::ffff:0:0/96') === true || g2ml_ipInRange($ip, '::/96') === true)
    {
        $embedded = inet_ntop(substr($packed, 12, 4));

        if ($embedded !== false)
        {
            return g2ml_isPrivateOrReservedIp($embedded);
        }

        return true;
    }

    $alwaysBlockedV6 = [
        '::/128',     // unspecified
        '::1/128',    // loopback
        'fe80::/10',  // link-local
        'ff00::/8',   // multicast
    ];

    foreach ($alwaysBlockedV6 as $range)
    {
        if (g2ml_ipInRange($ip, $range) === true)
        {
            return true;
        }
    }

    // Unique Local Addresses (private) — fc00::/7.
    if (g2ml_ipInRange($ip, 'fc00::/7') === true)
    {
        return true;
    }

    return false;
}

/**
 * Read the redirect.allow_private_destinations policy, FAILING CLOSED.
 *
 * Returns true only when settings are available AND the operator has
 * explicitly switched private destinations on. When the settings layer (and
 * therefore the database) is unavailable — for example security.php loaded in
 * isolation, or a DB outage — this returns false so private/internal hosts
 * stay blocked.
 *
 * @return bool  True when RFC1918/ULA private destinations are permitted.
 */
function g2ml_privateDestinationsAllowed(): bool
{
    // No settings layer loaded (e.g. unit tests, early bootstrap): fail closed.
    if (!function_exists('getSetting'))
    {
        return false;
    }

    // getSetting returns the supplied default when the DB/setting is absent.
    // The default of '0' keeps us closed unless the operator opts in.
    $value = getSetting('redirect.allow_private_destinations', '0');

    if ($value === true || $value === 1 || $value === '1')
    {
        return true;
    }

    return false;
}

/**
 * Decide whether a user-supplied destination URL is safe to fetch or store,
 * blocking SSRF against internal, loopback, link-local, and cloud-metadata
 * hosts.
 *
 * Policy:
 *   - The URL must be http or https; any other scheme is rejected.
 *   - The URL must NOT carry userinfo (user:pass@); such URLs are rejected
 *     because they are a classic SSRF/credential-leak smuggling vector.
 *   - The host is resolved: an IP literal is checked directly; a name is
 *     resolved to its A and AAAA records and EVERY resolved address is checked.
 *   - Loopback, link-local (incl. 169.254.169.254 metadata), unspecified, and
 *     reserved ranges are ALWAYS blocked.
 *   - RFC1918 private + IPv6 ULA are blocked BY DEFAULT, overridable via the
 *     redirect.allow_private_destinations setting (default off). The override
 *     fails closed when settings are unavailable.
 *   - Returns true only when the host resolves to at least one address and
 *     EVERY resolved address is allowed.
 *
 * @param  string $url  The destination URL to vet.
 * @return bool         True when the destination host is allowed.
 *
 * 📖 Reference: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
 * 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
 */
function g2ml_destinationHostIsAllowed(string $url): bool
{
    $url = trim($url);

    if ($url === '')
    {
        return false;
    }

    $parts = parse_url($url);

    if ($parts === false || !is_array($parts))
    {
        return false;
    }

    // Scheme must be http or https.
    if (!isset($parts['scheme']))
    {
        return false;
    }

    $scheme = strtolower($parts['scheme']);

    if ($scheme !== 'http' && $scheme !== 'https')
    {
        return false;
    }

    // Reject any userinfo component (user:pass@) — an SSRF smuggling vector.
    if (isset($parts['user']) || isset($parts['pass']))
    {
        return false;
    }

    if (!isset($parts['host']) || $parts['host'] === '')
    {
        return false;
    }

    $host = $parts['host'];

    // A bracketed IPv6 literal arrives as "[::1]" from some inputs; strip the
    // brackets so inet_pton can parse it.
    if (strlen($host) >= 2 && $host[0] === '[' && $host[strlen($host) - 1] === ']')
    {
        $host = substr($host, 1, -1);
    }

    $allowPrivate = g2ml_privateDestinationsAllowed();

    // ------------------------------------------------------------------------
    // Collect the candidate IP addresses for the host.
    // ------------------------------------------------------------------------
    $candidateIPs = [];

    // If the host is itself an IP literal, check it directly (no DNS).
    if (@inet_pton($host) !== false)
    {
        $candidateIPs[] = $host;
    }
    else
    {
        // Resolve A records (IPv4).
        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4))
        {
            foreach ($ipv4 as $address)
            {
                $candidateIPs[] = $address;
            }
        }

        // Resolve AAAA records (IPv6), when DNS lookups are available.
        if (function_exists('dns_get_record'))
        {
            $aaaa = @dns_get_record($host, DNS_AAAA);

            if (is_array($aaaa))
            {
                foreach ($aaaa as $record)
                {
                    if (isset($record['ipv6']) && $record['ipv6'] !== '')
                    {
                        $candidateIPs[] = $record['ipv6'];
                    }
                }
            }
        }
    }

    // A host that resolves to nothing is treated as not allowed (fail closed):
    // we cannot prove it is safe.
    if (count($candidateIPs) === 0)
    {
        return false;
    }

    // ------------------------------------------------------------------------
    // Every resolved address must be allowed under the active policy.
    // ------------------------------------------------------------------------
    foreach ($candidateIPs as $candidateIP)
    {
        if (g2ml_isPrivateOrReservedIp($candidateIP) === true)
        {
            // Private/ULA may be permitted by the override; the always-blocked
            // ranges are never permitted, so re-test those explicitly.
            if ($allowPrivate === true && g2ml_ipIsPrivateButOverridable($candidateIP) === true)
            {
                continue;
            }

            return false;
        }
    }

    return true;
}

/**
 * Whether an IP is in the RFC1918 / IPv6-ULA private set that the
 * redirect.allow_private_destinations override may unblock.
 *
 * The always-blocked ranges (loopback, link-local/metadata, unspecified,
 * reserved, multicast) are NOT included here, so the override can never
 * re-enable them.
 *
 * @param  string $ip  The IP literal to test.
 * @return bool        True when $ip is private-but-overridable.
 */
function g2ml_ipIsPrivateButOverridable(string $ip): bool
{
    $ip = trim($ip);

    if ($ip === '')
    {
        return false;
    }

    $packed = @inet_pton($ip);

    if ($packed === false)
    {
        return false;
    }

    // Unwrap an IPv4-mapped/compat IPv6 address to its embedded IPv4 value.
    if (strlen($packed) === 16)
    {
        if (g2ml_ipInRange($ip, '::ffff:0:0/96') === true || g2ml_ipInRange($ip, '::/96') === true)
        {
            $embedded = inet_ntop(substr($packed, 12, 4));

            if ($embedded !== false)
            {
                return g2ml_ipIsPrivateButOverridable($embedded);
            }

            return false;
        }
    }

    $overridable = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
    ];

    foreach ($overridable as $range)
    {
        if (g2ml_ipInRange($ip, $range) === true)
        {
            return true;
        }
    }

    return false;
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
 * Test whether an IP address falls inside a single IP or CIDR range.
 *
 * Supports an exact IPv4/IPv6 address (no slash) or a CIDR block
 * ("ip/prefix"). Comparison is done on the packed binary form so IPv4 and
 * IPv6 are both handled. Address families must match: an IPv4 address never
 * matches an IPv6 range and vice versa.
 *
 * @param  string $ip     The candidate IP address to test.
 * @param  string $range  A single IP ("203.0.113.9") or a CIDR ("10.0.0.0/8").
 * @return bool           True when $ip is the address or inside the range.
 *
 * 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
 */
function g2ml_ipInRange(string $ip, string $range): bool
{
    $ip    = trim($ip);
    $range = trim($range);

    if ($ip === '' || $range === '')
    {
        return false;
    }

    // No slash means an exact single-address comparison.
    if (strpos($range, '/') === false)
    {
        $packedIp    = @inet_pton($ip);
        $packedRange = @inet_pton($range);

        if ($packedIp === false || $packedRange === false)
        {
            return false;
        }

        return hash_equals($packedRange, $packedIp);
    }

    // CIDR form: "subnet/prefix".
    $parts = explode('/', $range, 2);
    $subnet = $parts[0];
    $prefixText = $parts[1];

    if ($prefixText === '' || !ctype_digit($prefixText))
    {
        return false;
    }

    $packedIp     = @inet_pton($ip);
    $packedSubnet = @inet_pton($subnet);

    if ($packedIp === false || $packedSubnet === false)
    {
        return false;
    }

    // Address families must match (same packed length: 4 for v4, 16 for v6).
    if (strlen($packedIp) !== strlen($packedSubnet))
    {
        return false;
    }

    $prefixBits = (int) $prefixText;
    $maxBits    = strlen($packedIp) * 8;

    if ($prefixBits < 0 || $prefixBits > $maxBits)
    {
        return false;
    }

    // A /0 matches everything within the family.
    if ($prefixBits === 0)
    {
        return true;
    }

    $fullBytes      = intdiv($prefixBits, 8);
    $remainingBits  = $prefixBits % 8;

    // Compare the whole bytes covered by the prefix.
    if ($fullBytes > 0)
    {
        if (hash_equals(substr($packedSubnet, 0, $fullBytes), substr($packedIp, 0, $fullBytes)) === false)
        {
            return false;
        }
    }

    // Compare the partial trailing byte, if the prefix is not byte-aligned.
    if ($remainingBits > 0)
    {
        $mask     = (~0 << (8 - $remainingBits)) & 0xFF;
        $ipByte   = ord($packedIp[$fullBytes]) & $mask;
        $netByte  = ord($packedSubnet[$fullBytes]) & $mask;

        if ($ipByte !== $netByte)
        {
            return false;
        }
    }

    return true;
}

/**
 * Determine whether the immediate peer (REMOTE_ADDR) is a configured trusted
 * proxy whose forwarded headers may be believed.
 *
 * Trust is opt-in via the OPTIONAL constant TRUSTED_PROXIES (a comma-separated
 * list of IPs and/or CIDR ranges, normally defined in the shared
 * auth_creds.php). When TRUSTED_PROXIES is UNDEFINED or empty, NO proxy is
 * trusted, which is the correct default for Dreamhost where the app is served
 * directly.
 *
 * @param  string $remoteAddr  The immediate peer address (REMOTE_ADDR).
 * @return bool                 True only when $remoteAddr is in the allowlist.
 */
function g2ml_isTrustedProxy(string $remoteAddr): bool
{
    if ($remoteAddr === '')
    {
        return false;
    }

    if (!defined('TRUSTED_PROXIES'))
    {
        return false;
    }

    $configuredValue = constant('TRUSTED_PROXIES');

    if (!is_string($configuredValue))
    {
        return false;
    }

    $configuredValue = trim($configuredValue);

    if ($configuredValue === '')
    {
        return false;
    }

    $entries = explode(',', $configuredValue);

    foreach ($entries as $entry)
    {
        $entry = trim($entry);

        if ($entry === '')
        {
            continue;
        }

        if (g2ml_ipInRange($remoteAddr, $entry) === true)
        {
            return true;
        }
    }

    return false;
}

/**
 * Get the client's real IP address.
 *
 * SECURITY (F-001 / #95): forwarded headers (X-Forwarded-For, X-Real-Ip) are
 * trivially spoofable by any client, so they are IGNORED BY DEFAULT. The
 * function returns REMOTE_ADDR — the address of the immediate peer — unless
 * that peer is an explicitly configured trusted proxy.
 *
 * Forwarded headers are honoured ONLY when REMOTE_ADDR is listed in the
 * OPTIONAL TRUSTED_PROXIES constant (see g2ml_isTrustedProxy). When trusted,
 * the RIGHT-most entry of X-Forwarded-For that is not itself a trusted proxy
 * is taken — this is the standard correct choice, because each trusted hop
 * appends the address it received the connection from, so the right-most
 * untrusted value is the real client. The chosen value is validated with
 * FILTER_VALIDATE_IP and the function falls back to REMOTE_ADDR if it is
 * missing or invalid.
 *
 * On Dreamhost (direct-served, no TRUSTED_PROXIES) this always returns
 * REMOTE_ADDR, so the activity log and the per-IP rate limits both see the
 * genuine peer address and cannot be poisoned or evaded by forged headers.
 *
 * @return string  The client IP address (IPv4 or IPv6)
 *
 * 📖 Reference: https://www.php.net/manual/en/reserved.variables.server.php
 */
function g2ml_getClientIP(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!is_string($remoteAddr))
    {
        $remoteAddr = '';
    }

    $remoteAddr = trim($remoteAddr);

    if ($remoteAddr === '')
    {
        $remoteAddr = '0.0.0.0';
    }

    // Default and only-safe path: trust nobody, use the immediate peer.
    if (g2ml_isTrustedProxy($remoteAddr) === false)
    {
        return $remoteAddr;
    }

    // REMOTE_ADDR is a configured trusted proxy: we MAY believe X-Forwarded-For.
    $forwardedHeader = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if (is_string($forwardedHeader) && trim($forwardedHeader) !== '')
    {
        $forwardedIPs = explode(',', $forwardedHeader);

        // Walk RIGHT to LEFT, skipping our own trusted hops, and take the
        // first (right-most) entry that is NOT a trusted proxy — that is the
        // genuine client as seen by the closest hop we trust.
        for ($index = count($forwardedIPs) - 1; $index >= 0; $index--)
        {
            $candidate = trim($forwardedIPs[$index]);

            if ($candidate === '')
            {
                continue;
            }

            if (g2ml_isTrustedProxy($candidate) === true)
            {
                // This hop is one of ours; keep walking left.
                continue;
            }

            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false)
            {
                return $candidate;
            }

            // First untrusted entry is malformed; do not trust anything left
            // of it. Fall through to REMOTE_ADDR.
            break;
        }
    }

    // No usable forwarded value: fall back to the trusted proxy's own address.
    return $remoteAddr;
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
