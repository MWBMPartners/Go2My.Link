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
 * 👤 Go2My.Link — API v1 Handler: GET /api/v1/account (#38)
 * ============================================================================
 *
 * Requires the `account:read` scope. Returns ONLY the authenticated key's own
 * account/org/key metadata — every value is derived from the verified key row
 * or a lookup keyed on that row's own userUID, so there is no user-suppliable
 * identifier anywhere in this handler (no BOLA/IDOR surface: a key can never
 * be used to read another user's or another org's account).
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#38)
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

/**
 * Handle GET /api/v1/account.
 *
 * @param  array $keyRow   The verified API key row (already scope-checked by the front controller).
 * @param  array $context  Request-scoped context (unused by this handler).
 * @return array            The success payload: {userUID, email, displayName, orgHandle, keyName, scopes}.
 *
 * @throws RuntimeException  If the key's own owning user row cannot be loaded
 *                            (should not happen — g2ml_apiVerifyKey() already
 *                            confirmed the user is active — but the front
 *                            controller catches this into a generic 500,
 *                            never leaking the underlying cause).
 */
function g2ml_apiHandleAccount(array $keyRow, array $context): array
{
    $userUID = (int) $keyRow['userUID'];

    $userRow = dbSelectOne(
        'SELECT email, displayName, firstName, lastName FROM tblUsers WHERE userUID = ?',
        'i',
        [$userUID]
    );

    if ($userRow === false || $userRow === null)
    {
        throw new RuntimeException('Account owner could not be loaded.');
    }

    $displayName = $userRow['displayName'];

    if ($displayName === null || trim((string) $displayName) === '')
    {
        $displayName = trim($userRow['firstName'] . ' ' . $userRow['lastName']);
    }

    $scopes = [];

    if (isset($keyRow['permissions']) && is_array($keyRow['permissions']))
    {
        $scopes = $keyRow['permissions'];
    }

    return [
        'userUID'     => $userUID,
        'email'       => $userRow['email'],
        'displayName' => $displayName,
        'orgHandle'   => $keyRow['orgHandle'],
        'keyName'     => $keyRow['keyName'],
        'scopes'      => $scopes,
    ];
}
