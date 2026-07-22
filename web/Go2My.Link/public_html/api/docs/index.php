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
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#75)
 *
 * 📖 References:
 *     - OpenAPI spec:  web/Go2My.Link/public_html/api/openapi.yaml
 *     - Redoc:         https://github.com/Redocly/redoc
 *     - Scoped CSP:    web/Go2My.Link/public_html/api/docs/.htaccess
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
    </style>
</head>
<body>
    <div id="g2ml-api-docs-container">
        <p id="g2ml-api-docs-loading">Loading the Go2My.Link API documentation…</p>
    </div>

    <script src="/api/docs/vendor/redoc.standalone.js"></script>
    <script src="/api/docs/init.js"></script>
</body>
</html>
