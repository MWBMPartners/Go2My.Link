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
 * 🙈 Go2My.Link — Keep Secrets in Web Addresses Out of the Logs (#323)
 * ============================================================================
 *
 * Password-reset, email-verification and invitation links carry a secret token
 * in the address. Anyone who can read a log that holds the whole address can
 * use that token. Everything the application writes to a log (the error log
 * table, PHP's error log, the activity log and the API request log) goes
 * through the functions here first.
 *
 * The rule is "keep only what is known to be harmless":
 *   - a query-string value is kept only when its name is on the list in
 *     g2ml_logSafeParameterRules() AND the value has the expected shape;
 *   - every other value is replaced with "[redacted]", so a new secret
 *     parameter added later is covered without anyone remembering this file;
 *   - the path is kept, except where it can hold a secret (see
 *     _g2ml_redactLogPath());
 *   - anything after "#" and any "name:password@" before the host is replaced.
 *
 * These functions are pure. They need nothing else loaded.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.0.0 — #323
 *
 * 📖 References:
 *     - Address parts: https://developer.mozilla.org/en-US/docs/Learn_web_development/Howto/Web_mechanics/What_is_a_URL
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * The query-string parameters whose values may be logged, each with the shape
 * its value must have.
 *
 * Never add a parameter that can carry a secret, a free-text search, an email
 * address or another web address.
 *
 * @return array<string, string>  Lower-case parameter name => pattern the
 *                                decoded value must match in full
 */
function g2ml_logSafeParameterRules(): array
{
    $number   = '/^[0-9]{0,20}$/D';
    $flag     = '/^(?:true|false|0|1)?$/D';
    $date     = '/^(?:[0-9]{4}-[0-9]{2}-[0-9]{2})?$/D';
    $word     = '/^[A-Za-z0-9_.-]{0,30}$/D';
    // Campaign tracking values. The same keys are recorded on purpose for
    // click statistics (g2ml_extractTrackingParams()).
    $tracking = '/^[^?&=#;\/\\\\\x00-\x1F\x7F]{0,255}$/D';

    return [
        'page'         => $number,
        'limit'        => $number,
        'step'         => $number,
        'id'           => $number,
        'after'        => $number,
        'http_error'   => $number,
        'active'       => $flag,
        'debug'        => $flag,
        'forced'       => $flag,
        'registered'   => $flag,
        'reset'        => $flag,
        'verified'     => $flag,
        'from'         => $date,
        'to'           => $date,
        'lang'         => $word,
        'filter'       => $word,
        'range'        => $word,
        'bucket'       => $word,
        'dimension'    => $word,
        'type'         => $word,
        'job'          => $word,
        'src'          => $word,
        'export_error' => $word,
        'utm_source'   => $tracking,
        'utm_medium'   => $tracking,
        'utm_campaign' => $tracking,
        'utm_term'     => $tracking,
        'utm_content'  => $tracking,
        'gclid'        => $tracking,
        'fbclid'       => $tracking,
        'msclkid'      => $tracking,
    ];
}

/**
 * Say whether a piece of an address looks like a secret on its own: a long run
 * of hexadecimal characters (our reset, verification, invitation, session and
 * installer tokens are 64 of them) or one of our API keys.
 *
 * @param  string $text  Decoded text
 * @return bool
 */
function _g2ml_logLooksSecret(string $text): bool
{
    // preg_match() returns false when it cannot check. That counts as secret.
    return preg_match('/[A-Fa-f0-9]{32}|g2ml_[A-Za-z0-9_-]{16}/', $text) !== 0;
}

/**
 * Undo percent-encoding, including text that was encoded more than once.
 *
 * @param  string $text
 * @return string
 */
function _g2ml_logDecode(string $text): string
{
    for ($round = 0; $round < 3; $round++)
    {
        $decoded = urldecode($text);

        if ($decoded === $text)
        {
            break;
        }

        $text = $decoded;
    }

    return $text;
}

/**
 * Make one "name=value" part of a query string safe to log.
 *
 * @param  string $pair  One part, still percent-encoded
 * @return string
 */
function _g2ml_redactLogParameter(string $pair): string
{
    if ($pair === '')
    {
        return '';
    }

    $rules      = g2ml_logSafeParameterRules();
    $equalsAt   = strpos($pair, '=');

    if ($equalsAt === false)
    {
        // A part with no "=" could itself be the secret.
        if (isset($rules[strtolower($pair)]))
        {
            return $pair;
        }

        return '[redacted]';
    }

    $name        = substr($pair, 0, $equalsAt);
    $value       = substr($pair, $equalsAt + 1);
    $decodedName = _g2ml_logDecode($name);
    $lowerName   = strtolower($decodedName);

    if (isset($rules[$lowerName]))
    {
        $decodedValue = _g2ml_logDecode($value);

        if (preg_match($rules[$lowerName], $decodedValue) === 1 && !_g2ml_logLooksSecret($decodedValue))
        {
            return $name . '=' . $value;
        }

        return $name . '=[redacted]';
    }

    // Keep an unknown name (it helps with fault finding) only when it looks
    // like an ordinary parameter name.
    if (preg_match('/^[A-Za-z0-9_.\[\]-]{1,40}$/D', $decodedName) !== 1 || _g2ml_logLooksSecret($decodedName))
    {
        $name = '[redacted]';
    }

    if ($value === '')
    {
        return $name . '=';
    }

    return $name . '=[redacted]';
}

/**
 * Make the path part of an address safe to log.
 *
 * The path is kept, with three exceptions: everything after a "reset-password",
 * "verify-email", "invite" or "unsubscribe" part is replaced, because only a
 * token would follow it; a part that looks like a token or an API key is
 * replaced; and so is a part that hides a query string by percent-encoding it.
 *
 * @param  string $path
 * @return string
 */
function _g2ml_redactLogPath(string $path): string
{
    $secretFollows = ['reset-password', 'verify-email', 'invite', 'unsubscribe'];
    $redactRest    = false;
    $parts         = explode('/', $path);

    foreach ($parts as $index => $part)
    {
        if ($part === '')
        {
            continue;
        }

        $decoded = _g2ml_logDecode($part);

        if ($redactRest || _g2ml_logLooksSecret($decoded) || strpbrk($decoded, '?&=#') !== false)
        {
            $parts[$index] = '[redacted]';
        }

        if (in_array(strtolower($decoded), $secretFollows, true))
        {
            $redactRest = true;
        }
    }

    return implode('/', $parts);
}

/**
 * Make a web address safe to write to a log.
 *
 * Accepts a full address ("https://host/path?query") or a request path
 * ("/path?query"). Anything else is treated as a path.
 *
 * @param  string|null $url  The address
 * @return string|null       The address with secret values replaced, or null
 */
function g2ml_redactLogURL(?string $url): ?string
{
    if ($url === null || $url === '')
    {
        return $url;
    }

    $fragment = '';
    $hashAt   = strpos($url, '#');

    if ($hashAt !== false)
    {
        $fragment = '#';

        if ($hashAt < strlen($url) - 1)
        {
            $fragment = '#[redacted]';
        }

        $url = substr($url, 0, $hashAt);
    }

    $query      = null;
    $questionAt = strpos($url, '?');

    if ($questionAt !== false)
    {
        $query = substr($url, $questionAt + 1);
        $url   = substr($url, 0, $questionAt);
    }

    $start = '';
    $found = [];

    if (preg_match('~^((?:[A-Za-z][A-Za-z0-9+.-]*:)?//)([^/]*)~', $url, $found) === 1)
    {
        $host = $found[2];
        $atAt = strrpos($host, '@');

        if ($atAt !== false)
        {
            $host = '[redacted]@' . substr($host, $atAt + 1);
        }

        $start = $found[1] . $host;
        $url   = substr($url, strlen($found[0]));
    }

    $safe = $start . _g2ml_redactLogPath($url);

    if ($query !== null)
    {
        $safeParts = [];

        foreach (explode('&', $query) as $pair)
        {
            $safeParts[] = _g2ml_redactLogParameter($pair);
        }

        $safe .= '?' . implode('&', $safeParts);
    }

    return $safe . $fragment;
}

/**
 * Make free text (an error message or a backtrace) safe to write to a log, by
 * cleaning every web address and query-string value found inside it. Other
 * text, including file paths, is left as it is.
 *
 * If the text cannot be checked, none of it is kept.
 *
 * @param  string $text
 * @return string
 */
function g2ml_redactLogText(string $text): string
{
    $unchecked = '[text removed: it could not be checked for secrets]';

    // 1. Full web addresses.
    $result = preg_replace_callback(
        '~\bhttps?://[^\s"\'<>`|]+~i',
        function (array $match): string
        {
            return (string) g2ml_redactLogURL($match[0]);
        },
        $text
    );

    if ($result === null)
    {
        return $unchecked;
    }

    // 2. Query-string values in an address written without "https://".
    $result = preg_replace_callback(
        '~([?&#])([A-Za-z0-9_.%\[\]-]{1,80}=[^&#\s"\'<>`|]*)~',
        function (array $match): string
        {
            return $match[1] . _g2ml_redactLogParameter($match[2]);
        },
        $result
    );

    if ($result === null)
    {
        return $unchecked;
    }

    // 3. A secret named without any "?" before it, a token written as part of
    //    a path, and an API key wherever it appears. A path ending in a file
    //    name such as "invite/index.php" is a file, not a token.
    $result = preg_replace(
        [
            '~\b((?:access_token|refresh_token|install_token|token|api_?key|password|secret)=)(?!\[redacted\])[^&#\s"\'<>`|?][^&#\s"\'<>`|]*~i',
            '~(/(?:reset-password|verify-email|invite|unsubscribe)/)(?![A-Za-z0-9_-]+\.(?:php|js|css|html)\b)[^/?&#\s"\'<>`|]+~i',
            '~g2ml_[A-Za-z0-9_-]{8}_[A-Za-z0-9_-]{20,}~',
        ],
        [
            '$1[redacted]',
            '$1[redacted]',
            '[redacted]',
        ],
        $result
    );

    if ($result === null)
    {
        return $unchecked;
    }

    return $result;
}

/**
 * Clean every piece of text inside a list of log details, however deeply it
 * is nested. Names and numbers are left as they are.
 *
 * The 'trackingParams' entry is left alone: it holds the campaign values that
 * are recorded on purpose for click statistics, taken only from a fixed list
 * of names (g2ml_extractTrackingParams()).
 *
 * @param  array<mixed> $data
 * @return array<mixed>
 */
function g2ml_redactLogData(array $data): array
{
    foreach ($data as $key => $value)
    {
        if ($key === 'trackingParams')
        {
            continue;
        }

        if (is_string($value))
        {
            $data[$key] = g2ml_redactLogText($value);
        }
        elseif (is_array($value))
        {
            $data[$key] = g2ml_redactLogData($value);
        }
    }

    return $data;
}
