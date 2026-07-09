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
 * 🏓 Go2My.Link — API v1 Handler: GET /api/v1/ping (#38)
 * ============================================================================
 *
 * Scope-free exerciser endpoint — reachable by ANY authenticated, non-rate-
 * limited key, purely to prove the framework (auth + rate limit + envelope +
 * logging) works end to end without touching product data.
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
 * Handle GET /api/v1/ping.
 *
 * @param  array $keyRow   The verified API key row (unused — ping is scope-free).
 * @param  array $context  Request-scoped context; 'requestId' is echoed into
 *                          the payload so it matches the envelope's meta.requestId.
 * @return array            The success payload.
 */
function g2ml_apiHandlePing(array $keyRow, array $context): array
{
    $requestId = '';

    if (isset($context['requestId']) && is_string($context['requestId']))
    {
        $requestId = $context['requestId'];
    }

    return [
        'pong'      => true,
        'requestId' => $requestId,
    ];
}
