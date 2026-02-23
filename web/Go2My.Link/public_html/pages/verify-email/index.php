<?php
/**
 * ============================================================================
 * ✉️ Go2My.Link — Email Verification Page (Component A)
 * ============================================================================
 *
 * Handles email verification via token from URL. Verifies the token on page
 * load and shows success or error.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('verify_email.title') : 'Verify Email';
$pageDesc  = function_exists('__') ? __('verify_email.description') : 'Verify your email address.';

// ============================================================================
// Process the verification token
// ============================================================================

$token       = $_GET['token'] ?? '';
$verified    = false;
$verifyError = '';

if ($token === '')
{
    $verifyError = function_exists('__')
        ? __('verify_email.error_no_token')
        : 'No verification token provided. Please use the link from your email.';
}
else
{
    $result = verifyEmail($token);

    if ($result['success'])
    {
        $verified = true;
    }
    else
    {
        $verifyError = $result['error'];
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="verify-heading">
    <div class="container">
        <h1 id="verify-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('verify_email.heading') : 'Email Verification'; ?>
        </h1>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Verification Result                                                     -->
<!-- ====================================================================== -->
<section class="py-5" aria-label="Verification result">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4 text-center">

                        <?php if ($verified) { ?>
                        <!-- Success -->
                        <div class="py-3">
                            <i class="fas fa-check-circle fa-4x text-success mb-3" aria-hidden="true"></i>
                            <h2 class="h4 mb-3">
                                <?php echo function_exists('__') ? __('verify_email.success_heading') : 'Email Verified!'; ?>
                            </h2>
                            <p class="text-body-secondary mb-4">
                                <?php echo function_exists('__')
                                    ? __('verify_email.success_message')
                                    : 'Your email address has been verified successfully. You can now log in and access all features.'; ?>
                            </p>

                            <?php if (function_exists('isAuthenticated') && isAuthenticated()) { ?>
                            <a href="https://admin.go2my.link/" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('verify_email.go_to_dashboard') : 'Go to Dashboard'; ?>
                            </a>
                            <?php } else { ?>
                            <a href="/login?verified=1" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('verify_email.go_to_login') : 'Log In'; ?>
                            </a>
                            <?php } ?>
                        </div>

                        <?php } else { ?>
                        <!-- Error -->
                        <div class="py-3">
                            <i class="fas fa-exclamation-circle fa-4x text-danger mb-3" aria-hidden="true"></i>
                            <h2 class="h4 mb-3">
                                <?php echo function_exists('__') ? __('verify_email.error_heading') : 'Verification Failed'; ?>
                            </h2>
                            <p class="text-body-secondary mb-4">
                                <?php echo g2ml_sanitiseOutput($verifyError); ?>
                            </p>

                            <div class="d-flex justify-content-center gap-2">
                                <a href="/login" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                    <?php echo function_exists('__') ? __('verify_email.go_to_login') : 'Log In'; ?>
                                </a>
                                <a href="/register" class="btn btn-outline-primary">
                                    <i class="fas fa-user-plus" aria-hidden="true"></i>
                                    <?php echo function_exists('__') ? __('verify_email.register_again') : 'Register Again'; ?>
                                </a>
                            </div>
                        </div>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
