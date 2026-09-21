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
 * 📖 Go2My.Link — Public API Documentation (Swagger UI)
 * ============================================================================
 *
 * A second, "try it out"-capable view of the same OpenAPI 3.1 document that
 * the Redoc page one directory up (/api/docs/) renders as a reference manual.
 *
 * Why two viewers rather than one:
 *
 *   - **Redoc (/api/docs/)** is a three-column reading experience. It is the
 *     better page for *learning* the API — reading it end to end, following
 *     the request pipeline, comparing schemas side by side.
 *   - **Swagger UI (/api/docs/swagger/)** is an interactive console. It is
 *     the better page for *using* the API — you paste in an API key, fill in
 *     the fields on a form, press a button, and see the real response come
 *     back from this very server.
 *
 * Both read the identical specification file, /api/openapi.yaml, so the two
 * pages can never drift apart from each other. There is exactly one source of
 * truth.
 *
 * ----------------------------------------------------------------------------
 * Running this on shared hosting (e.g. Dreamhost)
 * ----------------------------------------------------------------------------
 *
 * Everything this page needs is a plain file sitting on disk. There is no
 * Docker container, no Composer install, no Node.js process and no build step
 * at any point — upload the directory and it works. The Swagger UI JavaScript
 * and stylesheet are vendored locally under vendor/, so the page also makes no
 * call to any third-party network host: it will render correctly on a server
 * with no outbound internet access at all.
 *
 * ----------------------------------------------------------------------------
 * Security posture
 * ----------------------------------------------------------------------------
 *
 * This directory ships its own .htaccess with a Content-Security-Policy that
 * is *stricter* than both the site-wide policy and the neighbouring Redoc
 * page's policy. Swagger UI 5.x needs no Web Worker, no CSS-in-JS and no
 * eval(), so — unlike Redoc — it does not need `worker-src blob:` or
 * `style-src 'unsafe-inline'`. See the .htaccess in this directory for the
 * measurements behind that claim.
 *
 * The "Try it out" button issues genuine requests to /api/v1/* from the
 * visitor's own browser. That is deliberate and safe: those requests travel
 * the exact same path as any other API call, so they still need a valid API
 * key, they still have to pass the scope check for the endpoint, and they are
 * still counted against that key's rate limit and written to the audit log. A
 * visitor without a key can read the documentation but cannot call anything.
 * `persistAuthorization` is switched OFF in init.js, so a key pasted into the
 * Authorize dialog lives only in that browser tab's memory and is never
 * written to local storage or a cookie.
 *
 * ----------------------------------------------------------------------------
 * Dark and light mode
 * ----------------------------------------------------------------------------
 *
 * The site remembers a visitor's theme choice in the `g2ml_theme` cookie
 * ('auto', 'light' or 'dark'), which is written by /js/theme.js on the main
 * site. This page reads that cookie in PHP and stamps `data-bs-theme` on the
 * <html> element before a single byte of markup is sent, so there is no flash
 * of the wrong colours while the page loads. When the preference is 'auto' (or
 * the visitor has never been to the main site) no attribute is set and
 * swagger-ui-theme.css falls back to the operating system's own
 * `prefers-color-scheme` setting.
 *
 * Doing it this way means the page needs no inline <script> at all, which in
 * turn is what lets the Content-Security-Policy above stay at a strict
 * `script-src 'self'`.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7
 *
 * 📖 References:
 *     - OpenAPI spec:   web/Go2My.Link/public_html/api/openapi.yaml
 *     - Redoc page:     web/Go2My.Link/public_html/api/docs/index.php
 *     - Swagger UI:     https://github.com/swagger-api/swagger-ui (Apache-2.0)
 *     - Vendored build: vendor/swagger-ui-bundle.js (v5.32.15)
 *     - Scoped CSP:     web/Go2My.Link/public_html/api/docs/swagger/.htaccess
 * ============================================================================
 */

// ============================================================================
// 🎨 Theme preference — read the site-wide cookie, server-side
// ============================================================================
// This mirrors the logic in web/_includes/header.php. It is repeated here
// rather than included because this page deliberately does not bootstrap the
// application (no database connection, no session, no settings lookup) — it
// must stay renderable even when the database is down.
// ============================================================================
$g2mlThemePreference = 'auto';

if (isset($_COOKIE['g2ml_theme']) && is_string($_COOKIE['g2ml_theme']))
{
    $g2mlThemePreference = $_COOKIE['g2ml_theme'];
}

$g2mlValidThemes = ['auto', 'light', 'dark'];

if (!in_array($g2mlThemePreference, $g2mlValidThemes, true))
{
    $g2mlThemePreference = 'auto';
}

// For an explicit 'light' or 'dark' choice we stamp the attribute so the
// choice wins outright. For 'auto' we deliberately stamp nothing, which lets
// the stylesheet's prefers-color-scheme rules decide.
$g2mlThemeAttribute = '';

if ($g2mlThemePreference === 'light' || $g2mlThemePreference === 'dark')
{
    $g2mlThemeAttribute = ' data-bs-theme="' . htmlspecialchars($g2mlThemePreference, ENT_QUOTES, 'UTF-8') . '"';
}
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr"<?php echo $g2mlThemeAttribute; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Go2My.Link — API Console (Swagger UI)</title>
    <meta name="description" content="Interactive OpenAPI 3.1 console for the Go2My.Link public API (/api/v1/*) — read the reference and send real, authenticated test requests.">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
    <link rel="canonical" href="https://go2my.link/api/docs/swagger/">

    <link rel="stylesheet" type="text/css" href="/api/docs/swagger/vendor/swagger-ui.css">
    <link rel="stylesheet" type="text/css" href="/api/docs/swagger/swagger-ui-theme.css">
</head>
<body>
    <!-- ================================================================== -->
    <!-- Skip link — WCAG 2.1 AA (2.4.1 Bypass Blocks). Swagger UI renders  -->
    <!-- a long list of operations, so a keyboard user needs a way past the -->
    <!-- page header without tabbing through all of them.                   -->
    <!-- ================================================================== -->
    <a class="g2ml-skip-link" href="#g2ml-swagger-ui">Skip to the API reference</a>

    <header class="g2ml-docs-header">
        <div class="g2ml-docs-header-inner">
            <h1 class="g2ml-docs-title">Go2My.Link API console</h1>
            <p class="g2ml-docs-subtitle">
                Try the public API from your browser. Press <strong>Authorize</strong> and paste an
                API key to send real requests; without a key you can still read every endpoint.
            </p>
            <nav class="g2ml-docs-nav" aria-label="API documentation views">
                <a href="/api/docs/">📖 Reference manual (Redoc)</a>
                <a href="/api/openapi.yaml" download="go2my-link-openapi.yaml">⬇️ Download the specification</a>
            </nav>
        </div>
    </header>

    <main id="g2ml-swagger-ui" tabindex="-1">
        <p id="g2ml-swagger-loading">Loading the Go2My.Link API console…</p>
    </main>

    <script src="/api/docs/swagger/vendor/swagger-ui-bundle.js"></script>
    <script src="/api/docs/swagger/init.js"></script>
</body>
</html>
