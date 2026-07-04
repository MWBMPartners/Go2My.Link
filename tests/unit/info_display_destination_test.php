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
 * 🧪 Unit tests — info page destination display (FG-003 / #23)
 * ============================================================================
 *
 * Pure, DB-free tests for g2ml_infoDisplayDestination(), defined in
 * web/Go2My.Link/_functions/info_display.php. The helper decides which
 * destination string the public preview page renders:
 *
 *   - anonymous viewer → the destination DOMAIN with the path masked as "/..."
 *     (a leading "www." stripped for a cleaner display),
 *   - authenticated viewer → the FULL destination URL (a short URL redirects
 *     publicly, so its destination is not a secret; revealing it to a signed-in
 *     viewer is the documented, intended behaviour).
 *
 * The helper is pure (no session, no database), so its decision is pinned down
 * here without any bootstrap. Its file is safe to require directly: the
 * direct-access guard only fires when the file is executed as the main script,
 * and it declares no load-time dependencies.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-003 / #23)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/info_display.php';

// ============================================================================
// 🔒 Anonymous viewer — the path is masked, only the domain is revealed
// ============================================================================

test('info destination: anonymous viewer sees the domain with a masked path', function (): void
{
    $linkData = ['destinationURL' => 'https://example.com/very/long/secret/path'];
    assert_same('example.com/...', g2ml_infoDisplayDestination($linkData, false), 'A deep path must be masked to "/..." for an anonymous viewer');
});

test('info destination: anonymous viewer sees a leading www. stripped', function (): void
{
    $linkData = ['destinationURL' => 'https://www.example.com/campaign'];
    assert_same('example.com/...', g2ml_infoDisplayDestination($linkData, false), 'A leading www. is dropped for a cleaner display');
});

test('info destination: anonymous viewer sees a bare domain when there is no path', function (): void
{
    $linkData = ['destinationURL' => 'https://example.com'];
    assert_same('example.com', g2ml_infoDisplayDestination($linkData, false), 'A destination with no path shows just the domain');
});

test('info destination: anonymous viewer sees a bare domain for a root-only path', function (): void
{
    $linkData = ['destinationURL' => 'https://example.com/'];
    assert_same('example.com', g2ml_infoDisplayDestination($linkData, false), 'A root "/" path is not treated as a maskable path');
});

test('info destination: masking keeps a query string hidden behind the mask', function (): void
{
    $linkData = ['destinationURL' => 'https://shop.example.com/item?ref=abc123&token=secret'];
    assert_same('shop.example.com/...', g2ml_infoDisplayDestination($linkData, false), 'Only the domain surfaces; the path and query stay masked');
});

// ============================================================================
// 🔓 Authenticated viewer — the full destination is revealed unchanged
// ============================================================================

test('info destination: authenticated viewer sees the full destination URL', function (): void
{
    $linkData = ['destinationURL' => 'https://example.com/very/long/secret/path'];
    assert_same('https://example.com/very/long/secret/path', g2ml_infoDisplayDestination($linkData, true), 'A signed-in viewer sees the complete destination');
});

test('info destination: authenticated viewer sees the full URL including query and fragment', function (): void
{
    $url      = 'https://www.example.com/item?ref=abc123&token=secret#section';
    $linkData = ['destinationURL' => $url];
    assert_same($url, g2ml_infoDisplayDestination($linkData, true), 'The full URL is returned verbatim, www. and all, for an authenticated viewer');
});

// ============================================================================
// 🕳️ No-destination case — an empty string for either viewer
// ============================================================================

test('info destination: an empty destination yields an empty string (anonymous)', function (): void
{
    assert_same('', g2ml_infoDisplayDestination(['destinationURL' => ''], false), 'An empty destination produces nothing to display');
});

test('info destination: a missing destination key yields an empty string (authenticated)', function (): void
{
    assert_same('', g2ml_infoDisplayDestination([], true), 'A row without a destinationURL produces nothing to display');
});
