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
 * 📧 Go2My.Link — Email Template: Password Reset
 * ============================================================================
 *
 * Sent when a user requests a password reset via the forgot-password page.
 *
 * Available variables (from $data):
 *   $firstName — User's first name
 *   $resetURL  — Full password reset link with token
 *   $siteName  — Site name (auto-injected)
 *   $siteURL   — Site URL (auto-injected)
 *   $currentYear — Current year (auto-injected)
 *   $preheader — Optional preview text (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      Phase 4
 * ============================================================================
 */

// 🛡️ Ensure variables exist
$firstName   = $firstName ?? 'there';
$resetURL    = $resetURL ?? '#';
$siteName    = $siteName ?? 'Go2My.Link';
$siteURL     = $siteURL ?? 'https://go2my.link';
$currentYear = $currentYear ?? date('Y');
$preheader   = $preheader ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <!--[if !mso]><!-->
    <style>
        @media (prefers-color-scheme: dark) {
            body, .g2ml-body { background-color: #1a1a2e !important; }
            .g2ml-card { background-color: #2d2d44 !important; }
            .g2ml-header { background-color: #0a58ca !important; }
            .g2ml-heading { color: #e0e0e0 !important; }
            .g2ml-text { color: #b0b0c0 !important; }
            .g2ml-text-secondary { color: #8890a0 !important; }
            .g2ml-details { background-color: #3d3d55 !important; }
            .g2ml-details .g2ml-text { color: #b0b0c0 !important; }
            .g2ml-footer { background-color: #1a1a2e !important; border-top-color: #3d3d55 !important; }
            .g2ml-footer-text, .g2ml-footer-text a { color: #8890a0 !important; }
            .g2ml-link { color: #6ea8fe !important; }
            .g2ml-alert-danger { background-color: #2c0b0e !important; }
            .g2ml-alert-danger * { color: #f5c2c7 !important; }
            .g2ml-alert-warning { background-color: #332701 !important; }
            .g2ml-alert-warning * { color: #ffda6a !important; }
        }
    </style>
    <!--<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f8f9fa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <?php if (!empty($preheader)): ?>
    <div style="display:none;font-size:1px;color:#f8f9fa;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        <?php echo htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8'); ?>
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>
    <?php endif; ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="g2ml-body" style="background-color:#f8f9fa; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="g2ml-card" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td class="g2ml-header" style="background-color:#0d6efd; padding:30px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:700;">
                                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 class="g2ml-heading" style="margin:0 0 20px; color:#212529; font-size:20px;">
                                Reset Your Password
                            </h2>

                            <p class="g2ml-text" style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,
                            </p>

                            <p class="g2ml-text" style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                We received a request to reset the password on your <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> account. Click the button below to choose a new password.
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td style="background-color:#0d6efd; border-radius:6px;">
                                        <a href="<?php echo htmlspecialchars($resetURL, ENT_QUOTES, 'UTF-8'); ?>"
                                           style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:600;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p class="g2ml-text-secondary" style="margin:0 0 20px; color:#6c757d; font-size:14px; line-height:1.6;">
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p class="g2ml-link" style="margin:0 0 20px; color:#0d6efd; font-size:14px; word-break:break-all;">
                                <?php echo htmlspecialchars($resetURL, ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                            <p class="g2ml-text-secondary" style="margin:0 0 10px; color:#6c757d; font-size:14px; line-height:1.6;">
                                This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email — your password will remain unchanged.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="g2ml-footer" style="background-color:#f8f9fa; padding:20px 40px; text-align:center; border-top:1px solid #dee2e6;">
                            <p class="g2ml-footer-text" style="margin:0; color:#5a6268; font-size:12px;">
                                &copy; <?php echo htmlspecialchars($currentYear, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> — MWBM Partners Ltd.
                                <br>
                                <a href="<?php echo htmlspecialchars($siteURL, ENT_QUOTES, 'UTF-8'); ?>" style="color:#5a6268;">
                                    <?php echo htmlspecialchars($siteURL, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
