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
 * inactive/suspended, the owner is no longer an administrator of the key's
 * organisation (global administrators excepted, #328, owner decision D-033),
 * or the owning organisation is inactive.
 *
 * Whether the plan allows API access at all (#328) is also decided here, by
 * g2ml_apiPlanAccess(). It is separate from verification on purpose: a good key
 * on a plan without API access gets a plain explanation, not "invalid key".
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne/dbUpdate/dbInsert), and, at
 *               call time only, entitlements.php and pricing.php (for
 *               g2ml_apiPlanAccess()). settings.php is NOT required by this
 *               file directly.
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
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

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
// 💷 Does the plan include API access? (#328, owner decision D-025)
// ============================================================================
// Asked in two places: when a key is made (g2ml_apiGenerateKey() below) and on
// every call that presents a key (the /api/v1 front controller).
//
// Why this does not simply call g2ml_canUseFeature($orgHandle, 'hasAPIAccess'):
// that function was built never to block anybody. It answers "yes" when the
// plan cannot be read, "yes" for the shared "[default]" organisation, and "yes"
// whenever the browser making the request is signed in as a global
// administrator. For API access all three are wrong:
//   - a plan that cannot be read must refuse (fail closed);
//   - people with no organisation count as Free (decision 25 on #243);
//   - what counts is who OWNS the key, not who is signed in to the browser
//     that happens to send it.
// The plan data it reads is the same: the plan's hasAPIAccess value, or, when
// the pricing engine is switched on, the engine's answer for the same flag.
// ============================================================================

/**
 * May this user's API key, belonging to this organisation, be made or used?
 *
 * Answers one of:
 *   'allowed'       The organisation's plan includes API access, or the key's
 *                   owner is a global administrator (who is never limited).
 *   'not_included'  The plan does not include API access.
 *   'check_failed'  The owner or the plan could not be read. Callers refuse.
 *
 * Which plan: the one on the organisation's own row, including the shared
 * "[default]" organisation that people with no organisation belong to. As
 * everywhere else, an organisation with no plan, or a plan that has been
 * switched off, gets the first active plan (lowest sortOrder). No plan is
 * named here.
 *
 * Pricing engine switched off (as at launch): the plan row's hasAPIAccess.
 * Switched on: the engine's answer, which also applies any per-organisation
 * grant or refusal. If the engine is on but cannot answer, the result is
 * 'check_failed'; the older columns are not used instead, because they cannot
 * see a per-organisation refusal.
 *
 * Nothing is cached, so a change of plan or role counts from the next call.
 *
 * @param  int    $userUID    The key's owner (or, when making a key, its maker).
 * @param  string $orgHandle  The organisation the key belongs to.
 * @return string             'allowed', 'not_included' or 'check_failed'.
 */
#[\NoDiscard('the answer says whether API access may be used; ignoring it skips the check')]
function g2ml_apiPlanAccess(int $userUID, string $orgHandle): string
{
    try
    {
        $ownerRow = dbSelectOne(
            'SELECT `role` FROM `tblUsers` WHERE `userUID` = ?',
            'i',
            [$userUID]
        );

        if ($ownerRow === false || $ownerRow === null)
        {
            error_log('[Go2My.Link] ERROR: API plan check - could not read user ' . $userUID . ' for org "' . $orgHandle . '" - refusing.');

            return 'check_failed';
        }

        if ((string) $ownerRow['role'] === 'GlobalAdmin')
        {
            return 'allowed';
        }

        if (function_exists('g2ml_pricingEngineEnabled') && g2ml_pricingEngineEnabled() === true)
        {
            $engineTier = g2ml_pricingResolveOrgTier($orgHandle);

            if (!is_array($engineTier))
            {
                error_log('[Go2My.Link] ERROR: API plan check - the pricing engine could not resolve org "' . $orgHandle . '" - refusing.');

                return 'check_failed';
            }

            if (($engineTier['hasAPIAccess'] ?? false) === true)
            {
                return 'allowed';
            }

            return 'not_included';
        }

        $planRow = _g2ml_fetchOrgTierRow($orgHandle);

        if ($planRow !== false && ($planRow === null || (int) ($planRow['isActive'] ?? 0) !== 1))
        {
            $planRow = _g2ml_fetchFallbackTierRow();
        }

        if ($planRow === false || $planRow === null)
        {
            error_log('[Go2My.Link] ERROR: API plan check - no plan could be read for org "' . $orgHandle . '" - refusing.');

            return 'check_failed';
        }

        if ((int) ($planRow['hasAPIAccess'] ?? 0) === 1)
        {
            return 'allowed';
        }

        return 'not_included';
    }
    catch (Throwable $unexpectedError)
    {
        error_log('[Go2My.Link] ERROR: API plan check - unexpected exception for org "' . $orgHandle . '": ' . $unexpectedError->getMessage() . ' - refusing.');

        return 'check_failed';
    }
}

/**
 * The plain sentence shown when API access is refused.
 *
 * Neither sentence offers an upgrade: there is no way to buy one yet. If the
 * translation is missing (seed 083 not applied), the English below is used
 * rather than the key name, because an API caller sees this text as it is.
 *
 * @param  string $access  'not_included' or 'check_failed', as returned by
 *                         g2ml_apiPlanAccess(). Anything else is treated as
 *                         'check_failed'.
 * @return string          Plain text, not escaped.
 */
function g2ml_apiPlanMessage(string $access): string
{
    if ($access === 'not_included')
    {
        $translationKey = 'api.error_plan_not_included';
        $english        = 'The current plan does not include API access, so API keys cannot be created or used.';
    }
    else
    {
        $translationKey = 'api.error_plan_check_failed';
        $english        = 'We could not check whether the plan includes API access. Please try again shortly.';
    }

    if (function_exists('__'))
    {
        $translated = __($translationKey);

        if ($translated !== $translationKey)
        {
            return $translated;
        }
    }

    return $english;
}

// ============================================================================
// 👤 May the owner still hold this key? (#328, owner decision D-033)
// ============================================================================

/**
 * Whether a key's owner may still hold a key of this organisation: they must be
 * an administrator of it now. A global administrator may act in any
 * organisation. Nothing is stored on the key, so the key works again as soon as
 * the person is an administrator of the organisation again.
 *
 * Handles are compared without regard to capitals, as the database compares
 * them. Pure and database-free.
 *
 * @param  array  $ownerRow   The owner's `orgHandle` and `role` from tblUsers.
 * @param  string $orgHandle  The key's organisation.
 * @return bool
 */
function g2ml_apiOwnerMayHoldKey(array $ownerRow, string $orgHandle): bool
{
    $role = (string) ($ownerRow['role'] ?? '');

    if ($role === 'GlobalAdmin')
    {
        return true;
    }

    if ($role === 'Admin' && strcasecmp((string) ($ownerRow['orgHandle'] ?? ''), $orgHandle) === 0)
    {
        return true;
    }

    return false;
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
 * No key is made unless g2ml_apiPlanAccess() answers 'allowed' and the owner
 * passes g2ml_apiOwnerMayHoldKey() (#328). The role is read from the database,
 * because a signed-in person's session can still carry a role they have lost.
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
    $planAccess = g2ml_apiPlanAccess($userUID, $orgHandle);

    if ($planAccess !== 'allowed')
    {
        return [
            'success' => false,
            'error'   => g2ml_apiPlanMessage($planAccess),
        ];
    }

    $ownerRow = dbSelectOne(
        'SELECT `orgHandle`, `role` FROM `tblUsers` WHERE `userUID` = ?',
        'i',
        [$userUID]
    );

    if (!is_array($ownerRow) || !g2ml_apiOwnerMayHoldKey($ownerRow, $orgHandle))
    {
        $notAdminMessage = 'Only an administrator of the organisation can create API keys.';

        if (function_exists('__') && __('apikeys.error_not_admin') !== 'apikeys.error_not_admin')
        {
            $notAdminMessage = __('apikeys.error_not_admin');
        }

        return [
            'success' => false,
            'error'   => $notAdminMessage,
        ];
    }

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
 * Decide whether a stored API key expiry must be treated as expired.
 *
 * A NULL or empty expiry means the key never expires. A value strtotime()
 * cannot parse is treated as expired, not as never-expiring, because a
 * security check must refuse when it is unsure (#206) — the same choice
 * g2ml_isExportRequestDownloadable() in data_rights.php makes for export
 * downloads.
 *
 * Pure and DB-free: no I/O, so it is safe to unit test directly.
 *
 * @param  string|null $expiresAt     The stored expiresAt value, or null
 *                                     when the key has no expiry.
 * @param  int         $nowTimestamp  The current time as a Unix timestamp,
 *                                     passed in so this function stays pure
 *                                     and testable without mocking time().
 * @return bool                       True when the key must be treated as
 *                                     expired.
 */
function g2ml_apiKeyIsExpired(?string $expiresAt, int $nowTimestamp): bool
{
    if ($expiresAt === null || trim($expiresAt) === '')
    {
        return false;
    }

    $expiryTimestamp = strtotime($expiresAt);

    if ($expiryTimestamp === false)
    {
        return true;
    }

    if ($expiryTimestamp < $nowTimestamp)
    {
        return true;
    }

    return false;
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

    $expiresAtValue = null;

    if ($row['expiresAt'] !== null)
    {
        $expiresAtValue = (string) $row['expiresAt'];
    }

    if (g2ml_apiKeyIsExpired($expiresAtValue, time()))
    {
        return null;
    }

    $ownerRow = dbSelectOne(
        'SELECT `isActive`, `isSuspended`, `orgHandle`, `role` FROM `tblUsers` WHERE `userUID` = ?',
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

    // A key works only while its owner is an administrator of the key's
    // organisation (#328, D-033). Removing or demoting a member, or a member
    // moving to another organisation, leaves the key row in place, so without
    // this the person could go on reading and changing the organisation's
    // links.
    if (!g2ml_apiOwnerMayHoldKey($ownerRow, (string) $row['orgHandle']))
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
 * it — tests/unit/api_scopes_used_test.php fails the build if a scope is
 * added here without a route or handler checking for it.
 *
 * domains:read and domains:write were removed (#208): they were offered on
 * the keys page and accepted into a key's stored permissions, but no route
 * or handler checked for either one, so ticking them did nothing. Add them
 * back together with the domain endpoints themselves (the #91 follow-up),
 * not before.
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
 *                             `ownerBelongs` is 0 when the owner has left the
 *                             organisation, and `ownerIsAdmin` is 0 when the
 *                             owner is not an administrator (#328); both are 1
 *                             for a global administrator. g2ml_apiVerifyKey()
 *                             refuses a key if either is 0.
 */
function g2ml_apiListKeys(string $orgHandle): array
{
    $rows = dbSelect(
        'SELECT `k`.`apiKeyUID`, `k`.`userUID`, `k`.`orgHandle`, `k`.`apiKeyPrefix`, `k`.`keyName`, `k`.`permissions`, '
        . '`k`.`rateLimitOverride`, `k`.`lastUsedAt`, `k`.`expiresAt`, `k`.`isActive`, `k`.`createdAt`, `k`.`updatedAt`, '
        . 'EXISTS (SELECT 1 FROM `tblUsers` AS `u` WHERE `u`.`userUID` = `k`.`userUID` '
        . 'AND (`u`.`orgHandle` = `k`.`orgHandle` OR `u`.`role` = \'GlobalAdmin\')) AS `ownerBelongs`, '
        . 'EXISTS (SELECT 1 FROM `tblUsers` AS `a` WHERE `a`.`userUID` = `k`.`userUID` '
        . 'AND `a`.`role` IN (\'Admin\', \'GlobalAdmin\')) AS `ownerIsAdmin` '
        . 'FROM `tblAPIKeys` AS `k` WHERE `k`.`orgHandle` = ? ORDER BY `k`.`createdAt` DESC',
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
