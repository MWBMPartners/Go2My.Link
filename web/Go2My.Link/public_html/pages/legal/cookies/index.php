<?php
/**
 * ============================================================================
 * GoToMyLink — Cookie Policy Page (Component A)
 * ============================================================================
 *
 * Placeholder page for Cookie Policy. Full legal content will be added
 * in Phase 10 (Legal & Compliance).
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3 (full content Phase 10)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('legal.cookies_title') : 'Cookie Policy';
$pageDesc  = function_exists('__') ? __('legal.cookies_description') : 'GoToMyLink Cookie Policy.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="cookies-heading">
    <div class="container">
        <h1 id="cookies-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('legal.cookies_heading') : 'Cookie Policy'; ?>
        </h1>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Placeholder Content                                                     -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="cookies-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="cookies-content-heading" class="visually-hidden">Cookie Policy Content</h2>

                <div class="legal-placeholder text-center py-5">
                    <i class="fas fa-cookie-bite fa-3x text-body-secondary mb-3" aria-hidden="true"></i>
                    <h3 class="h5">
                        <?php echo function_exists('__') ? __('legal.placeholder_heading') : 'Coming Soon'; ?>
                    </h3>
                    <p class="text-body-secondary mb-0">
                        <?php echo function_exists('__') ? __('legal.cookies_placeholder') : 'Our Cookie Policy is being prepared and will be published in a future update. GoToMyLink uses essential cookies for session management and theme preferences. We do not use third-party tracking cookies.'; ?>
                    </p>
                </div>

                <div class="mt-4 text-center">
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.contact_cta') : 'Questions? Contact Us'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
