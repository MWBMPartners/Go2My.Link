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
 * 🔞 Go2My.Link — Adult Content Detection & Age-Gate Cookie (Component C.5, #50)
 * ============================================================================
 *
 * Two independent, reusable pieces of logic:
 *
 *   1. g2ml_isAdultDomain() — checks a destination URL's host against a
 *      curated (operator-configurable) list of known adult-content domains,
 *      used by web/_functions/linkspage_manage.php to AUTO-FLAG a LinksPage
 *      item's requiresAgeGate column when it is added or edited.
 *
 *   2. The g2ml_agegate cookie — a SIGNED (HMAC-SHA256), boolean, good-faith
 *      age confirmation. Used by web/Lnks.page/public_html/index.php (and
 *      the interstitial it renders — see linkspage_renderer.php's
 *      g2ml_linkspageRenderAgeGateInterstitial()) to remember that a visitor
 *      has already confirmed they are of legal age, without ever asking for
 *      or storing a date of birth.
 *
 * 🔒 SECURITY / PRIVACY:
 *   - This is deliberately a GOOD-FAITH gate, not an identity check. No date
 *     of birth, and no other personal data, is ever collected, requested,
 *     stored, or transmitted anywhere by this file — only a single boolean
 *     "confirmed" fact, expressed as a signed token with an expiry.
 *   - The cookie VALUE is signed with HMAC-SHA256 using
 *     ENCRYPTION_KEY_SECONDARY (the same constant the file header of
 *     web/_auth_keys/auth_creds.example.php already documents as being used
 *     for "session tokens, CSRF tokens" — this is the same class of use).
 *     A visitor (or any script running in their browser) can read or delete
 *     this cookie, but cannot FORGE a new valid one without the server's
 *     secret key — tampering with the expiry or version invalidates the
 *     signature and g2ml_ageGateVerifyToken() rejects it outright.
 *   - hash_equals() (timing-safe) is used for the signature comparison —
 *     never `===`/`==` on secret-derived material.
 *   - The cookie is Secure + SameSite=Lax with a short, fixed lifetime (see
 *     G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS) — it is NOT a permanent grant.
 *
 * This file is DB-free-testable: g2ml_isAdultDomain()'s operator-configured
 * allowlist read is guarded by function_exists('getSetting'), and the cookie
 * helpers touch only $_COOKIE / setcookie() plus the HMAC key constant —
 * mirroring the pattern already used by linkspage_renderer.php.
 *
 * Functions:
 *   - g2ml_adultDomainDefaultList()    — the curated, built-in fallback list
 *   - g2ml_adultDomainAllowlist()      — operator-configured list (setting),
 *                                         falling back to the built-in list
 *   - g2ml_isAdultDomain()             — host-match check against the allowlist
 *   - g2ml_ageGateSign()               — HMAC-SHA256 signing primitive
 *   - g2ml_ageGateBuildToken()         — build a signed "confirmed" token
 *   - g2ml_ageGateVerifyToken()        — verify a token's signature + expiry
 *   - g2ml_ageGateHasValidCookie()     — read + verify $_COOKIE[g2ml_agegate]
 *   - g2ml_ageGateSetConfirmedCookie() — set the signed cookie after confirmation
 *
 * Dependencies (all OPTIONAL, guarded by function_exists()):
 *   - web/_functions/settings.php (getSetting()) — for the operator-configured
 *     adult-domain allowlist override.
 *   - ENCRYPTION_KEY_SECONDARY (web/_auth_keys/auth_creds.php) — for cookie
 *     signing. Falls back to an empty key (fail-closed: a token signed with
 *     an empty key can still be verified consistently within one process,
 *     but see the note on g2ml_ageGateSign()).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#50)
 *
 * 📖 References:
 *     - HMAC:        https://www.php.net/manual/en/function.hash-hmac.php
 *     - hash_equals:  https://www.php.net/manual/en/function.hash-equals.php
 *     - setcookie:    https://www.php.net/manual/en/function.setcookie.php
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 📋 Constants
// ============================================================================

/** The cookie name carrying the signed, boolean age-gate confirmation. */
if (!defined('G2ML_AGEGATE_COOKIE_NAME'))
{
    define('G2ML_AGEGATE_COOKIE_NAME', 'g2ml_agegate');
}

/**
 * Cookie / token lifetime in seconds. Deliberately SHORT-LIVED (24 hours) —
 * this is a good-faith re-prompt, not a permanent grant, and re-confirming
 * occasionally is a reasonable, low-friction trade-off against a cookie that
 * silently unlocks gated content indefinitely.
 */
if (!defined('G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS'))
{
    define('G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS', 86400);
}

/** Token format version — bumping this instantly invalidates every outstanding cookie. */
if (!defined('G2ML_AGEGATE_TOKEN_VERSION'))
{
    define('G2ML_AGEGATE_TOKEN_VERSION', 'v1');
}

// ============================================================================
// 🌐 Adult-domain allowlist
// ============================================================================

/**
 * The curated, built-in default list of well-known adult-content domains.
 * Deliberately small and generic (a handful of the most widely-known
 * services) — operators extend/replace it via the 'linkspage.adult_domains'
 * setting (a JSON array) rather than this list needing constant code edits.
 *
 * @return array<int, string>  Lower-case, bare (no scheme/www) hostnames.
 */
function g2ml_adultDomainDefaultList(): array
{
    return [
        'onlyfans.com',
        'fansly.com',
        'pornhub.com',
        'xvideos.com',
        'xnxx.com',
        'chaturbate.com',
        'youporn.com',
        'redtube.com',
    ];
}

/**
 * Resolve the EFFECTIVE adult-domain allowlist: the operator-configured
 * 'linkspage.adult_domains' setting (JSON array of hostnames) when present
 * and non-empty, otherwise the curated built-in default list.
 *
 * DB-free-testable: guarded by function_exists('getSetting') so this file
 * (and its unit tests) can run without a database connection, mirroring the
 * pattern used throughout web/Lnks.page/_functions/linkspage_renderer.php.
 *
 * @return array<int, string>  Lower-case, bare (no scheme/www) hostnames.
 */
function g2ml_adultDomainAllowlist(): array
{
    if (function_exists('getSetting'))
    {
        $configuredDomains = getSetting('linkspage.adult_domains', null);

        if (is_array($configuredDomains) && count($configuredDomains) > 0)
        {
            $normalisedDomains = [];

            foreach ($configuredDomains as $domainEntry)
            {
                if (is_string($domainEntry) && trim($domainEntry) !== '')
                {
                    $normalisedDomains[] = strtolower(trim($domainEntry));
                }
            }

            if (count($normalisedDomains) > 0)
            {
                return $normalisedDomains;
            }
        }
    }

    return g2ml_adultDomainDefaultList();
}

/**
 * Determine whether a destination URL's host matches the adult-domain
 * allowlist (exact match, or a subdomain of an allowlisted domain).
 *
 * Used by web/_functions/linkspage_manage.php to auto-flag a LinksPage
 * item's requiresAgeGate column on add/edit. This is a HOST comparison
 * only — it never fetches the URL, and it is not itself a security/scheme
 * validator (that is g2ml_sanitiseURL()'s job); a malformed or schemeless
 * URL simply fails to match (returns false), it never throws.
 *
 * @param  string|null $url  A destination URL (ideally already scheme-validated by the caller).
 * @return bool
 */
function g2ml_isAdultDomain(?string $url): bool
{
    if ($url === null)
    {
        return false;
    }

    $trimmedURL = trim($url);

    if ($trimmedURL === '')
    {
        return false;
    }

    $host = parse_url($trimmedURL, PHP_URL_HOST);

    if (!is_string($host) || $host === '')
    {
        return false;
    }

    $host = strtolower($host);

    if (str_starts_with($host, 'www.'))
    {
        $host = substr($host, 4);
    }

    $allowlist = g2ml_adultDomainAllowlist();

    foreach ($allowlist as $adultDomain)
    {
        $adultDomain = strtolower(trim($adultDomain));

        if ($adultDomain === '')
        {
            continue;
        }

        if ($host === $adultDomain)
        {
            return true;
        }

        // Subdomain match (e.g. "cams.chaturbate.com" matches "chaturbate.com").
        if (str_ends_with($host, '.' . $adultDomain))
        {
            return true;
        }
    }

    return false;
}

// ============================================================================
// 🔏 Signed age-gate token (HMAC-SHA256)
// ============================================================================

/**
 * The HMAC signing key for the age-gate token. Reuses
 * ENCRYPTION_KEY_SECONDARY (see the file header) — falls back to an empty
 * string when the constant is not defined, which still produces a
 * CONSISTENT (but weak) signature within a single misconfigured environment;
 * this mirrors g2ml_encrypt()'s own defensive fallback style rather than
 * throwing, since a bad signature only ever fails CLOSED (the visitor is
 * re-prompted), never open.
 *
 * @return string
 */
function _g2ml_ageGateSigningKey(): string
{
    if (defined('ENCRYPTION_KEY_SECONDARY') && ENCRYPTION_KEY_SECONDARY !== '')
    {
        return ENCRYPTION_KEY_SECONDARY;
    }

    error_log('[Go2My.Link] WARNING: ENCRYPTION_KEY_SECONDARY is not configured — age-gate cookie signing is weakened.');

    return '';
}

/**
 * Sign a token payload (the version + expiry) with HMAC-SHA256.
 *
 * @param  string $expiresAtString  The token's expiry Unix timestamp, as a string.
 * @return string  A hex-encoded HMAC-SHA256 signature.
 */
function g2ml_ageGateSign(string $expiresAtString): string
{
    $signingKey = _g2ml_ageGateSigningKey();

    return hash_hmac('sha256', G2ML_AGEGATE_TOKEN_VERSION . '.' . $expiresAtString, $signingKey);
}

/**
 * Build a signed age-gate token: "v1.<expiresAt>.<hmacSignature>".
 *
 * @param  int|null $expiresAt  Unix timestamp when the token expires, or null
 *                               to use G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS
 *                               from now.
 * @return string
 */
function g2ml_ageGateBuildToken(?int $expiresAt = null): string
{
    if ($expiresAt === null)
    {
        $expiresAt = time() + G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS;
    }

    $expiresAtString = (string) $expiresAt;
    $signature        = g2ml_ageGateSign($expiresAtString);

    return G2ML_AGEGATE_TOKEN_VERSION . '.' . $expiresAtString . '.' . $signature;
}

/**
 * Verify a signed age-gate token: correct version, well-formed expiry,
 * VALID signature (timing-safe comparison), and not yet expired.
 *
 * A token failing ANY of these checks — including a tampered expiry that no
 * longer matches its own signature — is rejected outright. This function
 * never throws; a malformed token simply returns false.
 *
 * @param  string|null $token
 * @return bool
 */
function g2ml_ageGateVerifyToken(?string $token): bool
{
    if ($token === null)
    {
        return false;
    }

    $trimmedToken = trim($token);

    if ($trimmedToken === '')
    {
        return false;
    }

    $parts = explode('.', $trimmedToken);

    if (count($parts) !== 3)
    {
        return false;
    }

    [$version, $expiresAtRaw, $signature] = $parts;

    if ($version !== G2ML_AGEGATE_TOKEN_VERSION)
    {
        return false;
    }

    if ($expiresAtRaw === '' || !ctype_digit($expiresAtRaw))
    {
        return false;
    }

    $expectedSignature = g2ml_ageGateSign($expiresAtRaw);

    // 📖 Reference: https://www.php.net/manual/en/function.hash-equals.php
    if (!hash_equals($expectedSignature, $signature))
    {
        return false;
    }

    $expiresAt = (int) $expiresAtRaw;

    if ($expiresAt < time())
    {
        return false;
    }

    return true;
}

// ============================================================================
// 🍪 Cookie read / write
// ============================================================================

/**
 * Check whether the current request carries a VALID, unexpired, signed
 * age-gate confirmation cookie.
 *
 * @return bool
 */
function g2ml_ageGateHasValidCookie(): bool
{
    $cookieValue = null;

    if (isset($_COOKIE[G2ML_AGEGATE_COOKIE_NAME]) && is_string($_COOKIE[G2ML_AGEGATE_COOKIE_NAME]))
    {
        $cookieValue = $_COOKIE[G2ML_AGEGATE_COOKIE_NAME];
    }

    return g2ml_ageGateVerifyToken($cookieValue);
}

/**
 * Set the signed age-gate confirmation cookie after a visitor has confirmed
 * they are of legal age. NO date of birth (or any other personal data) is
 * ever written — only this single signed boolean-confirmation token.
 *
 * Secure + SameSite=Lax + HttpOnly, matching the pattern used elsewhere in
 * this codebase for non-JS-readable cookies (see web/_functions/session.php).
 * HttpOnly is not strictly REQUIRED for this cookie's security — the value
 * cannot be forged into a different meaning without the server's signing
 * key even if a script could read/rewrite it — but it is set anyway as
 * straightforward defence in depth, since this component ships no script
 * that would ever need to read it client-side.
 *
 * @return bool  True on success (mirrors setcookie()'s own return value).
 */
function g2ml_ageGateSetConfirmedCookie(): bool
{
    if (headers_sent())
    {
        error_log('[Go2My.Link] ERROR: g2ml_ageGateSetConfirmedCookie() called after headers were already sent.');
        return false;
    }

    $expiresAt = time() + G2ML_AGEGATE_COOKIE_LIFETIME_SECONDS;
    $token     = g2ml_ageGateBuildToken($expiresAt);

    // 📖 Reference: https://www.php.net/manual/en/function.setcookie.php
    return setcookie(G2ML_AGEGATE_COOKIE_NAME, $token, [
        'expires'  => $expiresAt,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
