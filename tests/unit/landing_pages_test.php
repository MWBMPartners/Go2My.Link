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
 * 🧪 Check — no coming-soon landing page has a form that throws data away (#211)
 * ============================================================================
 *
 * The lnks.page coming-soon page used to show a live "Notify Me" email
 * sign-up form whose action was "#". No file under any public_html_landing/
 * folder read $_POST or REQUEST_METHOD, so a visitor's address was thrown
 * away the moment the page reloaded. The go2my.link landing page had the
 * same form, commented out. Fixed by removing the live form and the CSS it
 * alone used, and by replacing the commented-out copy with an explanatory
 * comment so nobody brings it back by uncommenting.
 *
 * This check guards the fix: it fails the build if any coming-soon landing
 * page (web/*\/public_html_landing/*.php) gains either a live <form element
 * or the literal text action="#", so the same mistake cannot come back
 * without this test being touched too.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It is a plain text search that ignores case. So it also flags a <form
 * inside an HTML comment, and it cannot see a form that JavaScript or PHP
 * string-joining builds while the page runs. It reads only the .php and
 * .html files directly inside each public_html_landing/ folder.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#211)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🗂️ Finding the landing pages to scan.
// ============================================================================

/**
 * Return the absolute paths of every .php and .html file directly inside
 * each component's public_html_landing/ folder under web/ (there is no
 * nested structure to walk today, so this reads one directory per component
 * rather than recursing).
 *
 * @param  string $webRoot  Absolute path to the web/ directory.
 * @return array<int, string>
 */
function _g2mlLandingPagesTestCollectFiles(string $webRoot): array
{
    $collected = array();

    $componentDirectories = glob($webRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'public_html_landing', GLOB_ONLYDIR);

    if ($componentDirectories === false)
    {
        return $collected;
    }

    foreach ($componentDirectories as $landingDirectory)
    {
        $entries = scandir($landingDirectory);

        if ($entries === false)
        {
            continue;
        }

        foreach ($entries as $entry)
        {
            if ($entry === '.' || $entry === '..')
            {
                continue;
            }

            $fullPath  = $landingDirectory . DIRECTORY_SEPARATOR . $entry;
            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if (is_file($fullPath) && ($extension === 'php' || $extension === 'html'))
            {
                $collected[] = $fullPath;
            }
        }
    }

    sort($collected);

    return $collected;
}

/**
 * Turn an absolute path into one relative to $rootPath, using '/' separators
 * regardless of platform, so a failure message reads the same on every
 * machine that can run this suite.
 *
 * @param  string $rootPath  The absolute base path (no trailing slash).
 * @param  string $fullPath  An absolute path under $rootPath.
 * @return string
 */
function _g2mlLandingPagesTestRelativePath(string $rootPath, string $fullPath): string
{
    $normalisedRoot = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($rootPath, DIRECTORY_SEPARATOR));
    $normalisedFull = str_replace(DIRECTORY_SEPARATOR, '/', $fullPath);
    $prefix         = $normalisedRoot . '/';

    if (strncmp($normalisedFull, $prefix, strlen($prefix)) === 0)
    {
        return substr($normalisedFull, strlen($prefix));
    }

    return $normalisedFull;
}

// ============================================================================
// 🏃 The check itself.
// ============================================================================

test('no coming-soon landing page contains a <form or an action="#" (#211)', function (): void
{
    $repositoryRoot = dirname(__DIR__, 2);
    $webRoot         = $repositoryRoot . DIRECTORY_SEPARATOR . 'web';

    assert_true(is_dir($webRoot), 'web/ must exist for this check to run: ' . $webRoot);

    $filesScanned = _g2mlLandingPagesTestCollectFiles($webRoot);

    // At least one landing page must have been found, or the whole check is
    // a false pass (an empty $filesScanned would also produce zero findings).
    assert_true(count($filesScanned) > 0, 'No landing pages were found under ' . $webRoot . ' — the collector is wrong or the folders moved.');

    $failureLines = array();

    foreach ($filesScanned as $absoluteFilePath)
    {
        $relativeFilePath = 'web/' . _g2mlLandingPagesTestRelativePath($webRoot, $absoluteFilePath);
        $contents          = file_get_contents($absoluteFilePath);

        if ($contents === false)
        {
            $failureLines[] = $relativeFilePath . ': could not be read';
            continue;
        }

        if (stripos($contents, '<form') !== false)
        {
            $failureLines[] = $relativeFilePath . ': contains a <form — no landing page has a handler for one (#211)';
        }

        if (stripos($contents, 'action="#"') !== false || stripos($contents, "action='#'") !== false)
        {
            $failureLines[] = $relativeFilePath . ': contains action="#" — a form target that throws its data away (#211)';
        }
    }

    assert_same(
        array(),
        $failureLines,
        'Found a landing-page form that would throw its data away:' . PHP_EOL . implode(PHP_EOL, $failureLines)
    );
});

// ============================================================================
// 🧪 Self-tests for the collector, proving it actually finds the real
// landing pages and would find a form if one existed.
// ============================================================================

test('collector finds all three coming-soon landing pages', function (): void
{
    $repositoryRoot = dirname(__DIR__, 2);
    $webRoot         = $repositoryRoot . DIRECTORY_SEPARATOR . 'web';

    $files = _g2mlLandingPagesTestCollectFiles($webRoot);
    $names = array();

    foreach ($files as $file)
    {
        $names[] = _g2mlLandingPagesTestRelativePath($webRoot, $file);
    }

    assert_true(in_array('G2My.Link/public_html_landing/index.php', $names, true), 'Expected G2My.Link/public_html_landing/index.php to be scanned.');
    assert_true(in_array('Go2My.Link/public_html_landing/index.php', $names, true), 'Expected Go2My.Link/public_html_landing/index.php to be scanned.');
    assert_true(in_array('Lnks.page/public_html_landing/index.php', $names, true), 'Expected Lnks.page/public_html_landing/index.php to be scanned.');
});

test('relative-path helper strips the web root and normalises separators', function (): void
{
    $relative = _g2mlLandingPagesTestRelativePath('/repo/web', '/repo/web/Lnks.page/public_html_landing/index.php');

    assert_same('Lnks.page/public_html_landing/index.php', $relative);
});
