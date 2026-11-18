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
 * 🐘 Go2My.Link — PHP version support status
 * ============================================================================
 *
 * Says whether a PHP version is one Go2My.Link supports, and gives a plain
 * sentence to show a person. Used by the installer's requirements page.
 *
 * The version is passed IN rather than read from PHP_VERSION_ID, so the same
 * checks can be tested on any PHP.
 *
 * Dependencies: None (standalone functions)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      #308
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * Say how well a PHP version is supported.
 *
 * @param  int    $versionId     PHP_VERSION_ID, for example 80427
 * @param  string $extraVersion  PHP_EXTRA_VERSION, for example 'RC7', '-dev' or '-1ubuntu1'
 * @return string  'too-old', 'pre-release', 'untested' or 'supported'
 */
function g2ml_phpSupportStatus(int $versionId, string $extraVersion): string
{
    // The oldest PHP that works (8.4.0).
    $lowestSupported = 80400;

    // The first PHP that has not been fully tested yet (8.6.0).
    // Raise this to 80700 when PHP 8.6 is promoted to fully tested. The two
    // messages below (they name 8.4, 8.5 and 8.6) and the expectations in
    // tests/unit/php_support_test.php must be updated together with it.
    $firstUntested = 80600;

    // Order matters: too old first, then pre-release, then untested.
    if ($versionId < $lowestSupported)
    {
        return 'too-old';
    }

    // A test build has a suffix such as 'alpha1', 'beta2', 'RC7' or '-dev'.
    // A suffix a Linux distribution adds, such as '-1ubuntu1', is not one.
    $isPreRelease = false;

    if (preg_match('/^(alpha|beta|rc)\d*/i', $extraVersion) === 1)
    {
        $isPreRelease = true;
    }

    if (str_starts_with($extraVersion, '-dev'))
    {
        $isPreRelease = true;
    }

    if ($isPreRelease)
    {
        return 'pre-release';
    }

    if ($versionId >= $firstUntested)
    {
        return 'untested';
    }

    return 'supported';
}

/**
 * Give a plain-English sentence for a support status.
 *
 * @param  string $status       The value from g2ml_phpSupportStatus()
 * @param  string $versionText  The version to show, for example '8.6.0RC7'
 * @return string
 */
function g2ml_phpSupportMessage(string $status, string $versionText): string
{
    if ($status === 'too-old')
    {
        return 'PHP ' . $versionText . ' is too old. Go2My.Link needs PHP 8.4 or newer.';
    }

    if ($status === 'pre-release')
    {
        return 'PHP ' . $versionText . ' is a test (pre-release) build of PHP. '
            . 'Go2My.Link is tested on PHP 8.4 and 8.5, and PHP 8.6 is being tested. '
            . 'Do not run a live site on a pre-release PHP.';
    }

    if ($status === 'untested')
    {
        return 'PHP ' . $versionText . ' is newer than the versions Go2My.Link has been fully tested on (8.4 and 8.5). '
            . 'It may work, but has not been fully tested yet.';
    }

    return 'PHP ' . $versionText . ' is a version Go2My.Link has been tested on.';
}
