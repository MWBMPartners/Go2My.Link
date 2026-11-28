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
 * 🧪 Check — Lnks.Page is announced, and the announcement stays true (#320)
 * ============================================================================
 *
 * Decision D-012: the first version announces LinksPage. This checks that the
 * lnks.page home page no longer says "coming soon", that go2my.link links to
 * it, that every new text has a seed row with the same English as the code's
 * fallback, and that "five ready-made designs" still matches the seeded
 * templates.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text. It does not open a page in a browser, and it cannot
 * tell whether a sentence is true, apart from the number of designs.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#320)
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read a file given relative to the repository root.
 *
 * @param  string $relativePath
 * @return string
 */
function _g2mlAnnouncementTestRead(string $relativePath): string
{
    $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);

    assert_true($contents !== false, 'Could not read ' . $relativePath);

    return (string) $contents;
}

/**
 * The translation rows of seed 075: key => English text.
 *
 * @return array<string, string>
 */
function _g2mlAnnouncementTestSeedRows(): array
{
    $seed    = _g2mlAnnouncementTestRead('web/_sql/seeds/075_linkspage_announcement_translations.sql');
    $matches = array();
    $rows    = array();

    preg_match_all("/^\('en-GB', '([^']+)', '((?:[^']|'')*)',/m", $seed, $matches, PREG_SET_ORDER);

    foreach ($matches as $match)
    {
        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    return $rows;
}

test('lnks.page home page no longer says "coming soon" and links to sign-up and the dashboard (#320)', function (): void
{
    $source = _g2mlAnnouncementTestRead('web/Lnks.page/public_html/index.php');

    assert_true(stripos($source, 'coming soon') === false, 'The lnks.page home page still says "coming soon"');
    assert_true(strpos($source, 'linkspage.coming_soon') === false, 'The old coming-soon key is still used');
    assert_contains('href="https://go2my.link/register"', $source);
    assert_contains('href="https://admin.go2my.link/linkspage"', $source);
    assert_contains('<link rel="canonical" href="https://lnks.page/">', $source);
});

test('lnks.page holding page announces the product and names no unbuilt feature (#320)', function (): void
{
    $source = _g2mlAnnouncementTestRead('web/Lnks.page/public_html_landing/index.php');

    foreach (array('coming soon', 'upcoming', 'wysiwyg', 'click tracking', 'custom domain') as $phrase)
    {
        assert_true(stripos($source, $phrase) === false, 'The lnks.page holding page contains "' . $phrase . '"');
    }

    assert_contains('href="https://go2my.link/"', $source);
});

test('go2my.link links to lnks.page from the home page, features, help and the footer (#320)', function (): void
{
    $files = array(
        'web/Go2My.Link/public_html/pages/home.php',
        'web/Go2My.Link/public_html/pages/features/index.php',
        'web/Go2My.Link/public_html/pages/help/index.php',
        'web/_includes/footer.php',
    );

    foreach ($files as $file)
    {
        assert_contains('href="https://lnks.page/"', _g2mlAnnouncementTestRead($file), $file . ' does not link to lnks.page');
    }
});

test('every seed 075 text is used, and matches the English fallback in the code (#320)', function (): void
{
    $files = array(
        'web/Lnks.page/public_html/index.php',
        'web/Go2My.Link/public_html/pages/home.php',
        'web/Go2My.Link/public_html/pages/features/index.php',
        'web/Go2My.Link/public_html/pages/pricing/index.php',
        'web/Go2My.Link/public_html/pages/help/index.php',
        'web/_includes/footer.php',
    );
    $source = '';

    foreach ($files as $file)
    {
        // Undo PHP's escaping of apostrophes, so the English can be compared.
        $source .= str_replace("\\'", "'", _g2mlAnnouncementTestRead($file));
    }

    $rows     = _g2mlAnnouncementTestSeedRows();
    $problems = array();

    assert_true(count($rows) > 0, 'No rows were read from seed 075');

    foreach ($rows as $key => $english)
    {
        if (strpos($source, "'" . $key . "'") === false)
        {
            $problems[] = $key . ' is not used by any page';
        }

        if (strpos($source, "'" . $english . "'") === false)
        {
            $problems[] = $key . ' has different English in the code';
        }
    }

    // The other direction: a new key used on a page must have a row.
    $matches = array();
    preg_match_all("/'((?:linkspage\.home|home\.linkspage|features\.linkspage|pricing\.free_feature_linkspage|help\.home\.qa_linkspage|footer\.linkspage)[a-z_.]*)'/", $source, $matches);

    foreach (array_unique($matches[1]) as $usedKey)
    {
        if (!array_key_exists($usedKey, $rows))
        {
            $problems[] = $usedKey . ' has no row in seed 075';
        }
    }

    assert_same(array(), $problems, implode(PHP_EOL, $problems));
});

test('"five ready-made designs" matches the system templates in seed 004 (#320)', function (): void
{
    $seed    = _g2mlAnnouncementTestRead('web/_sql/seeds/004_linkspage_templates.sql');
    $matches = array();
    $count   = preg_match_all('/^\s*1, 1, [0-9]+\s*$/m', $seed, $matches);

    assert_same(5, $count, 'The number of system templates changed: update the "five ready-made designs" wording');
});
