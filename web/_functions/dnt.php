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
 * 🕵️ Go2My.Link — Do Not Track (DNT) & Global Privacy Control (GPC)
 * ============================================================================
 *
 * Provides functions to detect and respect user privacy preferences via the
 * DNT header (Do Not Track) and Sec-GPC header (Global Privacy Control).
 *
 * Dependencies: settings.php (getSetting()), cookie_consent.php (optional)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 References:
 *     - DNT:     https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/DNT
 *     - Sec-GPC: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-GPC
 *     - GPC spec: https://globalprivacycontrol.github.io/gpc-spec/
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
// 🕵️ Detect DNT / GPC Headers
// ============================================================================

/**
 * Check whether the user has sent a Do Not Track (DNT) or Global Privacy
 * Control (Sec-GPC) header.
 *
 * @return bool  True if DNT=1 or Sec-GPC=1 is present
 *
 * 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/DNT
 */
function g2ml_detectDNT(): bool
{
    // HTTP_DNT: "1" = do not track, "0" = allow tracking
    $dnt = $_SERVER['HTTP_DNT'] ?? null;

    if ($dnt === '1')
    {
        return true;
    }

    // Sec-GPC: "1" = user opts out of data sharing/selling (GPC spec)
    $gpc = $_SERVER['HTTP_SEC_GPC'] ?? null;

    if ($gpc === '1')
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🔍 Should Track Decision
// ============================================================================

/**
 * Determine whether tracking (activity logging, analytics cookies) is allowed
 * for the current request.
 *
 * Logic:
 *   1. If `compliance.always_assume_dnt` = true → never track
 *   2. If `analytics.respect_dnt` = true AND DNT/GPC detected → don't track
 *   3. Otherwise → allow tracking
 *
 * @return bool  True if tracking IS allowed; false if it should be suppressed
 */
function g2ml_shouldTrack(): bool
{
    // Setting: always assume DNT (maximum privacy mode)
    if (function_exists('getSetting'))
    {
        $alwaysAssumeDNT = getSetting('compliance.always_assume_dnt', false);

        if ($alwaysAssumeDNT)
        {
            return false;
        }

        // Setting: respect DNT header
        $respectDNT = getSetting('analytics.respect_dnt', true);

        if ($respectDNT && g2ml_detectDNT())
        {
            return false;
        }
    }

    return true;
}

// ============================================================================
// 🍪 Cookie Category Permission Check
// ============================================================================

/**
 * Check whether a specific cookie category is allowed for the current user.
 *
 * - Essential cookies are ALWAYS allowed (required for site operation).
 * - Non-essential cookies are blocked if:
 *   a) DNT/GPC is active AND analytics.respect_dnt is enabled, OR
 *   b) Cookie consent system is enabled AND user has not consented to that category.
 *
 * @param  string $category  Cookie category: 'essential', 'analytics', 'functional', 'marketing'
 * @return bool              True if cookies in this category are permitted
 */
function g2ml_isCookieAllowed(string $category): bool
{
    // Essential cookies are always permitted
    if ($category === 'essential')
    {
        return true;
    }

    // If tracking is suppressed (DNT/GPC active), block non-essential cookies
    if (!g2ml_shouldTrack())
    {
        return false;
    }

    // If cookie consent system is available, check consent status
    if (function_exists('g2ml_getConsentStatus'))
    {
        $consent = g2ml_getConsentStatus($category);

        // null = no consent recorded yet; treat as blocked in opt-in jurisdictions
        if ($consent === null)
        {
            // Check jurisdiction model
            if (function_exists('g2ml_detectJurisdiction') && function_exists('g2ml_isOptInJurisdiction'))
            {
                $jurisdiction = g2ml_detectJurisdiction();

                if (g2ml_isOptInJurisdiction($jurisdiction))
                {
                    return false; // Opt-in required, no consent yet
                }
            }

            return true; // Opt-out jurisdiction, default to allowed
        }

        return $consent; // Explicit consent decision
    }

    // No consent system loaded — allow by default
    return true;
}
