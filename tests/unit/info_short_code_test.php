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
 * 🧪 Unit tests — info page short-code validation (#204)
 * ============================================================================
 *
 * Pure, DB-free tests for g2ml_infoNormaliseShortCode(), defined in
 * web/Go2My.Link/_functions/info_display.php. A custom short code may
 * contain letters, digits, '-' and '_' (G2ML_CUSTOM_CODE_PATTERN in
 * shorturl_create.php), but the info page used to strip every other
 * character out of a code a visitor typed or pasted — turning
 * 'spring-sale' into 'springsale' and looking up the wrong link, or none at
 * all. This function REJECTS anything outside that alphabet instead of
 * stripping it, so these tests confirm both that the allowed characters
 * survive unchanged and that everything else comes back null.
 *
 * The helper is pure (no session, no database), so its decision is pinned
 * down here without any bootstrap. Its file is safe to require directly: the
 * direct-access guard only fires when the file is executed as the main
 * script, and it declares no load-time dependencies.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (#204)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/info_display.php';

// ============================================================================
// ✅ Allowed alphabet — letters, digits, '-' and '_' survive unchanged
// ============================================================================

test('info short code: a hyphenated code is kept unchanged', function (): void
{
    assert_same('spring-sale', g2ml_infoNormaliseShortCode('spring-sale'), 'A hyphen is part of the allowed custom-code alphabet and must not be stripped');
});

test('info short code: an underscored code is kept unchanged', function (): void
{
    assert_same('my_link', g2ml_infoNormaliseShortCode('my_link'), 'An underscore is part of the allowed custom-code alphabet and must not be stripped');
});

test('info short code: a plain alphanumeric code is kept unchanged', function (): void
{
    assert_same('abc123', g2ml_infoNormaliseShortCode('abc123'), 'A plain alphanumeric code was never affected by the bug and must keep working');
});

test('info short code: surrounding whitespace is trimmed', function (): void
{
    assert_same('abc', g2ml_infoNormaliseShortCode(' abc '), 'Leading/trailing whitespace is not part of a real short code');
});

// ============================================================================
// 🚫 Rejected input — anything outside the alphabet returns null, not a
// stripped-down guess at what the visitor meant
// ============================================================================

test('info short code: a slash is rejected, not stripped', function (): void
{
    assert_same(null, g2ml_infoNormaliseShortCode('a/b'), 'Stripping the slash would silently look up a different code (a/b is not ab)');
});

test('info short code: a dot is rejected, not stripped', function (): void
{
    assert_same(null, g2ml_infoNormaliseShortCode('a.b'), 'Stripping the dot would silently look up a different code (a.b is not ab)');
});

test('info short code: angle-bracket input is rejected, not stripped', function (): void
{
    assert_same(null, g2ml_infoNormaliseShortCode('<x>'), 'Markup characters are outside the custom-code alphabet and must be rejected outright');
});

test('info short code: an empty string is rejected', function (): void
{
    assert_same(null, g2ml_infoNormaliseShortCode(''), 'An empty code is not a valid lookup');
});

test('info short code: a 51-character code is rejected', function (): void
{
    $tooLong = str_repeat('a', 51);
    assert_same(null, g2ml_infoNormaliseShortCode($tooLong), 'Custom codes cap out at 50 characters (G2ML_CUSTOM_CODE_PATTERN)');
});

test('info short code: a 50-character code is accepted', function (): void
{
    $maxLength = str_repeat('a', 50);
    assert_same($maxLength, g2ml_infoNormaliseShortCode($maxLength), 'Exactly 50 characters is the allowed maximum, not a boundary that gets rejected');
});
