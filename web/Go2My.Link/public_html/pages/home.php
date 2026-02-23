<?php
/**
 * ============================================================================
 * 🏠 Go2My.Link — Homepage (Component A)
 * ============================================================================
 *
 * Public homepage for go2my.link. Features:
 *   - Hero section with site name and tagline
 *   - URL shortening form with AJAX submission (no-JS fallback)
 *   - Conditional bot protection (Turnstile/reCAPTCHA)
 *   - Result display with copy-to-clipboard
 *   - Feature highlights section
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 2 (functional form added Phase 3)
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('home.title') : 'Home';
$pageDesc  = getSetting('site.tagline', 'Shorten. Track. Manage.');

// 🤖 Determine bot protection configuration
$turnstileSiteKey = getSetting('captcha.turnstile_site_key', '');
$recaptchaSiteKey = getSetting('captcha.recaptcha_site_key', '');
$captchaType      = 'none';

if ($turnstileSiteKey !== '')
{
    $captchaType = 'turnstile';
}
elseif ($recaptchaSiteKey !== '')
{
    $captchaType = 'recaptcha';
}

// ♿ Check for no-JS fallback results (from API redirect)
$createdURL = isset($_GET['created']) ? g2ml_sanitiseInput($_GET['created']) : '';
$errorMsg   = isset($_GET['error']) ? g2ml_sanitiseInput($_GET['error']) : '';
?>

<!-- ====================================================================== -->
<!-- 🏠 Hero Section                                                        -->
<!-- ====================================================================== -->
<section class="py-5 text-center" aria-labelledby="hero-heading">
    <div class="container">
        <h1 id="hero-heading" class="display-3 fw-bold">
            <?php echo g2ml_sanitiseOutput(getSetting('site.name', 'Go2My.Link')); ?>
        </h1>
        <p class="lead text-body-secondary mb-4">
            <?php echo g2ml_sanitiseOutput(getSetting('site.tagline', 'Shorten. Track. Manage.')); ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- 🔗 URL Shortening Form                                                 -->
<!-- ====================================================================== -->
<section class="pb-5" aria-labelledby="shorten-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="shorten-heading" class="h5 mb-3">
                            <i class="fas fa-link" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('home.shorten_url') : 'Shorten a URL'; ?>
                        </h2>

                        <?php
                        // ♿ No-JS fallback: display server-side results from API redirect
                        if ($createdURL !== ''):
                        ?>
                        <div class="alert alert-success" role="status">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong><?php echo function_exists('__') ? __('home.result_success') : 'Your short URL is ready!'; ?></strong>
                                    <div class="mt-1">
                                        <a href="<?php echo g2ml_sanitiseOutput($createdURL); ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="fw-bold fs-5">
                                            <?php echo g2ml_sanitiseOutput($createdURL); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($errorMsg !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($errorMsg); ?>
                        </div>
                        <?php endif; ?>

                        <!-- 📝 URL Shortening Form -->
                        <form action="/api/create/" method="POST"
                              id="shorten-form"
                              data-captcha-type="<?php echo g2ml_sanitiseOutput($captchaType); ?>">

                            <?php echo g2ml_csrfField('shorten_url'); ?>

                            <?php
                            echo formField([
                                'id'           => 'destination-url',
                                'name'         => 'longURL',
                                'label'        => function_exists('__') ? __('home.url_label') : 'Enter your long URL',
                                'type'         => 'url',
                                'placeholder'  => 'https://example.com/my-very-long-url',
                                'required'     => true,
                                'helpText'     => function_exists('__') ? __('home.url_help') : 'Paste the URL you want to shorten',
                                'autocomplete' => 'url',
                            ]);
                            ?>

                            <?php if ($captchaType === 'turnstile'): ?>
                            <!-- ☁️ Cloudflare Turnstile Widget -->
                            <div class="cf-turnstile mb-3"
                                 data-sitekey="<?php echo g2ml_sanitiseOutput($turnstileSiteKey); ?>"
                                 data-theme="auto"
                                 data-callback="onCaptchaSuccess"
                                 data-expired-callback="onCaptchaExpired">
                            </div>
                            <?php elseif ($captchaType === 'recaptcha'): ?>
                            <!-- 🤖 Google reCAPTCHA v2 Widget -->
                            <div class="g-recaptcha mb-3"
                                 data-sitekey="<?php echo g2ml_sanitiseOutput($recaptchaSiteKey); ?>"
                                 data-callback="onCaptchaSuccess"
                                 data-expired-callback="onCaptchaExpired">
                            </div>
                            <?php endif; ?>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg"
                                        id="shorten-submit">
                                    <i class="fas fa-magic" aria-hidden="true"></i>
                                    <?php echo function_exists('__') ? __('home.shorten_button') : 'Shorten URL'; ?>
                                </button>
                            </div>
                        </form>

                        <!-- ✅ AJAX Result Area (hidden until URL created via JS) -->
                        <div id="shorten-result" class="mt-4 d-none"
                             role="region"
                             aria-label="<?php echo function_exists('__') ? __('home.result_label') : 'Shortened URL result'; ?>">
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <strong><?php echo function_exists('__') ? __('home.result_success') : 'Your short URL is ready!'; ?></strong>
                                        <div class="mt-1">
                                            <a id="result-url" href="#" target="_blank" rel="noopener noreferrer"
                                               class="fw-bold fs-5"></a>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-success"
                                            id="copy-url-btn"
                                            aria-label="<?php echo function_exists('__') ? __('home.copy_to_clipboard') : 'Copy short URL to clipboard'; ?>">
                                        <i class="fas fa-copy" aria-hidden="true"></i>
                                        <span id="copy-btn-text"><?php echo function_exists('__') ? __('home.copy_button') : 'Copy'; ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ❌ AJAX Error Area (hidden until error via JS) -->
                        <div id="shorten-error" class="mt-4 d-none" role="alert"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================================== -->
<!-- 🚀 Feature Highlights                                                  -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="features-heading">
    <div class="container">
        <h2 id="features-heading" class="visually-hidden">
            <?php echo function_exists('__') ? __('home.features_heading') : 'Features'; ?>
        </h2>
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-bolt fa-3x text-primary mb-3" aria-hidden="true"></i>
                    <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_fast') : 'Fast Redirects'; ?></h3>
                    <p class="text-body-secondary"><?php echo function_exists('__') ? __('home.feature_fast_desc') : 'Lightning-fast URL resolution with alias chain support.'; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-chart-bar fa-3x text-success mb-3" aria-hidden="true"></i>
                    <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_analytics') : 'Detailed Analytics'; ?></h3>
                    <p class="text-body-secondary"><?php echo function_exists('__') ? __('home.feature_analytics_desc') : 'Track clicks, geographic data, devices, and more.'; ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-shield-alt fa-3x text-danger mb-3" aria-hidden="true"></i>
                    <h3 class="h5"><?php echo function_exists('__') ? __('home.feature_secure') : 'Enterprise Security'; ?></h3>
                    <p class="text-body-secondary"><?php echo function_exists('__') ? __('home.feature_secure_desc') : 'AES-256 encryption, 2FA, SSO, and role-based access.'; ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// ============================================================================
// 📦 Load CAPTCHA JavaScript SDK (conditional)
// ============================================================================
if ($captchaType === 'turnstile'):
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php elseif ($captchaType === 'recaptcha'): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
