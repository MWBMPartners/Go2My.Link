<?php
/**
 * ============================================================================
 * Go2My.Link — Cookie Policy Page (Component A)
 * ============================================================================
 *
 * Structured Cookie Policy template with cookie inventory table.
 * Contains {{LEGAL_REVIEW_NEEDED}} placeholders for professional review.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 3 (structured template Phase 6)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('legal.cookies_title');
} else {
    $pageTitle = 'Cookie Policy';
}
if (function_exists('__')) {
    $pageDesc = __('legal.cookies_description');
} else {
    $pageDesc = 'Go2My.Link Cookie Policy — how we use cookies and similar technologies.';
}

if (function_exists('getSetting')) {
    $legalVersion = getSetting('legal.cookies_version', '1.0');
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
<section class="page-header text-center" aria-labelledby="cookies-heading">
    <div class="container">
        <h1 id="cookies-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('legal.cookies_heading'); } else { echo 'Cookie Policy'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('legal.cookies_subtitle'); } else { echo 'How we use cookies and similar technologies.'; } ?>
        </p>
        <p class="text-body-secondary mb-0">
            <span class="badge bg-secondary">
                <?php if (function_exists('__')) { echo __('legal.version'); } else { echo 'Version'; } ?>
                <?php echo htmlspecialchars($legalVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="ms-2">
                <?php if (function_exists('__')) { echo __('legal.last_updated'); } else { echo 'Last updated:'; } ?>
                <time datetime="<?php echo htmlspecialchars($legalUpdated, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(date('j F Y', strtotime($legalUpdated)), ENT_QUOTES, 'UTF-8'); ?>
                </time>
            </span>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Cookie Policy Content                                                   -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="cookies-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="cookies-content-heading" class="visually-hidden">Cookie Policy Content</h2>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('legal.toc_heading'); } else { echo 'Table of Contents'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#cookies-s1"><?php if (function_exists('__')) { echo __('legal.cookies_s1_title'); } else { echo 'What Are Cookies'; } ?></a></li>
                                <li><a href="#cookies-s2"><?php if (function_exists('__')) { echo __('legal.cookies_s2_title'); } else { echo 'How We Use Cookies'; } ?></a></li>
                                <li><a href="#cookies-s3"><?php if (function_exists('__')) { echo __('legal.cookies_s3_title'); } else { echo 'Cookie Categories &amp; Inventory'; } ?></a></li>
                                <li><a href="#cookies-s4"><?php if (function_exists('__')) { echo __('legal.cookies_s4_title'); } else { echo 'Managing Your Cookie Preferences'; } ?></a></li>
                                <li><a href="#cookies-s5"><?php if (function_exists('__')) { echo __('legal.cookies_s5_title'); } else { echo 'Do Not Track &amp; Global Privacy Control'; } ?></a></li>
                                <li><a href="#cookies-s6"><?php if (function_exists('__')) { echo __('legal.cookies_s6_title'); } else { echo 'Changes to This Policy'; } ?></a></li>
                                <li><a href="#cookies-s7"><?php if (function_exists('__')) { echo __('legal.cookies_s7_title'); } else { echo 'Contact'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: What Are Cookies                                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s1">
                    <h2 id="cookies-s1" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s1_heading'); } else { echo '1. What Are Cookies'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s1_p1'); } else { echo 'Cookies are small text files that are placed on your device (computer, tablet, or mobile phone) when you visit a website. They are widely used to make websites work more efficiently, provide a better user experience, and supply information to website operators.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s1_p2'); } else { echo 'In addition to cookies, we also use <strong>localStorage</strong>, a similar browser-based storage mechanism that allows websites to store data locally on your device. Unlike cookies, localStorage data is not sent to the server with every request, but it serves a similar purpose for preserving your preferences.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s1_p3'); } else { echo 'Throughout this policy, when we refer to "cookies", we include both traditional HTTP cookies and localStorage unless otherwise stated.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s1_first_vs_third_heading'); } else { echo 'First-Party vs Third-Party Cookies'; } ?>
                    </h3>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_first_party'); } else { echo 'First-party cookies'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_first_party_desc'); } else { echo 'Set by the website you are visiting (in this case, go2my.link, g2my.link, or lnks.page). All cookies we currently use are first-party cookies.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_third_party'); } else { echo 'Third-party cookies'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_third_party_desc'); } else { echo 'Set by a domain other than the one you are visiting. We do not currently use any third-party cookies.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: How We Use Cookies                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s2">
                    <h2 id="cookies-s2" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s2_heading'); } else { echo '2. How We Use Cookies'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s2_intro'); } else { echo 'We use cookies and similar technologies on our service for the following purposes:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_use_session'); } else { echo 'Session Management'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_use_session_desc'); } else { echo 'To maintain your session while you use the service, keeping you logged in and preserving your state as you navigate between pages.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_use_preferences'); } else { echo 'Preferences'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_use_preferences_desc'); } else { echo 'To remember your settings and preferences, such as your chosen theme (light or dark mode) and language preference, so you do not need to set them each time you visit.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_use_security'); } else { echo 'Security'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_use_security_desc'); } else { echo 'To protect you against cross-site request forgery (CSRF) attacks and other security threats by generating and validating security tokens.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_use_consent'); } else { echo 'Cookie Consent'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_use_consent_desc'); } else { echo 'To remember whether you have accepted or declined non-essential cookies, so we do not ask you repeatedly.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('legal.cookies_use_analytics'); } else { echo 'Analytics (Future)'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('legal.cookies_use_analytics_desc'); } else { echo 'In the future, we may use analytics cookies to understand how visitors interact with our service, helping us improve the user experience. These will only be set with your consent.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Cookie Categories & Inventory                      -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s3">
                    <h2 id="cookies-s3" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3_heading'); } else { echo '3. Cookie Categories &amp; Inventory'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3_intro'); } else { echo 'Below is a complete inventory of the cookies and similar technologies used by our service, organised by category.'; } ?>
                    </p>

                    <!-- ======================================================== -->
                    <!-- 3a. Essential Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3a">
                        <i class="fas fa-lock text-success" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3a_heading'); } else { echo 'Essential Cookies'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3a_desc'); } else { echo 'These cookies are strictly necessary for the operation of our service. They cannot be disabled as the service would not function correctly without them. They do not store any personally identifiable information.'; } ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_name'); } else { echo 'Name'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_purpose'); } else { echo 'Purpose'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_duration'); } else { echo 'Duration'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_type'); } else { echo 'Type'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>G2ML_SESSION</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_session_purpose'); } else { echo 'Maintains your server-side session, keeping you logged in and preserving your state across page requests.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_session_duration'); } else { echo 'Session (deleted when you close your browser)'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_http'); } else { echo 'HTTP Cookie'; } ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_consent</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_consent_purpose'); } else { echo 'Records your cookie consent preferences so we respect your choices on subsequent visits.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_consent_duration'); } else { echo '1 year'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_http'); } else { echo 'HTTP Cookie'; } ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_csrf</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_csrf_purpose'); } else { echo 'Provides cross-site request forgery (CSRF) protection by validating that form submissions originate from our service.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_csrf_duration'); } else { echo 'Session (deleted when you close your browser)'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_http'); } else { echo 'HTTP Cookie'; } ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3b. Functional Cookies                                    -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3b">
                        <i class="fas fa-sliders-h text-primary" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3b_heading'); } else { echo 'Functional Cookies'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3b_desc'); } else { echo 'These cookies enable enhanced functionality and personalisation. They may be set by us or by third-party providers whose services we have added to our pages. If you disable these cookies, some or all of these features may not function properly.'; } ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_name'); } else { echo 'Name'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_purpose'); } else { echo 'Purpose'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_duration'); } else { echo 'Duration'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('legal.cookies_col_type'); } else { echo 'Type'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>g2ml_theme</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_theme_purpose'); } else { echo 'Stores your display theme preference (light, dark, or auto/system) so the correct theme is applied on each visit, including on the initial page load before JavaScript runs.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_theme_duration'); } else { echo '1 year'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_http'); } else { echo 'HTTP Cookie'; } ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_locale</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_locale_purpose'); } else { echo 'Stores your preferred language so content is displayed in your chosen language on subsequent visits.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_locale_duration'); } else { echo '1 year'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_http'); } else { echo 'HTTP Cookie'; } ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml-theme</code></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_ls_theme_purpose'); } else { echo 'A client-side mirror of your theme preference, used by JavaScript to apply the theme instantly without waiting for a server response, preventing a flash of unstyled content (FOUC).'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_ls_theme_duration'); } else { echo 'Persistent (until cleared)'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('legal.cookies_type_localstorage'); } else { echo 'localStorage'; } ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3c. Analytics Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3c">
                        <i class="fas fa-chart-bar text-info" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3c_heading'); } else { echo 'Analytics Cookies'; } ?>
                    </h3>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3c_none'); } else { echo 'We do not currently use any analytics cookies. If we introduce analytics cookies in the future, this section will be updated and your consent will be requested before any such cookies are set.'; } ?>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3d. Marketing Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3d">
                        <i class="fas fa-bullhorn text-secondary" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3d_heading'); } else { echo 'Marketing Cookies'; } ?>
                    </h3>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s3d_none'); } else { echo 'We do not use marketing or advertising cookies. We do not track you across websites, build advertising profiles, or sell your data to third parties.'; } ?>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Managing Your Cookie Preferences                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s4">
                    <h2 id="cookies-s4" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_heading'); } else { echo '4. Managing Your Cookie Preferences'; } ?>
                    </h2>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_banner_heading'); } else { echo 'Cookie Consent Banner'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_banner_desc'); } else { echo 'When you first visit our service, you will be presented with a cookie consent banner that allows you to accept or decline non-essential cookies. Essential cookies cannot be disabled as they are required for the service to function.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_change_heading'); } else { echo 'Changing Your Preferences'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_change_desc'); } else { echo 'You can change your cookie preferences at any time by clicking the button below, or by visiting this page and using the cookie consent controls.'; } ?>
                    </p>

                    <p class="my-4">
                        <button type="button" class="btn btn-primary" onclick="if(window.G2MLCookieConsent){window.G2MLCookieConsent.showModal();}">
                            <i class="fas fa-cog" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('legal.cookies_manage_button'); } else { echo 'Manage Cookie Preferences'; } ?>
                        </button>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_browser_heading'); } else { echo 'Browser Settings'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_browser_desc'); } else { echo 'Most web browsers allow you to control cookies through their settings. You can typically find these options in the "Privacy" or "Security" section of your browser preferences. Common actions include:'; } ?>
                    </p>

                    <ul>
                        <li><?php if (function_exists('__')) { echo __('legal.cookies_browser_view'); } else { echo 'Viewing all cookies stored on your device'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.cookies_browser_delete'); } else { echo 'Deleting some or all cookies'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.cookies_browser_block'); } else { echo 'Blocking all cookies or only third-party cookies'; } ?></li>
                        <li><?php if (function_exists('__')) { echo __('legal.cookies_browser_clear_ls'); } else { echo 'Clearing localStorage data via your browser\'s developer tools'; } ?></li>
                    </ul>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_browser_warning'); } else { echo 'Please note that blocking or deleting essential cookies may prevent you from using certain features of our service, such as staying logged in or maintaining your session.'; } ?>
                    </div>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php if (function_exists('__')) { echo __('legal.cookies_s4_review_note'); } else { echo 'Cookie consent mechanism details (banner behaviour, granularity of controls, re-consent intervals) should be reviewed for compliance with UK GDPR, the Privacy and Electronic Communications Regulations 2003 (PECR), and ePrivacy requirements.'; } ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Do Not Track & Global Privacy Control              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s5">
                    <h2 id="cookies-s5" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s5_heading'); } else { echo '5. Do Not Track &amp; Global Privacy Control'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s5_p1'); } else { echo 'We respect the <strong>Do Not Track (DNT)</strong> signal sent by your browser. When we detect that DNT is enabled, we will not set any non-essential cookies or engage in any cross-site tracking.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s5_p2'); } else { echo 'We also honour the <strong>Global Privacy Control (GPC)</strong> signal, a newer standard that communicates your privacy preferences to websites. When we detect a GPC signal, we treat it as a request to opt out of non-essential cookies and any data sharing.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s5_p3'); } else { echo 'For more information about how we handle these signals and your broader privacy rights, please see our'; } ?>
                        <a href="/legal/privacy#dnt"><?php if (function_exists('__')) { echo __('legal.cookies_s5_privacy_link'); } else { echo 'Privacy Policy (Do Not Track section)'; } ?></a>.
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php if (function_exists('__')) { echo __('legal.cookies_s5_review_note'); } else { echo 'DNT/GPC implementation details and the legal effect of these signals under UK GDPR and applicable regulations should be confirmed by legal counsel.'; } ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Changes to This Policy                             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s6">
                    <h2 id="cookies-s6" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s6_heading'); } else { echo '6. Changes to This Policy'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s6_p1'); } else { echo 'We may update this Cookie Policy from time to time to reflect changes in our practices, the cookies we use, or for other operational, legal, or regulatory reasons. When we make changes, we will update the "Last updated" date at the top of this page and increment the version number.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s6_p2'); } else { echo 'If we make material changes to this policy, such as introducing new cookie categories or changing how we use existing cookies, we will notify you by displaying a prominent notice on our service or, where appropriate, requesting your consent again.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s6_p3'); } else { echo 'We encourage you to review this page periodically to stay informed about our use of cookies.'; } ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php if (function_exists('__')) { echo __('legal.cookies_s6_review_note'); } else { echo 'Notification and re-consent requirements for material cookie policy changes should be reviewed for UK GDPR and PECR compliance.'; } ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Contact                                            -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="cookies-s7">
                    <h2 id="cookies-s7" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('legal.cookies_s7_heading'); } else { echo '7. Contact'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('legal.cookies_s7_p1'); } else { echo 'If you have any questions about this Cookie Policy or our use of cookies, please contact us:'; } ?>
                    </p>

                    <ul class="list-unstyled ms-3">
                        <li class="mb-2">
                            <i class="fas fa-envelope text-body-secondary" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.contact_email_label'); } else { echo 'Email:'; } ?></strong>
                            <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-building text-body-secondary" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.contact_company_label'); } else { echo 'Company:'; } ?></strong>
                            <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>
                            (<?php if (function_exists('__')) { echo __('legal.trading_as'); } else { echo 'trading as'; } ?> MWservices)
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-globe text-body-secondary" aria-hidden="true"></i>
                            <strong><?php if (function_exists('__')) { echo __('legal.contact_website_label'); } else { echo 'Website:'; } ?></strong>
                            <a href="https://go2my.link">go2my.link</a>
                        </li>
                    </ul>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php if (function_exists('__')) { echo __('legal.cookies_s7_review_note'); } else { echo 'Contact information, registered address, and data protection officer details (if applicable) should be confirmed. Consider whether an ICO registration number should be included.'; } ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Back to Legal Pages + Contact CTA                             -->
                <!-- ============================================================ -->
                <hr class="my-5">

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/legal/privacy" class="btn btn-outline-secondary">
                        <i class="fas fa-user-shield" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.link_privacy'); } else { echo 'Privacy Policy'; } ?>
                    </a>
                    <a href="/legal/terms" class="btn btn-outline-secondary">
                        <i class="fas fa-file-contract" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('legal.link_terms'); } else { echo 'Terms of Use'; } ?>
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
