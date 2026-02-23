<?php
/**
 * ============================================================================
 * 🚪 GoToMyLink — Logout Page (Component A)
 * ============================================================================
 *
 * Handles user logout. GET request → destroy session → redirect to homepage.
 * No form needed — this is a simple action endpoint.
 *
 * @package    GoToMyLink
 * @subpackage ComponentA
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('logout.title') : 'Logging Out';
$pageDesc  = function_exists('__') ? __('logout.description') : 'You are being logged out.';

// Perform the logout
if (function_exists('logoutUser'))
{
    logoutUser();
}

// Redirect to homepage
header('Location: https://go2my.link/?logged_out=1');
exit;
