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
 * 📧 Go2My.Link — Email Functions
 * ============================================================================
 *
 * Sends transactional emails using PHP mail() with multipart MIME support.
 * Supports three MIME parts: text/plain, text/html, and text/x-amp-html (AMP).
 *
 * Templates are loaded from web/_includes/email_templates/ via output
 * buffering with data extraction. AMP variants live in email_templates/amp/.
 *
 * Dependencies: settings.php (getSetting()), activity_logger.php (logActivity()),
 *               security.php (g2ml_sanitiseOutput())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      Phase 4 (multipart MIME + AMP added Phase 7)
 *
 * 📖 References:
 *     - PHP mail(): https://www.php.net/manual/en/function.mail.php
 *     - MIME multipart: https://www.rfc-editor.org/rfc/rfc2046#section-5.1.4
 *     - AMP for Email: https://amp.dev/documentation/guides-and-tutorials/learn/email-spec/amp-email-format/
 *     - List-Unsubscribe: https://www.rfc-editor.org/rfc/rfc2369
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 📧 Send Email
// ============================================================================

/**
 * Send a transactional email with multipart MIME (text/plain + text/html + AMP).
 *
 * Renders the specified template with the provided data, auto-generates a
 * plaintext fallback, optionally includes an AMP variant, then sends via
 * PHP mail() with proper multipart/alternative MIME structure.
 *
 * From address, reply-to, and sender name are pulled from tblSettings.
 *
 * @param  string $to           Recipient email address
 * @param  string $subject      Email subject line
 * @param  string $template     Template name (e.g., 'verify_email' → verify_email.php)
 * @param  array  $data         Data to pass to the template (extracted into scope)
 * @param  string $preheader    Optional preheader text (preview text in email clients)
 * @param  array  $extraHeaders Optional additional headers (key => value pairs)
 * @return bool                 True if mail() accepted the message for delivery
 *
 * 📖 Reference: https://www.php.net/manual/en/function.mail.php
 *
 * Usage example:
 *   g2ml_sendEmail('user@example.com', 'Verify Your Email', 'verify_email', [
 *       'firstName'       => 'John',
 *       'verificationURL' => 'https://go2my.link/verify-email?token=abc123',
 *   ], 'Please verify your email address to activate your account.');
 */
function g2ml_sendEmail(
    string $to,
    string $subject,
    string $template,
    array $data = [],
    string $preheader = '',
    array $extraHeaders = []
): bool
{
    // 🛡️ Validate recipient (prevent header injection via CRLF)
    if (preg_match('/[\r\n]/', $to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_sendEmail rejected invalid recipient.');
        return false;
    }

    // 🛡️ Strip CRLF from subject (prevent header injection)
    $subject = str_replace(["\r", "\n", "\0"], '', $subject);

    // 🛡️ Validate template name (prevent path traversal)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template))
    {
        error_log('[Go2My.Link] ERROR: g2ml_sendEmail rejected invalid template name.');
        return false;
    }

    // Pass preheader text to template data for the hidden preview div
    if ($preheader !== '')
    {
        $data['preheader'] = $preheader;
    }

    // Render the HTML body from the template
    $htmlBody = g2ml_renderEmailTemplate($template, $data);

    if ($htmlBody === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_sendEmail failed — template "' . $template . '" could not be rendered.');
        return false;
    }

    // Generate plaintext version from HTML
    $plainText = g2ml_htmlToPlainText($htmlBody);

    // Attempt to render AMP template (graceful fallback if missing or disabled)
    $ampBody = null;

    if (function_exists('getSetting'))
    {
        $ampEnabled = (bool) getSetting('email.amp_enabled', false);
    }
    else
    {
        $ampEnabled = false;
    }

    if ($ampEnabled)
    {
        $ampBody = g2ml_renderAmpTemplate($template, $data);
    }

    // Get email settings from database
    // 📖 Reference: settings.php → getSetting()
    if (function_exists('getSetting'))
    {
        $fromAddress    = getSetting('email.from_address', 'noreply@go2my.link');
        $fromName       = getSetting('email.from_name', 'Go2My.Link');
        $replyTo        = getSetting('email.reply_to', 'support@go2my.link');
        $unsubscribeURL = getSetting('email.list_unsubscribe_url', '');
    }
    else
    {
        $fromAddress    = 'noreply@go2my.link';
        $fromName       = 'Go2My.Link';
        $replyTo        = 'support@go2my.link';
        $unsubscribeURL = '';
    }

    // 🛡️ Strip CRLF from all header-sourced values (prevent header injection via DB)
    $fromAddress    = preg_replace('/[\r\n\0]/', '', $fromAddress);
    $fromName       = preg_replace('/[\r\n\0]/', '', $fromName);
    $replyTo        = preg_replace('/[\r\n\0]/', '', $replyTo);
    $unsubscribeURL = preg_replace('/[\r\n\0]/', '', $unsubscribeURL);

    // Generate MIME boundary and build multipart body
    $boundary  = g2ml_generateMimeBoundary();
    $emailBody = g2ml_buildMultipartBody($plainText, $htmlBody, $ampBody, $boundary);

    // Build email headers
    // 📖 Reference: https://www.php.net/manual/en/function.mail.php#refsect1-function.mail-parameters
    $headers  = "From: " . $fromName . " <" . $fromAddress . ">\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    $headers .= "X-Mailer: Go2My.Link/1.0.0\r\n";

    // Modern email headers
    // 📖 Reference: https://www.rfc-editor.org/rfc/rfc2369
    $headers .= "Precedence: bulk\r\n";
    $headers .= "Auto-Submitted: auto-generated\r\n";
    $headers .= "X-Entity-Ref-ID: " . bin2hex(random_bytes(16)) . "\r\n";

    // List-Unsubscribe header (if configured)
    if ($unsubscribeURL !== '')
    {
        $headers .= "List-Unsubscribe: <" . $unsubscribeURL . ">\r\n";
        $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
    }

    // Append any caller-supplied extra headers (with CRLF sanitisation)
    $blockedHeaders = ['from', 'to', 'cc', 'bcc', 'sender', 'return-path', 'content-type', 'mime-version'];

    foreach ($extraHeaders as $headerName => $headerValue)
    {
        // 🛡️ Strip CRLF and null bytes to prevent header injection
        $headerName  = preg_replace('/[\r\n\0]/', '', (string) $headerName);
        $headerValue = preg_replace('/[\r\n\0]/', '', (string) $headerValue);

        // 🛡️ Block dangerous headers that could enable relay abuse
        if (in_array(strtolower($headerName), $blockedHeaders, true))
        {
            error_log('[Go2My.Link] WARNING: Blocked extraHeader "' . $headerName . '" in g2ml_sendEmail.');
            continue;
        }

        $headers .= $headerName . ": " . $headerValue . "\r\n";
    }

    // Send the email
    // 📖 Reference: https://www.php.net/manual/en/function.mail.php
    $sent = mail($to, $subject, $emailBody, $headers);

    // Log the activity
    if (function_exists('logActivity'))
    {
        if ($sent) {
            $logStatus = 'success';
            $logCode   = 200;
        } else {
            $logStatus = 'mail_failed';
            $logCode   = 500;
        }
        logActivity('send_email', $logStatus, $logCode, [
            'logData' => [
                'recipient' => $to,
                'template'  => $template,
                'subject'   => $subject,
                'amp'       => ($ampBody !== null),
            ],
        ]);
    }

    if (!$sent)
    {
        error_log('[Go2My.Link] ERROR: mail() failed for recipient: ' . $to . ' | template: ' . $template);
    }

    return $sent;
}

// ============================================================================
// 🎨 Render Email Template (HTML)
// ============================================================================

/**
 * Render an HTML email template file with data extracted into scope.
 *
 * Templates live in web/_includes/email_templates/{template}.php.
 * The $data array is extracted so template files can use $firstName,
 * $verificationURL, etc. directly.
 *
 * The template also receives $siteName, $siteURL, and $currentYear as
 * convenience variables.
 *
 * @param  string       $template  Template filename without .php extension
 * @param  array        $data      Data to extract into the template scope
 * @return string|false            Rendered HTML string, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.ob-start.php
 *
 * Usage example:
 *   $html = g2ml_renderEmailTemplate('password_reset', [
 *       'firstName' => 'John',
 *       'resetURL'  => 'https://go2my.link/reset-password?token=xyz',
 *   ]);
 */
function g2ml_renderEmailTemplate(string $template, array $data = []): string|false
{
    // 🛡️ Validate template name (prevent path traversal)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template))
    {
        error_log('[Go2My.Link] ERROR: Invalid email template name: ' . substr($template, 0, 50));
        return false;
    }

    // Build the template file path
    if (defined('G2ML_INCLUDES')) {
        $templateDir = G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'email_templates';
    } else {
        $templateDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'email_templates';
    }

    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $template . '.php';

    // Check the template file exists
    // 📖 Reference: https://www.php.net/manual/en/function.file-exists.php
    if (!file_exists($templateFile))
    {
        error_log('[Go2My.Link] ERROR: Email template not found: ' . $templateFile);
        return false;
    }

    // Add convenience variables available to all templates
    if (function_exists('getSetting')) {
        $data['siteName'] = getSetting('site.name', 'Go2My.Link');
    } else {
        $data['siteName'] = 'Go2My.Link';
    }

    $data['siteURL'] = 'https://go2my.link';

    $data['currentYear'] = date('Y');

    // Extract data into local scope so templates can use $firstName, etc.
    // 📖 Reference: https://www.php.net/manual/en/function.extract.php
    extract($data, EXTR_SKIP);

    // Capture the template output via output buffering
    // 📖 Reference: https://www.php.net/manual/en/function.ob-start.php
    ob_start();

    try
    {
        require $templateFile;
    }
    catch (\Throwable $e)
    {
        ob_end_clean();
        error_log('[Go2My.Link] ERROR: Email template render failed: ' . $e->getMessage());
        return false;
    }

    return ob_get_clean();
}

// ============================================================================
// ⚡ Render AMP Email Template
// ============================================================================

/**
 * Render an AMP email template file with data extracted into scope.
 *
 * AMP templates live in web/_includes/email_templates/amp/{template}.php.
 * Returns false if the AMP template file doesn't exist (graceful fallback).
 *
 * @param  string       $template  Template filename without .php extension
 * @param  array        $data      Data to extract into the template scope
 * @return string|false            Rendered AMP HTML string, or false if unavailable
 *
 * 📖 Reference: https://amp.dev/documentation/guides-and-tutorials/learn/email-spec/amp-email-format/
 */
function g2ml_renderAmpTemplate(string $template, array $data = []): string|false
{
    // 🛡️ Validate template name (prevent path traversal)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template))
    {
        return false;
    }

    // Build the AMP template file path
    if (defined('G2ML_INCLUDES')) {
        $templateDir = G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'email_templates' . DIRECTORY_SEPARATOR . 'amp';
    } else {
        $templateDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'email_templates' . DIRECTORY_SEPARATOR . 'amp';
    }

    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $template . '.php';

    // Gracefully return false if AMP variant doesn't exist
    if (!file_exists($templateFile))
    {
        return false;
    }

    // Add convenience variables available to all templates
    if (function_exists('getSetting')) {
        $data['siteName'] = getSetting('site.name', 'Go2My.Link');
    } else {
        $data['siteName'] = 'Go2My.Link';
    }

    $data['siteURL']     = 'https://go2my.link';
    $data['currentYear'] = date('Y');

    // Extract data into local scope
    extract($data, EXTR_SKIP);

    // Capture the template output via output buffering
    ob_start();

    try
    {
        require $templateFile;
    }
    catch (\Throwable $e)
    {
        ob_end_clean();
        error_log('[Go2My.Link] ERROR: AMP email template render failed: ' . $e->getMessage());
        return false;
    }

    return ob_get_clean();
}

// ============================================================================
// 📝 HTML to Plain Text Converter
// ============================================================================

/**
 * Convert HTML email content to a readable plaintext version.
 *
 * Preserves document structure by converting headings to UPPERCASE,
 * links to "text [url]" format, lists to "- item" format, and
 * paragraphs to double-newline-separated blocks.
 *
 * @param  string $html  The HTML email body
 * @return string        Formatted plaintext version
 */
function g2ml_htmlToPlainText(string $html): string
{
    // Remove the hidden preheader div (display:none content)
    $text = preg_replace('/<div[^>]*style="[^"]*display\s*:\s*none[^"]*"[^>]*>.*?<\/div>/is', '', $html);

    // Remove <head> section entirely (styles, meta, title)
    $text = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $text);

    // Remove <style> and <script> blocks
    $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text);
    $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text);

    // Convert headings to UPPERCASE with surrounding newlines
    $text = preg_replace_callback(
        '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is',
        function ($matches) {
            return "\n\n" . strtoupper(strip_tags($matches[1])) . "\n" . str_repeat('=', min(strlen(strip_tags($matches[1])), 60)) . "\n";
        },
        $text
    );

    // Convert links: <a href="url">text</a> → text [url]
    $text = preg_replace_callback(
        '/<a\s[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is',
        function ($matches) {
            $url  = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $linkText = strip_tags($matches[2]);
            // If link text is the same as URL, just show the URL
            if (trim($linkText) === trim($url))
            {
                return $url;
            }
            return $linkText . ' [' . $url . ']';
        },
        $text
    );

    // Convert <br> and <br/> to newlines
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

    // Convert <hr> to separator line
    $text = preg_replace('/<hr\s*\/?>/i', "\n" . str_repeat('-', 50) . "\n", $text);

    // Convert list items
    $text = preg_replace('/<li[^>]*>/i', "\n  - ", $text);

    // Convert table cells to tab-separated values
    $text = preg_replace('/<\/td>\s*<td[^>]*>/i', "\t", $text);
    $text = preg_replace('/<\/tr>/i', "\n", $text);

    // Convert paragraphs and divs to double newlines
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = preg_replace('/<\/div>/i', "\n", $text);
    $text = preg_replace('/<p[^>]*>/i', "\n", $text);

    // Strip all remaining HTML tags
    $text = strip_tags($text);

    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // Normalise whitespace: collapse multiple spaces to single space per line
    $text = preg_replace('/[ \t]+/', ' ', $text);

    // Normalise newlines: collapse 3+ consecutive newlines to 2
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    // Trim each line and remove leading/trailing whitespace from the whole text
    $lines = array_map('trim', explode("\n", $text));
    $text  = implode("\n", $lines);

    return trim($text);
}

// ============================================================================
// 📦 Build Multipart MIME Body
// ============================================================================

/**
 * Assemble a multipart/alternative email body with MIME parts.
 *
 * Order follows RFC 2046 and AMP spec: text/plain first, then
 * text/x-amp-html (if provided), then text/html last (highest fidelity).
 * Email clients select the last part they can render.
 *
 * @param  string      $plainText  The text/plain body
 * @param  string      $htmlBody   The text/html body
 * @param  string|null $ampBody    Optional text/x-amp-html body (null to skip)
 * @param  string      $boundary   The MIME boundary string
 * @return string                  Complete multipart MIME body
 *
 * 📖 Reference: https://www.rfc-editor.org/rfc/rfc2046#section-5.1.4
 */
function g2ml_buildMultipartBody(string $plainText, string $htmlBody, ?string $ampBody, string $boundary): string
{
    $body = "";

    // text/plain part (lowest fidelity — shown as fallback)
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $body .= "\r\n";
    $body .= quoted_printable_encode($plainText) . "\r\n";

    // text/x-amp-html part (middle — AMP-capable clients prefer this)
    // 📖 Reference: https://amp.dev/documentation/guides-and-tutorials/learn/email-spec/amp-email-format/
    if ($ampBody !== null && $ampBody !== '')
    {
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/x-amp-html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $body .= "\r\n";
        $body .= quoted_printable_encode($ampBody) . "\r\n";
    }

    // text/html part (highest fidelity — most clients use this)
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $body .= "\r\n";
    $body .= quoted_printable_encode($htmlBody) . "\r\n";

    // Closing boundary
    $body .= "--" . $boundary . "--\r\n";

    return $body;
}

// ============================================================================
// 🔑 Generate MIME Boundary
// ============================================================================

/**
 * Generate a unique MIME boundary string for multipart emails.
 *
 * The boundary must not appear in any MIME part body. Using a combination
 * of a fixed prefix and random hex ensures uniqueness.
 *
 * @return string  A unique MIME boundary string
 *
 * 📖 Reference: https://www.rfc-editor.org/rfc/rfc2046#section-5.1.1
 */
function g2ml_generateMimeBoundary(): string
{
    return '----=_G2ML_' . bin2hex(random_bytes(16));
}
