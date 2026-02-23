<?php
/**
 * ============================================================================
 * 📄 Go2My.Link — HTML Header Include
 * ============================================================================
 *
 * Outputs the HTML5 doctype, <head>, and opening <body> tag.
 * Includes Bootstrap 5 + Font Awesome 6 via CDN with local fallback.
 *
 * Expected variables (set by calling page or page_init.php):
 *   $pageTitle   — (string) Page title (appended to site name)
 *   $pageDesc    — (string) Meta description
 *   $bodyClass   — (string) Additional body CSS classes
 *
 * Dependencies: page_init.php (must be loaded first)
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 2 (dark mode Phase 3, CSRF meta Phase 6)
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
// 📋 Default Variable Values
// ============================================================================
$siteName  = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . $siteName : $siteName;
$pageDesc  = $pageDesc ?? (function_exists('getSetting') ? getSetting('site.tagline', 'Shorten. Track. Manage.') : 'Shorten. Track. Manage.');
$bodyClass = $bodyClass ?? '';
$locale    = function_exists('getLocale') ? getLocale() : 'en-GB';
$textDir   = function_exists('getTextDirection') ? getTextDirection() : 'ltr';
$componentDomain = defined('G2ML_COMPONENT_DOMAIN') ? G2ML_COMPONENT_DOMAIN : 'go2my.link';

// ============================================================================
// 🎨 Theme Preference (Dark/Light Mode)
// ============================================================================
// Read the theme cookie set by theme.js to prevent Flash of Unstyled Content.
// Valid values: 'auto', 'light', 'dark'. For 'auto', default to 'light' —
// the FOUC-prevention inline script corrects this before paint if system is dark.
//
// 📖 Reference: https://getbootstrap.com/docs/5.3/customize/color-modes/
// ============================================================================
$themePref    = $_COOKIE['g2ml_theme'] ?? 'auto';
$validThemes  = ['auto', 'light', 'dark'];

if (!in_array($themePref, $validThemes, true))
{
    $themePref = 'auto';
}

$initialTheme = ($themePref === 'auto') ? 'light' : $themePref;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
      dir="<?php echo $textDir; ?>"
      data-bs-theme="<?php echo $initialTheme; ?>"
      data-g2ml-theme-pref="<?php echo $themePref; ?>">
<head>
    <!-- ================================================================== -->
    <!-- Meta Tags                                                          -->
    <!-- ================================================================== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">
    <?php if (function_exists('g2ml_generateCSRFToken')): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(g2ml_generateCSRFToken('ajax'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Open Graph / Social Sharing -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="https://<?php echo htmlspecialchars($componentDomain, ENT_QUOTES, 'UTF-8'); ?>">

    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- ================================================================== -->
    <!-- Favicon                                                            -->
    <!-- ================================================================== -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- ================================================================== -->
    <!-- Bootstrap 5.3 CSS (CDN + Local Fallback)                           -->
    <!-- 📖 Reference: https://getbootstrap.com/docs/5.3/getting-started/   -->
    <!-- ================================================================== -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1RETi5cBu9ApG1MKOp/4xLHCRG4PKoV"
          crossorigin="anonymous"
          id="bootstrap-css"
          onerror="var l=document.createElement('link');l.rel='stylesheet';l.href='/_libraries/bootstrap/css/bootstrap.min.css';document.head.appendChild(l);">

    <!-- ================================================================== -->
    <!-- Font Awesome 6 (CDN + Local Fallback)                              -->
    <!-- 📖 Reference: https://fontawesome.com/docs/web/setup/host-yourself -->
    <!-- ================================================================== -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer"
          id="fontawesome-css">

    <!-- ================================================================== -->
    <!-- Custom CSS (Component-specific, loaded if exists)                  -->
    <!-- ================================================================== -->
    <link rel="stylesheet" href="/css/style.css">

    <?php
    // RTL support — load Bootstrap RTL if text direction is RTL
    if ($textDir === 'rtl')
    {
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" crossorigin="anonymous">';
    }
    ?>

    <!-- ================================================================== -->
    <!-- 🎨 Theme: FOUC Prevention (runs before first paint)                -->
    <!-- Reads localStorage and corrects data-bs-theme immediately.         -->
    <!-- 📖 Reference: https://getbootstrap.com/docs/5.3/customize/color-modes/ -->
    <!-- ================================================================== -->
    <script>
        (function(){
            var t = null;
            try { t = localStorage.getItem('g2ml-theme'); } catch(e) {}
            t = t || 'auto';
            if (t === 'auto') {
                t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    <!-- Theme Controller (loaded deferred — full toggle/persistence logic) -->
    <script src="/js/theme.js" defer></script>
</head>
<body class="<?php echo htmlspecialchars(trim('g2ml-' . (defined('G2ML_COMPONENT') ? G2ML_COMPONENT : 'unknown') . ' ' . $bodyClass), ENT_QUOTES, 'UTF-8'); ?>">

<?php
// Skip to content link (first focusable element)
echo skipToContent();
?>
