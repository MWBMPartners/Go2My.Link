<?php
/**
 * ============================================================================
 * 🌐 Go2My.Link — Language Switcher Dropdown
 * ============================================================================
 *
 * Renders a Bootstrap dropdown for switching between active languages.
 * Designed to be included within the navbar (nav.php).
 *
 * Persists the language choice via cookie and session (handled by g2ml_setLocale()).
 *
 * Dependencies: i18n.php (getActiveLanguages(), getLocale())
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - Bootstrap Dropdown: https://getbootstrap.com/docs/5.3/components/dropdowns/
 *     - WCAG Language:      https://www.w3.org/TR/WCAG21/#language-of-page
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

$activeLanguages = function_exists('getActiveLanguages') ? getActiveLanguages() : [];
$currentLocale   = function_exists('getLocale') ? getLocale() : 'en-GB';

// Only show the switcher if there are multiple active languages
if (count($activeLanguages) > 1) {
    $currentLang = $activeLanguages[$currentLocale] ?? ['nativeName' => 'English', 'name' => 'English (UK)'];
?>
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="languageSwitcher"
       role="button" data-bs-toggle="dropdown" aria-expanded="false"
       aria-label="<?php echo function_exists('__') ? __('nav.language') : 'Language'; ?>">
        <i class="fas fa-globe" aria-hidden="true"></i>
        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($currentLang['nativeName'], ENT_QUOTES, 'UTF-8'); ?></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageSwitcher">
        <?php foreach ($activeLanguages as $localeCode => $langInfo) { ?>
            <?php
            // Build the URL with the lang parameter
            $currentURL = $_SERVER['REQUEST_URI'] ?? '/';
            // Remove any existing lang= parameter
            $cleanURL   = preg_replace('/([?&])lang=[^&]*(&|$)/', '$1', $currentURL);
            $cleanURL   = rtrim($cleanURL, '?&');
            $separator   = (strpos($cleanURL, '?') !== false) ? '&' : '?';
            $switchURL   = $cleanURL . $separator . 'lang=' . urlencode($localeCode);
            $isCurrent   = ($localeCode === $currentLocale);
            ?>
            <li>
                <a class="dropdown-item<?php echo $isCurrent ? ' active' : ''; ?>"
                   href="<?php echo htmlspecialchars($switchURL, ENT_QUOTES, 'UTF-8'); ?>"
                   <?php echo $isCurrent ? 'aria-current="true"' : ''; ?>
                   lang="<?php echo htmlspecialchars($localeCode, ENT_QUOTES, 'UTF-8'); ?>"
                   dir="<?php echo htmlspecialchars($langInfo['direction'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($langInfo['nativeName'], ENT_QUOTES, 'UTF-8'); ?>
                    <small class="text-muted ms-1">(<?php echo htmlspecialchars($langInfo['name'], ENT_QUOTES, 'UTF-8'); ?>)</small>
                </a>
            </li>
        <?php } ?>
    </ul>
</li>
<?php } ?>
