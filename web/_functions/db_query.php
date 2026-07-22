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
 * 📊 Go2My.Link — Database Query Wrappers
 * ============================================================================
 *
 * Prepared statement wrappers for common database operations. All functions
 * use MySQLi prepared statements exclusively for SQL injection prevention.
 *
 * Functions: dbSelect(), dbInsert(), dbUpdate(), dbDelete(), dbCallProcedure()
 *
 * Dependencies: db_connect.php (getDB())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - Prepared statements: https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php
 *     - bind_param types:    https://www.php.net/manual/en/mysqli-stmt.bind-param.php
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
// 🔍 Debug Query Log
// ============================================================================
// When debug mode is enabled, all queries are logged here for the debug panel.
// ============================================================================

// Global query log for debug panel (populated when G2ML_DEBUG is true)
$GLOBALS['_g2ml_query_log'] = [];

// MySQLi error number from the most recent write (dbInsert/dbUpdate); 0 when none.
$GLOBALS['_g2ml_last_errno'] = 0;

/**
 * Return the MySQLi error number from the most recent write operation.
 *
 * dbInsert()/dbUpdate() return false on failure, which hides the underlying
 * cause. Callers that need to distinguish a duplicate-key collision
 * (errno 1062) from other failures — for example the short-code creation
 * retry loop — can read it here immediately after a failed write.
 *
 * @return int  The last MySQLi errno (0 if the last write had no error)
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.errno.php
 * 📖 Reference: https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html (1062 = ER_DUP_ENTRY)
 */
function dbLastErrno(): int
{
    return (int) $GLOBALS['_g2ml_last_errno'];
}

/**
 * Log a query for the debug panel.
 *
 * @param  string $sql       The SQL query or procedure name
 * @param  array  $params    The bound parameters
 * @param  float  $duration  Execution time in milliseconds
 * @param  bool   $success   Whether the query succeeded
 * @return void
 */
function _g2ml_logQuery(string $sql, array $params, float $duration, bool $success): void
{
    if (defined('G2ML_DEBUG') && G2ML_DEBUG === true)
    {
        $GLOBALS['_g2ml_query_log'][] = [
            'sql'      => $sql,
            'params'   => $params,
            'duration' => round($duration, 3),
            'success'  => $success,
            'time'     => date('H:i:s.') . substr((string) microtime(true), -3),
        ];
    }
}

// ============================================================================
// 🔍 SELECT Queries
// ============================================================================

/**
 * Execute a SELECT query with prepared statements.
 *
 * Returns an array of associative arrays (one per row). Returns an empty array
 * if no rows are found. Returns false on error.
 *
 * @param  string     $sql    The SELECT query with ? placeholders
 * @param  string     $types  Parameter type string (s=string, i=int, d=double, b=blob)
 * @param  array      $params Values to bind to the placeholders
 * @return array|false        Array of rows (associative), or false on error
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.get-result.php
 *
 * Usage example:
 *   $users = dbSelect(
 *       "SELECT username, email FROM tblUsers WHERE orgHandle = ? AND isActive = ?",
 *       "si",
 *       ["myorg", 1]
 *   );
 */
function dbSelect(string $sql, string $types = '', array $params = []): array|false
{
    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] ERROR: dbSelect failed — no database connection.');
        return false;
    }

    $startTime = microtime(true);

    try
    {
        // Prepare the statement
        // 📖 Reference: https://www.php.net/manual/en/mysqli.prepare.php
        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            error_log('[Go2My.Link] ERROR: dbSelect prepare failed: ' . $db->error . ' | SQL: ' . $sql);
            _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
            return false;
        }

        // Bind parameters if provided
        if ($types !== '' && count($params) > 0)
        {
            // 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.bind-param.php
            $stmt->bind_param($types, ...$params);
        }

        // Execute the statement
        // 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.execute.php
        $stmt->execute();

        // Get the result set
        // 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.get-result.php
        $result = $stmt->get_result();

        if ($result === false)
        {
            error_log('[Go2My.Link] ERROR: dbSelect get_result failed: ' . $stmt->error . ' | SQL: ' . $sql);
            _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
            $stmt->close();
            return false;
        }

        // Fetch all rows as associative arrays
        // 📖 Reference: https://www.php.net/manual/en/mysqli-result.fetch-all.php
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        $result->free();
        $stmt->close();

        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, true);

        return $rows;
    }
    catch (mysqli_sql_exception $e)
    {
        error_log('[Go2My.Link] ERROR: dbSelect exception: ' . $e->getMessage() . ' | SQL: ' . $sql);
        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
        return false;
    }
}

/**
 * Execute a SELECT query and return the first row only.
 *
 * Convenience wrapper around dbSelect() for queries that expect a single row.
 *
 * @param  string     $sql    The SELECT query with ? placeholders
 * @param  string     $types  Parameter type string
 * @param  array      $params Values to bind
 * @return array|null|false   Single row (associative), null if not found, false on error
 *
 * Usage example:
 *   $user = dbSelectOne(
 *       "SELECT * FROM tblUsers WHERE userUID = ?",
 *       "i",
 *       [42]
 *   );
 */
function dbSelectOne(string $sql, string $types = '', array $params = []): array|null|false
{
    $result = dbSelect($sql, $types, $params);

    if ($result === false)
    {
        return false;
    }

    if (count($result) === 0)
    {
        return null;
    }

    return $result[0];
}

// ============================================================================
// ✏️ INSERT Queries
// ============================================================================

/**
 * Execute an INSERT query with prepared statements.
 *
 * Returns the auto-generated insert ID (for AUTO_INCREMENT columns), or true
 * if the insert succeeded without an auto-increment column.
 *
 * @param  string    $sql    The INSERT query with ? placeholders
 * @param  string    $types  Parameter type string
 * @param  array     $params Values to bind
 * @return int|bool          Insert ID (> 0), true (no auto-increment), or false on error
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.insert-id.php
 *
 * Usage example:
 *   $newID = dbInsert(
 *       "INSERT INTO tblCategories (categoryName, orgHandle) VALUES (?, ?)",
 *       "ss",
 *       ["Marketing", "myorg"]
 *   );
 */
function dbInsert(string $sql, string $types = '', array $params = []): int|bool
{
    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] ERROR: dbInsert failed — no database connection.');
        return false;
    }

    $startTime = microtime(true);

    // Reset the last-errno tracker for this write.
    $GLOBALS['_g2ml_last_errno'] = 0;

    try
    {
        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            $GLOBALS['_g2ml_last_errno'] = (int) $db->errno;
            error_log('[Go2My.Link] ERROR: dbInsert prepare failed: ' . $db->error . ' | SQL: ' . $sql);
            _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
            return false;
        }

        if ($types !== '' && count($params) > 0)
        {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $insertID = $stmt->insert_id;
        $stmt->close();

        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, true);

        // Return the insert ID if available, otherwise return true for success
        if (($insertID > 0)) {
            return $insertID;
        } else {
            return true;
        }
    }
    catch (mysqli_sql_exception $e)
    {
        $GLOBALS['_g2ml_last_errno'] = (int) $e->getCode();
        error_log('[Go2My.Link] ERROR: dbInsert exception: ' . $e->getMessage() . ' | SQL: ' . $sql);
        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
        return false;
    }
}

// ============================================================================
// 🔄 UPDATE Queries
// ============================================================================

/**
 * Execute an UPDATE query with prepared statements.
 *
 * Returns the number of affected rows. Returns 0 if no rows matched the
 * WHERE clause. Returns false on error.
 *
 * @param  string   $sql    The UPDATE query with ? placeholders
 * @param  string   $types  Parameter type string
 * @param  array    $params Values to bind
 * @return int|false        Number of affected rows, or false on error
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.affected-rows.php
 *
 * Usage example:
 *   $affected = dbUpdate(
 *       "UPDATE tblUsers SET isActive = ? WHERE orgHandle = ?",
 *       "is",
 *       [0, "oldorg"]
 *   );
 */
function dbUpdate(string $sql, string $types = '', array $params = []): int|false
{
    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] ERROR: dbUpdate failed — no database connection.');
        return false;
    }

    $startTime = microtime(true);

    try
    {
        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            error_log('[Go2My.Link] ERROR: dbUpdate prepare failed: ' . $db->error . ' | SQL: ' . $sql);
            _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
            return false;
        }

        if ($types !== '' && count($params) > 0)
        {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, true);

        return $affectedRows;
    }
    catch (mysqli_sql_exception $e)
    {
        error_log('[Go2My.Link] ERROR: dbUpdate exception: ' . $e->getMessage() . ' | SQL: ' . $sql);
        _g2ml_logQuery($sql, $params, (microtime(true) - $startTime) * 1000, false);
        return false;
    }
}

// ============================================================================
// ❌ DELETE Queries
// ============================================================================

/**
 * Execute a DELETE query with prepared statements.
 *
 * Returns the number of deleted rows. Returns 0 if no rows matched.
 * Returns false on error.
 *
 * @param  string   $sql    The DELETE query with ? placeholders
 * @param  string   $types  Parameter type string
 * @param  array    $params Values to bind
 * @return int|false        Number of deleted rows, or false on error
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.affected-rows.php
 *
 * Usage example:
 *   $deleted = dbDelete(
 *       "DELETE FROM tblUserSessions WHERE userUID = ? AND createdAt < ?",
 *       "is",
 *       [42, "2025-01-01 00:00:00"]
 *   );
 */
function dbDelete(string $sql, string $types = '', array $params = []): int|false
{
    // DELETE uses the same mechanics as UPDATE
    return dbUpdate($sql, $types, $params);
}

// ============================================================================
// 📞 Stored Procedure Calls
// ============================================================================

/**
 * Call a stored procedure with prepared statements.
 *
 * Handles both IN and OUT parameters. OUT parameters are retrieved via
 * a follow-up SELECT query after the procedure call.
 *
 * @param  string $procedureName  The stored procedure name (without CALL keyword)
 * @param  array  $inParams       Array of IN parameter values
 * @param  string $inTypes        Type string for IN parameters
 * @param  array  $outParams      Array of OUT parameter names (e.g., ['@outputDest', '@outputStatus'])
 * @return array|false            Associative array of OUT parameter values, or false on error
 *
 * 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/call.html
 *
 * Usage example:
 *   $result = dbCallProcedure(
 *       'sp_lookupShortURL',
 *       ['g2my.link', 'abc123'],
 *       'ss',
 *       ['@outputDestination', '@outputStatus', '@outputOrgHandle']
 *   );
 *   // $result = ['@outputDestination' => 'https://...', '@outputStatus' => 'success', ...]
 */
function dbCallProcedure(string $procedureName, array $inParams = [], string $inTypes = '', array $outParams = []): array|false
{
    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] ERROR: dbCallProcedure failed — no database connection.');
        return false;
    }

    $startTime = microtime(true);

    try
    {
        // Build the CALL statement with placeholders for IN params and session vars for OUT params
        $allPlaceholders = [];

        // IN parameters use ? placeholders
        for ($i = 0; $i < count($inParams); $i++)
        {
            $allPlaceholders[] = '?';
        }

        // OUT parameters use session variables (validated to prevent injection)
        foreach ($outParams as $outParam)
        {
            // Validate OUT parameter names follow MySQL session variable naming convention
            if (!preg_match('/^@[a-zA-Z_][a-zA-Z0-9_]*$/', $outParam))
            {
                error_log('[Go2My.Link] ERROR: Invalid OUT parameter name: ' . $outParam);
                return false;
            }

            $allPlaceholders[] = $outParam;
        }

        // Validate procedure name to prevent SQL injection via dynamic names
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $procedureName))
        {
            error_log('[Go2My.Link] ERROR: Invalid procedure name rejected: ' . $procedureName);
            return false;
        }

        $sql = 'CALL `' . $procedureName . '`(' . implode(', ', $allPlaceholders) . ')';

        $stmt = $db->prepare($sql);

        if ($stmt === false)
        {
            error_log('[Go2My.Link] ERROR: dbCallProcedure prepare failed: ' . $db->error . ' | CALL: ' . $procedureName);
            _g2ml_logQuery($sql, $inParams, (microtime(true) - $startTime) * 1000, false);
            return false;
        }

        // Bind IN parameters
        if ($inTypes !== '' && count($inParams) > 0)
        {
            $stmt->bind_param($inTypes, ...$inParams);
        }

        $stmt->execute();

        // Free any result sets from the procedure
        // 📖 Reference: https://www.php.net/manual/en/mysqli-stmt.close.php
        while ($stmt->more_results())
        {
            $stmt->next_result();
        }
        $stmt->close();

        // Retrieve OUT parameter values via SELECT
        $result = [];

        if (count($outParams) > 0)
        {
            $selectSQL = 'SELECT ' . implode(', ', $outParams);
            $selectResult = $db->query($selectSQL);

            if ($selectResult !== false)
            {
                $row = $selectResult->fetch_assoc();
                $selectResult->free();

                if ($row !== null)
                {
                    $result = $row;
                }
            }
            else
            {
                error_log('[Go2My.Link] WARNING: dbCallProcedure OUT param SELECT failed: ' . $db->error);
            }
        }

        _g2ml_logQuery('CALL ' . $procedureName, $inParams, (microtime(true) - $startTime) * 1000, true);

        return $result;
    }
    catch (mysqli_sql_exception $e)
    {
        error_log('[Go2My.Link] ERROR: dbCallProcedure exception: ' . $e->getMessage() . ' | CALL: ' . $procedureName);
        _g2ml_logQuery('CALL ' . $procedureName, $inParams, (microtime(true) - $startTime) * 1000, false);
        return false;
    }
}

/**
 * Execute a raw SQL query (non-prepared).
 *
 * ⚠️ WARNING: Only use this for queries with NO user input (DDL, SET commands,
 * internal admin queries). NEVER pass user-supplied data to this function.
 *
 * @param  string             $sql  The SQL query to execute
 * @return mysqli_result|bool       Result object for SELECT, true/false for other queries
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.query.php
 */
function dbRawQuery(string $sql): mysqli_result|bool
{
    $db = getDB();

    if ($db === null)
    {
        error_log('[Go2My.Link] ERROR: dbRawQuery failed — no database connection.');
        return false;
    }

    $startTime = microtime(true);

    try
    {
        $result = $db->query($sql);
        _g2ml_logQuery($sql, [], (microtime(true) - $startTime) * 1000, $result !== false);
        return $result;
    }
    catch (mysqli_sql_exception $e)
    {
        error_log('[Go2My.Link] ERROR: dbRawQuery exception: ' . $e->getMessage() . ' | SQL: ' . $sql);
        _g2ml_logQuery($sql, [], (microtime(true) - $startTime) * 1000, false);
        return false;
    }
}

/**
 * Begin a database transaction.
 *
 * @return bool  True if the transaction started successfully
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.begin-transaction.php
 */
function dbBeginTransaction(): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    return $db->begin_transaction();
}

/**
 * Commit the current database transaction.
 *
 * @return bool  True if the commit succeeded
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.commit.php
 */
function dbCommit(): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    return $db->commit();
}

/**
 * Roll back the current database transaction.
 *
 * @return bool  True if the rollback succeeded
 *
 * 📖 Reference: https://www.php.net/manual/en/mysqli.rollback.php
 */
function dbRollback(): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    return $db->rollback();
}
