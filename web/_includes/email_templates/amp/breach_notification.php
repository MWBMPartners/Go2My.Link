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
 * ⚡ Go2My.Link — AMP Email Template: Breach Notification
 * ============================================================================
 *
 * AMP variant of the breach notification email. Sent during mass credential
 * reset. Uses red header to indicate urgent security action required.
 *
 * Available variables (from $data):
 *   $firstName  — User's first name
 *   $reason     — Admin-provided reason for the breach response
 *   $resetURL   — Individual password reset link with token
 *   $breachAt   — Date/time the breach response was triggered
 *   $siteName   — Site name (auto-injected)
 *   $siteURL    — Site URL (auto-injected)
 *   $currentYear — Current year (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

// 🛡️ Ensure variables exist
$firstName   = $firstName ?? 'User';
$reason      = $reason ?? 'a security precaution';
$resetURL    = $resetURL ?? '#';
$breachAt    = $breachAt ?? date('j M Y, H:i T');
$siteName    = $siteName ?? 'Go2My.Link';
$siteURL     = $siteURL ?? 'https://go2my.link';
$currentYear = $currentYear ?? date('Y');
$preheader   = $preheader ?? '';
?>
<!doctype html>
<html ⚡4email data-css-strict>
<head>
    <meta charset="utf-8">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <style amp4email-boilerplate>body{visibility:hidden}</style>
    <style amp-custom>
        body { margin: 0; padding: 0; background-color: #f8f9fa; }
        .wrapper { padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background-color: #dc3545; padding: 30px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .content { padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .content h2 { margin: 0 0 20px; color: #212529; font-size: 20px; }
        .content p { margin: 0 0 20px; color: #495057; font-size: 16px; line-height: 1.6; }
        .text-secondary { color: #6c757d; font-size: 14px; }
        .alert-danger { background-color: #f8d7da; border-radius: 6px; padding: 16px 20px; margin: 0 0 20px; }
        .alert-danger p { color: #842029; font-size: 14px; margin: 0 0 8px; }
        .alert-danger p:last-child { margin-bottom: 0; }
        .cta { margin: 30px 0; }
        .cta a { display: inline-block; padding: 14px 32px; background-color: #dc3545; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; }
        .fallback-link { color: #0d6efd; font-size: 14px; word-break: break-all; }
        .footer { background-color: #f8f9fa; padding: 20px 40px; text-align: center; border-top: 1px solid #dee2e6; }
        .footer p { margin: 0; color: #5a6268; font-size: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .footer a { color: #5a6268; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="content">
                <h2>Security Notice — Password Reset Required</h2>

                <p>Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    As a security precaution, we have reset all user passwords on
                    <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>.
                    Your previous password has been invalidated and all active sessions
                    have been terminated.
                </p>

                <div class="alert-danger">
                    <p><strong>Reason:</strong> <?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>When:</strong> <?php echo htmlspecialchars($breachAt, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <p>
                    <strong>Please reset your password as soon as possible</strong> by clicking
                    the button below. You will not be able to log in until you set a new password.
                </p>

                <div class="cta">
                    <a href="<?php echo htmlspecialchars($resetURL, ENT_QUOTES, 'UTF-8'); ?>">
                        Reset Your Password
                    </a>
                </div>

                <p class="text-secondary">
                    If the button doesn't work, copy and paste this link into your browser:
                </p>
                <p class="fallback-link">
                    <?php echo htmlspecialchars($resetURL, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <p class="text-secondary">
                    When choosing a new password, please use a unique password that you don't
                    use on any other website. We recommend using a password manager.
                </p>

                <p class="text-secondary">
                    If you have any questions or concerns, please contact our support team
                    at support@go2my.link.
                </p>
            </div>
            <div class="footer">
                <p>
                    &copy; <?php echo htmlspecialchars($currentYear, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> — MWBM Partners Ltd.
                    <br>
                    <a href="<?php echo htmlspecialchars($siteURL, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($siteURL, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
