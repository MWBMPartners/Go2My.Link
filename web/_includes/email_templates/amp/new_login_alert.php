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
 * ⚡ Go2My.Link — AMP Email Template: New Login Alert
 * ============================================================================
 *
 * AMP4Email variant of the new login alert template. Sent as a security
 * notification when a login is detected from a new device or IP address.
 * Includes details box (device + IP + when) and two CTA buttons (Change
 * Password + Review Sessions).
 *
 * Available variables (from $data):
 *   $firstName   — User's first name
 *   $deviceInfo  — Parsed device description (e.g., "Chrome on Windows")
 *   $ipAddress   — IP address of the login
 *   $loginAt     — Date/time of the login (formatted string)
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
$deviceInfo  = $deviceInfo ?? 'Unknown device';
$ipAddress   = $ipAddress ?? 'Unknown';
$loginAt     = $loginAt ?? date('j M Y, H:i T');
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
        /* CTA Buttons */
        .cta-group { margin: 20px 0; }
        .cta-btn { display: inline-block; padding: 12px 24px; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 6px; margin-right: 10px; margin-bottom: 10px; }
        .cta-btn-danger { background-color: #dc3545; }
        .cta-btn-secondary { background-color: #6c757d; }
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
                <h2>New Login to Your Account</h2>

                <p>Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    We detected a new sign-in to your
                    <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> account.
                    Here are the details:
                </p>

                <div class="details">
                    <p>
                        <strong>Device:</strong> <?php echo htmlspecialchars($deviceInfo, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <strong>When:</strong> <?php echo htmlspecialchars($loginAt, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>

                <p>If this was you, no action is needed.</p>

                <p class="text-danger">
                    <strong>If this wasn't you</strong>, please change your password immediately
                    and review your active sessions.
                </p>

                <div class="cta-group">
                    <a href="<?php echo htmlspecialchars($siteURL . '/forgot-password', ENT_QUOTES, 'UTF-8'); ?>" class="cta-btn cta-btn-danger">
                        Change Password
                    </a>
                    <a href="https://admin.go2my.link/profile/sessions" class="cta-btn cta-btn-secondary">
                        Review Sessions
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
