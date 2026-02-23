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
 * 🌍 Go2My.Link — Internationalisation (i18n) Functions
 * ============================================================================
 *
 * Translation system using dot-notation keys stored in tblTranslations.
 * Provides __() for simple translations and _n() for pluralisation.
 *
 * Translations are cached per-locale in memory after first load.
 * Falls back to the default locale (en-GB) if a translation is missing.
 *
 * Dependencies: db_query.php (dbSelect()), settings.php (getSetting())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - tblTranslations schema: web/_sql/schema/035_translations.sql
 *     - tblLanguages schema: web/_sql/schema/035_translations.sql
 *     - BCP 47 locale codes: https://www.rfc-editor.org/info/bcp47
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
// 💾 Translation Cache & State
// ============================================================================

/** @var string Current active locale code (BCP 47, e.g., 'en-GB', 'es', 'ar') */
$GLOBALS['_g2ml_locale'] = 'en-GB';

/** @var string Default/fallback locale */
$GLOBALS['_g2ml_default_locale'] = 'en-GB';

/** @var string Text direction for current locale ('ltr' or 'rtl') */
$GLOBALS['_g2ml_text_direction'] = 'ltr';

/** @var array Translation cache: ['en-GB' => ['key' => 'value', ...], ...] */
$GLOBALS['_g2ml_translations'] = [];

/** @var array Loaded locale flags: ['en-GB' => true, ...] */
$GLOBALS['_g2ml_translations_loaded'] = [];

/** @var array Language metadata cache: ['en-GB' => ['name' => ..., 'direction' => ...], ...] */
$GLOBALS['_g2ml_languages'] = [];

// ============================================================================
// 🌐 Locale Detection & Management
// ============================================================================

/**
 * Detect and set the user's preferred locale.
 *
 * Priority order:
 *   1. URL query parameter (?lang=es)
 *   2. Session value
 *   3. Cookie value
 *   4. Accept-Language header
 *   5. Default locale from settings
 *
 * @return string  The resolved locale code
 *
 * 📖 Reference: https://www.php.net/manual/en/function.locale-accept-from-http.php
 */
function detectLocale(): string
{
    // Load available languages if not cached
    if (empty($GLOBALS['_g2ml_languages']))
    {
        _g2ml_loadLanguages();
    }

    $locale = null;

    // 1. Check URL parameter
    // Supports both full locale codes (?lang=en-GB) and base language codes (?lang=en)
    if (isset($_GET['lang']) && is_string($_GET['lang']))
    {
        $requested = g2ml_sanitiseInput($_GET['lang']);
        $resolved  = _g2ml_resolveLocale($requested);

        if ($resolved !== null)
        {
            $locale = $resolved;
        }
    }

    // 2. Check session
    if ($locale === null && isset($_SESSION['locale']) && is_string($_SESSION['locale']))
    {
        $resolved = _g2ml_resolveLocale($_SESSION['locale']);

        if ($resolved !== null)
        {
            $locale = $resolved;
        }
    }

    // 3. Check cookie
    if ($locale === null && isset($_COOKIE['g2ml_locale']) && is_string($_COOKIE['g2ml_locale']))
    {
        $resolved = _g2ml_resolveLocale($_COOKIE['g2ml_locale']);

        if ($resolved !== null)
        {
            $locale = $resolved;
        }
    }

    // 4. Check Accept-Language header
    // 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Language
    if ($locale === null && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
    {
        $locale = _g2ml_parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    // 5. Fall back to default
    if ($locale === null)
    {
        if (function_exists('getSetting')) {
            $locale = getSetting('site.default_locale', 'en-GB');
        } else {
            $locale = 'en-GB';
        }
    }

    g2ml_setLocale($locale);

    return $locale;
}

/**
 * Set the active locale.
 *
 * Updates the global locale, session, and cookie. Also sets the text direction.
 *
 * @param  string $locale  BCP 47 locale code (e.g., 'en-GB', 'es', 'ar')
 * @return void
 */
function g2ml_setLocale(string $locale): void
{
    if (empty($GLOBALS['_g2ml_languages']))
    {
        _g2ml_loadLanguages();
    }

    // Resolve the locale using language-family fallback
    // e.g., 'en' → 'en-GB', 'pt' → 'pt-BR', 'en-US' (inactive) → 'en-GB'
    $resolved = _g2ml_resolveLocale($locale);
    $locale   = $resolved ?? $GLOBALS['_g2ml_default_locale'];

    $GLOBALS['_g2ml_locale'] = $locale;

    // Set text direction from language metadata
    if (isset($GLOBALS['_g2ml_languages'][$locale]))
    {
        $GLOBALS['_g2ml_text_direction'] = $GLOBALS['_g2ml_languages'][$locale]['direction'];
    }

    // Persist to session
    if (session_status() === PHP_SESSION_ACTIVE)
    {
        $_SESSION['locale'] = $locale;
    }

    // Persist to cookie (30 days)
    // 📖 Reference: https://www.php.net/manual/en/function.setcookie.php
    if (!headers_sent())
    {
        setcookie('g2ml_locale', $locale, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'secure'   => true,
            'httponly'  => false, // Readable by JS for language switcher
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Get the current active locale code.
 *
 * @return string  BCP 47 locale code
 */
function getLocale(): string
{
    return $GLOBALS['_g2ml_locale'];
}

/**
 * Get the current text direction.
 *
 * @return string  'ltr' or 'rtl'
 */
function getTextDirection(): string
{
    return $GLOBALS['_g2ml_text_direction'];
}

/**
 * Get all active languages (for language switcher UI).
 *
 * @return array  Array of language metadata indexed by locale code
 */
function getActiveLanguages(): array
{
    if (empty($GLOBALS['_g2ml_languages']))
    {
        _g2ml_loadLanguages();
    }

    return $GLOBALS['_g2ml_languages'];
}

// ============================================================================
// 📖 Translation Functions
// ============================================================================

/**
 * Translate a key to the current locale.
 *
 * Supports {placeholder} syntax: __('greeting', ['name' => 'Lance']) will
 * replace {name} with "Lance" in the translated string.
 *
 * Falls back to the default locale if the key is not found in the current
 * locale. Returns the key itself if no translation exists in any locale.
 *
 * @param  string $key          Dot-notation translation key (e.g., 'nav.login', 'error.404.message')
 * @param  array  $replacements Key-value pairs for {placeholder} replacement
 * @param  string|null $locale  Override locale (null = use current)
 * @return string               Translated string (or the key if not found)
 *
 * Usage example:
 *   echo __('nav.login');               // "Log In"
 *   echo __('welcome', ['name' => $user]); // "Welcome, Lance!"
 */
function __(string $key, array $replacements = [], ?string $locale = null): string
{
    $locale = $locale ?? $GLOBALS['_g2ml_locale'];

    // Ensure translations are loaded for this locale
    _g2ml_loadTranslations($locale);

    // Look up the key in the current locale
    $value = $GLOBALS['_g2ml_translations'][$locale][$key] ?? null;

    // Fall back to a sibling locale in the same language family
    // e.g., if 'en-US' has no translation for this key, try 'en-GB'
    // This ensures regional variants share translations within a language family
    // before falling all the way back to the site default locale
    if ($value === null)
    {
        $baseLanguage = strtolower(explode('-', $locale)[0]);

        foreach (array_keys($GLOBALS['_g2ml_languages'] ?? []) as $sibling)
        {
            // Skip the locale we already checked
            if ($sibling === $locale)
            {
                continue;
            }

            $siblingBase = strtolower(explode('-', $sibling)[0]);

            if ($siblingBase === $baseLanguage)
            {
                _g2ml_loadTranslations($sibling);
                $value = $GLOBALS['_g2ml_translations'][$sibling][$key] ?? null;

                if ($value !== null)
                {
                    break;
                }
            }
        }
    }

    // Fall back to default locale if still not found
    if ($value === null && $locale !== $GLOBALS['_g2ml_default_locale'])
    {
        _g2ml_loadTranslations($GLOBALS['_g2ml_default_locale']);
        $value = $GLOBALS['_g2ml_translations'][$GLOBALS['_g2ml_default_locale']][$key] ?? null;
    }

    // Return the key itself as a last resort (makes missing translations visible)
    if ($value === null)
    {
        $value = $key;
    }

    // Apply placeholder replacements
    if (!empty($replacements))
    {
        foreach ($replacements as $placeholder => $replacement)
        {
            $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
        }
    }

    return $value;
}

/**
 * Translate with pluralisation support.
 *
 * Expects translations stored with a pipe separator for singular|plural forms:
 *   "1 item|{count} items"
 *
 * @param  string $key          Translation key
 * @param  int    $count        The count to determine singular/plural
 * @param  array  $replacements Additional placeholder replacements
 * @param  string|null $locale  Override locale
 * @return string               Translated string with correct plural form
 *
 * Usage example:
 *   echo _n('link.count', 1);  // "1 link"
 *   echo _n('link.count', 5);  // "5 links"
 */
function _n(string $key, int $count, array $replacements = [], ?string $locale = null): string
{
    // Add count to replacements automatically
    $replacements['count'] = $count;

    $translated = __($key, $replacements, $locale);

    // Check for pipe-separated singular|plural forms
    if (strpos($translated, '|') !== false)
    {
        $forms = explode('|', $translated);

        if ($count === 1 && isset($forms[0]))
        {
            $translated = trim($forms[0]);
        }
        elseif (isset($forms[1]))
        {
            $translated = trim($forms[1]);
        }

        // Re-apply replacements after selecting the form
        foreach ($replacements as $placeholder => $replacement)
        {
            $translated = str_replace('{' . $placeholder . '}', (string) $replacement, $translated);
        }
    }

    return $translated;
}

/**
 * Translate and HTML-encode the result (safe for output).
 *
 * @param  string $key          Translation key
 * @param  array  $replacements Placeholder replacements
 * @return string               HTML-safe translated string
 */
function _e(string $key, array $replacements = []): string
{
    return htmlspecialchars(__($key, $replacements), ENT_QUOTES, 'UTF-8');
}

// ============================================================================
// 🔧 Internal Helpers
// ============================================================================

/**
 * Load all translations for a locale into the cache.
 *
 * @param  string $locale  The locale code to load
 * @return void
 */
function _g2ml_loadTranslations(string $locale): void
{
    if (isset($GLOBALS['_g2ml_translations_loaded'][$locale]))
    {
        return; // Already loaded
    }

    $rows = dbSelect(
        "SELECT translationKey, translationValue
         FROM tblTranslations
         WHERE localeCode = ?",
        's',
        [$locale]
    );

    if ($rows === false)
    {
        $GLOBALS['_g2ml_translations'][$locale] = [];
        $GLOBALS['_g2ml_translations_loaded'][$locale] = true;
        return;
    }

    $translations = [];

    foreach ($rows as $row)
    {
        $translations[$row['translationKey']] = $row['translationValue'];
    }

    $GLOBALS['_g2ml_translations'][$locale] = $translations;
    $GLOBALS['_g2ml_translations_loaded'][$locale] = true;
}

/**
 * Load active languages from tblLanguages into cache.
 *
 * @return void
 */
function _g2ml_loadLanguages(): void
{
    $rows = dbSelect(
        "SELECT localeCode, languageName, nativeName, direction, isDefault
         FROM tblLanguages
         WHERE isActive = 1
         ORDER BY sortOrder ASC",
        '',
        []
    );

    if ($rows === false)
    {
        // Minimal fallback if DB is unavailable
        $GLOBALS['_g2ml_languages'] = [
            'en-GB' => [
                'name'       => 'English (UK)',
                'nativeName' => 'English',
                'direction'  => 'ltr',
                'isDefault'  => true,
            ],
        ];
        return;
    }

    $languages = [];

    foreach ($rows as $row)
    {
        $languages[$row['localeCode']] = [
            'name'       => $row['languageName'],
            'nativeName' => $row['nativeName'],
            'direction'  => $row['direction'],
            'isDefault'  => (bool) $row['isDefault'],
        ];

        if ((bool) $row['isDefault'])
        {
            $GLOBALS['_g2ml_default_locale'] = $row['localeCode'];
        }
    }

    $GLOBALS['_g2ml_languages'] = $languages;
}

/**
 * Check if a locale code is valid (exists and is active).
 *
 * @param  string $locale  The locale code to check
 * @return bool            True if the locale exists and is active
 */
function _g2ml_isValidLocale(string $locale): bool
{
    return isset($GLOBALS['_g2ml_languages'][$locale]);
}

/**
 * Resolve a requested locale to the best available active locale.
 *
 * Uses a three-step resolution strategy:
 *   1. Exact match — 'en-GB' matches 'en-GB' directly
 *   2. Base language → regional — 'en' resolves to the first active 'en-*' variant (e.g., 'en-GB')
 *   3. Regional → sibling — 'en-US' (inactive) resolves to the first active 'en-*' variant (e.g., 'en-GB')
 *
 * This allows users to request a base language code (e.g., ?lang=en, ?lang=pt)
 * and receive the best available regional variant, and ensures that inactive
 * regional variants gracefully fall back within their language family before
 * falling back to the site default.
 *
 * @param  string $requested  The requested locale code (e.g., 'en', 'en-US', 'pt', 'zh-CN')
 * @return string|null        The best matching active locale code, or null if no match
 *
 * 📖 Reference: https://www.rfc-editor.org/info/bcp47
 */
function _g2ml_resolveLocale(string $requested): ?string
{
    // 1. Exact match — requested locale is active
    if (_g2ml_isValidLocale($requested))
    {
        return $requested;
    }

    // Extract the base language subtag (e.g., 'en' from 'en-GB', 'zh' from 'zh-CN')
    // 📖 Reference: https://www.php.net/manual/en/function.explode.php
    $baseLanguage = strtolower(explode('-', $requested)[0]);

    // 2 & 3. Find the first active locale in the same language family
    // Prefers the default locale if it shares the same base language, otherwise
    // takes the first match by sort order (as loaded from tblLanguages.sortOrder)
    $familyMatch = null;

    foreach (array_keys($GLOBALS['_g2ml_languages']) as $available)
    {
        $availableBase = strtolower(explode('-', $available)[0]);

        if ($availableBase === $baseLanguage)
        {
            // If this family member is the site default, prefer it immediately
            if ($available === $GLOBALS['_g2ml_default_locale'])
            {
                return $available;
            }

            // Otherwise, keep the first family match (by sort order)
            if ($familyMatch === null)
            {
                $familyMatch = $available;
            }
        }
    }

    return $familyMatch;
}

/**
 * Parse the Accept-Language header and find the best matching active locale.
 *
 * Uses _g2ml_resolveLocale() for each candidate to support base language
 * and language-family fallback resolution.
 *
 * @param  string $header  The Accept-Language header value
 * @return string|null     Best matching locale code, or null if no match
 *
 * 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Language
 */
function _g2ml_parseAcceptLanguage(string $header): ?string
{
    // Parse into locale => quality pairs
    // 📖 Reference: https://www.php.net/manual/en/function.preg-match-all.php
    $locales = [];

    if (preg_match_all('/([a-zA-Z]{1,8}(?:-[a-zA-Z]{1,8})*)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?/', $header, $matches))
    {
        for ($i = 0; $i < count($matches[1]); $i++)
        {
            $locale  = $matches[1][$i];
            if (isset($matches[2][$i]) && $matches[2][$i] !== '') {
                $quality = (float) $matches[2][$i];
            } else {
                $quality = 1.0;
            }
            $locales[$locale] = $quality;
        }
    }

    // Sort by quality (highest first)
    arsort($locales);

    // Find the first matching active locale using the resolution strategy
    foreach ($locales as $requested => $quality)
    {
        $resolved = _g2ml_resolveLocale($requested);

        if ($resolved !== null)
        {
            return $resolved;
        }
    }

    return null;
}
