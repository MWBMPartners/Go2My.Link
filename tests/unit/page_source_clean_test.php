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
 * 🧪 Check — page source carries no developer notes (#285)
 * ============================================================================
 *
 * Every page used to start with about 100 HTML comments (about 7 KB) written
 * for the people who edit the shared header, navigation, footer and cookie
 * banner. A visitor downloads them for nothing, and some said more than a
 * visitor should see: the three coming-soon pages carried notes with issue
 * numbers and printed the licence text before <!DOCTYPE html>.
 *
 * Those notes are now PHP comments, which PHP drops before the page is sent.
 * This check fails if:
 *   - a shared page layout file or a coming-soon page can print an HTML
 *     comment (an HTML comment outside PHP, or written inside a PHP string);
 *   - a coming-soon page's inline script carries a "// ... #123" note with an
 *     issue number;
 *   - a file that prints <!DOCTYPE html> prints anything before it: a comment,
 *     a blank line, a byte-order mark, or an echo.
 *
 * To allow a comment that must reach the browser (for example an Outlook-style
 * conditional comment), add it to $g2mlPageSourceAllowed below, with the file,
 * the exact text, and the reason.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads the files as PHP source. It cannot see a comment that is built from
 * pieces while the page runs, and it does not look at the pages under
 * public_html/pages/ (each of those has its own comments), the error pages, or
 * the email templates (they are not web pages, and their Outlook conditional
 * comments must stay).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #285
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 📋 What is checked
// ============================================================================

/**
 * The shared layout files, which every main-site and dashboard page includes.
 *
 * @return array<int, string>
 */
function _g2mlPageSourceLayoutFiles(): array
{
    return array(
        'web/_includes/header.php',
        'web/_includes/nav.php',
        'web/_includes/footer.php',
        'web/_includes/cookie_banner.php',
        'web/_includes/language_switcher.php',
        'web/_includes/translate_widget.php',
    );
}

/**
 * The three coming-soon pages.
 *
 * @return array<int, string>
 */
function _g2mlPageSourceLandingFiles(): array
{
    return array(
        'web/Go2My.Link/public_html_landing/index.php',
        'web/G2My.Link/public_html_landing/index.php',
        'web/Lnks.page/public_html_landing/index.php',
    );
}

/**
 * Comments that are allowed to reach the browser: file => list of the exact
 * comment text, each with the reason it must stay. Empty today.
 *
 * @var array<string, array<string, string>>
 */
$g2mlPageSourceAllowed = array();

// ============================================================================
// 🔍 The scanners (they take source text, so they can be tested on samples)
// ============================================================================

/**
 * Every HTML comment a file could print: inside inline HTML, or inside any PHP
 * string. Returns the text of each comment.
 *
 * @param  string $source  A PHP file's text.
 * @return array<int, string>
 */
function _g2mlPageSourceFindHtmlComments(string $source): array
{
    $found  = array();
    $tokens = token_get_all($source);

    foreach ($tokens as $token)
    {
        if (!is_array($token))
        {
            continue;
        }

        $kind = $token[0];

        if ($kind !== T_INLINE_HTML && $kind !== T_CONSTANT_ENCAPSED_STRING && $kind !== T_ENCAPSED_AND_WHITESPACE)
        {
            continue;
        }

        $matches = array();

        if (preg_match_all('/<!--(.*?)(?:-->|$)/s', $token[1], $matches) > 0)
        {
            foreach ($matches[0] as $comment)
            {
                $found[] = trim((string) preg_replace('/\s+/', ' ', $comment));
            }
        }
    }

    return $found;
}

/**
 * "// ... #123" notes with an issue number inside the inline HTML of a file
 * (that is, inside a <script> block that is sent to the browser).
 *
 * @param  string $source
 * @return array<int, string>
 */
function _g2mlPageSourceFindScriptIssueNotes(string $source): array
{
    $found  = array();
    $tokens = token_get_all($source);

    foreach ($tokens as $token)
    {
        if (!is_array($token) || $token[0] !== T_INLINE_HTML)
        {
            continue;
        }

        $matches = array();

        if (preg_match_all('~//[^\n]*(?<![&\w])#[0-9]{1,4}\b[^\n]*~', $token[1], $matches) > 0)
        {
            foreach ($matches[0] as $note)
            {
                $found[] = trim($note);
            }
        }
    }

    return $found;
}

/**
 * What a file prints before its <!DOCTYPE html>. Returns an empty string when
 * the file prints nothing first, or a short description of what it prints, or
 * "no doctype" when it never prints one.
 *
 * @param  string $source
 * @return string
 */
function _g2mlPageSourceBeforeDoctype(string $source): string
{
    if (strncmp($source, '<?php', 5) !== 0)
    {
        return 'the file does not start with <?php (a blank line, a space or a byte-order mark would be printed)';
    }

    $tokens = token_get_all($source);

    foreach ($tokens as $token)
    {
        if (!is_array($token))
        {
            continue;
        }

        if ($token[0] === T_INLINE_HTML)
        {
            if (strncmp($token[1], '<!DOCTYPE html>', 15) === 0)
            {
                return '';
            }

            return 'inline HTML that is not the doctype: ' . substr(trim($token[1]), 0, 60);
        }

        if ($token[0] === T_ECHO || $token[0] === T_PRINT || $token[0] === T_OPEN_TAG_WITH_ECHO)
        {
            return 'an echo or print at line ' . $token[2];
        }
    }

    return 'no doctype';
}

// ============================================================================
// 🏃 The checks
// ============================================================================

foreach (array_merge(_g2mlPageSourceLayoutFiles(), _g2mlPageSourceLandingFiles()) as $g2mlPageSourceFile)
{
    test('page source: ' . $g2mlPageSourceFile . ' prints no HTML comment (#285)', function () use ($g2mlPageSourceFile, $g2mlPageSourceAllowed): void
    {
        $path = dirname(__DIR__, 2) . '/' . $g2mlPageSourceFile;

        assert_true(is_file($path), 'Expected file not found: ' . $g2mlPageSourceFile);

        $allowed = array();

        if (isset($g2mlPageSourceAllowed[$g2mlPageSourceFile]))
        {
            $allowed = array_keys($g2mlPageSourceAllowed[$g2mlPageSourceFile]);
        }

        $unexpected = array();

        foreach (_g2mlPageSourceFindHtmlComments((string) file_get_contents($path)) as $comment)
        {
            if (!in_array($comment, $allowed, true))
            {
                $unexpected[] = $comment;
            }
        }

        assert_same(
            array(),
            $unexpected,
            $g2mlPageSourceFile . ' prints HTML comments. Make each a PHP comment (<?php // ... ?>), or allow it in this test with a reason:' . PHP_EOL . implode(PHP_EOL, $unexpected)
        );
    });
}

foreach (_g2mlPageSourceLandingFiles() as $g2mlPageSourceFile)
{
    test('page source: ' . $g2mlPageSourceFile . ' sends no issue-number notes in its script (#285)', function () use ($g2mlPageSourceFile): void
    {
        $path = dirname(__DIR__, 2) . '/' . $g2mlPageSourceFile;

        assert_same(
            array(),
            _g2mlPageSourceFindScriptIssueNotes((string) file_get_contents($path)),
            $g2mlPageSourceFile . ' has a script comment with an issue number. Move it into a PHP comment.'
        );
    });
}

foreach (array_merge(array('web/_includes/header.php'), _g2mlPageSourceLandingFiles()) as $g2mlPageSourceFile)
{
    test('page source: ' . $g2mlPageSourceFile . ' prints nothing before <!DOCTYPE html> (#285)', function () use ($g2mlPageSourceFile): void
    {
        $path = dirname(__DIR__, 2) . '/' . $g2mlPageSourceFile;

        assert_same(
            '',
            _g2mlPageSourceBeforeDoctype((string) file_get_contents($path)),
            $g2mlPageSourceFile . ' prints something before its doctype'
        );
    });
}

// ============================================================================
// 🧪 The scanners themselves, on sample text (so a broken scanner cannot pass)
// ============================================================================

test('page source: the comment scanner finds a comment in inline HTML and one inside a PHP string', function (): void
{
    $sample = "<?php \$x = 1; ?>\n<p>hi</p>\n<!-- a note -->\n<?php echo '<!-- another -->'; ?>\n";

    assert_same(array('<!-- a note -->', '<!-- another -->'), _g2mlPageSourceFindHtmlComments($sample));
});

test('page source: the comment scanner ignores PHP comments', function (): void
{
    $sample = "<?php // a note ?>\n<?php /* another */ ?>\n<p>hi</p>\n";

    assert_same(array(), _g2mlPageSourceFindHtmlComments($sample));
});

test('page source: the doctype scanner catches a comment, a blank line, an echo and a missing doctype', function (): void
{
    assert_same('', _g2mlPageSourceBeforeDoctype("<?php \$a = 1;\n?>\n<!DOCTYPE html>\n<html></html>\n"));
    assert_true(_g2mlPageSourceBeforeDoctype("<!-- c -->\n<?php \$a = 1; ?>\n<!DOCTYPE html>\n") !== '', 'A comment before PHP is printed');
    assert_true(_g2mlPageSourceBeforeDoctype("<?php \$a = 1;\n?>\n\n<!DOCTYPE html>\n") !== '', 'A blank line before the doctype is printed');
    assert_true(_g2mlPageSourceBeforeDoctype("<?php echo 'x'; ?>\n<!DOCTYPE html>\n") !== '', 'An echo before the doctype is printed');
    assert_same('no doctype', _g2mlPageSourceBeforeDoctype("<?php \$a = 1;\n"));
    assert_true(strpos(_g2mlPageSourceBeforeDoctype("<?php \$a = 1; ?>\n<p>hi</p>\n"), 'inline HTML') === 0, 'HTML before the doctype is printed');
});

test('page source: the issue-note scanner finds a script comment with an issue number but not a colour', function (): void
{
    $sample = "<script>\n  var a = 1; // see #104 for why\n  var b = '#1E88E5';\n</script>\n";

    assert_same(array('// see #104 for why'), _g2mlPageSourceFindScriptIssueNotes($sample));
});
