<?php
/**
 * ============================================================================
 * 🏠 GoToMyLink — Homepage (Component A)
 * ============================================================================
 *
 * Public homepage for go2my.link. This is a temporary page for Phase 2
 * framework verification. Will be replaced with the full homepage in Phase 3.
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @version    0.3.0
 * @since      Phase 2
 * ============================================================================
 */

$pageTitle = getSetting('site.name', 'GoToMyLink');
$pageDesc  = getSetting('site.tagline', 'Shorten. Track. Manage.');
?>

<div class="container py-5">
    <!-- Hero Section -->
    <div class="text-center mb-5">
        <h1 class="display-3 fw-bold">
            <?php echo htmlspecialchars(getSetting('site.name', 'GoToMyLink'), ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="lead text-muted">
            <?php echo htmlspecialchars(getSetting('site.tagline', 'Shorten. Track. Manage.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>

    <!-- URL Shortening Form (placeholder — Phase 3 implements AJAX submission) -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">
                        <i class="fas fa-link" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('home.shorten_url') : 'Shorten a URL'; ?>
                    </h2>
                    <form action="#" method="POST" class="row g-3" id="shorten-form">
                        <?php echo g2ml_csrfField('shorten_url'); ?>
                        <?php
                        echo formField([
                            'id'          => 'destination-url',
                            'name'        => 'url',
                            'label'       => function_exists('__') ? __('home.url_label') : 'Enter your long URL',
                            'type'        => 'url',
                            'placeholder' => 'https://example.com/my-very-long-url',
                            'required'    => true,
                            'helpText'    => function_exists('__') ? __('home.url_help') : 'Paste the URL you want to shorten',
                            'autocomplete' => 'url',
                        ]);
                        ?>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" disabled>
                                <i class="fas fa-magic" aria-hidden="true"></i>
                                <?php echo function_exists('__') ? __('home.shorten_button') : 'Shorten URL'; ?>
                            </button>
                            <small class="text-muted text-center mt-2">
                                (URL creation will be enabled in Phase 3)
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Preview -->
    <div class="row text-center g-4">
        <div class="col-md-4">
            <div class="p-3">
                <i class="fas fa-bolt fa-3x text-primary mb-3" aria-hidden="true"></i>
                <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_fast') : 'Fast Redirects'; ?></h3>
                <p class="text-muted"><?php echo function_exists('__') ? __('home.feature_fast_desc') : 'Lightning-fast URL resolution with alias chain support.'; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3">
                <i class="fas fa-chart-bar fa-3x text-success mb-3" aria-hidden="true"></i>
                <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_analytics') : 'Detailed Analytics'; ?></h3>
                <p class="text-muted"><?php echo function_exists('__') ? __('home.feature_analytics_desc') : 'Track clicks, geographic data, devices, and more.'; ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3">
                <i class="fas fa-shield-alt fa-3x text-danger mb-3" aria-hidden="true"></i>
                <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_secure') : 'Enterprise Security'; ?></h3>
                <p class="text-muted"><?php echo function_exists('__') ? __('home.feature_secure_desc') : 'AES-256 encryption, 2FA, SSO, and role-based access.'; ?></p>
            </div>
        </div>
    </div>
</div>
