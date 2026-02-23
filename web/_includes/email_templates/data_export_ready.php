<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Email Template: Data Export Ready
 * ============================================================================
 *
 * Sent when a user's data export has been generated and is ready to download.
 *
 * Available variables (from $data):
 *   $displayName  — User's display name
 *   $downloadURL  — Full download link
 *   $expiryHours  — Hours until download expires
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
$downloadURL = $downloadURL ?? '#';
$expiryHours = $expiryHours ?? 48;
$siteName    = $siteName ?? 'Go2My.Link';
$siteURL     = $siteURL ?? 'https://go2my.link';
$currentYear = $currentYear ?? date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Export Ready — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
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
                                Your Data Export is Ready
                            </h2>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Hi <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>,
                            </p>

                            <p style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                Your data export has been generated and is ready for download. This file contains
                                all personal data we hold about your account in JSON format.
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td style="background-color:#0d6efd; border-radius:6px;">
                                        <a href="<?php echo htmlspecialchars($downloadURL, ENT_QUOTES, 'UTF-8'); ?>"
                                           style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:600;">
                                            Download Your Data
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:#fff3cd; border-radius:6px; width:100%;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0; color:#856404; font-size:14px;">
                                            <strong>Important:</strong> This download link expires in
                                            <?php echo (int) $expiryHours; ?> hours. After that, you'll need
                                            to request a new export.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; color:#6c757d; font-size:14px; line-height:1.6;">
                                If you didn't request this export, please secure your account immediately
                                by changing your password.
                            </p>
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
