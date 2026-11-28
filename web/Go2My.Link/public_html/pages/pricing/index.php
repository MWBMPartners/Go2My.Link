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
 * Shows the Free plan and says paid plans are coming (#209, decision D-020).
 * It shows no prices. It does not describe a statistics time window or limit
 * the API by plan, because neither is enforced yet.
 *
 * The 50-link sentence covers people with and without an organisation: the
 * limit is checked when a link is created or switched back on (#327,
 * web/Go2My.Link/_functions/link_limit.php). The number is written in the text,
 * not read from the plan data, so it must be changed here if the plan changes.
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
    $pageDesc = __('pricing.description_v2');
} else {
    $pageDesc = 'The Go2My.Link Free plan. Paid plans are coming.';
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
            <?php if (function_exists('__')) { echo __('pricing.subtitle_v2'); } else { echo 'The Free plan is available now. Paid plans are coming.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Plans                                                                   -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="tiers-heading">
    <div class="container">
        <h2 id="tiers-heading" class="visually-hidden">
            <?php if (function_exists('__')) { echo __('pricing.tiers_heading'); } else { echo 'Plans'; } ?>
        </h2>
        <div class="row g-4 justify-content-center">

            <!-- Free plan -->
            <div class="col-md-6 col-lg-5">
                <div class="card h-100 border-primary shadow">
                    <div class="card-body text-center p-4">
                        <h3 class="h4"><?php if (function_exists('__')) { echo __('pricing.tier_free'); } else { echo 'Free'; } ?></h3>
                        <p class="text-body-secondary">
                            <?php if (function_exists('__')) { echo __('pricing.free_desc_v2'); } else { echo 'For shortening and looking after your links.'; } ?>
                        </p>
                        <ul class="list-unstyled text-start mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_limit_v3'); } else { echo 'You can create a link, or switch one back on, while fewer than 50 are switched on. A link past its end date still counts until it is switched off. Without an organisation the 50 are your own; in an organisation they are shared by all members.'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_statistics'); } else { echo 'Click statistics for your links'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_feature_3'); } else { echo 'g2my.link short domain'; } ?></li>
                            <li class="mb-2"><i class="fas fa-check text-success" aria-hidden="true"></i> <?php if (function_exists('__')) { echo __('pricing.free_feature_linkspage'); } else { echo 'A link page on Lnks.Page'; } ?></li>
                        </ul>
                        <a href="/register" class="btn btn-primary">
                            <?php if (function_exists('__')) { echo __('pricing.get_started'); } else { echo 'Get Started Free'; } ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Paid plans: no names, no prices and no feature list (D-020) -->
            <div class="col-md-6 col-lg-5">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h3 class="h4"><?php if (function_exists('__')) { echo __('pricing.paid_heading'); } else { echo 'Paid plans'; } ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('pricing.paid_text'); } else { echo 'Paid plans are coming. They cannot be bought yet.'; } ?>
                        </p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Using the service without an account -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-8 text-center">
                <div class="alert alert-info" role="note">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('pricing.anonymous_note'); } else { echo 'You can also shorten links on the home page without an account. Hourly and daily limits apply.'; } ?>
                </div>
            </div>
        </div>
    </div>
</section>
