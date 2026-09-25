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
 * 🧪 Integration tests — the database has the collation the app depends on (#196)
 * ============================================================================
 *
 * Proves, against a REAL imported database, three checks for #196: the
 * database's own default collation is utf8mb4_unicode_ci (not whatever a
 * hosting panel or a container's server default happens to be), every table
 * keeps that same collation, and the short-code generator actually produces
 * a code — the symptom that hid the bug for weeks was sp_generateShortCode
 * silently returning nothing rather than a loud SQL error (see #197, which
 * is about that swallowed error specifically).
 *
 * These checks are read-only against whatever database run_integration.php
 * connected to; they change nothing and can run alongside every other
 * integration file.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #196
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// With no reachable test DB the runner skips before this file is ever
// included — this guard mirrors the one in entitlements_test.php
// (lines 55-58).
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

/**
 * Read the name of the database $db is actually connected to, so these
 * checks run against whatever G2ML_TEST_DB_NAME really selected rather than
 * assuming the default 'mwtools_Go2MyLink'.
 *
 * @param  mysqli $db
 * @return string
 */
function _g2mlCollationTestCurrentDatabase(mysqli $db): string
{
    $result = mysqli_query($db, 'SELECT DATABASE() AS dbName');

    if ($result === false)
    {
        throw new RuntimeException('Could not read the current database name: ' . mysqli_error($db));
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    if ($row === null || $row['dbName'] === null)
    {
        throw new RuntimeException('SELECT DATABASE() returned no database — is the connection using one?');
    }

    return (string) $row['dbName'];
}

// ============================================================================
// (1) The database's own default collation — the setting a stored
//     procedure's local variables inherit — is the required one.
// ============================================================================
test('the database\'s own default collation is utf8mb4_unicode_ci (#196)', function () use ($db): void
{
    $databaseName = _g2mlCollationTestCurrentDatabase($db);

    $statement = mysqli_prepare(
        $db,
        'SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
    );
    mysqli_stmt_bind_param($statement, 's', $databaseName);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);

    assert_true($row !== null, 'information_schema.SCHEMATA had no row for database "' . $databaseName . '"');
    assert_same(
        'utf8mb4_unicode_ci',
        $row['DEFAULT_COLLATION_NAME'],
        'Database "' . $databaseName . '" has the wrong default collation — every stored-procedure '
            . 'variable-to-column comparison depends on this (#196).'
    );
});

// ============================================================================
// (2) Every real table keeps the collation its CREATE TABLE states.
// ============================================================================
test('every table in the schema keeps the utf8mb4_unicode_ci collation (#196)', function () use ($db): void
{
    $databaseName = _g2mlCollationTestCurrentDatabase($db);

    $statement = mysqli_prepare(
        $db,
        "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    mysqli_stmt_bind_param($statement, 's', $databaseName);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    $tableCount  = 0;
    $wrongTables = array();

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $tableCount = $tableCount + 1;

        if ($row['TABLE_COLLATION'] !== 'utf8mb4_unicode_ci')
        {
            $wrongTables[] = $row['TABLE_NAME'] . ' (' . $row['TABLE_COLLATION'] . ')';
        }
    }

    assert_true($tableCount > 0, 'No tables were found in database "' . $databaseName . '" — has the schema been imported?');
    assert_same(
        array(),
        $wrongTables,
        'Table(s) with the wrong collation: ' . implode(', ', $wrongTables)
    );
});

// ============================================================================
// (3) sp_generateShortCode actually produces a code. Before #196's fix, a
//     mismatched database collation made it silently return NULL instead
//     (swallowed by its own error handler — see #197).
// ============================================================================
test('sp_generateShortCode returns a code for the [default] org (#196)', function () use ($db): void
{
    $statement = mysqli_prepare($db, 'CALL sp_generateShortCode(?, ?, @sx196OutCode)');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare CALL failed: ' . mysqli_error($db));
    }

    $orgHandle = '[default]';
    $length    = 7;
    mysqli_stmt_bind_param($statement, 'si', $orgHandle, $length);
    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('CALL sp_generateShortCode failed: ' . $error);
    }

    mysqli_stmt_close($statement);

    $result = mysqli_query($db, 'SELECT @sx196OutCode AS code');

    if ($result === false)
    {
        throw new RuntimeException('Reading the OUT parameter failed: ' . mysqli_error($db));
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    assert_true(
        $row !== null && $row['code'] !== null && $row['code'] !== '',
        'sp_generateShortCode returned no code (#196). Since #197, NULL means all 20 random attempts '
            . 'were already taken — a real database error now fails the CALL itself instead.'
    );
});
