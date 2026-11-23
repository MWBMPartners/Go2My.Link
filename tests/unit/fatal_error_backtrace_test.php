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
 * 🧪 Checks — backtraces for fatal errors in the error log (#308, piece F2)
 * ============================================================================
 *
 * Newer PHP versions (8.5 and later) put a 'trace' entry into the data that
 * error_get_last() returns after a fatal error. The shutdown handler now turns
 * that entry into the backtrace it logs. PHP 8.4 has no such entry, and then
 * the handler logs no backtrace, exactly as before.
 *
 * WHAT IS CHECKED, AND HOW
 *
 *   - The formatter on its own, with error arrays made by hand: with and
 *     without a 'trace' entry, with function arguments (a password-like value
 *     must never appear in the output), and with a very long trace.
 *   - The real shutdown handler, in a separate PHP process, with a real fatal
 *     error and a stand-in database that records what would have been
 *     inserted. The test looks for the 'trace' entry in that process and
 *     expects the right result for what it found, and prints which of the two
 *     cases ran. It is skipped, with a printed reason, where exec() is off.
 *
 * WHAT THIS CANNOT DO
 *
 * It does not prove what a real MySQL server stores. The stand-in database
 * only shows what the handler tried to insert.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #308
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/error_handler.php';

// Every name here starts with g2mlFatalBacktraceTest_ so nothing can clash
// with another test file (all test files share one PHP process).

/**
 * One hand-made trace frame, as PHP 8.5 and later would give it, with an
 * argument list that holds a secret-looking value.
 *
 * @return array<string, mixed>
 */
function g2mlFatalBacktraceTest_frame(string $function, int $line, string $secret): array
{
    return array(
        'file'     => '/srv/site/page.php',
        'line'     => $line,
        'function' => $function,
        'args'     => array($secret, array('nested' => $secret)),
    );
}

test('fatal backtrace: no trace entry gives null (the old behaviour)', function ()
{
    $error = array(
        'type'    => E_ERROR,
        'message' => 'Out of memory',
        'file'    => '/srv/site/page.php',
        'line'    => 5,
    );

    assert_same(null, _g2ml_fatalErrorBacktrace($error));
});

test('fatal backtrace: an empty or unusable trace gives null', function ()
{
    $base = array('type' => E_ERROR, 'message' => 'x', 'file' => 'f.php', 'line' => 1);

    assert_same(null, _g2ml_fatalErrorBacktrace($base + array('trace' => array())));
    assert_same(null, _g2ml_fatalErrorBacktrace($base + array('trace' => 'not a list')));
    assert_same(null, _g2ml_fatalErrorBacktrace($base + array('trace' => null)));
    assert_same(null, _g2ml_fatalErrorBacktrace($base + array('trace' => array('junk', 5, null))));
});

test('fatal backtrace: a trace is formatted with file, line and function', function ()
{
    $error = array(
        'type'    => E_ERROR,
        'message' => 'x',
        'file'    => 'f.php',
        'line'    => 1,
        'trace'   => array(
            array('file' => '/srv/a.php', 'line' => 4, 'function' => 'inner', 'args' => array()),
            array('file' => '/srv/a.php', 'line' => 9, 'class' => 'Thing', 'type' => '->', 'function' => 'run'),
            array('function' => '{closure}'),
        ),
    );

    $text = _g2ml_fatalErrorBacktrace($error);

    assert_same(
        "#0 /srv/a.php(4): inner()\n"
        . "#1 /srv/a.php(9): Thing->run()\n"
        . "#2 [internal function](0): {closure}()\n",
        $text
    );
});

test('fatal backtrace: function arguments never reach the output', function ()
{
    $secret = 'hunter2-secret-password';

    $error = array(
        'type'    => E_ERROR,
        'message' => 'x',
        'file'    => 'f.php',
        'line'    => 1,
        'trace'   => array(
            g2mlFatalBacktraceTest_frame('login', 12, $secret),
            g2mlFatalBacktraceTest_frame('outer', 30, $secret),
        ),
    );

    $text = _g2ml_fatalErrorBacktrace($error);

    assert_true($text !== null, 'A trace with frames should give text.');
    assert_true(strpos((string) $text, $secret) === false, 'A function argument leaked into the backtrace.');
    assert_true(strpos((string) $text, 'nested') === false, 'An argument array key leaked into the backtrace.');
    assert_contains('login()', (string) $text);
    assert_contains('outer()', (string) $text);
});

test('fatal backtrace: odd values inside a frame do not leak or break it', function ()
{
    $secret = 'hunter2-secret-password';

    $error = array(
        'type'    => E_ERROR,
        'message' => 'x',
        'file'    => 'f.php',
        'line'    => 1,
        'trace'   => array(
            array('file' => 5, 'line' => 'seven', 'function' => array($secret), 'class' => 'Thing', 'args' => $secret),
        ),
    );

    $text = (string) _g2ml_fatalErrorBacktrace($error);

    assert_true(strpos($text, $secret) === false, 'A non-text value leaked into the backtrace.');
    assert_contains('#0 ', $text);
});

test('fatal backtrace: a very long trace is cut at the frame cap', function ()
{
    $frames = array();

    for ($index = 0; $index < 5000; $index++)
    {
        $frames[] = g2mlFatalBacktraceTest_frame('recurse', $index + 1, 'hunter2-secret-password');
    }

    $error = array('type' => E_ERROR, 'message' => 'x', 'file' => 'f.php', 'line' => 1, 'trace' => $frames);

    $text  = (string) _g2ml_fatalErrorBacktrace($error);
    $lines = explode("\n", rtrim($text, "\n"));

    // The cap is G2ML_FATAL_BACKTRACE_MAX_FRAMES frame lines plus one note.
    assert_same(G2ML_FATAL_BACKTRACE_MAX_FRAMES + 1, count($lines));
    assert_contains('#' . (G2ML_FATAL_BACKTRACE_MAX_FRAMES - 1) . ' ', $text);
    assert_true(strpos($text, '#' . G2ML_FATAL_BACKTRACE_MAX_FRAMES . ' ') === false, 'A frame past the cap was listed.');
    assert_contains('... [' . (5000 - G2ML_FATAL_BACKTRACE_MAX_FRAMES) . ' more frames not shown]', $text);
    assert_true(strpos($text, 'hunter2') === false);
});

test('fatal backtrace: a trace of exactly the cap has no "more frames" note', function ()
{
    $frames = array();

    for ($index = 0; $index < G2ML_FATAL_BACKTRACE_MAX_FRAMES; $index++)
    {
        $frames[] = array('file' => 'a.php', 'line' => $index, 'function' => 'f');
    }

    $text = (string) _g2ml_fatalErrorBacktrace(array('trace' => $frames));

    assert_true(strpos($text, 'more frames') === false);
});

// ============================================================================
// 🧪 The real shutdown handler, in a separate process
// ============================================================================

/**
 * The PHP code of the child process. It loads the real handler, replaces the
 * database with a stand-in that writes the inserted values to a file, then
 * causes a real fatal error (redeclaring a built-in function inside nested
 * functions that are passed a secret-looking argument).
 */
function g2mlFatalBacktraceTest_childScript(string $resultFile): string
{
    $root = dirname(__DIR__, 2);

    $script  = '$root = ' . var_export($root, true) . ';' . "\n";
    $script .= '$resultFile = ' . var_export($resultFile, true) . ';' . "\n";
    $script .= <<<'CHILD'
require $root . '/web/_functions/security.php';
require $root . '/web/_functions/error_handler.php';

class G2mlFakeStatement
{
    private array $values = array();

    public function bind_param(string $types, mixed &...$values): bool
    {
        $this->values = array();

        foreach ($values as $index => $value)
        {
            $this->values[$index] = $value;
        }

        return true;
    }

    public function execute(): bool
    {
        global $resultFile;

        file_put_contents($resultFile, json_encode($this->values));

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}

class G2mlFakeDatabase
{
    public function prepare(string $sql): G2mlFakeStatement
    {
        return new G2mlFakeStatement();
    }
}

function getDB()
{
    return new G2mlFakeDatabase();
}

// Shutdown handlers run in the order they were registered: the first one
// writes the ".seen" file, the second is the real handler.
register_shutdown_function(function ()
{
    global $resultFile;

    $error = error_get_last();

    file_put_contents(
        $resultFile . '.seen',
        json_encode(array(
            'hasTrace'          => ($error !== null && array_key_exists('trace', $error)),
            'backtraceSetting'  => ini_get('fatal_error_backtraces'),
        ))
    );
});

register_shutdown_function('g2ml_shutdownHandler');

function g2mlChildInner(string $password)
{
    eval('function strlen() {}');
}

function g2mlChildOuter(string $password)
{
    g2mlChildInner($password);
}

g2mlChildOuter('hunter2-secret-password');
CHILD;

    return $script;
}

test('fatal backtrace: the real shutdown handler logs a trace only when PHP gives one', function ()
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;

        return;
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'g2ml_fatal_bt_');
    $resultFile = tempnam(sys_get_temp_dir(), 'g2ml_fatal_res_');

    assert_true($scriptFile !== false && $resultFile !== false, 'Could not make temporary files.');

    @unlink((string) $resultFile);

    try
    {
        file_put_contents((string) $scriptFile, '<?php' . "\n" . g2mlFatalBacktraceTest_childScript((string) $resultFile));

        $outputLines = array();
        $exitCode    = -1;

        exec(
            escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg((string) $scriptFile) . ' 2>&1',
            $outputLines,
            $exitCode
        );

        $seenText = file_get_contents($resultFile . '.seen');

        assert_true($seenText !== false, 'The child did not reach its shutdown step: ' . implode("\n", $outputLines));

        $seen     = json_decode((string) $seenText, true);
        $hasTrace = ($seen['hasTrace'] === true);

        $loggedText = false;

        if (file_exists($resultFile))
        {
            $loggedText = file_get_contents($resultFile);
        }

        assert_true($loggedText !== false, 'The handler did not log the fatal error: ' . implode("\n", $outputLines));

        $logged    = json_decode((string) $loggedText, true);
        $title     = (string) $logged[2];
        $backtrace = $logged[6];

        assert_contains('[FATAL E_', $title);
        assert_contains('Cannot redeclare', $title);
        assert_true(strpos((string) $loggedText, 'hunter2-secret-password') === false, 'A function argument was logged.');

        // On 8.5 and later the trace should be there unless the setting is off.
        $settingOn = ($seen['backtraceSetting'] === '1');

        if (PHP_VERSION_ID >= 80500 && $settingOn)
        {
            assert_true($hasTrace, 'PHP 8.5+ with fatal_error_backtraces on gave no trace.');
        }
        elseif (PHP_VERSION_ID >= 80500)
        {
            echo 'NOTE: fatal_error_backtraces is off in the child, so the no-trace case is what runs here' . PHP_EOL;
        }

        if ($hasTrace)
        {
            echo 'BRANCH: this PHP (' . PHP_VERSION . ') gives a trace; the handler must log it' . PHP_EOL;

            assert_true(is_string($backtrace), 'Expected a backtrace string, got ' . gettype($backtrace));
            assert_contains('g2mlChildInner()', (string) $backtrace);
            assert_contains('g2mlChildOuter()', (string) $backtrace);
        }
        else
        {
            echo 'BRANCH: this PHP (' . PHP_VERSION . ') gives no trace; the handler must log none' . PHP_EOL;

            assert_same(null, $backtrace);
        }
    }
    finally
    {
        @unlink((string) $scriptFile);
        @unlink((string) $resultFile);
        @unlink($resultFile . '.seen');
    }
});
