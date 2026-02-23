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
 * 🍪 Go2My.Link — Cookie Consent Banner Include
 * ============================================================================
 *
 * Bootstrap fixed-bottom banner with customise modal. Only renders if:
 *   1. compliance.cookie_consent_enabled = true
 *   2. User has NOT given valid consent for the current consent version
 *
 * Dependencies: cookie_consent.php, settings.php, security.php, i18n.php
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 References:
 *     - WCAG Dialog: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
 *     - Bootstrap Modal: https://getbootstrap.com/docs/5.3/components/modal/
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔍 Determine Whether to Show Banner
// ============================================================================
$showBanner = false;

if (function_exists('getSetting') && function_exists('g2ml_hasValidConsent'))
{
    $consentEnabled = getSetting('compliance.cookie_consent_enabled', true);

    if ($consentEnabled && !g2ml_hasValidConsent())
    {
        $showBanner = true;
    }
}

if (!$showBanner)
{
    return; // Don't render anything
}

if (function_exists('g2ml_generateCSRFToken')) {
    $csrfToken = g2ml_generateCSRFToken('cookie_consent');
} else {
    $csrfToken = '';
}
?>

<!-- ====================================================================== -->
<!-- 🍪 Cookie Consent Banner                                               -->
<!-- ====================================================================== -->
<div id="g2ml-cookie-banner"
     class="fixed-bottom bg-body border-top shadow-lg p-3"
     role="region"
     aria-labelledby="cookie-banner-title"
     aria-describedby="cookie-banner-desc"
     style="z-index:1060;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-2 mb-lg-0">
                <h2 id="cookie-banner-title" class="h6 mb-1">
                    <i class="fas fa-cookie-bite" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('cookie.banner_title'); } else { echo 'We Use Cookies'; } ?>
                </h2>
                <p id="cookie-banner-desc" class="small text-body-secondary mb-0">
                    <?php if (function_exists('__')) { echo __('cookie.banner_message'); } else { echo 'We use cookies to improve your experience. Essential cookies are required for the site to function. You can choose which optional cookies to allow.'; } ?>
                    <a href="/legal/cookies" class="text-decoration-underline">
                        <?php if (function_exists('__')) { echo __('cookie.learn_more'); } else { echo 'Learn more'; } ?>
                    </a>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                    <button type="button" class="btn btn-primary btn-sm" id="g2ml-cookie-accept-all">
                        <?php if (function_exists('__')) { echo __('cookie.accept_all'); } else { echo 'Accept All'; } ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="g2ml-cookie-reject">
                        <?php if (function_exists('__')) { echo __('cookie.reject_optional'); } else { echo 'Essential Only'; } ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#g2ml-cookie-modal">
                        <?php if (function_exists('__')) { echo __('cookie.customise'); } else { echo 'Customise'; } ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="g2ml-consent-csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
</div>

<!-- ====================================================================== -->
<!-- 🍪 Cookie Preferences Modal                                            -->
<!-- ====================================================================== -->
<div class="modal fade" id="g2ml-cookie-modal" tabindex="-1"
     aria-labelledby="cookie-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="cookie-modal-title">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('cookie.preferences_title'); } else { echo 'Cookie Preferences'; } ?>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?php if (function_exists('__')) { echo __('common.close'); } else { echo 'Close'; } ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-body-secondary small mb-3">
                    <?php if (function_exists('__')) { echo __('cookie.preferences_desc'); } else { echo 'Choose which categories of cookies you want to allow. Essential cookies cannot be disabled as they are required for the site to function.'; } ?>
                </p>

                <!-- Essential (always on) -->
                <div class="form-check form-switch mb-3 pb-3 border-bottom">
                    <input class="form-check-input" type="checkbox" id="g2ml-consent-essential"
                           checked disabled aria-describedby="essential-desc">
                    <label class="form-check-label fw-semibold" for="g2ml-consent-essential">
                        <?php if (function_exists('__')) { echo __('cookie.essential'); } else { echo 'Essential'; } ?>
                    </label>
                    <small id="essential-desc" class="d-block text-body-secondary">
                        <?php if (function_exists('__')) { echo __('cookie.essential_desc'); } else { echo 'Required for the site to function. Includes session cookies and CSRF protection.'; } ?>
                    </small>
                </div>

                <!-- Analytics -->
                <div class="form-check form-switch mb-3 pb-3 border-bottom">
                    <input class="form-check-input g2ml-consent-toggle" type="checkbox" id="g2ml-consent-analytics"
                           data-consent-type="analytics" aria-describedby="analytics-desc">
                    <label class="form-check-label fw-semibold" for="g2ml-consent-analytics">
                        <?php if (function_exists('__')) { echo __('cookie.analytics'); } else { echo 'Analytics'; } ?>
                    </label>
                    <small id="analytics-desc" class="d-block text-body-secondary">
                        <?php if (function_exists('__')) { echo __('cookie.analytics_desc'); } else { echo 'Help us understand how visitors use the site so we can improve it.'; } ?>
                    </small>
                </div>

                <!-- Functional -->
                <div class="form-check form-switch mb-3 pb-3 border-bottom">
                    <input class="form-check-input g2ml-consent-toggle" type="checkbox" id="g2ml-consent-functional"
                           data-consent-type="functional" aria-describedby="functional-desc">
                    <label class="form-check-label fw-semibold" for="g2ml-consent-functional">
                        <?php if (function_exists('__')) { echo __('cookie.functional'); } else { echo 'Functional'; } ?>
                    </label>
                    <small id="functional-desc" class="d-block text-body-secondary">
                        <?php if (function_exists('__')) { echo __('cookie.functional_desc'); } else { echo 'Enable enhanced features like theme preferences and language settings.'; } ?>
                    </small>
                </div>

                <!-- Marketing -->
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input g2ml-consent-toggle" type="checkbox" id="g2ml-consent-marketing"
                           data-consent-type="marketing" aria-describedby="marketing-desc">
                    <label class="form-check-label fw-semibold" for="g2ml-consent-marketing">
                        <?php if (function_exists('__')) { echo __('cookie.marketing'); } else { echo 'Marketing'; } ?>
                    </label>
                    <small id="marketing-desc" class="d-block text-body-secondary">
                        <?php if (function_exists('__')) { echo __('cookie.marketing_desc'); } else { echo 'Used to deliver relevant advertising and track campaign effectiveness.'; } ?>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?php if (function_exists('__')) { echo __('common.cancel'); } else { echo 'Cancel'; } ?>
                </button>
                <button type="button" class="btn btn-primary" id="g2ml-cookie-save-preferences">
                    <?php if (function_exists('__')) { echo __('cookie.save_preferences'); } else { echo 'Save Preferences'; } ?>
                </button>
            </div>
        </div>
    </div>
</div>
