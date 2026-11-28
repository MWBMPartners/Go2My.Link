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
 * 🧪 Unit tests — the shared g2my.link codes (#289, owner decision D-003)
 * ============================================================================
 *
 * The database tests in tests/integration/shared_g2my_codes_test.php prove
 * the behaviour. These DB-free checks catch the cheaper mistakes early:
 *   - the rule for "does a new link use the shared codes?";
 *   - the fresh-install schema and migration 044 describing the same
 *     column, key and check, so a fresh install and an upgraded database
 *     end up the same;
 *   - migration 044 needing no special database privileges (no stored
 *     procedure, trigger or function, so no DELIMITER line either);
 *   - both stored procedures reading the new column.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #289
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';

/**
 * Read a file from the repository, failing the test loudly if it is missing.
 *
 * @param  string $relativePath
 * @return string
 */
function g2ml_sgc_unit_read(string $relativePath): string
{
    $path     = dirname(__DIR__, 2) . '/' . $relativePath;
    $contents = file_get_contents($path);

    if ($contents === false)
    {
        throw new RuntimeException('Could not read ' . $relativePath);
    }

    return $contents;
}

/**
 * Remove SQL comment lines ("-- ..."), so a check reads only statements.
 *
 * @param  string $sql
 * @return string
 */
function g2ml_sgc_unit_strip_comments(string $sql): string
{
    $kept = [];

    foreach (explode("\n", $sql) as $line)
    {
        if (str_starts_with(ltrim($line), '--'))
        {
            continue;
        }

        $kept[] = $line;
    }

    return implode("\n", $kept);
}

test('#289 rule: the default organisation always uses the shared g2my.link codes', function (): void
{
    assert_true(g2ml_linkUsesSharedShortCodes('[default]', 'g2my.link'), '[default] on g2my.link');
    assert_true(g2ml_linkUsesSharedShortCodes('[default]', 'something-else.test'), '[default] whatever its domain rows say');
});

test('#289 rule: an organisation whose links are printed on g2my.link uses the shared codes', function (): void
{
    assert_true(g2ml_linkUsesSharedShortCodes('acme', 'g2my.link'), 'No verified default domain of its own');
    assert_true(g2ml_linkUsesSharedShortCodes('acme', 'G2MY.LINK'), 'Capital letters make no difference');
});

test('#289 rule: an organisation with its own verified default domain keeps its own codes', function (): void
{
    assert_false(g2ml_linkUsesSharedShortCodes('acme', 'acme.link'), 'Its links are printed on, and open on, acme.link');
});

test('#289 schema: tblShortURLs has sharedShortCode, its unique key and its check', function (): void
{
    $schema = g2ml_sgc_unit_read('web/_sql/schema/020_shorturls_categories_tags.sql');

    assert_true(preg_match('/`sharedShortCode`\s+VARCHAR\(50\)\s+DEFAULT NULL/', $schema) === 1, 'The column is VARCHAR(50), empty by default');
    assert_contains('UNIQUE KEY `UQ_url_shared_code` (`sharedShortCode`)', $schema, 'The unique key');
    assert_contains('CONSTRAINT `CK_url_shared_code_matches`', $schema, 'The check');
    assert_contains('CHECK (`sharedShortCode` IS NULL OR `sharedShortCode` = `shortCode`)', $schema, 'The check\'s rule');
    assert_contains('UNIQUE KEY `UQ_shortcode_org` (`shortCode`, `orgHandle`)', $schema, 'The per-organisation key is kept');
});

test('#289 migration 044: adds the same column, key and check as the schema, with no special privileges', function (): void
{
    $migration  = g2ml_sgc_unit_read('web/_sql/migrations/044_shared_g2my_codes.sql');
    $statements = g2ml_sgc_unit_strip_comments($migration);

    assert_true(preg_match('/ADD COLUMN `sharedShortCode` VARCHAR\(50\)[^\']*DEFAULT NULL/', $statements) === 1, 'Same column type and default as the schema');
    assert_contains('ADD UNIQUE KEY `UQ_url_shared_code` (`sharedShortCode`)', $statements, 'Same unique key');
    assert_contains('CHECK (`sharedShortCode` IS NULL OR `sharedShortCode` = `shortCode`)', $statements, 'Same check');

    assert_false(stripos($statements, 'DELIMITER') !== false, 'No DELIMITER line, so it can be run by any client');
    assert_false(stripos($statements, 'CREATE PROCEDURE') !== false, 'No stored procedure');
    assert_false(stripos($statements, 'CREATE TRIGGER') !== false, 'No trigger');
    assert_false(stripos($statements, 'CREATE FUNCTION') !== false, 'No function');

    assert_contains('UPDATE IGNORE `tblShortURLs`', $statements, 'The fill-in skips taken codes instead of failing');
});

test('#289 procedures: the redirect lookup and the code generator both read sharedShortCode', function (): void
{
    $lookup    = g2ml_sgc_unit_strip_comments(g2ml_sgc_unit_read('web/_sql/procedures/sp_lookupShortURL.sql'));
    $generator = g2ml_sgc_unit_strip_comments(g2ml_sgc_unit_read('web/_sql/procedures/sp_generateShortCode.sql'));

    assert_contains('s.`sharedShortCode` = CONVERT(v_currentCode USING utf8mb4) COLLATE utf8mb4_unicode_ci', $lookup, 'g2my.link looks up the shared codes');
    assert_contains('`sharedShortCode` = CONVERT(v_candidate USING utf8mb4) COLLATE utf8mb4_unicode_ci', $generator, 'A random code must be free among the shared codes');
});
