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
 * 🧪 Check — the front controllers run each page before the header (#278)
 * ============================================================================
 *
 * A page file sets its own title and description, and some pages redirect or
 * set a status code. None of that works once header.php has sent HTML: every
 * page showed the site name as its title, and /logout left a blank page
 * instead of redirecting (#278). Both front controllers now run the page into
 * a buffer first and print the header afterwards.
 *
 * This check reads both front controllers and fails if any page file is
 * required while no buffer has been started since the last header.php was
 * printed, or if header.php is printed between starting a buffer and running
 * the page.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads the source text in order; it does not run the files. It treats any
 * plain "require" line (not "require_once") as a page being run, which is
 * true of both files today. It does not follow if/else branches, so it cannot
 * tell a header.php printed just before ob_start() in the same branch from
 * one printed at the end of an earlier branch: the order header.php,
 * ob_start(), page is not caught.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Line numbers where a page file is required with no buffer started since the
 * last header.php was printed.
 *
 * @param  string $source
 * @return array<int, int>
 */
function _g2mlPageBeforeHeaderTestProblems(string $source): array
{
    $problems        = array();
    $lastHeaderLine  = 0;
    $lastBufferLine  = 0;
    $lineNumber      = 0;

    foreach (preg_split('/\R/', $source) as $line)
    {
        $lineNumber = $lineNumber + 1;
        $trimmed    = ltrim($line);

        if (strncmp($trimmed, '//', 2) === 0 || strncmp($trimmed, '*', 1) === 0)
        {
            continue;
        }

        if (strpos($line, "'header.php'") !== false && strpos($line, 'require') !== false)
        {
            $lastHeaderLine = $lineNumber;
            continue;
        }

        if (strpos($line, 'ob_start(') !== false)
        {
            $lastBufferLine = $lineNumber;
            continue;
        }

        if (preg_match('/^\s*require\s+(?!_once)/', $line) === 1)
        {
            if ($lastBufferLine <= $lastHeaderLine)
            {
                $problems[] = $lineNumber;
            }
        }
    }

    return $problems;
}

test('page before header: both front controllers buffer each page before printing header.php (#278)', function (): void
{
    $repository = dirname(__DIR__, 2);
    $files      = array(
        $repository . '/web/Go2My.Link/public_html/index.php',
        $repository . '/web/Go2My.Link/_admin/public_html/index.php',
    );

    foreach ($files as $file)
    {
        $source = file_get_contents($file);
        assert_true($source !== false, 'Could not read ' . $file);

        $problems = _g2mlPageBeforeHeaderTestProblems((string) $source);
        assert_same(0, count($problems), $file . ' runs a page after the header on line(s) ' . implode(', ', $problems));
    }
});

test('page before header: the check itself catches the old order', function (): void
{
    $oldOrder = "<?php\n"
        . "require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';\n"
        . "require \$resolved['file'];\n";

    assert_same(array(3), _g2mlPageBeforeHeaderTestProblems($oldOrder), 'The old order should be reported on line 3');

    $headerInsideBuffer = "<?php\n"
        . "ob_start();\n"
        . "require_once G2ML_INCLUDES . DIRECTORY_SEPARATOR . 'header.php';\n"
        . "require \$resolved['file'];\n";

    assert_same(array(4), _g2mlPageBeforeHeaderTestProblems($headerInsideBuffer), 'A header printed after the buffer starts should be reported on line 4');
});
