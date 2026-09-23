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
 * 🧪 Unit tests — the build programme plan matches its schema
 * ============================================================================
 *
 * File: tests/unit/build_plan_schema_test.php
 *
 * WHAT THIS CHECKS
 *
 * `.claude/programme/build-plan.json` is the machine-readable plan for the
 * build programme agreed with the owner on 2026-09-21/22 (the decisions
 * themselves are GitHub issue #243). A build run reads it to find each item's
 * plan, so a typo in it sends a builder off with the wrong instructions.
 * `.claude/programme/build-plan.schema.json` describes the file's shape.
 *
 * The house rule is that every JSON file the project keeps gets a schema, and
 * that the schema is wired into something that actually runs — "a schema
 * nothing ever runs is a document, not a check". This test is that wiring.
 *
 * WHAT THIS CANNOT DO, said plainly
 *
 * It is NOT a full JSON Schema validator. It reads the schema file and checks
 * the parts that would really hurt if they were wrong: the required keys at
 * the top level, the required fields and the enumerated values on every item,
 * the shape of the build order, and the cross-references between the three
 * (every key in the build order exists as an item; every item has a GitHub
 * issue number). Full validation against the whole schema waits for the
 * separate job of wiring `web/_functions/json_validator.php` into the test
 * suite, which is tracked in issue #78 — this test deliberately does not
 * duplicate that work, and it says so here rather than implying more coverage
 * than it has.
 *
 * It also cannot tell whether a plan is a GOOD plan. That is what the review
 * loop is for.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      2026-09-23
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// Helpers
// ============================================================================

/**
 * Reads a JSON file from the repository root and decodes it to an array.
 *
 * Returns null when the file is missing or is not valid JSON, so each test
 * can say which of the two went wrong rather than dying on a type error.
 *
 * @param  string $relativePath Path relative to the repository root.
 * @return array<string, mixed>|null
 */
function g2ml_test_read_json_file(string $relativePath): ?array
{
    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;

    if (!is_file($fullPath))
    {
        return null;
    }

    $raw = file_get_contents($fullPath);

    if ($raw === false)
    {
        return null;
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded))
    {
        return null;
    }

    return $decoded;
}

// ============================================================================
// Tests
// ============================================================================

test('build plan: the plan file and its schema are both present and valid JSON', function (): void
{
    $plan   = g2ml_test_read_json_file('.claude/programme/build-plan.json');
    $schema = g2ml_test_read_json_file('.claude/programme/build-plan.schema.json');

    assert_true($plan !== null, 'build-plan.json is missing or is not valid JSON');
    assert_true($schema !== null, 'build-plan.schema.json is missing or is not valid JSON');
});

test('build plan: every key the schema marks as required at the top level is present', function (): void
{
    $plan   = g2ml_test_read_json_file('.claude/programme/build-plan.json');
    $schema = g2ml_test_read_json_file('.claude/programme/build-plan.schema.json');

    if ($plan === null || $schema === null)
    {
        assert_true(false, 'plan or schema could not be read');

        return;
    }

    $required = $schema['required'] ?? [];

    assert_true(count($required) > 0, 'the schema lists no required top-level keys, which cannot be right');

    foreach ($required as $key)
    {
        assert_true(array_key_exists($key, $plan), 'the plan is missing the required top-level key: ' . $key);
    }
});

test('build plan: every item has the fields the schema requires, and allowed values only', function (): void
{
    $plan   = g2ml_test_read_json_file('.claude/programme/build-plan.json');
    $schema = g2ml_test_read_json_file('.claude/programme/build-plan.schema.json');

    if ($plan === null || $schema === null)
    {
        assert_true(false, 'plan or schema could not be read');

        return;
    }

    $itemSchema        = $schema['properties']['items']['additionalProperties'] ?? [];
    $requiredFields    = $itemSchema['required'] ?? [];
    $allowedTiers      = $itemSchema['properties']['builder_tier']['enum'] ?? [];
    $allowedCommitType = $itemSchema['properties']['commit_type']['enum'] ?? [];

    assert_true(count($requiredFields) > 0, 'the schema lists no required fields for an item');
    assert_true(count($allowedTiers) > 0, 'the schema lists no allowed builder tiers');

    $items = $plan['items'] ?? [];

    assert_true(count($items) > 0, 'the plan contains no items');

    foreach ($items as $key => $item)
    {
        foreach ($requiredFields as $field)
        {
            assert_true(array_key_exists($field, $item), 'item ' . $key . ' is missing the required field: ' . $field);
        }

        if (array_key_exists('builder_tier', $item))
        {
            assert_true(
                in_array($item['builder_tier'], $allowedTiers, true),
                'item ' . $key . ' has a builder tier the schema does not allow: ' . (string) $item['builder_tier']
            );
        }

        if (array_key_exists('commit_type', $item))
        {
            assert_true(
                in_array($item['commit_type'], $allowedCommitType, true),
                'item ' . $key . ' has a commit type the schema does not allow: ' . (string) $item['commit_type']
            );
        }

        // A plan a builder cannot act on is worse than no plan: it looks like
        // instructions and is not. A handful of characters is never enough.
        if (array_key_exists('plan', $item))
        {
            assert_true(
                strlen((string) $item['plan']) > 200,
                'item ' . $key . ' has a plan too short to build from'
            );
        }

        if (array_key_exists('acceptance_criteria', $item))
        {
            assert_true(
                count($item['acceptance_criteria']) > 0,
                'item ' . $key . ' has no acceptance criteria, so nothing says when it is finished'
            );
        }
    }
});

test('build plan: the build order and the items agree with each other', function (): void
{
    $plan = g2ml_test_read_json_file('.claude/programme/build-plan.json');

    if ($plan === null)
    {
        assert_true(false, 'plan could not be read');

        return;
    }

    $items   = $plan['items'] ?? [];
    $order   = $plan['build_order'] ?? [];
    $issueOf = $plan['issue_of'] ?? [];

    assert_true(count($order) > 0, 'the plan has no build order');

    $keysInOrder = [];

    foreach ($order as $batch)
    {
        assert_true(array_key_exists('batch', $batch), 'a batch has no name');
        assert_true(array_key_exists('keys', $batch), 'a batch has no keys');
        assert_true(count($batch['keys']) > 0, 'batch ' . (string) ($batch['batch'] ?? '?') . ' has no items in it');

        foreach ($batch['keys'] as $key)
        {
            assert_true(
                array_key_exists($key, $items),
                'the build order names ' . $key . ', which is not an item in the plan'
            );

            assert_false(
                in_array($key, $keysInOrder, true),
                $key . ' appears more than once in the build order'
            );

            $keysInOrder[] = $key;
        }
    }

    // Every item must be either somewhere in the build order or already
    // recorded as finished, or it would simply never be built and nobody would
    // notice. (An item can be finished without ever appearing in the order:
    // LP-10 and LP-12 were already being built when this order was written.)
    $finishedKeys = [];

    foreach ($plan['progress']['done'] ?? [] as $entry)
    {
        $finishedKeys[] = (string) ($entry['key'] ?? '');
    }

    foreach ($items as $key => $item)
    {
        assert_true(
            in_array($key, $keysInOrder, true) || in_array((string) $key, $finishedKeys, true),
            'item ' . $key . ' is in the plan but neither in the build order nor recorded as finished, so it would never be built'
        );
    }

    // Every item needs a GitHub issue: that issue is where its plan is posted
    // and where the work is recorded.
    foreach ($items as $key => $item)
    {
        assert_true(
            array_key_exists($key, $issueOf) && (int) $issueOf[$key] > 0,
            'item ' . $key . ' has no GitHub issue number in issue_of'
        );
    }
});

test('build plan: each item depends only on items that exist', function (): void
{
    $plan = g2ml_test_read_json_file('.claude/programme/build-plan.json');

    if ($plan === null)
    {
        assert_true(false, 'plan could not be read');

        return;
    }

    $items = $plan['items'] ?? [];

    foreach ($items as $key => $item)
    {
        $dependencies = $item['depends_on'] ?? [];

        foreach ($dependencies as $dependency)
        {
            assert_true(
                array_key_exists($dependency, $items),
                'item ' . $key . ' says it depends on ' . $dependency . ', which is not an item in the plan'
            );
        }
    }
});

test('build plan: the progress section names real items and real-looking commits', function (): void
{
    $plan = g2ml_test_read_json_file('.claude/programme/build-plan.json');

    if ($plan === null)
    {
        assert_true(false, 'plan could not be read');

        return;
    }

    // The progress section is optional in the schema, because a freshly
    // written plan has no progress yet.
    if (!array_key_exists('progress', $plan))
    {
        assert_true(true, 'no progress section yet, which is allowed');

        return;
    }

    $progress = $plan['progress'];
    $items    = $plan['items'] ?? [];

    assert_true(array_key_exists('updated', $progress), 'the progress section does not say when it was last true');
    assert_true(array_key_exists('branch', $progress), 'the progress section does not name the working branch');

    foreach ($progress['done'] ?? [] as $entry)
    {
        assert_true(array_key_exists('commit', $entry), 'a finished entry has no commit');

        // A short commit id is hexadecimal and at least seven characters. This
        // catches a placeholder left behind ("TODO", "xxx") rather than proving
        // the commit exists — only git can do that.
        assert_true(
            preg_match('/^[0-9a-f]{7,40}$/', (string) $entry['commit']) === 1,
            'finished entry ' . (string) ($entry['key'] ?? '?') . ' has a commit that does not look like a commit id: ' . (string) $entry['commit']
        );
    }

    foreach ($progress['next'] ?? [] as $key)
    {
        assert_true(
            array_key_exists($key, $items),
            'the progress section says ' . $key . ' is next, but that is not an item in the plan'
        );
    }
});
