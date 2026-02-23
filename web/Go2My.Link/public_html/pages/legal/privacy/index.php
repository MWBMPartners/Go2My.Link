<?php
/**
 * ============================================================================
 * Go2My.Link — Privacy Policy Page (Component A)
 * ============================================================================
 *
 * Structured Privacy Policy template with GDPR, CCPA, and LGPD compliance.
 * Contains {{LEGAL_REVIEW_NEEDED}} placeholders for professional review.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.7.0
 * @since      Phase 3 (structured template Phase 6)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('legal.privacy_title') : 'Privacy Policy';
$pageDesc  = function_exists('__') ? __('legal.privacy_description') : 'Go2My.Link Privacy Policy — how we collect, use, and protect your personal data.';

$legalVersion   = function_exists('getSetting') ? getSetting('legal.privacy_version', '1.0') : '1.0';
$legalUpdated   = function_exists('getSetting') ? getSetting('legal.last_updated', '2026-02-23') : '2026-02-23';
$siteName       = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$companyName    = 'MWBM Partners Ltd';
$companyTrading = 'MWservices';
$contactEmail   = function_exists('getSetting') ? getSetting('site.contact_email', 'hello@go2my.link') : 'hello@go2my.link';
$hideReviewPlaceholders = function_exists('getSetting') && getSetting('legal.hide_review_placeholders', '0') === '1';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center py-5" aria-labelledby="privacy-heading">
    <div class="container">
        <h1 id="privacy-heading" class="display-4 fw-bold">
            <i class="fas fa-user-shield me-2" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('legal.privacy_heading') : 'Privacy Policy'; ?>
        </h1>
        <p class="lead text-body-secondary mt-3">
            <?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <div class="mt-3">
            <span class="badge bg-secondary fs-6">
                <?php echo function_exists('__') ? __('legal.version') : 'Version'; ?>
                <?php echo htmlspecialchars($legalVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="text-body-secondary ms-3">
                <i class="fas fa-calendar-alt me-1" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('legal.last_updated') : 'Last Updated'; ?>:
                <?php echo htmlspecialchars($legalUpdated, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Table of Contents                                                       -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-toc-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h2 id="privacy-toc-heading" class="card-title h5 mb-3">
                            <i class="fas fa-list me-2" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('legal.toc_heading') : 'Table of Contents'; ?>
                        </h2>
                        <nav aria-label="Table of Contents">
                            <ol class="list-group list-group-numbered list-group-flush">
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s1" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s1_title') : 'Introduction'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s2" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s2_title') : 'Data We Collect'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s3" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s3_title') : 'How We Use Your Data'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s4" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s4_title') : 'Legal Basis for Processing (GDPR Art. 6)'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s5" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s5_title') : 'Data Sharing'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s6" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s6_title') : 'International Data Transfers'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s7" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s7_title') : 'Data Retention'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s8" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s8_title') : 'Your Rights'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s9" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s9_title') : 'Children\'s Privacy'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s10" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s10_title') : 'Security'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s11" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s11_title') : 'Cookie Policy'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s12" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s12_title') : 'Do Not Track'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s13" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s13_title') : 'Changes to This Policy'; ?>
                                    </a>
                                </li>
                                <li class="list-group-item bg-transparent">
                                    <a href="#privacy-s14" class="text-decoration-none">
                                        <?php echo function_exists('__') ? __('legal.privacy_s14_title') : 'Contact &amp; Data Protection Officer'; ?>
                                    </a>
                                </li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 1: Introduction                                                 -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s1">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s1" class="h4 fw-bold mb-3">
                    1. <?php echo function_exists('__') ? __('legal.privacy_s1_title') : 'Introduction'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s1_intro') : 'Welcome to ' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' (the "Service"), operated by ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . ', trading as ' . htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8') . ', a company registered in the United Kingdom.'; ?>
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s1_scope') : 'This Privacy Policy explains how we collect, use, store, share, and protect your personal data when you use our URL shortening service across our three domains:'; ?>
                </p>

                <ul>
                    <li><strong>go2my.link</strong> &mdash; <?php echo function_exists('__') ? __('legal.privacy_domain_a') : 'Main website, account management, and URL creation'; ?></li>
                    <li><strong>g2my.link</strong> &mdash; <?php echo function_exists('__') ? __('legal.privacy_domain_b') : 'Short link redirection service'; ?></li>
                    <li><strong>lnks.page</strong> &mdash; <?php echo function_exists('__') ? __('legal.privacy_domain_c') : 'LinksPage public profile pages'; ?></li>
                </ul>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s1_controller') : 'For the purposes of applicable data protection legislation (including the UK General Data Protection Regulation, the EU General Data Protection Regulation, and the Data Protection Act 2018), the data controller is:'; ?>
                </p>

                <div class="card bg-body-tertiary mb-3">
                    <div class="card-body">
                        <strong><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <?php echo function_exists('__') ? __('legal.privacy_trading_as') : 'Trading as'; ?>
                        <?php echo htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8'); ?><br>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s1_consent') : 'By accessing or using the Service, you acknowledge that you have read and understood this Privacy Policy. If you do not agree with our data practices, please do not use the Service.'; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 2: Data We Collect                                              -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s2">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s2" class="h4 fw-bold mb-3">
                    2. <?php echo function_exists('__') ? __('legal.privacy_s2_title') : 'Data We Collect'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_intro') : 'We collect different types of data depending on how you interact with the Service. Below is a summary of the categories of personal data we may collect.'; ?>
                </p>

                <!-- 2.1 Account Data -->
                <h3 class="h5 mt-4 mb-2">
                    2.1 <?php echo function_exists('__') ? __('legal.privacy_s2_account_title') : 'Account Data'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_account_desc') : 'When you create an account, we collect:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_account_name') : 'Full name (first name and surname)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_account_email') : 'Email address'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_account_password') : 'Password (stored as a one-way cryptographic hash &mdash; we never store your plaintext password)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_account_org') : 'Organisation name (if you create or join an organisation)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_account_prefs') : 'Account preferences and settings'; ?></li>
                </ul>

                <!-- 2.2 Usage Data -->
                <h3 class="h5 mt-4 mb-2">
                    2.2 <?php echo function_exists('__') ? __('legal.privacy_s2_usage_title') : 'Usage Data'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_usage_desc') : 'When you interact with the Service, we automatically collect:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_ip') : 'IP address (may be truncated or anonymised for analytics)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_ua') : 'Browser type and user agent string'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_time') : 'Date and time of access (timestamps)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_referrer') : 'Referring URL (the page that linked you to us)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_pages') : 'Pages and features you access within the Service'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_usage_device') : 'Device type, operating system, and screen resolution'; ?></li>
                </ul>

                <!-- 2.3 Short URL Data -->
                <h3 class="h5 mt-4 mb-2">
                    2.3 <?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_title') : 'Short URL Data'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_desc') : 'When you create or interact with short URLs, we collect:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_dest') : 'Destination (long) URLs you submit for shortening'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_code') : 'Generated short codes and any custom aliases'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_clicks') : 'Click counts and click metadata (timestamp, IP, user agent, referrer)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s2_shorturl_meta') : 'URL metadata (title, description, creation date, expiry settings)'; ?></li>
                </ul>

                <!-- 2.4 Cookies & Local Storage -->
                <h3 class="h5 mt-4 mb-2">
                    2.4 <?php echo function_exists('__') ? __('legal.privacy_s2_cookies_title') : 'Cookies &amp; Local Storage'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_cookies_desc') : 'We use cookies and browser local storage to provide essential functionality. This includes session management, theme preferences (dark/light mode), and CSRF protection tokens. For full details, see our'; ?>
                    <a href="/legal/cookies"><?php echo function_exists('__') ? __('legal.cookie_policy_link') : 'Cookie Policy'; ?></a>.
                </p>

                <!-- 2.5 DNT / GPC Signals -->
                <h3 class="h5 mt-4 mb-2">
                    2.5 <?php echo function_exists('__') ? __('legal.privacy_s2_dnt_title') : 'Do Not Track &amp; Global Privacy Control Signals'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s2_dnt_desc') : 'We respect Do Not Track (DNT) and Global Privacy Control (GPC) signals sent by your browser. When we detect these signals, we limit data collection to what is strictly necessary for the Service to function. See Section 12 for more details.'; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 3: How We Use Your Data                                         -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s3">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s3" class="h4 fw-bold mb-3">
                    3. <?php echo function_exists('__') ? __('legal.privacy_s3_title') : 'How We Use Your Data'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s3_intro') : 'We use the personal data we collect for the following purposes:'; ?>
                </p>

                <h3 class="h5 mt-4 mb-2">
                    3.1 <?php echo function_exists('__') ? __('legal.privacy_s3_service_title') : 'Providing the Service'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_service_shorten') : 'Creating, managing, and resolving shortened URLs'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_service_accounts') : 'Managing user accounts, authentication, and session management'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_service_linkspage') : 'Rendering LinksPage profiles on lnks.page'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_service_redirect') : 'Processing redirect requests through g2my.link'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    3.2 <?php echo function_exists('__') ? __('legal.privacy_s3_analytics_title') : 'Analytics &amp; Improvement'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_analytics_clicks') : 'Tracking click statistics for your shortened URLs (visible in your dashboard)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_analytics_improve') : 'Understanding how users interact with the Service to improve features and performance'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_analytics_aggregate') : 'Generating aggregate, anonymised usage statistics'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    3.3 <?php echo function_exists('__') ? __('legal.privacy_s3_security_title') : 'Security &amp; Abuse Prevention'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_security_rate') : 'Rate limiting to prevent abuse of the URL creation service'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_security_spam') : 'Detecting and blocking spam, malicious URLs, and fraudulent activity'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_security_audit') : 'Maintaining audit logs for security monitoring and incident response'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    3.4 <?php echo function_exists('__') ? __('legal.privacy_s3_comms_title') : 'Communications'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_comms_service') : 'Sending service-related notifications (e.g., account verification, password resets)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_comms_updates') : 'Notifying you of important changes to the Service or this policy'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_comms_support') : 'Responding to your enquiries and support requests'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    3.5 <?php echo function_exists('__') ? __('legal.privacy_s3_legal_title') : 'Legal Obligations'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_legal_comply') : 'Complying with applicable laws, regulations, and legal processes'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_legal_enforce') : 'Enforcing our Terms of Use and other agreements'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s3_legal_protect') : 'Protecting the rights, property, and safety of our users and the public'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 4: Legal Basis for Processing (GDPR Art. 6)                     -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s4" class="h4 fw-bold mb-3">
                    4. <?php echo function_exists('__') ? __('legal.privacy_s4_title') : 'Legal Basis for Processing (GDPR Art. 6)'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s4_intro') : 'Under the UK GDPR and EU GDPR, we rely on the following legal bases when processing your personal data:'; ?>
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s4_col_basis') : 'Legal Basis'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s4_col_purpose') : 'Purpose'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s4_col_examples') : 'Examples'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php echo function_exists('__') ? __('legal.privacy_s4_contract') : 'Performance of Contract'; ?></strong><br><small class="text-body-secondary">Art. 6(1)(b)</small></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_contract_purpose') : 'Processing necessary to fulfil our agreement with you'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_contract_examples') : 'Creating your account, shortening URLs, providing redirect services, managing your dashboard'; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo function_exists('__') ? __('legal.privacy_s4_consent') : 'Consent'; ?></strong><br><small class="text-body-secondary">Art. 6(1)(a)</small></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_consent_purpose') : 'Processing based on your explicit, freely given consent'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_consent_examples') : 'Optional marketing communications, non-essential cookies, analytics beyond basic service operation'; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo function_exists('__') ? __('legal.privacy_s4_legitimate') : 'Legitimate Interest'; ?></strong><br><small class="text-body-secondary">Art. 6(1)(f)</small></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_legitimate_purpose') : 'Processing necessary for our legitimate business interests, balanced against your rights'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_legitimate_examples') : 'Security monitoring, abuse prevention, service improvement, aggregate analytics'; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo function_exists('__') ? __('legal.privacy_s4_obligation') : 'Legal Obligation'; ?></strong><br><small class="text-body-secondary">Art. 6(1)(c)</small></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_obligation_purpose') : 'Processing required to comply with legal requirements'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s4_obligation_examples') : 'Responding to lawful data subject requests, cooperating with law enforcement when legally required, tax and accounting records'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 5: Data Sharing                                                 -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s5" class="h4 fw-bold mb-3">
                    5. <?php echo function_exists('__') ? __('legal.privacy_s5_title') : 'Data Sharing'; ?>
                </h2>

                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                    <strong><?php echo function_exists('__') ? __('legal.privacy_no_sale') : 'We do not sell your personal data.'; ?></strong>
                    <?php echo function_exists('__') ? __('legal.privacy_no_sale_detail') : 'We have never sold personal data and have no plans to do so. We do not share your data with third parties for their own marketing purposes.'; ?>
                </div>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s5_intro') : 'We may share your data with the following categories of recipients, strictly as necessary:'; ?>
                </p>

                <h3 class="h5 mt-4 mb-2">
                    5.1 <?php echo function_exists('__') ? __('legal.privacy_s5_providers_title') : 'Service Providers'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s5_providers_desc') : 'We use trusted third-party providers to help operate the Service. These providers only process data on our behalf and under our instructions:'; ?>
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s5_col_provider') : 'Provider'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s5_col_purpose') : 'Purpose'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s5_col_location') : 'Location'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s5_dreamhost') : 'DreamHost'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s5_dreamhost_purpose') : 'Web hosting, database hosting, and email services'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s5_dreamhost_location') : 'United States'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="h5 mt-4 mb-2">
                    5.2 <?php echo function_exists('__') ? __('legal.privacy_s5_law_title') : 'Law Enforcement &amp; Legal Requirements'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s5_law_desc') : 'We may disclose your personal data if required to do so by law, or if we believe in good faith that such disclosure is necessary to:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s5_law_comply') : 'Comply with a legal obligation, court order, or lawful government request'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s5_law_protect') : 'Protect and defend the rights or property of ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s5_law_safety') : 'Prevent or investigate potential wrongdoing in connection with the Service'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s5_law_public') : 'Protect the personal safety of users or the public'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    5.3 <?php echo function_exists('__') ? __('legal.privacy_s5_business_title') : 'Business Transfers'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s5_business_desc') : 'In the event of a merger, acquisition, reorganisation, or sale of assets, your personal data may be transferred as part of the transaction. We will notify you before your data becomes subject to a different privacy policy.'; ?>
                </p>

                <?php if (!$hideReviewPlaceholders): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                    <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                    <?php echo function_exists('__') ? __('legal.privacy_s5_review') : 'This section should be reviewed by a qualified legal professional to ensure all data sharing arrangements are accurately disclosed, and that appropriate Data Processing Agreements (DPAs) are in place with all third-party providers.'; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 6: International Data Transfers                                 -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s6">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s6" class="h4 fw-bold mb-3">
                    6. <?php echo function_exists('__') ? __('legal.privacy_s6_title') : 'International Data Transfers'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s6_intro') : htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . ' is based in the United Kingdom. However, our hosting infrastructure is located in the United States (DreamHost). This means your personal data may be transferred to, stored, and processed in a country outside the United Kingdom and the European Economic Area (EEA).'; ?>
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s6_safeguards') : 'When we transfer personal data internationally, we ensure that appropriate safeguards are in place to protect your data, including:'; ?>
                </p>

                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s6_adequacy') : 'Transfers to countries that have been deemed to provide an adequate level of data protection by the UK Secretary of State or the European Commission'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s6_sccs') : 'Standard Contractual Clauses (SCCs) approved by the UK Information Commissioner\'s Office (ICO) or the European Commission, as applicable'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s6_measures') : 'Supplementary technical and organisational measures to ensure your data remains protected'; ?></li>
                </ul>

                <?php if (!$hideReviewPlaceholders): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                    <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                    <?php echo function_exists('__') ? __('legal.privacy_s6_review') : 'A legal professional should review the specific transfer mechanisms in use (e.g., UK International Data Transfer Agreement, EU SCCs, or adequacy decisions) and confirm that a Transfer Impact Assessment (TIA) has been conducted for US transfers, particularly in light of the UK-US Data Bridge and EU-US Data Privacy Framework.'; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 7: Data Retention                                               -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s7">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s7" class="h4 fw-bold mb-3">
                    7. <?php echo function_exists('__') ? __('legal.privacy_s7_title') : 'Data Retention'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s7_intro') : 'We retain your personal data only for as long as necessary to fulfil the purposes for which it was collected, or as required by law. The specific retention periods are:'; ?>
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s7_col_data') : 'Data Type'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s7_col_period') : 'Retention Period'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s7_col_notes') : 'Notes'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_account') : 'Account data'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_account_period') : 'Duration of account + 30 days'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_account_notes') : 'After you close your account, we retain your data for a 30-day grace period to allow for account recovery, after which it is permanently deleted.'; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_urls') : 'Short URLs &amp; metadata'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_urls_period') : 'Configurable (default: indefinite while account active)'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_urls_notes') : 'You may set expiration dates on individual short URLs. Expired URLs are soft-deleted and permanently removed after 30 days. Anonymous (non-account) URLs follow the system-configured retention policy.'; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_clicks') : 'Click/analytics data'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_clicks_period') : 'Configurable (default: 90 days detailed, aggregated thereafter)'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_clicks_notes') : 'Detailed click logs (including IP and user agent) are retained for 90 days, after which they are aggregated into anonymised statistics. Aggregate data is retained indefinitely.'; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_activity') : 'Activity/audit logs'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_activity_period') : '90 days'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_activity_notes') : 'System audit logs are automatically purged after 90 days. Security-critical logs may be retained longer if required for ongoing investigations.'; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_session') : 'Session data'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_session_period') : 'Duration of session'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s7_session_notes') : 'Session data is deleted when you log out or when the session expires (whichever comes first).'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s7_deletion') : 'You may request deletion of your data at any time (see Section 8). We will honour deletion requests within 30 days, subject to any legal obligations that require us to retain certain data.'; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 8: Your Rights                                                  -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s8">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s8" class="h4 fw-bold mb-3">
                    8. <?php echo function_exists('__') ? __('legal.privacy_s8_title') : 'Your Rights'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_intro') : 'Depending on your location, you may have specific rights regarding your personal data under applicable data protection laws. Below is a summary of rights under the key frameworks we comply with.'; ?>
                </p>

                <!-- 8.1 GDPR Rights -->
                <h3 class="h5 mt-4 mb-2">
                    8.1 <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_title') : 'Rights Under UK GDPR &amp; EU GDPR'; ?>
                </h3>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_note') : 'If you are located in the United Kingdom or the European Economic Area, you have the following rights under the General Data Protection Regulation:'; ?>
                </div>
                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_access') : 'Right of Access (Art. 15)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_access_desc') : 'You have the right to request a copy of the personal data we hold about you.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_rectify') : 'Right to Rectification (Art. 16)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_rectify_desc') : 'You have the right to request correction of inaccurate or incomplete personal data.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_erase') : 'Right to Erasure (Art. 17)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_erase_desc') : 'You have the right to request deletion of your personal data in certain circumstances (the "right to be forgotten").'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_restrict') : 'Right to Restrict Processing (Art. 18)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_restrict_desc') : 'You have the right to request that we limit the processing of your personal data in certain situations.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_port') : 'Right to Data Portability (Art. 20)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_port_desc') : 'You have the right to receive your personal data in a structured, commonly used, machine-readable format and to transmit it to another controller.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_object') : 'Right to Object (Art. 21)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_object_desc') : 'You have the right to object to processing of your personal data based on legitimate interests or for direct marketing purposes.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_withdraw') : 'Right to Withdraw Consent'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_gdpr_withdraw_desc') : 'Where processing is based on your consent, you may withdraw that consent at any time without affecting the lawfulness of prior processing.'; ?>
                    </li>
                </ul>

                <!-- 8.2 CCPA Rights -->
                <h3 class="h5 mt-4 mb-2">
                    8.2 <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_title') : 'Rights Under the California Consumer Privacy Act (CCPA/CPRA)'; ?>
                </h3>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_note') : 'If you are a California resident, you have the following rights under the CCPA, as amended by the CPRA:'; ?>
                </div>
                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_know') : 'Right to Know'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_know_desc') : 'You have the right to know what personal information we collect, use, disclose, and sell (if applicable) about you.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_delete') : 'Right to Delete'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_delete_desc') : 'You have the right to request deletion of your personal information, subject to certain exceptions.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_optout') : 'Right to Opt-Out of Sale'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_optout_desc') : 'We do not sell personal information. If this changes, you will have the right to opt-out of any such sale.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_nondiscrim') : 'Right to Non-Discrimination'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_ccpa_nondiscrim_desc') : 'We will not discriminate against you for exercising any of your CCPA rights. You will not receive different pricing or quality of service for making a rights request.'; ?>
                    </li>
                </ul>

                <!-- 8.3 LGPD Rights -->
                <h3 class="h5 mt-4 mb-2">
                    8.3 <?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_title') : 'Rights Under Brazil\'s LGPD'; ?>
                </h3>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_note') : 'If you are located in Brazil, you have rights under the Lei Geral de Prote&ccedil;&atilde;o de Dados (LGPD), including:'; ?>
                </div>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_confirm') : 'Confirmation of the existence of data processing'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_access') : 'Access to your personal data'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_correct') : 'Correction of incomplete, inaccurate, or outdated data'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_anon') : 'Anonymisation, blocking, or deletion of unnecessary or excessive data'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_port') : 'Data portability to another service or product provider'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_delete') : 'Deletion of personal data processed with your consent'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_info') : 'Information about public and private entities with which we have shared your data'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s8_lgpd_withdraw') : 'Revocation of consent'; ?></li>
                </ul>

                <!-- 8.4 How to Exercise Your Rights -->
                <h3 class="h5 mt-4 mb-2">
                    8.4 <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_title') : 'How to Exercise Your Rights'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_desc') : 'You can exercise your data rights in any of the following ways:'; ?>
                </p>
                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_exercise_dashboard') : 'Privacy Dashboard'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_dashboard_desc') : 'Log in to your account and visit your privacy settings to download, modify, or delete your data.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s8_exercise_email') : 'Email'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_email_desc') : 'Send a request to'; ?>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_email_detail') : 'with the subject line "Data Rights Request". Please include sufficient information for us to verify your identity.'; ?>
                    </li>
                </ul>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s8_exercise_timeframe') : 'We will respond to all verified rights requests within 30 days. If the request is complex or we receive a large number of requests, we may extend this period by an additional 60 days, and we will inform you of the extension.'; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 9: Children's Privacy                                           -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s9">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s9" class="h4 fw-bold mb-3">
                    9. <?php echo function_exists('__') ? __('legal.privacy_s9_title') : 'Children\'s Privacy'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s9_intro') : 'The Service is not directed at children. We take the protection of children\'s privacy seriously and comply with applicable child protection laws.'; ?>
                </p>

                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s9_coppa') : 'COPPA (United States)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s9_coppa_desc') : 'We do not knowingly collect personal information from children under the age of 13. If you are under 13, please do not use the Service or provide any personal information.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s9_gdpr_age') : 'GDPR (UK/EU)'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s9_gdpr_age_desc') : 'We do not knowingly process personal data of individuals under the age of 16 without parental consent. In jurisdictions where the age of digital consent is lower (but not below 13), we comply with the local threshold.'; ?>
                    </li>
                </ul>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s9_discovery') : 'If we discover that we have inadvertently collected personal data from a child below the applicable age threshold, we will take immediate steps to delete such data. If you believe that a child has provided us with personal data, please contact us at'; ?>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 10: Security                                                    -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s10">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s10" class="h4 fw-bold mb-3">
                    10. <?php echo function_exists('__') ? __('legal.privacy_s10_title') : 'Security'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s10_intro') : 'We take the security of your personal data seriously and implement appropriate technical and organisational measures to protect it against unauthorised access, alteration, disclosure, or destruction.'; ?>
                </p>

                <h3 class="h5 mt-4 mb-2">
                    10.1 <?php echo function_exists('__') ? __('legal.privacy_s10_technical_title') : 'Technical Measures'; ?>
                </h3>
                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s10_https') : 'HTTPS Encryption'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s10_https_desc') : 'All communication between your browser and our servers is encrypted using TLS/SSL. All three domains (go2my.link, g2my.link, lnks.page) enforce HTTPS.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s10_passwords') : 'Password Hashing'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s10_passwords_desc') : 'User passwords are stored using industry-standard one-way cryptographic hashing (bcrypt). We never store or have access to your plaintext password.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s10_sessions') : 'Secure Sessions'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s10_sessions_desc') : 'Session tokens are generated using cryptographically secure random number generators. Session cookies are marked as Secure, HttpOnly, and SameSite.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s10_csrf') : 'CSRF Protection'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s10_csrf_desc') : 'All forms and state-changing API requests are protected with Cross-Site Request Forgery (CSRF) tokens.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s10_input') : 'Input Validation'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s10_input_desc') : 'All user input is validated and sanitised to prevent injection attacks (SQL injection, XSS, etc.).'; ?>
                    </li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    10.2 <?php echo function_exists('__') ? __('legal.privacy_s10_org_title') : 'Organisational Measures'; ?>
                </h3>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s10_org_access') : 'Access to personal data is restricted to authorised personnel on a need-to-know basis'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s10_org_review') : 'Regular review of security practices and access controls'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s10_org_incident') : 'Incident response procedures for detecting and responding to data breaches'; ?></li>
                </ul>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s10_disclaimer') : 'While we strive to protect your personal data, no method of transmission over the Internet or method of electronic storage is 100% secure. We cannot guarantee absolute security, but we are committed to maintaining a high standard of protection.'; ?>
                </p>

                <?php if (!$hideReviewPlaceholders): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                    <strong>{{LEGAL_REVIEW_NEEDED}}</strong>
                    <?php echo function_exists('__') ? __('legal.privacy_s10_review') : 'A security professional and legal counsel should review this section to ensure all technical and organisational measures are accurately described, and that the disclaimer language is appropriate for the jurisdictions in which the Service operates.'; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 11: Cookie Policy                                               -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s11">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s11" class="h4 fw-bold mb-3">
                    11. <?php echo function_exists('__') ? __('legal.privacy_s11_title') : 'Cookie Policy'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s11_intro') : 'We use cookies and similar technologies to provide essential functionality and improve your experience. Here is a brief summary:'; ?>
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s11_col_cookie') : 'Cookie / Storage'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s11_col_type') : 'Type'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('legal.privacy_s11_col_purpose') : 'Purpose'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>PHPSESSID</code></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_essential') : 'Essential'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_session_desc') : 'Session management &mdash; maintains your authenticated state'; ?></td>
                            </tr>
                            <tr>
                                <td><code>g2ml_theme</code></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_functional') : 'Functional'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_theme_desc') : 'Stores your dark/light mode preference for server-side rendering'; ?></td>
                            </tr>
                            <tr>
                                <td><code>g2ml_csrf</code></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_essential') : 'Essential'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_csrf_desc') : 'CSRF protection token for form submissions'; ?></td>
                            </tr>
                            <tr>
                                <td><code>g2ml-theme</code> <small>(localStorage)</small></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_functional') : 'Functional'; ?></td>
                                <td><?php echo function_exists('__') ? __('legal.privacy_s11_localstorage_desc') : 'Client-side theme preference for instant rendering (prevents flash of unstyled content)'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s11_full_policy') : 'For a comprehensive list of all cookies, their lifetimes, and your options for managing them, please see our full'; ?>
                    <a href="/legal/cookies">
                        <?php echo function_exists('__') ? __('legal.cookie_policy_link') : 'Cookie Policy'; ?>
                    </a>.
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s11_preferences') : 'You can manage your cookie preferences at any time through your browser settings or by visiting our'; ?>
                    <a href="/legal/cookies#cookie-preferences">
                        <?php echo function_exists('__') ? __('legal.privacy_s11_preferences_link') : 'cookie preferences centre'; ?>
                    </a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 12: Do Not Track                                                -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s12">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s12" class="h4 fw-bold mb-3">
                    12. <?php echo function_exists('__') ? __('legal.privacy_s12_title') : 'Do Not Track'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s12_intro') : 'We respect your privacy preferences as expressed through browser signals.'; ?>
                </p>

                <h3 class="h5 mt-4 mb-2">
                    12.1 <?php echo function_exists('__') ? __('legal.privacy_s12_dnt_title') : 'Do Not Track (DNT)'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s12_dnt_desc') : 'Do Not Track is a browser setting that sends a signal to websites requesting that they do not track your browsing activity. When we detect a DNT signal (the <code>DNT: 1</code> HTTP header), we:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s12_dnt_no_analytics') : 'Disable non-essential analytics collection'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s12_dnt_no_tracking') : 'Do not record detailed click metadata beyond what is necessary for the redirect service'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s12_dnt_essential') : 'Continue to use only essential cookies required for the Service to function'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    12.2 <?php echo function_exists('__') ? __('legal.privacy_s12_gpc_title') : 'Global Privacy Control (GPC)'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s12_gpc_desc') : 'Global Privacy Control is a newer browser signal (the <code>Sec-GPC: 1</code> HTTP header) that communicates your privacy preferences under laws like the CCPA. When we detect a GPC signal, we treat it as:'; ?>
                </p>
                <ul>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s12_gpc_optout') : 'A valid opt-out of the sale or sharing of personal information (CCPA)'; ?></li>
                    <li><?php echo function_exists('__') ? __('legal.privacy_s12_gpc_object') : 'An objection to processing for targeted advertising purposes'; ?></li>
                </ul>

                <h3 class="h5 mt-4 mb-2">
                    12.3 <?php echo function_exists('__') ? __('legal.privacy_s12_enable_title') : 'How to Enable These Signals'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s12_enable_desc') : 'Most modern browsers support DNT and/or GPC settings:'; ?>
                </p>
                <ul>
                    <li><strong>DNT</strong> &mdash; <?php echo function_exists('__') ? __('legal.privacy_s12_enable_dnt') : 'Usually found in your browser\'s privacy or security settings under "Send a Do Not Track request" or similar'; ?></li>
                    <li><strong>GPC</strong> &mdash; <?php echo function_exists('__') ? __('legal.privacy_s12_enable_gpc') : 'Supported natively in some browsers (e.g., Firefox, Brave) or via browser extensions. Visit <a href="https://globalprivacycontrol.org/" rel="noopener noreferrer" target="_blank">globalprivacycontrol.org</a> for more information'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 13: Changes to This Policy                                      -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="privacy-s13">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s13" class="h4 fw-bold mb-3">
                    13. <?php echo function_exists('__') ? __('legal.privacy_s13_title') : 'Changes to This Policy'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s13_intro') : 'We may update this Privacy Policy from time to time to reflect changes in our practices, technology, legal requirements, or other factors.'; ?>
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s13_notification') : 'When we make changes:'; ?>
                </p>

                <ul>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s13_minor_title') : 'Minor Changes'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s13_minor_desc') : 'We will update the "Last Updated" date at the top of this policy and increment the version number.'; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('legal.privacy_s13_material_title') : 'Material Changes'; ?></strong> &mdash;
                        <?php echo function_exists('__') ? __('legal.privacy_s13_material_desc') : 'For significant changes that affect how we collect, use, or share your personal data, we will provide prominent notice through one or more of the following methods: a banner on the Service, an email to registered account holders, or a notification in your account dashboard.'; ?>
                    </li>
                </ul>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s13_continued') : 'Your continued use of the Service after the effective date of any updated Privacy Policy constitutes your acceptance of the changes. If you do not agree with the updated policy, you should stop using the Service and may request deletion of your account and personal data.'; ?>
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s13_archive') : 'Previous versions of this Privacy Policy are available upon request by contacting us at'; ?>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Section 14: Contact & Data Protection Officer                           -->
<!-- ====================================================================== -->
<section class="py-4 mb-5" aria-labelledby="privacy-s14">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="privacy-s14" class="h4 fw-bold mb-3">
                    14. <?php echo function_exists('__') ? __('legal.privacy_s14_title') : 'Contact &amp; Data Protection Officer'; ?>
                </h2>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_intro') : 'If you have questions, concerns, or requests regarding this Privacy Policy or our data practices, you can reach us through the following channels:'; ?>
                </p>

                <div class="card bg-body-tertiary mb-4">
                    <div class="card-body">
                        <h3 class="h6 fw-bold mb-2">
                            <?php echo function_exists('__') ? __('legal.privacy_s14_company_heading') : 'Data Controller'; ?>
                        </h3>
                        <p class="mb-1">
                            <strong><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            (<?php echo function_exists('__') ? __('legal.privacy_trading_as') : 'Trading as'; ?> <?php echo htmlspecialchars($companyTrading, ENT_QUOTES, 'UTF-8'); ?>)
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-envelope me-2" aria-hidden="true"></i>
                            <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-globe me-2" aria-hidden="true"></i>
                            <a href="https://go2my.link/contact">go2my.link/contact</a>
                        </p>
                    </div>
                </div>

                <h3 class="h5 mt-4 mb-2">
                    14.1 <?php echo function_exists('__') ? __('legal.privacy_s14_dpo_title') : 'Data Protection Officer'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_dpo_desc') : 'For data protection enquiries specifically, including exercising your rights under GDPR, CCPA, or LGPD, please contact our Data Protection Officer:'; ?>
                </p>
                <p>
                    <i class="fas fa-envelope me-2" aria-hidden="true"></i>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>?subject=Data%20Protection%20Enquiry">
                        <?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_dpo_subject') : '(subject line: "Data Protection Enquiry")'; ?>
                </p>

                <h3 class="h5 mt-4 mb-2">
                    14.2 <?php echo function_exists('__') ? __('legal.privacy_s14_complaints_title') : 'Complaints'; ?>
                </h3>
                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_complaints_intro') : 'If you are not satisfied with our response to your enquiry or believe we are processing your personal data unlawfully, you have the right to lodge a complaint with a supervisory authority.'; ?>
                </p>

                <div class="card bg-body-tertiary mb-4">
                    <div class="card-body">
                        <h3 class="h6 fw-bold mb-2">
                            <?php echo function_exists('__') ? __('legal.privacy_s14_ico_heading') : 'UK Information Commissioner\'s Office (ICO)'; ?>
                        </h3>
                        <p class="mb-1">
                            <i class="fas fa-globe me-2" aria-hidden="true"></i>
                            <a href="https://ico.org.uk/make-a-complaint/" rel="noopener noreferrer" target="_blank">ico.org.uk/make-a-complaint</a>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-phone me-2" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('legal.privacy_s14_ico_phone') : '0303 123 1113'; ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-2" aria-hidden="true"></i>
                            <a href="mailto:icocasework@ico.org.uk">icocasework@ico.org.uk</a>
                        </p>
                    </div>
                </div>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_complaints_eu') : 'If you are located in the EU, you may also contact your local Data Protection Authority. A list of EU DPAs is available at'; ?>
                    <a href="https://edpb.europa.eu/about-edpb/about-edpb/members_en" rel="noopener noreferrer" target="_blank">edpb.europa.eu</a>.
                </p>

                <p>
                    <?php echo function_exists('__') ? __('legal.privacy_s14_encourage') : 'We encourage you to contact us first so we can try to resolve your concern directly.'; ?>
                </p>

                <!-- Back to Legal Hub -->
                <hr class="my-4">
                <div class="text-center">
                    <a href="/legal/terms" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-file-contract me-1" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.terms_link') : 'Terms of Use'; ?>
                    </a>
                    <a href="/legal/cookies" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-cookie-bite me-1" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.cookies_link') : 'Cookie Policy'; ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope me-1" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('legal.contact_cta') : 'Contact Us'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
