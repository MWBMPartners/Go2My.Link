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
 * 🧪 Check — no web address in shipping code ends in .php (#203)
 * ============================================================================
 *
 * The owner's standing rule: no link, form target, redirect or background
 * request in the product may point at an address ending in ".php" — it must
 * use the clean address the router registers (for example "/help/api", never
 * "/help/api/index.php"). Two reasons:
 *
 *   1. A ".php" address tells a stranger what the site is built with, which
 *      is a free head start for anyone looking for a way in.
 *   2. It is often simply broken. Many hosting setups answer "page not
 *      found" for any address ending in ".php", and the page the link sits
 *      on looks completely normal, so nobody notices until someone clicks
 *      it.
 *
 * This file scans the shipping code under web/ for five shapes of address:
 * an href/action/src/formaction attribute, a header('Location: ...') PHP
 * redirect, a JavaScript fetch/open/location call, a quoted string literal
 * starting with "/" and ending in ".php" (a helper function's own `return`
 * value, say), and an EXTERNAL .htaccess redirect (a RewriteRule with an R
 * flag, or a Redirect/RedirectMatch line). An internal RewriteRule target
 * such as "index.php" is a file path the server rewrites to internally,
 * not an address a browser is sent to, and is deliberately not flagged.
 *
 * The idea follows the owner's existing Python check in the WebMS-Intra
 * project (tools/audit-checks/check_no_php_in_urls.py); this one is written
 * in plain PHP so it runs inside the existing DB-free harness with no new
 * tools.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text line by line with regular expressions. It cannot see
 * an address that is assembled at runtime from separate variables (for
 * example "$base . $slug . '.php'" with no ".php" written next to the sink),
 * and it cannot see a JavaScript template literal whose ".php" ending is
 * split across more than one line. It only recognises the five sink shapes
 * named above; a sixth kind of sink (a new JS framework's router, say)
 * would need its own pattern added here.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 📛 Folders that are not shipping web pages, and are skipped.
// ============================================================================

/**
 * True when a path (relative to web/, forward-slash separated) is a folder
 * this check must not walk into.
 *
 * "_libraries", "_backups" and "_uploads" are skipped wherever they occur —
 * each component keeps its own copy alongside the shared one at the top of
 * web/, and none of the three holds pages a browser is ever sent to
 * (vendored libraries, old file backups, and user-uploaded files).
 * "_sql" and "_schemas" are skipped only at the top of web/, which is the
 * only place they exist today; they hold database and JSON-Schema files,
 * not web pages.
 *
 * @param  string $relativePath  Path relative to web/, using '/' separators.
 * @return bool
 */
function _g2mlNoPhpTestShouldSkipDirectory(string $relativePath): bool
{
    if ($relativePath === '')
    {
        return false;
    }

    $segments = explode('/', $relativePath);

    foreach ($segments as $segment)
    {
        if ($segment === '_libraries' || $segment === '_backups' || $segment === '_uploads')
        {
            return true;
        }
    }

    if ($segments[0] === '_sql' || $segments[0] === '_schemas')
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🗂️ Finding the files to scan.
// ============================================================================

/**
 * Turn an absolute path into one relative to $rootPath, using '/' separators
 * regardless of platform, so the skip-folder check and the failure messages
 * read the same on every machine that can run this suite.
 *
 * @param  string $rootPath  The absolute base path (no trailing slash).
 * @param  string $fullPath  An absolute path under $rootPath.
 * @return string             $fullPath relative to $rootPath.
 */
function _g2mlNoPhpTestRelativePath(string $rootPath, string $fullPath): string
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
 * Walk $webRoot and return the absolute paths of every file this check
 * should read: anything named ".htaccess", plus anything ending in .php,
 * .js, .html or .htm, skipping the folders named in
 * _g2mlNoPhpTestShouldSkipDirectory().
 *
 * @param  string $webRoot  Absolute path to the web/ directory.
 * @return array<int, string>
 */
function _g2mlNoPhpTestCollectFiles(string $webRoot): array
{
    $collected = array();

    $directoryIterator = new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS);

    $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, function (SplFileInfo $current) use ($webRoot): bool
    {
        if ($current->isDir())
        {
            $relativePath = _g2mlNoPhpTestRelativePath($webRoot, $current->getPathname());

            if (_g2mlNoPhpTestShouldSkipDirectory($relativePath))
            {
                return false;
            }
        }

        return true;
    });

    $iterator = new RecursiveIteratorIterator($filterIterator);

    foreach ($iterator as $fileInfo)
    {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isDir())
        {
            continue;
        }

        $baseName    = $fileInfo->getFilename();
        $extension   = strtolower($fileInfo->getExtension());
        $isScannable = false;

        if ($baseName === '.htaccess')
        {
            $isScannable = true;
        }
        elseif ($extension === 'php' || $extension === 'js' || $extension === 'html' || $extension === 'htm')
        {
            $isScannable = true;
        }

        if ($isScannable)
        {
            $collected[] = $fileInfo->getPathname();
        }
    }

    sort($collected);

    return $collected;
}

// ============================================================================
// 🧵 Per-line comment detection — a commented-out example must not fail
// the build; only live code is checked.
// ============================================================================

/**
 * True when $line is a whole-line comment in any of the languages this check
 * reads (PHP/JS "//", PHP/.htaccess "#", a PHPDoc continuation "*", or an
 * HTML "<!--"). It only recognises a comment that starts the line — a
 * trailing "// ..." placed after real code on the same line is not treated
 * as a comment, so a genuine sink written that way is still scanned (and, if
 * it points at a ".php" address, still correctly flagged).
 *
 * @param  string $line
 * @return bool
 */
function _g2mlNoPhpTestIsCommentLine(string $line): bool
{
    $trimmed = ltrim($line);

    if ($trimmed === '')
    {
        return false;
    }

    if (strncmp($trimmed, '//', 2) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '#', 1) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '*', 1) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '<!--', 4) === 0)
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🔎 The five sink patterns.
// ============================================================================

/**
 * A ".php" match is thrown away when it has no '/' and no filename
 * character (a letter, digit, '_' or '-') directly before ".php" — for
 * example a bare ".php" captured from a string like "fetch('\.php')",
 * which is a regular-expression fragment, not an address. This drops that
 * kind of false match without dropping a real relative address such as
 * "x.php" or "v2.php".
 *
 * @param  string $matchedValue
 * @return bool
 */
function _g2mlNoPhpTestIsBareFragment(string $matchedValue): bool
{
    $hasSlash                    = (strpos($matchedValue, '/') !== false);
    $hasFileNameCharBeforeDotPhp = (preg_match('~[A-Za-z0-9_-]\.php$~i', $matchedValue) === 1);

    if ($hasSlash === false && $hasFileNameCharBeforeDotPhp === false)
    {
        return true;
    }

    return false;
}

/**
 * Find href / action / src / formaction attribute values ending in .php.
 *
 * @param  string $line
 * @return array<int, string>  The matched address from each occurrence.
 */
function _g2mlNoPhpTestFindHtmlAttributeMatches(string $line): array
{
    $matches = array();

    preg_match_all(
        '~\b(?:href|action|src|formaction)\s*=\s*["\']([^"\'\s>]*?\.php)(?:[?#][^"\']*)?["\']~i',
        $line,
        $found
    );

    foreach ($found[1] as $capturedValue)
    {
        $matches[] = $capturedValue;
    }

    return $matches;
}

/**
 * Find header('Location: ...php') PHP redirects.
 *
 * @param  string $line
 * @return array<int, string>
 */
function _g2mlNoPhpTestFindLocationMatches(string $line): array
{
    $matches = array();

    preg_match_all(
        '~header\(\s*["\']Location:\s*([^"\']*?\.php)(?:[?#][^"\']*)?["\']~i',
        $line,
        $found
    );

    foreach ($found[1] as $capturedValue)
    {
        $matches[] = $capturedValue;
    }

    return $matches;
}

/**
 * Find a JavaScript fetch()/XMLHttpRequest.open()/location(.href) navigation
 * to an address ending in .php. A word boundary is required before the verb
 * so that, for example, "reopen(" cannot be mistaken for "open(".
 *
 * @param  string $line
 * @return array<int, string>
 */
function _g2mlNoPhpTestFindJsMatches(string $line): array
{
    $matches = array();

    preg_match_all(
        '~\b(?:fetch|open|location(?:\.href)?\s*=|location\.(?:assign|replace))\s*\(?\s*(?:["\'][A-Z]+["\']\s*,\s*)?["\'`]([^"\'`\s]*?\.php)(?:[?#][^"\'`]*)?["\'`]~i',
        $line,
        $found
    );

    foreach ($found[1] as $capturedValue)
    {
        $matches[] = $capturedValue;
    }

    return $matches;
}

/**
 * Find a quoted string literal that is itself a web address ending in
 * .php — for example a helper function's own `return '/x.php?' . $q;` —
 * which never appears in an href, a header('Location: ...') redirect or a
 * JS fetch/open/location call, so none of the other patterns see it. A
 * line containing require, include, __DIR__ or DIRECTORY_SEPARATOR is
 * skipped, because those are PHP file-include paths, not addresses a
 * browser is ever sent to.
 *
 * @param  string $line
 * @return array<int, string>
 */
function _g2mlNoPhpTestFindStringLiteralMatches(string $line): array
{
    $matches = array();

    if (preg_match('~\b(?:require|include)(?:_once)?\b~i', $line) === 1)
    {
        return $matches;
    }

    if (strpos($line, '__DIR__') !== false || strpos($line, 'DIRECTORY_SEPARATOR') !== false)
    {
        return $matches;
    }

    preg_match_all(
        '~["\'](/[^"\'\s]*?\.php)(?:[?#][^"\']*)?["\']~',
        $line,
        $found
    );

    foreach ($found[1] as $capturedValue)
    {
        $matches[] = $capturedValue;
    }

    return $matches;
}

/**
 * Find an EXTERNAL .htaccess redirect to an address ending in .php:
 *
 *   - a RewriteRule whose target ends in .php AND whose flag list carries an
 *     R flag (R or R=3xx) — a plain RewriteRule with no R flag is an
 *     internal server-side rewrite the browser never sees, so it is not
 *     flagged;
 *   - a Redirect or RedirectMatch line, which is always browser-visible.
 *
 * @param  string $line
 * @return array<int, string>
 */
function _g2mlNoPhpTestFindHtaccessMatches(string $line): array
{
    $matches = array();

    if (preg_match('~^\s*RewriteRule\s+\S+\s+(\S*?\.php)(?:\?[^\s\[]*)?\s*\[([^\]]*)\]~i', $line, $ruleMatch) === 1)
    {
        $flags = $ruleMatch[2];

        if (preg_match('~(?:^|,)\s*R(?:=[0-9]{3})?\s*(?:,|$)~i', $flags) === 1)
        {
            $matches[] = $ruleMatch[1];
        }
    }

    if (preg_match('~^\s*Redirect(?:Match)?\b.*\s(\S*?\.php)(?:\?[^\s]*)?\s*$~i', $line, $redirectMatch) === 1)
    {
        $matches[] = $redirectMatch[1];
    }

    return $matches;
}

// ============================================================================
// 📄 Scanning one file.
// ============================================================================

/**
 * Scan one file's lines for every sink pattern that applies to it (the
 * .htaccess pattern for a file named ".htaccess", the other four
 * otherwise), skipping whole-line comments and bare-fragment matches. The
 * per-line matches are de-duplicated before becoming findings, because the
 * string-literal pattern can capture the very same address an href or a JS
 * call already matched on that line, and a real offender must not be
 * printed twice.
 *
 * @param  string $filePath  Absolute path to the file to read.
 * @return array<int, array{line: int, match: string}>
 */
function _g2mlNoPhpTestScanFile(string $filePath): array
{
    $findings = array();
    $lines    = file($filePath, FILE_IGNORE_NEW_LINES);

    if ($lines === false)
    {
        return $findings;
    }

    $isHtaccess = (basename($filePath) === '.htaccess');
    $lineNumber = 0;

    foreach ($lines as $line)
    {
        $lineNumber = $lineNumber + 1;

        if (_g2mlNoPhpTestIsCommentLine($line))
        {
            continue;
        }

        if ($isHtaccess)
        {
            $lineMatches = _g2mlNoPhpTestFindHtaccessMatches($line);
        }
        else
        {
            $lineMatches = array_values(array_unique(array_merge(
                _g2mlNoPhpTestFindHtmlAttributeMatches($line),
                _g2mlNoPhpTestFindLocationMatches($line),
                _g2mlNoPhpTestFindJsMatches($line),
                _g2mlNoPhpTestFindStringLiteralMatches($line)
            )));
        }

        foreach ($lineMatches as $matchedValue)
        {
            if (_g2mlNoPhpTestIsBareFragment($matchedValue))
            {
                continue;
            }

            $findings[] = array(
                'line'  => $lineNumber,
                'match' => $matchedValue,
            );
        }
    }

    return $findings;
}

// ============================================================================
// ✅ Allow-list — a genuine false positive gets an entry here, with a reason.
// ============================================================================

/**
 * Exceptions to the check: each entry names one file and one exact matched
 * text. An entry covers every occurrence of that exact text in that one
 * file, and nothing else — the match is not narrowed by line number.
 *
 * Empty as of 2026-09-25: scanning the current tree found no offender and no
 * false positive. Add an entry only for a real false positive — a value
 * that matched the pattern but is not actually a web address — with the
 * plain-English reason.
 *
 * @return array<int, array{file: string, match: string, reason: string}>
 */
function _g2mlNoPhpTestAllowList(): array
{
    return array();
}

// ============================================================================
// 🏃 The check itself.
// ============================================================================

test('no web address in shipping code (web/) ends in .php', function (): void
{
    $repositoryRoot = dirname(__DIR__, 2);
    $webRoot         = $repositoryRoot . DIRECTORY_SEPARATOR . 'web';

    assert_true(is_dir($webRoot), 'web/ must exist for this check to run: ' . $webRoot);

    $allowList     = _g2mlNoPhpTestAllowList();
    $failureLines  = array();
    $filesScanned  = _g2mlNoPhpTestCollectFiles($webRoot);

    foreach ($filesScanned as $absoluteFilePath)
    {
        $relativeFilePath = 'web/' . _g2mlNoPhpTestRelativePath($webRoot, $absoluteFilePath);
        $findings          = _g2mlNoPhpTestScanFile($absoluteFilePath);

        foreach ($findings as $finding)
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

    // At least one file must have been looked at, or the whole check is a
    // false pass (an empty $filesScanned would also produce zero failures).
    assert_true(count($filesScanned) > 0, 'No files were scanned under ' . $webRoot . ' — the collector or the skip rules are wrong.');

    assert_same(
        array(),
        $failureLines,
        'Found web address(es) ending in .php — use the clean address the router registers instead:' . PHP_EOL . implode(PHP_EOL, $failureLines)
    );
});

// ============================================================================
// 🧪 Self-tests for the matcher functions, proving both directions: a real
// .php address is flagged, and a lookalike that is not a web address is not.
// ============================================================================

test('html attribute matcher: an href ending in .php is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindHtmlAttributeMatches('<a href="/help/api/index.php">Help</a>');

    assert_same(array('/help/api/index.php'), $matches);
});

test('html attribute matcher: a clean href is not flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindHtmlAttributeMatches('<a href="/help/api">Help</a>');

    assert_same(array(), $matches);
});

test('location matcher: a PHP redirect to a .php address is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindLocationMatches('header(\'Location: /login.php?x=1\');');

    assert_same(array('/login.php'), $matches);
});

test('no matcher flags a require/include file path', function (): void
{
    $line = 'require_once __DIR__ . \'/x.php\';';

    $matches = array_merge(
        _g2mlNoPhpTestFindHtmlAttributeMatches($line),
        _g2mlNoPhpTestFindLocationMatches($line),
        _g2mlNoPhpTestFindJsMatches($line),
        _g2mlNoPhpTestFindStringLiteralMatches($line)
    );

    assert_same(array(), $matches);
});

test('string-literal matcher: a bare "return" of a .php address is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindStringLiteralMatches('    return \'/analytics-export.php?\' . http_build_query($args);');

    assert_same(array('/analytics-export.php'), $matches);
});

test('string-literal matcher: a plain include with no __DIR__ is not flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindStringLiteralMatches('include \'/templates/header.php\';');

    assert_same(array(), $matches);
});

test('js matcher: a fetch() call to a .php address is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindJsMatches('fetch(\'/api/create/index.php\')');

    assert_same(array('/api/create/index.php'), $matches);
});

test('js matcher: window.location.href set to a .php address is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindJsMatches('window.location.href = "/account/close.php";');

    assert_same(array('/account/close.php'), $matches);
});

test('htaccess matcher: an internal RewriteRule to index.php is not flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindHtaccessMatches('RewriteRule ^info/(.+)$ index.php?code=$1 [L]');

    assert_same(array(), $matches);
});

test('htaccess matcher: a RewriteRule with an R flag to a .php target is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindHtaccessMatches('RewriteRule ^old$ /new.php [R=301,L]');

    assert_same(array('/new.php'), $matches);
});

test('htaccess matcher: a Redirect line to a .php target is flagged', function (): void
{
    $matches = _g2mlNoPhpTestFindHtaccessMatches('Redirect 301 /old /new.php');

    assert_same(array('/new.php'), $matches);
});

test('comment lines are never flagged, even when they contain a .php address', function (): void
{
    assert_true(_g2mlNoPhpTestIsCommentLine('// see /old/page.php for the previous behaviour'));
    assert_true(_g2mlNoPhpTestIsCommentLine('   # RewriteRule ^old$ /new.php [R=301,L]'));
    assert_true(_g2mlNoPhpTestIsCommentLine(' * 📖 Reference: https://www.php.net/manual/en/book.mysqli.php'));
    assert_false(_g2mlNoPhpTestIsCommentLine('<a href="/old/page.php">not a comment</a>'));
});

test('bare-fragment filter: a bare ".php" with no path or filename character before it is dropped', function (): void
{
    assert_true(_g2mlNoPhpTestIsBareFragment('.php'));
    assert_true(_g2mlNoPhpTestIsBareFragment('\\.php'));
    assert_false(_g2mlNoPhpTestIsBareFragment('x.php'));
    assert_false(_g2mlNoPhpTestIsBareFragment('/x.php'));
});

test('bare-fragment filter: a relative address ending in a digit, underscore or hyphen is kept', function (): void
{
    assert_false(_g2mlNoPhpTestIsBareFragment('v2.php'));
    assert_false(_g2mlNoPhpTestIsBareFragment('page-2.php'));
    assert_false(_g2mlNoPhpTestIsBareFragment('login_.php'));
});

test('skip-directory rule: vendored, backup and upload folders are skipped at any depth', function (): void
{
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('_libraries'));
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('Go2My.Link/_libraries'));
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('Go2My.Link/_backups'));
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('_uploads'));
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('_sql'));
    assert_true(_g2mlNoPhpTestShouldSkipDirectory('_schemas'));
    assert_false(_g2mlNoPhpTestShouldSkipDirectory('Go2My.Link/public_html'));
    assert_false(_g2mlNoPhpTestShouldSkipDirectory('Go2My.Link/_admin'));
    assert_false(_g2mlNoPhpTestShouldSkipDirectory('Go2My.Link/public_html_landing'));
});
