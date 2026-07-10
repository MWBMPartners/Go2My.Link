/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📖 Go2My.Link — API Docs Bootstrap (Redoc, #75)
 * ============================================================================
 *
 * Initialises the vendored Redoc standalone bundle (vendor/redoc.standalone.js)
 * against the published OpenAPI 3.1 spec at /api/openapi.yaml.
 *
 * Kept as its own same-origin file (rather than an inline <script> block in
 * index.php) so the component's strict script-src CSP never needs
 * 'unsafe-inline' for this page — see .htaccess in this directory for the
 * scoped CSP relaxation that this page otherwise still requires (worker-src
 * blob: for Redoc's search-index Web Worker, style-src 'unsafe-inline' for
 * its CSS-in-JS theming).
 *
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#75)
 *
 * 📖 References:
 *     - Redoc:     https://github.com/Redocly/redoc
 *     - Redoc.init: https://github.com/Redocly/redoc#redocinit
 * ============================================================================
 */

'use strict';

document.addEventListener('DOMContentLoaded', function ()
{
    var specUrl       = '/api/openapi.yaml';
    var containerNode = document.getElementById('g2ml-api-docs-container');

    if (containerNode === null)
    {
        return;
    }

    if (typeof Redoc === 'undefined')
    {
        containerNode.textContent = 'The API documentation viewer failed to load. Please refresh the page or contact support@go2my.link.';

        return;
    }

    var redocOptions = {
        hideDownloadButton:  false,
        expandResponses:     '200,201',
        pathInMiddlePanel:   true,
        theme: {
            colors: {
                primary: {
                    main: '#0d6efd'
                }
            }
        }
    };

    Redoc.init(specUrl, redocOptions, containerNode, function (errors)
    {
        if (errors)
        {
            containerNode.textContent = 'Failed to load the API specification. Please refresh the page or contact support@go2my.link.';
        }
    });
});
