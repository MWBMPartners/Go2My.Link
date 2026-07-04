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
 * 🧰 Go2My.Link — Web Installer (Full Bootstrap)
 * ============================================================================
 *
 * A self-locking, browser-based setup wizard that stands up a fresh
 * Go2My.Link installation for ALL THREE components (Go2My.Link, G2My.Link,
 * Lnks.page), which share a single database and one server-wide credentials
 * file (web/_auth_keys/auth_creds.php).
 *
 * Steps:
 *   0. Requirements check (PHP version, extensions, writable paths).
 *   1. Database credentials (host/port/name/user/password/charset) + live test.
 *   2. Import schema + stored procedures + seed data into the chosen database.
 *   3. Create the system-wide GlobalAdmin user.
 *   4. Finalise: generate encryption keys, write the shared auth_creds.php
 *      (+ ensure each component's include file exists), write the lock file.
 *
 * 🛡️  SECURITY:
 *   - Refuses to run once a lock file exists (web/_auth_keys/.installed).
 *   - Never echoes entered secrets back to the page.
 *   - Generates ENCRYPTION_SALT / ENCRYPTION_KEY_SECONDARY server-side
 *     (32 random bytes each) and writes the creds file with 0600 perms.
 *   - Uses its own random CSRF token (the app's CSRF needs keys that do not
 *     exist yet during install).
 *   - The whole install/ directory MUST be deleted after a successful install.
 *
 * @package    Go2My.Link
 * @subpackage Installer
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.0.0 — Launch Hardening
 * ============================================================================
 */

declare(strict_types=1);

// Harden the session cookie before the session starts.
$g2mlSecureCookie = false;
if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
{
    $g2mlSecureCookie = true;
}
elseif (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
{
    $g2mlSecureCookie = true;
}

session_set_cookie_params([
    'httponly' => true,
    'secure'   => $g2mlSecureCookie,
    'samesite' => 'Strict',
]);
session_start();

// Regenerate the session id once per installer session (anti-fixation).
if (empty($_SESSION['g2ml_install_started']))
{
    session_regenerate_id(true);
    $_SESSION['g2ml_install_started'] = true;
}

// ============================================================================
// 📁 Paths
// ============================================================================
// install/ is at: web/<Component>/public_html/install/
//   dirname(__DIR__, 1) = public_html
//   dirname(__DIR__, 2) = <Component>
//   dirname(__DIR__, 3) = web/   (the shared root holding _auth_keys, _sql, etc.)
// ============================================================================

$g2mlWebRoot   = dirname(__DIR__, 3);
$g2mlAuthDir   = $g2mlWebRoot . DIRECTORY_SEPARATOR . '_auth_keys';
$g2mlCredsFile = $g2mlAuthDir . DIRECTORY_SEPARATOR . 'auth_creds.php';
$g2mlLockFile  = $g2mlAuthDir . DIRECTORY_SEPARATOR . '.installed';
$g2mlTokenFile = $g2mlAuthDir . DIRECTORY_SEPARATOR . '.install_token';
$g2mlSqlDir    = $g2mlWebRoot . DIRECTORY_SEPARATOR . '_sql';
$g2mlSecurity  = $g2mlWebRoot . DIRECTORY_SEPARATOR . '_functions'
    . DIRECTORY_SEPARATOR . 'security.php';

// The three component directories that each need an _auth_keys/auth_creds.php
// include pointing at the shared file above.
$g2mlComponents = [
    'Go2My.Link' => $g2mlWebRoot . DIRECTORY_SEPARATOR . 'Go2My.Link',
    'G2My.Link'  => $g2mlWebRoot . DIRECTORY_SEPARATOR . 'G2My.Link',
    'Lnks.page'  => $g2mlWebRoot . DIRECTORY_SEPARATOR . 'Lnks.page',
];

// ============================================================================
// 🔐 CSRF (installer-local — independent of the app's keyed CSRF)
// ============================================================================

function g2ml_install_csrf_token(): string
{
    if (empty($_SESSION['g2ml_install_csrf']))
    {
        $_SESSION['g2ml_install_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['g2ml_install_csrf'];
}

function g2ml_install_csrf_ok(): bool
{
    $supplied = $_POST['_csrf'] ?? '';
    $expected = $_SESSION['g2ml_install_csrf'] ?? '';

    if ($expected === '' || $supplied === '')
    {
        return false;
    }

    return hash_equals($expected, $supplied);
}

// ============================================================================
// 🔒 HTTPS + proof-of-control token
// ============================================================================

function g2ml_install_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    {
        return true;
    }

    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    {
        return true;
    }

    // Allow a plaintext exception for local development only.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === 'localhost' || strncmp($host, 'localhost:', 10) === 0 || strncmp($host, '127.0.0.1', 9) === 0)
    {
        return true;
    }

    return false;
}

/**
 * Read (creating on first use) the installer proof-of-control token. The token
 * is written to a file ON THE SERVER which the operator must read off disk and
 * paste into the wizard — a remote attacker who cannot read the filesystem
 * cannot drive the installer.
 */
function g2ml_install_token_value(string $tokenFile): string
{
    if (!file_exists($tokenFile))
    {
        @file_put_contents($tokenFile, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($tokenFile, 0600);
    }

    $value = @file_get_contents($tokenFile);

    if ($value === false)
    {
        return '';
    }

    return trim($value);
}

function g2ml_install_token_ok(string $tokenFile, string $supplied): bool
{
    $expected = g2ml_install_token_value($tokenFile);

    if ($expected === '' || $supplied === '')
    {
        return false;
    }

    return hash_equals($expected, trim($supplied));
}

// ============================================================================
// 🔧 Helpers
// ============================================================================

function g2ml_install_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function g2ml_install_generate_key(): string
{
    // 32 bytes -> 64 hexadecimal characters (matches ENCRYPTION_SALT format).
    return bin2hex(random_bytes(32));
}

/**
 * Escape a value for safe embedding inside a single-quoted PHP string literal.
 */
function g2ml_install_php_literal(string $value): string
{
    $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);

    return "'" . $escaped . "'";
}

/**
 * Split a SQL script into individual statements, honouring DELIMITER changes
 * (used by stored-procedure files) and skipping full-line comments.
 */
function g2ml_install_split_sql(string $sql): array
{
    $lines      = preg_split("/\r\n|\n|\r/", $sql);
    $delimiter  = ';';
    $buffer     = '';
    $statements = [];

    foreach ($lines as $line)
    {
        $trimmed = trim($line);

        if ($trimmed === '')
        {
            continue;
        }

        // Skip full-line comments (-- ... or # ...).
        if (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0)
        {
            continue;
        }

        // Handle a DELIMITER directive (client-side only; not sent to MySQL).
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

            if ($statement !== '')
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
 * Execute every statement in a SQL file against the connected database.
 * Skips CREATE DATABASE / USE statements (we connect directly to the target).
 *
 * @return array{ok: bool, count: int, error?: string, statement?: string}
 */
function g2ml_install_run_sql_file(mysqli $db, string $path): array
{
    $sql = file_get_contents($path);

    if ($sql === false)
    {
        return ['ok' => false, 'count' => 0, 'error' => 'Unable to read file.'];
    }

    $statements = g2ml_install_split_sql($sql);
    $executed   = 0;

    foreach ($statements as $statement)
    {
        if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement) === 1)
        {
            continue;
        }

        if ($db->query($statement) === false)
        {
            return [
                'ok'        => false,
                'count'     => $executed,
                'error'     => $db->error,
                'statement' => substr($statement, 0, 200),
            ];
        }

        $executed = $executed + 1;
    }

    return ['ok' => true, 'count' => $executed];
}

/**
 * Connect to MySQL with the given credentials. Returns the mysqli object on
 * success or a string error message on failure.
 *
 * @return mysqli|string
 */
function g2ml_install_connect(array $creds)
{
    mysqli_report(MYSQLI_REPORT_OFF);

    $db = @new mysqli(
        $creds['host'],
        $creds['user'],
        $creds['pass'],
        $creds['name'],
        (int) $creds['port']
    );

    if ($db->connect_errno !== 0)
    {
        return 'Connection failed (' . $db->connect_errno . '): ' . $db->connect_error;
    }

    if ($db->set_charset($creds['charset']) === false)
    {
        return 'Connected, but failed to set charset "' . $creds['charset'] . '": ' . $db->error;
    }

    $db->query("SET time_zone = '+00:00'");

    return $db;
}

/**
 * Build the full server-wide auth_creds.php file contents.
 */
function g2ml_install_render_creds(array $creds, string $salt, string $secondaryKey): string
{
    $host      = g2ml_install_php_literal($creds['host']);
    $user      = g2ml_install_php_literal($creds['user']);
    $pass      = g2ml_install_php_literal($creds['pass']);
    $name      = g2ml_install_php_literal($creds['name']);
    $charset   = g2ml_install_php_literal($creds['charset']);
    $port      = (int) $creds['port'];
    $saltLit   = g2ml_install_php_literal($salt);
    $keyLit    = g2ml_install_php_literal($secondaryKey);

    $generated = gmdate('Y-m-d H:i:s') . ' UTC';

    $template = <<<PHP
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
 * 🔐 Go2My.Link — Server-Wide Authentication Credentials
 * ============================================================================
 *
 * GENERATED BY THE INSTALLER on {$generated}.
 *
 * Shared by ALL components (Go2My.Link, G2My.Link, Lnks.page). Each component's
 * own _auth_keys/auth_creds.php includes this file.
 *
 * ⚠️  Keep this file OUTSIDE any web root and OUT of version control.
 *     Do NOT change ENCRYPTION_SALT after first use or encrypted data becomes
 *     unrecoverable.
 *
 * @package    Go2My.Link
 * @subpackage Configuration
 * ============================================================================
 */

// 🛡️ Direct access guard
if (basename(\$_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// 🗄️ Database connection credentials
if (!defined('DB_HOST'))
{
    define('DB_HOST', {$host});
}

if (!defined('DB_USER'))
{
    define('DB_USER', {$user});
}

if (!defined('DB_PASS'))
{
    define('DB_PASS', {$pass});
}

if (!defined('DB_NAME'))
{
    define('DB_NAME', {$name});
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', {$charset});
}

if (!defined('DB_PORT'))
{
    define('DB_PORT', {$port});
}

// 🔑 Encryption salt & keys (generated; AES-256-GCM via _functions/security.php)
if (!defined('ENCRYPTION_SALT'))
{
    define('ENCRYPTION_SALT', {$saltLit});
}

if (!defined('ENCRYPTION_KEY_SECONDARY'))
{
    define('ENCRYPTION_KEY_SECONDARY', {$keyLit});
}

// 🛡️ Trusted reverse proxies (OPTIONAL — leave undefined on Dreamhost)
// g2ml_getClientIP() returns REMOTE_ADDR and IGNORES X-Forwarded-For /
// X-Real-Ip unless the immediate peer (REMOTE_ADDR) is listed here. Define
// this ONLY if the app sits behind a reverse proxy / load balancer you
// control; supply a comma-separated list of that proxy's IPs and/or CIDR
// ranges (e.g. '10.0.0.0/8, 192.168.1.5'). Undefined or empty => trust none
// => forged headers cannot poison the activity log or evade per-IP limits.
// if (!defined('TRUSTED_PROXIES'))
// {
//     define('TRUSTED_PROXIES', '');
// }

// 🔐 Third-party service credentials (fallback; primary storage is tblSettings)
// Populate these from the Admin → Settings UI, or define them here if needed.
if (!defined('MS365_CLIENT_ID'))
{
    define('MS365_CLIENT_ID', '');
}

if (!defined('MS365_CLIENT_SECRET'))
{
    define('MS365_CLIENT_SECRET', '');
}

if (!defined('GOOGLE_CLIENT_ID'))
{
    define('GOOGLE_CLIENT_ID', '');
}

if (!defined('GOOGLE_CLIENT_SECRET'))
{
    define('GOOGLE_CLIENT_SECRET', '');
}

if (!defined('RECAPTCHA_SITE_KEY'))
{
    define('RECAPTCHA_SITE_KEY', '');
}

if (!defined('RECAPTCHA_SECRET_KEY'))
{
    define('RECAPTCHA_SECRET_KEY', '');
}

if (!defined('TURNSTILE_SITE_KEY'))
{
    define('TURNSTILE_SITE_KEY', '');
}

if (!defined('TURNSTILE_SECRET_KEY'))
{
    define('TURNSTILE_SECRET_KEY', '');
}
PHP;

    return $template . "\n";
}

/**
 * Build a per-component thin include that pulls in the shared creds file.
 */
function g2ml_install_render_component_include(string $componentName): string
{
    return <<<PHP
<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 */

/**
 * 🔐 {$componentName} — component credentials include.
 * Generated by the installer. Includes the shared server-wide auth_creds.php.
 * Define DB_* constants ABOVE the include to override per-component.
 */

if (basename(\$_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

\$serverWideAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '_auth_keys'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists(\$serverWideAuthPath))
{
    require_once \$serverWideAuthPath;
}
else
{
    error_log('[Go2My.Link] CRITICAL: Server-wide auth_creds.php not found at: ' . \$serverWideAuthPath);
}

PHP;
}

/**
 * Atomically write a file with restrictive (0600) permissions.
 */
function g2ml_install_write_file(string $path, string $contents): bool
{
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $contents, LOCK_EX) === false)
    {
        return false;
    }

    @chmod($tmp, 0600);

    if (rename($tmp, $path) === false)
    {
        @unlink($tmp);
        return false;
    }

    @chmod($path, 0600);

    return true;
}

/**
 * Create the system-wide GlobalAdmin user with a hashed password and the
 * 'globaladmin' account type. Returns ['ok' => bool, 'error' => string].
 */
function g2ml_install_create_admin(mysqli $db, array $admin, string $securityPath): array
{
    // Refuse if a GlobalAdmin already exists — prevents minting extra super-admins.
    $existing = $db->query("SELECT 1 FROM tblUsers WHERE role = 'GlobalAdmin' LIMIT 1");
    if ($existing !== false && $existing->num_rows > 0)
    {
        return ['ok' => false, 'error' => 'A GlobalAdmin account already exists. Refusing to create another.'];
    }

    // Hash the password with the project's canonical hasher when available.
    if (!function_exists('g2ml_hashPassword') && file_exists($securityPath))
    {
        require_once $securityPath;
    }

    if (function_exists('g2ml_hashPassword'))
    {
        $hash = g2ml_hashPassword($admin['password']);
    }
    elseif (defined('PASSWORD_ARGON2ID'))
    {
        $hash = password_hash($admin['password'], PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }
    else
    {
        $hash = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    $displayName = trim($admin['firstName'] . ' ' . $admin['lastName']);

    if ($displayName === '')
    {
        $displayName = $admin['username'];
    }

    $db->begin_transaction();

    $insertUser = $db->prepare(
        "INSERT INTO tblUsers
            (orgHandle, username, email, emailVerified, emailVerifiedAt,
             passwordHash, passwordChangedAt, firstName, lastName, displayName,
             role, isActive, locale, timezone)
         VALUES
            ('[default]', ?, ?, 1, NOW(), ?, NOW(), ?, ?, ?, 'GlobalAdmin', 1, 'en-GB', 'UTC')"
    );

    if ($insertUser === false)
    {
        $db->rollback();
        return ['ok' => false, 'error' => 'Prepare failed: ' . $db->error];
    }

    $insertUser->bind_param(
        'ssssss',
        $admin['username'],
        $admin['email'],
        $hash,
        $admin['firstName'],
        $admin['lastName'],
        $displayName
    );

    if ($insertUser->execute() === false)
    {
        $error = $insertUser->error;
        $insertUser->close();
        $db->rollback();
        return ['ok' => false, 'error' => 'Could not create the user (is the username/email already taken?): ' . $error];
    }

    $newUserUID = $db->insert_id;
    $insertUser->close();

    // Assign the 'globaladmin' account type within the [default] org.
    $insertType = $db->prepare(
        "INSERT INTO tblUserAccountTypes
            (userUID, orgHandle, accountTypeID, grantedByUserUID, grantedAt, expiresAt, isActive)
         VALUES (?, '[default]', 'globaladmin', NULL, NOW(), NULL, 1)"
    );

    if ($insertType === false)
    {
        $db->rollback();
        return ['ok' => false, 'error' => 'Prepare failed (account type): ' . $db->error];
    }

    $insertType->bind_param('i', $newUserUID);

    if ($insertType->execute() === false)
    {
        $error = $insertType->error;
        $insertType->close();
        $db->rollback();
        return ['ok' => false, 'error' => 'Could not assign the GlobalAdmin account type: ' . $error];
    }

    $insertType->close();
    $db->commit();

    return ['ok' => true, 'error' => ''];
}

// ============================================================================
// 🚦 Lock guard — refuse to run once installed
// ============================================================================

$alreadyInstalled = false;

if (file_exists($g2mlLockFile))
{
    $alreadyInstalled = true;
}

// ============================================================================
// 🧭 Determine current step & process actions
// ============================================================================

$step    = 0;
$errors  = [];
$notices = [];

if (!$alreadyInstalled)
{
    $requestedStep = $_POST['step'] ?? ($_GET['step'] ?? '0');
    $action        = $_POST['action'] ?? '';

    if ($action !== '')
    {
        if (!g2ml_install_csrf_ok())
        {
            $errors[] = 'Security token mismatch. Please restart the installer.';
            $step     = 0;
        }
        elseif (!g2ml_install_is_https())
        {
            $errors[] = 'Refusing to handle secrets over plain HTTP. Reload this page over HTTPS.';
            $step     = 0;
        }
        elseif ($action === 'verify_token')
        {
            // Proof-of-control: the operator must paste the token from the
            // server-side file web/_auth_keys/.install_token.
            if (g2ml_install_token_ok($g2mlTokenFile, (string) ($_POST['install_token'] ?? '')))
            {
                $_SESSION['g2ml_install_token_ok'] = true;
                $step = 1;
            }
            else
            {
                $errors[] = 'Installer token incorrect. Open web/_auth_keys/.install_token on the server and paste its exact contents.';
                $step     = 0;
            }
        }
        elseif (empty($_SESSION['g2ml_install_token_ok']))
        {
            $errors[] = 'Please enter the installer token first.';
            $step     = 0;
        }
        else
        {
            // ----------------------------------------------------------------
            // Action: test database connection (from step 1)
            // ----------------------------------------------------------------
            if ($action === 'test_db')
            {
                $creds = [
                    'host'    => trim((string) ($_POST['db_host'] ?? '')),
                    'port'    => (int) ($_POST['db_port'] ?? 3306),
                    'name'    => trim((string) ($_POST['db_name'] ?? '')),
                    'user'    => trim((string) ($_POST['db_user'] ?? '')),
                    'pass'    => (string) ($_POST['db_pass'] ?? ''),
                    'charset' => trim((string) ($_POST['db_charset'] ?? 'utf8mb4')),
                ];

                if ($creds['host'] === '' || $creds['name'] === '' || $creds['user'] === '')
                {
                    $errors[] = 'Host, database name and username are required.';
                    $step     = 1;
                }
                else
                {
                    $connection = g2ml_install_connect($creds);

                    if (is_string($connection))
                    {
                        $errors[] = $connection;
                        $step     = 1;
                    }
                    else
                    {
                        $_SESSION['g2ml_install_db'] = $creds;
                        $connection->close();
                        $notices[] = 'Database connection successful.';
                        $step      = 2;
                    }
                }
            }
            // ----------------------------------------------------------------
            // Action: import schema + procedures + seeds (from step 2)
            // ----------------------------------------------------------------
            elseif ($action === 'import')
            {
                if (empty($_SESSION['g2ml_install_db']))
                {
                    $errors[] = 'Database details were lost. Please re-enter them.';
                    $step     = 1;
                }
                else
                {
                    $connection = g2ml_install_connect($_SESSION['g2ml_install_db']);

                    if (is_string($connection))
                    {
                        $errors[] = $connection;
                        $step     = 1;
                    }
                    else
                    {
                        $importLog = [];
                        $importOk  = true;

                        $groups = [
                            'Schema'             => $g2mlSqlDir . DIRECTORY_SEPARATOR . 'schema',
                            'Stored procedures'  => $g2mlSqlDir . DIRECTORY_SEPARATOR . 'procedures',
                            'Seed data'          => $g2mlSqlDir . DIRECTORY_SEPARATOR . 'seeds',
                        ];

                        foreach ($groups as $groupLabel => $dir)
                        {
                            $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');

                            if ($files === false)
                            {
                                $files = [];
                            }

                            sort($files);

                            foreach ($files as $file)
                            {
                                if (!$importOk)
                                {
                                    break;
                                }

                                $result = g2ml_install_run_sql_file($connection, $file);
                                $entry  = [
                                    'group' => $groupLabel,
                                    'file'  => basename($file),
                                    'ok'    => $result['ok'],
                                    'count' => $result['count'],
                                ];

                                if (!$result['ok'])
                                {
                                    $entry['error']     = $result['error'] ?? 'Unknown error';
                                    $entry['statement'] = $result['statement'] ?? '';
                                    $importOk           = false;
                                }

                                $importLog[] = $entry;
                            }
                        }

                        $connection->close();
                        $_SESSION['g2ml_install_import_log'] = $importLog;

                        if ($importOk)
                        {
                            $_SESSION['g2ml_install_imported'] = true;
                            $notices[] = 'Database import completed successfully.';
                            $step      = 3;
                        }
                        else
                        {
                            $errors[] = 'Import halted on an error (see the log below). Fix it and retry.';
                            $step     = 2;
                        }
                    }
                }
            }
            // ----------------------------------------------------------------
            // Action: create the GlobalAdmin (from step 3)
            // ----------------------------------------------------------------
            elseif ($action === 'create_admin')
            {
                $admin = [
                    'username'  => trim((string) ($_POST['admin_username'] ?? '')),
                    'email'     => trim((string) ($_POST['admin_email'] ?? '')),
                    'password'  => (string) ($_POST['admin_password'] ?? ''),
                    'confirm'   => (string) ($_POST['admin_password_confirm'] ?? ''),
                    'firstName' => trim((string) ($_POST['admin_first_name'] ?? '')),
                    'lastName'  => trim((string) ($_POST['admin_last_name'] ?? '')),
                ];

                if ($admin['username'] === '' || $admin['email'] === '' || $admin['password'] === '')
                {
                    $errors[] = 'Username, email and password are required.';
                }

                if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $admin['username']) !== 1)
                {
                    $errors[] = 'Username must be 3–50 characters (letters, numbers, dot, underscore, hyphen).';
                }

                if (filter_var($admin['email'], FILTER_VALIDATE_EMAIL) === false)
                {
                    $errors[] = 'Please enter a valid email address.';
                }

                if (strlen($admin['password']) < 12)
                {
                    $errors[] = 'Password must be at least 12 characters.';
                }

                if ($admin['password'] !== $admin['confirm'])
                {
                    $errors[] = 'The two passwords do not match.';
                }

                if (empty($_SESSION['g2ml_install_db']))
                {
                    $errors[] = 'Database details were lost. Please re-enter them.';
                }

                if (count($errors) === 0)
                {
                    $connection = g2ml_install_connect($_SESSION['g2ml_install_db']);

                    if (is_string($connection))
                    {
                        $errors[] = $connection;
                        $step     = 3;
                    }
                    else
                    {
                        $created = g2ml_install_create_admin($connection, $admin, $g2mlSecurity);
                        $connection->close();

                        if ($created['ok'])
                        {
                            $_SESSION['g2ml_install_admin_done'] = true;
                            $notices[] = 'GlobalAdmin account created.';
                            $step      = 4;
                        }
                        else
                        {
                            $errors[] = $created['error'];
                            $step     = 3;
                        }
                    }
                }
                else
                {
                    $step = 3;
                }
            }
            // ----------------------------------------------------------------
            // Action: finalise — write creds, ensure includes, write lock
            // ----------------------------------------------------------------
            elseif ($action === 'finalise')
            {
                if (empty($_SESSION['g2ml_install_db']) || empty($_SESSION['g2ml_install_admin_done']))
                {
                    $errors[] = 'Earlier steps are incomplete. Please start again.';
                    $step     = 0;
                }
                elseif (!is_dir($g2mlAuthDir) || !is_writable($g2mlAuthDir))
                {
                    $errors[] = 'The credentials directory (web/_auth_keys) is not writable. Fix permissions and retry.';
                    $step     = 4;
                }
                else
                {
                    $salt      = g2ml_install_generate_key();
                    $secondary = g2ml_install_generate_key();
                    $credsBody = g2ml_install_render_creds($_SESSION['g2ml_install_db'], $salt, $secondary);

                    if (!g2ml_install_write_file($g2mlCredsFile, $credsBody))
                    {
                        $errors[] = 'Failed to write the credentials file.';
                        $step     = 4;
                    }
                    else
                    {
                        // Defence-in-depth: drop a deny-all guard into _auth_keys/
                        // in case the directory is ever mis-mapped into a web root.
                        $authHtaccess = $g2mlAuthDir . DIRECTORY_SEPARATOR . '.htaccess';
                        if (!file_exists($authHtaccess))
                        {
                            @file_put_contents($authHtaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
                        }

                        $authIndex = $g2mlAuthDir . DIRECTORY_SEPARATOR . 'index.php';
                        if (!file_exists($authIndex))
                        {
                            @file_put_contents($authIndex, "<?php\nheader('Location: https://go2my.link');\nexit;\n");
                        }

                        // Verify the credentials file is not group/other-readable.
                        clearstatcache();
                        $credsMode = @fileperms($g2mlCredsFile);
                        if ($credsMode !== false && ($credsMode & 0077) !== 0)
                        {
                            $notices[] = 'Credentials written, but the file is group/other-readable — tighten it now (chmod 600 web/_auth_keys/auth_creds.php).';
                        }

                        // Ensure each component has its thin include (create only if missing).
                        $componentResults = [];

                        foreach ($g2mlComponents as $componentName => $componentDir)
                        {
                            $includeDir  = $componentDir . DIRECTORY_SEPARATOR . '_auth_keys';
                            $includeFile = $includeDir . DIRECTORY_SEPARATOR . 'auth_creds.php';

                            if (file_exists($includeFile))
                            {
                                $componentResults[$componentName] = 'present';
                            }
                            elseif (is_dir($includeDir) && is_writable($includeDir))
                            {
                                $body = g2ml_install_render_component_include($componentName);

                                if (g2ml_install_write_file($includeFile, $body))
                                {
                                    $componentResults[$componentName] = 'created';
                                }
                                else
                                {
                                    $componentResults[$componentName] = 'write-failed';
                                }
                            }
                            else
                            {
                                $componentResults[$componentName] = 'missing-dir';
                            }
                        }

                        // Write the lock file to disable the installer.
                        @file_put_contents($g2mlLockFile, 'Installed ' . gmdate('Y-m-d H:i:s') . " UTC\n");
                        @chmod($g2mlLockFile, 0600);

                        // Clear secrets from the session.
                        unset($_SESSION['g2ml_install_db']);

                        $_SESSION['g2ml_install_components'] = $componentResults;
                        $alreadyInstalled                    = true;
                        $step                                = 5;
                    }
                }
            }
        }
    }
    else
    {
        $step = (int) $requestedStep;

        if ($step < 0 || $step > 4)
        {
            $step = 0;
        }

        // Do not show later-step forms until the installer token is verified.
        if (empty($_SESSION['g2ml_install_token_ok']) && $step > 0)
        {
            $step = 0;
        }
    }
}

// ============================================================================
// 🌍 Environment checks (step 0)
// ============================================================================

function g2ml_install_requirements(string $authDir, string $sqlDir): array
{
    $checks = [];

    $phpOk    = version_compare(PHP_VERSION, '8.4.0', '>=');
    $checks[] = ['label' => 'PHP 8.4 or newer (found ' . PHP_VERSION . ')', 'ok' => $phpOk];
    $checks[] = ['label' => 'mysqli extension', 'ok' => extension_loaded('mysqli')];
    $checks[] = ['label' => 'openssl extension', 'ok' => extension_loaded('openssl')];
    $checks[] = ['label' => 'mbstring extension', 'ok' => extension_loaded('mbstring')];
    $checks[] = ['label' => 'Argon2id password hashing', 'ok' => defined('PASSWORD_ARGON2ID')];
    $checks[] = ['label' => 'Credentials dir writable (web/_auth_keys)', 'ok' => is_dir($authDir) && is_writable($authDir)];
    $checks[] = ['label' => 'SQL directory readable (web/_sql)', 'ok' => is_dir($sqlDir) && is_readable($sqlDir)];

    return $checks;
}

// ============================================================================
// 🎨 Output
// ============================================================================

$csrf      = g2ml_install_csrf_token();

// Ensure the proof-of-control token file exists so the operator can read it off
// disk before entering it (created only while the installer is unlocked).
if (!$alreadyInstalled)
{
    g2ml_install_token_value($g2mlTokenFile);
}

$dbDefaults = $_SESSION['g2ml_install_db'] ?? [
    'host'    => 'localhost',
    'port'    => 3306,
    'name'    => 'mwtools_Go2MyLink',
    'user'    => '',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en-GB" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Go2My.Link — Installer</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f4f6fb; --card: #ffffff; --text: #1c2333; --muted: #56607a;
            --border: #d8dee9; --brand: #2f6fed; --ok: #1f7a44; --err: #b3261e;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #11151f; --card: #1b2130; --text: #e8ecf5; --muted: #9aa4ba;
                --border: #313a52; --brand: #6ea0ff; --ok: #4cc587; --err: #ff6b61;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 2rem 1rem; background: var(--bg); color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
        }
        .wrap { max-width: 760px; margin: 0 auto; }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 12px;
            padding: 1.75rem; box-shadow: 0 6px 24px rgba(0,0,0,0.06);
        }
        h1 { font-size: 1.5rem; margin: 0 0 0.25rem; }
        h2 { font-size: 1.15rem; margin: 0 0 1rem; }
        p.lead { color: var(--muted); margin-top: 0; }
        .steps { display: flex; gap: 0.5rem; margin: 0 0 1.5rem; padding: 0; list-style: none; flex-wrap: wrap; }
        .steps li {
            font-size: 0.78rem; padding: 0.3rem 0.6rem; border-radius: 999px;
            border: 1px solid var(--border); color: var(--muted);
        }
        .steps li.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .steps li.done { border-color: var(--ok); color: var(--ok); }
        label { display: block; font-weight: 600; margin: 0.9rem 0 0.3rem; font-size: 0.92rem; }
        input[type=text], input[type=number], input[type=email], input[type=password] {
            width: 100%; padding: 0.6rem 0.7rem; border: 1px solid var(--border);
            border-radius: 8px; background: var(--bg); color: var(--text); font-size: 0.95rem;
        }
        .hint { color: var(--muted); font-size: 0.82rem; margin: 0.25rem 0 0; }
        .row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .row > div { flex: 1; min-width: 200px; }
        button {
            margin-top: 1.5rem; background: var(--brand); color: #fff; border: 0;
            padding: 0.7rem 1.3rem; border-radius: 8px; font-size: 1rem; cursor: pointer;
        }
        button:hover { filter: brightness(1.07); }
        .alert { border-radius: 8px; padding: 0.75rem 1rem; margin: 0 0 1rem; font-size: 0.92rem; }
        .alert-err { background: rgba(179,38,30,0.12); border: 1px solid var(--err); color: var(--err); }
        .alert-ok { background: rgba(31,122,68,0.12); border: 1px solid var(--ok); color: var(--ok); }
        ul.checks { list-style: none; padding: 0; margin: 0 0 1rem; }
        ul.checks li { padding: 0.4rem 0; border-bottom: 1px solid var(--border); }
        .badge-ok { color: var(--ok); font-weight: 700; }
        .badge-no { color: var(--err); font-weight: 700; }
        table.log { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem; }
        table.log th, table.log td { text-align: left; padding: 0.35rem 0.5rem; border-bottom: 1px solid var(--border); }
        code { background: var(--bg); padding: 0.1rem 0.35rem; border-radius: 5px; font-size: 0.85em; }
        .warn-box { border: 1px solid var(--err); border-radius: 8px; padding: 1rem; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>🧰 Go2My.Link Installer</h1>
        <p class="lead">Sets up the database and credentials shared by all three components — Go2My.Link, G2My.Link and Lnks.page.</p>

        <?php if (!$alreadyInstalled || $step === 5) { ?>
        <ul class="steps">
            <?php
            $stepLabels = ['Requirements', 'Database', 'Import', 'Admin', 'Finish'];
            foreach ($stepLabels as $index => $stepLabel)
            {
                $class = '';
                if ($index === $step)
                {
                    $class = 'active';
                }
                elseif ($index < $step)
                {
                    $class = 'done';
                }
                echo '<li class="' . $class . '">' . ($index + 1) . '. ' . g2ml_install_e($stepLabel) . '</li>';
            }
            ?>
        </ul>
        <?php } ?>

        <?php foreach ($errors as $error) { ?>
        <div class="alert alert-err">⚠️ <?php echo g2ml_install_e($error); ?></div>
        <?php } ?>

        <?php foreach ($notices as $notice) { ?>
        <div class="alert alert-ok">✅ <?php echo g2ml_install_e($notice); ?></div>
        <?php } ?>

        <?php
        // ====================================================================
        // Already installed (and not the just-finished screen)
        // ====================================================================
        if ($alreadyInstalled && $step !== 5)
        {
        ?>
        <h2>✅ Already installed</h2>
        <p>This installation has already been completed (a lock file exists at <code>web/_auth_keys/.installed</code>).</p>
        <div class="warn-box">
            <strong>🔒 Security:</strong> delete the entire <code>install/</code> directory now. To re-run the installer
            you must first remove the lock file <code>web/_auth_keys/.installed</code> — only do this on a machine you control.
        </div>
        <?php
        }
        // ====================================================================
        // Step 0 — requirements
        // ====================================================================
        elseif ($step === 0)
        {
            $checks   = g2ml_install_requirements($g2mlAuthDir, $g2mlSqlDir);
            $allReady = true;

            foreach ($checks as $check)
            {
                if (!$check['ok'])
                {
                    $allReady = false;
                }
            }
        ?>
        <h2>1. Requirements</h2>
        <ul class="checks">
            <?php foreach ($checks as $check) { ?>
            <li>
                <?php if ($check['ok']) { ?>
                    <span class="badge-ok">✔</span>
                <?php } else { ?>
                    <span class="badge-no">✘</span>
                <?php } ?>
                <?php echo g2ml_install_e($check['label']); ?>
            </li>
            <?php } ?>
        </ul>
        <?php if ($allReady) { ?>
        <p class="hint">🔑 To prove you control this server, open the file
            <code>web/_auth_keys/.install_token</code> on the server and paste its contents below.</p>
        <form method="POST" action="?">
            <input type="hidden" name="_csrf" value="<?php echo g2ml_install_e($csrf); ?>">
            <input type="hidden" name="action" value="verify_token">
            <label for="install_token">Installer token</label>
            <input type="text" id="install_token" name="install_token" value="" autocomplete="off" spellcheck="false">
            <button type="submit">Verify &amp; continue →</button>
        </form>
        <?php } else { ?>
        <div class="warn-box">Some requirements are not met. Resolve the items marked ✘, then reload this page.</div>
        <?php } ?>
        <?php
        }
        // ====================================================================
        // Step 1 — database credentials
        // ====================================================================
        elseif ($step === 1)
        {
        ?>
        <h2>2. Database connection</h2>
        <p class="lead">Enter the details of an existing, empty MySQL database (create it first in your hosting panel).</p>
        <form method="POST" action="?">
            <input type="hidden" name="_csrf" value="<?php echo g2ml_install_e($csrf); ?>">
            <input type="hidden" name="step" value="1">
            <input type="hidden" name="action" value="test_db">
            <div class="row">
                <div>
                    <label for="db_host">Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo g2ml_install_e((string) $dbDefaults['host']); ?>" autocomplete="off">
                    <p class="hint">On Dreamhost, typically <code>mysql.yourdomain.com</code>.</p>
                </div>
                <div>
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port" value="<?php echo g2ml_install_e((string) $dbDefaults['port']); ?>">
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="db_name">Database name</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo g2ml_install_e((string) $dbDefaults['name']); ?>" autocomplete="off">
                </div>
                <div>
                    <label for="db_charset">Charset</label>
                    <input type="text" id="db_charset" name="db_charset" value="<?php echo g2ml_install_e((string) $dbDefaults['charset']); ?>">
                </div>
            </div>
            <label for="db_user">Username</label>
            <input type="text" id="db_user" name="db_user" value="<?php echo g2ml_install_e((string) $dbDefaults['user']); ?>" autocomplete="off">
            <label for="db_pass">Password</label>
            <input type="password" id="db_pass" name="db_pass" value="" autocomplete="new-password">
            <p class="hint">The password is never shown back to you and is only stored in your server-side session until setup finishes.</p>
            <button type="submit">Test connection &amp; continue →</button>
        </form>
        <?php
        }
        // ====================================================================
        // Step 2 — import
        // ====================================================================
        elseif ($step === 2)
        {
            $importLog = $_SESSION['g2ml_install_import_log'] ?? [];
        ?>
        <h2>3. Import database</h2>
        <p class="lead">This imports all schema tables, stored procedures and seed data into
            <code><?php echo g2ml_install_e((string) ($_SESSION['g2ml_install_db']['name'] ?? '')); ?></code>.
            Existing tables are preserved (<code>CREATE TABLE IF NOT EXISTS</code>).</p>

        <?php if (count($importLog) > 0) { ?>
        <table class="log">
            <thead><tr><th>Group</th><th>File</th><th>Statements</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($importLog as $entry) { ?>
                <tr>
                    <td><?php echo g2ml_install_e((string) $entry['group']); ?></td>
                    <td><code><?php echo g2ml_install_e((string) $entry['file']); ?></code></td>
                    <td><?php echo (int) $entry['count']; ?></td>
                    <td>
                        <?php if ($entry['ok']) { ?>
                            <span class="badge-ok">✔ OK</span>
                        <?php } else { ?>
                            <span class="badge-no">✘ <?php echo g2ml_install_e((string) ($entry['error'] ?? '')); ?></span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php } ?>

        <form method="POST" action="?">
            <input type="hidden" name="_csrf" value="<?php echo g2ml_install_e($csrf); ?>">
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="action" value="import">
            <button type="submit">Run import →</button>
        </form>
        <?php
        }
        // ====================================================================
        // Step 3 — GlobalAdmin
        // ====================================================================
        elseif ($step === 3)
        {
        ?>
        <h2>4. Create the GlobalAdmin</h2>
        <p class="lead">This is the system-wide super-administrator account.</p>
        <form method="POST" action="?">
            <input type="hidden" name="_csrf" value="<?php echo g2ml_install_e($csrf); ?>">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="action" value="create_admin">
            <div class="row">
                <div>
                    <label for="admin_first_name">First name</label>
                    <input type="text" id="admin_first_name" name="admin_first_name" value="">
                </div>
                <div>
                    <label for="admin_last_name">Last name</label>
                    <input type="text" id="admin_last_name" name="admin_last_name" value="">
                </div>
            </div>
            <label for="admin_username">Username</label>
            <input type="text" id="admin_username" name="admin_username" value="" autocomplete="off">
            <p class="hint">3–50 characters: letters, numbers, dot, underscore or hyphen.</p>
            <label for="admin_email">Email</label>
            <input type="email" id="admin_email" name="admin_email" value="" autocomplete="off">
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password" value="" autocomplete="new-password">
            <p class="hint">At least 12 characters.</p>
            <label for="admin_password_confirm">Confirm password</label>
            <input type="password" id="admin_password_confirm" name="admin_password_confirm" value="" autocomplete="new-password">
            <button type="submit">Create administrator →</button>
        </form>
        <?php
        }
        // ====================================================================
        // Step 4 — finalise
        // ====================================================================
        elseif ($step === 4)
        {
        ?>
        <h2>5. Finish</h2>
        <p class="lead">The final step generates fresh encryption keys, writes the shared credentials file
            (<code>web/_auth_keys/auth_creds.php</code>), ensures each component includes it, and locks the installer.</p>
        <form method="POST" action="?">
            <input type="hidden" name="_csrf" value="<?php echo g2ml_install_e($csrf); ?>">
            <input type="hidden" name="step" value="4">
            <input type="hidden" name="action" value="finalise">
            <button type="submit">Write configuration &amp; finish →</button>
        </form>
        <?php
        }
        // ====================================================================
        // Step 5 — done
        // ====================================================================
        elseif ($step === 5)
        {
            $componentResults = $_SESSION['g2ml_install_components'] ?? [];
        ?>
        <h2>🎉 Installation complete</h2>
        <p>The database is set up, the GlobalAdmin account is created, and the shared credentials file has been written.</p>
        <ul class="checks">
            <?php foreach ($componentResults as $componentName => $state) { ?>
            <li>
                <?php if ($state === 'present' || $state === 'created') { ?>
                    <span class="badge-ok">✔</span> <?php echo g2ml_install_e($componentName); ?> credentials include
                    (<?php echo g2ml_install_e($state); ?>)
                <?php } else { ?>
                    <span class="badge-no">✘</span> <?php echo g2ml_install_e($componentName); ?> include
                    (<?php echo g2ml_install_e($state); ?>) — create it manually
                <?php } ?>
            </li>
            <?php } ?>
        </ul>
        <div class="warn-box">
            <strong>🔒 Do this now:</strong>
            <ol>
                <li><strong>Delete the entire <code>install/</code> directory</strong> from the web root.</li>
                <li>Confirm <code>web/_auth_keys/auth_creds.php</code> is outside the web root and not world-readable.</li>
                <li>Sign in to the admin dashboard with your new GlobalAdmin account.</li>
            </ol>
        </div>
        <?php
        }
        ?>
    </div>
</div>
</body>
</html>
