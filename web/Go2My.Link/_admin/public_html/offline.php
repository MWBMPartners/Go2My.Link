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
 * 📴 Go2My.Link — Offline Page (Admin Dashboard)
 * ============================================================================
 *
 * Served at /offline on the dashboard, for its own service worker (#283). It sets up
 * this site like its front controller does (credentials, component
 * constants, page_init.php), then prints the shared page in
 * web/_includes/offline_page.php.
 *
 * @package    Go2My.Link
 * @subpackage PWA
 * @version    1.0.0
 * @since      v1.0.0 — Launch Hardening (#283)
 * ============================================================================
 */

$componentAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '.auth'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}

define('G2ML_COMPONENT',        'Admin');
define('G2ML_COMPONENT_NAME',   'Admin Dashboard');
define('G2ML_COMPONENT_DOMAIN', 'admin.go2my.link');
define('G2ML_ROOT', dirname(__DIR__, 3));

require_once G2ML_ROOT . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'page_init.php';
require G2ML_ROOT . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'offline_page.php';
