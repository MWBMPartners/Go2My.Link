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
 * 🧪 Checks — no function argument values in the error log; long titles (#312)
 * ============================================================================
 *
 * WHAT IS CHECKED, AND HOW
 *
 *   - The cleaning function on its own, against real stack traces made by the
 *     PHP running the tests (closures, anonymous classes, named arguments,
 *     chained exceptions, awkward values).
 *   - The real error, exception and shutdown handlers, each in a separate PHP
 *     process with PHP told to INCLUDE argument values. A stand-in database
 *     records what would have been inserted, and PHP's error log goes to a
 *     file. A secret-looking value must appear in neither.
 *   - Long and badly encoded values being cut to fit their columns.
 *   - page_init.php and the installer switching argument values off.
 *
 * WHAT THIS CANNOT DO
 *
 * The stand-in database does not refuse long values. The database suite
 * (tests/integration/error_log_save_test.php) checks that against MySQL.
 * The child-process checks are skipped, with a printed reason, where exec()
 * is off.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — #312
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/security.php';
require_once dirname(__DIR__, 2) . '/web/_functions/error_handler.php';

// Every name here starts with g2mlRedactTest so nothing can clash with
// another test file (all test files share one PHP process).

/** A made-up value, short enough that PHP prints all of it in a trace. */
const G2ML_REDACT_TEST_SECRET = 'hunter2-pw';

class G2mlRedactTestThrower
{
    public function run(string $password): void
    {
        $closure = function (string $value): void
        {
            throw new RuntimeException('inner failed', 1, new LogicException('first cause'));
        };

        array_map($closure, array($password));
    }
}

/**
 * Run a callback with PHP told to put argument values into stack traces, then
 * put the setting back.
 */
function g2mlRedactTest_withArguments(callable $callback): mixed
{
    $before = (string) ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');

    try
    {
        return $callback();
    }
    finally
    {
        ini_set('zend.exception_ignore_args', $before);
    }
}

test('trace cleaning: a real chained exception loses every argument value (#312)', function (): void
{
    $text = g2mlRedactTest_withArguments(function (): string
    {
        try
        {
            (new G2mlRedactTestThrower())->run(G2ML_REDACT_TEST_SECRET);
        }
        catch (Throwable $caught)
        {
            return (string) $caught;
        }

        return '';
    });

    assert_contains(G2ML_REDACT_TEST_SECRET, $text, 'The probe itself must hold the value');

    $clean = g2ml_stripTraceArguments($text);

    assert_true(strpos($clean, 'hunter2') === false, 'A value survived: ' . $clean);
    assert_contains('inner failed', $clean);
    assert_contains('first cause', $clean);
    assert_contains('G2mlRedactTestThrower->run()', $clean);
    assert_contains('array_map()', $clean);
    assert_contains('{main}', $clean);
});

test('trace cleaning: anonymous classes, named arguments and awkward values (#312)', function (): void
{
    $values = array(
        "hunter2\n#9 x(1): y",
        "hunter2') {x}(",
        "a}(hunter2",
        "{hunter2",
        "\0hunter2\r",
    );

    foreach ($values as $value)
    {
        $text = g2mlRedactTest_withArguments(function () use ($value): string
        {
            $object = new class
            {
                public function collect(mixed ...$rest): void
                {
                    throw new RuntimeException('boom');
                }
            };

            try
            {
                $object->collect($value, named: $value);
            }
            catch (Throwable $caught)
            {
                return $caught->getTraceAsString();
            }

            return '';
        });

        assert_contains('hunter2', $text, 'The probe itself must hold the value');

        $clean = g2ml_stripTraceArguments($text);

        assert_true(strpos($clean, 'hunter2') === false, 'A value survived: ' . $clean);
        assert_contains('->collect()', $clean);
    }
});

test('trace cleaning: PHP closure names and internal frames keep their names only (#312)', function (): void
{
    $input = "#0 [internal function]: A->{closure:A::m():2}('hunter2-pw')\n"
        . "#1 /srv/a (copy).php(3) : eval()'d code(1): B::{closure:{closure:B::go():4}:5}('hunter2-pw')\n"
        . "Query: #2 /srv/b.php(9): N\\{closure}('hunter2-pw', Array)\r\n"
        . '#3 {main}';

    $expected = "#0 [internal function]: A->{closure:A::m():2}()\n"
        . "#1 /srv/a (copy).php(3) : eval()'d code(1): B::{closure:{closure:B::go():4}:5}()\n"
        . "Query: #2 /srv/b.php(9): N\\{closure}()\n"
        . '#3 {main}';

    assert_same($expected, g2ml_stripTraceArguments($input));
});

test('trace cleaning: ordinary text is left alone (#312)', function (): void
{
    $texts = array(
        '',
        'Issue #312 (error log): nothing to remove',
        "Line one (x)\nLine #2: two(3)",
        "#0 /srv/a.php(4): Thing->run()\n#1 [internal function](0): {closure}()\n#2 {main}\n",
    );

    foreach ($texts as $text)
    {
        assert_same($text, g2ml_stripTraceArguments($text));
    }
});

test('error log columns: values are cut by characters or bytes without splitting a character (#312)', function (): void
{
    $long = str_repeat('é', 600);

    assert_same(str_repeat('é', 500), g2ml_fitCharacters($long, 500));
    assert_same(str_repeat('é', 2), g2ml_fitBytes($long, 5));
    assert_same(null, g2ml_fitCharacters(null, 500));
    assert_same(null, g2ml_fitBytes(null, 500));
    assert_same(1, preg_match('//u', (string) g2ml_fitCharacters("bad \xC3 byte \xFF", 500)));
});

// ============================================================================
// 🧪 The real handlers, in a separate process
// ============================================================================

/**
 * Run a small script in a child PHP process that has argument values switched
 * ON. It loads the real error handler with a stand-in database.
 *
 * @param  string $body    The code that causes the error
 * @param  bool   $dbWorks False makes the stand-in database unavailable
 * @return array{rows: array<int, array<int, mixed>>, log: string, output: string}|null
 */
function g2mlRedactTest_child(string $body, bool $dbWorks = true): ?array
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;

        return null;
    }

    $scriptFile = (string) tempnam(sys_get_temp_dir(), 'g2ml_redact_');
    $rowFile    = $scriptFile . '.rows';
    $logFile    = $scriptFile . '.log';

    $script  = '<?php' . "\n";
    $script .= '$root = ' . var_export(dirname(__DIR__, 2), true) . ';' . "\n";
    $script .= '$rowFile = ' . var_export($rowFile, true) . ';' . "\n";
    $script .= '$dbWorks = ' . var_export($dbWorks, true) . ';' . "\n";
    $script .= <<<'CHILD'
require $root . '/web/_functions/security.php';
require $root . '/web/_functions/error_handler.php';

class G2mlRedactStatement
{
    private array $values = array();

    public function bind_param(string $types, mixed &...$values): bool
    {
        $this->values = $values;

        return true;
    }

    public function execute(): bool
    {
        global $rowFile;

        file_put_contents($rowFile, base64_encode(serialize($this->values)) . "\n", FILE_APPEND);

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}

class G2mlRedactDatabase
{
    public function prepare(string $sql): G2mlRedactStatement
    {
        return new G2mlRedactStatement();
    }
}

function getDB()
{
    global $dbWorks;

    if (!$dbWorks)
    {
        return null;
    }

    return new G2mlRedactDatabase();
}

function login(string $password)
{
    throw new RuntimeException('sign-in broke');
}

CHILD;
    $script .= "\n" . $body . "\n";

    file_put_contents($scriptFile, $script);

    $output   = array();
    $exitCode = -1;

    exec(
        escapeshellarg(PHP_BINARY)
        . ' -d zend.exception_ignore_args=0 -d display_errors=0 -d log_errors=1'
        . ' -d ' . escapeshellarg('error_log=' . $logFile)
        . ' ' . escapeshellarg($scriptFile) . ' 2>&1',
        $output,
        $exitCode
    );

    $rows = array();

    if (file_exists($rowFile))
    {
        foreach (file($rowFile, FILE_IGNORE_NEW_LINES) as $encoded)
        {
            $rows[] = unserialize((string) base64_decode($encoded));
        }
    }

    $log = '';

    if (file_exists($logFile))
    {
        $log = (string) file_get_contents($logFile);
    }

    @unlink($scriptFile);
    @unlink($rowFile);
    @unlink($logFile);

    return array('rows' => $rows, 'log' => $log, 'output' => implode("\n", $output));
}

/**
 * Check that a child run saved exactly one row, and that the secret is in
 * neither the row, the log file nor the output.
 *
 * @param  array{rows: array<int, array<int, mixed>>, log: string, output: string} $run
 * @return array<int, mixed>  The saved row
 */
function g2mlRedactTest_oneCleanRow(array $run): array
{
    assert_same(1, count($run['rows']), 'Expected one saved row. Output: ' . $run['output'] . ' Log: ' . $run['log']);

    $everything = serialize($run['rows']) . $run['log'] . $run['output'];

    assert_true(strpos($everything, 'hunter2') === false, 'The secret was saved, logged or printed: ' . $everything);

    return $run['rows'][0];
}

test('error log: an uncaught exception saves a backtrace with no argument values (#312)', function (): void
{
    $run = g2mlRedactTest_child("set_exception_handler('g2ml_exceptionHandler');\nlogin('hunter2-pw');");

    if ($run === null)
    {
        return;
    }

    $row = g2mlRedactTest_oneCleanRow($run);

    assert_contains('sign-in broke', (string) $row[2]);
    assert_contains('login()', (string) $row[6]);
    assert_contains('{main}', (string) $row[6]);
});

test('error log: the debug error page shows no argument values (#312)', function (): void
{
    $run = g2mlRedactTest_child(
        // The details page is only for a signed-in global administrator (#319).
        "require " . var_export(dirname(__DIR__, 2) . '/web/_functions/debug_access.php', true) . ";\n"
        . "define('G2ML_DEBUG', true);\n\$GLOBALS['_g2ml_debug_user_uid'] = 1;\n\$_SESSION = ['user_uid' => 1];\n"
        . "set_exception_handler('g2ml_exceptionHandler');\n"
        . "function wrap(string \$token) { try { login(\$token); } catch (Throwable \$e) { throw new Exception('wrapped ' . \$e, 0, \$e); } }\n"
        . "wrap('hunter2-pw');"
    );

    if ($run === null)
    {
        return;
    }

    g2mlRedactTest_oneCleanRow($run);

    assert_contains('Stack Trace:', $run['output']);
    assert_contains('login()', $run['output']);
});

test('error log: a fatal "Uncaught" message is cleaned by the shutdown handler (#312)', function (): void
{
    // No exception handler, so PHP builds its own message holding the trace.
    // PHP's own copy of that message is switched off with log_errors=0; the
    // page_init.php test below covers that copy.
    $run = g2mlRedactTest_child(
        "ini_set('log_errors', '0');\nregister_shutdown_function('g2ml_shutdownHandler');\n"
        . "login('hunter2-pw');"
    );

    if ($run === null)
    {
        return;
    }

    $row = g2mlRedactTest_oneCleanRow($run);

    assert_contains('[FATAL E_ERROR] Uncaught RuntimeException: sign-in broke', (string) $row[2]);
    assert_contains('login()', (string) $row[2]);
});

test('error log: a warning message holding a trace is cleaned (#312)', function (): void
{
    $run = g2mlRedactTest_child(
        "set_error_handler('g2ml_errorHandler');\n"
        . "try { login('hunter2-pw'); } catch (Throwable \$e) { trigger_error((string) \$e, E_USER_WARNING); }"
    );

    if ($run === null)
    {
        return;
    }

    $row = g2mlRedactTest_oneCleanRow($run);

    assert_contains('login()', (string) $row[2]);
});

test('error log: the log file fallback has no argument values (#312)', function (): void
{
    $run = g2mlRedactTest_child(
        "set_exception_handler('g2ml_exceptionHandler');\n"
        . "function wrap(string \$token) { try { login(\$token); } catch (Throwable \$e) { throw new Exception('outer', 0, new Exception((string) \$e)); } }\n"
        . "wrap('hunter2-pw');",
        false
    );

    if ($run === null)
    {
        return;
    }

    assert_same(0, count($run['rows']));
    assert_contains('outer', $run['log']);
    assert_contains('login()', $run['log']);
    assert_true(strpos($run['log'], 'hunter2') === false, 'The secret reached the log file: ' . $run['log']);
});

test('error log: long values are cut to fit and the full title goes to the log file (#312)', function (): void
{
    $run = g2mlRedactTest_child(
        "\$_SERVER['REQUEST_URI'] = '/' . str_repeat('u', 900);\n"
        . "\$_SERVER['REQUEST_METHOD'] = str_repeat('M', 40);\n"
        . "\$_SERVER['HTTP_USER_AGENT'] = \"agent \\xFF\";\n"
        . "g2ml_logError('error', 99999999999, str_repeat('é', 700) . 'END-OF-TITLE', str_repeat('é', 40000), str_repeat('f', 900), 3, str_repeat('é', 9000));"
    );

    if ($run === null)
    {
        return;
    }

    assert_same(1, count($run['rows']), $run['output']);

    $row = $run['rows'][0];

    assert_same(null, $row[1]);
    assert_same(str_repeat('é', 500), $row[2]);
    assert_true(strlen((string) $row[3]) <= 65535 && preg_match('//u', (string) $row[3]) === 1);
    assert_same(str_repeat('f', 500), $row[4]);
    assert_true(strlen((string) $row[6]) < 10100 && preg_match('//u', (string) $row[6]) === 1);
    assert_same(500, strlen((string) $row[7]));
    assert_same(10, strlen((string) $row[8]));
    assert_true(is_string($row[9]) && json_decode($row[9]) !== null, 'The headers must still be valid JSON');
    assert_contains('END-OF-TITLE', $run['log']);
});

test('error log: a title that fits is not also written to the log file (#312)', function (): void
{
    $run = g2mlRedactTest_child("g2ml_logError('error', 1, str_repeat('é', 500));");

    if ($run === null)
    {
        return;
    }

    assert_same(1, count($run['rows']), $run['output']);
    assert_same('', $run['log']);
});

test('page_init.php switches argument values off, including in PHP\'s own log (#312)', function (): void
{
    $root = var_export(dirname(__DIR__, 2) . '/web', true);

    $run = g2mlRedactTest_child(
        "// Only the settings block of page_init.php is run here, taken from the file.\n"
        . "\$source = file_get_contents(" . $root . " . '/_includes/page_init.php');\n"
        . "preg_match('/^if \\(function_exists\\(\\'ini_set\\'\\)\\)\\n\\{.*?^\\}\\n/ms', \$source, \$found);\n"
        . "eval(\$found[0] ?? '');\n"
        . "function later(string \$password) { throw new RuntimeException('native'); }\n"
        . "later('hunter2-pw');"
    );

    if ($run === null)
    {
        return;
    }

    assert_contains('later()', $run['log'], 'PHP should have logged the uncaught exception itself');
    assert_true(strpos($run['log'], 'hunter2') === false, 'PHP logged the secret: ' . $run['log']);
});

test('page_init.php and the installer switch argument values off before other code (#312)', function (): void
{
    $web = dirname(__DIR__, 2) . '/web';

    $pageInit = (string) file_get_contents($web . '/_includes/page_init.php');
    $setting  = strpos($pageInit, "ini_set('zend.exception_ignore_args', '1');");
    $firstLoad = strpos($pageInit, 'require_once G2ML_FUNCTIONS');

    assert_true($setting !== false && $firstLoad !== false && $setting < $firstLoad, 'page_init.php must switch it off before loading anything');

    $installer = (string) file_get_contents($web . '/Go2My.Link/public_html/install/index.php');
    $setting   = strpos($installer, "ini_set('zend.exception_ignore_args', '1');");
    $session   = strpos($installer, 'session_start();');

    assert_true($setting !== false && $session !== false && $setting < $session, 'The installer must switch it off early');
});
