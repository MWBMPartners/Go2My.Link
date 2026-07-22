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
 * 💥 Go2My.Link — LinksPage 500 Internal Server Error (Component C)
 * ============================================================================
 *
 * Branded error page for server-side failures Apache itself reports (e.g. a
 * PHP fatal error with display_errors off, producing no output). Self-
 * contained — no external CDN scripts/stylesheets — so it renders correctly
 * under this component's strict Content-Security-Policy (script-src 'self';
 * no inline scripts at all).
 *
 * Reached via the ErrorDocument 500 directive in .htaccess, which routes back
 * through index.php (?http_error=500) so G2ML_COMPONENT/page_init.php are
 * already defined before this file's Direct Access Guard runs — see index.php
 * Step 3b.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
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
    header('Location: https://lnks.page', true, 302);
    exit;
}

if (function_exists('getSetting'))
{
    $siteName = getSetting('site.name', 'Lnks.page');
}
else
{
    $siteName = 'Lnks.page';
}

if (function_exists('__'))
{
    $errorTitle   = __('error.500_title');
    $errorMessage = __('error.500_message');
    $backHomeLabel = __('error.back_home');
}
else
{
    $errorTitle   = '500 — Server Error';
    $errorMessage = 'Something went wrong on our end.';
    $backHomeLabel = 'Back to Home';
}
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #212529;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .lp-error-container {
            max-width: 480px;
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .lp-error-code {
            font-size: 3.5rem;
            font-weight: 800;
            color: #adb5bd;
            margin: 0 0 0.5rem;
        }
        .lp-error-message {
            font-size: 1.05rem;
            font-weight: 400;
            color: #495057;
            margin: 0 0 1.75rem;
        }
        .lp-error-cta {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            background: #1E88E5;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .lp-error-cta:hover,
        .lp-error-cta:focus {
            background: #1668ab;
        }
        @media (prefers-color-scheme: dark) {
            body {
                background: #121212;
                color: #e9ecef;
            }
            .lp-error-code {
                color: #6c757d;
            }
            .lp-error-message {
                color: #ced4da;
            }
        }
    </style>
</head>
<body>
    <main class="lp-error-container">
        <p class="lp-error-code" aria-hidden="true">500</p>
        <h1 class="lp-error-message"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="lp-error-cta" href="https://go2my.link">
            <?php echo htmlspecialchars($backHomeLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </main>
</body>
</html>
