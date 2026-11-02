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
 * 🍪 Go2My.Link — Cookie Consent Endpoint (Admin Dashboard)
 * ============================================================================
 *
 * POST /api/consent/ on admin.go2my.link
 *
 * The cookie banner's script (js/cookie-consent.js) sends the visitor's
 * choice to "/api/consent/" on whatever site it is running on. The real
 * endpoint lives on the main site, so on the dashboard that address would
 * give "page not found" and the banner would do nothing.
 *
 * This file simply runs the main site's endpoint, so there is one copy of
 * the logic. The main site's file finds its own files from its own folder
 * and sets up everything it needs, so nothing is defined here first.
 *
 * The request goes to the dashboard's own address, so it carries the
 * dashboard's session cookie, and the security token the banner was given is
 * found in that same session.
 *
 * Folder depth, counting up from this file: api/consent -> api ->
 * public_html (dashboard) -> _admin -> Go2My.Link. The main site's
 * public_html folder sits beside _admin inside Go2My.Link.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Dashboard fixes (#279)
 * ============================================================================
 */

$mainSiteConsentEndpoint = dirname(__DIR__, 4)
    . DIRECTORY_SEPARATOR . 'public_html'
    . DIRECTORY_SEPARATOR . 'api'
    . DIRECTORY_SEPARATOR . 'consent'
    . DIRECTORY_SEPARATOR . 'index.php';

if (!file_exists($mainSiteConsentEndpoint))
{
    error_log('[Go2My.Link] Cookie consent endpoint not found at: ' . $mainSiteConsentEndpoint);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Consent endpoint unavailable']);
    exit;
}

require $mainSiteConsentEndpoint;
