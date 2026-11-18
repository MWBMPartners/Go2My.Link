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
 * 🧪 Go2My.Link — Lightweight Pure-PHP Test Harness (bootstrap)
 * ============================================================================
 *
 * A dependency-free test micro-framework for Dreamhost shared hosting where
 * Composer / PHPUnit are unavailable. Provides:
 *
 *   - A test registrar:   test('name', function () { ... });
 *   - Assertion helpers:  assert_true, assert_false, assert_same,
 *                         assert_not_same, assert_throws, assert_contains.
 *   - A results collector accessed via g2ml_test_run_all().
 *
 * Each registered test is a closure. Assertions throw a G2mlAssertionError on
 * failure; the runner catches it, marks the test FAILED, and continues with
 * the next test so one failure never hides the others.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @author     MWBM Partners Ltd (MWservices)
 * @since      Phase v1.0.0 (autopilot STABILIZE)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🕐 Force UTC Timezone
// ============================================================================
// Production sets this in web/_includes/page_init.php (and the installer
// sets it on its own too). Nothing in the test suites loads page_init.php,
// so the test run sets it here instead. Without this line the tests would
// run under whatever zone the CLI's php.ini happens to name, which would
// let a test pass here and fail against the real request path, or the
// other way round (#214).
// 📖 Reference: https://www.php.net/manual/en/function.date-default-timezone-set.php
// ============================================================================

date_default_timezone_set('UTC');

// ============================================================================
// ⚠️ Deprecation notices fail the run
// ============================================================================
// A newer PHP version marks more things as deprecated. Those notices never
// change the exit code on their own, so they are easy to miss. Here every
// deprecation notice is recorded, and g2ml_test_run_all() prints each one and
// counts it as a failure. A notice hidden with the @ operator is left alone.
// A recorded notice is NOT also printed by PHP itself (the handler returns
// true), so each one shows up exactly once, in the report at the end.
// The list is never cleared. A test may only save it and put it back.
// ============================================================================

error_reporting(E_ALL);

$GLOBALS['g2ml_test_deprecations'] = array();

/**
 * Record one deprecation notice. Installed as the PHP error handler for
 * deprecation notices only.
 *
 * @param  int    $errno    The PHP error level.
 * @param  string $errstr  The notice text.
 * @param  string $errfile The file the notice came from.
 * @param  int    $errline The line the notice came from.
 * @return bool  True when the notice was recorded, false when it was left to PHP.
 */
function g2ml_test_record_deprecation(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
{
    if ((error_reporting() & $errno) === 0)
    {
        return false;
    }

    $GLOBALS['g2ml_test_deprecations'][] = array(
        'message' => $errstr,
        'file'    => $errfile,
        'line'    => $errline,
    );

    return true;
}

set_error_handler('g2ml_test_record_deprecation', E_DEPRECATED | E_USER_DEPRECATED);

/**
 * Return the deprecation notices recorded so far.
 *
 * @return array<int, array{message: string, file: string, line: int}>
 */
function g2ml_test_get_deprecations(): array
{
    return $GLOBALS['g2ml_test_deprecations'];
}

/**
 * Put back a list of deprecation notices saved earlier with
 * g2ml_test_get_deprecations(). A test that checks the recording itself uses
 * this to leave the list as it found it.
 *
 * @param  array<int, array{message: string, file: string, line: int}> $saved
 * @return void
 */
function g2ml_test_restore_deprecations(array $saved): void
{
    $GLOBALS['g2ml_test_deprecations'] = $saved;
}

// ============================================================================
// 🧾 Assertion failure exception
// ============================================================================

/**
 * Thrown by an assertion helper when a check fails.
 *
 * Carrying its own type lets the runner distinguish a deliberate assertion
 * failure from an unexpected PHP exception bubbling out of a test body.
 */
class G2mlAssertionError extends Exception
{
}

// ============================================================================
// 🗂️ Global test registry
// ============================================================================

/**
 * The ordered list of registered tests. Each entry is an associative array
 * with keys 'name' (string) and 'callback' (callable).
 *
 * @var array<int, array{name: string, callback: callable}> $GLOBALS['g2ml_tests']
 */
if (!isset($GLOBALS['g2ml_tests']))
{
    $GLOBALS['g2ml_tests'] = array();
}

/**
 * Register a single test case.
 *
 * @param  string   $name      Human-readable test description.
 * @param  callable $callback  The test body. Should run assertions; may throw.
 * @return void
 */
function test(string $name, callable $callback): void
{
    $GLOBALS['g2ml_tests'][] = array(
        'name'     => $name,
        'callback' => $callback,
    );
}

// ============================================================================
// ✅ Assertion helpers
// ============================================================================

/**
 * Build a descriptive failure message, optionally prefixed by the caller's
 * own message.
 *
 * @param  string $detail   The auto-generated detail.
 * @param  string $message  Optional caller-supplied context.
 * @return string
 */
function g2ml_test_format_message(string $detail, string $message): string
{
    if ($message === '')
    {
        return $detail;
    }

    return $message . ' — ' . $detail;
}

/**
 * Render any value compactly for inclusion in a failure message.
 *
 * @param  mixed $value
 * @return string
 */
function g2ml_test_export(mixed $value): string
{
    if (is_string($value))
    {
        return "'" . $value . "'";
    }

    if (is_bool($value))
    {
        if ($value === true)
        {
            return 'true';
        }

        return 'false';
    }

    if ($value === null)
    {
        return 'null';
    }

    if (is_scalar($value))
    {
        return (string) $value;
    }

    return gettype($value);
}

/**
 * Assert that a condition is strictly boolean true.
 *
 * @param  mixed  $condition
 * @param  string $message
 * @return void
 */
function assert_true(mixed $condition, string $message = ''): void
{
    if ($condition !== true)
    {
        $detail = 'Expected true, got ' . g2ml_test_export($condition);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * Assert that a condition is strictly boolean false.
 *
 * @param  mixed  $condition
 * @param  string $message
 * @return void
 */
function assert_false(mixed $condition, string $message = ''): void
{
    if ($condition !== false)
    {
        $detail = 'Expected false, got ' . g2ml_test_export($condition);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * Assert that two values are identical (=== comparison).
 *
 * @param  mixed  $expected
 * @param  mixed  $actual
 * @param  string $message
 * @return void
 */
function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual)
    {
        $detail = 'Expected ' . g2ml_test_export($expected)
            . ', got ' . g2ml_test_export($actual);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * Assert that two values are NOT identical (!== comparison).
 *
 * @param  mixed  $expected
 * @param  mixed  $actual
 * @param  string $message
 * @return void
 */
function assert_not_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected === $actual)
    {
        $detail = 'Expected value to differ from ' . g2ml_test_export($expected)
            . ', but they were identical';
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * Assert that invoking the callback throws (any Throwable, or a specific
 * class when $expectedClass is supplied).
 *
 * @param  callable    $callback       The code expected to throw.
 * @param  string|null $expectedClass  Optional Throwable class name to require.
 * @param  string      $message
 * @return void
 */
function assert_throws(callable $callback, ?string $expectedClass = null, string $message = ''): void
{
    $threw  = false;
    $actual = null;

    try
    {
        $callback();
    }
    catch (Throwable $caught)
    {
        $threw  = true;
        $actual = $caught;
    }

    if ($threw === false)
    {
        $detail = 'Expected callback to throw, but it returned normally';
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }

    if ($expectedClass !== null && !($actual instanceof $expectedClass))
    {
        $detail = 'Expected throwable of type ' . $expectedClass
            . ', got ' . get_class($actual);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

/**
 * Assert that a haystack string contains a needle substring.
 *
 * @param  string $needle
 * @param  string $haystack
 * @param  string $message
 * @return void
 */
function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle))
    {
        $detail = 'Expected to find ' . g2ml_test_export($needle)
            . ' within ' . g2ml_test_export($haystack);
        throw new G2mlAssertionError(g2ml_test_format_message($detail, $message));
    }
}

// ============================================================================
// 🏃 Runner
// ============================================================================

/**
 * Execute every registered test in order, printing a per-test PASS/FAIL line
 * and a final summary. Every recorded deprecation notice is printed and counts
 * as one extra failure. Returns the number of failures so a caller can set the
 * process exit code.
 *
 * @return int  Count of failed tests (0 means everything passed).
 */
function g2ml_test_run_all(): int
{
    $tests  = $GLOBALS['g2ml_tests'];
    $passed = 0;
    $failed = 0;

    foreach ($tests as $entry)
    {
        $name     = $entry['name'];
        $callback = $entry['callback'];

        try
        {
            $callback();
            $passed = $passed + 1;
            echo 'PASS  ' . $name . PHP_EOL;
        }
        catch (G2mlAssertionError $assertionError)
        {
            $failed = $failed + 1;
            echo 'FAIL  ' . $name . PHP_EOL;
            echo '        ' . $assertionError->getMessage() . PHP_EOL;
        }
        catch (Throwable $unexpected)
        {
            $failed = $failed + 1;
            echo 'FAIL  ' . $name . ' (unexpected ' . get_class($unexpected) . ')' . PHP_EOL;
            echo '        ' . $unexpected->getMessage() . PHP_EOL;
        }
    }

    $deprecations = g2ml_test_get_deprecations();

    foreach ($deprecations as $notice)
    {
        $failed = $failed + 1;
        echo 'DEPRECATED  ' . $notice['message'] . ' in ' . $notice['file'] . ':' . $notice['line'] . PHP_EOL;
    }

    echo PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;
    echo $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;

    return $failed;
}
