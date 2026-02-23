<?php
/**
 * ============================================================================
 * Go2My.Link — Contact Page (Component A)
 * ============================================================================
 *
 * Contact form with CSRF protection, optional CAPTCHA, and rate limiting.
 * Sends email via PHP mail() function.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('contact.title') : 'Contact Us';
$pageDesc  = function_exists('__') ? __('contact.description') : 'Get in touch with the Go2My.Link team.';

// Process form submission (no-JS fallback)
$formSuccess = false;
$formError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'contact_form'))
    {
        $formError = function_exists('__')
            ? __('contact.error_csrf')
            : 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        // Extract and validate fields
        $contactName    = trim(g2ml_sanitiseInput($_POST['contact_name'] ?? ''));
        $contactEmail   = trim(g2ml_sanitiseInput($_POST['contact_email'] ?? ''));
        $contactSubject = trim(g2ml_sanitiseInput($_POST['contact_subject'] ?? ''));
        $contactMessage = trim(g2ml_sanitiseInput($_POST['contact_message'] ?? ''));

        if ($contactName === '' || $contactEmail === '' || $contactMessage === '')
        {
            $formError = function_exists('__')
                ? __('contact.error_required')
                : 'Please fill in all required fields.';
        }
        elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
        {
            $formError = function_exists('__')
                ? __('contact.error_email')
                : 'Please enter a valid email address.';
        }
        else
        {
            // Rate limit check (reuse anonymous rate limit logic on IP)
            $clientIP  = g2ml_getClientIP();
            $rateCheck = dbSelectOne(
                "SELECT COUNT(*) AS cnt
                 FROM tblActivityLog
                 WHERE logAction = 'contact_form'
                   AND ipAddress = ?
                   AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                's',
                [$clientIP]
            );

            $contactCount = ($rateCheck !== null && $rateCheck !== false)
                ? (int) $rateCheck['cnt']
                : 0;

            if ($contactCount >= 5)
            {
                $formError = function_exists('__')
                    ? __('contact.error_rate_limit')
                    : 'Too many messages sent. Please try again later.';
            }
            else
            {
                // Send email
                $recipient = getSetting('contact.email_recipient', 'support@go2my.link');
                $subject   = '[Go2My.Link Contact] '
                    . ($contactSubject !== '' ? $contactSubject : 'New Message');

                $body  = "Name: " . $contactName . "\r\n";
                $body .= "Email: " . $contactEmail . "\r\n";
                $body .= "Subject: " . $contactSubject . "\r\n";
                $body .= "IP: " . $clientIP . "\r\n";
                $body .= "\r\n---\r\n\r\n";
                $body .= $contactMessage . "\r\n";

                $headers  = "From: noreply@go2my.link\r\n";
                $headers .= "Reply-To: " . $contactEmail . "\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                $mailSent = @mail($recipient, $subject, $body, $headers);

                // Log the activity regardless of mail success
                logActivity('contact_form', $mailSent ? 'success' : 'mail_failed', $mailSent ? 200 : 500, [
                    'logData' => [
                        'senderEmail' => $contactEmail,
                        'subject'     => $contactSubject,
                    ],
                ]);

                if ($mailSent)
                {
                    $formSuccess = true;
                }
                else
                {
                    $formError = function_exists('__')
                        ? __('contact.error_send')
                        : 'Failed to send your message. Please try again later.';
                }
            }
        }
    }
}

// CAPTCHA config (same pattern as home.php)
$turnstileSiteKey = getSetting('captcha.turnstile_site_key', '');
$recaptchaSiteKey = getSetting('captcha.recaptcha_site_key', '');
$captchaType      = 'none';

if ($turnstileSiteKey !== '')
{
    $captchaType = 'turnstile';
}
elseif ($recaptchaSiteKey !== '')
{
    $captchaType = 'recaptcha';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="contact-heading">
    <div class="container">
        <h1 id="contact-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('contact.heading') : 'Contact Us'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('contact.subtitle') : 'Have a question or feedback? We\'d love to hear from you.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Contact Form                                                            -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="form-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm contact-card mx-auto">
                    <div class="card-body p-4">
                        <h2 id="form-heading" class="h5 mb-3">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('contact.form_heading') : 'Send a Message'; ?>
                        </h2>

                        <?php if ($formSuccess): ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('contact.success') : 'Your message has been sent. We\'ll get back to you as soon as possible.'; ?>
                        </div>
                        <?php else: ?>

                            <?php if ($formError !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($formError); ?>
                            </div>
                            <?php endif; ?>

                            <form action="/contact" method="POST" id="contact-form">
                                <?php echo g2ml_csrfField('contact_form'); ?>

                                <?php
                                echo formField([
                                    'id'           => 'contact-name',
                                    'name'         => 'contact_name',
                                    'label'        => function_exists('__') ? __('contact.label_name') : 'Your Name',
                                    'type'         => 'text',
                                    'placeholder'  => function_exists('__') ? __('contact.placeholder_name') : 'John Doe',
                                    'required'     => true,
                                    'autocomplete' => 'name',
                                ]);

                                echo formField([
                                    'id'           => 'contact-email',
                                    'name'         => 'contact_email',
                                    'label'        => function_exists('__') ? __('contact.label_email') : 'Email Address',
                                    'type'         => 'email',
                                    'placeholder'  => function_exists('__') ? __('contact.placeholder_email') : 'you@example.com',
                                    'required'     => true,
                                    'autocomplete' => 'email',
                                ]);

                                echo formField([
                                    'id'          => 'contact-subject',
                                    'name'        => 'contact_subject',
                                    'label'       => function_exists('__') ? __('contact.label_subject') : 'Subject',
                                    'type'        => 'text',
                                    'placeholder' => function_exists('__') ? __('contact.placeholder_subject') : 'How can we help?',
                                    'required'    => false,
                                ]);

                                echo formField([
                                    'id'          => 'contact-message',
                                    'name'        => 'contact_message',
                                    'label'       => function_exists('__') ? __('contact.label_message') : 'Message',
                                    'type'        => 'textarea',
                                    'placeholder' => function_exists('__') ? __('contact.placeholder_message') : 'Your message...',
                                    'required'    => true,
                                    'rows'        => 5,
                                ]);
                                ?>

                                <?php if ($captchaType === 'turnstile'): ?>
                                <div class="cf-turnstile mb-3"
                                     data-sitekey="<?php echo g2ml_sanitiseOutput($turnstileSiteKey); ?>"
                                     data-theme="auto">
                                </div>
                                <?php elseif ($captchaType === 'recaptcha'): ?>
                                <div class="g-recaptcha mb-3"
                                     data-sitekey="<?php echo g2ml_sanitiseOutput($recaptchaSiteKey); ?>">
                                </div>
                                <?php endif; ?>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                        <?php echo function_exists('__') ? __('contact.send_button') : 'Send Message'; ?>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Load CAPTCHA JS SDK if needed
if ($captchaType === 'turnstile'):
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php elseif ($captchaType === 'recaptcha'): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
