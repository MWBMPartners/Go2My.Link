<?php
/**
 * ============================================================================
 * Go2My.Link — Acceptable Use Policy Page (Component A)
 * ============================================================================
 *
 * Structured Acceptable Use Policy defining prohibited activities.
 * Contains {{LEGAL_REVIEW_NEEDED}} placeholders for professional review.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('legal.aup_title');
} else {
    $pageTitle = 'Acceptable Use Policy';
}
if (function_exists('__')) {
    $pageDesc = __('legal.aup_description');
} else {
    $pageDesc = 'Go2My.Link Acceptable Use Policy — rules governing use of our services.';
}

if (function_exists('getSetting')) {
    $legalVersion = getSetting('legal.aup_version', '1.0');
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
if (function_exists('getSetting')) {
    $contactEmail = getSetting('site.contact_email', 'hello@go2my.link');
} else {
    $contactEmail = 'hello@go2my.link';
}
$hideReviewPlaceholders = function_exists('getSetting') && getSetting('legal.hide_review_placeholders', '0') === '1';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="aup-heading">
    <div class="container">
        <h1 id="aup-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('legal.aup_heading'); } else { echo 'Acceptable Use Policy'; } ?>
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
<!-- Acceptable Use Policy Content                                           -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="aup-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="aup-content-heading" class="visually-hidden">Acceptable Use Policy Content</h2>

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
                                <li><a href="#aup-s1"><?php if (function_exists('__')) { echo __('legal.aup_s1_title'); } else { echo 'Purpose'; } ?></a></li>
                                <li><a href="#aup-s2"><?php if (function_exists('__')) { echo __('legal.aup_s2_title'); } else { echo 'Prohibited Content'; } ?></a></li>
                                <li><a href="#aup-s3"><?php if (function_exists('__')) { echo __('legal.aup_s3_title'); } else { echo 'Prohibited Activities'; } ?></a></li>
                                <li><a href="#aup-s4"><?php if (function_exists('__')) { echo __('legal.aup_s4_title'); } else { echo 'URL &amp; Link Policies'; } ?></a></li>
                                <li><a href="#aup-s5"><?php if (function_exists('__')) { echo __('legal.aup_s5_title'); } else { echo 'Reporting Violations'; } ?></a></li>
                                <li><a href="#aup-s6"><?php if (function_exists('__')) { echo __('legal.aup_s6_title'); } else { echo 'Enforcement'; } ?></a></li>
                                <li><a href="#aup-s7"><?php if (function_exists('__')) { echo __('legal.aup_s7_title'); } else { echo 'API Usage Limits'; } ?></a></li>
                                <li><a href="#aup-s8"><?php if (function_exists('__')) { echo __('legal.aup_s8_title'); } else { echo 'Changes to This Policy'; } ?></a></li>
                                <li><a href="#aup-s9"><?php if (function_exists('__')) { echo __('legal.aup_s9_title'); } else { echo 'Contact'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: Purpose                                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s1">
                    <h2 id="aup-s1" class="h4 mb-3">
                        1. <?php if (function_exists('__')) { echo __('legal.aup_s1_title'); } else { echo 'Purpose'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s1_p1'); } else { echo 'This Acceptable Use Policy ("AUP") governs your use of all services provided by <strong>MWBM Partners Ltd</strong>, trading as <strong>MWservices</strong>, through the <strong>go2my.link</strong>, <strong>g2my.link</strong>, and <strong>lnks.page</strong> domains (collectively, the "Service"). This AUP applies to all users, including those using the Service without an account.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s1_p2'); } else { echo 'This policy supplements our <a href="/legal/terms">Terms of Use</a> and is incorporated by reference into those Terms. In the event of any conflict between this AUP and the Terms of Use, the Terms of Use shall prevail unless this AUP explicitly states otherwise.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s1_p3'); } else { echo 'By using the Service, you agree to comply with this AUP. Failure to comply may result in the suspension or termination of your access to the Service.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: Prohibited Content                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s2">
                    <h2 id="aup-s2" class="h4 mb-3">
                        2. <?php if (function_exists('__')) { echo __('legal.aup_s2_title'); } else { echo 'Prohibited Content'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s2_p1'); } else { echo 'All URLs shortened through the Service, LinksPage profile content, custom aliases, and any other content submitted to the Service must not link to, host, promote, or contain any of the following:'; } ?>
                    </p>

                    <div class="alert alert-danger" role="alert">
                        <h3 class="h6 alert-heading mb-3">
                            <i class="fas fa-ban me-2" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('legal.aup_s2_alert_heading'); } else { echo 'The following content is strictly prohibited:'; } ?>
                        </h3>
                        <ul class="mb-0">
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li1'); } else { echo '<strong>Malware and malicious software</strong> &mdash; Viruses, ransomware, trojans, spyware, adware, cryptominers, or any other software designed to damage, disrupt, or gain unauthorised access to computer systems.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li2'); } else { echo '<strong>Phishing and credential harvesting</strong> &mdash; Pages designed to deceive users into revealing personal information, login credentials, financial details, or other sensitive data.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li3'); } else { echo '<strong>Child sexual abuse material (CSAM)</strong> &mdash; Any content that sexually exploits or depicts minors. We report all instances to the relevant authorities, including the National Crime Agency (NCA) and the Internet Watch Foundation (IWF).'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li4'); } else { echo '<strong>Terrorism and violent extremism</strong> &mdash; Content that promotes, supports, incites, or glorifies terrorism, terrorist organisations, or acts of violent extremism.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li5'); } else { echo '<strong>Illegal content</strong> &mdash; Content that is illegal under the laws of England and Wales, or under the laws of the jurisdiction in which the user is located.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li6'); } else { echo '<strong>Copyright-infringing material</strong> &mdash; Content that infringes upon the copyrights, trademarks, or other intellectual property rights of any third party. See our <a href="/legal/copyright">Copyright Policy</a> for more information.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li7'); } else { echo '<strong>Defamatory or harassing content</strong> &mdash; Content intended to defame, bully, harass, threaten, stalk, or intimidate any individual or group.'; } ?>
                            </li>
                            <li>
                                <?php if (function_exists('__')) { echo __('legal.aup_s2_li8'); } else { echo '<strong>Self-harm and suicide</strong> &mdash; Content that promotes, encourages, or provides instructions for self-harm or suicide.'; } ?>
                            </li>
                        </ul>
                    </div>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s2_p2'); } else { echo 'This list is not exhaustive. We reserve the right to determine, at our sole discretion, whether any content violates this policy.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Prohibited Activities                              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s3">
                    <h2 id="aup-s3" class="h4 mb-3">
                        3. <?php if (function_exists('__')) { echo __('legal.aup_s3_title'); } else { echo 'Prohibited Activities'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s3_p1'); } else { echo 'When using the Service, you must not engage in any of the following activities:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li1'); } else { echo '<strong>Spam and unsolicited messages</strong> &mdash; Using the Service to send, facilitate, or distribute spam, unsolicited bulk messages, or unsolicited commercial communications.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li2'); } else { echo '<strong>Bulk URL creation for spam</strong> &mdash; Creating short URLs in bulk for the purpose of spam distribution, link manipulation, or artificially inflating click counts.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li3'); } else { echo '<strong>Bypassing rate limits</strong> &mdash; Attempting to circumvent, bypass, or exceed rate limits, usage quotas, or other technical restrictions imposed by the Service.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li4'); } else { echo '<strong>Scraping and data mining</strong> &mdash; Scraping, crawling, harvesting, or data-mining the Service or its content without our prior written permission.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li5'); } else { echo '<strong>Service disruption</strong> &mdash; Interfering with, disrupting, or attempting to gain unauthorised access to the Service infrastructure, servers, networks, or connected systems.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li6'); } else { echo '<strong>Impersonation</strong> &mdash; Impersonating any person, company, or organisation, or falsely stating or misrepresenting your affiliation with any person or entity.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li7'); } else { echo '<strong>Illegal purposes</strong> &mdash; Using the Service for any purpose that is unlawful under applicable law, including but not limited to fraud, money laundering, or the facilitation of criminal activity.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li8'); } else { echo '<strong>Deceptive redirect chains</strong> &mdash; Creating deceptive or misleading redirect chains that obscure the true destination or purpose of a link.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.aup_s3_li9'); } else { echo '<strong>Deceptive URL shortening</strong> &mdash; Using URL shortening to deliberately obscure the true destination of a link in a manner intended to deceive, mislead, or defraud users.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: URL & Link Policies                                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s4">
                    <h2 id="aup-s4" class="h4 mb-3">
                        4. <?php if (function_exists('__')) { echo __('legal.aup_s4_title'); } else { echo 'URL &amp; Link Policies'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_sub1'); } else { echo 'URL Deactivation'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_p1'); } else { echo 'Short URLs that are found to violate this Acceptable Use Policy may be disabled, deactivated, or removed without prior notice. We are under no obligation to notify the URL creator before or after taking such action.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_sub2'); } else { echo 'Custom Aliases'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_p2'); } else { echo 'Custom short URL aliases (vanity URLs) must not impersonate or be confusingly similar to the names, brands, trademarks, or identities of other individuals, companies, or organisations. We reserve the right to reclaim or reject any custom alias at our sole discretion.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_sub3'); } else { echo 'Adult Content'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_p3'); } else { echo 'URLs linking to legal adult content must be flagged appropriately using the content classification tools provided by the Service. Failure to flag adult content may result in URL deactivation and account sanctions.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_sub4'); } else { echo 'Redirect Chains'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s4_p4'); } else { echo 'Redirect chains consisting of more than three (3) hops may be blocked or flagged for review. This includes chains where multiple URL shortening services are used in sequence to obscure the final destination.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Reporting Violations                               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s5">
                    <h2 id="aup-s5" class="h4 mb-3">
                        5. <?php if (function_exists('__')) { echo __('legal.aup_s5_title'); } else { echo 'Reporting Violations'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_p1'); } else { echo 'If you believe that a short URL, LinksPage profile, or any other content on the Service violates this Acceptable Use Policy, we encourage you to report it to us promptly.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_sub1'); } else { echo 'How to Report'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_p2'); } else { echo 'Please send violation reports to:'; } ?>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_sub2'); } else { echo 'What to Include'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_p3'); } else { echo 'To help us investigate your report efficiently, please include the following information:'; } ?>
                    </p>
                    <ul>
                        <li><?php if (function_exists('__')) { echo __('legal.aup_s5_li1'); } else { echo 'The short URL or LinksPage URL in question.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.aup_s5_li2'); } else { echo 'A description of the violation and which section of this policy it breaches.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.aup_s5_li3'); } else { echo 'Any supporting evidence, such as screenshots or additional URLs.'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.aup_s5_li4'); } else { echo 'Your contact information so we can follow up if needed.'; } ?></li>
                    </ul>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_sub3'); } else { echo 'Investigation Timeline'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s5_p4'); } else { echo 'We aim to acknowledge all violation reports within 24 hours and to complete our investigation within 48 hours of receipt. In cases involving illegal content or imminent harm, we will take immediate action where possible.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Enforcement                                        -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s6">
                    <h2 id="aup-s6" class="h4 mb-3">
                        6. <?php if (function_exists('__')) { echo __('legal.aup_s6_title'); } else { echo 'Enforcement'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s6_p1'); } else { echo 'Violations of this Acceptable Use Policy will be addressed in proportion to their severity. The following table outlines our general enforcement approach:'; } ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.aup_s6_th_severity'); } else { echo 'Severity'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.aup_s6_th_action'); } else { echo 'Action'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.aup_s6_th_example'); } else { echo 'Example'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row1_severity'); } else { echo 'Minor (first offence)'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row1_action'); } else { echo 'Written warning and request to remediate'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row1_example'); } else { echo 'Unflagged adult content, mildly misleading alias'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row2_severity'); } else { echo 'Moderate'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row2_action'); } else { echo 'URL deactivation and/or temporary account suspension'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row2_example'); } else { echo 'Spam distribution, repeated minor violations, rate limit abuse'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row3_severity'); } else { echo 'Severe'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row3_action'); } else { echo 'Immediate URL removal and account suspension'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row3_example'); } else { echo 'Phishing, malware distribution, impersonation, deceptive redirects'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row4_severity'); } else { echo 'Critical'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row4_action'); } else { echo 'Permanent ban and referral to law enforcement'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.aup_s6_row4_example'); } else { echo 'CSAM, terrorism content, illegal activity facilitation'; } ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s6_p2'); } else { echo 'We may report any content we believe to be illegal to the appropriate law enforcement authorities, including but not limited to the National Crime Agency (NCA), the Internet Watch Foundation (IWF), and Action Fraud. We will cooperate fully with law enforcement investigations.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                        <p class="mb-0 mt-2">
                            <?php if (function_exists('__')) { echo __('legal.aup_s6_review'); } else { echo 'This section requires professional legal review to ensure enforcement discretion language is appropriately drafted. In particular, legal counsel should review: (a) the extent of discretion reserved by the Company in determining violation severity, (b) whether the enforcement tiers create any binding obligations or implied commitments, and (c) compliance with the Online Safety Act 2023 reporting requirements.'; } ?>
                        </p>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: API Usage Limits                                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s7">
                    <h2 id="aup-s7" class="h4 mb-3">
                        7. <?php if (function_exists('__')) { echo __('legal.aup_s7_title'); } else { echo 'API Usage Limits'; } ?>
                    </h2>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_sub1'); } else { echo 'Fair Use'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_p1'); } else { echo 'API access is provided on a fair-use basis. You must use the API in a manner that is consistent with its intended purpose of URL shortening, link management, and analytics. Usage that places an unreasonable or disproportionate burden on our infrastructure may be restricted.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_sub2'); } else { echo 'Rate Limits'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_p2'); } else { echo 'Rate limits are enforced on all API endpoints. The specific limits applicable to your account are determined by your subscription plan. Exceeding these limits may result in temporary blocking of your API requests. Persistent or intentional abuse of rate limits may result in revocation of your API key.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_sub3'); } else { echo 'Automated URL Creation'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_p3'); } else { echo 'Automated creation of URLs via the API must comply with all provisions of this Acceptable Use Policy and our <a href="/legal/terms">Terms of Use</a>. You are responsible for ensuring that all URLs created programmatically through your API key comply with our policies, regardless of whether those URLs were created directly by you or by an automated system operating on your behalf.'; } ?>
                    </p>

                    <h3 class="h5 mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_sub4'); } else { echo 'Excessive Use'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s7_p4'); } else { echo 'If your API usage is deemed excessive or places an undue strain on the Service, we may contact you to discuss your usage patterns. If the issue is not resolved, we reserve the right to temporarily or permanently revoke your API access.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 8: Changes to This Policy                             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s8">
                    <h2 id="aup-s8" class="h4 mb-3">
                        8. <?php if (function_exists('__')) { echo __('legal.aup_s8_title'); } else { echo 'Changes to This Policy'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s8_p1'); } else { echo 'We reserve the right to update or modify this Acceptable Use Policy at any time. When we make changes, we will update the "Last Updated" date at the top of this page and increment the version number.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s8_p2'); } else { echo 'Your continued use of the Service after any changes to this AUP constitutes your acceptance of the revised policy. If you do not agree with the updated AUP, you must stop using the Service.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s8_p3'); } else { echo 'For material changes that significantly affect what is considered acceptable use, we will make reasonable efforts to notify you in advance by sending an email to the address associated with your account or by displaying a prominent notice within the Service.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 9: Contact                                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="aup-s9">
                    <h2 id="aup-s9" class="h4 mb-3">
                        9. <?php if (function_exists('__')) { echo __('legal.aup_s9_title'); } else { echo 'Contact'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s9_p1'); } else { echo 'If you have any questions about this Acceptable Use Policy or wish to report a violation, please contact us:'; } ?>
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

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.aup_s9_p2'); } else { echo 'This Acceptable Use Policy should be read in conjunction with our <a href="/legal/terms">Terms of Use</a>, <a href="/legal/privacy">Privacy Policy</a>, and <a href="/legal/cookies">Cookie Policy</a>.'; } ?>
                    </p>
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
