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
 * 🧪 Check — the installer's collation fix stays a constant statement (#196, CX-02)
 * ============================================================================
 *
 * Codex's catch-up review of #196 found that the installer's automatic
 * collation correction built its ALTER DATABASE statement by joining the
 * entered database name into the SQL text (the name was checked first
 * against a character allow-list, but it was still string-built SQL, which
 * the project's rules forbid). CX-02 replaced it with a fixed, nameless
 * statement instead — the connection already has the target database
 * selected, so nothing needs to be joined in — and removed the
 * name-validation block that existed only to make that joining safe.
 *
 * This is a plain text/regex check on install/index.php, not a PHP parser,
 * so it cannot see through source written on purpose to defeat it. It
 * exists to catch an ordinary, accidental reintroduction of the old
 * concatenated-string pattern.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      CX-02
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read install/index.php from the repository.
 *
 * @return string
 */
function _g2mlInstallerAlterTestReadFile(): string
{
    $path     = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/install/index.php';
    $contents = file_get_contents($path);

    if ($contents === false)
    {
        throw new RuntimeException('Could not read ' . $path);
    }

    return $contents;
}

/**
 * Find the argument text of the FIRST "$db->query(...)" call in $sourceText
 * whose argument mentions "ALTER DATABASE". The match cannot cross a
 * semicolon. Returns null when no such call is found.
 *
 * @param  string $sourceText
 * @return string|null
 */
function _g2mlInstallerAlterTestFindQueryArgument(string $sourceText): ?string
{
    if (preg_match('/\$db->query\(([^;]*?ALTER DATABASE[^;]*?)\);/s', $sourceText, $matches) !== 1)
    {
        return null;
    }

    return trim($matches[1]);
}

// ============================================================================
// 🏃 The checks themselves.
// ============================================================================

test('the executed ALTER DATABASE statement is one constant string, not built from text (#196, CX-02)', function (): void
{
    $sourceText = _g2mlInstallerAlterTestReadFile();
    $argument   = _g2mlInstallerAlterTestFindQueryArgument($sourceText);

    assert_true($argument !== null, 'No $db->query(...) call mentioning ALTER DATABASE was found in install/index.php.');
    assert_same(
        "'ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'",
        $argument,
        'The executed ALTER DATABASE statement is no longer the fixed constant string — it may have gone '
            . 'back to being built by joining a database name or other value into the SQL text (CX-02).'
    );
});

test('the installer no longer validates the database name for placing it inside SQL (#196, CX-02)', function (): void
{
    $sourceText = _g2mlInstallerAlterTestReadFile();

    assert_false(
        str_contains($sourceText, 'contains a character this installer will not place'),
        'The old name-validation error message is still present — CX-02 removed it because the executed '
            . 'ALTER DATABASE statement no longer has a name joined into it that needs validating.'
    );
});

// ============================================================================
// 🧪 Self-tests for the extractor, so a change to it cannot silently make the
// checks above pass for the wrong reason.
// ============================================================================

test('query-argument extractor: tells a text-built ALTER DATABASE apart from the constant one', function (): void
{
    $fixture = '$db->query(\'ALTER DATABASE `\' . $dbName . \'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\');';

    $argument = _g2mlInstallerAlterTestFindQueryArgument($fixture);

    assert_true($argument !== null, 'The extractor found no call at all in the text-built fixture.');
    assert_not_same(
        "'ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'",
        $argument,
        'A text-built statement (with the database name joined in) was not told apart from the constant one.'
    );
});

test('query-argument extractor: accepts the constant statement', function (): void
{
    $fixture = "\$db->query('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');";

    $argument = _g2mlInstallerAlterTestFindQueryArgument($fixture);

    assert_same("'ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'", $argument);
});
