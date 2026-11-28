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
 * 📧 Go2My.Link — Email Template: Contact Form Message
 * ============================================================================
 *
 * Sent to the support inbox (contact.email_recipient) whenever a visitor
 * submits the public contact form (web/Go2My.Link/public_html/pages/contact/
 * index.php). Added by #212, replacing a raw @mail() call that built its own
 * plain-text body; this template goes through the shared g2ml_sendEmail()
 * pipeline instead, so the message gets the same multipart HTML/plain-text
 * body and header defences as every other outgoing email.
 *
 * Every value below comes from the PUBLIC contact form, filled in by an
 * anonymous visitor — none of it is trusted. Each one is escaped with
 * htmlspecialchars() before being printed, exactly like password_changed.php
 * (this template's structural model) escapes its own $firstName/$ipAddress.
 * $contactMessage additionally goes through nl2br() AFTER escaping, so a
 * blank line the visitor typed still shows as a blank line without ever
 * letting raw HTML in the message reach the page — see the note above that
 * line below for why the order (escape, then nl2br) matters.
 *
 * The visitor's IP address is deliberately NOT a variable this template
 * accepts. The page that renders this template used to put it in the email
 * body directly; #212 removed that as unnecessary data minimisation — the
 * message does not need it.
 *
 * Available variables (from $data):
 *   $contactName    — Visitor's given name (untrusted, escaped below)
 *   $contactEmail   — Visitor's email address (untrusted, escaped below;
 *                      also passed as this email's Reply-To — see
 *                      web/_functions/email.php's g2ml_sendEmail())
 *   $contactSubject — Visitor's subject line, may be '' (untrusted, escaped)
 *   $contactMessage — Visitor's message body (untrusted, escaped + nl2br)
 *   $siteName       — Site name (auto-injected)
 *   $siteURL        — Site URL (auto-injected)
 *   $currentYear    — Current year (auto-injected)
 *   $preheader      — Optional preview text (auto-injected)
 *
 * @package    Go2My.Link
 * @subpackage EmailTemplates
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#212)
 * ============================================================================
 */

// 🛡️ Ensure variables exist — a template is rendered with extract($data),
// so any key the caller omitted simply never becomes a variable here.
$contactName    = $contactName ?? '';
$contactEmail   = $contactEmail ?? '';
$contactSubject = $contactSubject ?? '';
$contactMessage = $contactMessage ?? '';
$siteName       = $siteName ?? 'Go2My.Link';
$siteURL        = $siteURL ?? 'https://go2my.link';
if (!isset($currentYear))
{
    $currentYear = date('Y');
}
$preheader = $preheader ?? '';

// A blank subject reads better as "(no subject)" than as an empty heading.
if (trim($contactSubject) === '')
{
    $displaySubject = '(no subject)';
}
else
{
    $displaySubject = $contactSubject;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        }
    </style>
    <!--<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f8f9fa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <?php if (!empty($preheader)) { ?>
    <div style="display:none;font-size:1px;color:#f8f9fa;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        <?php echo htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8'); ?>
        &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>
    <?php } ?>
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
                                New Contact Form Message
                            </h2>

                            <!-- Sender details box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="g2ml-details" style="background-color:#f8f9fa; border-radius:6px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p class="g2ml-text" style="margin:0 0 8px; color:#495057; font-size:14px;">
                                            <strong>From:</strong> <?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p class="g2ml-text" style="margin:0 0 8px; color:#495057; font-size:14px;">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p class="g2ml-text" style="margin:0; color:#495057; font-size:14px;">
                                            <strong>Subject:</strong> <?php echo htmlspecialchars($displaySubject, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p class="g2ml-text" style="margin:0 0 8px; color:#495057; font-size:16px; line-height:1.6;">
                                <strong>Message:</strong>
                            </p>
                            <p class="g2ml-text" style="margin:0 0 20px; color:#495057; font-size:16px; line-height:1.6;">
                                <?php
                                // Escape FIRST, then convert newlines to <br> — never the other
                                // way round. nl2br() before escaping would insert real <br>
                                // tags and then htmlspecialchars() would turn every one of them
                                // into visible "&lt;br /&gt;" text instead of a line break; doing
                                // it in this order keeps the visitor's line breaks as line
                                // breaks while every other character in their message stays
                                // inert text, never markup.
                                echo nl2br(htmlspecialchars($contactMessage, ENT_QUOTES, 'UTF-8'));
                                ?>
                            </p>

                            <p class="g2ml-text-secondary" style="margin:0; color:#6c757d; font-size:13px; line-height:1.5;">
                                Reply directly to this email to respond to <?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?> — the Reply-To address on this message is theirs, not <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>'s own.
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
