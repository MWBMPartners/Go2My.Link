<?php
/**
 * ============================================================================
 * 🔑 Go2My.Link — Forgot Password Page (Component A)
 * ============================================================================
 *
 * Password reset request form. Sends a reset email if the account exists,
 * but always shows a generic success message (email enumeration prevention).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

// Redirect if already authenticated
if (function_exists('isAuthenticated') && isAuthenticated())
{
    header('Location: https://admin.go2my.link/profile');
    exit;
}

$pageTitle = function_exists('__') ? __('forgot_password.title') : 'Forgot Password';
$pageDesc  = function_exists('__') ? __('forgot_password.description') : 'Reset your Go2My.Link account password.';

// ============================================================================
// Process form submission
// ============================================================================

$formError   = '';
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'forgot_password_form'))
    {
        $formError = function_exists('__')
            ? __('forgot_password.error_csrf')
            : 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        // Verify CAPTCHA if configured
        $captchaValid = true;
        $turnstileKey = getSetting('captcha.turnstile_site_key', '');
        $recaptchaKey = getSetting('captcha.recaptcha_site_key', '');

        if ($turnstileKey !== '' && function_exists('_g2ml_verifyTurnstile'))
        {
            $captchaResponse = $_POST['cf-turnstile-response'] ?? '';
            $captchaValid    = _g2ml_verifyTurnstile($captchaResponse);
        }
        elseif ($recaptchaKey !== '' && function_exists('_g2ml_verifyRecaptcha'))
        {
            $captchaResponse = $_POST['g-recaptcha-response'] ?? '';
            $captchaValid    = _g2ml_verifyRecaptcha($captchaResponse);
        }

        if (!$captchaValid)
        {
            $formError = function_exists('__')
                ? __('forgot_password.error_captcha')
                : 'CAPTCHA verification failed. Please try again.';
        }
        else
        {
            // Rate limit check (max 5 per IP per hour)
            $clientIP  = g2ml_getClientIP();
            $rateCheck = dbSelectOne(
                "SELECT COUNT(*) AS cnt
                 FROM tblActivityLog
                 WHERE logAction = 'password_reset_request'
                   AND ipAddress = ?
                   AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                's',
                [$clientIP]
            );

            $resetCount = ($rateCheck !== null && $rateCheck !== false) ? (int) $rateCheck['cnt'] : 0;

            if ($resetCount >= 5)
            {
                $formError = function_exists('__')
                    ? __('forgot_password.error_rate_limit')
                    : 'Too many reset requests. Please try again later.';
            }
            else
            {
                $email = trim($_POST['email'] ?? '');

                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
                {
                    $formError = function_exists('__')
                        ? __('forgot_password.error_email')
                        : 'Please enter a valid email address.';
                }
                else
                {
                    // Always returns success (enumeration prevention)
                    generatePasswordResetToken($email);
                    $formSuccess = true;
                }
            }
        }
    }
}

// CAPTCHA config
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
<section class="page-header text-center" aria-labelledby="forgot-heading">
    <div class="container">
        <h1 id="forgot-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('forgot_password.heading') : 'Forgot Password'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('forgot_password.subtitle') : "Enter your email and we'll send you a reset link."; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Reset Request Form                                                      -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="form-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="form-heading" class="h5 mb-3">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('forgot_password.form_heading') : 'Reset Your Password'; ?>
                        </h2>

                        <?php if ($formSuccess): ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php echo function_exists('__')
                                ? __('forgot_password.success')
                                : 'If an account exists with that email, we\'ve sent a password reset link. Please check your inbox (and spam folder).'; ?>
                        </div>
                        <div class="text-center">
                            <a href="/login" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('forgot_password.back_to_login') : 'Back to Login'; ?>
                            </a>
                        </div>
                        <?php else: ?>

                            <?php if ($formError !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($formError); ?>
                            </div>
                            <?php endif; ?>

                            <form action="/forgot-password" method="POST" id="forgot-password-form" novalidate>
                                <?php echo g2ml_csrfField('forgot_password_form'); ?>

                                <?php
                                echo formField([
                                    'id'           => 'forgot-email',
                                    'name'         => 'email',
                                    'label'        => function_exists('__') ? __('forgot_password.label_email') : 'Email Address',
                                    'type'         => 'email',
                                    'placeholder'  => function_exists('__') ? __('forgot_password.placeholder_email') : 'you@example.com',
                                    'required'     => true,
                                    'autocomplete' => 'email',
                                    'value'        => isset($_POST['email']) ? g2ml_sanitiseOutput($_POST['email']) : '',
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

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                        <?php echo function_exists('__') ? __('forgot_password.submit_button') : 'Send Reset Link'; ?>
                                    </button>
                                </div>

                                <p class="text-center text-body-secondary small mb-0">
                                    <?php echo function_exists('__') ? __('forgot_password.remember_password') : 'Remember your password?'; ?>
                                    <a href="/login"><?php echo function_exists('__') ? __('forgot_password.login_link') : 'Log in'; ?></a>
                                </p>
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
