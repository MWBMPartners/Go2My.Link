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
 * 🧪 Check — every dirname(__DIR__, N) path points at something real (#277)
 * ============================================================================
 *
 * Files find their neighbours by climbing N folders with dirname(__DIR__, N)
 * and then naming a folder or file. Climbing one level too few or too many
 * breaks a feature: the homepage "Shorten" endpoint crashed with an HTTP 500, and
 * the dashboard's "Create link" page always said the service was unavailable,
 * because each climbed one level too few (#277).
 *
 * This check reads every first-party PHP file under web/, works out where each
 * dirname(__DIR__, N) . DIRECTORY_SEPARATOR . '<name>' path lands, and fails
 * if that place does not exist. Two cases are judged differently:
 *   - '.auth' is not in a checkout (its file is git-ignored), so the folder
 *     it sits in must instead be a component folder (one that holds a
 *     public_html/ folder).
 *   - define('G2ML_ROOT', dirname(__DIR__, N)) must land on web/, which is
 *     recognised by its _includes/page_init.php.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It skips text inside heredoc strings (the installer writes PHP from a
 * heredoc, and that code runs from a different folder), comment lines, and
 * the '_logs' folder (created when first needed) and the GeoIP '.mmdb'
 * database (git-ignored, downloaded at deploy time, #43). It cannot follow a path
 * stored in a variable and built up later.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Every first-party PHP file under web/ (third-party folders left out).
 *
 * @return array<int, string>
 */
function _g2mlFolderDepthTestFiles(): array
{
    $webRoot  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web';
    $files    = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo)
    {
        $path = $fileInfo->getPathname();

        if (substr($path, -4) !== '.php')
        {
            continue;
        }

        $relative = substr($path, strlen($webRoot));

        if (strpos($relative, DIRECTORY_SEPARATOR . '_libraries' . DIRECTORY_SEPARATOR) !== false)
        {
            continue;
        }

        if (strpos($relative, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false)
        {
            continue;
        }

        $files[] = $path;
    }

    sort($files);

    return $files;
}

/**
 * Character ranges [start, end) of every heredoc or nowdoc body in $source.
 *
 * @param  string $source
 * @return array<int, array{0: int, 1: int}>
 */
function _g2mlFolderDepthTestHeredocRanges(string $source): array
{
    $ranges  = array();
    $matches = array();

    preg_match_all("/<<<\\s*['\"]?([A-Za-z_][A-Za-z0-9_]*)['\"]?\\R.*?\\R\\s*\\1\\b/s", $source, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $match)
    {
        $ranges[] = array($match[1], $match[1] + strlen($match[0]));
    }

    return $ranges;
}

/**
 * True when $offset falls inside one of $ranges.
 *
 * @param  int                              $offset
 * @param  array<int, array{0: int, 1: int}> $ranges
 * @return bool
 */
function _g2mlFolderDepthTestInRanges(int $offset, array $ranges): bool
{
    foreach ($ranges as $range)
    {
        if ($offset >= $range[0] && $offset < $range[1])
        {
            return true;
        }
    }

    return false;
}

/**
 * True when the line holding $offset starts with a comment marker.
 *
 * @param  string $source
 * @param  int    $offset
 * @return bool
 */
function _g2mlFolderDepthTestIsCommentLine(string $source, int $offset): bool
{
    $lineStart = strrpos(substr($source, 0, $offset), "\n");

    if ($lineStart === false)
    {
        $lineStart = 0;
    }
    else
    {
        $lineStart = $lineStart + 1;
    }

    $before = ltrim(substr($source, $lineStart, $offset - $lineStart));

    if (strncmp($before, '//', 2) === 0)
    {
        return true;
    }

    if (strncmp($before, '*', 1) === 0)
    {
        return true;
    }

    if (strncmp($before, '#', 1) === 0)
    {
        return true;
    }

    return false;
}

/**
 * Every dirname(__DIR__, N) path in one file that lands nowhere.
 *
 * @param  string $filePath
 * @return array<int, string>  One plain-English line per broken path.
 */
function _g2mlFolderDepthTestProblems(string $filePath): array
{
    $problems = array();
    $source   = file_get_contents($filePath);

    if ($source === false)
    {
        return array('could not read ' . $filePath);
    }

    $heredocs = _g2mlFolderDepthTestHeredocRanges($source);
    $matches  = array();
    $pattern  = "/dirname\\(__DIR__(?:\\s*,\\s*(\\d+))?\\s*\\)((?:\\s*\\.\\s*DIRECTORY_SEPARATOR\\s*\\.\\s*'[^']+')*)/";

    preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

    foreach ($matches[0] as $index => $fullMatch)
    {
        $offset = $fullMatch[1];

        if (_g2mlFolderDepthTestInRanges($offset, $heredocs))
        {
            continue;
        }

        if (_g2mlFolderDepthTestIsCommentLine($source, $offset))
        {
            continue;
        }

        $levels = 1;

        if ($matches[1][$index][0] !== null)
        {
            $levels = (int) $matches[1][$index][0];
        }

        $base = dirname($filePath, $levels + 1);

        $segmentMatches = array();
        preg_match_all("/'([^']+)'/", (string) $matches[2][$index][0], $segmentMatches);
        $segments = $segmentMatches[1];

        $lineNumber = substr_count(substr($source, 0, $offset), "\n") + 1;
        $where      = $filePath . ':' . $lineNumber;

        if (count($segments) === 0)
        {
            $contextStart = max(0, $offset - 40);
            $context      = substr($source, $contextStart, $offset - $contextStart);

            if (strpos($context, 'G2ML_ROOT') !== false)
            {
                $pageInit = $base . DIRECTORY_SEPARATOR . '_includes' . DIRECTORY_SEPARATOR . 'page_init.php';

                if (!is_file($pageInit))
                {
                    $problems[] = $where . ' — G2ML_ROOT lands on ' . $base . ', which is not web/';
                }
            }

            continue;
        }

        if ($segments[0] === '_logs')
        {
            continue;
        }

        if (substr($segments[count($segments) - 1], -5) === '.mmdb')
        {
            continue;
        }

        if ($segments[0] === '.auth')
        {
            if (!is_dir($base . DIRECTORY_SEPARATOR . 'public_html'))
            {
                $problems[] = $where . ' — .auth is looked for in ' . $base . ', which is not a component folder';
            }

            continue;
        }

        $target = $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);

        if (!file_exists($target))
        {
            $problems[] = $where . ' — ' . $target . ' does not exist';
        }
    }

    return $problems;
}

test('folder depth: every dirname(__DIR__, N) path under web/ points at something real (#277)', function (): void
{
    $allProblems = array();

    foreach (_g2mlFolderDepthTestFiles() as $filePath)
    {
        foreach (_g2mlFolderDepthTestProblems($filePath) as $problem)
        {
            $allProblems[] = $problem;
        }
    }

    assert_same(0, count($allProblems), 'These paths climb the wrong number of folders: ' . implode(' | ', $allProblems));
});

test('folder depth: the check itself catches a path that climbs one level too few', function (): void
{
    $temporaryFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_folder_depth_' . bin2hex(random_bytes(4));
    $nestedFolder    = $temporaryFolder . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b';
    mkdir($nestedFolder, 0700, true);
    mkdir($temporaryFolder . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . '_functions', 0700, true);

    $probeFile = $nestedFolder . DIRECTORY_SEPARATOR . 'probe.php';
    $goodLine  = "\$good = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . '_functions';\n";
    $badLine   = "\$bad = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '_functions';\n";
    file_put_contents($probeFile, "<?php\n" . $goodLine . $badLine);

    $problems = _g2mlFolderDepthTestProblems($probeFile);

    unlink($probeFile);
    rmdir($temporaryFolder . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . '_functions');
    rmdir($nestedFolder);
    rmdir($temporaryFolder . DIRECTORY_SEPARATOR . 'a');
    rmdir($temporaryFolder);

    assert_same(1, count($problems), 'Exactly the one wrong path should be reported');
    assert_true(strpos($problems[0], 'probe.php:3') !== false, 'The report should name line 3');
});
