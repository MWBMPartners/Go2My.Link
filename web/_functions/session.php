<?php
/**
 * ============================================================================
 * 🔐 GoToMyLink — Session Management Functions
 * ============================================================================
 *
 * Database-backed session management. PHP sessions carry the user identity,
 * but a parallel token in tblUserSessions provides server-side validation,
 * multi-device tracking, and remote revocation.
 *
 * Every authenticated request validates the PHP session token against the DB.
 * Tokens are stored as SHA-256 hashes in the database; the plaintext token
 * lives only in $_SESSION.
 *
 * Dependencies: db_query.php (dbSelect, dbInsert, dbUpdate, dbDelete),
 *               security.php (g2ml_generateToken, g2ml_getClientIP),
 *               settings.php (getSetting),
 *               activity_logger.php (logActivity)
 *
 * @package    GoToMyLink
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.5.0
 * @since      Phase 4
 *
 * 📖 References:
 *     - tblUserSessions schema: web/_sql/schema/020_users.sql
 *     - Session security: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
 *     - PHP sessions: https://www.php.net/manual/en/book.session.php
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
// 🆕 Create User Session
// ============================================================================

/**
 * Create a new database-backed session for an authenticated user.
 *
 * Generates a cryptographic token, stores its SHA-256 hash in tblUserSessions
 * alongside device metadata, and stores the plaintext token in $_SESSION.
 *
 * @param  int          $userUID  The authenticated user's UID
 * @return string|false           The plaintext session token, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.hash.php
 */
function createUserSession(int $userUID): string|false
{
    // Generate a cryptographically secure token (64 hex chars)
    // 📖 Reference: security.php → g2ml_generateToken()
    $plainToken = g2ml_generateToken(32);

    // Store the hash in the database (leak-safe)
    // 📖 Reference: https://www.php.net/manual/en/function.hash.php
    $tokenHash = hash('sha256', $plainToken);

    // Gather request metadata
    $ipAddress = function_exists('g2ml_getClientIP') ? g2ml_getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceInfo = parseDeviceInfo($userAgent);

    // Get session lifetime from settings (default 24 hours)
    $lifetime = function_exists('getSetting')
        ? (int) getSetting('security.session_lifetime', 86400)
        : 86400;

    // Calculate expiry timestamp
    // 📖 Reference: https://www.php.net/manual/en/function.date.php
    $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

    // Truncate long user agent strings to match column limit
    if (strlen($userAgent) > 500)
    {
        $userAgent = substr($userAgent, 0, 500);
    }

    // Insert the session into tblUserSessions
    $sessionUID = dbInsert(
        "INSERT INTO tblUserSessions (userUID, sessionToken, ipAddress, userAgent, deviceInfo, expiresAt, isActive)
         VALUES (?, ?, ?, ?, ?, ?, 1)",
        'isssss',
        [$userUID, $tokenHash, $ipAddress, $userAgent, $deviceInfo, $expiresAt]
    );

    if ($sessionUID === false)
    {
        error_log('[GoToMyLink] ERROR: createUserSession — failed to insert session for userUID: ' . $userUID);
        return false;
    }

    // Store the plaintext token in the PHP session
    $_SESSION['session_token'] = $plainToken;

    return $plainToken;
}

// ============================================================================
// ✅ Validate User Session
// ============================================================================

/**
 * Validate the current PHP session token against the database.
 *
 * Hashes the plaintext token from $_SESSION, looks it up in tblUserSessions,
 * and checks that it's active and not expired.
 *
 * @return bool  True if the session is valid, false otherwise
 */
function validateUserSession(): bool
{
    // Check that a session token exists in the PHP session
    if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] === '')
    {
        return false;
    }

    // Hash the token for DB lookup
    $tokenHash = hash('sha256', $_SESSION['session_token']);

    // Look up the session in the database
    $session = dbSelectOne(
        "SELECT sessionUID, userUID, expiresAt, isActive
         FROM tblUserSessions
         WHERE sessionToken = ?
           AND isActive = 1
           AND expiresAt > NOW()
         LIMIT 1",
        's',
        [$tokenHash]
    );

    if ($session === null || $session === false)
    {
        // Session not found, expired, or deactivated — clear PHP session
        unset($_SESSION['session_token']);
        unset($_SESSION['user_uid']);
        return false;
    }

    return true;
}

// ============================================================================
// 🔄 Refresh User Session
// ============================================================================

/**
 * Update the last activity timestamp for the current session.
 *
 * Called on each authenticated request to keep the session alive and
 * track when the user was last active.
 *
 * @return bool  True if the session was refreshed successfully
 */
function refreshUserSession(): bool
{
    if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] === '')
    {
        return false;
    }

    $tokenHash = hash('sha256', $_SESSION['session_token']);

    $result = dbUpdate(
        "UPDATE tblUserSessions
         SET lastActivityAt = NOW()
         WHERE sessionToken = ?
           AND isActive = 1",
        's',
        [$tokenHash]
    );

    return ($result !== false && $result > 0);
}

// ============================================================================
// 🗑️ Destroy User Session
// ============================================================================

/**
 * Destroy the current session (both PHP and database).
 *
 * Marks the DB session as inactive, clears all PHP session data,
 * deletes the session cookie, and destroys the PHP session.
 *
 * @return void
 *
 * 📖 Reference: https://www.php.net/manual/en/function.session-destroy.php
 */
function destroyUserSession(): void
{
    // Deactivate the DB session
    if (isset($_SESSION['session_token']) && $_SESSION['session_token'] !== '')
    {
        $tokenHash = hash('sha256', $_SESSION['session_token']);

        dbUpdate(
            "UPDATE tblUserSessions
             SET isActive = 0
             WHERE sessionToken = ?",
            's',
            [$tokenHash]
        );
    }

    // Clear all session data
    // 📖 Reference: https://www.php.net/manual/en/function.session-unset.php
    $_SESSION = [];

    // Delete the session cookie
    // 📖 Reference: https://www.php.net/manual/en/function.setcookie.php
    if (ini_get('session.use_cookies'))
    {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly'  => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    // Destroy the PHP session
    // 📖 Reference: https://www.php.net/manual/en/function.session-destroy.php
    if (session_status() === PHP_SESSION_ACTIVE)
    {
        session_destroy();
    }
}

// ============================================================================
// 📋 List User Sessions
// ============================================================================

/**
 * List all active sessions for a user.
 *
 * Returns session metadata (device, IP, last activity) with a 'isCurrent' flag
 * indicating which session belongs to the current request.
 *
 * @param  int   $userUID  The user's UID
 * @return array           Array of session records, each with 'isCurrent' flag
 */
function listUserSessions(int $userUID): array
{
    $sessions = dbSelect(
        "SELECT sessionUID, sessionToken, ipAddress, userAgent, deviceInfo,
                lastActivityAt, expiresAt, createdAt
         FROM tblUserSessions
         WHERE userUID = ?
           AND isActive = 1
           AND expiresAt > NOW()
         ORDER BY lastActivityAt DESC",
        'i',
        [$userUID]
    );

    if ($sessions === false)
    {
        return [];
    }

    // Determine which session is the current one
    $currentTokenHash = '';

    if (isset($_SESSION['session_token']) && $_SESSION['session_token'] !== '')
    {
        $currentTokenHash = hash('sha256', $_SESSION['session_token']);
    }

    // Mask IPs and add 'isCurrent' flag
    foreach ($sessions as &$session)
    {
        // 📖 Check if this is the current session by comparing token hashes
        $session['isCurrent'] = ($session['sessionToken'] === $currentTokenHash);

        // Mask IP for privacy (show first 2 octets only for IPv4)
        $session['ipMasked'] = _g2ml_maskIP($session['ipAddress']);

        // Remove the raw token hash from the response (security)
        unset($session['sessionToken']);
    }
    unset($session); // Break reference

    return $sessions;
}

// ============================================================================
// ❌ Revoke a Single Session
// ============================================================================

/**
 * Revoke (deactivate) a specific session by its UID.
 *
 * Includes an ownership check — the session must belong to the specified user.
 *
 * @param  int  $sessionUID  The session UID to revoke
 * @param  int  $userUID     The user's UID (ownership check)
 * @return bool              True if the session was revoked
 */
function revokeSession(int $sessionUID, int $userUID): bool
{
    $result = dbUpdate(
        "UPDATE tblUserSessions
         SET isActive = 0
         WHERE sessionUID = ?
           AND userUID = ?
           AND isActive = 1",
        'ii',
        [$sessionUID, $userUID]
    );

    return ($result !== false && $result > 0);
}

// ============================================================================
// ❌ Revoke All Other Sessions
// ============================================================================

/**
 * Revoke all active sessions for a user except the current one.
 *
 * Used after password change to force re-authentication on all other devices.
 *
 * @param  int    $userUID       The user's UID
 * @param  string $currentToken  The current session's plaintext token (to keep active)
 * @return int                   Number of sessions revoked
 */
function revokeAllOtherSessions(int $userUID, string $currentToken): int
{
    $currentHash = hash('sha256', $currentToken);

    $result = dbUpdate(
        "UPDATE tblUserSessions
         SET isActive = 0
         WHERE userUID = ?
           AND sessionToken != ?
           AND isActive = 1",
        'is',
        [$userUID, $currentHash]
    );

    return ($result !== false) ? $result : 0;
}

// ============================================================================
// 🧹 Clean Expired Sessions
// ============================================================================

/**
 * Delete expired and old inactive sessions from the database.
 *
 * Removes:
 *   - Expired sessions (expiresAt < NOW())
 *   - Inactive sessions older than 30 days
 *
 * Called probabilistically (1% of requests) from page_init.php to avoid
 * running on every single request.
 *
 * @return int  Number of sessions cleaned
 */
function cleanExpiredSessions(): int
{
    $result = dbDelete(
        "DELETE FROM tblUserSessions
         WHERE expiresAt < NOW()
            OR (isActive = 0 AND lastActivityAt < DATE_SUB(NOW(), INTERVAL 30 DAY))",
        '',
        []
    );

    return ($result !== false) ? $result : 0;
}

// ============================================================================
// 🔍 Parse Device Info
// ============================================================================

/**
 * Extract a human-readable device description from a User-Agent string.
 *
 * Returns a short summary like "Chrome on Windows" or "Safari on macOS".
 * Used for session listing and new login alerts.
 *
 * @param  string $userAgent  The raw User-Agent header
 * @return string             Parsed device description
 */
function parseDeviceInfo(string $userAgent): string
{
    if ($userAgent === '')
    {
        return 'Unknown device';
    }

    // Detect browser
    // 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
    $browser = 'Unknown browser';

    if (preg_match('/Edg(?:e|A|iOS)?\//', $userAgent))
    {
        $browser = 'Edge';
    }
    elseif (preg_match('/OPR\//', $userAgent))
    {
        $browser = 'Opera';
    }
    elseif (preg_match('/SamsungBrowser\//', $userAgent))
    {
        $browser = 'Samsung Internet';
    }
    elseif (preg_match('/Vivaldi\//', $userAgent))
    {
        $browser = 'Vivaldi';
    }
    elseif (preg_match('/Firefox\//', $userAgent))
    {
        $browser = 'Firefox';
    }
    elseif (preg_match('/Chrome\//', $userAgent))
    {
        $browser = 'Chrome';
    }
    elseif (preg_match('/Safari\//', $userAgent) && preg_match('/Version\//', $userAgent))
    {
        $browser = 'Safari';
    }
    elseif (preg_match('/MSIE|Trident/', $userAgent))
    {
        $browser = 'Internet Explorer';
    }

    // Detect OS
    $os = 'Unknown OS';

    if (preg_match('/Windows/', $userAgent))
    {
        $os = 'Windows';
    }
    elseif (preg_match('/iPhone|iPad|iPod/', $userAgent))
    {
        $os = 'iOS';
    }
    elseif (preg_match('/Mac OS X/', $userAgent))
    {
        $os = 'macOS';
    }
    elseif (preg_match('/Android/', $userAgent))
    {
        $os = 'Android';
    }
    elseif (preg_match('/CrOS/', $userAgent))
    {
        $os = 'Chrome OS';
    }
    elseif (preg_match('/Linux/', $userAgent))
    {
        $os = 'Linux';
    }

    return $browser . ' on ' . $os;
}

// ============================================================================
// 🔧 Internal Helpers
// ============================================================================

/**
 * Mask an IP address for privacy display.
 *
 * IPv4: shows first two octets (e.g., "192.168.*.*")
 * IPv6: shows first two groups (e.g., "2001:db8:*")
 *
 * @param  string $ip  The full IP address
 * @return string      The masked IP address
 */
function _g2ml_maskIP(string $ip): string
{
    // Check for IPv6
    if (strpos($ip, ':') !== false)
    {
        $parts = explode(':', $ip);

        if (count($parts) >= 2)
        {
            return $parts[0] . ':' . $parts[1] . ':*';
        }

        return $ip;
    }

    // IPv4 masking
    $parts = explode('.', $ip);

    if (count($parts) === 4)
    {
        return $parts[0] . '.' . $parts[1] . '.*.*';
    }

    return $ip;
}
