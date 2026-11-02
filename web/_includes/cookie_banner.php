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
 * Where it is printed: header.php includes this file straight after the
 * skip link, so a keyboard or screen-reader user reaches the banner before
 * the navigation. It still APPEARS at the bottom of the screen (fixed-bottom)
 * wherever it sits in the page source. It used to be printed after the
 * footer, which meant tabbing through the whole page to find it (#287).
 *
 * Headings: the banner has NO heading. It is a labelled region, and its
 * title is bold text, not an h2. Being now above the page's own h1, an h2
 * here would have put a level-2 heading before the level-1 one and broken
 * the page outline. The preferences dialog title is an h2: the dialog is
 * hidden until it is opened, and when it is open it is a dialog on top of the
 * page, so a screen reader meets it as its own small outline.
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
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

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

<?php // 🍪 Cookie Consent Banner ?>
<?php // z-index 1040 is above anything Bootstrap pins with fixed-top or sticky-top (1030 and 1020) but below its modal backdrop (1050) and modal (1055). At 1060 the banner sat on top of the preferences dialog, so it could cover part of it. ?>
<div id="g2ml-cookie-banner"
     class="fixed-bottom bg-body border-top shadow-lg px-2 px-lg-3 py-2 py-lg-3"
     role="region"
     aria-labelledby="cookie-banner-title"
     aria-describedby="cookie-banner-desc"
     style="z-index:1040;">
    <div class="container">
        <div class="row align-items-center g-2">
            <div class="col-lg-8">
                <?php // Below the large breakpoint the title and icon are hidden from sight (style.css, section 14) to save a line; the banner is still named by the title for screen readers. ?>
                <p class="small mb-0">
                    <span class="g2ml-cookie-heading d-lg-block mb-lg-1">
                        <i class="fas fa-cookie-bite" aria-hidden="true"></i>
                        <strong id="cookie-banner-title" class="me-1"><?php if (function_exists('__')) { echo __('cookie.banner_title'); } else { echo 'We Use Cookies'; } ?></strong>
                    </span>
                    <span id="cookie-banner-desc" class="text-body-secondary"><?php if (function_exists('__')) { echo __('cookie.banner_message'); } else { echo 'We use cookies to improve your experience. Essential cookies are required for the site to function. You can choose which optional cookies to allow.'; } ?></span>
                    <a href="<?php echo g2ml_mainSiteURL('/legal/cookies'); ?>" class="text-decoration-underline"><?php if (function_exists('__')) { echo __('cookie.learn_more'); } else { echo 'Learn more'; } ?><span class="visually-hidden"> <?php if (function_exists('__')) { echo __('cookie.learn_more_suffix'); } else { echo 'about our cookie policy'; } ?></span></a>
                </p>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <button type="button" class="btn btn-primary btn-sm flex-fill flex-lg-grow-0" id="g2ml-cookie-accept-all">
                        <?php if (function_exists('__')) { echo __('cookie.accept_all'); } else { echo 'Accept All'; } ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill flex-lg-grow-0" id="g2ml-cookie-reject">
                        <?php if (function_exists('__')) { echo __('cookie.reject_optional'); } else { echo 'Essential Only'; } ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill flex-lg-grow-0"
                            data-bs-toggle="modal" data-bs-target="#g2ml-cookie-modal">
                        <?php if (function_exists('__')) { echo __('cookie.customise'); } else { echo 'Customise'; } ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="g2ml-consent-csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
</div>

<?php // 🍪 Cookie Preferences Modal ?>
<div class="modal fade" id="g2ml-cookie-modal" tabindex="-1"
     aria-labelledby="cookie-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="cookie-modal-title">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('cookie.preferences_title'); } else { echo 'Cookie Preferences'; } ?>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?php if (function_exists('__')) { echo __('common.close'); } else { echo 'Close'; } ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-body-secondary small mb-3">
                    <?php if (function_exists('__')) { echo __('cookie.preferences_desc'); } else { echo 'Choose which categories of cookies you want to allow. Essential cookies cannot be disabled as they are required for the site to function.'; } ?>
                </p>

                <?php // Essential (always on) ?>
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

                <?php // Analytics ?>
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

                <?php // Functional ?>
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

                <?php // Marketing ?>
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
