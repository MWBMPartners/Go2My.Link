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
 * ⚡ Go2My.Link — AMP Email Template: Data Export Ready
 * ============================================================================
 *
 * AMP4Email variant of the data export ready template. Sent when a user's data
 * export has been generated and is ready to download. Includes download CTA
 * and warning box about expiry.
 *
 * Available variables (from $data):
 *   $displayName — User's display name
 *   $downloadURL — Full download link
 *   $expiryHours — Hours until download expires
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
$displayName = $displayName ?? 'User';
$downloadURL = $downloadURL ?? '#';
$expiryHours = $expiryHours ?? 48;
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
        /* Alert boxes */
        .alert-warning { background-color: #fff3cd; border-radius: 6px; padding: 16px 20px; margin: 0 0 20px; }
        .alert-warning p { color: #856404; font-size: 14px; margin: 0; }
        /* CTA Button */
        .cta { margin: 30px 0; }
        .cta a { display: inline-block; padding: 14px 32px; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; }
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
                <h2>Your Data Export is Ready</h2>

                <p>Hi <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    Your data export has been generated and is ready for download. This file
                    contains all personal data we hold about your account in JSON format.
                </p>

                <div class="cta">
                    <a href="<?php echo htmlspecialchars($downloadURL, ENT_QUOTES, 'UTF-8'); ?>">
                        Download Your Data
                    </a>
                </div>

                <div class="alert-warning">
                    <p>
                        <strong>Important:</strong> This download link expires in
                        <?php echo (int) $expiryHours; ?> hours. After that, you'll need
                        to request a new export.
                    </p>
                </div>

                <p class="text-secondary">
                    If you didn't request this export, please secure your account immediately
                    by changing your password.
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
