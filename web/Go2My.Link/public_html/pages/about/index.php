<?php
/**
 * ============================================================================
 * Go2My.Link — About Page (Component A)
 * ============================================================================
 *
 * Public about page for go2my.link. Describes the service, mission,
 * and the team behind Go2My.Link.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('about.title') : 'About';
$pageDesc  = function_exists('__') ? __('about.description') : 'Learn about Go2My.Link — the smart URL shortening platform.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="about-heading">
    <div class="container">
        <h1 id="about-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('about.heading') : 'About Go2My.Link'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('about.subtitle') : 'Smarter links for a connected world.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Mission Section                                                         -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="mission-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="mission-heading" class="h3 mb-3">
                    <?php echo function_exists('__') ? __('about.mission_heading') : 'Our Mission'; ?>
                </h2>
                <p class="text-body-secondary">
                    <?php echo function_exists('__') ? __('about.mission_text') : 'Go2My.Link was built to make link management simple, secure, and powerful. Whether you\'re sharing a single URL or managing thousands of links across an organisation, we provide the tools you need to shorten, track, and manage your links with confidence.'; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- What We Offer                                                           -->
<!-- ====================================================================== -->
<section class="py-5 bg-body-tertiary" aria-labelledby="offer-heading">
    <div class="container">
        <h2 id="offer-heading" class="h3 text-center mb-4">
            <?php echo function_exists('__') ? __('about.offer_heading') : 'What We Offer'; ?>
        </h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-link text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('about.offer_shorten') : 'URL Shortening'; ?>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('about.offer_shorten_desc') : 'Create clean, memorable short links from long URLs. Use our default g2my.link domain or bring your own custom domain.'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-chart-bar text-success" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('about.offer_analytics') : 'Detailed Analytics'; ?>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('about.offer_analytics_desc') : 'Understand your audience with click tracking, geographic data, device breakdowns, and referrer insights.'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-building text-info" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('about.offer_orgs') : 'Organisation Management'; ?>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('about.offer_orgs_desc') : 'Manage links across teams with role-based access, custom domains per organisation, and shared link libraries.'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5">
                            <i class="fas fa-shield-alt text-danger" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('about.offer_security') : 'Enterprise Security'; ?>
                        </h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('about.offer_security_desc') : 'AES-256 encryption at rest, two-factor authentication, SSO integration, and comprehensive audit logging.'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Team Section                                                            -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="team-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 id="team-heading" class="h3 mb-3">
                    <?php echo function_exists('__') ? __('about.team_heading') : 'Built by MWBM Partners'; ?>
                </h2>
                <p class="text-body-secondary">
                    <?php echo function_exists('__') ? __('about.team_text') : 'Go2My.Link is developed and maintained by MWBM Partners Ltd (trading as MWservices), a technology company focused on building practical, reliable web tools. We believe in clean architecture, strong security, and accessible design.'; ?>
                </p>
                <a href="/contact" class="btn btn-primary mt-3">
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('about.contact_cta') : 'Get in Touch'; ?>
                </a>
            </div>
        </div>
    </div>
</section>
