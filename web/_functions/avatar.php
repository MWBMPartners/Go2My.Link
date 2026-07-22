<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

declare(strict_types=1);

/**
 * ============================================================================
 * 🖼️ Go2My.Link — Avatar Priority Cascade (FG-005)
 * ============================================================================
 *
 * Resolves the best available avatar for a user through a deterministic
 * priority cascade and renders it as safe HTML. The cascade is, in order:
 *
 *   1. Local stored avatar  — a URL the user uploaded / set (avatarPath/avatar).
 *   2. Microsoft 365 (SSO)  — placeholder; wired in Phase 10 (no network here).
 *   3. Google (SSO)         — placeholder; wired in Phase 10 (no network here).
 *   4. Gravatar             — GATED behind the 'avatar.gravatar_enabled' setting,
 *                             OFF by default for privacy (this product is
 *                             privacy-first: DNT/GPC/consent honoured).
 *   5. Initials monogram    — deterministic coloured placeholder, always works.
 *
 * The resolver returns a STRUCTURED array (not a bare string) so callers can
 * distinguish an <img> from an initials monogram and render each accessibly.
 *
 * 🔒 Privacy note on the Gravatar tier: enabling it leaks a hash of the user's
 *    email to gravatar.com on every avatar render. It is therefore OFF by
 *    default. Turning it on ALSO requires:
 *      - relaxing the Content-Security-Policy img-src to allow www.gravatar.com;
 *      - a small CSP-safe external script that reads the img's
 *        data-avatar-fallback attribute and swaps a 404'd gravatar for the
 *        monogram (we do NOT use an inline onerror handler — CSP blocks it).
 *        NOTE: that fallback script is NOT shipped yet — until it is written,
 *        an enabled-but-missing gravatar simply shows the browser's broken-
 *        image glyph. That is acceptable degradation and privacy-safe, because
 *        the whole tier is OFF by default.
 *
 * This file performs NO database work at load time. getSetting() is consulted
 * only if it is already defined (function_exists guard), so the helpers are
 * safe to unit-test without a database connection.
 *
 * 🔒 Tier 1 (local stored avatar) URLs are user-settable data, so they are run
 *    through g2ml_sanitiseURL() (http/https scheme allowlist) before being
 *    trusted as an image source. A URL that fails sanitisation is treated as
 *    absent and the cascade falls through to the next tier — this mirrors
 *    the same allowlist already used for redirect destinations in
 *    expired.php / validating.php.
 *
 * Dependencies (all optional / soft): security.php (g2ml_sanitiseOutput(),
 *               g2ml_sanitiseURL()), settings.php (getSetting()).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.1.0
 * @since      autopilot COMPLETE (FG-005)
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
// 🎨 Accessible monogram palette
// ============================================================================
// Every colour below has been verified to yield a WCAG contrast ratio of at
// least 4.5:1 against WHITE (#ffffff) text, so the monogram always meets AA
// for normal-size text. Ratios (computed with the WCAG relative-luminance
// formula) are noted beside each entry.
//
//   #3b5bdb → 5.67:1    #364fc7 → 6.77:1    #1864ab → 6.09:1
//   #0b7285 → 5.59:1    #087f5b → 5.00:1    #c92a2a → 5.46:1
//   #a61e4d → 7.21:1    #862e9c → 7.28:1    #5f3dc4 → 7.12:1
//   #495057 → 8.18:1
// ============================================================================

/**
 * The fixed, accessibility-verified monogram background palette.
 *
 * @return array<int, string>  Hex colours, each ≥ 4.5:1 contrast vs white.
 */
function g2ml_avatarPalette(): array
{
    return [
        '#3b5bdb',
        '#364fc7',
        '#1864ab',
        '#0b7285',
        '#087f5b',
        '#c92a2a',
        '#a61e4d',
        '#862e9c',
        '#5f3dc4',
        '#495057',
    ];
}

// ============================================================================
// 🔤 Initials derivation
// ============================================================================

/**
 * Derive 1–2 UPPERCASE initials for a user, multibyte-safe.
 *
 * Priority for the source text:
 *   1. firstName / lastName  — first letter of each that is present.
 *   2. displayName           — first letters of the first two words, or the
 *                              first two letters of a single word.
 *   3. email local-part      — first letter of the part before '@'.
 *
 * Never returns more than 2 characters. Returns '' only when nothing usable
 * is present (the caller substitutes 'U' in that case).
 *
 * @param  array $user  User data (any of firstName, lastName, displayName, email).
 * @return string       1–2 uppercase characters, or '' if nothing usable.
 */
function g2ml_avatarInitials(array $user): string
{
    // --- Tier 1: first + last name -----------------------------------------
    $firstName = '';
    if (isset($user['firstName']) && is_string($user['firstName']))
    {
        $firstName = trim($user['firstName']);
    }

    $lastName = '';
    if (isset($user['lastName']) && is_string($user['lastName']))
    {
        $lastName = trim($user['lastName']);
    }

    $firstInitial = '';
    if ($firstName !== '')
    {
        $firstInitial = mb_substr($firstName, 0, 1, 'UTF-8');
    }

    $lastInitial = '';
    if ($lastName !== '')
    {
        $lastInitial = mb_substr($lastName, 0, 1, 'UTF-8');
    }

    if ($firstInitial !== '' || $lastInitial !== '')
    {
        return g2ml_avatarCapInitials($firstInitial . $lastInitial);
    }

    // --- Tier 2: display name ----------------------------------------------
    $displayName = '';
    if (isset($user['displayName']) && is_string($user['displayName']))
    {
        $displayName = trim($user['displayName']);
    }

    if ($displayName !== '')
    {
        $words = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false)
        {
            $words = [];
        }

        if (count($words) >= 2)
        {
            $twoInitials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
            return g2ml_avatarCapInitials($twoInitials);
        }

        if (count($words) === 1)
        {
            $twoLetters = mb_substr($words[0], 0, 2, 'UTF-8');
            return g2ml_avatarCapInitials($twoLetters);
        }
    }

    // --- Tier 3: email local-part ------------------------------------------
    $email = '';
    if (isset($user['email']) && is_string($user['email']))
    {
        $email = trim($user['email']);
    }

    if ($email !== '')
    {
        $atPosition = mb_strpos($email, '@', 0, 'UTF-8');
        if ($atPosition === false)
        {
            $localPart = $email;
        }
        else
        {
            $localPart = mb_substr($email, 0, $atPosition, 'UTF-8');
        }

        $localPart = trim($localPart);
        if ($localPart !== '')
        {
            return g2ml_avatarCapInitials(mb_substr($localPart, 0, 1, 'UTF-8'));
        }
    }

    // --- Tier 4: nothing usable --------------------------------------------
    return '';
}

/**
 * Uppercase a candidate initials string, then hard-cap it to two characters.
 *
 * Uppercasing MUST happen before the cap: some characters expand when upper-
 * cased (for example the German 'ß' becomes 'SS' and the ligature 'ﬃ' becomes
 * 'FFI'), so capping first would let that expansion overflow the two-character
 * monogram and spill out of the circular placeholder. This helper guarantees
 * the "never more than 2 characters" invariant of g2ml_avatarInitials() on
 * every return path.
 *
 * @param  string $value  Raw 1–2 character candidate (pre-uppercase).
 * @return string         At most 2 uppercase characters.
 */
function g2ml_avatarCapInitials(string $value): string
{
    return mb_substr(mb_strtoupper($value, 'UTF-8'), 0, 2, 'UTF-8');
}

// ============================================================================
// 🎯 Deterministic colour selection
// ============================================================================

/**
 * Pick a deterministic monogram background colour from the accessible palette.
 *
 * The same seed always maps to the same colour, so a given user keeps a stable
 * monogram colour across page loads. Selection uses the low bytes of an md5
 * digest of the seed, modulo the palette size.
 *
 * @param  string $seed  A stable per-user seed (e.g. lower-cased email).
 * @return string        A palette hex colour such as '#3b5bdb'.
 */
function g2ml_avatarColour(string $seed): string
{
    $palette = g2ml_avatarPalette();
    $count   = count($palette);

    if ($count === 0)
    {
        return '#495057';
    }

    // hexdec() of 8 hex chars stays well within a 64-bit PHP int (max 0xffffffff).
    $digest = md5($seed);
    $slice  = substr($digest, 0, 8);
    $index  = (int) (hexdec($slice) % $count);

    return $palette[$index];
}

/**
 * Build a stable seed for a user's monogram colour.
 *
 * Prefers the (lower-cased) email, then the numeric user UID, then the display
 * name, then the combined first/last name. Falls back to a constant so the
 * result is never empty.
 *
 * @param  array $user
 * @return string
 */
function g2ml_avatarSeed(array $user): string
{
    if (isset($user['email']) && is_string($user['email']) && trim($user['email']) !== '')
    {
        return strtolower(trim($user['email']));
    }

    if (isset($user['userUID']) && (is_int($user['userUID']) || is_string($user['userUID'])))
    {
        $uid = (string) $user['userUID'];
        if ($uid !== '' && $uid !== '0')
        {
            return 'uid:' . $uid;
        }
    }

    if (isset($user['displayName']) && is_string($user['displayName']) && trim($user['displayName']) !== '')
    {
        return strtolower(trim($user['displayName']));
    }

    $firstName = '';
    if (isset($user['firstName']) && is_string($user['firstName']))
    {
        $firstName = trim($user['firstName']);
    }

    $lastName = '';
    if (isset($user['lastName']) && is_string($user['lastName']))
    {
        $lastName = trim($user['lastName']);
    }

    $combined = trim($firstName . ' ' . $lastName);
    if ($combined !== '')
    {
        return strtolower($combined);
    }

    return 'anonymous';
}

// ============================================================================
// 🔗 Gravatar helpers (gated, privacy-first)
// ============================================================================

/**
 * Compute the Gravatar avatar hash for an email address.
 *
 * Gravatar hashes are md5 of the trimmed, lower-cased email address.
 *
 * @param  string $email
 * @return string  32-character lower-case hex md5 hash.
 */
function g2ml_avatarGravatarHash(string $email): string
{
    return md5(strtolower(trim($email)));
}

/**
 * Is the (privacy-sensitive) Gravatar tier enabled?
 *
 * Consults the 'avatar.gravatar_enabled' setting ONLY when getSetting() is
 * available. When settings are unavailable (e.g. in DB-free unit tests) the
 * tier is treated as disabled — the privacy-safe default.
 *
 * @return bool
 */
function g2ml_avatarGravatarEnabled(): bool
{
    if (!function_exists('getSetting'))
    {
        return false;
    }

    $enabled = getSetting('avatar.gravatar_enabled', false);

    if ($enabled === true || $enabled === 1 || $enabled === '1' || $enabled === 'true')
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🧭 The priority cascade
// ============================================================================

/**
 * Resolve the best available avatar for a user, as a structured descriptor.
 *
 * Return shapes:
 *   - Local image:   ['type'=>'image','url'=>..., 'source'=>'local']
 *   - Gravatar:      ['type'=>'image','url'=>..., 'source'=>'gravatar']
 *   - Monogram:      ['type'=>'initials','text'=>..., 'colour'=>..., 'source'=>'placeholder']
 *
 * @param  array $user  User data.
 * @param  int   $size  Requested pixel size (used for the Gravatar 's' param).
 * @return array        A structured avatar descriptor (see above).
 */
function g2ml_resolveAvatar(array $user, int $size = 48): array
{
    // --- Tier 1: local stored avatar ---------------------------------------
    $storedAvatar = '';
    if (isset($user['avatarPath']) && is_string($user['avatarPath']) && trim($user['avatarPath']) !== '')
    {
        $storedAvatar = trim($user['avatarPath']);
    }
    elseif (isset($user['avatar']) && is_string($user['avatar']) && trim($user['avatar']) !== '')
    {
        $storedAvatar = trim($user['avatar']);
    }

    if ($storedAvatar !== '')
    {
        // 🔒 Never trust a stored avatar URL as-is: it is user-settable data
        // (no writer exists yet, but a profile-photo/SSO tier will populate
        // it), so allowlist it to http/https via g2ml_sanitiseURL() before
        // treating it as a safe <img src>. A rejected URL is NOT emitted —
        // the cascade falls through to the next tier instead.
        $sanitisedAvatar = false;
        if (function_exists('g2ml_sanitiseURL'))
        {
            $sanitisedAvatar = g2ml_sanitiseURL($storedAvatar);
        }

        if ($sanitisedAvatar !== false && $sanitisedAvatar !== '')
        {
            return [
                'type'   => 'image',
                'url'    => $sanitisedAvatar,
                'source' => 'local',
            ];
        }
    }

    // --- Tier 2: Microsoft 365 (SSO) ---------------------------------------
    // Phase 10 (Advanced Authentication / SIGNula). No network call here; when
    // implemented this tier will read a cached MS Graph photo URL from the
    // user's linked-identity record. Intentionally left as a no-op gap.

    // --- Tier 3: Google (SSO) ----------------------------------------------
    // Phase 10 (Advanced Authentication / SIGNula). No network call here; when
    // implemented this tier will read a cached Google profile picture URL from
    // the user's linked-identity record. Intentionally left as a no-op gap.

    // --- Tier 4: Gravatar (gated, OFF by default) --------------------------
    $email = '';
    if (isset($user['email']) && is_string($user['email']))
    {
        $email = trim($user['email']);
    }

    if ($email !== '' && g2ml_avatarGravatarEnabled() === true)
    {
        $gravatarSize = $size;
        if ($gravatarSize < 1)
        {
            $gravatarSize = 48;
        }

        // d=404 → Gravatar returns a 404 (not a default image) when the email
        // has no Gravatar, so the front-end can fall back to the monogram.
        $url = 'https://www.gravatar.com/avatar/'
            . g2ml_avatarGravatarHash($email)
            . '?d=404&s=' . $gravatarSize;

        return [
            'type'   => 'image',
            'url'    => $url,
            'source' => 'gravatar',
        ];
    }

    // --- Tier 5: initials monogram (always available) ----------------------
    $initials = g2ml_avatarInitials($user);
    if ($initials === '')
    {
        $initials = 'U';
    }

    return [
        'type'   => 'initials',
        'text'   => $initials,
        'colour' => g2ml_avatarColour(g2ml_avatarSeed($user)),
        'source' => 'placeholder',
    ];
}

// ============================================================================
// 🧱 Rendering
// ============================================================================

/**
 * Escape a value for safe HTML output.
 *
 * Prefers the project's g2ml_sanitiseOutput() when loaded; otherwise falls
 * back to htmlspecialchars() so the renderer is self-contained (and testable
 * without the security module).
 *
 * @param  string $value
 * @return string
 */
function g2ml_avatarEscape(string $value): string
{
    if (function_exists('g2ml_sanitiseOutput'))
    {
        return g2ml_sanitiseOutput($value);
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Render a user's avatar as a safe HTML string.
 *
 * For an image avatar this is a decorative (alt="") <img>. A Gravatar image
 * additionally carries a data-avatar-fallback="INITIALS|#colour" payload so a
 * CSP-safe external script can swap a 404'd Gravatar for the monogram — we do
 * NOT use an inline onerror handler because the site's CSP blocks inline JS.
 * If no such script runs, a missing Gravatar simply shows the browser's
 * broken-image glyph; acceptable because the tier is OFF by default.
 *
 * For an initials avatar this is a coloured, circular <span> monogram. The
 * background colour is dynamic, so an inline style attribute is used (the
 * codebase already relies on inline styles such as object-fit:cover).
 *
 * The visible display name elsewhere in the UI remains the accessible label,
 * so the avatar itself is decorative (alt="" / aria-hidden="true").
 *
 * @param  array  $user          User data.
 * @param  int    $sizePx        Rendered width/height in pixels.
 * @param  string $extraClasses  Extra CSS classes (e.g. 'me-1').
 * @return string                Safe HTML.
 */
function g2ml_renderAvatar(array $user, int $sizePx = 24, string $extraClasses = ''): string
{
    if ($sizePx < 1)
    {
        $sizePx = 24;
    }

    $resolved = g2ml_resolveAvatar($user, $sizePx);
    $extra    = trim($extraClasses);

    if ($resolved['type'] === 'image')
    {
        $classAttr = 'rounded-circle';
        if ($extra !== '')
        {
            $classAttr = $classAttr . ' ' . $extra;
        }

        $html = '<img src="' . g2ml_avatarEscape($resolved['url']) . '"'
            . ' alt=""'
            . ' class="' . g2ml_avatarEscape($classAttr) . '"'
            . ' width="' . $sizePx . '"'
            . ' height="' . $sizePx . '"'
            . ' style="object-fit:cover;"'
            . ' loading="lazy"';

        if ($resolved['source'] === 'gravatar')
        {
            $fallbackInitials = g2ml_avatarInitials($user);
            if ($fallbackInitials === '')
            {
                $fallbackInitials = 'U';
            }

            $payload = $fallbackInitials . '|' . g2ml_avatarColour(g2ml_avatarSeed($user));
            $html    = $html . ' data-avatar-fallback="' . g2ml_avatarEscape($payload) . '"';
        }

        $html = $html . '>';

        return $html;
    }

    // type === 'initials'
    $spanClass = 'rounded-circle d-inline-flex align-items-center justify-content-center';
    if ($extra !== '')
    {
        $spanClass = $spanClass . ' ' . $extra;
    }

    $fontSize = (int) round($sizePx * 0.45);
    if ($fontSize < 8)
    {
        $fontSize = 8;
    }

    $style = 'width:' . $sizePx . 'px;'
        . 'height:' . $sizePx . 'px;'
        . 'background:' . g2ml_avatarEscape($resolved['colour']) . ';'
        . 'color:#fff;'
        . 'font-size:' . $fontSize . 'px;'
        . 'font-weight:600;'
        . 'line-height:1;'
        . 'text-transform:uppercase;';

    $html = '<span class="' . g2ml_avatarEscape($spanClass) . '"'
        . ' style="' . $style . '"'
        . ' aria-hidden="true">'
        . g2ml_avatarEscape($resolved['text'])
        . '</span>';

    return $html;
}
