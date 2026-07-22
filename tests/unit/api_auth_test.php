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
 * 🧪 Unit tests — Public API v1 framework: pure/DB-free functions (#38)
 * ============================================================================
 *
 * Covers the DB-free pieces of the API framework:
 *   (1) g2ml_apiExtractBearerToken() — Bearer/X-API-Key header extraction.
 *   (2) g2ml_apiKeyHasScope()        — scope membership check.
 *   (3) g2ml_apiRedactRequestBody()  — secret redaction before logging.
 *   (4) g2ml_apiEnvelope() / g2ml_apiErrorBody() / g2ml_apiSanitiseRoute()
 *       in api_response.php.
 *
 * DB-touching functions (g2ml_apiGenerateKey, g2ml_apiVerifyKey,
 * g2ml_apiCheckRateLimit, g2ml_apiLogRequest, g2ml_apiRevokeKey,
 * g2ml_apiListKeys) are covered by tests/integration/api_key_test.php against
 * a real schema instead of being faked here.
 *
 * Both api_auth.php and api_ratelimit.php only DEFINE functions at include
 * time (guarded direct-access, no top-level side effects), so they are safe
 * to require directly under the DB-free unit runner.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#38)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_auth.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';

// ============================================================================
// 🔑 g2ml_apiExtractBearerToken — header extraction
// ============================================================================

test('extractBearer: a well-formed Bearer header yields the key', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('Bearer g2ml_abcd1234_secretvalue', null);
    assert_same('g2ml_abcd1234_secretvalue', $extracted, 'The token after "Bearer " must be returned verbatim');
});

test('extractBearer: the Bearer scheme match is case-insensitive', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('bearer g2ml_abcd1234_secretvalue', null);
    assert_same('g2ml_abcd1234_secretvalue', $extracted, 'A lower-case "bearer" scheme must still be recognised');
});

test('extractBearer: surrounding whitespace is trimmed', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('  Bearer   g2ml_abcd1234_secretvalue  ', null);
    assert_same('g2ml_abcd1234_secretvalue', $extracted, 'Leading/trailing whitespace around the header and token must be trimmed');
});

test('extractBearer: X-API-Key is used when no Authorization header is present', function (): void
{
    $extracted = g2ml_apiExtractBearerToken(null, 'g2ml_abcd1234_secretvalue');
    assert_same('g2ml_abcd1234_secretvalue', $extracted, 'X-API-Key must be accepted as a documented alias');
});

test('extractBearer: Bearer takes priority over X-API-Key when both are present', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('Bearer g2ml_primary_secret', 'g2ml_secondary_secret');
    assert_same('g2ml_primary_secret', $extracted, 'Authorization: Bearer is canonical and must win over X-API-Key');
});

test('extractBearer: an empty Bearer value falls through to X-API-Key', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('Bearer ', 'g2ml_fallback_secret');
    assert_same('g2ml_fallback_secret', $extracted, 'A Bearer header with no token must not shadow a usable X-API-Key');
});

test('extractBearer: neither header present yields null', function (): void
{
    $extracted = g2ml_apiExtractBearerToken(null, null);
    assert_same(null, $extracted, 'No credential presented must yield null, not an empty string');
});

test('extractBearer: a non-Bearer Authorization scheme is ignored', function (): void
{
    $extracted = g2ml_apiExtractBearerToken('Basic dXNlcjpwYXNz', null);
    assert_same(null, $extracted, 'A Basic-auth header must not be mistaken for a Bearer token');
});

// ============================================================================
// 🏷️ g2ml_apiKeyHasScope — scope membership
// ============================================================================

test('hasScope: a present scope returns true', function (): void
{
    $keyRow = ['permissions' => ['urls:read', 'account:read']];
    assert_true(g2ml_apiKeyHasScope($keyRow, 'account:read'), 'account:read is in the permissions array');
});

test('hasScope: an absent scope returns false', function (): void
{
    $keyRow = ['permissions' => ['urls:read']];
    assert_false(g2ml_apiKeyHasScope($keyRow, 'account:read'), 'account:read is NOT in the permissions array');
});

test('hasScope: a missing permissions key returns false rather than erroring', function (): void
{
    assert_false(g2ml_apiKeyHasScope([], 'account:read'), 'A key row with no permissions array must fail closed');
});

test('hasScope: a non-array permissions value returns false rather than erroring', function (): void
{
    $keyRow = ['permissions' => 'not-an-array'];
    assert_false(g2ml_apiKeyHasScope($keyRow, 'account:read'), 'A malformed permissions value must fail closed, not throw');
});

// ============================================================================
// 🙈 g2ml_apiRedactRequestBody — secret redaction before logging
// ============================================================================

test('redact: an Authorization-shaped key is redacted', function (): void
{
    $body = ['authorization' => 'Bearer g2ml_x_y', 'longURL' => 'https://example.com'];
    $redacted = g2ml_apiRedactRequestBody($body);
    assert_same('[REDACTED]', $redacted['authorization'], 'authorization must never reach the log verbatim');
    assert_same('https://example.com', $redacted['longURL'], 'Non-sensitive fields must pass through unchanged');
});

test('redact: apiKey/api_key/password/token/secret variants are all redacted', function (): void
{
    $body = [
        'apiKey'       => 'g2ml_x_y',
        'api_key'      => 'g2ml_x_y',
        'password'     => 'hunter2',
        'new_password' => 'hunter2',
        'token'        => 'abc',
        'secret'       => 'abc',
        'plaintextKey' => 'g2ml_x_y',
    ];
    $redacted = g2ml_apiRedactRequestBody($body);

    foreach (array_keys($body) as $key)
    {
        assert_same('[REDACTED]', $redacted[$key], $key . ' must be redacted');
    }
});

test('redact: matching is case-insensitive on the key name', function (): void
{
    $body = ['PASSWORD' => 'hunter2'];
    $redacted = g2ml_apiRedactRequestBody($body);
    assert_same('[REDACTED]', $redacted['PASSWORD'], 'Key-name matching must not be case-sensitive');
});

test('redact: nested arrays are redacted recursively', function (): void
{
    $body = ['user' => ['email' => 'a@example.com', 'password' => 'hunter2']];
    $redacted = g2ml_apiRedactRequestBody($body);
    assert_same('a@example.com', $redacted['user']['email'], 'Non-sensitive nested fields pass through');
    assert_same('[REDACTED]', $redacted['user']['password'], 'Sensitive nested fields are redacted too');
});

// ============================================================================
// 📦 g2ml_apiEnvelope / g2ml_apiErrorBody / g2ml_apiSanitiseRoute
// ============================================================================

test('envelope: success shape has status/data/meta with a timestamp and requestId', function (): void
{
    $envelope = g2ml_apiEnvelope(['pong' => true]);
    assert_same('success', $envelope['status'], 'Success envelopes carry status=success');
    assert_same(['pong' => true], $envelope['data'], 'The payload is carried verbatim under data');
    assert_true(isset($envelope['meta']['timestamp']), 'meta.timestamp must be auto-populated');
    assert_true(isset($envelope['meta']['requestId']), 'meta.requestId must be auto-populated');
});

test('envelope: caller-supplied meta overrides the auto-generated requestId', function (): void
{
    $envelope = g2ml_apiEnvelope(['pong' => true], ['requestId' => 'fixed-id-123']);
    assert_same('fixed-id-123', $envelope['meta']['requestId'], 'An explicit requestId must win over the auto-generated one');
});

test('envelope: caller-supplied meta can add extra fields such as rateLimit', function (): void
{
    $envelope = g2ml_apiEnvelope(['pong' => true], ['rateLimit' => ['limit' => 60, 'remaining' => 59]]);
    assert_same(60, $envelope['meta']['rateLimit']['limit'], 'Extra meta fields must be preserved');
});

test('errorBody: shape is status=error with code/message/field', function (): void
{
    $error = g2ml_apiErrorBody(401, 'Invalid or expired API key.');
    assert_same('error', $error['status'], 'Error envelopes carry status=error');
    assert_same(401, $error['error']['code'], 'error.code echoes the HTTP status');
    assert_same('Invalid or expired API key.', $error['error']['message'], 'error.message carries the message verbatim');
    assert_same(null, $error['error']['field'], 'field defaults to null when not supplied');
});

test('errorBody: an explicit field is carried through', function (): void
{
    $error = g2ml_apiErrorBody(422, 'Missing required field.', 'destination_url');
    assert_same('destination_url', $error['error']['field'], 'An explicit field name must be carried through');
});

test('sanitiseRoute: a plain route passes through unchanged', function (): void
{
    assert_same('account', g2ml_apiSanitiseRoute('account'), 'A simple alphanumeric route is valid');
});

test('sanitiseRoute: leading/trailing slashes are trimmed', function (): void
{
    assert_same('account', g2ml_apiSanitiseRoute('/account/'), 'Leading and trailing slashes must be trimmed');
});

test('sanitiseRoute: an empty route normalises to an empty string, not null', function (): void
{
    assert_same('', g2ml_apiSanitiseRoute(''), 'An empty route is a valid (though unmapped) route, not malformed input');
    assert_same('', g2ml_apiSanitiseRoute('/'), 'A bare slash normalises to the empty route');
});

test('sanitiseRoute: a nested path with slashes/hyphens/underscores is valid', function (): void
{
    assert_same('urls/my-code_1', g2ml_apiSanitiseRoute('urls/my-code_1'), 'Slashes, hyphens and underscores are all whitelisted');
});

test('sanitiseRoute: a directory-traversal attempt is rejected', function (): void
{
    assert_same(null, g2ml_apiSanitiseRoute('../../_auth_keys/auth_creds.php'), 'A traversal attempt (containing a dot) must be rejected outright');
});

test('sanitiseRoute: a null-byte or query-injection attempt is rejected', function (): void
{
    assert_same(null, g2ml_apiSanitiseRoute('account?x=1'), 'A query-string character is outside the whitelist and must be rejected');
    assert_same(null, g2ml_apiSanitiseRoute("account\x00"), 'A null byte is outside the whitelist and must be rejected');
});

test('sanitiseRoute: whitespace is rejected', function (): void
{
    assert_same(null, g2ml_apiSanitiseRoute('account ping'), 'A space is outside the whitelist and must be rejected');
});
