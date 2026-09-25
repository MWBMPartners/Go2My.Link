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
 * 🧪 Check — the integration test job in ci.yml stayed fixed (#196)
 * ============================================================================
 *
 * This is a plain text check — no YAML library — reading
 * .github/workflows/ci.yml and confirming a future edit cannot silently undo
 * the two #196 fixes, or the matrix that gives the check its name:
 *
 *   1. The MySQL service container must not be given MYSQL_DATABASE (that
 *      setting made the container pre-create the database with the wrong
 *      collation, before our own schema file could).
 *   2. The "Tests (Integration)" job — and every step inside it — must not
 *      be continue-on-error: true (that is what let 25 failing tests report
 *      as a green build for weeks).
 *   3. The job must keep its single-value "os: [ubuntu-latest]" matrix,
 *      which is what makes GitHub name the check
 *      "Tests (Integration) (ubuntu-latest)" — the exact name
 *      docs/DEPLOYMENT.md tells the owner to add as a required status check.
 *
 * WHAT THIS CHECK CANNOT DO. It is a substring search, not a YAML parser, so
 * it cannot tell a real "continue-on-error: true" apart from one written
 * inside a comment or a quoted string in ci.yml. Today's file has neither.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #196
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Read .github/workflows/ci.yml from the repository root.
 *
 * @return string
 */
function _g2mlCiWorkflowTestReadFile(): string
{
    $path = dirname(__DIR__, 2) . '/.github/workflows/ci.yml';
    $contents = file_get_contents($path);

    if ($contents === false)
    {
        throw new RuntimeException('Could not read ' . $path);
    }

    return $contents;
}

/**
 * Extract the text of one top-level GitHub Actions job block: from its own
 * "  <jobKey>:" line (two-space indent, the level `jobs:` entries sit at) up
 * to — but not including — the next line at that same two-space indent that
 * looks like another job key, or the end of the file if this is the last
 * job. Nested content (steps, env blocks, run scripts) sits at four or more
 * spaces of indent, so it is never mistaken for the start of the next job.
 *
 * @param  string $workflowText
 * @param  string $jobKey
 * @return string
 */
function _g2mlCiWorkflowTestExtractJobBlock(string $workflowText, string $jobKey): string
{
    $startPattern = '/^  ' . preg_quote($jobKey, '/') . ':\s*$/m';

    if (preg_match($startPattern, $workflowText, $startMatch, PREG_OFFSET_CAPTURE) !== 1)
    {
        throw new RuntimeException('Job "' . $jobKey . '" was not found in the workflow file.');
    }

    $blockStart = $startMatch[0][1];
    $afterStart = $blockStart + strlen($startMatch[0][0]);

    // Look for the next top-level job key AFTER this one's own line.
    $nextJobPattern = '/^  [A-Za-z0-9_-]+:\s*$/m';
    $remainingText  = substr($workflowText, $afterStart);

    if (preg_match($nextJobPattern, $remainingText, $nextMatch, PREG_OFFSET_CAPTURE) === 1)
    {
        $blockEnd = $afterStart + $nextMatch[0][1];

        return substr($workflowText, $blockStart, $blockEnd - $blockStart);
    }

    // This is the last job in the file.
    return substr($workflowText, $blockStart);
}

// ============================================================================
// 🏃 The checks themselves.
// ============================================================================

test('ci.yml never gives the MySQL service container MYSQL_DATABASE (#196)', function (): void
{
    $workflowText = _g2mlCiWorkflowTestReadFile();

    assert_false(
        str_contains($workflowText, 'MYSQL_DATABASE'),
        'ci.yml sets MYSQL_DATABASE — this makes the container pre-create the database with the wrong '
            . 'collation before web/_sql/schema/000_create_database.sql can (#196).'
    );
});

test('the "Tests (Integration)" job in ci.yml has no continue-on-error: true (#196)', function (): void
{
    $workflowText = _g2mlCiWorkflowTestReadFile();
    $jobBlock     = _g2mlCiWorkflowTestExtractJobBlock($workflowText, 'tests-integration');

    assert_false(
        str_contains($jobBlock, 'continue-on-error: true'),
        'The "Tests (Integration)" job (or a step inside it) is still continue-on-error: true — a real '
            . 'failure would once again report as a green build (#196).'
    );
});

test('the "Tests (Integration)" job keeps its single-value os: [ubuntu-latest] matrix (#196)', function (): void
{
    $workflowText = _g2mlCiWorkflowTestReadFile();
    $jobBlock     = _g2mlCiWorkflowTestExtractJobBlock($workflowText, 'tests-integration');

    assert_true(
        str_contains($jobBlock, 'os: [ubuntu-latest]'),
        'The "Tests (Integration)" job no longer states a single-value os: [ubuntu-latest] matrix — '
            . 'without it GitHub would not name the check "Tests (Integration) (ubuntu-latest)", the '
            . 'exact name docs/DEPLOYMENT.md tells the owner to make required.'
    );
});

// ============================================================================
// 🧪 Self-tests for the block extractor, so a change to it cannot silently
// make the checks above pass for the wrong reason.
// ============================================================================

test('job block extractor: pulls only the named job, not its neighbours', function (): void
{
    $fixture = "jobs:\n  frontend:\n    name: Frontend\n    continue-on-error: true\n  backend:\n    name: Backend\n  tests-integration:\n    name: Tests (Integration)\n    os: [ubuntu-latest]\n";

    $block = _g2mlCiWorkflowTestExtractJobBlock($fixture, 'backend');

    assert_true(str_contains($block, 'name: Backend'), 'The named job\'s own content is missing');
    assert_false(str_contains($block, 'continue-on-error: true'), 'The extractor bled in the PREVIOUS job\'s content');
    assert_false(str_contains($block, 'Tests (Integration)'), 'The extractor bled in the NEXT job\'s content');
});

test('job block extractor: the last job in the file runs to the end', function (): void
{
    $fixture = "jobs:\n  frontend:\n    name: Frontend\n  tests-integration:\n    name: Tests (Integration)\n    os: [ubuntu-latest]\n";

    $block = _g2mlCiWorkflowTestExtractJobBlock($fixture, 'tests-integration');

    assert_true(str_contains($block, 'os: [ubuntu-latest]'), 'The last job\'s own content is missing');
});
