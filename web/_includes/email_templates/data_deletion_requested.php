<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Email Template: Data Deletion Requested
 * ============================================================================
 *
 * Sent when a user requests deletion of their account/data.
 * Includes grace period info and cancel link.
 *
 * Available variables (from $data):
 *   $displayName  — User's display name
 *   $graceDays    — Days before deletion is executed
 *   $cancelURL    — Link to cancel the deletion request
 *   $siteName     — Site name (auto-injected)
 *   $siteURL      — Site URL (auto-injected)
 *   $currentYear  — Current year (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

$displayName = $displayName ?? 'User';
$graceDays   = $graceDays ?? 30;
$cancelURL   = $cancelURL ?? '#';
$siteName    = $siteName ?? 'Go2My.Link';
$siteURL     = $siteURL ?? 'https://go2my.link';
$currentYear = $currentYear ?? date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Deletion Request — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f8f9fa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa; padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#dc3545; padding:30px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:700;">
                                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="margin:0 0 20px; color:#212529; font-size:20px;">
                                Data Deletion Request Received
                            </h2>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Hi <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>,
                            </p>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                We've received your request to delete your account and all associated data.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:#f8d7da; border-radius:6px; width:100%;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px; color:#842029; font-size:14px;">
                                            <strong>What happens next:</strong>
                                        </p>
                                        <ul style="margin:0; padding:0 0 0 20px; color:#842029; font-size:14px; line-height:1.8;">
                                            <li>Your account will be scheduled for deletion</li>
                                            <li>You have <strong><?php echo (int) $graceDays; ?> days</strong> to cancel this request</li>
                                            <li>After the grace period, all data will be permanently removed</li>
                                            <li>This action is <strong>irreversible</strong> once completed</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Changed your mind? You can cancel this request within the grace period:
                            </p>

                            <!-- Cancel Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td style="background-color:#6c757d; border-radius:6px;">
                                        <a href="<?php echo htmlspecialchars($cancelURL, ENT_QUOTES, 'UTF-8'); ?>"
                                           style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:600;">
                                            Cancel Deletion Request
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#6c757d; font-size:14px; line-height:1.6;">
                                If you didn't make this request, please log in and cancel it immediately,
                                then change your password.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f9fa; padding:20px 40px; text-align:center; border-top:1px solid #dee2e6;">
                            <p style="margin:0; color:#5a6268; font-size:12px;">
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
