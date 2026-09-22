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

// ============================================================================
// 🐛 #218 — social links lost on create, font family saved as '0', re-publish
//     says "not found", 200-item abuse cap. Each of these tests drives the
//     REAL function end to end against a real database, because the bug in
//     each case was a bind-type letter or a row-count comparison that only
//     shows up once MySQLi actually talks to a server (a MariaDB test server
//     would not even have shown the create/socialLinks failure — see the
//     comment above the INSERT in g2ml_linkspageManageCreatePage()).
// ============================================================================

test('linkspage manage (#218): creating a page with social links and a font family survives — read back matches exactly', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('social');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => 'Social Links Page',
        'fontFamily'  => 'Georgia',
        'socialLinks' => [
            'twitter' => 'https://twitter.com/example',
            'github'  => 'https://github.com/example',
        ],
    ]);

    assert_true(
        $createResult['success'],
        'A create with social links and a font family must succeed — this used to fail outright on MySQL 8 (error 3140, socialLinks bound as an integer): ' . ($createResult['error'] ?? '')
    );

    $pageUID = $createResult['pageUID'];
    $fetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    assert_same('Georgia', $fetched['fontFamily'], 'The font family must be stored and read back exactly as submitted, not coerced to "0" by a wrong bind type');

    $decodedSocialLinks = json_decode((string) $fetched['socialLinks'], true);

    assert_true(is_array($decodedSocialLinks), 'socialLinks must be stored as valid, readable JSON, not lost or corrupted');
    assert_same('https://twitter.com/example', $decodedSocialLinks['twitter'] ?? null, 'The twitter social link must survive create');
    assert_same('https://github.com/example', $decodedSocialLinks['github'] ?? null, 'The github social link must survive create');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage (#218): saving the edit form with a font family survives — not coerced to "0"', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('font');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Font Test Page',
    ]);
    $pageUID = $createResult['pageUID'];

    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Font Test Page',
        'fontFamily' => 'Verdana',
    ]);

    assert_true($updateResult['success'], 'A valid update carrying a font family must succeed: ' . ($updateResult['error'] ?? ''));

    $fetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same('Verdana', $fetched['fontFamily'], 'The font family must be stored and read back exactly as submitted after an edit-form save, not coerced to "0" by a wrong bind type');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage (#218): publishing an already-published page succeeds instead of reporting "not found"', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('republish');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Republish Test Page',
    ]);
    $pageUID = $createResult['pageUID'];

    $firstPublish = g2ml_linkspageManageSetPublished($userUID, $pageUID, true);
    assert_true($firstPublish['success'], 'The first publish must succeed: ' . ($firstPublish['error'] ?? ''));

    $secondPublish = g2ml_linkspageManageSetPublished($userUID, $pageUID, true);
    assert_true(
        $secondPublish['success'],
        'Publishing an ALREADY-published page must still succeed — MySQLi reports 0 affected rows when nothing actually changed, and that is not "not found": ' . ($secondPublish['error'] ?? '')
    );

    $fetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same(1, (int) $fetched['isPublished'], 'The page must still be published after the redundant second publish call');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage (#218): a page already at the 200-item abuse cap rejects one more add', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('cap');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Cap Test Page',
    ]);
    $pageUID = $createResult['pageUID'];

    // Fill the page to EXACTLY the cap via raw inserts, bypassing
    // g2ml_linkspageManageAddItem() — the function under test — so this
    // setup does not depend on the very thing being tested.
    $insertStatement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageItems` (`pageUID`, `itemTitle`, `itemURL`, `sortOrder`) VALUES (?, ?, ?, ?)'
    );

    for ($fillerIndex = 0; $fillerIndex < G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE; $fillerIndex++)
    {
        $fillerTitle = 'Filler ' . $fillerIndex;
        $fillerURL   = 'https://example.com/filler-' . $fillerIndex;

        mysqli_stmt_bind_param($insertStatement, 'issi', $pageUID, $fillerTitle, $fillerURL, $fillerIndex);
        $ok = mysqli_stmt_execute($insertStatement);

        if ($ok === false)
        {
            $error = mysqli_stmt_error($insertStatement);
            mysqli_stmt_close($insertStatement);
            throw new RuntimeException('Setup: filler item insert failed: ' . $error);
        }
    }

    mysqli_stmt_close($insertStatement);

    $confirmCount = dbSelectOne('SELECT COUNT(*) AS itemCount FROM tblLinksPageItems WHERE pageUID = ?', 'i', [$pageUID]);
    assert_same(
        G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE,
        (int) $confirmCount['itemCount'],
        'Setup: the page must hold exactly the cap\'s worth of items before the real assertion runs'
    );

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/one-too-many',
        'itemTitle' => 'One Too Many',
    ]);

    assert_false($addResult['success'], 'Adding one more item to a page already at the 200-item cap must be rejected');
    assert_contains((string) G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE, (string) $addResult['error'], 'The rejection message must mention the cap in plain English, not a generic error');

    $finalCount = dbSelectOne('SELECT COUNT(*) AS itemCount FROM tblLinksPageItems WHERE pageUID = ?', 'i', [$pageUID]);
    assert_same(
        G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE,
        (int) $finalCount['itemCount'],
        'The rejected add must not have written a 201st row'
    );

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🖼️ Avatar and per-link icon addresses must be https (#221, #273)
// ============================================================================
//
// #221 (LP-10) allowed https: images through the public page's CSP, so an
// avatar or icon can finally be SEEN by a visitor. #273 then found that
// saving an http:// address here still silently succeeded — the help text
// on the create/edit forms said "Must start with https://" but the server
// disagreed. An http:// address is unreliable on an https page (a modern
// browser quietly tries it over https:// instead and shows nothing if that
// server has none; an older browser is blocked outright by the CSP, which
// lists https: alone), so a creator who saved one had no way to know
// whether it would actually show to a visitor. These end-to-end tests
// exercise the REAL create/update-page/add-item/update-item functions (not
// just the pure validator, which tests/unit/linkspage_manage_test.php
// already pins) to prove the full save path refuses http:// and accepts
// https:// for both fields, on every one of those four ways to write an
// avatar or icon address.
// ============================================================================

test('linkspage manage (#221/#273): creating a page with an http:// avatar is rejected', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('avatarhttp');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar HTTP Test Page',
        'avatarPath' => 'http://example.com/me.png',
    ]);

    assert_false($createResult['success'], 'Creating a page with an http:// avatar address must be rejected');

    // Exact message, not a substring match: 'https' also turns up in the
    // raw translation KEY names (linkspage.avatar_url_https_error) and in
    // unrelated errors, so a loose assert_contains('https', ...) here would
    // still pass even if this check were checking the wrong thing, or
    // nothing at all. __() is not loaded in this test suite (nothing under
    // tests/ requires web/_functions/i18n.php), so the plain-English
    // fallback written at the call site is exactly what comes back.
    assert_same('The avatar must be a valid https:// image URL.', (string) $createResult['error'], 'The rejection message must be the avatar https error, not some other failure');

    $pages = g2ml_linkspageManageListPagesForUser($userUID);
    assert_same(0, count($pages), 'No page must have been created from the rejected http:// avatar attempt');
});

test('linkspage manage (#221/#273): creating a page with an https:// avatar succeeds and is read back unchanged', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('avatarhttps');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar HTTPS Test Page',
        'avatarPath' => 'https://example.com/me.png',
    ]);

    assert_true($createResult['success'], 'Creating a page with an https:// avatar address must succeed: ' . ($createResult['error'] ?? ''));
    $pageUID = $createResult['pageUID'];

    $pageData = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same('https://example.com/me.png', $pageData['avatarPath'], 'The https avatar address must be stored and read back unchanged');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items (#221/#273): adding a link with an http:// icon is rejected', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('iconhttp');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Icon HTTP Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/destination',
        'itemTitle' => 'Icon HTTP Test Link',
        'itemIcon'  => 'http://example.com/icon.png',
    ]);

    assert_false($addResult['success'], 'Adding a link with an http:// icon address must be rejected');

    // Exact message, not a substring match — see the comment on the
    // matching avatar-rejection assertion above for why 'https' alone is
    // not a safe thing to look for.
    assert_same('The icon must be a valid https:// image URL.', (string) $addResult['error'], 'The rejection message must be the icon https error, not some other failure');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same(0, count($items), 'No item must have been created from the rejected http:// icon attempt');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items (#221/#273): adding a link with an https:// icon succeeds and is read back unchanged', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('iconhttps');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Icon HTTPS Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/destination',
        'itemTitle' => 'Icon HTTPS Test Link',
        'itemIcon'  => 'https://example.com/icon.png',
    ]);

    assert_true($addResult['success'], 'Adding a link with an https:// icon address must succeed: ' . ($addResult['error'] ?? ''));

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same(1, count($items), 'Exactly one item must exist after the add');
    assert_same('https://example.com/icon.png', $items[0]['itemIcon'], 'The https icon address must be stored and read back unchanged');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items (#221/#273): updating a link to an http:// icon is rejected and the old value is kept', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('iconupdatehttp');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Icon Update HTTP Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/destination',
        'itemTitle' => 'Icon Update Test Link',
        'itemIcon'  => 'https://example.com/original-icon.png',
    ]);
    $itemUID = $addResult['itemUID'];

    // manualURL is included here, valid and https, for the same reason the
    // matching success test below includes it. This used to matter for a
    // different reason than it does now: in review round 3, this test only
    // checked that the error message CONTAINED the word "https", and with
    // no manualURL in the request, g2ml_linkspageManageUpdateItem() would
    // have failed on the missing destination instead ("Please enter a
    // valid http:// or https:// URL.") — a message that also contains
    // "https", so the test still passed even with the icon-scheme check
    // deleted from the function entirely. That was found by deleting the
    // check and re-running the suite: every test still passed.
    //
    // The assert_same() a few lines below now compares the exact icon
    // message instead of a substring, so that old loophole is closed on
    // its own — if the icon check were deleted, the update would fail with
    // the manualURL message instead, and the exact-match assertion would
    // fail. manualURL is kept here anyway, because the icon check runs
    // BEFORE the manualURL check inside g2ml_linkspageManageUpdateItem()
    // in web/_functions/linkspage_manage.php (the icon block comes first,
    // the "Only a MANUAL item ... may have its destination URL changed"
    // block after it): supplying a valid manualURL removes the other way
    // this request could fail, so the icon check is the only thing left
    // that can refuse the save, and this test proves specifically that
    // check rather than some other one.
    $updateResult = g2ml_linkspageManageUpdateItem($userUID, $itemUID, [
        'itemTitle' => 'Icon Update Test Link',
        'manualURL' => 'https://example.com/destination',
        'itemIcon'  => 'http://example.com/new-icon.png',
    ]);

    assert_false($updateResult['success'], 'Updating a link to an http:// icon address must be rejected');

    // Exact message, not a substring match — see the comment on the
    // avatar-rejection assertion earlier in this file for why 'https'
    // alone is not a safe thing to look for.
    assert_same('The icon must be a valid https:// image URL.', (string) $updateResult['error'], 'The rejection message must be the icon https error, not some other failure');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same('https://example.com/original-icon.png', $items[0]['itemIcon'], 'The rejected update must leave the original https icon address in place');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

test('linkspage manage items (#221/#273): updating a link to an https:// icon succeeds and is read back unchanged', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('iconupdatehttps');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Icon Update HTTPS Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/destination',
        'itemTitle' => 'Icon Update Test Link',
        'itemIcon'  => 'https://example.com/original-icon.png',
    ]);
    $itemUID = $addResult['itemUID'];

    $updateResult = g2ml_linkspageManageUpdateItem($userUID, $itemUID, [
        'itemTitle' => 'Icon Update Test Link',
        'manualURL' => 'https://example.com/destination',
        'itemIcon'  => 'https://example.com/new-icon.png',
    ]);

    assert_true($updateResult['success'], 'Updating a link to an https:// icon address must succeed: ' . ($updateResult['error'] ?? ''));

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same('https://example.com/new-icon.png', $items[0]['itemIcon'], 'The updated https icon address must be stored and read back unchanged');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// ----------------------------------------------------------------------
// g2ml_linkspageManageUpdatePage() is a SEPARATE code path from create
// (it reads the existing row first, then re-validates and re-writes it —
// see the function itself in web/_functions/linkspage_manage.php). The
// tests above cover create, add-item and update-item; this one covers the
// fourth way to write an avatar address, an EXISTING page being edited
// through the admin "edit" form, which is exactly the form #273 was
// originally filed against.
// ----------------------------------------------------------------------
test('linkspage manage (#221/#273): updating a page to an http:// avatar is rejected and the old value is kept', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('avatarupdatehttp');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar Update HTTP Test Page',
        'avatarPath' => 'https://example.com/original-me.png',
    ]);
    $pageUID = $createResult['pageUID'];

    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar Update HTTP Test Page',
        'avatarPath' => 'http://example.com/new-me.png',
    ]);

    assert_false($updateResult['success'], 'Updating a page to an http:// avatar address must be rejected');

    // Exact message, not a substring match — see the comment on the
    // avatar-creation-rejection assertion earlier in this file for why
    // 'https' alone is not a safe thing to look for.
    assert_same('The avatar must be a valid https:// image URL.', (string) $updateResult['error'], 'The rejection message must be the avatar https error, not some other failure');

    $pageData = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same('https://example.com/original-me.png', $pageData['avatarPath'], 'The rejected update must leave the original https avatar address in place');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// Review round 5 (LP-10) found that this file's header comment claimed https
// acceptance was proven end to end "on every one of those four ways to write
// an avatar or icon address" (create page, update page, add item, update
// item), but only THREE of those four had a matching https-success test —
// update-page only had the http-rejection test above. https acceptance for
// update-page was still correct (it runs through the same shared validator,
// _g2ml_linkspageManageValidateFields(), that tests/unit/linkspage_manage_test.php
// already proves accepts https — see the "an https:// avatar address is
// accepted" test there), but nothing at the end-to-end level actually
// exercised it. This test closes that gap, so the header comment's claim is
// true of all four paths rather than three of them.
test('linkspage manage (#221/#273): updating a page to an https:// avatar succeeds and is read back unchanged', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('avatarupdatehttps');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar Update HTTPS Test Page',
        'avatarPath' => 'https://example.com/original-me.png',
    ]);
    $pageUID = $createResult['pageUID'];

    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'       => $marker . '-slug',
        'pageTitle'  => 'Avatar Update HTTPS Test Page',
        'avatarPath' => 'https://example.com/new-me.png',
    ]);

    assert_true($updateResult['success'], 'Updating a page to an https:// avatar address must succeed: ' . ($updateResult['error'] ?? ''));

    $pageData = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same('https://example.com/new-me.png', $pageData['avatarPath'], 'The updated https avatar address must be stored and read back unchanged');

    g2ml_lpm_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🐛 #220 (LP-12) — an apostrophe and an ampersand must round-trip unchanged
// ============================================================================
// The bug was in the ADMIN FORM PAGES (pages/linkspage/create/index.php and
// edit/index.php), which used to escape a value with g2ml_sanitiseOutput()
// BEFORE handing it to formField() — and formField() then escaped it AGAIN
// on the way out, so the double-escaped text got saved back on the next
// submit. This manage-layer function, g2ml_linkspageManageCreatePage(), is
// what those pages actually call to save — it never escapes for HTML
// output at all; it only strip_tags()-and-trims via g2ml_sanitiseInput()
// (title/description) and scheme-validates via g2ml_sanitiseURL() (social
// links), neither of which touches an apostrophe or an ampersand. This test
// proves that THIS layer was never the problem: an apostrophe in the title
// and an ampersand in a social-link URL survive completely unchanged. The
// admin pages' own fix (removing the extra g2ml_sanitiseOutput() call
// before formField()) is covered by the automated source check in
// tests/unit/accessibility_formfield_test.php (the "no formField() call
// pre-escapes its value" tests), and formField()'s own single-escape
// behaviour is pinned down in the same file. (Review round 2 on LP-12
// found this comment out of date — it used to say the admin pages' fix was
// checked only by reading the source in code review, which stopped being
// true once that automated check was added in round 1.)
test('linkspage manage (#220): a title with an apostrophe and a social URL with "&" round-trip unchanged', function () use ($db, $g2mlLpmOrgHandle): void
{
    $marker  = g2ml_lpm_test_marker('escaping');
    $userUID = g2ml_lpm_test_insert_user($db, $g2mlLpmOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpmOrgHandle, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => "Jane's & Co",
        'socialLinks' => [
            'website' => 'https://example.com/?a=1&b=2',
        ],
    ]);

    assert_true($createResult['success'], 'Creating a page with an apostrophe in the title and "&" in a social URL must succeed: ' . ($createResult['error'] ?? ''));

    $pageUID = $createResult['pageUID'];
    $fetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    // Neither the apostrophe nor the ampersand is an HTML entity — the
    // manage layer must never escape for HTML output, only escaping on the
    // way OUT (in formField(), exactly once) is correct.
    assert_same("Jane's & Co", $fetched['pageTitle'], 'The title must be stored and read back with its apostrophe and ampersand exactly as submitted, not HTML-escaped');

    $decodedSocialLinks = json_decode((string) $fetched['socialLinks'], true);
    assert_true(is_array($decodedSocialLinks), 'socialLinks must be stored as valid, readable JSON');
    assert_same('https://example.com/?a=1&b=2', $decodedSocialLinks['website'] ?? null, 'The social URL\'s "&" must survive exactly as submitted, not escaped to "&amp;" nor corrupted further');

    // Round-trip through an UPDATE too, since the admin edit page saves via
    // this same path — a value already corrupted by the old double-escape
    // bug would compound further here if any escaping crept into this
    // layer.
    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => "Jane's & Co",
        'socialLinks' => [
            'website' => 'https://example.com/?a=1&b=2',
        ],
    ]);

    assert_true($updateResult['success'], 'Updating a page with the same apostrophe/ampersand values must succeed: ' . ($updateResult['error'] ?? ''));

    $refetched = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same("Jane's & Co", $refetched['pageTitle'], 'The title must still be unescaped after an update, not compounded by a second escape pass');

    $decodedSocialLinksAfterUpdate = json_decode((string) $refetched['socialLinks'], true);
    assert_same('https://example.com/?a=1&b=2', $decodedSocialLinksAfterUpdate['website'] ?? null, 'The social URL must still be unescaped after an update');

    g2ml_lpm_test_delete_page($db, $pageUID);
});
