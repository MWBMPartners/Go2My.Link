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
 * 🧪 Integration tests — LinksPage template picker + owner preview (C.3, #47)
 * ============================================================================
 *
 * Drives the REAL functions against a freshly imported test database:
 *
 *   - g2ml_linkspageManageListSystemTemplates() (web/_functions/linkspage_manage.php)
 *     — the picker's template list: only ACTIVE system templates, now widened
 *     to include templateHTML/templateCSS for the live-render thumbnails.
 *   - g2ml_linkspageManageCreatePage() / g2ml_linkspageManageUpdatePage()
 *     — selecting a template on create/update persists templateUID.
 *   - g2ml_linkspageBuildOwnerPreviewModel() (web/Lnks.page/_functions/linkspage_resolver.php)
 *     + g2ml_renderLinksPage() (web/Lnks.page/_functions/linkspage_renderer.php)
 *     — the owner-only preview pipeline the admin route
 *     (pages/linkspage/preview/index.php) uses.
 *
 * Proves, against real MySQL-backed rows:
 *   - the picker lists ONLY active system templates (an inactive one is
 *     excluded), and every listed row carries templateHTML for a live render;
 *   - selecting a template on create, and switching it on update, persists
 *     templateUID exactly;
 *   - the preview pipeline renders an OWNER'S OWN UNPUBLISHED (draft) page —
 *     something the PUBLIC resolver (g2ml_resolveLinksPage(), isPublished = 1
 *     only) would never return;
 *   - a cross-user preview attempt is denied — g2ml_linkspageManageGetPageForOwner()
 *     (the SAME ownership gate the admin preview route calls) returns null
 *     for another user's pageUID, IDENTICALLY to a pageUID that does not
 *     exist (IDOR denied);
 *   - an XSS payload in pageTitle is HTML-escaped in the rendered output, not
 *     emitted raw;
 *   - stored customHTML (C.6, #49) is returned to its OWNER by
 *     g2ml_linkspageManageGetPageForOwner() (their own data, for the editor)
 *     but is NOT rendered into the preview unless the org is permitted
 *     (g2ml_linkspageCustomHtmlAllowedForOrg() — kill-switch + premium); with
 *     the feature off the preview falls back to the system template;
 *   - a page with no templateUID falls back to the default active system
 *     template, exactly like the public resolver.
 *
 * Registration model mirrors linkspage_manage_test.php / linkspage_render_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed
 * g2ml_lppreview_test_* to stay unique alongside sibling integration files
 * (this file deliberately does NOT reuse tests/integration/linkspage_render_test.php's
 * helpers, to keep each integration file self-contained). With no reachable
 * test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#47)
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
    $g2mlLpPreviewEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLpPreviewEnvHost === false || $g2mlLpPreviewEnvHost === '')
    {
        $g2mlLpPreviewEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLpPreviewEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLpPreviewEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLpPreviewEnvPort === false || $g2mlLpPreviewEnvPort === '')
    {
        $g2mlLpPreviewEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlLpPreviewEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlLpPreviewEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLpPreviewEnvUser === false || $g2mlLpPreviewEnvUser === '')
    {
        $g2mlLpPreviewEnvUser = 'root';
    }

    define('DB_USER', $g2mlLpPreviewEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLpPreviewEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLpPreviewEnvPass === false)
    {
        $g2mlLpPreviewEnvPass = '';
    }

    define('DB_PASS', $g2mlLpPreviewEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLpPreviewEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLpPreviewEnvName === false || $g2mlLpPreviewEnvName === '')
    {
        $g2mlLpPreviewEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLpPreviewEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlLpPreviewFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';
$g2mlLpPreviewComponentDir = dirname(__DIR__, 2) . '/web/Lnks.page/_functions/';

require_once $g2mlLpPreviewFunctionsDir . 'db_connect.php';
require_once $g2mlLpPreviewFunctionsDir . 'db_query.php';
require_once $g2mlLpPreviewFunctionsDir . 'security.php';
require_once $g2mlLpPreviewFunctionsDir . 'settings.php';
require_once $g2mlLpPreviewFunctionsDir . 'activity_logger.php';
require_once $g2mlLpPreviewFunctionsDir . 'entitlements.php';
require_once $g2mlLpPreviewFunctionsDir . 'linkspage_manage.php';
require_once $g2mlLpPreviewComponentDir . 'linkspage_resolver.php';
require_once $g2mlLpPreviewComponentDir . 'linkspage_renderer.php';

// ----------------------------------------------------------------------------
// Shared fixtures: the free tier and the [default] org (idempotent).
// ----------------------------------------------------------------------------
$g2mlLpPreviewExec = function (mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
};

$g2mlLpPreviewExec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

$g2mlLpPreviewExec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlLpPreviewOrgHandle = '[default]';

// ----------------------------------------------------------------------------
// Small DB helpers (prepared statements throughout, uniquely prefixed).
// ----------------------------------------------------------------------------

/**
 * Build a unique marker string for this test run.
 *
 * @param  string $label
 * @return string
 */
function g2ml_lppreview_test_marker(string $label): string
{
    static $counter = 0;
    $counter++;

    return 'lppv' . $counter . '_' . $label . '_' . substr(hash('sha256', (string) microtime(true) . $label), 0, 8);
}

/**
 * Insert a throwaway user and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_lppreview_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, 1)'
    );

    $username     = $marker;
    $email        = $marker . '@lppv47.test';
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
 * Insert a tblLinksPages row DIRECTLY (bypassing the manage layer's own input
 * sanitisation), so a payload such as an XSS pageTitle or a customHTML
 * sentinel can reach the row exactly as given — this is what proves the
 * RENDER path (not the input layer) is what is actually escaping/omitting
 * dangerous content in the owner preview.
 *
 * @param  mysqli      $db
 * @param  int         $userUID
 * @param  string      $orgHandle
 * @param  string      $slug
 * @param  string      $pageTitle
 * @param  int|null    $templateUID
 * @param  string|null $customHTML
 * @param  int         $isPublished
 * @return int  The new pageUID.
 */
function g2ml_lppreview_test_insert_page(
    mysqli $db,
    int $userUID,
    string $orgHandle,
    string $slug,
    string $pageTitle,
    ?int $templateUID,
    ?string $customHTML,
    int $isPublished
): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPages`
            (`userUID`, `orgHandle`, `slug`, `pageTitle`, `templateUID`, `customHTML`, `isPublished`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (tblLinksPages insert) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $statement,
        'isssisi',
        $userUID,
        $orgHandle,
        $slug,
        $pageTitle,
        $templateUID,
        $customHTML,
        $isPublished
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (tblLinksPages) failed: ' . $error);
    }

    $pageUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $pageUID;
}

/**
 * Insert a tblLinksPageItems row directly and return its itemUID.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @param  string $itemTitle
 * @param  string $itemURL
 * @return int
 */
function g2ml_lppreview_test_insert_item(mysqli $db, int $pageUID, string $itemTitle, string $itemURL): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageItems` (`pageUID`, `itemTitle`, `itemURL`) VALUES (?, ?, ?)'
    );

    mysqli_stmt_bind_param($statement, 'iss', $pageUID, $itemTitle, $itemURL);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
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
function g2ml_lppreview_test_delete_page(mysqli $db, int $pageUID): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblLinksPages` WHERE `pageUID` = ?');
    mysqli_stmt_bind_param($statement, 'i', $pageUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Look up an active SYSTEM template's templateUID by its slug (the 5 seeded
 * templates from web/_sql/seeds/004_linkspage_templates.sql).
 *
 * @param  mysqli $db
 * @param  string $templateSlug
 * @return int
 */
function g2ml_lppreview_test_lookup_template_uid(mysqli $db, string $templateSlug): int
{
    $statement = mysqli_prepare($db, 'SELECT templateUID FROM `tblLinksPageTemplates` WHERE templateSlug = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $templateSlug);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);

    if (!is_array($row) || !isset($row['templateUID']))
    {
        throw new RuntimeException('Could not find seeded template with slug: ' . $templateSlug);
    }

    return (int) $row['templateUID'];
}

// ============================================================================
// 🎨 Picker — g2ml_linkspageManageListSystemTemplates() lists only ACTIVE
//     system templates, and now carries templateHTML/templateCSS
// ============================================================================

test('picker: lists only active system templates, and every row includes templateHTML for the live-render thumbnail', function () use ($db): void
{
    $baselineTemplates = g2ml_linkspageManageListSystemTemplates();
    $baselineCount     = count($baselineTemplates);

    assert_true($baselineCount >= 5, 'The 5 seeded system templates (004_linkspage_templates.sql) must all be listed');

    foreach ($baselineTemplates as $templateRow)
    {
        assert_true(isset($templateRow['templateHTML']) && is_string($templateRow['templateHTML']) && $templateRow['templateHTML'] !== '', 'Every listed template must carry a non-empty templateHTML for the picker\'s live-render thumbnail: ' . ($templateRow['templateName'] ?? '?'));
        assert_true(array_key_exists('templateCSS', $templateRow), 'Every listed template row must include the templateCSS column');
        assert_true(array_key_exists('templateThumbnail', $templateRow), 'Every listed template row must include the templateThumbnail column (the fallback path)');
    }
});

test('picker: an INACTIVE system template is excluded from the picker list', function () use ($db, $g2mlLpPreviewExec): void
{
    $marker           = g2ml_lppreview_test_marker('inactive-template');
    $inactiveSlug     = $marker . '-slug';

    $g2mlLpPreviewExec(
        $db,
        "INSERT INTO `tblLinksPageTemplates` (`templateSlug`, `templateName`, `templateHTML`, `isSystem`, `isActive`, `sortOrder`) "
        . "VALUES ('" . $inactiveSlug . "', 'Inactive Test Template', '<div>{{name}}</div>', 1, 0, 999)"
    );

    $insertedUID = mysqli_insert_id($db);

    $templatesAfterInsert = g2ml_linkspageManageListSystemTemplates();

    $foundInactive = false;

    foreach ($templatesAfterInsert as $templateRow)
    {
        if ((int) $templateRow['templateUID'] === (int) $insertedUID)
        {
            $foundInactive = true;
        }
    }

    assert_false($foundInactive, 'An isActive = 0 system template row must NEVER appear in the picker list');

    $g2mlLpPreviewExec($db, 'DELETE FROM `tblLinksPageTemplates` WHERE `templateUID` = ' . (int) $insertedUID);
});

// ============================================================================
// 💾 Picker — selecting a template on create/update PERSISTS templateUID
// ============================================================================

test('picker: selecting a template on CREATE persists templateUID, and switching it on UPDATE persists the change', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    $defaultTemplateUID = g2ml_lppreview_test_lookup_template_uid($db, 'default');
    $modernTemplateUID  = g2ml_lppreview_test_lookup_template_uid($db, 'modern');

    $marker  = g2ml_lppreview_test_marker('picker-persist');
    $userUID = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $marker);

    $createResult = g2ml_linkspageManageCreatePage($userUID, $g2mlLpPreviewOrgHandle, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => 'Picker Persistence Test',
        'templateUID' => (string) $defaultTemplateUID,
    ]);

    assert_true($createResult['success'], 'A create with a valid templateUID must succeed: ' . ($createResult['error'] ?? ''));
    $pageUID = $createResult['pageUID'];

    $afterCreate = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same($defaultTemplateUID, (int) $afterCreate['templateUID'], 'Selecting a template on CREATE must persist the exact templateUID chosen');

    $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
        'slug'        => $marker . '-slug',
        'pageTitle'   => 'Picker Persistence Test',
        'templateUID' => (string) $modernTemplateUID,
    ]);

    assert_true($updateResult['success'], 'Switching to a different valid templateUID on UPDATE must succeed: ' . ($updateResult['error'] ?? ''));

    $afterUpdate = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    assert_same($modernTemplateUID, (int) $afterUpdate['templateUID'], 'Switching templates on UPDATE must persist the NEW templateUID');

    g2ml_lppreview_test_delete_page($db, $pageUID);
});

// ============================================================================
// 👁️ Owner preview — renders an OWNER'S OWN UNPUBLISHED page
// ============================================================================

test('owner preview: renders the owner\'s own UNPUBLISHED (draft) page — something the public resolver would never return', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    $marker  = g2ml_lppreview_test_marker('draft-preview');
    $userUID = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $marker);

    $draftPageUID = g2ml_lppreview_test_insert_page(
        $db,
        $userUID,
        $g2mlLpPreviewOrgHandle,
        $marker . '-slug',
        'My Draft Page',
        null,
        null,
        0 // isPublished = 0 — a draft.
    );

    g2ml_lppreview_test_insert_item($db, $draftPageUID, 'My Sample Link', 'https://example.com/sample');

    // Confirm the PUBLIC resolver genuinely refuses this page (proves the
    // draft state is real, and that the owner-preview path below is doing
    // something the public path cannot).
    $publicResolveAttempt = g2ml_resolveLinksPage($marker . '-slug');
    assert_same(null, $publicResolveAttempt, 'The PUBLIC resolver must refuse an unpublished page');

    // The exact pair of calls the admin preview route makes.
    $ownedPage  = g2ml_linkspageManageGetPageForOwner($draftPageUID, $userUID);
    $ownedItems = g2ml_linkspageManageListItemsForPage($draftPageUID, $userUID);

    assert_true(is_array($ownedPage), 'The owner must be able to load their own draft page for preview');
    assert_same(0, (int) $ownedPage['isPublished'], 'The loaded page must genuinely be a draft (isPublished = 0)');

    $previewModel = g2ml_linkspageBuildOwnerPreviewModel($ownedPage, $ownedItems);
    assert_true(is_array($previewModel), 'g2ml_linkspageBuildOwnerPreviewModel() must build a model for an owner-verified draft page');

    $renderedHTML = g2ml_renderLinksPage($previewModel);

    assert_contains('My Draft Page', $renderedHTML, 'The draft page\'s title must appear in the preview render');
    assert_contains('My Sample Link', $renderedHTML, 'The draft page\'s item must appear in the preview render');
    assert_contains('https://example.com/sample', $renderedHTML, 'The draft page\'s item URL must appear in the preview render');

    g2ml_lppreview_test_delete_page($db, $draftPageUID);
});

test('owner preview: an XSS payload in pageTitle is HTML-escaped in the rendered preview, never emitted raw', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    $marker  = g2ml_lppreview_test_marker('xss-preview');
    $userUID = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $marker);

    $xssPayload = '<script>alert(1)</script>';

    $pageUID = g2ml_lppreview_test_insert_page(
        $db,
        $userUID,
        $g2mlLpPreviewOrgHandle,
        $marker . '-slug',
        $xssPayload,
        null,
        null,
        0
    );

    $ownedPage  = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    $ownedItems = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);

    $previewModel = g2ml_linkspageBuildOwnerPreviewModel($ownedPage, $ownedItems);
    $renderedHTML = g2ml_renderLinksPage($previewModel);

    assert_false(str_contains($renderedHTML, $xssPayload), 'The raw <script> payload must NEVER appear unescaped in the preview render');
    assert_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $renderedHTML, 'The XSS payload must be present, but fully HTML-escaped');

    g2ml_lppreview_test_delete_page($db, $pageUID);
});

test('owner preview: stored customHTML is NOT rendered unless the org is permitted (C.6 #49 gate)', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    // 🧨 Contract change (C.6, #49): g2ml_linkspageManageGetPageForOwner() now
    // DOES return the owner's OWN customHTML/customCSS (the editor needs them to
    // prefill, and the preview to render them WHEN PERMITTED) — it is the
    // owner's own, ownership-enforced data, not a leak. The security guarantee
    // moved: g2ml_linkspageBuildOwnerPreviewModel() strips the customHTML from
    // the preview model UNLESS g2ml_linkspageCustomHtmlAllowedForOrg() is true
    // (operator kill-switch ON + premium hasCustomHTML entitlement). In this
    // suite the kill-switch is OFF (the sibling C.6 test resets it), so the
    // preview must fall back to the system template and NEVER emit the sentinel.
    $marker  = g2ml_lppreview_test_marker('customhtml-preview');
    $userUID = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $marker);

    $customHTMLSentinel = '<div id="should-never-appear-in-preview-customhtml-sentinel">C6 GATED CONTENT</div>';

    $pageUID = g2ml_lppreview_test_insert_page(
        $db,
        $userUID,
        $g2mlLpPreviewOrgHandle,
        $marker . '-slug',
        'CustomHTML Guard Test',
        null,
        $customHTMLSentinel,
        0
    );

    $ownedPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    // The owner CAN retrieve their own stored customHTML (for editing).
    assert_true(array_key_exists('customHTML', $ownedPage), 'The owner-verified row now includes the owner\'s own customHTML (for the C.6 editor/preview)');

    $ownedItems   = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    $previewModel = g2ml_linkspageBuildOwnerPreviewModel($ownedPage, $ownedItems);
    $renderedHTML = g2ml_renderLinksPage($previewModel);

    // With the feature NOT permitted (kill-switch off), the preview strips
    // customHTML and renders the system template — the sentinel never appears.
    assert_false(str_contains($renderedHTML, 'should-never-appear-in-preview-customhtml-sentinel'), 'An UNPERMITTED org\'s customHTML must NOT reach the rendered preview');
    assert_false(str_contains($renderedHTML, 'C6 GATED CONTENT'), 'The gated customHTML sentinel text must not appear when the feature is not permitted');

    g2ml_lppreview_test_delete_page($db, $pageUID);
});

test('owner preview: a page with no templateUID falls back to the default active system template, exactly like the public resolver', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    $marker  = g2ml_lppreview_test_marker('template-fallback');
    $userUID = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $marker);

    $pageUID = g2ml_lppreview_test_insert_page(
        $db,
        $userUID,
        $g2mlLpPreviewOrgHandle,
        $marker . '-slug',
        'No Template Chosen',
        null, // templateUID = NULL
        null,
        0
    );

    $ownedPage    = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
    $ownedItems   = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
    $previewModel = g2ml_linkspageBuildOwnerPreviewModel($ownedPage, $ownedItems);

    assert_true(is_array($previewModel), 'A page with no templateUID must still resolve a usable preview model (falling back to the default system template)');
    assert_true(isset($previewModel['template']['templateHTML']) && $previewModel['template']['templateHTML'] !== '', 'The fallback default template must carry a non-empty templateHTML');

    g2ml_lppreview_test_delete_page($db, $pageUID);
});

// ============================================================================
// 🔒 Owner preview — IDOR: a cross-user preview attempt is denied
// ============================================================================

test('owner preview: IDOR — user B cannot load user A\'s page for preview (denied identically to not-found)', function () use ($db, $g2mlLpPreviewOrgHandle): void
{
    $markerA = g2ml_lppreview_test_marker('idor-ownerA');
    $markerB = g2ml_lppreview_test_marker('idor-ownerB');
    $userA   = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $markerA);
    $userB   = g2ml_lppreview_test_insert_user($db, $g2mlLpPreviewOrgHandle, $markerB);

    $pageUID = g2ml_lppreview_test_insert_page(
        $db,
        $userA,
        $g2mlLpPreviewOrgHandle,
        $markerA . '-slug',
        'User A\'s Private Draft',
        null,
        null,
        0
    );

    // 🔒 This is EXACTLY the call the admin preview route
    // (pages/linkspage/preview/index.php) makes before it will ever build or
    // render a preview model. User B must get null — the SAME outcome as an
    // entirely non-existent pageUID — so the route can never distinguish
    // "exists but not yours" from "does not exist" in its response.
    $crossOwnerAttempt = g2ml_linkspageManageGetPageForOwner($pageUID, $userB);
    assert_same(null, $crossOwnerAttempt, 'User B must NOT be able to load user A\'s draft page for preview — this is the sole gate protecting the preview route from IDOR');

    $nonExistentAttempt = g2ml_linkspageManageGetPageForOwner(999999999, $userB);
    assert_same($crossOwnerAttempt, $nonExistentAttempt, 'A cross-owner pageUID and a wholly non-existent pageUID must be indistinguishable (both null) — no existence oracle');

    // The real owner can still preview their own page.
    $ownedByA = g2ml_linkspageManageGetPageForOwner($pageUID, $userA);
    assert_true(is_array($ownedByA), 'The real owner must still be able to load their own page for preview');

    g2ml_lppreview_test_delete_page($db, $pageUID);
});
