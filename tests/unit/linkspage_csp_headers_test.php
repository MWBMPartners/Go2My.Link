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
 * 🧪 Unit tests — LinksPage CSP header policy (LP-10)
 * ============================================================================
 *
 * Tests that the Lnks.page (.htaccess) Content Security Policy header
 * includes the required directives for avatar and link icon images, and does
 * not contain unsafe-inline in script-src.
 *
 * The policy is specified in web/Lnks.page/public_html/.htaccess:
 *   Header setifempty Content-Security-Policy "..."
 *
 * Issue LP-10 (#221): Allow https images on lnks.page so creator-supplied
 * avatars and per-link icons render on the public page. The policy now
 * includes `img-src 'self' https: data:`.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (LP-10, #221)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// Helper function: read and parse the .htaccess CSP policy
// ============================================================================

/**
 * Returns the exact pattern used, in this file, to detect any
 * "Header ... Content-Security-Policy" directive in the .htaccess file —
 * whatever verb it carries (set, setifempty, unset, add, append, merge,
 * edit, edit*) and whatever optional "always"/"onsuccess" qualifier comes
 * before it. Excludes Content-Security-Policy-Report-Only, which is a
 * different header (it only reports violations; it does not enforce).
 *
 * WHY THIS IS ITS OWN FUNCTION rather than an inline literal, and why BOTH
 * g2ml_testLnksPageCspPolicy() below and the "Regex detects all Header
 * directive variants" test call it: LP-10 review round 1 found that test
 * kept its own hand-copied second pattern and ran it only against the one
 * real "setifempty" line in .htaccess. It could pass even if both copies of
 * the pattern were narrowed to match "setifempty" alone, because nothing
 * ever fed it a "Header add"/"append"/"merge"/"edit" line to prove the
 * pattern still caught those too. Sharing one function closes that gap —
 * change the pattern once, and both the real check and the test that is
 * meant to prove the pattern works see the same change.
 *
 * @return string A PCRE pattern, ready for preg_match()/preg_match_all().
 */
function g2ml_testLnksPageCspHeaderDirectiveRegex(): string
{
    return '/^\s*Header\s+(?:(?:always|onsuccess)\s+)?(?:set|setifempty|unset|add|append|merge|edit\*?)\s+Content-Security-Policy(?![\w-])/mi';
}

/**
 * Reads the LinksPage .htaccess file and extracts the CSP policy string.
 * Returns an associative array with:
 *   - 'policy' (string): the full CSP header value
 *   - 'directiveMap' (array): parsed directives, keyed by name
 *   - 'cspCount' (int): number of CSP Header directives found
 *
 * There is deliberately no 'usesSetifempty' field here any more — an
 * earlier version hard-coded one to `true` on every successful return,
 * which meant a test that asserted on it could never fail and proved
 * nothing (LP-10 review round 1). Reaching this point WITHOUT an 'error'
 * key already proves the setifempty line was found: if it had not been,
 * the 'no_setifempty_line' branch below would have returned instead. See
 * the "CSP uses Header setifempty" test, which checks exactly that.
 *
 * Returns an associative array with 'error' key (string describing the failure)
 * on any of these failure modes:
 *   - 'file_unreadable': file_get_contents() failed
 *   - 'regex_error': preg_match_all() or preg_match() returned false
 *   - 'no_setifempty_line': no "Header setifempty Content-Security-Policy" found
 *   - 'regex_no_match': the regex matched zero lines (no CSP header directive found at all)
 *
 * @return array Array with 'error' key on failure, or with 'policy',
 *               'directiveMap', 'cspCount' keys on success
 */
function g2ml_testLnksPageCspPolicy()
{
    $htaccessPath = dirname(__DIR__, 2) . '/web/Lnks.page/public_html/.htaccess';
    $htaccessContent = file_get_contents($htaccessPath);

    if ($htaccessContent === false)
    {
        return ['error' => 'file_unreadable'];
    }

    // Count all CSP Header directives (any variant: set, setifempty, unset, add,
    // append, merge, edit, edit*) to detect conflicts. Pattern must avoid matching
    // Content-Security-Policy-Report-Only (used for testing policies that do not
    // enforce). We use negative lookahead for that. The expanded verb group ensures
    // we catch all possible Header directive types that could modify the CSP. This
    // is the SAME pattern the "Regex detects all Header directive variants" test
    // below proves works against every one of those verbs — see
    // g2ml_testLnksPageCspHeaderDirectiveRegex()'s own docblock for why sharing it
    // matters.
    $allCspMatches = [];
    $allCspCount = preg_match_all(
        g2ml_testLnksPageCspHeaderDirectiveRegex(),
        $htaccessContent,
        $allCspMatches
    );

    if ($allCspCount === false)
    {
        return ['error' => 'regex_error'];
    }

    if ($allCspCount === 0)
    {
        return ['error' => 'regex_no_match'];
    }

    // Extract the policy value from the setifempty line specifically.
    $cspMatches = [];
    $matchCount = preg_match('/^\s*Header\s+setifempty\s+Content-Security-Policy\s+"([^"]+)"/m', $htaccessContent, $cspMatches);

    if ($matchCount === false)
    {
        return ['error' => 'regex_error'];
    }

    if ($matchCount === 0)
    {
        return ['error' => 'no_setifempty_line'];
    }

    $cspPolicy = $cspMatches[1];

    // Split the policy into directives (separated by semicolons)
    $directives = array_map('trim', explode(';', $cspPolicy));
    $directiveMap = [];
    foreach ($directives as $directive)
    {
        if ($directive === '')
        {
            continue;
        }
        $parts = explode(' ', $directive, 2);
        $key = $parts[0];
        $value = '';
        if (isset($parts[1]))
        {
            $value = $parts[1];
        }
        $directiveMap[$key] = $value;
    }

    return [
        'policy' => $cspPolicy,
        'directiveMap' => $directiveMap,
        'cspCount' => $allCspCount,
    ];
}

// ============================================================================
// Setup: read and validate the .htaccess CSP policy
// ============================================================================

test('.htaccess file can be read', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(
        !isset($result['error']),
        'Could not read .htaccess file: ' . ($result['error'] ?? 'unknown error')
    );
});

test('Exactly one CSP Header directive exists', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    assert_same(
        1,
        $result['cspCount'],
        'Expected exactly one Content-Security-Policy header directive in .htaccess to avoid conflicts'
    );
});

test('Regex detects all Header directive variants (set, setifempty, unset, add, append, merge, edit)', function (): void
{
    // WRONG BEFORE (LP-10 review round 1): this test kept its OWN hand-copied
    // second regex, and only ever ran it against the real .htaccess file,
    // which contains a single "Header setifempty" line. That meant the test
    // could stay green even if BOTH copies of the pattern were narrowed to
    // match "setifempty" alone — nothing here ever fed the regex a "Header
    // add"/"append"/"merge"/"edit" line to prove it still caught those too.
    //
    // Fixed by proving the claim directly: this now runs the ONE shared
    // pattern (g2ml_testLnksPageCspHeaderDirectiveRegex(), the exact pattern
    // g2ml_testLnksPageCspPolicy() uses for the real check) against a
    // fabricated line for every variant named in this test's title, plus a
    // Content-Security-Policy-Report-Only line that must NOT match (that is
    // a different header — it only reports violations, it does not
    // enforce). A hand-written line, not a hand-copied regex, is what
    // proves the pattern actually does what the title says.
    $pattern = g2ml_testLnksPageCspHeaderDirectiveRegex();

    $linesThatMustMatch = [
        'set'         => 'Header set Content-Security-Policy "default-src \'self\'"',
        'always set'  => 'Header always set Content-Security-Policy "default-src \'self\'"',
        'setifempty'  => 'Header setifempty Content-Security-Policy "default-src \'self\'"',
        'unset'       => 'Header unset Content-Security-Policy',
        'add'         => 'Header add Content-Security-Policy "default-src \'self\'"',
        'append'      => 'Header append Content-Security-Policy "frame-ancestors \'none\'"',
        'merge'       => 'Header merge Content-Security-Policy "frame-ancestors \'none\'"',
        'edit'        => 'Header edit Content-Security-Policy "(.*)" "$1"',
        'edit*'       => 'Header edit* Content-Security-Policy "(.*)" "$1"',
    ];

    foreach ($linesThatMustMatch as $variantName => $line)
    {
        assert_true(
            preg_match($pattern, $line) === 1,
            'A "Header ' . $variantName . ' Content-Security-Policy" line should match the shared regex, but did not: ' . $line
        );
    }

    // Content-Security-Policy-Report-Only must never be mistaken for one of
    // the enforcing variants above — this is what the pattern's negative
    // lookahead, (?![\w-]), exists to exclude.
    assert_true(
        preg_match($pattern, 'Header set Content-Security-Policy-Report-Only "default-src \'self\'"') === 0,
        'A Content-Security-Policy-Report-Only line must NOT match the CSP Header regex'
    );

    // Finally, confirm the real .htaccess still carries exactly the one
    // intended CSP Header directive (setifempty) and no stray add/append/
    // merge/edit line that would silently change the enforced policy.
    $htaccessPath = dirname(__DIR__, 2) . '/web/Lnks.page/public_html/.htaccess';
    $htaccessContent = file_get_contents($htaccessPath);
    assert_true($htaccessContent !== false, 'Could not read .htaccess file');

    $allCspMatches = [];
    $allCspCount = preg_match_all($pattern, $htaccessContent, $allCspMatches);
    assert_same(1, $allCspCount, 'The real .htaccess must contain exactly one CSP Header directive');
});

test('CSP uses Header setifempty (not Header set)', function (): void
{
    // WRONG BEFORE (LP-10 review round 1): g2ml_testLnksPageCspPolicy() used
    // to return a 'usesSetifempty' field hard-coded to `true` on every
    // successful call, so the assertion that used to sit here could never
    // fail and proved nothing.
    //
    // The real check does not need a separate field: g2ml_testLnksPageCspPolicy()
    // only reaches its success return (no 'error' key) after its own
    // setifempty-specific regex has matched a line in .htaccess. If the
    // real policy used a bare "Header set" instead of "Header setifempty",
    // that regex would NOT match, and the helper would return
    // ['error' => 'no_setifempty_line'] instead. So an error-free result
    // from the helper already IS the proof that setifempty (not a plain
    // set, which would clobber the stricter CSP that a LinksPage rendering
    // owner-supplied custom HTML sets in PHP — Component C.6) is what
    // .htaccess uses.
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(
        !isset($result['error']),
        'The CSP header must use setifempty, not plain set, so custom HTML policies (Component C.6) can override it — helper reported: '
            . ($result['error'] ?? 'no error')
    );
});

// ============================================================================
// Test 1: img-src directive includes https and data
// ============================================================================

test('CSP allows images from https URLs', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $directiveMap = $result['directiveMap'];
    $imgSrcValue = $directiveMap['img-src'] ?? '';
    $imgSrcTokens = preg_split('/\s+/', trim($imgSrcValue));
    assert_true(
        in_array('https:', $imgSrcTokens, true),
        'The img-src directive must include the separate token https:'
    );
});

test('CSP allows data URIs for images', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $directiveMap = $result['directiveMap'];
    $imgSrcValue = $directiveMap['img-src'] ?? '';
    $imgSrcTokens = preg_split('/\s+/', trim($imgSrcValue));
    assert_true(
        in_array('data:', $imgSrcTokens, true),
        'The img-src directive must include the separate token data:'
    );
});

test('CSP img-src is exactly self, https and data', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $cspPolicy = $result['policy'];
    $foundImgSrc = false;
    foreach (explode(';', $cspPolicy) as $directive)
    {
        if (strpos(trim($directive), 'img-src') === 0)
        {
            $foundImgSrc = true;
            assert_same(
                "img-src 'self' https: data:",
                trim($directive),
                'The img-src directive must exactly match the expected policy'
            );
            break;
        }
    }
    assert_true($foundImgSrc, 'The CSP must contain an img-src directive');
});

// ============================================================================
// Test 2: script-src directive does NOT contain unsafe-inline
// ============================================================================

test('CSP script-src does not allow unsafe-inline scripts', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $directiveMap = $result['directiveMap'];
    $scriptSrcValue = $directiveMap['script-src'] ?? '';
    assert_true(
        strpos($scriptSrcValue, "'unsafe-inline'") === false,
        'The script-src directive must not contain unsafe-inline (found: ' . $scriptSrcValue . ')'
    );
});

test('CSP script-src only allows self', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $directiveMap = $result['directiveMap'];
    $scriptSrcValue = $directiveMap['script-src'] ?? '';
    assert_same(
        "'self'",
        trim($scriptSrcValue),
        'The script-src directive must only allow self'
    );
});

// ============================================================================
// Test 3: object-src directive is set to none (disables all plugins)
// ============================================================================

test('CSP disables plugins and embedded content', function (): void
{
    $result = g2ml_testLnksPageCspPolicy();
    assert_true(!isset($result['error']), 'Helper error: ' . ($result['error'] ?? 'unknown'));
    $directiveMap = $result['directiveMap'];
    $objectSrcValue = $directiveMap['object-src'] ?? '';
    assert_same(
        "'none'",
        trim($objectSrcValue),
        'The object-src directive must be set to none'
    );
});

