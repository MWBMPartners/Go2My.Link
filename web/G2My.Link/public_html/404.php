<?php
/**
 * ============================================================================
 * ❌ Go2My.Link — 404 Error Page (Component B)
 * ============================================================================
 *
 * Branded error page for short links that cannot be found, are disabled,
 * or have configuration issues. Self-contained HTML — does not use the
 * shared header/nav/footer templates (Component B is performance-critical).
 *
 * Expected variables from the including file (index.php):
 *   - $errorTitle   (string) — Error heading text
 *   - $errorMessage (string) — Descriptive error message
 *   - $orgHandle    (string|null) — Organisation handle for branding
 *   - $status       (string) — SP status code for context
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (!defined('G2ML_COMPONENT'))
{
    header('Location: https://go2my.link', true, 302);
    exit;
}

// ============================================================================
// 📋 Set Default Values
// ============================================================================
$errorTitle   = $errorTitle ?? '404 — Link Not Found';
$errorMessage = $errorMessage ?? 'The short link you requested does not exist or has been removed.';
$siteName     = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$mainSiteURL  = 'https://go2my.link';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Bootstrap 5.3 CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1lP6tVXrIFb8e1TdnJOz3m8f2Md5ND"
          crossorigin="anonymous">

    <!-- Font Awesome 6 (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous">

    <!-- 🌓 FOUC Prevention — Apply theme before first paint -->
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

    <style>
        /* 🎨 Minimal self-contained styles for error page */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
            padding: 2rem;
        }
        .error-icon {
            font-size: 4rem;
            opacity: 0.6;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

    <div class="error-container" role="main">
        <!-- 🔗 Error Icon -->
        <div class="error-icon text-body-secondary" aria-hidden="true">
            <i class="fas fa-link-slash"></i>
        </div>

        <!-- 📋 Error Title -->
        <h1 class="h3 mb-3"><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></h1>

        <!-- 📋 Error Message -->
        <p class="text-body-secondary mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>

        <!-- 🔗 CTA Button -->
        <div class="mb-4">
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary btn-lg">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                Create a Short Link
            </a>
        </div>

        <!-- 📋 Powered By -->
        <p class="text-body-secondary small">
            Powered by
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="text-decoration-none">
                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    </div>

</body>
</html>
