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
 * 🧪 Integration tests — LinksPage management CRUD (Component C.2, #48)
 * ============================================================================
 *
 * Drives the REAL functions from web/_functions/linkspage_manage.php against
 * a freshly imported test database (see tests/README.md), covering:
 *
 *   - Page create -> read -> update -> delete, end to end.
 *   - OWNERSHIP / IDOR: user B can neither read, update, nor delete a page
 *     (or an item on that page) owned by user A — every such attempt is
 *     denied and behaves identically to the target not existing at all.
 *   - Slug uniqueness: a second page created with an already-taken slug is
 *     rejected with errorCode 'slug_taken', and the existing row is untouched.
 *   - Item add (from an owned short URL, and from a manual URL), reject of a
 *     short URL owned by ANOTHER user (IDOR guard on the item-add path),
 *     reorder (move up/down), toggle active, and delete.
 *   - Deleting a page cascades to its items (FK_item_page ON DELETE CASCADE).
 *   - The maxLinksPages entitlement (#146): a tight tier blocks a NEW page
 *     once the user is already at the limit, without touching any existing
 *     page, and the rejection carries errorCode 'limit_reached'.
 *
 * Registration model mirrors entitlements_test.php / api_key_test.php: cases
 * register at INCLUDE time using the $db handle from run_integration.php's
 * script scope. Helper names are prefixed g2ml_lpm_test_* to stay unique
 * alongside sibling integration files. With no reachable test DB the runner
 * skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#48)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
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
    $g2mlLpmEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLpmEnvHost === false || $g2mlLpmEnvHost === '')
    {
        $g2mlLpmEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLpmEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLpmEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLpmEnvPort === false || $g2mlLpmEnvPort === '')
    {
        $g2mlLpmEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlLpmEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlLpmEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLpmEnvUser === false || $g2mlLpmEnvUser === '')
    {
        $g2mlLpmEnvUser = 'root';
    }

    define('DB_USER', $g2mlLpmEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLpmEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLpmEnvPass === false)
    {
        $g2mlLpmEnvPass = '';
    }

    define('DB_PASS', $g2mlLpmEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLpmEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLpmEnvName === false || $g2mlLpmEnvName === '')
    {
        $g2mlLpmEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLpmEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlLpmFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlLpmFunctionsDir . 'db_connect.php';
require_once $g2mlLpmFunctionsDir . 'db_query.php';
require_once $g2mlLpmFunctionsDir . 'security.php';
require_once $g2mlLpmFunctionsDir . 'settings.php';
require_once $g2mlLpmFunctionsDir . 'activity_logger.php';
require_once $g2mlLpmFunctionsDir . 'entitlements.php';
require_once $g2mlLpmFunctionsDir . 'linkspage_manage.php';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout).
// ----------------------------------------------------------------------------

/**
 * Execute a setup/teardown query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_lpm_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user for the given org and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_lpm_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@lpm48.test';
    $passwordHash = 'x';

    mysqli_stmt_bind_param($statement, 'ssss', $orgHandle, $username, $email, $passwordHash);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (user) failed: ' . $error);
    }

    $userUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $userUID;
}

/**
 * Insert a throwaway active short URL owned by the given user and return its
 * urlUID.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_lpm_test_insert_shorturl(mysqli $db, int $userUID, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblShortURLs` (`orgHandle`, `shortCode`, `destinationURL`, `createdByUserUID`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, 1)'
    );

    $shortCode      = $marker;
    $destinationURL = 'https://example.com/' . $marker;

    mysqli_stmt_bind_param($statement, 'sssi', $orgHandle, $shortCode, $destinationURL, $userUID);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (short URL) failed: ' . $error);
    }

    $urlUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $urlUID;
}

/**
 * Delete a page (and, via FK_item_page ON DELETE CASCADE, its items) by
 * pageUID, bypassing ownership — a raw teardown helper, not the code under
 * test.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @return void
 */
function g2ml_lpm_test_delete_page(mysqli $db, int $pageUID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblLinksPages` WHERE `pageUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $pageUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Build a unique marker string for this test run.
 *
 * @param  string $label
 * @return string
 */
function g2ml_lpm_test_marker(string $label): string
{
    static $counter = 0;
    $counter++;

    return 'lpm' . $counter . '_' . $label . '_' . substr(hash('sha256', (string) microtime(true) . $label), 0, 8);
}

// ----------------------------------------------------------------------------
// Shared fixtures: the free tier and the [default] org (idempotent).
// ----------------------------------------------------------------------------
g2ml_lpm_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_lpm_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlLpmOrgHandle = '[default]';

// ============================================================================
// 📄 Page create -> read -> update -> delete
// ============================================================================

test('linkspage manage: create -> get -> update -> delete works end to end', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('crud');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'My Test Page',
    ]);

    assert_true($createResult['success'], 'A valid create must succeed: ' . ($createResult['error'] ?? ''));
    assert_true(is_int($createResult['pageUID']) && $createResult['pageUID'] > 0, 'A successful create must return a positive pageUID');

    $pageUID = $createResult['pageUID'];

    $fetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_true(is_array($fetched), 'The owner must be able to fetch their own newly created page');
    assert_same($marker . '-slug', $fetched['slug'], 'The stored slug must match what was submitted');
    assert_same('My Test Page', $fetched['pageTitle'], 'The stored title must match what was submitted');
    assert_same(0, (int) $fetched['isPublished'], 'A page defaults to unpublished (draft) unless isPublished is explicitly set');

    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => 'Updated Title',
        'isPublished' => true,
    ]);

    assert_true($updateResult['success'], 'A valid update by the owner must succeed: ' . ($updateResult['error'] ?? ''));

    $refetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same('Updated Title', $refetched['pageTitle'], 'The update must be persisted');
    assert_same(1, (int) $refetched['isPublished'], 'isPublished must be persisted as 1');

    $deleteResult = g2ml_linkspageManageDeletePage($userUID, $pageUID);
    assert_true($deleteResult['success'], 'The owner must be able to delete their own page');

    assert_same(null, g2ml_linkspageManageGetPageForOwner($pageUID, $userUID), 'The page must be gone after deletion');
});

// ============================================================================
// 🔒 Ownership / IDOR — user B cannot touch user A's page
// ============================================================================

test('linkspage manage: OWNERSHIP — user B cannot read, update, or delete user A\'s page (IDOR denied)', function () use ($db, $g2mlLpmOrgHandle): void
{
    $markerA = g2ml_lpm_test_marker('ownerA');
    $markerB = g2ml_lpm_test_marker('ownerB');
    $userA   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerA);
    $userB   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerB);

    $createResult = g2ml_linkspageManageCreatePage($userA, $g2mlLpmOrgHandle, [
        'slug'      => $markerA . '-slug',
        'pageTitle' => 'User A\'s Page',
    ]);

    assert_true($createResult['success'], 'Setup: user A\'s page must be created successfully');
    $pageUID = $createResult['pageUID'];

    // 🔒 READ denied.
    assert_same(null, g2ml_linkspageManageGetPageForOwner($pageUID, $userB), 'User B must NOT be able to read user A\'s page');

    // 🔒 UPDATE denied — behaves exactly like "not found", not a permission-specific message leak.
    $updateAttempt = g2ml_linkspageManageUpdatePage($userB, $pageUID, [
        'slug'      => $markerA . '-slug',
        'pageTitle' => 'Hijacked Title',
    ]);

    assert_false($updateAttempt['success'], 'User B must NOT be able to update user A\'s page');
    assert_same('not_found', $updateAttempt['errorCode'], 'A cross-owner update must report not_found, not a permission-specific leak');

    $stillOwnedByA = g2ml_linkspageManageGetPageForOwner($pageUID, $userA);
    assert_same('User A\'s Page', $stillOwnedByA['pageTitle'], 'The rejected cross-owner update must NOT have changed the title');

    // 🔒 DELETE denied.
    $deleteAttempt = g2ml_linkspageManageDeletePage($userB, $pageUID);
    assert_false($deleteAttempt['success'], 'User B must NOT be able to delete user A\'s page');

    assert_true(is_array(g2ml_linkspageManageGetPageForOwner($pageUID, $userA)), 'The page must still exist for its real owner after the rejected cross-owner delete');

    // The real owner CAN still update/delete their own page.
    $ownerUpdateResult = g2ml_linkspageManageUpdatePage($userA, $pageUID, [
        'slug'      => $markerA . '-slug',
        'pageTitle' => 'Still Mine',
    ]);
    assert_true($ownerUpdateResult['success'], 'The real owner must still be able to update their own page');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🐛 Slug uniqueness collision -> friendly errorCode, no insert
// ============================================================================

test('linkspage manage: a slug collision on CREATE is rejected with errorCode slug_taken', function () use ($db, $g2mlLpmOrgHandle): void
{
    $markerA = g2ml_lpm_test_marker('slugA');
    $markerB = g2ml_lpm_test_marker('slugB');
    $userA   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerA);
    $userB   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerB);

    $sharedSlug = g2ml_lpm_test_marker('shared-slug');

    $firstResult = g2ml_linkspageManageCreatePage($userA, $g2mlLpmOrgHandle, [
        'slug'      => $sharedSlug,
        'pageTitle' => 'First Owner',
    ]);

    assert_true($firstResult['success'], 'The first page with this slug must be created successfully');

    $collisionResult = g2ml_linkspageManageCreatePage($userB, $g2mlLpmOrgHandle, [
        'slug'      => $sharedSlug,
        'pageTitle' => 'Second Owner (should fail)',
    ]);

    assert_false($collisionResult['success'], 'A second page with the SAME slug must be rejected');
    assert_same('slug_taken', $collisionResult['errorCode'], 'The rejection must be flagged slug_taken');
    assert_same(null, $collisionResult['pageUID'], 'A rejected create must not return a pageUID');

    $userBPages = g2ml_linkspageManageListPagesForUser($userB);
    assert_same(0, count($userBPages), 'User B must have NO pages after the rejected collision attempt');

    g2ml_lpm_test_delete_page($db, $firstResult['pageUID']);
});

// ============================================================================
// 🔗 Items — add (short URL + manual), IDOR on short-URL source, reorder,
//     toggle, delete, ownership
// ============================================================================

test('linkspage manage items: add from an owned short URL derives itemURL server-side', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('itemshort');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Item Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $urlUID = g2ml_lpm_test_insert_shorturl($db, $userUID, $g2mlLpmOrgHandle, $marker . '-code');

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'shorturl',
        'urlUID'    => $urlUID,
        'itemTitle' => 'My Short Link',
    ]);

    assert_true($addResult['success'], 'Adding an item from the user\'s OWN short URL must succeed: ' . ($addResult['error'] ?? ''));

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same(1, count($items), 'Exactly one item must exist after the add');
    assert_same($urlUID, (int) $items[0]['urlUID'], 'The item must record the linked urlUID');
    assert_contains($marker . '-code', $items[0]['itemURL'], 'itemURL must be derived server-side from the short URL\'s own code');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items: IDOR — a short URL owned by ANOTHER user cannot be attached as an item', function () use ($db, $g2mlLpmOrgHandle): void
{
    $markerA = g2ml_lpm_test_marker('idorA');
    $markerB = g2ml_lpm_test_marker('idorB');
    $userA   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerA);
    $userB   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerB);

    $pageResult = g2ml_linkspageManageCreatePage($userA, $g2mlLpmOrgHandle, [
        'slug'      => $markerA . '-slug',
        'pageTitle' => 'User A Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    // A short URL that belongs to user B.
    $userBUrlUID = g2ml_lpm_test_insert_shorturl($db, $userB, $g2mlLpmOrgHandle, $markerB . '-code');

    $addResult = g2ml_linkspageManageAddItem($userA, $pageUID, [
        'source'    => 'shorturl',
        'urlUID'    => $userBUrlUID,
        'itemTitle' => 'Stolen Link',
    ]);

    assert_false($addResult['success'], 'User A must NOT be able to attach user B\'s short URL as an item');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userA);
    assert_same(0, count($items), 'No item must have been created from the rejected cross-owner short URL attempt');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items: a manual URL with an unsafe scheme is rejected', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('manualbad');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Manual URL Test',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'javascript:alert(1)',
        'itemTitle' => 'Evil Link',
    ]);

    assert_false($addResult['success'], 'A javascript: manual URL must be rejected');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same(0, count($items), 'No item must exist after the rejected unsafe manual URL');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items: reorder (move up/down), toggle active, and delete — all ownership-checked', function () use ($db, $g2mlLpmOrgHandle): void
{
    $markerA = g2ml_lpm_test_marker('reorderA');
    $markerB = g2ml_lpm_test_marker('reorderB');
    $userA   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerA);
    $userB   = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $markerB);

    $pageResult = g2ml_linkspageManageCreatePage($userA, $g2mlLpmOrgHandle, [
        'slug'      => $markerA . '-slug',
        'pageTitle' => 'Reorder Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $firstAdd  = g2ml_linkspageManageAddItem($userA, $pageUID, ['source' => 'manual', 'manualURL' => 'https://example.com/one', 'itemTitle' => 'One']);
    $secondAdd = g2ml_linkspageManageAddItem($userA, $pageUID, ['source' => 'manual', 'manualURL' => 'https://example.com/two', 'itemTitle' => 'Two']);
    $thirdAdd  = g2ml_linkspageManageAddItem($userA, $pageUID, ['source' => 'manual', 'manualURL' => 'https://example.com/three', 'itemTitle' => 'Three']);

    assert_true($firstAdd['success'] && $secondAdd['success'] && $thirdAdd['success'], 'All three items must be added successfully');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userA);
    assert_same(['One', 'Two', 'Three'], [$items[0]['itemTitle'], $items[1]['itemTitle'], $items[2]['itemTitle']], 'Items must initially be ordered by insertion order');

    // 🔒 IDOR: user B cannot move, toggle, or delete user A's item.
    $crossOwnerMove = g2ml_linkspageManageMoveItem($userB, (int) $thirdAdd['itemUID'], 'up');
    assert_false($crossOwnerMove['success'], 'User B must NOT be able to reorder user A\'s item');

    $crossOwnerToggle = g2ml_linkspageManageToggleItemActive($userB, (int) $thirdAdd['itemUID']);
    assert_false($crossOwnerToggle['success'], 'User B must NOT be able to toggle user A\'s item');

    $crossOwnerDelete = g2ml_linkspageManageDeleteItem($userB, (int) $thirdAdd['itemUID']);
    assert_false($crossOwnerDelete['success'], 'User B must NOT be able to delete user A\'s item');

    // Move the THIRD item ("Three") up — it should swap with "Two".
    $moveResult = g2ml_linkspageManageMoveItem($userA, (int) $thirdAdd['itemUID'], 'up');
    assert_true($moveResult['success'], 'The real owner\'s move must succeed');
    assert_true($moveResult['moved'], 'A move with an available sibling must report moved => true');

    $itemsAfterMove = g2ml_linkspageManageListItemsForPage($pageUID, $userA);
    assert_same(['One', 'Three', 'Two'], [$itemsAfterMove[0]['itemTitle'], $itemsAfterMove[1]['itemTitle'], $itemsAfterMove[2]['itemTitle']], 'Moving "Three" up must swap it with "Two"');

    // Moving the FIRST item up again is a no-op (already at the top).
    $noopMove = g2ml_linkspageManageMoveItem($userA, (int) $firstAdd['itemUID'], 'up');
    assert_true($noopMove['success'], 'Moving the first item further up must still report success');
    assert_false($noopMove['moved'], 'Moving the first item further up must report moved => false (no-op)');

    // Toggle "One" inactive, by its real owner.
    $toggleResult = g2ml_linkspageManageToggleItemActive($userA, (int) $firstAdd['itemUID']);
    assert_true($toggleResult['success'], 'The real owner\'s toggle must succeed');
    assert_same(0, $toggleResult['isActive'], 'Toggling an active item must flip it to inactive');

    // Delete "Two", by its real owner.
    $deleteResult = g2ml_linkspageManageDeleteItem($userA, (int) $secondAdd['itemUID']);
    assert_true($deleteResult['success'], 'The real owner\'s delete must succeed');

    $finalItems = g2ml_linkspageManageListItemsForPage($pageUID, $userA);
    assert_same(2, count($finalItems), 'Exactly 2 items must remain after deleting one of the three');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🗑️ Deleting a page cascades to its items
// ============================================================================

test('linkspage manage: deleting a page cascades to its items', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('cascade');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Cascade Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, ['source' => 'manual', 'manualURL' => 'https://example.com/x', 'itemTitle' => 'X']);
    assert_true($addResult['success'], 'Setup: the item must be added successfully');

    $deletePageResult = g2ml_linkspageManageDeletePage($userUID, $pageUID);
    assert_true($deletePageResult['success'], 'The page delete must succeed');

    $orphanCheck = dbSelectOne('SELECT itemUID FROM tblLinksPageItems WHERE itemUID = ?', 'i', [(int) $addResult['itemUID']]);
    assert_same(null, $orphanCheck, 'The item row must be gone too (FK_item_page ON DELETE CASCADE) — no orphaned item left behind');
});

// ============================================================================
// 💎 #146 — maxLinksPages entitlement gate blocks a new page over the limit
// ============================================================================

test('linkspage manage (#146): a tight maxLinksPages tier blocks a new page once the user is at the limit', function () use ($db): void
{
    g2ml_lpm_test_exec(
        $db,
        "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `maxLinksPages`, `sortOrder`, `isActive`) "
        . "VALUES ('lpm146tight', 'LinksPage Test Tight Tier', 1, 999, 1) "
        . "ON DUPLICATE KEY UPDATE `maxLinksPages` = VALUES(`maxLinksPages`), `sortOrder` = VALUES(`sortOrder`)"
    );

    $orgHandle = 'lpm146org';

    g2ml_lpm_test_exec(
        $db,
        "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
        . "VALUES ('" . $orgHandle . "', 'LinksPage #146 Test Org', 'https://go2my.link/lpm146org', 'lpm146tight', 1) "
        . "ON DUPLICATE KEY UPDATE `tierID` = VALUES(`tierID`)"
    );

    g2ml_clearOrgTierCache($orgHandle);

    $marker  = g2ml_lpm_test_marker('limit');
    $userUID = g2ml_lpm_test_insert_user($db, $orgHandle, $marker);

    $firstResult = g2ml_linkspageManageCreatePage($userUID, $orgHandle, [
        'slug'      => $marker . '-first',
        'pageTitle' => 'First Page (within limit of 1)',
    ]);

    assert_true($firstResult['success'], 'The first page, AT the tier limit of 1, must be accepted');

    $secondResult = g2ml_linkspageManageCreatePage($userUID, $orgHandle, [
        'slug'      => $marker . '-second',
        'pageTitle' => 'Second Page (should be blocked)',
    ]);

    assert_false($secondResult['success'], 'A second page, OVER the tier limit of 1, must be rejected');
    assert_same('limit_reached', $secondResult['errorCode'], 'The rejection must be flagged limit_reached');
    assert_same(null, $secondResult['pageUID'], 'A limit-blocked create must not return a pageUID');

    $userPages = g2ml_linkspageManageListPagesForUser($userUID);
    assert_same(1, count($userPages), 'The user must still have exactly 1 page — the blocked create wrote nothing');

    g2ml_lpm_test_delete_page($db, $firstResult['pageUID']);
    g2ml_clearOrgTierCache($orgHandle);
});
