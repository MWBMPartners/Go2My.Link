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
 * 💥 Go2My.Link — 500 Internal Server Error Page (Component B)
 * ============================================================================
 *
 * Branded error page for server-side failures Apache itself reports (e.g. a
 * PHP fatal error with display_errors off, producing no output). Self-
 * contained HTML — does not use the shared header/nav/footer templates
 * (Component B is performance-critical).
 *
 * Reached via the ErrorDocument 500 directive in .htaccess, which routes back
 * through index.php (?http_error=500) so G2ML_COMPONENT/page_init.php are
 * already defined before this file's Direct Access Guard runs — see index.php
 * Step 3b.
 *
 * Expected variables from the including file (index.php):
 *   - $errorTitle   (string) — Error heading text
 *   - $errorMessage (string) — Descriptive error message
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.1.0
 * @since      Phase 6 (#114)
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
$errorTitle   = $errorTitle ?? '500 — Server Error';
$errorMessage = $errorMessage ?? 'Something went wrong on our end. Please try again later.';
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
if (function_exists('__')) {
    $goHomeLabel = __('error.go_home');
} else {
    $goHomeLabel = 'Go Home';
}
if (function_exists('__')) {
    $poweredByLabel = __('error.powered_by');
} else {
    $poweredByLabel = 'Powered by';
}
$mainSiteURL  = 'https://go2my.link';
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Bootstrap 5.3 CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Font Awesome 6 (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous">

    <!-- 🌓 FOUC Prevention — Apply theme before first paint -->
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

    <main class="error-container">
        <!-- 💥 Error Icon -->
        <div class="error-icon text-danger" aria-hidden="true">
            <i class="fas fa-server"></i>
        </div>

        <!-- 📋 Error Title -->
        <h1 class="h3 mb-3"><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></h1>

        <!-- 📋 Error Message -->
        <p class="text-body-secondary mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>

        <!-- 🔗 CTA Button -->
        <div class="mb-4">
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary btn-lg">
                <i class="fas fa-home" aria-hidden="true"></i>
                <?php echo htmlspecialchars($goHomeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>

        <!-- 📋 Powered By -->
        <p class="text-body-secondary small">
            <?php echo htmlspecialchars($poweredByLabel, ENT_QUOTES, 'UTF-8'); ?>
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="text-decoration-none">
                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    </main>

</body>
</html>
