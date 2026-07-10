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
 * 🧪 Unit tests — Public API v1 endpoint surface: routing + scopes (#39)
 * ============================================================================
 *
 * Covers the DB-free pieces introduced/extended by #39:
 *   (1) g2ml_apiMatchParamRoute() — the "METHOD urls/{code}" parameterised
 *       route matcher used by the front controller's dispatcher.
 *   (2) g2ml_apiKeyHasScope() against the NEW #39 scopes (urls:write,
 *       urls:delete, org:read) — #38's unit suite only exercised
 *       account:read/urls:read generically.
 *   (3) G2mlApiHandlerException — the handler-thrown structured error type
 *       that lets a handler signal 404/409/422/etc. without ever leaking an
 *       unexpected failure's cause (that path is unchanged: a generic
 *       Throwable still maps to 500 in the front controller).
 *
 * DB-touching endpoint behaviour (create/list/get/update/delete/bulk,
 * org-scoping/BOLA, alias-collision, bulk partial-failure, and the pre-auth
 * backoff) is covered by tests/integration/api_v1_urls_test.php against a
 * real schema instead of being faked here.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#39)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_auth.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';

// ============================================================================
// 🗺️ g2ml_apiMatchParamRoute — "METHOD urls/{code}" matching
// ============================================================================

$g2mlTestParamRouteTable = [
    'GET urls/{code}' => [
        'scope'   => 'urls:read',
        'handler' => 'g2ml_apiHandleUrlsGet',
    ],
    'PUT urls/{code}' => [
        'scope'   => 'urls:write',
        'handler' => 'g2ml_apiHandleUrlsUpdate',
    ],
    'DELETE urls/{code}' => [
        'scope'   => 'urls:delete',
        'handler' => 'g2ml_apiHandleUrlsDelete',
    ],
];

test('matchParamRoute: GET urls/<code> matches and extracts the code', function () use ($g2mlTestParamRouteTable): void
{
    $match = g2ml_apiMatchParamRoute('GET', 'urls/abc123', $g2mlTestParamRouteTable);

    assert_true(is_array($match), 'A well-formed two-segment urls/<code> path must match');
    assert_same('abc123', $match['code'], 'The extracted code must be the second segment verbatim');
    assert_same('g2ml_apiHandleUrlsGet', $match['route']['handler'], 'The matched route must be the GET urls/{code} definition');
});

test('matchParamRoute: PUT and DELETE also match urls/<code>', function () use ($g2mlTestParamRouteTable): void
{
    $putMatch = g2ml_apiMatchParamRoute('PUT', 'urls/my-code_1', $g2mlTestParamRouteTable);
    assert_true(is_array($putMatch), 'PUT must match urls/{code}');
    assert_same('urls:write', $putMatch['route']['scope'], 'The PUT route must carry the urls:write scope');

    $deleteMatch = g2ml_apiMatchParamRoute('DELETE', 'urls/my-code_1', $g2mlTestParamRouteTable);
    assert_true(is_array($deleteMatch), 'DELETE must match urls/{code}');
    assert_same('urls:delete', $deleteMatch['route']['scope'], 'The DELETE route must carry the urls:delete scope');
});

test('matchParamRoute: a code using hyphens and underscores is accepted', function () use ($g2mlTestParamRouteTable): void
{
    $match = g2ml_apiMatchParamRoute('GET', 'urls/My-Code_42', $g2mlTestParamRouteTable);
    assert_true(is_array($match), 'Hyphen/underscore/mixed-case codes are within the short-code charset');
    assert_same('My-Code_42', $match['code'], 'The code must be extracted verbatim (case preserved)');
});

test('matchParamRoute: a method with no urls/{code} entry does not match', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('POST', 'urls/abc123', $g2mlTestParamRouteTable), 'POST has no urls/{code} entry (create is a literal "urls" route, not parameterised)');
});

test('matchParamRoute: a single-segment route never matches (that is the literal "urls" list/create route)', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls', $g2mlTestParamRouteTable), 'A bare "urls" path must be handled by the literal route table, not this matcher');
});

test('matchParamRoute: a three-segment route never matches', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/abc123/extra', $g2mlTestParamRouteTable), 'Only exactly two segments are recognised');
});

test('matchParamRoute: a first segment other than "urls" never matches', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'org/abc123', $g2mlTestParamRouteTable), 'Only "urls/<code>" is a recognised parameterised shape');
});

test('matchParamRoute: "urls/bulk" is grammatically a candidate but is deliberately reserved as a short code', function () use ($g2mlTestParamRouteTable): void
{
    // Routing-wise this DOES match (POST urls/bulk is caught earlier by the
    // literal table before this matcher ever runs) — this test documents that
    // GET/PUT/DELETE urls/bulk would be treated as a lookup for a code
    // literally named "bulk", which is why g2ml_isReservedShortCode() now
    // rejects 'bulk' at creation time (shorturl_create.php), so no such code
    // can ever exist to be ambiguous.
    $match = g2ml_apiMatchParamRoute('GET', 'urls/bulk', $g2mlTestParamRouteTable);
    assert_true(is_array($match), 'urls/bulk is syntactically a valid urls/{code} candidate for GET');
    assert_same('bulk', $match['code'], 'The candidate code is "bulk" — reserved so it can never be a real, ambiguous code');
});

test('matchParamRoute: a code containing an invalid character is rejected', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/abc 123', $g2mlTestParamRouteTable), 'A space is outside the short-code charset');
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/abc.123', $g2mlTestParamRouteTable), 'A dot is outside the short-code charset');
});

test('matchParamRoute: an over-length code (>50 chars) is rejected', function () use ($g2mlTestParamRouteTable): void
{
    $overLong = str_repeat('a', 51);
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/' . $overLong, $g2mlTestParamRouteTable), 'tblShortURLs.shortCode is VARCHAR(50) — a longer candidate must never match');
});

test('matchParamRoute: an empty paramRouteTable never matches anything', function (): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/abc123', []), 'With no entries in the table, nothing can match');
});

// ============================================================================
// 🗺️ g2ml_apiMatchParamRoute — "analytics/{code}" (#41)
// ============================================================================
// The matcher was generalised from a hardcoded 'urls' prefix to a whitelist
// (g2ml_apiParamRoutePrefixes()) so it could ALSO recognise
// "analytics/{code}" without a second bespoke matcher function. These tests
// cover that generalisation stays additive: every urls/{code} behaviour
// above is unaffected, and analytics/{code} now resolves the SAME way.
// ============================================================================

$g2mlTestAnalyticsParamRouteTable = [
    'GET analytics/{code}' => [
        'scope'   => 'analytics:read',
        'handler' => 'g2ml_apiHandleAnalyticsGet',
    ],
];

test('matchParamRoute: GET analytics/<code> matches and extracts the code', function () use ($g2mlTestAnalyticsParamRouteTable): void
{
    $match = g2ml_apiMatchParamRoute('GET', 'analytics/abc123', $g2mlTestAnalyticsParamRouteTable);

    assert_true(is_array($match), 'A well-formed two-segment analytics/<code> path must match');
    assert_same('abc123', $match['code'], 'The extracted code must be the second segment verbatim');
    assert_same('g2ml_apiHandleAnalyticsGet', $match['route']['handler'], 'The matched route must be the GET analytics/{code} definition');
});

test('matchParamRoute: a method with no analytics/{code} entry does not match', function () use ($g2mlTestAnalyticsParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('PUT', 'analytics/abc123', $g2mlTestAnalyticsParamRouteTable), 'PUT has no analytics/{code} entry — there is no write path for analytics');
});

test('matchParamRoute: "analytics" is only recognised against a table that actually defines it — the urls-only table must not cross-match', function () use ($g2mlTestParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'analytics/abc123', $g2mlTestParamRouteTable), 'The prefix whitelist only bounds candidacy — actual matching still requires the "GET analytics/{code}" key to exist in the supplied table, which the urls-only table never defines');
});

test('matchParamRoute: "urls" is still recognised against a table that only defines analytics/{code} — same reasoning in reverse', function () use ($g2mlTestAnalyticsParamRouteTable): void
{
    assert_same(null, g2ml_apiMatchParamRoute('GET', 'urls/abc123', $g2mlTestAnalyticsParamRouteTable), 'The analytics-only table never defines "GET urls/{code}"');
});

test('matchParamRoute: an unwhitelisted prefix (e.g. "domains") never matches, even with a permissive table', function (): void
{
    $permissiveTable = [
        'GET domains/{code}' => [
            'scope'   => 'domains:read',
            'handler' => 'some_future_handler',
        ],
    ];

    assert_same(null, g2ml_apiMatchParamRoute('GET', 'domains/abc123', $permissiveTable), '"domains" is not on g2ml_apiParamRoutePrefixes() — the prefix whitelist rejects it before the table is even consulted');
});

// ============================================================================
// 🏷️ g2ml_apiKeyHasScope — the NEW #39 scopes
// ============================================================================

test('hasScope: urls:write is present when granted, absent otherwise', function (): void
{
    $keyRow = ['permissions' => ['urls:read', 'urls:write']];
    assert_true(g2ml_apiKeyHasScope($keyRow, 'urls:write'), 'urls:write was granted and must be present');

    $readOnlyKeyRow = ['permissions' => ['urls:read']];
    assert_false(g2ml_apiKeyHasScope($readOnlyKeyRow, 'urls:write'), 'A read-only key must not carry urls:write');
});

test('hasScope: urls:delete is distinct from urls:write — granting one does not imply the other', function (): void
{
    $writeOnlyKeyRow = ['permissions' => ['urls:read', 'urls:write']];
    assert_false(g2ml_apiKeyHasScope($writeOnlyKeyRow, 'urls:delete'), 'urls:delete must be granted explicitly, never implied by urls:write');

    $fullKeyRow = ['permissions' => ['urls:read', 'urls:write', 'urls:delete']];
    assert_true(g2ml_apiKeyHasScope($fullKeyRow, 'urls:delete'), 'urls:delete must be present when explicitly granted');
});

test('hasScope: org:read is independent of the urls:* and account:read scopes', function (): void
{
    $keyRow = ['permissions' => ['account:read', 'urls:read']];
    assert_false(g2ml_apiKeyHasScope($keyRow, 'org:read'), 'org:read must not be implied by account:read or urls:read');

    $orgScopedKeyRow = ['permissions' => ['org:read']];
    assert_true(g2ml_apiKeyHasScope($orgScopedKeyRow, 'org:read'), 'org:read must be present when explicitly granted');
});

test('hasScope: analytics:read (#41) is independent of every other scope — the front controller\'s missing-scope 403 guard for /api/v1/analytics relies on exactly this check', function (): void
{
    $analyticsScopedKeyRow = ['permissions' => ['analytics:read']];
    assert_true(g2ml_apiKeyHasScope($analyticsScopedKeyRow, 'analytics:read'), 'analytics:read must be present when explicitly granted');

    $everythingButAnalyticsKeyRow = ['permissions' => ['urls:read', 'urls:write', 'urls:delete', 'org:read', 'account:read']];
    assert_false(g2ml_apiKeyHasScope($everythingButAnalyticsKeyRow, 'analytics:read'), 'analytics:read must NEVER be implied by any other scope, however broad the key otherwise is — this is the exact check that maps to a 403 for GET /api/v1/analytics and /api/v1/analytics/{code} when absent');
});

// ============================================================================
// 🚨 G2mlApiHandlerException — structured handler-thrown client errors
// ============================================================================

test('G2mlApiHandlerException: carries the status code, message, and field', function (): void
{
    $exception = new G2mlApiHandlerException(422, 'destination_url is required.', 'destination_url');

    assert_same(422, $exception->getHttpStatusCode(), 'getHttpStatusCode() must return the constructor value');
    assert_same('destination_url is required.', $exception->getMessage(), 'getMessage() must return the constructor value');
    assert_same('destination_url', $exception->getField(), 'getField() must return the constructor value');
});

test('G2mlApiHandlerException: field defaults to null when not supplied', function (): void
{
    $exception = new G2mlApiHandlerException(404, 'No such short URL in this account.');
    assert_same(null, $exception->getField(), 'field must default to null, matching g2ml_apiErrorBody()\'s own default');
});

test('G2mlApiHandlerException: is a RuntimeException (catchable by any generic Throwable/Exception handler)', function (): void
{
    assert_throws(function (): void
    {
        throw new G2mlApiHandlerException(409, 'That alias is already taken.');
    }, RuntimeException::class, 'G2mlApiHandlerException must extend RuntimeException');
});
