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
 * ⚡ Go2My.Link — AMP Email Template: Data Deletion Requested
 * ============================================================================
 *
 * AMP4Email variant of the data deletion requested template. Sent when a user
 * requests deletion of their account/data. Features a RED header, alert box
 * with bullet list of consequences, cancel CTA, and grace period info.
 *
 * Available variables (from $data):
 *   $displayName — User's display name
 *   $graceDays   — Days before deletion is executed
 *   $cancelURL   — Link to cancel the deletion request
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
$graceDays   = $graceDays ?? 30;
$cancelURL   = $cancelURL ?? '#';
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
        /* Header — RED for deletion */
        .header { background-color: #dc3545; padding: 30px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        /* Body */
        .content { padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .content h2 { margin: 0 0 20px; color: #212529; font-size: 20px; }
        .content p { margin: 0 0 20px; color: #495057; font-size: 16px; line-height: 1.6; }
        .text-secondary { color: #6c757d; font-size: 14px; }
        /* Alert boxes */
        .alert-danger { background-color: #f8d7da; border-radius: 6px; padding: 16px 20px; margin: 0 0 20px; }
        .alert-danger p { color: #842029; font-size: 14px; margin: 0 0 8px; }
        .alert-danger p:last-child { margin-bottom: 0; }
        .alert-danger ul { margin: 0; padding: 0 0 0 20px; color: #842029; font-size: 14px; line-height: 1.8; }
        .alert-danger li { color: #842029; }
        /* CTA Button */
        .cta { margin: 30px 0; }
        .cta a { display: inline-block; padding: 14px 32px; background-color: #6c757d; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; }
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
                <h2>Data Deletion Request Received</h2>

                <p>Hi <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>,</p>

                <p>
                    We've received your request to delete your account and all associated data.
                </p>

                <div class="alert-danger">
                    <p><strong>What happens next:</strong></p>
                    <ul>
                        <li>Your account will be scheduled for deletion</li>
                        <li>You have <strong><?php echo (int) $graceDays; ?> days</strong> to cancel this request</li>
                        <li>After the grace period, all data will be permanently removed</li>
                        <li>This action is <strong>irreversible</strong> once completed</li>
                    </ul>
                </div>

                <p>Changed your mind? You can cancel this request within the grace period:</p>

                <div class="cta">
                    <a href="<?php echo htmlspecialchars($cancelURL, ENT_QUOTES, 'UTF-8'); ?>">
                        Cancel Deletion Request
                    </a>
                </div>

                <p class="text-secondary">
                    If you didn't make this request, please log in and cancel it immediately,
                    then change your password.
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
