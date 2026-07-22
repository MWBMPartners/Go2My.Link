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
 * 🧪 Unit tests — Scheduled Jobs pure helpers (cron.php) (#163, #167)
 * ============================================================================
 *
 * Covers every PURE helper in web/_functions/cron.php — no database, no
 * settings layer, no superglobals beyond what is passed explicitly as a
 * parameter. These are the exact functions the dispatch endpoint and the
 * probabilistic page_init.php fallback both build on, so pinning their
 * behaviour here is what proves (independently of any live DB run):
 *
 *   - the token check is constant-time, fails closed on a null/empty/weak
 *     stored token, and matches only on an exact, well-formed byte match;
 *   - job-name parsing rejects anything outside the exact allow-list;
 *   - the deletion executor's mode resolution NEVER unlocks "live" on
 *     anything other than the exact boolean false for the dry-run flag;
 *   - every clamp helper fails safe on non-numeric/out-of-range input; and
 *   - the export-path confinement guard rejects both a directory-traversal
 *     escape AND a sibling-directory string-prefix collision.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.7.0 — GDPR Scheduled Jobs (#163, #167)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/cron.php';

// ============================================================================
// 🔑 g2ml_cronVerifyToken()
// ============================================================================

test('cron token: null provided token fails', function (): void
{
    assert_false(g2ml_cronVerifyToken(null, str_repeat('a', 64)), 'A null provided token must never match');
});

test('cron token: empty provided token fails', function (): void
{
    assert_false(g2ml_cronVerifyToken('', str_repeat('a', 64)), 'An empty provided token must never match');
});

test('cron token: null stored token fails (endpoint-inert-when-unseeded)', function (): void
{
    assert_false(g2ml_cronVerifyToken(str_repeat('a', 64), null), 'A null stored token (the seeded default) must never authenticate anyone');
});

test('cron token: empty stored token fails', function (): void
{
    assert_false(g2ml_cronVerifyToken(str_repeat('a', 64), ''), 'An empty stored token must never authenticate anyone');
});

test('cron token: a stored token shorter than 32 chars fails even on an identical match', function (): void
{
    $weakToken = str_repeat('a', 31);

    assert_false(g2ml_cronVerifyToken($weakToken, $weakToken), 'A weak (<32 char) stored token must fail closed, not "work"');
});

test('cron token: a 64-char exact match succeeds', function (): void
{
    $token = bin2hex(random_bytes(32));

    assert_true(g2ml_cronVerifyToken($token, $token), 'An identical 64-char token must verify');
});

test('cron token: a 64-char mismatch (one differing character) fails', function (): void
{
    $stored   = str_repeat('a', 64);
    $provided = str_repeat('a', 63) . 'b';

    assert_false(g2ml_cronVerifyToken($provided, $stored), 'A single differing character must fail verification');
});

test('cron token: a differing-length mismatch fails', function (): void
{
    $stored   = str_repeat('a', 64);
    $provided = str_repeat('a', 40);

    assert_false(g2ml_cronVerifyToken($provided, $stored), 'A shorter provided token must fail verification');
});

// ============================================================================
// 🔑 g2ml_cronReadPresentedToken()
// ============================================================================

test('cron token read: the header takes priority over the query parameter', function (): void
{
    $server = ['HTTP_X_G2ML_CRON_TOKEN' => 'from-header'];
    $get    = ['token' => 'from-query'];

    assert_same('from-header', g2ml_cronReadPresentedToken($server, $get), 'Header must win when both are present');
});

test('cron token read: falls back to ?token= when the header is absent', function (): void
{
    $server = [];
    $get    = ['token' => 'from-query'];

    assert_same('from-query', g2ml_cronReadPresentedToken($server, $get), 'The query parameter must be used when there is no header');
});

test('cron token read: returns null when neither the header nor the query parameter is present', function (): void
{
    assert_same(null, g2ml_cronReadPresentedToken([], []), 'No token anywhere must resolve to null');
});

test('cron token read: an empty header value falls back to the query parameter', function (): void
{
    $server = ['HTTP_X_G2ML_CRON_TOKEN' => ''];
    $get    = ['token' => 'from-query'];

    assert_same('from-query', g2ml_cronReadPresentedToken($server, $get), 'An empty header value must not shadow a real query token');
});

// ============================================================================
// 🧭 g2ml_cronNormaliseJobName()
// ============================================================================

test('job name: null normalises to "all"', function (): void
{
    assert_same('all', g2ml_cronNormaliseJobName(null), 'An absent ?job= must mean "run everything"');
});

test('job name: an empty string normalises to "all"', function (): void
{
    assert_same('all', g2ml_cronNormaliseJobName(''), 'An empty ?job= must mean "run everything"');
});

test('job name: "deletion" passes through unchanged', function (): void
{
    assert_same('deletion', g2ml_cronNormaliseJobName('deletion'));
});

test('job name: "retention" passes through unchanged', function (): void
{
    assert_same('retention', g2ml_cronNormaliseJobName('retention'));
});

test('job name: "all" passes through unchanged', function (): void
{
    assert_same('all', g2ml_cronNormaliseJobName('all'));
});

test('job name: "ALL" (wrong case) is rejected, not silently folded', function (): void
{
    assert_same(null, g2ml_cronNormaliseJobName('ALL'), 'Job names are lower-case only — no case folding');
});

test('job name: an unrecognised string is rejected', function (): void
{
    assert_same(null, g2ml_cronNormaliseJobName('foo'));
});

test('job name: a non-string value (array) is rejected', function (): void
{
    assert_same(null, g2ml_cronNormaliseJobName(['deletion']));
});

// ============================================================================
// 📅 g2ml_cronEffectiveGraceDays()
// ============================================================================

test('grace days: a valid numeric string passes through', function (): void
{
    assert_same(30, g2ml_cronEffectiveGraceDays('30'));
});

test('grace days: zero falls back to 30 (never shrinks the grace window to 0)', function (): void
{
    assert_same(30, g2ml_cronEffectiveGraceDays(0));
});

test('grace days: a negative value falls back to 30', function (): void
{
    assert_same(30, g2ml_cronEffectiveGraceDays(-5));
});

test('grace days: a non-numeric value falls back to 30', function (): void
{
    assert_same(30, g2ml_cronEffectiveGraceDays('abc'));
});

test('grace days: a value above the sane 365-day ceiling falls back to 30', function (): void
{
    assert_same(30, g2ml_cronEffectiveGraceDays(400));
});

test('grace days: a valid in-range value passes through unchanged', function (): void
{
    assert_same(7, g2ml_cronEffectiveGraceDays(7));
});

// ============================================================================
// 🚦 g2ml_cronDeletionMode()
// ============================================================================

test('deletion mode: enabled=false is always "disabled", regardless of dry-run', function (): void
{
    assert_same('disabled', g2ml_cronDeletionMode(false, false));
    assert_same('disabled', g2ml_cronDeletionMode(false, true));
    assert_same('disabled', g2ml_cronDeletionMode(false, null));
});

test('deletion mode: enabled=true, dryRun=true is "dry-run"', function (): void
{
    assert_same('dry-run', g2ml_cronDeletionMode(true, true));
});

test('deletion mode: enabled=true, dryRun=null is "dry-run" (fail-safe)', function (): void
{
    assert_same('dry-run', g2ml_cronDeletionMode(true, null));
});

test('deletion mode: enabled=true, dryRun="0" (string) is still "dry-run" (only exact boolean false unlocks live)', function (): void
{
    assert_same('dry-run', g2ml_cronDeletionMode(true, '0'));
});

test('deletion mode: enabled=true, dryRun=false (exact boolean) is "live"', function (): void
{
    assert_same('live', g2ml_cronDeletionMode(true, false));
});

test('deletion mode: enabled="1" (string, not exact boolean true) is "disabled" (fail-safe)', function (): void
{
    assert_same('disabled', g2ml_cronDeletionMode('1', false));
});

// ============================================================================
// 🧮 g2ml_cronClampInt()
// ============================================================================

test('clamp int: an in-range value passes through unchanged', function (): void
{
    assert_same(10, g2ml_cronClampInt(10, 1, 100, 10));
});

test('clamp int: a value below the minimum clamps up to the minimum', function (): void
{
    assert_same(1, g2ml_cronClampInt(-5, 1, 100, 10));
});

test('clamp int: a value above the maximum clamps down to the maximum', function (): void
{
    assert_same(100, g2ml_cronClampInt(500, 1, 100, 10));
});

test('clamp int: a non-numeric value falls back to the fallback', function (): void
{
    assert_same(10, g2ml_cronClampInt('abc', 1, 100, 10));
});

test('clamp int: the retention batch size range clamps correctly (50-2000)', function (): void
{
    assert_same(50, g2ml_cronClampInt(1, 50, 2000, 500));
    assert_same(2000, g2ml_cronClampInt(999999, 50, 2000, 500));
});

test('clamp int: divisor of 0 clamps to 0 (the "fallback path disabled" sentinel)', function (): void
{
    assert_same(0, g2ml_cronClampInt(0, 0, PHP_INT_MAX, 500));
});

test('clamp int: a negative divisor also clamps to 0', function (): void
{
    assert_same(0, g2ml_cronClampInt(-3, 0, PHP_INT_MAX, 500));
});

test('clamp int: a non-numeric divisor falls back to the default (not 0)', function (): void
{
    assert_same(500, g2ml_cronClampInt(null, 0, PHP_INT_MAX, 500));
});

// ============================================================================
// 📁 g2ml_retentionPathIsConfined()
// ============================================================================

test('path confinement: a file directly inside the base directory is confined', function (): void
{
    assert_true(g2ml_retentionPathIsConfined('/var/app/exports', '/var/app/exports/export_1.json'));
});

test('path confinement: a directory-traversal escape is rejected', function (): void
{
    assert_false(g2ml_retentionPathIsConfined('/var/app/exports', '/var/app/secrets/export_1.json'));
});

test('path confinement: a sibling directory sharing a string prefix is rejected (trailing-separator trap)', function (): void
{
    assert_false(g2ml_retentionPathIsConfined('/var/app/exports', '/var/app/exports_evil/export_1.json'));
});

test('path confinement: an empty base directory is never confined', function (): void
{
    assert_false(g2ml_retentionPathIsConfined('', '/var/app/exports/export_1.json'));
});

test('path confinement: an empty file path is never confined', function (): void
{
    assert_false(g2ml_retentionPathIsConfined('/var/app/exports', ''));
});
