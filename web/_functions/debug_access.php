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
 * 🔏 Go2My.Link — Who May See Debug Output (#319)
 * ============================================================================
 *
 * One rule, on every web address, including alpha., beta. and local ones: the
 * debug panel and any error details on screen are shown only to a signed-in
 * global administrator. Everyone else sees a normal page.
 * Decision: docs/decisions/D-011-debug-panel-global-admin.md
 *
 * The rule "fails closed": if we cannot prove who the visitor is (no session,
 * database down, an error early in the request), nothing is shown.
 *
 * On alpha, beta and local addresses a global administrator always gets debug
 * output. On the live site they must also add ?debug=true to the address.
 *
 * Dependencies: db_query.php (dbSelectOne())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.0.0 — Launch Hardening (#319)
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
 * Return the user number of the signed-in global administrator, or 0 when the
 * visitor is not one or this cannot be checked.
 *
 * The answer comes from the database, not from the role copied into the PHP
 * session at sign-in, so an administrator who has since been demoted,
 * suspended or signed out gets nothing. A visitor with no sign-in session
 * costs no database query.
 *
 * @return int  The userUID, or 0
 */
function g2ml_signedInGlobalAdminUID(): int
{
    if (!isset($_SESSION['user_uid']) || (int) $_SESSION['user_uid'] <= 0)
    {
        return 0;
    }

    if (!isset($_SESSION['session_token']) || !is_string($_SESSION['session_token']) || $_SESSION['session_token'] === '')
    {
        return 0;
    }

    if (!function_exists('dbSelectOne'))
    {
        return 0;
    }

    $sessionUserUID = (int) $_SESSION['user_uid'];

    try
    {
        $row = dbSelectOne(
            "SELECT `u`.`userUID`
             FROM `tblUserSessions` AS `s`
             INNER JOIN `tblUsers` AS `u` ON `u`.`userUID` = `s`.`userUID`
             WHERE `s`.`sessionToken` = ?
               AND `s`.`userUID` = ?
               AND `s`.`isActive` = 1
               AND `s`.`expiresAt` > NOW()
               AND `u`.`role` = 'GlobalAdmin'
               AND `u`.`isActive` = 1
               AND `u`.`isSuspended` = 0
             LIMIT 1",
            'si',
            [hash('sha256', $_SESSION['session_token']), $sessionUserUID]
        );
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] WARNING: could not check for a global administrator, so debug output stays off: ' . $e->getMessage());
        return 0;
    }

    if (!is_array($row) || !isset($row['userUID']) || (int) $row['userUID'] !== $sessionUserUID)
    {
        return 0;
    }

    return $sessionUserUID;
}

/**
 * Say whether this request asks for debug output, given who is already known to
 * be a global administrator. This does not decide who the visitor is.
 *
 * @param  string $environment     'alpha', 'beta', 'local' or 'production'
 * @param  mixed  $debugParameter  The ?debug= value from the address, if any
 * @return bool
 */
function g2ml_debugWanted(string $environment, mixed $debugParameter): bool
{
    if (in_array($environment, ['alpha', 'beta', 'local'], true))
    {
        return true;
    }

    return ($debugParameter === 'true');
}

/**
 * Decide debug mode for this request: define G2ML_DEBUG and, for a global
 * administrator only, let PHP show errors on screen.
 *
 * Call it once, after the session has been started and checked.
 *
 * @param  string $environment     'alpha', 'beta', 'local' or 'production'
 * @param  mixed  $debugParameter  The ?debug= value from the address, if any
 * @return void
 */
function g2ml_applyDebugMode(string $environment, mixed $debugParameter): void
{
    $adminUID = 0;

    if (g2ml_debugWanted($environment, $debugParameter))
    {
        $adminUID = g2ml_signedInGlobalAdminUID();
    }

    $GLOBALS['_g2ml_debug_user_uid'] = $adminUID;

    define('G2ML_DEBUG', $adminUID > 0);

    if ($adminUID > 0 && function_exists('ini_set'))
    {
        // 📖 Reference: https://www.php.net/manual/en/function.ini-set.php
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }
}

/**
 * Say whether debug output may be printed right now.
 *
 * Checked again at the moment of printing, because the administrator may have
 * signed out during this request. It needs no database, so it also works while
 * reporting a database failure.
 *
 * @return bool
 */
function g2ml_debugOutputAllowed(): bool
{
    if (!defined('G2ML_DEBUG') || G2ML_DEBUG !== true)
    {
        return false;
    }

    $adminUID = (int) ($GLOBALS['_g2ml_debug_user_uid'] ?? 0);

    if ($adminUID <= 0)
    {
        return false;
    }

    return ((int) ($_SESSION['user_uid'] ?? 0) === $adminUID);
}
