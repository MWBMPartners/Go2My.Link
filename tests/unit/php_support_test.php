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
 * 🧪 Unit tests — PHP version support status (#308)
 * ============================================================================
 *
 * The version is passed in, so every case runs on any PHP.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #308
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/php_support.php';

test('php support: each version and suffix gets the right status', function (): void
{
    $cases = array(
        array(80399, '', 'too-old'),
        array(80400, '', 'supported'),
        array(80427, '', 'supported'),
        array(80512, '', 'supported'),
        array(80600, 'RC7', 'pre-release'),
        array(80600, '', 'supported'),
        array(80601, '', 'supported'),
        array(80699, '', 'supported'),
        array(80427, '-dev', 'pre-release'),
        array(80427, '-1ubuntu1', 'supported'),
        // A development build with extra build text after '-dev'. A distribution
        // suffix that merely starts with '-dev' is unlikely, so any '-dev...' counts.
        array(80510, '-dev+foo', 'pre-release'),
        array(80700, '', 'untested'),
        array(80700, 'RC1', 'pre-release'),
        array(80700, '-dev', 'pre-release'),
        array(80399, 'RC1', 'too-old'),
        array(80600, 'alpha2', 'pre-release'),
        array(80600, 'beta1', 'pre-release'),
    );

    foreach ($cases as $case)
    {
        $label = $case[0] . ' with "' . $case[1] . '"';
        assert_same($case[2], g2ml_phpSupportStatus($case[0], $case[1]), $label);
    }
});

test('php support: every status has a plain sentence that names the version', function (): void
{
    foreach (array('too-old', 'pre-release', 'untested', 'supported') as $status)
    {
        $message = g2ml_phpSupportMessage($status, '8.6.0RC7');

        assert_true($message !== '', $status . ' message is not empty');
        assert_contains('8.6.0RC7', $message, $status . ' message names the version');
    }
});

test('php support: the pre-release message says not to run a live site', function (): void
{
    $message = g2ml_phpSupportMessage('pre-release', '8.6.0RC7');

    assert_contains('Do not run a live site on a pre-release PHP.', $message);
    assert_contains('is a test (pre-release) build of PHP', $message);
    assert_contains('tested on PHP 8.4, 8.5 and 8.6', $message);
});

test('php support: the untested message says it has not been fully tested', function (): void
{
    $message = g2ml_phpSupportMessage('untested', '8.7.0');

    assert_contains('(8.4, 8.5 and 8.6)', $message);
    assert_contains('It may work, but has not been fully tested yet', $message);
});
