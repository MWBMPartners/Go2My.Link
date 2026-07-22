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
 * 🧪 Unit tests — g2ml_appendUtmToDestination() UTM forwarding (#92)
 * ============================================================================
 *
 * Pure-function, DB-free coverage for the redirect-hot-path helper that
 * appends a short link's OWN configured tblShortURLs.utm* values onto its
 * destination URL, just before the Location header is emitted. This is the
 * "forward" half of #92 (redirect.forward_utm_params, OFF by default).
 *
 * These tests pin down:
 *   - the override semantics (the link's configured value wins over a
 *     same-named param already on the destination);
 *   - that scheme/user/pass/host/port/path/fragment survive untouched;
 *   - the no-op cases (empty $linkUtm, unparseable destination) return the
 *     ORIGINAL string byte-for-byte — this is what guarantees "a normal
 *     redirect is byte-identical with both settings off" at the index.php
 *     call site, since index.php never even calls this helper when
 *     redirect.forward_utm_params is off;
 *   - that http_build_query() encoding closes off any header-injection
 *     attempt via a stored UTM value containing CR/LF.
 *
 * The companion database-backed proof — that sp_lookupShortURL actually
 * projects these columns, and only on a successful resolution — lives in
 * tests/integration/shorturl_lookup_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#92)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

// ----------------------------------------------------------------------------
// No-op cases — MUST return the original string byte-for-byte.
// ----------------------------------------------------------------------------

test('appendUtmToDestination: an empty $linkUtm array is a no-op', function (): void
{
    $destination = 'https://example.com/path?existing=1';
    $result      = g2ml_appendUtmToDestination($destination, []);
    assert_same($destination, $result, 'No configured UTM values must leave the destination byte-identical');
});

test('appendUtmToDestination: $linkUtm with only null/empty values is a no-op', function (): void
{
    $destination = 'https://example.com/path';
    $linkUtm     = [
        'utmSource'   => null,
        'utmMedium'   => '',
        'utmCampaign' => null,
        'utmTerm'     => null,
        'utmContent'  => null,
    ];
    $result = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same($destination, $result, 'All-empty configured values must leave the destination byte-identical');
});

test('appendUtmToDestination: an unparseable destination is returned unchanged', function (): void
{
    $destination = 'not a url at all';
    $linkUtm     = ['utmSource' => 'twitter'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same($destination, $result, 'An unparseable destination must never be corrupted — return it untouched');
});

test('appendUtmToDestination: a destination missing a host (e.g. a bare path) is returned unchanged', function (): void
{
    $destination = '/relative/path?x=1';
    $linkUtm     = ['utmSource' => 'twitter'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same($destination, $result, 'A destination with no host must never be corrupted — return it untouched');
});

test('appendUtmToDestination: a non-string linkUtm value is ignored, not coerced', function (): void
{
    $destination = 'https://example.com/path';
    $linkUtm     = ['utmSource' => 12345];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same($destination, $result, 'A non-string configured value must be ignored entirely (defensive)');
});

// ----------------------------------------------------------------------------
// Appending onto a destination with no existing query string.
// ----------------------------------------------------------------------------

test('appendUtmToDestination: appends a single utm_source onto a destination with no query string', function (): void
{
    $destination = 'https://example.com/landing';
    $linkUtm     = ['utmSource' => 'twitter'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same('https://example.com/landing?utm_source=twitter', $result, 'A bare destination gets a fresh query string appended');
});

test('appendUtmToDestination: appends all five configured utm fields', function (): void
{
    $destination = 'https://example.com/landing';
    $linkUtm     = [
        'utmSource'   => 'twitter',
        'utmMedium'   => 'social',
        'utmCampaign' => 'launch',
        'utmTerm'     => 'shortlinks',
        'utmContent'  => 'variant-a',
    ];
    $result = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same(
        'https://example.com/landing?utm_source=twitter&utm_medium=social&utm_campaign=launch&utm_term=shortlinks&utm_content=variant-a',
        $result,
        'All five whitelisted UTM fields are appended in a deterministic order'
    );
});

// ----------------------------------------------------------------------------
// Override semantics — the link's configured value wins.
// ----------------------------------------------------------------------------

test('appendUtmToDestination: the configured utm_source overrides a same-named param already on the destination', function (): void
{
    $destination = 'https://example.com/landing?utm_source=direct';
    $linkUtm     = ['utmSource' => 'twitter'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same('https://example.com/landing?utm_source=twitter', $result, 'The link owner configured value must win over an existing destination param');
});

test('appendUtmToDestination: unrelated existing query params are preserved alongside the appended UTM values', function (): void
{
    $destination = 'https://example.com/landing?ref=partner&existing=1';
    $linkUtm     = ['utmCampaign' => 'launch'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same('https://example.com/landing?ref=partner&existing=1&utm_campaign=launch', $result, 'Non-UTM existing params must survive untouched, in their original order, before the appended value');
});

// ----------------------------------------------------------------------------
// Preservation of the rest of the URL: scheme, auth, host, port, path, fragment.
// ----------------------------------------------------------------------------

test('appendUtmToDestination: preserves scheme, host, port, path and fragment exactly', function (): void
{
    $destination = 'https://example.com:8443/deep/path/segment#section-2';
    $linkUtm     = ['utmSource' => 'newsletter'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same(
        'https://example.com:8443/deep/path/segment?utm_source=newsletter#section-2',
        $result,
        'Scheme/host/port/path are preserved and the fragment is re-appended AFTER the rebuilt query string'
    );
});

test('appendUtmToDestination: preserves userinfo (user:pass@) in the destination', function (): void
{
    $destination = 'https://user:pass@example.com/secure';
    $linkUtm     = ['utmSource' => 'email'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same('https://user:pass@example.com/secure?utm_source=email', $result, 'Userinfo must survive the rebuild');
});

test('appendUtmToDestination: http (not just https) destinations are handled identically', function (): void
{
    $destination = 'http://example.com/path';
    $linkUtm     = ['utmMedium' => 'cpc'];
    $result      = g2ml_appendUtmToDestination($destination, $linkUtm);
    assert_same('http://example.com/path?utm_medium=cpc', $result, 'http destinations are forwarded the same way as https ones');
});

// ----------------------------------------------------------------------------
// Encoding / header-injection safety.
// ----------------------------------------------------------------------------

test('appendUtmToDestination: a stored value containing CR/LF is percent-encoded, never emitted raw', function (): void
{
    $destination  = 'https://example.com/path';
    $maliciousUtm = ['utmCampaign' => "line1\r\nSet-Cookie: pwned=1"];
    $result       = g2ml_appendUtmToDestination($destination, $maliciousUtm);

    assert_false(str_contains($result, "\r"), 'A raw carriage return must never appear in the rebuilt URL');
    assert_false(str_contains($result, "\n"), 'A raw line feed must never appear in the rebuilt URL');
    assert_contains('utm_campaign=line1%0D%0ASet-Cookie%3A%20pwned%3D1', $result, 'The value must be present ONLY in its percent-encoded form (RFC3986 — space encodes as %20)');
});

test('appendUtmToDestination: a stored value containing an ampersand cannot inject an extra query parameter', function (): void
{
    $destination  = 'https://example.com/path';
    $maliciousUtm = ['utmSource' => 'twitter&admin=1'];
    $result       = g2ml_appendUtmToDestination($destination, $maliciousUtm);

    assert_same('https://example.com/path?utm_source=twitter%26admin%3D1', $result, 'An embedded & must be percent-encoded, not treated as a parameter separator');
});
