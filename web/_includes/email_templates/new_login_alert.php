<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Email Template: New Login Alert
 * ============================================================================
 *
 * Sent as a security notification when a login is detected from a new
 * device or IP address that hasn't been seen before.
 *
 * Available variables (from $data):
 *   $firstName  — User's first name
 *   $deviceInfo — Parsed device description (e.g., "Chrome on Windows")
 *   $ipAddress  — IP address of the login
 *   $loginAt    — Date/time of the login (formatted string)
 *   $siteName   — Site name (auto-injected)
 *   $siteURL    — Site URL (auto-injected)
 *   $currentYear — Current year (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    0.5.0
 * @since      Phase 4
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Login Detected — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f8f9fa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#0d6efd; padding:30px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:700;">
                                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 20px; color:#212529; font-size:20px;">
                                New Login to Your Account
                            </h2>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Hi <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>,
                            </p>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                We detected a new sign-in to your <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> account. Here are the details:
                            </p>

                            <!-- Details Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa; border-radius:6px; margin:20px 0;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 8px; color:#495057; font-size:14px;">
                                            <strong>Device:</strong> <?php echo htmlspecialchars($deviceInfo, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p style="margin:0 0 8px; color:#495057; font-size:14px;">
                                            <strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p style="margin:0; color:#495057; font-size:14px;">
                                            <strong>When:</strong> <?php echo htmlspecialchars($loginAt, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                If this was you, no action is needed.
                            </p>

                            <p style="margin:0 0 10px; color:#dc3545; font-size:14px; line-height:1.6;">
                                <strong>If this wasn't you</strong>, please change your password immediately and review your active sessions.
                            </p>

                            <!-- CTA Buttons -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
                                <tr>
                                    <td style="background-color:#dc3545; border-radius:6px; margin-right:10px;">
                                        <a href="<?php echo htmlspecialchars($siteURL . '/forgot-password', ENT_QUOTES, 'UTF-8'); ?>"
                                           style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                                            Change Password
                                        </a>
                                    </td>
                                    <td style="width:10px;">&nbsp;</td>
                                    <td style="background-color:#6c757d; border-radius:6px;">
                                        <a href="https://admin.go2my.link/profile/sessions"
                                           style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                                            Review Sessions
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f9fa; padding:20px 40px; text-align:center; border-top:1px solid #dee2e6;">
                            <p style="margin:0; color:#6c757d; font-size:12px;">
                                &copy; <?php echo htmlspecialchars($currentYear, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> — MWBM Partners Ltd.
                                <br>
                                <a href="<?php echo htmlspecialchars($siteURL, ENT_QUOTES, 'UTF-8'); ?>" style="color:#6c757d;">
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
