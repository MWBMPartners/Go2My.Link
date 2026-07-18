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
 * 🔍 Go2My.Link — Destination Validation Page (Component B)
 * ============================================================================
 *
 * Shown when the destination URL fails the validateDestination() check.
 * Displays a safety warning and countdown before redirecting to the
 * original destination (with a manual fallback link to the org fallback URL).
 *
 * Expected variables from the including file (index.php):
 *   - $destination  (string) — The original destination URL
 *   - $orgHandle    (string|null) — Organisation handle for fallback
 *   - $shortCode    (string) — The short code that was resolved
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
$destination  = $destination ?? '';
if (function_exists('getSetting')) {
    $siteName = getSetting('site.name', 'Go2My.Link');
} else {
    $siteName = 'Go2My.Link';
}
$mainSiteURL  = 'https://go2my.link';

// Fallback URL goes to the org fallback (NOT the unverified destination)
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

// ============================================================================
// 🛡️ Scheme-guard every URL before it reaches an href / JS sink (F-003 / #99)
// ============================================================================
// A migrated legacy URL with a javascript:/data:/vbscript: scheme would become
// a live XSS / open-redirect vector once emitted into an href or
// window.location.href. g2ml_sanitiseURL() (loaded by page_init.php for
// Component B) returns false for anything that is not a valid http(s) URL, so
// we drop any destination/fallback that fails the guard back to the safe main
// site. htmlspecialchars(..., ENT_QUOTES) is still applied at every sink.

if (function_exists('g2ml_sanitiseURL'))
{
    $safeDestination = g2ml_sanitiseURL($destination);

    if ($safeDestination === false)
    {
        $safeDestination = '';
    }

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
    if (preg_match('#^https?://#i', $destination) === 1)
    {
        $safeDestination = $destination;
    }
    else
    {
        $safeDestination = '';
    }

    if (preg_match('#^https?://#i', $fallbackURL) === 1)
    {
        $safeFallbackURL = $fallbackURL;
    }
    else
    {
        $safeFallbackURL = $mainSiteURL;
    }
}

// Show the destination domain for user context (not the full URL for safety).
// Derived from the GUARDED destination so a rejected scheme yields no domain.
$destinationDomain = '';
if ($safeDestination !== '')
{
    $parsed = parse_url($safeDestination);
    $destinationDomain = $parsed['host'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Checking Link Safety — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- ♿ No-JS fallback: meta refresh to fallback URL (safe URL, not destination) -->
    <noscript>
        <meta http-equiv="refresh" content="<?php echo $countdownDelay; ?>;url=<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
    </noscript>

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
        /* 🎨 Minimal self-contained styles for validation page */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .validation-container {
            max-width: 550px;
            text-align: center;
            padding: 2rem;
        }
        .validation-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        .countdown-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .destination-domain {
            font-family: monospace;
            word-break: break-all;
        }
    </style>
</head>
<body>

    <main class="validation-container">
        <!-- 🔍 Validation Icon -->
        <div class="validation-icon text-warning" aria-hidden="true">
            <i class="fas fa-shield-halved"></i>
        </div>

        <!-- 📋 Title -->
        <h1 class="h3 mb-3">Checking Link Safety</h1>

        <!-- 📋 Message -->
        <p class="text-body-secondary mb-2">
            We could not verify the safety of the destination for this short link.
        </p>

        <?php if ($destinationDomain !== '') { ?>
        <p class="text-body-secondary mb-4">
            Destination: <span class="destination-domain"><?php echo htmlspecialchars($destinationDomain, ENT_QUOTES, 'UTF-8'); ?></span>
        </p>
        <?php } ?>

        <!-- ⚠️ Warning -->
        <div class="alert alert-warning text-start mb-4" role="alert">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <strong>Proceed with caution.</strong>
            The destination may be temporarily unavailable or may have moved.
            If you do not trust this link, use the safe alternative below.
        </div>

        <!-- ⏱️ Countdown -->
        <div class="mb-3">
            <p class="text-body-secondary">
                Proceeding in <span id="countdown" class="countdown-number"><?php echo $countdownDelay; ?></span> seconds...
            </p>
            <div class="progress" role="progressbar" aria-label="Redirect countdown"
                 aria-valuenow="<?php echo $countdownDelay; ?>" aria-valuemin="0" aria-valuemax="<?php echo $countdownDelay; ?>"
                 style="height: 4px;">
                <div id="countdown-bar" class="progress-bar bg-warning" style="width: 100%;"></div>
            </div>
        </div>

        <!-- 🔗 Action Buttons -->
        <div class="d-flex justify-content-center gap-3 mb-4">
            <?php if ($safeDestination !== '') { ?>
            <a href="<?php echo htmlspecialchars($safeDestination, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-warning text-dark"
               rel="noopener noreferrer">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                Proceed Anyway
            </a>
            <?php } ?>

            <a href="<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                Go Somewhere Safe
            </a>
        </div>

        <!-- ♿ No-JS Manual Link -->
        <noscript>
            <p class="text-body-secondary">
                <a href="<?php echo htmlspecialchars($safeFallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
                    Go to a safe page if you are not redirected automatically.
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

    <!-- ♿ ARIA Live Region for Countdown Announcements (polite: a countdown is not urgent) -->
    <div id="countdown-status" class="visually-hidden" aria-live="polite" role="status"></div>

    <!-- ⏱️ Countdown Timer Script — Redirects to the DESTINATION (not fallback) -->
    <script>
        (function() {
            var remaining     = <?php echo (int) $countdownDelay; ?>;
            var total         = remaining;
            var destinationURL = <?php echo json_encode($safeDestination, JSON_UNESCAPED_SLASHES); ?>;
            var fallbackURL   = <?php echo json_encode($safeFallbackURL, JSON_UNESCAPED_SLASHES); ?>;
            var countdownEl   = document.getElementById('countdown');
            var progressEl    = document.getElementById('countdown-bar');
            var statusEl      = document.getElementById('countdown-status');

            // If no destination, redirect to fallback instead
            var targetURL;
            if (destinationURL && destinationURL !== '') {
                targetURL = destinationURL;
            } else {
                targetURL = fallbackURL;
            }

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
                    var secondLabel;
                    if (remaining === 1) {
                        secondLabel = ' second';
                    } else {
                        secondLabel = ' seconds';
                    }
                    statusEl.textContent = 'Proceeding in ' + remaining + secondLabel;
                }

                if (remaining <= 0) {
                    clearInterval(timer);
                    window.location.href = targetURL;
                }
            }, 1000);
        })();
    </script>

</body>
</html>
