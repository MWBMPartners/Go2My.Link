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
 * 🌍 Go2My.Link — Web Addresses With Non-English Letters
 * ============================================================================
 *
 * Turns a web address that contains non-English letters (or spaces) into plain
 * ASCII text that browsers and servers understand, so that
 * `https://bücher.de/` is saved as `https://xn--bcher-kva.de/` instead of
 * having its letters silently deleted (which pointed at a different website).
 *
 * How it works, in plain words:
 *
 *   - The host name (the part after `https://`) is converted with the standard
 *     "punycode" method (RFC 3492): `bücher` becomes `xn--bcher-kva`.
 *   - Everything after the host (path, query, fragment) is percent-encoded the
 *     way browsers do it: every non-ASCII byte becomes `%` plus two hex digits,
 *     and a space becomes `%20`.
 *   - Only a short list of safe letters is allowed in a host name: accented
 *     Latin (including Vietnamese), Greek, Cyrillic, and Japanese, Chinese and
 *     Korean. Anything else is refused. A refusal is always safer than a
 *     wrong guess, because a wrong guess sends visitors to a different website.
 *
 * It is written in plain PHP on purpose. It does not need the `intl`
 * extension (not every host has it, and its answers depend on the server's
 * library version), so it gives the same answer on every PHP version.
 *
 * g2ml_sanitiseURL() (security.php) calls g2ml_urlToAscii() before any other
 * check, so the own-domain and server-request-forgery checks always see the
 * real ASCII host name.
 *
 * Dependencies: PHP's pcre and mbstring (mb_ord is used only to read UTF-8).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.1.0
 * @since      PHP 8.6 readiness (#308, #309)
 *
 * 📖 References:
 *     - Punycode: https://www.rfc-editor.org/rfc/rfc3492
 *     - Browser URL rules: https://url.spec.whatwg.org/
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name was the script being
// run (#198). realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

// ============================================================================
// 🔤 Punycode (RFC 3492)
// ============================================================================

/**
 * Turn a punycode digit number (0 to 35) into its character (a-z, 0-9).
 *
 * @param  int $digit  A number from 0 to 35
 * @return string      One character
 */
function g2ml_punycodeDigit(int $digit): string
{
    if ($digit < 26)
    {
        return chr(0x61 + $digit);
    }

    return chr(0x30 + $digit - 26);
}

/**
 * The punycode "bias adaptation" step. Both the encoder and the decoder use
 * it, and they must use it identically for the two to match.
 *
 * @param  int  $delta       The latest delta
 * @param  int  $pointCount  Number of code points handled so far, counting this one
 * @param  bool $firstTime   True for the first delta only
 * @return int               The new bias
 */
function g2ml_punycodeAdaptBias(int $delta, int $pointCount, bool $firstTime): int
{
    if ($firstTime)
    {
        $delta = intdiv($delta, 700);
    }
    else
    {
        $delta = intdiv($delta, 2);
    }

    $delta += intdiv($delta, $pointCount);

    $step = 0;
    while ($delta > 455)
    {
        $delta = intdiv($delta, 35);
        $step += 36;
    }

    return $step + intdiv(36 * $delta, $delta + 38);
}

/**
 * The smallest "threshold" for a position in a punycode number.
 *
 * @param  int $position  The running position (36, 72, 108, ...)
 * @param  int $bias      The current bias
 * @return int            A threshold from 1 to 26
 */
function g2ml_punycodeThreshold(int $position, int $bias): int
{
    if ($position <= $bias)
    {
        return 1;
    }

    if ($position >= $bias + 26)
    {
        return 26;
    }

    return $position - $bias;
}

/**
 * Encode a list of Unicode code points as punycode (without the `xn--`).
 *
 * Example: the code points of `bücher` give `bcher-kva`.
 *
 * @param  array<int, int> $codePoints  Unicode code points, in order
 * @return string                       The punycode text
 *
 * 📖 Reference: https://www.rfc-editor.org/rfc/rfc3492#section-6.3
 */
function g2ml_punycodeEncode(array $codePoints): string
{
    $codePoints = array_values($codePoints);
    $nextPoint  = 128;
    $delta      = 0;
    $bias       = 72;
    $output     = '';

    // The plain ASCII characters go first, as they are.
    foreach ($codePoints as $codePoint)
    {
        if ($codePoint < 128)
        {
            $output .= chr($codePoint);
        }
    }

    $basicCount   = strlen($output);
    $handledCount = $basicCount;

    if ($basicCount > 0)
    {
        $output .= '-';
    }

    $totalCount = count($codePoints);

    while ($handledCount < $totalCount)
    {
        // Find the smallest code point not handled yet.
        $smallest = PHP_INT_MAX;
        foreach ($codePoints as $codePoint)
        {
            if ($codePoint >= $nextPoint && $codePoint < $smallest)
            {
                $smallest = $codePoint;
            }
        }

        $delta += ($smallest - $nextPoint) * ($handledCount + 1);
        $nextPoint = $smallest;

        foreach ($codePoints as $codePoint)
        {
            if ($codePoint < $nextPoint)
            {
                $delta++;
            }

            if ($codePoint === $nextPoint)
            {
                $quotient = $delta;

                for ($position = 36; ; $position += 36)
                {
                    $threshold = g2ml_punycodeThreshold($position, $bias);

                    if ($quotient < $threshold)
                    {
                        break;
                    }

                    $output .= g2ml_punycodeDigit($threshold + (($quotient - $threshold) % (36 - $threshold)));
                    $quotient = intdiv($quotient - $threshold, 36 - $threshold);
                }

                $output .= g2ml_punycodeDigit($quotient);
                $bias = g2ml_punycodeAdaptBias($delta, $handledCount + 1, $handledCount === $basicCount);
                $delta = 0;
                $handledCount++;
            }
        }

        $delta++;
        $nextPoint++;
    }

    return $output;
}

/**
 * Decode punycode text (without the `xn--`) back into Unicode code points.
 *
 * Returns false for anything that is not valid punycode: a character that is
 * not a letter or digit, a number that runs past the end, a result outside
 * the Unicode range, or a surrogate.
 *
 * @param  string $encoded  Punycode text, for example `bcher-kva`
 * @return array<int, int>|false  Code points, or false if invalid
 *
 * 📖 Reference: https://www.rfc-editor.org/rfc/rfc3492#section-6.2
 */
function g2ml_punycodeDecode(string $encoded): array|false
{
    // A label is at most 63 bytes and starts with `xn--`, so the text after
    // that prefix is at most 59. Refusing longer text up front keeps the slow
    // part below from being used against us.
    if (strlen($encoded) > 59)
    {
        return false;
    }

    $nextPoint = 128;
    $index     = 0;
    $bias      = 72;
    $output    = [];

    // Everything before the last hyphen is plain ASCII text.
    $lastHyphen = strrpos($encoded, '-');
    $position   = 0;

    if ($lastHyphen === 0)
    {
        // A delimiter with no basic code points before it is not valid
        // (RFC 3492 section 6.2). The encoder never writes one.
        return false;
    }

    if ($lastHyphen !== false)
    {
        for ($offset = 0; $offset < $lastHyphen; $offset++)
        {
            $byte = ord($encoded[$offset]);

            if ($byte >= 0x80)
            {
                return false;
            }

            $output[] = $byte;
        }

        $position = $lastHyphen + 1;
    }

    $length = strlen($encoded);

    // No genuine result can need an index larger than this.
    $indexLimit = 0x110000 * ($length + 1);

    while ($position < $length)
    {
        $oldIndex = $index;
        $weight   = 1;

        for ($step = 36; ; $step += 36)
        {
            if ($position >= $length)
            {
                return false;
            }

            $byte = ord($encoded[$position]);
            $position++;

            if ($byte >= 0x61 && $byte <= 0x7A)
            {
                $digit = $byte - 0x61;
            }
            elseif ($byte >= 0x41 && $byte <= 0x5A)
            {
                $digit = $byte - 0x41;
            }
            elseif ($byte >= 0x30 && $byte <= 0x39)
            {
                $digit = $byte - 0x30 + 26;
            }
            else
            {
                return false;
            }

            $index += $digit * $weight;

            if ($index > $indexLimit)
            {
                return false;
            }

            $threshold = g2ml_punycodeThreshold($step, $bias);

            if ($digit < $threshold)
            {
                break;
            }

            $weight *= (36 - $threshold);

            if ($weight > $indexLimit)
            {
                return false;
            }
        }

        $pointCount = count($output) + 1;
        $bias       = g2ml_punycodeAdaptBias($index - $oldIndex, $pointCount, $oldIndex === 0);
        $nextPoint += intdiv($index, $pointCount);
        $index      = $index % $pointCount;

        if ($nextPoint > 0x10FFFF || ($nextPoint >= 0xD800 && $nextPoint <= 0xDFFF))
        {
            return false;
        }

        array_splice($output, $index, 0, [$nextPoint]);
        $index++;
    }

    return $output;
}

/**
 * Check one host label. A label that does not start with `xn--` is fine as it
 * is. A label that does must hold real punycode that decodes to at least one
 * non-ASCII letter and encodes back to exactly the same text. That rules out
 * made-up labels such as `xn--`, `xn--xn--` and `xn--123`. Control codes,
 * noncharacters and the byte-order mark are refused too. It does NOT check
 * which alphabets the decoded letters belong to: a caller that needs that
 * must also apply g2ml_urlAsciiLetterGroup() and g2ml_urlAsciiLabelAlphabetsOk().
 *
 * @param  string $label  One label of a host name (no dots)
 * @return bool           True if the label is acceptable
 */
function g2ml_punycodeLabelIsValid(string $label): bool
{
    $lowered = strtolower($label);

    if (strncmp($lowered, 'xn--', 4) !== 0)
    {
        return true;
    }

    $payload = substr($lowered, 4);

    if ($payload === '' || strlen($payload) > 59)
    {
        return false;
    }

    $codePoints = g2ml_punycodeDecode($payload);

    if ($codePoints === false)
    {
        return false;
    }

    $hasNonAscii = false;
    foreach ($codePoints as $codePoint)
    {
        // U+0080 to U+009F are control codes, never part of a real name.
        if ($codePoint >= 0x80 && $codePoint < 0xA0)
        {
            return false;
        }

        // Noncharacters (U+FDD0 to U+FDEF and U+xxFFFE or U+xxFFFF) and the
        // byte-order mark are never part of a real name either.
        if (($codePoint >= 0xFDD0 && $codePoint <= 0xFDEF) || ($codePoint & 0xFFFE) === 0xFFFE || $codePoint === 0xFEFF)
        {
            return false;
        }

        if ($codePoint >= 0xA0)
        {
            $hasNonAscii = true;
        }
    }

    if (!$hasNonAscii)
    {
        return false;
    }

    return g2ml_punycodeEncode($codePoints) === $payload;
}

// ============================================================================
// 🏷️ Host Name Letters
// ============================================================================

/**
 * Turn a capital letter into its lower-case form, using a fixed list of ranges
 * written out here. It does not use mb_strtolower(), because that depends on
 * tables inside PHP that can change between versions. A code point not in the
 * list is returned unchanged (and is then usually refused as "not allowed").
 *
 * The list covers: A-Z, the capital letters of Latin-1 and Latin Extended-A,
 * the Greek capitals, the Cyrillic capitals (including the extra Cyrillic
 * letters) and the Vietnamese capitals. Each rule was checked against a
 * browser-style parser (Uri\WhatWg\Url on PHP 8.5 and 8.6), which lower-cases
 * every one of these capitals to the same small letter.
 *
 * @param  int $codePoint  A Unicode code point
 * @return int             Its lower-case code point (or the same one)
 */
function g2ml_urlAsciiLowerCodePoint(int $codePoint): int
{
    if ($codePoint >= 0x41 && $codePoint <= 0x5A)
    {
        return $codePoint + 0x20;
    }

    if ($codePoint >= 0xC0 && $codePoint <= 0xDE && $codePoint !== 0xD7)
    {
        return $codePoint + 0x20;
    }

    // Latin Extended-A: these blocks are pairs, capital then small.
    if ($codePoint >= 0x100 && $codePoint <= 0x137 && $codePoint % 2 === 0 && $codePoint !== 0x130 && $codePoint !== 0x132)
    {
        return $codePoint + 1;
    }

    if ($codePoint >= 0x139 && $codePoint <= 0x148 && $codePoint % 2 === 1 && $codePoint !== 0x13F)
    {
        return $codePoint + 1;
    }

    if ($codePoint >= 0x14A && $codePoint <= 0x177 && $codePoint % 2 === 0)
    {
        return $codePoint + 1;
    }

    if ($codePoint === 0x178)
    {
        return 0xFF;
    }

    if ($codePoint >= 0x179 && $codePoint <= 0x17E && $codePoint % 2 === 1)
    {
        return $codePoint + 1;
    }

    // Greek.
    if ($codePoint === 0x386)
    {
        return 0x3AC;
    }

    if ($codePoint >= 0x388 && $codePoint <= 0x38A)
    {
        return $codePoint + 0x25;
    }

    if ($codePoint === 0x38C)
    {
        return 0x3CC;
    }

    if ($codePoint === 0x38E || $codePoint === 0x38F)
    {
        return $codePoint + 0x3F;
    }

    if ($codePoint >= 0x391 && $codePoint <= 0x3AB && $codePoint !== 0x3A2)
    {
        return $codePoint + 0x20;
    }

    // Cyrillic.
    if ($codePoint >= 0x400 && $codePoint <= 0x40F)
    {
        return $codePoint + 0x50;
    }

    if ($codePoint >= 0x410 && $codePoint <= 0x42F)
    {
        return $codePoint + 0x20;
    }

    // Extra Cyrillic letters (Ukrainian, Kazakh and others), which come in
    // pairs, capital then small. Two runs of even numbers are capitals, the
    // palochka U+04C0 is a one-off, a run of odd numbers is capitals, and the
    // last run is even again. U+0460 to U+0489 are old letters, symbols and
    // marks that no modern language needs, so they are left alone.
    if (($codePoint >= 0x48A && $codePoint <= 0x4BE) || ($codePoint >= 0x4D0 && $codePoint <= 0x52E))
    {
        if ($codePoint % 2 === 0)
        {
            return $codePoint + 1;
        }

        return $codePoint;
    }

    if ($codePoint === 0x4C0)
    {
        return 0x4CF;
    }

    if ($codePoint >= 0x4C1 && $codePoint <= 0x4CD && $codePoint % 2 === 1)
    {
        return $codePoint + 1;
    }

    // Vietnamese letters (Latin Extended Additional): capital then small.
    if ($codePoint >= 0x1EA0 && $codePoint <= 0x1EF8 && $codePoint % 2 === 0)
    {
        return $codePoint + 1;
    }

    return $codePoint;
}

/**
 * Say which alphabet an allowed (already lower-case) code point belongs to.
 *
 * Returns one of: 'latin', 'greek', 'cyrillic', 'cjk' (Japanese, Chinese and
 * Korean), 'neutral' (digits and the hyphen), or '' when the code point is
 * not allowed in a host name at all.
 *
 * Where the extra letters come from: the extra Cyrillic letters (U+048B to
 * U+04BF, U+04C2 to U+04CF, U+04D1 to U+052F), the Vietnamese
 * letters (U+1EA1 to U+1EF9, small ones only), the Japanese iteration mark
 * U+3005 and the ideographic zero U+3007 were each tested against a
 * browser-style parser (Uri\WhatWg\Url on PHP 8.5 and 8.6). It keeps each one
 * unchanged, so each is allowed.
 * The old Cyrillic letters, symbols and marks (U+0460 to U+048A) are left out.
 *
 * The Latin list still leaves out five letters: U+0133, U+0140, U+0149 and
 * U+017F, because browsers change them into other letters, and the Greenlandic
 * "ĸ" (U+0138), which looks like "k" and which no real name needs. One letter
 * that browsers keep is allowed on purpose: the Turkish dotless "ı" (U+0131).
 * The owner decided on 2026-11-23 to allow it so that Turkish names such as
 * `ışık.com.tr` work, knowing that it can be mistaken for "i" or "l". Mixed
 * alphabets are still refused, so it cannot be slipped into an otherwise
 * Cyrillic or Greek name.
 *
 * @param  int $codePoint  A lower-case Unicode code point
 * @return string          The alphabet name, or '' if not allowed
 */
function g2ml_urlAsciiLetterGroup(int $codePoint): string
{
    if ($codePoint >= 0x61 && $codePoint <= 0x7A)
    {
        return 'latin';
    }

    if (($codePoint >= 0x30 && $codePoint <= 0x39) || $codePoint === 0x2D)
    {
        return 'neutral';
    }

    if (($codePoint >= 0xDF && $codePoint <= 0xF6) || ($codePoint >= 0xF8 && $codePoint <= 0xFF))
    {
        return 'latin';
    }

    // Latin Extended-A, small letters only.
    if ($codePoint >= 0x101 && $codePoint <= 0x137 && $codePoint % 2 === 1)
    {
        if ($codePoint === 0x133)
        {
            return '';
        }

        return 'latin';
    }

    // Dotless i: allowed by owner decision (see above).
    if ($codePoint === 0x131)
    {
        return 'latin';
    }

    if ($codePoint >= 0x13A && $codePoint <= 0x148 && $codePoint % 2 === 0)
    {
        if ($codePoint === 0x140)
        {
            return '';
        }

        return 'latin';
    }

    if ($codePoint >= 0x14B && $codePoint <= 0x177 && $codePoint % 2 === 1)
    {
        return 'latin';
    }

    if ($codePoint >= 0x17A && $codePoint <= 0x17E && $codePoint % 2 === 0)
    {
        return 'latin';
    }

    if ($codePoint >= 0x3AC && $codePoint <= 0x3CE)
    {
        return 'greek';
    }

    if ($codePoint >= 0x430 && $codePoint <= 0x45F)
    {
        return 'cyrillic';
    }

    // Extra Cyrillic letters. In the first and last runs the small letters are
    // the odd numbers, in the middle run they are the even numbers (the
    // palochka U+04CF is a small letter too).
    if (($codePoint >= 0x48B && $codePoint <= 0x4BF && $codePoint % 2 === 1)
        || ($codePoint >= 0x4C2 && $codePoint <= 0x4CE && $codePoint % 2 === 0)
        || $codePoint === 0x4CF
        || ($codePoint >= 0x4D1 && $codePoint <= 0x52F && $codePoint % 2 === 1))
    {
        return 'cyrillic';
    }

    // Vietnamese letters, small ones only.
    if ($codePoint >= 0x1EA1 && $codePoint <= 0x1EF9 && $codePoint % 2 === 1)
    {
        return 'latin';
    }

    // Hiragana, Katakana (and the long-vowel mark), the ideograph repeat mark
    // (U+3005, as in 佐々木), the ideographic zero (U+3007), CJK ideographs,
    // Hangul. Browsers keep U+3005 and U+3007.
    if ($codePoint === 0x3005
        || $codePoint === 0x3007
        || ($codePoint >= 0x3041 && $codePoint <= 0x3096)
        || ($codePoint >= 0x30A1 && $codePoint <= 0x30FA)
        || $codePoint === 0x30FC
        || ($codePoint >= 0x4E00 && $codePoint <= 0x9FFF)
        || ($codePoint >= 0xAC00 && $codePoint <= 0xD7A3))
    {
        return 'cjk';
    }

    return '';
}

/**
 * Say whether a code point is a Japanese or Chinese character that looks like
 * an ASCII dash, slash or ring. Browsers warn about these when they sit next to
 * Latin letters or digits, because `google一com` (with the ideograph for "one")
 * reads like `google-com` or `google.com`. The list is short and written out
 * on purpose:
 *
 *   - U+4E00 (the ideograph for "one"), U+30FC (the katakana long-vowel mark)
 *     and U+3007 (the ideographic zero): they look like a dash or hyphen, and
 *     a letter "o" or digit "0".
 *   - U+30CE (katakana "no"), U+4E36 (a dot stroke) and U+4E28 (a vertical
 *     stroke): they look like a slash, a dot and a bar.
 *   - U+30A8 (katakana "e"), U+30ED (katakana "ro"), U+30CB (katakana "ni")
 *     and U+5DE5 (the ideograph for "work"): browsers warn about these too,
 *     because in many fonts they look like Latin letters or an equals sign.
 *
 * Other katakana and ideographs, such as カ ト ハ タ, can look a little like
 * Latin letters too, but they are very common in real words, so refusing them
 * would block ordinary names. The middle dot U+30FB is not allowed in a host
 * name at all.
 *
 * @param  int $codePoint  A lower-case Unicode code point
 * @return bool            True if it is one of these look-alikes
 */
function g2ml_urlAsciiIsAsciiLookalike(int $codePoint): bool
{
    return in_array($codePoint, [0x4E00, 0x30CE, 0x30FC, 0x3007, 0x4E36, 0x4E28, 0x30A8, 0x30ED, 0x30CB, 0x5DE5], true);
}

/**
 * Check that one label uses a single alphabet. The only mix allowed is
 * Latin with Japanese/Chinese/Korean. Digits and hyphens fit with anything.
 * This blocks look-alike tricks such as a Cyrillic "р" and "а" followed by
 * the Latin letters "ypal". It also refuses the ASCII look-alikes listed in
 * g2ml_urlAsciiIsAsciiLookalike() when the label has a to z or 0 to 9.
 *
 * @param  array<int, int> $label  The lower-case code points of one label
 * @return bool                    True if the alphabets may be combined
 */
function g2ml_urlAsciiLabelAlphabetsOk(array $label): bool
{
    $seen           = [];
    $hasAsciiLetter = false;
    $hasLookalike   = false;

    foreach ($label as $codePoint)
    {
        $group = g2ml_urlAsciiLetterGroup($codePoint);

        if (($codePoint >= 0x61 && $codePoint <= 0x7A) || ($codePoint >= 0x30 && $codePoint <= 0x39))
        {
            $hasAsciiLetter = true;
        }

        if (g2ml_urlAsciiIsAsciiLookalike($codePoint))
        {
            $hasLookalike = true;
        }

        if ($group === '')
        {
            return false;
        }

        if ($group !== 'neutral')
        {
            $seen[$group] = true;
        }
    }

    // A look-alike of an ASCII dash, slash or ring is refused when the same
    // label also holds a to z or 0 to 9. In an all-Japanese label it is fine.
    if ($hasLookalike && $hasAsciiLetter)
    {
        return false;
    }

    if (count($seen) <= 1)
    {
        return true;
    }

    if (count($seen) === 2 && isset($seen['latin']) && isset($seen['cjk']))
    {
        return true;
    }

    return false;
}

/**
 * Convert a host name that may hold non-English letters into plain ASCII.
 *
 * Capital letters are lower-cased, full-width letters (ｅｘａｍｐｌｅ) become
 * plain ones, the dot variants U+3002, U+FF0E and U+FF61 become a dot, and
 * every label with a non-ASCII letter becomes `xn--` plus punycode. Returns
 * false if the host uses a letter or a mix of alphabets that is not allowed,
 * has an empty label (except one trailing dot), a hyphen at the start or end
 * of a label, a label over 63 bytes, or more than 253 bytes in total.
 *
 * @param  string $host  A host name in UTF-8, without port
 * @return string|false  The ASCII host, or false if refused
 */
function g2ml_hostToAscii(string $host): string|false
{
    // A host can never be longer than 253 bytes once converted, and every
    // character becomes at least one byte, so a long host can be refused
    // before any slow work is done.
    if (strlen($host) > 1012 || preg_match('//u', $host) !== 1)
    {
        return false;
    }

    $characters = preg_split('//u', $host, -1, PREG_SPLIT_NO_EMPTY);

    if ($characters === false || count($characters) > 253)
    {
        return false;
    }

    // Split into labels, mapping each character on the way.
    $labels  = [];
    $current = [];

    foreach ($characters as $character)
    {
        // The text was checked as valid UTF-8 above, so this always succeeds.
        $codePoint = (int) mb_ord($character, 'UTF-8');

        if ($codePoint === 0x2E || $codePoint === 0x3002 || $codePoint === 0xFF0E || $codePoint === 0xFF61)
        {
            $labels[] = $current;
            $current  = [];
            continue;
        }

        // Full-width ASCII (U+FF01 to U+FF5E) sits 0xFEE0 above plain ASCII.
        if ($codePoint >= 0xFF01 && $codePoint <= 0xFF5E)
        {
            $codePoint -= 0xFEE0;
        }

        $codePoint = g2ml_urlAsciiLowerCodePoint($codePoint);

        if (g2ml_urlAsciiLetterGroup($codePoint) === '')
        {
            return false;
        }

        $current[] = $codePoint;
    }

    $labels[] = $current;

    $labelCount  = count($labels);
    $outputLabels = [];

    foreach ($labels as $labelIndex => $label)
    {
        if (count($label) === 0)
        {
            // One trailing dot (`example.com.`) is fine. Any other empty label is not.
            if ($labelIndex === $labelCount - 1 && $labelCount > 1)
            {
                $outputLabels[] = '';
                continue;
            }

            return false;
        }

        if (!g2ml_urlAsciiLabelAlphabetsOk($label))
        {
            return false;
        }

        if ($label[0] === 0x2D || $label[count($label) - 1] === 0x2D)
        {
            return false;
        }

        $isAscii = true;
        foreach ($label as $codePoint)
        {
            if ($codePoint >= 0x80)
            {
                $isAscii = false;
            }
        }

        if ($isAscii)
        {
            $text = '';
            foreach ($label as $codePoint)
            {
                $text .= chr($codePoint);
            }

            $outputLabels[] = $text;
            continue;
        }

        // A hyphen in both the third and fourth place is reserved for `xn--`.
        if (count($label) >= 4 && $label[2] === 0x2D && $label[3] === 0x2D)
        {
            return false;
        }

        $encoded = 'xn--' . g2ml_punycodeEncode($label);

        if (strlen($encoded) > 63)
        {
            return false;
        }

        $outputLabels[] = $encoded;
    }

    $result = implode('.', $outputLabels);

    if (strlen($result) > 253)
    {
        return false;
    }

    return $result;
}

// ============================================================================
// 🔗 Whole Web Addresses
// ============================================================================

/**
 * Take whitespace and invisible characters off both ends of valid UTF-8 text.
 *
 * The characters removed are the ones trim() removes (space, tab, newline,
 * carriage return, NUL, vertical tab) plus the byte-order mark U+FEFF, the
 * zero-width space U+200B, the no-break space U+00A0 and the ideographic space
 * U+3000, mixed in any order. It walks in from each end once and cuts once, so
 * it takes time in proportion to the text length, however the characters
 * alternate.
 *
 * @param  string $text  Valid UTF-8 text
 * @return string        The text without those characters at either end
 */
function g2ml_urlAsciiStripEnds(string $text): string
{
    $length = strlen($text);
    $start  = 0;
    $end    = $length;

    // The four multi-byte characters, as UTF-8 bytes.
    $invisible = ["\xEF\xBB\xBF", "\xE2\x80\x8B", "\xC2\xA0", "\xE3\x80\x80"];
    $plain     = " \t\n\r\0\x0B";

    while ($start < $end)
    {
        if (strpos($plain, $text[$start]) !== false)
        {
            $start++;
            continue;
        }

        $matched = false;
        foreach ($invisible as $bytes)
        {
            $size = strlen($bytes);

            if ($end - $start >= $size && substr_compare($text, $bytes, $start, $size) === 0)
            {
                $start += $size;
                $matched = true;
                break;
            }
        }

        if (!$matched)
        {
            break;
        }
    }

    while ($end > $start)
    {
        if (strpos($plain, $text[$end - 1]) !== false)
        {
            $end--;
            continue;
        }

        $matched = false;
        foreach ($invisible as $bytes)
        {
            $size = strlen($bytes);

            if ($end - $start >= $size && substr_compare($text, $bytes, $end - $size, $size) === 0)
            {
                $end -= $size;
                $matched = true;
                break;
            }
        }

        if (!$matched)
        {
            break;
        }
    }

    return substr($text, $start, $end - $start);
}

/**
 * Turn a run of non-ASCII bytes into %XX codes (upper-case hex). Called by
 * preg_replace_callback() once for each run, not once for each byte.
 *
 * @param  array<int, string> $match  The regular-expression match
 * @return string                     For example %C3%BC
 */
function g2ml_urlAsciiPercentEncodeRun(array $match): string
{
    return '%' . rtrim(chunk_split(strtoupper(bin2hex($match[0])), 2, '%'), '%');
}

/**
 * Turn a web address with non-English letters or spaces into plain ASCII.
 *
 * Steps:
 *   1. Refuse text that is not valid UTF-8 (today such bytes are deleted).
 *   2. Remove invisible characters that pasted text often carries at either
 *      end (byte-order mark, zero-width space, no-break space, ideographic
 *      space), together with ordinary surrounding whitespace.
 *   3. Split into `scheme://`, the host part, and the rest. Text without
 *      `scheme://` is refused.
 *   4. A space in the host part is refused. If the host part has a non-ASCII
 *      character, it is refused when it holds user-info (`@`) or an IPv6
 *      address (`[`); otherwise the host is converted with g2ml_hostToAscii()
 *      and a `:digits` port is kept.
 *   5. In the rest, every byte of 128 or above becomes `%XX` (upper-case hex)
 *      and every space becomes `%20`.
 *
 * A label already written as `xn--...` is not checked here for alphabets or
 * look-alikes; the later check on punycode labels (F1) does that.
 *
 * Text that has no non-ASCII byte and no space after step 2 is returned as it
 * is, so the plain ASCII path of the caller stays exactly as it was.
 *
 * @param  string $url  The web address typed or pasted by a person
 * @return string|false The ASCII web address, or false if refused
 */
function g2ml_urlToAscii(string $url): string|false
{
    if (preg_match('//u', $url) !== 1)
    {
        return false;
    }

    // Take invisible characters and whitespace off both ends, in any order.
    $url = g2ml_urlAsciiStripEnds($url);

    if (preg_match('/[\x80-\xFF ]/', $url) !== 1)
    {
        return $url;
    }

    if (preg_match('~^([A-Za-z][A-Za-z0-9+.\-]*://)([^/?#\\\\]*)(.*)$~s', $url, $parts) !== 1)
    {
        return false;
    }

    $prefix    = $parts[1];
    $authority = $parts[2];
    $rest      = $parts[3];

    if (strpos($authority, ' ') !== false)
    {
        return false;
    }

    if (preg_match('/[\x80-\xFF]/', $authority) === 1)
    {
        if (strpos($authority, '@') !== false || strpos($authority, '[') !== false)
        {
            return false;
        }

        $port = '';
        if (preg_match('/^(.*)(:[0-9]*)$/s', $authority, $portParts) === 1)
        {
            $authority = $portParts[1];
            $port      = $portParts[2];
        }

        $asciiHost = g2ml_hostToAscii($authority);

        if ($asciiHost === false)
        {
            return false;
        }

        $authority = $asciiHost . $port;
    }

    $rest = preg_replace_callback('/[\x80-\xFF]+/', 'g2ml_urlAsciiPercentEncodeRun', $rest);

    if ($rest === null)
    {
        return false;
    }

    $rest = str_replace(' ', '%20', $rest);

    return $prefix . $authority . $rest;
}
