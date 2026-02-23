<?php
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
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

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
// 📥 Log Error to Database
// ============================================================================

/**
 * Insert an error record into tblErrorLog.
 *
 * If the database INSERT fails, falls back to PHP's error_log().
 * This function MUST NOT throw exceptions.
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
    // Truncate backtrace to prevent excessively large inserts
    if ($backtrace !== null && strlen($backtrace) > 10000)
    {
        $backtrace = substr($backtrace, 0, 10000) . "\n... [truncated]";
    }

    // Build request context
    $requestURL    = ($_SERVER['REQUEST_URI'] ?? null);
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

    if (!empty($safeHeaders)) {
        $requestHeaders = json_encode($safeHeaders);
    } else {
        $requestHeaders = null;
    }
    if (function_exists('g2ml_getClientIP')) {
        $ipAddress = g2ml_getClientIP();
    } else {
        $ipAddress = ($_SERVER['REMOTE_ADDR'] ?? null);
    }
    $userUID        = $_SESSION['user_uid'] ?? null;

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
                $phpVersion = PHP_VERSION;

                $stmt->bind_param(
                    'sisssisssssis',
                    $severity,
                    $code,
                    $title,
                    $detail,
                    $file,
                    $line,
                    $backtrace,
                    $requestURL,
                    $requestMethod,
                    $requestHeaders,
                    $ipAddress,
                    $userUID,
                    $phpVersion
                );

                $stmt->execute();
                $stmt->close();

                return; // Successfully logged to database
            }
        }
    }
    catch (\Throwable $e)
    {
        // Silently fall through to error_log — NEVER re-throw from the error handler
    }

    // Fallback: log to PHP error_log
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
    $backtrace = $exception->getTraceAsString();

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

    // In production, show a generic error page
    if (!defined('G2ML_DEBUG') || G2ML_DEBUG !== true)
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
        // In debug mode, show the exception details
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

            g2ml_logError(
                $severity,
                $error['type'],
                '[FATAL ' . $errorType . '] ' . $error['message'],
                null,
                $error['file'],
                $error['line'],
                null // No backtrace available for fatal errors
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
