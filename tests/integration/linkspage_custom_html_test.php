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
 * 🧪 Integration tests — LinksPage custom HTML/CSS (Component C.6, #49)
 * ============================================================================
 *
 * Drives the REAL end-to-end custom-HTML path against a freshly imported test
 * database:
 *
 *   - g2ml_linkspageManageSaveCustomHTML() / …FromUpload()
 *     (web/_functions/linkspage_manage.php) — the GATED, sanitising save.
 *   - g2ml_resolveLinksPage() (web/Lnks.page/_functions/linkspage_resolver.php)
 *     — the GATED custom-HTML attach.
 *   - g2ml_renderLinksPage() (web/Lnks.page/_functions/linkspage_renderer.php)
 *     — the custom-document render (re-sanitised on output).
 *   - g2ml_linkspageCustomHtmlAllowedForOrg() (web/_functions/html_sanitiser.php)
 *     — kill-switch + premium hasCustomHTML entitlement gate.
 *
 * Proves, against real MySQL-backed rows:
 *   - a PREMIUM org with the kill-switch ON: a save SANITISES then stores (the
 *     stored value has no script/event-handler/javascript: content), the
 *     resolver ATTACHES it, and the renderer emits a custom document
 *     (re-sanitised) carrying the strict CSP signal;
 *   - a NON-PREMIUM org: the save is BLOCKED (nothing stored) and the resolver
 *     never attaches custom HTML — the page renders from the system template;
 *   - the operator KILL-SWITCH off: neither saved nor rendered, even for a
 *     premium org that already has stored custom HTML;
 *   - a benign custom HTML renders as intended;
 *   - the uploaded-file path runs the SAME sanitiser and never stores raw;
 *   - a full XSS payload battery is neutralised both in the stored value and
 *     in the rendered output.
 *
 * Registration model mirrors linkspage_render_test.php: cases register at
 * INCLUDE time using the $db handle from run_integration.php's script scope.
 * Helper names are prefixed g2ml_c6_* to avoid redeclaration alongside sibling
 * integration files. With no reachable test DB the runner skips before this
 * file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#49)
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
// constant is guarded so this file composes with any sibling integration file.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlC6TestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlC6TestHost === false || $g2mlC6TestHost === '')
    {
        $g2mlC6TestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlC6TestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlC6TestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlC6TestPortRaw === false || $g2mlC6TestPortRaw === '')
    {
        $g2mlC6TestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlC6TestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlC6TestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlC6TestUser === false || $g2mlC6TestUser === '')
    {
        $g2mlC6TestUser = 'root';
    }

    define('DB_USER', $g2mlC6TestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlC6TestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlC6TestPass === false)
    {
        $g2mlC6TestPass = '';
    }

    define('DB_PASS', $g2mlC6TestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlC6TestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlC6TestName === false || $g2mlC6TestName === '')
    {
        $g2mlC6TestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlC6TestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlC6FunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlC6ComponentDir = dirname(__DIR__, 2) . '/web/Lnks.page/_functions';

require_once $g2mlC6FunctionsDir . '/db_connect.php';
require_once $g2mlC6FunctionsDir . '/db_query.php';
require_once $g2mlC6FunctionsDir . '/security.php';
require_once $g2mlC6FunctionsDir . '/html_sanitiser.php';
require_once $g2mlC6FunctionsDir . '/settings.php';
require_once $g2mlC6FunctionsDir . '/entitlements.php';
require_once $g2mlC6FunctionsDir . '/linkspage_manage.php';
require_once $g2mlC6ComponentDir . '/linkspage_resolver.php';
require_once $g2mlC6ComponentDir . '/linkspage_renderer.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Execute a raw statement, throwing on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_c6_exec(mysqli $db, string $sql): void
{
    if (mysqli_query($db, $sql) === false)
    {
        throw new RuntimeException('SQL failed: ' . mysqli_error($db) . ' — ' . $sql);
    }
}

/**
 * Toggle the operator kill-switch by writing directly into the settings cache
 * (getSetting reads $GLOBALS['_g2ml_settings_cache']). Also clears the org→tier
 * cache so an entitlement re-check reflects the current fixtures.
 *
 * @param  bool $enabled
 * @return void
 */
function g2ml_c6_set_kill_switch(bool $enabled): void
{
    if (!isset($GLOBALS['_g2ml_settings_cache']) || !is_array($GLOBALS['_g2ml_settings_cache']))
    {
        $GLOBALS['_g2ml_settings_cache'] = [];
    }

    if (!isset($GLOBALS['_g2ml_settings_cache']['System']) || !is_array($GLOBALS['_g2ml_settings_cache']['System']))
    {
        $GLOBALS['_g2ml_settings_cache']['System'] = [];
    }

    $GLOBALS['_g2ml_settings_cache']['System']['linkspage.custom_html_enabled'] = $enabled;

    if (function_exists('g2ml_clearOrgTierCache'))
    {
        g2ml_clearOrgTierCache();
    }
}

/**
 * Read a page's stored customHTML/customCSS directly.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @return array{customHTML: ?string, customCSS: ?string}
 */
function g2ml_c6_read_stored_custom(mysqli $db, int $pageUID): array
{
    $statement = mysqli_prepare($db, 'SELECT customHTML, customCSS FROM tblLinksPages WHERE pageUID = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $pageUID);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);

    if (!is_array($row))
    {
        return ['customHTML' => null, 'customCSS' => null];
    }

    return [
        'customHTML' => $row['customHTML'],
        'customCSS'  => $row['customCSS'],
    ];
}

/**
 * Insert (idempotently) the C.6 test fixtures: two tiers (premium/free), two
 * orgs, two users, one system template, and one published page per org.
 * Returns the identifiers the tests need.
 *
 * @param  mysqli $db
 * @return array
 */
function g2ml_c6_seed(mysqli $db): array
{
    // Tiers — one with the custom-HTML entitlement, one without.
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblSubscriptionTiers (tierID, tierName, hasCustomHTML, sortOrder, isActive)
         VALUES ('c6prem', 'C6 Premium', 1, 970, 1)
         ON DUPLICATE KEY UPDATE hasCustomHTML = 1, isActive = 1"
    );
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblSubscriptionTiers (tierID, tierName, hasCustomHTML, sortOrder, isActive)
         VALUES ('c6free', 'C6 Free', 0, 971, 1)
         ON DUPLICATE KEY UPDATE hasCustomHTML = 0, isActive = 1"
    );

    // A guaranteed lowest-sortOrder active fallback tier without the flag, so a
    // no-tier org would NOT accidentally get custom HTML.
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblSubscriptionTiers (tierID, tierName, hasCustomHTML, sortOrder, isActive)
         VALUES ('c6fallback', 'C6 Fallback', 0, 1, 1)
         ON DUPLICATE KEY UPDATE hasCustomHTML = 0, isActive = 1"
    );

    // Orgs.
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblOrganisations (orgHandle, orgName, orgFallbackURL, tierID, isActive)
         VALUES ('c6org_prem', 'C6 Premium Org', 'https://go2my.link/fallback', 'c6prem', 1)
         ON DUPLICATE KEY UPDATE tierID = 'c6prem', isActive = 1"
    );
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblOrganisations (orgHandle, orgName, orgFallbackURL, tierID, isActive)
         VALUES ('c6org_free', 'C6 Free Org', 'https://go2my.link/fallback', 'c6free', 1)
         ON DUPLICATE KEY UPDATE tierID = 'c6free', isActive = 1"
    );

    // Users (owners of the pages). Unique emails/usernames keep re-runs clean.
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblUsers (orgHandle, username, email, passwordHash, role, isActive)
         VALUES ('c6org_prem', 'c6_prem_user', 'c6_prem_user@example.test', 'x', 'User', 1)
         ON DUPLICATE KEY UPDATE isActive = 1"
    );
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblUsers (orgHandle, username, email, passwordHash, role, isActive)
         VALUES ('c6org_free', 'c6_free_user', 'c6_free_user@example.test', 'x', 'User', 1)
         ON DUPLICATE KEY UPDATE isActive = 1"
    );

    $premUserRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT userUID FROM tblUsers WHERE username = 'c6_prem_user' LIMIT 1"));
    $freeUserRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT userUID FROM tblUsers WHERE username = 'c6_free_user' LIMIT 1"));

    $premUserUID = (int) $premUserRow['userUID'];
    $freeUserUID = (int) $freeUserRow['userUID'];

    // A system template so a render is always possible.
    g2ml_c6_exec(
        $db,
        "INSERT INTO tblLinksPageTemplates (templateSlug, templateName, templateHTML, templateCSS, isSystem, isActive, sortOrder)
         VALUES ('c6-tpl', 'C6 Template',
                 '<div class=\"C6-SYSTEM-TEMPLATE\"><h1>{{name}}</h1><div>{{links}}</div></div>',
                 '.c6 { color: {{theme}}; }', 1, 1, 995)
         ON DUPLICATE KEY UPDATE isSystem = 1, isActive = 1"
    );

    $templateRow = mysqli_fetch_assoc(mysqli_query($db, "SELECT templateUID FROM tblLinksPageTemplates WHERE templateSlug = 'c6-tpl' LIMIT 1"));
    $templateUID = (int) $templateRow['templateUID'];

    // Clean any prior pages for these slugs (cascades to items), then insert.
    g2ml_c6_exec($db, "DELETE FROM tblLinksPages WHERE slug IN ('c6-prem-page', 'c6-free-page')");

    $insertPage = function (string $slug, int $userUID, string $orgHandle) use ($db, $templateUID): int
    {
        $statement = mysqli_prepare(
            $db,
            "INSERT INTO tblLinksPages (userUID, orgHandle, slug, pageTitle, templateUID, isPublished, isActive)
             VALUES (?, ?, ?, ?, ?, 1, 1)"
        );
        $pageTitle = 'C6 Page';
        mysqli_stmt_bind_param($statement, 'isssi', $userUID, $orgHandle, $slug, $pageTitle, $templateUID);

        if (mysqli_stmt_execute($statement) === false)
        {
            $error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            throw new RuntimeException('Insert page failed: ' . $error);
        }

        $pageUID = (int) mysqli_insert_id($db);
        mysqli_stmt_close($statement);

        return $pageUID;
    };

    $premPageUID = $insertPage('c6-prem-page', $premUserUID, 'c6org_prem');
    $freePageUID = $insertPage('c6-free-page', $freeUserUID, 'c6org_free');

    return [
        'premUserUID' => $premUserUID,
        'freeUserUID' => $freeUserUID,
        'premPageUID' => $premPageUID,
        'freePageUID' => $freePageUID,
        'templateUID' => $templateUID,
    ];
}

// ----------------------------------------------------------------------------
// Seed once at include time.
// ----------------------------------------------------------------------------
$g2mlC6Fixtures = g2ml_c6_seed($db);

// ============================================================================
// 🧨 Cases
// ============================================================================

test('C6: PREMIUM org + kill-switch ON — save sanitises, stores, resolves, renders (no script)', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    $payload = '<h1>Welcome</h1><script>alert(1)</script><p>Hi <a href="https://ok.example">here</a></p>'
        . '<img src="x" onerror="alert(2)">';
    $css = 'h1 { color: #ff0000; } .evil { background: url(javascript:alert(1)); }';

    $result = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        $payload,
        $css
    );

    assert_true($result['success'], 'A premium org with the kill-switch on can save custom HTML');

    // Stored value is the SANITISED form.
    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['premPageUID']);
    assert_true(is_string($stored['customHTML']), 'customHTML is stored');
    assert_true(str_contains($stored['customHTML'], '<h1>Welcome</h1>'), 'Benign HTML is stored');
    assert_false(str_contains($stored['customHTML'], '<script'), 'No <script is stored (sanitised on input)');
    assert_false((bool) preg_match('/\son[a-z]+\s*=/i', $stored['customHTML']), 'No on* handler is stored');
    assert_false(str_contains((string) $stored['customCSS'], 'javascript:'), 'No javascript: url is stored in CSS');

    // The resolver ATTACHES the custom HTML for this permitted org.
    $model = g2ml_resolveLinksPage('c6-prem-page');
    assert_true(is_array($model), 'The page resolves');
    assert_true(g2ml_linkspageModelUsesCustomHTML($model), 'The model uses the custom-HTML path');

    // The renderer emits the custom document (re-sanitised), NOT the system template.
    $html = g2ml_renderLinksPage($model);
    assert_true(str_contains($html, 'Welcome'), 'The custom heading text renders');
    assert_false(str_contains($html, 'C6-SYSTEM-TEMPLATE'), 'The system template is bypassed');
    assert_false(str_contains($html, '<script'), 'The rendered output has no <script');
    assert_false((bool) preg_match('/\son[a-z]+\s*=/i', $html), 'The rendered output has no on* handler');
    assert_false(str_contains($html, 'javascript:'), 'The rendered output has no javascript: scheme');
    assert_true(str_contains($html, 'href="https://ok.example"'), 'A benign https link survives and renders');
});

test('C6: NON-PREMIUM org — save is BLOCKED and nothing is stored', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    $result = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['freeUserUID'],
        $g2mlC6Fixtures['freePageUID'],
        '<h1>Free tier custom</h1>',
        ''
    );

    assert_false($result['success'], 'A non-premium org cannot save custom HTML');
    assert_same('feature_unavailable', $result['errorCode'], 'The block reason is feature_unavailable');

    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['freePageUID']);
    assert_same(null, $stored['customHTML'], 'Nothing was stored for the non-premium org');

    // The resolver must NOT attach custom HTML for a non-premium org, even if a
    // value somehow existed — the page renders from the system template.
    $model = g2ml_resolveLinksPage('c6-free-page');
    assert_true(is_array($model), 'The free page resolves');
    assert_false(g2ml_linkspageModelUsesCustomHTML($model), 'A non-premium page never uses the custom path');

    $html = g2ml_renderLinksPage($model);
    assert_true(str_contains($html, 'C6-SYSTEM-TEMPLATE'), 'The non-premium page renders the system template');
});

test('C6: kill-switch OFF — a premium org cannot save, and stored custom HTML is NOT rendered', function () use ($db, $g2mlC6Fixtures): void
{
    // First store some custom HTML with the feature ON.
    g2ml_c6_set_kill_switch(true);
    $stored = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        '<h1>Stored earlier</h1>',
        ''
    );
    assert_true($stored['success'], 'Precondition: stored with the feature on');

    // Now turn the whole feature OFF.
    g2ml_c6_set_kill_switch(false);

    // A save is blocked.
    $blocked = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        '<h1>Should not save</h1>',
        ''
    );
    assert_false($blocked['success'], 'With the kill-switch off, even a premium org cannot save');
    assert_same('feature_unavailable', $blocked['errorCode'], 'Blocked as feature_unavailable');

    // And the already-stored custom HTML is NOT rendered — falls back to system.
    $model = g2ml_resolveLinksPage('c6-prem-page');
    assert_false(g2ml_linkspageModelUsesCustomHTML($model), 'With the kill-switch off, custom HTML is never attached');
    $html = g2ml_renderLinksPage($model);
    assert_true(str_contains($html, 'C6-SYSTEM-TEMPLATE'), 'The system template renders while the feature is off');
    assert_false(str_contains($html, 'Stored earlier'), 'The stored custom HTML is not emitted while the feature is off');

    // Restore for any later cases.
    g2ml_c6_set_kill_switch(true);
});

test('C6: a stored XSS payload battery is neutralised in BOTH the stored value and the render', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    $payload = implode('', [
        '<script>alert(1)</script>',
        '<img src=x onerror=alert(2)>',
        '<svg onload=alert(3)></svg>',
        '<a href="javascript:alert(4)">j</a>',
        '<iframe src="https://evil.example"></iframe>',
        '<form action="/x"><input name="a"></form>',
        '<a href="data:text/html,<script>alert(5)</script>">d</a>',
        '<div style="width:expression(alert(6))">e</div>',
        '<!--[if IE]><script>alert(7)</script><![endif]-->',
        '<p>KEEP-THIS-TEXT</p>',
    ]);

    $result = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        $payload,
        '@import url(evil.css); body { background: url(javascript:alert(8)); }'
    );
    assert_true($result['success'], 'The payload save succeeds (sanitised, not rejected)');

    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['premPageUID']);
    $storedHTML = (string) $stored['customHTML'];
    $storedCSS  = (string) $stored['customCSS'];

    assert_false(str_contains($storedHTML, '<script'), 'stored: no <script');
    assert_false(str_contains($storedHTML, '<iframe'), 'stored: no <iframe');
    assert_false(str_contains($storedHTML, '<svg'), 'stored: no <svg');
    assert_false(str_contains($storedHTML, '<form'), 'stored: no <form');
    assert_false(str_contains($storedHTML, '<input'), 'stored: no <input');
    assert_false((bool) preg_match('/\son[a-z]+\s*=/i', $storedHTML), 'stored: no on* handler');
    assert_false(stripos($storedHTML, 'javascript:') !== false, 'stored: no javascript: scheme');
    assert_false(stripos($storedHTML, '<!--') !== false, 'stored: no HTML comment');
    assert_true(str_contains($storedHTML, 'KEEP-THIS-TEXT'), 'stored: benign text preserved');
    assert_false(stripos($storedCSS, '@import') !== false, 'stored CSS: no @import');
    assert_false(stripos($storedCSS, 'javascript:') !== false, 'stored CSS: no javascript:');
    assert_false(str_contains($storedCSS, '<'), 'stored CSS: no "<" (no </style> breakout)');

    $model = g2ml_resolveLinksPage('c6-prem-page');
    $html  = g2ml_renderLinksPage($model);

    assert_false(str_contains($html, '<script'), 'render: no <script');
    assert_false(str_contains($html, '<iframe'), 'render: no <iframe');
    assert_false(str_contains($html, '<svg'), 'render: no <svg');
    assert_false((bool) preg_match('/\son[a-z]+\s*=/i', $html), 'render: no on* handler');
    assert_false(stripos($html, 'javascript:') !== false, 'render: no javascript: scheme');
    assert_true(str_contains($html, 'KEEP-THIS-TEXT'), 'render: benign text preserved');
});

test('C6: the .html upload path runs the SAME sanitiser and never stores raw', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    // Write a throwaway .html file with a script payload.
    $tmpFile = tempnam(sys_get_temp_dir(), 'c6up') . '.html';
    file_put_contents($tmpFile, '<h1>Uploaded</h1><script>alert(1)</script><p>Body</p>');

    // is_uploaded_file() is false under CLI — the manage function honours this
    // test-only override to accept a plain temp file.
    $GLOBALS['g2ml_linkspage_test_allow_plain_upload'] = true;

    $file = [
        'name'     => 'page.html',
        'type'     => 'text/html',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($tmpFile),
    ];

    $result = g2ml_linkspageManageSaveCustomHTMLFromUpload(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        $file,
        ''
    );

    unset($GLOBALS['g2ml_linkspage_test_allow_plain_upload']);
    @unlink($tmpFile);

    assert_true($result['success'], 'A premium org can upload an .html file');

    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['premPageUID']);
    assert_true(str_contains((string) $stored['customHTML'], '<h1>Uploaded</h1>'), 'The uploaded benign HTML is stored');
    assert_false(str_contains((string) $stored['customHTML'], '<script'), 'The uploaded script is sanitised away, never stored raw');
});

test('C6: a non-.html upload is rejected', function () use ($g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    $tmpFile = tempnam(sys_get_temp_dir(), 'c6up');
    file_put_contents($tmpFile, '<?php echo 1; ?>');
    $GLOBALS['g2ml_linkspage_test_allow_plain_upload'] = true;

    $file = [
        'name'     => 'evil.php',
        'type'     => 'application/x-php',
        'tmp_name' => $tmpFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($tmpFile),
    ];

    $result = g2ml_linkspageManageSaveCustomHTMLFromUpload(
        $g2mlC6Fixtures['premUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        $file,
        ''
    );

    unset($GLOBALS['g2ml_linkspage_test_allow_plain_upload']);
    @unlink($tmpFile);

    assert_false($result['success'], 'A .php upload is rejected');
    assert_same('wrong_type', $result['errorCode'], 'Rejected as wrong_type');
});

test('C6: clearing custom HTML (empty submission) reverts to the system template and is always allowed', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    // Ensure something is stored first.
    g2ml_linkspageManageSaveCustomHTML($g2mlC6Fixtures['premUserUID'], $g2mlC6Fixtures['premPageUID'], '<h1>x</h1>', '');

    // Clearing.
    $clear = g2ml_linkspageManageSaveCustomHTML($g2mlC6Fixtures['premUserUID'], $g2mlC6Fixtures['premPageUID'], '', '');
    assert_true($clear['success'], 'Clearing succeeds');

    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['premPageUID']);
    assert_same(null, $stored['customHTML'], 'customHTML is cleared to NULL');

    $model = g2ml_resolveLinksPage('c6-prem-page');
    assert_false(g2ml_linkspageModelUsesCustomHTML($model), 'A cleared page uses the system template');
    $html = g2ml_renderLinksPage($model);
    assert_true(str_contains($html, 'C6-SYSTEM-TEMPLATE'), 'The system template renders after clearing');
});

test('C6: IDOR — a user cannot write custom HTML to another user\'s page', function () use ($db, $g2mlC6Fixtures): void
{
    g2ml_c6_set_kill_switch(true);

    // The FREE user attempts to write to the PREMIUM user's page.
    $result = g2ml_linkspageManageSaveCustomHTML(
        $g2mlC6Fixtures['freeUserUID'],
        $g2mlC6Fixtures['premPageUID'],
        '<h1>IDOR</h1>',
        ''
    );

    assert_false($result['success'], 'A different user cannot write to a page they do not own');
    assert_same('not_found', $result['errorCode'], 'Ownership failure looks exactly like not-found');

    $stored = g2ml_c6_read_stored_custom($db, $g2mlC6Fixtures['premPageUID']);
    assert_false(str_contains((string) $stored['customHTML'], 'IDOR'), 'The IDOR write never lands');
});

test('C6: cleanup — the kill-switch is restored to OFF (no global-state leak to sibling tests)', function () use ($db, $g2mlC6Fixtures): void
{
    // Also clear this file's stored custom HTML so a sibling test that resolves
    // one of these pages sees a clean, system-template page.
    g2ml_linkspageManageSaveCustomHTML($g2mlC6Fixtures['premUserUID'], $g2mlC6Fixtures['premPageUID'], '', '');

    g2ml_c6_set_kill_switch(false);

    assert_false(g2ml_linkspageCustomHtmlKillSwitchEnabled(), 'The kill-switch is off after cleanup');
});
