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
 * 🧪 Unit tests — API Key dashboard scope validation (#40)
 * ============================================================================
 *
 * Covers the pure, DB-free scope helpers added alongside the API Key
 * Management dashboard:
 *   (1) g2ml_apiValidScopesList()      — the canonical scope whitelist.
 *   (2) g2ml_apiValidateScopesInput()  — filters caller-supplied scopes down
 *       to that whitelist, dropping unrecognised values and de-duplicating.
 *
 * The rest of the API-key backend (#38) — g2ml_apiGenerateKey(),
 * g2ml_apiVerifyKey(), g2ml_apiRevokeKey(), g2ml_apiListKeys() — is already
 * covered by tests/unit/api_auth_test.php and the integration suite; this
 * file only adds coverage for the NEW helper introduced for the dashboard
 * page's "create key" form.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#40)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';
require_once dirname(__DIR__, 2) . '/web/_functions/api_auth.php';

// ============================================================================
// 📋 g2ml_apiValidScopesList — canonical whitelist
// ============================================================================

test('validScopesList: contains exactly the 9 documented scopes', function (): void
{
    $expectedScopes = [
        'urls:read',
        'urls:write',
        'urls:delete',
        'analytics:read',
        'domains:read',
        'domains:write',
        'org:read',
        'account:read',
        'qr:link',
    ];

    $actualScopes = g2ml_apiValidScopesList();

    assert_same(count($expectedScopes), count($actualScopes), 'The whitelist must have exactly 9 entries');

    foreach ($expectedScopes as $expectedScope)
    {
        assert_true(in_array($expectedScope, $actualScopes, true), $expectedScope . ' must be in the whitelist');
    }
});

// ============================================================================
// ✅ g2ml_apiValidateScopesInput — valid input
// ============================================================================

test('validateScopes: a fully valid scope set passes through unchanged', function (): void
{
    $requested = ['urls:read', 'account:read'];
    $validated = g2ml_apiValidateScopesInput($requested);

    assert_same(2, count($validated), 'Both valid scopes must survive validation');
    assert_true(in_array('urls:read', $validated, true), 'urls:read must be present');
    assert_true(in_array('account:read', $validated, true), 'account:read must be present');
});

test('validateScopes: a single valid scope passes through', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['qr:link']);
    assert_same(['qr:link'], $validated, 'A single valid scope must be returned as a one-element array');
});

// ============================================================================
// 🚫 g2ml_apiValidateScopesInput — invalid / malformed input
// ============================================================================

test('validateScopes: an unrecognised scope string is dropped', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['urls:read', 'sudo:everything']);
    assert_same(['urls:read'], $validated, 'A forged/unlisted scope must be silently dropped, not passed through');
});

test('validateScopes: an entirely bogus scope set yields an empty array', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['drop:table', 'admin:all']);
    assert_same([], $validated, 'No recognised scopes means an empty, not partial, result');
});

test('validateScopes: duplicate valid scopes are de-duplicated', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['urls:read', 'urls:read', 'urls:write']);
    assert_same(2, count($validated), 'Duplicates of the same scope must collapse to one entry');
});

test('validateScopes: non-string entries are ignored rather than erroring', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['urls:read', 123, null, ['nested']]);
    assert_same(['urls:read'], $validated, 'Non-string array entries must be skipped, not throw or coerce');
});

// ============================================================================
// ⭕ g2ml_apiValidateScopesInput — empty input
// ============================================================================

test('validateScopes: an empty scope set yields an empty array', function (): void
{
    $validated = g2ml_apiValidateScopesInput([]);
    assert_same([], $validated, 'No scopes requested (e.g. no checkboxes ticked) must yield an empty array, not an error');
});

test('validateScopes: the returned array is reindexed from 0', function (): void
{
    $validated = g2ml_apiValidateScopesInput(['bogus', 'urls:read', 'also-bogus', 'urls:write']);
    assert_same(0, array_keys($validated)[0], 'The first surviving element must be at index 0');
    assert_same(['urls:read', 'urls:write'], array_values($validated), 'Surviving scopes must be reindexed contiguously');
});
