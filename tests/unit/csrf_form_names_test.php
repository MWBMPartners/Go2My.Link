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
 * 🧪 Check — no page renders the same literal CSRF form name twice (#147)
 * ============================================================================
 *
 * g2ml_generateCSRFToken() (web/_functions/security.php) keeps exactly ONE
 * token per form name and overwrites it on every call; g2ml_validateCSRFToken()
 * is single-use (it removes the token once checked). So a page that calls
 * g2ml_csrfField('some_name') more than once — for example once per row in a
 * table — has every earlier call's token overwritten by the next one, and
 * only the LAST-rendered form on the page still validates; every earlier row
 * fails with "Session expired". Admin pages had exactly this fault (#147);
 * this check reads every page under the two admin/public page trees and
 * fails the build if another page writes the same literal name twice, or
 * writes it once from inside a loop (see the two scans below).
 *
 * A form name built with concatenation, such as
 * g2ml_csrfField('delete_link_' . $urlUID), is the FIX for this fault, not
 * an instance of it — each call produces a different string once $urlUID
 * differs, so a repeated CALL SITE like that is exactly what the fix looks
 * like. Both scans below therefore only look at a call whose argument is a
 * plain, single-quoted string literal with nothing concatenated onto it;
 * a concatenated call is left alone regardless of how many times it appears
 * or where it sits.
 *
 * TWO SCANS, because a repeated literal can come back in two different
 * shapes:
 *
 *   (1) g2ml_csrfFormNamesTest_extractLiterals() — a regular expression over
 *       the raw source text, counting how many times each literal is
 *       WRITTEN in the file. Catches a form name pasted onto more than one
 *       form on the same page, the shape the sessions and members pages
 *       had before this fix.
 *   (2) g2ml_csrfFormNamesTest_extractLoopLiterals() — a token walk over the
 *       same file, flagging a literal call whose call site sits lexically
 *       inside a foreach/for/while loop body. Catches a form name written
 *       ONCE in the source but RENDERED once per row, the shape the links
 *       page had before its own #147 fix — a shape (1) alone cannot see,
 *       because the literal only appears once in the text no matter how
 *       many rows render it.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * Scan (1) reads source text with a regular expression, so it only
 * recognises g2ml_csrfField('literal') written with single quotes and no
 * concatenation. A double-quoted literal is not recognised as a plain
 * literal by that pattern — the codebase does not currently write calls
 * that way, so this is a limitation, not a known gap being tolerated.
 *
 * Scan (2) only follows the call's own lexical position: a literal call
 * made once per row through a separate function that is itself invoked
 * from a loop, rather than written directly inside the loop's braces, is
 * not caught, because the call site is not textually inside the loop body.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 — Launch Hardening (#147)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 📋 Allow-list — a file that is allowed to render one literal form name
// more than once, and why that is safe. Keyed by the file's path relative
// to web/ (forward-slash separated), then by the literal itself.
// ============================================================================

/**
 * @return array<string, array<string, string>>
 */
function g2ml_csrfFormNamesTest_allowList(): array
{
    return array(
        'Go2My.Link/_admin/public_html/pages/privacy/export/index.php' => array(
            'data_export' => 'Two forms in mutually exclusive if/elseif/else branches on this one page — only one of them ever renders on a given request, so only one token is ever issued at a time.',
        ),
    );
}

// ============================================================================
// 🗂️ Finding the files to scan — the two page trees in this codebase.
// ============================================================================

/**
 * @return array<int, string>
 */
function g2ml_csrfFormNamesTest_pageRoots(): array
{
    $webRoot = dirname(__DIR__, 2) . '/web';

    return array(
        $webRoot . '/Go2My.Link/_admin/public_html/pages',
        $webRoot . '/Go2My.Link/public_html/pages',
    );
}

/**
 * Every .php file under $root, recursively.
 *
 * @param  string $root
 * @return array<int, string>
 */
function g2ml_csrfFormNamesTest_collectPhpFiles(string $root): array
{
    $collected = array();

    if (!is_dir($root))
    {
        return $collected;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo)
    {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isDir())
        {
            continue;
        }

        if (strtolower($fileInfo->getExtension()) !== 'php')
        {
            continue;
        }

        $collected[] = $fileInfo->getPathname();
    }

    sort($collected);

    return $collected;
}

/**
 * $fullPath, made relative to $webRoot with forward slashes, so the
 * allow-list and the failure messages read the same on every machine that
 * can run this suite.
 *
 * @param  string $webRoot  Absolute path to web/ (no trailing slash).
 * @param  string $fullPath Absolute path under $webRoot.
 * @return string
 */
function g2ml_csrfFormNamesTest_relativePath(string $webRoot, string $fullPath): string
{
    $normalisedRoot = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($webRoot, DIRECTORY_SEPARATOR));
    $normalisedFull = str_replace(DIRECTORY_SEPARATOR, '/', $fullPath);
    $prefix         = $normalisedRoot . '/';

    if (strncmp($normalisedFull, $prefix, strlen($prefix)) === 0)
    {
        return substr($normalisedFull, strlen($prefix));
    }

    return $normalisedFull;
}

// ============================================================================
// 🔎 Extracting literal g2ml_csrfField() form names from a file's contents.
// ============================================================================

/**
 * Every literal form name passed to g2ml_csrfField() in $contents — only a
 * call whose argument is a single-quoted string literal with nothing
 * concatenated onto it (see the file docblock for why a concatenated call
 * is deliberately left out). One entry per call site, so a name used twice
 * appears twice in the returned list.
 *
 * @param  string $contents
 * @return array<int, string>
 */
function g2ml_csrfFormNamesTest_extractLiterals(string $contents): array
{
    $matchCount = preg_match_all(
        '/g2ml_csrfField\(\s*\'([^\']*)\'\s*\)/',
        $contents,
        $matches
    );

    if ($matchCount === false || $matchCount === 0)
    {
        return array();
    }

    return $matches[1];
}

// ============================================================================
// 🔁 Scan (2) — a literal g2ml_csrfField() call written once but sitting
// inside a foreach/for/while loop body, so it renders once per iteration
// even though it appears only once in the source text. Token-based, not
// regular-expression-based, because a regular expression has no notion of
// "the enclosing braces" — see the file docblock for why scan (1) alone
// misses this shape.
// ============================================================================

/**
 * Advance past any run of whitespace tokens starting at $index.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string> $tokens  As returned by token_get_all().
 * @param  int   $index
 * @return int   The index of the first non-whitespace token at or after $index.
 */
function g2ml_csrfFormNamesTest_skipWhitespace(array $tokens, int $index): int
{
    while (isset($tokens[$index]) && is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE)
    {
        $index++;
    }

    return $index;
}

/**
 * If the tokens starting at $startIndex are exactly `( 'literal' )` — a
 * single-quoted string literal and nothing else, whitespace aside — return
 * that literal's value. Anything else (concatenation, a second argument,
 * a double-quoted string, an empty argument list) returns null, because it
 * is not the plain-literal shape this check cares about.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string> $tokens
 * @param  int    $startIndex  The index right after the g2ml_csrfField token.
 * @return string|null
 */
function g2ml_csrfFormNamesTest_matchCallLiteral(array $tokens, int $startIndex): ?string
{
    $index = g2ml_csrfFormNamesTest_skipWhitespace($tokens, $startIndex);

    if (!isset($tokens[$index]) || $tokens[$index] !== '(')
    {
        return null;
    }

    $index = g2ml_csrfFormNamesTest_skipWhitespace($tokens, $index + 1);

    if (!isset($tokens[$index]))
    {
        return null;
    }

    $stringToken = $tokens[$index];

    if (!is_array($stringToken) || $stringToken[0] !== T_CONSTANT_ENCAPSED_STRING)
    {
        return null;
    }

    $raw = $stringToken[1];

    if ($raw === '' || $raw[0] !== "'")
    {
        // Double-quoted — out of scope for this scan too, same reasoning
        // as scan (1) (see the file docblock).
        return null;
    }

    $index = g2ml_csrfFormNamesTest_skipWhitespace($tokens, $index + 1);

    if (!isset($tokens[$index]) || $tokens[$index] !== ')')
    {
        // Something else follows the string — concatenation, most likely —
        // so this is not a plain literal call.
        return null;
    }

    return stripcslashes(substr($raw, 1, -1));
}

/**
 * Every plain-literal g2ml_csrfField() call in $contents whose call site is
 * lexically inside a foreach/for/while loop body, however deeply nested in
 * further braces inside that loop. foreach, for and while are all handled
 * the same way (the same branch below), because a token walk cannot tell
 * them apart in any way that matters here — all three open a body the same
 * way once the parenthesised header is balanced.
 *
 * @param  string $contents
 * @return array<int, string>
 */
function g2ml_csrfFormNamesTest_extractLoopLiterals(string $contents): array
{
    $tokens     = token_get_all($contents);
    $tokenCount = count($tokens);

    // One entry per '{' currently open; true means that brace opened a
    // loop's body (or sits inside one), false means it did not. A literal
    // call is "inside a loop" whenever this stack contains any true entry.
    $braceStack = array();

    // Set from the loop keyword until its body's opening brace (or, for a
    // braceless body, until the terminating ';') has been consumed.
    $awaitingLoopBrace = false;
    $parenDepth        = 0;
    $seenParen         = false;

    $flagged = array();

    for ($i = 0; $i < $tokenCount; $i++)
    {
        $token = $tokens[$i];
        $id    = null;
        $text  = $token;

        if (is_array($token))
        {
            $id   = $token[0];
            $text = $token[1];
        }

        if ($id === T_FOREACH || $id === T_FOR || $id === T_WHILE)
        {
            $awaitingLoopBrace = true;
            $parenDepth        = 0;
            $seenParen         = false;
            continue;
        }

        if ($awaitingLoopBrace)
        {
            if ($text === '(')
            {
                $parenDepth++;
                $seenParen = true;
                continue;
            }

            if ($text === ')')
            {
                $parenDepth--;
                continue;
            }

            if ($text === '{' && $seenParen && $parenDepth === 0)
            {
                $braceStack[]      = true;
                $awaitingLoopBrace = false;
                continue;
            }

            if ($text === ';' && $seenParen && $parenDepth === 0)
            {
                // A braceless loop body — this codebase's no-shorthand rule
                // requires braces on every loop, so this is defensive only.
                // Nothing is pushed, so the single statement that follows
                // is simply not tracked as "inside a loop".
                $awaitingLoopBrace = false;
                continue;
            }

            continue;
        }

        if ($text === '{')
        {
            $braceStack[] = false;
            continue;
        }

        if ($text === '}')
        {
            array_pop($braceStack);
            continue;
        }

        if ($id === T_STRING && $text === 'g2ml_csrfField')
        {
            $literal = g2ml_csrfFormNamesTest_matchCallLiteral($tokens, $i + 1);

            if ($literal !== null && in_array(true, $braceStack, true))
            {
                $flagged[] = $literal;
            }
        }
    }

    return $flagged;
}

// ============================================================================
// (a) The real sweep over every shipping page.
// ============================================================================

test('csrf form names: no page renders the same literal g2ml_csrfField() name twice, unless allow-listed (#147)', function (): void
{
    $webRoot   = dirname(__DIR__, 2) . '/web';
    $allowList = g2ml_csrfFormNamesTest_allowList();
    $offenders = array();

    $files = array();

    foreach (g2ml_csrfFormNamesTest_pageRoots() as $pageRoot)
    {
        $files = array_merge($files, g2ml_csrfFormNamesTest_collectPhpFiles($pageRoot));
    }

    foreach ($files as $filePath)
    {
        $contents = file_get_contents($filePath);

        if ($contents === false)
        {
            continue;
        }

        $relativePath = g2ml_csrfFormNamesTest_relativePath($webRoot, $filePath);

        // Scan (1) — the same literal written twice or more in the file.
        $literals = g2ml_csrfFormNamesTest_extractLiterals($contents);

        if (count($literals) >= 2)
        {
            $counts = array_count_values($literals);

            foreach ($counts as $literal => $count)
            {
                if ($count < 2)
                {
                    continue;
                }

                $allowedReason = $allowList[$relativePath][$literal] ?? null;

                if ($allowedReason !== null)
                {
                    continue;
                }

                $offenders[] = $relativePath . ": '" . $literal . "' rendered " . $count . ' times';
            }
        }

        // Scan (2) — a literal written once but sitting inside a loop body,
        // so it renders once per row (see the file docblock).
        $loopLiterals = g2ml_csrfFormNamesTest_extractLoopLiterals($contents);

        foreach ($loopLiterals as $loopLiteral)
        {
            $allowedReason = $allowList[$relativePath][$loopLiteral] ?? null;

            if ($allowedReason !== null)
            {
                continue;
            }

            $offenders[] = $relativePath . ": '" . $loopLiteral . "' called once but sits inside a loop body";
        }
    }

    assert_true(
        count($offenders) === 0,
        'A literal CSRF form name is rendered more than once on one page — see #147: '
            . implode('; ', $offenders)
    );
});

// ============================================================================
// (b) Self-test — proves the scanner actually catches a duplicate, rather
// than passing (a) vacuously because nothing in the pattern ever matches.
// ============================================================================

test('csrf form names: the scanner flags a literal g2ml_csrfField() call used twice in one file', function (): void
{
    $literals = g2ml_csrfFormNamesTest_extractLiterals(
        "<?php\necho g2ml_csrfField('dup_name');\necho g2ml_csrfField('dup_name');\n"
    );

    assert_same(
        array('dup_name', 'dup_name'),
        $literals,
        'Both calls to the same literal must be captured so a real duplicate is caught'
    );
});

// ============================================================================
// (c) Self-test — proves a per-row, concatenated form name is never flagged,
// no matter how many times that call site appears (see the file docblock).
// ============================================================================

test('csrf form names: the scanner does not flag a concatenated (per-row) g2ml_csrfField() call', function (): void
{
    $literals = g2ml_csrfFormNamesTest_extractLiterals(
        "<?php\necho g2ml_csrfField('delete_link_' . (int) \$urlUID);\necho g2ml_csrfField('delete_link_' . (int) \$urlUID);\n"
    );

    assert_same(
        array(),
        $literals,
        'A form name built with concatenation is the FIX for #147, not an instance of it, and must never be matched'
    );
});

// ============================================================================
// (d) Self-test — the allow-list only silences the exact (file, literal)
// pair it names, not the literal everywhere, or the file for every literal.
// ============================================================================

test('csrf form names: the allow-list only covers the exact file and literal it names', function (): void
{
    $allowList = g2ml_csrfFormNamesTest_allowList();

    assert_true(
        !isset($allowList['some/other/file.php']['data_export']),
        'The allow-list must not silence data_export on a file other than privacy/export/index.php'
    );

    assert_true(
        !isset($allowList['Go2My.Link/_admin/public_html/pages/privacy/export/index.php']['some_other_name']),
        'The allow-list must not silence a different literal on privacy/export/index.php'
    );
});

// ============================================================================
// (e) Self-test — proves the loop scanner catches a literal call site
// written once but sitting inside a foreach loop, the exact shape scan (1)
// cannot see (the old links page's single 'delete_link' call, #147).
// ============================================================================

test('csrf form names: the loop scanner flags a literal g2ml_csrfField() call inside a foreach loop', function (): void
{
    $loopLiterals = g2ml_csrfFormNamesTest_extractLoopLiterals(
        "<?php foreach (\$links as \$link) { ?>\n"
            . "<?php echo g2ml_csrfField('delete_link'); ?>\n"
            . '<?php } ?>'
    );

    assert_same(
        array('delete_link'),
        $loopLiterals,
        'A literal call written once inside a foreach body renders once per row and must be flagged'
    );
});

// ============================================================================
// (f) Self-test — proves the loop scanner leaves a literal call alone when
// it sits outside any loop, so it does not flag every literal call on a page.
// ============================================================================

test('csrf form names: the loop scanner does not flag a literal g2ml_csrfField() call outside a loop', function (): void
{
    $loopLiterals = g2ml_csrfFormNamesTest_extractLoopLiterals(
        "<?php\necho g2ml_csrfField('sessions_revoke_all_others');\n"
    );

    assert_same(
        array(),
        $loopLiterals,
        'A single form outside any loop renders once and must never be flagged by the loop scan'
    );
});

// ============================================================================
// (g) Self-test — proves the loop scanner leaves a concatenated per-row call
// alone even inside a loop, the same literal-vs-concatenation distinction
// scan (1) makes (see the file docblock).
// ============================================================================

test('csrf form names: the loop scanner does not flag a concatenated call inside a loop', function (): void
{
    $loopLiterals = g2ml_csrfFormNamesTest_extractLoopLiterals(
        "<?php foreach (\$links as \$link) { ?>\n"
            . "<?php echo g2ml_csrfField('delete_link_' . (int) \$link['urlUID']); ?>\n"
            . '<?php } ?>'
    );

    assert_same(
        array(),
        $loopLiterals,
        'A form name built with concatenation is the FIX for #147, not an instance of it, even inside a loop'
    );
});
