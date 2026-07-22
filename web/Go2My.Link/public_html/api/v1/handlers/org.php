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
 * 🏢 Go2My.Link — API v1 Handler: GET /api/v1/org (#39)
 * ============================================================================
 *
 * Requires the `org:read` scope. Returns ONLY the authenticated key's own
 * organisation summary — every value is derived from getOrganisation() keyed
 * on the verified key row's OWN orgHandle, so there is no user-suppliable
 * identifier anywhere in this handler (no BOLA/IDOR surface: a key can never
 * be used to read another organisation's summary), mirroring the same
 * self-scoped pattern as g2ml_apiHandleAccount() (#38).
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#39)
 *
 * 📖 References:
 *     - web/_functions/org.php — getOrganisation()
 *     - web/Go2My.Link/public_html/api/v1/handlers/account.php — the #38 self-scoped precedent
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
 * Handle GET /api/v1/org.
 *
 * @param  array $keyRow   The verified API key row (already scope-checked by the front controller).
 * @param  array $context  Request-scoped context (unused by this handler).
 * @return array            {org_handle, org_name, tier_id, tier_name, is_verified, created_at}
 *
 * @throws RuntimeException  If the key's own organisation row cannot be
 *                            loaded (should not happen — g2ml_apiVerifyKey()
 *                            already confirmed the org is active — but the
 *                            front controller catches this into a generic
 *                            500, never leaking the underlying cause).
 */
function g2ml_apiHandleOrgRead(array $keyRow, array $context): array
{
    $orgHandle = (string) $keyRow['orgHandle'];

    $org = getOrganisation($orgHandle);

    if ($org === null)
    {
        throw new RuntimeException('The key\'s own organisation could not be loaded.');
    }

    $tierName = null;

    if (isset($org['tierName']))
    {
        $tierName = $org['tierName'];
    }

    return [
        'org_handle'  => $org['orgHandle'],
        'org_name'    => $org['orgName'],
        'tier_id'     => $org['tierID'],
        'tier_name'   => $tierName,
        'is_verified' => ((int) $org['isVerified'] === 1),
        'created_at'  => $org['createdAt'],
    ];
}
