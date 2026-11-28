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
 * 🧪 Check — links that do not open show a page, not the homepage (#290, D-006)
 * ============================================================================
 *
 * The database-backed test tests/integration/dead_link_pages_test.php makes
 * real web requests. These checks cover what can be told from the files alone.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text. It does not open a page in a browser, and it does not
 * run the Apache rules in .htaccess.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#290)
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read a file given relative to the repository root. A missing file fails the
 * test.
 *
 * @param  string $relativePath
 * @return string
 */
function _g2mlDeadLinkRead(string $relativePath): string
{
    $path = dirname(__DIR__, 2) . '/' . $relativePath;

    assert_true(is_file($path), 'Missing file: ' . $relativePath);

    return (string) file_get_contents($path);
}

/**
 * The g2my.link files that print words to a visitor.
 *
 * @return array<int, string>
 */
function _g2mlDeadLinkPageFiles(): array
{
    return array(
        'web/G2My.Link/public_html/index.php',
        'web/G2My.Link/public_html/403.php',
        'web/G2My.Link/public_html/404.php',
        'web/G2My.Link/public_html/500.php',
        'web/G2My.Link/public_html/expired.php',
        'web/G2My.Link/public_html/validating.php',
    );
}

test('#290: a fresh install gives the default organisation no fallback address', function (): void
{
    $seed = _g2mlDeadLinkRead('web/_sql/seeds/002_default_organisation.sql');

    assert_true(
        preg_match("/'go2my\\.link',\\s*NULL,\\s*'free',/", $seed) === 1,
        'Seed 002 stores NULL as the default organisation\'s orgFallbackURL'
    );
});

test('#290: migration 048 clears only the seeded homepage address of the default organisation', function (): void
{
    $migration = _g2mlDeadLinkRead('web/_sql/migrations/048_default_org_dead_link_page.sql');
    $code      = (string) preg_replace('/^--.*$/m', '', $migration);

    assert_contains("`orgHandle` = '[default]'", $code);
    assert_contains("`orgFallbackURL` IN ('https://go2my.link', 'https://go2my.link/')", $code);
    assert_false(str_contains($code, 'tblSettings'), 'The root-visit setting is left alone');
    assert_same(1, substr_count($code, 'UPDATE `tblOrganisations`'), 'One statement changes data');
});

test('#290: no g2my.link page moves a visitor on after a link fails to open', function (): void
{
    foreach (array('web/G2My.Link/public_html/404.php', 'web/G2My.Link/public_html/expired.php') as $file)
    {
        $source = _g2mlDeadLinkRead($file);

        assert_false(str_contains($source, 'http-equiv="refresh"'), $file . ' refreshes to another page');
        assert_false(str_contains($source, 'window.location'), $file . ' changes the address by script');
        assert_false(str_contains($source, 'getDomainFallbackURL'), $file . ' looks up a fallback address');
        assert_contains("g2ml_shortlinkText('shortlink.home_link'", $source, $file . ' has no homepage link text');
    }
});

test('#290: every g2my.link page takes its language and direction from the visitor\'s locale', function (): void
{
    foreach (_g2mlDeadLinkPageFiles() as $file)
    {
        if (str_ends_with($file, 'index.php'))
        {
            continue;
        }

        $source = _g2mlDeadLinkRead($file);

        assert_contains('g2ml_shortlinkLocale()', $source, $file);
        assert_contains('g2ml_shortlinkDirection()', $source, $file);
        assert_false(str_contains($source, '<html lang="en-GB"'), $file . ' fixes the language');
    }
});

test('#290: every text on the g2my.link pages has a seed row, and the English matches', function (): void
{
    $seeds = _g2mlDeadLinkRead('web/_sql/seeds/081_dead_link_page_translations.sql')
        . _g2mlDeadLinkRead('web/_sql/seeds/010_phase6_translations.sql');
    $used  = array();

    foreach (_g2mlDeadLinkPageFiles() as $file)
    {
        $matches = array();

        preg_match_all("/g2ml_shortlinkText\\('([a-z0-9_.]+)', '((?:[^'\\\\]|\\\\.)*)'/", _g2mlDeadLinkRead($file), $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            $key     = $match[1];
            $english = stripslashes($match[2]);

            $used[$key] = true;

            assert_contains("'" . $key . "', '", $seeds, 'No seed row for ' . $key);

            // The older error.* rows have their own wording in seed 010.
            if (str_starts_with($key, 'shortlink.'))
            {
                assert_contains("('en-GB', '" . $key . "', '" . str_replace("'", "''", $english) . "', ", $seeds, 'The English differs for ' . $key . ' in ' . $file);
            }
        }
    }

    $rows = array();

    preg_match_all("/^\\('en-GB', '(shortlink\\.[a-z0-9_]+)',/m", $seeds, $rows);

    assert_true(count($rows[1]) > 20, 'The seed rows were found');

    foreach ($rows[1] as $key)
    {
        assert_true(isset($used[$key]), 'Seed 081 has a row no page uses: ' . $key);
    }
});

test('#290: the corrected help paragraphs use new keys whose English matches the seed', function (): void
{
    $seed  = _g2mlDeadLinkRead('web/_sql/seeds/081_dead_link_page_translations.sql');
    $pages = array(
        'help.short_links.s4_p3_v2' => 'web/Go2My.Link/public_html/pages/help/short-links/index.php',
        'help.short_links.s7_p2_v3' => 'web/Go2My.Link/public_html/pages/help/short-links/index.php',
        'help.analytics.s1_p2_v2'   => 'web/Go2My.Link/public_html/pages/help/analytics/index.php',
    );

    foreach ($pages as $key => $file)
    {
        $matches = array();
        $found   = preg_match("/echo __\\('" . preg_quote($key, '/') . "'\\); \\} else \\{ echo '((?:[^'\\\\]|\\\\.)*)'; \\}/", _g2mlDeadLinkRead($file), $matches);

        assert_same(1, $found, $file . ' does not use ' . $key);
        assert_contains("('en-GB', '" . $key . "', '" . str_replace("'", "''", stripslashes($matches[1])) . "', ", $seed, 'The English differs for ' . $key);
        assert_false(str_contains($matches[1], 'five seconds'), 'The countdown is gone');
    }
});
