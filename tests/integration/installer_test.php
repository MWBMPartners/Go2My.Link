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
 * 🧪 Integration test — a fresh install, using the installer's own code (#316)
 * ============================================================================
 *
 * The web installer could not create the first administrator: it wrote the
 * account type 'globaladmin', the seed file defines 'global-admin', and the
 * foreign key between them rolled the whole account back. Nothing ran the
 * installer against a real database, so nobody saw it.
 *
 * This file loads web/Go2My.Link/public_html/install/index.php with
 * G2ML_INSTALLER_FUNCTIONS_ONLY defined (so no page is run) and calls the same
 * functions the wizard's buttons call, in the same order:
 *
 *   collation check -> import -> create administrator -> finish
 *
 * It works in its own empty database with a made-up name, created with the
 * "wrong" collation as a hosting panel would, and in its own temporary folder.
 * Both are removed at the end. The database the rest of the suite uses is not
 * touched. The test database user therefore needs permission to create and
 * drop databases (root, in tests/README.md and run-tests.sh).
 *
 * The import reads whatever is in web/_sql at the time of the run, so a new
 * numbered file is covered without changing this test.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

if (!defined('G2ML_INSTALLER_FUNCTIONS_ONLY'))
{
    define('G2ML_INSTALLER_FUNCTIONS_ONLY', true);
}

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/install/index.php';

/**
 * The details a person would type on the installer's database step, taken from
 * the same environment variables the integration runner uses.
 *
 * @param  string $databaseName
 * @return array<string, mixed>
 */
function _g2mlInstallerTestCreds(string $databaseName): array
{
    $host   = g2ml_test_env('G2ML_TEST_DB_HOST', '127.0.0.1');
    $socket = getenv('G2ML_TEST_DB_SOCKET');

    if ($socket !== false && $socket !== '')
    {
        // The installer takes a host name only. "localhost" makes mysqli use
        // its default socket, so point that at the test server's socket.
        ini_set('mysqli.default_socket', $socket);
        $host = 'localhost';
    }

    return array(
        'host'    => $host,
        'port'    => (int) g2ml_test_env('G2ML_TEST_DB_PORT', '3306'),
        'name'    => $databaseName,
        'user'    => g2ml_test_env('G2ML_TEST_DB_USER', 'root'),
        'pass'    => g2ml_test_env('G2ML_TEST_DB_PASS', ''),
        'charset' => 'utf8mb4',
    );
}

/**
 * Run a piece of work on a connection made by the installer, then put the
 * mysqli error reporting setting back as it was (the installer switches it
 * off, and later tests must not inherit that).
 *
 * @param  string   $databaseName
 * @param  callable $work          Receives the mysqli connection.
 * @return void
 */
function _g2mlInstallerTestWithConnection(string $databaseName, callable $work): void
{
    $driver     = new mysqli_driver();
    $reportMode = $driver->report_mode;
    $connection = null;

    try
    {
        $connection = g2ml_install_connect(_g2mlInstallerTestCreds($databaseName));

        if (is_string($connection))
        {
            throw new G2mlAssertionError($connection);
        }

        $work($connection);
    }
    finally
    {
        if ($connection instanceof mysqli)
        {
            $connection->close();
        }

        mysqli_report($reportMode);
    }
}

/**
 * The first column of the first row of a query, as text. Fails the test if the
 * query fails.
 *
 * @param  mysqli $connection
 * @param  string $sql
 * @return string
 */
function _g2mlInstallerTestValue(mysqli $connection, string $sql): string
{
    $result = $connection->query($sql);

    if (!($result instanceof mysqli_result))
    {
        throw new G2mlAssertionError('Query failed: ' . $connection->error . ' in ' . $sql);
    }

    $row = $result->fetch_row();
    $result->free();

    if (!is_array($row))
    {
        return '';
    }

    return (string) $row[0];
}

/**
 * Remove a temporary folder and everything in it.
 *
 * @param  string $folder
 * @return void
 */
function _g2mlInstallerTestRemove(string $folder): void
{
    if (!is_dir($folder))
    {
        return;
    }

    foreach (array_diff((array) scandir($folder), array('.', '..')) as $entry)
    {
        $path = $folder . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($path) && !is_link($path))
        {
            _g2mlInstallerTestRemove($path);
        }
        else
        {
            unlink($path);
        }
    }

    rmdir($folder);
}

$g2mlInstallerSuffix   = bin2hex(random_bytes(6));
$g2mlInstallerDbName   = 'g2ml_installer_test_' . $g2mlInstallerSuffix;
$g2mlInstallerWebRoot  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_installer_it_' . $g2mlInstallerSuffix;
$g2mlInstallerSqlDir   = dirname(__DIR__, 2) . '/web/_sql';
$g2mlInstallerAdmin    = array(
    'username'  => 'Salem874',
    'email'     => 'installer-test@example.com',
    'password'  => 'Installer-Test-Password-1!',
    'firstName' => 'Installer',
    'lastName'  => 'Test',
);

if (mysqli_query($db, 'CREATE DATABASE `' . $g2mlInstallerDbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci') !== true)
{
    test('installer: the test database user can create a scratch database (#316)', function () use ($db): void
    {
        assert_true(false, 'CREATE DATABASE failed: ' . mysqli_error($db));
    });

    return;
}

register_shutdown_function(function () use ($g2mlInstallerDbName, $g2mlInstallerWebRoot): void
{
    $driver     = new mysqli_driver();
    $reportMode = $driver->report_mode;
    mysqli_report(MYSQLI_REPORT_OFF);

    // The runner has closed its own connection by now, so open a new one.
    $creds      = _g2mlInstallerTestCreds($g2mlInstallerDbName);
    $connection = g2ml_install_connect($creds);

    if ($connection instanceof mysqli)
    {
        $connection->query('DROP DATABASE IF EXISTS `' . $g2mlInstallerDbName . '`');
        $connection->close();
    }

    mysqli_report($reportMode);
    _g2mlInstallerTestRemove($g2mlInstallerWebRoot);
});

test('installer: the database step corrects the collation a hosting panel would leave (#316, #196)', function () use ($g2mlInstallerDbName): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerDbName): void
    {
        $result = g2ml_install_check_collation($connection, $g2mlInstallerDbName);

        assert_true($result['ok'], $result['error'] ?? '');
    });
});

test('installer: the import step runs every schema, procedure and seed file into an empty database (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerSqlDir): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerSqlDir): void
    {
        $result = g2ml_install_import($connection, $g2mlInstallerSqlDir);
        $last   = $result['log'][count($result['log']) - 1];

        assert_true($result['ok'], $last['file'] . ': ' . ($last['error'] ?? ''));

        $expectedFiles  = array();
        $expectedTables = array();

        foreach (array('schema', 'procedures', 'seeds') as $folder)
        {
            foreach ((array) glob($g2mlInstallerSqlDir . '/' . $folder . '/*.sql') as $file)
            {
                $expectedFiles[] = basename((string) $file);

                preg_match_all('/^CREATE TABLE IF NOT EXISTS `([A-Za-z0-9_]+)`/m', (string) file_get_contents((string) $file), $matches);
                $expectedTables = array_merge($expectedTables, $matches[1]);
            }
        }

        assert_same($expectedFiles, array_column($result['log'], 'file'), 'Every SQL file must be imported');
        assert_true(count($expectedTables) > 20, 'The table names could not be read from the schema files');

        foreach ($expectedTables as $table)
        {
            assert_same('1', _g2mlInstallerTestValue(
                $connection,
                "SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $table . "'"
            ), 'Missing table ' . $table);
        }

        assert_same('2', _g2mlInstallerTestValue(
            $connection,
            "SELECT COUNT(*) FROM `information_schema`.`ROUTINES` WHERE `ROUTINE_SCHEMA` = DATABASE() AND `ROUTINE_NAME` IN ('sp_generateShortCode', 'sp_lookupShortURL')"
        ), 'Both stored procedures must exist');

        // A stored procedure that exists can still fail when it runs.
        assert_true($connection->query("CALL `sp_generateShortCode`('[default]', 7, @g2mlCode)") !== false, $connection->error);
        assert_same('7', _g2mlInstallerTestValue($connection, 'SELECT CHAR_LENGTH(@g2mlCode)'));
    });
});

test('installer: running the import a second time succeeds and adds nothing twice (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerSqlDir): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerSqlDir): void
    {
        $countSettings = 'SELECT COUNT(*) FROM `tblSettings`';
        $before        = _g2mlInstallerTestValue($connection, $countSettings);

        $result = g2ml_install_import($connection, $g2mlInstallerSqlDir);
        $last   = $result['log'][count($result['log']) - 1];

        assert_true($result['ok'], $last['file'] . ': ' . ($last['error'] ?? ''));
        assert_same($before, _g2mlInstallerTestValue($connection, $countSettings));
        assert_same('2', _g2mlInstallerTestValue(
            $connection,
            "SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `CONSTRAINT_NAME` IN ('FK_url_apikey', 'FK_short_domain_linkspage')"
        ), 'The two foreign keys added after table creation must exist exactly once');
    });
});

test('installer: the import step reports a missing SQL folder instead of succeeding (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerWebRoot): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerWebRoot): void
    {
        $result = g2ml_install_import($connection, $g2mlInstallerWebRoot . '/no_such_sql_folder');

        assert_false($result['ok']);
    });
});

test('installer: the first GlobalAdmin is created with its account type (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerAdmin): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerAdmin): void
    {
        $securityPath = dirname(__DIR__, 2) . '/web/_functions/security.php';
        $result       = g2ml_install_create_admin($connection, $g2mlInstallerAdmin, $securityPath);

        assert_true($result['ok'], $result['error']);
        assert_false(isset($result['resumed']));

        assert_same('1', _g2mlInstallerTestValue(
            $connection,
            "SELECT COUNT(*) FROM `tblUsers` AS `u`
             INNER JOIN `tblUserAccountTypes` AS `a` ON `a`.`userUID` = `u`.`userUID`
             INNER JOIN `tblAccountTypes` AS `t` ON `t`.`accountTypeID` = `a`.`accountTypeID`
             WHERE `u`.`username` = 'Salem874' AND `u`.`role` = 'GlobalAdmin' AND `u`.`isActive` = 1
               AND `u`.`emailVerified` = 1 AND `a`.`isActive` = 1 AND `a`.`orgHandle` = '[default]'
               AND `a`.`accountTypeID` = 'global-admin' AND `t`.`roleName` = 'GlobalAdmin'"
        ));

        $hash = _g2mlInstallerTestValue($connection, "SELECT `passwordHash` FROM `tblUsers` WHERE `username` = 'Salem874'");
        assert_true(password_verify($g2mlInstallerAdmin['password'], $hash), 'The stored password must verify');
    });
});

test('installer: a second administrator is refused, and only the same details resume an unfinished install (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerAdmin): void
{
    _g2mlInstallerTestWithConnection($g2mlInstallerDbName, function (mysqli $connection) use ($g2mlInstallerAdmin): void
    {
        $securityPath = dirname(__DIR__, 2) . '/web/_functions/security.php';

        foreach (array('username' => 'someoneelse', 'email' => 'other@example.com', 'password' => 'Not-The-Password-1!') as $field => $value)
        {
            $other         = $g2mlInstallerAdmin;
            $other[$field] = $value;
            $result        = g2ml_install_create_admin($connection, $other, $securityPath);

            assert_false($result['ok'], 'A different ' . $field . ' must be refused');
        }

        $again = g2ml_install_create_admin($connection, $g2mlInstallerAdmin, $securityPath);

        assert_true($again['ok'], $again['error']);
        assert_true($again['resumed'] ?? false);
        assert_same('1', _g2mlInstallerTestValue($connection, 'SELECT COUNT(*) FROM `tblUsers`'));
        assert_same('1', _g2mlInstallerTestValue($connection, 'SELECT COUNT(*) FROM `tblUserAccountTypes`'));
    });
});

test('installer: finishing writes the credentials files and locks only when all of them exist (#316)', function () use ($g2mlInstallerDbName, $g2mlInstallerWebRoot): void
{
    $authDir    = $g2mlInstallerWebRoot . '/_auth_keys';
    $components = array();

    mkdir($authDir, 0700, true);

    foreach (array('Go2My.Link', 'G2My.Link', 'Lnks.page') as $name)
    {
        $components[$name] = $g2mlInstallerWebRoot . '/' . $name;
        mkdir($components[$name], 0700);
    }

    // A plain file where the .auth folder belongs: the include cannot be made.
    // (File permissions would not stop it, as the tests may run as root.)
    file_put_contents($components['Lnks.page'] . '/.auth', 'in the way');

    $creds  = _g2mlInstallerTestCreds($g2mlInstallerDbName);
    $failed = g2ml_install_finalise($creds, $authDir, $components);

    assert_false($failed['ok'], 'Finishing must fail when a component include cannot be written');
    assert_true(count($failed['errors']) === 1);
    assert_false(file_exists($authDir . '/.installed'), 'A half-finished install must not be locked');

    $keyBefore = g2ml_install_existing_key($authDir . '/auth_creds.php', 'ENCRYPTION_SALT');
    assert_true($keyBefore !== null && strlen($keyBefore) === 64);

    unlink($components['Lnks.page'] . '/.auth');

    $finished = g2ml_install_finalise($creds, $authDir, $components);

    assert_true($finished['ok'], implode(' ', $finished['errors']));
    assert_true(file_exists($authDir . '/.installed'));
    assert_true(file_exists($authDir . '/.htaccess'));
    assert_same($keyBefore, g2ml_install_existing_key($authDir . '/auth_creds.php', 'ENCRYPTION_SALT'), 'A second attempt must keep the keys');
    assert_same(0600, fileperms($authDir . '/auth_creds.php') & 0777);

    // Load each component's include in a fresh PHP process, as the site does,
    // and connect to the database with what it defines.
    foreach ($components as $name => $folder)
    {
        $include = $folder . '/.auth/auth_creds.php';

        assert_true(file_exists($include), $name . ' include is missing');

        $code = 'require ' . var_export($include, true) . ';'
            . '$c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);'
            . 'echo $c->query("SELECT COUNT(*) FROM `tblUsers`")->fetch_row()[0], "|",'
            . ' strlen(ENCRYPTION_SALT), "|", strlen(ENCRYPTION_KEY_SECONDARY);';
        $socketSetting = '';

        if (getenv('G2ML_TEST_DB_SOCKET') !== false && getenv('G2ML_TEST_DB_SOCKET') !== '')
        {
            $socketSetting = ' -d ' . escapeshellarg('mysqli.default_socket=' . getenv('G2ML_TEST_DB_SOCKET'));
        }

        $output = shell_exec(escapeshellarg(PHP_BINARY) . $socketSetting . ' -r ' . escapeshellarg($code) . ' 2>&1');

        assert_same('1|64|64', $output, $name);
    }
});
