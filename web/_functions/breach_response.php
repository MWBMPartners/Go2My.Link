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
 * 🚨 Go2My.Link — Breach Response Functions
 * ============================================================================
 *
 * GlobalAdmin-only functions for mass credential reset in the event of a
 * security breach. Includes password invalidation, session revocation,
 * mass notification emails, and ENCRYPTION_SALT rotation.
 *
 * Dependencies: security.php, auth.php, session.php, email.php,
 *               settings.php (getSetting/setSetting),
 *               db_query.php (dbSelect/dbUpdate), activity_logger.php
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      Phase 7
 *
 * 📖 References:
 *     - OWASP Incident Response: https://cheatsheetseries.owasp.org/cheatsheets/Credential_Stuffing_Prevention_Cheat_Sheet.html
 *     - Password Storage: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html
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
// 🚨 Execute Full Breach Response
// ============================================================================

/**
 * Execute a full breach response: invalidate passwords, revoke sessions,
 * and send mass notification emails with reset links.
 *
 * This is the main orchestrator function. Only callable by GlobalAdmin.
 *
 * @param  int         $adminUID   The GlobalAdmin's user UID triggering the response
 * @param  string      $reason     Human-readable reason for the breach response
 * @param  string|null $newSalt    New ENCRYPTION_SALT hex string (null to skip rotation)
 * @return array                   ['success' => bool, 'error' => string, 'stats' => array]
 */
function g2ml_breachResponse(int $adminUID, string $reason, ?string $newSalt = null): array
{
    $stats = [
        'passwords_invalidated' => 0,
        'sessions_revoked'      => 0,
        'emails_sent'           => 0,
        'emails_failed'         => 0,
        'salt_rotated'          => false,
        'started_at'            => gmdate('Y-m-d H:i:s'),
        'completed_at'          => null,
    ];

    // 🛡️ Validate adminUID bounds
    if ($adminUID <= 0)
    {
        return ['success' => false, 'error' => 'Invalid admin user identifier.', 'stats' => $stats];
    }

    // 🛡️ Strip control characters from reason (prevent log injection)
    $reason = preg_replace('/[\x00-\x1F\x7F]/', '', $reason);

    // Verify the caller is a GlobalAdmin
    $admin = dbSelectOne(
        "SELECT userUID, role, email, displayName FROM tblUsers WHERE userUID = ? AND isActive = 1 LIMIT 1",
        'i',
        [$adminUID]
    );

    if ($admin === null || $admin === false || !hasMinimumRole($admin['role'], 'GlobalAdmin'))
    {
        g2ml_logBreachResponse($adminUID, 'denied', ['reason' => 'Insufficient privileges']);

        return [
            'success' => false,
            'error'   => 'Only GlobalAdmin users can trigger a breach response.',
            'stats'   => $stats,
        ];
    }

    // Check if breach response is enabled
    if (!(bool) getSetting('security.breach_response_enabled', true))
    {
        g2ml_logBreachResponse($adminUID, 'denied', ['reason' => 'Feature disabled']);

        return [
            'success' => false,
            'error'   => 'Breach response is currently disabled in system settings.',
            'stats'   => $stats,
        ];
    }

    // Check cooldown period
    $cooldown       = (int) getSetting('security.breach_response_cooldown', 3600);
    $lastBreachTime = getSetting('security.last_breach_response', '');

    if ($lastBreachTime !== '' && (time() - strtotime($lastBreachTime)) < $cooldown)
    {
        $remaining = $cooldown - (time() - strtotime($lastBreachTime));
        g2ml_logBreachResponse($adminUID, 'denied', ['reason' => 'Cooldown active', 'remaining_seconds' => $remaining]);

        return [
            'success' => false,
            'error'   => 'Breach response cooldown active. Please wait ' . ceil($remaining / 60) . ' minute(s).',
            'stats'   => $stats,
        ];
    }

    // 🛡️ Set cooldown timestamp immediately to prevent TOCTOU race condition
    setSetting('security.last_breach_response', gmdate('Y-m-d H:i:s'), 'System');

    // Prevent PHP timeout for mass operations (bounded to 1 hour max)
    set_time_limit(3600);

    // Log initiation
    g2ml_logBreachResponse($adminUID, 'initiated', [
        'reason'       => $reason,
        'rotate_salt'  => ($newSalt !== null),
    ]);

    logActivity('breach_response', 'initiated', 200, [
        'userUID' => $adminUID,
        'logData' => ['reason' => $reason],
    ]);

    // Step 1: Invalidate all passwords
    $stats['passwords_invalidated'] = g2ml_invalidateAllPasswords();

    // Step 2: Revoke all sessions
    $stats['sessions_revoked'] = g2ml_revokeAllSessions();

    // Step 3: Rotate ENCRYPTION_SALT if requested
    if ($newSalt !== null && $newSalt !== '')
    {
        $rotationResult = g2ml_rotateEncryptionSalt($newSalt);
        $stats['salt_rotated'] = $rotationResult['success'];

        if (!$rotationResult['success'])
        {
            g2ml_logBreachResponse($adminUID, 'salt_rotation_failed', $rotationResult);
        }
    }

    // Step 4: Send mass reset emails
    $emailResult = g2ml_sendMassResetEmails($reason);
    $stats['emails_sent']   = $emailResult['sent'];
    $stats['emails_failed'] = $emailResult['failed'];

    // Record completion
    $stats['completed_at'] = gmdate('Y-m-d H:i:s');

    // Update the breach response timestamp with final completion time
    setSetting('security.last_breach_response', $stats['completed_at'], 'System');

    // Log completion
    g2ml_logBreachResponse($adminUID, 'completed', $stats);

    logActivity('breach_response', 'completed', 200, [
        'userUID' => $adminUID,
        'logData' => $stats,
    ]);

    return [
        'success' => true,
        'error'   => '',
        'stats'   => $stats,
    ];
}

// ============================================================================
// 🔒 Invalidate All Passwords
// ============================================================================

/**
 * Invalidate all user passwords by setting them to an invalid marker
 * and enabling the forcePasswordReset flag.
 *
 * The marker '[INVALIDATED]' is not a valid Argon2id/bcrypt hash, so
 * password_verify() will always return false for it.
 *
 * @return int  Number of users whose passwords were invalidated
 */
function g2ml_invalidateAllPasswords(): int
{
    // Invalidate ALL users (including inactive) to prevent offline cracking
    $result = dbUpdate(
        "UPDATE tblUsers
         SET passwordHash = '[INVALIDATED]',
             forcePasswordReset = 1,
             passwordResetToken = NULL,
             passwordResetExpiry = NULL",
        '',
        []
    );

    if ($result === false)
    {
        error_log('[Go2My.Link] CRITICAL: g2ml_invalidateAllPasswords — UPDATE query failed.');
        return 0;
    }

    return (int) $result;
}

// ============================================================================
// 🔓 Revoke All Sessions
// ============================================================================

/**
 * Revoke all active user sessions system-wide.
 *
 * This forces every user to re-authenticate on their next request.
 *
 * @return int  Number of sessions revoked
 */
function g2ml_revokeAllSessions(): int
{
    $result = dbUpdate(
        "UPDATE tblUserSessions SET isActive = 0 WHERE isActive = 1",
        '',
        []
    );

    if ($result === false)
    {
        error_log('[Go2My.Link] CRITICAL: g2ml_revokeAllSessions — UPDATE query failed.');
        return 0;
    }

    return (int) $result;
}

// ============================================================================
// 📧 Send Mass Reset Emails
// ============================================================================

/**
 * Send breach notification emails to all active users in batches.
 *
 * For each user, generates a unique password reset token and sends
 * the 'breach_notification' email template with the reset link.
 *
 * Batch size is controlled by the 'security.breach_email_batch_size' setting.
 *
 * @param  string $reason  Human-readable reason for the breach
 * @return array           ['sent' => int, 'failed' => int]
 */
function g2ml_sendMassResetEmails(string $reason): array
{
    $batchSize = (int) getSetting('security.breach_email_batch_size', 50);
    $sent      = 0;
    $failed    = 0;
    $offset    = 0;
    $siteName  = getSetting('site.name', 'Go2My.Link');
    $resetExpiry = (int) getSetting('auth.password_reset_expiry', 3600);

    while (true)
    {
        // Fetch a batch of active users
        $users = dbSelect(
            "SELECT userUID, email, firstName, displayName
             FROM tblUsers
             WHERE isActive = 1
             ORDER BY userUID ASC
             LIMIT ? OFFSET ?",
            'ii',
            [$batchSize, $offset]
        );

        // No more users or query failed
        if ($users === false || count($users) === 0)
        {
            break;
        }

        foreach ($users as $user)
        {
            // Generate a unique password reset token for this user
            $token     = g2ml_generateToken(32);
            $tokenHash = hash('sha256', $token);
            $expiry    = date('Y-m-d H:i:s', time() + $resetExpiry);

            // Store the reset token (skip email if storage fails)
            $tokenStored = dbUpdate(
                "UPDATE tblUsers SET passwordResetToken = ?, passwordResetExpiry = ? WHERE userUID = ?",
                'ssi',
                [$tokenHash, $expiry, $user['userUID']]
            );

            if ($tokenStored === false)
            {
                error_log('[Go2My.Link] ERROR: Failed to store reset token for userUID=' . $user['userUID']);
                $failed++;
                continue;
            }

            // Build the reset URL
            $resetURL = 'https://go2my.link/reset-password?token=' . urlencode($token);

            // Send the breach notification email
            $emailSent = g2ml_sendEmail(
                $user['email'],
                'Security Notice — Password Reset Required — ' . $siteName,
                'breach_notification',
                [
                    'firstName' => $user['firstName'] ?: ($user['displayName'] ?: 'User'),
                    'reason'    => $reason,
                    'resetURL'  => $resetURL,
                    'breachAt'  => date('j M Y, H:i T'),
                ],
                'Urgent: Your password has been reset for security reasons. Please set a new password.'
            );

            if ($emailSent)
            {
                $sent++;
            }
            else
            {
                $failed++;
            }
        }

        $offset += $batchSize;

        // Log batch progress
        error_log('[Go2My.Link] Breach response: processed batch offset=' . $offset . ', sent=' . $sent . ', failed=' . $failed);
    }

    return ['sent' => $sent, 'failed' => $failed];
}

// ============================================================================
// 🔑 Rotate ENCRYPTION_SALT
// ============================================================================

/**
 * Rotate the encryption key by re-encrypting all sensitive settings.
 *
 * Reads all settings where isSensitive = 1, decrypts each with the current
 * ENCRYPTION_SALT, then re-encrypts with the new salt and updates the DB.
 *
 * IMPORTANT: After calling this function, the auth_creds.php file must be
 * manually updated with the new ENCRYPTION_SALT value. Until that happens,
 * the application will not be able to decrypt settings on the next request.
 *
 * @param  string $newSalt  The new 64-character hex ENCRYPTION_SALT
 * @return array            ['success' => bool, 'rotated' => int, 'failed' => int, 'error' => string]
 */
function g2ml_rotateEncryptionSalt(string $newSalt): array
{
    // Validate the new salt format (64 hex characters = 32 bytes)
    if (!preg_match('/^[0-9a-fA-F]{64}$/', $newSalt))
    {
        return [
            'success' => false,
            'rotated' => 0,
            'failed'  => 0,
            'error'   => 'New ENCRYPTION_SALT must be exactly 64 hexadecimal characters.',
        ];
    }

    // Get the current salt
    if (!defined('ENCRYPTION_SALT'))
    {
        return [
            'success' => false,
            'rotated' => 0,
            'failed'  => 0,
            'error'   => 'Current ENCRYPTION_SALT is not defined.',
        ];
    }

    $oldSalt = ENCRYPTION_SALT;

    // Fetch all sensitive settings
    $sensitiveSettings = dbSelect(
        "SELECT settingUID, settingID, settingValue
         FROM tblSettings
         WHERE isSensitive = 1 AND settingValue IS NOT NULL AND settingValue != ''",
        '',
        []
    );

    if ($sensitiveSettings === false || count($sensitiveSettings) === 0)
    {
        return [
            'success' => true,
            'rotated' => 0,
            'failed'  => 0,
            'error'   => '',
        ];
    }

    $rotated = 0;
    $failed  = 0;

    // 🛡️ Wrap in a database transaction to prevent partial rotation
    // (partial failure would leave some settings encrypted with old key, some with new)
    dbBeginTransaction();

    try
    {
        foreach ($sensitiveSettings as $setting)
        {
            // Decrypt with old key
            $plaintext = g2ml_decrypt($setting['settingValue'], $oldSalt);

            if ($plaintext === false)
            {
                error_log('[Go2My.Link] WARNING: Could not decrypt setting "' . $setting['settingID'] . '" during salt rotation — skipping.');
                $failed++;
                continue;
            }

            // Re-encrypt with new key
            $newEncrypted = g2ml_encrypt($plaintext, $newSalt);

            // 🛡️ Clear plaintext from memory immediately after re-encryption
            $plaintext = str_repeat("\0", strlen($plaintext));
            unset($plaintext);

            if ($newEncrypted === false)
            {
                error_log('[Go2My.Link] ERROR: Could not re-encrypt setting "' . $setting['settingID'] . '" with new salt.');
                $failed++;
                continue;
            }

            // Update the database
            $updateResult = dbUpdate(
                "UPDATE tblSettings SET settingValue = ? WHERE settingUID = ?",
                'si',
                [$newEncrypted, $setting['settingUID']]
            );

            if ($updateResult !== false)
            {
                $rotated++;
            }
            else
            {
                $failed++;
            }
        }

        // Only commit if all settings were rotated successfully
        if ($failed > 0)
        {
            dbRollback();
            error_log('[Go2My.Link] CRITICAL: Salt rotation rolled back due to ' . $failed . ' failure(s).');
        }
        else
        {
            dbCommit();
        }
    }
    catch (\Throwable $e)
    {
        dbRollback();
        error_log('[Go2My.Link] CRITICAL: Salt rotation failed, rolled back: ' . $e->getMessage());

        return [
            'success' => false,
            'rotated' => 0,
            'failed'  => count($sensitiveSettings),
            'error'   => 'Salt rotation failed and was rolled back.',
        ];
    }

    return [
        'success' => ($failed === 0),
        'rotated' => $rotated,
        'failed'  => $failed,
        'error'   => ($failed > 0) ? $failed . ' setting(s) failed to re-encrypt. Changes were rolled back.' : '',
    ];
}

// ============================================================================
// 📋 Breach Response Audit Log
// ============================================================================

/**
 * Log a breach response action to a dedicated audit file.
 *
 * Writes to a JSON-lines file at web/_logs/breach_response.log in addition
 * to the standard activity log. This provides a tamper-evident separate
 * audit trail for breach response actions.
 *
 * @param  int    $adminUID  The admin who triggered the action
 * @param  string $action    Action type (initiated, completed, denied, etc.)
 * @param  array  $details   Additional details to log
 * @return void
 */
function g2ml_logBreachResponse(int $adminUID, string $action, array $details = []): void
{
    $logEntry = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'adminUID'  => $adminUID,
        'action'    => $action,
        'ip'        => function_exists('g2ml_getClientIP') ? g2ml_getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        'details'   => $details,
    ];

    // Determine log directory
    if (defined('G2ML_ROOT'))
    {
        $logDir = G2ML_ROOT . DIRECTORY_SEPARATOR . '_logs';
    }
    else
    {
        $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_logs';
    }

    // Create log directory if it doesn't exist (explicit error handling for audit integrity)
    if (!is_dir($logDir) && !mkdir($logDir, 0750, true))
    {
        error_log('[Go2My.Link] CRITICAL: Cannot create breach response log directory: ' . $logDir);
    }

    $logFile = $logDir . DIRECTORY_SEPARATOR . 'breach_response.log';

    // Append JSON-lines format (explicit error handling for audit integrity)
    $written = file_put_contents(
        $logFile,
        json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );

    if ($written === false)
    {
        error_log('[Go2My.Link] CRITICAL: Cannot write to breach response audit log: ' . $logFile);
    }

    // Also log to error_log for server-level visibility
    error_log('[Go2My.Link] BREACH RESPONSE: ' . $action . ' by adminUID=' . $adminUID . ' — ' . json_encode($details, JSON_UNESCAPED_SLASHES));
}
