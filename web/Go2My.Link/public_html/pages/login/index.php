<?php
/**
 * ============================================================================
 * 🔐 Go2My.Link — Login Page (Component A)
 * ============================================================================
 *
 * Login form with CSRF protection, CAPTCHA after N failed attempts,
 * account lockout display, and redirect-back support.
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
    $pageTitle = __('login.title');
} else {
    $pageTitle = 'Log In';
}
if (function_exists('__')) {
    $pageDesc = __('login.description');
} else {
    $pageDesc = 'Log in to your Go2My.Link account.';
}

// ============================================================================
// Determine if CAPTCHA should be shown (after N failed attempts from this IP)
// ============================================================================

$captchaThreshold = (int) getSetting('auth.login_captcha_threshold', 3);
$clientIP         = g2ml_getClientIP();

$failedFromIP = dbSelectOne(
    "SELECT COUNT(*) AS cnt
     FROM tblActivityLog
     WHERE logAction = 'login'
       AND logStatus = 'wrong_password'
       AND ipAddress = ?
       AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    's',
    [$clientIP]
);

if (($failedFromIP !== null && $failedFromIP !== false)) {
    $recentFailures = (int) $failedFromIP['cnt'];
} else {
    $recentFailures = 0;
}

$showCaptcha = ($recentFailures >= $captchaThreshold);

// CAPTCHA config
$turnstileSiteKey = getSetting('captcha.turnstile_site_key', '');
$recaptchaSiteKey = getSetting('captcha.recaptcha_site_key', '');
$captchaType      = 'none';

if ($showCaptcha)
{
    if ($turnstileSiteKey !== '')
    {
        $captchaType = 'turnstile';
    }
    elseif ($recaptchaSiteKey !== '')
    {
        $captchaType = 'recaptcha';
    }
}

// ============================================================================
// Process form submission
// ============================================================================

$formError  = '';
$isLocked   = false;
$lockSeconds = 0;

// Get redirect URL from query string (for post-login redirect)
if (isset($_GET['redirect'])) {
    $redirectURL = g2ml_sanitiseInput($_GET['redirect']);
} else {
    $redirectURL = '';
}

// Flash message from registration or password reset
$flashMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1')
{
    if (function_exists('__')) {
        $flashMessage = __('login.flash_registered');
    } else {
        $flashMessage = 'Account created! Please check your email to verify your address, then log in.';
    }
}
elseif (isset($_GET['reset']) && $_GET['reset'] === '1')
{
    if (function_exists('__')) {
        $flashMessage = __('login.flash_password_reset');
    } else {
        $flashMessage = 'Your password has been reset. You can now log in with your new password.';
    }
}
elseif (isset($_GET['verified']) && $_GET['verified'] === '1')
{
    if (function_exists('__')) {
        $flashMessage = __('login.flash_email_verified');
    } else {
        $flashMessage = 'Email verified successfully! You can now log in.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'login_form'))
    {
        if (function_exists('__')) {
            $formError = __('login.error_csrf');
        } else {
            $formError = 'Your session has expired. Please reload the page and try again.';
        }
    }
    else
    {
        // Verify CAPTCHA if required
        $captchaValid = true;

        if ($showCaptcha)
        {
            if ($turnstileSiteKey !== '' && function_exists('_g2ml_verifyTurnstile'))
            {
                $captchaResponse = $_POST['cf-turnstile-response'] ?? '';
                $captchaValid    = _g2ml_verifyTurnstile($captchaResponse);
            }
            elseif ($recaptchaSiteKey !== '' && function_exists('_g2ml_verifyRecaptcha'))
            {
                $captchaResponse = $_POST['g-recaptcha-response'] ?? '';
                $captchaValid    = _g2ml_verifyRecaptcha($captchaResponse);
            }
        }

        if (!$captchaValid)
        {
            if (function_exists('__')) {
                $formError = __('login.error_captcha');
            } else {
                $formError = 'CAPTCHA verification failed. Please try again.';
            }
        }
        else
        {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $result = loginUser($email, $password);

            if ($result['success'])
            {
                // Determine where to redirect
                $postLoginRedirect = 'https://admin.go2my.link/';

                $postRedirect = $_POST['redirect'] ?? '';

                if ($postRedirect !== '' && strpos($postRedirect, '/') === 0)
                {
                    // Only allow relative paths (prevent open redirect)
                    $postLoginRedirect = $postRedirect;
                }

                header('Location: ' . $postLoginRedirect);
                exit;
            }
            else
            {
                $formError   = $result['error'];
                $isLocked    = $result['locked'];
                $lockSeconds = $result['lockSeconds'];
            }
        }
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="login-heading">
    <div class="container">
        <h1 id="login-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('login.heading'); } else { echo 'Log In'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('login.subtitle'); } else { echo 'Sign in to manage your short links and analytics.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Login Form                                                              -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="form-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="form-heading" class="h5 mb-3">
                            <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('login.form_heading'); } else { echo 'Sign In'; } ?>
                        </h2>

                        <?php if ($flashMessage !== '') { ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($flashMessage); ?>
                        </div>
                        <?php } ?>

                        <?php if ($formError !== '') { ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($formError); ?>
                        </div>
                        <?php } ?>

                        <?php if ($isLocked) { ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('login.locked_message'); } else { echo 'Account temporarily locked. Please try again later or reset your password.'; } ?>
                        </div>
                        <?php } ?>

                        <form action="/login<?php if ($redirectURL !== '') { echo '?redirect=' . urlencode($redirectURL); } ?>" method="POST" id="login-form" novalidate>
                            <?php echo g2ml_csrfField('login_form'); ?>

                            <?php if ($redirectURL !== '') { ?>
                            <input type="hidden" name="redirect" value="<?php echo g2ml_sanitiseOutput($redirectURL); ?>">
                            <?php } ?>

                            <?php
                                if (function_exists('__')) {
                                    $fieldLabel = __('login.label_email');
                                } else {
                                    $fieldLabel = 'Email Address';
                                }
                                if (function_exists('__')) {
                                    $fieldPlaceholder = __('login.placeholder_email');
                                } else {
                                    $fieldPlaceholder = 'you@example.com';
                                }
                                if (isset($_POST['email'])) {
                                    $fieldValue = g2ml_sanitiseOutput($_POST['email']);
                                } else {
                                    $fieldValue = '';
                                }
                            echo formField([
                                'id'           => 'login-email',
                                'name'         => 'email',
                                'label' => $fieldLabel,
                                'type'         => 'email',
                                'placeholder' => $fieldPlaceholder,
                                'required'     => true,
                                'autocomplete' => 'email',
                                'value' => $fieldValue,
                            ]);

                                if (function_exists('__')) {
                                    $fieldLabel = __('login.label_password');
                                } else {
                                    $fieldLabel = 'Password';
                                }
                                if (function_exists('__')) {
                                    $fieldPlaceholder = __('login.placeholder_password');
                                } else {
                                    $fieldPlaceholder = 'Your password';
                                }
                            echo formField([
                                'id'           => 'login-password',
                                'name'         => 'password',
                                'label' => $fieldLabel,
                                'type'         => 'password',
                                'placeholder' => $fieldPlaceholder,
                                'required'     => true,
                                'autocomplete' => 'current-password',
                            ]);
                            ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember-me" name="remember_me" value="1">
                                    <label class="form-check-label" for="remember-me">
                                        <?php if (function_exists('__')) { echo __('login.remember_me'); } else { echo 'Remember me'; } ?>
                                    </label>
                                </div>
                                <a href="/forgot-password" class="small">
                                    <?php if (function_exists('__')) { echo __('login.forgot_password'); } else { echo 'Forgot password?'; } ?>
                                </a>
                            </div>

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
                                <button type="submit" class="btn btn-primary btn-lg" <?php if ($isLocked) { echo 'disabled'; } ?>>
                                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                    <?php if (function_exists('__')) { echo __('login.submit_button'); } else { echo 'Log In'; } ?>
                                </button>
                            </div>

                            <p class="text-center text-body-secondary small mb-0">
                                <?php if (function_exists('__')) { echo __('login.no_account'); } else { echo "Don't have an account?"; } ?>
                                <a href="/register"><?php if (function_exists('__')) { echo __('login.register_link'); } else { echo 'Sign up'; } ?></a>
                            </p>
                        </form>
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
