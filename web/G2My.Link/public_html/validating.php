<?php
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
$siteName     = function_exists('getSetting') ? getSetting('site.name', 'Go2My.Link') : 'Go2My.Link';
$mainSiteURL  = 'https://go2my.link';

// Fallback URL goes to the org fallback (NOT the unverified destination)
$fallbackURL  = function_exists('getDomainFallbackURL')
    ? getDomainFallbackURL($orgHandle ?? '[default]')
    : $mainSiteURL;

// Countdown delay from settings (default: 5 seconds)
$countdownDelay = function_exists('getSetting')
    ? (int) getSetting('redirect.fallback_delay', 5)
    : 5;

// Show the destination domain for user context (not the full URL for safety)
$destinationDomain = '';
if ($destination !== '')
{
    $parsed = parse_url($destination);
    $destinationDomain = $parsed['host'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Checking Link Safety — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- ♿ No-JS fallback: meta refresh to fallback URL (safe URL, not destination) -->
    <noscript>
        <meta http-equiv="refresh" content="<?php echo $countdownDelay; ?>;url=<?php echo htmlspecialchars($fallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
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

        <?php if ($destinationDomain !== ''): ?>
        <p class="text-body-secondary mb-4">
            Destination: <span class="destination-domain"><?php echo htmlspecialchars($destinationDomain, ENT_QUOTES, 'UTF-8'); ?></span>
        </p>
        <?php endif; ?>

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
                Proceeding in <span id="countdown" class="countdown-number" aria-live="polite"><?php echo $countdownDelay; ?></span> seconds...
            </p>
            <div class="progress" role="progressbar" aria-label="Redirect countdown"
                 aria-valuenow="<?php echo $countdownDelay; ?>" aria-valuemin="0" aria-valuemax="<?php echo $countdownDelay; ?>"
                 style="height: 4px;">
                <div id="countdown-bar" class="progress-bar bg-warning" style="width: 100%;"></div>
            </div>
        </div>

        <!-- 🔗 Action Buttons -->
        <div class="d-flex justify-content-center gap-3 mb-4">
            <?php if ($destination !== ''): ?>
            <a href="<?php echo htmlspecialchars($destination, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-warning text-dark"
               rel="noopener noreferrer">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                Proceed Anyway
            </a>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($fallbackURL, ENT_QUOTES, 'UTF-8'); ?>"
               class="btn btn-primary">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                Go Somewhere Safe
            </a>
        </div>

        <!-- ♿ No-JS Manual Link -->
        <noscript>
            <p class="text-body-secondary">
                <a href="<?php echo htmlspecialchars($fallbackURL, ENT_QUOTES, 'UTF-8'); ?>">
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

    <!-- ♿ ARIA Live Region for Countdown Announcements -->
    <div id="countdown-status" class="visually-hidden" aria-live="assertive" role="status"></div>

    <!-- ⏱️ Countdown Timer Script — Redirects to the DESTINATION (not fallback) -->
    <script>
        (function() {
            var remaining     = <?php echo (int) $countdownDelay; ?>;
            var total         = remaining;
            var destinationURL = <?php echo json_encode($destination, JSON_UNESCAPED_SLASHES); ?>;
            var fallbackURL   = <?php echo json_encode($fallbackURL, JSON_UNESCAPED_SLASHES); ?>;
            var countdownEl   = document.getElementById('countdown');
            var progressEl    = document.getElementById('countdown-bar');
            var statusEl      = document.getElementById('countdown-status');

            // If no destination, redirect to fallback instead
            var targetURL = (destinationURL && destinationURL !== '') ? destinationURL : fallbackURL;

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
                    statusEl.textContent = 'Proceeding in ' + remaining + ' second' + (remaining !== 1 ? 's' : '');
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
