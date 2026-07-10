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
 * 🧪 Integration tests — Public API v1 key auth, rate limiting, logging (#38)
 * ============================================================================
 *
 * Drives the REAL functions from web/_functions/api_auth.php and
 * web/_functions/api_ratelimit.php against a freshly imported test database
 * (see tests/README.md), covering:
 *
 *   - g2ml_apiGenerateKey() -> g2ml_apiVerifyKey() round-trip.
 *   - Rejection of a tampered secret, a wrong key, an expired key, and a
 *     soft-revoked key (including that revocation is ORG-SCOPED — a caller
 *     cannot revoke another org's key).
 *   - Scope enforcement via g2ml_apiKeyHasScope().
 *   - g2ml_apiCheckRateLimit() returning allowed=false once the per-minute
 *     burst limit AND once the per-key daily limit are exceeded.
 *   - g2ml_apiLogRequest() writing a row whose stored requestBody contains NO
 *     secret (password/token/apiKey values are redacted before storage).
 *   - g2ml_apiListKeys() never selecting the apiKey hash column.
 *
 * Registration model mirrors auth_login_test.php / cross_org_isolation_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are prefixed
 * g2ml_apikey_test_* to avoid redeclaration alongside sibling integration
 * files. With no reachable test DB the runner skips before this file is ever
 * included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#38)
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
// Align this raw connection's session time zone with the app layer's own
// convention (web/_functions/db_connect.php runs "SET time_zone = '+00:00'"
// on every getDB() connection). g2ml_apikey_test_seed_log_row() below uses
// DATE_SUB(NOW(), INTERVAL ...) on THIS connection; without this alignment,
// on a host whose SYSTEM time zone is not UTC, its "now" can differ from
// getDB()'s UTC "now" (used by g2ml_apiCheckRateLimit()) by a full time-zone
// offset (discovered via #39's pre-auth-backoff tests, which are sensitive to
// this at their tested window boundaries; the daily/burst windows tested here
// happen to be wide enough not to expose it, but the same latent risk exists).
// ----------------------------------------------------------------------------
mysqli_query($db, "SET time_zone = '+00:00'");

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlApiKeyTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlApiKeyTestHost === false || $g2mlApiKeyTestHost === '')
    {
        $g2mlApiKeyTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlApiKeyTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlApiKeyTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlApiKeyTestPortRaw === false || $g2mlApiKeyTestPortRaw === '')
    {
        $g2mlApiKeyTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlApiKeyTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlApiKeyTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlApiKeyTestUser === false || $g2mlApiKeyTestUser === '')
    {
        $g2mlApiKeyTestUser = 'root';
    }

    define('DB_USER', $g2mlApiKeyTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlApiKeyTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlApiKeyTestPass === false)
    {
        $g2mlApiKeyTestPass = '';
    }

    define('DB_PASS', $g2mlApiKeyTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlApiKeyTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlApiKeyTestName === false || $g2mlApiKeyTestName === '')
    {
        $g2mlApiKeyTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlApiKeyTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlApiKeyFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';

require_once $g2mlApiKeyFunctionsDir . '/db_connect.php';
require_once $g2mlApiKeyFunctionsDir . '/db_query.php';
require_once $g2mlApiKeyFunctionsDir . '/security.php';
require_once $g2mlApiKeyFunctionsDir . '/settings.php';
require_once $g2mlApiKeyFunctionsDir . '/api_response.php';
require_once $g2mlApiKeyFunctionsDir . '/api_auth.php';
require_once $g2mlApiKeyFunctionsDir . '/api_ratelimit.php';
require_once $g2mlApiKeyFunctionsDir . '/entitlements.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Execute a setup query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_apikey_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user via a prepared statement and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker  A unique marker used to build the username/email.
 * @return int
 */
function g2ml_apikey_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`, `isSuspended`) '
        . 'VALUES (?, ?, ?, ?, 1, 0)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (user) failed: ' . mysqli_error($db));
    }

    $username     = $marker;
    $email        = $marker . '@apikey.test';
    $passwordHash = 'x';

    mysqli_stmt_bind_param($statement, 'ssss', $orgHandle, $username, $email, $passwordHash);

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
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
 * Insert one throwaway tblAPIRequestLog row directly (bypassing the timing
 * window this test needs to control), used to seed burst/daily counts.
 *
 * @param  mysqli $db
 * @param  int    $apiKeyUID
 * @param  string $createdAtOffset  A MySQL DATE_SUB()-compatible interval expression, e.g. '10 SECOND'.
 * @return void
 */
function g2ml_apikey_test_seed_log_row(mysqli $db, int $apiKeyUID, string $createdAtOffset): void
{
    $sql = 'INSERT INTO `tblAPIRequestLog` '
        . '(`apiKeyUID`, `endpoint`, `httpMethod`, `responseCode`, `ipAddress`, `createdAt`) '
        . 'VALUES (' . $apiKeyUID . ", '/api/v1/ping', 'GET', 200, '127.0.0.1', "
        . 'DATE_SUB(NOW(), INTERVAL ' . $createdAtOffset . '))';

    g2ml_apikey_test_exec($db, $sql);
}

/**
 * Delete all tblAPIRequestLog rows for a key (cleanup between tests).
 *
 * @param  mysqli $db
 * @param  int    $apiKeyUID
 * @return void
 */
function g2ml_apikey_test_clear_log(mysqli $db, int $apiKeyUID): void
{
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIRequestLog` WHERE `apiKeyUID` = ' . $apiKeyUID);
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier and two organisations (A = [default],
// B = orgbapikey, used only for the org-scoped revoke test).
// ----------------------------------------------------------------------------
g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgbapikey', 'Org B (API key isolation test)', 'https://go2my.link/orgb', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

// A dedicated, tight test tier (#146) so "the daily limit reflects the org's
// tier" is deterministic and independent of whatever the live pricing seed
// (web/_sql/seeds/001_subscription_tiers.sql) happens to allow.
//
// sortOrder is set to a deliberately HIGH value (999): g2ml_getOrgTier()'s
// Free-tier FALLBACK query picks the lowest-sortOrder ACTIVE tier across the
// WHOLE tblSubscriptionTiers table (not scoped to this file's own fixtures),
// and the column's own DEFAULT is 0 — lower than the real seeded 'free'
// tier's sortOrder of 1. Leaving this test tier at the default would make IT
// win that global fallback query and corrupt any OTHER integration file in
// the same suite run asserting "no-tier org -> Free".
g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `maxLinks`, `maxCustomDomains`, `maxAPIRequestsPerDay`, `maxLinksPages`, `sortOrder`, `isActive`) "
    . "VALUES ('apikey146tier', 'API Key Test Tier (#146)', NULL, NULL, 3, NULL, 999, 1) "
    . "ON DUPLICATE KEY UPDATE `maxAPIRequestsPerDay` = VALUES(`maxAPIRequestsPerDay`), `sortOrder` = VALUES(`sortOrder`)"
);

g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgapikey146', 'Org (#146 tier-reflects-rate-limit test)', 'https://go2my.link/orgapikey146', 'apikey146tier', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `tierID` = VALUES(`tierID`)"
);

// A second dedicated tight tier + two dedicated orgs (#149.1) for the
// per-org daily budget tests below: orgapikey149a holds TWO keys that must
// SHARE one daily budget; orgapikey149b holds a single key that must stay
// completely unaffected by orgapikey149a's exhausted budget. sortOrder=999
// for the same reason as apikey146tier above.
g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`, `maxLinks`, `maxCustomDomains`, `maxAPIRequestsPerDay`, `maxLinksPages`, `sortOrder`, `isActive`) "
    . "VALUES ('apikey149tier', 'API Key Test Tier (#149.1)', NULL, NULL, 4, NULL, 999, 1) "
    . "ON DUPLICATE KEY UPDATE `maxAPIRequestsPerDay` = VALUES(`maxAPIRequestsPerDay`), `sortOrder` = VALUES(`sortOrder`)"
);

g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgapikey149a', 'Org A (#149.1 per-org budget test)', 'https://go2my.link/orgapikey149a', 'apikey149tier', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `tierID` = VALUES(`tierID`)"
);

g2ml_apikey_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgapikey149b', 'Org B (#149.1 per-org budget test)', 'https://go2my.link/orgapikey149b', 'apikey149tier', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`), `tierID` = VALUES(`tierID`)"
);

// ============================================================================
// 🔑 Generate -> verify round-trip
// ============================================================================

test('API key: generate -> verify round-trips to the correct owner/org/scopes', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);

    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Round-trip test key', ['account:read', 'urls:read'], null);

    assert_true($created['success'], 'Key generation must succeed against the real schema');
    assert_true(str_starts_with($created['plaintextKey'], 'g2ml_'), 'The plaintext key must carry the g2ml_ prefix');
    assert_same(8, strlen($created['apiKeyPrefix']), 'The stored prefix must be exactly 8 base64url characters');

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    assert_true(is_array($verified), 'A freshly generated key must verify successfully');
    assert_same($userUID, (int) $verified['userUID'], 'The verified row must carry the correct owning userUID');
    assert_same('[default]', $verified['orgHandle'], 'The verified row must carry the correct orgHandle');
    assert_true(is_array($verified['permissions']), 'permissions must be decoded to an array');
    assert_true(in_array('account:read', $verified['permissions'], true), 'account:read must be present in the decoded scopes');
    assert_true(in_array('urls:read', $verified['permissions'], true), 'urls:read must be present in the decoded scopes');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// 🔁 apiKeyPrefix collision retry (#149.4 — UQ_apikey_prefix)
// ============================================================================

test('API key: a prefix collision (1062) is retried with a new key and succeeds', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'prefixcollide'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);

    // Reserve a fixed prefix by inserting a throwaway placeholder row, so the
    // FIRST draw from the $GLOBALS['g2ml_api_prefix_override'] seam below
    // collides against it on UQ_apikey_prefix (errno 1062).
    $collidingPrefix  = 'colide01';
    $placeholderHash  = hash('sha256', 'placeholder-collision-row-' . $marker);

    g2ml_apikey_test_exec(
        $db,
        "INSERT INTO `tblAPIKeys` (`userUID`, `orgHandle`, `apiKey`, `apiKeyPrefix`, `keyName`) "
        . "VALUES (" . (int) $userUID . ", '[default]', '" . $placeholderHash . "', '" . $collidingPrefix . "', 'Collision placeholder')"
    );

    $callCount = 0;

    $GLOBALS['g2ml_api_prefix_override'] = function () use ($collidingPrefix, &$callCount): string
    {
        $callCount = $callCount + 1;

        if ($callCount === 1)
        {
            return $collidingPrefix;
        }

        return _g2ml_apiBase64UrlToken(G2ML_API_KEY_PREFIX_BYTES);
    };

    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Collision retry test key', ['account:read'], null);

    unset($GLOBALS['g2ml_api_prefix_override']);

    assert_true($created['success'], 'A single prefix collision must be transparently retried, never surfaced as a failure');
    assert_same(2, $callCount, 'Exactly one retry must have occurred (collision on attempt 1, success on attempt 2)');
    assert_not_same($collidingPrefix, $created['apiKeyPrefix'], 'The retried key must carry a DIFFERENT prefix from the one that collided');

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);
    assert_true(is_array($verified), 'The retried key must verify successfully like any normally generated key');

    g2ml_apikey_test_exec($db, "DELETE FROM `tblAPIKeys` WHERE `apiKeyPrefix` = '" . $collidingPrefix . "'");
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: exhausting every retry attempt on a persistent collision fails gracefully (never an unhandled error)', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'prefixexhaust'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);

    $stuckPrefix     = 'stuckabc';
    $placeholderHash = hash('sha256', 'placeholder-exhaustion-row-' . $marker);

    g2ml_apikey_test_exec(
        $db,
        "INSERT INTO `tblAPIKeys` (`userUID`, `orgHandle`, `apiKey`, `apiKeyPrefix`, `keyName`) "
        . "VALUES (" . (int) $userUID . ", '[default]', '" . $placeholderHash . "', '" . $stuckPrefix . "', 'Exhaustion placeholder')"
    );

    // Every attempt draws the SAME already-taken prefix, so every one of the
    // bounded retry loop's attempts collides on UQ_apikey_prefix.
    $GLOBALS['g2ml_api_prefix_override'] = function () use ($stuckPrefix): string
    {
        return $stuckPrefix;
    };

    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Collision exhaustion test key', ['account:read'], null);

    unset($GLOBALS['g2ml_api_prefix_override']);

    assert_false($created['success'], 'Persistent collisions across every retry attempt must fail cleanly, not throw or 500');
    assert_true(isset($created['error']), 'A failed generation must still report an error message to the caller');

    g2ml_apikey_test_exec($db, "DELETE FROM `tblAPIKeys` WHERE `apiKeyPrefix` = '" . $stuckPrefix . "'");
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// 🚫 Rejection paths
// ============================================================================

test('API key: a tampered secret is rejected', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'tamper'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Tamper test key', ['account:read'], null);

    // Flip the last character of the secret.
    $lastCharacter = substr($created['plaintextKey'], -1);

    if ($lastCharacter === 'A')
    {
        $replacementCharacter = 'B';
    }
    else
    {
        $replacementCharacter = 'A';
    }

    $tampered = substr($created['plaintextKey'], 0, -1) . $replacementCharacter;

    assert_same(null, g2ml_apiVerifyKey($tampered), 'A tampered secret must never verify');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: a well-formed but wholly wrong key is rejected', function (): void
{
    assert_same(null, g2ml_apiVerifyKey('g2ml_ffffffff_' . str_repeat('A', 43)), 'A key with no matching prefix must be rejected');
});

test('API key: malformed input (missing prefix delimiter, wrong wrapper) is rejected', function (): void
{
    assert_same(null, g2ml_apiVerifyKey('not-a-key-at-all'), 'A string with no g2ml_ wrapper must be rejected');
    assert_same(null, g2ml_apiVerifyKey('g2ml_noUnderscoreAfterPrefix'), 'A string missing the second underscore must be rejected');
    assert_same(null, g2ml_apiVerifyKey(''), 'An empty string must be rejected');
});

// ============================================================================
// 🐛 Regression — prefix containing '_' must still verify (#39 discovery)
// ============================================================================
// g2ml_apiVerifyKey() used to locate the prefix/secret delimiter by searching
// for the FIRST '_' after "g2ml_". The prefix's own base64url alphabet
// includes '_' (from the '/' -> '_' substitution in
// _g2ml_apiBase64UrlToken()), so roughly 1 in 9 freshly generated keys carry
// an underscore SOMEWHERE WITHIN their own 8-character prefix — that search
// found the WRONG (internal) underscore, truncated the extracted prefix, and
// caused a perfectly valid, non-expired, non-revoked key to fail
// verification. The fix extracts a FIXED-LENGTH prefix (G2ML_API_KEY_PREFIX_LENGTH
// characters) instead of searching for a delimiter. This test forces exactly
// that scenario by hand-crafting a stored key whose prefix contains '_'.
// ============================================================================

test('API key: a prefix that itself contains an underscore still verifies correctly (regression)', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'underscoreprefix'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);

    // A hand-crafted prefix with '_' at an internal position — exactly the
    // shape a real _g2ml_apiBase64UrlToken(6) draw can produce.
    $forcedPrefix = 'ab_cdefg';
    assert_same(8, strlen($forcedPrefix), 'The forced prefix must be exactly 8 characters, matching G2ML_API_KEY_PREFIX_LENGTH');

    $secret       = str_repeat('S', 43);
    $plaintextKey = 'g2ml_' . $forcedPrefix . '_' . $secret;
    $hashedKey    = hash('sha256', $plaintextKey);

    $inserted = dbInsert(
        'INSERT INTO tblAPIKeys (userUID, orgHandle, apiKey, apiKeyPrefix, keyName, permissions, expiresAt) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        'issssss',
        [$userUID, '[default]', $hashedKey, $forcedPrefix, 'Underscore-prefix regression key', '["account:read"]', null]
    );

    assert_true(is_int($inserted) && $inserted > 0, 'Setup: the hand-crafted key row must insert successfully');

    $verified = g2ml_apiVerifyKey($plaintextKey);

    assert_true(is_array($verified), 'A key whose prefix contains an underscore must still verify — the delimiter must be found by FIXED LENGTH, not by searching for the first underscore');
    assert_same($forcedPrefix, $verified['apiKeyPrefix'], 'The full, untruncated prefix must be the one recorded on the verified row');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $inserted);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: an expired key is rejected', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'expiry'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Expiry test key', ['account:read'], null);

    g2ml_apikey_test_exec(
        $db,
        'UPDATE `tblAPIKeys` SET `expiresAt` = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']
    );

    assert_same(null, g2ml_apiVerifyKey($created['plaintextKey']), 'A key whose expiresAt is in the past must be rejected');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: a revoked (isActive=0) key is rejected', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'revoke'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Revoke test key', ['account:read'], null);

    $revoked = g2ml_apiRevokeKey((int) $created['apiKeyUID'], '[default]');

    assert_true($revoked, 'Revoking a key that belongs to the correct org must report success');
    assert_same(null, g2ml_apiVerifyKey($created['plaintextKey']), 'A revoked key must be rejected by verify');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: revocation is org-scoped — another org cannot revoke this key (BOLA guard)', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'crossorg'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Cross-org revoke test key', ['account:read'], null);

    // Org B attempts to revoke a key that actually belongs to [default].
    $revokedByWrongOrg = g2ml_apiRevokeKey((int) $created['apiKeyUID'], 'orgbapikey');

    assert_false($revokedByWrongOrg, 'A revoke call scoped to the WRONG org must report no rows affected');
    assert_true(is_array(g2ml_apiVerifyKey($created['plaintextKey'])), 'The key must still verify — a cross-org revoke attempt must not have touched it');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('API key: a suspended owning user invalidates the key even though the key row itself is untouched', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'suspend'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Suspended-owner test key', ['account:read'], null);

    g2ml_apikey_test_exec($db, 'UPDATE `tblUsers` SET `isSuspended` = 1 WHERE `userUID` = ' . $userUID);

    assert_same(null, g2ml_apiVerifyKey($created['plaintextKey']), 'A key owned by a suspended user must be rejected');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// 🏷️ Scope enforcement
// ============================================================================

test('API key: scope enforcement — present scope passes, absent scope fails', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'scope'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Scope test key', ['account:read'], null);

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    assert_true(g2ml_apiKeyHasScope($verified, 'account:read'), 'account:read was granted and must be present');
    assert_false(g2ml_apiKeyHasScope($verified, 'urls:write'), 'urls:write was never granted and must be absent');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// ⏳ Rate limiting
// ============================================================================

test('rateLimit: allowed=true when well under both windows', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-ok'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Rate-limit OK test key', ['account:read'], null);
    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    assert_true($rateLimit['allowed'], 'With zero prior requests logged, the key must be allowed');
    assert_same(0, $rateLimit['retryAfter'], 'retryAfter must be 0 when the request is allowed');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('rateLimit: the per-key rateLimitOverride (daily) is enforced and returns allowed=false once reached', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-daily'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Rate-limit daily test key', ['account:read'], null);

    // A tiny daily override so the test does not depend on the global default.
    g2ml_apikey_test_exec($db, 'UPDATE `tblAPIKeys` SET `rateLimitOverride` = 2 WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);
    assert_same(2, (int) $verified['rateLimitOverride'], 'The override must round-trip through verify');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    // Seed exactly the override's worth of requests within the last day (well
    // outside the 60-second burst window, so only the daily window trips).
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '2 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '1 HOUR');

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    assert_false($rateLimit['allowed'], 'Reaching the per-key daily override must deny the request');
    assert_same(2, $rateLimit['limit'], 'The reported limit must reflect the per-key override, not the global default');
    assert_same(0, $rateLimit['remaining'], 'remaining must be 0 once the limit is reached');
    assert_same(86400, $rateLimit['retryAfter'], 'A daily-window exhaustion must report a ~24h Retry-After');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

test('rateLimit: the per-minute burst limit trips independently of the daily limit', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-burst'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Rate-limit burst test key', ['account:read'], null);

    // Force a tiny global per-minute setting for a deterministic burst test,
    // loaded into the getSetting() cache via loadSettingsCache(). A plain
    // UPDATE, not INSERT ... ON DUPLICATE KEY UPDATE: the row is already
    // seeded by web/_sql/seeds/015_api_settings.sql, and
    // tblSettings.UQ_setting_scope (settingID, settingScope, settingScopeRef)
    // cannot de-duplicate here — settingScopeRef is NULL for every
    // System-scope setting, and MySQL never treats two NULLs as equal for
    // unique-key purposes, so INSERT ... ON DUPLICATE KEY UPDATE would
    // silently insert a SECOND row instead of updating the existing one,
    // leaving getSetting()'s outcome dependent on undefined row-scan order.
    g2ml_apikey_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '2' WHERE `settingID` = 'api.default_per_minute'");
    loadSettingsCache();

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    // Two requests inside the last 60 seconds meets the (overridden) burst limit of 2.
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '5 SECOND');
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '10 SECOND');

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    assert_false($rateLimit['allowed'], 'Reaching the per-minute burst limit must deny the request even with daily quota remaining');
    assert_same(60, $rateLimit['retryAfter'], 'A burst-window exhaustion must report a 60s Retry-After');

    // Restore the global setting so it does not leak into sibling tests.
    g2ml_apikey_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '60' WHERE `settingID` = 'api.default_per_minute'");
    loadSettingsCache();

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// 💎 #146 — the daily limit reflects the org's subscription tier
// ============================================================================

test('rateLimit (#146): the daily limit reflects the org\'s tier when no per-key override is set', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-tier'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, 'orgapikey146', $marker);
    $created = g2ml_apiGenerateKey($userUID, 'orgapikey146', 'Rate-limit tier test key', ['account:read'], null);

    // No rateLimitOverride is set on this key — the tier's maxAPIRequestsPerDay
    // (3, per the fixture above) must be what governs, NOT the global
    // api.default_daily_limit setting (5000).
    $verified = g2ml_apiVerifyKey($created['plaintextKey']);
    assert_same(null, $verified['rateLimitOverride'], 'Precondition: no per-key override is set');

    g2ml_clearOrgTierCache('orgapikey146');
    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    // Seed exactly the tier's worth of requests within the last day.
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '2 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '1 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '30 MINUTE');

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    assert_false($rateLimit['allowed'], 'Reaching the ORG TIER\'s daily limit (3) must deny the request');
    assert_same(3, $rateLimit['limit'], 'The reported limit must reflect the org\'s tier, not the global default (5000)');
    assert_same(0, $rateLimit['remaining'], 'remaining must be 0 once the tier limit is reached');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
    g2ml_clearOrgTierCache('orgapikey146');
});

test('rateLimit (#146): a per-key rateLimitOverride still wins over the org\'s tier', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-override-wins'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, 'orgapikey146', $marker);
    $created = g2ml_apiGenerateKey($userUID, 'orgapikey146', 'Rate-limit override-wins test key', ['account:read'], null);

    // The org's own tier allows 3/day, but THIS key has an explicit override
    // of 1 — the override must win, per g2ml_apiCheckRateLimit()'s own
    // documented priority order (per-key override > tier > setting).
    g2ml_apikey_test_exec($db, 'UPDATE `tblAPIKeys` SET `rateLimitOverride` = 1 WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);
    assert_same(1, (int) $verified['rateLimitOverride'], 'The override must round-trip through verify');

    g2ml_clearOrgTierCache('orgapikey146');
    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_seed_log_row($db, (int) $created['apiKeyUID'], '1 HOUR');

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    assert_false($rateLimit['allowed'], 'Reaching the per-key override (1) must deny the request');
    assert_same(1, $rateLimit['limit'], 'The reported limit is the per-key override (1), NOT the tier\'s 3');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
    g2ml_clearOrgTierCache('orgapikey146');
});

test('rateLimit (#146): a simulated tier-lookup failure fails OPEN (falls back to the global default)', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl-failopen'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, 'orgapikey146', $marker);
    $created = g2ml_apiGenerateKey($userUID, 'orgapikey146', 'Rate-limit fail-open test key', ['account:read'], null);

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);
    assert_same(null, $verified['rateLimitOverride'], 'Precondition: no per-key override is set');

    g2ml_clearOrgTierCache('orgapikey146');
    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    // Simulate a DB/query system failure resolving the org's tier.
    $GLOBALS['g2ml_entitlements_tier_lookup_override'] = function (string $orgHandle): array|null|false
    {
        return false;
    };

    $rateLimit = g2ml_apiCheckRateLimit($verified);

    unset($GLOBALS['g2ml_entitlements_tier_lookup_override']);
    g2ml_clearOrgTierCache('orgapikey146');

    // g2ml_getOrgTier() fails OPEN to the unlimited sentinel (maxAPIRequestsPerDay
    // = null) on a lookup failure, so g2ml_apiCheckRateLimit() falls through to
    // the global api.default_daily_limit setting, exactly as it would for a key
    // whose org genuinely has no tier-imposed cap — never a hard block caused by
    // the entitlement system's own fault.
    assert_true($rateLimit['allowed'], 'A simulated entitlement-system lookup failure must fail OPEN — the request is allowed');
    assert_same((int) getSetting('api.default_daily_limit', 5000), $rateLimit['limit'], 'The reported limit falls back to the global default, not a blocked/zero limit');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
    g2ml_clearOrgTierCache('orgapikey146');
});

// ============================================================================
// 🏢 #149.1 — the tier/global-default daily budget is per-ORG, not per-key
// ============================================================================

test('rateLimit (#149.1): two keys in the SAME org share one daily budget; an independent org is unaffected', function () use ($db): void
{
    $markerA1 = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl149-a1'), 0, 12);
    $markerA2 = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl149-a2'), 0, 12);
    $markerB1 = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl149-b1'), 0, 12);

    $userA1 = g2ml_apikey_test_insert_user($db, 'orgapikey149a', $markerA1);
    $userA2 = g2ml_apikey_test_insert_user($db, 'orgapikey149a', $markerA2);
    $userB1 = g2ml_apikey_test_insert_user($db, 'orgapikey149b', $markerB1);

    $createdA1 = g2ml_apiGenerateKey($userA1, 'orgapikey149a', 'Org A key 1', ['account:read'], null);
    $createdA2 = g2ml_apiGenerateKey($userA2, 'orgapikey149a', 'Org A key 2', ['account:read'], null);
    $createdB1 = g2ml_apiGenerateKey($userB1, 'orgapikey149b', 'Org B key 1', ['account:read'], null);

    $verifiedA1 = g2ml_apiVerifyKey($createdA1['plaintextKey']);
    $verifiedA2 = g2ml_apiVerifyKey($createdA2['plaintextKey']);
    $verifiedB1 = g2ml_apiVerifyKey($createdB1['plaintextKey']);

    assert_same(null, $verifiedA1['rateLimitOverride'], 'Precondition: no per-key override on org A key 1');
    assert_same(null, $verifiedA2['rateLimitOverride'], 'Precondition: no per-key override on org A key 2');
    assert_same(null, $verifiedB1['rateLimitOverride'], 'Precondition: no per-key override on org B key 1');

    g2ml_clearOrgTierCache('orgapikey149a');
    g2ml_clearOrgTierCache('orgapikey149b');
    g2ml_apikey_test_clear_log($db, (int) $createdA1['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdA2['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdB1['apiKeyUID']);

    // Org A's tier caps the org at 4/day. Split exactly that many requests
    // across its TWO keys (2 each) — individually, NEITHER key has reached
    // even half of the cap, but the ORG has reached all of it.
    g2ml_apikey_test_seed_log_row($db, (int) $createdA1['apiKeyUID'], '3 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $createdA1['apiKeyUID'], '2 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $createdA2['apiKeyUID'], '1 HOUR');
    g2ml_apikey_test_seed_log_row($db, (int) $createdA2['apiKeyUID'], '30 MINUTE');

    // Org B has the SAME tier/cap but only ONE request logged against its
    // own single key — its budget must be entirely independent of org A's.
    g2ml_apikey_test_seed_log_row($db, (int) $createdB1['apiKeyUID'], '1 HOUR');

    $rateLimitA1 = g2ml_apiCheckRateLimit($verifiedA1);
    $rateLimitA2 = g2ml_apiCheckRateLimit($verifiedA2);
    $rateLimitB1 = g2ml_apiCheckRateLimit($verifiedB1);

    assert_false($rateLimitA1['allowed'], 'Org A key 1 must be denied — the ORG total (4) has reached the tier cap (4), even though key 1 alone only logged 2');
    assert_same(4, $rateLimitA1['limit'], 'The reported limit must be org A\'s tier cap');
    assert_same(0, $rateLimitA1['remaining'], 'remaining must be 0 once the ORG total reaches the cap');

    assert_false($rateLimitA2['allowed'], 'Org A key 2 must ALSO be denied — the shared org budget is exhausted regardless of which of org A\'s keys checks it');
    assert_same(4, $rateLimitA2['limit'], 'Org A key 2 must report the same org-wide limit as key 1');

    assert_true($rateLimitB1['allowed'], 'Org B\'s key must be allowed — its own org has only 1 of its 4 daily requests used, wholly unaffected by org A being at its cap');
    assert_same(3, $rateLimitB1['remaining'], 'Org B\'s remaining must reflect ONLY its own org\'s usage (4 - 1 = 3), never org A\'s');

    g2ml_apikey_test_clear_log($db, (int) $createdA1['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdA2['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdB1['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $createdA1['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $createdA2['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $createdB1['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userA1);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userA2);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userB1);
    g2ml_clearOrgTierCache('orgapikey149a');
    g2ml_clearOrgTierCache('orgapikey149b');
});

test('rateLimit (#149.1): a per-key rateLimitOverride still counts ONLY that key, even when a sibling key shares the org', function () use ($db): void
{
    $markerOverride = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl149-override'), 0, 12);
    $markerSibling  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'rl149-sibling'), 0, 12);

    $userOverride = g2ml_apikey_test_insert_user($db, 'orgapikey149a', $markerOverride);
    $userSibling  = g2ml_apikey_test_insert_user($db, 'orgapikey149a', $markerSibling);

    $createdOverride = g2ml_apiGenerateKey($userOverride, 'orgapikey149a', 'Org A override key', ['account:read'], null);
    $createdSibling  = g2ml_apiGenerateKey($userSibling, 'orgapikey149a', 'Org A sibling key', ['account:read'], null);

    // A generous per-key override (10) — far above the org tier's cap of 4 —
    // so if the override were EVER counted org-wide instead of per-key, the
    // sibling's own requests below would wrongly push it over 4 and deny it.
    g2ml_apikey_test_exec($db, 'UPDATE `tblAPIKeys` SET `rateLimitOverride` = 10 WHERE `apiKeyUID` = ' . (int) $createdOverride['apiKeyUID']);

    $verifiedOverride = g2ml_apiVerifyKey($createdOverride['plaintextKey']);
    $verifiedSibling   = g2ml_apiVerifyKey($createdSibling['plaintextKey']);

    assert_same(10, (int) $verifiedOverride['rateLimitOverride'], 'The override must round-trip through verify');
    assert_same(null, $verifiedSibling['rateLimitOverride'], 'Precondition: the sibling key has no override of its own');

    g2ml_clearOrgTierCache('orgapikey149a');
    g2ml_apikey_test_clear_log($db, (int) $createdOverride['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdSibling['apiKeyUID']);

    // 5 requests on the OVERRIDE key alone (under its own limit of 10) and
    // 3 on the sibling (under the org tier's 4).
    for ($overrideRequestIndex = 0; $overrideRequestIndex < 5; $overrideRequestIndex++)
    {
        g2ml_apikey_test_seed_log_row($db, (int) $createdOverride['apiKeyUID'], (string) (60 + $overrideRequestIndex) . ' MINUTE');
    }

    g2ml_apikey_test_seed_log_row($db, (int) $createdSibling['apiKeyUID'], '10 MINUTE');
    g2ml_apikey_test_seed_log_row($db, (int) $createdSibling['apiKeyUID'], '20 MINUTE');
    g2ml_apikey_test_seed_log_row($db, (int) $createdSibling['apiKeyUID'], '30 MINUTE');

    $rateLimitOverride = g2ml_apiCheckRateLimit($verifiedOverride);
    $rateLimitSibling   = g2ml_apiCheckRateLimit($verifiedSibling);

    assert_true($rateLimitOverride['allowed'], 'The override key must be judged ONLY against its own 5 requests vs its own limit of 10, never the org total');
    assert_same(10, $rateLimitOverride['limit'], 'The override key must report its own override, not the org tier');

    assert_true($rateLimitSibling['allowed'], 'The sibling key must be judged against the ORG total (3 + 0 own-org-tier-counted requests from the override key = 3), well under the tier cap of 4');
    assert_same(4, $rateLimitSibling['limit'], 'The sibling key must report the org tier\'s limit, not the sibling\'s own override (it has none)');
    assert_same(1, $rateLimitSibling['remaining'], 'The sibling\'s org-wide count must be its own 3 requests only (4 - 3 = 1) — the override key\'s 5 requests must NOT be counted into the org total');

    g2ml_apikey_test_clear_log($db, (int) $createdOverride['apiKeyUID']);
    g2ml_apikey_test_clear_log($db, (int) $createdSibling['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $createdOverride['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $createdSibling['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userOverride);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userSibling);
    g2ml_clearOrgTierCache('orgapikey149a');
});

// ============================================================================
// 📝 Request logging — no secrets ever stored
// ============================================================================

test('apiLogRequest: writes a row, and the stored requestBody contains NO secret', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'log'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, '[default]', $marker);
    $created = g2ml_apiGenerateKey($userUID, '[default]', 'Log test key', ['account:read'], null);

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);

    $secretPassword = 'S3cr3t-Value-Should-Never-Persist';
    $secretApiKey   = $created['plaintextKey'];

    g2ml_apiLogRequest(
        (int) $created['apiKeyUID'],
        '/api/v1/ping',
        'POST',
        200,
        12,
        '203.0.113.7',
        'Go2MyLink-Test-Agent/1.0',
        ['password' => $secretPassword, 'apiKey' => $secretApiKey, 'note' => 'hello']
    );

    $row = dbSelectOne(
        'SELECT requestBody, ipAddress, endpoint, httpMethod, responseCode FROM tblAPIRequestLog WHERE apiKeyUID = ? ORDER BY requestUID DESC LIMIT 1',
        'i',
        [(int) $created['apiKeyUID']]
    );

    assert_true(is_array($row), 'g2ml_apiLogRequest must write a retrievable row');
    assert_same('203.0.113.7', $row['ipAddress'], 'ipAddress (NOT NULL) must be stored exactly as passed');
    assert_same('/api/v1/ping', $row['endpoint'], 'endpoint must be stored exactly as passed');

    $storedBody = (string) $row['requestBody'];

    assert_false(str_contains($storedBody, $secretPassword), 'The plaintext password must NEVER appear in the stored requestBody');
    assert_false(str_contains($storedBody, $secretApiKey), 'The plaintext API key must NEVER appear in the stored requestBody');
    assert_contains('[REDACTED]', $storedBody, 'Sensitive fields must be replaced with the redaction marker');
    assert_contains('hello', $storedBody, 'Non-sensitive fields must still be preserved for audit purposes');

    g2ml_apikey_test_clear_log($db, (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});

// ============================================================================
// 📋 Key listing — metadata only, never the hash
// ============================================================================

test('apiListKeys: returns metadata only — the apiKey hash column is never selected', function () use ($db): void
{
    $marker  = 'apikey_' . substr(hash('sha256', (string) microtime(true) . 'list'), 0, 12);
    $userUID = g2ml_apikey_test_insert_user($db, 'orgbapikey', $marker);
    $created = g2ml_apiGenerateKey($userUID, 'orgbapikey', 'Listing test key', ['account:read'], null);

    $keys = g2ml_apiListKeys('orgbapikey');

    $found = null;

    foreach ($keys as $keyRow)
    {
        if ((int) $keyRow['apiKeyUID'] === (int) $created['apiKeyUID'])
        {
            $found = $keyRow;
            break;
        }
    }

    assert_true(is_array($found), 'The newly created key must appear in its own org listing');
    assert_false(array_key_exists('apiKey', $found), 'The apiKey hash column must never be present in a listing row');
    assert_same($created['apiKeyPrefix'], $found['apiKeyPrefix'], 'The listing must expose the plaintext-safe prefix');
    assert_true(is_array($found['permissions']), 'permissions must be decoded to an array in the listing too');

    g2ml_apikey_test_exec($db, 'DELETE FROM `tblAPIKeys` WHERE `apiKeyUID` = ' . (int) $created['apiKeyUID']);
    g2ml_apikey_test_exec($db, 'DELETE FROM `tblUsers` WHERE `userUID` = ' . $userUID);
});
