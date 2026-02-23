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
 * @since      Phase 2 (dark mode Phase 3, CSRF meta Phase 6, PWA Phase 6)
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
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
if (isset($pageTitle)) {
    $pageTitle = $pageTitle . ' — ' . $siteName;
} else {
    $pageTitle = $siteName;
}
if (!isset($pageDesc)) {
    if (function_exists('getSetting')) {
        $pageDesc = getSetting('site.tagline', 'Shorten. Track. Manage.');
    } else {
        $pageDesc = 'Shorten. Track. Manage.';
    }
}
$bodyClass = $bodyClass ?? '';
if (function_exists('getLocale')) {
    $locale = getLocale();
} else {
    $locale = 'en-GB';
}
if (function_exists('getTextDirection')) {
    $textDir = getTextDirection();
} else {
    $textDir = 'ltr';
}
if (defined('G2ML_COMPONENT_DOMAIN')) {
    $componentDomain = G2ML_COMPONENT_DOMAIN;
} else {
    $componentDomain = 'go2my.link';
}

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

if (($themePref === 'auto')) {
    $initialTheme = 'light';
} else {
    $initialTheme = $themePref;
}
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
    <?php if (function_exists('g2ml_generateCSRFToken')) { ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(g2ml_generateCSRFToken('ajax'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>

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
    <!-- PWA Manifest & Apple Touch Icon                                    -->
    <!-- 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/Manifest -->
    <!-- ================================================================== -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1E88E5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192x192.png">

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
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">';
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

    <!-- 📱 PWA: Service Worker Registration -->
    <!-- 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerContainer/register -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').catch(function() {});
            });
        }
    </script>
</head>
<?php
if (defined('G2ML_COMPONENT')) {
    $bodyComponent = G2ML_COMPONENT;
} else {
    $bodyComponent = 'unknown';
}
?>
<body class="<?php echo htmlspecialchars(trim('g2ml-' . $bodyComponent . ' ' . $bodyClass), ENT_QUOTES, 'UTF-8'); ?>">

<?php
// Skip to content link (first focusable element)
echo skipToContent();
?>
