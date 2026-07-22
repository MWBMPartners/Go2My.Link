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
 * 🧪 Integration tests — custom-domain LinksPage fallback (Component C.4, #46)
 * ============================================================================
 *
 * Covers the whole feature end to end at the FUNCTION level (mirrors the
 * project's established convention — see custom_domain_verification_test.php
 * and shorturl_lookup_test.php, neither of which drives index.php over real
 * HTTP either; every existing hot-path test exercises resolveShortCode() /
 * the SP directly):
 *
 *   1. A verified domain with a designated PUBLISHED page: the fallback
 *      function renders that page (proxy for a root/bare-domain request).
 *   2. The SAME domain, with an unresolvable short code: resolveShortCode()
 *      returns 'not_found' (unchanged SP behaviour) AND the fallback
 *      function renders the page — together these prove index.php's
 *      `if ($status === 'not_found') { ... }` branch would render the page
 *      instead of showing 404.php.
 *   3. A verified domain with NO designated page: the fallback function
 *      returns false — index.php's existing 404 / fallback-redirect
 *      behaviour is completely untouched.
 *   4. A domain designated at a page that was PUBLISHED at designation time
 *      but has SINCE been unpublished: the fallback function still returns
 *      false — g2ml_resolveLinksPageByUID()'s independent isPublished/isActive
 *      re-check (defence-in-depth) means a draft can never be rendered
 *      through a custom domain, even via a stale designation.
 *   5. A normal, resolvable short code on the SAME custom domain still
 *      resolves via resolveShortCode() exactly as before (status success,
 *      correct destination) — proving the successful-redirect hot path is
 *      byte-for-byte unaffected by this feature.
 *   6. setShortDomainLinksPage() (the admin designation write path,
 *      web/_functions/org.php) rejects a pageUID belonging to ANOTHER
 *      organisation (IDOR) — the domain's linksPageUID is left untouched.
 *   7. setShortDomainLinksPage() + getOrgPublishedLinksPages() exercised
 *      directly for a legitimate same-org designation and a "None" clear.
 *
 * Registration model mirrors custom_domain_verification_test.php /
 * linkspage_render_test.php: cases register at INCLUDE time using the $db
 * handle from run_integration.php's script scope. Helper names are prefixed
 * g2ml_lpcdf_* to stay unique alongside the other integration files. With no
 * reachable test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#46)
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
    $g2mlLpcdfEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlLpcdfEnvHost === false || $g2mlLpcdfEnvHost === '')
    {
        $g2mlLpcdfEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlLpcdfEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlLpcdfEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlLpcdfEnvPort === false || $g2mlLpcdfEnvPort === '')
    {
        $g2mlLpcdfEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlLpcdfEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlLpcdfEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlLpcdfEnvUser === false || $g2mlLpcdfEnvUser === '')
    {
        $g2mlLpcdfEnvUser = 'root';
    }

    define('DB_USER', $g2mlLpcdfEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlLpcdfEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlLpcdfEnvPass === false)
    {
        $g2mlLpcdfEnvPass = '';
    }

    define('DB_PASS', $g2mlLpcdfEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlLpcdfEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlLpcdfEnvName === false || $g2mlLpcdfEnvName === '')
    {
        $g2mlLpcdfEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlLpcdfEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlLpcdfFunctionsDir  = dirname(__DIR__, 2) . '/web/_functions/';
$g2mlLpcdfComponentBDir = dirname(__DIR__, 2) . '/web/G2My.Link/_functions/';
$g2mlLpcdfComponentCDir = dirname(__DIR__, 2) . '/web/Lnks.page/_functions/';

require_once $g2mlLpcdfFunctionsDir . 'db_connect.php';
require_once $g2mlLpcdfFunctionsDir . 'db_query.php';
require_once $g2mlLpcdfFunctionsDir . 'security.php';
require_once $g2mlLpcdfFunctionsDir . 'settings.php';
require_once $g2mlLpcdfFunctionsDir . 'activity_logger.php';
require_once $g2mlLpcdfFunctionsDir . 'session.php';
require_once $g2mlLpcdfFunctionsDir . 'auth.php';
require_once $g2mlLpcdfFunctionsDir . 'org.php';
require_once $g2mlLpcdfFunctionsDir . 'entitlements.php';
require_once $g2mlLpcdfComponentBDir . 'domain_resolver.php';
require_once $g2mlLpcdfComponentBDir . 'redirect_resolver.php';
require_once $g2mlLpcdfComponentBDir . 'linkspage_fallback.php';
require_once $g2mlLpcdfComponentCDir . 'linkspage_resolver.php';
require_once $g2mlLpcdfComponentCDir . 'linkspage_renderer.php';

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
function g2ml_lpcdf_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Delete a tblOrgShortDomains row by shortDomain so tests are repeatable.
 *
 * @param  mysqli $db
 * @param  string $shortDomain
 * @return void
 */
function g2ml_lpcdf_delete_short_domain(mysqli $db, string $shortDomain): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblOrgShortDomains` WHERE `shortDomain` = ?');
    mysqli_stmt_bind_param($statement, 's', $shortDomain);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Insert a VERIFIED, ACTIVE tblOrgShortDomains row directly (bypassing the
 * DNS verification flow entirely, which is already covered end to end by
 * custom_domain_verification_test.php) and return its shortDomainUID.
 *
 * @param  mysqli   $db
 * @param  string   $orgHandle
 * @param  string   $shortDomain
 * @param  int|null $linksPageUID
 * @return int
 */
function g2ml_lpcdf_insert_verified_domain(mysqli $db, string $orgHandle, string $shortDomain, ?int $linksPageUID): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblOrgShortDomains` '
        . '(`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`, `verifiedAt`, `linksPageUID`) '
        . "VALUES (?, ?, 0, 1, 'verified', NOW(), ?)"
    );

    mysqli_stmt_bind_param($statement, 'ssi', $orgHandle, $shortDomain, $linksPageUID);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Insert (verified short domain) failed: ' . mysqli_error($db));
    }

    $shortDomainUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $shortDomainUID;
}

/**
 * Fetch a tblOrgShortDomains row's linksPageUID by shortDomain (null if the
 * row is absent, or if the column itself is NULL).
 *
 * @param  mysqli $db
 * @param  string $shortDomain
 * @return int|null|false  int = designated pageUID, null = no designation
 *                          (or column NULL), false = row not found at all.
 */
function g2ml_lpcdf_fetch_linkspage_uid(mysqli $db, string $shortDomain): int|null|false
{
    $statement = mysqli_prepare($db, 'SELECT `linksPageUID` FROM `tblOrgShortDomains` WHERE `shortDomain` = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $shortDomain);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null || $row === false)
    {
        return false;
    }

    if ($row['linksPageUID'] === null)
    {
        return null;
    }

    return (int) $row['linksPageUID'];
}

/**
 * Insert (or update, keyed on templateSlug) a throwaway SYSTEM template and
 * return its templateUID. Mirrors linkspage_render_test.php's own helper —
 * using a dedicated test-only template keeps this suite independent of
 * future edits to web/_sql/seeds/004_linkspage_templates.sql.
 *
 * @param  mysqli $db
 * @param  string $templateSlug
 * @return int
 */
function g2ml_lpcdf_insert_template(mysqli $db, string $templateSlug): int
{
    $templateName = 'LinksPage Custom-Domain Fallback Test Template';

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
 * Delete any leftover tblLinksPages row for a slug (and its items, via
 * ON DELETE CASCADE, and any tblOrgShortDomains row pointing at it, via
 * ON DELETE SET NULL) so the suite is repeatable across runs.
 *
 * @param  mysqli $db
 * @param  string $slug
 * @return void
 */
function g2ml_lpcdf_delete_page(mysqli $db, string $slug): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblLinksPages` WHERE `slug` = ?');
    mysqli_stmt_bind_param($statement, 's', $slug);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Insert a tblLinksPages row (org-scoped) and return its pageUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $slug
 * @param  string $pageTitle
 * @param  int    $templateUID
 * @param  int    $isPublished
 * @param  int    $isActive
 * @return int
 */
function g2ml_lpcdf_insert_page(
    mysqli $db,
    string $orgHandle,
    string $slug,
    string $pageTitle,
    int $templateUID,
    int $isPublished,
    int $isActive
): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPages`
            (`orgHandle`, `slug`, `pageTitle`, `templateUID`, `isPublished`, `isActive`)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    mysqli_stmt_bind_param($statement, 'sssiii', $orgHandle, $slug, $pageTitle, $templateUID, $isPublished, $isActive);
    $ok = mysqli_stmt_execute($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Insert (tblLinksPages) failed: ' . mysqli_error($db));
    }

    $pageUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $pageUID;
}

/**
 * Insert a tblLinksPageItems row for a page.
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @param  string $itemTitle
 * @param  string $itemURL
 * @return void
 */
function g2ml_lpcdf_insert_item(mysqli $db, int $pageUID, string $itemTitle, string $itemURL): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblLinksPageItems` (`pageUID`, `itemTitle`, `itemURL`, `isActive`) VALUES (?, ?, ?, 1)'
    );

    mysqli_stmt_bind_param($statement, 'iss', $pageUID, $itemTitle, $itemURL);
    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Insert (tblLinksPageItems) failed: ' . mysqli_error($db));
    }
}

/**
 * Set a tblLinksPages row's isPublished flag directly (simulating "the org
 * unpublished this page after designating it", independent of
 * g2ml_linkspageManageSetPublished()'s own ownership-checked path — this
 * file is testing the READ-time defence-in-depth, not the write path).
 *
 * @param  mysqli $db
 * @param  int    $pageUID
 * @param  int    $isPublished
 * @return void
 */
function g2ml_lpcdf_set_page_published(mysqli $db, int $pageUID, int $isPublished): void
{
    $statement = mysqli_prepare($db, 'UPDATE `tblLinksPages` SET `isPublished` = ? WHERE `pageUID` = ?');
    mysqli_stmt_bind_param($statement, 'ii', $isPublished, $pageUID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Delete every tblShortURLs row for a short code across all orgs.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_lpcdf_delete_shorturl(mysqli $db, string $shortCode): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ?');
    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Seed a minimal, active tblShortURLs row for a given org/code/destination.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $shortCode
 * @param  string $destinationURL
 * @return void
 */
function g2ml_lpcdf_seed_shorturl(mysqli $db, string $orgHandle, string $shortCode, string $destinationURL): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `createdByUserUID`, `isActive`, `createdAt`, `updatedAt`) '
        . "VALUES (?, ?, ?, 'url', NULL, 1, NOW(), NOW())"
    );
    mysqli_stmt_bind_param($statement, 'sss', $orgHandle, $shortCode, $destinationURL);
    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Seed shortURL INSERT failed: ' . mysqli_error($db));
    }
}

/**
 * Insert a throwaway user for the given org and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $username
 * @param  string $email
 * @return int
 */
function g2ml_lpcdf_insert_user(mysqli $db, string $orgHandle, string $username, string $email): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `role`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );

    $passwordHash = 'x';
    $role         = 'Admin';
    $isActive     = 1;

    mysqli_stmt_bind_param($statement, 'sssssi', $orgHandle, $username, $email, $passwordHash, $role, $isActive);
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
 * Insert an active, unexpired tblUserSessions row for a user.
 *
 * @param  mysqli $db
 * @param  int    $userUID
 * @param  string $tokenHash
 * @return void
 */
function g2ml_lpcdf_insert_session(mysqli $db, int $userUID, string $tokenHash): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUserSessions` '
        . '(`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $ipAddress  = '203.0.113.10';
    $userAgent  = 'IntegrationTest/1.0';
    $deviceInfo = 'Test on Test';
    $expiresAt  = date('Y-m-d H:i:s', time() + 3600);
    $isActive   = 1;

    mysqli_stmt_bind_param(
        $statement,
        'isssssi',
        $userUID,
        $tokenHash,
        $ipAddress,
        $userAgent,
        $deviceInfo,
        $expiresAt,
        $isActive
    );

    $ok = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if ($ok === false)
    {
        throw new RuntimeException('Insert (session) failed: ' . mysqli_error($db));
    }
}

/**
 * Seed a real, DB-backed authenticated session bound to a fresh throwaway
 * user, and bind $_SESSION so canManageOrg($orgHandle) / getCurrentUser()
 * see an Admin of $orgHandle. Mirrors custom_domain_verification_test.php's
 * g2ml_cdv_login_as() — validateUserSession() (called by isAuthenticated())
 * requires a genuine tblUserSessions row.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @return void
 */
function g2ml_lpcdf_login_as(mysqli $db, string $orgHandle): void
{
    static $counter = 0;
    $counter++;

    $marker   = 'lpcdf' . $counter . substr(hash('sha256', (string) microtime(true)), 0, 10);
    $username = $marker;
    $email    = $marker . '@lpcdf.test';

    $userUID = g2ml_lpcdf_insert_user($db, $orgHandle, $username, $email);

    $plainToken = 'plain_' . $marker;
    $tokenHash  = hash('sha256', $plainToken);
    g2ml_lpcdf_insert_session($db, $userUID, $tokenHash);

    $_SESSION['session_token']   = $plainToken;
    $_SESSION['user_uid']        = $userUID;
    $_SESSION['user_role']       = 'Admin';
    $_SESSION['user_org_handle'] = $orgHandle;
}

// ----------------------------------------------------------------------------
// Shared fixtures: two orgs (the domain-owning org, and a SEPARATE org used
// only to prove the IDOR defence), both on the free tier (seeded by
// 001_subscription_tiers.sql — no entitlement limits are exercised here).
// ----------------------------------------------------------------------------
g2ml_lpcdf_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) "
    . "VALUES ('lpcdftestorg', 'LinksPage Custom-Domain Fallback Test Org', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgName` = VALUES(`orgName`)"
);

g2ml_lpcdf_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `tierID`, `isActive`) "
    . "VALUES ('lpcdftestorgb', 'LinksPage Custom-Domain Fallback Test Org B', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgName` = VALUES(`orgName`)"
);

$g2mlLpcdfTemplateUID = g2ml_lpcdf_insert_template($db, 'lpcdf-test-template');

// ----------------------------------------------------------------------------
// 1. Root request (proxied by the fallback function) on a verified domain
//    with a designated PUBLISHED page renders that page.
// ----------------------------------------------------------------------------
test('g2ml_renderCustomDomainLinksPageFallback: a verified domain with a designated published page renders it (root request)', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-designated.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-designated-page');

    $pageUID = g2ml_lpcdf_insert_page($db, 'lpcdftestorg', 'lpcdf-designated-page', 'LPCDF Designated Page', $g2mlLpcdfTemplateUID, 1, 1);
    g2ml_lpcdf_insert_item($db, $pageUID, 'LPCDF Sample Link', 'https://example.com/lpcdf-sample-link');
    g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-designated.test', $pageUID);

    ob_start();
    $rendered = g2ml_renderCustomDomainLinksPageFallback('lpcdf-designated.test');
    $output   = ob_get_clean();

    assert_true($rendered, 'A verified domain with a designated published page is rendered, not falling through to 404');
    assert_contains('LPCDF Designated Page', $output, 'The designated page\'s own title appears in the rendered output');
    assert_contains('LPCDF Sample Link', $output, 'The designated page\'s own item appears in the rendered output');
    // NOTE: g2ml_renderCustomDomainLinksPageFallback() also resets the HTTP
    // status to 200 via http_response_code(), guarded by headers_sent() —
    // not asserted here because this shared CLI test process has already
    // echoed prior tests' PASS/FAIL output, so headers_sent() is already
    // true by the time THIS test runs (a CLI-harness artifact, not a
    // production behaviour — a real request reaches this code before any
    // output has been sent).

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-designated.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-designated-page');
});

// ----------------------------------------------------------------------------
// 2. An unresolvable code on that SAME domain: resolveShortCode() still
//    returns 'not_found' (unchanged SP behaviour) AND the fallback renders
//    the designated page instead of a 404 — together these are exactly what
//    index.php's `if ($status === 'not_found') { ... }` branch does.
// ----------------------------------------------------------------------------
test('resolver + fallback: an unresolvable code on a domain with a designated page still reports not_found, but the fallback renders the page (not 404)', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-notfound.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-notfound-page');
    g2ml_lpcdf_delete_shorturl($db, 'lpcdfnosuchcode');

    $pageUID = g2ml_lpcdf_insert_page($db, 'lpcdftestorg', 'lpcdf-notfound-page', 'LPCDF NotFound Fallback Page', $g2mlLpcdfTemplateUID, 1, 1);
    g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-notfound.test', $pageUID);

    $result = resolveShortCode('lpcdf-notfound.test', 'lpcdfnosuchcode');
    assert_same('not_found', $result['status'], 'The SP still reports not_found for a genuinely unresolvable code — unchanged behaviour');

    ob_start();
    $rendered = g2ml_renderCustomDomainLinksPageFallback('lpcdf-notfound.test');
    $output   = ob_get_clean();

    assert_true($rendered, 'The not_found branch renders the designated LinksPage instead of a 404');
    assert_contains('LPCDF NotFound Fallback Page', $output, 'The designated page\'s own title appears in the rendered output');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-notfound.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-notfound-page');
});

// ----------------------------------------------------------------------------
// 3. A verified domain with NO designated page: falls through unchanged
//    (existing 404 / fallback-redirect behaviour applies).
// ----------------------------------------------------------------------------
test('g2ml_renderCustomDomainLinksPageFallback: a verified domain with NO designated page renders nothing (existing 404 behaviour is unchanged)', function () use ($db): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-nopage.test');

    g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-nopage.test', null);

    ob_start();
    $rendered = g2ml_renderCustomDomainLinksPageFallback('lpcdf-nopage.test');
    $output   = ob_get_clean();

    assert_false($rendered, 'A domain with no designated page renders nothing at all');
    assert_same('', $output, 'Nothing is echoed when there is no designated page');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-nopage.test');
});

// ----------------------------------------------------------------------------
// 4. A domain designated at a page that has SINCE been unpublished: never
//    rendered (defence-in-depth re-check in g2ml_resolveLinksPageByUID()).
// ----------------------------------------------------------------------------
test('g2ml_renderCustomDomainLinksPageFallback: a designated page that has since been unpublished is NEVER rendered (404 instead)', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-unpublished.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-unpublished-page');

    // Designated while published (a legitimate designation at the time)...
    $pageUID = g2ml_lpcdf_insert_page($db, 'lpcdftestorg', 'lpcdf-unpublished-page', 'LPCDF Unpublished Page', $g2mlLpcdfTemplateUID, 1, 1);
    g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-unpublished.test', $pageUID);

    // ...then unpublished afterwards, WITHOUT clearing the domain's designation.
    g2ml_lpcdf_set_page_published($db, $pageUID, 0);

    ob_start();
    $rendered = g2ml_renderCustomDomainLinksPageFallback('lpcdf-unpublished.test');
    $output   = ob_get_clean();

    assert_false($rendered, 'A designated but now-unpublished page is NEVER rendered, even though the domain still points at it');
    assert_same('', $output, 'Nothing is echoed for the unpublished page');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-unpublished.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-unpublished-page');
});

// ----------------------------------------------------------------------------
// 5. A normal, resolvable short code on the SAME custom domain still
//    resolves exactly as before — the successful-redirect hot path is
//    untouched by this feature.
// ----------------------------------------------------------------------------
test('resolveShortCode: a normal short-code redirect on a custom domain with a designated LinksPage still redirects unchanged', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-normal.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-normal-page');
    g2ml_lpcdf_delete_shorturl($db, 'lpcdfnormalcode');

    $pageUID = g2ml_lpcdf_insert_page($db, 'lpcdftestorg', 'lpcdf-normal-page', 'LPCDF Normal Redirect Domain Page', $g2mlLpcdfTemplateUID, 1, 1);
    g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-normal.test', $pageUID);
    g2ml_lpcdf_seed_shorturl($db, 'lpcdftestorg', 'lpcdfnormalcode', 'https://example.com/lpcdf-normal-target');

    $result = resolveShortCode('lpcdf-normal.test', 'lpcdfnormalcode');

    assert_same('success', $result['status'], 'A normal, resolvable short code still resolves successfully');
    assert_same('https://example.com/lpcdf-normal-target', $result['destination'], 'It resolves to the correct, unchanged destination');
    assert_same('lpcdftestorg', $result['orgHandle'], 'It resolves under the correct org — the LinksPage designation does not interfere');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-normal.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-normal-page');
    g2ml_lpcdf_delete_shorturl($db, 'lpcdfnormalcode');
});

// ----------------------------------------------------------------------------
// 6. setShortDomainLinksPage() (the admin write path) rejects a pageUID
//    belonging to ANOTHER organisation — the actual IDOR defence.
// ----------------------------------------------------------------------------
test('setShortDomainLinksPage (#46 IDOR): rejects a pageUID belonging to a DIFFERENT organisation', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-idor.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-idor-other-org-page');

    // The victim domain belongs to lpcdftestorg...
    $domainUID = g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-idor.test', null);

    // ...but the targeted page belongs to a COMPLETELY DIFFERENT org.
    $otherOrgPageUID = g2ml_lpcdf_insert_page($db, 'lpcdftestorgb', 'lpcdf-idor-other-org-page', 'LPCDF Other Org Page', $g2mlLpcdfTemplateUID, 1, 1);

    g2ml_lpcdf_login_as($db, 'lpcdftestorg');

    $result = setShortDomainLinksPage($domainUID, 'lpcdftestorg', $otherOrgPageUID);

    assert_false($result['success'], 'A pageUID belonging to another organisation is rejected');
    assert_contains('not found', strtolower($result['error'] ?? ''), 'The rejection uses the generic not-found message, not a specific "wrong org" disclosure');

    $afterAttempt = g2ml_lpcdf_fetch_linkspage_uid($db, 'lpcdf-idor.test');
    assert_same(null, $afterAttempt, 'The domain\'s linksPageUID is left NULL — the write never happened despite the attempt');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-idor.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-idor-other-org-page');
});

// ----------------------------------------------------------------------------
// 7. setShortDomainLinksPage() + getOrgPublishedLinksPages() exercised
//    directly for a LEGITIMATE same-org designation, and clearing it back to
//    "None".
// ----------------------------------------------------------------------------
test('setShortDomainLinksPage: a legitimate same-org, published page is accepted, listed by getOrgPublishedLinksPages(), and can be cleared to None', function () use ($db, $g2mlLpcdfTemplateUID): void
{
    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-legit.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-legit-page');

    $domainUID = g2ml_lpcdf_insert_verified_domain($db, 'lpcdftestorg', 'lpcdf-legit.test', null);
    $pageUID   = g2ml_lpcdf_insert_page($db, 'lpcdftestorg', 'lpcdf-legit-page', 'LPCDF Legit Page', $g2mlLpcdfTemplateUID, 1, 1);

    g2ml_lpcdf_login_as($db, 'lpcdftestorg');

    $publishedPages = getOrgPublishedLinksPages('lpcdftestorg');
    $foundInPicker  = false;

    foreach ($publishedPages as $publishedPage)
    {
        if ((int) $publishedPage['pageUID'] === $pageUID)
        {
            $foundInPicker = true;
        }
    }

    assert_true($foundInPicker, 'The org\'s own published page is offered by the designation picker');

    $setResult = setShortDomainLinksPage($domainUID, 'lpcdftestorg', $pageUID);
    assert_true($setResult['success'], 'A legitimate same-org, published page designation succeeds');
    assert_same($pageUID, g2ml_lpcdf_fetch_linkspage_uid($db, 'lpcdf-legit.test'), 'The domain row is updated with the designated pageUID');

    $clearResult = setShortDomainLinksPage($domainUID, 'lpcdftestorg', null);
    assert_true($clearResult['success'], 'Clearing the designation back to "None" succeeds');
    assert_same(null, g2ml_lpcdf_fetch_linkspage_uid($db, 'lpcdf-legit.test'), 'The domain row\'s linksPageUID is cleared back to NULL');

    g2ml_lpcdf_delete_short_domain($db, 'lpcdf-legit.test');
    g2ml_lpcdf_delete_page($db, 'lpcdf-legit-page');
});
