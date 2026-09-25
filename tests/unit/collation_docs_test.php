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
 * 🧪 Check — the required collation is written into the developer docs (#196)
 * ============================================================================
 *
 * Owner decision 32 (#243): the required database collation
 * (utf8mb4_unicode_ci) must be stated in DEV_NOTES.md and the other
 * developer-facing documents, not just enforced in code — so a developer
 * reading the docs before touching the database learns the rule, rather
 * than only finding out from a failed check.
 *
 * This is a plain substring search on each file, not a check of the prose
 * around it — it proves the fact is WRITTEN somewhere in each file, not that
 * it is explained well. That is a judgement call for review, not something
 * a mechanical check can make.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #196
 * ============================================================================
 */

declare(strict_types=1);

/**
 * The developer-facing documents the SX-196 plan lists for decision 32,
 * each relative to the repository root.
 *
 * @return array<int, string>
 */
function _g2mlCollationDocsTestFiles(): array
{
    return array(
        'DEV_NOTES.md',
        'README.md',
        'docs/DATABASE.md',
        'docs/INSTALL.md',
        'docs/DEPLOYMENT.md',
        '.claude/memory/patterns.md',
    );
}

foreach (_g2mlCollationDocsTestFiles() as $relativePath)
{
    test('states the required collation utf8mb4_unicode_ci: ' . $relativePath . ' (#196)', function () use ($relativePath): void
    {
        $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;

        assert_true(is_file($fullPath), $relativePath . ' does not exist');

        $contents = file_get_contents($fullPath);

        assert_true($contents !== false, 'Could not read ' . $relativePath);
        assert_true(
            str_contains((string) $contents, 'utf8mb4_unicode_ci'),
            $relativePath . ' does not mention utf8mb4_unicode_ci — decision 32 (#243) requires the '
                . 'required collation to be stated in the developer documentation, not just enforced in code.'
        );
    });
}
