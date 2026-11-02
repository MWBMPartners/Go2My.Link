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
 * 📄 Go2My.Link — HTML Header Include
 * ============================================================================
 *
 * Outputs the HTML5 doctype, <head>, and opening <body> tag.
 * Includes Bootstrap 5 + Font Awesome 6 via CDN with local fallback.
 *
 * Expected variables (set by calling page or page_init.php):
 *   $pageTitle     — (string) Page title (the site name is added after it)
 *   $pageTitleFull — (string) Optional: the whole title, shown as written
 *   $pageDesc      — (string) Meta description
 *   $pageRobots    — (string) Optional robots value, default "index, follow"
 *   $pageURLPath   — (string) Optional: the page's own address path, starting
 *                    with "/", when the route alone does not say which page
 *                    is shown (the info page's /info/CODE)
 *   $bodyClass     — (string) Additional body CSS classes
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
// 📋 Default Variable Values
// ============================================================================
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
if (isset($pageTitleFull)) {
    $pageTitle = $pageTitleFull;
} elseif (isset($pageTitle)) {
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
// 🔎 Search and sharing (#282)
// ============================================================================
// Pages that should stay out of search results (sign-in, password reset,
// error pages and so on) set $pageRobots before the header. The dashboard is
// never indexed.
if (!isset($pageRobots)) {
    $pageRobots = 'index, follow';
}
if (function_exists('g2ml_getComponent') && g2ml_getComponent() === 'Admin') {
    $pageRobots = 'noindex, nofollow';
}
// The canonical address and og:url are built from the route the front
// controller actually served ($resolved, from resolveRoute()), not from the
// address typed. So every spelling the router accepts (a trailing slash,
// /index.php?route=about, characters the router strips) names the same
// address, and /home names "/". A page whose route alone does not say what
// is shown (the info page's /info/CODE arrives as route "info" plus a code)
// names its own path in $pageURLPath. When no page was found, og:url falls
// back to the address typed (no canonical is printed then).
$requestPath = '/';
if (isset($resolved) && is_array($resolved) && isset($resolved['file']) && is_array($resolved['segments'] ?? null)) {
    if (count($resolved['segments']) > 0) {
        $requestPath = '/' . implode('/', $resolved['segments']);
    }
} elseif (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
    $parsedRequestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($parsedRequestPath) && $parsedRequestPath !== '') {
        $requestPath = $parsedRequestPath;
    }
}
if (isset($pageURLPath) && is_string($pageURLPath) && strncmp($pageURLPath, '/', 1) === 0) {
    $requestPath = $pageURLPath;
}
if (strncmp($requestPath, '/', 1) !== 0) {
    $requestPath = '/' . $requestPath;
}
if ($requestPath === '/home') {
    $requestPath = '/';
}
$pageURL = 'https://' . $componentDomain . $requestPath;
// Only a page that answered normally and may be indexed names a canonical
// address; on a "noindex" page it would send search engines mixed signals.
$printCanonical = false;
if (http_response_code() === 200 && strpos($pageRobots, 'noindex') === false) {
    $printCanonical = true;
}
$ogImageURL     = 'https://go2my.link/img/og-image.png';
$ogLocale       = str_replace('-', '_', $locale);

$faviconIcoHref = '/favicon.ico';
$faviconPngHref = '/favicon.png';
$hasFaviconPng = false;
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $hasFaviconPng = file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'favicon.png');
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
    <meta name="robots" content="<?php echo htmlspecialchars($pageRobots, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (function_exists('g2ml_generateCSRFToken')) { ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(g2ml_generateCSRFToken('ajax'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>

    <!-- Open Graph / Social Sharing -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($pageURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="<?php echo htmlspecialchars($ogLocale, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImageURL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if ($printCanonical) { ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($pageURL, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>

    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- ================================================================== -->
    <!-- Favicon                                                            -->
    <!-- ================================================================== -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($faviconIcoHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($hasFaviconPng) { ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($faviconPngHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php } ?>
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($faviconIcoHref, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ================================================================== -->
    <!-- PWA Manifest & Apple Touch Icon                                    -->
    <!-- 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/Manifest -->
    <!-- ================================================================== -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1E88E5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">

    <!-- ================================================================== -->
    <!-- Bootstrap 5.3 CSS (CDN + Local Fallback)                           -->
    <!-- 📖 Reference: https://getbootstrap.com/docs/5.3/getting-started/   -->
    <!-- ================================================================== -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
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
        (function()
        {
            var t = null;
            try
            {
                t = localStorage.getItem('g2ml-theme');
            }
            catch (e)
            {
            }
            if (t === null || t === '')
            {
                t = 'auto';
            }
            if (t === 'auto')
            {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                {
                    t = 'dark';
                }
                else
                {
                    t = 'light';
                }
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

// 🍪 Cookie consent banner. It is printed here, straight after the skip link
// and before the navigation, so a keyboard or screen-reader user reaches it
// first instead of after the whole page and the footer (#287). It still
// appears at the bottom of the screen because it is positioned fixed-bottom.
// The file prints nothing when consent has already been given.
if (defined('G2ML_INCLUDES') && file_exists(G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'cookie_banner.php'))
{
    require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'cookie_banner.php';
}
?>
