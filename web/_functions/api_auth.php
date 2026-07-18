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
 * 🔑 Go2My.Link — Public API: Key Authentication (#38)
 * ============================================================================
 *
 * Generates, verifies, scopes, lists and revokes tblAPIKeys rows for the
 * versioned public API (/api/v1/*). This file defines ONLY the auth
 * primitives — rate limiting and request logging live in api_ratelimit.php,
 * and the HTTP front controller lives in
 * web/Go2My.Link/public_html/api/v1/index.php.
 *
 * Key format:  g2ml_<8-char base64url prefix>_<43-char base64url secret>
 *   - The prefix is stored in plaintext (`apiKeyPrefix`) for an O(1) indexed
 *     lookup at verification time. It carries no secrecy value.
 *   - `apiKey` stores hash('sha256', $fullPresentedKey) — the WHOLE presented
 *     string (including the "g2ml_<prefix>_" wrapper), never the raw key.
 *     The secret has 256 bits of entropy, so a fast hash is appropriate here
 *     (this is a bearer credential compared via hash_equals(), not a
 *     user-chosen password needing Argon2id-style stretching).
 *   - The plaintext key is returned to the caller exactly ONCE, at creation.
 *     It is never stored, logged, or reconstructable from the database.
 *
 * Verification rejects (returns null, revealing nothing about WHICH check
 * failed) when: the key is malformed, the prefix is unknown, the secret does
 * not match, the key is inactive, the key has expired, the owning user is
 * inactive/suspended, or the owning organisation is inactive.
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne/dbUpdate/dbInsert),
 *               settings.php is NOT required by this file directly.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#38)
 *
 * 📖 References:
 *     - tblAPIKeys schema:        web/_sql/schema/031_api.sql
 *     - hash_equals (timing-safe): https://www.php.net/manual/en/function.hash-equals.php
 *     - random_bytes:              https://www.php.net/manual/en/function.random-bytes.php
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔢 Key-format invariants — shared between generation and verification
// ============================================================================
// The prefix is drawn from exactly G2ML_API_KEY_PREFIX_BYTES random bytes,
// which base64url-encodes to EXACTLY G2ML_API_KEY_PREFIX_LENGTH characters
// with no padding (6 bytes -> 48 bits -> 48/6 = 8 base64 characters exactly).
// g2ml_apiVerifyKey() relies on this fixed length to extract the prefix — see
// the bug-fix note there for why a variable-length/delimiter-search approach
// is unsafe for this alphabet.
// ============================================================================
if (!defined('G2ML_API_KEY_PREFIX_BYTES'))
{
    define('G2ML_API_KEY_PREFIX_BYTES', 6);
}

if (!defined('G2ML_API_KEY_PREFIX_LENGTH'))
{
    define('G2ML_API_KEY_PREFIX_LENGTH', 8);
}

// ============================================================================
// 🎲 Internal — base64url random token generator
// ============================================================================

/**
 * Generate a cryptographically secure, URL-safe (base64url, unpadded) random
 * token of the given input byte length.
 *
 * @param  int    $bytes  Number of random bytes to draw (before encoding).
 * @return string         The base64url-encoded token (no '+', '/' or '=').
 *
 * 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
 */
function _g2ml_apiBase64UrlToken(int $bytes): string
{
    $rawBytes     = random_bytes($bytes);
    $base64       = base64_encode($rawBytes);
    $urlSafe      = strtr($base64, '+/', '-_');
    $unpadded     = rtrim($urlSafe, '=');

    return $unpadded;
}

/**
 * Draw a fresh apiKeyPrefix candidate.
 *
 * Testability seam (mirrors $GLOBALS['g2ml_geoip_reader_override'] in
 * geolocation.php): when $GLOBALS['g2ml_api_prefix_override'] is set to a
 * callable, its return value is used instead of a real random draw, so an
 * integration test can force a deterministic sequence of prefixes (e.g. a
 * pre-seeded colliding value, then a real random one) to exercise the
 * duplicate-key retry loop in g2ml_apiGenerateKey() without waiting on a
 * genuinely random 48-bit collision (#149.4).
 *
 * @return string  G2ML_API_KEY_PREFIX_LENGTH base64url characters.
 */
function _g2ml_apiGeneratePrefix(): string
{
    if (isset($GLOBALS['g2ml_api_prefix_override']) && is_callable($GLOBALS['g2ml_api_prefix_override']))
    {
        $override = $GLOBALS['g2ml_api_prefix_override'];

        return $override();
    }

    return _g2ml_apiBase64UrlToken(G2ML_API_KEY_PREFIX_BYTES);
}

// ============================================================================
// 🆕 Key generation
// ============================================================================

/**
 * Mint a new API key for a user within an organisation.
 *
 * Returns the plaintext key exactly once — the caller MUST surface it to the
 * user immediately (e.g. a one-time "copy your key now" dashboard panel) and
 * must never log it. Only the SHA-256 hash of the full key is persisted.
 *
 * @param  int         $userUID     The owning user (FK tblUsers.userUID).
 * @param  string      $orgHandle   The owning organisation (FK tblOrganisations.orgHandle).
 * @param  string      $keyName     A human-friendly label for the key (UI display).
 * @param  array       $scopes      Array of scope strings, e.g. ['urls:read', 'account:read'].
 * @param  string|null $expiresAt   Optional 'Y-m-d H:i:s' expiry; null = never expires.
 * @return array                    ['success'=>bool, 'apiKeyUID'=>int, 'apiKeyPrefix'=>string,
 *                                   'plaintextKey'=>string] on success, or
 *                                   ['success'=>false, 'error'=>string] on failure.
 *
 * Usage example:
 *   $created = g2ml_apiGenerateKey(42, 'myorg', 'CI pipeline', ['urls:write'], null);
 *   if ($created['success']) {
 *       // Show $created['plaintextKey'] to the user ONCE, then discard it.
 *   }
 *
 * Collision handling (#149.4): apiKeyPrefix is only a 48-bit random value
 * (G2ML_API_KEY_PREFIX_BYTES = 6), guarded by the UNIQUE key
 * UQ_apikey_prefix (web/_sql/migrations/017_apikey_prefix_unique.sql). A
 * collision is astronomically unlikely but, unhandled, would surface as an
 * unhandled dbInsert() failure. Since BOTH the prefix and the secret are
 * freshly drawn together on every attempt, any duplicate-key (1062) failure
 * — on either UQ_apikey_prefix or UQ_api_key — is retried with an entirely
 * new key inside a small bounded loop, mirroring the shortCode TOCTOU retry
 * pattern in web/Go2My.Link/_functions/shorturl_create.php.
 */
function g2ml_apiGenerateKey(int $userUID, string $orgHandle, string $keyName, array $scopes, ?string $expiresAt = null): array
{
    $encodedScopes = json_encode(array_values($scopes));

    if ($encodedScopes === false)
    {
        $encodedScopes = '[]';
    }

    $duplicateKeyErrno = 1062;
    $maxAttempts       = 5;
    $insertResult      = false;
    $prefix            = null;
    $plaintextKey      = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++)
    {
        $prefix       = _g2ml_apiGeneratePrefix(); // -> G2ML_API_KEY_PREFIX_LENGTH base64url chars
        $secret       = _g2ml_apiBase64UrlToken(32); // 32 bytes -> 43 base64url chars
        $plaintextKey = 'g2ml_' . $prefix . '_' . $secret;
        $hashedKey    = hash('sha256', $plaintextKey);

        $insertResult = dbInsert(
            'INSERT INTO tblAPIKeys (userUID, orgHandle, apiKey, apiKeyPrefix, keyName, permissions, expiresAt) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            'issssss',
            [$userUID, $orgHandle, $hashedKey, $prefix, $keyName, $encodedScopes, $expiresAt]
        );

        if ($insertResult !== false)
        {
            break;
        }

        if (dbLastErrno() === $duplicateKeyErrno)
        {
            error_log('[Go2My.Link] WARNING: API key generation collision (1062) — user: ' . $userUID . ', org: ' . $orgHandle . ', attempt ' . $attempt . ' of ' . $maxAttempts);
            continue;
        }

        error_log('[Go2My.Link] ERROR: Failed to insert API key (non-duplicate) — user: ' . $userUID . ', org: ' . $orgHandle);
        break;
    }

    if ($insertResult === false)
    {
        return [
            'success' => false,
            'error'   => 'Failed to create API key.',
        ];
    }

    return [
        'success'      => true,
        'apiKeyUID'    => (int) $insertResult,
        'apiKeyPrefix' => $prefix,
        'plaintextKey' => $plaintextKey,
    ];
}

// ============================================================================
// ✅ Key verification
// ============================================================================

/**
 * Extract the presented API key from the Authorization/X-API-Key headers.
 *
 * Pure and DB-free so it is directly unit-testable. "Authorization: Bearer
 * <key>" is canonical; "X-API-Key: <key>" is accepted as a documented alias
 * and is only consulted when no Bearer token was present.
 *
 * @param  string|null $authorizationHeader  Raw value of the Authorization header, or null.
 * @param  string|null $apiKeyHeader         Raw value of the X-API-Key header, or null.
 * @return string|null                       The presented key string, or null if neither header carried one.
 */
function g2ml_apiExtractBearerToken(?string $authorizationHeader, ?string $apiKeyHeader): ?string
{
    if ($authorizationHeader !== null)
    {
        $trimmedHeader = trim($authorizationHeader);

        if (stripos($trimmedHeader, 'Bearer ') === 0)
        {
            $candidate = trim(substr($trimmedHeader, 7));

            if ($candidate !== '')
            {
                return $candidate;
            }
        }
    }

    if ($apiKeyHeader !== null)
    {
        $trimmedKey = trim($apiKeyHeader);

        if ($trimmedKey !== '')
        {
            return $trimmedKey;
        }
    }

    return null;
}

/**
 * Verify a presented API key and return its tblAPIKeys row (with `permissions`
 * decoded to an array) on success, or null on ANY failure.
 *
 * Every rejection path returns the same generic null — the caller must not be
 * able to distinguish "unknown prefix" from "wrong secret" from "expired" from
 * timing or response shape, so a caller cannot enumerate valid prefixes.
 *
 * On success, `lastUsedAt` is refreshed but throttled: it is only written when
 * the stored value is more than 60 seconds stale, so a burst of requests from
 * the same key does not turn every request into a write.
 *
 * @param  string $presented  The raw key string as presented by the client
 *                             (already extracted from its header — see
 *                             g2ml_apiExtractBearerToken()).
 * @return array|null          The key row (permissions decoded to an array),
 *                             or null if the key is invalid for any reason.
 */
function g2ml_apiVerifyKey(string $presented): ?array
{
    $presented = trim($presented);

    if ($presented === '' || strlen($presented) > 128)
    {
        return null;
    }

    if (!str_starts_with($presented, 'g2ml_'))
    {
        return null;
    }

    // ------------------------------------------------------------------------
    // 🐛 Fixed-length prefix extraction (bug fix, was delimiter-search)
    // ------------------------------------------------------------------------
    // The prefix must be extracted as EXACTLY the next G2ML_API_KEY_PREFIX_LENGTH
    // characters after "g2ml_" — NOT by searching for the first '_' as a
    // prefix/secret delimiter. The base64url alphabet used for the prefix
    // itself includes '_' (from the '/' -> '_' substitution in
    // _g2ml_apiBase64UrlToken()), so roughly 1 in 9 freshly generated keys
    // have an underscore SOMEWHERE WITHIN their own prefix. A delimiter search
    // would find that internal underscore first, truncate the extracted
    // prefix, miss the tblAPIKeys row on lookup, and reject an entirely
    // valid, non-expired, non-revoked key — a real, previously-undetected
    // defect (see tests/unit/api_auth_test.php for the regression case).
    // ------------------------------------------------------------------------
    $afterPrefix = substr($presented, 5);

    if (strlen($afterPrefix) <= G2ML_API_KEY_PREFIX_LENGTH)
    {
        return null;
    }

    $prefix    = substr($afterPrefix, 0, G2ML_API_KEY_PREFIX_LENGTH);
    $delimiter = substr($afterPrefix, G2ML_API_KEY_PREFIX_LENGTH, 1);

    if ($delimiter !== '_')
    {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9_-]{' . G2ML_API_KEY_PREFIX_LENGTH . '}$/', $prefix) !== 1)
    {
        return null;
    }

    $row = dbSelectOne(
        'SELECT apiKeyUID, userUID, orgHandle, apiKey, apiKeyPrefix, keyName, permissions, '
        . 'rateLimitOverride, lastUsedAt, expiresAt, isActive, createdAt, updatedAt '
        . 'FROM tblAPIKeys WHERE apiKeyPrefix = ?',
        's',
        [$prefix]
    );

    $presentedHash = hash('sha256', $presented);

    if ($row === false || $row === null)
    {
        // No matching prefix. Still perform a dummy constant-time compare so
        // the CPU cost of "unknown prefix" and "known prefix, wrong secret"
        // stays similar, denying an attacker an easy timing oracle. The
        // result is deliberately discarded — only the constant-time work
        // itself matters here, never its outcome.
        $g2mlDummyTimingCompare = hash_equals(hash('sha256', 'g2ml_no_such_prefix'), $presentedHash);

        unset($g2mlDummyTimingCompare);

        return null;
    }

    if (!hash_equals((string) $row['apiKey'], $presentedHash))
    {
        return null;
    }

    if ((int) $row['isActive'] !== 1)
    {
        return null;
    }

    if ($row['expiresAt'] !== null)
    {
        $expiryTimestamp = strtotime((string) $row['expiresAt']);

        if ($expiryTimestamp !== false && $expiryTimestamp < time())
        {
            return null;
        }
    }

    $ownerRow = dbSelectOne(
        'SELECT isActive, isSuspended FROM tblUsers WHERE userUID = ?',
        'i',
        [(int) $row['userUID']]
    );

    if ($ownerRow === false || $ownerRow === null)
    {
        return null;
    }

    if ((int) $ownerRow['isActive'] !== 1 || (int) $ownerRow['isSuspended'] !== 0)
    {
        return null;
    }

    $orgRow = dbSelectOne(
        'SELECT isActive FROM tblOrganisations WHERE orgHandle = ?',
        's',
        [(string) $row['orgHandle']]
    );

    if ($orgRow === false || $orgRow === null)
    {
        return null;
    }

    if ((int) $orgRow['isActive'] !== 1)
    {
        return null;
    }

    $decodedPermissions = json_decode((string) $row['permissions'], true);

    if (!is_array($decodedPermissions))
    {
        $decodedPermissions = [];
    }

    $row['permissions'] = $decodedPermissions;

    // Throttled lastUsedAt refresh — only write when stale by more than 60s.
    $shouldRefresh = true;

    if ($row['lastUsedAt'] !== null)
    {
        $lastUsedTimestamp = strtotime((string) $row['lastUsedAt']);

        if ($lastUsedTimestamp !== false && (time() - $lastUsedTimestamp) < 60)
        {
            $shouldRefresh = false;
        }
    }

    if ($shouldRefresh)
    {
        dbUpdate(
            'UPDATE tblAPIKeys SET lastUsedAt = NOW() WHERE apiKeyUID = ?',
            'i',
            [(int) $row['apiKeyUID']]
        );
    }

    return $row;
}

// ============================================================================
// 🏷️ Scope enforcement
// ============================================================================

/**
 * Check whether a verified key row carries a given scope.
 *
 * @param  array  $keyRow  A key row as returned by g2ml_apiVerifyKey() (with
 *                          `permissions` already decoded to an array).
 * @param  string $scope   The scope to check for, e.g. 'urls:write'.
 * @return bool             True if the key carries the scope.
 */
function g2ml_apiKeyHasScope(array $keyRow, string $scope): bool
{
    if (!isset($keyRow['permissions']) || !is_array($keyRow['permissions']))
    {
        return false;
    }

    return in_array($scope, $keyRow['permissions'], true);
}

// ============================================================================
// 🔐 Scope whitelist & dashboard input validation (#40)
// ============================================================================

/**
 * The canonical, whitelisted set of API scopes a key may be granted.
 *
 * Single source of truth consulted by g2ml_apiValidateScopesInput() below
 * and by the API Key Management dashboard (#40,
 * web/Go2My.Link/_admin/public_html/pages/api-keys/index.php) when rendering
 * the scope checkboxes. A new scope should be added here first, then wired
 * into the handler under public_html/api/v1/handlers/ that actually enforces
 * it.
 *
 * @return array<int, string>
 */
function g2ml_apiValidScopesList(): array
{
    return [
        'urls:read',
        'urls:write',
        'urls:delete',
        'analytics:read',
        'domains:read',
        'domains:write',
        'org:read',
        'account:read',
        'qr:link',
    ];
}

/**
 * Filter caller-supplied scope strings down to the whitelist in
 * g2ml_apiValidScopesList(), dropping anything unrecognised and
 * de-duplicating. Pure and DB-free, so it is directly unit-testable.
 *
 * Used by the API Key Management dashboard (#40) to sanitise the "create
 * key" form's scope checkboxes before calling g2ml_apiGenerateKey() — a
 * forged or stray POST value (e.g. an attacker adding an unlisted scope
 * name to the request body) must never be able to grant a key a permission
 * outside the documented set.
 *
 * @param  array $requestedScopes  Raw scope strings from user input (any
 *                                  non-string entries are silently skipped).
 * @return array                   Valid scopes only, de-duplicated, reindexed from 0.
 */
function g2ml_apiValidateScopesInput(array $requestedScopes): array
{
    $allowedScopes = g2ml_apiValidScopesList();
    $validScopes   = [];

    foreach ($requestedScopes as $requestedScope)
    {
        if (!is_string($requestedScope))
        {
            continue;
        }

        if (in_array($requestedScope, $allowedScopes, true) && !in_array($requestedScope, $validScopes, true))
        {
            $validScopes[] = $requestedScope;
        }
    }

    return $validScopes;
}

// ============================================================================
// 🗑️ Revocation & listing (org-scoped — never cross-org)
// ============================================================================

/**
 * Soft-revoke an API key (isActive = 0), scoped to the calling organisation.
 *
 * The orgHandle is bound directly into the WHERE clause so a caller can never
 * revoke a key belonging to a different organisation, even by guessing UIDs
 * (BOLA/IDOR defence — see the OWASP self-review notes in the commit body).
 *
 * @param  int    $apiKeyUID  The key to revoke.
 * @param  string $orgHandle  The organisation the caller is acting as.
 * @return bool                True if a row was updated (the key existed and
 *                             belonged to that org), false otherwise.
 */
function g2ml_apiRevokeKey(int $apiKeyUID, string $orgHandle): bool
{
    $affectedRows = dbUpdate(
        'UPDATE tblAPIKeys SET isActive = 0 WHERE apiKeyUID = ? AND orgHandle = ?',
        'is',
        [$apiKeyUID, $orgHandle]
    );

    if ($affectedRows === false)
    {
        return false;
    }

    return $affectedRows > 0;
}

/**
 * List API keys for an organisation — metadata ONLY, never the hash or any
 * value from which the plaintext key could be reconstructed.
 *
 * @param  string $orgHandle  The organisation to list keys for.
 * @return array               Rows with `permissions` decoded to an array;
 *                             `apiKey` (the hash) is never selected.
 */
function g2ml_apiListKeys(string $orgHandle): array
{
    $rows = dbSelect(
        'SELECT apiKeyUID, userUID, orgHandle, apiKeyPrefix, keyName, permissions, '
        . 'rateLimitOverride, lastUsedAt, expiresAt, isActive, createdAt, updatedAt '
        . 'FROM tblAPIKeys WHERE orgHandle = ? ORDER BY createdAt DESC',
        's',
        [$orgHandle]
    );

    if ($rows === false)
    {
        return [];
    }

    foreach ($rows as $index => $row)
    {
        $decodedPermissions = json_decode((string) $row['permissions'], true);

        if (!is_array($decodedPermissions))
        {
            $decodedPermissions = [];
        }

        $rows[$index]['permissions'] = $decodedPermissions;
    }

    return $rows;
}
