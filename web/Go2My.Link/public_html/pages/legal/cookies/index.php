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

$pageTitle = function_exists('__') ? __('legal.cookies_title') : 'Cookie Policy';
$pageDesc  = function_exists('__') ? __('legal.cookies_description') : 'Go2My.Link Cookie Policy — how we use cookies and similar technologies.';

$legalVersion   = function_exists('getSetting') ? getSetting('legal.cookies_version', '1.0') : '1.0';
$legalUpdated   = function_exists('getSetting') ? getSetting('legal.last_updated', '2026-02-23') : '2026-02-23';
$siteName       = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$companyName    = 'MWBM Partners Ltd';
$contactEmail   = function_exists('getSetting') ? getSetting('site.contact_email', 'hello@go2my.link') : 'hello@go2my.link';
$hideReviewPlaceholders = function_exists('getSetting') && getSetting('legal.hide_review_placeholders', '0') === '1';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="cookies-heading">
    <div class="container">
        <h1 id="cookies-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('legal.cookies_heading') : 'Cookie Policy'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('legal.cookies_subtitle') : 'How we use cookies and similar technologies.'; ?>
        </p>
        <p class="text-body-secondary mb-0">
            <span class="badge bg-secondary">
                <?php echo function_exists('__') ? __('legal.version') : 'Version'; ?>
                <?php echo htmlspecialchars($legalVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="ms-2">
                <?php echo function_exists('__') ? __('legal.last_updated') : 'Last updated:'; ?>
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
                                <?php echo function_exists('__') ? __('legal.toc_heading') : 'Table of Contents'; ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#cookies-s1"><?php echo function_exists('__') ? __('legal.cookies_s1_title') : 'What Are Cookies'; ?></a></li>
                                <li><a href="#cookies-s2"><?php echo function_exists('__') ? __('legal.cookies_s2_title') : 'How We Use Cookies'; ?></a></li>
                                <li><a href="#cookies-s3"><?php echo function_exists('__') ? __('legal.cookies_s3_title') : 'Cookie Categories &amp; Inventory'; ?></a></li>
                                <li><a href="#cookies-s4"><?php echo function_exists('__') ? __('legal.cookies_s4_title') : 'Managing Your Cookie Preferences'; ?></a></li>
                                <li><a href="#cookies-s5"><?php echo function_exists('__') ? __('legal.cookies_s5_title') : 'Do Not Track &amp; Global Privacy Control'; ?></a></li>
                                <li><a href="#cookies-s6"><?php echo function_exists('__') ? __('legal.cookies_s6_title') : 'Changes to This Policy'; ?></a></li>
                                <li><a href="#cookies-s7"><?php echo function_exists('__') ? __('legal.cookies_s7_title') : 'Contact'; ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: What Are Cookies                                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s1">
                    <h2 id="cookies-s1" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s1_heading') : '1. What Are Cookies'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s1_p1') : 'Cookies are small text files that are placed on your device (computer, tablet, or mobile phone) when you visit a website. They are widely used to make websites work more efficiently, provide a better user experience, and supply information to website operators.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s1_p2') : 'In addition to cookies, we also use <strong>localStorage</strong>, a similar browser-based storage mechanism that allows websites to store data locally on your device. Unlike cookies, localStorage data is not sent to the server with every request, but it serves a similar purpose for preserving your preferences.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s1_p3') : 'Throughout this policy, when we refer to "cookies", we include both traditional HTTP cookies and localStorage unless otherwise stated.'; ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php echo function_exists('__') ? __('legal.cookies_s1_first_vs_third_heading') : 'First-Party vs Third-Party Cookies'; ?>
                    </h3>

                    <ul>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_first_party') : 'First-party cookies'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_first_party_desc') : 'Set by the website you are visiting (in this case, go2my.link, g2my.link, or lnks.page). All cookies we currently use are first-party cookies.'; ?>
                        </li>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_third_party') : 'Third-party cookies'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_third_party_desc') : 'Set by a domain other than the one you are visiting. We do not currently use any third-party cookies.'; ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: How We Use Cookies                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s2">
                    <h2 id="cookies-s2" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s2_heading') : '2. How We Use Cookies'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s2_intro') : 'We use cookies and similar technologies on our service for the following purposes:'; ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_use_session') : 'Session Management'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_use_session_desc') : 'To maintain your session while you use the service, keeping you logged in and preserving your state as you navigate between pages.'; ?>
                        </li>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_use_preferences') : 'Preferences'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_use_preferences_desc') : 'To remember your settings and preferences, such as your chosen theme (light or dark mode) and language preference, so you do not need to set them each time you visit.'; ?>
                        </li>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_use_security') : 'Security'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_use_security_desc') : 'To protect you against cross-site request forgery (CSRF) attacks and other security threats by generating and validating security tokens.'; ?>
                        </li>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_use_consent') : 'Cookie Consent'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_use_consent_desc') : 'To remember whether you have accepted or declined non-essential cookies, so we do not ask you repeatedly.'; ?>
                        </li>
                        <li>
                            <strong><?php echo function_exists('__') ? __('legal.cookies_use_analytics') : 'Analytics (Future)'; ?></strong> —
                            <?php echo function_exists('__') ? __('legal.cookies_use_analytics_desc') : 'In the future, we may use analytics cookies to understand how visitors interact with our service, helping us improve the user experience. These will only be set with your consent.'; ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Cookie Categories & Inventory                      -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s3">
                    <h2 id="cookies-s3" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s3_heading') : '3. Cookie Categories &amp; Inventory'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s3_intro') : 'Below is a complete inventory of the cookies and similar technologies used by our service, organised by category.'; ?>
                    </p>

                    <!-- ======================================================== -->
                    <!-- 3a. Essential Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3a">
                        <i class="fas fa-lock text-success" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3a_heading') : 'Essential Cookies'; ?>
                    </h3>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s3a_desc') : 'These cookies are strictly necessary for the operation of our service. They cannot be disabled as the service would not function correctly without them. They do not store any personally identifiable information.'; ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_name') : 'Name'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_purpose') : 'Purpose'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_duration') : 'Duration'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_type') : 'Type'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>G2ML_SESSION</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_session_purpose') : 'Maintains your server-side session, keeping you logged in and preserving your state across page requests.'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_session_duration') : 'Session (deleted when you close your browser)'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_http') : 'HTTP Cookie'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_consent</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_consent_purpose') : 'Records your cookie consent preferences so we respect your choices on subsequent visits.'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_consent_duration') : '1 year'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_http') : 'HTTP Cookie'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_csrf</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_csrf_purpose') : 'Provides cross-site request forgery (CSRF) protection by validating that form submissions originate from our service.'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_csrf_duration') : 'Session (deleted when you close your browser)'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_http') : 'HTTP Cookie'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3b. Functional Cookies                                    -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3b">
                        <i class="fas fa-sliders-h text-primary" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3b_heading') : 'Functional Cookies'; ?>
                    </h3>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s3b_desc') : 'These cookies enable enhanced functionality and personalisation. They may be set by us or by third-party providers whose services we have added to our pages. If you disable these cookies, some or all of these features may not function properly.'; ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_name') : 'Name'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_purpose') : 'Purpose'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_duration') : 'Duration'; ?></th>
                                    <th scope="col"><?php echo function_exists('__') ? __('legal.cookies_col_type') : 'Type'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>g2ml_theme</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_theme_purpose') : 'Stores your display theme preference (light, dark, or auto/system) so the correct theme is applied on each visit, including on the initial page load before JavaScript runs.'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_theme_duration') : '1 year'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_http') : 'HTTP Cookie'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml_locale</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_locale_purpose') : 'Stores your preferred language so content is displayed in your chosen language on subsequent visits.'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_locale_duration') : '1 year'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_http') : 'HTTP Cookie'; ?></td>
                                </tr>
                                <tr>
                                    <td><code>g2ml-theme</code></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_ls_theme_purpose') : 'A client-side mirror of your theme preference, used by JavaScript to apply the theme instantly without waiting for a server response, preventing a flash of unstyled content (FOUC).'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_ls_theme_duration') : 'Persistent (until cleared)'; ?></td>
                                    <td><?php echo function_exists('__') ? __('legal.cookies_type_localstorage') : 'localStorage'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3c. Analytics Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3c">
                        <i class="fas fa-chart-bar text-info" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3c_heading') : 'Analytics Cookies'; ?>
                    </h3>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3c_none') : 'We do not currently use any analytics cookies. If we introduce analytics cookies in the future, this section will be updated and your consent will be requested before any such cookies are set.'; ?>
                    </div>

                    <!-- ======================================================== -->
                    <!-- 3d. Marketing Cookies                                     -->
                    <!-- ======================================================== -->
                    <h3 class="h5 fw-bold mt-4 mb-3" id="cookies-s3d">
                        <i class="fas fa-bullhorn text-secondary" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3d_heading') : 'Marketing Cookies'; ?>
                    </h3>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s3d_none') : 'We do not use marketing or advertising cookies. We do not track you across websites, build advertising profiles, or sell your data to third parties.'; ?>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Managing Your Cookie Preferences                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s4">
                    <h2 id="cookies-s4" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s4_heading') : '4. Managing Your Cookie Preferences'; ?>
                    </h2>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php echo function_exists('__') ? __('legal.cookies_s4_banner_heading') : 'Cookie Consent Banner'; ?>
                    </h3>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s4_banner_desc') : 'When you first visit our service, you will be presented with a cookie consent banner that allows you to accept or decline non-essential cookies. Essential cookies cannot be disabled as they are required for the service to function.'; ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php echo function_exists('__') ? __('legal.cookies_s4_change_heading') : 'Changing Your Preferences'; ?>
                    </h3>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s4_change_desc') : 'You can change your cookie preferences at any time by clicking the button below, or by visiting this page and using the cookie consent controls.'; ?>
                    </p>

                    <p class="my-4">
                        <button type="button" class="btn btn-primary" onclick="if(window.G2MLCookieConsent){window.G2MLCookieConsent.showModal();}">
                            <i class="fas fa-cog" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('legal.cookies_manage_button') : 'Manage Cookie Preferences'; ?>
                        </button>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php echo function_exists('__') ? __('legal.cookies_s4_browser_heading') : 'Browser Settings'; ?>
                    </h3>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s4_browser_desc') : 'Most web browsers allow you to control cookies through their settings. You can typically find these options in the "Privacy" or "Security" section of your browser preferences. Common actions include:'; ?>
                    </p>

                    <ul>
                        <li><?php echo function_exists('__') ? __('legal.cookies_browser_view') : 'Viewing all cookies stored on your device'; ?></li>
                        <li><?php echo function_exists('__') ? __('legal.cookies_browser_delete') : 'Deleting some or all cookies'; ?></li>
                        <li><?php echo function_exists('__') ? __('legal.cookies_browser_block') : 'Blocking all cookies or only third-party cookies'; ?></li>
                        <li><?php echo function_exists('__') ? __('legal.cookies_browser_clear_ls') : 'Clearing localStorage data via your browser\'s developer tools'; ?></li>
                    </ul>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_s4_browser_warning') : 'Please note that blocking or deleting essential cookies may prevent you from using certain features of our service, such as staying logged in or maintaining your session.'; ?>
                    </div>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php echo function_exists('__') ? __('legal.cookies_s4_review_note') : 'Cookie consent mechanism details (banner behaviour, granularity of controls, re-consent intervals) should be reviewed for compliance with UK GDPR, the Privacy and Electronic Communications Regulations 2003 (PECR), and ePrivacy requirements.'; ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Do Not Track & Global Privacy Control              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s5">
                    <h2 id="cookies-s5" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s5_heading') : '5. Do Not Track &amp; Global Privacy Control'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s5_p1') : 'We respect the <strong>Do Not Track (DNT)</strong> signal sent by your browser. When we detect that DNT is enabled, we will not set any non-essential cookies or engage in any cross-site tracking.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s5_p2') : 'We also honour the <strong>Global Privacy Control (GPC)</strong> signal, a newer standard that communicates your privacy preferences to websites. When we detect a GPC signal, we treat it as a request to opt out of non-essential cookies and any data sharing.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s5_p3') : 'For more information about how we handle these signals and your broader privacy rights, please see our'; ?>
                        <a href="/legal/privacy#dnt"><?php echo function_exists('__') ? __('legal.cookies_s5_privacy_link') : 'Privacy Policy (Do Not Track section)'; ?></a>.
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php echo function_exists('__') ? __('legal.cookies_s5_review_note') : 'DNT/GPC implementation details and the legal effect of these signals under UK GDPR and applicable regulations should be confirmed by legal counsel.'; ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Changes to This Policy                             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cookies-s6">
                    <h2 id="cookies-s6" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s6_heading') : '6. Changes to This Policy'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s6_p1') : 'We may update this Cookie Policy from time to time to reflect changes in our practices, the cookies we use, or for other operational, legal, or regulatory reasons. When we make changes, we will update the "Last updated" date at the top of this page and increment the version number.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s6_p2') : 'If we make material changes to this policy, such as introducing new cookie categories or changing how we use existing cookies, we will notify you by displaying a prominent notice on our service or, where appropriate, requesting your consent again.'; ?>
                    </p>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s6_p3') : 'We encourage you to review this page periodically to stay informed about our use of cookies.'; ?>
                    </p>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php echo function_exists('__') ? __('legal.cookies_s6_review_note') : 'Notification and re-consent requirements for material cookie policy changes should be reviewed for UK GDPR and PECR compliance.'; ?>
                    </div>
                    <?php } ?>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Contact                                            -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="cookies-s7">
                    <h2 id="cookies-s7" class="h4 fw-bold mb-3">
                        <?php echo function_exists('__') ? __('legal.cookies_s7_heading') : '7. Contact'; ?>
                    </h2>

                    <p>
                        <?php echo function_exists('__') ? __('legal.cookies_s7_p1') : 'If you have any questions about this Cookie Policy or our use of cookies, please contact us:'; ?>
                    </p>

                    <ul class="list-unstyled ms-3">
                        <li class="mb-2">
                            <i class="fas fa-envelope text-body-secondary" aria-hidden="true"></i>
                            <strong><?php echo function_exists('__') ? __('legal.contact_email_label') : 'Email:'; ?></strong>
                            <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-building text-body-secondary" aria-hidden="true"></i>
                            <strong><?php echo function_exists('__') ? __('legal.contact_company_label') : 'Company:'; ?></strong>
                            <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo function_exists('__') ? __('legal.trading_as') : 'trading as'; ?> MWservices)
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-globe text-body-secondary" aria-hidden="true"></i>
                            <strong><?php echo function_exists('__') ? __('legal.contact_website_label') : 'Website:'; ?></strong>
                            <a href="https://go2my.link">go2my.link</a>
                        </li>
                    </ul>

                    <?php if (!$hideReviewPlaceholders) { ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-balance-scale" aria-hidden="true"></i>
                        <strong>{{LEGAL_REVIEW_NEEDED}}</strong> —
                        <?php echo function_exists('__') ? __('legal.cookies_s7_review_note') : 'Contact information, registered address, and data protection officer details (if applicable) should be confirmed. Consider whether an ICO registration number should be included.'; ?>
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
                        <?php echo function_exists('__') ? __('legal.link_privacy') : 'Privacy Policy'; ?>
                    </a>
                    <a href="/legal/terms" class="btn btn-outline-secondary">
                        <i class="fas fa-file-contract" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.link_terms') : 'Terms of Use'; ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.contact_cta') : 'Questions? Contact Us'; ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
