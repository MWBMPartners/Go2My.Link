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
 * Go2My.Link — Copyright Notice Page (Component A)
 * ============================================================================
 *
 * Structured Copyright Notice with DMCA/takedown procedures.
 * Contains {{LEGAL_REVIEW_NEEDED}} placeholders for professional review.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('legal.copyright_title');
} else {
    $pageTitle = 'Copyright Notice';
}
if (function_exists('__')) {
    $pageDesc = __('legal.copyright_description');
} else {
    $pageDesc = 'Go2My.Link Copyright Notice and DMCA takedown procedures.';
}

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
<section class="page-header text-center" aria-labelledby="copyright-heading">
    <div class="container">
        <h1 id="copyright-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('legal.copyright_heading'); } else { echo 'Copyright Notice'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <span class="badge bg-secondary">
                <?php if (function_exists('__')) { echo __('legal.version_label'); } else { echo 'Version'; } ?>
                <?php echo htmlspecialchars($legalVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="ms-2">
                <?php if (function_exists('__')) { echo __('legal.last_updated_label'); } else { echo 'Last updated:'; } ?>
                <time datetime="<?php echo htmlspecialchars($legalUpdated, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($legalUpdated, ENT_QUOTES, 'UTF-8'); ?>
                </time>
            </span>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Copyright Content                                                       -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="copyright-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="copyright-content-heading" class="visually-hidden">Copyright Notice Content</h2>

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
                                <li>
                                    <a href="#copyright-s1">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s1_title'); } else { echo 'Copyright Ownership'; } ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#copyright-s2">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s2_title'); } else { echo 'User-Generated Content'; } ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#copyright-s3">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_title'); } else { echo 'DMCA &amp; Copyright Takedown'; } ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#copyright-s4">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s4_title'); } else { echo 'Repeat Infringer Policy'; } ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#copyright-s5">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s5_title'); } else { echo 'Third-Party Content'; } ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#copyright-s6">
                                        <?php if (function_exists('__')) { echo __('legal.copyright_s6_title'); } else { echo 'Contact'; } ?>
                                    </a>
                                </li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: Copyright Ownership                                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s1">
                    <h2 id="copyright-s1" class="h3 mb-3">
                        1. <?php if (function_exists('__')) { echo __('legal.copyright_s1_title'); } else { echo 'Copyright Ownership'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s1_p1'); } else { echo 'All content, source code, software, graphics, logos, branding materials, page designs, and documentation comprising the GoToMyLink service are the copyright of <strong>MWBM Partners Ltd</strong> (trading as <strong>MWservices</strong>) unless otherwise stated.'; } ?>
                    </p>

                    <p>
                        &copy; 2024&ndash;<?php echo date('Y'); ?>
                        <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>
                        (trading as <?php echo htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8'); ?>).
                        <?php if (function_exists('__')) { echo __('legal.copyright_s1_rights'); } else { echo 'All rights reserved.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s1_trademarks'); } else { echo 'The following names and associated logos are trademarks or service marks of MWBM Partners Ltd:'; } ?>
                    </p>

                    <ul>
                        <li><strong>GoToMyLink</strong></li>
                        <li><strong>Go2My.Link</strong></li>
                        <li><strong>G2My.Link</strong></li>
                        <li><strong>Lnks.page</strong></li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s1_p3'); } else { echo 'These marks may not be used in connection with any product or service that is not provided by MWBM Partners Ltd, in any manner that is likely to cause confusion, or in any manner that disparages or discredits MWBM Partners Ltd.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: User-Generated Content                             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s2">
                    <h2 id="copyright-s2" class="h3 mb-3">
                        2. <?php if (function_exists('__')) { echo __('legal.copyright_s2_title'); } else { echo 'User-Generated Content'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s2_p1'); } else { echo 'Users retain full copyright ownership of their own content, including but not limited to:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s2_item1'); } else { echo 'Destination URLs submitted for shortening'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s2_item2'); } else { echo 'LinksPage profile content, descriptions, and custom imagery'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s2_item3'); } else { echo 'Custom metadata such as link titles, descriptions, and tags'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s2_p2'); } else { echo 'By using the GoToMyLink service, you grant MWBM Partners Ltd a non-exclusive, worldwide, royalty-free licence to display, reproduce, cache, and distribute your content solely as necessary to operate, maintain, and provide the service. This licence continues for as long as your content remains on the service and terminates when your content is deleted.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s2_p3'); } else { echo 'You represent and warrant that you own or have the necessary rights, licences, and permissions to submit content to the service. You must not upload, share, or link to any content that infringes the copyright, trademark, or other intellectual property rights of any third party.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: DMCA & Copyright Takedown                          -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s3">
                    <h2 id="copyright-s3" class="h3 mb-3">
                        3. <?php if (function_exists('__')) { echo __('legal.copyright_s3_title'); } else { echo 'DMCA &amp; Copyright Takedown'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_p1'); } else { echo 'MWBM Partners Ltd respects the intellectual property rights of others and expects users of the GoToMyLink service to do the same. If you believe that content available through our service infringes your copyright, you may submit a takedown notice to our designated agent.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> &mdash;
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_review_note'); } else { echo 'Formal DMCA agent designation and registration details require professional legal review and must be filed with the U.S. Copyright Office if applicable.'; } ?>
                    </div>
                    <?php } ?>

                    <h3 class="h5 mt-4 mb-3">
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_notice_heading'); } else { echo 'Filing a Takedown Notice'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_notice_p1'); } else { echo 'To file a copyright takedown notice, please send a written communication to our designated agent that includes the following information:'; } ?>
                    </p>

                    <ol>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req1'); } else { echo 'A description of the copyrighted work that you claim has been infringed, or, if multiple works are covered by a single notification, a representative list of such works.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req2'); } else { echo 'The specific URL(s) or short link(s) on the GoToMyLink service that you claim are infringing or are the subject of infringing activity, with enough detail for us to locate the material.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req3'); } else { echo 'Your full name, postal address, telephone number, and email address so that we may contact you.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req4'); } else { echo 'A statement that you have a good faith belief that the use of the material in the manner complained of is not authorised by the copyright owner, its agent, or the law.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req5'); } else { echo 'A statement that the information in the notification is accurate, and under penalty of perjury, that you are authorised to act on behalf of the owner of the copyright that is allegedly infringed.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_req6'); } else { echo 'A physical or electronic signature of the copyright owner or a person authorised to act on their behalf.'; } ?>
                        </li>
                    </ol>

                    <h3 class="h5 mt-4 mb-3">
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_agent_heading'); } else { echo 'Designated Agent'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_agent_p1'); } else { echo 'Takedown notices should be sent to our designated copyright agent at:'; } ?>
                    </p>

                    <address class="ms-3">
                        <strong><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_agent_attn'); } else { echo 'Attn: Copyright Agent'; } ?><br>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_agent_email_label'); } else { echo 'Email:'; } ?>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </address>

                    <h3 class="h5 mt-4 mb-3">
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_heading'); } else { echo 'Counter-Notification'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_p1'); } else { echo 'If you believe that your content was removed or disabled as a result of a mistake or misidentification, you may submit a counter-notification to our designated agent. Your counter-notification must include:'; } ?>
                    </p>

                    <ol>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_req1'); } else { echo 'Your full name, postal address, telephone number, and email address.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_req2'); } else { echo 'Identification of the material that was removed or disabled, and the URL where it previously appeared.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_req3'); } else { echo 'A statement under penalty of perjury that you have a good faith belief the material was removed or disabled as a result of mistake or misidentification.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_req4'); } else { echo 'A statement that you consent to the jurisdiction of the courts in your locality and that you will accept service of process from the person who filed the original takedown notice.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_req5'); } else { echo 'Your physical or electronic signature.'; } ?>
                        </li>
                    </ol>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s3_counter_p2'); } else { echo 'Upon receipt of a valid counter-notification, we will forward it to the original complainant and restore the removed content within 10 to 14 business days, unless we receive notice that the complainant has filed a court action seeking to restrain you from engaging in the allegedly infringing activity.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Repeat Infringer Policy                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s4">
                    <h2 id="copyright-s4" class="h3 mb-3">
                        4. <?php if (function_exists('__')) { echo __('legal.copyright_s4_title'); } else { echo 'Repeat Infringer Policy'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s4_p1'); } else { echo 'MWBM Partners Ltd maintains a policy for the termination of accounts belonging to users who are repeat copyright infringers. If a user is found to have repeatedly uploaded, linked to, or facilitated access to infringing content, their account and all associated short links may be permanently terminated at our sole discretion.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s4_p2'); } else { echo 'We reserve the right to disable any short link, LinksPage profile, or user account at any time if we reasonably believe that the content infringes the intellectual property rights of others, regardless of whether a formal takedown notice has been received.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> &mdash;
                        <?php if (function_exists('__')) { echo __('legal.copyright_s4_review_note'); } else { echo 'Repeat infringer policy thresholds, appeals processes, and specific procedural details require professional legal review to ensure compliance with applicable safe harbour provisions.'; } ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Third-Party Content                                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s5">
                    <h2 id="copyright-s5" class="h3 mb-3">
                        5. <?php if (function_exists('__')) { echo __('legal.copyright_s5_title'); } else { echo 'Third-Party Content'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s5_p1'); } else { echo 'Links shortened through the GoToMyLink service redirect users to third-party websites and content. MWBM Partners Ltd does not own, control, or endorse any third-party content accessible through short links created on the service.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s5_p2'); } else { echo 'The presence of a short link on Go2My.Link, G2My.Link, or Lnks.page does not imply any affiliation with, endorsement of, or responsibility for the linked content or the practices of the third-party website operators.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s5_p3'); } else { echo 'The GoToMyLink service makes use of the following third-party software and assets, each used under their respective licences:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong>Bootstrap</strong> &mdash;
                            <?php if (function_exists('__')) { echo __('legal.copyright_s5_bootstrap'); } else { echo 'Licensed under the MIT Licence. Copyright &copy; Bootstrap Authors.'; } ?>
                        </li>
                        <li>
                            <strong>Font Awesome</strong> &mdash;
                            <?php if (function_exists('__')) { echo __('legal.copyright_s5_fontawesome'); } else { echo 'Icons licensed under CC BY 4.0, fonts under SIL OFL 1.1, and code under the MIT Licence. Copyright &copy; Fonticons, Inc.'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s5_p4'); } else { echo 'All third-party trademarks, logos, and brand names referenced on this site are the property of their respective owners and are used for identification purposes only.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Contact                                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="copyright-s6">
                    <h2 id="copyright-s6" class="h3 mb-3">
                        6. <?php if (function_exists('__')) { echo __('legal.copyright_s6_title'); } else { echo 'Contact'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s6_p1'); } else { echo 'If you have any questions about this Copyright Notice, or if you wish to report a copyright concern, please contact us:'; } ?>
                    </p>

                    <address>
                        <strong><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></strong>
                        (trading as <?php echo htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8'); ?>)<br>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s6_email_label'); } else { echo 'Email:'; } ?>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                        </a><br>
                        <?php if (function_exists('__')) { echo __('legal.copyright_s6_web_label'); } else { echo 'Website:'; } ?>
                        <a href="https://go2my.link">
                            <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </address>

                    <div class="mt-4 text-center">
                        <a href="/contact" class="btn btn-outline-primary">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('legal.contact_cta'); } else { echo 'Questions? Contact Us'; } ?>
                        </a>
                    </div>
                </section>

            </div>
        </div>
    </div>
</section>
