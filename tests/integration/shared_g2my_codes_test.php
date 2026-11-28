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
 * 🧪 Integration tests — organisation links share the g2my.link codes (#289)
 * ============================================================================
 *
 * Owner decision D-003 (docs/decisions/D-003-org-links-share-g2my-codes.md):
 * a member of an organisation with no short web address of its own gets
 * g2my.link links that work like anybody else's; each code on g2my.link is
 * used once across everybody; the organisation still sees the link and its
 * statistics. An organisation with its own verified short domain keeps its
 * own set of codes there.
 *
 * Before #289 a link made by such a member was stored under the member's
 * organisation and handed back as g2my.link/CODE, but the g2my.link lookup
 * only searched the default organisation's links, so the address never
 * opened. Every test below fails on the code before #289 (most of them
 * because tblShortURLs had no sharedShortCode column at all, the migration
 * file did not exist, or the lookup returned "not found").
 *
 * What is covered:
 *   1. creating (random code and custom alias) and following an
 *      organisation member's g2my.link link, and who it is counted for;
 *   2. each code used once on g2my.link, both ways round, and through the
 *      API's create path (the same createShortURL());
 *   3. an organisation with its own verified domain keeps its own codes;
 *   4. statistics: the click is logged for the owning organisation;
 *   5. alias hops never jump into another organisation's link, and a link
 *      that does not open goes to its owner's fallback address;
 *   6. the public info page finds the same link the address opens;
 *   7. two creations of the same code at once — the second waits, then
 *      fails, so the two can never both succeed;
 *   8. migration 044 on an old-shaped copy of the table, including two
 *      organisations that already hold the same code, and a second run.
 *
 * Helper names start g2ml_sgc_ so they cannot clash with other test files.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #289
 * ============================================================================
 */

declare(strict_types=1);

// $db and the connection settings ($socket, $host, $port, $database, $user,
// $password) come from tests/run_integration.php, which includes this file
// at its top level.
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application database layer (getDB) at the same test server.
// Guarded, so it works whichever test file ran first.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    define('DB_HOST', $host);
}

if (!defined('DB_PORT'))
{
    define('DB_PORT', $port);
}

if (!defined('DB_USER'))
{
    define('DB_USER', $user);
}

if (!defined('DB_PASS'))
{
    define('DB_PASS', $password);
}

if (!defined('DB_NAME'))
{
    define('DB_NAME', $database);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

$g2mlSgcFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlSgcFunctionsDir . 'db_connect.php';
require_once $g2mlSgcFunctionsDir . 'db_query.php';
require_once $g2mlSgcFunctionsDir . 'security.php';
require_once $g2mlSgcFunctionsDir . 'settings.php';
require_once $g2mlSgcFunctionsDir . 'activity_logger.php';
require_once $g2mlSgcFunctionsDir . 'analytics.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

// The settings for a second and third connection (the race test).
$g2mlSgcConnection = [
    'socket'   => $socket,
    'host'     => $host,
    'port'     => $port,
    'database' => $database,
    'user'     => $user,
    'password' => $password,
];

/**
 * Run a setup query, stopping loudly if the database refuses it.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_sgc_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if ($result instanceof mysqli_result)
    {
        mysqli_free_result($result);
    }
}

/**
 * Run a prepared statement that returns no rows.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @param  string $types
 * @param  array  $params
 * @return void
 */
function g2ml_sgc_prepared(mysqli $db, string $sql, string $types, array $params): void
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if ($types !== '')
    {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    $ok = mysqli_stmt_execute($statement);
    $error = mysqli_stmt_error($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Statement failed: ' . $error . ' — SQL: ' . $sql);
    }
}

/**
 * Read rows with a prepared statement.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @param  string $types
 * @param  array  $params
 * @return array<int, array<string, mixed>>
 */
function g2ml_sgc_rows(mysqli $db, string $sql, string $types, array $params): array
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }

    if ($types !== '')
    {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }

    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $rows   = [];

    if ($result !== false)
    {
        while (true)
        {
            $row = mysqli_fetch_assoc($result);

            if ($row === null || $row === false)
            {
                break;
            }

            $rows[] = $row;
        }

        mysqli_free_result($result);
    }

    mysqli_stmt_close($statement);

    return $rows;
}

/**
 * Delete every test link whose code starts with 'sgc', in every organisation.
 *
 * @param  mysqli $db
 * @return void
 */
function g2ml_sgc_clean(mysqli $db): void
{
    g2ml_sgc_exec($db, "DELETE FROM `tblShortURLs` WHERE `shortCode` LIKE 'sgc%'");
    g2ml_sgc_exec($db, "DELETE FROM `tblActivityLog` WHERE `shortCode` LIKE 'sgc%'");
}

/**
 * Insert a link row directly, the way an old import or a test seed would.
 *
 * @param  mysqli      $db
 * @param  string      $orgHandle
 * @param  string      $shortCode
 * @param  string|null $sharedShortCode
 * @param  string|null $destinationURL
 * @param  int         $isActive
 * @param  string|null $redirectAlias
 * @return void
 */
function g2ml_sgc_seed(mysqli $db, string $orgHandle, string $shortCode, ?string $sharedShortCode, ?string $destinationURL, int $isActive, ?string $redirectAlias = null): void
{
    $destinationType = 'url';

    if ($redirectAlias !== null)
    {
        $destinationType = 'alias';
    }

    g2ml_sgc_prepared(
        $db,
        'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `destinationType`, `redirectAlias`, `isActive`, `createdAt`, `updatedAt`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
        'ssssssi',
        [$orgHandle, $shortCode, $sharedShortCode, $destinationURL, $destinationType, $redirectAlias, $isActive]
    );
}

/**
 * Read one link row by organisation and code, or null.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $shortCode
 * @return array<string, mixed>|null
 */
function g2ml_sgc_link(mysqli $db, string $orgHandle, string $shortCode): ?array
{
    $rows = g2ml_sgc_rows(
        $db,
        'SELECT `urlUID`, `orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `clickCount` '
        . 'FROM `tblShortURLs` WHERE `orgHandle` = ? AND `shortCode` = ? LIMIT 1',
        'ss',
        [$orgHandle, $shortCode]
    );

    if (count($rows) === 0)
    {
        return null;
    }

    return $rows[0];
}

/**
 * Count the rows holding a code on g2my.link.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return int
 */
function g2ml_sgc_shared_count(mysqli $db, string $shortCode): int
{
    $rows = g2ml_sgc_rows(
        $db,
        'SELECT COUNT(*) AS `total` FROM `tblShortURLs` WHERE `sharedShortCode` = ?',
        's',
        [$shortCode]
    );

    return (int) $rows[0]['total'];
}

/**
 * Run a query that may be refused, and return the database's error number
 * (0 when it succeeded). Works whether mysqli reports errors by returning
 * false or by throwing — an earlier test file may have switched it to
 * throwing.
 *
 * @param  mysqli $connection
 * @param  string $sql
 * @return int
 */
function g2ml_sgc_errno_of(mysqli $connection, string $sql): int
{
    try
    {
        $result = mysqli_query($connection, $sql);
    }
    catch (mysqli_sql_exception $refused)
    {
        return (int) $refused->getCode();
    }

    if ($result === false)
    {
        return mysqli_errno($connection);
    }

    if ($result instanceof mysqli_result)
    {
        mysqli_free_result($result);
    }

    return 0;
}

/**
 * Collect the answer to a query sent with MYSQLI_ASYNC, and return the
 * database's error number (0 when it succeeded).
 *
 * @param  mysqli $connection
 * @return int
 */
function g2ml_sgc_reap_errno(mysqli $connection): int
{
    try
    {
        $result = mysqli_reap_async_query($connection);
    }
    catch (mysqli_sql_exception $refused)
    {
        return (int) $refused->getCode();
    }

    if ($result === false)
    {
        return mysqli_errno($connection);
    }

    if ($result instanceof mysqli_result)
    {
        mysqli_free_result($result);
    }

    return 0;
}

/**
 * Open another connection to the test database (for the race test).
 *
 * @param  array $settings  $g2mlSgcConnection
 * @return mysqli
 */
function g2ml_sgc_connect(array $settings): mysqli
{
    $connection = g2ml_test_connect(
        $settings['socket'],
        $settings['host'],
        (int) $settings['port'],
        $settings['database'],
        $settings['user'],
        $settings['password']
    );

    if ($connection === null)
    {
        throw new RuntimeException('Could not open a second connection to the test database.');
    }

    mysqli_set_charset($connection, 'utf8mb4');

    return $connection;
}

// ----------------------------------------------------------------------------
// Shared setup. Organisations:
//   '[default]'   the default organisation (g2my.link)
//   'sgcorga'     no short domain of its own
//   'sgcorgb'     no short domain of its own
//   'sgcown'      its own verified, active, default domain sgc-own.test
//   'sgcnofb'     no short domain and no fallback address of its own
// ----------------------------------------------------------------------------
g2ml_sgc_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_sgc_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) VALUES "
    . "('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1), "
    . "('sgcorga', 'Shared codes org A', 'https://example.com/sgcorga-fallback', 'free', 1), "
    . "('sgcorgb', 'Shared codes org B', 'https://example.com/sgcorgb-fallback', 'free', 1), "
    . "('sgcown', 'Shared codes own-domain org', 'https://example.com/sgcown-fallback', 'free', 1), "
    . "('sgcnofb', 'Shared codes org without fallback', NULL, 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `isActive` = 1"
);

g2ml_sgc_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`, `verifiedAt`) VALUES "
    . "('[default]', 'g2my.link', 1, 1, 'verified', NOW()), "
    . "('sgcown', 'sgc-own.test', 1, 1, 'verified', NOW()) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1, `verificationStatus` = 'verified', `isDefault` = 1"
);

// A claimed but unverified domain for org B: the info page must find nothing
// through it, as following it would.
g2ml_sgc_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`) VALUES "
    . "('sgcorgb', 'sgc-unverified.test', 0, 0, 'pending') "
    . "ON DUPLICATE KEY UPDATE `isActive` = 0, `verificationStatus` = 'pending'"
);

g2ml_sgc_clean($db);

// ============================================================================
// 1. An organisation member's g2my.link link opens, and is counted for them
// ============================================================================
test('#289: an organisation member without a short domain gets a g2my.link link that opens', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $created = createShortURL('https://example.com/sgc-member-link', [
        'orgHandle'  => 'sgcorga',
        'userUID'    => null,
        'customCode' => 'sgcmember1',
    ]);

    assert_true($created['success'], 'The link is created');
    assert_same('https://g2my.link/sgcmember1', $created['shortURL'], 'The address handed back is on g2my.link');

    $row = g2ml_sgc_link($db, 'sgcorga', 'sgcmember1');
    assert_true($row !== null, 'The row belongs to the member\'s organisation');
    assert_same('sgcmember1', $row['sharedShortCode'], 'The row holds its code among the shared g2my.link codes');

    $resolved = resolveShortCode('g2my.link', 'sgcmember1');
    assert_same('success', $resolved['status'], 'Following g2my.link/sgcmember1 opens the link (it used to be not_found)');
    assert_same('https://example.com/sgc-member-link', $resolved['destination'], 'It goes to the member\'s destination');
    assert_same('sgcorga', $resolved['orgHandle'], 'The click is counted for the organisation that owns the link, not [default]');

    g2ml_sgc_clean($db);
});

test('#289: a random code for an organisation member opens on g2my.link too', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $created = createShortURL('https://example.com/sgc-member-random', [
        'orgHandle' => 'sgcorga',
        'userUID'   => null,
    ]);

    assert_true($created['success'], 'The link is created');
    $code = (string) $created['shortCode'];
    assert_same('https://g2my.link/' . $code, $created['shortURL'], 'The address handed back is on g2my.link');

    $row = g2ml_sgc_link($db, 'sgcorga', $code);
    assert_same($code, $row['sharedShortCode'], 'The random code is held among the shared codes');

    $resolved = resolveShortCode('g2my.link', $code);
    assert_same('success', $resolved['status'], 'The random-code address opens');
    assert_same('sgcorga', $resolved['orgHandle'], 'It is counted for the owning organisation');

    // Clean up the random code (it does not start with 'sgc').
    g2ml_sgc_prepared($db, 'DELETE FROM `tblShortURLs` WHERE `orgHandle` = ? AND `shortCode` = ?', 'ss', ['sgcorga', $code]);
    g2ml_sgc_clean($db);
});

// ============================================================================
// 2. Each code is used once on g2my.link, by everybody together
// ============================================================================
test('#289: a code an organisation member holds on g2my.link cannot be taken by another organisation or by [default]', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $first = createShortURL('https://example.com/sgc-once-a', [
        'orgHandle'  => 'sgcorga',
        'customCode' => 'sgconce',
    ]);
    assert_true($first['success'], 'Organisation A takes the code');

    $second = createShortURL('https://example.com/sgc-once-b', [
        'orgHandle'  => 'sgcorgb',
        'customCode' => 'sgconce',
    ]);
    assert_false($second['success'], 'Organisation B (also without a short domain) is refused the same code');
    assert_contains('already taken', (string) $second['error'], 'With the usual "already taken" message, which the API turns into 409');

    $third = createShortURL('https://example.com/sgc-once-default', [
        'orgHandle'  => '[default]',
        'customCode' => 'sgconce',
    ]);
    assert_false($third['success'], 'Somebody with no organisation is refused it too');

    assert_same(1, g2ml_sgc_shared_count($db, 'sgconce'), 'Exactly one link holds the code on g2my.link');
    assert_same('https://example.com/sgc-once-a', resolveShortCode('g2my.link', 'sgconce')['destination'], 'And g2my.link opens organisation A\'s link');

    g2ml_sgc_clean($db);
});

test('#289: a code the default organisation holds cannot be taken by an organisation member', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $first = createShortURL('https://example.com/sgc-default-first', [
        'orgHandle'  => '[default]',
        'customCode' => 'sgcdeffirst',
    ]);
    assert_true($first['success'], 'The default organisation takes the code');

    $second = createShortURL('https://example.com/sgc-member-second', [
        'orgHandle'  => 'sgcorga',
        'customCode' => 'sgcdeffirst',
        'createdVia' => 'api',
    ]);
    assert_false($second['success'], 'An organisation member (through the API path) is refused it');
    assert_contains('already taken', (string) $second['error'], 'As "already taken"');
    assert_true(g2ml_sgc_link($db, 'sgcorga', 'sgcdeffirst') === null, 'No row was written for the refused link');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 3. An organisation with its own verified domain keeps its own codes
// ============================================================================
test('#289: an organisation with its own verified domain keeps its own codes there', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $shared = createShortURL('https://example.com/sgc-shared-holder', [
        'orgHandle'  => 'sgcorga',
        'customCode' => 'sgcownsame',
    ]);
    assert_true($shared['success'], 'Organisation A holds the code on g2my.link');

    $own = createShortURL('https://example.com/sgc-own-domain', [
        'orgHandle'  => 'sgcown',
        'customCode' => 'sgcownsame',
    ]);
    assert_true($own['success'], 'The organisation with its own domain may use the same code on its own domain');
    assert_same('https://sgc-own.test/sgcownsame', $own['shortURL'], 'Its address is on its own domain');

    $row = g2ml_sgc_link($db, 'sgcown', 'sgcownsame');
    assert_true($row['sharedShortCode'] === null, 'Its link does not use up a g2my.link code');

    $onOwnDomain = resolveShortCode('sgc-own.test', 'sgcownsame');
    assert_same('success', $onOwnDomain['status'], 'Its own domain opens its own link');
    assert_same('https://example.com/sgc-own-domain', $onOwnDomain['destination'], 'With its own destination');
    assert_same('sgcown', $onOwnDomain['orgHandle'], 'Counted for it');

    $onShared = resolveShortCode('g2my.link', 'sgcownsame');
    assert_same('https://example.com/sgc-shared-holder', $onShared['destination'], 'g2my.link still opens organisation A\'s link');

    $onlyOwn = createShortURL('https://example.com/sgc-own-only', [
        'orgHandle'  => 'sgcown',
        'customCode' => 'sgcownonly',
    ]);
    assert_true($onlyOwn['success'], 'A code only the own-domain organisation uses');
    assert_same('not_found', resolveShortCode('g2my.link', 'sgcownonly')['status'], 'does not open on g2my.link');

    g2ml_sgc_clean($db);
});

test('#289: a g2my.link link made before an organisation had its own domain also opens on that domain', function () use ($db): void
{
    g2ml_sgc_clean($db);

    // Made while the organisation used the shared codes.
    g2ml_sgc_seed($db, 'sgcown', 'sgcbefore', 'sgcbefore', 'https://example.com/sgc-before-domain', 1);

    assert_same('success', resolveShortCode('g2my.link', 'sgcbefore')['status'], 'It still opens on g2my.link, the address people were given');
    assert_same('success', resolveShortCode('sgc-own.test', 'sgcbefore')['status'], 'And on the organisation\'s own domain');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 4. Statistics: the organisation sees the click
// ============================================================================
test('#289: a click on an organisation member\'s g2my.link link appears in the organisation\'s statistics', function () use ($db): void
{
    g2ml_sgc_clean($db);

    $created = createShortURL('https://example.com/sgc-stats', [
        'orgHandle'  => 'sgcorga',
        'customCode' => 'sgcstats',
    ]);
    assert_true($created['success'], 'The link is created');

    $resolved = resolveShortCode('g2my.link', 'sgcstats');
    assert_same('success', $resolved['status'], 'It opens');

    // Exactly what web/G2My.Link/public_html/index.php does with the result.
    logActivity('redirect', 'success', 302, [
        'orgHandle'      => $resolved['orgHandle'],
        'shortCode'      => 'sgcstats',
        'destinationURL' => $resolved['destination'],
    ]);

    $updated = dbUpdate(
        'UPDATE `tblShortURLs` SET `clickCount` = `clickCount` + 1, `lastClickAt` = NOW() WHERE `shortCode` = ? AND `orgHandle` = ?',
        'ss',
        ['sgcstats', $resolved['orgHandle']]
    );
    assert_same(1, $updated, 'The redirect page\'s click-count update finds exactly the organisation\'s row');
    assert_same(1, (int) g2ml_sgc_link($db, 'sgcorga', 'sgcstats')['clickCount'], 'Its click count went up');

    $from   = gmdate('Y-m-d H:i:s', time() - 172800);
    $to     = gmdate('Y-m-d H:i:s', time() + 172800);
    $totals = g2ml_analyticsTotals('sgcorga', 'sgcstats', $from, $to);
    assert_same(1, $totals['totalClicks'], 'The organisation\'s statistics show the click');

    $defaultTotals = g2ml_analyticsTotals('[default]', 'sgcstats', $from, $to);
    assert_same(0, $defaultTotals['totalClicks'], 'The default organisation does not get it');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 5. Alias hops and fallback addresses
// ============================================================================
test('#289: an alias on a shared link only follows the owner\'s own links', function () use ($db): void
{
    g2ml_sgc_clean($db);

    // [default] holds sgcaliast on g2my.link. Organisation A's link
    // sgcaliasa points at "sgcaliast", but A has no such link of its own.
    g2ml_sgc_seed($db, '[default]', 'sgcaliast', 'sgcaliast', 'https://example.com/sgc-default-target', 1);
    g2ml_sgc_seed($db, 'sgcorga', 'sgcaliasa', 'sgcaliasa', null, 1, 'sgcaliast');

    $jump = resolveShortCode('g2my.link', 'sgcaliasa');
    assert_same('not_found', $jump['status'], 'It does not jump into the default organisation\'s link');
    assert_same('sgcorga', $jump['orgHandle'], 'And reports the owner');

    // Now A has its own target: the alias reaches it.
    g2ml_sgc_seed($db, 'sgcorga', 'sgcaliast', null, 'https://example.com/sgc-own-target', 1);

    $own = resolveShortCode('g2my.link', 'sgcaliasa');
    assert_same('success', $own['status'], 'The alias reaches the owner\'s own target');
    assert_same('https://example.com/sgc-own-target', $own['destination'], 'Not the default organisation\'s');

    g2ml_sgc_clean($db);
});

test('#289: a disabled shared link sends visitors to its owner\'s fallback address, or the default one when it has none', function () use ($db): void
{
    g2ml_sgc_clean($db);

    g2ml_sgc_seed($db, 'sgcorga', 'sgcoff', 'sgcoff', 'https://example.com/sgc-off', 0);
    g2ml_sgc_seed($db, 'sgcnofb', 'sgcoffnofb', 'sgcoffnofb', 'https://example.com/sgc-off-nofb', 0);

    $withFallback = resolveShortCode('g2my.link', 'sgcoff');
    assert_same('inactive', $withFallback['status'], 'The link is reported disabled');
    assert_same('https://example.com/sgcorga-fallback', $withFallback['destination'], 'The visitor goes to the owner\'s fallback');

    $withoutFallback = resolveShortCode('g2my.link', 'sgcoffnofb');
    assert_same('inactive', $withoutFallback['status'], 'Disabled');
    assert_same('https://go2my.link/fallback', $withoutFallback['destination'], 'An owner without a fallback leaves the default one');

    $missing = resolveShortCode('g2my.link', 'sgcnobodyhasthis');
    assert_same('not_found', $missing['status'], 'An unknown code is not found');
    assert_same('[default]', $missing['orgHandle'], 'and reported under [default], as before');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 6. The public info page finds the link the address opens
// ============================================================================
test('#289: the info page finds an organisation member\'s g2my.link link, and an own-domain link only through that domain', function () use ($db): void
{
    g2ml_sgc_clean($db);

    g2ml_sgc_seed($db, 'sgcorga', 'sgcinfo', 'sgcinfo', 'https://example.com/sgc-info-shared', 1);
    g2ml_sgc_seed($db, 'sgcown', 'sgcinfo', null, 'https://example.com/sgc-info-own', 1);
    g2ml_sgc_seed($db, 'sgcorgb', 'sgcinfob', 'sgcinfob', 'https://example.com/sgc-info-b', 1);

    $shared = g2ml_findLinkForInfo('sgcinfo', '');
    assert_true(is_array($shared), 'g2my.link/sgcinfo is found (it used to be "no link found")');
    assert_same('https://example.com/sgc-info-shared', $shared['destinationURL'], 'It is the link g2my.link opens');
    assert_same('g2my.link', $shared['shortDomain'], 'Shown on g2my.link');

    $own = g2ml_findLinkForInfo('sgcinfo', 'sgc-own.test');
    assert_true(is_array($own), 'sgc-own.test/sgcinfo is found');
    assert_same('https://example.com/sgc-info-own', $own['destinationURL'], 'It is the own-domain link');
    assert_same('sgc-own.test', $own['shortDomain'], 'Shown on the organisation\'s domain');

    assert_true(g2ml_findLinkForInfo('sgcinfob', 'sgc-unverified.test') === null, 'An unverified domain finds nothing, as following it would');
    assert_true(g2ml_findLinkForInfo('sgcinfob', 'g2my.link') !== null, 'g2my.link given as the domain finds the shared link');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 7. Two creations of the same code at the same moment
// ============================================================================
test('#289: two creations of the same g2my.link code at once — the second waits for the first, then fails', function () use ($db, $g2mlSgcConnection): void
{
    g2ml_sgc_clean($db);

    $first  = g2ml_sgc_connect($g2mlSgcConnection);
    $second = g2ml_sgc_connect($g2mlSgcConnection);

    try
    {
        // A short wait, so a lock wait cannot hang the suite.
        g2ml_sgc_exec($second, 'SET SESSION innodb_lock_wait_timeout = 10');

        // The first creation is half-way through: its row is written but
        // not yet committed.
        mysqli_begin_transaction($first);
        g2ml_sgc_exec(
            $first,
            "INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `destinationType`, `isActive`) "
            . "VALUES ('sgcorga', 'sgcrace', 'sgcrace', 'https://example.com/sgc-race-a', 'url', 1)"
        );

        // The second creation, for another organisation, starts at the
        // same moment. It is sent without waiting for an answer.
        $sent = mysqli_query(
            $second,
            "INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `destinationType`, `isActive`) "
            . "VALUES ('sgcorgb', 'sgcrace', 'sgcrace', 'https://example.com/sgc-race-b', 'url', 1)",
            MYSQLI_ASYNC
        );
        assert_true($sent !== false, 'The second INSERT was sent');

        // Half a second later it has still not finished: the database is
        // holding it until the first one decides.
        $readable = [$second];
        $errors   = [$second];
        $rejected = [$second];
        $ready    = mysqli_poll($readable, $errors, $rejected, 0, 500000);
        assert_same(0, $ready, 'The second creation is waiting on the first, not racing past it');

        mysqli_commit($first);

        assert_same(1062, g2ml_sgc_reap_errno($second), 'Once the first is committed, the second fails with a duplicate-key error, which createShortURL() turns into "taken" or a retry');

        assert_same(1, g2ml_sgc_shared_count($db, 'sgcrace'), 'Exactly one link holds the code');
    }
    finally
    {
        mysqli_close($first);
        mysqli_close($second);
        g2ml_sgc_clean($db);
    }
});

test('#289: the database itself refuses a second row with the same g2my.link code, and a mismatched code', function () use ($db): void
{
    g2ml_sgc_clean($db);

    g2ml_sgc_seed($db, 'sgcorga', 'sgcdup', 'sgcdup', 'https://example.com/sgc-dup-a', 1);

    $duplicate = g2ml_sgc_errno_of(
        $db,
        "INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `destinationType`, `isActive`) "
        . "VALUES ('sgcorgb', 'sgcdup', 'sgcdup', 'https://example.com/sgc-dup-b', 'url', 1)"
    );
    assert_same(1062, $duplicate, 'A second organisation cannot hold the same g2my.link code: refused by the unique key UQ_url_shared_code');

    // 3819 is MySQL's "check constraint is violated" (MariaDB uses 4025).
    $mismatch = g2ml_sgc_errno_of(
        $db,
        "INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `sharedShortCode`, `destinationURL`, `destinationType`, `isActive`) "
        . "VALUES ('sgcorgb', 'sgcmisa', 'sgcmisb', 'https://example.com/sgc-mismatch', 'url', 1)"
    );
    assert_same(3819, $mismatch, 'A row cannot open on g2my.link under a code other than its own: refused by CK_url_shared_code_matches');

    g2ml_sgc_clean($db);
});

// ============================================================================
// 8. Migration 044 on an old-shaped copy of the table
// ============================================================================

/**
 * Run a whole SQL file (no DELIMITER lines) against a connection, after
 * replacing its USE line with the given database. Returns every result set,
 * each as a list of rows.
 *
 * @param  mysqli $db
 * @param  string $path
 * @param  string $database
 * @return array<int, array<int, array<string, mixed>>>
 */
function g2ml_sgc_run_sql_file(mysqli $db, string $path, string $database): array
{
    $sql = file_get_contents($path);

    if ($sql === false)
    {
        throw new RuntimeException('Could not read ' . $path);
    }

    $sql = str_replace('USE `mwtools_Go2MyLink`;', 'USE `' . $database . '`;', $sql);

    $resultSets = [];

    if (mysqli_multi_query($db, $sql) === false)
    {
        throw new RuntimeException('Migration failed at the first statement: ' . mysqli_error($db));
    }

    while (true)
    {
        $result = mysqli_store_result($db);

        if ($result instanceof mysqli_result)
        {
            $rows = [];

            while (true)
            {
                $row = mysqli_fetch_assoc($result);

                if ($row === null || $row === false)
                {
                    break;
                }

                $rows[] = $row;
            }

            $resultSets[] = $rows;
            mysqli_free_result($result);
        }

        if (mysqli_more_results($db) === false)
        {
            break;
        }

        if (mysqli_next_result($db) === false)
        {
            throw new RuntimeException('Migration failed part-way: ' . mysqli_error($db));
        }
    }

    if (mysqli_errno($db) !== 0)
    {
        throw new RuntimeException('Migration failed: ' . mysqli_error($db));
    }

    return $resultSets;
}

/**
 * Find the result set whose first row has the given report label.
 *
 * @param  array  $resultSets
 * @param  string $labelStart
 * @return array<int, array<string, mixed>>|null  null when no such set (an
 *                                                empty report has no rows to
 *                                                carry the label).
 */
function g2ml_sgc_report(array $resultSets, string $labelStart): ?array
{
    foreach ($resultSets as $rows)
    {
        if (count($rows) > 0 && isset($rows[0]['report']) && str_starts_with((string) $rows[0]['report'], $labelStart))
        {
            return $rows;
        }
    }

    return null;
}

test('#289: migration 044 brings an old table up to date, keeps working links, and lists clashing codes', function () use ($db, $database): void
{
    $scratch       = 'g2ml_sgc_migration_test';
    $migrationPath = dirname(__DIR__, 2) . '/web/_sql/migrations/044_shared_g2my_codes.sql';

    assert_true(is_file($migrationPath), 'web/_sql/migrations/044_shared_g2my_codes.sql exists');

    g2ml_sgc_exec($db, 'DROP DATABASE IF EXISTS `' . $scratch . '`');
    g2ml_sgc_exec($db, 'CREATE DATABASE `' . $scratch . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    try
    {
        // Copies of the two tables the migration reads, made from today's
        // real tables, then taken back to their shape before #289.
        g2ml_sgc_exec($db, 'CREATE TABLE `' . $scratch . '`.`tblShortURLs` LIKE `' . $database . '`.`tblShortURLs`');
        g2ml_sgc_exec($db, 'CREATE TABLE `' . $scratch . '`.`tblOrgShortDomains` LIKE `' . $database . '`.`tblOrgShortDomains`');

        $checks = g2ml_sgc_rows(
            $db,
            "SELECT `CONSTRAINT_NAME` FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE `CONSTRAINT_SCHEMA` = ? AND `TABLE_NAME` = 'tblShortURLs' AND `CONSTRAINT_TYPE` = 'CHECK'",
            's',
            [$scratch]
        );

        foreach ($checks as $check)
        {
            g2ml_sgc_exec($db, 'ALTER TABLE `' . $scratch . '`.`tblShortURLs` DROP CHECK `' . $check['CONSTRAINT_NAME'] . '`');
        }

        g2ml_sgc_exec($db, 'ALTER TABLE `' . $scratch . '`.`tblShortURLs` DROP INDEX `UQ_url_shared_code`');
        g2ml_sgc_exec($db, 'ALTER TABLE `' . $scratch . '`.`tblShortURLs` DROP COLUMN `sharedShortCode`');

        // Old data, inserted in this order (so urlUID follows it):
        //   [default]  mgcode1  — opens on g2my.link today
        //   mgorga     mgcode1  — clashes with [default]: left out
        //   mgorga     mgcode2  — free: goes on g2my.link
        //   mgorgb     mgcode2  — clashes with mgorga (newer): left out
        //   mgown      mgcode3  — own verified default domain: left alone
        //   mgorgc     mgcode4  — disabled, older
        //   mgorgd     mgcode4  — active, newer: wins over the disabled one
        g2ml_sgc_exec($db, 'INSERT INTO `' . $scratch . '`.`tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `isActive`) VALUES '
            . "('[default]', 'mgcode1', 'https://example.com/d1', 1), "
            . "('mgorga', 'mgcode1', 'https://example.com/a1', 1), "
            . "('mgorga', 'mgcode2', 'https://example.com/a2', 1), "
            . "('mgorgb', 'mgcode2', 'https://example.com/b2', 1), "
            . "('mgown', 'mgcode3', 'https://example.com/o3', 1), "
            . "('mgorgc', 'mgcode4', 'https://example.com/c4', 0), "
            . "('mgorgd', 'mgcode4', 'https://example.com/d4', 1)");

        g2ml_sgc_exec($db, 'INSERT INTO `' . $scratch . '`.`tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`) VALUES '
            . "('[default]', 'g2my.link', 1, 1, 'verified'), "
            . "('mgown', 'mg-own.test', 1, 1, 'verified'), "
            . "('mgorgb', 'mg-pending.test', 1, 0, 'pending')");

        $firstRun = g2ml_sgc_run_sql_file($db, $migrationPath, $scratch);

        $placement = g2ml_sgc_rows(
            $db,
            'SELECT `orgHandle`, `shortCode`, `sharedShortCode` FROM `' . $scratch . '`.`tblShortURLs` ORDER BY `urlUID`',
            '',
            []
        );

        $expected = [
            ['[default]', 'mgcode1', 'mgcode1'],
            ['mgorga', 'mgcode1', null],
            ['mgorga', 'mgcode2', 'mgcode2'],
            ['mgorgb', 'mgcode2', null],
            ['mgown', 'mgcode3', null],
            ['mgorgc', 'mgcode4', null],
            ['mgorgd', 'mgcode4', 'mgcode4'],
        ];

        assert_same(count($expected), count($placement), 'Every old row is still there');

        foreach ($expected as $index => $expectedRow)
        {
            assert_same($expectedRow[0], $placement[$index]['orgHandle'], 'Row ' . $index . ' organisation');
            assert_same($expectedRow[2], $placement[$index]['sharedShortCode'], 'Row ' . $index . ' (' . $expectedRow[0] . ' ' . $expectedRow[1] . ') g2my.link code');
        }

        $leftOut = g2ml_sgc_report($firstRun, 'Migration 044 — links left off');
        assert_true($leftOut !== null, 'The migration prints the left-out links');
        assert_same(3, count($leftOut), 'Three links are left out: mgorga mgcode1, mgorgb mgcode2, mgorgc mgcode4');
        assert_same('[default]', $leftOut[0]['holderOrgHandle'], 'mgcode1 is held by [default]');
        assert_same('mgorga', $leftOut[1]['holderOrgHandle'], 'mgcode2 is held by mgorga');
        assert_same('mgorgd', $leftOut[2]['holderOrgHandle'], 'mgcode4 is held by mgorgd, the active one');

        $mismatched = g2ml_sgc_report($firstRun, 'Migration 044 — mismatched');
        assert_same(0, (int) $mismatched[0]['mismatched'], 'No mismatched codes');

        $structure = g2ml_sgc_rows(
            $db,
            "SELECT "
            . "(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE `TABLE_SCHEMA` = ? AND `TABLE_NAME` = 'tblShortURLs' AND `COLUMN_NAME` = 'sharedShortCode') AS `columnCount`, "
            . "(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE `TABLE_SCHEMA` = ? AND `TABLE_NAME` = 'tblShortURLs' AND `INDEX_NAME` = 'UQ_url_shared_code' AND `NON_UNIQUE` = 0) AS `keyCount`, "
            . "(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE `CONSTRAINT_SCHEMA` = ? AND `TABLE_NAME` = 'tblShortURLs' AND `CONSTRAINT_NAME` = 'CK_url_shared_code_matches') AS `checkCount`",
            'sss',
            [$scratch, $scratch, $scratch]
        );
        assert_same(1, (int) $structure[0]['columnCount'], 'The column was added');
        assert_same(1, (int) $structure[0]['keyCount'], 'The unique key was added');
        assert_same(1, (int) $structure[0]['checkCount'], 'The check was added');

        // An old row that arrives later, as migration 004 brings them,
        // with the column empty; then the migration is run a second time.
        // A late default-organisation row whose code an organisation now
        // holds cannot take it back; it must be LISTED, not silently lost.
        g2ml_sgc_exec($db, 'INSERT INTO `' . $scratch . '`.`tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `isActive`) VALUES '
            . "('mgorgb', 'mgcode5', 'https://example.com/b5', 1), "
            . "('[default]', 'mgcode2', 'https://example.com/d2', 1)");

        $secondRun = g2ml_sgc_run_sql_file($db, $migrationPath, $scratch);

        $late = g2ml_sgc_rows(
            $db,
            'SELECT `sharedShortCode` FROM `' . $scratch . "`.`tblShortURLs` WHERE `orgHandle` = 'mgorgb' AND `shortCode` = 'mgcode5'",
            '',
            []
        );
        assert_same('mgcode5', $late[0]['sharedShortCode'], 'A second run puts the late row on g2my.link');

        $leftOutAgain = g2ml_sgc_report($secondRun, 'Migration 044 — links left off');
        assert_same(4, count($leftOutAgain), 'The second run lists the same three plus the late default-organisation row');
        assert_same('[default]', $leftOutAgain[2]['leftOutOrgHandle'], 'The late [default] mgcode2 row is listed');
        assert_same('mgorga', $leftOutAgain[2]['holderOrgHandle'], 'beside mgorga, which holds the code');

        $unchanged = g2ml_sgc_rows(
            $db,
            'SELECT `sharedShortCode` FROM `' . $scratch . "`.`tblShortURLs` WHERE `orgHandle` = '[default]' AND `shortCode` = 'mgcode1'",
            '',
            []
        );
        assert_same('mgcode1', $unchanged[0]['sharedShortCode'], 'The default organisation keeps its code after a second run');
    }
    finally
    {
        g2ml_sgc_exec($db, 'DROP DATABASE IF EXISTS `' . $scratch . '`');
        mysqli_select_db($db, $database);
    }
});
