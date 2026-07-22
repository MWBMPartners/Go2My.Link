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
 * 🧪 Integration tests — LinksPage age verification (Component C.5, #50)
 * ============================================================================
 *
 * Drives the REAL functions against a freshly imported test database:
 *
 *   - g2ml_resolveLinksPage() + g2ml_linkspageHasAgeGatedItems() — proves a
 *     page with a gated item is correctly detected end to end from the DB.
 *   - g2ml_linkspageRenderAgeGateInterstitial() — proves NEITHER the gated
 *     item's destination/title NOR any other item's data ever appears in
 *     the pre-confirmation interstitial output (the whole-page gate).
 *   - g2ml_linkspageManageAddItem() / g2ml_linkspageManageUpdateItem() —
 *     proves the adult-domain auto-flag (#50) fires on add, persists across
 *     edits while the destination stays a known-adult domain, and that the
 *     owner retains full manual toggle control for any OTHER destination.
 *   - g2ml_isAdultDomain() against the operator-configurable
 *     'linkspage.adult_domains' setting (real DB round trip via
 *     getSetting()/loadSettingsCache()).
 *   - A normal (no-gate) page is confirmed unaffected.
 *
 * Registration model mirrors linkspage_manage_test.php / linkspage_render_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed
 * g2ml_lpag_test_* to stay unique alongside sibling integration files. With
 * no reachable test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#50)
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
// Point the application DB layer (getDB()) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlLpagEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLpagEnvHost === false || $g2mlLpagEnvHost === '')
    {
        $g2mlLpagEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLpagEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLpagEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLpagEnvPort === false || $g2mlLpagEnvPort === '')
    {
        $g2mlLpagEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlLpagEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlLpagEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLpagEnvUser === false || $g2mlLpagEnvUser === '')
    {
        $g2mlLpagEnvUser = 'root';
    }

    define('DB_USER', $g2mlLpagEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLpagEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLpagEnvPass === false)
    {
        $g2mlLpagEnvPass = '';
    }

    define('DB_PASS', $g2mlLpagEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLpagEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLpagEnvName === false || $g2mlLpagEnvName === '')
    {
        $g2mlLpagEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLpagEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlLpagFunctionsDir  = dirname(__DIR__, 2) . '/web/_functions/';
$g2mlLpagComponentDir  = dirname(__DIR__, 2) . '/web/Lnks.page/_functions/';

require_once $g2mlLpagFunctionsDir . 'db_connect.php';
require_once $g2mlLpagFunctionsDir . 'db_query.php';
require_once $g2mlLpagFunctionsDir . 'security.php';
require_once $g2mlLpagFunctionsDir . 'settings.php';
require_once $g2mlLpagFunctionsDir . 'activity_logger.php';
require_once $g2mlLpagFunctionsDir . 'entitlements.php';
require_once $g2mlLpagFunctionsDir . 'adult_content.php';
require_once $g2mlLpagFunctionsDir . 'linkspage_manage.php';
require_once $g2mlLpagComponentDir . 'linkspage_resolver.php';
require_once $g2mlLpagComponentDir . 'linkspage_renderer.php';

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
function g2ml_lpag_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Build a unique marker string for this test run.
 *
 * @param  string $label
 * @return string
 */
function g2ml_lpag_test_marker(string $label): string
{
    static $counter = 0;
    $counter++;

    return 'lpag' . $counter . '_' . $label . '_' . substr(hash('sha256', (string) microtime(true) . $label), 0, 8);
}

/**
 * Insert a throwaway user for the given org and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_lpag_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@lpag50.test';
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
 * Insert (or update, keyed on templateSlug) a throwaway SYSTEM template and
 * return its templateUID.
 *
 * @param  mysqli $db
 * @param  string $templateSlug
 * @return int
 */
function g2ml_lpag_test_insert_template(mysqli $db, string $templateSlug): int
{
    $templateName = 'Age Gate Test Template';

    $templateHTML = '<div class="lp-test">'
        . '<h1 class="lp-name">{{name}}</h1>'
        . '<div class="lp-links">{{links}}</div>'
        . '</div>';

    $templateCSS = '.lp-test { background: {{background}}; color: {{theme}}; }';

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageTemplates`
            (`templateSlug`, `templateName`, `templateHTML`, `templateCSS`, `isSystem`, `isActive`, `sortOrder`)
         VALUES (?, ?, ?, ?, 1, 1, 999)
         ON DUPLICATE KEY UPDATE
            `templateHTML` = VALUES(`templateHTML`),
            `templateCSS`  = VALUES(`templateCSS`),
            `isSystem`     = 1,
            `isActive`     = 1'
    );

    mysqli_stmt_bind_param($statement, 'ssss', $templateSlug, $templateName, $templateHTML, $templateCSS);
    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (tblLinksPageTemplates) failed: ' . $error);
    }

    mysqli_stmt_close($statement);

    $selectStatement = mysqli_prepare($db, 'SELECT templateUID FROM tblLinksPageTemplates WHERE templateSlug = ? LIMIT 1');
    mysqli_stmt_bind_param($selectStatement, 's', $templateSlug);
    mysqli_stmt_execute($selectStatement);
    $result = mysqli_stmt_get_result($selectStatement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($selectStatement);

    if (!is_array($row) || !isset($row['templateUID']))
    {
        throw new RuntimeException('Could not read back templateUID for slug: ' . $templateSlug);
    }

    return (int) $row['templateUID'];
}

/**
 * Insert a tblLinksPageItems row DIRECTLY (bypassing the manage layer), so a
 * test can set requiresAgeGate to an EXACT known value independent of the
 * adult-domain auto-flag logic under test elsewhere in this file.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @param  string $itemTitle
 * @param  string $itemURL
 * @param  int    $requiresAgeGate
 * @param  int    $sortOrder
 * @return int
 */
function g2ml_lpag_test_insert_item_raw(mysqli $db, int $pageUID, string $itemTitle, string $itemURL, int $requiresAgeGate, int $sortOrder): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageItems` (`pageUID`, `itemTitle`, `itemURL`, `requiresAgeGate`, `sortOrder`, `isActive`)
         VALUES (?, ?, ?, ?, ?, 1)'
    );

    mysqli_stmt_bind_param($statement, 'issii', $pageUID, $itemTitle, $itemURL, $requiresAgeGate, $sortOrder);
    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (tblLinksPageItems) failed: ' . $error);
    }

    $itemUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $itemUID;
}

/**
 * Delete a page (and, via FK_item_page ON DELETE CASCADE, its items) by
 * pageUID — a raw teardown helper, not the code under test.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @return void
 */
function g2ml_lpag_test_delete_page(mysqli $db, int $pageUID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblLinksPages` WHERE `pageUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $pageUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Shared fixtures: the free tier and the [default] org (idempotent).
// ----------------------------------------------------------------------------
g2ml_lpag_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_lpag_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlLpagOrgHandle    = '[default]';
$g2mlLpagTemplateUID  = g2ml_lpag_test_insert_template($db, 'linkspage-agegate-test-template');

// ============================================================================
// ✅ A page with a gated item resolves as gated, and the interstitial NEVER
//    reveals ANY item's destination — gated OR not (whole-page gate).
// ============================================================================

test('agegate: a page with a gated item is detected, and the pre-confirmation interstitial reveals NO item data at all', function () use ($db, $g2mlLpagTemplateUID): void
{
    $marker  = g2ml_lpag_test_marker('reveal');
    $slug    = $marker . '-slug';

    $pageTitle = 'Age Gate Reveal Test';

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPages` (`slug`, `pageTitle`, `templateUID`, `isPublished`, `isActive`) VALUES (?, ?, ?, 1, 1)'
    );
    mysqli_stmt_bind_param($statement, 'ssi', $slug, $pageTitle, $g2mlLpagTemplateUID);
    mysqli_stmt_execute($statement);
    $pageUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    $gatedItemTitle  = 'ADULT-MARKER-XYZ-' . $marker;
    $gatedItemURL    = 'https://example.com/adult-secret-destination-' . $marker;
    $normalItemTitle = 'NORMAL-MARKER-ABC-' . $marker;
    $normalItemURL   = 'https://example.com/normal-destination-' . $marker;

    g2ml_lpag_test_insert_item_raw($db, $pageUID, $gatedItemTitle, $gatedItemURL, 1, 1);
    g2ml_lpag_test_insert_item_raw($db, $pageUID, $normalItemTitle, $normalItemURL, 0, 2);

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');
    assert_same(2, count($model['items']), 'Both items must be returned by the resolver');

    assert_true(g2ml_linkspageHasAgeGatedItems($model['items']), 'A page with at least one requiresAgeGate=1 item must be detected as gated');

    // 🔒 Pre-confirmation: the interstitial takes ONLY the slug — no page
    // model at all — so NEITHER the gated item NOR the normal item's title
    // or URL can possibly appear in its output.
    $interstitialHTML = g2ml_linkspageRenderAgeGateInterstitial($slug);

    assert_false(str_contains($interstitialHTML, $gatedItemURL), 'The GATED item URL must be ABSENT from the pre-confirmation interstitial');
    assert_false(str_contains($interstitialHTML, $gatedItemTitle), 'The GATED item title must be ABSENT from the pre-confirmation interstitial');
    assert_false(str_contains($interstitialHTML, $normalItemURL), 'The whole-page gate means even the NON-gated item URL must be absent pre-confirmation');
    assert_false(str_contains($interstitialHTML, $normalItemTitle), 'The whole-page gate means even the NON-gated item title must be absent pre-confirmation');
    assert_contains('Age Verification Required', $interstitialHTML, 'The interstitial itself must render its own generic heading');

    // ✅ Post-confirmation (simulated): once a valid cookie is present,
    // index.php calls g2ml_renderLinksPage() with the FULL model instead —
    // both items (including the gated one) now render normally.
    $normalHTML = g2ml_renderLinksPage($model);

    assert_contains($gatedItemURL, $normalHTML, 'Once confirmed, the previously-gated item URL must render normally');
    assert_contains($gatedItemTitle, $normalHTML, 'Once confirmed, the previously-gated item title must render normally');
    assert_contains($normalItemURL, $normalHTML, 'The non-gated item must also render normally');

    g2ml_lpag_test_delete_page($db, $pageUID);
});

// ============================================================================
// ✅ A normal (no-gate) page is unaffected
// ============================================================================

test('agegate: a page with no gated items is NOT flagged — normal pages are unaffected', function () use ($db, $g2mlLpagTemplateUID): void
{
    $marker = g2ml_lpag_test_marker('normal');
    $slug   = $marker . '-slug';

    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPages` (`slug`, `pageTitle`, `templateUID`, `isPublished`, `isActive`) VALUES (?, ?, ?, 1, 1)'
    );
    $pageTitle = 'Normal Page Test';
    mysqli_stmt_bind_param($statement, 'ssi', $slug, $pageTitle, $g2mlLpagTemplateUID);
    mysqli_stmt_execute($statement);
    $pageUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    g2ml_lpag_test_insert_item_raw($db, $pageUID, 'Normal Link One', 'https://example.com/one', 0, 1);
    g2ml_lpag_test_insert_item_raw($db, $pageUID, 'Normal Link Two', 'https://example.com/two', 0, 2);

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');
    assert_false(g2ml_linkspageHasAgeGatedItems($model['items']), 'A page with zero gated items must never be flagged — no interstitial would be shown for it');

    g2ml_lpag_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🔞 Auto-flag on ADD — a known adult domain is force-gated even when the
//    owner's checkbox was left unchecked; a normal domain is not; the owner
//    CAN explicitly opt a non-adult domain in.
// ============================================================================

test('agegate manage: adding an item pointing at a curated adult domain auto-flags requiresAgeGate=1, even unchecked', function () use ($db, $g2mlLpagOrgHandle): void
{
    $marker  = g2ml_lpag_test_marker('autoflag');
    $userUID = g2ml_lpag_test_insert_user($db, $g2mlLpagOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpagOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Auto-Flag Test Page',
    ]);
    assert_true($pageResult['success'], 'Setup: page create must succeed');
    $pageUID = $pageResult['pageUID'];

    // Adult domain, checkbox NOT set — must still be force-flagged.
    $adultAddResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://onlyfans.com/' . $marker,
        'itemTitle' => 'Adult Domain Item',
    ]);
    assert_true($adultAddResult['success'], 'Adding a manual item at a curated adult domain must still succeed: ' . ($adultAddResult['error'] ?? ''));

    // Normal domain, checkbox NOT set — must stay unflagged.
    $normalAddResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/' . $marker,
        'itemTitle' => 'Normal Domain Item',
    ]);
    assert_true($normalAddResult['success'], 'Adding a manual item at a normal domain must succeed');

    // Normal domain, owner EXPLICITLY checks the box — full manual control.
    $ownerFlaggedResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'          => 'manual',
        'manualURL'       => 'https://example.org/' . $marker,
        'itemTitle'       => 'Owner-Flagged Item',
        'requiresAgeGate' => true,
    ]);
    assert_true($ownerFlaggedResult['success'], 'Adding a manual item with the owner explicitly checking the box must succeed');

    $items = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    assert_same(3, count($items), 'All three items must have been added');

    $itemsByTitle = [];

    foreach ($items as $item)
    {
        $itemsByTitle[$item['itemTitle']] = $item;
    }

    assert_same(1, (int) $itemsByTitle['Adult Domain Item']['requiresAgeGate'], 'A curated adult-domain destination must be auto-flagged, even with the checkbox unchecked');
    assert_same(0, (int) $itemsByTitle['Normal Domain Item']['requiresAgeGate'], 'A normal destination with the checkbox unchecked must NOT be flagged');
    assert_same(1, (int) $itemsByTitle['Owner-Flagged Item']['requiresAgeGate'], 'The owner must be able to explicitly opt a normal destination in');

    g2ml_lpag_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🔓 Owner toggle on UPDATE — full control for a normal domain; the
//    auto-flag STAYS force-enabled while the destination remains adult.
// ============================================================================

test('agegate manage: the owner can freely toggle a normal domain\'s gate, but cannot silently disable it while the destination stays a known adult domain', function () use ($db, $g2mlLpagOrgHandle): void
{
    $marker  = g2ml_lpag_test_marker('toggle');
    $userUID = g2ml_lpag_test_insert_user($db, $g2mlLpagOrgHandle, $marker);

    $pageResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpagOrgHandle, [
        'slug'      => $marker . '-slug',
        'pageTitle' => 'Toggle Test Page',
    ]);
    $pageUID = $pageResult['pageUID'];

    $normalAdd = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://example.com/' . $marker . '-normal',
        'itemTitle' => 'Togglable Item',
    ]);
    assert_true($normalAdd['success'], 'Setup: the normal item must be added');
    $normalItemUID = $normalAdd['itemUID'];

    $adultAdd = g2ml_linkspageManageAddItem($userUID, $pageUID, [
        'source'    => 'manual',
        'manualURL' => 'https://fansly.com/' . $marker,
        'itemTitle' => 'Sticky Adult Item',
    ]);
    assert_true($adultAdd['success'], 'Setup: the adult-domain item must be added');
    $adultItemUID = $adultAdd['itemUID'];

    // Owner enables the gate manually for the NORMAL item.
    $enableResult = g2ml_linkspageManageUpdateItem($userUID, $normalItemUID, [
        'itemTitle'       => 'Togglable Item',
        'manualURL'       => 'https://example.com/' . $marker . '-normal',
        'requiresAgeGate' => true,
    ]);
    assert_true($enableResult['success'], 'The owner\'s manual enable must succeed');

    $afterEnable = g2ml_linkspageManageGetItemForOwner($normalItemUID, $userUID);
    assert_same(1, (int) $afterEnable['requiresAgeGate'], 'The normal item must now be gated (owner opted in)');

    // Owner disables it again — full control, since the domain is not adult.
    $disableResult = g2ml_linkspageManageUpdateItem($userUID, $normalItemUID, [
        'itemTitle'       => 'Togglable Item',
        'manualURL'       => 'https://example.com/' . $marker . '-normal',
        'requiresAgeGate' => false,
    ]);
    assert_true($disableResult['success'], 'The owner\'s manual disable must succeed');

    $afterDisable = g2ml_linkspageManageGetItemForOwner($normalItemUID, $userUID);
    assert_same(0, (int) $afterDisable['requiresAgeGate'], 'The owner must be able to freely toggle the gate off for a normal domain');

    // Owner tries to uncheck the gate on the ADULT-domain item — the
    // destination is unchanged, so the auto-flag must stay forced on.
    $stickyAttempt = g2ml_linkspageManageUpdateItem($userUID, $adultItemUID, [
        'itemTitle'       => 'Sticky Adult Item',
        'manualURL'       => 'https://fansly.com/' . $marker,
        'requiresAgeGate' => false,
    ]);
    assert_true($stickyAttempt['success'], 'The edit itself must still succeed (title/description changes are allowed)');

    $afterStickyAttempt = g2ml_linkspageManageGetItemForOwner($adultItemUID, $userUID);
    assert_same(1, (int) $afterStickyAttempt['requiresAgeGate'], 'The gate must stay force-enabled while the destination remains a known adult domain, regardless of the checkbox');

    g2ml_lpag_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🌐 g2ml_isAdultDomain() against the operator-configurable setting (real DB)
// ============================================================================

test('agegate: g2ml_isAdultDomain() honours an operator-configured override of linkspage.adult_domains, then restores the seeded default', function () use ($db): void
{
    // Sanity: the seeded default list (003_default_settings.sql) must already
    // make a well-known curated domain match, and an arbitrary domain not.
    assert_true(g2ml_isAdultDomain('https://onlyfans.com/x'), 'Sanity: the seeded default list must match a well-known curated domain');
    assert_false(g2ml_isAdultDomain('https://custom-adult-example.test/x'), 'Sanity: an arbitrary test domain must not match before any override');

    $existingRow = dbSelectOne(
        "SELECT settingValue FROM tblSettings WHERE settingID = 'linkspage.adult_domains' AND settingScope = 'Default' AND settingScopeRef IS NULL LIMIT 1",
        '',
        []
    );

    $originalValue = null;

    if (is_array($existingRow))
    {
        $originalValue = $existingRow['settingValue'];
    }

    // Override with a single, test-only domain.
    $overrideOk = setSetting('linkspage.adult_domains', json_encode(['custom-adult-example.test']), 'Default');
    assert_true($overrideOk, 'Setup: the setting override must be written successfully');

    loadSettingsCache();

    assert_true(g2ml_isAdultDomain('https://custom-adult-example.test/x'), 'The operator-configured override domain must now match');
    assert_false(g2ml_isAdultDomain('https://onlyfans.com/x'), 'The curated built-in default must NOT match while an override is active (override REPLACES, not merges)');

    // Restore the original seeded value so sibling tests (and other
    // integration files) see the expected default list again. Falls back to
    // the hard-coded PHP default list if, for any reason, no seeded row was
    // found — guaranteeing restoration either way.
    if ($originalValue !== null)
    {
        setSetting('linkspage.adult_domains', json_decode($originalValue, true), 'Default');
    }
    else
    {
        setSetting('linkspage.adult_domains', g2ml_adultDomainDefaultList(), 'Default');
    }

    loadSettingsCache();

    assert_true(g2ml_isAdultDomain('https://onlyfans.com/x'), 'Teardown: the seeded default list must be restored for any tests that run after this one');
});
