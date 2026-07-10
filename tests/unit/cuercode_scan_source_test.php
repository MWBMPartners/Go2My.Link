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
 * 🧪 Unit tests — g2ml_resolveScanSource() CueRCode scan-attribution guard (#145)
 * ============================================================================
 *
 * Pure-function, DB-free coverage for the query-string check that decides
 * whether a Component B redirect is tagged as a CueRCode QR scan. This is the
 * FIRST line of defence in the scan-attribution feature: the function must
 * never echo back attacker-controlled query-string text, and must return null
 * (meaning "not a scan — log neither field") for every shape of malformed or
 * non-matching input, so a normal redirect is provably unaffected.
 *
 * The companion database-backed proof — that qrCodeExternalID is looked up
 * from the STORED short URL row (never trusted from the query string) and
 * that logActivity() persists both fields correctly — lives in
 * tests/integration/cuercode_test.php and tests/integration/activity_log_test.php.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#145)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

test('resolveScanSource: the configured param+value pair matches, returning the CONFIGURED value', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['src' => 'qr']);
    assert_same('qr', $result, 'A matching scan-source param must return the configured expected value');
});

test('resolveScanSource: a non-matching value returns null', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['src' => 'not-qr']);
    assert_same(null, $result, 'A present but non-matching value must return null');
});

test('resolveScanSource: an absent param returns null (a normal redirect)', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', []);
    assert_same(null, $result, 'A normal redirect (no scan-source param at all) must return null');
});

test('resolveScanSource: an empty configured param name disables the feature entirely', function (): void
{
    $result = g2ml_resolveScanSource('', 'qr', ['src' => 'qr']);
    assert_same(null, $result, 'An empty scan_source_param setting must disable the check');
});

test('resolveScanSource: an empty configured expected value disables the feature entirely', function (): void
{
    $result = g2ml_resolveScanSource('src', '', ['src' => 'qr']);
    assert_same(null, $result, 'An empty scan_source_value setting must disable the check');
});

test('resolveScanSource: a non-string query value (e.g. an array from src[]=x) is rejected, not coerced', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['src' => ['qr']]);
    assert_same(null, $result, 'A non-string query value must never be treated as a match');
});

test('resolveScanSource: the RAW query-string value is never returned, even when it superficially matches by prefix', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['src' => 'qr; DROP TABLE tblActivityLog']);
    assert_same(null, $result, 'Anything other than an EXACT match to the configured value must return null');
});

test('resolveScanSource: the returned value is capped to the tblActivityLog.scanSource VARCHAR(50) column width', function (): void
{
    $longExpectedValue = str_repeat('q', 60);
    $result            = g2ml_resolveScanSource('src', $longExpectedValue, ['src' => $longExpectedValue]);

    assert_same(50, strlen((string) $result), 'A configured expected value longer than 50 chars must be capped to 50');
    assert_same(str_repeat('q', 50), $result, 'The capped value must be a straight truncation of the configured value');
});

test('resolveScanSource: a differently-cased query value does NOT match (case-sensitive by design)', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['src' => 'QR']);
    assert_same(null, $result, 'The match is exact/case-sensitive — operators configure the exact value CueRCode sends');
});

test('resolveScanSource: an unrelated query string parameter never accidentally matches', function (): void
{
    $result = g2ml_resolveScanSource('src', 'qr', ['utm_source' => 'qr']);
    assert_same(null, $result, 'Only the CONFIGURED param name is ever inspected');
});
