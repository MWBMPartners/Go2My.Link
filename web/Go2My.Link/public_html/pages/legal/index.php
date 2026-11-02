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
 * Go2My.Link — Legal Hub Page (Component A)
 * ============================================================================
 *
 * Front door of the legal section at /legal: one card per legal document.
 * The Terms and Acceptable Use pages link "Back to Legal" here; before this
 * page existed that link was a 404 (#284). Each card reuses its document's
 * own title and description translation keys.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.1.0
 * @since      v1.0.0 — Launch Hardening (#284)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('legal.hub_title');
} else {
    $pageTitle = 'Legal';
}
if (function_exists('__')) {
    $pageDesc = __('legal.hub_description');
} else {
    $pageDesc = 'The terms, privacy and cookie policies, acceptable use rules and copyright notice for Go2My.Link.';
}
?>

<section class="page-header text-center" aria-labelledby="legal-heading">
    <div class="container">
        <h1 id="legal-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('legal.hub_title'); } else { echo 'Legal'; } ?>
        </h1>
        <p class="lead text-body-secondary mb-0">
            <?php if (function_exists('__')) { echo __('legal.hub_intro'); } else { echo 'The rules for using Go2My.Link, and how we look after your information.'; } ?>
        </p>
    </div>
</section>

<section class="py-5 bg-body-tertiary">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">
                            <i class="fas fa-file-contract text-primary" aria-hidden="true"></i>
                            <a href="/legal/terms" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('legal.terms_title'); } else { echo 'Terms of Use'; } ?>
                            </a>
                        </h2>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('legal.terms_description'); } else { echo 'Go2My.Link Terms of Use — governing use of our URL shortening and link management services.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">
                            <i class="fas fa-user-shield text-primary" aria-hidden="true"></i>
                            <a href="/legal/privacy" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('legal.privacy_title'); } else { echo 'Privacy Policy'; } ?>
                            </a>
                        </h2>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('legal.privacy_description'); } else { echo 'Go2My.Link Privacy Policy — how we collect, use, and protect your personal data.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">
                            <i class="fas fa-cookie-bite text-primary" aria-hidden="true"></i>
                            <a href="/legal/cookies" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('legal.cookies_title'); } else { echo 'Cookie Policy'; } ?>
                            </a>
                        </h2>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('legal.cookies_description'); } else { echo 'Go2My.Link Cookie Policy — how we use cookies and similar technologies.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">
                            <i class="fas fa-scale-balanced text-primary" aria-hidden="true"></i>
                            <a href="/legal/acceptable-use" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('legal.aup_title'); } else { echo 'Acceptable Use Policy'; } ?>
                            </a>
                        </h2>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('legal.aup_description'); } else { echo 'Go2My.Link Acceptable Use Policy — rules governing use of our services.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">
                            <i class="fas fa-copyright text-primary" aria-hidden="true"></i>
                            <a href="/legal/copyright" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('legal.copyright_title'); } else { echo 'Copyright Notice'; } ?>
                            </a>
                        </h2>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('legal.copyright_description'); } else { echo 'Go2My.Link Copyright Notice and DMCA takedown procedures.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
