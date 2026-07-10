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
 * 🧪 Integration tests — custom short-domain ownership verification (#91)
 * ============================================================================
 *
 * Covers the whole ownership-verification lifecycle added to
 * tblOrgShortDomains: addOrgShortDomain() now creates a domain PENDING and
 * NOT routable; verifyOrgShortDomain() is the only path (besides migration
 * 013's one-time grandfather backfill) that ever flips it live; and
 * sp_lookupShortURL (via resolveShortCode()) must never let an
 * unverified/claimed domain resolve into the '[default]' org's namespace.
 *
 *   1. add            → pending, isActive=0, token + DNS records returned
 *   2. verify (correct TXT, injected) → verified, isActive=1, verifiedAt set
 *   3. verify (wrong TXT)             → failed, stays unroutable
 *   4. resolver: an unverified custom domain does NOT leak into '[default]'
 *      (the key regression this whole change exists to close)
 *   5. resolver: the system's own default host (g2my.link) is unaffected
 *   6. quota: org.max_short_domains is enforced (previously dead code)
 *   7. grandfather: migration 013's backfill marks a pre-existing isActive=1
 *      row verified, and the resolver then treats it as routable — proving
 *      the migration does not silently break already-live partner domains
 *
 * DNS is never actually queried: g2ml_lookupDnsTxtRecords() (org.php) checks
 * $GLOBALS['g2ml_dns_txt_lookup_override'] first, so tests 2 and 3 inject a
 * fake resolver rather than depending on real DNS propagation.
 *
 * Registration model mirrors cross_org_isolation_test.php / cuercode_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed g2ml_cdv_* to
 * stay unique alongside the other integration files. With no reachable test
 * DB the runner skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#91)
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
    $g2mlCdvEnvHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlCdvEnvHost === false || $g2mlCdvEnvHost === '')
    {
        $g2mlCdvEnvHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlCdvEnvHost);
}

if (!defined('DB_PORT'))
{
    $g2mlCdvEnvPort = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlCdvEnvPort === false || $g2mlCdvEnvPort === '')
    {
        $g2mlCdvEnvPort = '3306';
    }

    define('DB_PORT', (int) $g2mlCdvEnvPort);
}

if (!defined('DB_USER'))
{
    $g2mlCdvEnvUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlCdvEnvUser === false || $g2mlCdvEnvUser === '')
    {
        $g2mlCdvEnvUser = 'root';
    }

    define('DB_USER', $g2mlCdvEnvUser);
}

if (!defined('DB_PASS'))
{
    $g2mlCdvEnvPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlCdvEnvPass === false)
    {
        $g2mlCdvEnvPass = '';
    }

    define('DB_PASS', $g2mlCdvEnvPass);
}

if (!defined('DB_NAME'))
{
    $g2mlCdvEnvName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlCdvEnvName === false || $g2mlCdvEnvName === '')
    {
        $g2mlCdvEnvName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlCdvEnvName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application function files the code under test depends on.
// ----------------------------------------------------------------------------
$g2mlCdvFunctionsDir = dirname(__DIR__, 2) . '/web/_functions/';

require_once $g2mlCdvFunctionsDir . 'db_connect.php';
require_once $g2mlCdvFunctionsDir . 'db_query.php';
require_once $g2mlCdvFunctionsDir . 'security.php';
require_once $g2mlCdvFunctionsDir . 'settings.php';
require_once $g2mlCdvFunctionsDir . 'activity_logger.php';
require_once $g2mlCdvFunctionsDir . 'session.php';
require_once $g2mlCdvFunctionsDir . 'auth.php';
require_once $g2mlCdvFunctionsDir . 'org.php';
require_once $g2mlCdvFunctionsDir . 'entitlements.php';
require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/domain_resolver.php';
require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';

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
function g2ml_cdv_exec(mysqli $db, string $sql): void
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
function g2ml_cdv_delete_short_domain(mysqli $db, string $shortDomain): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblOrgShortDomains` WHERE `shortDomain` = ?');
    mysqli_stmt_bind_param($statement, 's', $shortDomain);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Fetch a tblOrgShortDomains row by shortDomain, or null if absent.
 *
 * @param  mysqli $db
 * @param  string $shortDomain
 * @return array|null
 */
function g2ml_cdv_fetch_short_domain(mysqli $db, string $shortDomain): ?array
{
    $statement = mysqli_prepare($db, 'SELECT * FROM `tblOrgShortDomains` WHERE `shortDomain` = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $shortDomain);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * Delete every tblShortURLs row for a short code across all orgs.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_cdv_delete_shorturl(mysqli $db, string $shortCode): void
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
function g2ml_cdv_seed_shorturl(mysqli $db, string $orgHandle, string $shortCode, string $destinationURL): void
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
function g2ml_cdv_insert_user(mysqli $db, string $orgHandle, string $username, string $email): int
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
function g2ml_cdv_insert_session(mysqli $db, int $userUID, string $tokenHash): void
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUserSessions` '
        . '(`userUID`, `sessionToken`, `ipAddress`, `userAgent`, `deviceInfo`, `expiresAt`, `isActive`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $ipAddress  = '203.0.113.9';
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
 * see an Admin of $orgHandle. Mirrors session_rebind_test.php's pattern —
 * validateUserSession() (called by isAuthenticated()) requires a genuine
 * tblUserSessions row, so there is no way to shortcut this via $_SESSION
 * alone.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @return void
 */
function g2ml_cdv_login_as(mysqli $db, string $orgHandle): void
{
    static $counter = 0;
    $counter++;

    $marker   = 'cdv' . $counter . substr(hash('sha256', (string) microtime(true)), 0, 10);
    $username = $marker;
    $email    = $marker . '@cdv.test';

    $userUID = g2ml_cdv_insert_user($db, $orgHandle, $username, $email);

    $plainToken = 'plain_' . $marker;
    $tokenHash  = hash('sha256', $plainToken);
    g2ml_cdv_insert_session($db, $userUID, $tokenHash);

    $_SESSION['session_token']   = $plainToken;
    $_SESSION['user_uid']        = $userUID;
    $_SESSION['user_role']       = 'Admin';
    $_SESSION['user_org_handle'] = $orgHandle;
}

/**
 * Set a Default-scope tblSettings value and reload the settings cache so
 * getSetting() sees it immediately. Mirrors
 * g2ml_cuercode_test_set_setting() in cuercode_test.php.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @param  string $value
 * @return void
 */
function g2ml_cdv_set_setting(mysqli $db, string $settingID, string $value): void
{
    $statement = mysqli_prepare($db, 'UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = ?');
    mysqli_stmt_bind_param($statement, 'ss', $value, $settingID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    loadSettingsCache();
}

// ----------------------------------------------------------------------------
// Shared fixtures: the free tier, [default] org + its verified g2my.link
// short domain (mirrors seed 002), and a second org ('cdvtestorg') to own the
// domains under test.
//
// cdvtestorg is deliberately assigned a TIER with an unlimited
// maxCustomDomains ('cdvunlimited', defined below), NOT the real seeded
// 'free' tier (whose maxCustomDomains is a genuine, intentional 0 per
// web/_sql/seeds/001_subscription_tiers.sql). This suite's own purpose is
// DNS-verification mechanics and the org.max_short_domains SETTING-only
// quota (#91) — both predate and are independent of the tier-based cap added
// by #146 (entitlements.php). A dedicated tier∩setting reconciliation test is
// added below (case 8) to cover #146's addOrgShortDomain() behaviour
// specifically, without coupling every pre-existing case in this file to
// whatever the live pricing tiers happen to allow.
// ----------------------------------------------------------------------------
g2ml_cdv_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

// sortOrder is set to a deliberately HIGH value (999): g2ml_getOrgTier()'s
// Free-tier FALLBACK query picks the lowest-sortOrder ACTIVE tier across the
// WHOLE tblSubscriptionTiers table (it is not scoped to this file's own
// fixtures), and the column's own DEFAULT is 0 — lower than the real seeded
// 'free' tier's sortOrder of 1. Leaving this test tier at the default would
// make IT win that global fallback query and corrupt any OTHER test/file in
// the same suite run that asserts a "no-tier org -> Free" resolution.
g2ml_cdv_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `maxLinks`, `maxCustomDomains`, `maxAPIRequestsPerDay`, `maxLinksPages`, `sortOrder`, `isActive`) "
    . "VALUES ('cdvunlimited', 'CDV Unlimited Test Tier', NULL, NULL, NULL, NULL, 999, 1) "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`), `sortOrder` = VALUES(`sortOrder`)"
);

g2ml_cdv_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_cdv_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('cdvtestorg', 'Custom Domain Verification Test Org', 'https://go2my.link/cdvtestorg', 'cdvunlimited', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `tierID` = VALUES(`tierID`)"
);

// The system's own default short domain, pre-verified — mirrors the fix made
// to web/_sql/seeds/002_default_organisation.sql in this same change.
g2ml_cdv_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`, `verificationStatus`, `verifiedAt`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1, 'verified', NOW()) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1, `verificationStatus` = 'verified', `verifiedAt` = COALESCE(`verifiedAt`, NOW())"
);

// Ensure org.max_short_domains starts unlimited (some other test run may have
// left it non-zero if it failed mid-way; belt and braces).
g2ml_cdv_set_setting($db, 'org.max_short_domains', '0');

// ----------------------------------------------------------------------------
// 1. add → pending, not routable, token + DNS records returned
// ----------------------------------------------------------------------------
test('addOrgShortDomain: a new domain starts pending and NOT routable, with a token and DNS records', function () use ($db): void
{
    g2ml_cdv_delete_short_domain($db, 'cdv-add-1.test');
    g2ml_cdv_login_as($db, 'cdvtestorg');

    $result = addOrgShortDomain('cdvtestorg', 'cdv-add-1.test');

    assert_true($result['success'], 'Adding a fresh, unclaimed short domain should succeed');
    assert_true(
        is_string($result['verificationToken']) && strlen($result['verificationToken']) >= 32,
        'A per-domain, unguessable verification token is generated and returned'
    );
    assert_same(
        '_g2ml-verify.cdv-add-1.test',
        $result['dnsRecords']['txt']['host'],
        'The TXT host uses the configured verification prefix'
    );
    assert_same(
        $result['verificationToken'],
        $result['dnsRecords']['txt']['value'],
        'The returned DNS record value matches the returned token'
    );

    $row = g2ml_cdv_fetch_short_domain($db, 'cdv-add-1.test');
    assert_true($row !== null, 'The domain row exists after adding');
    assert_same('pending', $row['verificationStatus'], 'A newly added domain starts pending');
    assert_same(0, (int) $row['isActive'], 'A newly added domain is NOT active/routable until verified');

    g2ml_cdv_delete_short_domain($db, 'cdv-add-1.test');
});

// ----------------------------------------------------------------------------
// 2. verify (correct TXT, injected) → verified, routable
// ----------------------------------------------------------------------------
test('verifyOrgShortDomain: a matching TXT record verifies the domain and flips it to routable', function () use ($db): void
{
    g2ml_cdv_delete_short_domain($db, 'cdv-verify-ok.test');
    g2ml_cdv_login_as($db, 'cdvtestorg');

    $addResult = addOrgShortDomain('cdvtestorg', 'cdv-verify-ok.test');
    assert_true($addResult['success'], 'Precondition: domain added');

    $row           = g2ml_cdv_fetch_short_domain($db, 'cdv-verify-ok.test');
    $expectedToken = $row['verificationToken'];
    $expectedHost  = '_g2ml-verify.cdv-verify-ok.test';

    $GLOBALS['g2ml_dns_txt_lookup_override'] = function (string $host) use ($expectedHost, $expectedToken): array
    {
        if ($host === $expectedHost)
        {
            return [['host' => $host, 'class' => 'IN', 'ttl' => 60, 'type' => 'TXT', 'txt' => $expectedToken]];
        }

        return [];
    };

    $verifyResult = verifyOrgShortDomain((int) $row['shortDomainUID'], 'cdvtestorg');

    unset($GLOBALS['g2ml_dns_txt_lookup_override']);

    assert_true($verifyResult['success'], 'verifyOrgShortDomain itself succeeds (the injected lookup was reachable)');
    assert_true($verifyResult['verified'], 'A matching TXT record verifies the domain');

    $rowAfter = g2ml_cdv_fetch_short_domain($db, 'cdv-verify-ok.test');
    assert_same('verified', $rowAfter['verificationStatus'], 'Status flips to verified');
    assert_same(1, (int) $rowAfter['isActive'], 'The domain becomes routable (isActive=1) once verified');
    assert_true($rowAfter['verifiedAt'] !== null, 'verifiedAt is stamped');

    g2ml_cdv_delete_short_domain($db, 'cdv-verify-ok.test');
});

// ----------------------------------------------------------------------------
// 3. verify (wrong TXT) → failed, stays unroutable
// ----------------------------------------------------------------------------
test('verifyOrgShortDomain: a mismatching TXT value marks the domain failed and leaves it unroutable', function () use ($db): void
{
    g2ml_cdv_delete_short_domain($db, 'cdv-verify-fail.test');
    g2ml_cdv_login_as($db, 'cdvtestorg');

    $addResult = addOrgShortDomain('cdvtestorg', 'cdv-verify-fail.test');
    assert_true($addResult['success'], 'Precondition: domain added');

    $row = g2ml_cdv_fetch_short_domain($db, 'cdv-verify-fail.test');

    $GLOBALS['g2ml_dns_txt_lookup_override'] = function (string $host): array
    {
        return [['host' => $host, 'class' => 'IN', 'ttl' => 60, 'type' => 'TXT', 'txt' => 'totally-the-wrong-value']];
    };

    $verifyResult = verifyOrgShortDomain((int) $row['shortDomainUID'], 'cdvtestorg');

    unset($GLOBALS['g2ml_dns_txt_lookup_override']);

    assert_false($verifyResult['verified'], 'A mismatching TXT value must not verify the domain');

    $rowAfter = g2ml_cdv_fetch_short_domain($db, 'cdv-verify-fail.test');
    assert_same('failed', $rowAfter['verificationStatus'], 'Status is marked failed');
    assert_same(0, (int) $rowAfter['isActive'], 'The domain remains unroutable after a failed verification');

    g2ml_cdv_delete_short_domain($db, 'cdv-verify-fail.test');
});

// ----------------------------------------------------------------------------
// 4. resolver: an unverified custom domain does NOT leak into '[default]'
//    — THE key regression this whole change exists to close.
// ----------------------------------------------------------------------------
test('resolver: an unverified custom short domain does NOT resolve into the default org namespace (key regression)', function () use ($db): void
{
    g2ml_cdv_delete_short_domain($db, 'cdv-unverified-resolve.test');
    g2ml_cdv_login_as($db, 'cdvtestorg');

    $addResult = addOrgShortDomain('cdvtestorg', 'cdv-unverified-resolve.test');
    assert_true($addResult['success'], 'Precondition: pending (unverified) domain exists');

    // A short code that exists — with a DIFFERENT destination — under BOTH
    // the default org and cdvtestorg, so a leak into either namespace would
    // be caught by the destination/orgHandle assertions below.
    g2ml_cdv_delete_shorturl($db, 'cdvsharedcode');
    g2ml_cdv_seed_shorturl($db, '[default]', 'cdvsharedcode', 'https://example.com/default-target');
    g2ml_cdv_seed_shorturl($db, 'cdvtestorg', 'cdvsharedcode', 'https://example.com/cdv-target');

    $result = resolveShortCode('cdv-unverified-resolve.test', 'cdvsharedcode');

    assert_same('domain_not_configured', $result['status'], 'An unverified custom domain must return domain_not_configured, never resolve anything');
    assert_same(null, $result['orgHandle'], 'No org handle is ever returned for an unverified domain claim');
    assert_same(null, $result['destination'], 'No destination is ever returned for an unverified domain claim (not the default target, not the org target)');

    g2ml_cdv_delete_short_domain($db, 'cdv-unverified-resolve.test');
    g2ml_cdv_delete_shorturl($db, 'cdvsharedcode');
});

// ----------------------------------------------------------------------------
// 5. resolver: the system's own default host is unaffected by the gate
// ----------------------------------------------------------------------------
test('resolver: g2my.link (the default host) still resolves normally after the verification gate', function () use ($db): void
{
    g2ml_cdv_delete_shorturl($db, 'cdvdefaultcode');
    g2ml_cdv_seed_shorturl($db, '[default]', 'cdvdefaultcode', 'https://example.com/default-ok-target');

    $result = resolveShortCode('g2my.link', 'cdvdefaultcode');

    assert_same('success', $result['status'], 'The default host still resolves normally');
    assert_same('https://example.com/default-ok-target', $result['destination'], 'It resolves to the seeded destination');
    assert_same('[default]', $result['orgHandle'], 'It resolves under the [default] org');

    g2ml_cdv_delete_shorturl($db, 'cdvdefaultcode');
});

// ----------------------------------------------------------------------------
// 6. quota: org.max_short_domains (previously dead code) is enforced
// ----------------------------------------------------------------------------
test('addOrgShortDomain: adding beyond org.max_short_domains is rejected', function () use ($db): void
{
    g2ml_cdv_login_as($db, 'cdvtestorg');

    // Clean slate so the count driving the quota check is known.
    g2ml_cdv_exec($db, "DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = 'cdvtestorg'");
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '1');

    $first = addOrgShortDomain('cdvtestorg', 'cdv-quota-1.test');
    assert_true($first['success'], 'The first domain, within the quota of 1, is accepted');

    $second = addOrgShortDomain('cdvtestorg', 'cdv-quota-2.test');
    assert_false($second['success'], 'A second domain, over the quota of 1, is rejected');
    assert_contains('maximum', $second['error'] ?? '', 'The rejection explains the plan limit');

    // Restore.
    g2ml_cdv_delete_short_domain($db, 'cdv-quota-1.test');
    g2ml_cdv_delete_short_domain($db, 'cdv-quota-2.test');
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '0');
});

// ----------------------------------------------------------------------------
// 7. grandfather: migration 013's backfill marks a pre-existing isActive=1
//    row verified, and it then resolves correctly.
// ----------------------------------------------------------------------------
test('migration 013 backfill: a pre-existing isActive=1 row is grandfathered to verified and keeps resolving', function () use ($db): void
{
    g2ml_cdv_delete_short_domain($db, 'cdv-grandfathered.test');

    // Simulate a row that existed BEFORE the verification columns/backfill:
    // isActive=1 (it was live under the old, unverified-routing contract),
    // verificationStatus left at the column's own default ('pending') since
    // no verification flow existed when it was created.
    g2ml_cdv_exec(
        $db,
        "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
        . "VALUES ('cdvtestorg', 'cdv-grandfathered.test', 0, 1)"
    );

    $before = g2ml_cdv_fetch_short_domain($db, 'cdv-grandfathered.test');
    assert_same('pending', $before['verificationStatus'], 'Precondition: simulates a pre-migration row at the column default');

    // Run the EXACT backfill statement from
    // web/_sql/migrations/013_short_domain_verification.sql — this proves the
    // migration's grandfathering logic itself, not a reimplementation of it.
    g2ml_cdv_exec(
        $db,
        "UPDATE `tblOrgShortDomains` SET `verificationStatus` = 'verified', `verifiedAt` = COALESCE(`verifiedAt`, NOW()) "
        . "WHERE `isActive` = 1 AND `verificationStatus` <> 'verified'"
    );

    $after = g2ml_cdv_fetch_short_domain($db, 'cdv-grandfathered.test');
    assert_same('verified', $after['verificationStatus'], 'The backfill grandfathers the pre-existing active row to verified');
    assert_true($after['verifiedAt'] !== null, 'verifiedAt is backfilled too');

    // And prove the RESOLVER actually treats it as routable post-backfill —
    // this is the whole point of grandfathering: live partner redirects must
    // not go dark the moment the stricter resolver check ships.
    g2ml_cdv_delete_shorturl($db, 'cdvgrandfcode');
    g2ml_cdv_seed_shorturl($db, 'cdvtestorg', 'cdvgrandfcode', 'https://example.com/grandfathered-target');

    $result = resolveShortCode('cdv-grandfathered.test', 'cdvgrandfcode');

    assert_same('success', $result['status'], 'A grandfathered domain resolves successfully post-migration');
    assert_same('https://example.com/grandfathered-target', $result['destination'], 'It resolves to the correct org-scoped destination');
    assert_same('cdvtestorg', $result['orgHandle'], 'It resolves under the owning org, not [default]');

    g2ml_cdv_delete_short_domain($db, 'cdv-grandfathered.test');
    g2ml_cdv_delete_shorturl($db, 'cdvgrandfcode');
});

// ----------------------------------------------------------------------------
// 8. #146: the effective cap is the TIGHTER of tier.maxCustomDomains and the
//    org.max_short_domains setting — proven from BOTH directions.
// ----------------------------------------------------------------------------
test('addOrgShortDomain (#146): a tighter TIER cap than the setting is enforced', function () use ($db): void
{
    g2ml_cdv_exec($db, "DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = 'cdvtestorg'");
    g2ml_cdv_login_as($db, 'cdvtestorg');

    // Tier cap (1) is TIGHTER than the setting (5) — the tier must win.
    g2ml_cdv_exec($db, "UPDATE `tblSubscriptionTiers` SET `maxCustomDomains` = 1 WHERE `tierID` = 'cdvunlimited'");
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '5');
    g2ml_clearOrgTierCache('cdvtestorg');

    $first = addOrgShortDomain('cdvtestorg', 'cdv-reconcile-tier-1.test');
    assert_true($first['success'], 'The first domain, within the tier cap of 1, is accepted even though the setting allows 5');

    $second = addOrgShortDomain('cdvtestorg', 'cdv-reconcile-tier-2.test');
    assert_false($second['success'], 'A second domain is rejected by the TIGHTER tier cap, even though the setting alone would allow it');
    assert_contains('maximum', $second['error'] ?? '', 'The rejection explains the plan limit');

    // Restore.
    g2ml_cdv_delete_short_domain($db, 'cdv-reconcile-tier-1.test');
    g2ml_cdv_delete_short_domain($db, 'cdv-reconcile-tier-2.test');
    g2ml_cdv_exec($db, "UPDATE `tblSubscriptionTiers` SET `maxCustomDomains` = NULL WHERE `tierID` = 'cdvunlimited'");
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '0');
    g2ml_clearOrgTierCache('cdvtestorg');
});

test('addOrgShortDomain (#146): a tighter SETTING cap than the tier is enforced', function () use ($db): void
{
    g2ml_cdv_exec($db, "DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = 'cdvtestorg'");
    g2ml_cdv_login_as($db, 'cdvtestorg');

    // Setting cap (1) is TIGHTER than the tier (NULL = unlimited) — the
    // setting must win. cdvunlimited's maxCustomDomains is NULL here (the
    // fixture default), so this also proves NULL is correctly ignored/never
    // treated as "0" when combining.
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '1');
    g2ml_clearOrgTierCache('cdvtestorg');

    $first = addOrgShortDomain('cdvtestorg', 'cdv-reconcile-setting-1.test');
    assert_true($first['success'], 'The first domain, within the setting cap of 1, is accepted with an unlimited tier');

    $second = addOrgShortDomain('cdvtestorg', 'cdv-reconcile-setting-2.test');
    assert_false($second['success'], 'A second domain is rejected by the setting cap, with the tier itself uncapped');
    assert_contains('maximum', $second['error'] ?? '', 'The rejection explains the plan limit');

    // Restore.
    g2ml_cdv_delete_short_domain($db, 'cdv-reconcile-setting-1.test');
    g2ml_cdv_delete_short_domain($db, 'cdv-reconcile-setting-2.test');
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '0');
    g2ml_clearOrgTierCache('cdvtestorg');
});

// ----------------------------------------------------------------------------
// 9. #146 fail-open: a simulated entitlement-system lookup failure must NOT
//    block addOrgShortDomain() — it must behave as though the tier imposes no
//    cap at all (the setting, if any, still applies on its own).
// ----------------------------------------------------------------------------
test('addOrgShortDomain (#146): a simulated tier-lookup failure fails OPEN (does not block the add)', function () use ($db): void
{
    g2ml_cdv_exec($db, "DELETE FROM `tblOrgShortDomains` WHERE `orgHandle` = 'cdvtestorg'");
    g2ml_cdv_login_as($db, 'cdvtestorg');

    // Even with a tier cap of 1 already reached, a lookup FAILURE must fail
    // OPEN — the entitlement system's own fault must never block a legitimate
    // add. This simulates a real DB/query error, not a "no tier" or
    // "inactive tier" state (those already have their own tested fallback).
    g2ml_cdv_exec($db, "UPDATE `tblSubscriptionTiers` SET `maxCustomDomains` = 1 WHERE `tierID` = 'cdvunlimited'");
    g2ml_cdv_set_setting($db, 'org.max_short_domains', '0');
    g2ml_clearOrgTierCache('cdvtestorg');

    $seeded = addOrgShortDomain('cdvtestorg', 'cdv-failopen-seed.test');
    assert_true($seeded['success'], 'Precondition: the tier cap of 1 is reached with one domain seeded');
    g2ml_clearOrgTierCache('cdvtestorg');

    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array|null|false
    {
        // A DB/query system failure — dbSelectOne()'s own false-on-error contract.
        return false;
    };

    $duringFailure = addOrgShortDomain('cdvtestorg', 'cdv-failopen-add.test');

    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    g2ml_clearOrgTierCache('cdvtestorg');

    assert_true($duringFailure['success'], 'A simulated entitlement-system lookup failure must fail OPEN — the add proceeds despite the (now-unknowable) tier cap');

    // Restore.
    g2ml_cdv_delete_short_domain($db, 'cdv-failopen-seed.test');
    g2ml_cdv_delete_short_domain($db, 'cdv-failopen-add.test');
    g2ml_cdv_exec($db, "UPDATE `tblSubscriptionTiers` SET `maxCustomDomains` = NULL WHERE `tierID` = 'cdvunlimited'");
    g2ml_clearOrgTierCache('cdvtestorg');
});
