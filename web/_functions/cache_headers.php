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
 * 🗄️ Go2My.Link — Which Pages Must Not Be Stored (#213)
 * ============================================================================
 *
 * A private page must not be saved by the browser or by anything between the
 * server and the browser. Otherwise the next person on a shared computer can
 * press "Back" after sign-out and read it. These pages are sent with
 * "Cache-Control: no-store, private":
 *
 *   - every response to a visitor who arrives signed in, on any component;
 *   - every dashboard (admin.go2my.link) response, signed in or not;
 *   - every page opened from a link that carries a one-time token (password
 *     reset, email verification, invitation), and the sign-in pages that
 *     carry such a link onwards.
 *
 * Other public pages are left as they were, and static files (CSS, images,
 * scripts) are sent by the web server without running PHP at all.
 *
 * PHP's own "session cache limiter": session_start() sends its own
 * Cache-Control, Pragma and Expires headers, chosen by the server setting
 * session.cache_limiter. The usual default says "no-store" but not "private",
 * and a server can be set to "public" or "private", which let pages be saved.
 * So the headers here are sent AFTER session_start(), where they replace the
 * limiter's. We do not change the setting itself, so public pages behave as
 * before. (The limiter may still leave its own Expires header behind;
 * Cache-Control takes priority over Expires.)
 *
 * A later header() call with the same name replaces these, so any response
 * that sets its own Cache-Control (the downloads, for example) must also say
 * "no-store, private".
 *
 * 📖 Reference: https://www.php.net/manual/en/function.session-cache-limiter.php
 * 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
 *
 * Dependencies: none
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.0.0 — Launch Hardening (#213)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * PURE — does the address of this request carry a one-time token?
 *
 *   - ?token=     password reset, email verification, invitation
 *   - ?forced=    the forced password reset, which takes its token from the
 *                 session and writes it into the page's form
 *   - ?redirect=  the sign-in page, when it carries a token link onwards (an
 *                 invitation opened by someone who is not signed in yet)
 *
 * @param  array $query  The query string values (normally $_GET)
 * @return bool
 */
function g2ml_requestCarriesOneTimeToken(array $query): bool
{
    if (isset($query['token']) || isset($query['forced']))
    {
        return true;
    }

    if (isset($query['redirect']) && is_string($query['redirect']))
    {
        if (strpos($query['redirect'], 'token=') !== false)
        {
            return true;
        }
    }

    return false;
}

/**
 * PURE — the headers that stop this response being stored, or none.
 *
 * @param  bool   $isSignedIn  The visitor arrived with a signed-in session
 * @param  string $component   G2ML_COMPONENT ('A', 'B', 'C' or 'Admin')
 * @param  array  $query       The query string values (normally $_GET)
 * @return array<string, string>  Header name => value; empty for a public page
 */
function g2ml_privatePageCacheHeaders(bool $isSignedIn, string $component, array $query = []): array
{
    $isPrivate = false;

    if ($isSignedIn === true || $component === 'Admin')
    {
        $isPrivate = true;
    }
    elseif (g2ml_requestCarriesOneTimeToken($query))
    {
        $isPrivate = true;
    }

    if ($isPrivate === false)
    {
        return [];
    }

    return [
        'Cache-Control' => 'no-store, private',
        'Pragma'        => 'no-cache',
    ];
}

/**
 * Send those headers for the current request. Call after session_start().
 *
 * @return void
 */
function g2ml_sendPrivatePageCacheHeaders(): void
{
    if (headers_sent())
    {
        return;
    }

    $isSignedIn = false;

    if (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] > 0)
    {
        $isSignedIn = true;
    }

    $component = '';

    if (defined('G2ML_COMPONENT'))
    {
        $component = (string) G2ML_COMPONENT;
    }

    // Set by a dashboard address that runs a main-site file (/api/consent).
    if (isset($GLOBALS['g2mlAdminArea']) && $GLOBALS['g2mlAdminArea'] === true)
    {
        $component = 'Admin';
    }

    foreach (g2ml_privatePageCacheHeaders($isSignedIn, $component, $_GET) as $name => $value)
    {
        header($name . ': ' . $value);
    }
}
