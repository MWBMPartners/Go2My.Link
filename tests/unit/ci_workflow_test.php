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
 * Added for #308 (PHP 8.4, 8.5 and 8.6): the required check names stay
 * exactly as they are, so PHP 8.5 and 8.6 run in their own jobs ("Backend
 * (PHP ...)" and "Tests (Integration, PHP ...)"). The 8.5 entries are a real
 * gate, the 8.6 entries are advisory until 8.6 is promoted, and sftp-deploy.yml
 * re-runs the lint and the unit tests on 8.4 and 8.5 before it deploys. The
 * rules sit in one function that takes the file texts, and a test breaks each
 * rule in memory to prove the function notices. Full-line comments are
 * dropped before the rules look at the text, so a comment cannot satisfy one.
 *
 * The 8.6 entries run inside our own Docker image (built from
 * tests/docker/Dockerfile) because the pinned setup-php action still treats
 * 8.6 as a nightly build. Each of the two newer-PHP jobs has an `in-image`
 * field (true for 8.6, false for 8.5); the image is built, its `php -v` is
 * printed and its version checked, and the gate steps run through
 * scripts/ci-php-run.sh, so none of them needs an `if:` of its own. The
 * rules below check all of that, and the script itself.
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

// ============================================================================
// 🐘 Several PHP versions without losing the required check names (#308)
// ============================================================================
// "Frontend (ubuntu-latest)" and "Backend (ubuntu-latest)" are required checks
// BY NAME. So PHP 8.5 and 8.6 are checked in their own jobs, and the
// 8.6 entries are advisory (a failure shows red but does not fail the run)
// until 8.6 is promoted, and run in our own image (the `in-image` field). The
// deploy workflow re-runs the checks on 8.4 and 8.5.
// These tests turn that design into rules. The rule checker takes the file
// texts as arguments, so the mutation test below can break each rule in memory
// and confirm the checker notices.

/**
 * Read .github/workflows/sftp-deploy.yml from the repository root.
 *
 * @return string
 */
function _g2mlCiWorkflowTestReadSftpFile(): string
{
    $path = dirname(__DIR__, 2) . '/.github/workflows/sftp-deploy.yml';
    $contents = file_get_contents($path);

    if ($contents === false)
    {
        throw new RuntimeException('Could not read ' . $path);
    }

    return $contents;
}

/**
 * Read scripts/ci-php-run.sh from the repository root. '' when it is missing,
 * so a missing script is reported as a rule problem.
 *
 * @return string
 */
function _g2mlCiWorkflowTestReadRunScript(): string
{
    $contents = file_get_contents(dirname(__DIR__, 2) . '/scripts/ci-php-run.sh');

    if ($contents === false)
    {
        return '';
    }

    return $contents;
}

/**
 * Extract a job block, or '' when the job does not exist (so a missing job is
 * reported as a rule problem instead of stopping the whole test).
 *
 * @param  string $workflowText
 * @param  string $jobKey
 * @return string
 */
function _g2mlCiWorkflowTestJobOrEmpty(string $workflowText, string $jobKey): string
{
    try
    {
        return _g2mlCiWorkflowTestExtractJobBlock($workflowText, $jobKey);
    }
    catch (RuntimeException $exception)
    {
        return '';
    }
}

/**
 * The text without its full-line comments (lines whose first character that
 * is not a space is "#"), so a rule cannot be satisfied by a comment.
 *
 * @param  string $workflowText
 * @return string
 */
function _g2mlCiWorkflowTestStripComments(string $workflowText): string
{
    $kept = array();

    foreach (preg_split('/\R/', $workflowText) as $line)
    {
        if (str_starts_with(ltrim($line, ' '), '#') === false)
        {
            $kept[] = $line;
        }
    }

    return implode("\n", $kept);
}

/**
 * Whether a job block runs this exact command: a line that is just the
 * command (inside a "run: |" block), or a "run: <command>" line.
 *
 * @param  string $jobBlock
 * @param  string $command
 * @return bool
 */
function _g2mlCiWorkflowTestRunsCommand(string $jobBlock, string $command): bool
{
    foreach (preg_split('/\R/', $jobBlock) as $line)
    {
        $trimmed = trim($line);

        if ($trimmed === $command || $trimmed === 'run: ' . $command)
        {
            return true;
        }
    }

    return false;
}

/**
 * The lines of one step: from its "- name: <stepName>" line to the next
 * "- name:" line, or the end of the job. '' when there is no such step.
 *
 * @param  string $jobBlock
 * @param  string $stepName
 * @return string
 */
function _g2mlCiWorkflowTestStepBlock(string $jobBlock, string $stepName): string
{
    $collected = array();
    $inside    = false;

    foreach (preg_split('/\R/', $jobBlock) as $line)
    {
        if (preg_match('/^      - name: /', $line) === 1)
        {
            $inside = ($line === '      - name: ' . $stepName);
        }

        if ($inside === true)
        {
            $collected[] = $line;
        }
    }

    return implode("\n", $collected);
}

/**
 * What stops a step from being a hard gate: continue-on-error: true, or an
 * "if:" key of its own. '' when the step is fine (or does not exist).
 *
 * @param  string $stepText
 * @return string
 */
function _g2mlCiWorkflowTestStepSwitchedOff(string $stepText): string
{
    if (preg_match('/^\s+continue-on-error:\s*true\s*$/m', $stepText) === 1)
    {
        return 'is continue-on-error: true';
    }

    if (preg_match('/^\s+if:/m', $stepText) === 1)
    {
        return 'has an if: of its own';
    }

    return '';
}

/**
 * The text of a job's own "if:" key, including any continuation lines (the
 * "if: >-" and "if: |" forms), or '' when the job has none.
 *
 * @param  string $jobBlock
 * @return string
 */
function _g2mlCiWorkflowTestJobIfText(string $jobBlock): string
{
    $collected = array();
    $inside    = false;

    foreach (preg_split('/\R/', $jobBlock) as $line)
    {
        $indent = strlen($line) - strlen(ltrim($line, ' '));

        if ($inside === false && preg_match('/^    if:/', $line) === 1)
        {
            $inside      = true;
            $collected[] = $line;
            continue;
        }

        if ($inside === true)
        {
            if (trim($line) === '' || $indent > 4)
            {
                $collected[] = $line;
            }
            else
            {
                break;
            }
        }
    }

    return implode("\n", $collected);
}

/**
 * Every broken rule in the workflow texts and the run script, as plain
 * sentences. An empty list means every rule holds.
 *
 * @param  string $ciText     Text of ci.yml.
 * @param  string $sftpText   Text of sftp-deploy.yml.
 * @param  string $scriptText Text of scripts/ci-php-run.sh.
 * @return array<int, string>
 */
function _g2mlCiWorkflowTestRuleProblems(string $ciText, string $sftpText, string $scriptText): array
{
    $problems   = array();
    $ciText     = _g2mlCiWorkflowTestStripComments($ciText);
    $sftpText   = _g2mlCiWorkflowTestStripComments($sftpText);
    $scriptText = _g2mlCiWorkflowTestStripComments($scriptText);
    $viaScript  = 'sh scripts/ci-php-run.sh ';

    // Rule 1: the two required-by-name jobs keep their names and matrix, and
    // have no PHP version in the matrix.
    $fixedJobs = array('frontend' => 'Frontend', 'backend' => 'Backend');

    foreach ($fixedJobs as $jobKey => $jobName)
    {
        $block = _g2mlCiWorkflowTestJobOrEmpty($ciText, $jobKey);

        if ($block === '')
        {
            $problems[] = 'ci.yml has no "' . $jobKey . '" job';
            continue;
        }

        if (preg_match('/^    name: ' . $jobName . '\s*$/m', $block) !== 1)
        {
            $problems[] = 'the "' . $jobKey . '" job is no longer named exactly "' . $jobName . '"';
        }

        if (str_contains($block, 'os: [ubuntu-latest]') === false)
        {
            $problems[] = 'the "' . $jobKey . '" job lost its os: [ubuntu-latest] matrix';
        }

        $strategy = array();

        if (preg_match('/^    strategy:(.*?)^    runs-on:/ms', $block, $strategy) !== 1 || str_contains($strategy[1], 'php-version'))
        {
            $problems[] = 'the "' . $jobKey . '" job has a php-version in its matrix, or no readable strategy block';
        }
    }

    // Rule 2: "Backend" stays on PHP 8.4.
    $backend = _g2mlCiWorkflowTestJobOrEmpty($ciText, 'backend');

    if (preg_match("/^\\s+php-version: '8\\.4'\\s*$/m", $backend) !== 1)
    {
        $problems[] = 'the "backend" job no longer sets php-version: \'8.4\'';
    }

    // Rule 3: both new jobs exist and are set up the same way.
    $newJobs = array('backend-newer-php' => 'Backend (PHP', 'tests-integration-newer-php' => 'Tests (Integration, PHP');

    foreach ($newJobs as $jobKey => $namePrefix)
    {
        $block = _g2mlCiWorkflowTestJobOrEmpty($ciText, $jobKey);

        if ($block === '')
        {
            $problems[] = 'ci.yml has no "' . $jobKey . '" job';
            continue;
        }

        if (preg_match('/^    name: ' . preg_quote($namePrefix, '/') . ' \$\{\{ matrix\.php-version \}\}\)\s*$/m', $block) !== 1)
        {
            $problems[] = 'the "' . $jobKey . '" job name must be "' . $namePrefix . ' ${{ matrix.php-version }})"';
        }

        if (preg_match("/- php-version: '8\\.5'\\s*\\n\\s*advisory: false\\s*\\n\\s*in-image: false\\s*\\n/", $block) !== 1)
        {
            $problems[] = 'the "' . $jobKey . '" job must list \'8.5\' with advisory: false and in-image: false';
        }

        if (preg_match("/- php-version: '8\\.5'\\s*\\n\\s*advisory: true/", $block) === 1)
        {
            $problems[] = 'the "' . $jobKey . '" job makes 8.5 advisory, so it could not block a merge';
        }

        if (preg_match("/- php-version: '8\\.6'\\s*\\n\\s*advisory: true\\s*\\n\\s*in-image: true\\s*\\n/", $block) !== 1)
        {
            $problems[] = 'the "' . $jobKey . '" job must list \'8.6\' with advisory: true and in-image: true';
        }

        if (preg_match('/^    continue-on-error: \$\{\{ matrix\.advisory \}\}\s*$/m', $block) !== 1)
        {
            $problems[] = 'the "' . $jobKey . '" job must set continue-on-error: ${{ matrix.advisory }}';
        }

        if (str_contains($block, 'php-version: ${{ matrix.php-version }}') === false)
        {
            $problems[] = 'the "' . $jobKey . '" job does not install the PHP version from its matrix';
        }
    }

    // Rule 3b: in both newer-PHP jobs, setup-php is skipped for the in-image
    // entry, and that entry builds our own image, shows and checks its PHP
    // version, and hands it to the later steps.
    foreach (array_keys($newJobs) as $jobKey)
    {
        $block = _g2mlCiWorkflowTestJobOrEmpty($ciText, $jobKey);

        if ($block === '')
        {
            continue;
        }

        $setupStep = _g2mlCiWorkflowTestStepBlock($block, 'Setup PHP');

        if (str_contains($setupStep, 'if: ${{ !matrix.in-image }}') === false)
        {
            $problems[] = 'the "' . $jobKey . '" job runs setup-php for the in-image entry too (the "Setup PHP" step needs if: ${{ !matrix.in-image }})';
        }

        // parallel-lint comes from setup-php, so the in-image entry has none (only the backend job has this step).
        if ($jobKey === 'backend-newer-php' && str_contains(_g2mlCiWorkflowTestStepBlock($block, 'PHP syntax lint'), 'if: ${{ !matrix.in-image }}') === false)
        {
            $problems[] = 'the "' . $jobKey . '" job runs the parallel-lint step for the in-image entry too (the "PHP syntax lint" step needs if: ${{ !matrix.in-image }})';
        }

        $buildStep = _g2mlCiWorkflowTestStepBlock($block, 'Build the PHP test image');

        if ($buildStep === '')
        {
            $problems[] = 'the "' . $jobKey . '" job has no "Build the PHP test image" step';
            continue;
        }

        $buildNeeds = array(
            'if: ${{ matrix.in-image }}'                                 => 'only run when matrix.in-image is true',
            'PHP_VERSION: ${{ matrix.php-version }}'                     => 'take the version from the matrix',
            '--build-arg "PHP_TAG=${PHP_VERSION}-cli"'                   => 'build with PHP_TAG=<version>-cli',
            '- < tests/docker/Dockerfile'                                => 'build from tests/docker/Dockerfile',
            'docker run --rm "${image}" php -v'                          => 'print php -v',
            'echo "G2ML_CI_IMAGE=${image}" >> "${GITHUB_ENV}"'           => 'hand the image to the later steps through G2ML_CI_IMAGE',
        );

        foreach ($buildNeeds as $needle => $meaning)
        {
            if (str_contains($buildStep, $needle) === false)
            {
                $problems[] = 'the "Build the PHP test image" step in the "' . $jobKey . '" job does not ' . $meaning;
            }
        }

        if (preg_match('/does not have PHP \$\{PHP_VERSION\}\."\s*\n\s*exit 1\b/', $buildStep) !== 1)
        {
            $problems[] = 'the "Build the PHP test image" step in the "' . $jobKey . '" job does not stop when the image has the wrong PHP version';
        }

        if (preg_match('/not a release\."\s*\n\s*exit 1\b/', $buildStep) !== 1)
        {
            $problems[] = 'the "Build the PHP test image" step in the "' . $jobKey . '" job does not stop when the image has a pre-release PHP';
        }
    }

    // Rule 3c: the gate steps of the newer-PHP jobs run through the script
    // (which picks the machine's PHP or the image), and PHPStan is still
    // downloaded and checked against its pinned hash on the runner.
    $viaCommands = array(
        'backend-newer-php'          => array(
            'PHP strict lint' => $viaScript . 'sh scripts/php-lint.sh',
            'PHPStan'         => $viaScript . 'php -d memory_limit=2G phpstan.phar analyse --configuration=phpstan.neon --no-progress',
            'PHP unit tests'  => $viaScript . 'php tests/run.php',
        ),
        'tests-integration-newer-php' => array(
            'PHP integration tests' => $viaScript . 'php tests/run_integration.php',
        ),
    );

    foreach ($viaCommands as $jobKey => $steps)
    {
        $block = _g2mlCiWorkflowTestJobOrEmpty($ciText, $jobKey);

        foreach ($steps as $stepName => $command)
        {
            if (_g2mlCiWorkflowTestRunsCommand(_g2mlCiWorkflowTestStepBlock($block, $stepName), $command) === false)
            {
                $problems[] = 'the "' . $stepName . '" step in the "' . $jobKey . '" job must run "' . $command . '"';
            }
        }
    }

    $phpstanStep = _g2mlCiWorkflowTestStepBlock(_g2mlCiWorkflowTestJobOrEmpty($ciText, 'backend-newer-php'), 'PHPStan');

    if (str_contains($phpstanStep, 'sha256sum --check --strict') === false || str_contains($phpstanStep, 'PHPSTAN_SHA256') === false)
    {
        $problems[] = 'the PHPStan step in the "backend-newer-php" job no longer checks the downloaded phar against its pinned hash';
    }

    // Rule 3d: the run script passes the command on, shares the network (so
    // the database service on 127.0.0.1 is reachable), mounts the checkout,
    // and falls back to the machine's own PHP.
    $scriptNeeds = array(
        '[ -n "${G2ML_CI_IMAGE-}" ]' => 'choose the image from G2ML_CI_IMAGE',
        '--network host'             => 'use --network host',
        '-v "${PWD}:/app" -w /app'   => 'mount the checkout at /app',
        '--user "$(id -u):$(id -g)"' => 'run as the caller\'s own user',
        '"${G2ML_CI_IMAGE}" "$@"'    => 'pass the command on inside the image',
        'G2ML_TEST_DB_HOST'          => 'pass the database settings on',
    );

    if ($scriptText === '')
    {
        $problems[] = 'scripts/ci-php-run.sh is missing';
    }
    else
    {
        foreach ($scriptNeeds as $needle => $meaning)
        {
            if (str_contains($scriptText, $needle) === false)
            {
                $problems[] = 'scripts/ci-php-run.sh does not ' . $meaning;
            }
        }

        if (preg_match('/^exec "\$@"\s*$/m', $scriptText) !== 1)
        {
            $problems[] = 'scripts/ci-php-run.sh does not fall back to running the command on the machine\'s own PHP';
        }
    }

    // Rule 4: the database job copies the service container, and the strict
    // lint runs in both PHP jobs.
    $databaseJob = _g2mlCiWorkflowTestJobOrEmpty($ciText, 'tests-integration-newer-php');

    if (str_contains($databaseJob, 'image: "mysql:8"') === false || str_contains($databaseJob, 'php tests/run_integration.php') === false)
    {
        $problems[] = 'the "tests-integration-newer-php" job lost its mysql:8 service or its integration test step';
    }

    foreach (array('backend', 'backend-newer-php') as $jobKey)
    {
        $block = _g2mlCiWorkflowTestJobOrEmpty($ciText, $jobKey);

        $lintCommand = 'sh scripts/php-lint.sh';

        if ($jobKey === 'backend-newer-php')
        {
            $lintCommand = $viaScript . $lintCommand;
        }

        if (str_contains($block, '- name: PHP strict lint') === false || _g2mlCiWorkflowTestRunsCommand($block, $lintCommand) === false)
        {
            $problems[] = 'the "' . $jobKey . '" job has no PHP strict lint step';
        }
    }

    // Rule 4b: those required steps cannot be switched off.
    $gates = array(
        array('backend', $backend, 'PHP strict lint'),
        array('backend', $backend, 'PHP unit tests'),
        array('backend-newer-php', _g2mlCiWorkflowTestJobOrEmpty($ciText, 'backend-newer-php'), 'PHP strict lint'),
        array('backend-newer-php', _g2mlCiWorkflowTestJobOrEmpty($ciText, 'backend-newer-php'), 'PHP unit tests'),
        array('backend-newer-php', _g2mlCiWorkflowTestJobOrEmpty($ciText, 'backend-newer-php'), 'PHPStan'),
        array('tests-integration-newer-php', _g2mlCiWorkflowTestJobOrEmpty($ciText, 'tests-integration-newer-php'), 'PHP integration tests'),
        array('checks', _g2mlCiWorkflowTestJobOrEmpty($sftpText, 'checks'), 'PHP strict lint'),
        array('checks', _g2mlCiWorkflowTestJobOrEmpty($sftpText, 'checks'), 'PHP unit tests'),
    );

    foreach ($gates as $gate)
    {
        $why = _g2mlCiWorkflowTestStepSwitchedOff(_g2mlCiWorkflowTestStepBlock($gate[1], $gate[2]));

        if ($why !== '')
        {
            $problems[] = 'the "' . $gate[2] . '" step in the "' . $gate[0] . '" job ' . $why . ', so it is no longer a gate';
        }
    }

    // Rule 5: the deploy workflow's checks run on 8.4 and 8.5, and the deploy
    // waits for them.
    $checks = _g2mlCiWorkflowTestJobOrEmpty($sftpText, 'checks');
    $deploy = _g2mlCiWorkflowTestJobOrEmpty($sftpText, 'deploy');

    if (preg_match("/^\\s+php-version:\\s*\\['8\\.4', '8\\.5'\\]\\s*$/m", $checks) !== 1)
    {
        $problems[] = 'sftp-deploy.yml "checks" must list PHP versions \'8.4\' and \'8.5\'';
    }

    if (_g2mlCiWorkflowTestRunsCommand($checks, 'sh scripts/php-lint.sh') === false)
    {
        $problems[] = 'sftp-deploy.yml "checks" does not run scripts/php-lint.sh';
    }

    if (_g2mlCiWorkflowTestRunsCommand($checks, 'php tests/run.php') === false)
    {
        $problems[] = 'sftp-deploy.yml "checks" does not run tests/run.php';
    }

    if (preg_match('/^    needs: checks\s*$/m', $deploy) !== 1)
    {
        $problems[] = 'sftp-deploy.yml "deploy" no longer has needs: checks';
    }

    if (preg_match('/(always|failure|cancelled)\(/', _g2mlCiWorkflowTestJobIfText($deploy)) === 1)
    {
        $problems[] = 'sftp-deploy.yml "deploy" uses a status function in its if:, so a failed check might not stop it';
    }

    return $problems;
}

test('the CI workflows keep the required check names and run PHP 8.4, 8.5 and 8.6 as designed (#308)', function (): void
{
    $problems = _g2mlCiWorkflowTestRuleProblems(_g2mlCiWorkflowTestReadFile(), _g2mlCiWorkflowTestReadSftpFile(), _g2mlCiWorkflowTestReadRunScript());

    assert_same(0, count($problems), implode(' | ', $problems));
});

test('workflow rules: each way of breaking the design is noticed', function (): void
{
    $ci     = _g2mlCiWorkflowTestReadFile();
    $sftp   = _g2mlCiWorkflowTestReadSftpFile();
    $script = _g2mlCiWorkflowTestReadRunScript();

    assert_same(0, count(_g2mlCiWorkflowTestRuleProblems($ci, $sftp, $script)), 'The real files must pass before mutating them');

    // Each entry: a label, which file to change, text to find, text to put there.
    $mutations = array(
        array('backend renamed',              'ci',   "  backend:\n    name: Backend\n", "  backend:\n    name: Backend (PHP 8.4)\n"),
        array('frontend renamed',             'ci',   "    name: Frontend\n", "    name: Frontend check\n"),
        array('backend matrix gets a PHP version', 'ci', "  backend:\n    name: Backend\n    strategy:\n      fail-fast: false\n      matrix:\n        os: [ubuntu-latest]\n", "  backend:\n    name: Backend\n    strategy:\n      fail-fast: false\n      matrix:\n        os: [ubuntu-latest]\n        php-version: ['8.4', '8.5']\n"),
        array('backend os matrix dropped',    'ci',   "  backend:\n    name: Backend\n    strategy:\n      fail-fast: false\n      matrix:\n        os: [ubuntu-latest]\n", "  backend:\n    name: Backend\n    strategy:\n      fail-fast: false\n      matrix:\n        os: [ubuntu-latest, macos-latest]\n"),
        array('backend moves off PHP 8.4',    'ci',   "          php-version: '8.4'\n          tools: parallel-lint\n", "          php-version: '8.5'\n          tools: parallel-lint\n"),
        array('jobs lose the strict lint',    'ci',   "      - name: PHP strict lint\n        run: |\n          sh scripts/php-lint.sh\n\n      - name: PHPStan\n", "      - name: PHPStan\n"),
        array('new job renamed',              'ci',   "    name: Backend (PHP \${{ matrix.php-version }})\n", "    name: Backend newer\n"),
        array('8.5 made advisory (both jobs)', 'ci',  "          - php-version: '8.5'\n            advisory: false\n", "          - php-version: '8.5'\n            advisory: true\n"),
        array('8.6 made required',            'ci',   "          - php-version: '8.6'\n            advisory: true\n", "          - php-version: '8.6'\n            advisory: false\n"),
        array('continue-on-error dropped',    'ci',   "    continue-on-error: \${{ matrix.advisory }}\n", "    continue-on-error: false\n"),
        array('database job loses its service', 'ci', "        image: \"mysql:8\"\n        env:\n          MYSQL_ROOT_PASSWORD: \"g2ml_ci_root_password\"\n        ports:\n          - \"3306:3306\"\n        options: >-\n          --health-cmd=\"mysqladmin ping --silent\"\n          --health-interval=10s\n          --health-timeout=5s\n          --health-retries=10\n\n    steps:\n      - name: Checkout code\n        uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1  # v7.0.1\n\n      - name: Setup PHP\n        if: \${{ !matrix.in-image }}\n        uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240  # v2.37.2\n        with:\n          php-version: \${{ matrix.php-version }}", "        image: \"mysql:9\"\n\n    steps:\n      - name: Checkout code\n        uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1  # v7.0.1\n\n      - name: Setup PHP\n        if: \${{ !matrix.in-image }}\n        uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240  # v2.37.2\n        with:\n          php-version: \${{ matrix.php-version }}"),
        array('deploy checks drop 8.5',       'sftp', "php-version: ['8.4', '8.5']", "php-version: ['8.4']"),
        array('deploy checks drop the strict lint', 'sftp', "          sh scripts/php-lint.sh\n", "          echo skipped\n"),
        array('deploy checks drop the unit tests', 'sftp', "          php tests/run.php\n", "          echo skipped\n"),
        array('deploy stops waiting',         'sftp', "    needs: checks\n", "    needs: []\n"),
        array('deploy ignores failed checks', 'sftp', "    if: vars.SFTP_ENABLED == 'true'\n", "    if: always() && vars.SFTP_ENABLED == 'true'\n"),
        array('deploy ignores failed checks (multi-line if)', 'sftp', "    if: vars.SFTP_ENABLED == 'true'\n", "    if: >-\n      always() &&\n      vars.SFTP_ENABLED == 'true'\n"),
        array('strict lint made continue-on-error (ci)', 'ci', "      - name: PHP strict lint\n        run: |\n", "      - name: PHP strict lint\n        continue-on-error: true\n        run: |\n"),
        array('strict lint made continue-on-error (deploy)', 'sftp', "      - name: PHP strict lint\n        run: |\n", "      - name: PHP strict lint\n        continue-on-error: true\n        run: |\n"),
        array('deploy unit tests given an if:', 'sftp', "      - name: PHP unit tests\n        run: |\n", "      - name: PHP unit tests\n        if: false\n        run: |\n"),
        array('deploy strict lint given an if:', 'sftp', "      - name: PHP strict lint\n        run: |\n", "      - name: PHP strict lint\n        if: false\n        run: |\n"),
        array('strict lint only in a comment (deploy)', 'sftp', "          sh scripts/php-lint.sh\n", "          # sh scripts/php-lint.sh\n"),
        array('strict lint only in a comment (ci)', 'ci', "          sh scripts/php-lint.sh\n", "          # sh scripts/php-lint.sh\n"),
        array('unit tests only in a comment (deploy)', 'sftp', "          php tests/run.php\n", "          # php tests/run.php\n"),
        array('8.5 only in a comment (deploy)', 'sftp', "        php-version: ['8.4', '8.5']\n", "        # php-version: ['8.4', '8.5']\n        php-version: ['8.4']\n"),
        // The 8.6 entries run in our own image (the in-image rules).
        array('8.6 loses in-image',           'ci',   "            advisory: true\n            in-image: true\n", "            advisory: true\n            in-image: false\n"),
        array('8.5 given in-image',           'ci',   "            advisory: false\n            in-image: false\n", "            advisory: false\n            in-image: true\n"),
        array('in-image field dropped',       'ci',   "            advisory: true\n            in-image: true\n", "            advisory: true\n"),
        array('setup-php no longer skipped for 8.6', 'ci', "        if: \${{ !matrix.in-image }}\n        uses: shivammathur/setup-php", "        uses: shivammathur/setup-php"),
        array('image build step renamed',     'ci',   "      - name: Build the PHP test image\n", "      - name: Build something\n"),
        array('image build step made unconditional', 'ci', "        if: \${{ matrix.in-image }}\n", "        if: true\n"),
        array('image not built from the Dockerfile', 'ci', "- < tests/docker/Dockerfile", "- < tests/docker/Other"),
        array('image built without the PHP tag', 'ci', "--build-arg \"PHP_TAG=\${PHP_VERSION}-cli\"", "--build-arg \"PHP_TAG=8.4-cli\""),
        array('php -v no longer printed',     'ci',   "          docker run --rm \"\${image}\" php -v\n", ""),
        array('wrong PHP version no longer stops the leg', 'ci', "              echo \"The image does not have PHP \${PHP_VERSION}.\"\n              exit 1\n", "              echo \"The image does not have PHP \${PHP_VERSION}.\"\n"),
        array('pre-release PHP no longer stops the leg', 'ci', "              echo \"The image has a test (pre-release) build of PHP, not a release.\"\n              exit 1\n", "              echo \"The image has a test (pre-release) build of PHP, not a release.\"\n"),
        array('image not handed to later steps', 'ci', "          echo \"G2ML_CI_IMAGE=\${image}\" >> \"\${GITHUB_ENV}\"\n", "          echo built\n"),
        array('lint no longer through the script', 'ci', "          sh scripts/ci-php-run.sh sh scripts/php-lint.sh\n", "          sh scripts/php-lint.sh\n"),
        array('PHPStan no longer through the script', 'ci', "          sh scripts/ci-php-run.sh php -d memory_limit=2G phpstan.phar", "          php -d memory_limit=2G phpstan.phar"),
        array('unit tests no longer through the script', 'ci', "          sh scripts/ci-php-run.sh php tests/run.php\n", "          php tests/run.php\n"),
        array('integration tests no longer through the script', 'ci', "          sh scripts/ci-php-run.sh php tests/run_integration.php\n", "          php tests/run_integration.php\n"),
        array('PHPStan hash check dropped (newer job)', 'ci', "          echo \"\${PHPSTAN_SHA256}  phpstan.phar\" | sha256sum --check --strict\n          chmod +x phpstan.phar\n          sh scripts/ci-php-run.sh", "          chmod +x phpstan.phar\n          sh scripts/ci-php-run.sh"),
        array('newer strict lint given an if:', 'ci', "      - name: PHP strict lint\n        run: |\n          sh scripts/ci-php-run.sh", "      - name: PHP strict lint\n        if: \${{ !matrix.in-image }}\n        run: |\n          sh scripts/ci-php-run.sh"),
        array('newer PHPStan given an if:',   'ci',   "      - name: PHPStan\n        continue-on-error: false\n        env:\n          PHPSTAN_VERSION: \"2.2.4\"\n          PHPSTAN_SHA256: \"f1e81a1418faab14a057188646cf1220f066276fc640ce9ef1c9a4af3f0ed46b\"\n        run: |\n          curl -sL \"https://github.com/phpstan/phpstan/releases/download/\${PHPSTAN_VERSION}/phpstan.phar\" -o phpstan.phar\n          echo \"\${PHPSTAN_SHA256}  phpstan.phar\" | sha256sum --check --strict\n          chmod +x phpstan.phar\n          sh scripts/ci-php-run.sh", "      - name: PHPStan\n        if: false\n        continue-on-error: false\n        env:\n          PHPSTAN_VERSION: \"2.2.4\"\n          PHPSTAN_SHA256: \"f1e81a1418faab14a057188646cf1220f066276fc640ce9ef1c9a4af3f0ed46b\"\n        run: |\n          curl -sL \"https://github.com/phpstan/phpstan/releases/download/\${PHPSTAN_VERSION}/phpstan.phar\" -o phpstan.phar\n          echo \"\${PHPSTAN_SHA256}  phpstan.phar\" | sha256sum --check --strict\n          chmod +x phpstan.phar\n          sh scripts/ci-php-run.sh"),
        array('newer unit tests given an if:', 'ci',  "        run: |\n          sh scripts/ci-php-run.sh php tests/run.php\n", "        if: \${{ !matrix.in-image }}\n        run: |\n          sh scripts/ci-php-run.sh php tests/run.php\n"),
        array('newer integration tests given an if:', 'ci', "      - name: PHP integration tests\n        env:\n          G2ML_TEST_DB_HOST: \"127.0.0.1\"\n          G2ML_TEST_DB_PORT: \"3306\"\n          G2ML_TEST_DB_NAME: \"mwtools_Go2MyLink\"\n          G2ML_TEST_DB_USER: \"root\"\n          G2ML_TEST_DB_PASS: \"g2ml_ci_root_password\"\n        run: |\n          sh scripts/ci-php-run.sh", "      - name: PHP integration tests\n        if: false\n        env:\n          G2ML_TEST_DB_HOST: \"127.0.0.1\"\n          G2ML_TEST_DB_PORT: \"3306\"\n          G2ML_TEST_DB_NAME: \"mwtools_Go2MyLink\"\n          G2ML_TEST_DB_USER: \"root\"\n          G2ML_TEST_DB_PASS: \"g2ml_ci_root_password\"\n        run: |\n          sh scripts/ci-php-run.sh"),
        // The run script itself.
        array('script loses --network host',  'script', "--network host", "--network bridge"),
        array('script stops passing the command on', 'script', "\"\${G2ML_CI_IMAGE}\" \"\$@\"", "\"\${G2ML_CI_IMAGE}\""),
        array('script no longer mounts the checkout', 'script', "-v \"\${PWD}:/app\" -w /app", "-w /app"),
        array('script no longer reads G2ML_CI_IMAGE', 'script', "[ -n \"\${G2ML_CI_IMAGE-}\" ]", "[ -n \"\${OTHER_IMAGE-}\" ]"),
        array('script no longer passes the database settings', 'script', "-e G2ML_TEST_DB_HOST ", ""),
        array('script has no fallback to the machine\'s PHP', 'script', "\nexec \"\$@\"\n", "\n"),
        array('script loses --user',          'script', "--user \"\$(id -u):\$(id -g)\"", "--user root"),
        array('syntax lint no longer skipped for 8.6', 'ci', "      - name: PHP syntax lint\n        if: \${{ !matrix.in-image }}\n", "      - name: PHP syntax lint\n"),
    );

    foreach ($mutations as $mutation)
    {
        $label      = $mutation[0];
        $ciText     = $ci;
        $sftpText   = $sftp;
        $scriptText = $script;

        $originals = array('ci' => $ci, 'sftp' => $sftp, 'script' => $script);
        $original  = $originals[$mutation[1]];

        assert_true(
            str_contains($original, $mutation[2]),
            'Mutation "' . $label . '" no longer matches the file text, so it proves nothing; update it'
        );

        // Text that appears in both newer-PHP jobs is changed in every copy; that is the point.
        $changed = str_replace($mutation[2], $mutation[3], $original);

        if ($mutation[1] === 'ci')
        {
            $ciText = $changed;
        }
        elseif ($mutation[1] === 'sftp')
        {
            $sftpText = $changed;
        }
        else
        {
            $scriptText = $changed;
        }

        assert_true(
            count(_g2mlCiWorkflowTestRuleProblems($ciText, $sftpText, $scriptText)) > 0,
            'Breaking the design in this way was not noticed: ' . $label
        );
    }

    // A missing script file reads as an empty text and must be reported.
    assert_true(
        count(_g2mlCiWorkflowTestRuleProblems($ci, $sftp, '')) > 0,
        'A missing scripts/ci-php-run.sh was not noticed'
    );
});
