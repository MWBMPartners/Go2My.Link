<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

declare(strict_types=1);

/**
 * ============================================================================
 * 🔍 Go2My.Link — Info Page Display Helpers (Component A)
 * ============================================================================
 *
 * Pure, dependency-free presentation logic for the public short-code preview
 * page (pages/info/index.php). Kept in its own file so it can be unit-tested
 * without a database connection or an active session.
 *
 * Functions:
 *   - g2ml_infoDisplayDestination()  — Decide the destination string to show,
 *                                       masked for anonymous viewers and full
 *                                       for authenticated viewers (FG-003 / #23)
 *
 * Dependencies: none (uses only core PHP string / URL functions).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      autopilot COMPLETE (FG-003 / #23)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔍 Destination display decision (public-vs-authenticated view) — FG-003 / #23
// ============================================================================
// A short URL redirects publicly, so its destination is NOT a secret. The
// public preview nonetheless masks the path so a casual paste of a link does
// not leak deep-link details at a glance; an authenticated viewer sees the
// FULL destination, which is the documented, intended behaviour.
//
// This function is deliberately pure: given the already-loaded link row and a
// boolean authentication flag, it returns the exact string the page should
// display. It performs NO escaping — the caller escapes the returned value at
// the point of output with g2ml_sanitiseOutput().
//
// 📖 Reference: https://www.php.net/manual/en/function.parse-url.php
// ============================================================================

if (!function_exists('g2ml_infoDisplayDestination'))
{
    /**
     * Resolve the destination string to display on the info page.
     *
     * For an authenticated viewer the full destination URL is returned. For an
     * anonymous viewer the destination domain is returned with a leading
     * "www." stripped and any non-empty path masked as "/...". When the row
     * carries no usable destination, an empty string is returned so the caller
     * can omit the field entirely.
     *
     * @param  array $linkData          The loaded short-URL row. Only the
     *                                   'destinationURL' key is consulted.
     * @param  bool  $isAuthenticated   True when the viewer is signed in.
     * @return string                   The (unescaped) destination string to
     *                                   display, or '' when none is available.
     */
    function g2ml_infoDisplayDestination(array $linkData, bool $isAuthenticated): string
    {
        // No destination on the row — nothing to display.
        if (!isset($linkData['destinationURL']))
        {
            return '';
        }

        $destinationURL = (string) $linkData['destinationURL'];

        if ($destinationURL === '')
        {
            return '';
        }

        // Authenticated viewers see the full, unmasked destination.
        if ($isAuthenticated === true)
        {
            return $destinationURL;
        }

        // Anonymous viewers see the domain with the path masked for privacy.
        $parsed = parse_url($destinationURL);

        if (is_array($parsed) && isset($parsed['host']))
        {
            $host = $parsed['host'];
        }
        else
        {
            $host = '';
        }

        // Strip a leading "www." for a cleaner display.
        if (strpos($host, 'www.') === 0)
        {
            $host = substr($host, 4);
        }

        if (is_array($parsed) && isset($parsed['path']))
        {
            $path = $parsed['path'];
        }
        else
        {
            $path = '';
        }

        if ($path !== '' && $path !== '/')
        {
            return $host . '/...';
        }

        return $host;
    }
}
