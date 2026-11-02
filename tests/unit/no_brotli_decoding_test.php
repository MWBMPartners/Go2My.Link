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
 * 🧪 Check — nothing under web/ switches on Brotli decoding (#302)
 * ============================================================================
 *
 * CVE-2026-111497 ("HTTP2Shell") is a bug in the part of Google's Brotli
 * library that UNPACKS compressed data. A program is only at risk if it
 * unpacks Brotli data that someone else controls. The review in #302 found
 * that nothing under web/ does that today:
 *
 *   - our outbound requests use PHP's built-in http:// and https:// streams,
 *     which never unpack a compressed response (checked against the PHP 8.4
 *     source and by experiment);
 *   - the PHP cURL extension unpacks a response only when a script switches
 *     that on: CURLOPT_ACCEPT_ENCODING (older name: CURLOPT_ENCODING) for
 *     Content-Encoding, or CURLOPT_TRANSFER_ENCODING for Transfer-Encoding
 *     (a #302 review round showed curl unpacking Brotli through the second);
 *   - the PHP "brotli" extension unpacks through its functions, named
 *     brotli_*() or, in its namespace, Brotli\uncompress() and the like,
 *     and through a compress.brotli:// address. (It can also unpack the
 *     APCu cache when the server's own php.ini sets apc.serializer=brotli;
 *     a site cannot set that, and the data is the site's own.)
 *
 * This file fails the build if anything that could switch Brotli decoding
 * on appears under web/, so a future change cannot quietly bring the risk
 * back. Rather than try to recognise every way of writing a Brotli call (a
 * #302 review found several that a pattern for "a call" misses), it flags
 * any mention at all, since nothing under web/ mentions Brotli today. It
 * looks for three things:
 *
 *   1. the word "brotli" in any case, anywhere: function names, the Brotli\
 *      namespace however it is written or imported, names passed as strings,
 *      "namespace Brotli;", compress.brotli:// and comments alike;
 *   2. the header name "Accept-Encoding" anywhere, however it is written (a
 *      request that names it can ask a server for Brotli);
 *   3. the constant CURLOPT_ACCEPT_ENCODING, CURLOPT_ENCODING or
 *      CURLOPT_TRANSFER_ENCODING.
 *
 * It reads every text file under web/, vendored libraries included, because
 * a library we copy in can switch decoding on just as easily as our own code.
 * Binary files (any file with a zero byte anywhere in it) are skipped only
 * when they are images or fonts that visitors' browsers display (.png, .ico,
 * .woff2 and the like) AND their first bytes match that type; any other
 * binary file, such as a .phar archive, is reported, because the code inside
 * it cannot be read here, and so is an "image" holding a .phar's
 * __halt_compiler marker, or a file that cannot be opened. The local-only
 * folders _backups and _uploads (ignored by git, empty in CI) are skipped,
 * as tests/unit/no_php_in_urls_test.php does.
 * Comment lines are read too. A genuine, harmless mention is cleared with an
 * allow-list entry and a written reason (see the allow-list below).
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text line by line. It cannot see a word built at runtime
 * from separate pieces (for example 'bro' . 'tli_uncompress' or
 * constant('CURLOPT_' . 'ENCODING')), a cURL setting passed by its number
 * instead of its name (for example curl_setopt($handle, 10102, '')), or an
 * outside program that PHP runs (for example shell_exec('curl --compressed
 * ...')). None of these appears under web/ today: there are no curl_*() calls
 * and no exec-style calls at all. Nor can it see a server-wide php.ini
 * setting such as apc.serializer=brotli, which a site cannot set, or a
 * library that unpacks Brotli inside itself without the word appearing in
 * our code, such as Imagick reading a JPEG XL image or pecl_http's
 * 'compress' option; adding a library like that needs a review against
 * #302 (none is used today: no Imagick, GD or pecl_http calls). It does not
 * look at anything outside web/ (the CI workflows, the Docker images used for
 * local testing, the hosting company's servers), and it cannot tell whether
 * the copy of the Brotli library on any server is patched. Those are covered
 * by the written review in #302, not by this test.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #302
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🗂️ Finding the files to read.
// ============================================================================

/**
 * Turn an absolute path into one relative to $rootPath, using '/' separators
 * on every platform, so failure messages read the same everywhere.
 *
 * @param  string $rootPath  The absolute base path (no trailing slash).
 * @param  string $fullPath  An absolute path under $rootPath.
 * @return string             $fullPath relative to $rootPath.
 */
function _g2mlNoBrotliTestRelativePath(string $rootPath, string $fullPath): string
{
    $normalisedRoot = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($rootPath, DIRECTORY_SEPARATOR));
    $normalisedFull = str_replace(DIRECTORY_SEPARATOR, '/', $fullPath);
    $prefix         = $normalisedRoot . '/';

    if (strncmp($normalisedFull, $prefix, strlen($prefix)) === 0)
    {
        return substr($normalisedFull, strlen($prefix));
    }

    return $normalisedFull;
}

/**
 * True when $sample is binary (an image, a font, an archive). A UTF-8 text
 * file never contains a zero byte. The whole file is passed in, because an
 * archive such as a .phar can start with many kilobytes of plain text.
 *
 * @param  string $sample  A file's contents.
 * @return bool
 */
function _g2mlNoBrotliTestLooksBinary(string $sample): bool
{
    if (strpos($sample, "\0") !== false)
    {
        return true;
    }

    return false;
}

/**
 * True when $contents starts the way a file of type $extension must and
 * holds no .phar marker: the image and font types that are skipped unread
 * are skipped only when their first bytes match, and never when the file
 * also contains __halt_compiler (a .phar can start with real image bytes and
 * still be loaded as code through a phar:// path).
 *
 * @param  string $extension  Lower-case, without the dot.
 * @param  string $contents
 * @return bool
 */
function _g2mlNoBrotliTestIsDisplayFile(string $extension, string $contents): bool
{
    $signatures = array(
        'png'   => array("\x89PNG\r\n\x1a\n"),
        'ico'   => array("\x00\x00\x01\x00"),
        'jpg'   => array("\xFF\xD8\xFF"),
        'jpeg'  => array("\xFF\xD8\xFF"),
        'gif'   => array('GIF87a', 'GIF89a'),
        'woff'  => array('wOFF'),
        'woff2' => array('wOF2'),
        'ttf'   => array("\x00\x01\x00\x00", 'true'),
        'otf'   => array('OTTO', "\x00\x01\x00\x00"),
    );

    if (!isset($signatures[$extension]))
    {
        return false;
    }

    if (stripos($contents, '__halt_compiler') !== false)
    {
        return false;
    }

    foreach ($signatures[$extension] as $signature)
    {
        if (strncmp($contents, $signature, strlen($signature)) === 0)
        {
            return true;
        }
    }

    return false;
}

/**
 * Walk $webRoot and sort its files into text files (to be read) and files
 * that cannot be read here (to be reported): binary files that are not an
 * image or font of the type their name says, and files that cannot be
 * opened. The local-only folders _backups and _uploads are skipped (git
 * ignores them, so they hold nothing that ships).
 *
 * @param  string $webRoot  Absolute path to the web/ directory.
 * @return array{text: array<int, string>, unreadable: array<int, string>}
 */
function _g2mlNoBrotliTestCollectFiles(string $webRoot): array
{
    $collected    = array();
    $unreadable   = array();
    $iterator  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo)
    {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isFile() === false)
        {
            continue;
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($fileInfo->getPathname(), strlen($webRoot) + 1));

        if (preg_match('#(^|/)(_backups|_uploads)/#', $relativePath) === 1)
        {
            continue;
        }

        $contents = file_get_contents($fileInfo->getPathname());

        if ($contents === false)
        {
            $unreadable[] = $fileInfo->getPathname();
            continue;
        }

        if (_g2mlNoBrotliTestLooksBinary($contents))
        {
            if (!_g2mlNoBrotliTestIsDisplayFile(strtolower($fileInfo->getExtension()), $contents))
            {
                $unreadable[] = $fileInfo->getPathname();
            }

            continue;
        }

        $collected[] = $fileInfo->getPathname();
    }

    sort($collected);
    sort($unreadable);

    return array('text' => $collected, 'unreadable' => $unreadable);
}

// ============================================================================
// 🔎 The three patterns.
// ============================================================================

/**
 * Find every mention on one line of source text that could switch Brotli
 * decoding on.
 *
 * @param  string $line
 * @return array<int, string>  The text found, as written.
 */
function _g2mlNoBrotliTestFindMatches(string $line): array
{
    $matches  = array();
    $patterns = array(
        '~[A-Za-z0-9_]*brotli[A-Za-z0-9_]*~i',
        '~Accept-Encoding~i',
        '~\bCURLOPT_(?:ACCEPT_|TRANSFER_)?ENCODING\b~i',
    );

    foreach ($patterns as $pattern)
    {
        $found = array();

        if (preg_match_all($pattern, $line, $found) > 0)
        {
            foreach ($found[0] as $matchedText)
            {
                $matches[] = $matchedText;
            }
        }
    }

    return $matches;
}

/**
 * Read one file and return every match with its line number.
 *
 * @param  string $filePath  Absolute path to the file to read.
 * @return array<int, array{line: int, match: string}>
 */
function _g2mlNoBrotliTestScanFile(string $filePath): array
{
    $findings = array();
    $lines    = file($filePath, FILE_IGNORE_NEW_LINES);

    if ($lines === false)
    {
        return $findings;
    }

    $lineNumber = 0;

    foreach ($lines as $line)
    {
        $lineNumber = $lineNumber + 1;

        foreach (_g2mlNoBrotliTestFindMatches($line) as $matchedValue)
        {
            $findings[] = array(
                'line'  => $lineNumber,
                'match' => $matchedValue,
            );
        }
    }

    return $findings;
}

// ============================================================================
// ✅ Allow-list — a reviewed exception gets an entry here, with a reason.
// ============================================================================

/**
 * Exceptions to the check: each entry names one file and one exact matched
 * text, and covers every occurrence of that text in that one file. For a
 * binary file that is not an image or font, or a file that cannot be
 * opened, the matched text is '(binary or unreadable file)'.
 *
 * Empty as of 2026-11-02: the #302 review found no match under web/. Add an
 * entry only after a security review has confirmed that the mention cannot
 * unpack Brotli data from outside (for example a comment, or a header that
 * asks only for gzip), and write down why.
 *
 * @return array<int, array{file: string, match: string, reason: string}>
 */
function _g2mlNoBrotliTestAllowList(): array
{
    return array();
}

// ============================================================================
// 🏃 The check itself.
// ============================================================================

test('nothing under web/ switches on Brotli decoding (CVE-2026-111497, #302)', function (): void
{
    $webRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'web';

    assert_true(is_dir($webRoot), 'web/ must exist for this check to run: ' . $webRoot);

    $allowList    = _g2mlNoBrotliTestAllowList();
    $failureLines = array();
    $files        = _g2mlNoBrotliTestCollectFiles($webRoot);
    $filesScanned = $files['text'];

    // A binary file that is not an image or font (a .phar archive, say) may
    // hold code this check cannot read, so it needs a review of its own.
    foreach ($files['unreadable'] as $absoluteFilePath)
    {
        $relativeFilePath = 'web/' . _g2mlNoBrotliTestRelativePath($webRoot, $absoluteFilePath);
        $isAllowed        = false;

        foreach ($allowList as $allowEntry)
        {
            if ($allowEntry['file'] === $relativeFilePath && $allowEntry['match'] === '(binary or unreadable file)')
            {
                $isAllowed = true;
                break;
            }
        }

        if ($isAllowed === false)
        {
            $failureLines[] = $relativeFilePath . ': (binary or unreadable file) — its contents cannot be read here';
        }
    }

    foreach ($filesScanned as $absoluteFilePath)
    {
        $relativeFilePath = 'web/' . _g2mlNoBrotliTestRelativePath($webRoot, $absoluteFilePath);

        foreach (_g2mlNoBrotliTestScanFile($absoluteFilePath) as $finding)
        {
            $isAllowed = false;

            foreach ($allowList as $allowEntry)
            {
                if ($allowEntry['file'] === $relativeFilePath && $allowEntry['match'] === $finding['match'])
                {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed === false)
            {
                $failureLines[] = $relativeFilePath . ':' . $finding['line'] . ': ' . $finding['match'];
            }
        }
    }

    // An empty file list would also produce zero failures, so make sure the
    // walk actually found the code it is meant to read.
    assert_true(count($filesScanned) > 100, 'Too few files were read under ' . $webRoot . ' — the file walk is broken.');

    assert_same(
        array(),
        $failureLines,
        'Found a mention of Brotli, Accept-Encoding or a cURL decoding setting, or a binary file that is not an image or font, under web/ (#302). If it cannot unpack Brotli data from outside, add an entry with a written reason to _g2mlNoBrotliTestAllowList() in tests/unit/no_brotli_decoding_test.php:' . PHP_EOL . implode(PHP_EOL, $failureLines)
    );
});

// ============================================================================
// 🧪 Self-tests for the matcher, in both directions.
// ============================================================================

test('brotli matcher: all three cURL decoding switches are flagged', function (): void
{
    assert_same(array('CURLOPT_ACCEPT_ENCODING'), _g2mlNoBrotliTestFindMatches('curl_setopt($handle, CURLOPT_ACCEPT_ENCODING, \'\');'));
    assert_same(array('CURLOPT_ENCODING'), _g2mlNoBrotliTestFindMatches('curl_setopt($handle, CURLOPT_ENCODING, \'br\');'));
    assert_same(array('CURLOPT_TRANSFER_ENCODING'), _g2mlNoBrotliTestFindMatches('curl_setopt($handle, CURLOPT_TRANSFER_ENCODING, true);'));
    assert_same(array('CURLOPT_ENCODING'), _g2mlNoBrotliTestFindMatches('curl_setopt_array($handle, array(CURLOPT_ENCODING => \'\'));'));
});

test('brotli matcher: Swagger UI\'s "curlOptions" is not flagged', function (): void
{
    assert_same(array(), _g2mlNoBrotliTestFindMatches('const x = s.get("curlOptions");'));
});

test('brotli matcher: every way of writing a Brotli name is flagged', function (): void
{
    $lines = array(
        '$plain = brotli_uncompress($body);'                        => 'brotli_uncompress',
        '$plain = call_user_func(\'brotli_uncompress\', $body);'    => 'brotli_uncompress',
        '$plain = \\Brotli\\uncompress($body);'                     => 'Brotli',
        '$plain = call_user_func(\'Brotli\\\\uncompress\', $body);' => 'Brotli',
        'use function Brotli\\uncompress_add;'                      => 'Brotli',
        'use function Brotli\\{uncompress, uncompress_add};'        => 'Brotli',
        'use function Brotli \\ {uncompress};'                       => 'Brotli',
        'namespace Brotli;'                                         => 'Brotli',
        'use function BROTLI/** x */\\{uncompress};'                 => 'BROTLI',
        '$h = fopen(\'compress.brotli://\' . $path, \'rb\');'        => 'brotli',
        '// the libbrotli package'                                  => 'libbrotli',
    );

    foreach ($lines as $line => $expected)
    {
        assert_same(array($expected), _g2mlNoBrotliTestFindMatches($line), 'Not flagged as expected: ' . $line);
    }
});

test('brotli matcher: compress.zlib:// (gzip, not Brotli) is not flagged', function (): void
{
    assert_same(array(), _g2mlNoBrotliTestFindMatches('$h = fopen(\'compress.zlib://\' . $path, \'rb\');'));
});

test('brotli matcher: Accept-Encoding is flagged however it is written', function (): void
{
    assert_same(array('Accept-Encoding'), _g2mlNoBrotliTestFindMatches('\'header\' => "Accept-Encoding: gzip, br\r\n",'));
    assert_same(array('Accept-Encoding'), _g2mlNoBrotliTestFindMatches('$headers = array(\'Accept-Encoding\' => \'br\');'));
    assert_same(array('accept-encoding'), _g2mlNoBrotliTestFindMatches('$name = \'accept-encoding\';'));
});

test('brotli matcher: an ordinary "Accept: */*" header is not flagged', function (): void
{
    assert_same(array(), _g2mlNoBrotliTestFindMatches('\'header\' => "Accept: */*\r\nHost: " . $hostHeaderValue . "\r\n",'));
});

test('binary check: a zero byte marks a file as binary; plain text does not', function (): void
{
    assert_true(_g2mlNoBrotliTestLooksBinary("wOF2\0\1\0\0"));
    assert_false(_g2mlNoBrotliTestLooksBinary("<?php\necho 'hello';\n"));
});

test('binary check: an image or font is skipped only when its first bytes match its type', function (): void
{
    assert_true(_g2mlNoBrotliTestIsDisplayFile('png', "\x89PNG\r\n\x1a\n" . "\0rest"));
    assert_true(_g2mlNoBrotliTestIsDisplayFile('woff2', "wOF2\0\1"));
    assert_false(_g2mlNoBrotliTestIsDisplayFile('png', "<?php __HALT_COMPILER(); ?>\0phar"), 'A .phar named .png must not be skipped');
    assert_false(_g2mlNoBrotliTestIsDisplayFile('phar', "\x89PNG\r\n\x1a\n"), 'Only image and font types are ever skipped');
    assert_false(_g2mlNoBrotliTestIsDisplayFile('png', "\x89PNG\r\n\x1a\n<?php __halt_compiler(); ?>\0"), 'A .phar that starts with real PNG bytes must not be skipped');
    assert_true(_g2mlNoBrotliTestIsDisplayFile('otf', "\x00\x01\x00\x00\0rest"), 'An OpenType font with TrueType outlines is a font');
});
