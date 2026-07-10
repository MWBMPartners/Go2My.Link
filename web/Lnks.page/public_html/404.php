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
 * ❌ Go2My.Link — LinksPage 404 (Component C)
 * ============================================================================
 *
 * Branded error page for a slug that does not resolve to a publicly visible
 * LinksPage (missing, unpublished, inactive, malformed, or the service is
 * administratively disabled). Self-contained — no external CDN
 * scripts/stylesheets — so it renders correctly under this component's strict
 * Content-Security-Policy (default-src 'self'; script-src 'self'; no
 * third-party origins).
 *
 * Expected to be `require`-d from index.php AFTER http_response_code(404) has
 * already been set and the bootstrap (page_init.php, i18n) has already run.
 *
 * @package    Go2My.Link
 * @subpackage ComponentC
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.1.0
 * @since      Phase 8 (#45)
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
    $notFoundMessage = __('linkspage.not_found');
    $backHomeLabel   = __('error.back_home');
}
else
{
    $notFoundMessage = 'This LinksPage does not exist or has not been set up yet.';
    $backHomeLabel   = 'Back to Home';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>404 — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        .lp-404-container {
            max-width: 480px;
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .lp-404-code {
            font-size: 3.5rem;
            font-weight: 800;
            color: #adb5bd;
            margin: 0 0 0.5rem;
        }
        .lp-404-message {
            font-size: 1.05rem;
            font-weight: 400;
            color: #495057;
            margin: 0 0 1.75rem;
        }
        .lp-404-cta {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            background: #1E88E5;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .lp-404-cta:hover,
        .lp-404-cta:focus {
            background: #1668ab;
        }
    </style>
</head>
<body>
    <main class="lp-404-container">
        <p class="lp-404-code" aria-hidden="true">404</p>
        <h1 class="lp-404-message"><?php echo htmlspecialchars($notFoundMessage, ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="lp-404-cta" href="https://go2my.link">
            <?php echo htmlspecialchars($backHomeLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </main>
</body>
</html>
