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
 * 🧪 Characterization tests — formField() escapes its value exactly once
 * ============================================================================
 *
 * #220 (LP-12): the LinksPage create and edit pages used to pass a value
 * into formField() (web/_includes/accessibility.php) AFTER already escaping
 * it themselves with g2ml_sanitiseOutput(). formField() then escaped it
 * again on top of that. A title with an apostrophe, "Jane's", was stored as
 * "Jane&#039;s" — and because the page's OWN sanitise-then-save round trip
 * fed that already-corrupted text straight back through the same double
 * escape on the next edit, it got worse on every save. A URL containing an
 * ampersand (common in image and social links, e.g. a query string like
 * "?a=1&b=2") was corrupted the exact same way, since "&" also has a
 * htmlspecialchars() encoding of its own that then got encoded a second
 * time ("&amp;" becoming "&amp;amp;").
 *
 * This file pins down the FIX at the level of the shared helper itself:
 * formField() must escape a raw, UNescaped value exactly once. If
 * formField() itself ever starts escaping zero times or twice, the first
 * two formField() tests below catch it.
 *
 * WHAT THIS FILE DOES NOT COVER, AND WHERE THAT IS ACTUALLY CHECKED (review
 * round 1 on LP-12 found the note below wrong — it used to claim the
 * integration test covered the page files, which it does not; review
 * round 2 found the "above"/"below" wording below no longer matched where
 * those tests actually sit in this file, and found this paragraph's last
 * sentence overstating what the source-scanning test below can detect —
 * both are fixed here):
 * The real #220 bug was never inside formField() — it was the two ADMIN
 * PAGES (pages/linkspage/create/index.php and edit/index.php) escaping a
 * value with g2ml_sanitiseOutput() BEFORE ever handing it to formField().
 * Neither of those formField() tests would notice if that extra escape
 * came back, because each one calls formField() directly with a value
 * that is already correct. The integration test in
 * tests/integration/linkspage_manage_test.php does not cover it either —
 * by its own comment, it calls the save layer
 * (g2ml_linkspageManageCreatePage() / g2ml_linkspageManageUpdatePage()),
 * which was never where the fault was. So until the test below was added,
 * the page-file fix was checked only by reading the source in code
 * review — a check that can fail quietly if the extra escape is ever put
 * back without anyone noticing. The "no formField() call pre-escapes its
 * value" test below closes most of that gap: it reads the two page
 * files' own text and fails if either one hands formField() a 'value'
 * written DIRECTLY as a g2ml_sanitiseOutput(...) call. It is a text
 * search, not a real parser, so it does not follow variables: a value
 * that was escaped into a variable first and then passed in by name (for
 * example `$titleValue = g2ml_sanitiseOutput(...);` followed by
 * `'value' => $titleValue`) would NOT be caught. That gap is closed the
 * same way the direct-call case used to be: by reading the page source in
 * code review.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#220 / LP-12)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// accessibility.php declares "Dependencies: None (standalone helper
// functions)" in its own file header, and its one runtime guard (the
// "Direct Access Guard" at the top of the file) only fires when
// $_SERVER['SCRIPT_FILENAME'] is this file itself — which it never is when
// the file is require_once'd from the test runner — so no __() stub or
// other guard is needed here, unlike some other unit tests in this suite
// that DO depend on the translation layer.
// ----------------------------------------------------------------------------
require_once dirname(__DIR__, 1) . '/../web/_includes/accessibility.php';

// ----------------------------------------------------------------------------
// formField(): exactly one escape pass over 'value'
// ----------------------------------------------------------------------------

test('formField: a value containing an apostrophe and an ampersand is escaped exactly once', function (): void
{
    // The raw, UNescaped value a page should now be passing in — this is
    // deliberately what a page's own POSTed/stored data looks like BEFORE
    // any escaping, since escaping the value before handing it to
    // formField() is the exact fault #220 fixes.
    $html = formField([
        'id'    => 't',
        'name'  => 't',
        'label' => 'T',
        'value' => "Jane's & Co",
    ]);

    assert_contains('value="Jane&#039;s &amp; Co"', $html, 'formField() must htmlspecialchars() the raw value exactly once');

    // If the caller (wrongly) escaped the value first, the ampersand's own
    // escape ("&amp;") would itself get escaped again, producing
    // "&amp;amp;" — this is the literal double-escape #220 fixes, so its
    // absence is the clearest possible proof that only one pass ran.
    assert_false(str_contains($html, '&amp;amp;'), 'A double-escaped ampersand ("&amp;amp;") must never appear — that is the exact corruption #220 fixes');

    // Likewise, a double-escaped apostrophe would show as "&amp;#039;"
    // (the literal "&#039;" text re-escaped) rather than the correct
    // single-pass "&#039;".
    assert_false(str_contains($html, '&amp;#039;'), 'A double-escaped apostrophe ("&amp;#039;") must never appear');
});

test('formField: a textarea value is also escaped exactly once, not zero or twice', function (): void
{
    $html = formField([
        'id'    => 'bio',
        'name'  => 'bio',
        'label' => 'Bio',
        'type'  => 'textarea',
        'value' => "Rock & Roll's finest",
    ]);

    // A textarea prints its value as element TEXT CONTENT, not an
    // attribute, but the same single htmlspecialchars() call handles both
    // — this pins that shared code path down for the textarea branch too.
    assert_contains('Rock &amp; Roll&#039;s finest', $html, 'A textarea value must be escaped exactly once');
    assert_false(str_contains($html, '&amp;amp;'), 'A textarea value must never be double-escaped');
});

test('formField: an empty value renders a plain, unescaped-looking empty attribute', function (): void
{
    $html = formField([
        'id'    => 'empty-field',
        'name'  => 'empty-field',
        'label' => 'Empty',
        'value' => '',
    ]);

    assert_contains('value=""', $html, 'An empty value must render as a plain empty attribute, not a missing one');
});

// ----------------------------------------------------------------------------
// Regression check on the actual #220 fault: the two LinksPage admin pages
// must never hand formField() a value that they escaped themselves first.
// ----------------------------------------------------------------------------

/**
 * Reads one PHP source file and returns every formField([ ... ]); call
 * body found in it, so a test can check what each call actually passes as
 * its 'value'.
 *
 * WHY A REGEX AND NOT A REAL PARSER: both admin pages write every
 * formField() call the same way — `formField([` ... `]);` with a plain
 * array literal, one key per line, and no nested array VALUES (checked by
 * hand against both files when this test was written: neither file has a
 * formField() call with an 'options' => [...] or similar nested-array
 * argument). That means the first `]);` after `formField([` is always the
 * real end of that call, so a non-greedy match between them safely
 * captures one call at a time without needing to track nested brackets.
 * A value like $pageData['slug'] does not break this: the `]` there is
 * followed by `,`, never by `);`, so it can never be mistaken for the
 * call's closing `]);`. If a future formField() call in either file
 * legitimately needs a nested array value, this pattern will need
 * revisiting alongside it — it must not be left to quietly stop matching.
 *
 * @param  string $filePath Absolute path to the PHP file to scan.
 * @return array{error: string}|array{callCount: int, callBodies: array<int, string>}
 *         An 'error' key on failure ('file_unreadable', 'regex_error', or
 *         'no_formfield_calls_found' — the last one means this test's own
 *         assumption about the file no longer holds and needs a look, not
 *         that the file is fine), or 'callCount' and 'callBodies' (the raw
 *         text between each call's '[' and ']);') on success.
 */
function g2ml_testLinkspageAdminPageFormFieldCallBodies(string $filePath): array
{
    $source = file_get_contents($filePath);

    if ($source === false)
    {
        return ['error' => 'file_unreadable'];
    }

    $callCount = preg_match_all('/formField\(\s*\[(.*?)\]\);/s', $source, $callMatches);

    if ($callCount === false)
    {
        return ['error' => 'regex_error'];
    }

    if ($callCount === 0)
    {
        return ['error' => 'no_formfield_calls_found'];
    }

    return [
        'callCount'  => $callCount,
        'callBodies' => $callMatches[1],
    ];
}

// The two files #220 actually fixed. Kept as a named list rather than a
// glob so this test only ever speaks for the files it has actually
// checked — LP-12 review round 1 noted the same read-the-source-text
// approach could later cover the further five pages named in #274, but
// that is a separate, not-yet-planned piece of work: widening this list
// without also confirming each added page follows the same one-call-per-
// "]);"-line shape above would silently weaken the regex assumption this
// test relies on.
$g2mlTestLinkspageAdminPagesFixedByLp12 = [
    'LinksPage create page' => dirname(__DIR__, 2) . '/web/Go2My.Link/_admin/public_html/pages/linkspage/create/index.php',
    'LinksPage edit page'   => dirname(__DIR__, 2) . '/web/Go2My.Link/_admin/public_html/pages/linkspage/edit/index.php',
];

foreach ($g2mlTestLinkspageAdminPagesFixedByLp12 as $g2mlTestPageLabel => $g2mlTestPagePath)
{
    test($g2mlTestPageLabel . ' (#220): no formField() call pre-escapes its value with g2ml_sanitiseOutput()', function () use ($g2mlTestPageLabel, $g2mlTestPagePath): void
    {
        $result = g2ml_testLinkspageAdminPageFormFieldCallBodies($g2mlTestPagePath);
        assert_true(
            !isset($result['error']),
            $g2mlTestPageLabel . ': could not scan file for formField() calls: ' . ($result['error'] ?? 'unknown error')
        );

        $doubleEscapedCallBodies = [];
        foreach ($result['callBodies'] as $callBody)
        {
            // This is the exact fault #220 fixed: a page escaping the
            // value itself before formField() escapes it again. Matching
            // on 'value' => g2ml_sanitiseOutput( — not on
            // g2ml_sanitiseOutput( anywhere in the call — is deliberate:
            // g2ml_sanitiseOutput() calls on OTHER keys (or none at all)
            // are fine; only feeding an already-escaped string as 'value'
            // is the bug.
            if (preg_match("/'value'\s*=>\s*g2ml_sanitiseOutput\s*\(/", $callBody) === 1)
            {
                $doubleEscapedCallBodies[] = trim($callBody);
            }
        }

        assert_true(
            $doubleEscapedCallBodies === [],
            $g2mlTestPageLabel . ": found a formField() call whose 'value' is pre-escaped with g2ml_sanitiseOutput() — this is the #220 double-escape bug. Offending call(s): "
                . implode(' ||| ', $doubleEscapedCallBodies)
        );
    });
}
