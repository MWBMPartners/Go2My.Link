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
 * Branded error page for short links that have expired or are not yet active.
 * Includes a countdown timer that redirects to the fallback URL after a
 * configurable delay (default: 5 seconds).
 *
 * Expected variables from the including file (index.php):
 *   - $errorTitle   (string) — Error heading text
 *   - $errorMessage (string) — Descriptive error message
 *   - $orgHandle    (string|null) — Organisation handle for fallback
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
// 📋 Set Default Values and Determine Fallback
// ============================================================================
$errorTitle   = $errorTitle ?? 'Link Expired';
$errorMessage = $errorMessage ?? 'This short link has expired and is no longer available.';
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
$mainSiteURL  = 'https://go2my.link';

// Get the fallback URL via the cascade (category → org → system → default)
if (function_exists('getDomainFallbackURL')) {
    $fallbackURL = getDomainFallbackURL($orgHandle ?? '[default]');
} else {
    $fallbackURL = $mainSiteURL;
}

// Countdown delay from settings (default: 5 seconds)
if (function_exists('getSetting')) {
    $countdownDelay = (int) getSetting('redirect.fallback_delay', 5);
} else {
    $countdownDelay = 5;
}

// Choose appropriate icon based on status
if (($status ?? 'expired') === 'not_yet_active') {
    $statusIcon = 'fa-clock';
} else {
    $statusIcon = 'fa-calendar-xmark';
}

// ============================================================================
// 🛡️ Scheme-guard the fallback URL before it reaches an href / JS sink (F-003 / #99)
// ============================================================================
// The org/category fallback could be a migrated legacy URL carrying a
// javascript:/data:/vbscript: scheme, which would become a live XSS /
// open-redirect vector once emitted into an href or window.location.href.
// g2ml_sanitiseURL() (loaded by page_init.php for Component B) returns false
// for anything that is not a valid http(s) URL; on rejection we drop back to
// the safe main site. htmlspecialchars(..., ENT_QUOTES) is still applied at
// every sink.

if (function_exists('g2ml_sanitiseURL'))
{
    $safeFallbackURL = g2ml_sanitiseURL($fallbackURL);

    if ($safeFallbackURL === false)
    {
        $safeFallbackURL = $mainSiteURL;
    }
}
else
{
    // Defensive fallback if the shared helper is ever unavailable in this
    // include path: an http(s)-only scheme guard consistent with house style.
    if (preg_match('#^https?://#i', $fallbackURL) === 1)
    {
        $safeFallbackURL = $fallbackURL;
    }
    else
    {
        $safeFallbackURL = $mainSiteURL;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- ♿ No-JS fallback: meta refresh to fallback URL -->
    <noscript>
        <meta http-equiv="refresh" content="<?php echo $countdownDelay; ?>;url=<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
    </noscript>

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
        .countdown-number {
            font-size: 1.5rem;
            font-weight: bold;
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

        <!-- ⏱️ Countdown -->
        <div class="mb-4">
            <p class="text-body-secondary">
                Redirecting in <span id="countdown" class="countdown-number" aria-live="assertive"><?php echo $countdownDelay; ?></span> seconds...
            </p>
            <div class="progress" role="progressbar" aria-label="Redirect countdown"
                 aria-valuenow="<?php echo $countdownDelay; ?>" aria-valuemin="0" aria-valuemax="<?php echo $countdownDelay; ?>"
                 style="height: 4px;">
                <div id="countdown-bar" class="progress-bar" style="width: 100%;"></div>
            </div>
        </div>

        <!-- 🔗 Manual Fallback Link -->
        <div class="mb-4">
            <a href="<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                Continue
            </a>
        </div>

        <!-- ♿ No-JS Manual Link -->
        <noscript>
            <p class="text-body-secondary">
                <a href="<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
                    Continue to <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> if you are not redirected automatically.
                </a>
            </p>
        </noscript>

        <!-- 📋 Powered By -->
        <p class="text-body-secondary small">
            Powered by
            <a href="<?php echo htmlspecialchars($mainSiteURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="text-decoration-none">
                <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    </main>

    <!-- ♿ ARIA Live Region for Countdown Announcements -->
    <div id="countdown-status" class="visually-hidden" aria-live="assertive" role="status"></div>

    <!-- ⏱️ Countdown Timer Script -->
    <script>
        (function() {
            var remaining   = <?php echo (int) $countdownDelay; ?>;
            var total       = remaining;
            var fallbackURL = <?php echo json_encode($safeFallbackURL, JSON_UNESCAPED_SLASHES); ?>;
            var countdownEl = document.getElementById('countdown');
            var progressEl  = document.getElementById('countdown-bar');
            var statusEl    = document.getElementById('countdown-status');

            var timer = setInterval(function() {
                remaining--;

                if (countdownEl) {
                    countdownEl.textContent = remaining;
                }

                if (progressEl) {
                    progressEl.style.width = ((remaining / total) * 100) + '%';
                }

                // Announce at key moments for screen readers
                if (statusEl && (remaining === 3 || remaining === 1)) {
                    statusEl.textContent = 'Redirecting in ' + remaining + ' second' + (remaining !== 1 ? 's' : '');
                }

                if (remaining <= 0) {
                    clearInterval(timer);
                    window.location.href = fallbackURL;
                }
            }, 1000);
        })();
    </script>

</body>
</html>
