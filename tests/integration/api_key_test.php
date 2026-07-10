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
