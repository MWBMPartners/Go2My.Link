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
 * ⏰ Go2My.Link — Expired / Not Yet Active Link Page (Component B)
 * ============================================================================
 *
 * Branded page for short links that have expired or are not yet active. It
 * says why and links to the homepage.
 *
 * It used to count down for a few seconds and then send the visitor to the
 * fallback address. That was removed in #290: decision D-006 rules out
 * sending visitors to the homepage without being asked, and a page that moves
 * on by itself leaves slow readers behind (WCAG 2.2.1). An organisation that
 * set its own fallback address never reaches this page; index.php redirects
 * there first.
 *
 * Expected variables from the including file (index.php):
 *   - $errorTitle   (string) — Error heading text
 *   - $errorMessage (string) — Descriptive error message
 *   - $status       (string) — SP status: 'expired' or 'not_yet_active'
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
if (!isset($errorTitle)) {
    $errorTitle = g2ml_shortlinkText('shortlink.expired_title', 'Link Expired');
}
if (!isset($errorMessage)) {
    $errorMessage = g2ml_shortlinkText('shortlink.expired_message', 'This short link has expired and is no longer available.');
}
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
$mainSiteURL    = 'https://go2my.link';
$homeLinkLabel  = g2ml_shortlinkText('shortlink.home_link', 'Go to the {site} homepage', ['site' => $siteName]);
$poweredByLabel = g2ml_shortlinkText('error.powered_by', 'Powered by');

// Choose appropriate icon based on status
if (($status ?? 'expired') === 'not_yet_active') {
    $statusIcon = 'fa-clock';
} else {
    $statusIcon = 'fa-calendar-xmark';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(g2ml_shortlinkLocale(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(g2ml_shortlinkDirection(), ENT_QUOTES, 'UTF-8'); ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <?php // Bootstrap 5.3 CSS: CDN first; a local copy in /vendor/ if the CDN fails. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous"
          onerror="var l=document.createElement('link');l.rel='stylesheet';l.href='/vendor/bootstrap/css/bootstrap.min.css';this.parentNode.insertBefore(l, this.nextSibling);">

    <?php // Font Awesome 6: CDN first; a local copy in /vendor/ if the CDN fails. ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous"
          onerror="var l=document.createElement('link');l.rel='stylesheet';l.href='/vendor/fontawesome/css/all.min.css';this.parentNode.insertBefore(l, this.nextSibling);">

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
            box-sizing: border-box;
            width: 100%;
            overflow-wrap: anywhere;
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
        <!-- ⏰ Status Icon -->
        <div class="error-icon text-body-secondary" aria-hidden="true">
            <i class="fas <?php echo $statusIcon; ?>"></i>
        </div>

        <!-- 📋 Error Title -->
        <h1 class="h3 mb-3"><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></h1>

        <!-- 📋 Error Message -->
        <p class="text-body-secondary mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>

        <!-- 🔗 Link to the homepage -->
        <div class="mb-4">
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary btn-lg">
                <i class="fas fa-home" aria-hidden="true"></i>
                <?php echo htmlspecialchars($homeLinkLabel, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>

        <!-- 📋 Powered By -->
        <p class="text-body-secondary small">
            <?php echo htmlspecialchars($poweredByLabel, ENT_QUOTES, 'UTF-8'); ?>
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    </main>

</body>
</html>
