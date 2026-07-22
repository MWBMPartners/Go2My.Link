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
 * 🧪 Integration tests — feature-entitlement / premium-tier gating layer (#146)
 * ============================================================================
 *
 * Drives the REAL functions from web/_functions/entitlements.php against a
 * freshly imported test database (see tests/README.md), covering:
 *
 *   (1) g2ml_getOrgTier() — the real tblOrganisations JOIN tblSubscriptionTiers
 *       query, end to end, for an org with an assigned tier.
 *   (2) g2ml_getOrgTier() — the real fallback query (lowest-sortOrder ACTIVE
 *       tier) for an org that has no row in tblOrganisations at all.
 *   (3) createShortURL(): a tight maxLinks tier blocks a NEW create once the
 *       org is already at its limit, without touching any existing link.
 *   (4) createShortURL(): GlobalAdmin and the '[default]' org are NEVER
 *       blocked, even against a tier/org that is already over its own limit.
 *   (5) createShortURL(): a SIMULATED entitlement-system lookup failure fails
 *       OPEN — the create proceeds despite the org being nominally over limit.
 *
 * Deliberately uses a DEDICATED test tier ('ent146tight', maxLinks=2) rather
 * than the real seeded 'free' tier, so the maxLinks boundary in cases (3)-(5)
 * is small, deterministic, and independent of whatever the live pricing
 * seed (web/_sql/seeds/001_subscription_tiers.sql) happens to allow. Case (2)
 * DOES rely on the real seeded 'free' tier (the lowest sortOrder, per that
 * same seed file) — that is the one assertion in this file that is
 * INTENTIONALLY coupled to the real seed, because "no-tier org -> Free
 * fallback" is a promise about the REAL install, not an arbitrary fixture.
 *
 * Registration model mirrors custom_domain_verification_test.php /
 * cuercode_test.php: cases register at INCLUDE time using the $db handle
 * from run_integration.php's script scope. Helper names are prefixed
 * g2ml_ent_* to stay unique alongside the other integration files. With no
 * reachable test DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.5.0 — Phase 11 (#146)
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
// Point the application DB layer (getDB) at the same throwaway server. These
// mirror run_integration.php's env resolution and are guarded so they compose
// with any other integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlEntEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlEntEnvHost === false || $g2mlEntEnvHost === '')
    {
        $g2mlEntEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlEntEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlEntEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlEntEnvPort === false || $g2mlEntEnvPort === '')
    {
        $g2mlEntEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlEntEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlEntEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlEntEnvUser === false || $g2mlEntEnvUser === '')
    {
        $g2mlEntEnvUser = 'root';
    }

    define('DB_USER', $g2mlEntEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlEntEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlEntEnvPass === false)
    {
        $g2mlEntEnvPass = '';
    }

    define('DB_PASS', $g2mlEntEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlEntEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlEntEnvName === false || $g2mlEntEnvName === '')
    {
        $g2mlEntEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlEntEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlEntFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlEntFunctionsDir . 'db_connect.php';
require_once $g2mlEntFunctionsDir . 'db_query.php';
require_once $g2mlEntFunctionsDir . 'security.php';
require_once $g2mlEntFunctionsDir . 'settings.php';
require_once $g2mlEntFunctionsDir . 'activity_logger.php';
require_once $g2mlEntFunctionsDir . 'session.php';
require_once $g2mlEntFunctionsDir . 'auth.php';
require_once $g2mlEntFunctionsDir . 'org.php';
require_once $g2mlEntFunctionsDir . 'entitlements.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

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
function g2ml_ent_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Delete every tblShortURLs row for an org, so a maxLinks count starts known.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @return void
 */
function g2ml_ent_delete_links(mysqli $db, string $orgHandle): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `orgHandle` = ?');
    mysqli_stmt_bind_param($statement, 's', $orgHandle);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Count ACTIVE tblShortURLs rows for an org (mirrors createShortURL()'s own
 * maxLinks count query).
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @return int
 */
function g2ml_ent_count_active_links(mysqli $db, string $orgHandle): int
{
    $statement = mysqli_prepare($db, 'SELECT COUNT(*) AS cnt FROM `tblShortURLs` WHERE `orgHandle` = ? AND `isActive` = 1');
    mysqli_stmt_bind_param($statement, 's', $orgHandle);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return (int) $row['cnt'];
}

/**
 * Insert a throwaway user for the given org/role and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $role
 * @param  string $marker
 * @return int
 */
function g2ml_ent_insert_user(mysqli $db, string $orgHandle, string $role, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `role`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?)'
    );

    $username     = $marker;
    $email        = $marker . '@ent146.test';
    $passwordHash = 'x';
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
function g2ml_ent_insert_session(mysqli $db, int $userUID, string $tokenHash): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUserSessions` '
        . '(`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $ipAddress  = '203.0.113.146';
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
 * user of the given role, and bind $_SESSION so getCurrentUser()/
 * hasMinimumRole() see it. Mirrors custom_domain_verification_test.php's
 * g2ml_cdv_login_as(), generalised to accept a role.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $role  One of the tblUsers.role ENUM values.
 * @return void
 */
function g2ml_ent_login_as(mysqli $db, string $orgHandle, string $role): void
{
    static $counter = 0;
    $counter++;

    $marker = 'ent' . $counter . substr(hash('sha256', (string) microtime(true)), 0, 10);

    $userUID = g2ml_ent_insert_user($db, $orgHandle, $role, $marker);

    $plainToken = 'plain_' . $marker;
    $tokenHash  = hash('sha256', $plainToken);
    g2ml_ent_insert_session($db, $userUID, $tokenHash);

    $_SESSION['session_token']   = $plainToken;
    $_SESSION['user_uid']        = $userUID;
    $_SESSION['user_role']       = $role;
    $_SESSION['user_org_handle'] = $orgHandle;
}

/**
 * Log out (clear) the simulated session, so a subsequent case starts
 * unauthenticated (the public/anonymous acting context).
 *
 * @return void
 */
function g2ml_ent_logout(): void
{
    unset($_SESSION['session_token'], $_SESSION['user_uid'], $_SESSION['user_role'], $_SESSION['user_org_handle']);
}

// ----------------------------------------------------------------------------
// Shared fixtures: a dedicated, tight test tier ('ent146tight', maxLinks=2)
// and its owning org ('ent146org'). The '[default]' org and the real 'free'
// tier are also asserted to exist (mirrors seed 002 / 001) since several
// other integration files already seed them identically — ON DUPLICATE KEY
// UPDATE makes this idempotent regardless of run order.
// ----------------------------------------------------------------------------
g2ml_ent_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

// sortOrder is set to a deliberately HIGH value (999): g2ml_getOrgTier()'s
// Free-tier FALLBACK query picks the lowest-sortOrder ACTIVE tier across the
// WHOLE tblSubscriptionTiers table (not scoped to this file's own fixtures),
// and the column's own DEFAULT is 0 — lower than the real seeded 'free'
// tier's sortOrder of 1. Leaving this test tier at the default would make IT
// win that global fallback query and corrupt case (2) below (and any other
// integration file in the same suite run asserting "no-tier org -> Free").
g2ml_ent_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `maxLinks`, `maxCustomDomains`, `maxAPIRequestsPerDay`, `maxLinksPages`, `sortOrder`, `isActive`) "
    . "VALUES ('ent146tight', 'Entitlements Test Tight Tier', 2, NULL, NULL, NULL, 999, 1) "
    . "ON DUPLICATE KEY UPDATE `maxLinks` = VALUES(`maxLinks`), `sortOrder` = VALUES(`sortOrder`)"
);

g2ml_ent_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_ent_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('ent146org', 'Entitlements Test Org', 'https://go2my.link/ent146org', 'ent146tight', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `tierID` = VALUES(`tierID`)"
);

// ============================================================================
// (1) g2ml_getOrgTier(): the REAL JOIN query, end to end.
// ============================================================================
test('g2ml_getOrgTier: resolves the real org->tier JOIN and normalises the row correctly', function () use ($db): void
{
    g2ml_ent_logout();
    g2ml_clearOrgTierCache('ent146org');

    $tier = g2ml_getOrgTier('ent146org');

    assert_same('ent146tight', $tier['tierID'], 'The org\'s own assigned tierID is resolved');
    assert_same(2, $tier['maxLinks'], 'maxLinks reflects the real seeded value for this tier');
    assert_same(null, $tier['maxCustomDomains'], 'A NULL tier column normalises to null (unlimited)');
    assert_false($tier['unlimited'], 'A real, active tier is never the unlimited sentinel');
    assert_false($tier['hasAnalytics'], 'A TINYINT(0) column normalises to strict false');

    g2ml_clearOrgTierCache('ent146org');
});

// ============================================================================
// (2) g2ml_getOrgTier(): the REAL fallback query for an org with NO row at
//     all in tblOrganisations — "no-tier org -> Free fallback".
// ============================================================================
test('g2ml_getOrgTier: an org with no tblOrganisations row falls back to the real Free tier', function () use ($db): void
{
    g2ml_ent_logout();
    g2ml_clearOrgTierCache('ent146-nonexistent-org');

    $tier = g2ml_getOrgTier('ent146-nonexistent-org');

    assert_same('free', $tier['tierID'], 'An org with no row resolves to the lowest-sortOrder ACTIVE tier (Free)');
    assert_false($tier['unlimited'], 'The Free fallback is a real, finite tier — never the unlimited sentinel');
    assert_true($tier['maxLinks'] !== null, 'The real Free tier has a finite maxLinks (per seed 001)');

    g2ml_clearOrgTierCache('ent146-nonexistent-org');
});

// ============================================================================
// (3) createShortURL(): a tight tier blocks a NEW create once the org is
//     already at its limit — existing links are never touched.
// ============================================================================
test('createShortURL (#146): a tight maxLinks tier blocks a new create once the org is at its limit', function () use ($db): void
{
    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');
    g2ml_ent_logout();

    $first = createShortURL('https://example.com/ent146-first', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_true($first['success'], 'The first link, within the tier limit of 2, is accepted');

    $second = createShortURL('https://example.com/ent146-second', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_true($second['success'], 'The second link, AT the tier limit of 2, is accepted');

    assert_same(2, g2ml_ent_count_active_links($db, 'ent146org'), 'Precondition: exactly 2 active links exist');

    $third = createShortURL('https://example.com/ent146-third', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_false($third['success'], 'A third link, OVER the tier limit of 2, is rejected');
    assert_true(($third['limitReached'] ?? false) === true, 'The rejection is flagged limitReached => true');
    assert_contains('limit', $third['error'] ?? '', 'The rejection error mentions the limit');

    assert_same(2, g2ml_ent_count_active_links($db, 'ent146org'), 'The rejected create wrote NO new row — the existing 2 links are untouched');

    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');
});

// ============================================================================
// (4) createShortURL(): GlobalAdmin and '[default]' are NEVER blocked, even
//     against an org/tier that is already over its own limit.
// ============================================================================
test('createShortURL (#146): a GlobalAdmin acting context is never blocked, even over the org\'s tier limit', function () use ($db): void
{
    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');

    // Fill the org to its limit (2) as a normal, unauthenticated create first.
    g2ml_ent_logout();
    createShortURL('https://example.com/ent146-ga-fill-1', ['orgHandle' => 'ent146org', 'userUID' => null]);
    createShortURL('https://example.com/ent146-ga-fill-2', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_same(2, g2ml_ent_count_active_links($db, 'ent146org'), 'Precondition: the org is already at its tier limit of 2');

    // A GlobalAdmin acting context must still be able to create for this org.
    g2ml_ent_login_as($db, '[default]', 'GlobalAdmin');
    g2ml_clearOrgTierCache('ent146org');

    $result = createShortURL('https://example.com/ent146-ga-over', ['orgHandle' => 'ent146org', 'userUID' => null]);

    assert_true($result['success'], 'GlobalAdmin creating for an org already OVER its tier limit must still succeed');
    assert_same(3, g2ml_ent_count_active_links($db, 'ent146org'), 'The GlobalAdmin create was NOT blocked and wrote a 3rd row');

    g2ml_ent_logout();
    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');
});

test('createShortURL (#146): the [default] org is never blocked by any tier limit', function () use ($db): void
{
    g2ml_ent_logout();
    g2ml_clearOrgTierCache('[default]');

    // '[default]' is always the unlimited sentinel regardless of how many
    // links already exist for it (other integration test files in this same
    // suite run may have created many).
    $result = createShortURL('https://example.com/ent146-default-org', ['orgHandle' => '[default]', 'userUID' => null]);

    assert_true($result['success'], 'A create for the [default] org is never blocked by maxLinks');

    if (is_string($result['shortCode']))
    {
        $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ? AND `orgHandle` = ?');
        $orgHandleLiteral = '[default]';
        mysqli_stmt_bind_param($statement, 'ss', $result['shortCode'], $orgHandleLiteral);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    g2ml_clearOrgTierCache('[default]');
});

// ============================================================================
// (5) createShortURL(): a SIMULATED entitlement-system lookup failure fails
//     OPEN — the create proceeds despite the org being nominally over limit.
// ============================================================================
test('createShortURL (#146): a simulated tier-lookup failure fails OPEN — the create still proceeds', function () use ($db): void
{
    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');
    g2ml_ent_logout();

    // Fill the org to its limit (2) for real, so a THIRD create would
    // genuinely be rejected under normal (working) entitlement resolution.
    createShortURL('https://example.com/ent146-fo-fill-1', ['orgHandle' => 'ent146org', 'userUID' => null]);
    createShortURL('https://example.com/ent146-fo-fill-2', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_same(2, g2ml_ent_count_active_links($db, 'ent146org'), 'Precondition: the org is genuinely at its tier limit of 2');

    // Confirm the control case first: WITHOUT the override, a 3rd create is
    // genuinely rejected (proves the fixture is really at its limit).
    $control = createShortURL('https://example.com/ent146-fo-control', ['orgHandle' => 'ent146org', 'userUID' => null]);
    assert_false($control['success'], 'Control: without a simulated failure, the 3rd create is genuinely rejected');
    assert_same(2, g2ml_ent_count_active_links($db, 'ent146org'), 'Control: the genuinely-rejected create wrote no row');

    // Now simulate a DB/query system failure resolving the org's tier.
    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array|null|false
    {
        return false;
    };

    g2ml_clearOrgTierCache('ent146org');

    $duringFailure = createShortURL('https://example.com/ent146-fo-during', ['orgHandle' => 'ent146org', 'userUID' => null]);

    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    g2ml_clearOrgTierCache('ent146org');

    assert_true($duringFailure['success'], 'A simulated entitlement-system lookup failure must fail OPEN — the create proceeds');
    assert_same(3, g2ml_ent_count_active_links($db, 'ent146org'), 'The fail-open create actually wrote its row');

    g2ml_ent_delete_links($db, 'ent146org');
    g2ml_clearOrgTierCache('ent146org');
});
