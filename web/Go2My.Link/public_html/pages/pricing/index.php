<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

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

if (function_exists('__')) {
    $pageTitle = __('pricing.title');
} else {
    $pageTitle = 'Pricing';
}
if (function_exists('__')) {
    $pageDesc = __('pricing.description');
} else {
    $pageDesc = 'Go2My.Link pricing plans — free and premium options.';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="pricing-heading">
    <div class="container">
        <h1 id="pricing-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('pricing.heading'); } else { echo 'Pricing'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('pricing.subtitle'); } else { echo 'Simple, transparent pricing for everyone.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Pricing Tiers Preview                                                   -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="tiers-heading">
    <div class="container">
        <h2 id="tiers-heading" class="visually-hidden">
            <?php if (function_exists('__')) { echo __('pricing.tiers_heading'); } else { echo 'Plans'; } ?>
        </h2>
        <div class="row g-4 justify-content-center">

            <!-- Free Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-body-secondary"><?php if (function_exists('__')) { echo __('pricing.tier_free'); } else { echo 'Free'; } ?></h3>
                        <div class="display-5 fw-bold my-3">$0</div>
                        <p class="text-body-secondary">
                            <?php if (function_exists('__')) { echo __('pricing.tier_free_desc'); } else { echo 'Perfect for personal use and trying out the platform.'; } ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_feature_1'); } else { echo 'Unlimited short links'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_feature_2'); } else { echo 'Basic click analytics'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_feature_3'); } else { echo 'g2my.link short domain'; } ?></li>
                        </ul>
                        <a href="/" class="btn btn-outline-primary">
                            <?php if (function_exists('__')) { echo __('pricing.get_started'); } else { echo 'Get Started Free'; } ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pro Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-primary shadow">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-primary"><?php if (function_exists('__')) { echo __('pricing.tier_pro'); } else { echo 'Pro'; } ?></h3>
                        <div class="display-5 fw-bold my-3">
                            <span class="text-body-secondary fs-6"><?php if (function_exists('__')) { echo __('pricing.coming_soon'); } else { echo 'Coming Soon'; } ?></span>
                        </div>
                        <p class="text-body-secondary">
                            <?php if (function_exists('__')) { echo __('pricing.tier_pro_desc'); } else { echo 'For professionals and small teams who need more.'; } ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.pro_feature_1'); } else { echo 'Everything in Free'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.pro_feature_2'); } else { echo 'Custom short domains'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.pro_feature_3'); } else { echo 'Advanced analytics'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.pro_feature_4'); } else { echo 'API access'; } ?></li>
                        </ul>
                        <button class="btn btn-primary" disabled>
                            <?php if (function_exists('__')) { echo __('pricing.coming_soon'); } else { echo 'Coming Soon'; } ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enterprise Tier -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h3 class="h5 text-body-secondary"><?php if (function_exists('__')) { echo __('pricing.tier_enterprise'); } else { echo 'Enterprise'; } ?></h3>
                        <div class="display-5 fw-bold my-3">
                            <span class="text-body-secondary fs-6"><?php if (function_exists('__')) { echo __('pricing.coming_soon'); } else { echo 'Coming Soon'; } ?></span>
                        </div>
                        <p class="text-body-secondary">
                            <?php if (function_exists('__')) { echo __('pricing.tier_enterprise_desc'); } else { echo 'For organisations with advanced needs.'; } ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.enterprise_feature_1'); } else { echo 'Everything in Pro'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.enterprise_feature_2'); } else { echo 'SSO / SAML integration'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.enterprise_feature_3'); } else { echo 'Dedicated support'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.enterprise_feature_4'); } else { echo 'Custom SLA'; } ?></li>
                        </ul>
                        <a href="/contact" class="btn btn-outline-primary">
                            <?php if (function_exists('__')) { echo __('pricing.contact_sales'); } else { echo 'Contact Sales'; } ?>
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
                    <?php if (function_exists('__')) { echo __('pricing.phase9_notice'); } else { echo 'Detailed pricing and subscription management are coming in a future update. Anonymous URL shortening is free and unlimited (rate limits apply).'; } ?>
                </div>
            </div>
        </div>
    </div>
</section>
