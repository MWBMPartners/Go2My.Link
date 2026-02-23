<?php
/**
 * ============================================================================
 * 📝 Go2My.Link — Registration Page (Component A)
 * ============================================================================
 *
 * User registration form with CSRF protection, password validation, CAPTCHA,
 * and email verification. Follows the contact form pattern.
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
    header('Location: https://admin.go2my.link/');
    exit;
}

if (function_exists('__')) {
    $pageTitle = __('register.title');
} else {
    $pageTitle = 'Create Account';
}
if (function_exists('__')) {
    $pageDesc = __('register.description');
} else {
    $pageDesc = 'Sign up for a free Go2My.Link account.';
}

// ============================================================================
// Process form submission
// ============================================================================

$formError   = '';
$formSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'register_form'))
    {
        if (function_exists('__')) {
            $formError = __('register.error_csrf');
        } else {
            $formError = 'Your session has expired. Please reload the page and try again.';
        }
    }
    else
    {
        // Verify CAPTCHA if configured
        $captchaValid = true;
        $turnstileKey = getSetting('captcha.turnstile_site_key', '');
        $recaptchaKey = getSetting('captcha.recaptcha_site_key', '');

        if ($turnstileKey !== '')
        {
            $captchaResponse = $_POST['cf-turnstile-response'] ?? '';
            $captchaValid    = _g2ml_verifyTurnstile($captchaResponse);
        }
        elseif ($recaptchaKey !== '')
        {
            $captchaResponse = $_POST['g-recaptcha-response'] ?? '';
            $captchaValid    = _g2ml_verifyRecaptcha($captchaResponse);
        }

        if (!$captchaValid)
        {
            if (function_exists('__')) {
                $formError = __('register.error_captcha');
            } else {
                $formError = 'CAPTCHA verification failed. Please try again.';
            }
        }
        else
        {
            // Extract fields
            $firstName       = trim(g2ml_sanitiseInput($_POST['first_name'] ?? ''));
            $lastName         = trim(g2ml_sanitiseInput($_POST['last_name'] ?? ''));
            $email           = trim($_POST['email'] ?? '');
            $password        = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Check passwords match
            if ($password !== $confirmPassword)
            {
                if (function_exists('__')) {
                    $formError = __('register.error_password_mismatch');
                } else {
                    $formError = 'Passwords do not match.';
                }
            }
            else
            {
                // Attempt registration
                $result = registerUser($email, $password, $firstName, $lastName);

                if ($result['success'])
                {
                    // Send verification email
                    $verifyURL = 'https://go2my.link/verify-email?token=' . urlencode($result['verifyToken']);

                    g2ml_sendEmail(
                        $email,
                        'Verify Your Email — ' . getSetting('site.name', 'Go2My.Link'),
                        'verify_email',
                        [
                            'firstName'       => $firstName,
                            'verificationURL' => $verifyURL,
                        ]
                    );

                    $formSuccess = true;
                }
                else
                {
                    $formError = $result['error'];
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
<section class="page-header text-center" aria-labelledby="register-heading">
    <div class="container">
        <h1 id="register-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('register.heading'); } else { echo 'Create Your Account'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('register.subtitle'); } else { echo 'Sign up to manage your short links, view analytics, and more.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Registration Form                                                       -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="form-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="form-heading" class="h5 mb-3">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('register.form_heading'); } else { echo 'Sign Up'; } ?>
                        </h2>

                        <?php if ($formSuccess) { ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('register.success'); } else { echo 'Account created! Please check your email to verify your address, then log in.'; } ?>
                        </div>
                        <div class="text-center">
                            <a href="/login" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('register.go_to_login'); } else { echo 'Go to Login'; } ?>
                            </a>
                        </div>
                        <?php } else { ?>

                            <?php if ($formError !== '') { ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($formError); ?>
                            </div>
                            <?php } ?>

                            <form action="/register" method="POST" id="register-form" novalidate>
                                <?php echo g2ml_csrfField('register_form'); ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <?php
                                            if (function_exists('__')) {
                                                $fieldLabel = __('register.label_first_name');
                                            } else {
                                                $fieldLabel = 'First Name';
                                            }
                                            if (function_exists('__')) {
                                                $fieldPlaceholder = __('register.placeholder_first_name');
                                            } else {
                                                $fieldPlaceholder = 'John';
                                            }
                                            if (isset($_POST['first_name'])) {
                                                $fieldValue = g2ml_sanitiseOutput($_POST['first_name']);
                                            } else {
                                                $fieldValue = '';
                                            }
                                        echo formField([
                                            'id'           => 'reg-first-name',
                                            'name'         => 'first_name',
                                            'label' => $fieldLabel,
                                            'type'         => 'text',
                                            'placeholder' => $fieldPlaceholder,
                                            'required'     => true,
                                            'autocomplete' => 'given-name',
                                            'value' => $fieldValue,
                                        ]);
                                        ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php
                                            if (function_exists('__')) {
                                                $fieldLabel = __('register.label_last_name');
                                            } else {
                                                $fieldLabel = 'Last Name';
                                            }
                                            if (function_exists('__')) {
                                                $fieldPlaceholder = __('register.placeholder_last_name');
                                            } else {
                                                $fieldPlaceholder = 'Doe';
                                            }
                                            if (isset($_POST['last_name'])) {
                                                $fieldValue = g2ml_sanitiseOutput($_POST['last_name']);
                                            } else {
                                                $fieldValue = '';
                                            }
                                        echo formField([
                                            'id'           => 'reg-last-name',
                                            'name'         => 'last_name',
                                            'label' => $fieldLabel,
                                            'type'         => 'text',
                                            'placeholder' => $fieldPlaceholder,
                                            'required'     => true,
                                            'autocomplete' => 'family-name',
                                            'value' => $fieldValue,
                                        ]);
                                        ?>
                                    </div>
                                </div>

                                <?php
                                    if (function_exists('__')) {
                                        $fieldLabel = __('register.label_email');
                                    } else {
                                        $fieldLabel = 'Email Address';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('register.placeholder_email');
                                    } else {
                                        $fieldPlaceholder = 'you@example.com';
                                    }
                                    if (isset($_POST['email'])) {
                                        $fieldValue = g2ml_sanitiseOutput($_POST['email']);
                                    } else {
                                        $fieldValue = '';
                                    }
                                echo formField([
                                    'id'           => 'reg-email',
                                    'name'         => 'email',
                                    'label' => $fieldLabel,
                                    'type'         => 'email',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'     => true,
                                    'autocomplete' => 'email',
                                    'value' => $fieldValue,
                                ]);

                                    if (function_exists('__')) {
                                        $fieldLabel = __('register.label_password');
                                    } else {
                                        $fieldLabel = 'Password';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('register.placeholder_password');
                                    } else {
                                        $fieldPlaceholder = 'At least 8 characters';
                                    }
                                    if (function_exists('__')) {
                                        $fieldHelpText = __('register.password_help');
                                    } else {
                                        $fieldHelpText = 'Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.';
                                    }
                                echo formField([
                                    'id'           => 'reg-password',
                                    'name'         => 'password',
                                    'label' => $fieldLabel,
                                    'type'         => 'password',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'     => true,
                                    'autocomplete' => 'new-password',
                                    'helpText' => $fieldHelpText,
                                ]);

                                    if (function_exists('__')) {
                                        $fieldLabel = __('register.label_confirm_password');
                                    } else {
                                        $fieldLabel = 'Confirm Password';
                                    }
                                    if (function_exists('__')) {
                                        $fieldPlaceholder = __('register.placeholder_confirm_password');
                                    } else {
                                        $fieldPlaceholder = 'Re-enter your password';
                                    }
                                echo formField([
                                    'id'           => 'reg-confirm-password',
                                    'name'         => 'confirm_password',
                                    'label' => $fieldLabel,
                                    'type'         => 'password',
                                    'placeholder' => $fieldPlaceholder,
                                    'required'     => true,
                                    'autocomplete' => 'new-password',
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

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                                        <?php if (function_exists('__')) { echo __('register.submit_button'); } else { echo 'Create Account'; } ?>
                                    </button>
                                </div>

                                <p class="text-center text-body-secondary small mb-0">
                                    <?php if (function_exists('__')) { echo __('register.has_account'); } else { echo 'Already have an account?'; } ?>
                                    <a href="/login"><?php if (function_exists('__')) { echo __('register.login_link'); } else { echo 'Log in'; } ?></a>
                                </p>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// CAPTCHA helper functions (reusable across auth pages)
// These are defined here so they're available in the page scope

/**
 * Verify a Cloudflare Turnstile CAPTCHA response.
 * @param  string $response  The cf-turnstile-response token
 * @return bool
 */
function _g2ml_verifyTurnstile(string $response): bool
{
    if ($response === '')
    {
        return false;
    }

    $secretKey = getSetting('captcha.turnstile_secret_key', '');

    if ($secretKey === '')
    {
        return true; // No secret key configured — pass through
    }

    $verifyURL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $postData  = http_build_query([
        'secret'   => $secretKey,
        'response' => $response,
        'remoteip' => g2ml_getClientIP(),
    ]);

    // 📖 Reference: https://www.php.net/manual/en/function.file-get-contents.php
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 5,
        ],
    ]);

    $result = @file_get_contents($verifyURL, false, $context);

    if ($result === false)
    {
        error_log('[Go2My.Link] WARNING: Turnstile verification request failed.');
        return false;
    }

    $data = json_decode($result, true);

    return isset($data['success']) && $data['success'] === true;
}

/**
 * Verify a Google reCAPTCHA v2 response.
 * @param  string $response  The g-recaptcha-response token
 * @return bool
 */
function _g2ml_verifyRecaptcha(string $response): bool
{
    if ($response === '')
    {
        return false;
    }

    $secretKey = getSetting('captcha.recaptcha_secret_key', '');

    if ($secretKey === '')
    {
        return true; // No secret key configured — pass through
    }

    $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
    $postData  = http_build_query([
        'secret'   => $secretKey,
        'response' => $response,
        'remoteip' => g2ml_getClientIP(),
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 5,
        ],
    ]);

    $result = @file_get_contents($verifyURL, false, $context);

    if ($result === false)
    {
        error_log('[Go2My.Link] WARNING: reCAPTCHA verification request failed.');
        return false;
    }

    $data = json_decode($result, true);

    return isset($data['success']) && $data['success'] === true;
}

// Load CAPTCHA JS SDK if needed
if ($captchaType === 'turnstile') {
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php } elseif ($captchaType === 'recaptcha') { ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php } ?>
