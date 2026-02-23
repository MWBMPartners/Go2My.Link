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
 * 🚪 Go2My.Link — Logout Page (Component A)
 * ============================================================================
 *
 * Handles user logout. GET request → destroy session → redirect to homepage.
 * No form needed — this is a simple action endpoint.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('logout.title');
} else {
    $pageTitle = 'Logging Out';
}
if (function_exists('__')) {
    $pageDesc = __('logout.description');
} else {
    $pageDesc = 'You are being logged out.';
}

// Perform the logout
if (function_exists('logoutUser'))
{
    logoutUser();
}

// Redirect to homepage
header('Location: https://go2my.link/?logged_out=1');
exit;
