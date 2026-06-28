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
 * 🧪 Regression tests — anti-SSRF destination host guard (F-004 / F-005 / #100)
 * ============================================================================
 *
 * Pins the policy of g2ml_destinationHostIsAllowed() and the underlying
 * classifier g2ml_isPrivateOrReservedIp(). These guard every server-side
 * fetch of (Component B validateDestination) and every stored (Component A
 * createShortURL) user-supplied destination URL.
 *
 * To avoid real-DNS flakiness, the host-allowed cases use IP LITERALS (which
 * the guard checks directly, with no lookup). The public case likewise uses a
 * routable public IP literal. The classifier is exercised independently for
 * every block category.
 *
 * The private-default-block + override behaviour is verified against
 * g2ml_privateDestinationsAllowed(): in this DB-free runner getSetting() is
 * not defined, so the override FAILS CLOSED and private ranges stay blocked —
 * which is exactly the secure default we want to pin.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

// ----------------------------------------------------------------------------
// Classifier: g2ml_isPrivateOrReservedIp()
// ----------------------------------------------------------------------------

test('SSRF classifier: loopback 127.0.0.1 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('127.0.0.1'), 'IPv4 loopback must be blocked');
});

test('SSRF classifier: IPv6 loopback ::1 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('::1'), 'IPv6 loopback must be blocked');
});

test('SSRF classifier: cloud-metadata 169.254.169.254 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('169.254.169.254'), 'Link-local metadata endpoint must be blocked');
});

test('SSRF classifier: RFC1918 10.0.0.5 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('10.0.0.5'), '10/8 must be blocked by default');
});

test('SSRF classifier: RFC1918 192.168.1.1 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('192.168.1.1'), '192.168/16 must be blocked by default');
});

test('SSRF classifier: RFC1918 172.16.0.1 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('172.16.0.1'), '172.16/12 must be blocked by default');
});

test('SSRF classifier: unspecified 0.0.0.0 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('0.0.0.0'), 'Unspecified address must be blocked');
});

test('SSRF classifier: IPv6 ULA fc00::1 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('fc00::1'), 'IPv6 ULA must be blocked by default');
});

test('SSRF classifier: IPv4-mapped private ::ffff:10.0.0.5 is private/reserved', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('::ffff:10.0.0.5'), 'IPv4-mapped private must be unwrapped and blocked');
});

test('SSRF classifier: public 93.184.216.34 is NOT private/reserved', function (): void
{
    assert_false(g2ml_isPrivateOrReservedIp('93.184.216.34'), 'A routable public IP is allowed');
});

test('SSRF classifier: malformed input is treated as unsafe', function (): void
{
    assert_true(g2ml_isPrivateOrReservedIp('not-an-ip'), 'A non-IP literal must be treated as unsafe');
});

// ----------------------------------------------------------------------------
// Guard: g2ml_destinationHostIsAllowed() — IP-literal hosts (no DNS).
// ----------------------------------------------------------------------------

test('SSRF guard: http://169.254.169.254/ (metadata) is blocked', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://169.254.169.254/'), 'Cloud-metadata endpoint must be blocked');
});

test('SSRF guard: http://127.0.0.1/ (loopback) is blocked', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://127.0.0.1/'), 'Loopback must be blocked');
});

test('SSRF guard: http://10.0.0.5/ (private) is blocked by default', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://10.0.0.5/'), '10/8 private blocked by default (override fails closed)');
});

test('SSRF guard: http://192.168.1.1/ (private) is blocked by default', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://192.168.1.1/'), '192.168/16 private blocked by default');
});

test('SSRF guard: http://[::1]/ (bracketed IPv6 loopback) is blocked', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://[::1]/'), 'Bracketed IPv6 loopback must be blocked');
});

test('SSRF guard: userinfo http://user:pass@evil/ is rejected', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://user:pass@evil/'), 'URLs with userinfo must be rejected');
});

test('SSRF guard: userinfo against a public IP literal is still rejected', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('http://user:pass@93.184.216.34/'), 'userinfo is rejected even with a public host');
});

test('SSRF guard: non-http scheme ftp:// is rejected', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('ftp://example.com/'), 'Only http/https schemes are allowed');
});

test('SSRF guard: javascript: scheme is rejected', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed('javascript:alert(1)'), 'javascript: scheme is rejected');
});

test('SSRF guard: empty string is rejected', function (): void
{
    assert_false(g2ml_destinationHostIsAllowed(''), 'Empty URL is rejected');
});

test('SSRF guard: a public IP-literal http destination is allowed', function (): void
{
    assert_true(g2ml_destinationHostIsAllowed('http://93.184.216.34/'), 'A public IPv4-literal host is allowed');
});

test('SSRF guard: a public IP-literal https destination is allowed', function (): void
{
    assert_true(g2ml_destinationHostIsAllowed('https://8.8.8.8/'), 'A public IPv4-literal host over https is allowed');
});

// ----------------------------------------------------------------------------
// Private-default-block + override policy (fails closed without settings).
// ----------------------------------------------------------------------------

test('SSRF policy: private destinations are disallowed when settings unavailable (fail closed)', function (): void
{
    assert_false(function_exists('getSetting'), 'DB-free runner must not load the settings layer');
    assert_false(g2ml_privateDestinationsAllowed(), 'Override must fail closed without getSetting()');
});

test('SSRF policy: override targets only RFC1918/ULA, never the always-blocked ranges', function (): void
{
    assert_true(g2ml_ipIsPrivateButOverridable('10.0.0.5'), '10/8 is overridable');
    assert_true(g2ml_ipIsPrivateButOverridable('fc00::1'), 'IPv6 ULA is overridable');
    assert_false(g2ml_ipIsPrivateButOverridable('127.0.0.1'), 'Loopback is NEVER overridable');
    assert_false(g2ml_ipIsPrivateButOverridable('169.254.169.254'), 'Metadata endpoint is NEVER overridable');
});
