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
 * 🧪 Check — scripts/php-lint.sh flags real problems, and is not fooled by
 *    a message PHP prints every time it starts (#308)
 * ============================================================================
 *
 * The script runs in a child process, in a temporary folder, on tiny PHP
 * files written for the purpose. It is tried four ways:
 *
 *   a. a clean file passes (exit 0, "0 problems");
 *   b. a file with a syntax error fails (exit 1);
 *   c. the same two again with a FAKE "php" first on the PATH. The fake
 *      prints one start-up warning on every run and then runs the real PHP.
 *      Without the start-up rule every file would be flagged, and the
 *      required Backend check would fail for a reason that is not our code;
 *   d. a file that gives a deprecation notice is reported when PHP prints no
 *      start-up message at all (the "empty list" path of the script);
 *   e. PHP that cannot start at all (the fake prints a fatal error) is
 *      reported, with exit 1.
 *
 * Not tried: running the script with no file names. That lints the whole
 * repository, which takes several seconds, and the unit run is meant to be
 * quick. The CI lint step does that already.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #308
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Run a shell command and return its exit code and combined output.
 *
 * @param  string $command
 * @return array{0: int, 1: string}
 */
function _g2mlPhpLintTestRun(string $command): array
{
    $lines = array();
    $code  = 0;

    exec($command . ' 2>&1', $lines, $code);

    return array($code, implode("\n", $lines));
}

/**
 * Make a fresh temporary folder holding a clean file, a broken file and,
 * optionally, a fake php that prints the given lines first.
 *
 * @param  string $fakeStartupText Text the fake php prints first ('' = no fake php).
 * @return string The folder path.
 */
function _g2mlPhpLintTestFolder(string $fakeStartupText): string
{
    $folder = sys_get_temp_dir() . '/g2ml-lint-test-' . bin2hex(random_bytes(6));
    mkdir($folder, 0700);

    file_put_contents($folder . '/clean.php', "<?php\necho 'ok';\n");
    file_put_contents($folder . '/deprecated.php', "<?php\nfunction demo(\$value = null, \$other)\n{\n    return \$other;\n}\n");
    file_put_contents($folder . '/broken.php', "<?php\necho 'ok'\necho 'missing semicolon';\n");

    if ($fakeStartupText !== '')
    {
        mkdir($folder . '/bin');
        // Like real PHP, the fake prints its message unless it is asked to
        // hide errors with "-d display_errors=0".
        $script = "#!/bin/sh\ncase \"\$*\" in\n    *display_errors=0*)\n        ;;\n    *)\n        " . $fakeStartupText . "\n        ;;\nesac\nexec " . escapeshellarg(PHP_BINARY) . " \"\$@\"\n";
        file_put_contents($folder . '/bin/php', $script);
        chmod($folder . '/bin/php', 0700);
    }

    return $folder;
}

/**
 * Run scripts/php-lint.sh on one file in the folder.
 *
 * @param  string $folder
 * @param  string $fileName
 * @param  bool   $useFakePhp
 * @return array{0: int, 1: string}
 */
function _g2mlPhpLintTestLint(string $folder, string $fileName, bool $useFakePhp): array
{
    $script = dirname(__DIR__, 2) . '/scripts/php-lint.sh';
    $path   = '';

    if ($useFakePhp)
    {
        $path = 'PATH=' . escapeshellarg($folder . '/bin:' . (string) getenv('PATH')) . ' ';
    }

    return _g2mlPhpLintTestRun(
        'cd ' . escapeshellarg($folder) . ' && ' . $path . 'TMPDIR=' . escapeshellarg($folder) . ' sh ' . escapeshellarg($script) . ' ' . escapeshellarg($fileName)
    );
}

/**
 * Remove the temporary folder made by _g2mlPhpLintTestFolder().
 *
 * @param  string $folder
 * @return void
 */
function _g2mlPhpLintTestCleanUp(string $folder): void
{
    foreach (array('/bin/php', '/bin', '/clean.php', '/broken.php', '/deprecated.php') as $part)
    {
        if (is_dir($folder . $part))
        {
            rmdir($folder . $part);
        }
        elseif (file_exists($folder . $part))
        {
            unlink($folder . $part);
        }
    }

    rmdir($folder);
}

test('php-lint.sh: a clean file passes and a file with a syntax error fails', function (): void
{
    $folder = _g2mlPhpLintTestFolder('');

    list($cleanCode, $cleanText)   = _g2mlPhpLintTestLint($folder, 'clean.php', false);
    list($brokenCode, $brokenText) = _g2mlPhpLintTestLint($folder, 'broken.php', false);

    _g2mlPhpLintTestCleanUp($folder);

    assert_same(0, $cleanCode, 'A clean file must exit 0: ' . $cleanText);
    assert_contains('1 files, 0 problems', $cleanText);
    assert_same(1, $brokenCode, 'A file with a syntax error must exit 1: ' . $brokenText);
    assert_contains('1 problems', $brokenText);
});

test('php-lint.sh: a message PHP prints at every start-up is ignored, real problems still fail', function (): void
{
    $folder = _g2mlPhpLintTestFolder("echo 'Warning: Module \"example\" is already loaded in Unknown on line 0'");

    list($cleanCode, $cleanText)   = _g2mlPhpLintTestLint($folder, 'clean.php', true);
    list($brokenCode, $brokenText) = _g2mlPhpLintTestLint($folder, 'broken.php', true);

    _g2mlPhpLintTestCleanUp($folder);

    assert_same(0, $cleanCode, 'A clean file must still exit 0 when PHP prints a start-up warning: ' . $cleanText);
    assert_contains('1 files, 0 problems', $cleanText);
    assert_contains('ignored 1 start-up line', $cleanText);
    assert_same(1, $brokenCode, 'A syntax error must still exit 1: ' . $brokenText);
});

test('php-lint.sh: PHP that cannot start is reported and fails', function (): void
{
    $folder = _g2mlPhpLintTestFolder("echo 'PHP Fatal error:  Cannot start in Unknown on line 0'");

    list($code, $text) = _g2mlPhpLintTestLint($folder, 'clean.php', true);

    _g2mlPhpLintTestCleanUp($folder);

    assert_same(1, $code, 'A start-up fatal error must exit 1: ' . $text);
    assert_contains('could not start', $text);
});

test('php-lint.sh: with no start-up message, a deprecation is still reported', function (): void
{
    $folder = _g2mlPhpLintTestFolder('');

    list($code, $text) = _g2mlPhpLintTestLint($folder, 'deprecated.php', false);

    _g2mlPhpLintTestCleanUp($folder);

    assert_same(1, $code, 'A deprecation must fail the lint: ' . $text);
    assert_contains('Deprecated:', $text);
});

test('php-lint.sh: the summary shows a clean version number when PHP prints a start-up message', function (): void
{
    $folder = _g2mlPhpLintTestFolder("echo 'Warning: Module \"example\" is already loaded in Unknown on line 0'");

    list($code, $text) = _g2mlPhpLintTestLint($folder, 'clean.php', true);

    _g2mlPhpLintTestCleanUp($folder);

    assert_same(0, $code, $text);
    assert_contains('(PHP ' . PHP_VERSION . '; ignored 1 start-up line)', $text);
});
