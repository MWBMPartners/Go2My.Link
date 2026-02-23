<?php
/**
 * ============================================================================
 * 🔑 Go2My.Link — Reset Password Page (Component A)
 * ============================================================================
 *
 * Password reset form. Requires a valid token from the forgot-password email.
 * Validates the token before showing the form, then resets the password.
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

$pageTitle = function_exists('__') ? __('reset_password.title') : 'Reset Password';
$pageDesc  = function_exists('__') ? __('reset_password.description') : 'Choose a new password for your account.';

// ============================================================================
// Validate the token from the URL
// ============================================================================

$token       = $_GET['token'] ?? '';
$tokenValid  = false;
$tokenError  = '';
$formError   = '';
$formSuccess = false;

if ($token === '')
{
    $tokenError = function_exists('__')
        ? __('reset_password.error_no_token')
        : 'No reset token provided. Please use the link from your email.';
}
else
{
    $tokenCheck = validatePasswordResetToken($token);

    if ($tokenCheck['valid'])
    {
        $tokenValid = true;
    }
    else
    {
        $tokenError = $tokenCheck['error'];
    }
}

// ============================================================================
// Process form submission
// ============================================================================

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'reset_password_form'))
    {
        $formError = function_exists('__')
            ? __('reset_password.error_csrf')
            : 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        $newPassword     = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword)
        {
            $formError = function_exists('__')
                ? __('reset_password.error_password_mismatch')
                : 'Passwords do not match.';
        }
        else
        {
            $result = resetPassword($token, $newPassword);

            if ($result['success'])
            {
                $formSuccess = true;
            }
            else
            {
                $formError = $result['error'];
            }
        }
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="reset-heading">
    <div class="container">
        <h1 id="reset-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('reset_password.heading') : 'Reset Password'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('reset_password.subtitle') : 'Choose a new password for your account.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Reset Form                                                              -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="form-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="form-heading" class="h5 mb-3">
                            <i class="fas fa-key" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('reset_password.form_heading') : 'New Password'; ?>
                        </h2>

                        <?php if ($tokenError !== '') { ?>
                        <!-- Invalid/expired token -->
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($tokenError); ?>
                        </div>
                        <div class="text-center">
                            <a href="/forgot-password" class="btn btn-primary">
                                <i class="fas fa-redo" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('reset_password.request_new') : 'Request a New Link'; ?>
                            </a>
                        </div>

                        <?php } elseif ($formSuccess) { ?>
                        <!-- Password reset successful -->
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?php echo function_exists('__')
                                ? __('reset_password.success')
                                : 'Your password has been reset successfully. You can now log in with your new password.'; ?>
                        </div>
                        <div class="text-center">
                            <a href="/login?reset=1" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('reset_password.go_to_login') : 'Go to Login'; ?>
                            </a>
                        </div>

                        <?php } else { ?>
                        <!-- Reset form -->

                            <?php if ($formError !== '') { ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($formError); ?>
                            </div>
                            <?php } ?>

                            <form action="/reset-password?token=<?php echo urlencode($token); ?>" method="POST" id="reset-password-form" novalidate>
                                <?php echo g2ml_csrfField('reset_password_form'); ?>

                                <?php
                                echo formField([
                                    'id'           => 'reset-password',
                                    'name'         => 'password',
                                    'label'        => function_exists('__') ? __('reset_password.label_password') : 'New Password',
                                    'type'         => 'password',
                                    'placeholder'  => function_exists('__') ? __('reset_password.placeholder_password') : 'At least 8 characters',
                                    'required'     => true,
                                    'autocomplete' => 'new-password',
                                    'helpText'     => function_exists('__') ? __('reset_password.password_help') : 'Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.',
                                ]);

                                echo formField([
                                    'id'           => 'reset-confirm-password',
                                    'name'         => 'confirm_password',
                                    'label'        => function_exists('__') ? __('reset_password.label_confirm_password') : 'Confirm New Password',
                                    'type'         => 'password',
                                    'placeholder'  => function_exists('__') ? __('reset_password.placeholder_confirm_password') : 'Re-enter your new password',
                                    'required'     => true,
                                    'autocomplete' => 'new-password',
                                ]);
                                ?>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-key" aria-hidden="true"></i>
                                        <?php echo function_exists('__') ? __('reset_password.submit_button') : 'Reset Password'; ?>
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
