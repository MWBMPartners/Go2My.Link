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
 * 🧪 Integration tests — LinksPage renderer (Component C, #45)
 * ============================================================================
 *
 * Drives the REAL functions under test against a freshly imported test
 * database:
 *
 *   - g2ml_resolveLinksPage()  (web/Lnks.page/_functions/linkspage_resolver.php)
 *     — the published-page + system-template + active-items lookup.
 *   - g2ml_renderLinksPage()   (web/Lnks.page/_functions/linkspage_renderer.php)
 *     — the 7-placeholder substitution engine and full document assembly.
 *
 * Proves, against a real MySQL-backed page row:
 *   - the substituted name/bio/links appear in the rendered HTML;
 *   - an XSS payload in pageTitle/itemTitle is escaped (no raw <script>);
 *   - a javascript: itemURL is rejected — the item (and its title) never
 *     appears in the output at all, not merely with a stripped href;
 *   - an invalid (non-hex) themeColour is never injected — the safe built-in
 *     default is used instead;
 *   - an unpublished page, and a slug that was never created, both resolve
 *     to null (the caller's cue to serve a branded 404);
 *   - customHTML stored on the page row is NEVER emitted, even though it is
 *     present in the database — because the resolver never SELECTs it.
 *
 * Registration model mirrors cuercode_test.php: cases register at INCLUDE
 * time using the $db handle from run_integration.php's script scope. Helper
 * names are prefixed g2ml_linkspage_test_* to avoid redeclaration alongside
 * sibling integration files. With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#45)
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
// constant is guarded individually so this file composes correctly with any
// sibling integration file that already defined them (run_integration.php
// glob()s and require_once's every tests/integration/*.php file in
// alphabetical order).
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlLinkspageTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLinkspageTestHost === false || $g2mlLinkspageTestHost === '')
    {
        $g2mlLinkspageTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLinkspageTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLinkspageTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLinkspageTestPortRaw === false || $g2mlLinkspageTestPortRaw === '')
    {
        $g2mlLinkspageTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlLinkspageTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlLinkspageTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLinkspageTestUser === false || $g2mlLinkspageTestUser === '')
    {
        $g2mlLinkspageTestUser = 'root';
    }

    define('DB_USER', $g2mlLinkspageTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLinkspageTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLinkspageTestPass === false)
    {
        $g2mlLinkspageTestPass = '';
    }

    define('DB_PASS', $g2mlLinkspageTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLinkspageTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLinkspageTestName === false || $g2mlLinkspageTestName === '')
    {
        $g2mlLinkspageTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLinkspageTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlLinkspageFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlLinkspageComponentDir = dirname(__DIR__, 2) . '/web/Lnks.page/_functions';

require_once $g2mlLinkspageFunctionsDir . '/db_connect.php';
require_once $g2mlLinkspageFunctionsDir . '/db_query.php';
require_once $g2mlLinkspageFunctionsDir . '/security.php';
require_once $g2mlLinkspageFunctionsDir . '/settings.php';
require_once $g2mlLinkspageComponentDir . '/linkspage_resolver.php';
require_once $g2mlLinkspageComponentDir . '/linkspage_renderer.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Insert (or update, keyed on templateSlug) a throwaway SYSTEM template and
 * return its templateUID. Using a dedicated test-only template — rather than
 * depending on the exact seeded content of the 5 production templates —
 * keeps this suite independent of future edits to
 * web/_sql/seeds/004_linkspage_templates.sql.
 *
 * @param  mysqli $db
 * @param  string $templateSlug
 * @return int
 */
function g2ml_linkspage_test_insert_template(mysqli $db, string $templateSlug): int
{
    $templateName = 'LinksPage Test Template';

    $templateHTML = '<div class="lp-test">'
        . '<img class="lp-avatar" src="{{avatar}}" alt="{{name}}">'
        . '<h1 class="lp-name">{{name}}</h1>'
        . '<p class="lp-bio">{{bio}}</p>'
        . '<div class="lp-links">{{links}}</div>'
        . '<div class="lp-social">{{social}}</div>'
        . '</div>';

    $templateCSS = '.lp-test { background: {{background}}; } .lp-test .lp-accent { color: {{theme}}; }';

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

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (tblLinksPageTemplates insert) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'ssss', $templateSlug, $templateName, $templateHTML, $templateCSS);

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (tblLinksPageTemplates) failed: ' . $error);
    }

    mysqli_stmt_close($statement);

    // ON DUPLICATE KEY UPDATE does not reliably report insert_id on every
    // MySQL version when the row already existed — look the UID up directly.
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
 * Delete any leftover tblLinksPages row for a slug (and its items, via
 * ON DELETE CASCADE) so the suite is repeatable across runs.
 *
 * @param  mysqli $db
 * @param  string $slug
 * @return void
 */
function g2ml_linkspage_test_delete_page(mysqli $db, string $slug): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblLinksPages` WHERE `slug` = ?');
    mysqli_stmt_bind_param($statement, 's', $slug);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Insert a tblLinksPages row and return its pageUID.
 *
 * @param  mysqli      $db
 * @param  string      $slug
 * @param  string      $pageTitle
 * @param  string|null $pageDescription
 * @param  int|null    $templateUID
 * @param  string|null $customHTML
 * @param  string|null $themeColour
 * @param  string|null $backgroundColour
 * @param  int         $showSocialIcons
 * @param  string|null $socialLinks
 * @param  int         $isPublished
 * @param  int         $isActive
 * @return int
 */
function g2ml_linkspage_test_insert_page(
    mysqli $db,
    string $slug,
    string $pageTitle,
    ?string $pageDescription,
    ?int $templateUID,
    ?string $customHTML = null,
    ?string $themeColour = null,
    ?string $backgroundColour = null,
    int $showSocialIcons = 0,
    ?string $socialLinks = null,
    int $isPublished = 1,
    int $isActive = 1
): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPages`
            (`slug`, `pageTitle`, `pageDescription`, `templateUID`, `customHTML`,
             `themeColour`, `backgroundColour`, `showSocialIcons`, `socialLinks`,
             `isPublished`, `isActive`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (tblLinksPages insert) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $statement,
        'sssisssisii',
        $slug,
        $pageTitle,
        $pageDescription,
        $templateUID,
        $customHTML,
        $themeColour,
        $backgroundColour,
        $showSocialIcons,
        $socialLinks,
        $isPublished,
        $isActive
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
 * Insert a tblLinksPageItems row.
 *
 * @param  mysqli      $db
 * @param  int         $pageUID
 * @param  string      $itemTitle
 * @param  string      $itemURL
 * @param  string|null $itemDescription
 * @param  int         $sortOrder
 * @param  int         $isActive
 * @return int
 */
function g2ml_linkspage_test_insert_item(
    mysqli $db,
    int $pageUID,
    string $itemTitle,
    string $itemURL,
    ?string $itemDescription,
    int $sortOrder,
    int $isActive = 1
): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageItems` (`pageUID`, `itemTitle`, `itemURL`, `itemDescription`, `sortOrder`, `isActive`)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (tblLinksPageItems insert) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 'isssii', $pageUID, $itemTitle, $itemURL, $itemDescription, $sortOrder, $isActive);

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

// ----------------------------------------------------------------------------
// Shared fixture: one throwaway SYSTEM template, reused by every test case.
// ----------------------------------------------------------------------------
$g2mlLinkspageTemplateUID = g2ml_linkspage_test_insert_template($db, 'linkspage-integration-test-template');

// ============================================================================
// ✅ Happy path — published page + items + a system template renders correctly
// ============================================================================

test('linkspageRender: a published page renders substituted name/bio/links, ordered by sortOrder', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-happy-path';
    g2ml_linkspage_test_delete_page($db, $slug);

    $pageUID = g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        'Ada Lovelace',
        'Mathematician & writer.',
        $g2mlLinkspageTemplateUID
    );

    // Inserted out of display order — the resolver must sort by sortOrder.
    g2ml_linkspage_test_insert_item($db, $pageUID, 'Second Link', 'https://example.com/second', null, 2);
    g2ml_linkspage_test_insert_item($db, $pageUID, 'First Link', 'https://example.com/first', 'A short description', 1);

    $model = g2ml_resolveLinksPage($slug);

    assert_true(is_array($model), 'A published page with a valid system template must resolve');
    assert_same(2, count($model['items']), 'Both active items must be returned');
    assert_same('First Link', $model['items'][0]['itemTitle'], 'Items must be ordered by sortOrder ascending');
    assert_same('Second Link', $model['items'][1]['itemTitle'], 'The higher sortOrder item must come second');

    $html = g2ml_renderLinksPage($model);

    assert_contains('Ada Lovelace', $html, 'The substituted name must appear in the rendered HTML');
    assert_contains('Mathematician &amp; writer.', $html, 'The bio must appear, HTML-escaped');
    assert_contains('First Link', $html, 'The first item title must appear');
    assert_contains('https://example.com/first', $html, 'The first item URL must appear as an href');
    assert_contains('Second Link', $html, 'The second item title must appear');
    assert_contains('A short description', $html, "An item's description must appear");

    g2ml_linkspage_test_delete_page($db, $slug);
});

// ============================================================================
// 🛡️ XSS payloads are escaped, never emitted raw
// ============================================================================

test('linkspageRender: an XSS payload in pageTitle and itemTitle is escaped, not emitted raw', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-xss';
    g2ml_linkspage_test_delete_page($db, $slug);

    $pageUID = g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        '<script>alert(1)</script>',
        null,
        $g2mlLinkspageTemplateUID
    );

    g2ml_linkspage_test_insert_item($db, $pageUID, '<img src=x onerror=alert(2)>', 'https://example.com/xss', null, 1);

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');

    $html = g2ml_renderLinksPage($model);

    assert_false(str_contains($html, '<script>alert(1)</script>'), 'The raw <script> tag must never appear in the output');
    assert_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'The pageTitle XSS payload must be HTML-escaped');

    assert_false(str_contains($html, '<img src=x onerror=alert(2)>'), 'The raw onerror payload must never appear in the output');
    assert_contains('&lt;img src=x onerror=alert(2)&gt;', $html, 'The itemTitle XSS payload must be HTML-escaped');

    g2ml_linkspage_test_delete_page($db, $slug);
});

// ============================================================================
// 🔗 A javascript: itemURL is rejected outright
// ============================================================================

test('linkspageRender: a javascript: itemURL is rejected — the item never appears in an href (or at all)', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-jsurl';
    g2ml_linkspage_test_delete_page($db, $slug);

    $pageUID = g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        'JS URL Test Page',
        null,
        $g2mlLinkspageTemplateUID
    );

    g2ml_linkspage_test_insert_item($db, $pageUID, 'DangerousLinkMarkerXYZ', 'javascript:alert(document.domain)', null, 1);
    g2ml_linkspage_test_insert_item($db, $pageUID, 'SafeLinkMarkerXYZ', 'https://example.com/safe', null, 2);

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');
    assert_same(2, count($model['items']), 'Both items are stored, even though one is unsafe — the RENDERER rejects it, not the resolver');

    $html = g2ml_renderLinksPage($model);

    assert_false(str_contains($html, 'javascript:'), 'The javascript: scheme must never appear anywhere in the rendered output');
    assert_false(str_contains($html, 'DangerousLinkMarkerXYZ'), 'An item with an unsafe URL must be omitted entirely, including its title');
    assert_contains('SafeLinkMarkerXYZ', $html, 'The sibling item with a safe https URL must still render');
    assert_contains('https://example.com/safe', $html, 'The safe item URL must appear as an href');

    g2ml_linkspage_test_delete_page($db, $slug);
});

// ============================================================================
// 🎨 An invalid themeColour is never injected
// ============================================================================

test('linkspageRender: an invalid (non-hex) themeColour is never injected — the safe default is used instead', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-badtheme';
    g2ml_linkspage_test_delete_page($db, $slug);

    // themeColour is VARCHAR(7) in the schema — "#ZZZZZZ" fits that width
    // while still failing the hex allowlist (Z is not a hex digit), so this
    // proves the REGEX rejects it, not merely that it was too long to store.
    g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        'Bad Theme Page',
        null,
        $g2mlLinkspageTemplateUID,
        null,
        '#ZZZZZZ'
    );

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');

    $html = g2ml_renderLinksPage($model);

    assert_false(str_contains($html, '#ZZZZZZ'), 'An invalid themeColour value must never be injected into the output');
    assert_contains(g2ml_linkspageDefaultThemeColour(), $html, 'The safe built-in default theme colour must be used instead');

    g2ml_linkspage_test_delete_page($db, $slug);
});

// ============================================================================
// 🚫 Unpublished / missing slugs resolve to null (caller serves a branded 404)
// ============================================================================

test('linkspageRender: an unpublished page resolves to null', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-unpublished';
    g2ml_linkspage_test_delete_page($db, $slug);

    g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        'Unpublished Page',
        null,
        $g2mlLinkspageTemplateUID,
        null,
        null,
        null,
        0,
        null,
        0 // isPublished = 0
    );

    $model = g2ml_resolveLinksPage($slug);
    assert_true($model === null, 'An unpublished page must resolve to null, not a partial page');

    g2ml_linkspage_test_delete_page($db, $slug);
});

test('linkspageRender: a slug that was never created resolves to null', function (): void
{
    $model = g2ml_resolveLinksPage('lptest-never-existed-marker-abc123');
    assert_true($model === null, 'A missing slug must resolve to null');
});

// ============================================================================
// 🔒 customHTML stored on the page is NEVER emitted
// ============================================================================

test('linkspageRender: customHTML stored on the page row is NEVER emitted, even though it exists in the database', function () use ($db, $g2mlLinkspageTemplateUID): void
{
    $slug = 'lptest-customhtml';
    g2ml_linkspage_test_delete_page($db, $slug);

    g2ml_linkspage_test_insert_page(
        $db,
        $slug,
        'Custom HTML Guard Page',
        'A normal bio',
        $g2mlLinkspageTemplateUID,
        '<div id="should-never-appear">INJECTED-CUSTOM-HTML-MARKER</div>'
    );

    $model = g2ml_resolveLinksPage($slug);
    assert_true(is_array($model), 'The page must resolve');
    assert_false(isset($model['page']['customHTML']), 'g2ml_resolveLinksPage() must never even SELECT customHTML');

    $html = g2ml_renderLinksPage($model);

    assert_false(str_contains($html, 'INJECTED-CUSTOM-HTML-MARKER'), 'customHTML content must never appear in the rendered output');
    assert_false(str_contains($html, 'should-never-appear'), 'customHTML content must never appear in the rendered output');
    assert_contains('Custom HTML Guard Page', $html, 'The normal, escaped fields must still render');

    g2ml_linkspage_test_delete_page($db, $slug);
});

// ----------------------------------------------------------------------------
// NOTE: the shared template fixture is deliberately NOT deleted here — see
// cuercode_test.php's identical note. The throwaway test database is torn
// down entirely after the suite runs.
// ----------------------------------------------------------------------------
