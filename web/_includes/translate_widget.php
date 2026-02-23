<?php
/**
 * ============================================================================
 * 🌍 Go2My.Link — Interim Google Translate Widget
 * ============================================================================
 *
 * Adds the Google Translate inline widget as an interim solution until
 * formal professional translations are available (Phase 10).
 *
 * This widget is included in footer.php and provides machine translation
 * for languages that don't have formal translations yet.
 *
 * The widget is hidden when the current locale has formal translations
 * (completionPercent >= 80 in tblLanguages).
 *
 * Dependencies: i18n.php (getLocale(), getActiveLanguages())
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - Google Translate Widget: https://cloud.google.com/translate/docs/basic/translating-text
 *     - Privacy: The widget sends page content to Google for translation.
 *       This is noted in the Privacy Policy (Phase 10).
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

// Only show the translate widget if the current language doesn't have
// high-quality formal translations
$currentLocale   = function_exists('getLocale') ? getLocale() : 'en-GB';
$defaultLocale   = $GLOBALS['_g2ml_default_locale'] ?? 'en-GB';

// Don't show widget for the default language (English already complete)
if ($currentLocale === $defaultLocale)
{
    return;
}
?>

<!-- ====================================================================== -->
<!-- Interim Google Translate Widget                                         -->
<!-- This will be removed when formal translations are complete (Phase 10)  -->
<!-- ====================================================================== -->
<div id="g2ml-translate-widget" class="text-center py-2"
     style="background:#f8f9fa;border-top:1px solid #dee2e6;">
    <small class="text-muted">
        <i class="fas fa-language" aria-hidden="true"></i>
        <?php echo function_exists('__') ? __('translate.powered_by') : 'Machine translation powered by'; ?>
    </small>
    <div id="google_translate_element" class="d-inline-block ms-2"></div>
</div>

<script>
    // Google Translate initialisation
    // 📖 Reference: https://cloud.google.com/translate/docs/basic/translating-text
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: '<?php echo htmlspecialchars(explode('-', $defaultLocale)[0], ENT_QUOTES, 'UTF-8'); ?>',
            includedLanguages: 'en,es,fr,de,pt,ar,zh-CN,ja,hi',
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
            autoDisplay: false
        }, 'google_translate_element');
    }
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async defer></script>
