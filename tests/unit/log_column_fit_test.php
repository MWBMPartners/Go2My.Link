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
 * 🧪 Unit tests — fitting request text into log columns (#331)
 * ============================================================================
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/api_ratelimit.php';
require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

test('log columns (#331): the API log bound never splits a letter or keeps stray bytes', function (): void
{
    assert_same(str_repeat('é', 10), g2ml_apiBoundLogValue(str_repeat('é', 40), 10));
    assert_same(1, preg_match('//u', (string) g2ml_apiBoundLogValue("/api/v1/\xFF\xC3", 255)));
});

test('log columns (#331): a captured tracking value is cut without splitting a letter', function (): void
{
    $captured = g2ml_extractTrackingParams(['utm_source' => 'a' . str_repeat('é', 200), 'utm_medium' => "b\xFF"]);

    assert_same('a' . str_repeat('é', 127), $captured['utm_source']);
    assert_same(1, preg_match('//u', $captured['utm_medium']));
});

test('log columns (#331): JSON for a log column is always valid or empty', function (): void
{
    assert_same('{"a":1}', g2ml_encodeJSONColumn(['a' => 1], 'log data', 'activity log'));
    assert_true(json_decode((string) g2ml_encodeJSONColumn(["k\xFF" => "v\xFF"], 'log data', 'activity log')) !== null);

    $saved   = (string) ini_get('error_log');
    $logFile = (string) tempnam(sys_get_temp_dir(), 'g2ml_json_');
    ini_set('error_log', $logFile);

    try
    {
        assert_same(null, g2ml_encodeJSONColumn(['n' => INF], 'log data', 'activity log'));
    }
    finally
    {
        ini_set('error_log', $saved);
        @unlink($logFile);
    }
});
