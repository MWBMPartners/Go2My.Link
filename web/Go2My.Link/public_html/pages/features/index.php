<?php
/**
 * ============================================================================
 * Go2My.Link — Features Page (Component A)
 * ============================================================================
 *
 * Public features page for go2my.link. Displays the platform's capabilities
 * with icons and descriptions.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('features.title') : 'Features';
$pageDesc  = function_exists('__') ? __('features.description') : 'Discover the powerful features of Go2My.Link.';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="features-heading">
    <div class="container">
        <h1 id="features-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('features.heading') : 'Features'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('features.subtitle') : 'Everything you need to shorten, track, and manage your links.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Feature Grid                                                            -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="feature-grid-heading">
    <div class="container">
        <h2 id="feature-grid-heading" class="visually-hidden">
            <?php echo function_exists('__') ? __('features.grid_heading') : 'Feature List'; ?>
        </h2>
        <div class="row g-4">

            <!-- URL Shortening -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                            <i class="fas fa-link" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.shorten_title') : 'URL Shortening'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.shorten_desc') : 'Create short, memorable links from any URL. Supports custom short codes for registered users.'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                            <i class="fas fa-chart-bar" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.analytics_title') : 'Detailed Analytics'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.analytics_desc') : 'Track clicks, geographic data, device types, referrers, and more with real-time dashboards.'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Custom Domains -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto mb-3">
                            <i class="fas fa-globe" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.domains_title') : 'Custom Domains'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.domains_desc') : 'Use your own branded short domain instead of g2my.link. Multiple domains per organisation supported.'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Team Management -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                            <i class="fas fa-users" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.teams_title') : 'Team Management'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.teams_desc') : 'Invite team members with role-based access. Owners, admins, and members each have appropriate permissions.'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- API Access -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-secondary bg-opacity-10 text-secondary mx-auto mb-3">
                            <i class="fas fa-code" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.api_title') : 'REST API'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.api_desc') : 'Integrate URL shortening into your own applications with our RESTful API. Full documentation included.'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Security -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-danger bg-opacity-10 text-danger mx-auto mb-3">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        </div>
                        <h3 class="h5"><?php echo function_exists('__') ? __('features.security_title') : 'Enterprise Security'; ?></h3>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('features.security_desc') : 'AES-256 encryption, two-factor authentication, SSO support, and comprehensive audit trails.'; ?>
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- CTA Section                                                             -->
<!-- ====================================================================== -->
<section class="py-5 bg-body-tertiary text-center" aria-labelledby="features-cta-heading">
    <div class="container">
        <h2 id="features-cta-heading" class="h3 mb-3">
            <?php echo function_exists('__') ? __('features.cta_heading') : 'Ready to get started?'; ?>
        </h2>
        <p class="text-body-secondary mb-4">
            <?php echo function_exists('__') ? __('features.cta_text') : 'Start shortening URLs for free — no account required.'; ?>
        </p>
        <a href="/" class="btn btn-primary btn-lg">
            <i class="fas fa-magic" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('features.cta_button') : 'Shorten a URL'; ?>
        </a>
    </div>
</section>
