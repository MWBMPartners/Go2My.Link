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
 * 🧪 Tests — web addresses with non-English letters and spaces (#309)
 * ============================================================================
 *
 * The link cleaner used to delete every non-English letter, so
 * https://bücher.de/ was saved as https://bcher.de/, a different website.
 * url_ascii.php now converts such addresses to plain ASCII (or refuses them),
 * and g2ml_sanitiseURL() uses it. These tests check:
 *
 *   1. The full input-to-output table, one row per case.
 *   2. The punycode encoder and decoder against the published RFC 3492
 *      examples, and a round trip over generated text.
 *   3. That plain ASCII input (with no space) comes out exactly as the OLD
 *      cleaner gave it. The old cleaner is copied into this file, frozen, so
 *      the proof does not depend on the new code.
 *   4. That cleaning a cleaned address changes nothing, and that no result
 *      holds a non-ASCII byte.
 *   5. That the lower-case rule agrees with mb_strtolower() for every letter
 *      it accepts, and the list of real domain names (including the extra
 *      Vietnamese, Cyrillic, iteration-mark and dotless-i letters, and the
 *      refused Japanese look-alikes).
 *   6. Where PHP has a browser-style parser (Uri\WhatWg\Url, PHP 8.5 and
 *      later), that every host we convert matches the parser's own answer.
 *      On PHP 8.4 this check says SKIPPED.
 *   7. That a missing url_ascii.php makes non-English input refused, never
 *      silently cut.
 *
 * Every check gives the same answer on PHP 8.4, 8.5 and 8.6. The table check
 * prints a short fingerprint of all its outputs so the three versions can be
 * compared by eye.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      PHP 8.6 readiness (#308, #309)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * The cleaner as it was BEFORE #309, copied word for word and frozen here.
 * Used only to prove that plain ASCII input is unchanged.
 */
function g2mlUrlAsciiTest_oldSanitiseURL(?string $url): string|false
{
    if ($url === null || $url === '')
    {
        return false;
    }

    $sanitised = filter_var(trim($url), FILTER_SANITIZE_URL);

    if (filter_var($sanitised, FILTER_VALIDATE_URL) === false)
    {
        return false;
    }

    $scheme = parse_url($sanitised, PHP_URL_SCHEME);

    if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
    {
        return false;
    }

    return $sanitised;
}

/**
 * Turn a list of code points into a UTF-8 string.
 *
 * @param array<int, int> $codePoints
 */
function g2mlUrlAsciiTest_utf8(array $codePoints): string
{
    $text = '';

    foreach ($codePoints as $codePoint)
    {
        $character = mb_chr($codePoint, 'UTF-8');

        if ($character !== false)
        {
            $text .= $character;
        }
    }

    return $text;
}

/**
 * The input-to-output table: [number, input, expected result or false].
 * Rows 1 to 39 are the cases written down when the problem was found. Rows 40
 * onward cover spaces (the owner decided that a space in a path, query or
 * fragment becomes %20, and a space in the host is refused).
 *
 * @return array<int, array{0: int, 1: string, 2: string|false}>
 */
function g2mlUrlAsciiTest_table(): array
{
    $manyAccents = 'https://example.com/' . str_repeat('é', 3000);
    $manyEncoded = 'https://example.com/' . str_repeat('%C3%A9', 3000);

    return [
        [1, 'https://bücher.de/', 'https://xn--bcher-kva.de/'],
        [2, 'https://BÜCHER.DE/Straße', 'https://xn--bcher-kva.de/Stra%C3%9Fe'],
        [3, 'https://Example.COM/x', 'https://Example.COM/x'],
        [4, 'https://example.com./x', 'https://example.com./x'],
        [5, 'https://bücher.de./', 'https://xn--bcher-kva.de./'],
        [6, 'https://example.com/straße', 'https://example.com/stra%C3%9Fe'],
        [7, 'https://example.com/?q=café&x=ü', 'https://example.com/?q=caf%C3%A9&x=%C3%BC'],
        [8, 'https://example.com/#ü', 'https://example.com/#%C3%BC'],
        [9, 'https://example.com/😀', 'https://example.com/%F0%9F%98%80'],
        [10, 'https://i❤.ws/', false],
        [11, 'https://مثال.إختبار/', false],
        [12, 'https://example.com/שלום', 'https://example.com/%D7%A9%D7%9C%D7%95%D7%9D'],
        [13, 'https://example.com/%C3%BC', 'https://example.com/%C3%BC'],
        [14, 'https://example.com/?q=%', 'https://example.com/?q=%'],
        [15, 'https://example.com/%', 'https://example.com/%'],
        [16, 'https://example.com/a b', 'https://example.com/a%20b'],
        [17, "https://example.com/a\tb\nc", 'https://example.com/abc'],
        [18, "https://exa\0mple.com/", 'https://example.com/'],
        [19, "https://example.com/caf\xC3", false],
        [20, "https://example.com/\xFC", false],
        [21, 'https://user:pw@bücher.de/', false],
        [22, 'https://usér@example.com/', false],
        [23, 'https://[2001:db8::1]/ü', 'https://[2001:db8::1]/%C3%BC'],
        [24, 'https://xn--bcher-kva.de/', 'https://xn--bcher-kva.de/'],
        [25, 'https://раypal.com/', false],
        [26, 'https://bücher．de/', 'https://xn--bcher-kva.de/'],
        [27, 'https://ｅｘａｍｐｌｅ．ｃｏｍ/', 'https://example.com/'],
        [28, 'https://пример.рф/путь?к=з', 'https://xn--e1afmkfd.xn--p1ai/%D0%BF%D1%83%D1%82%D1%8C?%D0%BA=%D0%B7'],
        [29, 'https://例え.テスト/パス', 'https://xn--r8jz45g.xn--zckzah/%E3%83%91%E3%82%B9'],
        [30, "\u{FEFF}https://bücher.de/", 'https://xn--bcher-kva.de/'],
        [31, 'https://bücher.de:8080/x', 'https://xn--bcher-kva.de:8080/x'],
        [32, 'https://bücher.de:0x50/', false],
        [33, 'https://g２my．link/x', 'https://g2my.link/x'],
        [34, $manyAccents, $manyEncoded],
        [35, 'https://xn--bcher-kva.bücher.de/', 'https://xn--bcher-kva.xn--bcher-kva.de/'],
        [36, 'https://-bücher.de/', false],
        [37, 'https://İstanbul.tr/', false],
        [38, 'https://bücher.de\\@evil.com/', false],
        [39, 'https://ΕΛΛΗΝΙΚΆ.ελ/', 'https://xn--hxargifdar.xn--qxam/'],
        // Spaces (owner decision D4).
        [40, 'https://example.com/a b?x=1 2#f g', 'https://example.com/a%20b?x=1%202#f%20g'],
        [41, '  https://example.com/a  ', 'https://example.com/a'],
        [42, 'https://exa mple.com/', false],
        [43, 'https://example.com /x', false],
        [44, 'https://example.com/a  b', 'https://example.com/a%20%20b'],
        [45, 'https://example.com/a%20b c', 'https://example.com/a%20b%20c'],
        [46, "\u{00A0}https://example.com/\u{3000}", 'https://example.com/'],
        [47, 'https://bücher.de/a b', 'https://xn--bcher-kva.de/a%20b'],
        [48, "https://example.com/a \tb", 'https://example.com/a%20b'],
        [49, 'https://bücher .de/', false],
        [50, "https://\u{200B}example.com/", false],
    ];
}

/**
 * Run a PHP script in a child process (used for the missing-file check).
 *
 * @return array{output: string, exitCode: int}
 */
function g2mlUrlAsciiTest_runChild(string $scriptBody): array
{
    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_ua_');

    if ($tempFile === false)
    {
        return ['output' => '', 'exitCode' => -1];
    }

    try
    {
        file_put_contents($tempFile, '<?php' . "\n" . $scriptBody);

        $lines    = [];
        $exitCode = -1;

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1', $lines, $exitCode);

        return ['output' => implode("\n", $lines), 'exitCode' => $exitCode];
    }
    finally
    {
        unlink($tempFile);
    }
}

// ----------------------------------------------------------------------------
// 1. The input-to-output table
// ----------------------------------------------------------------------------

test('url_ascii: every row of the input-to-output table gives the expected result', function (): void
{
    $fingerprint = '';

    foreach (g2mlUrlAsciiTest_table() as $row)
    {
        $number   = $row[0];
        $input    = $row[1];
        $expected = $row[2];
        $actual   = g2ml_sanitiseURL($input);

        assert_same($expected, $actual, 'Table row ' . $number);

        $fingerprint .= $number . '=' . var_export($actual, true) . "\n";
    }

    echo 'url_ascii table fingerprint (same on every PHP version): ' . sha1($fingerprint) . PHP_EOL;
});

test('url_ascii: hosts with a bad hyphen, an empty label or a leading dot are refused', function (): void
{
    assert_same(false, g2ml_sanitiseURL('https://-bücher.de/'), 'A leading hyphen is refused');
    assert_same(false, g2ml_sanitiseURL('https://bücher-.de/'), 'A trailing hyphen is refused');
    assert_same(false, g2ml_sanitiseURL('https://bü--cher.de/'), 'Hyphens in places 3 and 4 are refused after conversion');
    assert_same(false, g2ml_sanitiseURL('https://bücher..de/'), 'An empty label is refused');
    assert_same(false, g2ml_sanitiseURL('https://.bücher.de/'), 'A leading dot is refused');
});

test('url_ascii: look-alike, right-to-left and combining-mark hosts are refused', function (): void
{
    assert_same(false, g2ml_hostToAscii('раypal.com'), 'Cyrillic mixed with Latin is refused');
    assert_same(false, g2ml_hostToAscii('αbc.com'), 'Greek mixed with Latin is refused');
    assert_same(false, g2ml_hostToAscii('שלום.co.il'), 'Hebrew is refused');
    assert_same(false, g2ml_hostToAscii('مثال.com'), 'Arabic is refused');
    assert_same(false, g2ml_hostToAscii('ไทย.th'), 'Thai (combining marks) is refused');
    assert_same(false, g2ml_hostToAscii('example😀.com'), 'Emoji is refused');
    assert_same(false, g2ml_hostToAscii('bu' . "\u{0308}" . 'cher.de'), 'A combining mark is refused');
    assert_same(false, g2ml_hostToAscii("b\xFCcher.de"), 'Broken UTF-8 is refused');
    assert_same(false, g2ml_hostToAscii(''), 'An empty host is refused');
    assert_same('xn--bcher-kva.de', g2ml_hostToAscii('BÜCHER.de'), 'Accented Latin is converted and lower-cased');
    assert_same('xn--e1afmkfd.xn--p1ai', g2ml_hostToAscii('ПРИМЕР.РФ'), 'Cyrillic is converted');
    assert_same('xn--hxargifdar.xn--qxam', g2ml_hostToAscii('ΕΛΛΗΝΙΚΆ.ΕΛ'), 'Greek is converted');
    assert_same('xn--r8jz45g.xn--zckzah', g2ml_hostToAscii('例え.テスト'), 'Japanese is converted');
    assert_same('xn--3e0b707e', g2ml_hostToAscii('한국'), 'Korean is converted');
});

test('url_ascii: a host that is too long is refused', function (): void
{
    $longLabel = str_repeat('ü', 60);
    assert_same(false, g2ml_hostToAscii($longLabel . '.de'), 'A converted label over 63 bytes is refused');
    assert_same(false, g2ml_hostToAscii(str_repeat('ü', 5000) . '.de'), 'A huge host is refused quickly');
});

// ----------------------------------------------------------------------------
// 2. Punycode
// ----------------------------------------------------------------------------

test('url_ascii: the punycode encoder matches the published examples', function (): void
{
    $cases = [
        'bcher-kva'                  => [0x62, 0xFC, 0x63, 0x68, 0x65, 0x72],
        'e1afmkfd'                   => [0x43F, 0x440, 0x438, 0x43C, 0x435, 0x440],
        'p1ai'                       => [0x440, 0x444],
        // RFC 3492 section 7.1: Arabic (Egyptian).
        'egbpdaj6bu4bxfgehfvwxn'     => [0x644, 0x64A, 0x647, 0x645, 0x627, 0x628, 0x62A, 0x643, 0x644, 0x645, 0x648, 0x634, 0x639, 0x631, 0x628, 0x64A, 0x61F],
        // RFC 3492 section 7.1: Chinese (simplified).
        'ihqwcrb4cv8a8dqg056pqjye'   => [0x4ED6, 0x4EEC, 0x4E3A, 0x4EC0, 0x4E48, 0x4E0D, 0x8BF4, 0x4E2D, 0x6587],
        // RFC 3492 section 7.1: "3<nen>B<gumi><kinpachi><sensei>".
        '3B-ww4c5e180e575a65lsy2b'   => [0x33, 0x5E74, 0x42, 0x7D44, 0x91D1, 0x516B, 0x5148, 0x751F],
        // RFC 3492 section 7.1: plain ASCII with symbols.
        '-> $1.00 <--'               => [0x2D, 0x3E, 0x20, 0x24, 0x31, 0x2E, 0x30, 0x30, 0x20, 0x3C, 0x2D],
    ];

    foreach ($cases as $expected => $codePoints)
    {
        assert_same((string) $expected, g2ml_punycodeEncode($codePoints), 'Encode ' . $expected);
        assert_same($codePoints, g2ml_punycodeDecode((string) $expected), 'Decode ' . $expected);
    }
});

test('url_ascii: punycode decode then encode gives the same text for 2000 generated labels', function (): void
{
    mt_srand(309);

    for ($count = 0; $count < 2000; $count++)
    {
        $length     = mt_rand(1, 12);
        $codePoints = [];

        for ($index = 0; $index < $length; $index++)
        {
            $kind = mt_rand(0, 4);

            if ($kind === 0)
            {
                $codePoints[] = mt_rand(0x61, 0x7A);
            }
            elseif ($kind === 1)
            {
                $codePoints[] = mt_rand(0xE0, 0x17F);
            }
            elseif ($kind === 2)
            {
                $codePoints[] = mt_rand(0x430, 0x44F);
            }
            elseif ($kind === 3)
            {
                $codePoints[] = mt_rand(0x4E00, 0x9FFF);
            }
            else
            {
                $codePoints[] = mt_rand(0x30, 0x39);
            }
        }

        $encoded = g2ml_punycodeEncode($codePoints);
        $decoded = g2ml_punycodeDecode($encoded);

        assert_same($codePoints, $decoded, 'Round trip of ' . $encoded);
        assert_same($encoded, g2ml_punycodeEncode((array) $decoded), 'Decode then encode of ' . $encoded);
    }
});

test('url_ascii: made-up xn-- labels are refused, real ones are accepted', function (): void
{
    foreach (['xn--', 'xn--xn--', 'xn--123', 'xn--a', 'xn--bcher-kva!'] as $label)
    {
        assert_false(g2ml_punycodeLabelIsValid($label), 'Refused: ' . $label);
    }

    foreach (['xn--bcher-kva', 'XN--BCHER-KVA', 'xn--e1afmkfd', 'xn--p1ai', 'xn--r8jz45g', 'xn--zckzah', 'xn--i-7iq', 'xn--mgbh0fb', 'xn--80ak6aa92e', 'xn--3e0b707e', 'example', 'a-b'] as $label)
    {
        assert_true(g2ml_punycodeLabelIsValid($label), 'Accepted: ' . $label);
    }
});

test('url_ascii: the punycode decoder refuses bad input without error', function (): void
{
    assert_same(false, g2ml_punycodeDecode('ab!c'), 'A character that is not a letter or digit');
    assert_same(false, g2ml_punycodeDecode("a\xC3\xBC-b"), 'Non-ASCII before the last hyphen');
    assert_same(false, g2ml_punycodeDecode(str_repeat('z', 80)), 'A number that never ends');
    assert_same([], g2ml_punycodeDecode(''), 'Empty text decodes to nothing');
});

// ----------------------------------------------------------------------------
// 3. Plain ASCII is unchanged
// ----------------------------------------------------------------------------

test('url_ascii: 30000 generated ASCII inputs (no inner space) match the frozen old cleaner byte for byte', function (): void
{
    mt_srand(20261122);

    $prefixes = ['http://', 'https://', 'HTTP://', 'HtTpS://', 'ftp://', 'javascript:', 'http:/', 'https:', '//', '', 'data:text/html,', 'mailto:'];
    $pieces   = [
        'example.com', 'EXAMPLE.com', 'a.b.c', 'g2my.link', 'localhost', '127.0.0.1', '[::1]', '[2001:db8::1]', 'xn--bcher-kva.de', 'a-b.co.uk',
        ':8080', ':80', ':', '@', 'user:pw@', '/', '/a', '/a/b/', '?', '?q=1', '&x=2', '#', '#frag', '%20', '%C3%BC', '%', '%zz', '.', '..', '\\', ';',
        '|', '{', '}', '<', '>', '"', "'", '(', ')', '*', '!', '~', '^', '`', '$', ',', '+', '=', '-', '_', "\t", "\n", "\r", "\0", "\x0B", "\x0C",
    ];

    $checked = 0;

    for ($count = 0; $count < 30000; $count++)
    {
        $input = $prefixes[mt_rand(0, count($prefixes) - 1)];
        $parts = mt_rand(0, 8);

        for ($index = 0; $index < $parts; $index++)
        {
            $kind = mt_rand(0, 5);

            if ($kind === 0)
            {
                // A random ASCII byte, never a space and never 128 or above.
                $byte = mt_rand(0, 126);

                if ($byte === 0x20)
                {
                    $byte = 0x21;
                }

                $input .= chr($byte);
            }
            else
            {
                $input .= $pieces[mt_rand(0, count($pieces) - 1)];
            }
        }

        // Spaces are allowed at the two ends only (they are trimmed away).
        if (mt_rand(0, 9) === 0)
        {
            $input = '  ' . $input;
        }

        if (mt_rand(0, 9) === 0)
        {
            $input .= ' ';
        }

        $expected = g2mlUrlAsciiTest_oldSanitiseURL($input);
        $actual   = g2ml_sanitiseURL($input);

        if ($expected !== $actual)
        {
            assert_same($expected, $actual, 'ASCII input changed: ' . json_encode($input, JSON_INVALID_UTF8_SUBSTITUTE));
        }

        $checked++;
    }

    assert_same(30000, $checked, 'Every generated input was compared');
});

test('url_ascii: null and empty input are still refused', function (): void
{
    assert_same(false, g2ml_sanitiseURL(null), 'null');
    assert_same(false, g2ml_sanitiseURL(''), 'empty');
    assert_same(false, g2ml_sanitiseURL('   '), 'only spaces');
});

// ----------------------------------------------------------------------------
// 4. Cleaning twice changes nothing
// ----------------------------------------------------------------------------

test('url_ascii: cleaning a cleaned address changes nothing, and results hold no non-ASCII byte', function (): void
{
    mt_srand(4242);

    $pieces = [
        'a', 'b.com', 'evil.com', 'g2my.link', '@', ':', '/', '\\', '?', '#', ';', '%40', '%2F', '.', '[', ']', '80', '%', '&', '=', '..', '//', "\t", ' ',
        'É', 'ü', '．', 'Ｇ', '例', "\xC3", '😀', 'é@', 'ß', 'ж', 'ω', "\u{00A0}", "\u{3000}",
    ];

    $accepted = 0;

    for ($count = 0; $count < 20000; $count++)
    {
        $input = 'https://';
        $parts = mt_rand(1, 7);

        for ($index = 0; $index < $parts; $index++)
        {
            $input .= $pieces[mt_rand(0, count($pieces) - 1)];
        }

        $first = g2ml_sanitiseURL($input);

        if ($first === false)
        {
            continue;
        }

        $accepted++;

        assert_same(0, preg_match('/[\x80-\xFF ]/', $first), 'No non-ASCII byte or space in the result of ' . json_encode($input, JSON_INVALID_UTF8_SUBSTITUTE));
        assert_same($first, g2ml_sanitiseURL($first), 'Second pass changed the result of ' . json_encode($input, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    assert_true($accepted > 1000, 'The generated sample must contain a useful number of accepted addresses (got ' . $accepted . ')');
});

// ----------------------------------------------------------------------------
// 5. The lower-case rule
// ----------------------------------------------------------------------------

test('url_ascii: the lower-case rule only maps into allowed letters and agrees with mb_strtolower()', function (): void
{
    $mapped   = 0;
    $accepted = 0;

    $checkList = array_merge(range(0, 0x52F), range(0x1E00, 0x1EFF), [0x3005]);

    foreach ($checkList as $codePoint)
    {
        $lower = g2ml_urlAsciiLowerCodePoint($codePoint);

        if ($lower !== $codePoint)
        {
            $mapped++;
            assert_true(g2ml_urlAsciiLetterGroup($lower) !== '', 'U+' . dechex($codePoint) . ' maps to U+' . dechex($lower) . ', which must be an allowed letter');
        }

        if (g2ml_urlAsciiLetterGroup($lower) === '')
        {
            continue;
        }

        $accepted++;

        // Where we accept the letter, PHP's own lower-casing must agree. If a
        // future PHP changes its tables, this reports it.
        $character = g2mlUrlAsciiTest_utf8([$codePoint]);
        $expected  = g2mlUrlAsciiTest_utf8([$lower]);

        assert_same($expected, mb_strtolower($character, 'UTF-8'), 'mb_strtolower() disagrees for U+' . dechex($codePoint));
    }

    assert_true($mapped >= 320, 'The rule must map at least 320 letters (mapped ' . $mapped . ')');
    assert_true($accepted > 600, 'A sensible number of letters must be accepted (got ' . $accepted . ')');
});

test('url_ascii: five Latin letters are refused; dotless i is allowed by decision', function (): void
{
    // U+0133 ĳ, U+0140 ŀ, U+0149 ŉ and U+017F ſ are changed by browsers into
    // other letters, so they stay refused. U+0138 ĸ is kept by browsers but
    // looks like k and no real name needs it, so it is refused too. U+0130 İ, U+0132 Ĳ and U+013F Ŀ are
    // their capitals (also changed by browsers).
    foreach ([0x133, 0x138, 0x140, 0x149, 0x17F, 0x130, 0x132, 0x13F] as $codePoint)
    {
        assert_same('', g2ml_urlAsciiLetterGroup(g2ml_urlAsciiLowerCodePoint($codePoint)), 'U+' . dechex($codePoint) . ' must not be allowed');
    }

    // The owner decided (2026-11-23) to allow dotless i (browsers keep it),
    // although it looks like "i".
    assert_same('latin', g2ml_urlAsciiLetterGroup(0x131), 'Dotless i is allowed');

    foreach ([0x3F4, 0x1E9E, 0x2126, 0x212A, 0x212B, 0x1E9A, 0x1E9B] as $codePoint)
    {
        assert_same('', g2ml_urlAsciiLetterGroup(g2ml_urlAsciiLowerCodePoint($codePoint)), 'Compatibility character U+' . dechex($codePoint) . ' must not be allowed');
    }

    // Combining marks, symbols and the letters just outside the Vietnamese
    // block are not allowed.
    foreach ([0x460, 0x461, 0x481, 0x48A, 0x482, 0x483, 0x487, 0x488, 0x489, 0x300, 0x1EFA, 0x1EFB, 0x1E01, 0x1E9F, 0x1EA0] as $codePoint)
    {
        assert_same('', g2ml_urlAsciiLetterGroup($codePoint), 'U+' . dechex($codePoint) . ' is not an allowed lower-case letter');
    }
});

test('url_ascii: the extra letters are allowed, and look-alike Japanese characters are refused beside Latin letters', function (): void
{
    assert_same('xn--vitnam-jk8b.vn', g2ml_hostToAscii('việtnam.vn'), 'Vietnamese');
    assert_same('xn--vitnam-jk8b.vn', g2ml_hostToAscii('VIỆTNAM.VN'), 'Vietnamese capitals');
    assert_same('xn--03a', g2ml_hostToAscii('ґ'), 'Ukrainian ghe with upturn');
    assert_same('xn--03a', g2ml_hostToAscii('Ґ'), 'Ukrainian capital ghe with upturn');
    assert_same('xn--u6jy55gilq.jp', g2ml_hostToAscii('佐々木.jp'), 'Iteration mark');
    assert_same('xn--k-ekaa7p.com.tr', g2ml_hostToAscii('ışık.com.tr'), 'Dotless i');
    assert_same(false, g2ml_hostToAscii('ĸ.de'), 'Kra is refused');
    assert_same(false, g2ml_hostToAscii('ѡ.рф'), 'An old Cyrillic letter is refused');
    assert_same(g2ml_hostToAscii('ӏ'), g2ml_hostToAscii('Ӏ'), 'Palochka capital and small give the same host');

    foreach ([0x4E00, 0x30CE, 0x30FC, 0x3007, 0x4E36, 0x4E28, 0x30A8, 0x30ED, 0x30CB, 0x5DE5] as $codePoint)
    {
        $character = g2mlUrlAsciiTest_utf8([$codePoint]);

        assert_same(false, g2ml_hostToAscii('a' . $character . 'b.com'), 'U+' . dechex($codePoint) . ' beside Latin letters is refused');
        assert_same(false, g2ml_hostToAscii('日本' . $character . '1.jp'), 'U+' . dechex($codePoint) . ' beside a digit is refused');
        assert_true(is_string(g2ml_hostToAscii('日本' . $character . '.jp')), 'U+' . dechex($codePoint) . ' in an all-Japanese name is allowed');
    }

    assert_same(false, g2ml_hostToAscii('google一com.evil.net'), 'The ideograph for one between Latin words');
    assert_same(false, g2ml_hostToAscii('googleノcom.net'), 'Katakana no between Latin words');
    assert_true(is_string(g2ml_hostToAscii('google.日本一.jp')), 'The look-alike is only a problem in the label that also has Latin letters');
});

test('url_ascii: a table of real domain names gives the expected result', function (): void
{
    $table = [
        'bücher.de'            => 'xn--bcher-kva.de',
        'münchen.de'           => 'xn--mnchen-3ya.de',
        '例え.jp'                => 'xn--r8jz45g.jp',
        'пример.рф'            => 'xn--e1afmkfd.xn--p1ai',
        'россия.рф'            => 'xn--h1alffa9f.xn--p1ai',
        'ελλάδα.gr'            => 'xn--hxakic4aa.gr',
        'café.fr'              => 'xn--caf-dma.fr',
        'ñandú.com.ar'         => 'xn--and-6ma2c.com.ar',
        '日本語.jp'               => 'xn--wgv71a119e.jp',
        '中文.中国'                => 'xn--fiq228c.xn--fiqs8s',
        '한국.kr'                => 'xn--3e0b707e.kr',
        'straße.de'            => 'xn--strae-oqa.de',
        'øl.dk'                => 'xn--l-4ga.dk',
        'ålesund.no'           => 'xn--lesund-hua.no',
        'tromsø.no'            => 'xn--troms-zua.no',
        'zürich.ch'            => 'xn--zrich-kva.ch',
        'việtnam.vn'           => 'xn--vitnam-jk8b.vn',
        'ışık.com.tr'          => 'xn--k-ekaa7p.com.tr',
        'ґ.укр'                => 'xn--03a.xn--j1amh',
        'қазақстан.қаз'        => 'xn--80aaa0a6awh12ed.xn--80ao21a',
        '佐々木.jp'               => 'xn--u6jy55gilq.jp',
        '日本一.jp'               => 'xn--4gq659cb2a.jp',
        // Refused by our own decision.
        'google一com.evil.net' => false,
        'googleノcom.net'       => false,
        'pаypal.com'           => false,
        'عرب.com'              => false,
        'ไทย.th'               => false,
        'भारत.in'              => false,
        // Refused because the browser changes the letter.
        'ĳssel.nl'             => false,
        'ſ.de'                 => false,
    ];

    $parserClass = 'Uri\\WhatWg\\Url';
    $hasParser   = class_exists($parserClass, false);

    foreach ($table as $host => $expected)
    {
        assert_same($expected, g2ml_hostToAscii($host), 'Host ' . $host);
        $expectedAddress = false;
        if (is_string($expected))
        {
            $expectedAddress = 'https://' . $expected . '/';
        }

        assert_same($expectedAddress, g2ml_sanitiseURL('https://' . $host . '/'), 'Address with host ' . $host);

        if ($hasParser && is_string($expected))
        {
            $parsed = $parserClass::parse('https://' . $host . '/');

            assert_true($parsed !== null && $parsed->getAsciiHost() === $expected, 'The browser-style parser agrees for ' . $host);
        }
    }

    if (!$hasParser)
    {
        echo 'SKIPPED (browser part): this PHP has no browser-style parser (Uri\\WhatWg\\Url)' . PHP_EOL;
    }
});

test('url_ascii: every allowed letter is kept (or lower-cased the same way) by the browser-style parser', function (): void
{
    $parserClass = 'Uri\\WhatWg\\Url';

    if (!class_exists($parserClass, false))
    {
        echo 'SKIPPED: this PHP has no browser-style parser (Uri\\WhatWg\\Url)' . PHP_EOL;
        return;
    }

    $checked = 0;

    foreach (array_merge(range(0x80, 0x52F), range(0x1E00, 0x1EFF), [0x3005]) as $codePoint)
    {
        $lower = g2ml_urlAsciiLowerCodePoint($codePoint);

        if (g2ml_urlAsciiLetterGroup($lower) === '')
        {
            continue;
        }

        // The letter twice, so a label holds one alphabet only.
        $host   = g2mlUrlAsciiTest_utf8([$codePoint, $codePoint]) . '.example';
        $ours   = g2ml_hostToAscii($host);
        $parsed = $parserClass::parse('https://' . $host . '/');

        assert_true($ours !== false, 'We accept ' . $host);
        assert_true($parsed !== null && $parsed->getAsciiHost() === $ours, 'Parser agrees for U+' . dechex($codePoint));
        $checked++;
    }

    echo 'url_ascii allowed-letter oracle check: letters=' . $checked . PHP_EOL;
    assert_true($checked > 600, 'A useful number of letters was checked (got ' . $checked . ')');
});

// ----------------------------------------------------------------------------
// 6. Cross-check with the browser-style parser (PHP 8.5 and later)
// ----------------------------------------------------------------------------

test('url_ascii: every converted host matches the browser-style parser (where PHP has one)', function (): void
{
    if (!class_exists('Uri\\WhatWg\\Url', false))
    {
        echo 'SKIPPED: this PHP has no browser-style parser (Uri\\WhatWg\\Url)' . PHP_EOL;
        return;
    }

    $parserClass = 'Uri\\WhatWg\\Url';
    $compared    = 0;
    $differences = [];
    $refused     = 0;

    $ranges = [[0x80, 0x24F], [0x370, 0x52F], [0x1E00, 0x1EFF], [0x3000, 0x30FF], [0x4E00, 0x9FFF], [0xAC00, 0xD7A3], [0xFF00, 0xFF65]];

    $hosts = [];

    foreach ($ranges as $range)
    {
        for ($codePoint = $range[0]; $codePoint <= $range[1]; $codePoint++)
        {
            $character = g2mlUrlAsciiTest_utf8([$codePoint]);

            if ($character === '')
            {
                continue;
            }

            $hosts[] = 'x' . $character . 'y.de';
            $hosts[] = $character . '.de';
            $hosts[] = $character . $character . '1.com';
        }
    }

    // Random labels from the allowed alphabets, some in capitals.
    mt_srand(7);
    $pools = [
        array_merge(range(0x61, 0x7A), range(0xDF, 0xF6), range(0xF8, 0xFF), range(0x101, 0x17E, 2), range(0x1EA1, 0x1EF9, 2)),
        range(0x3AC, 0x3CE),
        array_merge(range(0x430, 0x45F), range(0x48B, 0x4BF, 2), range(0x4D1, 0x52F, 2)),
        array_merge(range(0x3041, 0x3096), range(0x30A1, 0x30FA), [0x3005], range(0x4E00, 0x4E80), range(0xAC00, 0xAC80)),
    ];

    for ($count = 0; $count < 10000; $count++)
    {
        $pool   = $pools[mt_rand(0, count($pools) - 1)];
        $length = mt_rand(1, 12);
        $label  = '';

        for ($index = 0; $index < $length; $index++)
        {
            $codePoint = $pool[mt_rand(0, count($pool) - 1)];

            if (mt_rand(0, 9) === 0)
            {
                $codePoint = mt_rand(0x30, 0x39);
            }

            $label .= g2mlUrlAsciiTest_utf8([$codePoint]);
        }

        if (mt_rand(0, 3) === 0)
        {
            $label = mb_strtoupper($label, 'UTF-8');
        }

        $hosts[] = $label . '.' . ['com', 'de', 'рф', '中国', 'ελ'][mt_rand(0, 4)];
    }

    foreach ($hosts as $host)
    {
        $ours = g2ml_hostToAscii($host);

        if ($ours === false)
        {
            $refused++;
            continue;
        }

        $parsed = $parserClass::parse('https://' . $host . '/');

        if ($parsed === null)
        {
            $differences[] = $host . ' ours=' . $ours . ' parser=refused';
            continue;
        }

        $theirs = $parsed->getAsciiHost();
        $compared++;

        if ($theirs !== $ours)
        {
            $differences[] = $host . ' ours=' . $ours . ' parser=' . var_export($theirs, true);
        }
    }

    echo 'url_ascii browser cross-check: compared=' . $compared . ' refusedByUsOnly=' . $refused . ' differences=' . count($differences) . PHP_EOL;

    assert_same([], array_slice($differences, 0, 10), 'Every host we accept must match the browser-style parser');
    assert_true($compared > 1000, 'The comparison must cover a useful number of hosts (got ' . $compared . ')');
});

// ----------------------------------------------------------------------------
// 7. Fail closed when url_ascii.php is missing
// ----------------------------------------------------------------------------

test('url_ascii: with url_ascii.php missing, non-English input and spaces are refused and plain ASCII still works', function (): void
{
    if (!function_exists('exec'))
    {
        echo 'SKIPPED: exec disabled' . PHP_EOL;
        return;
    }

    // Copy security.php alone into a new folder, so its loader finds no
    // url_ascii.php next to it.
    $folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'g2ml_ua_missing_' . bin2hex(random_bytes(6));

    if (!mkdir($folder, 0700))
    {
        echo 'SKIPPED: could not make a temporary folder' . PHP_EOL;
        return;
    }

    $copy = $folder . DIRECTORY_SEPARATOR . 'security.php';

    try
    {
        copy(dirname(__DIR__, 2) . '/web/_functions/security.php', $copy);

        $script  = 'require ' . var_export($copy, true) . ';' . "\n";
        $script .= 'echo json_encode(array(' . "\n";
        $script .= '    g2ml_sanitiseURL(\'https://bücher.de/\'),' . "\n";
        $script .= '    g2ml_sanitiseURL(\'https://example.com/straße\'),' . "\n";
        $script .= '    g2ml_sanitiseURL(\'https://example.com/a b\'),' . "\n";
        $script .= '    g2ml_sanitiseURL(\'https://example.com/a\'),' . "\n";
        $script .= '    function_exists(\'g2ml_urlToAscii\'),' . "\n";
        $script .= '));' . "\n";

        $result = g2mlUrlAsciiTest_runChild($script);

        assert_same(0, $result['exitCode'], 'Child process must finish cleanly: ' . $result['output']);
        assert_same('[false,false,false,"https:\/\/example.com\/a",false]', $result['output'], 'Missing file: refuse non-English input and spaces, keep plain ASCII');
    }
    finally
    {
        if (file_exists($copy))
        {
            unlink($copy);
        }

        rmdir($folder);
    }
});

// ----------------------------------------------------------------------------
// 8. Speed on hostile input, and the stricter punycode label check
// ----------------------------------------------------------------------------

/**
 * The end-trimming loop as it was before it was made fast, frozen here. It
 * took one pass for each change of kind, so it was slow on text that
 * alternates spaces and invisible characters. Used to prove the new helper
 * gives the same answers.
 */
function g2mlUrlAsciiTest_oldStripLoop(string $url): string|null
{
    do
    {
        $before = $url;
        $url    = trim($url);
        $url    = preg_replace('/^[\x{FEFF}\x{200B}\x{00A0}\x{3000}]+|[\x{FEFF}\x{200B}\x{00A0}\x{3000}]+$/u', '', $url);

        if ($url === null)
        {
            return null;
        }
    }
    while ($url !== $before);

    return $url;
}

test('url_ascii: the end-trimming gives the same result as the old loop on alternating input', function (): void
{
    $units = [' ', "\t", "\n", "\r", "\0", "\x0B", "\u{FEFF}", "\u{200B}", "\u{00A0}", "\u{3000}", 'a', 'é', '/'];

    mt_srand(11);

    for ($count = 0; $count < 3000; $count++)
    {
        $text   = '';
        $pieces = mt_rand(0, 14);

        for ($index = 0; $index < $pieces; $index++)
        {
            $text .= $units[mt_rand(0, count($units) - 1)];
        }

        assert_same(g2mlUrlAsciiTest_oldStripLoop($text), g2ml_urlAsciiStripEnds($text), 'Trim of ' . json_encode($text));
    }

    foreach ([4, 20, 200] as $repeat)
    {
        $text = str_repeat("\u{FEFF} ", $repeat) . 'https://bücher.de/' . str_repeat(" \u{00A0}", $repeat);
        assert_same(g2mlUrlAsciiTest_oldStripLoop($text), g2ml_urlAsciiStripEnds($text), 'Alternating ends, repeat ' . $repeat);
    }
});

test('url_ascii: about 100 KB of alternating spaces and invisible characters is handled quickly', function (): void
{
    $junk  = str_repeat("\u{FEFF} ", 17000);
    $other = str_repeat(" \u{00A0}", 17000);
    $core  = 'https://bücher.de/';

    $inputs = [
        'start'  => $junk . $core,
        'end'    => $core . $other,
        'middle' => $core . $junk . 'x',
    ];

    $started = hrtime(true);

    foreach ($inputs as $name => $input)
    {
        $result = g2ml_sanitiseURL($input);

        if ($name === 'middle')
        {
            // The junk in the middle is not at an end, so it is kept and encoded.
            assert_true(is_string($result) || $result === false, 'Middle input gives a string or false');
        }
        else
        {
            assert_same('https://xn--bcher-kva.de/', $result, 'Junk at the ' . $name . ' is removed');
        }
    }

    $milliseconds = (hrtime(true) - $started) / 1000000;

    // The slow version needed several seconds for the start and end cases.
    assert_true($milliseconds < 1000, 'Three 100 KB inputs must finish in under a second (took ' . round($milliseconds) . ' ms)');
});

test('url_ascii: percent-encoding runs gives the same bytes as encoding one byte at a time', function (): void
{
    mt_srand(77);

    for ($count = 0; $count < 500; $count++)
    {
        $length = mt_rand(0, 40);
        $text   = '';

        for ($index = 0; $index < $length; $index++)
        {
            if (mt_rand(0, 2) === 0)
            {
                $text .= chr(mt_rand(0x21, 0x7E));
            }
            else
            {
                $text .= chr(mt_rand(0x80, 0xFF));
            }
        }

        $expected = (string) preg_replace_callback(
            '/[\x80-\xFF]/',
            function (array $match): string
            {
                return '%' . strtoupper(bin2hex($match[0]));
            },
            $text
        );

        assert_same($expected, (string) preg_replace_callback('/[\x80-\xFF]+/', 'g2ml_urlAsciiPercentEncodeRun', $text), 'Encoding of ' . bin2hex($text));

        // The earlier version of the run encoder, which used str_split().
        $earlier = (string) preg_replace_callback(
            '/[\x80-\xFF]+/',
            function (array $match): string
            {
                return '%' . implode('%', str_split(strtoupper(bin2hex($match[0])), 2));
            },
            $text
        );

        assert_same($earlier, (string) preg_replace_callback('/[\x80-\xFF]+/', 'g2ml_urlAsciiPercentEncodeRun', $text), 'Earlier version agrees for ' . bin2hex($text));
    }

    $started = hrtime(true);
    $big     = g2ml_urlToAscii('https://example.com/' . str_repeat('é', 500000));
    $elapsed = (hrtime(true) - $started) / 1000000;

    assert_true(is_string($big) && strlen($big) === 20 + 6 * 500000, 'A 1 MB path is encoded completely');
    assert_true($elapsed < 2000, 'A 1 MB path must be encoded quickly (took ' . round($elapsed) . ' ms)');
});

test('url_ascii: the punycode decoder and label check refuse over-long payloads quickly', function (): void
{
    $started = hrtime(true);

    assert_same(false, g2ml_punycodeDecode(str_repeat('a', 100000)), 'A 100,000-byte payload is refused');
    assert_false(g2ml_punycodeLabelIsValid('xn--' . str_repeat('a', 100000)), 'A 100,000-byte label is refused');
    assert_false(g2ml_punycodeLabelIsValid('xn--' . str_repeat('a', 60)), 'A label over 63 bytes is refused');

    $elapsed = (hrtime(true) - $started) / 1000000;

    assert_true($elapsed < 500, 'Refusal must be fast (took ' . round($elapsed) . ' ms)');
    assert_true(is_array(g2ml_punycodeDecode('bcher-kva')), 'A normal payload still decodes');
});

test('url_ascii: a leading hyphen is not skipped by the punycode decoder', function (): void
{
    assert_same(false, g2ml_punycodeDecode('-a'), 'A hyphen with nothing before it is refused');
    assert_same([0x62, 0xFC, 0x63, 0x68, 0x65, 0x72], g2ml_punycodeDecode('bcher-kva'), 'Normal text still decodes');
});

test('url_ascii: the label check refuses noncharacters, the byte-order mark and control codes', function (): void
{
    $bad = [
        [0xFDD0], [0xFDEF], [0xFFFE], [0xFFFF], [0x1FFFE], [0x10FFFF], [0xFEFF], [0x80], [0x9F],
    ];

    foreach ($bad as $codePoints)
    {
        $label = 'xn--' . g2ml_punycodeEncode(array_merge([0x61], $codePoints));

        assert_false(g2ml_punycodeLabelIsValid($label), 'Refused: U+' . dechex($codePoints[0]));
    }

    assert_true(g2ml_punycodeLabelIsValid('xn--' . g2ml_punycodeEncode([0x61, 0xFC])), 'An ordinary letter is still accepted');
});
