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
 * 🔑 Go2My.Link — Authentication Functions
 * ============================================================================
 *
 * User registration, login/logout, password management, email verification,
 * and role-based access control. All token storage uses SHA-256 hashing so
 * a database leak does not compromise tokens.
 *
 * Dependencies: security.php (password hashing, token generation, sanitisation),
 *               db_query.php (prepared statement wrappers),
 *               settings.php (getSetting),
 *               session.php (createUserSession, destroyUserSession),
 *               email.php (g2ml_sendEmail),
 *               activity_logger.php (logActivity)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.5.0
 * @since      Phase 4
 *
 * 📖 References:
 *     - tblUsers schema: web/_sql/schema/020_users.sql
 *     - OWASP Auth: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
 *     - OWASP Password: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html
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
// 🏗️ Role Hierarchy
// ============================================================================
// Used by hasMinimumRole() for role-based access checks.
// Higher number = higher privilege.
// ============================================================================

/** @var array Role name → numeric level mapping */
define('G2ML_ROLE_LEVELS', [
    'Anonymous'   => 0,
    'User'        => 1,
    'Admin'       => 2,
    'GlobalAdmin' => 3,
]);

// ============================================================================
// 📝 Register User
// ============================================================================

/**
 * Register a new user account.
 *
 * Creates the user in tblUsers with Argon2id-hashed password, generates an
 * email verification token, and returns the token for sending the verification
 * email. Uses generic error messages to prevent email enumeration.
 *
 * @param  string $email     User's email address
 * @param  string $password  Plaintext password
 * @param  string $firstName User's first name
 * @param  string $lastName  User's last name
 * @return array             ['success' => bool, 'error' => string, 'userUID' => int, 'verifyToken' => string]
 *
 * 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
 */
function registerUser(string $email, string $password, string $firstName, string $lastName): array
{
    // Sanitise inputs
    $email     = strtolower(trim($email));
    $firstName = trim(g2ml_sanitiseInput($firstName));
    $lastName  = trim(g2ml_sanitiseInput($lastName));

    // Validate email format
    // 📖 Reference: https://www.php.net/manual/en/filter.filters.validate.php
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
        return [
            'success' => false,
            'error'   => 'Please enter a valid email address.',
        ];
    }

    // Validate password strength
    $passwordCheck = validatePasswordStrength($password);

    if (!$passwordCheck['valid'])
    {
        return [
            'success' => false,
            'error'   => implode(' ', $passwordCheck['errors']),
        ];
    }

    // Validate name fields
    if ($firstName === '' || $lastName === '')
    {
        return [
            'success' => false,
            'error'   => 'First name and last name are required.',
        ];
    }

    // Check if email already exists
    // 📖 Use generic error to prevent email enumeration
    $existing = dbSelectOne(
        "SELECT userUID FROM tblUsers WHERE email = ? LIMIT 1",
        's',
        [$email]
    );

    if ($existing !== null && $existing !== false)
    {
        // Email already registered — return generic error
        // 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html#account-creation
        return [
            'success' => false,
            'error'   => 'Unable to create account. Please try a different email address or log in if you already have an account.',
        ];
    }

    // Hash the password
    // 📖 Reference: security.php → g2ml_hashPassword()
    $passwordHash = g2ml_hashPassword($password);

    // Generate email verification token
    $verifyToken     = g2ml_generateToken(32);
    $verifyTokenHash = hash('sha256', $verifyToken);
    $verifyExpiry    = date('Y-m-d H:i:s', time() + (int) getSetting('auth.email_verification_expiry', 86400));

    // Build display name from first + last
    $displayName = $firstName . ' ' . $lastName;

    // Insert the user
    $userUID = dbInsert(
        "INSERT INTO tblUsers (
            orgHandle, email, passwordHash, firstName, lastName, displayName,
            role, emailVerified, emailVerifyToken, emailVerifyExpiry,
            isActive, isSuspended, createdAt
        ) VALUES (
            '[default]', ?, ?, ?, ?, ?,
            'User', 0, ?, ?,
            1, 0, NOW()
        )",
        'sssssss',
        [$email, $passwordHash, $firstName, $lastName, $displayName, $verifyTokenHash, $verifyExpiry]
    );

    if ($userUID === false)
    {
        error_log('[Go2My.Link] ERROR: registerUser — failed to insert user for email: ' . $email);

        return [
            'success' => false,
            'error'   => 'An error occurred during registration. Please try again.',
        ];
    }

    // Log the registration activity
    logActivity('register', 'success', 201, [
        'userUID' => $userUID,
        'logData' => ['email' => $email],
    ]);

    return [
        'success'     => true,
        'error'       => '',
        'userUID'     => $userUID,
        'verifyToken' => $verifyToken,
    ];
}

// ============================================================================
// 🔐 Login User
// ============================================================================

/**
 * Authenticate a user with email and password.
 *
 * Checks credentials, handles account lockout, creates a database-backed
 * session, and populates $_SESSION with user data. Uses timing-safe
 * comparisons and generic error messages to prevent enumeration.
 *
 * @param  string $email     User's email address
 * @param  string $password  Plaintext password
 * @return array             ['success' => bool, 'error' => string, 'locked' => bool, 'lockSeconds' => int]
 *
 * 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
 */
function loginUser(string $email, string $password): array
{
    $email = strtolower(trim($email));

    // Look up the user by email
    $user = dbSelectOne(
        "SELECT userUID, orgHandle, email, passwordHash, firstName, lastName,
                displayName, role, emailVerified, isActive, isSuspended,
                failedLoginAttempts, lockedUntil, avatarURL, timezone
         FROM tblUsers
         WHERE email = ?
         LIMIT 1",
        's',
        [$email]
    );

    // If user not found, still run password_verify against a dummy hash
    // to prevent timing-based email enumeration
    // 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html#authentication-responses
    if ($user === null || $user === false)
    {
        // Use Argon2id dummy hash to match the real algorithm's timing profile
        // and prevent timing-based user enumeration
        g2ml_verifyPassword($password, '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlzYWx0Zm9ydGhpcw$dummyhashvaluetopreventtimingleaksinthisproject0000');

        return [
            'success'     => false,
            'error'       => 'Invalid email or password.',
            'locked'      => false,
            'lockSeconds' => 0,
        ];
    }

    // Check if account is locked
    $lockCheck = isAccountLocked($user);

    if ($lockCheck['locked'])
    {
        logActivity('login', 'locked_out', 403, [
            'userUID' => $user['userUID'],
            'logData' => ['email' => $email, 'remainingSeconds' => $lockCheck['remainingSeconds']],
        ]);

        return [
            'success'     => false,
            'error'       => 'Account temporarily locked due to too many failed login attempts. Please try again in ' . ceil($lockCheck['remainingSeconds'] / 60) . ' minute(s).',
            'locked'      => true,
            'lockSeconds' => $lockCheck['remainingSeconds'],
        ];
    }

    // Check if account is suspended
    if ((int) $user['isSuspended'] === 1)
    {
        logActivity('login', 'suspended', 403, [
            'userUID' => $user['userUID'],
            'logData' => ['email' => $email],
        ]);

        return [
            'success'     => false,
            'error'       => 'This account has been suspended. Please contact support.',
            'locked'      => false,
            'lockSeconds' => 0,
        ];
    }

    // Check if account is active
    if ((int) $user['isActive'] !== 1)
    {
        return [
            'success'     => false,
            'error'       => 'Invalid email or password.',
            'locked'      => false,
            'lockSeconds' => 0,
        ];
    }

    // Verify the password
    if (!g2ml_verifyPassword($password, $user['passwordHash']))
    {
        // Increment failed login attempts
        $maxAttempts    = (int) getSetting('security.max_login_attempts', 5);
        $lockoutSeconds = (int) getSetting('security.lockout_duration', 900);
        $newAttempts    = (int) $user['failedLoginAttempts'] + 1;

        if ($newAttempts >= $maxAttempts)
        {
            // Lock the account
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutSeconds);

            dbUpdate(
                "UPDATE tblUsers SET failedLoginAttempts = ?, lockedUntil = ? WHERE userUID = ?",
                'isi',
                [$newAttempts, $lockedUntil, $user['userUID']]
            );

            logActivity('login', 'account_locked', 403, [
                'userUID' => $user['userUID'],
                'logData' => ['email' => $email, 'attempts' => $newAttempts],
            ]);
        }
        else
        {
            dbUpdate(
                "UPDATE tblUsers SET failedLoginAttempts = ? WHERE userUID = ?",
                'ii',
                [$newAttempts, $user['userUID']]
            );

            logActivity('login', 'wrong_password', 401, [
                'userUID' => $user['userUID'],
                'logData' => ['email' => $email, 'attempts' => $newAttempts],
            ]);
        }

        return [
            'success'     => false,
            'error'       => 'Invalid email or password.',
            'locked'      => false,
            'lockSeconds' => 0,
        ];
    }

    // === Password is correct — login successful ===

    // Reset failed login attempts and update login metadata
    if (function_exists('g2ml_getClientIP')) {
        $ipAddress = g2ml_getClientIP();
    } else {
        $ipAddress = ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    dbUpdate(
        "UPDATE tblUsers SET failedLoginAttempts = 0, lockedUntil = NULL, lastLoginAt = NOW(), lastLoginIP = ? WHERE userUID = ?",
        'si',
        [$ipAddress, $user['userUID']]
    );

    // Check if password hash needs rehashing (algorithm upgrade)
    // 📖 Reference: security.php → g2ml_passwordNeedsRehash()
    if (g2ml_passwordNeedsRehash($user['passwordHash']))
    {
        $newHash = g2ml_hashPassword($password);

        dbUpdate(
            "UPDATE tblUsers SET passwordHash = ? WHERE userUID = ?",
            'si',
            [$newHash, $user['userUID']]
        );
    }

    // Regenerate PHP session ID to prevent session fixation
    // 📖 Reference: https://www.php.net/manual/en/function.session-regenerate-id.php
    session_regenerate_id(true);
    $_SESSION['_g2ml_session_created'] = time();

    // Create a database-backed session
    $sessionToken = createUserSession($user['userUID']);

    if ($sessionToken === false)
    {
        error_log('[Go2My.Link] ERROR: loginUser — createUserSession failed for userUID: ' . $user['userUID']);

        return [
            'success'     => false,
            'error'       => 'An error occurred during login. Please try again.',
            'locked'      => false,
            'lockSeconds' => 0,
        ];
    }

    // Populate $_SESSION with user data
    $_SESSION['user_uid']          = (int) $user['userUID'];
    $_SESSION['user_email']        = $user['email'];
    $_SESSION['user_display_name'] = $user['displayName'] ?? ($user['firstName'] . ' ' . $user['lastName']);
    $_SESSION['user_first_name']   = $user['firstName'];
    $_SESSION['user_last_name']    = $user['lastName'];
    $_SESSION['user_role']         = $user['role'];
    $_SESSION['user_org_handle']   = $user['orgHandle'];
    $_SESSION['user_avatar']       = $user['avatarURL'] ?? '';
    $_SESSION['user_timezone']     = $user['timezone'] ?? 'UTC';
    $_SESSION['email_verified']    = (int) $user['emailVerified'];

    // Log successful login
    logActivity('login', 'success', 200, [
        'userUID' => $user['userUID'],
        'logData' => ['email' => $email],
    ]);

    // Send new login alert if this IP hasn't been seen before
    _g2ml_sendNewLoginAlert($user);

    return [
        'success'     => true,
        'error'       => '',
        'locked'      => false,
        'lockSeconds' => 0,
    ];
}

// ============================================================================
// 🚪 Logout User
// ============================================================================

/**
 * Log out the current user.
 *
 * Destroys the database session and PHP session, then logs the activity.
 *
 * @return void
 */
function logoutUser(): void
{
    $userUID = $_SESSION['user_uid'] ?? null;

    // Destroy the session (DB + PHP)
    destroyUserSession();

    // Log the activity
    if ($userUID !== null)
    {
        logActivity('logout', 'success', 200, [
            'userUID' => $userUID,
        ]);
    }
}

// ============================================================================
// 🔍 Authentication State Checks
// ============================================================================

/**
 * Check if the current request is from an authenticated user.
 *
 * Verifies both the PHP session and the database-backed session token.
 *
 * @return bool  True if the user is authenticated
 */
function isAuthenticated(): bool
{
    // Check PHP session has a user UID
    if (!isset($_SESSION['user_uid']) || (int) $_SESSION['user_uid'] <= 0)
    {
        return false;
    }

    // Validate the database-backed session
    return validateUserSession();
}

/**
 * Get the current authenticated user's data from the session.
 *
 * Returns null if not authenticated.
 *
 * @return array|null  User data array, or null if not logged in
 */
function getCurrentUser(): ?array
{
    if (!isAuthenticated())
    {
        return null;
    }

    return [
        'userUID'       => (int) $_SESSION['user_uid'],
        'email'         => $_SESSION['user_email'] ?? '',
        'displayName'   => $_SESSION['user_display_name'] ?? '',
        'firstName'     => $_SESSION['user_first_name'] ?? '',
        'lastName'      => $_SESSION['user_last_name'] ?? '',
        'role'          => $_SESSION['user_role'] ?? 'User',
        'orgHandle'     => $_SESSION['user_org_handle'] ?? '[default]',
        'avatar'        => $_SESSION['user_avatar'] ?? '',
        'timezone'      => $_SESSION['user_timezone'] ?? 'UTC',
        'emailVerified' => (int) ($_SESSION['email_verified'] ?? 0),
    ];
}

/**
 * Require authentication to access a page.
 *
 * If the user is not authenticated, redirects to the login page with a
 * redirect-back parameter. If the user's role is below the minimum required,
 * shows a 403 error page.
 *
 * @param  string $minRole  Minimum role required (default: 'User')
 * @return void             Returns normally if authenticated, exits otherwise
 */
function requireAuth(string $minRole = 'User'): void
{
    if (!isAuthenticated())
    {
        $redirectURL = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://go2my.link/login?redirect=' . urlencode($redirectURL));
        exit;
    }

    // Check role level
    $userRole = $_SESSION['user_role'] ?? 'User';

    if (!hasMinimumRole($userRole, $minRole))
    {
        http_response_code(403);

        logActivity('auth', 'insufficient_role', 403, [
            'userUID' => $_SESSION['user_uid'] ?? null,
            'logData' => ['required' => $minRole, 'actual' => $userRole],
        ]);

        echo '<!DOCTYPE html><html><head><title>403 — Access Denied</title></head><body>';
        echo '<div style="text-align:center;padding:60px;font-family:sans-serif;">';
        echo '<h1>403 — Access Denied</h1>';
        echo '<p>You do not have permission to access this page.</p>';
        echo '<a href="https://go2my.link">Return to Homepage</a>';
        echo '</div></body></html>';
        exit;
    }
}

// ============================================================================
// 🔒 Password Validation
// ============================================================================

/**
 * Validate password strength against configurable rules.
 *
 * Rules are loaded from tblSettings:
 *   - security.password_min_length (default: 8)
 *   - security.password_require_uppercase (default: true)
 *   - security.password_require_lowercase (default: true)
 *   - security.password_require_number (default: true)
 *   - security.password_require_special (default: false)
 *
 * @param  string $password  The plaintext password to validate
 * @return array             ['valid' => bool, 'errors' => string[]]
 */
function validatePasswordStrength(string $password): array
{
    $errors = [];

    // Minimum length
    $minLength = (int) getSetting('security.password_min_length', 8);

    if (strlen($password) < $minLength)
    {
        $errors[] = 'Password must be at least ' . $minLength . ' characters long.';
    }

    // Maximum length (prevent DoS via very long passwords for Argon2id)
    if (strlen($password) > 128)
    {
        $errors[] = 'Password must be 128 characters or fewer.';
    }

    // Require uppercase letter
    if (getSetting('security.password_require_uppercase', true))
    {
        if (!preg_match('/[A-Z]/', $password))
        {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
    }

    // Require lowercase letter
    if (getSetting('security.password_require_lowercase', true))
    {
        if (!preg_match('/[a-z]/', $password))
        {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
    }

    // Require number
    if (getSetting('security.password_require_number', true))
    {
        if (!preg_match('/[0-9]/', $password))
        {
            $errors[] = 'Password must contain at least one number.';
        }
    }

    // Require special character
    if (getSetting('security.password_require_special', false))
    {
        if (!preg_match('/[^A-Za-z0-9]/', $password))
        {
            $errors[] = 'Password must contain at least one special character.';
        }
    }

    return [
        'valid'  => (count($errors) === 0),
        'errors' => $errors,
    ];
}

// ============================================================================
// 📧 Email Verification
// ============================================================================

/**
 * Generate a new email verification token for a user.
 *
 * Stores the SHA-256 hash of the token in tblUsers with a 24-hour expiry.
 *
 * @param  int          $userUID  The user's UID
 * @return string|false           The plaintext verification token, or false on failure
 */
function generateEmailVerificationToken(int $userUID): string|false
{
    $token     = g2ml_generateToken(32);
    $tokenHash = hash('sha256', $token);
    $expiry    = date('Y-m-d H:i:s', time() + (int) getSetting('auth.email_verification_expiry', 86400));

    $result = dbUpdate(
        "UPDATE tblUsers SET emailVerifyToken = ?, emailVerifyExpiry = ? WHERE userUID = ?",
        'ssi',
        [$tokenHash, $expiry, $userUID]
    );

    if ($result === false)
    {
        return false;
    }

    return $token;
}

/**
 * Verify a user's email address using the submitted token.
 *
 * Hashes the submitted token, looks up the matching user, verifies expiry,
 * and sets emailVerified = 1.
 *
 * @param  string $token  The plaintext verification token (from URL)
 * @return array          ['success' => bool, 'error' => string]
 */
function verifyEmail(string $token): array
{
    if ($token === '')
    {
        return ['success' => false, 'error' => 'Verification token is required.'];
    }

    $tokenHash = hash('sha256', $token);

    // Find the user with this token
    $user = dbSelectOne(
        "SELECT userUID, emailVerifyExpiry, emailVerified
         FROM tblUsers
         WHERE emailVerifyToken = ?
         LIMIT 1",
        's',
        [$tokenHash]
    );

    if ($user === null || $user === false)
    {
        return ['success' => false, 'error' => 'Invalid or expired verification link.'];
    }

    // Check if already verified
    if ((int) $user['emailVerified'] === 1)
    {
        return ['success' => true, 'error' => ''];
    }

    // Check expiry
    if ($user['emailVerifyExpiry'] !== null && strtotime($user['emailVerifyExpiry']) < time())
    {
        return ['success' => false, 'error' => 'This verification link has expired. Please request a new one.'];
    }

    // Mark email as verified and clear the token
    dbUpdate(
        "UPDATE tblUsers SET emailVerified = 1, emailVerifyToken = NULL, emailVerifyExpiry = NULL WHERE userUID = ?",
        'i',
        [$user['userUID']]
    );

    // Update session if the verified user is currently logged in
    if (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] === (int) $user['userUID'])
    {
        $_SESSION['email_verified'] = 1;
    }

    logActivity('verify_email', 'success', 200, [
        'userUID' => $user['userUID'],
    ]);

    return ['success' => true, 'error' => ''];
}

// ============================================================================
// 🔑 Password Reset
// ============================================================================

/**
 * Generate a password reset token and send the reset email.
 *
 * Always returns success to prevent email enumeration — even if the
 * email doesn't exist in the database.
 *
 * @param  string $email  The user's email address
 * @return array          ['success' => true] (always, for enumeration prevention)
 */
function generatePasswordResetToken(string $email): array
{
    $email = strtolower(trim($email));

    // Look up the user
    $user = dbSelectOne(
        "SELECT userUID, firstName, email
         FROM tblUsers
         WHERE email = ? AND isActive = 1
         LIMIT 1",
        's',
        [$email]
    );

    // If user exists, generate token and send email
    if ($user !== null && $user !== false)
    {
        $token     = g2ml_generateToken(32);
        $tokenHash = hash('sha256', $token);
        $expiry    = date('Y-m-d H:i:s', time() + (int) getSetting('auth.password_reset_expiry', 3600));

        dbUpdate(
            "UPDATE tblUsers SET passwordResetToken = ?, passwordResetExpiry = ? WHERE userUID = ?",
            'ssi',
            [$tokenHash, $expiry, $user['userUID']]
        );

        // Send the reset email
        $resetURL = 'https://go2my.link/reset-password?token=' . urlencode($token);

        g2ml_sendEmail(
            $user['email'],
            'Reset Your Password — ' . getSetting('site.name', 'Go2My.Link'),
            'password_reset',
            [
                'firstName' => $user['firstName'],
                'resetURL'  => $resetURL,
            ]
        );

        logActivity('password_reset_request', 'success', 200, [
            'userUID' => $user['userUID'],
            'logData' => ['email' => $email],
        ]);
    }

    // Always return success (timing-safe enumeration prevention)
    // 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html
    return ['success' => true];
}

/**
 * Validate a password reset token (check if it's valid and not expired).
 *
 * @param  string $token  The plaintext reset token
 * @return array          ['valid' => bool, 'userUID' => int|null, 'error' => string]
 */
function validatePasswordResetToken(string $token): array
{
    if ($token === '')
    {
        return ['valid' => false, 'userUID' => null, 'error' => 'Reset token is required.'];
    }

    $tokenHash = hash('sha256', $token);

    $user = dbSelectOne(
        "SELECT userUID, passwordResetExpiry
         FROM tblUsers
         WHERE passwordResetToken = ?
         LIMIT 1",
        's',
        [$tokenHash]
    );

    if ($user === null || $user === false)
    {
        return ['valid' => false, 'userUID' => null, 'error' => 'Invalid or expired reset link.'];
    }

    if ($user['passwordResetExpiry'] !== null && strtotime($user['passwordResetExpiry']) < time())
    {
        return ['valid' => false, 'userUID' => null, 'error' => 'This reset link has expired. Please request a new one.'];
    }

    return ['valid' => true, 'userUID' => (int) $user['userUID'], 'error' => ''];
}

/**
 * Reset a user's password using a valid reset token.
 *
 * Validates the token, checks password strength, updates the hash,
 * clears the reset token, and revokes all active sessions.
 *
 * @param  string $token        The plaintext reset token
 * @param  string $newPassword  The new plaintext password
 * @return array                ['success' => bool, 'error' => string]
 */
function resetPassword(string $token, string $newPassword): array
{
    // Validate the token
    $tokenCheck = validatePasswordResetToken($token);

    if (!$tokenCheck['valid'])
    {
        return ['success' => false, 'error' => $tokenCheck['error']];
    }

    // Validate password strength
    $passwordCheck = validatePasswordStrength($newPassword);

    if (!$passwordCheck['valid'])
    {
        return ['success' => false, 'error' => implode(' ', $passwordCheck['errors'])];
    }

    $userUID = $tokenCheck['userUID'];

    // Hash the new password
    $passwordHash = g2ml_hashPassword($newPassword);

    // Update password and clear reset token
    dbUpdate(
        "UPDATE tblUsers SET passwordHash = ?, passwordResetToken = NULL, passwordResetExpiry = NULL, failedLoginAttempts = 0, lockedUntil = NULL WHERE userUID = ?",
        'si',
        [$passwordHash, $userUID]
    );

    // Revoke all active sessions (force re-login everywhere)
    dbUpdate(
        "UPDATE tblUserSessions SET isActive = 0 WHERE userUID = ?",
        'i',
        [$userUID]
    );

    // Send password changed notification
    $user = dbSelectOne(
        "SELECT firstName, email FROM tblUsers WHERE userUID = ? LIMIT 1",
        'i',
        [$userUID]
    );

    if ($user !== null && $user !== false)
    {
        if (function_exists('g2ml_getClientIP')) {
            $ipAddress = g2ml_getClientIP();
        } else {
            $ipAddress = 'Unknown';
        }

        g2ml_sendEmail(
            $user['email'],
            'Your Password Has Been Changed — ' . getSetting('site.name', 'Go2My.Link'),
            'password_changed',
            [
                'firstName' => $user['firstName'],
                'changedAt' => date('j M Y, H:i T'),
                'ipAddress' => $ipAddress,
            ]
        );
    }

    logActivity('password_reset', 'success', 200, [
        'userUID' => $userUID,
    ]);

    return ['success' => true, 'error' => ''];
}

// ============================================================================
// 🔑 Change Password (Authenticated)
// ============================================================================

/**
 * Change password for an authenticated user.
 *
 * Verifies the current password, validates the new one, updates the hash,
 * and revokes all other sessions.
 *
 * @param  int    $userUID         The user's UID
 * @param  string $currentPassword Current plaintext password
 * @param  string $newPassword     New plaintext password
 * @return array                   ['success' => bool, 'error' => string]
 */
function changePassword(int $userUID, string $currentPassword, string $newPassword): array
{
    // Get the current password hash
    $user = dbSelectOne(
        "SELECT passwordHash, firstName, email FROM tblUsers WHERE userUID = ? LIMIT 1",
        'i',
        [$userUID]
    );

    if ($user === null || $user === false)
    {
        return ['success' => false, 'error' => 'User not found.'];
    }

    // Verify current password
    if (!g2ml_verifyPassword($currentPassword, $user['passwordHash']))
    {
        logActivity('change_password', 'wrong_current', 401, [
            'userUID' => $userUID,
        ]);

        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    // Validate new password strength
    $passwordCheck = validatePasswordStrength($newPassword);

    if (!$passwordCheck['valid'])
    {
        return ['success' => false, 'error' => implode(' ', $passwordCheck['errors'])];
    }

    // Hash and update the new password
    $passwordHash = g2ml_hashPassword($newPassword);

    dbUpdate(
        "UPDATE tblUsers SET passwordHash = ? WHERE userUID = ?",
        'si',
        [$passwordHash, $userUID]
    );

    // Revoke all other sessions
    if (isset($_SESSION['session_token']))
    {
        revokeAllOtherSessions($userUID, $_SESSION['session_token']);
    }

    // Send password changed notification
    if (function_exists('g2ml_getClientIP')) {
        $ipAddress = g2ml_getClientIP();
    } else {
        $ipAddress = 'Unknown';
    }

    g2ml_sendEmail(
        $user['email'],
        'Your Password Has Been Changed — ' . getSetting('site.name', 'Go2My.Link'),
        'password_changed',
        [
            'firstName' => $user['firstName'],
            'changedAt' => date('j M Y, H:i T'),
            'ipAddress' => $ipAddress,
        ]
    );

    logActivity('change_password', 'success', 200, [
        'userUID' => $userUID,
    ]);

    return ['success' => true, 'error' => ''];
}

// ============================================================================
// 🔒 Account Lockout
// ============================================================================

/**
 * Check if a user account is currently locked out.
 *
 * @param  array $user  User row from tblUsers (must include 'lockedUntil')
 * @return array        ['locked' => bool, 'remainingSeconds' => int]
 */
function isAccountLocked(array $user): array
{
    if (empty($user['lockedUntil']))
    {
        return ['locked' => false, 'remainingSeconds' => 0];
    }

    $lockedUntil = strtotime($user['lockedUntil']);
    $now         = time();

    if ($lockedUntil > $now)
    {
        return [
            'locked'           => true,
            'remainingSeconds' => $lockedUntil - $now,
        ];
    }

    return ['locked' => false, 'remainingSeconds' => 0];
}

// ============================================================================
// 🛡️ Role-Based Access Control
// ============================================================================

/**
 * Check if a user's role meets the minimum required role.
 *
 * Role hierarchy: Anonymous (0) < User (1) < Admin (2) < GlobalAdmin (3)
 *
 * @param  string $userRole      The user's current role
 * @param  string $requiredRole  The minimum required role
 * @return bool                  True if the user's role is sufficient
 */
function hasMinimumRole(string $userRole, string $requiredRole): bool
{
    $userLevel     = G2ML_ROLE_LEVELS[$userRole] ?? 0;
    $requiredLevel = G2ML_ROLE_LEVELS[$requiredRole] ?? 0;

    return $userLevel >= $requiredLevel;
}

// ============================================================================
// 🔧 Internal Helpers
// ============================================================================

/**
 * Send a new login alert email if the IP address hasn't been seen before.
 *
 * Checks tblUserSessions for previous logins from this IP. If none found,
 * sends the new_login_alert email template.
 *
 * @param  array $user  User row from tblUsers
 * @return void
 */
function _g2ml_sendNewLoginAlert(array $user): void
{
    if (function_exists('g2ml_getClientIP')) {
        $ipAddress = g2ml_getClientIP();
    } else {
        $ipAddress = ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Check if this IP has been used before for this user (excluding the current session)
    $previousFromIP = dbSelectOne(
        "SELECT sessionUID FROM tblUserSessions
         WHERE userUID = ? AND ipAddress = ? AND sessionUID != (
             SELECT MAX(sessionUID) FROM tblUserSessions WHERE userUID = ?
         )
         LIMIT 1",
        'isi',
        [$user['userUID'], $ipAddress, $user['userUID']]
    );

    // If this IP has been seen before, no alert needed
    if ($previousFromIP !== null && $previousFromIP !== false)
    {
        return;
    }

    // Also skip if this is the user's very first session (just registered)
    $sessionCount = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblUserSessions WHERE userUID = ?",
        'i',
        [$user['userUID']]
    );

    if ($sessionCount !== null && $sessionCount !== false && (int) $sessionCount['cnt'] <= 1)
    {
        return; // First ever session — don't alert
    }

    // Send the alert
    g2ml_sendEmail(
        $user['email'],
        'New Login to Your Account — ' . getSetting('site.name', 'Go2My.Link'),
        'new_login_alert',
        [
            'firstName'  => $user['firstName'],
            'deviceInfo' => parseDeviceInfo($userAgent),
            'ipAddress'  => $ipAddress,
            'loginAt'    => date('j M Y, H:i T'),
        ]
    );
}
