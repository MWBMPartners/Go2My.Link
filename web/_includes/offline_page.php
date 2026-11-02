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
 * 📴 Go2My.Link — Offline Page (shared by the main site and the dashboard)
 * ============================================================================
 *
 * Printed at /offline. Each site's service worker (sw.js) stores one copy of
 * this page when it installs and shows it whenever a page cannot load
 * because the network is down (#283).
 *
 * It lives here, in the shared backend, because the deploy copies each web
 * root on its own schedule: the dashboard's web root reaches the live
 * dashboard on an alpha or beta push, the main site's only on a main push.
 * A dashboard /offline that loaded the main site's file could fail on the
 * live dashboard, and the dashboard's worker would then never install.
 *
 * The caller (web/Go2My.Link/public_html/offline.php or
 * web/Go2My.Link/_admin/public_html/offline.php) sets its own component
 * constants and loads page_init.php first, like a front controller.
 *
 * It prints its own small document: no site header, navigation or cookie
 * banner (fetching it must not issue form tokens) and no files from other
 * sites. Its only other file is /css/style.css, which the worker caches too.
 *
 * @package    Go2My.Link
 * @subpackage PWA
 * @version    1.0.0
 * @since      v1.0.0 — Launch Hardening (#283)
 * ============================================================================
 */

/**
 * The translated text for $key, or $english when there is none. __() hands
 * back the key itself when the translation is missing (before seed 070 is
 * applied, or with the database unreachable), and the service worker keeps
 * the copy it fetched, so a key name must never be what this page shows.
 *
 * @param  string $key
 * @param  string $english
 * @return string
 */
function g2ml_offlinePageText(string $key, string $english): string
{
    if (function_exists('__'))
    {
        $translated = __($key);

        if (is_string($translated) && $translated !== '' && $translated !== $key)
        {
            return $translated;
        }
    }

    return $english;
}

$offlineTitle   = g2ml_offlinePageText('offline.title', 'You are offline');
$offlineMessage = g2ml_offlinePageText('offline.message', 'This page needs an internet connection. Check your connection, then try again.');
$offlineRetry   = g2ml_offlinePageText('offline.retry', 'Try again');

if (function_exists('getLocale'))
{
    $offlineLocale = getLocale();
}
else
{
    $offlineLocale = 'en-GB';
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($offlineLocale, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($offlineTitle, ENT_QUOTES, 'UTF-8'); ?> — Go2My.Link</title>
    <link rel="stylesheet" href="/css/style.css">
    <style type="text/css">
        /* Brand colours come from style.css (cached by the service worker);
           the fallbacks are the browser's own system colours, so the page
           still reads correctly in light and dark mode without it. */
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background-color: Canvas;
            color: CanvasText;
        }
        main {
            max-width: 32rem;
            padding: 2rem;
            text-align: center;
        }
        h1 {
            font-size: 1.75rem;
            margin-top: 0;
        }
        .g2ml-offline-retry {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.6rem 1.4rem;
            border-radius: 0.375rem;
            background-color: var(--g2ml-skip-bg, LinkText);
            color: var(--g2ml-skip-color, Canvas);
            text-decoration: none;
        }
        .g2ml-offline-retry:focus-visible {
            outline: 3px solid var(--g2ml-focus-color, CanvasText);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <main>
        <h1><?php echo htmlspecialchars($offlineTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars($offlineMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="g2ml-offline-retry" href=""><?php echo htmlspecialchars($offlineRetry, ENT_QUOTES, 'UTF-8'); ?></a>
    </main>
</body>
</html>
