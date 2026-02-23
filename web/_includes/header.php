<?php
/**
 * ============================================================================
 * 📄 GoToMyLink — HTML Header Include
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
 * @package    GoToMyLink
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
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
$siteName  = function_exists('getSetting') ? getSetting('site.name', 'GoToMyLink') : 'GoToMyLink';
$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . $siteName : $siteName;
$pageDesc  = $pageDesc ?? (function_exists('getSetting') ? getSetting('site.tagline', 'Shorten. Track. Manage.') : 'Shorten. Track. Manage.');
$bodyClass = $bodyClass ?? '';
$locale    = function_exists('getLocale') ? getLocale() : 'en-GB';
$textDir   = function_exists('getTextDirection') ? getTextDirection() : 'ltr';
$componentDomain = defined('G2ML_COMPONENT_DOMAIN') ? G2ML_COMPONENT_DOMAIN : 'go2my.link';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo $textDir; ?>">
<head>
    <!-- ================================================================== -->
    <!-- Meta Tags                                                          -->
    <!-- ================================================================== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">

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
          id="bootstrap-css">
    <script>
        // Bootstrap CSS local fallback
        // 📖 Reference: https://getbootstrap.com/docs/5.3/getting-started/download/
        (function() {
            var testEl = document.createElement('div');
            testEl.className = 'd-none';
            document.body.appendChild(testEl);
            if (window.getComputedStyle(testEl).display !== 'none') {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = '<?php echo htmlspecialchars(defined('G2ML_LIBRARIES') ? '/_libraries/bootstrap/css/bootstrap.min.css' : '/_libraries/bootstrap/css/bootstrap.min.css', ENT_QUOTES, 'UTF-8'); ?>';
                document.head.appendChild(link);
            }
            document.body.removeChild(testEl);
        })();
    </script>

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
</head>
<body class="<?php echo htmlspecialchars(trim('g2ml-' . (defined('G2ML_COMPONENT') ? G2ML_COMPONENT : 'unknown') . ' ' . $bodyClass), ENT_QUOTES, 'UTF-8'); ?>">

<?php
// Skip to content link (first focusable element)
echo skipToContent();
?>
