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
 * 🧪 Check — public pages do not advertise features that are not built (#210)
 * ============================================================================
 *
 * The 2026-09-21 issue sweep (#210) found the homepage, the features page,
 * the about page, the pricing page and the lnks.page landing page each
 * promising something that does not exist: two-factor authentication (#34),
 * SSO/SAML (#36) or automatic favicon fetching (#45). This check reads the
 * five pages' source text and fails if any of those five phrases reappears,
 * so a future edit cannot silently bring the same over-promise back.
 *
 * Each page's comments explaining the #210 fix name the removed phrases on
 * purpose (that is what a "what was rejected and why" comment is for — see
 * .claude/memory/working-rules.md's commenting rule), so this check skips
 * whole-line comments (a line starting with "//", "#", "*" or "<!--", once
 * leading whitespace is removed) and scans every other line in full. The
 * same skip rule is used by tests/unit/no_php_in_urls_test.php for the same
 * reason.
 *
 * WHAT THIS CHECK CANNOT DO
 *
 * It reads source text line by line. It cannot see a phrase built up at
 * runtime from separate pieces (for example two variables concatenated), and
 * it cannot see a phrase split across more than one line. It only reads the
 * five files named below; it says nothing about any other page.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 📛 Whole-line comment detection (same shape as no_php_in_urls_test.php,
// declared again here so this file stands on its own).
// ============================================================================

/**
 * True when $line is a whole-line comment: PHP/JS "//", PHP/shell "#", a
 * PHPDoc continuation "*", or an HTML "<!--", once leading whitespace is
 * stripped. A trailing comment placed after real code on the same line is
 * NOT treated as a comment, so genuine visible text is still scanned.
 *
 * @param  string $line
 * @return bool
 */
function _g2mlMarketingClaimsTestIsCommentLine(string $line): bool
{
    $trimmed = ltrim($line);

    if ($trimmed === '')
    {
        return false;
    }

    if (strncmp($trimmed, '//', 2) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '#', 1) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '*', 1) === 0)
    {
        return true;
    }

    if (strncmp($trimmed, '<!--', 4) === 0)
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🔎 Scanning one file for the forbidden phrases.
// ============================================================================

/**
 * The phrases a public page must never contain (outside a comment), because
 * none of them is true today. Matching is case-insensitive, so this list is
 * kept lower-case and every comparison lower-cases the line first.
 *
 * @return array<int, string>
 */
function _g2mlMarketingClaimsTestForbiddenPhrases(): array
{
    return array(
        'two-factor',
        '2fa',
        'sso',
        'saml',
        'auto favicons',
    );
}

/**
 * Scan one file's non-comment lines for every forbidden phrase.
 *
 * @param  string $filePath  Absolute path to the file to read.
 * @return array<int, array{line: int, phrase: string}>
 */
function _g2mlMarketingClaimsTestScanFile(string $filePath): array
{
    $findings = array();
    $lines    = file($filePath, FILE_IGNORE_NEW_LINES);

    if ($lines === false)
    {
        return $findings;
    }

    $forbiddenPhrases = _g2mlMarketingClaimsTestForbiddenPhrases();
    $lineNumber       = 0;

    foreach ($lines as $line)
    {
        $lineNumber = $lineNumber + 1;

        if (_g2mlMarketingClaimsTestIsCommentLine($line))
        {
            continue;
        }

        $lowerLine = strtolower($line);

        foreach ($forbiddenPhrases as $phrase)
        {
            if (str_contains($lowerLine, $phrase))
            {
                $findings[] = array(
                    'line'   => $lineNumber,
                    'phrase' => $phrase,
                );
            }
        }
    }

    return $findings;
}

// ============================================================================
// 🏃 The check itself.
// ============================================================================

test('public pages do not advertise 2FA, SSO, SAML or Auto Favicons (#210)', function (): void
{
    $repositoryRoot = dirname(__DIR__, 2);

    // Relative to the repository root, exactly the five pages #210 named.
    $filesToCheck = array(
        'web/Go2My.Link/public_html/pages/home.php',
        'web/Go2My.Link/public_html/pages/features/index.php',
        'web/Go2My.Link/public_html/pages/about/index.php',
        'web/Go2My.Link/public_html/pages/pricing/index.php',
        'web/Lnks.page/public_html_landing/index.php',
    );

    $failureLines = array();

    foreach ($filesToCheck as $relativeFilePath)
    {
        $absoluteFilePath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFilePath);

        assert_true(is_file($absoluteFilePath), 'Expected page not found: ' . $relativeFilePath);

        $findings = _g2mlMarketingClaimsTestScanFile($absoluteFilePath);

        foreach ($findings as $finding)
        {
            $failureLines[] = $relativeFilePath . ':' . $finding['line'] . ': "' . $finding['phrase'] . '"';
        }
    }

    assert_same(
        array(),
        $failureLines,
        'Found a feature claim that is not true today — see #210 for why these were removed:' . PHP_EOL . implode(PHP_EOL, $failureLines)
    );
});

test('seed 058 defines every _v2 translation key the pages now use', function (): void
{
    $repositoryRoot = dirname(__DIR__, 2);
    $seedFilePath    = $repositoryRoot . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . '_sql' . DIRECTORY_SEPARATOR . 'seeds' . DIRECTORY_SEPARATOR . '058_truthful_marketing_translations.sql';

    assert_true(is_file($seedFilePath), 'Expected seed file not found: web/_sql/seeds/058_truthful_marketing_translations.sql');

    $seedText = file_get_contents($seedFilePath);

    assert_true($seedText !== false, 'Could not read the seed file: ' . $seedFilePath);

    $expectedKeys = array(
        'home.feature_secure_desc_v2',
        'home.feature_analytics_desc_v2',
        'features.security_desc_v2',
        'features.analytics_desc_v2',
        'about.offer_security_desc_v2',
        'about.offer_analytics_desc_v2',
    );

    foreach ($expectedKeys as $expectedKey)
    {
        assert_contains($expectedKey, $seedText, 'Seed 058 is missing translation key: ' . $expectedKey);
    }
});

// ============================================================================
// 🧪 Self-tests for the comment-line filter, proving both directions: a
// forbidden phrase inside a comment is ignored, and the same phrase in real
// visible text is still caught.
// ============================================================================

test('comment-line filter: "//", "#", "*" and "<!--" lines are comments', function (): void
{
    assert_true(_g2mlMarketingClaimsTestIsCommentLine('// #210: SSO and 2FA were removed'));
    assert_true(_g2mlMarketingClaimsTestIsCommentLine('    # SAML mentioned only here, in a comment'));
    assert_true(_g2mlMarketingClaimsTestIsCommentLine(' * two-factor is not built'));
    assert_true(_g2mlMarketingClaimsTestIsCommentLine('<!-- Auto Favicons removed -->'));
    assert_false(_g2mlMarketingClaimsTestIsCommentLine('<span class="feature">Auto Favicons</span>'));
});

test('scanner: a forbidden phrase inside a comment line is not a finding', function (): void
{
    $tempFilePath = tempnam(sys_get_temp_dir(), 'g2ml_marketing_claims_test_');
    file_put_contents($tempFilePath, '// this comment mentions SSO and 2FA on purpose' . PHP_EOL . '<p>Nothing forbidden here.</p>' . PHP_EOL);

    $findings = _g2mlMarketingClaimsTestScanFile($tempFilePath);

    unlink($tempFilePath);

    assert_same(array(), $findings);
});

test('scanner: a forbidden phrase in real visible text is a finding', function (): void
{
    $tempFilePath = tempnam(sys_get_temp_dir(), 'g2ml_marketing_claims_test_');
    file_put_contents($tempFilePath, '<li>SSO integration</li>' . PHP_EOL);

    $findings = _g2mlMarketingClaimsTestScanFile($tempFilePath);

    unlink($tempFilePath);

    assert_same(1, count($findings));
    assert_same(1, $findings[0]['line']);
    assert_same('sso', $findings[0]['phrase']);
});

test('scanner: two forbidden phrases on one line produce two findings', function (): void
{
    $tempFilePath = tempnam(sys_get_temp_dir(), 'g2ml_marketing_claims_test_');
    file_put_contents($tempFilePath, '<li>SSO / SAML integration</li>' . PHP_EOL);

    $findings = _g2mlMarketingClaimsTestScanFile($tempFilePath);

    unlink($tempFilePath);

    assert_same(2, count($findings));
});
