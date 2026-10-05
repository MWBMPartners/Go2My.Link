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
 * 🧪 Check — form values are escaped once, by formField() only (#280)
 * ============================================================================
 *
 * formField() (web/_includes/accessibility.php) escapes the value it is given.
 * Several pages escaped the value first as well, so "&" and apostrophes
 * showed as "&amp;" and "&#039;" in the box, and pressing Save stored that
 * text: a link's destination "?a=1&b=2" became "?a=1&amp;b=2" (#280).
 *
 * This check reads every page under web/ that calls formField() and fails if
 * a value is escaped before it is handed over. It also checks
 * g2ml_postedText(), the helper those pages now use to refill a field.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It recognises two shapes: 'value' => g2ml_sanitiseOutput(...) or
 * htmlspecialchars(...), and $fieldValue = one of those two. A value escaped
 * into some other variable first is not seen.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

/**
 * Lines in one file where a value is escaped before formField() sees it.
 *
 * @param  string $source
 * @return array<int, int>
 */
function _g2mlEscapedOnceTestProblems(string $source): array
{
    $problems   = array();
    $lineNumber = 0;

    foreach (preg_split('/\R/', $source) as $line)
    {
        $lineNumber = $lineNumber + 1;

        if (preg_match("/'value'\\s*=>\\s*(g2ml_sanitiseOutput|htmlspecialchars)\\(/", $line) === 1)
        {
            $problems[] = $lineNumber;
            continue;
        }

        if (preg_match('/\\$fieldValue\\s*=\\s*(g2ml_sanitiseOutput|htmlspecialchars)\\(/', $line) === 1)
        {
            $problems[] = $lineNumber;
        }
    }

    return $problems;
}

test('form values: no page escapes a value before handing it to formField() (#280)', function (): void
{
    $webRoot  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)
    );
    $allProblems = array();

    foreach ($iterator as $fileInfo)
    {
        $path = $fileInfo->getPathname();

        if (substr($path, -4) !== '.php')
        {
            continue;
        }

        if (strpos($path, DIRECTORY_SEPARATOR . '_libraries' . DIRECTORY_SEPARATOR) !== false)
        {
            continue;
        }

        $source = file_get_contents($path);

        if ($source === false || strpos($source, 'formField(') === false)
        {
            continue;
        }

        foreach (_g2mlEscapedOnceTestProblems($source) as $lineNumber)
        {
            $allProblems[] = substr($path, strlen($webRoot) + 1) . ':' . $lineNumber;
        }
    }

    assert_same(0, count($allProblems), 'Escaped before formField(): ' . implode(', ', $allProblems));
});

test('form values: the check itself catches both shapes', function (): void
{
    $sample = "<?php\n"
        . "\$fieldValue = g2ml_sanitiseOutput(\$_POST['email']);\n"
        . "echo formField(['value' => htmlspecialchars(\$name, ENT_QUOTES, 'UTF-8')]);\n"
        . "echo formField(['value' => \$name]);\n";

    assert_same(array(2, 3), _g2mlEscapedOnceTestProblems($sample), 'Lines 2 and 3 should be reported, line 4 not');
});

test('g2ml_postedText: returns posted text unchanged, and an empty string for missing or list values', function (): void
{
    $savedPost = $_POST;

    $_POST = array(
        'name' => "Seán O'Neil & Sons",
        'list' => array('a', 'b'),
    );

    assert_same("Seán O'Neil & Sons", g2ml_postedText('name'), 'Posted text must come back unescaped');
    assert_same('', g2ml_postedText('list'), 'A list must not be treated as text');
    assert_same('', g2ml_postedText('missing'), 'A missing field gives an empty string');

    $_POST = $savedPost;
});
