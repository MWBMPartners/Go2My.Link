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
 * 📖 Go2My.Link — Public API Documentation (Redoc, #75)
 * ============================================================================
 *
 * Self-hosted, unauthenticated static documentation page for the versioned
 * public API (/api/v1/*). Renders web/Go2My.Link/public_html/api/openapi.yaml
 * via a locally vendored Redoc standalone bundle — vendor/redoc.standalone.js
 * — so this page has NO dependency on any external CDN and works fully
 * offline of any third-party network call.
 *
 * This page is deliberately a plain PHP file with no dynamic behaviour (no
 * database access, no session, no user input processed) — it is pure static
 * markup, kept as .php only for consistency with the rest of the component's
 * clean-URL routing (DirectoryIndex serves this for a request to /api/docs/).
 *
 * CSP: the component's site-wide Content-Security-Policy (see the parent
 * public_html/.htaccess) does not permit the worker-src/blob: or
 * style-src 'unsafe-inline' behaviour Redoc's self-hosted bundle needs (a Web
 * Worker for search indexing, constructed from a blob: URL; CSS-in-JS
 * theming). Rather than weaken the site-wide policy, .htaccess in THIS
 * directory only relaxes the policy for paths under /api/docs/ — every
 * asset it references remains same-origin ('self'); no external CDN is
 * whitelisted anywhere.
 *
 * There is a second view of the very same specification file next door, at
 * /api/docs/swagger/. That one is Swagger UI: an interactive console where a
 * developer pastes in an API key and sends real requests. This page is the
 * reference manual — better for reading the API end to end and comparing
 * schemas side by side. Both load /api/openapi.yaml, so the two can never
 * disagree with each other. The slim bar at the top of the page links across.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.1.0
 * @since      v1.1.0 — Phase 7 (#75)
 *
 * 📖 References:
 *     - OpenAPI spec:  web/Go2My.Link/public_html/api/openapi.yaml
 *     - Redoc:         https://github.com/Redocly/redoc
 *     - Scoped CSP:    web/Go2My.Link/public_html/api/docs/.htaccess
 *     - Sister page:   web/Go2My.Link/public_html/api/docs/swagger/index.php
 * ============================================================================
 */
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Go2My.Link — API Documentation</title>
    <meta name="description" content="Interactive OpenAPI 3.1 reference for the Go2My.Link public API (/api/v1/*).">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/svg+xml" href="/img/logo.svg">
    <style>
        html, body
        {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #ffffff;
        }

        #g2ml-api-docs-loading
        {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #6c757d;
            text-align: center;
            padding: 3rem 1rem;
        }

        /* -----------------------------------------------------------------
           A slim bar above the reference, pointing at the other two ways of
           reading the same specification. It sits in normal document flow so
           it cannot overlap or fight with Redoc's own layout below it.
           ----------------------------------------------------------------- */
        .g2ml-docs-nav
        {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0d6efd;
            color: #ffffff;
            padding: 0.6rem 1rem;
            font-size: 0.92rem;
        }

        .g2ml-docs-nav a
        {
            color: #ffffff;
            text-decoration: underline;
            margin-right: 1.25rem;
            display: inline-block;
            padding: 0.15rem 0;
        }

        .g2ml-docs-nav a:hover,
        .g2ml-docs-nav a:focus
        {
            text-decoration: none;
        }

        .g2ml-docs-nav a:focus-visible
        {
            outline: 3px solid #ffffff;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <nav class="g2ml-docs-nav" aria-label="API documentation views">
        <a href="/api/docs/swagger/">⚙️ Try the API (Swagger UI console)</a>
        <a href="/api/openapi.yaml" download="go2my-link-openapi.yaml">⬇️ Download the specification</a>
    </nav>

    <div id="g2ml-api-docs-container">
        <p id="g2ml-api-docs-loading">Loading the Go2My.Link API documentation…</p>
    </div>

    <script src="/api/docs/vendor/redoc.standalone.js"></script>
    <script src="/api/docs/init.js"></script>
</body>
</html>
