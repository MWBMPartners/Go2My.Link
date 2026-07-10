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
 * 🧪 Unit tests — IP geolocation (#43)
 * ============================================================================
 *
 * Covers the DB-FREE pieces of web/_functions/geolocation.php:
 *
 *   - g2ml_geolocationEnabled() / g2ml_geolocationAvailable() fail CLOSED
 *     when the settings layer is unavailable — mirrored exactly on
 *     g2ml_privateDestinationsAllowed() (security_ssrf_host_guard_test.php).
 *     settings.php is deliberately NOT required by this file, so
 *     function_exists('getSetting') is false, the same rig every other
 *     "fails closed" test in this suite relies on.
 *
 *   - g2ml_geolocateIP() via the $GLOBALS['g2ml_geoip_reader_override'] seam
 *     (mirrors $GLOBALS['g2ml_dns_txt_lookup_override'] in org.php and
 *     $GLOBALS['g2ml_entitlements_tier_lookup_override'] in
 *     entitlements.php): known IP → country/region/city, unknown IP → null,
 *     private/reserved IP → null WITHOUT ever consulting the override, a
 *     malformed IP string → null, and an override that throws → caught,
 *     never escapes.
 *
 *   - g2ml_geolocateIP() with NO override and NO real `.mmdb` present (the
 *     guaranteed state of this CI/unit sandbox) resolves to all-null without
 *     throwing — proving the "missing database" graceful-degradation path.
 *
 * security.php IS required (for g2ml_isPrivateOrReservedIp(), the same
 * classifier the anti-SSRF destination guard uses) so the private/reserved
 * IP tests exercise the real classifier, not just a missing-function no-op.
 *
 * DB-touching behaviour (a real redirect logging countryCode/regionCode/
 * cityName end-to-end) is covered by tests/integration/activity_log_test.php
 * against a real schema.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#43)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/geolocation.php';

// ============================================================================
// 🔒 Fail-closed when the settings layer is unavailable
// ============================================================================

test('geolocationEnabled: returns false when the settings layer is unavailable (fails closed)', function (): void
{
    assert_false(g2ml_geolocationEnabled(), 'Without getSetting(), geolocation must default to disabled');
});

test('geolocationAvailable: returns false when the settings layer is unavailable (fails closed)', function (): void
{
    assert_false(g2ml_geolocationAvailable(), 'Without getSetting(), geolocation must never be considered available');
});

// ============================================================================
// 🌍 g2ml_geolocateIP() — basic input handling (no override needed)
// ============================================================================

test('geolocateIP: an empty IP string returns all-null immediately', function (): void
{
    $result = g2ml_geolocateIP('');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

test('geolocateIP: a malformed (non-IP) string returns all-null', function (): void
{
    $result = g2ml_geolocateIP('not-an-ip-address');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

test('geolocateIP: IPv4 loopback (127.0.0.1) returns all-null — private/reserved, no lookup attempted', function (): void
{
    $result = g2ml_geolocateIP('127.0.0.1');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

test('geolocateIP: RFC1918 private (10.0.0.5) returns all-null — private/reserved, no lookup attempted', function (): void
{
    $result = g2ml_geolocateIP('10.0.0.5');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

test('geolocateIP: IPv6 loopback (::1) returns all-null — private/reserved, no lookup attempted', function (): void
{
    $result = g2ml_geolocateIP('::1');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

// ============================================================================
// 🧪 No override, no real database present — the guaranteed CI/unit state.
// This is the "missing database" graceful-degradation path proven directly:
// a genuinely PUBLIC (non-reserved) IP, no override set, and (in this
// sandbox) certainly no `.mmdb` file at the default vendored path. 8.8.8.8
// (a real public resolver address) is used rather than an RFC 5737
// documentation-range literal (192.0.2.0/24, 198.51.100.0/24,
// 203.0.113.0/24) — g2ml_isPrivateOrReservedIp() correctly classifies ALL
// THREE of those as reserved/unsafe (the same policy the anti-SSRF
// destination guard needs), which would short-circuit this test before it
// ever reached the "no database" code path it is meant to exercise.
// ============================================================================

test('geolocateIP: with no override and no real database present, a public IP resolves to all-null without throwing', function (): void
{
    unset($GLOBALS['g2ml_geoip_reader_override']);
    unset($GLOBALS['_g2ml_geoip_reader_cache']);

    $result = g2ml_geolocateIP('8.8.8.8');

    assert_same(null, $result['countryCode'], 'No database is present in this sandbox, so countryCode must stay null');
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);
});

// ============================================================================
// 🧵 $GLOBALS['g2ml_geoip_reader_override'] seam
// ============================================================================

test('geolocateIP: override returning a full record extracts countryCode/regionCode/cityName', function (): void
{
    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip): ?array
    {
        if ($ip === '8.8.4.4')
        {
            return [
                'country' => ['iso_code' => 'US'],
                'subdivisions' => [
                    ['iso_code' => 'CA'],
                ],
                'city' => ['names' => ['en' => 'Mountain View']],
            ];
        }

        return null;
    };

    $result = g2ml_geolocateIP('8.8.4.4');

    assert_same('US', $result['countryCode']);
    assert_same('CA', $result['regionCode']);
    assert_same('Mountain View', $result['cityName']);

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

test('geolocateIP: override returning a Country-tier record (no subdivisions/city) leaves regionCode/cityName null', function (): void
{
    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip): ?array
    {
        return [
            'country' => ['iso_code' => 'GB'],
        ];
    };

    $result = g2ml_geolocateIP('1.1.1.1');

    assert_same('GB', $result['countryCode'], 'GeoLite2-Country-tier data only ever provides countryCode');
    assert_same(null, $result['regionCode'], 'No subdivisions in a Country-tier record');
    assert_same(null, $result['cityName'], 'No city in a Country-tier record');

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

test('geolocateIP: override returning null (unknown IP within the database) resolves to all-null', function (): void
{
    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip): ?array
    {
        return null;
    };

    $result = g2ml_geolocateIP('9.9.9.9');

    assert_same(null, $result['countryCode']);
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

test('geolocateIP: a private/reserved IP resolves to all-null WITHOUT ever consulting the override', function (): void
{
    $overrideWasCalled = false;

    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip) use (&$overrideWasCalled): ?array
    {
        $overrideWasCalled = true;

        return ['country' => ['iso_code' => 'US']];
    };

    $result = g2ml_geolocateIP('192.168.1.50');

    assert_false($overrideWasCalled, 'A private/reserved IP must short-circuit BEFORE the override is ever consulted');
    assert_same(null, $result['countryCode']);

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

test('geolocateIP: an override that throws is caught and resolves to all-null (never escapes)', function (): void
{
    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip): ?array
    {
        throw new RuntimeException('simulated decoder failure');
    };

    $result = g2ml_geolocateIP('208.67.222.222');

    assert_same(null, $result['countryCode'], 'An exception from the reader must degrade to null, never escape');
    assert_same(null, $result['regionCode']);
    assert_same(null, $result['cityName']);

    unset($GLOBALS['g2ml_geoip_reader_override']);
});

test('geolocateIP: an override returning a non-array value is treated as "not found"', function (): void
{
    $GLOBALS['g2ml_geoip_reader_override'] = function (string $ip)
    {
        return 'not-an-array';
    };

    $result = g2ml_geolocateIP('208.67.220.220');

    assert_same(null, $result['countryCode']);

    unset($GLOBALS['g2ml_geoip_reader_override']);
});
