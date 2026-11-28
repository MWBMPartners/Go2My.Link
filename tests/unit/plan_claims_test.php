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
 * 🧪 Check — public words about plans and limits stay true (#209, #294 item 8)
 * ============================================================================
 *
 * Decision D-020: the pricing page shows the Free plan with its 50 active
 * links, says paid plans are coming, shows no prices, and does not describe a
 * 30-day statistics window or an API limit on Free. Paid plans cannot be
 * bought, so no message may ask anybody to upgrade.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text. It does not open a page, and it cannot tell whether a
 * sentence is true, apart from the link number matching seed 001.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.0.0 (#209)
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read a file given relative to the repository root.
 *
 * @param  string $relativePath
 * @return string
 */
function _g2mlPlanClaimsTestRead(string $relativePath): string
{
    $path = dirname(__DIR__, 2) . '/' . $relativePath;

    assert_true(is_file($path), 'File not found: ' . $relativePath);

    return (string) file_get_contents($path);
}

/**
 * The translation rows of one seed file: key => English text.
 *
 * @param  string $relativePath
 * @return array<string, string>
 */
function _g2mlPlanClaimsTestSeedRows(string $relativePath): array
{
    $matches = array();
    $rows    = array();

    preg_match_all("/^\('en-GB', '([^']+)', '((?:[^']|'')*)',/m", _g2mlPlanClaimsTestRead($relativePath), $matches, PREG_SET_ORDER);

    foreach ($matches as $match)
    {
        $rows[$match[1]] = str_replace("''", "'", $match[2]);
    }

    return $rows;
}

/**
 * The files that show the words of seed 082.
 *
 * @return array<int, string>
 */
function _g2mlPlanClaimsTestFiles(): array
{
    return array(
        'web/Go2My.Link/public_html/pages/pricing/index.php',
        'web/Go2My.Link/public_html/pages/features/index.php',
        'web/Go2My.Link/public_html/pages/about/index.php',
        'web/Go2My.Link/public_html/pages/help/index.php',
        'web/Go2My.Link/public_html/pages/help/api/index.php',
        'web/Go2My.Link/public_html/pages/help/short-links/index.php',
        'web/Go2My.Link/public_html/pages/help/custom-domains/index.php',
        'web/Go2My.Link/_admin/public_html/pages/linkspage/index.php',
        'web/Go2My.Link/_admin/public_html/pages/linkspage/create/index.php',
        'web/Go2My.Link/_functions/shorturl_create.php',
        'web/_functions/linkspage_manage.php',
    );
}

/**
 * The visible words of the pricing page: every English fallback it echoes or
 * assigns, joined together.
 *
 * @return string
 */
function _g2mlPlanClaimsTestPricingWords(): string
{
    $matches = array();

    preg_match_all(
        "/(?:echo |pageDesc = |pageTitle = )'((?:[^'\\\\]|\\\\.)*)';/",
        _g2mlPlanClaimsTestRead('web/Go2My.Link/public_html/pages/pricing/index.php'),
        $matches
    );

    assert_true(count($matches[1]) > 5, 'The pricing page words could not be read');

    return str_replace("\\'", "'", implode("\n", $matches[1]));
}

test('pricing page: Free with 50 active links and "paid plans are coming" (#209, D-020)', function (): void
{
    $words = _g2mlPlanClaimsTestPricingWords();

    assert_contains('fewer than 50 are switched on', $words);
    assert_contains('past its end date still counts', $words);
    assert_contains('Paid plans are coming', $words);

    $tiers   = _g2mlPlanClaimsTestRead('web/_sql/seeds/001_subscription_tiers.sql');
    $matches = array();

    assert_same(1, preg_match("/'free', 'Free'.*?(\d+),\s*-- maxLinks/s", $tiers, $matches), 'Free tier not found in seed 001');
    assert_same('50', $matches[1], 'Seed 001 no longer gives Free 50 links, so the pricing page is wrong');
});

test('pricing page: no prices, no unenforced limits, no unbuilt plan list (#209, D-020)', function (): void
{
    $words = _g2mlPlanClaimsTestPricingWords();

    foreach (array('$', '£', '€', 'unlimited', '30 days', 'API', 'Enterprise', 'Premium', 'Basic') as $phrase)
    {
        assert_true(stripos($words, $phrase) === false, 'The pricing page says "' . $phrase . '"');
    }

    $source = _g2mlPlanClaimsTestRead('web/Go2My.Link/public_html/pages/pricing/index.php');

    assert_true(preg_match('/[£€]|\$\d/u', $source) !== 1, 'The pricing page source holds a price');
});

test('no public wording asks anybody to upgrade, names an Owner role or promises "shared link libraries" (#209, #294)', function (): void
{
    $problems = array();

    foreach (_g2mlPlanClaimsTestFiles() as $file)
    {
        foreach (explode("\n", _g2mlPlanClaimsTestRead($file)) as $index => $line)
        {
            $trimmed = ltrim($line);

            if (preg_match('#^(//|\*|\#|<!--)#', $trimmed) === 1)
            {
                continue;
            }

            foreach (array('please upgrade', 'upgrade your plan', 'upgrade to create', 'owners, admins', 'shared link libraries', 'thousands of links') as $phrase)
            {
                if (stripos($line, $phrase) !== false)
                {
                    $problems[] = $file . ':' . ($index + 1) . ' says "' . $phrase . '"';
                }
            }
        }
    }

    assert_same(array(), $problems, implode(PHP_EOL, $problems));
});

test('help: the country breakdown is described as conditional (#294 item 8)', function (): void
{
    $rows = _g2mlPlanClaimsTestSeedRows('web/_sql/seeds/082_plans_and_pricing_translations.sql');

    assert_true(isset($rows['help.home.qa5_a_v2']), 'Seed 082 has no help.home.qa5_a_v2');
    assert_contains('only where the site operator has switched on location lookup', $rows['help.home.qa5_a_v2']);
});

test('every seed 082 text is used, and matches the English fallback in the code (#209)', function (): void
{
    $source = '';

    foreach (_g2mlPlanClaimsTestFiles() as $file)
    {
        // Undo PHP's escaping of apostrophes, so the English can be compared.
        $source .= str_replace("\\'", "'", _g2mlPlanClaimsTestRead($file));
    }

    $rows     = _g2mlPlanClaimsTestSeedRows('web/_sql/seeds/082_plans_and_pricing_translations.sql');
    $problems = array();

    assert_same(21, count($rows), 'Unexpected number of rows in seed 082');

    foreach ($rows as $key => $english)
    {
        if (strpos($source, "'" . $key . "'") === false)
        {
            $problems[] = $key . ' is not used';
        }

        // The "with limit" LinksPage message is built around the number in the
        // code's fallback, so its two halves are looked for separately.
        foreach (explode('{limit}', $english) as $part)
        {
            if (strpos($source, $part) === false)
            {
                $problems[] = $key . ' has different English in the code';
            }
        }
    }

    assert_same(array(), $problems, implode(PHP_EOL, $problems));
});

test('seed 082 holds only new key names (#209)', function (): void
{
    $newRows  = _g2mlPlanClaimsTestSeedRows('web/_sql/seeds/082_plans_and_pricing_translations.sql');
    $problems = array();

    foreach (glob(dirname(__DIR__, 2) . '/web/_sql/seeds/*.sql') as $path)
    {
        $name = basename($path);

        if (strpos($name, '082_') === 0)
        {
            continue;
        }

        $otherRows = _g2mlPlanClaimsTestSeedRows('web/_sql/seeds/' . $name);

        foreach (array_keys(array_intersect_key($newRows, $otherRows)) as $key)
        {
            $problems[] = $key . ' is already in ' . $name;
        }
    }

    assert_same(array(), $problems, implode(PHP_EOL, $problems));
});
