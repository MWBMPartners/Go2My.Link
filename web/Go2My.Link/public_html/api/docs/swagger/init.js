/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📖 Go2My.Link — API Console Bootstrap (Swagger UI)
 * ============================================================================
 *
 * Starts the vendored Swagger UI bundle against the published OpenAPI 3.1
 * document at /api/openapi.yaml.
 *
 * This is kept as its own file, loaded with a normal <script src>, rather than
 * an inline <script> block inside index.php. That is not a style preference —
 * it is what allows this directory's Content-Security-Policy to stay at a
 * strict `script-src 'self'` with no 'unsafe-inline' anywhere. The moment any
 * inline script appears on the page, that policy would have to be loosened for
 * every script on it.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7
 *
 * 📖 References:
 *     - Swagger UI configuration: https://swagger.io/docs/open-source-tools/swagger-ui/usage/configuration/
 *     - Deep linking:             https://swagger.io/docs/open-source-tools/swagger-ui/usage/deep-linking/
 * ============================================================================
 */

'use strict';

document.addEventListener('DOMContentLoaded', function ()
{
    var specUrl       = '/api/openapi.yaml';
    var containerNode = document.getElementById('g2ml-swagger-ui');

    if (containerNode === null)
    {
        return;
    }

    // ------------------------------------------------------------------
    // If the vendored bundle failed to download (a broken upload, a
    // mis-set MIME type, an over-eager ad blocker), say so in plain words
    // rather than leaving the visitor looking at a permanently "loading"
    // page with no explanation.
    // ------------------------------------------------------------------
    if (typeof SwaggerUIBundle === 'undefined')
    {
        containerNode.textContent = 'The API console failed to load. Please refresh the page, or read the reference manual at /api/docs/ instead. If it keeps happening, contact support@go2my.link.';

        return;
    }

    var swaggerOptions = {
        url:    specUrl,
        domNode: containerNode,

        // ------------------------------------------------------------------
        // Only the core preset is loaded. The "standalone" preset is
        // deliberately NOT vendored: it adds a top bar in which a visitor can
        // type the address of any specification they like, and this page is
        // meant to document one API — ours. Leaving it out also keeps 267 KB
        // off every page load.
        // ------------------------------------------------------------------
        presets: [
            SwaggerUIBundle.presets.apis
        ],

        layout: 'BaseLayout',

        // ------------------------------------------------------------------
        // A key pasted into the Authorize dialog stays in this tab's memory
        // only. Turning this on would write it to the browser's local storage,
        // where it would survive the tab closing and be readable by any script
        // on the same origin. An API key is a live credential, so it does not
        // get stored.
        // ------------------------------------------------------------------
        persistAuthorization: false,

        // ------------------------------------------------------------------
        // Put the endpoint's own address in the browser's address bar as the
        // visitor scrolls, so a link to one specific endpoint can be shared.
        // ------------------------------------------------------------------
        deepLinking: true,

        // ------------------------------------------------------------------
        // Show every endpoint collapsed on arrival. The API has enough
        // operations that expanding them all makes the page hard to scan.
        // ------------------------------------------------------------------
        docExpansion: 'list',

        // Hide the schema explorer's "expand everything" default, which is
        // overwhelming on the larger request bodies.
        defaultModelsExpandDepth:   1,
        defaultModelExpandDepth:    2,
        defaultModelRendering:      'example',

        // Show how long each test request took — useful when someone is
        // checking whether the rate limiter or a slow query is biting.
        displayRequestDuration: true,

        // Let visitors filter a long operation list by typing a tag name.
        filter: true,

        // ------------------------------------------------------------------
        // Which HTTP methods get a working "Try it out" button.
        //
        // Read-only calls (GET) are listed first because they are the safe
        // ones to experiment with. POST, PUT and DELETE are included too —
        // this is a real console, and a partner integrating against us needs
        // to be able to create and remove a test link — but note that every
        // one of those calls is a genuine change to real data belonging to
        // whichever key was pasted in. There is no sandbox.
        //
        // To turn the console into a read-only reference (for example on a
        // public marketing site), reduce this list to ['get'], or set it to
        // an empty array [] to remove the button entirely.
        // ------------------------------------------------------------------
        supportedSubmitMethods: ['get', 'post', 'put', 'delete'],

        // ------------------------------------------------------------------
        // Sort operations by path rather than by however they happen to appear
        // in the specification file, so the list stays stable as the spec
        // grows.
        // ------------------------------------------------------------------
        operationsSorter: 'alpha',
        tagsSorter:       'alpha',

        // ------------------------------------------------------------------
        // Report a specification that will not load, in words a human can act
        // on. Without this the page just sits blank.
        // ------------------------------------------------------------------
        onComplete: function ()
        {
            var loadingNode = document.getElementById('g2ml-swagger-loading');

            if (loadingNode !== null)
            {
                loadingNode.remove();
            }
        },

        requestInterceptor: function (request)
        {
            // ------------------------------------------------------------------
            // Every request the console sends carries a header saying where it
            // came from. That lets the API's audit log tell an exploratory call
            // made from this documentation page apart from a call made by a real
            // integration, which matters when someone is reading the logs trying
            // to work out why a key's rate limit was consumed.
            // ------------------------------------------------------------------
            request.headers['X-G2ML-Client'] = 'swagger-ui-console';

            return request;
        }
    };

    // ------------------------------------------------------------------
    // Swagger UI throws synchronously if the specification cannot be parsed
    // at all (a truncated upload, a YAML syntax error). Catch it so the page
    // shows a readable message rather than an empty white screen with the
    // detail hidden in the developer console.
    // ------------------------------------------------------------------
    try
    {
        window.g2mlSwaggerUi = SwaggerUIBundle(swaggerOptions);
    }
    catch (error)
    {
        containerNode.textContent = 'The API specification could not be read, so the console cannot start. Please try the reference manual at /api/docs/ instead, or contact support@go2my.link.';
    }
});
