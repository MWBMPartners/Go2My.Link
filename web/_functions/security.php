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
 * 🔒 Go2My.Link — Security Utilities
 * ============================================================================
 *
 * AES-256-GCM encryption/decryption, Argon2id password hashing, CSRF token
 * management, and input sanitisation functions.
 *
 * All functions use the g2ml_ prefix to avoid collisions with framework
 * functions or user code.
 *
 * Dependencies: auth_creds.php constants (ENCRYPTION_SALT, ENCRYPTION_KEY_SECONDARY)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - OpenSSL:  https://www.php.net/manual/en/function.openssl-encrypt.php
 *     - Argon2id: https://www.php.net/manual/en/function.password-hash.php
 *     - CSRF:     https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
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
// 🌍 Non-English Letters In Web Addresses (url_ascii.php)
// ============================================================================
// Loaded only if the file is there. If it is missing, g2ml_sanitiseURL()
// refuses any address that has non-English letters or spaces, instead of
// silently cutting those letters out.
$g2mlUrlAsciiPath = __DIR__ . DIRECTORY_SEPARATOR . 'url_ascii.php';
if (file_exists($g2mlUrlAsciiPath))
{
    require_once $g2mlUrlAsciiPath;
}
unset($g2mlUrlAsciiPath);

// ============================================================================
// 🔐 AES-256-GCM Encryption / Decryption
// ============================================================================
// Used for encrypting sensitive database values (tblSettings.isSensitive = 1,
// OAuth tokens, API secrets, etc.).
//
// 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
// ============================================================================

/**
 * Encrypt a plaintext value using AES-256-GCM.
 *
 * Returns a base64-encoded string containing the IV, tag, and ciphertext
 * concatenated together: base64(iv . tag . ciphertext)
 *
 * @param  string      $plaintext  The value to encrypt
 * @param  string|null $key        Optional encryption key (defaults to ENCRYPTION_SALT)
 * @return string|false            Base64-encoded encrypted string, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
 */
function g2ml_encrypt(string $plaintext, ?string $key = null): string|false
{
    // Use the primary encryption salt if no key provided
    if ($key !== null) {
        $encryptionKey = $key;
    } elseif (defined('ENCRYPTION_SALT')) {
        $encryptionKey = ENCRYPTION_SALT;
    } else {
        $encryptionKey = '';
    }

    if ($encryptionKey === '' || $encryptionKey === 'CHANGE_ME_TO_A_64_CHAR_HEX_STRING_GENERATED_WITH_RANDOM_BYTES_32')
    {
        // 📖 Reference: https://www.php.net/manual/en/function.error-log.php
        error_log('[Go2My.Link] CRITICAL: ENCRYPTION_SALT is not configured. Cannot encrypt.');
        return false;
    }

    // Derive a 32-byte key from the hex string
    // 📖 Reference: https://www.php.net/manual/en/function.hex2bin.php
    $derivedKey = hex2bin(substr(hash('sha256', $encryptionKey), 0, 64));

    // Generate a random 12-byte IV (recommended size for GCM)
    // 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
    $iv = random_bytes(12);

    // Encrypt using AES-256-GCM
    // 📖 Reference: https://www.php.net/manual/en/function.openssl-encrypt.php
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $derivedKey,
        OPENSSL_RAW_DATA, // Return raw binary instead of base64
        $iv,
        $tag,
        '',               // No additional authenticated data (AAD)
        16                // 16-byte authentication tag
    );

    if ($ciphertext === false)
    {
        error_log('[Go2My.Link] ERROR: openssl_encrypt failed: ' . openssl_error_string());
        return false;
    }

    // Concatenate IV + tag + ciphertext and base64-encode the result
    // 📖 Reference: https://www.php.net/manual/en/function.base64-encode.php
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt a value that was encrypted with g2ml_encrypt().
 *
 * Expects a base64-encoded string containing: iv (12 bytes) + tag (16 bytes) + ciphertext
 *
 * @param  string      $encrypted  Base64-encoded encrypted string from g2ml_encrypt()
 * @param  string|null $key        Optional encryption key (defaults to ENCRYPTION_SALT)
 * @return string|false            Decrypted plaintext, or false on failure
 *
 * 📖 Reference: https://www.php.net/manual/en/function.openssl-decrypt.php
 */
function g2ml_decrypt(string $encrypted, ?string $key = null): string|false
{
    // Use the primary encryption salt if no key provided
    if ($key !== null) {
        $encryptionKey = $key;
    } elseif (defined('ENCRYPTION_SALT')) {
        $encryptionKey = ENCRYPTION_SALT;
    } else {
        $encryptionKey = '';
    }

    if ($encryptionKey === '' || $encryptionKey === 'CHANGE_ME_TO_A_64_CHAR_HEX_STRING_GENERATED_WITH_RANDOM_BYTES_32')
    {
        error_log('[Go2My.Link] CRITICAL: ENCRYPTION_SALT is not configured. Cannot decrypt.');
        return false;
    }

    // Derive the same 32-byte key
    $derivedKey = hex2bin(substr(hash('sha256', $encryptionKey), 0, 64));

    // Decode the base64 payload
    // 📖 Reference: https://www.php.net/manual/en/function.base64-decode.php
    $raw = base64_decode($encrypted, true);

    if ($raw === false || strlen($raw) < 28) // 12 (IV) + 16 (tag) minimum
    {
        error_log('[Go2My.Link] ERROR: g2ml_decrypt received invalid encrypted data.');
        return false;
    }

    // Extract IV (12 bytes), tag (16 bytes), and ciphertext (remainder)
    // 📖 Reference: https://www.php.net/manual/en/function.substr.php
    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);

    // Decrypt using AES-256-GCM
    // 📖 Reference: https://www.php.net/manual/en/function.openssl-decrypt.php
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $derivedKey,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($plaintext === false)
    {
        error_log('[Go2My.Link] ERROR: openssl_decrypt failed (wrong key or tampered data): ' . openssl_error_string());

        // FALLBACK (key-rotation support): when the caller used the default
        // primary key (ENCRYPTION_SALT, i.e. $key was not explicitly
        // supplied) and a secondary key is configured, retry decryption with
        // it before giving up. This lets values encrypted before an
        // ENCRYPTION_SALT rotation keep decrypting, as long as the retired
        // key is kept around as ENCRYPTION_KEY_SECONDARY.
        if ($key === null && defined('ENCRYPTION_KEY_SECONDARY'))
        {
            $secondaryKey = ENCRYPTION_KEY_SECONDARY;

            if (is_string($secondaryKey) && $secondaryKey !== '' && $secondaryKey !== 'CHANGE_ME_TO_ANOTHER_64_CHAR_HEX_STRING')
            {
                $derivedSecondaryKey = hex2bin(substr(hash('sha256', $secondaryKey), 0, 64));

                $secondaryPlaintext = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $derivedSecondaryKey,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag
                );

                if ($secondaryPlaintext !== false)
                {
                    return $secondaryPlaintext;
                }
            }
        }

        return false;
    }

    return $plaintext;
}

// ============================================================================
// 🔑 Password Hashing (Argon2id)
// ============================================================================
// Uses Argon2id by default with bcrypt as automatic fallback for systems
// that don't support Argon2id.
//
// 📖 Reference: https://www.php.net/manual/en/function.password-hash.php
// ============================================================================

/**
 * Hash a password using Argon2id (with bcrypt fallback).
 *
 * @param  string $password  The plaintext password to hash
 * @return string            The hashed password string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-hash.php
 */
function g2ml_hashPassword(string $password): string
{
    // Prefer Argon2id (PHP 7.3+), fall back to bcrypt
    // 📖 Reference: https://www.php.net/manual/en/password.constants.php
    if (defined('PASSWORD_ARGON2ID'))
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost'   => 4,     // 4 iterations
            'threads'     => 1,     // Single thread (shared hosting safe)
        ]);
    }
    else
    {
        // Fallback to bcrypt with cost factor 12
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
    }
}

/**
 * Verify a plaintext password against a stored hash.
 *
 * @param  string $password  The plaintext password to check
 * @param  string $hash      The stored hash from g2ml_hashPassword()
 * @return bool              True if the password matches the hash
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-verify.php
 */
#[\NoDiscard('the answer says whether the password matches; ignoring it skips the check')]
function g2ml_verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check if a password hash needs to be rehashed (e.g., algorithm upgrade).
 *
 * Call this after successful login to transparently upgrade password hashes.
 *
 * @param  string $hash  The stored password hash
 * @return bool          True if the hash should be regenerated
 *
 * 📖 Reference: https://www.php.net/manual/en/function.password-needs-rehash.php
 */
function g2ml_passwordNeedsRehash(string $hash): bool
{
    if (defined('PASSWORD_ARGON2ID'))
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }
    else
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
    }
}

// ============================================================================
// 🛡️ CSRF Token Management
// ============================================================================
// A CSRF token ("cross-site request forgery" token) is a long random secret
// put into each form. When the form comes back, its token must match one this
// visitor's session handed out. Another website cannot read our pages, so it
// cannot know the token, and so it cannot send a form in the visitor's name.
//
// How the tokens are kept (#291):
//
//   - Each form name keeps up to G2ML_CSRF_TOKENS_PER_FORM tokens (five),
//     oldest first. Showing a form again ADDS a token instead of replacing
//     the only one. So a form left open in a second tab still works. Until
//     #291 there was one token per form name, and opening the form anywhere
//     else (another tab, or the browser fetching the page in the background)
//     broke the copy that was already open.
//   - Each token still works once only. A successful check removes that one
//     token, so the same form cannot be replayed. The other tokens for the
//     same form name are left alone.
//   - Each token expires (one hour unless the caller asks for another time).
//   - Tokens are compared with hash_equals(), which takes the same time
//     whether the first character differs or the last, so the time taken
//     gives nothing away.
//   - The whole store is limited to G2ML_CSRF_MAX_STORED_TOKENS. Once in each
//     request, just before the first new token is made, expired tokens are
//     removed and, if there are still too many, the ones that would expire
//     soonest are dropped. So the session cannot grow without limit. Until
//     #291 expired tokens were never removed at all.
//
// A page that shows the same action once per row still needs one form name
// per row (#147). Each new row's token pushes out the oldest, so five tokens
// for a shared name would only make the LAST five rows of a long list work.
//
// 📖 Reference: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
// ============================================================================

if (!defined('G2ML_CSRF_TOKENS_PER_FORM'))
{
    // Five copies of one form open at once is already unusual. A sixth copy
    // pushes out the oldest, which then gets the "please reload" message.
    define('G2ML_CSRF_TOKENS_PER_FORM', 5);
}

if (!defined('G2ML_CSRF_MAX_STORED_TOKENS'))
{
    // The busiest page is the LinksPage editor: up to 200 links (see
    // G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE in linkspage_manage.php), each with
    // five small forms, so about 1,005 tokens for one view of it. Two tabs
    // of it are exactly the case #291 is for, so the limit holds two full
    // views (about 2,010) with room to spare for the visitor's other pages.
    //
    // The cost, measured on PHP 8.5: 3,000 tokens take about 400 KB of
    // session data, read and written on every request while they last.
    // Tidying happens before a request's first new token, so between
    // requests the store can briefly hold the limit plus one page's worth
    // (about 4,000 tokens, roughly 540 KB). Only someone who keeps opening
    // the biggest editors gets near this; ordinary use stays at a few
    // dozen tokens, a few kilobytes, and every token is gone within an hour.
    define('G2ML_CSRF_MAX_STORED_TOKENS', 3000);
}

if (!defined('G2ML_CSRF_RESULTS_PER_FORM'))
{
    // How many remembered results (see g2ml_csrfRememberResult()) one form
    // name keeps. Only the API key form uses this, and only for two minutes.
    define('G2ML_CSRF_RESULTS_PER_FORM', 5);
}

/**
 * The tokens stored for one form name that are still usable.
 *
 * Reads $_SESSION['_csrf_tokens'][$formName] and returns its entries, oldest
 * first, leaving out any that have expired or are not in the expected shape.
 * It also understands the shape used before #291 (a single
 * ['token' => ..., 'expires' => ...] entry rather than a list of them), so a
 * visitor whose session was started before this change was deployed can
 * still send the form they already have open.
 *
 * Nothing is written back; the callers decide what to store.
 *
 * @param  string $formName  The form identifier
 * @param  int    $now       The current time (Unix seconds)
 * @return array<int, array{token: string, expires: int}>
 */
function g2ml_csrfLiveTokens(string $formName, int $now): array
{
    if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens']))
    {
        return [];
    }

    if (!isset($_SESSION['_csrf_tokens'][$formName]) || !is_array($_SESSION['_csrf_tokens'][$formName]))
    {
        return [];
    }

    $slot = $_SESSION['_csrf_tokens'][$formName];

    // The shape used before #291: one entry, not a list of entries.
    if (isset($slot['token']))
    {
        $slot = [$slot];
    }

    $liveTokens = [];

    foreach ($slot as $entry)
    {
        if (!is_array($entry))
        {
            continue;
        }

        if (!isset($entry['token']) || !is_string($entry['token']) || $entry['token'] === '')
        {
            continue;
        }

        if (!isset($entry['expires']) || !is_int($entry['expires']))
        {
            continue;
        }

        // A token is usable up to and including its expiry second, the same
        // rule as before #291.
        if ($entry['expires'] < $now)
        {
            continue;
        }

        $liveTokens[] = [
            'token'   => $entry['token'],
            'expires' => $entry['expires'],
        ];
    }

    return $liveTokens;
}

/**
 * Store the tokens for one form name, or remove the name if none are left.
 *
 * @param string                                        $formName  The form identifier
 * @param array<int, array{token: string, expires: int}> $tokens    The tokens to keep, oldest first
 */
function g2ml_csrfStoreTokens(string $formName, array $tokens): void
{
    if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens']))
    {
        $_SESSION['_csrf_tokens'] = [];
    }

    if (count($tokens) === 0)
    {
        unset($_SESSION['_csrf_tokens'][$formName]);
        return;
    }

    $_SESSION['_csrf_tokens'][$formName] = array_values($tokens);
}

/**
 * Tidy the whole token store: drop expired tokens, then cap its size.
 *
 * g2ml_generateCSRFToken() calls this once in each request, before it makes
 * the first new token. Tidying before, not after, means a page with many
 * forms is never cut short by its own tokens: the store can hold the limit
 * plus one page's worth until the next request tidies it again.
 *
 * When the store is still over G2ML_CSRF_MAX_STORED_TOKENS after expired
 * tokens are gone, the tokens that would expire soonest are dropped first.
 * Almost every token has the same one-hour lifetime, so in practice those
 * are the oldest ones, from pages the visitor has most likely left.
 *
 * @param int|null $now  The current time (Unix seconds); null means now. Tests
 *                       pass a later time to see what happens after expiry.
 */
function g2ml_pruneCSRFTokens(?int $now = null): void
{
    if ($now === null)
    {
        $now = time();
    }

    if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens']))
    {
        $_SESSION['_csrf_tokens'] = [];
        return;
    }

    // 1. Drop expired and broken entries, and names with nothing left.
    $liveStore   = [];
    $totalTokens = 0;

    foreach (array_keys($_SESSION['_csrf_tokens']) as $formName)
    {
        $formName   = (string) $formName;
        $liveTokens = g2ml_csrfLiveTokens($formName, $now);

        if (count($liveTokens) > 0)
        {
            $liveStore[$formName] = $liveTokens;
            $totalTokens += count($liveTokens);
        }
    }

    // 2. Still too many: drop the ones that would expire soonest.
    if ($totalTokens > G2ML_CSRF_MAX_STORED_TOKENS)
    {
        $everyToken = [];

        foreach ($liveStore as $formName => $liveTokens)
        {
            foreach ($liveTokens as $position => $entry)
            {
                $everyToken[] = [
                    'formName' => (string) $formName,
                    'position' => $position,
                    'expires'  => $entry['expires'],
                ];
            }
        }

        // 📖 Reference: https://www.php.net/manual/en/function.usort.php
        // (sorting is stable since PHP 8.0, so equal times keep their order)
        usort($everyToken, function (array $left, array $right): int
        {
            return $left['expires'] <=> $right['expires'];
        });

        $dropCount = $totalTokens - G2ML_CSRF_MAX_STORED_TOKENS;

        for ($index = 0; $index < $dropCount; $index++)
        {
            $dropped = $everyToken[$index];
            unset($liveStore[$dropped['formName']][$dropped['position']]);
        }

        foreach (array_keys($liveStore) as $formName)
        {
            if (count($liveStore[$formName]) === 0)
            {
                unset($liveStore[$formName]);
            }
            else
            {
                $liveStore[$formName] = array_values($liveStore[$formName]);
            }
        }
    }

    $_SESSION['_csrf_tokens'] = $liveStore;
}

/**
 * Generate a CSRF token for a specific form.
 *
 * Adds a new token to the ones already kept for this form name (up to
 * G2ML_CSRF_TOKENS_PER_FORM; the oldest is pushed out after that) and
 * returns it for embedding in a hidden form field. See the section comment
 * above for the whole scheme.
 *
 * @param  string $formName   Unique form identifier (e.g., 'login', 'create_link')
 * @param  int    $lifetime   Token lifetime in seconds (default: 3600 = 1 hour)
 * @return string             The CSRF token string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
 */
function g2ml_generateCSRFToken(string $formName = 'default', int $lifetime = 3600): string
{
    // Tidy the store once per request, before the first new token (see
    // g2ml_pruneCSRFTokens() for why it is before and not after).
    // 📖 Reference: https://www.php.net/manual/en/language.variables.scope.php#language.variables.scope.static
    static $storeTidiedThisRequest = false;

    if ($storeTidiedThisRequest === false)
    {
        g2ml_pruneCSRFTokens();
        $storeTidiedThisRequest = true;
    }

    $now = time();

    // Generate a cryptographically secure random token
    // 📖 Reference: https://www.php.net/manual/en/function.bin2hex.php
    $token = bin2hex(random_bytes(32));

    $tokens   = g2ml_csrfLiveTokens($formName, $now);
    $tokens[] = [
        'token'   => $token,
        'expires' => $now + $lifetime,
    ];

    // Keep only the newest few for this form name.
    // 📖 Reference: https://www.php.net/manual/en/function.array-slice.php
    if (count($tokens) > G2ML_CSRF_TOKENS_PER_FORM)
    {
        $tokens = array_slice($tokens, count($tokens) - G2ML_CSRF_TOKENS_PER_FORM);
    }

    g2ml_csrfStoreTokens($formName, $tokens);

    return $token;
}

/**
 * Validate a submitted CSRF token against the session.
 *
 * The token is valid when it matches one of the unexpired tokens kept for
 * this form name. A matching token is removed (used once), so the same
 * submission cannot be replayed. The other tokens for the form name stay,
 * so another open copy of the form still works.
 *
 * A token that matches nothing removes nothing except expired tokens. Before
 * #291 any failed check also threw away the one stored token. Now that there
 * are several, throwing them all away would break the visitor's other open
 * tabs, and gives no extra protection: a 64-character random token cannot be
 * guessed, however many tries are made.
 *
 * @param  string $token     The submitted token from the form
 * @param  string $formName  The form identifier used during generation
 * @return bool              True if the token is valid and not expired
 */
#[\NoDiscard('the answer says whether the CSRF token is valid; ignoring it skips the check')]
function g2ml_validateCSRFToken(string $token, string $formName = 'default'): bool
{
    if ($token === '')
    {
        return false;
    }

    $liveTokens = g2ml_csrfLiveTokens($formName, time());
    $isValid    = false;
    $keptTokens = [];

    // Every stored token is compared; the loop never stops early, so the
    // time taken does not show which stored token matched, if any.
    foreach ($liveTokens as $entry)
    {
        // Timing-safe comparison
        // 📖 Reference: https://www.php.net/manual/en/function.hash-equals.php
        if (hash_equals($entry['token'], $token))
        {
            // Used once: the matching token is not kept.
            $isValid = true;
        }
        else
        {
            $keptTokens[] = $entry;
        }
    }

    // Writing back also drops any tokens for this form name that expired.
    g2ml_csrfStoreTokens($formName, $keptTokens);

    return $isValid;
}

/**
 * Generate a hidden HTML input field containing a CSRF token.
 *
 * Convenience function for embedding in forms.
 *
 * @param  string $formName  Unique form identifier
 * @return string            HTML hidden input element
 */
function g2ml_csrfField(string $formName = 'default'): string
{
    $token = g2ml_generateCSRFToken($formName);

    // 📖 Reference: https://www.php.net/manual/en/function.htmlspecialchars.php
    return '<input type="hidden" name="_csrf_token" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        . '">';
}

// ============================================================================
// 🔁 Remembering what a form did, for a form sent twice (#291)
// ============================================================================
// A double click sends the same form twice with the same token. The first
// request uses the token up and does the work; the second finds the token
// already used and fails. The browser shows the response to the LAST
// request, so the person sees "Session expired" and never sees what the
// first request did. For most forms that is only annoying. For the API key
// form it loses something that can never be shown again: the new key itself.
//
// So a form can ask for its result to be remembered for a short time, tied
// to the token it was sent with. If the same token comes back, the page can
// show the same result again instead of failing, and does not do the work a
// second time.
//
// The result is stored ENCRYPTED, with a key worked out from the form's
// token. The session itself no longer holds that token once it is used, so
// the stored copy is unreadable to anything that has the session file but
// not the form the browser sent. Only someone sending the exact same token
// within the time limit can get it back, and that is the person who sent the
// form a moment ago.
//
// It is also tied to an OWNER the caller names (for the API key form: the
// signed-in user and their organisation). The owner is mixed into both the
// lookup value and the encryption key, so if a different person signs in
// in the same browser within the two minutes, the copy cannot be found or
// read, even with the old token. As a second guard, signing in
// (loginUser() in auth.php) and losing a signed-in session (page_init.php)
// both throw away every remembered result.
//
// The requests cannot overlap: PHP's standard file-based sessions, which this
// site uses, let only one request at a time open a given session, so the
// second click waits until the first has finished and saved its result.
// ============================================================================

/**
 * The text that ties a remembered result to its form and owner. The lengths
 * are written in, so no two different form-and-owner pairs can give the
 * same text (for example "a|b" + "c" against "a" + "b|c").
 *
 * @param  string $formName  The form identifier
 * @param  string $owner     Who the result belongs to (see the section comment)
 * @return string
 */
function g2ml_csrfResultContext(string $formName, string $owner): string
{
    return strlen($formName) . ':' . $formName . '|' . strlen($owner) . ':' . $owner;
}

/**
 * The lookup value for a remembered result: a keyed hash of the token, so
 * the token itself is never stored.
 *
 * @param  string $token     The form's token
 * @param  string $formName  The form identifier
 * @param  string $owner     Who the result belongs to
 * @return string            64 hexadecimal characters
 *
 * 📖 Reference: https://www.php.net/manual/en/function.hash-hmac.php
 */
function g2ml_csrfResultLookup(string $token, string $formName, string $owner): string
{
    return hash_hmac('sha256', 'g2ml-csrf-result-lookup|' . g2ml_csrfResultContext($formName, $owner), $token);
}

/**
 * The encryption key for a remembered result, worked out from the token.
 * It is different from the lookup value, so knowing one gives nothing about
 * the other.
 *
 * @param  string $token     The form's token
 * @param  string $formName  The form identifier
 * @param  string $owner     Who the result belongs to
 * @return string            64 hexadecimal characters, passed to g2ml_encrypt()
 *
 * 📖 Reference: https://www.php.net/manual/en/function.hash-hkdf.php
 */
function g2ml_csrfResultKey(string $token, string $formName, string $owner): string
{
    return bin2hex(hash_hkdf('sha256', $token, 32, 'g2ml-csrf-result-key|' . g2ml_csrfResultContext($formName, $owner)));
}

/**
 * The remembered results for one form name that have not expired yet.
 *
 * @param  string $formName  The form identifier
 * @param  int    $now       The current time (Unix seconds)
 * @return array<int, array{lookup: string, sealed: string, expires: int}>
 */
function g2ml_csrfLiveResults(string $formName, int $now): array
{
    if (!isset($_SESSION['_csrf_results']) || !is_array($_SESSION['_csrf_results']))
    {
        return [];
    }

    if (!isset($_SESSION['_csrf_results'][$formName]) || !is_array($_SESSION['_csrf_results'][$formName]))
    {
        return [];
    }

    $liveResults = [];

    foreach ($_SESSION['_csrf_results'][$formName] as $entry)
    {
        if (!is_array($entry))
        {
            continue;
        }

        if (!isset($entry['lookup'], $entry['sealed'], $entry['expires']))
        {
            continue;
        }

        if (!is_string($entry['lookup']) || !is_string($entry['sealed']) || !is_int($entry['expires']))
        {
            continue;
        }

        if ($entry['expires'] < $now)
        {
            continue;
        }

        $liveResults[] = [
            'lookup'  => $entry['lookup'],
            'sealed'  => $entry['sealed'],
            'expires' => $entry['expires'],
        ];
    }

    return $liveResults;
}

/**
 * Store the remembered results for one form name, or remove the name.
 *
 * @param string                                                      $formName  The form identifier
 * @param array<int, array{lookup: string, sealed: string, expires: int}> $results   The results to keep
 */
function g2ml_csrfStoreResults(string $formName, array $results): void
{
    if (!isset($_SESSION['_csrf_results']) || !is_array($_SESSION['_csrf_results']))
    {
        $_SESSION['_csrf_results'] = [];
    }

    if (count($results) === 0)
    {
        unset($_SESSION['_csrf_results'][$formName]);
        return;
    }

    $_SESSION['_csrf_results'][$formName] = array_values($results);
}

/**
 * Remember what a form did, so a second copy of the same submission can be
 * answered with the same result (see the section comment above).
 *
 * Call it only AFTER g2ml_validateCSRFToken() has accepted $token and the
 * work has been done.
 *
 * @param  string $token     The token the form was sent with
 * @param  string $formName  The form identifier
 * @param  string $owner     Who the result belongs to, for example the user
 *                           and organisation; it must not be empty
 * @param  string $result    What to show again (for example a JSON string)
 * @param  int    $lifetime  How long to remember it, in seconds (default two minutes)
 * @return bool              True if it was stored
 */
function g2ml_csrfRememberResult(string $token, string $formName, string $owner, string $result, int $lifetime = 120): bool
{
    if ($token === '' || $owner === '')
    {
        return false;
    }

    $sealed = g2ml_encrypt($result, g2ml_csrfResultKey($token, $formName, $owner));

    if ($sealed === false)
    {
        return false;
    }

    $now       = time();
    $results   = g2ml_csrfLiveResults($formName, $now);
    $results[] = [
        'lookup'  => g2ml_csrfResultLookup($token, $formName, $owner),
        'sealed'  => $sealed,
        'expires' => $now + $lifetime,
    ];

    if (count($results) > G2ML_CSRF_RESULTS_PER_FORM)
    {
        $results = array_slice($results, count($results) - G2ML_CSRF_RESULTS_PER_FORM);
    }

    g2ml_csrfStoreResults($formName, $results);

    return true;
}

/**
 * Get back a result remembered for this exact token, if there is one.
 *
 * Call it only when g2ml_validateCSRFToken() has REFUSED the token: a token
 * that is still valid has not done its work yet, so there is nothing to
 * recall. The result stays remembered until it expires, so a third click
 * gets the same answer as the second.
 *
 * @param  string $token     The token the form was sent with
 * @param  string $formName  The form identifier
 * @param  string $owner     Who is asking; it must be the same owner the
 *                           result was remembered for, or nothing is found
 * @return string|null       The remembered result, or null if there is none
 */
function g2ml_csrfRecallResult(string $token, string $formName, string $owner): ?string
{
    if ($token === '' || $owner === '')
    {
        return null;
    }

    $results = g2ml_csrfLiveResults($formName, time());
    $lookup  = g2ml_csrfResultLookup($token, $formName, $owner);
    $sealed  = null;

    foreach ($results as $entry)
    {
        if (hash_equals($entry['lookup'], $lookup))
        {
            $sealed = $entry['sealed'];
        }
    }

    // Writing back drops any expired results for this form name.
    g2ml_csrfStoreResults($formName, $results);

    if ($sealed === null)
    {
        return null;
    }

    $result = g2ml_decrypt($sealed, g2ml_csrfResultKey($token, $formName, $owner));

    if ($result === false)
    {
        return null;
    }

    return $result;
}

/**
 * Throw away every remembered form result (see g2ml_csrfRememberResult()).
 *
 * Called when the person using the session changes: at sign-in, and when a
 * signed-in session turns out to be no longer valid.
 */
function g2ml_csrfForgetResults(): void
{
    unset($_SESSION['_csrf_results']);
}

// ============================================================================
// ↩️ Sending a browser back to the page it came from (#291)
// ============================================================================

/**
 * The path to send a browser back to, taken from the page it came from (the
 * Referer header), but only when that page is on THIS site.
 *
 * The answer is always a path that starts with one "/" (for example
 * "/pricing?plan=pro"), never a full address, so the browser can only be
 * sent somewhere on the host it is already talking to. Anything doubtful
 * gives "/", the home page:
 *   - no Referer, or one that is not a full http or https address;
 *   - a different host or port from the one this request was sent to;
 *   - a user name or password in the address ("user@host");
 *   - a backslash, a space or a control character anywhere in it (some
 *     browsers read "/\evil.com" as another website);
 *   - a path that does not start with "/", or starts with "//".
 *
 * @param  string|null $referer      The Referer header, or null if none was sent
 * @param  string      $currentHost  The host this request was sent to (HTTP_HOST)
 * @return string                    A same-host path, or "/"
 *
 * 📖 Reference: https://www.php.net/manual/en/function.parse-url.php
 */
function g2ml_safeSameHostReturnPath(?string $referer, string $currentHost): string
{
    if ($referer === null || $referer === '' || $currentHost === '')
    {
        return '/';
    }

    // A backslash, any space or any control character: refuse outright.
    if (preg_match('/[\\\\\s\x00-\x1F\x7F]/', $referer) === 1 || preg_match('/[\\\\\s\x00-\x1F\x7F]/', $currentHost) === 1)
    {
        return '/';
    }

    $parts = parse_url($referer);

    if (!is_array($parts))
    {
        return '/';
    }

    if (!isset($parts['scheme'], $parts['host']))
    {
        return '/';
    }

    $scheme = strtolower($parts['scheme']);

    if ($scheme !== 'http' && $scheme !== 'https')
    {
        return '/';
    }

    if (isset($parts['user']) || isset($parts['pass']))
    {
        return '/';
    }

    $refererAuthority = strtolower($parts['host']);

    if (isset($parts['port']))
    {
        $refererAuthority .= ':' . $parts['port'];
    }

    if (!hash_equals(strtolower($currentHost), $refererAuthority))
    {
        return '/';
    }

    $path = '/';

    if (isset($parts['path']))
    {
        $path = $parts['path'];
    }

    if (!str_starts_with($path, '/') || str_starts_with($path, '//'))
    {
        return '/';
    }

    if (isset($parts['query']) && $parts['query'] !== '')
    {
        $path .= '?' . $parts['query'];
    }

    return $path;
}

// ============================================================================
// 🧹 Input Sanitisation
// ============================================================================
// Functions for cleaning and validating user input. These are the first line
// of defence; prepared statements handle SQL injection prevention separately.
//
// 📖 Reference: https://www.php.net/manual/en/filter.filters.sanitize.php
// ============================================================================

/**
 * Sanitise a string for safe HTML output (prevent XSS).
 *
 * @param  string|null $input  The raw input string
 * @return string              HTML-entity-encoded string safe for output
 *
 * 📖 Reference: https://www.php.net/manual/en/function.htmlspecialchars.php
 */
function g2ml_sanitiseOutput(?string $input): string
{
    if ($input === null)
    {
        return '';
    }

    return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The text a form posted under $name, unchanged, for showing it again in the
 * form. Returns '' when the field is missing or was sent as a list instead of
 * text. Hand the result to formField(), which escapes it once; escaping it
 * here as well made "&" and apostrophes appear as "&amp;" and "&#039;" and
 * saved them that way (#280).
 *
 * @param  string $name  The form field's name
 * @return string        The posted text, or '' if there is none
 */
function g2ml_postedText(string $name): string
{
    if (isset($_POST[$name]) && is_string($_POST[$name]))
    {
        return $_POST[$name];
    }

    return '';
}

/**
 * Where to send someone after they sign in, or '' if $value is not safe.
 *
 * Two kinds of value are accepted:
 *   - a path on this site: one leading slash, not followed by another slash
 *     or a backslash (so "//evil.com" and "/\evil.com" are refused);
 *   - a full address on the dashboard, starting exactly with
 *     https://admin.go2my.link/ (the dashboard's sign-in check sends these,
 *     because a bare dashboard path such as /links would land on a page of
 *     the main site that does not exist, #281).
 * Any ASCII control character or backslash anywhere refuses the value:
 * browsers drop tabs and line breaks from inside an address, which can turn
 * "/<TAB>/evil.com" into "//evil.com".
 *
 * @param  string $value  The requested redirect target
 * @return string         $value unchanged when safe, otherwise ''
 */
#[\NoDiscard('the result is the safe redirect target (or an empty text); ignoring it skips the check')]
function g2ml_safePostLoginRedirect(string $value): string
{
    if ($value === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $value) === 1)
    {
        return '';
    }

    if (preg_match('/^\/(?!\/)/', $value) === 1)
    {
        return $value;
    }

    if (strncmp($value, 'https://admin.go2my.link/', 25) !== 0)
    {
        return '';
    }

    $parts = parse_url($value);

    if (!is_array($parts))
    {
        return '';
    }

    if (($parts['host'] ?? '') !== 'admin.go2my.link')
    {
        return '';
    }

    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']))
    {
        return '';
    }

    return $value;
}

/**
 * Sanitise a string for general use (strip tags, trim whitespace).
 *
 * @param  string|null $input  The raw input string
 * @return string              Cleaned string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.strip-tags.php
 */
function g2ml_sanitiseInput(?string $input): string
{
    if ($input === null)
    {
        return '';
    }

    // Trim whitespace and strip HTML/PHP tags
    return trim(strip_tags($input));
}

/**
 * Sanitise a URL for safe use (validate format and scheme).
 *
 * Plain ASCII text with no space is cleaned exactly as it always was.
 *
 * Text with a byte of 128 or above (non-English letters) or with a space is
 * first turned into plain ASCII by g2ml_urlToAscii() (url_ascii.php):
 *   - A host name such as bücher.de becomes xn--bcher-kva.de, so the saved
 *     link points at the real website. Host names that cannot be converted
 *     safely (right-to-left scripts, mixed alphabets, emoji, and so on) are
 *     refused.
 *   - In the path, query and fragment, non-English letters become %XX codes
 *     and a space becomes %20, as browsers do.
 *   - A space in the host, broken UTF-8, or a missing url_ascii.php file all
 *     give false. Letters are never silently deleted.
 * This happens before any other check, so the own-domain and
 * server-request-forgery checks see the real ASCII host.
 *
 * @param  string|null $url  The raw URL string
 * @return string|false      Sanitised URL, or false if invalid
 *
 * 📖 Reference: https://www.php.net/manual/en/function.filter-var.php
 */
#[\NoDiscard('the result is the cleaned address, or false when it is not valid; ignoring it skips the check')]
function g2ml_sanitiseURL(?string $url): string|false
{
    if ($url === null || $url === '')
    {
        return false;
    }

    $trimmed = trim($url);

    // Non-English letters or a space: convert to ASCII first (or refuse).
    if (preg_match('/[\x80-\xFF ]/', $trimmed) === 1)
    {
        if (!function_exists('g2ml_urlToAscii'))
        {
            return false;
        }

        $converted = g2ml_urlToAscii($trimmed);

        if ($converted === false)
        {
            return false;
        }

        $trimmed = $converted;
    }

    // Sanitise the URL
    // 📖 Reference: https://www.php.net/manual/en/filter.filters.sanitize.php
    $sanitised = filter_var($trimmed, FILTER_SANITIZE_URL);

    // Validate the URL structure
    // 📖 Reference: https://www.php.net/manual/en/filter.filters.validate.php
    if (filter_var($sanitised, FILTER_VALIDATE_URL) === false)
    {
        return false;
    }

    // Only allow http and https schemes
    $scheme = parse_url($sanitised, PHP_URL_SCHEME);

    if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return false;
    }

    return $sanitised;
}

// ============================================================================
// 🔎 The Stricter Link Check (#308, F1)
// ============================================================================
// g2ml_sanitiseURL() accepts some addresses that a browser reads differently
// from PHP or cannot open at all. This section adds a second check that is
// used when a link is created or edited. It can only REFUSE, never accept
// more. It has two layers:
//
//   1. Three plain-PHP rules (g2ml_urlHostShapeProblem). They give the same
//      answer on every PHP version.
//        - A host that ends in a number must be a normal dotted IPv4 address.
//          (A browser reads http://1572395022/ as 93.184.216.14.)
//        - A port must be digits only.
//        - Every host label that starts with `xn--` must be real punycode and
//          must use allowed letters.
//   2. PHP 8.5 and newer have a browser-style parser (Uri\WhatWg\Url). When it
//      exists, the link is also refused if that parser cannot read it or reads
//      a different host. PHP 8.4 skips this layer.
//
// 📖 Reference: https://url.spec.whatwg.org/
// ============================================================================

/**
 * Say which parser layer is in use: 'uri-ext' when PHP has the browser-style
 * parser, otherwise 'legacy'.
 *
 * Test seam: setting $GLOBALS['g2ml_url_parser_mode_override'] to 'legacy'
 * forces 'legacy'. Any other value is ignored, so a test can never switch the
 * parser on where PHP does not have it, and can never make a version accept more.
 *
 * @return string  'uri-ext' or 'legacy'
 */
function g2ml_urlParserMode(): string
{
    if (isset($GLOBALS['g2ml_url_parser_mode_override']) && $GLOBALS['g2ml_url_parser_mode_override'] === 'legacy')
    {
        return 'legacy';
    }

    if (class_exists('Uri\\WhatWg\\Url', false))
    {
        return 'uri-ext';
    }

    return 'legacy';
}

/**
 * Say whether the last label of a host is made only of digits, or is `0x`
 * followed by hex digits. One trailing dot is ignored.
 *
 * @param  string $host  A host name (not an IPv6 literal)
 * @return bool          True if the host ends in a number
 */
function g2ml_urlHostEndsInNumber(string $host): bool
{
    $labels = explode('.', $host);

    if (count($labels) > 1 && end($labels) === '')
    {
        array_pop($labels);
    }

    $last = end($labels);

    if ($last === '' || $last === false)
    {
        return false;
    }

    if (preg_match('/^[0-9]+$/', $last) === 1)
    {
        return true;
    }

    return preg_match('/^0[xX][0-9a-fA-F]*$/', $last) === 1;
}

/**
 * Run the three plain-PHP rules on an address that g2ml_sanitiseURL() accepted.
 *
 * @param  string $sanitisedURL  A cleaned address
 * @return string                '' if all is well, else 'number_host',
 *                               'odd_port' or 'bad_punycode'
 */
function g2ml_urlHostShapeProblem(string $sanitisedURL): string
{
    $host = parse_url($sanitisedURL, PHP_URL_HOST);

    if (!is_string($host) || $host === '')
    {
        return '';
    }

    $isIPv6 = ($host[0] === '[');

    // Rule 1: a host that ends in a number must be a normal dotted IPv4 address.
    if (!$isIPv6 && g2ml_urlHostEndsInNumber($host))
    {
        if (!g2ml_isCanonicalIPv4Literal(rtrim($host, '.')))
        {
            return 'number_host';
        }
    }

    // Rule 2: the port, read from the text after the last "@" and the host.
    if (preg_match('#^[A-Za-z][A-Za-z0-9+.\-]*://([^/?\#]*)#', $sanitisedURL, $matches) === 1)
    {
        $authority = $matches[1];
        $atSign    = strrpos($authority, '@');

        if ($atSign !== false)
        {
            $authority = substr($authority, $atSign + 1);
        }

        $afterHost = '';

        if ($authority !== '' && $authority[0] === '[')
        {
            $close = strpos($authority, ']');

            if ($close !== false)
            {
                $afterHost = substr($authority, $close + 1);
            }
        }
        else
        {
            $colon = strrpos($authority, ':');

            if ($colon !== false)
            {
                $afterHost = substr($authority, $colon);
            }
        }

        if ($afterHost !== '' && preg_match('/^:[0-9]*$/', $afterHost) !== 1)
        {
            return 'odd_port';
        }
    }

    // Rule 3: every `xn--` label must be real punycode, and its letters must
    // pass the same alphabet checks as a typed non-English host. If the
    // helper functions are missing, this fails closed.
    if (!$isIPv6)
    {
        foreach (explode('.', $host) as $label)
        {
            if (strncasecmp($label, 'xn--', 4) !== 0)
            {
                continue;
            }

            if (!function_exists('g2ml_punycodeLabelIsValid')
                || !function_exists('g2ml_punycodeDecode')
                || !function_exists('g2ml_urlAsciiLabelAlphabetsOk')
                || !g2ml_punycodeLabelIsValid($label))
            {
                return 'bad_punycode';
            }

            $decoded = g2ml_punycodeDecode(substr(strtolower($label), 4));

            if ($decoded === false || !g2ml_urlAsciiLabelAlphabetsOk($decoded))
            {
                return 'bad_punycode';
            }
        }
    }

    return '';
}

/**
 * Say whether the browser-style parser reads the same host as parse_url().
 *
 * It can only refuse. Without the parser it has nothing to add and returns
 * true. Hosts are compared in lower case with one trailing dot removed, and
 * IPv6 addresses by value.
 *
 * @param  string $sanitisedURL  A cleaned address
 * @return bool                  True if the parser reads it and agrees on the host
 */
function g2ml_browserSeesSameHost(string $sanitisedURL): bool
{
    if (g2ml_urlParserMode() !== 'uri-ext')
    {
        return true;
    }

    // Url::parse() returns null for a bad address. The constructor would throw.
    $parserClass = 'Uri\\WhatWg\\Url';

    try
    {
        $browserUrl = $parserClass::parse($sanitisedURL);
    }
    catch (\Throwable $error)
    {
        return false;
    }

    if ($browserUrl === null)
    {
        return false;
    }

    $browserHost = rtrim(strtolower((string) $browserUrl->getAsciiHost()), '.');
    $phpHost     = rtrim(strtolower((string) parse_url($sanitisedURL, PHP_URL_HOST)), '.');

    if ($browserHost === $phpHost)
    {
        return true;
    }

    if (str_starts_with($browserHost, '[') && str_starts_with($phpHost, '['))
    {
        $browserBytes = @inet_pton(trim($browserHost, '[]'));
        $phpBytes     = @inet_pton(trim($phpHost, '[]'));

        return $browserBytes !== false && $browserBytes === $phpBytes;
    }

    return false;
}

/**
 * Give the reason a destination fails the stricter check, or null if it passes.
 *
 * The reason never holds the address, so it is safe to log.
 *
 * @param  string $url  A destination address
 * @return string|null  null, or 'not_valid', 'number_host', 'odd_port',
 *                      'bad_punycode' or 'browser_host'
 */
#[\NoDiscard('the result says why a destination is refused, or null when it is fine; ignoring it skips the check')]
function g2ml_destinationProblem(string $url): ?string
{
    $sanitised = g2ml_sanitiseURL($url);

    if ($sanitised === false)
    {
        return 'not_valid';
    }

    $shapeProblem = g2ml_urlHostShapeProblem($sanitised);

    if ($shapeProblem !== '')
    {
        return $shapeProblem;
    }

    if (g2ml_urlParserMode() === 'uri-ext' && !g2ml_browserSeesSameHost($sanitised))
    {
        return 'browser_host';
    }

    return null;
}

// ============================================================================
// 🛡️ Destination Host Safety / Anti-SSRF
// ============================================================================
// Guards every server-side fetch of, and every stored, user-supplied
// destination URL against Server-Side Request Forgery (SSRF). The policy
// blocks loopback, link-local (incl. the 169.254.169.254 cloud-metadata
// endpoint), unspecified/reserved, and — by default — RFC1918 / IPv6-ULA
// private ranges, after resolving the host's A/AAAA records. URLs carrying
// userinfo (user:pass@) are rejected outright, and only http/https schemes
// are considered.
//
// Used by:
//   - Component B  validateDestination()  (F-004 / #100)
//   - Component A  createShortURL()        (F-005 / #100)
//
// 📖 Reference: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
// 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
// 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
// ============================================================================

/**
 * Determine whether a string is a STRICT canonical IPv4 dotted-quad literal:
 * exactly 4 dot-separated decimal octets, each 0-255, with no leading zeros
 * (other than a bare "0").
 *
 * inet_pton() itself is lenient about non-canonical forms — for example it
 * will happily parse "0177.0.0.1" — but curl and the WHATWG URL parser used
 * by browsers treat a leading-zero octet as OCTAL, so "0177" becomes decimal
 * 127 there, not 177. If this code trusted inet_pton's parse of such a
 * non-canonical literal, a destination host string could be classified here
 * as one (public) address while the browser/curl actually connects to a
 * completely different one (e.g. loopback) — an SSRF allowlist bypass.
 *
 * @param  string $host  The candidate host/IP string to test.
 * @return bool          True only when $host is a canonical IPv4 dotted-quad.
 *
 * 📖 Reference: https://url.spec.whatwg.org/#concept-ipv4-parser
 */
function g2ml_isCanonicalIPv4Literal(string $host): bool
{
    $octets = explode('.', $host);

    if (count($octets) !== 4)
    {
        return false;
    }

    foreach ($octets as $octet)
    {
        if (preg_match('/^(0|[1-9][0-9]{0,2})$/', $octet) !== 1)
        {
            return false;
        }

        if ((int) $octet > 255)
        {
            return false;
        }
    }

    return true;
}

/**
 * Determine whether an IP address is private, reserved, loopback, link-local,
 * or otherwise unsafe as an outbound destination.
 *
 * Always-unsafe (never overridable): loopback (127.0.0.0/8, ::1), link-local
 * incl. the 169.254.169.254 cloud-metadata endpoint (169.254.0.0/16,
 * fe80::/10), the unspecified address (0.0.0.0, ::), IPv4-mapped/compat IPv6,
 * and assorted reserved ranges.
 *
 * RFC1918 private (10/8, 172.16/12, 192.168/16) and IPv6 Unique-Local
 * (fc00::/7) are reported as private here; the CALLER decides whether to allow
 * them (see g2ml_destinationHostIsAllowed and redirect.allow_private_destinations).
 *
 * @param  string $ip  The IP literal to classify (IPv4 or IPv6).
 * @return bool        True when the IP must NOT be used as an outbound target.
 *
 * 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
 */
function g2ml_isPrivateOrReservedIp(string $ip): bool
{
    $ip = trim($ip);

    if ($ip === '')
    {
        return true;
    }

    $packed = @inet_pton($ip);

    if ($packed === false)
    {
        // Not a parseable IP literal — treat as unsafe.
        return true;
    }

    // ------------------------------------------------------------------------
    // IPv4 (4-byte packed form).
    // ------------------------------------------------------------------------
    if (strlen($packed) === 4)
    {
        // SECURITY: inet_pton silently reparses a non-canonical dotted-quad
        // (e.g. a leading-zero octet) rather than rejecting it, but curl and
        // browsers (WHATWG URL parser) read a leading-zero octet as OCTAL.
        // We cannot trust the classification below unless $ip is the exact
        // canonical decimal form inet_pton just produced — reject anything
        // else outright (fail closed).
        if (g2ml_isCanonicalIPv4Literal($ip) === false)
        {
            return true;
        }

        $alwaysBlockedV4 = [
            '0.0.0.0/8',        // "this" network / unspecified
            '127.0.0.0/8',      // loopback
            '169.254.0.0/16',   // link-local incl. 169.254.169.254 metadata
            '100.64.0.0/10',    // carrier-grade NAT (RFC 6598)
            '192.0.0.0/24',     // IETF protocol assignments
            '192.0.2.0/24',     // TEST-NET-1 (documentation)
            '198.18.0.0/15',    // benchmarking (RFC 2544)
            '198.51.100.0/24',  // TEST-NET-2 (documentation)
            '203.0.113.0/24',   // TEST-NET-3 (documentation)
            '224.0.0.0/4',      // multicast
            '240.0.0.0/4',      // reserved (incl. 255.255.255.255 broadcast)
        ];

        foreach ($alwaysBlockedV4 as $range)
        {
            if (g2ml_ipInRange($ip, $range) === true)
            {
                return true;
            }
        }

        $privateV4 = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ];

        foreach ($privateV4 as $range)
        {
            if (g2ml_ipInRange($ip, $range) === true)
            {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------------
    // IPv6 (16-byte packed form).
    // ------------------------------------------------------------------------
    // An IPv4-mapped (::ffff:0:0/96) or IPv4-compatible address can be used to
    // smuggle a private IPv4 target past an IPv6-only check, so unwrap it and
    // re-classify the embedded IPv4 value first.
    if (g2ml_ipInRange($ip, '::ffff:0:0/96') === true || g2ml_ipInRange($ip, '::/96') === true)
    {
        $embedded = inet_ntop(substr($packed, 12, 4));

        if ($embedded !== false)
        {
            return g2ml_isPrivateOrReservedIp($embedded);
        }

        return true;
    }

    $alwaysBlockedV6 = [
        '::/128',     // unspecified
        '::1/128',    // loopback
        'fe80::/10',  // link-local
        'ff00::/8',   // multicast
    ];

    foreach ($alwaysBlockedV6 as $range)
    {
        if (g2ml_ipInRange($ip, $range) === true)
        {
            return true;
        }
    }

    // Unique Local Addresses (private) — fc00::/7.
    if (g2ml_ipInRange($ip, 'fc00::/7') === true)
    {
        return true;
    }

    return false;
}

/**
 * Read the redirect.allow_private_destinations policy, FAILING CLOSED.
 *
 * Returns true only when settings are available AND the operator has
 * explicitly switched private destinations on. When the settings layer (and
 * therefore the database) is unavailable — for example security.php loaded in
 * isolation, or a DB outage — this returns false so private/internal hosts
 * stay blocked.
 *
 * @return bool  True when RFC1918/ULA private destinations are permitted.
 */
function g2ml_privateDestinationsAllowed(): bool
{
    // No settings layer loaded (e.g. unit tests, early bootstrap): fail closed.
    if (!function_exists('getSetting'))
    {
        return false;
    }

    // getSetting returns the supplied default when the DB/setting is absent.
    // The default of '0' keeps us closed unless the operator opts in.
    $value = getSetting('redirect.allow_private_destinations', '0');

    if ($value === true || $value === 1 || $value === '1')
    {
        return true;
    }

    return false;
}

/**
 * Decide whether a user-supplied destination URL is safe to fetch or store,
 * blocking SSRF against internal, loopback, link-local, and cloud-metadata
 * hosts.
 *
 * Policy:
 *   - The URL must be http or https; any other scheme is rejected.
 *   - The URL must NOT carry userinfo (user:pass@); such URLs are rejected
 *     because they are a classic SSRF/credential-leak smuggling vector.
 *   - The host is resolved: an IP literal is checked directly; a name is
 *     resolved to its A and AAAA records and EVERY resolved address is checked.
 *   - Loopback, link-local (incl. 169.254.169.254 metadata), unspecified, and
 *     reserved ranges are ALWAYS blocked.
 *   - RFC1918 private + IPv6 ULA are blocked BY DEFAULT, overridable via the
 *     redirect.allow_private_destinations setting (default off). The override
 *     fails closed when settings are unavailable.
 *   - Returns true only when the host resolves to at least one address and
 *     EVERY resolved address is allowed.
 *
 * This is a thin boolean wrapper around g2ml_resolveAllowedDestinationIps() —
 * see that function for the full resolution logic. A caller that also needs
 * to know WHICH IP(s) were validated (for example to pin an outbound request
 * to the exact address that was checked, avoiding a DNS-rebinding TOCTOU
 * between the check and the fetch) should call that function directly instead
 * of re-resolving the host itself.
 *
 * @param  string $url  The destination URL to vet.
 * @return bool         True when the destination host is allowed.
 *
 * 📖 Reference: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
 * 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
 */
#[\NoDiscard('the answer says whether the destination host is allowed; ignoring it skips the check')]
function g2ml_destinationHostIsAllowed(string $url): bool
{
    return g2ml_resolveAllowedDestinationIps($url) !== false;
}

/**
 * Resolve a destination URL's host and return every candidate IP address,
 * having already confirmed EVERY one of them is allowed under the active
 * SSRF policy — or return false when the URL/host is invalid, unresolvable,
 * or any resolved address is disallowed.
 *
 * This holds the full resolution + vetting logic that g2ml_destinationHostIsAllowed()
 * wraps (see that function's doc block for the policy). It is exposed
 * separately so a caller that is about to make the actual outbound request
 * can reuse the SAME resolved IP(s) for the fetch, instead of independently
 * re-resolving the hostname — re-resolving opens a DNS-rebinding TOCTOU
 * window (attacker DNS answers "public" for the check, then "internal" for
 * the fetch, e.g. via a TTL=0 record).
 *
 * @param  string $url  The destination URL to vet and resolve.
 * @return array|false  The list of validated candidate IPs (in resolution
 *                       order), or false when the destination is not allowed.
 *
 * 📖 Reference: https://owasp.org/www-community/attacks/Server_Side_Request_Forgery
 * 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
 */
#[\NoDiscard('the result is the checked list of addresses, or false when the destination is not allowed; ignoring it skips the check')]
function g2ml_resolveAllowedDestinationIps(string $url): array|false
{
    $url = trim($url);

    if ($url === '')
    {
        return false;
    }

    $parts = parse_url($url);

    // parse_url() only ever returns array|false, so once false is
    // eliminated the remaining value is guaranteed to be an array — no
    // separate is_array() check is needed.
    if ($parts === false)
    {
        return false;
    }

    // Scheme must be http or https.
    if (!isset($parts['scheme']))
    {
        return false;
    }

    $scheme = strtolower($parts['scheme']);

    if ($scheme !== 'http' && $scheme !== 'https')
    {
        return false;
    }

    // Reject any userinfo component (user:pass@) — an SSRF smuggling vector.
    if (isset($parts['user']) || isset($parts['pass']))
    {
        return false;
    }

    if (!isset($parts['host']) || $parts['host'] === '')
    {
        return false;
    }

    $host = $parts['host'];

    // A bracketed IPv6 literal arrives as "[::1]" from some inputs; strip the
    // brackets so inet_pton can parse it.
    if (strlen($host) >= 2 && $host[0] === '[' && $host[strlen($host) - 1] === ']')
    {
        $host = substr($host, 1, -1);
    }

    $allowPrivate = g2ml_privateDestinationsAllowed();

    // ------------------------------------------------------------------------
    // Collect the candidate IP addresses for the host.
    // ------------------------------------------------------------------------
    $candidateIPs = [];

    $packedHost = @inet_pton($host);

    // If the host is itself an IP literal, check it directly (no DNS).
    if ($packedHost !== false)
    {
        // SECURITY: a 4-byte packed result means inet_pton parsed $host as
        // IPv4. inet_pton silently reparses a non-canonical dotted-quad (e.g.
        // a leading-zero octet) rather than rejecting it, but curl and
        // browsers (WHATWG URL parser) read a leading-zero octet as OCTAL —
        // so "0177.0.0.1" would be classified here as 177.0.0.1 (public)
        // while actually connecting to 127.0.0.1 (loopback). Reject any
        // non-canonical dotted-quad outright (fail closed) instead of trusting
        // inet_pton's reinterpretation.
        if (strlen($packedHost) === 4 && g2ml_isCanonicalIPv4Literal($host) === false)
        {
            return false;
        }

        $candidateIPs[] = $host;
    }
    else
    {
        // Resolve A records (IPv4).
        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4))
        {
            foreach ($ipv4 as $address)
            {
                $candidateIPs[] = $address;
            }
        }

        // Resolve AAAA records (IPv6), when DNS lookups are available.
        if (function_exists('dns_get_record'))
        {
            $aaaa = @dns_get_record($host, DNS_AAAA);

            if (is_array($aaaa))
            {
                foreach ($aaaa as $record)
                {
                    if (isset($record['ipv6']) && $record['ipv6'] !== '')
                    {
                        $candidateIPs[] = $record['ipv6'];
                    }
                }
            }
        }
    }

    // A host that resolves to nothing is treated as not allowed (fail closed):
    // we cannot prove it is safe.
    if (count($candidateIPs) === 0)
    {
        return false;
    }

    // ------------------------------------------------------------------------
    // Every resolved address must be allowed under the active policy.
    // ------------------------------------------------------------------------
    foreach ($candidateIPs as $candidateIP)
    {
        if (g2ml_isPrivateOrReservedIp($candidateIP) === true)
        {
            // Private/ULA may be permitted by the override; the always-blocked
            // ranges are never permitted, so re-test those explicitly.
            if ($allowPrivate === true && g2ml_ipIsPrivateButOverridable($candidateIP) === true)
            {
                continue;
            }

            return false;
        }
    }

    return $candidateIPs;
}

/**
 * Whether an IP is in the RFC1918 / IPv6-ULA private set that the
 * redirect.allow_private_destinations override may unblock.
 *
 * The always-blocked ranges (loopback, link-local/metadata, unspecified,
 * reserved, multicast) are NOT included here, so the override can never
 * re-enable them.
 *
 * @param  string $ip  The IP literal to test.
 * @return bool        True when $ip is private-but-overridable.
 */
function g2ml_ipIsPrivateButOverridable(string $ip): bool
{
    $ip = trim($ip);

    if ($ip === '')
    {
        return false;
    }

    $packed = @inet_pton($ip);

    if ($packed === false)
    {
        return false;
    }

    // Unwrap an IPv4-mapped/compat IPv6 address to its embedded IPv4 value.
    if (strlen($packed) === 16)
    {
        if (g2ml_ipInRange($ip, '::ffff:0:0/96') === true || g2ml_ipInRange($ip, '::/96') === true)
        {
            $embedded = inet_ntop(substr($packed, 12, 4));

            if ($embedded !== false)
            {
                return g2ml_ipIsPrivateButOverridable($embedded);
            }

            return false;
        }
    }

    $overridable = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
    ];

    foreach ($overridable as $range)
    {
        if (g2ml_ipInRange($ip, $range) === true)
        {
            return true;
        }
    }

    return false;
}

/**
 * Sanitise an email address.
 *
 * @param  string|null $email  The raw email string
 * @return string|false        Validated email, or false if invalid
 *
 * 📖 Reference: https://www.php.net/manual/en/filter.filters.validate.php
 */
function g2ml_sanitiseEmail(?string $email): string|false
{
    if ($email === null || $email === '')
    {
        return false;
    }

    $sanitised = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

    if (filter_var($sanitised, FILTER_VALIDATE_EMAIL) === false)
    {
        return false;
    }

    return $sanitised;
}

/**
 * Test whether an IP address falls inside a single IP or CIDR range.
 *
 * Supports an exact IPv4/IPv6 address (no slash) or a CIDR block
 * ("ip/prefix"). Comparison is done on the packed binary form so IPv4 and
 * IPv6 are both handled. Address families must match: an IPv4 address never
 * matches an IPv6 range and vice versa.
 *
 * @param  string $ip     The candidate IP address to test.
 * @param  string $range  A single IP ("203.0.113.9") or a CIDR ("10.0.0.0/8").
 * @return bool           True when $ip is the address or inside the range.
 *
 * 📖 Reference: https://www.php.net/manual/en/function.inet-pton.php
 */
function g2ml_ipInRange(string $ip, string $range): bool
{
    $ip    = trim($ip);
    $range = trim($range);

    if ($ip === '' || $range === '')
    {
        return false;
    }

    // No slash means an exact single-address comparison.
    if (strpos($range, '/') === false)
    {
        $packedIp    = @inet_pton($ip);
        $packedRange = @inet_pton($range);

        if ($packedIp === false || $packedRange === false)
        {
            return false;
        }

        return hash_equals($packedRange, $packedIp);
    }

    // CIDR form: "subnet/prefix".
    $parts = explode('/', $range, 2);
    $subnet = $parts[0];
    $prefixText = $parts[1];

    if ($prefixText === '' || !ctype_digit($prefixText))
    {
        return false;
    }

    $packedIp     = @inet_pton($ip);
    $packedSubnet = @inet_pton($subnet);

    if ($packedIp === false || $packedSubnet === false)
    {
        return false;
    }

    // Address families must match (same packed length: 4 for v4, 16 for v6).
    if (strlen($packedIp) !== strlen($packedSubnet))
    {
        return false;
    }

    $prefixBits = (int) $prefixText;
    $maxBits    = strlen($packedIp) * 8;

    if ($prefixBits < 0 || $prefixBits > $maxBits)
    {
        return false;
    }

    // A /0 matches everything within the family.
    if ($prefixBits === 0)
    {
        return true;
    }

    $fullBytes      = intdiv($prefixBits, 8);
    $remainingBits  = $prefixBits % 8;

    // Compare the whole bytes covered by the prefix.
    if ($fullBytes > 0)
    {
        if (hash_equals(substr($packedSubnet, 0, $fullBytes), substr($packedIp, 0, $fullBytes)) === false)
        {
            return false;
        }
    }

    // Compare the partial trailing byte, if the prefix is not byte-aligned.
    if ($remainingBits > 0)
    {
        $mask     = (~0 << (8 - $remainingBits)) & 0xFF;
        $ipByte   = ord($packedIp[$fullBytes]) & $mask;
        $netByte  = ord($packedSubnet[$fullBytes]) & $mask;

        if ($ipByte !== $netByte)
        {
            return false;
        }
    }

    return true;
}

/**
 * Determine whether the immediate peer (REMOTE_ADDR) is a configured trusted
 * proxy whose forwarded headers may be believed.
 *
 * Trust is opt-in via the OPTIONAL constant TRUSTED_PROXIES (a comma-separated
 * list of IPs and/or CIDR ranges, normally defined in the shared
 * auth_creds.php). When TRUSTED_PROXIES is UNDEFINED or empty, NO proxy is
 * trusted, which is the correct default for Dreamhost where the app is served
 * directly.
 *
 * @param  string $remoteAddr  The immediate peer address (REMOTE_ADDR).
 * @return bool                 True only when $remoteAddr is in the allowlist.
 */
function g2ml_isTrustedProxy(string $remoteAddr): bool
{
    if ($remoteAddr === '')
    {
        return false;
    }

    if (!defined('TRUSTED_PROXIES'))
    {
        return false;
    }

    $configuredValue = constant('TRUSTED_PROXIES');

    if (!is_string($configuredValue))
    {
        return false;
    }

    $configuredValue = trim($configuredValue);

    if ($configuredValue === '')
    {
        return false;
    }

    $entries = explode(',', $configuredValue);

    foreach ($entries as $entry)
    {
        $entry = trim($entry);

        if ($entry === '')
        {
            continue;
        }

        if (g2ml_ipInRange($remoteAddr, $entry) === true)
        {
            return true;
        }
    }

    return false;
}

/**
 * Get the client's real IP address.
 *
 * SECURITY (F-001 / #95): forwarded headers (X-Forwarded-For, X-Real-Ip) are
 * trivially spoofable by any client, so they are IGNORED BY DEFAULT. The
 * function returns REMOTE_ADDR — the address of the immediate peer — unless
 * that peer is an explicitly configured trusted proxy.
 *
 * Forwarded headers are honoured ONLY when REMOTE_ADDR is listed in the
 * OPTIONAL TRUSTED_PROXIES constant (see g2ml_isTrustedProxy). When trusted,
 * the RIGHT-most entry of X-Forwarded-For that is not itself a trusted proxy
 * is taken — this is the standard correct choice, because each trusted hop
 * appends the address it received the connection from, so the right-most
 * untrusted value is the real client. The chosen value is validated with
 * FILTER_VALIDATE_IP and the function falls back to REMOTE_ADDR if it is
 * missing or invalid.
 *
 * On Dreamhost (direct-served, no TRUSTED_PROXIES) this always returns
 * REMOTE_ADDR, so the activity log and the per-IP rate limits both see the
 * genuine peer address and cannot be poisoned or evaded by forged headers.
 *
 * @return string  The client IP address (IPv4 or IPv6)
 *
 * 📖 Reference: https://www.php.net/manual/en/reserved.variables.server.php
 */
function g2ml_getClientIP(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!is_string($remoteAddr))
    {
        $remoteAddr = '';
    }

    $remoteAddr = trim($remoteAddr);

    if ($remoteAddr === '')
    {
        $remoteAddr = '0.0.0.0';
    }

    // Default and only-safe path: trust nobody, use the immediate peer.
    if (g2ml_isTrustedProxy($remoteAddr) === false)
    {
        return $remoteAddr;
    }

    // REMOTE_ADDR is a configured trusted proxy: we MAY believe X-Forwarded-For.
    $forwardedHeader = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if (is_string($forwardedHeader) && trim($forwardedHeader) !== '')
    {
        $forwardedIPs = explode(',', $forwardedHeader);

        // Walk RIGHT to LEFT, skipping our own trusted hops, and take the
        // first (right-most) entry that is NOT a trusted proxy — that is the
        // genuine client as seen by the closest hop we trust.
        for ($index = count($forwardedIPs) - 1; $index >= 0; $index--)
        {
            $candidate = trim($forwardedIPs[$index]);

            if ($candidate === '')
            {
                continue;
            }

            if (g2ml_isTrustedProxy($candidate) === true)
            {
                // This hop is one of ours; keep walking left.
                continue;
            }

            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false)
            {
                return $candidate;
            }

            // First untrusted entry is malformed; do not trust anything left
            // of it. Fall through to REMOTE_ADDR.
            break;
        }
    }

    // No usable forwarded value: fall back to the trusted proxy's own address.
    return $remoteAddr;
}

/**
 * Return the client IP via g2ml_getClientIP() when it is already defined,
 * otherwise a caller-supplied default.
 *
 * A handful of call sites (e.g. the early error handler, which may run
 * before security.php has finished loading) cannot assume
 * g2ml_getClientIP() is defined yet, so they historically repeated their
 * own `function_exists('g2ml_getClientIP') ? ... : ...` guard with their
 * own default. This centralises that one guard so every caller only
 * supplies the default value it needs (#118).
 *
 * @param  ?string $default  Value returned when g2ml_getClientIP() is not
 *                            yet defined. Defaults to the same '0.0.0.0'
 *                            sentinel g2ml_getClientIP() itself falls back
 *                            to when REMOTE_ADDR is missing.
 * @return ?string
 */
function g2ml_clientIpOrDefault(?string $default = '0.0.0.0'): ?string
{
    if (function_exists('g2ml_getClientIP'))
    {
        return g2ml_getClientIP();
    }

    return $default;
}

/**
 * Generate a cryptographically secure random token (hex string).
 *
 * @param  int    $bytes  Number of random bytes (output will be 2x this length)
 * @return string         Hex-encoded random string
 *
 * 📖 Reference: https://www.php.net/manual/en/function.random-bytes.php
 */
function g2ml_generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
