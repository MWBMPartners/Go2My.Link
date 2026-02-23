<?php
/**
 * ============================================================================
 * 🗄️ Go2My.Link — Database Connection Manager
 * ============================================================================
 *
 * Provides a MySQLi singleton connection via getDB(). The connection is lazy-
 * initialised on first call and reused for all subsequent queries within the
 * same request.
 *
 * Connection is automatically closed at shutdown via register_shutdown_function().
 *
 * Dependencies: auth_creds.php constants (DB_HOST, DB_USER, DB_PASS, DB_NAME,
 *               DB_CHARSET, DB_PORT)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - MySQLi: https://www.php.net/manual/en/book.mysqli.php
 *     - Singleton: https://www.php.net/manual/en/language.variables.scope.php#language.variables.scope.static
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

/**
 * Get the MySQLi database connection (singleton).
 *
 * Creates a new connection on first call, then returns the same connection
 * for all subsequent calls within the same request. Uses a static variable
 * to persist the connection across function calls.
 *
 * Registers a shutdown function to close the connection when the script ends.
 *
 * @return mysqli|null  The MySQLi connection object, or null if connection failed
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.construct.php
 */
function getDB(): ?mysqli
{
    // Static variable persists across function calls within the same request
    // 📖 Reference: https://www.php.net/manual/en/language.variables.scope.php#language.variables.scope.static
    static $db = null;

    // Return existing connection if already established
    if ($db !== null && $db instanceof mysqli)
    {
        // Check if the connection is still alive
        // 📖 Reference: https://www.php.net/manual/en/mysqli.ping.php
        if (@$db->ping())
        {
            return $db;
        }
        else
        {
            // Connection was lost — reset and reconnect
            $db = null;
        }
    }

    // Verify required constants are defined
    $requiredConstants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];

    foreach ($requiredConstants as $constant)
    {
        if (!defined($constant))
        {
            error_log('[Go2My.Link] CRITICAL: Database constant ' . $constant . ' is not defined. Check auth_creds.php.');
            return null;
        }
    }

    // Suppress connection errors to handle them gracefully
    // 📖 Reference: https://www.php.net/manual/en/mysqli.construct.php
    mysqli_report(MYSQLI_REPORT_OFF);

    $db = @new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        defined('DB_PORT') ? DB_PORT : 3306
    );

    // Check for connection errors
    // 📖 Reference: https://www.php.net/manual/en/mysqli.connect-error.php
    if ($db->connect_errno)
    {
        error_log(
            '[Go2My.Link] CRITICAL: Database connection failed: ('
            . $db->connect_errno . ') ' . $db->connect_error
        );
        $db = null;
        return null;
    }

    // Set character set to utf8mb4 for full Unicode support
    // 📖 Reference: https://www.php.net/manual/en/mysqli.set-charset.php
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

    if (!$db->set_charset($charset))
    {
        error_log('[Go2My.Link] WARNING: Failed to set charset to ' . $charset . ': ' . $db->error);
    }

    // Set the session timezone to UTC for consistent date/time handling
    // 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html
    $db->query("SET time_zone = '+00:00'");

    // Set SQL mode for strict data handling
    // 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html
    $db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    // Re-enable error reporting for query operations
    // 📖 Reference: https://www.php.net/manual/en/function.mysqli-report.php
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Register shutdown function to close the connection
    // Only register once (use a flag)
    // 📖 Reference: https://www.php.net/manual/en/function.register-shutdown-function.php
    static $shutdownRegistered = false;

    if (!$shutdownRegistered)
    {
        register_shutdown_function('closeDB');
        $shutdownRegistered = true;
    }

    return $db;
}

/**
 * Close the database connection.
 *
 * Called automatically at script shutdown via register_shutdown_function().
 * Can also be called manually if early cleanup is needed.
 *
 * @return void
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.close.php
 */
function closeDB(): void
{
    // Access the static variable from getDB() by calling it
    // We need to get and close the connection without creating a new one
    $db = getDB();

    if ($db !== null && $db instanceof mysqli)
    {
        @$db->close();
    }
}

/**
 * Check if a database connection is currently active.
 *
 * @return bool  True if a connection exists and is responsive
 */
function isDBConnected(): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    return @$db->ping();
}
