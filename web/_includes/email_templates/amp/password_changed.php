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
 * ⚡ Go2My.Link — AMP Email Template: Password Changed Notification
 * ============================================================================
 *
 * AMP4Email variant of the password changed notification template. Sent as a
 * security notification when a user's password is changed (via profile settings
 * or password reset). Includes details box (when + IP), red warning, and reset
 * CTA button.
 *
 * Available variables (from $data):
 *   $firstName   — User's first name
 *   $changedAt   — Date/time the password was changed (formatted string)
 *   $ipAddress   — IP address of the request
 *   $siteName    — Site name (auto-injected)
 *   $siteURL     — Site URL (auto-injected)
 *   $currentYear — Current year (auto-injected)
 *   $preheader   — Optional preview text (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

// 🛡️ Ensure variables exist
$firstName   = $firstName ?? 'there';
$changedAt   = $changedAt ?? date('j M Y, H:i T');
$ipAddress   = $ipAddress ?? 'Unknown';
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
        /* Layout */
        body { margin: 0; padding: 0; background-color: #f8f9fa; }
        .wrapper { padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; }
        /* Header */
        .header { background-color: #0d6efd; padding: 30px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        /* Body */
        .content { padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .content h2 { margin: 0 0 20px; color: #212529; font-size: 20px; }
        .content p { margin: 0 0 20px; color: #495057; font-size: 16px; line-height: 1.6; }
        .text-secondary { color: #6c757d; font-size: 14px; }
        .text-danger { color: #dc3545; font-size: 14px; }
        /* Details box */
        .details { background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin: 20px 0; }
        .details p { margin: 0 0 8px; color: #495057; font-size: 14px; }
        .details p:last-child { margin-bottom: 0; }
        /* CTA Button */
        .cta { margin: 20px 0; }
        .cta a { display: inline-block; padding: 12px 24px; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 6px; }
        .cta-danger a { background-color: #dc3545; }
        /* Footer */
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
                <h2>Your Password Has Been Changed</h2>

                <p>Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    This is a confirmation that the password for your
                    <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> account
                    has been successfully changed.
                </p>

                <div class="details">
                    <p>
                        <strong>When:</strong> <?php echo htmlspecialchars($changedAt, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>

                <p class="text-danger">
                    <strong>If you did not make this change</strong>, please reset your password
                    immediately and contact our support team.
                </p>

                <div class="cta cta-danger">
                    <a href="<?php echo htmlspecialchars($siteURL . '/forgot-password', ENT_QUOTES, 'UTF-8'); ?>">
                        Reset Password
                    </a>
                </div>
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
