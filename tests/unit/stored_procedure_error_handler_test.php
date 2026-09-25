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
 * 🧪 Check — neither stored procedure hides a database error any more (#197)
 * ============================================================================
 *
 * sp_generateShortCode and sp_lookupShortURL each used to carry a
 * "DECLARE EXIT HANDLER FOR SQLEXCEPTION" block that caught EVERY SQL error
 * — a wrong collation, a missing table, a permissions problem, anything —
 * and quietly folded it into an existing outcome: sp_generateShortCode into
 * the same NULL it already returns after running out of attempts;
 * sp_lookupShortURL into a bare status='error' with no further detail. That
 * made a real database fault indistinguishable from ordinary bad luck, and
 * swallowed the MySQL error message that would have said why (#197).
 *
 * This is a plain substring search on each file, the same technique already
 * used by collation_docs_test.php for a different fact — it proves the
 * handler text is ABSENT, not that the procedure behaves correctly, which
 * tests/integration/stored_procedure_errors_test.php proves for
 * sp_generateShortCode against a real database.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #197
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The two procedure files #197 applies to, each relative to the repository
 * root.
 *
 * @return array<int, string>
 */
function _g2mlStoredProcedureErrorHandlerTestFiles(): array
{
    return array(
        'web/_sql/procedures/sp_generateShortCode.sql',
        'web/_sql/procedures/sp_lookupShortURL.sql',
    );
}

foreach (_g2mlStoredProcedureErrorHandlerTestFiles() as $relativePath)
{
    test('has no catch-all SQL error handler: ' . $relativePath . ' (#197)', function () use ($relativePath): void
    {
        $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;

        assert_true(is_file($fullPath), $relativePath . ' does not exist');

        $contents = file_get_contents($fullPath);

        assert_true($contents !== false, 'Could not read ' . $relativePath);

        // Either handler form catches every SQL error the same way — a
        // CONTINUE HANDLER would swallow it just as silently as the EXIT
        // HANDLER these files used to have, so both are checked for.
        assert_false(
            str_contains((string) $contents, 'EXIT HANDLER FOR SQLEXCEPTION'),
            $relativePath . ' still has a catch-all EXIT HANDLER FOR SQLEXCEPTION — this is exactly '
                . 'what #197 removed, because it swallows every database error.'
        );
        assert_false(
            str_contains((string) $contents, 'CONTINUE HANDLER FOR SQLEXCEPTION'),
            $relativePath . ' has a catch-all CONTINUE HANDLER FOR SQLEXCEPTION — this would swallow a '
                . 'database error exactly as silently as the EXIT HANDLER #197 removed.'
        );
    });
}
