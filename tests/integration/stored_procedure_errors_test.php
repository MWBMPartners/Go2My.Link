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
 * 🧪 Integration test — a real database error now reaches the log (#197)
 * ============================================================================
 *
 * Both sp_generateShortCode and sp_lookupShortURL used to catch every SQL
 * error inside the procedure itself and quietly fold it into an existing
 * outcome — sp_generateShortCode into the same NULL it already returns
 * after running out of attempts, sp_lookupShortURL into a bare
 * status='error' — indistinguishable from bad luck, with the real MySQL
 * message thrown away. This file proves the REPLACEMENT behaviour end to
 * end for sp_generateShortCode: a genuine database error (the stored
 * procedure cannot see the table it needs) now propagates out of the
 * procedure, and dbCallProcedure() (web/_functions/db_query.php) catches
 * THAT and returns false, logging the real MySQL message, rather than the
 * procedure swallowing it first.
 *
 * sp_generateShortCode returning a code normally is already characterised
 * by tests/integration/database_collation_test.php ("sp_generateShortCode
 * returns a code for the [default] org (#196)"), and sp_lookupShortURL
 * returning 'not_found' for an unknown code is already characterised by
 * tests/integration/shorturl_lookup_test.php ("an unknown code resolves
 * with status=not_found") — both still exercised by this suite and both
 * still pass after #197, since removing the catch-all handler changes
 * nothing about either procedure's ordinary, non-error outcomes. Neither is
 * repeated here.
 *
 * WHY A TEMPORARY DATABASE, AND WHY A CHILD PROCESS
 *
 * getDB() (web/_functions/db_connect.php) is a per-process singleton: once
 * anything in this test run has connected it, every later call returns
 * that SAME connection, whatever DB_NAME says by then — and earlier files
 * in this suite (entitlements_test.php, for one) already do. The only
 * reliable way to make dbCallProcedure() actually talk to a throwaway
 * database is to ask a FRESH PHP process to do it, with DB_NAME pointing
 * at that database from its very first line.
 *
 * The temporary database holds a COPY of sp_generateShortCode ONLY — never
 * tblShortURLs — so calling it must fail with a real MySQL error (the table
 * does not exist), which is exactly the class of fault #197 is about.
 *
 * WHAT THIS FILE CANNOT DO
 *
 * It cannot run without a database user that can CREATE and DROP a
 * database — the plan for #197 says to skip in that case, which this file
 * does, with a clear message, rather than failing. It also does not run
 * when the CLI's exec() is disabled, the same limit
 * direct_access_guard_test.php has, and for the same reason.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #197
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// With no reachable test DB the runner skips before this file is ever
// included — this guard mirrors the one in entitlements_test.php.
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Connection parameters for the REAL test server, re-derived from the same
// environment variables run_integration.php used. Guarded with !defined()
// so this composes with entitlements_test.php or any other file that
// already defined them (see that file's own copy of this pattern) — the
// child process below needs these as literal values, not just as this
// process's already-open $db handle.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlSpErrEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlSpErrEnvHost === false || $g2mlSpErrEnvHost === '')
    {
        $g2mlSpErrEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlSpErrEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlSpErrEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlSpErrEnvPort === false || $g2mlSpErrEnvPort === '')
    {
        $g2mlSpErrEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlSpErrEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlSpErrEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlSpErrEnvUser === false || $g2mlSpErrEnvUser === '')
    {
        $g2mlSpErrEnvUser = 'root';
    }

    define('DB_USER', $g2mlSpErrEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlSpErrEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlSpErrEnvPass === false)
    {
        $g2mlSpErrEnvPass = '';
    }

    define('DB_PASS', $g2mlSpErrEnvPass);
}

// ============================================================================
// 🧰 Helpers
// ============================================================================

/**
 * Whether this PHP environment can shell out via exec(). Mirrors
 * g2ml_directAccessGuard_execAvailable() in
 * tests/unit/direct_access_guard_test.php, duplicated under a different
 * name here because that file lives in tests/unit and is never loaded by
 * this (integration) runner.
 *
 * @return bool
 */
function g2ml_spErr_execAvailable(): bool
{
    return function_exists('exec');
}

/**
 * Run a short, throwaway PHP script in a brand-new child process and
 * return its combined stdout+stderr together with its real exit code.
 *
 * @param  string $scriptBody  PHP code (no opening "<?php" tag) to run.
 * @return array{output: string, exitCode: int}
 */
function g2ml_spErr_runChildScript(string $scriptBody): array
{
    $script   = '<?php' . "\n" . 'declare(strict_types=1);' . "\n" . $scriptBody;
    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_sperr_');

    if ($tempFile === false)
    {
        return array('output' => '', 'exitCode' => -1);
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
 * Split a SQL script into individual statements, honouring DELIMITER
 * changes and skipping full-line comments and CREATE DATABASE / USE
 * statements — the connection running each statement already has the
 * target database selected.
 *
 * Deliberately duplicates the parsing web/Go2My.Link/public_html/install/
 * index.php's g2ml_install_split_sql() already does, rather than requiring
 * that file: it starts a PHP session and touches the installer's own lock
 * files as soon as it is loaded, which a test must never trigger.
 *
 * @param  string $sql
 * @return array<int, string>
 */
function g2ml_spErr_splitSql(string $sql): array
{
    $lines      = preg_split("/\r\n|\n|\r/", $sql);
    $delimiter  = ';';
    $buffer     = '';
    $statements = array();

    foreach ($lines as $line)
    {
        $trimmed = trim($line);

        if ($trimmed === '')
        {
            continue;
        }

        if (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0)
        {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $matches) === 1)
        {
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . "\n";

        $delimiterLength = strlen($delimiter);

        if (substr(rtrim($trimmed), -$delimiterLength) === $delimiter)
        {
            $statement = rtrim($buffer);
            $statement = substr($statement, 0, strlen($statement) - $delimiterLength);
            $statement = trim($statement);

            if ($statement !== '' && preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement) !== 1)
            {
                $statements[] = $statement;
            }

            $buffer = '';
        }
    }

    if (trim($buffer) !== '')
    {
        $statements[] = trim($buffer);
    }

    return $statements;
}

/**
 * Open a dedicated connection to the named database on the same server the
 * suite is already using, independent of getDB()'s singleton and of the
 * shared $db this file receives.
 *
 * @param  string $databaseName
 * @return mysqli|null
 */
function g2ml_spErr_connectToDatabase(string $databaseName): ?mysqli
{
    $connection = @mysqli_init();

    if ($connection === false)
    {
        return null;
    }

    $opened = @mysqli_real_connect($connection, DB_HOST, DB_USER, DB_PASS, $databaseName, DB_PORT);

    if ($opened === false)
    {
        return null;
    }

    return $connection;
}

/**
 * Run a setup statement, throwing with the real MySQL error on failure —
 * whether mysqli reports that failure by returning false or by throwing,
 * which depends on mysqli_report() state this process may have inherited
 * from an earlier test file. A setup failure here is a fault in this test's
 * own arrangement, not the behaviour under test, so it must surface clearly
 * rather than masquerade as an assertion failure (mirrors g2ml_test_exec()
 * in shorturl_lookup_test.php).
 *
 * @param  mysqli $connection
 * @param  string $sql
 * @return void
 */
function g2ml_spErr_execOrThrow(mysqli $connection, string $sql): void
{
    try
    {
        $result = mysqli_query($connection, $sql);
    }
    catch (mysqli_sql_exception $exception)
    {
        throw new RuntimeException('Setup query failed: ' . $exception->getMessage() . ' — SQL: ' . $sql);
    }

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($connection) . ' — SQL: ' . $sql);
    }
}

/**
 * Attempt to create the named database, returning whether it succeeded —
 * false rather than an exception either way, since the caller treats "this
 * user cannot CREATE DATABASE" as a SKIP, not a setup fault.
 *
 * CREATE/DROP DATABASE statements cannot use a bound parameter (MySQL does
 * not allow binding an identifier), so $databaseName is placed directly in
 * the statement — safe here because it is generated by this test itself
 * (a fixed prefix plus random hex digits), never taken from any external
 * input.
 *
 * @param  mysqli $connection
 * @param  string $databaseName
 * @return bool
 */
function g2ml_spErr_tryCreateDatabase(mysqli $connection, string $databaseName): bool
{
    try
    {
        $result = mysqli_query(
            $connection,
            'CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }
    catch (mysqli_sql_exception $exception)
    {
        return false;
    }

    return $result === true;
}

/**
 * Best-effort cleanup of the temporary database. A failure here must never
 * turn a passing test into a failure — there is nothing more this test can
 * do about it. Nothing in this suite removes a database this leaves behind.
 *
 * @param  mysqli $connection
 * @param  string $databaseName
 * @return void
 */
function g2ml_spErr_dropDatabaseIfExists(mysqli $connection, string $databaseName): void
{
    try
    {
        mysqli_query($connection, 'DROP DATABASE IF EXISTS `' . $databaseName . '`');
    }
    catch (mysqli_sql_exception $exception)
    {
        // Best-effort only — see the doc comment above.
    }
}

// ============================================================================
// The test
// ============================================================================

test('dbCallProcedure(\'sp_generateShortCode\') returns false and logs the MySQL message when the procedure cannot see tblShortURLs (#197)', function () use ($db): void
{
    if (!g2ml_spErr_execAvailable())
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    $tempDatabaseName = 'g2ml_test_sp_err_' . bin2hex(random_bytes(4));

    if (!g2ml_spErr_tryCreateDatabase($db, $tempDatabaseName))
    {
        echo 'SKIPPED: the test database user cannot CREATE DATABASE — see #197\'s plan for this fallback' . PHP_EOL;
        return;
    }

    try
    {
        // Import ONLY the procedure into the temporary database — never
        // tblShortURLs — so calling it must hit a real "table doesn't
        // exist" error rather than succeeding.
        $procedureFilePath = dirname(__DIR__, 2) . '/web/_sql/procedures/sp_generateShortCode.sql';
        $procedureSql       = file_get_contents($procedureFilePath);

        assert_true($procedureSql !== false, 'Could not read ' . $procedureFilePath);

        $tempConnection = g2ml_spErr_connectToDatabase($tempDatabaseName);

        assert_true($tempConnection !== null, 'Could not connect to the temporary database ' . $tempDatabaseName);

        foreach (g2ml_spErr_splitSql((string) $procedureSql) as $statement)
        {
            g2ml_spErr_execOrThrow($tempConnection, $statement);
        }

        mysqli_close($tempConnection);

        // dbCallProcedure() uses getDB(), a per-process singleton — see this
        // file's header for why this specific check needs a fresh child
        // process rather than the shared $db this file otherwise uses.
        $childScript  = 'define(\'DB_HOST\', ' . var_export(DB_HOST, true) . ');' . "\n";
        $childScript .= 'define(\'DB_PORT\', ' . var_export(DB_PORT, true) . ');' . "\n";
        $childScript .= 'define(\'DB_USER\', ' . var_export(DB_USER, true) . ');' . "\n";
        $childScript .= 'define(\'DB_PASS\', ' . var_export(DB_PASS, true) . ');' . "\n";
        $childScript .= 'define(\'DB_NAME\', ' . var_export($tempDatabaseName, true) . ');' . "\n";
        $childScript .= 'define(\'DB_CHARSET\', \'utf8mb4\');' . "\n";
        $childScript .= 'require ' . var_export(dirname(__DIR__, 2) . '/web/_functions/db_connect.php', true) . ';' . "\n";
        $childScript .= 'require ' . var_export(dirname(__DIR__, 2) . '/web/_functions/db_query.php', true) . ';' . "\n";
        $childScript .= '$result = dbCallProcedure(\'sp_generateShortCode\', [\'[default]\', 7], \'si\', [\'@outputCode\']);' . "\n";
        $childScript .= 'if ($result === false) { echo \'SPERR_RESULT_FALSE\' . PHP_EOL; } '
            . 'else { echo \'SPERR_RESULT_OTHER:\' . var_export($result, true) . PHP_EOL; }' . "\n";

        $childResult = g2ml_spErr_runChildScript($childScript);

        assert_contains(
            'SPERR_RESULT_FALSE',
            $childResult['output'],
            'dbCallProcedure() must return false when the procedure it calls cannot see tblShortURLs — child '
                . 'process output was: ' . $childResult['output']
        );

        // The stronger check: not just that dbCallProcedure() returned
        // false, but that it did so via its EXCEPTION path (#197) — the
        // procedure's error genuinely reached PHP — rather than, say,
        // failing to connect at all for an unrelated reason. This is the
        // exact log line web/_functions/db_query.php writes in that catch
        // block; error_log() with no destination configured writes to
        // stderr under the CLI SAPI, which g2ml_spErr_runChildScript()
        // captures alongside stdout.
        assert_contains(
            '[Go2My.Link] ERROR: dbCallProcedure exception:',
            $childResult['output'],
            'dbCallProcedure() must log the real MySQL exception message (#197) — child process output was: '
                . $childResult['output']
        );
    }
    finally
    {
        g2ml_spErr_dropDatabaseIfExists($db, $tempDatabaseName);
    }
});
