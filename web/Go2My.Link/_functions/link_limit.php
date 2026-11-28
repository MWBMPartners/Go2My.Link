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
 * 🔢 Go2My.Link — The limit on switched-on links (#327, decision D-024)
 * ============================================================================
 *
 * Every place that adds to the number of switched-on links asks here first:
 * createShortURL() and the dashboard's edit page. The API creates links only
 * through createShortURL() and has no way to switch a link back on.
 *
 * WHO IS COUNTED
 *   - A link in an organisation counts against that organisation's plan,
 *     shared by its members.
 *   - A link in no organisation (the '[default]' organisation) that has an
 *     owner counts against that person alone, and the number is the Free
 *     plan's. "The Free plan" is the plan an unknown organisation falls back
 *     to (the lowest sort order among active plans), so no plan name is written
 *     here and the pricing engine's switch is honoured either way.
 *   - Nobody is counted for a link with no owner: shortening on the home page
 *     without an account is unchanged. An organisation member's signed-in
 *     home-page link goes outside the organisation, so it counts against that
 *     member's own allowance (D-035).
 *   - Global administrators are not limited: neither while one is signed in
 *     and acting, nor for any link a global administrator owns, in an
 *     organisation or not (which covers the API, where nobody is signed in).
 *
 * "Switched on" is isActive = 1, so a link past its end date still counts. A new
 * link is refused at the limit even if it is to be created switched off.
 *
 * TWO REQUESTS AT ONCE
 *   Counting and writing happen while holding a database lock named after the
 *   person or organisation, so two requests cannot both take the last place.
 *   A request that cannot get the lock within a few seconds is refused with a
 *   "try again" message. Nothing is locked for a plan with no limit.
 *
 * WHEN SOMETHING CANNOT BE LOOKED UP (D-037)
 *   If the plan, the owner or the count cannot be read, the link is ALLOWED and
 *   a line is written to the server's error log. This is the same rule for
 *   people and for organisations, and the house rule from #146: a fault in plan
 *   checking does not stop a legitimate action. A failed lock query is also
 *   logged, but does not by itself allow the link: the count is still made,
 *   without the lock, and the link is refused if the limit is reached.
 *
 * WHAT IS NOT CHECKED HERE
 *   Removing a staff block (#325) puts a link back as it was (D-026) without
 *   counting, so it can leave an owner above the limit (D-036).
 *
 * Dependencies: db_query.php, entitlements.php (and pricing.php when loaded)
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    1.0.0
 * @since      v1.0.0 — Launch (#327)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * How long a request waits for another one to finish, in seconds.
 */
const G2ML_LINK_LOCK_WAIT_SECONDS = 5;

/**
 * The database lock's name, worked out in SQL from one bound value. It includes
 * the database's name because lock names are shared by the whole database
 * server, and it is hashed to stay within the 64-character limit.
 */
const G2ML_LINK_LOCK_NAME_SQL = "CONCAT('g2ml_links_', LEFT(SHA2(CONCAT(DATABASE(), '|', ?), 256), 40))";

/**
 * Ask whether one more switched-on link is allowed, and hold the place.
 *
 * When 'allowed' is true, write the link and then ALWAYS call
 * g2ml_linkLimitClose() with the same answer.
 *
 * @param  string   $orgHandle     The organisation the link belongs to.
 * @param  int|null $ownerUserUID  The person who owns the link; null for none.
 * @return array{allowed: bool, reason: string, limit: int|null, personal: bool, lockScope: string}
 *         reason is '', 'limit' (the limit is reached) or 'busy' (another
 *         request held the lock for too long).
 */
#[\NoDiscard('the link must not be written unless this says allowed')]
function g2ml_linkLimitOpen(string $orgHandle, ?int $ownerUserUID): array
{
    $gate = [
        'allowed'   => true,
        'reason'    => '',
        'limit'     => null,
        'personal'  => false,
        'lockScope' => '',
    ];

    if (!function_exists('g2ml_getOrgTier') || !function_exists('g2ml_checkLimit'))
    {
        error_log('[Go2My.Link] ERROR: link limit — entitlements.php is not loaded, so the limit was not checked for org: ' . $orgHandle);

        return $gate;
    }

    // A link with no owner outside any organisation is not counted at all.
    $hasOwner = ($ownerUserUID !== null && $ownerUserUID > 0);

    if ($orgHandle === '[default]' && !$hasOwner)
    {
        return $gate;
    }

    // A global administrator's links are never limited, wherever they are.
    // Looked up from the owner because the API has no signed-in session.
    if ($hasOwner)
    {
        $ownerRow = dbSelectOne(
            'SELECT `role` FROM `tblUsers` WHERE `userUID` = ? LIMIT 1',
            'i',
            [$ownerUserUID]
        );

        if ($ownerRow === false)
        {
            error_log('[Go2My.Link] ERROR: link limit — could not look up user ' . $ownerUserUID . ' — allowing the link (fail OPEN).');

            return $gate;
        }

        if (is_array($ownerRow) && $ownerRow['role'] === 'GlobalAdmin')
        {
            return $gate;
        }
    }

    if ($orgHandle === '[default]')
    {
        $gate['personal'] = true;

        // '' is no organisation at all, which resolves to the Free plan.
        $planHandle = '';
        $lockScope  = 'user|' . $ownerUserUID;
    }
    else
    {
        $planHandle = $orgHandle;
        $lockScope  = 'org|' . strtolower($orgHandle);
    }

    $tier = g2ml_getOrgTier($planHandle);

    if (!isset($tier['maxLinks']))
    {
        // No limit on this plan, a global administrator is acting, or the plan
        // could not be read (entitlements.php has then logged why).
        return $gate;
    }

    $gate['limit'] = (int) $tier['maxLinks'];

    $lockRow = dbSelectOne(
        'SELECT GET_LOCK(' . G2ML_LINK_LOCK_NAME_SQL . ', ?) AS `gotLock`',
        'si',
        [$lockScope, G2ML_LINK_LOCK_WAIT_SECONDS]
    );

    if (!is_array($lockRow) || $lockRow['gotLock'] === null)
    {
        error_log('[Go2My.Link] ERROR: link limit — the database lock query failed for ' . $lockScope . ' — checking without it.');
    }
    elseif ((int) $lockRow['gotLock'] === 1)
    {
        $gate['lockScope'] = $lockScope;
    }
    else
    {
        error_log('[Go2My.Link] WARNING: link limit — timed out waiting for the lock for ' . $lockScope . '.');

        $gate['allowed'] = false;
        $gate['reason']  = 'busy';

        return $gate;
    }

    if ($gate['personal'] === true)
    {
        $countRow = dbSelectOne(
            "SELECT COUNT(*) AS `cnt` FROM `tblShortURLs` WHERE `createdByUserUID` = ? AND `orgHandle` = '[default]' AND `isActive` = 1",
            'i',
            [$ownerUserUID]
        );
    }
    else
    {
        $countRow = dbSelectOne(
            'SELECT COUNT(*) AS `cnt` FROM `tblShortURLs` WHERE `orgHandle` = ? AND `isActive` = 1',
            's',
            [$orgHandle]
        );
    }

    if (is_array($countRow))
    {
        $activeCount = (int) $countRow['cnt'];
    }
    else
    {
        error_log('[Go2My.Link] WARNING: link limit — could not count switched-on links for ' . $lockScope . ' — treating as 0 (fail OPEN).');
        $activeCount = 0;
    }

    $check = g2ml_checkLimit($planHandle, 'maxLinks', $activeCount);

    if ($check['allowed'] === false)
    {
        g2ml_linkLimitClose($gate);

        $gate['allowed']   = false;
        $gate['reason']    = 'limit';
        $gate['lockScope'] = '';
    }

    return $gate;
}

/**
 * Let go of the place held by g2ml_linkLimitOpen(). Safe to call when nothing
 * is held.
 *
 * @param  array $gate  The answer from g2ml_linkLimitOpen() or
 *                      g2ml_linkLimitOpenForSwitchOn().
 * @return void
 */
function g2ml_linkLimitClose(array $gate): void
{
    $lockScope = (string) ($gate['lockScope'] ?? '');

    if ($lockScope === '')
    {
        return;
    }

    dbSelectOne(
        'SELECT RELEASE_LOCK(' . G2ML_LINK_LOCK_NAME_SQL . ') AS `released`',
        's',
        [$lockScope]
    );
}

/**
 * Ask whether one existing link may be switched back on.
 *
 * Only a link that is switched off now, and not blocked by staff, can need a
 * place. 'switchOn' is true only then and only when the limit allows it: the
 * caller must change isActive to 1 only when 'switchOn' is true, so a link that
 * looked switched on (and so was not counted) is never switched on by a save
 * that arrives after somebody switched it off. Always call
 * g2ml_linkLimitClose() with the answer afterwards.
 *
 * @param  int $urlUID
 * @return array{allowed: bool, reason: string, limit: int|null, personal: bool, lockScope: string, switchOn: bool}
 */
#[\NoDiscard('the link may be switched on only when switchOn is true')]
function g2ml_linkLimitOpenForSwitchOn(int $urlUID): array
{
    $nothingToDo = [
        'allowed'   => true,
        'reason'    => '',
        'limit'     => null,
        'personal'  => false,
        'lockScope' => '',
        'switchOn'  => false,
    ];

    $linkSQL = 'SELECT `orgHandle`, `createdByUserUID`, `isActive`, `staffDisabledAt` FROM `tblShortURLs` WHERE `urlUID` = ? LIMIT 1';
    $link    = dbSelectOne($linkSQL, 'i', [$urlUID]);

    if ($link === false)
    {
        error_log('[Go2My.Link] ERROR: link limit — could not read link ' . $urlUID . ' — allowing it to be switched on (fail OPEN).');

        $nothingToDo['switchOn'] = true;

        return $nothingToDo;
    }

    if ($link === null || (int) $link['isActive'] === 1 || $link['staffDisabledAt'] !== null)
    {
        return $nothingToDo;
    }

    $ownerUserUID = null;

    if ($link['createdByUserUID'] !== null)
    {
        $ownerUserUID = (int) $link['createdByUserUID'];
    }

    $gate             = g2ml_linkLimitOpen((string) $link['orgHandle'], $ownerUserUID);
    $gate['switchOn'] = $gate['allowed'];

    // Read again: another request may have changed the link while this one
    // waited for the lock.
    $linkNow = dbSelectOne($linkSQL, 'i', [$urlUID]);

    if (is_array($linkNow) && (int) $linkNow['isActive'] === 1)
    {
        g2ml_linkLimitClose($gate);

        return $nothingToDo;
    }

    return $gate;
}

/**
 * The words shown when a link may not be created or switched on.
 *
 * @param  array  $gate    A refused answer from one of the functions above.
 * @param  string $action  'create' or 'switch_on'.
 * @return string          Plain text, not escaped.
 */
function g2ml_linkLimitMessage(array $gate, string $action): string
{
    $replacements = ['limit' => (string) ($gate['limit'] ?? '')];

    if ($gate['reason'] === 'busy')
    {
        if ($action === 'switch_on')
        {
            $key     = 'edit_link.error_switch_on_busy';
            $english = 'Your other changes were saved, but the link was not switched on: other links were being saved at the same moment. Please try again.';
        }
        else
        {
            $key     = 'links.error_limit_busy';
            $english = 'The link was not created: other links were being saved at the same moment. Please try again.';
        }
    }
    elseif ($action === 'switch_on')
    {
        if ($gate['personal'] === true)
        {
            $key     = 'edit_link.error_switch_on_limit_personal';
            $english = 'Your other changes were saved, but the link was not switched on. You have reached the Free plan\'s limit of {limit} switched-on links of your own. To make room, switch off a link you no longer need. Paid plans are coming and cannot be bought yet.';
        }
        else
        {
            $key     = 'edit_link.error_switch_on_limit_org';
            $english = 'Your other changes were saved, but the link was not switched on. Your organisation has reached its plan\'s limit of {limit} switched-on links. To make room, switch off a link that is no longer needed. Paid plans are coming and cannot be bought yet.';
        }
    }
    elseif ($gate['personal'] === true)
    {
        $key     = 'links.error_personal_limit';
        $english = 'You have reached the Free plan\'s limit of {limit} switched-on links of your own. To make room, switch off a link you no longer need (a link past its end date still counts until it is switched off). Paid plans are coming and cannot be bought yet.';
    }
    else
    {
        // The organisation message from seed 082 (#209).
        $key     = 'links.error_plan_limit';
        $english = 'You have reached your plan\'s link limit. To make room, turn off a link that is no longer needed (a link past its end date still counts until it is turned off). Paid plans are coming and cannot be bought yet.';
    }

    if (function_exists('__'))
    {
        return __($key, $replacements);
    }

    return str_replace('{limit}', $replacements['limit'], $english);
}
