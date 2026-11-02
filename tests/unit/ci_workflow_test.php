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
 * 🧪 Check — the CI workflows stay fixed: the integration job (#196) and
 *    the Node version every workflow installs (#302)
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
 * A fourth check, added for #302, reads every actions/setup-node step in
 * every workflow file (.yml and .yaml) and fails unless it asks for a plain
 * major version of 24 or later with check-latest: true. CVE-2026-111497, the
 * Brotli decoder bug, is not fixed in Node 20; a matrix value or a version
 * file cannot be checked from here, so those are refused too.
 *
 * WHAT THIS CHECK CANNOT DO. It is a substring search, not a YAML parser, so
 * it cannot tell a real "continue-on-error: true" apart from one written
 * inside a comment or a quoted string in ci.yml. Today's file has neither.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      #196 (Node version check: #302)
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

// ============================================================================
// 🟢 Every workflow that installs Node uses a release with the Brotli fix (#302)
// ============================================================================
// CVE-2026-111497 ("HTTP2Shell") is a bug in Node's built-in Brotli decoder.
// Node 20 and older get no fix; 24.22.1 is the first fixed 24.x. Each
// actions/setup-node step must ask for a plain major version of 24 or later
// and set check-latest: true, so setup-node fetches the newest release of
// that line instead of reusing an older copy cached on the runner.

/**
 * The lines of each actions/setup-node step in a workflow file: from its
 * "uses:" line down to the first later line that is indented less than the
 * "uses:" key (the next step, or the end of the job).
 *
 * @param  string $workflowText
 * @return array<int, array<int, string>>
 */
function _g2mlCiWorkflowTestSetupNodeSteps(string $workflowText): array
{
    $lines = preg_split('/\R/', $workflowText);
    $steps = array();
    $count = count($lines);

    for ($index = 0; $index < $count; $index = $index + 1)
    {
        $usesColumn = strpos($lines[$index], 'uses:');

        if ($usesColumn === false || preg_match('/uses:\s*[\'"]?actions\/setup-node@/i', $lines[$index]) !== 1)
        {
            continue;
        }

        $stepLines = array($lines[$index]);

        for ($next = $index + 1; $next < $count; $next = $next + 1)
        {
            $line = $lines[$next];

            if (trim($line) === '')
            {
                continue;
            }

            if (strlen($line) - strlen(ltrim($line, ' ')) < $usesColumn)
            {
                break;
            }

            $stepLines[] = $line;
        }

        $steps[] = $stepLines;
    }

    return $steps;
}

/**
 * What is wrong with one setup-node step, or '' when it is fine.
 *
 * @param  array<int, string> $stepLines
 * @return string
 */
function _g2mlCiWorkflowTestSetupNodeProblem(array $stepLines): string
{
    $stepText = implode("\n", $stepLines);
    $version  = array();

    if (preg_match("/^\\s*node-version:\\s*['\"]?([0-9]+)(\\.x)?['\"]?\\s*$/m", $stepText, $version) !== 1)
    {
        return 'does not ask for a plain major version (node-version: \'24\')';
    }

    if ((int) $version[1] < 24)
    {
        return 'asks for Node ' . $version[1];
    }

    if (preg_match('/^\s*check-latest:\s*true\s*$/m', $stepText) !== 1)
    {
        return 'has no check-latest: true';
    }

    return '';
}

test('every setup-node step asks for Node 24 or later, with check-latest (#302)', function (): void
{
    $workflowFiles = array_merge(
        (array) glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml'),
        (array) glob(dirname(__DIR__, 2) . '/.github/workflows/*.yaml')
    );
    $problems   = array();
    $nodeSetups = 0;

    assert_true(count($workflowFiles) > 0, 'No workflow files were found');

    foreach ($workflowFiles as $workflowFile)
    {
        foreach (_g2mlCiWorkflowTestSetupNodeSteps((string) file_get_contents($workflowFile)) as $stepLines)
        {
            $nodeSetups = $nodeSetups + 1;
            $problem    = _g2mlCiWorkflowTestSetupNodeProblem($stepLines);

            if ($problem !== '')
            {
                $problems[] = basename($workflowFile) . ': a setup-node step ' . $problem;
            }
        }
    }

    assert_true($nodeSetups > 0, 'No workflow installs Node any more; update this check');
    assert_same(0, count($problems), implode(' | ', $problems));
});

test('setup-node check: the ways a step could slip back to an unfixed Node are refused', function (): void
{
    $good = array('      - name: Setup Node.js', '        uses: actions/setup-node@abc', '        with:', "          node-version: '24'", '          check-latest: true');
    assert_same('', _g2mlCiWorkflowTestSetupNodeProblem($good));

    $old = $good;
    $old[3] = "          node-version: '20'";
    assert_same('asks for Node 20', _g2mlCiWorkflowTestSetupNodeProblem($old));

    $matrix = $good;
    $matrix[3] = '          node-version: ${{ matrix.node }}';
    assert_true(_g2mlCiWorkflowTestSetupNodeProblem($matrix) !== '', 'A matrix value cannot be checked, so it is refused');

    $range = $good;
    $range[3] = "          node-version: '>=18'";
    assert_true(_g2mlCiWorkflowTestSetupNodeProblem($range) !== '', 'A range is refused');

    $noLatest = $good;
    unset($noLatest[4]);
    assert_same('has no check-latest: true', _g2mlCiWorkflowTestSetupNodeProblem($noLatest));

    // A quoted "uses:" value is still found.
    $quoted = _g2mlCiWorkflowTestSetupNodeSteps("      - uses: \"actions/setup-node@abc\"\n        with:\n          node-version: '20'\n");
    assert_same(1, count($quoted), 'A quoted uses: value is found');
    assert_same('asks for Node 20', _g2mlCiWorkflowTestSetupNodeProblem($quoted[0]));

    $noInputs = array('      - uses: actions/setup-node@abc');
    assert_true(_g2mlCiWorkflowTestSetupNodeProblem($noInputs) !== '', 'A step with no node-version is refused');

    // check-latest on a different action in the same job does not count.
    $workflow = implode("\n", array(
        '    steps:',
        '      - uses: actions/setup-node@abc',
        '        with:',
        "          node-version: '24'",
        '      - uses: actions/setup-python@def',
        '        with:',
        '          check-latest: true',
    ));
    $steps = _g2mlCiWorkflowTestSetupNodeSteps($workflow);
    assert_same(1, count($steps), 'One setup-node step');
    assert_same('has no check-latest: true', _g2mlCiWorkflowTestSetupNodeProblem($steps[0]));
});
