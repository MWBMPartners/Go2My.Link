<?php
/**
 * ============================================================================
 * GoToMyLink — Terms of Use Page (Component A)
 * ============================================================================
 *
 * Placeholder page for Terms of Use. Full legal content will be added
 * in Phase 10 (Legal & Compliance).
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3 (full content Phase 10)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('legal.terms_title') : 'Terms of Use';
$pageDesc  = function_exists('__') ? __('legal.terms_description') : 'GoToMyLink Terms of Use.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="terms-heading">
    <div class="container">
        <h1 id="terms-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('legal.terms_heading') : 'Terms of Use'; ?>
        </h1>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Placeholder Content                                                     -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="terms-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="terms-content-heading" class="visually-hidden">Terms of Use Content</h2>

                <div class="legal-placeholder text-center py-5">
                    <i class="fas fa-file-contract fa-3x text-body-secondary mb-3" aria-hidden="true"></i>
                    <h3 class="h5">
                        <?php echo function_exists('__') ? __('legal.placeholder_heading') : 'Coming Soon'; ?>
                    </h3>
                    <p class="text-body-secondary mb-0">
                        <?php echo function_exists('__') ? __('legal.terms_placeholder') : 'Our Terms of Use are being prepared and will be published in a future update. By using GoToMyLink, you agree to use the service responsibly and in accordance with applicable laws.'; ?>
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
