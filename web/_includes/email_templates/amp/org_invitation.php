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
 * ⚡ Go2My.Link — AMP Email Template: Organisation Invitation
 * ============================================================================
 *
 * AMP4Email variant of the organisation invitation template. Sent when an org
 * Admin or GlobalAdmin invites someone to join their organisation. Includes
 * details box, green Accept button, fallback link, and expiry notice.
 *
 * Available variables (from $data):
 *   $orgName      — Organisation display name
 *   $inviterName  — Name of the person who sent the invitation
 *   $inviterEmail — Email of the inviter
 *   $role         — Role being assigned ('User' or 'Admin')
 *   $acceptURL    — Full acceptance link with token
 *   $expiryDays   — Number of days until token expires
 *   $siteName     — Site name (auto-injected)
 *   $siteURL      — Site URL (auto-injected)
 *   $currentYear  — Current year (auto-injected)
 *   $preheader    — Optional preview text (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

// 🛡️ Ensure variables exist
$orgName      = $orgName ?? 'an organisation';
$inviterName  = $inviterName ?? 'Someone';
$inviterEmail = $inviterEmail ?? '';
$role         = $role ?? 'User';
$acceptURL    = $acceptURL ?? '#';
$expiryDays   = $expiryDays ?? 7;
$siteName     = $siteName ?? 'Go2My.Link';
$siteURL      = $siteURL ?? 'https://go2my.link';
$currentYear  = $currentYear ?? date('Y');
$preheader    = $preheader ?? '';
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
        /* Details box */
        .details { background-color: #f8f9fa; border-radius: 6px; padding: 16px 20px; margin: 0 0 20px; }
        .details p { margin: 0 0 8px; color: #6c757d; font-size: 14px; }
        .details p:last-child { margin-bottom: 0; }
        /* CTA Button */
        .cta { margin: 30px 0; }
        .cta a { display: inline-block; padding: 14px 32px; background-color: #198754; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; }
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
                <h2>You've Been Invited!</h2>

                <p>
                    <strong><?php echo htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8'); ?></strong>
                    has invited you to join
                    <strong><?php echo htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8'); ?></strong>
                    on <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>.
                </p>

                <div class="details">
                    <p>
                        <strong>Organisation:</strong>
                        <?php echo htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <strong>Your Role:</strong>
                        <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p>
                        <strong>Invited By:</strong>
                        <?php echo htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($inviterEmail !== '') { ?>
                        (<?php echo htmlspecialchars($inviterEmail, ENT_QUOTES, 'UTF-8'); ?>)
                        <?php } ?>
                    </p>
                </div>

                <div class="cta">
                    <a href="<?php echo htmlspecialchars($acceptURL, ENT_QUOTES, 'UTF-8'); ?>">
                        Accept Invitation
                    </a>
                </div>

                <p class="text-secondary">
                    If the button doesn't work, copy and paste this link into your browser:
                </p>
                <p class="fallback-link">
                    <?php echo htmlspecialchars($acceptURL, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <p class="text-secondary">
                    This invitation expires in <?php echo (int) $expiryDays; ?> days. If you don't have a
                    <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> account, you'll be asked
                    to create one first.
                </p>

                <p class="text-secondary">
                    If you weren't expecting this invitation, you can safely ignore this email.
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
