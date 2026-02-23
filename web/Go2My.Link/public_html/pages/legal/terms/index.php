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
 * Go2My.Link — Terms of Use Page (Component A)
 * ============================================================================
 *
 * Structured Terms of Use template with section navigation.
 * Contains {{LEGAL_REVIEW_NEEDED}} placeholders for professional review.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 3 (structured template Phase 6)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('legal.terms_title');
} else {
    $pageTitle = 'Terms of Use';
}
if (function_exists('__')) {
    $pageDesc = __('legal.terms_description');
} else {
    $pageDesc = 'Go2My.Link Terms of Use — governing use of our URL shortening and link management services.';
}

// Legal document metadata
if (function_exists('getSetting')) {
    $legalVersion = getSetting('legal.terms_version', '1.0');
} else {
    $legalVersion = '1.0';
}
if (function_exists('getSetting')) {
    $legalUpdated = getSetting('legal.last_updated', '2026-02-23');
} else {
    $legalUpdated = '2026-02-23';
}
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
$companyName    = 'MWBM Partners Ltd';
$companyTrading = 'MWservices';
$hideReviewPlaceholders = function_exists('getSetting') && getSetting('legal.hide_review_placeholders', '0') === '1';
if (function_exists('getSetting')) {
    $contactEmail = getSetting('site.contact_email', 'hello@go2my.link');
} else {
    $contactEmail = 'hello@go2my.link';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="terms-heading">
    <div class="container">
        <h1 id="terms-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('legal.terms_heading'); } else { echo 'Terms of Use'; } ?>
        </h1>
        <p class="text-body-secondary mt-3 mb-1">
            <span class="badge bg-secondary">
                <?php if (function_exists('__')) { echo __('legal.version'); } else { echo 'Version'; } ?>
                <?php echo htmlspecialchars($legalVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </p>
        <p class="text-body-secondary small">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('legal.last_updated'); } else { echo 'Last Updated'; } ?>:
            <?php echo htmlspecialchars($legalUpdated, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Terms of Use Content                                                    -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="terms-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="terms-content-heading" class="visually-hidden">Terms of Use Content</h2>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="card-title h5 mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('legal.toc_heading'); } else { echo 'Table of Contents'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#terms-s1"><?php if (function_exists('__')) { echo __('legal.terms_s1_title'); } else { echo 'Acceptance of Terms'; } ?></a></li>
                                <li><a href="#terms-s2"><?php if (function_exists('__')) { echo __('legal.terms_s2_title'); } else { echo 'Description of Services'; } ?></a></li>
                                <li><a href="#terms-s3"><?php if (function_exists('__')) { echo __('legal.terms_s3_title'); } else { echo 'User Accounts'; } ?></a></li>
                                <li><a href="#terms-s4"><?php if (function_exists('__')) { echo __('legal.terms_s4_title'); } else { echo 'Acceptable Use'; } ?></a></li>
                                <li><a href="#terms-s5"><?php if (function_exists('__')) { echo __('legal.terms_s5_title'); } else { echo 'Intellectual Property'; } ?></a></li>
                                <li><a href="#terms-s6"><?php if (function_exists('__')) { echo __('legal.terms_s6_title'); } else { echo 'Short URL Policies'; } ?></a></li>
                                <li><a href="#terms-s7"><?php if (function_exists('__')) { echo __('legal.terms_s7_title'); } else { echo 'LinksPage Service'; } ?></a></li>
                                <li><a href="#terms-s8"><?php if (function_exists('__')) { echo __('legal.terms_s8_title'); } else { echo 'API Usage'; } ?></a></li>
                                <li><a href="#terms-s9"><?php if (function_exists('__')) { echo __('legal.terms_s9_title'); } else { echo 'Disclaimers &amp; Warranties'; } ?></a></li>
                                <li><a href="#terms-s10"><?php if (function_exists('__')) { echo __('legal.terms_s10_title'); } else { echo 'Limitation of Liability'; } ?></a></li>
                                <li><a href="#terms-s11"><?php if (function_exists('__')) { echo __('legal.terms_s11_title'); } else { echo 'Indemnification'; } ?></a></li>
                                <li><a href="#terms-s12"><?php if (function_exists('__')) { echo __('legal.terms_s12_title'); } else { echo 'Governing Law'; } ?></a></li>
                                <li><a href="#terms-s13"><?php if (function_exists('__')) { echo __('legal.terms_s13_title'); } else { echo 'Changes to Terms'; } ?></a></li>
                                <li><a href="#terms-s14"><?php if (function_exists('__')) { echo __('legal.terms_s14_title'); } else { echo 'Contact'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: Acceptance of Terms                                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s1">
                    <h2 id="terms-s1" class="h4 mb-3">
                        1. <?php if (function_exists('__')) { echo __('legal.terms_s1_title'); } else { echo 'Acceptance of Terms'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s1_p1'); } else { echo 'By accessing or using the services provided at <strong>go2my.link</strong>, <strong>g2my.link</strong>, and <strong>lnks.page</strong> (collectively, the "Service"), you agree to be bound by these Terms of Use ("Terms"). If you do not agree to all of these Terms, you must not use the Service.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s1_p2'); } else { echo 'These Terms constitute a legally binding agreement between you ("User", "you", or "your") and <strong>MWBM Partners Ltd</strong>, trading as <strong>MWservices</strong> ("Company", "we", "us", or "our").'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s1_p3'); } else { echo 'You must be at least 13 years of age to use the Service. By using the Service, you represent and warrant that you are at least 13 years old. If you are under 18, you represent that your parent or legal guardian has reviewed and agreed to these Terms on your behalf.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: Description of Services                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s2">
                    <h2 id="terms-s2" class="h4 mb-3">
                        2. <?php if (function_exists('__')) { echo __('legal.terms_s2_title'); } else { echo 'Description of Services'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s2_p1'); } else { echo 'The Service provides URL shortening, link management, and related tools. Our core offerings include:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.terms_s2_li1_label'); } else { echo 'URL Shortening'; } ?></strong>
                            &mdash;
                            <?php if (function_exists('__')) { echo __('legal.terms_s2_li1_desc'); } else { echo 'Create shortened URLs via <strong>g2my.link</strong> that redirect to your original destination URLs.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.terms_s2_li2_label'); } else { echo 'Link Management'; } ?></strong>
                            &mdash;
                            <?php if (function_exists('__')) { echo __('legal.terms_s2_li2_desc'); } else { echo 'Manage, edit, and organise your shortened URLs through the <strong>go2my.link</strong> dashboard.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.terms_s2_li3_label'); } else { echo 'Analytics'; } ?></strong>
                            &mdash;
                            <?php if (function_exists('__')) { echo __('legal.terms_s2_li3_desc'); } else { echo 'View click statistics and performance data for your shortened URLs.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.terms_s2_li4_label'); } else { echo 'LinksPage Profiles'; } ?></strong>
                            &mdash;
                            <?php if (function_exists('__')) { echo __('legal.terms_s2_li4_desc'); } else { echo 'Create customisable profile landing pages hosted on <strong>lnks.page</strong>.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.terms_s2_li5_label'); } else { echo 'API Access'; } ?></strong>
                            &mdash;
                            <?php if (function_exists('__')) { echo __('legal.terms_s2_li5_desc'); } else { echo 'Programmatic access to URL creation and management via our REST API (subject to plan limits).'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s2_p2'); } else { echo 'We reserve the right to modify, suspend, or discontinue any part of the Service at any time, with or without notice. Certain features may only be available to users on paid subscription plans.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: User Accounts                                      -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s3">
                    <h2 id="terms-s3" class="h4 mb-3">
                        3. <?php if (function_exists('__')) { echo __('legal.terms_s3_title'); } else { echo 'User Accounts'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_sub1'); } else { echo 'Registration'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_p1'); } else { echo 'Some features of the Service require you to create an account. When registering, you must provide accurate, current, and complete information. You agree to update your information to keep it accurate and current.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_sub2'); } else { echo 'Account Security'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_p2'); } else { echo 'You are responsible for maintaining the confidentiality of your account credentials, including your password. You agree to notify us immediately of any unauthorised use of your account. We are not liable for any loss or damage arising from your failure to protect your account credentials.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_sub3'); } else { echo 'One Account Per Person'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s3_p3'); } else { echo 'Each individual may maintain only one personal account. Creating multiple accounts to circumvent limits, restrictions, or bans is prohibited and may result in termination of all associated accounts.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Acceptable Use                                     -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s4">
                    <h2 id="terms-s4" class="h4 mb-3">
                        4. <?php if (function_exists('__')) { echo __('legal.terms_s4_title'); } else { echo 'Acceptable Use'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s4_p1'); } else { echo 'Your use of the Service is governed by our <a href="/legal/acceptable-use">Acceptable Use Policy</a> (AUP), which is incorporated into these Terms by reference. You agree to comply with the AUP at all times.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s4_p2'); } else { echo 'Without limiting the AUP, you must not use the Service to:'; } ?>
                    </p>

                    <ul>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s4_li1'); } else { echo 'Distribute malware, viruses, or other harmful software.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s4_li2'); } else { echo 'Conduct phishing attacks or fraudulent schemes.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s4_li3'); } else { echo 'Link to illegal content or facilitate illegal activities.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s4_li4'); } else { echo 'Send unsolicited bulk messages (spam) using shortened URLs.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s4_li5'); } else { echo 'Infringe upon the copyrights, trademarks, or other intellectual property rights of any third party.'; } ?></li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s4_p3'); } else { echo 'We reserve the right to investigate and take appropriate action against anyone who, in our sole discretion, violates these provisions, including but not limited to removing offending content, suspending or terminating accounts, and reporting violations to law enforcement authorities.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Intellectual Property                              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s5">
                    <h2 id="terms-s5" class="h4 mb-3">
                        5. <?php if (function_exists('__')) { echo __('legal.terms_s5_title'); } else { echo 'Intellectual Property'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_sub1'); } else { echo 'Our Intellectual Property'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_p1'); } else { echo 'The Service, including its original content, features, functionality, design, logos, and trademarks, is owned by <strong>MWBM Partners Ltd</strong> and is protected by international copyright, trademark, and other intellectual property laws. You may not copy, modify, distribute, sell, or lease any part of the Service without our prior written consent.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_sub2'); } else { echo 'Your Content'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_p2'); } else { echo 'You retain all ownership rights to the content you submit, post, or display through the Service. This includes your destination URLs, custom aliases, LinksPage profile content, and any other materials you provide.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_sub3'); } else { echo 'Licence Grant'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s5_p3'); } else { echo 'By submitting content to the Service, you grant <strong>MWBM Partners Ltd</strong> a worldwide, non-exclusive, royalty-free licence to use, host, store, reproduce, and display your content solely for the purpose of operating and providing the Service. This licence continues for as long as your content remains on the Service and for a reasonable period thereafter to allow for removal.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Short URL Policies                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s6">
                    <h2 id="terms-s6" class="h4 mb-3">
                        6. <?php if (function_exists('__')) { echo __('legal.terms_s6_title'); } else { echo 'Short URL Policies'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_sub1'); } else { echo 'Deactivation for Violations'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_p1'); } else { echo 'Short URLs that are found to violate these Terms or our <a href="/legal/acceptable-use">Acceptable Use Policy</a> may be deactivated, disabled, or removed at any time without prior notice. We may also deactivate short URLs in response to valid legal requests or abuse reports.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_sub2'); } else { echo 'Availability'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_p2'); } else { echo 'While we strive to maintain the availability of all short URLs, we do not guarantee the permanent availability of short URLs created on free-tier accounts. Free-tier short URLs may be subject to expiration after a period of inactivity or if the Service is discontinued. Paid plans may offer extended or permanent URL retention as described in the applicable plan details.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_sub3'); } else { echo 'Custom Aliases'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s6_p3'); } else { echo 'Custom short URL aliases (vanity URLs) are subject to availability and are provided on a first-come, first-served basis. We reserve the right to reclaim, reassign, or reject any custom alias at our sole discretion, including aliases that may cause confusion, infringe trademarks, or violate our policies.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: LinksPage Service                                  -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s7">
                    <h2 id="terms-s7" class="h4 mb-3">
                        7. <?php if (function_exists('__')) { echo __('legal.terms_s7_title'); } else { echo 'LinksPage Service'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_sub1'); } else { echo 'Profile Pages'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_p1'); } else { echo 'The LinksPage service allows you to create customisable profile landing pages hosted on <strong>lnks.page</strong>. These pages serve as a centralised hub for your links and online presence.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_sub2'); } else { echo 'Content Guidelines'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_p2'); } else { echo 'All content displayed on your LinksPage profile must comply with these Terms and our <a href="/legal/acceptable-use">Acceptable Use Policy</a>. You must not use your LinksPage profile to display misleading, harmful, offensive, or illegal content. Profile pages that impersonate other individuals, brands, or organisations are strictly prohibited.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_sub3'); } else { echo 'Right to Remove'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s7_p3'); } else { echo '<strong>MWBM Partners Ltd</strong> reserves the right to remove, disable, or modify any LinksPage profile or its content at any time and for any reason, including but not limited to violations of these Terms, abuse reports, legal requirements, or inactivity.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 8: API Usage                                          -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s8">
                    <h2 id="terms-s8" class="h4 mb-3">
                        8. <?php if (function_exists('__')) { echo __('legal.terms_s8_title'); } else { echo 'API Usage'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_sub1'); } else { echo 'Rate Limits'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_p1'); } else { echo 'Access to the API is subject to rate limits that vary by subscription plan. You must not attempt to circumvent, bypass, or exceed these rate limits. Exceeding rate limits may result in temporary or permanent suspension of your API access.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_sub2'); } else { echo 'API Key Confidentiality'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_p2'); } else { echo 'Your API keys are confidential credentials. You must not share, publish, or expose your API keys in public repositories, client-side code, or any other publicly accessible location. You are responsible for all activity that occurs using your API keys. If you believe your API key has been compromised, you must regenerate it immediately.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_sub3'); } else { echo 'Fair Use'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s8_p3'); } else { echo 'API access is provided for legitimate use cases related to URL shortening and link management. Automated or bulk usage that places an unreasonable burden on the Service, or usage that is inconsistent with the intended purpose of the API, may be restricted at our discretion.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 9: Disclaimers & Warranties                           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s9">
                    <h2 id="terms-s9" class="h4 mb-3">
                        9. <?php if (function_exists('__')) { echo __('legal.terms_s9_title'); } else { echo 'Disclaimers &amp; Warranties'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s9_p1'); } else { echo 'The Service is provided on an "as is" and "as available" basis, without warranties of any kind, either express or implied. We do not guarantee that the Service will be uninterrupted, error-free, secure, or free from viruses or other harmful components.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s9_p2'); } else { echo 'We do not guarantee any specific level of uptime or availability. While we make reasonable efforts to maintain the Service, scheduled and unscheduled maintenance, technical issues, or circumstances beyond our control may result in temporary unavailability.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                        <p class="mb-0 mt-2">
                            <?php if (function_exists('__')) { echo __('legal.terms_s9_review'); } else { echo 'This section requires professional legal review to include specific statutory disclaimer language applicable under the laws of England and Wales, including but not limited to disclaimers of implied warranties of merchantability, fitness for a particular purpose, and non-infringement.'; } ?>
                        </p>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 10: Limitation of Liability                           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s10">
                    <h2 id="terms-s10" class="h4 mb-3">
                        10. <?php if (function_exists('__')) { echo __('legal.terms_s10_title'); } else { echo 'Limitation of Liability'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s10_p1'); } else { echo 'To the maximum extent permitted by applicable law, <strong>MWBM Partners Ltd</strong>, its directors, employees, partners, agents, and affiliates shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, use, goodwill, or other intangible losses.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s10_p2'); } else { echo 'Our total aggregate liability to you for all claims arising out of or relating to the Service shall not exceed the greater of: (a) the total amount you have paid to us in the twelve (12) months preceding the claim, or (b) one hundred pounds sterling (&pound;100).'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s10_p3'); } else { echo 'Nothing in these Terms shall exclude or limit our liability for death or personal injury caused by our negligence, fraud or fraudulent misrepresentation, or any other liability that cannot be excluded or limited by the laws of England and Wales.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                        <p class="mb-0 mt-2">
                            <?php if (function_exists('__')) { echo __('legal.terms_s10_review'); } else { echo 'This section requires professional legal review to ensure the limitation of liability provisions are enforceable under the laws of England and Wales, comply with the Consumer Rights Act 2015 and the Unfair Contract Terms Act 1977, and appropriately address both consumer and business users.'; } ?>
                        </p>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 11: Indemnification                                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s11">
                    <h2 id="terms-s11" class="h4 mb-3">
                        11. <?php if (function_exists('__')) { echo __('legal.terms_s11_title'); } else { echo 'Indemnification'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s11_p1'); } else { echo 'You agree to defend, indemnify, and hold harmless <strong>MWBM Partners Ltd</strong>, its directors, employees, partners, agents, and affiliates from and against any and all claims, damages, obligations, losses, liabilities, costs, and expenses (including but not limited to legal fees) arising from:'; } ?>
                    </p>

                    <ol type="a">
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s11_li1'); } else { echo 'Your use of the Service.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s11_li2'); } else { echo 'Your violation of these Terms or any applicable law or regulation.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s11_li3'); } else { echo 'Your violation of any rights of a third party, including intellectual property rights.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.terms_s11_li4'); } else { echo 'Any content you submit, post, or transmit through the Service.'; } ?></li>
                    </ol>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                        <p class="mb-0 mt-2">
                            <?php if (function_exists('__')) { echo __('legal.terms_s11_review'); } else { echo 'This section requires professional legal review to ensure the indemnification clause is reasonable and enforceable under the laws of England and Wales, particularly with respect to consumer users where such clauses may be considered unfair terms.'; } ?>
                        </p>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 12: Governing Law                                     -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s12">
                    <h2 id="terms-s12" class="h4 mb-3">
                        12. <?php if (function_exists('__')) { echo __('legal.terms_s12_title'); } else { echo 'Governing Law'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s12_p1'); } else { echo 'These Terms shall be governed by and construed in accordance with the laws of England and Wales, without regard to its conflict of law provisions.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s12_p2'); } else { echo 'Any disputes arising out of or in connection with these Terms, including any question regarding their existence, validity, or termination, shall be subject to the exclusive jurisdiction of the courts of England and Wales.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s12_p3'); } else { echo 'If you are a consumer, you may also be entitled to bring proceedings in the courts of the country in which you reside, and nothing in these Terms affects your statutory rights as a consumer.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 13: Changes to Terms                                  -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s13">
                    <h2 id="terms-s13" class="h4 mb-3">
                        13. <?php if (function_exists('__')) { echo __('legal.terms_s13_title'); } else { echo 'Changes to Terms'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s13_p1'); } else { echo 'We reserve the right to update or modify these Terms at any time. When we make changes, we will update the "Last Updated" date at the top of this page and increment the version number.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s13_p2'); } else { echo 'Your continued use of the Service after any changes to these Terms constitutes your acceptance of the revised Terms. If you do not agree with the updated Terms, you must stop using the Service.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s13_p3'); } else { echo 'For material changes that significantly affect your rights or obligations, we will make reasonable efforts to notify you in advance by sending an email to the address associated with your account or by displaying a prominent notice within the Service.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 14: Contact                                           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="terms-s14">
                    <h2 id="terms-s14" class="h4 mb-3">
                        14. <?php if (function_exists('__')) { echo __('legal.terms_s14_title'); } else { echo 'Contact'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.terms_s14_p1'); } else { echo 'If you have any questions, concerns, or requests regarding these Terms of Use, please contact us:'; } ?>
                    </p>

                    <ul class="list-unstyled ms-3">
                        <li class="mb-2">
                            <i class="fas fa-envelope text-body-secondary me-2" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.email_label'); } else { echo 'Email'; } ?>:</strong>
                            <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-building text-body-secondary me-2" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.company_label'); } else { echo 'Company'; } ?>:</strong>
                            <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-tag text-body-secondary me-2" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.trading_as_label'); } else { echo 'Trading As'; } ?>:</strong>
                            <?php echo htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-globe text-body-secondary me-2" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.website_label'); } else { echo 'Website'; } ?>:</strong>
                            <a href="https://go2my.link">go2my.link</a>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Back to Legal Hub / Contact CTA                               -->
                <!-- ============================================================ -->
                <div class="mt-5 pt-4 border-top text-center">
                    <a href="/legal" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.back_to_legal'); } else { echo 'Back to Legal'; } ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.contact_cta'); } else { echo 'Questions? Contact Us'; } ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
