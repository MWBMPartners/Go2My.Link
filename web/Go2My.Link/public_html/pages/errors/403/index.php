<?php
/**
 * ============================================================================
 * 🔒 Go2My.Link — 403 Forbidden Error Page (Component A)
 * ============================================================================
 *
 * Branded error page for access denied / forbidden requests.
 * Renders inside the full template (header/nav/footer).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('error.403_title') : '403 — Forbidden';
$pageDesc  = function_exists('__') ? __('error.403_description') : 'You do not have permission to access this resource.';

http_response_code(403);
?>

<section class="py-5" aria-labelledby="error-heading">
    <div class="container" style="max-width:600px;">
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-lock fa-4x text-danger" aria-hidden="true"></i>
            </div>
            <h1 id="error-heading" class="display-4 fw-bold mb-3">403</h1>
            <h2 class="h4 mb-3">
                <?php echo function_exists('__') ? __('error.403_heading') : 'Access Denied'; ?>
            </h2>
            <p class="text-body-secondary mb-4">
                <?php echo function_exists('__') ? __('error.403_message') : 'You don\'t have permission to access this page. If you believe this is an error, please contact support.'; ?>
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('error.go_home') : 'Go Home'; ?>
                </a>
                <a href="/login" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('nav.login') : 'Log In'; ?>
                </a>
            </div>
        </div>
    </div>
</section>
