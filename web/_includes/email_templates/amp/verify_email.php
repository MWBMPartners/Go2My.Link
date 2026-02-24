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
 * ⚡ Go2My.Link — AMP Email Template: Verify Email Address
 * ============================================================================
 *
 * AMP4Email variant of the verify email template. Sent after user registration
 * to verify their email address. Includes CTA button, fallback link, and
 * 24-hour expiry notice.
 *
 * Available variables (from $data):
 *   $firstName       — User's first name
 *   $verificationURL — Full verification link with token
 *   $siteName        — Site name (auto-injected)
 *   $siteURL         — Site URL (auto-injected)
 *   $currentYear     — Current year (auto-injected)
 *   $preheader       — Optional preview text (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

// 🛡️ Ensure variables exist
$firstName       = $firstName ?? 'there';
$verificationURL = $verificationURL ?? '#';
$siteName        = $siteName ?? 'Go2My.Link';
$siteURL         = $siteURL ?? 'https://go2my.link';
$currentYear     = $currentYear ?? date('Y');
$preheader       = $preheader ?? '';
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
        /* CTA Button */
        .cta { margin: 30px 0; }
        .cta a { display: inline-block; padding: 14px 32px; background-color: #0d6efd; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; }
        /* Link fallback */
        .fallback-link { color: #0d6efd; font-size: 14px; word-break: break-all; }
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
                <h2>Verify Your Email Address</h2>

                <p>Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    Thanks for signing up for <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>!
                    Please verify your email address by clicking the button below.
                </p>

                <div class="cta">
                    <a href="<?php echo htmlspecialchars($verificationURL, ENT_QUOTES, 'UTF-8'); ?>">
                        Verify Email Address
                    </a>
                </div>

                <p class="text-secondary">
                    If the button doesn't work, copy and paste this link into your browser:
                </p>
                <p class="fallback-link">
                    <?php echo htmlspecialchars($verificationURL, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <p class="text-secondary">
                    This link will expire in 24 hours. If you didn't create an account,
                    you can safely ignore this email.
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
