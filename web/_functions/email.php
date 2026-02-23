<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Email Functions
 * ============================================================================
 *
 * Sends transactional emails using PHP mail() with HTML templates.
 * Templates are loaded from web/_includes/email_templates/ via output
 * buffering with data extraction.
 *
 * Dependencies: settings.php (getSetting()), activity_logger.php (logActivity()),
 *               security.php (g2ml_sanitiseOutput())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.5.0
 * @since      Phase 4
 *
 * 📖 References:
 *     - PHP mail(): https://www.php.net/manual/en/function.mail.php
 *     - Email headers: https://www.php.net/manual/en/function.mail.php#refsect1-function.mail-parameters
 *     - Output buffering: https://www.php.net/manual/en/function.ob-start.php
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
 * Send a transactional email using an HTML template.
 *
 * Renders the specified template with the provided data, then sends it via
 * PHP mail(). From address, reply-to, and sender name are pulled from
 * tblSettings (email.from_address, email.from_name, email.reply_to).
 *
 * @param  string $to        Recipient email address
 * @param  string $subject   Email subject line
 * @param  string $template  Template name (e.g., 'verify_email' → verify_email.php)
 * @param  array  $data      Data to pass to the template (extracted into scope)
 * @return bool              True if mail() accepted the message for delivery
 *
 * 📖 Reference: https://www.php.net/manual/en/function.mail.php
 *
 * Usage example:
 *   g2ml_sendEmail('user@example.com', 'Verify Your Email', 'verify_email', [
 *       'firstName'       => 'John',
 *       'verificationURL' => 'https://go2my.link/verify-email?token=abc123',
 *   ]);
 */
function g2ml_sendEmail(string $to, string $subject, string $template, array $data = []): bool
{
    // Render the HTML body from the template
    $htmlBody = g2ml_renderEmailTemplate($template, $data);

    if ($htmlBody === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_sendEmail failed — template "' . $template . '" could not be rendered.');
        return false;
    }

    // Get email settings from database
    // 📖 Reference: settings.php → getSetting()
    $fromAddress = function_exists('getSetting')
        ? getSetting('email.from_address', 'noreply@go2my.link')
        : 'noreply@go2my.link';

    $fromName = function_exists('getSetting')
        ? getSetting('email.from_name', 'Go2My.Link')
        : 'Go2My.Link';

    $replyTo = function_exists('getSetting')
        ? getSetting('email.reply_to', 'support@go2my.link')
        : 'support@go2my.link';

    // Build email headers
    // 📖 Reference: https://www.php.net/manual/en/function.mail.php#refsect1-function.mail-parameters
    $headers  = "From: " . $fromName . " <" . $fromAddress . ">\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "X-Mailer: Go2My.Link/0.5.0\r\n";

    // Send the email
    // 📖 Reference: https://www.php.net/manual/en/function.mail.php
    $sent = @mail($to, $subject, $htmlBody, $headers);

    // Log the activity
    if (function_exists('logActivity'))
    {
        logActivity('send_email', $sent ? 'success' : 'mail_failed', $sent ? 200 : 500, [
            'logData' => [
                'recipient' => $to,
                'template'  => $template,
                'subject'   => $subject,
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
// 🎨 Render Email Template
// ============================================================================

/**
 * Render an email template file with data extracted into scope.
 *
 * Templates live in web/_includes/email_templates/{template}.php.
 * The $data array is extracted so template files can use $firstName,
 * $verificationURL, etc. directly.
 *
 * The template also receives $siteName and $siteURL as convenience variables.
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
    // Build the template file path
    $templateDir = defined('G2ML_INCLUDES')
        ? G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'email_templates'
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'email_templates';

    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $template . '.php';

    // Check the template file exists
    // 📖 Reference: https://www.php.net/manual/en/function.file-exists.php
    if (!file_exists($templateFile))
    {
        error_log('[Go2My.Link] ERROR: Email template not found: ' . $templateFile);
        return false;
    }

    // Add convenience variables available to all templates
    $data['siteName'] = function_exists('getSetting')
        ? getSetting('site.name', 'Go2My.Link')
        : 'Go2My.Link';

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
