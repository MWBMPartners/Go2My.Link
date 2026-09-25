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
 * 🧪 Unit tests — PHP's default time zone is forced to UTC (#214)
 * ============================================================================
 *
 * The database session runs in UTC (web/_functions/db_connect.php sends
 * "SET time_zone" right after connecting), but PHP's own date(),
 * strtotime(), mktime() and DateTime calls follow whatever the server's
 * php.ini names instead, which on shared hosting is usually the host's own
 * local zone (gmdate() and time() are unaffected by that setting — gmdate()
 * always formats in UTC and time() returns a zone-agnostic Unix timestamp).
 * A value written under one zone and read back, or compared, under the
 * other can then disagree by however far the server clock sits from UTC —
 * for example the breach-response cooldown (written with gmdate(), read
 * back with strtotime()) and session expiry (written with date(), checked
 * against NOW() in SQL).
 *
 * The fix is one line, date_default_timezone_set('UTC'), placed early in
 * web/_includes/page_init.php (which the application's entry points load)
 * and again at the top of web/Go2My.Link/public_html/install/index.php,
 * which does not load page_init.php and needs the same line on its own.
 * This file proves four things:
 *
 *   (a) page_init.php contains the call, and it comes before the first
 *       place that file itself reads the clock — so nothing in page_init.php
 *       can run under the wrong zone even for a moment.
 *   (b) install/index.php contains the call, in the same "before anything
 *       else reads the clock" position.
 *   (c) tests/bootstrap.php (loaded by both tests/run.php and
 *       tests/run_integration.php, so both suites are covered) contains the
 *       same call — this is the file that stands in for page_init.php
 *       during a test run, and the check that would actually notice someone
 *       deleting the line from it.
 *   (d) date_default_timezone_get() reports 'UTC' while this very test
 *       runs — the acceptance criterion #214 asks for directly. On its own
 *       this does not prove (c) is what caused it: PHP 8.4's own
 *       command-line default is already UTC with no ini setting at all, so
 *       this check would still pass even with the bootstrap.php line
 *       removed. (c) is the check that would catch that removal; (d) is
 *       kept because it is the actual acceptance criterion.
 *
 * (a), (b) and (c) read their file as plain text and search for a literal
 * substring. That is deliberately dumb: it cannot tell code from a comment,
 * so a comment that happens to mention one of those calls by name would
 * trip it too. The guard comments actually written beside both calls were
 * checked by hand to avoid that, but nothing stops a FUTURE edit
 * reintroducing the words in prose above the call — if that ever happens
 * here, the fix is to reword the comment, not to weaken this test.
 *
 * WHAT THIS FILE CANNOT DO
 *
 * It cannot prove real request traffic on Dreamhost actually gets UTC —
 * that depends on page_init.php actually being loaded before anything else
 * runs, which is a property of every entry point's own first few lines, not
 * of this file. It also cannot see a date/time call reached only through a
 * require()'d file, since it reads each entry point's own source text in
 * isolation, exactly as the build plan for #214 asks it to.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — Phase 8 (#214)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// 🧰 Helper
// ============================================================================

/**
 * The literal substrings that count as "this file reads the clock", exactly
 * as the #214 build plan names them. A plain substring search, not a real
 * PHP parse — see the file header for what that does and does not prove.
 *
 * @var array<int, string>
 */
const G2ML_TIMEZONE_TEST_CLOCK_PATTERNS = [
    'date(',
    'gmdate(',
    'strtotime(',
    'time()',
    'new DateTime',
];

/**
 * Find the character offset of the first substring in
 * G2ML_TIMEZONE_TEST_CLOCK_PATTERNS to appear in $source, or null if none of
 * them appear at all.
 *
 * @param  string $source
 * @return int|null
 */
function g2ml_timezoneTest_firstClockCallOffset(string $source): ?int
{
    $earliest = null;

    foreach (G2ML_TIMEZONE_TEST_CLOCK_PATTERNS as $pattern)
    {
        $offset = strpos($source, $pattern);

        if ($offset !== false && ($earliest === null || $offset < $earliest))
        {
            $earliest = $offset;
        }
    }

    return $earliest;
}

// ============================================================================
// (a) web/_includes/page_init.php
// ============================================================================

test('timezone: page_init.php forces UTC before it reads the clock itself (#214)', function (): void
{
    $path = dirname(__DIR__, 2) . '/web/_includes/page_init.php';

    assert_true(file_exists($path), 'page_init.php must exist at ' . $path);

    $source = file_get_contents($path);

    assert_true($source !== false, 'Could not read page_init.php');

    $callOffset = strpos($source, "date_default_timezone_set('UTC')");

    assert_true(
        $callOffset !== false,
        "page_init.php must call date_default_timezone_set('UTC')"
    );

    $firstClockCallOffset = g2ml_timezoneTest_firstClockCallOffset($source);

    // No other clock read anywhere in the file is the trivially-safe case —
    // there is then nothing the timezone call could possibly run too late
    // for — so it only needs checking when one exists.
    if ($firstClockCallOffset !== null)
    {
        assert_true(
            $callOffset < $firstClockCallOffset,
            "date_default_timezone_set('UTC') appears at offset {$callOffset}, "
                . "but page_init.php already reads the clock at offset {$firstClockCallOffset} "
                . '— the timezone call must come first'
        );
    }
});

// ============================================================================
// (b) web/Go2My.Link/public_html/install/index.php
// ============================================================================

test('timezone: the installer forces UTC before it reads the clock itself, independently of page_init.php (#214)', function (): void
{
    $path = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/install/index.php';

    assert_true(file_exists($path), 'install/index.php must exist at ' . $path);

    $source = file_get_contents($path);

    assert_true($source !== false, 'Could not read install/index.php');

    $callOffset = strpos($source, "date_default_timezone_set('UTC')");

    assert_true(
        $callOffset !== false,
        "install/index.php must call date_default_timezone_set('UTC') — it does not load page_init.php"
    );

    $firstClockCallOffset = g2ml_timezoneTest_firstClockCallOffset($source);

    if ($firstClockCallOffset !== null)
    {
        assert_true(
            $callOffset < $firstClockCallOffset,
            "date_default_timezone_set('UTC') appears at offset {$callOffset}, "
                . "but install/index.php already reads the clock at offset {$firstClockCallOffset} "
                . '— the timezone call must come first'
        );
    }
});

// ============================================================================
// (c) tests/bootstrap.php — the file that stands in for page_init.php while
//     the test suites run (loaded by both tests/run.php and
//     tests/run_integration.php)
// ============================================================================

test('timezone: tests/bootstrap.php forces UTC before it reads the clock itself (#214)', function (): void
{
    $path = dirname(__DIR__, 2) . '/tests/bootstrap.php';

    assert_true(file_exists($path), 'tests/bootstrap.php must exist at ' . $path);

    $source = file_get_contents($path);

    assert_true($source !== false, 'Could not read tests/bootstrap.php');

    $callOffset = strpos($source, "date_default_timezone_set('UTC')");

    assert_true(
        $callOffset !== false,
        "tests/bootstrap.php must call date_default_timezone_set('UTC') — without it, "
            . 'the next test below (which only checks the RESULT, not the cause) would '
            . 'still pass, because PHP already defaults to UTC in the container this test '
            . 'suite runs in'
    );

    $firstClockCallOffset = g2ml_timezoneTest_firstClockCallOffset($source);

    // No other clock read anywhere in the file is the trivially-safe case —
    // there is then nothing the timezone call could possibly run too late
    // for — so it only needs checking when one exists.
    if ($firstClockCallOffset !== null)
    {
        assert_true(
            $callOffset < $firstClockCallOffset,
            "date_default_timezone_set('UTC') appears at offset {$callOffset}, "
                . "but tests/bootstrap.php already reads the clock at offset {$firstClockCallOffset} "
                . '— the timezone call must come first'
        );
    }
});

// ============================================================================
// (d) The test run itself — the acceptance criterion #214 asks for
//     directly. Kept even though it cannot on its own prove (c) is what
//     caused it: PHP 8.4's command-line default is already UTC with no ini
//     setting at all, so this check would still pass with the
//     tests/bootstrap.php line removed. (c) above is the check that would
//     actually notice that removal.
// ============================================================================

test('timezone: the default PHP timezone is UTC while the test suite runs (#214)', function (): void
{
    assert_same(
        'UTC',
        date_default_timezone_get(),
        'The default PHP timezone must be UTC while the test suite runs'
    );
});
