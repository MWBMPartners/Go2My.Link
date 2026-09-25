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
 * 🧪 Regression tests — direct-access guards compare paths, not names (#198)
 * ============================================================================
 *
 * Every library file in this codebase opens with a guard meant to send
 * somebody away if they point a browser straight at it: "if this file is the
 * one Apache is running, redirect and stop." The guard used to compare only
 * FILE NAMES (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)).
 * That broke the moment a real endpoint happened to share a name with a
 * library — web/Go2My.Link/_admin/public_html/cron.php loading the library
 * web/_functions/cron.php is the case that was actually live: both files are
 * called "cron.php", so the guard decided the library was being opened
 * directly and redirected before the endpoint's job code ran. The
 * scheduled-jobs endpoint — GDPR account deletion (#163) and data retention
 * (#167) — was completely dead as a result.
 *
 * The fix compares the REAL, RESOLVED path (realpath()) instead of the bare
 * name, so two different files that merely share a name are told apart.
 *
 * WHY A CHILD PROCESS IS THE ONLY MEANINGFUL PROOF
 *
 * When the guard fires it calls exit, which stops everything after the
 * require and ends the process with status 0. The test therefore runs the
 * require in a child process and looks for a marker printed after it: the
 * marker appears only if the guard let execution continue.
 *
 * WHAT THIS FILE CANNOT DO
 *
 * It cannot exercise the guard as PHP-FPM or Apache's mod_php would run it —
 * only what a bare `php <script>` CLI invocation with SCRIPT_FILENAME forced
 * by hand reproduces. It also does not run when the CLI's exec() is
 * disabled; that condition is reported as SKIPPED rather than silently
 * passed or silently failed.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#198)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * Runs a short, throwaway PHP script in a brand-new child process and
 * returns its combined stdout+stderr together with its real exit code.
 *
 * A child process is needed because when the guard fires it calls exit,
 * which would end the whole test run.
 *
 * @param  string $scriptBody  PHP code (no opening "<?php" tag) to run.
 * @return array{output: string, exitCode: int}
 */
function g2ml_directAccessGuard_runChildScript(string $scriptBody): array
{
    $script = '<?php' . "\n" . 'declare(strict_types=1);' . "\n" . $scriptBody;

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_guard_');

    if ($tempFile === false)
    {
        return array(
            'output'   => '',
            'exitCode' => -1,
        );
    }

    try
    {
        file_put_contents($tempFile, $script);

        $command     = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1';
        $outputLines = array();
        $exitCode    = -1;

        exec($command, $outputLines, $exitCode);

        return array(
            'output'   => implode("\n", $outputLines),
            'exitCode' => $exitCode,
        );
    }
    finally
    {
        unlink($tempFile);
    }
}

/**
 * Whether this PHP environment can shell out via exec(). Some hosts disable
 * it; when they do, function_exists() returns false for it, which is the
 * standard, documented way to detect a disabled function without relying on
 * parsing php.ini's disable_functions string by hand.
 *
 * @return bool
 */
function g2ml_directAccessGuard_execAvailable(): bool
{
    return function_exists('exec');
}

// ============================================================================
// (a) Static check — the old name-only guard must be gone everywhere under
//     web/, except the vendored _libraries folders (root and per-component),
//     which this codebase does not own and does not guard.
// ============================================================================

test('direct-access guard: no file under web/ outside _libraries uses the old basename()-only comparison (#198)', function (): void
{
    $webRoot   = dirname(__DIR__, 2) . '/web';
    $offenders = array();

    // The old guard's text can sit in a file's raw bytes two different ways.
    // Most files carry it as ordinary PHP code, where $_SERVER is a bare
    // dollar sign. install/index.php's two auth_creds.php templates instead
    // build the guard inside a heredoc that writes another PHP file to
    // disk, where the dollar has to be escaped with a backslash or the
    // heredoc would try to interpolate $_SERVER itself — so the raw bytes
    // there read basename(\$_SERVER[...], not basename($_SERVER[...]. Both
    // forms are checked, so the old guard cannot come back inside a heredoc
    // template unnoticed.
    $offendingPatterns = array(
        "basename(\$_SERVER['SCRIPT_FILENAME']",
        'basename(\\$_SERVER[\'SCRIPT_FILENAME\']',
    );

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo)
    {
        if ($fileInfo->getExtension() !== 'php')
        {
            continue;
        }

        $path = $fileInfo->getPathname();

        if (str_contains($path, '_libraries'))
        {
            continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false)
        {
            continue;
        }

        foreach ($offendingPatterns as $offendingPattern)
        {
            if (str_contains($contents, $offendingPattern))
            {
                $offenders[] = $path;
                break;
            }
        }
    }

    assert_true(
        count($offenders) === 0,
        'Old-style basename()-only guard still present in: ' . implode(', ', $offenders)
    );
});

// ============================================================================
// (b) + (c) — web/_functions/cron.php versus the admin cron endpoint, the
//     case that was actually live and broken (#198's own repro).
// ============================================================================

test('direct-access guard: web/_functions/cron.php runs past its guard when the admin cron endpoint is the running script', function (): void
{
    if (!g2ml_directAccessGuard_execAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $libraryPath  = dirname(__DIR__, 2) . '/web/_functions/cron.php';
    $endpointPath = dirname(__DIR__, 2) . '/web/Go2My.Link/_admin/public_html/cron.php';

    $scriptBody  = '$_SERVER[\'SCRIPT_FILENAME\'] = ' . var_export($endpointPath, true) . ';' . "\n";
    $scriptBody .= 'require ' . var_export($libraryPath, true) . ';' . "\n";
    $scriptBody .= 'echo \'AFTER_REQUIRE_OK\';' . "\n";

    $result = g2ml_directAccessGuard_runChildScript($scriptBody);

    assert_contains(
        'AFTER_REQUIRE_OK',
        $result['output'],
        'Requiring cron.php with SCRIPT_FILENAME set to the admin endpoint must reach the line after the require — output was: ' . $result['output']
    );
});

test('direct-access guard: web/_functions/cron.php still stops when it is itself the running script', function (): void
{
    if (!g2ml_directAccessGuard_execAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $libraryPath = dirname(__DIR__, 2) . '/web/_functions/cron.php';

    $scriptBody  = '$_SERVER[\'SCRIPT_FILENAME\'] = ' . var_export($libraryPath, true) . ';' . "\n";
    $scriptBody .= 'require ' . var_export($libraryPath, true) . ';' . "\n";
    $scriptBody .= 'echo \'AFTER_REQUIRE_OK\';' . "\n";

    $result = g2ml_directAccessGuard_runChildScript($scriptBody);

    assert_false(
        str_contains($result['output'], 'AFTER_REQUIRE_OK'),
        'cron.php opened directly (SCRIPT_FILENAME pointing at itself) must still be stopped by the guard — output was: ' . $result['output']
    );
});

// ============================================================================
// (d) — web/_functions/analytics.php versus the API handler that shares its
//     name (identified in #198 as one rename away from the same failure,
//     though never actually reachable that way — the handler is only ever
//     loaded through api/v1/index.php, never served directly by Apache).
// ============================================================================

test('direct-access guard: web/_functions/analytics.php runs past its guard when the API analytics handler is the running script', function (): void
{
    if (!g2ml_directAccessGuard_execAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $libraryPath = dirname(__DIR__, 2) . '/web/_functions/analytics.php';
    $handlerPath = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers/analytics.php';

    $scriptBody  = '$_SERVER[\'SCRIPT_FILENAME\'] = ' . var_export($handlerPath, true) . ';' . "\n";
    $scriptBody .= 'require ' . var_export($libraryPath, true) . ';' . "\n";
    $scriptBody .= 'echo \'AFTER_REQUIRE_OK\';' . "\n";

    $result = g2ml_directAccessGuard_runChildScript($scriptBody);

    assert_contains(
        'AFTER_REQUIRE_OK',
        $result['output'],
        'Requiring analytics.php with SCRIPT_FILENAME set to the API handler must reach the line after the require — output was: ' . $result['output']
    );
});
