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
 * Go2My.Link — Help Hub Page (Component A)
 * ============================================================================
 *
 * Front door of the help section at /help. Introduces the help pages,
 * links out to the four topic pages, and answers the questions a new
 * customer asks first.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.1.0
 * @since      Phase 8
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('help.home.title');
} else {
    $pageTitle = 'Help';
}
if (function_exists('__')) {
    $pageDesc = __('help.home.description');
} else {
    $pageDesc = 'Guides and quick answers for using Go2My.Link.';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="help-heading">
    <div class="container">
        <h1 id="help-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('help.home.heading'); } else { echo 'Help'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('help.home.subtitle'); } else { echo 'Guides and quick answers for using Go2My.Link.'; } ?>
        </p>
        <p class="text-body-secondary mb-0">
            <?php if (function_exists('__')) { echo __('help.home.intro'); } else { echo 'This page brings together our step-by-step guides and the questions people ask most when they are getting started. Pick a topic below for a full guide, or scan the quick answers further down if you just need a quick answer.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Help Topics                                                             -->
<!-- ====================================================================== -->
<section class="py-5 bg-body-tertiary" aria-labelledby="topics-heading">
    <div class="container">
        <h2 id="topics-heading" class="h3 text-center mb-4">
            <?php if (function_exists('__')) { echo __('help.home.topics_heading'); } else { echo 'Help topics'; } ?>
        </h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-link text-primary" aria-hidden="true"></i>
                            <a href="/help/short-links" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('help.home.topic_shortlinks_title'); } else { echo 'Creating and managing short links'; } ?>
                            </a>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('help.home.topic_shortlinks_desc'); } else { echo 'How to turn a long web address into a short one, choose your own ending, and change where it points later.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-chart-bar text-success" aria-hidden="true"></i>
                            <a href="/help/analytics" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('help.home.topic_analytics_title'); } else { echo 'Understanding your click statistics'; } ?>
                            </a>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('help.home.topic_analytics_desc'); } else { echo 'How to see how many people clicked your links, and where those clicks came from.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-globe text-info" aria-hidden="true"></i>
                            <a href="/help/custom-domains" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('help.home.topic_domains_title'); } else { echo 'Using your own short domain'; } ?>
                            </a>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('help.home.topic_domains_desc'); } else { echo 'How to send your short links out from a web address you already own, instead of g2my.link.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-code text-danger" aria-hidden="true"></i>
                            <a href="/help/api" class="stretched-link text-decoration-none">
                                <?php if (function_exists('__')) { echo __('help.home.topic_api_title'); } else { echo 'Using the API'; } ?>
                            </a>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php if (function_exists('__')) { echo __('help.home.topic_api_desc'); } else { echo 'How to create and manage short links from your own code, without using the website.'; } ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Quick Answers                                                           -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="quick-answers-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="quick-answers-heading" class="h3 text-center mb-4">
                    <?php if (function_exists('__')) { echo __('help.home.qa_heading'); } else { echo 'Quick answers'; } ?>
                </h2>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa1_q'); } else { echo 'Do I need an account to shorten a link?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa1_a'); } else { echo 'No. Go to the Go2My.Link homepage, paste your long web address, and press Shorten URL — no sign-in needed. Creating a free account additionally lets you choose your own ending for a link, give it a start and end date, and change or track it later.'; } ?>
                </p>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa2_q'); } else { echo 'How long does a short link last?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa2_a'); } else { echo 'A short link works for as long as you like — there is no automatic expiry. If you are signed in, you can optionally give a link a start date and an end date when you create or edit it, after which it stops working.'; } ?>
                </p>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa3_q'); } else { echo 'Can I choose my own ending instead of random letters?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa3_a'); } else { echo 'Yes, once you are signed in. When you create a link from your dashboard, you can type your own ending instead of letting Go2My.Link pick one at random. An ending cannot be changed once the link has been created, so choose it carefully.'; } ?>
                </p>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa4_q'); } else { echo 'Can I change where a link points after I have shared it?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa4_a'); } else { echo 'Yes, if you created the link while signed in. Open Links in your dashboard, edit the link, and change its destination web address — the short link itself stays exactly the same, so anyone who already has it will now be sent to the new destination. A link created anonymously from the homepage cannot be edited afterwards, because it is not attached to any account.'; } ?>
                </p>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa5_q'); } else { echo 'Where do I see how many people have clicked my link?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa5_a'); } else { echo 'Sign in and open Analytics in your dashboard. It shows your total clicks over time, your best-performing links, and a breakdown by browser, device, and country.'; } ?>
                </p>

                <h3 class="h6 fw-bold mt-4 mb-2">
                    <?php if (function_exists('__')) { echo __('help.home.qa6_q'); } else { echo 'How do I get help if something is wrong?'; } ?>
                </h3>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.qa6_a'); } else { echo 'Send us a message from our Contact page and we will get back to you.'; } ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Still Need Help                                                         -->
<!-- ====================================================================== -->
<section class="py-5 bg-body-tertiary" aria-labelledby="still-need-help-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 id="still-need-help-heading" class="h3 mb-3">
                    <?php if (function_exists('__')) { echo __('help.home.closing_heading'); } else { echo 'Still need help?'; } ?>
                </h2>
                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.home.closing_text'); } else { echo 'If none of the topics above cover what you need, get in touch and we will help you directly. You may also want to read our legal pages.'; } ?>
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                    <a href="/contact" class="btn btn-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.home.contact_cta'); } else { echo 'Contact us'; } ?>
                    </a>
                    <a href="/legal/privacy" class="btn btn-outline-secondary">
                        <i class="fas fa-user-shield" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.home.legal_privacy'); } else { echo 'Privacy Policy'; } ?>
                    </a>
                    <a href="/legal/terms" class="btn btn-outline-secondary">
                        <i class="fas fa-file-contract" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.home.legal_terms'); } else { echo 'Terms of Use'; } ?>
                    </a>
                    <a href="/legal/cookies" class="btn btn-outline-secondary">
                        <i class="fas fa-cookie-bite" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.home.legal_cookies'); } else { echo 'Cookie Policy'; } ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
