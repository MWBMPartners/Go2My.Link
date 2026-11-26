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
 * Go2My.Link — Contact Page (Component A)
 * ============================================================================
 *
 * Contact form with CSRF protection, optional CAPTCHA, and rate limiting.
 * Sends email via g2ml_sendEmail() (#212 — previously a raw PHP mail() call).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('contact.title');
} else {
    $pageTitle = 'Contact Us';
}
if (function_exists('__')) {
    $pageDesc = __('contact.description');
} else {
    $pageDesc = 'Get in touch with the Go2My.Link team.';
}

// Process form submission (no-JS fallback)
$formSuccess = false;
$formError   = '';

// What the visitor typed, kept so a failed send does not wipe the form (#286).
// Raw values on purpose: formField() below escapes each one exactly once.
// A field posted as something other than plain text (an array, for example)
// is treated as empty.
$postedValues = [
    'contact_name'    => '',
    'contact_email'   => '',
    'contact_subject' => '',
    'contact_message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    foreach (array_keys($postedValues) as $postedKey)
    {
        if (isset($_POST[$postedKey]) && is_string($_POST[$postedKey]))
        {
            $postedValues[$postedKey] = $_POST[$postedKey];
        }
    }

    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'contact_form'))
    {
        if (function_exists('__')) {
            $formError = __('contact.error_csrf');
        } else {
            $formError = 'Your session has expired. Please reload the page and try again.';
        }
    }
    else
    {
        // Extract and validate fields
        $contactName    = trim(g2ml_sanitiseInput($_POST['contact_name'] ?? ''));
        $contactEmail   = trim(g2ml_sanitiseInput($_POST['contact_email'] ?? ''));
        $contactSubject = trim(g2ml_sanitiseInput($_POST['contact_subject'] ?? ''));
        $contactMessage = trim(g2ml_sanitiseInput($_POST['contact_message'] ?? ''));

        // 🤖 Server-side CAPTCHA verification (mirrors api/create and register).
        // Load the shared verifyCaptcha() helper if it is not already available.
        if (!function_exists('verifyCaptcha'))
        {
            $captchaHelperPath = dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . '_functions'
                . DIRECTORY_SEPARATOR . 'shorturl_create.php';

            if (file_exists($captchaHelperPath))
            {
                require_once $captchaHelperPath;
            }
        }

        $turnstileSecretKey = getSetting('captcha.turnstile_secret_key', '');
        $recaptchaSecretKey = getSetting('captcha.recaptcha_secret_key', '');
        $captchaError       = '';

        if ($turnstileSecretKey !== '')
        {
            $captchaResponse = $_POST['cf-turnstile-response'] ?? '';

            if ($captchaResponse === '' || !function_exists('verifyCaptcha') || !verifyCaptcha('turnstile', $captchaResponse, $turnstileSecretKey))
            {
                $captchaError = 'CAPTCHA verification failed. Please try again.';
            }
        }
        elseif ($recaptchaSecretKey !== '')
        {
            $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

            if ($captchaResponse === '' || !function_exists('verifyCaptcha') || !verifyCaptcha('recaptcha', $captchaResponse, $recaptchaSecretKey))
            {
                $captchaError = 'CAPTCHA verification failed. Please try again.';
            }
        }

        if ($captchaError !== '')
        {
            $formError = $captchaError;
        }
        elseif ($contactName === '' || $contactEmail === '' || $contactMessage === '')
        {
            if (function_exists('__')) {
                $formError = __('contact.error_required');
            } else {
                $formError = 'Please fill in all required fields.';
            }
        }
        elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
        {
            if (function_exists('__')) {
                $formError = __('contact.error_email');
            } else {
                $formError = 'Please enter a valid email address.';
            }
        }
        else
        {
            // Rate limit check (#212). This USED TO count rows in
            // tblActivityLog for logAction = 'contact_form'. That table is
            // written by logActivity(), which SKIPS most actions for a
            // visitor who has sent Do Not Track, and 'contact_form' was
            // never on activity_logger.php's short "always log" list — so a
            // visitor with Do Not Track on could send unlimited messages no
            // matter how the limit was set. tblFormAttempts
            // (web/_functions/form_rate_limit.php) records every attempt
            // with no DNT check at all, which closes that gap. The message
            // shown on refusal is unchanged.
            // 'unavailable' (also used if the limiter is not loaded) refuses.
            $contactAttempt = 'unavailable';

            if (function_exists('g2ml_formRateLimitTake'))
            {
                $contactAttempt = g2ml_formRateLimitTake(
                    'contact',
                    (int) getSetting('security.contact_max_per_ip_per_hour', 5)
                );
            }

            if ($contactAttempt === 'unavailable')
            {
                if (function_exists('__')) {
                    $formError = __('form.error_unavailable');
                } else {
                    $formError = 'We cannot process this form just now. Please try again later.';
                }
            }
            elseif ($contactAttempt === 'limited')
            {
                if (function_exists('__')) {
                    $formError = __('contact.error_rate_limit');
                } else {
                    $formError = 'Too many messages sent. Please try again later.';
                }
            }
            else
            {
                // Send email
                $recipient = getSetting('contact.email_recipient', 'support@go2my.link');

                // Strip CR/LF/NUL from the subject to prevent mail header injection.
                $safeSubject = str_replace(["\r", "\n", "\0"], '', $contactSubject);

                if ($safeSubject !== '')
                {
                    $subject = '[Go2My.Link Contact] ' . $safeSubject;
                }
                else
                {
                    $subject = '[Go2My.Link Contact] New Message';
                }

                // Sent through the shared g2ml_sendEmail() (#212) instead of
                // a raw @mail() call, so this message gets the same
                // multipart HTML/plain-text body, From/List-Unsubscribe
                // headers and CRLF-injection defences as every other
                // outgoing email. The visitor's address is passed as a
                // Reply-To override (see web/_functions/email.php) so
                // replying from the support inbox reaches them directly,
                // without making the message's own From address the
                // visitor's (which would fail sender-authentication checks
                // at the recipient's mail server). The visitor's IP address
                // is deliberately NOT included any more — the old plain
                // @mail() body put it in the message text, which is more
                // than the message needs (data minimisation).
                $mailSent = g2ml_sendEmail(
                    $recipient,
                    $subject,
                    'contact_message',
                    [
                        'contactName'    => $contactName,
                        'contactEmail'   => $contactEmail,
                        'contactSubject' => $contactSubject,
                        'contactMessage' => $contactMessage,
                    ],
                    '',
                    ['Reply-To' => $contactEmail]
                );

                // Log the activity regardless of mail success
                if ($mailSent) {
                    $logStatus = 'success';
                    $logCode = 200;
                } else {
                    $logStatus = 'mail_failed';
                    $logCode = 500;
                }

                // The visitor's email address used to be stored here too
                // ('senderEmail'). It is personal data that this log entry
                // does not need — the address is not used for the rate
                // limit (tblFormAttempts holds only a hashed IP address, not
                // an email address) and is not needed to tell whether the
                // send succeeded. Only whether a subject was given is kept.
                logActivity('contact_form', $logStatus, $logCode, [
                    'logData' => [
                        'hasSubject' => ($safeSubject !== ''),
                    ],
                ]);

                if ($mailSent)
                {
                    $formSuccess = true;
                }
                else
                {
                    if (function_exists('__')) {
                        $formError = __('contact.error_send');
                    } else {
                        $formError = 'Failed to send your message. Please try again later.';
                    }
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
            <?php if (function_exists('__')) { echo __('contact.heading'); } else { echo 'Contact Us'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('contact.subtitle'); } else { echo 'Have a question or feedback? We\'d love to hear from you.'; } ?>
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
                            <?php if (function_exists('__')) { echo __('contact.form_heading'); } else { echo 'Send a Message'; } ?>
                        </h2>

                        <?php if ($formSuccess) { ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('contact.success'); } else { echo 'Your message has been sent. We\'ll get back to you as soon as possible.'; } ?>
                        </div>
                        <?php } else { ?>

                            <?php if ($formError !== '') { ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($formError); ?>
                            </div>
                            <?php } ?>

                            <form action="/contact" method="POST" id="contact-form">
                                <?php echo g2ml_csrfField('contact_form'); ?>

                                <?php
                                    if (function_exists('__')) {
                                        $fieldLabel = __('contact.label_name');
                                    } else {
                                        $fieldLabel = 'Your Name';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('contact.placeholder_name');
                                    } else {
                                        $fieldPlaceholder = 'John Doe';
                                    }
                                echo formField([
                                    'id'           => 'contact-name',
                                    'name'         => 'contact_name',
                                    'label' => $fieldLabel,
                                    'type'         => 'text',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'     => true,
                                    'autocomplete' => 'name',
                                    'value'        => $postedValues['contact_name'],
                                ]);

                                    if (function_exists('__')) {
                                        $fieldLabel = __('contact.label_email');
                                    } else {
                                        $fieldLabel = 'Email Address';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('contact.placeholder_email');
                                    } else {
                                        $fieldPlaceholder = 'you@example.com';
                                    }
                                echo formField([
                                    'id'           => 'contact-email',
                                    'name'         => 'contact_email',
                                    'label' => $fieldLabel,
                                    'type'         => 'email',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'     => true,
                                    'autocomplete' => 'email',
                                    'value'        => $postedValues['contact_email'],
                                ]);

                                    if (function_exists('__')) {
                                        $fieldLabel = __('contact.label_subject');
                                    } else {
                                        $fieldLabel = 'Subject';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('contact.placeholder_subject');
                                    } else {
                                        $fieldPlaceholder = 'How can we help?';
                                    }
                                echo formField([
                                    'id'          => 'contact-subject',
                                    'name'        => 'contact_subject',
                                    'label' => $fieldLabel,
                                    'type'        => 'text',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'    => false,
                                    'value'       => $postedValues['contact_subject'],
                                ]);

                                    if (function_exists('__')) {
                                        $fieldLabel = __('contact.label_message');
                                    } else {
                                        $fieldLabel = 'Message';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('contact.placeholder_message');
                                    } else {
                                        $fieldPlaceholder = 'Your message...';
                                    }
                                echo formField([
                                    'id'          => 'contact-message',
                                    'name'        => 'contact_message',
                                    'label' => $fieldLabel,
                                    'type'        => 'textarea',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'    => true,
                                    'rows'        => 5,
                                    'value'       => $postedValues['contact_message'],
                                ]);
                                ?>

                                <?php if ($captchaType === 'turnstile') { ?>
                                <div class="cf-turnstile mb-3"
                                     data-sitekey="<?php echo g2ml_sanitiseOutput($turnstileSiteKey); ?>"
                                     data-theme="auto">
                                </div>
                                <?php } elseif ($captchaType === 'recaptcha') { ?>
                                <div class="g-recaptcha mb-3"
                                     data-sitekey="<?php echo g2ml_sanitiseOutput($recaptchaSiteKey); ?>">
                                </div>
                                <?php } ?>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                        <?php if (function_exists('__')) { echo __('contact.send_button'); } else { echo 'Send Message'; } ?>
                                    </button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Load CAPTCHA JS SDK if needed
if ($captchaType === 'turnstile') {
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php } elseif ($captchaType === 'recaptcha') { ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php } ?>
