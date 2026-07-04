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
 * 🧪 Integration tests — link tagging on create + list display (FG-002)
 * ============================================================================
 *
 * Exercises the real createShortURL() tag path against a freshly imported test
 * database (see tests/README.md), driving the application's own DB layer
 * (getDB()) so the full path — find-or-create tblTags, the tblShortURLTags
 * junction, and the dashboard list's display query — is covered end to end.
 *
 * Registration model: like custom_alias_create_test.php, this file registers
 * its cases at INCLUDE time. run_integration.php require_once's every test file
 * in the script scope BEFORE g2ml_test_run_all() runs, so the connected $db
 * handle is in scope here and the closures execute after seeding. This avoids a
 * fatal redeclaration of g2ml_register_integration_tests() (owned by
 * shorturl_lookup_test.php). With no reachable test DB the runner skips before
 * this file is ever included.
 *
 * Characterised behaviour (the FG-002 acceptance criteria):
 *   (1) creating a link with tags="FG002 Marketing, FG002 Q3" creates two
 *       tblTags rows (when new) + two junction rows, and the display query
 *       returns both names;
 *   (2) reusing an existing tag does NOT duplicate the tblTags row (find-or-
 *       create) and does not error;
 *   (3) invalid/empty tags are skipped;
 *   (4) a tag failure (junction table unavailable) leaves the created short URL
 *       intact and still reports success;
 *   (5) no tags → behaves exactly as before (a link is created, no junction
 *       rows).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-002)
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
// Point the application DB layer (getDB) at the same throwaway server the
// integration runner connected to. Guarded so this file is safe to load after
// another integration file that already defined the DB_* constants. getDB()
// uses a TCP host/port, so the throwaway server must be reachable over TCP.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlEnvHost === false || $g2mlEnvHost === '')
    {
        $g2mlEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlEnvPort === false || $g2mlEnvPort === '')
    {
        $g2mlEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlEnvUser === false || $g2mlEnvUser === '')
    {
        $g2mlEnvUser = 'root';
    }

    define('DB_USER', $g2mlEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlEnvPass === false)
    {
        $g2mlEnvPass = '';
    }

    define('DB_PASS', $g2mlEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlEnvName === false || $g2mlEnvName === '')
    {
        $g2mlEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files that createShortURL() depends on.
// require_once is idempotent, so this is safe even if a sibling test loaded them.
// ----------------------------------------------------------------------------
$g2mlFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlFunctionsDir . 'db_connect.php';
require_once $g2mlFunctionsDir . 'db_query.php';
require_once $g2mlFunctionsDir . 'security.php';
require_once $g2mlFunctionsDir . 'settings.php';
require_once $g2mlFunctionsDir . 'activity_logger.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

// ----------------------------------------------------------------------------
// Verification / setup helpers (uniquely named to avoid clashes with siblings).
// ----------------------------------------------------------------------------

/**
 * Execute a setup query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_tags_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Return the urlUID for a short code, or 0 when absent.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return int
 */
function g2ml_tags_test_url_uid(mysqli $db, string $shortCode): int
{
    $statement = mysqli_prepare($db, 'SELECT urlUID FROM tblShortURLs WHERE shortCode = ? LIMIT 1');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare url_uid failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        return 0;
    }

    return (int) $row['urlUID'];
}

/**
 * Count tblShortURLs rows for a short code.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return int
 */
function g2ml_tags_test_url_count(mysqli $db, string $shortCode): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM tblShortURLs WHERE shortCode = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare url_count failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Count tblTags rows for a slug within an org.
 *
 * @param  mysqli $db
 * @param  string $slug
 * @param  string $orgHandle
 * @return int
 */
function g2ml_tags_test_tag_count(mysqli $db, string $slug, string $orgHandle): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM tblTags WHERE tagSlug = ? AND orgHandle = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare tag_count failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $slug, $orgHandle);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Count junction rows (tblShortURLTags) for a urlUID.
 *
 * @param  mysqli $db
 * @param  int    $urlUID
 * @return int
 */
function g2ml_tags_test_junction_count(mysqli $db, int $urlUID): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM tblShortURLTags WHERE urlUID = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare junction_count failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'i', $urlUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Mirror the dashboard list's display query for one urlUID: fetch the tag names
 * linked to it, ordered by tagName (exactly as the list page renders them).
 *
 * @param  mysqli $db
 * @param  int    $urlUID
 * @return array<int, string>
 */
function g2ml_tags_test_display_names(mysqli $db, int $urlUID): array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT t.tagName
         FROM tblShortURLTags st
         INNER JOIN tblTags t ON st.tagUID = t.tagUID
         WHERE st.urlUID = ?
         ORDER BY t.tagName ASC'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare display_names failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'i', $urlUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    $names = [];

    while (($row = mysqli_fetch_assoc($result)) !== null)
    {
        $names[] = (string) $row['tagName'];
    }

    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return $names;
}

/**
 * Delete a short URL by code (junction rows cascade via FK). Repeatability.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_tags_test_delete_url(mysqli $db, string $shortCode): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM tblShortURLs WHERE shortCode = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare delete_url failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Delete a tblTags row by slug within an org. Repeatability.
 *
 * @param  mysqli $db
 * @param  string $slug
 * @param  string $orgHandle
 * @return void
 */
function g2ml_tags_test_delete_tag(mysqli $db, string $slug, string $orgHandle): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM tblTags WHERE tagSlug = ? AND orgHandle = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare delete_tag failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ss', $slug, $orgHandle);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Shared setup: the [default] org + its default short domain must exist
// (createShortURL → getDefaultShortDomain). Idempotent; tolerates prior runs
// and a sibling test having already seeded these rows.
// ----------------------------------------------------------------------------
g2ml_tags_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_tags_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_tags_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

// Clean any leftovers so the suite is repeatable (short URLs first — junction
// rows cascade — then the tags themselves).
foreach (['fg002main', 'fg002reusea', 'fg002reuseb', 'fg002skip', 'fg002robust', 'fg002notags'] as $g2mlLeftoverCode)
{
    g2ml_tags_test_delete_url($db, $g2mlLeftoverCode);
}

foreach (['fg002-marketing', 'fg002-q3', 'fg002-reuse', 'fg002-robust'] as $g2mlLeftoverSlug)
{
    g2ml_tags_test_delete_tag($db, $g2mlLeftoverSlug, '[default]');
}

// ----------------------------------------------------------------------------
// (1) Creating a link with two new tags creates two tblTags rows + two junction
//     rows, and the display query returns both names.
// ----------------------------------------------------------------------------
test('FG-002 create: two new tags create two tag rows, two junction rows, and display both', function () use ($db): void
{
    g2ml_tags_test_delete_url($db, 'fg002main');
    g2ml_tags_test_delete_tag($db, 'fg002-marketing', '[default]');
    g2ml_tags_test_delete_tag($db, 'fg002-q3', '[default]');

    $result = createShortURL('https://example.com/fg002-main', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg002main',
        'tags'       => 'FG002 Marketing, FG002 Q3',
    ]);

    assert_true($result['success'], 'A create with tags should succeed');
    assert_same('fg002main', $result['shortCode'], 'The short code should be the requested alias');

    $urlUID = g2ml_tags_test_url_uid($db, 'fg002main');
    assert_true($urlUID > 0, 'The created short URL should have a urlUID');

    assert_same(1, g2ml_tags_test_tag_count($db, 'fg002-marketing', '[default]'), 'The marketing tag row should exist exactly once');
    assert_same(1, g2ml_tags_test_tag_count($db, 'fg002-q3', '[default]'), 'The q3 tag row should exist exactly once');
    assert_same(2, g2ml_tags_test_junction_count($db, $urlUID), 'Two junction rows should link the link to its two tags');

    $names = g2ml_tags_test_display_names($db, $urlUID);
    assert_same(2, count($names), 'The display query should return two tag names');
    assert_same('FG002 Marketing', $names[0], 'Display names are ordered by tagName (Marketing first)');
    assert_same('FG002 Q3', $names[1], 'Display names are ordered by tagName (Q3 second)');

    g2ml_tags_test_delete_url($db, 'fg002main');
    g2ml_tags_test_delete_tag($db, 'fg002-marketing', '[default]');
    g2ml_tags_test_delete_tag($db, 'fg002-q3', '[default]');
});

// ----------------------------------------------------------------------------
// (2) Reusing an existing tag does NOT duplicate the tblTags row, and does not
//     error; both links point at the SAME tagUID.
// ----------------------------------------------------------------------------
test('FG-002 create: reusing a tag finds it (no duplicate tag row) and links both URLs to it', function () use ($db): void
{
    g2ml_tags_test_delete_url($db, 'fg002reusea');
    g2ml_tags_test_delete_url($db, 'fg002reuseb');
    g2ml_tags_test_delete_tag($db, 'fg002-reuse', '[default]');

    $first = createShortURL('https://example.com/fg002-reuse-1', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg002reusea',
        'tags'       => 'FG002 Reuse',
    ]);
    assert_true($first['success'], 'The first create with the tag should succeed');
    assert_same(1, g2ml_tags_test_tag_count($db, 'fg002-reuse', '[default]'), 'One tag row should exist after the first create');

    $second = createShortURL('https://example.com/fg002-reuse-2', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg002reuseb',
        // Different case/spacing — must resolve to the SAME slug (no duplicate).
        'tags'       => '  fg002 reuse  ',
    ]);
    assert_true($second['success'], 'The second create reusing the tag should succeed');
    assert_same(1, g2ml_tags_test_tag_count($db, 'fg002-reuse', '[default]'), 'Reusing a tag must NOT create a second tag row');

    $uidA = g2ml_tags_test_url_uid($db, 'fg002reusea');
    $uidB = g2ml_tags_test_url_uid($db, 'fg002reuseb');
    assert_same(1, g2ml_tags_test_junction_count($db, $uidA), 'The first link should have one junction row');
    assert_same(1, g2ml_tags_test_junction_count($db, $uidB), 'The second link should have one junction row');

    $namesA = g2ml_tags_test_display_names($db, $uidA);
    $namesB = g2ml_tags_test_display_names($db, $uidB);
    assert_same('FG002 Reuse', $namesA[0], 'The first link displays the shared tag');
    assert_same('FG002 Reuse', $namesB[0], 'The second link displays the same shared tag (first-writer display name)');

    g2ml_tags_test_delete_url($db, 'fg002reusea');
    g2ml_tags_test_delete_url($db, 'fg002reuseb');
    g2ml_tags_test_delete_tag($db, 'fg002-reuse', '[default]');
});

// ----------------------------------------------------------------------------
// (3) Invalid / empty tags are skipped; only the real one is stored.
// ----------------------------------------------------------------------------
test('FG-002 create: empty and punctuation-only tags are skipped', function () use ($db): void
{
    g2ml_tags_test_delete_url($db, 'fg002skip');
    g2ml_tags_test_delete_tag($db, 'fg002-marketing', '[default]');

    $result = createShortURL('https://example.com/fg002-skip', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg002skip',
        // Two empties and a punctuation-only tag surround one real tag.
        'tags'       => ' , !!! , FG002 Marketing , ',
    ]);

    assert_true($result['success'], 'A create with mostly-invalid tags should still succeed');

    $urlUID = g2ml_tags_test_url_uid($db, 'fg002skip');
    assert_same(1, g2ml_tags_test_junction_count($db, $urlUID), 'Only the single valid tag should be linked');

    $names = g2ml_tags_test_display_names($db, $urlUID);
    assert_same(1, count($names), 'Exactly one tag name should be displayed');
    assert_same('FG002 Marketing', $names[0], 'The one valid tag survives normalisation');

    g2ml_tags_test_delete_url($db, 'fg002skip');
    g2ml_tags_test_delete_tag($db, 'fg002-marketing', '[default]');
});

// ----------------------------------------------------------------------------
// (4) A tag failure leaves the created short URL intact. We force a genuine
//     failure by temporarily renaming the junction table away (nothing has an
//     FK pointing at it, so this is safe and fully reversible). The short URL
//     INSERT is unaffected; the tag attach fails, is swallowed, and the create
//     still reports success with a live row. The rename is always reverted.
// ----------------------------------------------------------------------------
test('FG-002 create: a tag (junction) failure leaves the created short URL intact', function () use ($db): void
{
    g2ml_tags_test_delete_url($db, 'fg002robust');
    g2ml_tags_test_delete_tag($db, 'fg002-robust', '[default]');

    g2ml_tags_test_exec($db, 'RENAME TABLE `tblShortURLTags` TO `tblShortURLTags_fg002_hidden`');

    try
    {
        $result = createShortURL('https://example.com/fg002-robust', [
            'userUID'    => null,
            'orgHandle'  => '[default]',
            'customCode' => 'fg002robust',
            'tags'       => 'FG002 Robust',
        ]);

        assert_true($result['success'], 'The create must succeed even though the tag junction is unavailable');
        assert_same('fg002robust', $result['shortCode'], 'The short code should still be returned');
        assert_same(1, g2ml_tags_test_url_count($db, 'fg002robust'), 'The short URL row must exist despite the tag failure');
    }
    finally
    {
        g2ml_tags_test_exec($db, 'RENAME TABLE `tblShortURLTags_fg002_hidden` TO `tblShortURLTags`');
    }

    // With the junction restored, the created link simply has no tags attached.
    $urlUID = g2ml_tags_test_url_uid($db, 'fg002robust');
    assert_same(0, g2ml_tags_test_junction_count($db, $urlUID), 'No junction row was written while the table was unavailable');

    g2ml_tags_test_delete_url($db, 'fg002robust');
    g2ml_tags_test_delete_tag($db, 'fg002-robust', '[default]');
});

// ----------------------------------------------------------------------------
// (5) No tags → behaves exactly as before: a link is created with no junction
//     rows. Guards the "public/anonymous path unaffected" criterion.
// ----------------------------------------------------------------------------
test('FG-002 create: without tags a link is created and has no junction rows', function () use ($db): void
{
    g2ml_tags_test_delete_url($db, 'fg002notags');

    $result = createShortURL('https://example.com/fg002-notags', [
        'userUID'    => null,
        'orgHandle'  => '[default]',
        'customCode' => 'fg002notags',
    ]);

    assert_true($result['success'], 'A create without tags should succeed as before');

    $urlUID = g2ml_tags_test_url_uid($db, 'fg002notags');
    assert_true($urlUID > 0, 'The short URL should exist');
    assert_same(0, g2ml_tags_test_junction_count($db, $urlUID), 'No tags means no junction rows');
    assert_same(0, count(g2ml_tags_test_display_names($db, $urlUID)), 'The display query returns nothing for an untagged link');

    g2ml_tags_test_delete_url($db, 'fg002notags');
});
