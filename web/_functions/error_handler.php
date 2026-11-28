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
 * ⚠️ Go2My.Link — Error & Exception Handlers
 * ============================================================================
 *
 * Custom error and exception handlers that log to tblErrorLog in the database.
 * Falls back to PHP's error_log() if the database is unavailable.
 *
 * This handler MUST NEVER throw exceptions itself — any failure within the
 * handler silently falls back to error_log().
 *
 * A stack trace written or shown by this file must never contain the values
 * that were passed to a function (passwords, tokens and so on). Traces are
 * rebuilt or cleaned so that only file, line and function names remain (#312).
 *
 * Dependencies: db_connect.php (getDB()), db_query.php (dbInsert())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - set_error_handler:     https://www.php.net/manual/en/function.set-error-handler.php
 *     - set_exception_handler: https://www.php.net/manual/en/function.set-exception-handler.php
 *     - error constants:       https://www.php.net/manual/en/errorfunc.constants.php
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

// Removes secrets in web addresses from everything logged here (#323)
require_once __DIR__ . DIRECTORY_SEPARATOR . 'log_redaction.php';

// ============================================================================
// 📝 Error Severity Mapping
// ============================================================================
// Maps PHP error constants to our tblErrorLog severity ENUM values.
//
// 📖 Reference: https://www.php.net/manual/en/errorfunc.constants.php
// ============================================================================

/**
 * Map a PHP error code to a severity level string.
 *
 * @param  int    $errno  The PHP error code constant
 * @return string         One of: notice, warning, error, critical
 */
function _g2ml_mapErrorSeverity(int $errno): string
{
    return match ($errno)
    {
        E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED
            => 'notice',
        E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING
            => 'warning',
        E_ERROR, E_USER_ERROR, E_PARSE, E_RECOVERABLE_ERROR
            => 'error',
        E_CORE_ERROR, E_COMPILE_ERROR
            => 'critical',
        default
            => 'error',
    };
}

/**
 * Map a PHP error code to a human-readable type name.
 *
 * @param  int    $errno  The PHP error code constant
 * @return string         Human-readable error type
 */
function _g2ml_mapErrorType(int $errno): string
{
    return match ($errno)
    {
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        default             => 'UNKNOWN (' . $errno . ')',
    };
}

// ============================================================================
// 🙈 Remove Function Argument Values From Stack Traces (#312)
// ============================================================================
// PHP writes the values passed to each function into a stack trace, for example
// "#0 /srv/page.php(3): login('hunter2')". That text turns up in the message of
// an uncaught exception, and in any message built from an exception object. A
// password or token must never be stored or logged that way.
//
// page_init.php also tells PHP to leave the values out in the first place, but
// a host may not allow that, so this cleaning step does not rely on it.
// ============================================================================

/**
 * Remove the argument list from every stack trace line in a piece of text.
 *
 * A stack trace line looks like "#2 /path/file.php(12): Class->method(...)" or
 * "#3 [internal function]: name(...)". Everything from the opening bracket of
 * the argument list to the end of the line is replaced with "()". PHP writes a
 * new line inside a value as the two characters \n, so a value cannot carry on
 * onto the next line. All other text is returned unchanged.
 *
 * If a line cannot be checked, it is removed rather than risk keeping a value.
 *
 * @param  string $text  A message, detail or backtrace
 * @return string        The same text with no argument values in trace lines
 */
function g2ml_stripTraceArguments(string $text): string
{
    if (strpos($text, '#') === false)
    {
        return $text;
    }

    $lines = explode("\n", $text);

    foreach ($lines as $index => $line)
    {
        $found  = [];
        $result = preg_match(
            '/#\d+ (?:\[internal function\]|[^\r]*?\(\d+\)): /',
            $line,
            $found,
            PREG_OFFSET_CAPTURE
        );

        if ($result === false)
        {
            $lines[$index] = '[line removed: it could not be checked for argument values]';
            continue;
        }

        if ($result === 1)
        {
            $callStart = $found[0][1] + strlen($found[0][0]);

            $lines[$index] = substr($line, 0, $callStart)
                . _g2ml_traceCallWithoutArguments((string) substr($line, $callStart));
        }
    }

    return implode("\n", $lines);
}

/**
 * Turn the call part of a trace line, such as "Class->method('value')", into
 * "Class->method()".
 *
 * The argument list starts at the first "(" that is not inside curly brackets.
 * The curly brackets matter because PHP 8.4 and later name a closure like
 * "{closure:Class::method():12}", which has round brackets of its own.
 *
 * @param  string $call  The text after "file(line): "
 * @return string        The function name followed by "()", or a fixed note
 *                       when no argument list can be found
 */
function _g2ml_traceCallWithoutArguments(string $call): string
{
    $depth  = 0;
    $length = strlen($call);

    for ($position = 0; $position < $length; $position++)
    {
        $character = $call[$position];

        if ($character === '{')
        {
            $depth++;
        }
        elseif ($character === '}')
        {
            $depth--;
        }
        elseif ($character === '(' && $depth <= 0)
        {
            return substr($call, 0, $position) . '()';
        }
    }

    // Not a shape PHP produces. Keep none of it.
    return '[call removed]';
}

// ============================================================================
// ✂️ Fit Values Into Their Database Columns (#312)
// ============================================================================
// The database runs in strict mode, so a value that is too long, or that is not
// valid UTF-8 text, makes the whole INSERT fail and the error row is lost.
// ============================================================================

/**
 * Make a value valid UTF-8 and cut it to a number of characters (for VARCHAR
 * columns), without splitting a multi-byte character.
 *
 * @param  string|null $value          The value to fit
 * @param  int         $maxCharacters  The column's size in characters
 * @return string|null                 The value that is safe to insert
 */
function _g2ml_fitCharacters(?string $value, int $maxCharacters): ?string
{
    if ($value === null)
    {
        return null;
    }

    if (function_exists('mb_substr') && function_exists('mb_scrub'))
    {
        return mb_substr(mb_scrub($value, 'UTF-8'), 0, $maxCharacters, 'UTF-8');
    }

    // Without the mbstring extension, keep plain ASCII only.
    return substr((string) preg_replace('/[^\x00-\x7F]/', '?', $value), 0, $maxCharacters);
}

/**
 * Make a value valid UTF-8 and cut it to a number of bytes (for TEXT columns,
 * which are measured in bytes), without splitting a multi-byte character.
 *
 * @param  string|null $value     The value to fit
 * @param  int         $maxBytes  The largest number of bytes to keep
 * @return string|null            The value that is safe to insert
 */
function _g2ml_fitBytes(?string $value, int $maxBytes): ?string
{
    if ($value === null)
    {
        return null;
    }

    if (function_exists('mb_strcut') && function_exists('mb_scrub'))
    {
        return mb_strcut(mb_scrub($value, 'UTF-8'), 0, $maxBytes, 'UTF-8');
    }

    return substr((string) preg_replace('/[^\x00-\x7F]/', '?', $value), 0, $maxBytes);
}

// ============================================================================
// 📥 Log Error to Database
// ============================================================================

/**
 * Insert an error record into tblErrorLog.
 *
 * If the database INSERT fails, falls back to PHP's error_log().
 * This function MUST NOT throw exceptions.
 *
 * Function argument values are removed from the title, detail and backtrace
 * before anything is written. Values too long for their column are cut to fit.
 * When the title or detail had to be cut, the full text is also written to
 * PHP's error log so nothing is lost.
 *
 * @param  string      $severity   One of: notice, warning, error, critical, exception
 * @param  int|null    $code       PHP error code or custom error code
 * @param  string      $title      Error message or exception class name
 * @param  string|null $detail     Detailed error message
 * @param  string|null $file       File where the error occurred
 * @param  int|null    $line       Line number
 * @param  string|null $backtrace  Stack trace string
 * @return void
 *
 * 📖 Reference: tblErrorLog schema in web/_sql/schema/030_analytics.sql
 */
function g2ml_logError(
    string  $severity,
    ?int    $code,
    string  $title,
    ?string $detail    = null,
    ?string $file      = null,
    ?int    $line      = null,
    ?string $backtrace = null
): void
{
    // Remove function argument values and secrets in web addresses first, so
    // that neither the database nor the log file can receive them
    $title = g2ml_redactLogText(g2ml_stripTraceArguments($title));

    if ($detail !== null)
    {
        $detail = g2ml_redactLogText(g2ml_stripTraceArguments($detail));
    }

    if ($backtrace !== null)
    {
        $backtrace = g2ml_redactLogText(g2ml_stripTraceArguments($backtrace));

        // Truncate backtrace to prevent excessively large inserts
        if (strlen($backtrace) > 10000)
        {
            $backtrace = _g2ml_fitBytes($backtrace, 10000) . "\n... [truncated]";
        }
        else
        {
            $backtrace = _g2ml_fitBytes($backtrace, 10000);
        }
    }

    // Build request context
    $requestURL    = g2ml_redactLogURL($_SERVER['REQUEST_URI'] ?? null);
    $requestMethod = ($_SERVER['REQUEST_METHOD'] ?? null);

    // Sanitise request headers (only include relevant, non-sensitive headers)
    $safeHeaders = [];
    $allowedHeaderKeys = [
        'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'HTTP_ACCEPT',
        'HTTP_ACCEPT_LANGUAGE', 'CONTENT_TYPE', 'CONTENT_LENGTH',
    ];

    foreach ($allowedHeaderKeys as $key)
    {
        if (isset($_SERVER[$key]))
        {
            $safeHeaders[$key] = $_SERVER[$key];
        }
    }

    // The referring page's address can carry a token too (#323)
    if (isset($safeHeaders['HTTP_REFERER']))
    {
        $safeHeaders['HTTP_REFERER'] = g2ml_redactLogURL((string) $safeHeaders['HTTP_REFERER']);
    }

    if (!empty($safeHeaders)) {
        // A header can hold bytes that are not valid UTF-8. Replace them, or
        // json_encode() fails and the database refuses the row.
        $requestHeaders = json_encode($safeHeaders, JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        $requestHeaders = null;
    }
    $ipAddress      = g2ml_clientIpOrDefault($_SERVER['REMOTE_ADDR'] ?? null);
    $userUID        = $_SESSION['user_uid'] ?? null;

    // The full text for PHP's error log, built before anything is cut
    // 📖 Reference: https://www.php.net/manual/en/function.error-log.php
    $fallbackMessage = '[Go2My.Link] ' . strtoupper($severity)
        . ' [' . ($code ?? 0) . ']: ' . $title;

    if ($file !== null)
    {
        $fallbackMessage .= ' in ' . $file;

        if ($line !== null)
        {
            $fallbackMessage .= ':' . $line;
        }
    }

    if ($detail !== null)
    {
        $fallbackMessage .= ' | Detail: ' . $detail;
    }

    // Cut each value to fit its column (sizes: web/_sql/schema/030_analytics.sql)
    $columnTitle   = (string) _g2ml_fitCharacters($title, 500);
    $columnDetail  = _g2ml_fitBytes($detail, 65535);
    $columnFile    = _g2ml_fitCharacters($file, 500);
    $requestURL    = _g2ml_fitCharacters($requestURL, 500);
    $requestMethod = _g2ml_fitCharacters($requestMethod, 10);

    // True when the stored title or detail is shorter than the real one
    $textWasCut = false;

    if ($columnTitle !== _g2ml_fitCharacters($title, strlen($title)))
    {
        $textWasCut = true;
    }

    if ($detail !== null && $columnDetail !== _g2ml_fitBytes($detail, strlen($detail) * 4))
    {
        $textWasCut = true;
    }

    // An error code outside the INT column's range cannot be stored
    if ($code !== null && ($code > 2147483647 || $code < -2147483647 - 1))
    {
        $code = null;
    }

    // Attempt database insert
    try
    {
        $db = getDB();

        if ($db !== null)
        {
            $sql = "INSERT INTO tblErrorLog (
                        errorSeverity, errorCode, errorTitle, errorDetail,
                        errorFile, errorLine, errorBacktrace,
                        requestURL, requestMethod, requestHeaders,
                        ipAddress, userUID, phpVersion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);

            if ($stmt !== false)
            {
                // Some Linux packages report a version longer than the column
                $phpVersion = _g2ml_fitCharacters(PHP_VERSION, 20);

                $stmt->bind_param(
                    'sisssisssssis',
                    $severity,
                    $code,
                    $columnTitle,
                    $columnDetail,
                    $columnFile,
                    $line,
                    $backtrace,
                    $requestURL,
                    $requestMethod,
                    $requestHeaders,
                    $ipAddress,
                    $userUID,
                    $phpVersion
                );

                $saved = $stmt->execute();
                $stmt->close();

                if ($saved !== false)
                {
                    if ($textWasCut)
                    {
                        error_log($fallbackMessage);
                    }

                    return; // Successfully logged to database
                }
            }
        }
    }
    catch (\Throwable $e)
    {
        // Silently fall through to error_log — NEVER re-throw from the error handler
    }

    // Fallback: log to PHP error_log
    error_log($fallbackMessage);
}

// ============================================================================
// 🎯 PHP Error Handler
// ============================================================================

/**
 * Custom PHP error handler.
 *
 * Captures PHP errors (warnings, notices, etc.) and logs them to tblErrorLog.
 * Respects the error_reporting level — errors suppressed with @ are skipped.
 *
 * @param  int    $errno    Error code (E_WARNING, E_NOTICE, etc.)
 * @param  string $errstr   Error message
 * @param  string $errfile  File where the error occurred
 * @param  int    $errline  Line number where the error occurred
 * @return bool             True to prevent PHP's default error handler from running
 *
 * 📖 Reference: https://www.php.net/manual/en/function.set-error-handler.php
 */
function g2ml_errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Respect error suppression operator (@)
    // 📖 Reference: https://www.php.net/manual/en/language.operators.errorcontrol.php
    if (!(error_reporting() & $errno))
    {
        return false; // Let PHP handle suppressed errors
    }

    $severity  = _g2ml_mapErrorSeverity($errno);
    $errorType = _g2ml_mapErrorType($errno);

    // Generate a backtrace for non-trivial errors
    $backtrace = null;

    if ($severity !== 'notice')
    {
        // 📖 Reference: https://www.php.net/manual/en/function.debug-backtrace.php
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Remove the first frame (this function itself)
        array_shift($trace);

        $backtrace = _g2ml_formatBacktrace($trace);
    }

    g2ml_logError(
        $severity,
        $errno,
        '[' . $errorType . '] ' . $errstr,
        null,
        $errfile,
        $errline,
        $backtrace
    );

    // Return true to prevent PHP's default error handler
    // For fatal-level errors, this won't actually prevent termination
    return true;
}

// ============================================================================
// 🎯 PHP Exception Handler
// ============================================================================

/**
 * Custom uncaught exception handler.
 *
 * Captures uncaught exceptions and logs them to tblErrorLog with full backtrace.
 *
 * @param  \Throwable $exception  The uncaught exception or error
 * @return void
 *
 * 📖 Reference: https://www.php.net/manual/en/function.set-exception-handler.php
 */
function g2ml_exceptionHandler(\Throwable $exception): void
{
    $className = get_class($exception);
    $message   = $exception->getMessage();
    $code      = (int) $exception->getCode();
    $file      = $exception->getFile();
    $line      = $exception->getLine();
    // Built from file, line and function names only. getTraceAsString() is not
    // used because it includes the values passed to each function (#312).
    $trace     = $exception->getTrace();
    $backtrace = _g2ml_formatBacktrace($trace) . '#' . count($trace) . " {main}\n";

    // Check for previous exception chain
    $detail = null;
    $previous = $exception->getPrevious();

    if ($previous !== null)
    {
        $detail = 'Previous: [' . get_class($previous) . '] '
            . $previous->getMessage()
            . ' in ' . $previous->getFile() . ':' . $previous->getLine();
    }

    g2ml_logError(
        'exception',
        $code,
        '[' . $className . '] ' . $message,
        $detail,
        $file,
        $line,
        $backtrace
    );

    // The message can itself contain a stack trace, so clean it before the
    // debug page below can show it
    $message = g2ml_stripTraceArguments($message);

    // Everyone gets a generic error page, except a signed-in global
    // administrator (#319). If that cannot be established, for example because
    // the error happened early in the request, no details are shown.
    if (!function_exists('g2ml_debugOutputAllowed') || !g2ml_debugOutputAllowed())
    {
        if (!headers_sent())
        {
            http_response_code(500);
            // A minimal error display — will be replaced with a branded error page in Phase 4
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
                . '<title>Error</title></head><body>'
                . '<h1>Something went wrong</h1>'
                . '<p>An unexpected error occurred. Please try again later.</p>'
                . '</body></html>';
        }
    }
    else
    {
        // Global administrator in debug mode: show the exception details
        if (!headers_sent())
        {
            http_response_code(500);
            echo '<pre style="background:#1a1a2e;color:#e94560;padding:20px;font-size:14px;">';
            echo '<strong>Uncaught Exception:</strong> ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . "\n";
            echo '<strong>Message:</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n";
            echo '<strong>Code:</strong> ' . $code . "\n";
            echo '<strong>File:</strong> ' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . ':' . $line . "\n\n";
            echo '<strong>Stack Trace:</strong>' . "\n";
            echo htmlspecialchars($backtrace, ENT_QUOTES, 'UTF-8');
            echo '</pre>';
        }
    }
}

// ============================================================================
// 🎯 Shutdown Handler (Fatal Errors)
// ============================================================================

/**
 * Shutdown handler for catching fatal errors that bypass set_error_handler().
 *
 * Registered via register_shutdown_function() in page_init.php.
 *
 * @return void
 *
 * 📖 Reference: https://www.php.net/manual/en/function.register-shutdown-function.php
 */
function g2ml_shutdownHandler(): void
{
    // 📖 Reference: https://www.php.net/manual/en/function.error-get-last.php
    $error = error_get_last();

    if ($error !== null)
    {
        // Only handle fatal errors (these bypass set_error_handler)
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if (in_array($error['type'], $fatalTypes, true))
        {
            $severity  = _g2ml_mapErrorSeverity($error['type']);
            $errorType = _g2ml_mapErrorType($error['type']);

            // PHP 8.5 and later can add a 'trace' entry to some fatal errors.
            // When it is missing, no backtrace is logged, as before.
            g2ml_logError(
                $severity,
                $error['type'],
                '[FATAL ' . $errorType . '] ' . $error['message'],
                null,
                $error['file'],
                $error['line'],
                _g2ml_fatalErrorBacktrace($error)
            );
        }
    }
}

// ============================================================================
// 🔧 Backtrace Formatting Helper
// ============================================================================

/**
 * Format a debug_backtrace() array into a readable string.
 *
 * @param  array  $trace  The debug backtrace array
 * @return string         Formatted backtrace string
 */
function _g2ml_formatBacktrace(array $trace): string
{
    $output = '';
    $frameNum = 0;

    foreach ($trace as $frame)
    {
        $file     = $frame['file'] ?? '[internal function]';
        $line     = $frame['line'] ?? 0;
        $class    = $frame['class'] ?? '';
        $type     = $frame['type'] ?? '';
        $function = $frame['function'] ?? '';

        $output .= '#' . $frameNum . ' ' . $file . '(' . $line . '): '
            . $class . $type . $function . "()\n";

        $frameNum++;
    }

    return $output;
}

/**
 * The most stack frames a fatal-error backtrace will list. A deep trace is
 * cut here so a runaway recursion cannot fill the log. Only the first frames
 * are kept because they show where the error happened.
 */
const G2ML_FATAL_BACKTRACE_MAX_FRAMES = 50;

/**
 * Turn the 'trace' entry of error_get_last() into a readable backtrace.
 *
 * Some PHP versions add a 'trace' entry to the fatal error data; others do
 * not, and out-of-memory errors never have one. The choice is made by whether
 * the entry is there, never by the PHP version number. When it is missing or
 * is not a usable list, this returns null, which is what the log received
 * before this function existed.
 *
 * Function arguments are NEVER copied. Each frame is rebuilt from only its
 * file, line, class, call type and function name, so a password passed to a
 * function cannot reach the log.
 *
 * @param  array<mixed> $error  The array from error_get_last()
 * @return string|null          The formatted backtrace, or null when there is none
 */
function _g2ml_fatalErrorBacktrace(array $error): ?string
{
    if (!array_key_exists('trace', $error) || !is_array($error['trace']) || count($error['trace']) === 0)
    {
        return null;
    }

    $safeFrames = [];
    $totalFrames = 0;

    foreach ($error['trace'] as $frame)
    {
        if (!is_array($frame))
        {
            continue;
        }

        $totalFrames++;

        if (count($safeFrames) >= G2ML_FATAL_BACKTRACE_MAX_FRAMES)
        {
            continue;
        }

        $safeFrame = [];

        foreach (['file', 'class', 'type', 'function'] as $textKey)
        {
            if (isset($frame[$textKey]) && is_string($frame[$textKey]))
            {
                $safeFrame[$textKey] = $frame[$textKey];
            }
        }

        if (isset($frame['line']) && is_int($frame['line']))
        {
            $safeFrame['line'] = $frame['line'];
        }

        $safeFrames[] = $safeFrame;
    }

    if (count($safeFrames) === 0)
    {
        return null;
    }

    $output = _g2ml_formatBacktrace($safeFrames);

    if ($totalFrames > count($safeFrames))
    {
        $output .= '... [' . ($totalFrames - count($safeFrames)) . " more frames not shown]\n";
    }

    return $output;
}

// ============================================================================
// 📢 Convenience Error Triggers
// ============================================================================

/**
 * Trigger a user-level warning and log it.
 *
 * Use for non-fatal issues that should be logged but don't stop execution.
 *
 * @param  string $message  The warning message
 * @return void
 */
function g2ml_warning(string $message): void
{
    // 📖 Reference: https://www.php.net/manual/en/function.trigger-error.php
    trigger_error($message, E_USER_WARNING);
}

/**
 * Trigger a user-level notice and log it.
 *
 * Use for informational messages about potentially unexpected conditions.
 *
 * @param  string $message  The notice message
 * @return void
 */
function g2ml_notice(string $message): void
{
    trigger_error($message, E_USER_NOTICE);
}
