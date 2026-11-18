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
 * 🧪 Go2My.Link — Test runner deprecation recording (#308)
 * ============================================================================
 *
 * The test runner records every deprecation notice and fails the run for each
 * one. This file checks the recording itself: it causes one notice on purpose,
 * checks it was recorded once with the right text, then puts the list back as
 * it found it. It never clears the list, because notices recorded earlier (while
 * files loaded, or by other tests) must still fail the run.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      Phase v1.0.0
 * ============================================================================
 */

test('test runner: a deprecation notice is recorded once, with its message', function ()
{
    $before = g2ml_test_get_deprecations();

    trigger_error('self-test deprecation notice', E_USER_DEPRECATED);

    $recorded = g2ml_test_get_deprecations();
    g2ml_test_restore_deprecations($before);

    assert_same(count($before) + 1, count($recorded), 'exactly one new notice should be recorded');

    $last = $recorded[count($recorded) - 1];
    assert_same('self-test deprecation notice', $last['message']);
    assert_same(__FILE__, $last['file']);
    assert_same($before, g2ml_test_get_deprecations(), 'the list should be back as it was');
});

test('test runner: a notice hidden with the @ operator is not recorded', function ()
{
    $before = g2ml_test_get_deprecations();

    @trigger_error('hidden self-test notice', E_USER_DEPRECATED);

    $after = g2ml_test_get_deprecations();
    g2ml_test_restore_deprecations($before);

    assert_same($before, $after);
});
