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
 * 🛡️ Go2My.Link — Staff: find any short link and block it (#325)
 * ============================================================================
 *
 * Owner decisions D-023 and D-026. These functions work across ALL organisations, so they
 * must only be called after the caller has checked that the signed-in person is
 * a GlobalAdmin (see web/Go2My.Link/_admin/public_html/pages/staff/links/).
 * None of them checks that itself.
 *
 * Switching a link off sets isActive = 0 and records the block in
 * staffDisabledAt. sp_lookupShortURL then neither redirects nor uses a fallback
 * address. While that column is set the database check
 * CK_url_staff_disabled_off refuses any attempt to switch the link on.
 *
 * Removing the block puts the link back as it was before (D-026):
 * staffPreviousIsActive holds that state, and the places that switch a link off
 * for its owner (My Links, the edit page, the API, account erasure) set it to 0
 * on a blocked link, so such a link stays off.
 *
 * Dependencies: db_query.php, activity_logger.php
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#325)
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
 * The longest reason kept, in characters (tblShortURLs.staffDisableReason).
 */
const G2ML_STAFF_REASON_MAX_LENGTH = 500;

/**
 * The columns and joins every staff look-up returns. A fixed text: nothing from
 * a request is ever put into it.
 */
const G2ML_STAFF_LINK_SELECT = 'SELECT `s`.`urlUID`, `s`.`orgHandle`, `s`.`shortCode`, `s`.`destinationURL`,
        `s`.`redirectAlias`, `s`.`isActive`, `s`.`startDate`, `s`.`endDate`, `s`.`clickCount`,
        `s`.`lastClickAt`, `s`.`createdAt`, `s`.`createdByUserUID`, `s`.`staffDisabledAt`,
        `s`.`staffDisabledByUserUID`, `s`.`staffDisableReason`, `s`.`staffPreviousIsActive`, `o`.`orgName`,
        `u`.`email` AS `ownerEmail`, `u`.`displayName` AS `ownerName`,
        `a`.`email` AS `staffEmail`
    FROM `tblShortURLs` AS `s`
    LEFT JOIN `tblOrganisations` AS `o` ON `o`.`orgHandle` = `s`.`orgHandle`
    LEFT JOIN `tblUsers` AS `u` ON `u`.`userUID` = `s`.`createdByUserUID`
    LEFT JOIN `tblUsers` AS `a` ON `a`.`userUID` = `s`.`staffDisabledByUserUID`';

/**
 * One piece of the staff screen's wording: the translation when there is one,
 * otherwise the English given. NOT escaped: escape it where it is printed.
 *
 * @param  string                $key
 * @param  string                $english
 * @param  array<string, string> $replacements  Values for {name} placeholders.
 * @return string
 */
function g2ml_staffText(string $key, string $english, array $replacements = []): string
{
    if (function_exists('__'))
    {
        $translated = __($key, $replacements);

        if ($translated !== $key)
        {
            return $translated;
        }
    }

    foreach ($replacements as $name => $value)
    {
        $english = str_replace('{' . $name . '}', $value, $english);
    }

    return $english;
}

/**
 * Read what a staff member typed into the search box.
 *
 * Accepts a short code ("abc123") or a short address, with or without
 * "https://" ("g2my.link/abc123"). Anything after "?" or "#" in an address is
 * ignored.
 *
 * @param  string $input
 * @return array{code: string, domain: string}|null  domain is '' for a bare
 *         code. null when no single short code can be read from the text.
 */
function g2ml_staffParseLinkSearch(string $input): ?array
{
    $text = trim($input);

    if ($text === '' || strlen($text) > 2048 || preg_match('/[\x00-\x20\x7F]/', $text) === 1)
    {
        return null;
    }

    $domain = '';
    $code   = $text;

    if (strpos($text, '/') !== false)
    {
        $address = $text;

        if (preg_match('/^https?:\/\//i', $address) !== 1)
        {
            $address = 'https://' . $address;
        }

        $parts = parse_url($address);

        if ($parts === false || empty($parts['host']) || !isset($parts['path']))
        {
            return null;
        }

        $domain = strtolower($parts['host']);
        $code   = rawurldecode(trim($parts['path'], '/'));
    }

    if ($code === '' || strpos($code, '/') !== false || !mb_check_encoding($code, 'UTF-8') || mb_strlen($code, 'UTF-8') > 50)
    {
        return null;
    }

    return ['code' => $code, 'domain' => $domain];
}

/**
 * Every link with this short code. A code is only unique within one
 * organisation, so there can be several.
 *
 * When $domain is an organisation's own short domain, only that organisation's
 * link is returned. For no domain, g2my.link, or an address we do not know,
 * every organisation's link with the code is returned.
 *
 * @param  string $code
 * @param  string $domain  Lower-case host name, or ''.
 * @return array{links: array<int, array<string, mixed>>, narrowedTo: string}|false
 *         false when the database could not be read.
 */
function g2ml_staffFindLinks(string $code, string $domain = ''): array|false
{
    $narrowedTo = '';

    if ($domain !== '')
    {
        $domainRow = dbSelectOne(
            'SELECT `orgHandle` FROM `tblOrgShortDomains` WHERE `shortDomain` = ? LIMIT 1',
            's',
            [$domain]
        );

        if ($domainRow === false)
        {
            return false;
        }

        if ($domainRow !== null && $domainRow['orgHandle'] !== '[default]')
        {
            $narrowedTo = (string) $domainRow['orgHandle'];
        }
    }

    $sql    = G2ML_STAFF_LINK_SELECT . ' WHERE `s`.`shortCode` = ?';
    $types  = 's';
    $params = [$code];

    if ($narrowedTo !== '')
    {
        $sql     .= ' AND `s`.`orgHandle` = ?';
        $types   .= 's';
        $params[] = $narrowedTo;
    }

    $sql .= ' ORDER BY `s`.`orgHandle` ASC, `s`.`urlUID` ASC';

    $links = dbSelect($sql, $types, $params);

    if ($links === false)
    {
        return false;
    }

    return ['links' => $links, 'narrowedTo' => $narrowedTo];
}

/**
 * One link by its row number.
 *
 * @param  int $urlUID
 * @return array<string, mixed>|null
 */
function g2ml_staffGetLink(int $urlUID): ?array
{
    $row = dbSelectOne(G2ML_STAFF_LINK_SELECT . ' WHERE `s`.`urlUID` = ? LIMIT 1', 'i', [$urlUID]);

    if ($row === false)
    {
        return null;
    }

    return $row;
}

/**
 * Where a link stands now. The date checks match sp_lookupShortURL, which
 * compares with the database's UTC clock.
 *
 * @param  array<string, mixed> $link
 * @return string  'staff_off', 'off', 'scheduled', 'ended' or 'on'.
 */
function g2ml_staffLinkStatus(array $link): string
{
    if (!empty($link['staffDisabledAt']))
    {
        return 'staff_off';
    }

    if ((int) $link['isActive'] !== 1)
    {
        return 'off';
    }

    $nowUtc = gmdate('Y-m-d H:i:s');

    if (!empty($link['startDate']) && $link['startDate'] > $nowUtc)
    {
        return 'scheduled';
    }

    if (!empty($link['endDate']) && $link['endDate'] < $nowUtc)
    {
        return 'ended';
    }

    return 'on';
}

/**
 * Block one link, or remove the block, as a staff member, and record it.
 *
 * 'off' switches the link off and sets a staff block, also on a link that is
 * already switched off, so its owner cannot switch it on. 'unblock' removes the
 * block and puts isActive back to what it was before (D-026); a link its owner
 * or account erasure had switched off stays off.
 *
 * @param  int    $urlUID
 * @param  string $change        'off' or 'unblock'.
 * @param  int    $staffUserUID  The signed-in GlobalAdmin.
 * @param  string $reason        Optional; cut to 500 characters.
 * @return array{success: bool, errorCode: string, logged: bool, restoredActive: bool}
 *         errorCode is '', 'invalid', 'not_found', 'no_change' or 'failed'.
 *         restoredActive is true only when 'unblock' left the link switched on.
 */
function g2ml_staffSetLinkState(int $urlUID, string $change, int $staffUserUID, string $reason = ''): array
{
    $result = ['success' => false, 'errorCode' => '', 'logged' => false, 'restoredActive' => false];

    if (($change !== 'off' && $change !== 'unblock') || $staffUserUID <= 0)
    {
        $result['errorCode'] = 'invalid';

        return $result;
    }

    if (g2ml_staffGetLink($urlUID) === null)
    {
        $result['errorCode'] = 'not_found';

        return $result;
    }

    $reason = trim(mb_substr(trim(mb_scrub($reason, 'UTF-8')), 0, G2ML_STAFF_REASON_MAX_LENGTH, 'UTF-8'));

    if ($change === 'off')
    {
        $storedReason = null;

        if ($reason !== '')
        {
            $storedReason = $reason;
        }

        // The order matters: the old isActive is kept before it is changed.
        $affected = dbUpdate(
            'UPDATE `tblShortURLs`
             SET `staffPreviousIsActive` = `isActive`, `isActive` = 0,
                 `staffDisabledAt` = UTC_TIMESTAMP(),
                 `staffDisabledByUserUID` = ?, `staffDisableReason` = ?
             WHERE `urlUID` = ? AND `staffDisabledAt` IS NULL',
            'isi',
            [$staffUserUID, $storedReason, $urlUID]
        );
    }
    else
    {
        // The order matters: isActive is restored before the kept value is
        // cleared. With no kept value the link stays off.
        $affected = dbUpdate(
            'UPDATE `tblShortURLs`
             SET `isActive` = COALESCE(`staffPreviousIsActive`, 0),
                 `staffPreviousIsActive` = NULL, `staffDisabledAt` = NULL,
                 `staffDisabledByUserUID` = NULL, `staffDisableReason` = NULL
             WHERE `urlUID` = ? AND `staffDisabledAt` IS NOT NULL',
            'i',
            [$urlUID]
        );
    }

    if ($affected === false)
    {
        $result['errorCode'] = 'failed';

        return $result;
    }

    if ($affected === 0)
    {
        $result['errorCode'] = 'no_change';

        return $result;
    }

    // Read after the change, so the record says what really happened.
    $link = g2ml_staffGetLink($urlUID);

    if ($link === null)
    {
        $result['errorCode'] = 'failed';

        return $result;
    }

    $logData = ['urlUID' => $urlUID, 'reason' => $reason];

    if ($change === 'off')
    {
        $action               = 'staff_link_disabled';
        $logData['wasActive'] = ((int) $link['staffPreviousIsActive'] === 1);
    }
    else
    {
        $action                    = 'staff_link_unblocked';
        $result['restoredActive']  = ((int) $link['isActive'] === 1);
        $logData['restoredActive'] = $result['restoredActive'];
    }

    $result['success'] = true;
    $result['logged']  = logActivity($action, 'success', 200, [
        'userUID'   => $staffUserUID,
        'orgHandle' => (string) $link['orgHandle'],
        'shortCode' => (string) $link['shortCode'],
        'logData'   => $logData,
    ]);

    return $result;
}
