<?php
/**
 * ============================================================================
 * GoToMyLink — Privacy Policy Page (Component A)
 * ============================================================================
 *
 * Placeholder page for Privacy Policy. Full legal content will be added
 * in Phase 10 (Legal & Compliance).
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3 (full content Phase 10)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('legal.privacy_title') : 'Privacy Policy';
$pageDesc  = function_exists('__') ? __('legal.privacy_description') : 'GoToMyLink Privacy Policy.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="privacy-heading">
    <div class="container">
        <h1 id="privacy-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('legal.privacy_heading') : 'Privacy Policy'; ?>
        </h1>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Placeholder Content                                                     -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="privacy-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-content-heading" class="visually-hidden">Privacy Policy Content</h2>

                <div class="legal-placeholder text-center py-5">
                    <i class="fas fa-user-shield fa-3x text-body-secondary mb-3" aria-hidden="true"></i>
                    <h3 class="h5">
                        <?php echo function_exists('__') ? __('legal.placeholder_heading') : 'Coming Soon'; ?>
                    </h3>
                    <p class="text-body-secondary mb-0">
                        <?php echo function_exists('__') ? __('legal.privacy_placeholder') : 'Our Privacy Policy is being prepared and will be published in a future update. We are committed to protecting your personal data and being transparent about how we collect and use information.'; ?>
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
