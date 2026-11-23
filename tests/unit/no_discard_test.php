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
 * 🧪 Check — the security checks must not have their answer thrown away (#308)
 * ============================================================================
 *
 * Nine functions give an answer that must be used (is the token valid, is the
 * destination allowed, and so on). Each carries #[\NoDiscard(...)]. From PHP
 * 8.5 that makes PHP warn when a caller ignores the answer. PHP 8.4 treats the
 * attribute as a comment, so these tests also read the source and so work on
 * every version:
 *   1. each of the nine functions has the attribute on the line above it;
 *   2. no line in web/ or tests/ calls one of them as a bare statement, and
 *      nothing uses the (void) cast, which PHP 8.4 cannot read;
 *   3. a small child PHP process ignores an answer on purpose, to see what
 *      this PHP version does.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The nine functions, with the file each one lives in (relative to the root).
 *
 * @return array<string, string>
 */
function _g2mlNoDiscardFunctions(): array
{
    return array(
        'g2ml_validateCSRFToken'             => 'web/_functions/security.php',
        'g2ml_verifyPassword'                => 'web/_functions/security.php',
        'g2ml_safePostLoginRedirect'         => 'web/_functions/security.php',
        'g2ml_sanitiseURL'                   => 'web/_functions/security.php',
        'g2ml_destinationHostIsAllowed'      => 'web/_functions/security.php',
        'g2ml_resolveAllowedDestinationIps'  => 'web/_functions/security.php',
        'g2ml_validateLinkDestination'       => 'web/Go2My.Link/_functions/shorturl_create.php',
        'g2ml_canUseFeature'                 => 'web/_functions/entitlements.php',
        'g2ml_destinationProblem'            => 'web/_functions/security.php',
    );
}

/**
 * Every .php file under web/ and tests/, third-party libraries left out.
 *
 * @param  string $root  The repository root
 * @return array<int, string>  Paths relative to the root
 */
function _g2mlNoDiscardPhpFiles(string $root): array
{
    $files = array();

    foreach (array('web', 'tests') as $folder)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo)
        {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($fileInfo->getPathname(), strlen($root) + 1));

            if (strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'php')
            {
                continue;
            }

            if (preg_match('#(^|/)_libraries/#', $relative) === 1)
            {
                continue;
            }

            $files[] = $relative;
        }
    }

    sort($files);

    return $files;
}

test('no discard: each of the nine security checks has the attribute on the line above it', function (): void
{
    $root = dirname(__DIR__, 2);

    foreach (_g2mlNoDiscardFunctions() as $name => $file)
    {
        $lines = file($root . '/' . $file, FILE_IGNORE_NEW_LINES);
        assert_true(is_array($lines), 'Could not read ' . $file);

        $found = false;

        foreach ($lines as $index => $line)
        {
            if (strpos($line, 'function ' . $name . '(') !== 0)
            {
                continue;
            }

            $found = true;
            $above = '';

            if ($index > 0)
            {
                $above = $lines[$index - 1];
            }

            assert_true(
                strpos($above, "#[\\NoDiscard('") === 0,
                $name . ' needs #[\\NoDiscard(...)] on the line directly above its declaration'
            );
        }

        assert_true($found, 'The declaration of ' . $name . ' was not found in ' . $file);
    }
});

test('no discard: nothing calls a security check as a bare statement, and nothing uses a (void) cast', function (): void
{
    $root  = dirname(__DIR__, 2);
    $names = implode('|', array_keys(_g2mlNoDiscardFunctions()));

    foreach (_g2mlNoDiscardPhpFiles($root) as $relative)
    {
        // This file names the casts and the calls it looks for.
        if ($relative === 'tests/unit/no_discard_test.php')
        {
            continue;
        }

        $lines = file($root . '/' . $relative, FILE_IGNORE_NEW_LINES);
        assert_true(is_array($lines), 'Could not read ' . $relative);

        $previous = '';

        foreach ($lines as $index => $line)
        {
            $position = $relative . ':' . ($index + 1);
            $trimmed  = ltrim($line);

            // Comment lines may talk about the cast; they do not use it.
            if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0)
            {
                continue;
            }

            assert_true(
                preg_match('/\(\s*void\s*\)\s*[$\\\\a-zA-Z_(]/', $line) !== 1,
                $position . ' uses a (void) cast, which PHP 8.4 cannot read; keep the answer in a named variable instead'
            );

            // A line that starts with the call is a bare statement only when
            // the line before it finished a statement or opened a block.
            // (A call that starts a line because it is an argument follows a
            // line ending in a comma or an opening bracket.)
            if (preg_match('/^\s*(' . $names . ')\(/', $line) === 1)
            {
                $last = substr($previous, -1);

                assert_false(
                    $previous === '' || $last === ';' || $last === '{' || $last === '}',
                    $position . ' calls a security check as a statement and throws the answer away'
                );
            }

            if (trim($line) !== '')
            {
                $previous = rtrim($line);
            }
        }
    }
});

test('no discard: a child PHP process shows what this PHP version does with an ignored answer', function (): void
{
    if (!function_exists('exec'))
    {
        echo "      (skipped: exec() is not available, so no child process can be started)\n";
        assert_true(true);

        return;
    }

    $root   = dirname(__DIR__, 2);
    $script = tempnam(sys_get_temp_dir(), 'g2ml_nodiscard_');
    assert_true(is_string($script), 'Could not create a temporary file');

    $code = "<?php\nerror_reporting(E_ALL);\nini_set('display_errors', '1');\n"
        . "define('ENCRYPTION_SALT', str_repeat('a1b2c3d4', 8));\n"
        . "define('ENCRYPTION_KEY_SECONDARY', str_repeat('9f8e7d6c', 8));\n"
        . "require " . var_export($root . '/web/_functions/security.php', true) . ";\n"
        . "g2ml_sanitiseURL('https://example.com/');\n"
        . "echo \"child finished\\n\";\n";

    file_put_contents($script, $code);

    $output = array();
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $status);
    unlink($script);

    $text = implode("\n", $output);

    assert_contains('child finished', $text, 'The child script did not run to the end: ' . $text);

    if (class_exists('NoDiscard', false))
    {
        echo '      (PHP ' . PHP_VERSION . ": NoDiscard exists, so the ignored answer must warn)\n";
        assert_contains('should either be used', $text, 'PHP should warn about the ignored answer');
    }
    else
    {
        echo '      (PHP ' . PHP_VERSION . ": no NoDiscard class, so the attribute is only a comment and there must be no warning)\n";
        assert_false(
            stripos($text, 'warning') !== false || stripos($text, 'deprecated') !== false,
            'PHP should print no warning: ' . $text
        );
    }
});
