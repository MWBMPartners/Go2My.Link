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
 * 💬 Go2My.Link — Words for g2my.link's own pages (Component B, #290)
 * ============================================================================
 *
 * The explanation pages (404.php, expired.php, validating.php, 403.php and
 * 500.php) get every visible word through __(), so they can be translated.
 *
 * Calling __() directly is not enough here. __() answers with the key name
 * itself when it finds no text, which happens when the database is down (the
 * very moment the "temporarily unavailable" page is needed) or when a seed
 * file has not been applied yet. g2ml_shortlinkText() then uses the English
 * written beside the call instead, so a visitor never sees a key name.
 *
 * @package    Go2My.Link
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.0.0 (#290)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * A translated text, or the given English when there is no translation.
 *
 * @param  string               $key           Translation key.
 * @param  string               $english       English used when the key has no text.
 * @param  array<string,string> $replacements  {name} placeholder values.
 * @return string  Plain text. The caller escapes it for HTML.
 */
function g2ml_shortlinkText(string $key, string $english, array $replacements = []): string
{
    if (function_exists('__'))
    {
        $translated = __($key, $replacements);

        if ($translated !== '' && $translated !== $key)
        {
            return $translated;
        }
    }

    foreach ($replacements as $name => $value)
    {
        $english = str_replace('{' . $name . '}', (string) $value, $english);
    }

    return $english;
}

/**
 * The language of the page, for <html lang="...">.
 *
 * @return string
 */
function g2ml_shortlinkLocale(): string
{
    if (function_exists('getLocale'))
    {
        return getLocale();
    }

    return 'en-GB';
}

/**
 * The reading direction of the page, for <html dir="...">.
 *
 * @return string  'ltr' or 'rtl'
 */
function g2ml_shortlinkDirection(): string
{
    if (function_exists('getTextDirection'))
    {
        return getTextDirection();
    }

    return 'ltr';
}
