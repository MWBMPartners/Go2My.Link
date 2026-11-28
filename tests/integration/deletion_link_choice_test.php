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
 * 🧪 Integration tests — #246 short links when an account is erased
 * ============================================================================
 *
 * Owner decisions D-017 and D-021: links are switched off by default, or the
 * person chooses one option for all of them (30 days, 90 days, 1 year, for
 * ever). Drives the real g2ml_requestDataDeletion() and
 * g2ml_processDataDeletion() (data_rights.php) against a real database and
 * asks the real sp_lookupShortURL whether each link still redirects.
 *
 * Every case creates its own uniquely-marked rows and removes them again.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#246)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// If it is somehow unavailable, register nothing rather than fataling.
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlLinkChoiceTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLinkChoiceTestHost === false || $g2mlLinkChoiceTestHost === '')
    {
        $g2mlLinkChoiceTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLinkChoiceTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLinkChoiceTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLinkChoiceTestPortRaw === false || $g2mlLinkChoiceTestPortRaw === '')
    {
        $g2mlLinkChoiceTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlLinkChoiceTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlLinkChoiceTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLinkChoiceTestUser === false || $g2mlLinkChoiceTestUser === '')
    {
        $g2mlLinkChoiceTestUser = 'root';
    }

    define('DB_USER', $g2mlLinkChoiceTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLinkChoiceTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLinkChoiceTestPass === false)
    {
        $g2mlLinkChoiceTestPass = '';
    }

    define('DB_PASS', $g2mlLinkChoiceTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLinkChoiceTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLinkChoiceTestName === false || $g2mlLinkChoiceTestName === '')
    {
        $g2mlLinkChoiceTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLinkChoiceTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// cron.php's own functions call getSetting()/logActivity() UNGUARDED (unlike
// data_export_test.php's target, which guards every optional collaborator),
// so settings.php and activity_logger.php MUST both be loaded here.
// ----------------------------------------------------------------------------
$g2mlLinkChoiceFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlLinkChoiceFunctionsDir . '/db_connect.php';
require_once $g2mlLinkChoiceFunctionsDir . '/db_query.php';
require_once $g2mlLinkChoiceFunctionsDir . '/security.php';
require_once $g2mlLinkChoiceFunctionsDir . '/settings.php';
require_once $g2mlLinkChoiceFunctionsDir . '/activity_logger.php';
require_once $g2mlLinkChoiceFunctionsDir . '/data_rights.php';

// ----------------------------------------------------------------------------
// Helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Run one prepared statement and return the insert ID.
 *
 * @param  mysqli                $db
 * @param  string                $sql
 * @param  array<int, mixed>     $params
 * @return int
 */
function g2ml_linkchoice_test_run(mysqli $db, string $sql, array $params = []): int
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false)
    {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($db));
    }

    if (mysqli_stmt_execute($statement, $params) === false)
    {
        throw new RuntimeException('Execute failed: ' . mysqli_stmt_error($statement));
    }

    $insertID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $insertID;
}

/**
 * Fetch one row.
 *
 * @param  mysqli            $db
 * @param  string            $sql
 * @param  array<int, mixed> $params
 * @return array<string, mixed>|null
 */
function g2ml_linkchoice_test_row(mysqli $db, string $sql, array $params = []): ?array
{
    $statement = mysqli_prepare($db, $sql);

    if ($statement === false || mysqli_stmt_execute($statement, $params) === false)
    {
        throw new RuntimeException('Query failed: ' . mysqli_error($db));
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    return $row;
}

/**
 * A fresh "world": a unique marker plus the IDs to remove afterwards.
 *
 * @return array{marker: string, users: array<int, int>, orgs: array<int, string>}
 */
function g2ml_linkchoice_test_world(): array
{
    return ['marker' => 'lc' . bin2hex(random_bytes(5)), 'users' => [], 'orgs' => []];
}

function g2ml_linkchoice_test_user(mysqli $db, array &$world, string $orgHandle = '[default]'): int
{
    $name    = $world['marker'] . 'u' . count($world['users']);
    $userUID = g2ml_linkchoice_test_run(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `firstName`, `lastName`, `isActive`) '
        . 'VALUES (?, ?, ?, \'x\', \'Link\', \'Tester\', 1)',
        [$orgHandle, $name, $name . '@linkchoice246.test']
    );
    $world['users'][] = $userUID;

    return $userUID;
}

function g2ml_linkchoice_test_org(mysqli $db, array &$world): string
{
    $orgHandle = $world['marker'] . 'o' . count($world['orgs']);
    g2ml_linkchoice_test_run(
        $db,
        'INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) VALUES (?, \'Org\', \'free\', 1)',
        [$orgHandle]
    );
    $world['orgs'][] = $orgHandle;

    return $orgHandle;
}

/**
 * Create a link. $endOffset and $startOffset are MySQL interval text relative
 * to the database's UTC time (for example '+10' means ten days ahead), or null.
 *
 * @return string  The short code.
 */
function g2ml_linkchoice_test_link(mysqli $db, array $world, string $suffix, int $userUID, string $orgHandle = '[default]', int $isActive = 1, ?int $endInDays = null): string
{
    $shortCode = $world['marker'] . $suffix;
    g2ml_linkchoice_test_run(
        $db,
        'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `title`, `urlNotes`, `createdByUserUID`, `isActive`, `endDate`) '
        . 'VALUES (?, ?, \'https://example.com/kept\', \'My own title\', \'Private note\', ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY))',
        [$orgHandle, $shortCode, $userUID, $isActive, $endInDays]
    );

    return $shortCode;
}

function g2ml_linkchoice_test_fetch_link(mysqli $db, string $shortCode): array
{
    $row = g2ml_linkchoice_test_row(
        $db,
        'SELECT `isActive`, `endDate`, `title`, `urlNotes`, `createdByUserUID`, `createdViaAPIKeyUID`, `destinationURL`, '
        . 'TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), `endDate`) AS `secondsLeft`, '
        . 'TIMESTAMPDIFF(SECOND, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 YEAR), `endDate`) AS `secondsFromOneYear` '
        . 'FROM `tblShortURLs` WHERE `shortCode` = ?',
        [$shortCode]
    );

    if ($row === null)
    {
        throw new RuntimeException('Link not found: ' . $shortCode);
    }

    return $row;
}

/**
 * Ask the real redirect procedure what a visitor would get.
 */
function g2ml_linkchoice_test_status(mysqli $db, string $shortCode): string
{
    g2ml_linkchoice_test_run(
        $db,
        'CALL `sp_lookupShortURL`(\'g2my.link\', ?, @lcDest, @lcStatus, @lcOrg, @lcS, @lcM, @lcC, @lcT, @lcN)',
        [$shortCode]
    );
    $row = g2ml_linkchoice_test_row($db, 'SELECT @lcStatus AS `status`');

    return (string) $row['status'];
}

function g2ml_linkchoice_test_request(mysqli $db, int $userUID): array
{
    $row = g2ml_linkchoice_test_row(
        $db,
        'SELECT `requestUID`, `status`, `linkChoice` FROM `tblDataDeletionRequests` WHERE `userUID` = ? ORDER BY `requestUID` DESC LIMIT 1',
        [$userUID]
    );

    if ($row === null)
    {
        throw new RuntimeException('No deletion request for user ' . $userUID);
    }

    return $row;
}

/**
 * Request deletion with a choice, then erase the account.
 */
function g2ml_linkchoice_test_erase(mysqli $db, int $userUID, int $actorUID, string $linkChoice): void
{
    $result = g2ml_requestDataDeletion($userUID, null, $linkChoice);
    assert_true($result['success'], 'The deletion request is accepted');

    assert_true(g2ml_processDataDeletion((int) $result['requestUID'], $actorUID), 'The erasure succeeds');
}

function g2ml_linkchoice_test_cleanup(mysqli $db, array $world): void
{
    g2ml_linkchoice_test_run($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` LIKE ?', [$world['marker'] . '%']);

    foreach ($world['users'] as $userUID)
    {
        g2ml_linkchoice_test_run($db, 'DELETE FROM `tblDataDeletionRequests` WHERE `userUID` = ? OR `processedByUserUID` = ?', [$userUID, $userUID]);
        g2ml_linkchoice_test_run($db, 'DELETE FROM `tblActivityLog` WHERE `userUID` = ?', [$userUID]);
        g2ml_linkchoice_test_run($db, 'DELETE FROM `tblAPIKeys` WHERE `userUID` = ?', [$userUID]);
        g2ml_linkchoice_test_run($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ?', [$userUID]);
    }

    foreach ($world['orgs'] as $orgHandle)
    {
        g2ml_linkchoice_test_run($db, 'DELETE FROM `tblOrganisations` WHERE `orgHandle` = ?', [$orgHandle]);
    }
}

g2ml_linkchoice_test_run(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);
g2ml_linkchoice_test_run(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

// ============================================================================
// (1) Default — switched off, and disconnected.
// ============================================================================
test('link choice (#246): with no choice made, links are switched off and disconnected on erasure', function () use ($db): void
{
    $world = g2ml_linkchoice_test_world();
    $user  = g2ml_linkchoice_test_user($db, $world);
    $actor = g2ml_linkchoice_test_user($db, $world);
    $other = g2ml_linkchoice_test_user($db, $world);
    $code  = g2ml_linkchoice_test_link($db, $world, 'a', $user);
    $theirs = g2ml_linkchoice_test_link($db, $world, 'z', $other);

    $keyUID = g2ml_linkchoice_test_run(
        $db,
        'INSERT INTO `tblAPIKeys` (`userUID`, `apiKey`, `apiKeyPrefix`) VALUES (?, ?, ?)',
        [$user, $world['marker'] . 'key', substr($world['marker'], 0, 10)]
    );
    g2ml_linkchoice_test_run($db, 'UPDATE `tblShortURLs` SET `createdViaAPIKeyUID` = ? WHERE `shortCode` = ?', [$keyUID, $code]);

    $result = g2ml_requestDataDeletion($user);
    assert_true($result['success'], 'The request is accepted');
    assert_same('off', g2ml_linkchoice_test_request($db, $user)['linkChoice'], 'The recorded default is off');
    assert_same('success', g2ml_linkchoice_test_status($db, $code), 'Requesting deletion changes no link yet');
    assert_same($user, (int) g2ml_linkchoice_test_fetch_link($db, $code)['createdByUserUID'], 'The link is still theirs during the grace period');

    assert_true(g2ml_processDataDeletion((int) $result['requestUID'], $actor), 'The erasure succeeds');

    assert_same('inactive', g2ml_linkchoice_test_status($db, $code), 'The link no longer redirects');

    $link = g2ml_linkchoice_test_fetch_link($db, $code);
    assert_same(null, $link['createdByUserUID'], 'No longer tied to the account');
    assert_same(null, $link['createdViaAPIKeyUID'], 'No longer tied to the API key');
    assert_same(null, $link['title'], 'Title removed');
    assert_same(null, $link['urlNotes'], 'Notes removed');

    $untouched = g2ml_linkchoice_test_fetch_link($db, $theirs);
    assert_same(1, (int) $untouched['isActive'], 'Another person\'s link stays on');
    assert_same($other, (int) $untouched['createdByUserUID'], 'Another person\'s link stays theirs');
    assert_same('My own title', $untouched['title'], 'Another person\'s title is kept');

    g2ml_linkchoice_test_cleanup($db, $world);
});

// ============================================================================
// (2) Set periods.
// ============================================================================
foreach (['30_days' => 30, '90_days' => 90, '1_year' => 0] as $periodChoice => $periodDays)
{
    test('link choice (#246): ' . $periodChoice . ' keeps links working until that long after erasure, never extending or reviving', function () use ($db, $periodChoice, $periodDays): void
    {
        $world = g2ml_linkchoice_test_world();
        $user  = g2ml_linkchoice_test_user($db, $world);
        $actor = g2ml_linkchoice_test_user($db, $world);

        $open    = g2ml_linkchoice_test_link($db, $world, 'a', $user);
        $sooner  = g2ml_linkchoice_test_link($db, $world, 'b', $user, '[default]', 1, 5);
        $later   = g2ml_linkchoice_test_link($db, $world, 'c', $user, '[default]', 1, 900);
        $off     = g2ml_linkchoice_test_link($db, $world, 'd', $user, '[default]', 0);
        $expired = g2ml_linkchoice_test_link($db, $world, 'e', $user, '[default]', 1, -3);

        $soonerBefore  = g2ml_linkchoice_test_fetch_link($db, $sooner)['endDate'];
        $expiredBefore = g2ml_linkchoice_test_fetch_link($db, $expired)['endDate'];

        g2ml_linkchoice_test_erase($db, $user, $actor, $periodChoice);

        foreach ([$open, $later] as $code)
        {
            $link = g2ml_linkchoice_test_fetch_link($db, $code);
            assert_true($link['endDate'] !== null, 'An end date is set');

            if ($periodChoice === '1_year')
            {
                $difference = (int) $link['secondsFromOneYear'];
            }
            else
            {
                $difference = (int) $link['secondsLeft'] - ($periodDays * 86400);
            }

            assert_true(abs($difference) < 120, 'End date is the chosen period after erasure (out by ' . $difference . ' seconds)');
            assert_same(1, (int) $link['isActive'], 'Still on');
            assert_same('success', g2ml_linkchoice_test_status($db, $code), 'Still redirects');
            assert_same(null, $link['createdByUserUID'], 'Disconnected from the account');
            assert_same(null, $link['title'], 'Title removed');
            assert_same(null, $link['urlNotes'], 'Notes removed');
            assert_same('https://example.com/kept', $link['destinationURL'], 'Destination kept');
        }

        assert_same($soonerBefore, g2ml_linkchoice_test_fetch_link($db, $sooner)['endDate'], 'A sooner expiry is not extended');
        assert_same($expiredBefore, g2ml_linkchoice_test_fetch_link($db, $expired)['endDate'], 'An expired link is not revived');
        assert_same('expired', g2ml_linkchoice_test_status($db, $expired), 'An expired link stays expired');

        $offLink = g2ml_linkchoice_test_fetch_link($db, $off);
        assert_same(0, (int) $offLink['isActive'], 'A link that was off stays off');
        assert_same(null, $offLink['endDate'], 'A link that was off gets no end date');
        assert_same(null, $offLink['createdByUserUID'], 'A link that was off is disconnected too');

        g2ml_linkchoice_test_cleanup($db, $world);
    });
}

// ============================================================================
// (3) For ever.
// ============================================================================
test('link choice (#246): forever keeps links and their own expiry dates exactly as they were', function () use ($db): void
{
    $world  = g2ml_linkchoice_test_world();
    $user   = g2ml_linkchoice_test_user($db, $world);
    $actor  = g2ml_linkchoice_test_user($db, $world);
    $open   = g2ml_linkchoice_test_link($db, $world, 'a', $user);
    $dated  = g2ml_linkchoice_test_link($db, $world, 'b', $user, '[default]', 1, 5);
    $off    = g2ml_linkchoice_test_link($db, $world, 'c', $user, '[default]', 0);
    $before = g2ml_linkchoice_test_fetch_link($db, $dated)['endDate'];

    g2ml_linkchoice_test_erase($db, $user, $actor, 'forever');

    $openLink = g2ml_linkchoice_test_fetch_link($db, $open);
    assert_same(null, $openLink['endDate'], 'No end date added');
    assert_same(null, $openLink['createdByUserUID'], 'Disconnected from the account');
    assert_same(null, $openLink['title'], 'Title removed');
    assert_same('success', g2ml_linkchoice_test_status($db, $open), 'Still redirects');
    assert_same($before, g2ml_linkchoice_test_fetch_link($db, $dated)['endDate'], 'Own expiry kept');
    assert_same(0, (int) g2ml_linkchoice_test_fetch_link($db, $off)['isActive'], 'Off stays off');

    g2ml_linkchoice_test_cleanup($db, $world);
});

// ============================================================================
// (4) The period counts from erasure, and a repeat run does not restart it.
// ============================================================================
test('link choice (#246): the period counts from erasure, not the request, and a repeat run does not restart it', function () use ($db): void
{
    $world = g2ml_linkchoice_test_world();
    $user  = g2ml_linkchoice_test_user($db, $world);
    $actor = g2ml_linkchoice_test_user($db, $world);
    $code  = g2ml_linkchoice_test_link($db, $world, 'a', $user);

    $result = g2ml_requestDataDeletion($user, null, '30_days');
    assert_true($result['success'], 'The request is accepted');
    g2ml_linkchoice_test_run(
        $db,
        'UPDATE `tblDataDeletionRequests` SET `createdAt` = DATE_SUB(NOW(), INTERVAL 40 DAY) WHERE `requestUID` = ?',
        [(int) $result['requestUID']]
    );

    assert_true(g2ml_processDataDeletion((int) $result['requestUID'], $actor), 'The erasure succeeds');

    $first = g2ml_linkchoice_test_fetch_link($db, $code);
    assert_true(abs((int) $first['secondsLeft'] - (30 * 86400)) < 120, '30 days from erasure, although requested 40 days ago');

    sleep(1);
    assert_true(g2ml_anonymiseUserData($user, '30_days'), 'A repeat run succeeds');
    assert_same($first['endDate'], g2ml_linkchoice_test_fetch_link($db, $code)['endDate'], 'The end date does not move');

    g2ml_linkchoice_test_cleanup($db, $world);
});

// ============================================================================
// (5) Cancelled requests change nothing.
// ============================================================================
test('link choice (#246): a cancelled request leaves every link as it was', function () use ($db): void
{
    $world = g2ml_linkchoice_test_world();
    $user  = g2ml_linkchoice_test_user($db, $world);
    $actor = g2ml_linkchoice_test_user($db, $world);
    $code  = g2ml_linkchoice_test_link($db, $world, 'a', $user);

    $result = g2ml_requestDataDeletion($user, null, 'off');
    assert_true($result['success'], 'The request is accepted');
    g2ml_linkchoice_test_run(
        $db,
        'UPDATE `tblDataDeletionRequests` SET `status` = \'rejected\' WHERE `requestUID` = ?',
        [(int) $result['requestUID']]
    );

    assert_false(g2ml_processDataDeletion((int) $result['requestUID'], $actor), 'A cancelled request is not processed');

    $link = g2ml_linkchoice_test_fetch_link($db, $code);
    assert_same(1, (int) $link['isActive'], 'Still on');
    assert_same($user, (int) $link['createdByUserUID'], 'Still theirs');
    assert_same('My own title', $link['title'], 'Title kept');

    // A new request after cancelling records the new choice.
    assert_true(g2ml_requestDataDeletion($user, null, '90_days')['success'], 'A new request is accepted');
    assert_same('90_days', g2ml_linkchoice_test_request($db, $user)['linkChoice'], 'The new choice is recorded');

    g2ml_linkchoice_test_cleanup($db, $world);
});

// ============================================================================
// (6) An unknown choice is refused.
// ============================================================================
test('link choice (#246): an unknown choice is refused and no request is recorded', function () use ($db): void
{
    $world = g2ml_linkchoice_test_world();
    $user  = g2ml_linkchoice_test_user($db, $world);

    foreach (['', 'Forever', '2_years', 'off; DROP TABLE'] as $badChoice)
    {
        assert_false(g2ml_requestDataDeletion($user, null, $badChoice)['success'], 'Refused: ' . $badChoice);
    }

    $count = g2ml_linkchoice_test_row($db, 'SELECT COUNT(*) AS `n` FROM `tblDataDeletionRequests` WHERE `userUID` = ?', [$user]);
    assert_same(0, (int) $count['n'], 'Nothing recorded');

    g2ml_linkchoice_test_cleanup($db, $world);
});

// ============================================================================
// (7) Organisation links.
// ============================================================================
test('link choice (#246): links of an organisation with another active member are left working, only disconnected', function () use ($db): void
{
    $world  = g2ml_linkchoice_test_world();
    $shared = g2ml_linkchoice_test_org($db, $world);
    $user   = g2ml_linkchoice_test_user($db, $world, $shared);
    g2ml_linkchoice_test_user($db, $world, $shared);
    $actor    = g2ml_linkchoice_test_user($db, $world);
    $orgLink  = g2ml_linkchoice_test_link($db, $world, 'a', $user, $shared);
    $ownLink  = g2ml_linkchoice_test_link($db, $world, 'b', $user);

    g2ml_linkchoice_test_erase($db, $user, $actor, 'off');

    $link = g2ml_linkchoice_test_fetch_link($db, $orgLink);
    assert_same(1, (int) $link['isActive'], 'The organisation link stays on');
    assert_same(null, $link['endDate'], 'The organisation link gets no end date');
    assert_same('My own title', $link['title'], 'The organisation keeps the title');
    assert_same('Private note', $link['urlNotes'], 'The organisation keeps the notes');
    assert_same(null, $link['createdByUserUID'], 'It is disconnected from the person');
    assert_same(0, (int) g2ml_linkchoice_test_fetch_link($db, $ownLink)['isActive'], 'Their personal link is switched off');

    g2ml_linkchoice_test_cleanup($db, $world);
});

test('link choice (#246): links in an organisation with nobody else active follow the person\'s choice', function () use ($db): void
{
    $world = g2ml_linkchoice_test_world();
    $solo  = g2ml_linkchoice_test_org($db, $world);
    $user  = g2ml_linkchoice_test_user($db, $world, $solo);
    $gone  = g2ml_linkchoice_test_user($db, $world, $solo);
    g2ml_linkchoice_test_run($db, 'UPDATE `tblUsers` SET `isActive` = 0 WHERE `userUID` = ?', [$gone]);
    $actor = g2ml_linkchoice_test_user($db, $world);
    $code  = g2ml_linkchoice_test_link($db, $world, 'a', $user, $solo);

    g2ml_linkchoice_test_erase($db, $user, $actor, 'off');

    $link = g2ml_linkchoice_test_fetch_link($db, $code);
    assert_same(0, (int) $link['isActive'], 'Switched off');
    assert_same(null, $link['title'], 'Title removed');
    assert_same(null, $link['createdByUserUID'], 'Disconnected');

    g2ml_linkchoice_test_cleanup($db, $world);
});
