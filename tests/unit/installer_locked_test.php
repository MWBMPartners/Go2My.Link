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
 * 🧪 Check — the installer says nothing once installation is finished (#285)
 * ============================================================================
 *
 * The installer used to answer after installation with an "Already installed"
 * page, which named an internal path (web/_auth_keys/.installed). It now
 * answers a plain "404 Not Found" and nothing else, as soon as its lock file
 * exists. It decides it is installed by the lock file, as before.
 *
 * This check fails if:
 *   - the lock check moves below the point where the session starts, or the
 *     old "Already installed" page comes back;
 *   - a copy of the installer, run on its own beside a lock file, prints
 *     anything other than "Not Found" or answers any status other than 404;
 *   - the same copy, run beside NO lock file, answers 404 (so the guard is not
 *     simply refusing every request).
 *
 * The two runs use a copy of install/index.php in a temporary folder laid out
 * like the real one (web/Go2My.Link/public_html/install/ with web/_auth_keys/
 * beside it), so nothing in the repository is touched. They need PHP's
 * shell_exec(), which some hosts switch off; the check says so and passes
 * without running them when it is off.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It does not run Apache, so it cannot show how the web server turns the
 * status code and body into a response.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The installer's source text.
 *
 * @return string
 */
function _g2mlInstallerLockedSource(): string
{
    $path = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/install/index.php';

    assert_true(is_file($path), 'install/index.php not found');

    return (string) file_get_contents($path);
}

/**
 * Remove a folder and everything in it (used only on the temporary copy).
 *
 * @param  string $folder
 * @return void
 */
function _g2mlInstallerLockedRemove(string $folder): void
{
    if (!is_dir($folder))
    {
        return;
    }

    $entries = scandir($folder);

    if ($entries === false)
    {
        return;
    }

    foreach ($entries as $entry)
    {
        if ($entry === '.' || $entry === '..')
        {
            continue;
        }

        $path = $folder . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($path) && !is_link($path))
        {
            _g2mlInstallerLockedRemove($path);
        }
        else
        {
            unlink($path);
        }
    }

    rmdir($folder);
}

/**
 * Run a copy of the installer in a child process, with or without a lock file.
 * Returns the output and the status code the installer set, or null when the
 * child process could not be run here.
 *
 * @param  bool $withLockFile
 * @return array{output: string, status: string}|null
 */
function _g2mlInstallerLockedRun(bool $withLockFile): ?array
{
    if (!function_exists('shell_exec'))
    {
        return null;
    }

    $disabled = (string) ini_get('disable_functions');

    if (strpos($disabled, 'shell_exec') !== false)
    {
        return null;
    }

    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_installer_test_' . bin2hex(random_bytes(6));

    $installDir = $base . '/web/Go2My.Link/public_html/install';
    $authDir    = $base . '/web/_auth_keys';

    mkdir($installDir, 0777, true);
    mkdir($authDir, 0777, true);

    file_put_contents($installDir . '/index.php', _g2mlInstallerLockedSource());

    if ($withLockFile)
    {
        file_put_contents($authDir . '/.installed', "Installed test\n");
    }

    // The wrapper prints the status code the installer set, after it exits.
    $wrapper = "<?php\n"
        . "register_shutdown_function(function () { echo \"\\n[status=\" . http_response_code() . \"]\"; });\n"
        . "require " . var_export($installDir . '/index.php', true) . ";\n";

    file_put_contents($base . '/wrapper.php', $wrapper);

    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . '/wrapper.php') . ' 2>&1');

    _g2mlInstallerLockedRemove($base);

    if (!is_string($output))
    {
        return null;
    }

    $status = '';
    $found  = array();

    if (preg_match('/\n\[status=(\d+)\]$/', $output, $found) === 1)
    {
        $status = $found[1];
        $output = (string) preg_replace('/\n\[status=\d+\]$/', '', $output);
    }

    return array('output' => $output, 'status' => $status);
}

test('installer: the lock check runs before the session starts, and no page names the lock file path (#285)', function (): void
{
    $source = _g2mlInstallerLockedSource();

    $lockCheck = strpos($source, 'if (file_exists($g2mlLockFile))');
    $session   = strpos($source, 'session_start();');

    assert_true($lockCheck !== false, 'The lock-file check was not found');
    assert_true($session !== false, 'session_start() was not found');
    assert_true($lockCheck < $session, 'The lock-file check must come before session_start(), so a finished installation sets no cookie');

    assert_contains('http_response_code(404);', $source);
    // A request that waited while another finished the installation (a double
    // click) still gets a short "already complete" note, but never the path.
    assert_contains('<h2>✅ Installation is already complete</h2>', $source);
    assert_false(strpos($source, 'a lock file exists at') !== false, 'A page naming the lock file path is back');
    assert_false(strpos($source, '<code>web/_auth_keys/.installed</code>') !== false, 'A page naming the lock file path is back');
});

test('installer: after installation a request gets a bare 404 "Not Found", with no path in it (#285)', function (): void
{
    $result = _g2mlInstallerLockedRun(true);

    if ($result === null)
    {
        // shell_exec() is switched off here, so the child process cannot run.
        assert_true(true);

        return;
    }

    assert_same('404', $result['status'], 'The installer did not answer 404 once the lock file existed. Output: ' . $result['output']);
    assert_same('Not Found', $result['output'], 'The installer printed something besides "Not Found"');
});

test('installer: with no lock file the installer still answers (so the guard is not refusing everything) (#285)', function (): void
{
    $result = _g2mlInstallerLockedRun(false);

    if ($result === null)
    {
        assert_true(true);

        return;
    }

    assert_not_same('404', $result['status'], 'The installer answered 404 although no lock file exists');
    assert_contains('Go2My.Link Installer', $result['output'], 'The installer page was not printed');
});
