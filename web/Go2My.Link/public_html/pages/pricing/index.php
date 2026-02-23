<?php
/**
 * ============================================================================
 * Go2My.Link — Pricing Page (Component A)
 * ============================================================================
 *
 * Pricing placeholder page. Full pricing tiers and Stripe integration
 * will be added in Phase 9 (Billing & Subscriptions).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3 (functional pricing Phase 9)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('pricing.title') : 'Pricing';
$pageDesc  = function_exists('__') ? __('pricing.description') : 'Go2My.Link pricing plans — free and premium options.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="pricing-heading">
    <div class="container">
        <h1 id="pricing-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('pricing.heading') : 'Pricing'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('pricing.subtitle') : 'Simple, transparent pricing for everyone.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Pricing Tiers Preview                                                   -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="tiers-heading">
    <div class="container">
        <h2 id="tiers-heading" class="visually-hidden">
            <?php echo function_exists('__') ? __('pricing.tiers_heading') : 'Plans'; ?>
        </h2>
        <div class="row g-4 justify-content-center">

            <!-- Free Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-body-secondary"><?php echo function_exists('__') ? __('pricing.tier_free') : 'Free'; ?></h3>
                        <div class="display-5 fw-bold my-3">$0</div>
                        <p class="text-body-secondary">
                            <?php echo function_exists('__') ? __('pricing.tier_free_desc') : 'Perfect for personal use and trying out the platform.'; ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.free_feature_1') : 'Unlimited short links'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.free_feature_2') : 'Basic click analytics'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.free_feature_3') : 'g2my.link short domain'; ?></li>
                        </ul>
                        <a href="/" class="btn btn-outline-primary">
                            <?php echo function_exists('__') ? __('pricing.get_started') : 'Get Started Free'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pro Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-primary shadow">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-primary"><?php echo function_exists('__') ? __('pricing.tier_pro') : 'Pro'; ?></h3>
                        <div class="display-5 fw-bold my-3">
                            <span class="text-body-secondary fs-6"><?php echo function_exists('__') ? __('pricing.coming_soon') : 'Coming Soon'; ?></span>
                        </div>
                        <p class="text-body-secondary">
                            <?php echo function_exists('__') ? __('pricing.tier_pro_desc') : 'For professionals and small teams who need more.'; ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.pro_feature_1') : 'Everything in Free'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.pro_feature_2') : 'Custom short domains'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.pro_feature_3') : 'Advanced analytics'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.pro_feature_4') : 'API access'; ?></li>
                        </ul>
                        <button class="btn btn-primary" disabled>
                            <?php echo function_exists('__') ? __('pricing.coming_soon') : 'Coming Soon'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enterprise Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-body-secondary"><?php echo function_exists('__') ? __('pricing.tier_enterprise') : 'Enterprise'; ?></h3>
                        <div class="display-5 fw-bold my-3">
                            <span class="text-body-secondary fs-6"><?php echo function_exists('__') ? __('pricing.coming_soon') : 'Coming Soon'; ?></span>
                        </div>
                        <p class="text-body-secondary">
                            <?php echo function_exists('__') ? __('pricing.tier_enterprise_desc') : 'For organisations with advanced needs.'; ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.enterprise_feature_1') : 'Everything in Pro'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.enterprise_feature_2') : 'SSO / SAML integration'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.enterprise_feature_3') : 'Dedicated support'; ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php echo function_exists('__') ? __('pricing.enterprise_feature_4') : 'Custom SLA'; ?></li>
                        </ul>
                        <a href="/contact" class="btn btn-outline-primary">
                            <?php echo function_exists('__') ? __('pricing.contact_sales') : 'Contact Sales'; ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Coming Soon Notice -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-8 text-center">
                <div class="alert alert-info" role="status">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('pricing.phase9_notice') : 'Detailed pricing and subscription management are coming in a future update. Anonymous URL shortening is free and unlimited (rate limits apply).'; ?>
                </div>
            </div>
        </div>
    </div>
</section>
