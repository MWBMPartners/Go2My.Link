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
 * 🧪 Unit tests — g2ml_buildCsv() + g2ml_csvHumaniseHeader() (#44)
 * ============================================================================
 *
 * Pure-function, DB-free coverage for the CSV-formatting half of the
 * analytics export feature (web/_functions/export.php). g2ml_buildCsv() does
 * no I/O beyond an in-memory php://temp stream, so it is fully testable here
 * without a request/DB context — the streaming half
 * (g2ml_streamCsvDownload(), which sends real HTTP headers) and the
 * org-scoped export endpoint itself
 * (web/Go2My.Link/_admin/public_html/analytics-export.php) are integration
 * concerns, not covered by this file.
 *
 * Every assertion re-parses the built CSV with str_getcsv() (PHP's own
 * RFC-4180-aware reader) rather than checking exact byte-for-byte output, so
 * these tests assert on MEANING (does field N of row M round-trip correctly)
 * rather than on incidental formatting choices.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#44)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/export.php';

/**
 * Split a CSV document into its RFC-4180 rows using PHP's own reader
 * (str_getcsv() per line does not correctly handle embedded newlines inside
 * a quoted field, so this walks the string with the same enclosure-aware
 * logic fgetcsv() would apply to a stream). Used only by this test file to
 * verify g2ml_buildCsv()'s output round-trips correctly.
 *
 * @param  string $csv
 * @return array<int, array<int, string>>
 */
function g2ml_export_test_parse_csv(string $csv): array
{
    $stream = fopen('php://temp', 'r+');

    fwrite($stream, $csv);
    rewind($stream);

    $rows = [];

    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false)
    {
        $rows[] = $row;
    }

    fclose($stream);

    return $rows;
}

// ============================================================================
// 📄 g2ml_buildCsv() — header row
// ============================================================================

test('buildCsv: with $headers supplied, the header row is written first, verbatim', function (): void
{
    $csv = g2ml_buildCsv(
        [['g2my.link/abc', 42]],
        ['Short Code', 'Clicks']
    );

    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(2, count($rows), 'Header row + one data row');
    assert_same(['Short Code', 'Clicks'], $rows[0], 'The header row matches verbatim');
    assert_same(['g2my.link/abc', '42'], $rows[1], 'The data row follows the header row');
});

test('buildCsv: with no $headers, only data rows are written (no blank leading row)', function (): void
{
    $csv = g2ml_buildCsv([['a', '1'], ['b', '2']]);

    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(2, count($rows), 'Exactly two data rows, no header row');
    assert_same(['a', '1'], $rows[0]);
    assert_same(['b', '2'], $rows[1]);
});

// ============================================================================
// 📄 g2ml_buildCsv() — empty input
// ============================================================================

test('buildCsv: empty $rows and empty $headers returns an empty string', function (): void
{
    assert_same('', g2ml_buildCsv([], []), 'No rows and no headers must produce an empty document');
});

test('buildCsv: empty $rows with $headers still writes just the header row', function (): void
{
    $csv  = g2ml_buildCsv([], ['Value', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(1, count($rows), 'Only the header row is written when there are no data rows');
    assert_same(['Value', 'Clicks'], $rows[0]);
});

// ============================================================================
// 📄 g2ml_buildCsv() — RFC-4180 quoting: commas, embedded quotes, newlines
// ============================================================================

test('buildCsv: a value containing a comma round-trips through quoting intact', function (): void
{
    $csv  = g2ml_buildCsv([['Acme, Inc.', 5]], ['Name', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same('Acme, Inc.', $rows[1][0], 'A comma-containing value must survive a quote/re-parse round trip');
});

test('buildCsv: a value containing an embedded double-quote is escaped as "" and round-trips intact', function (): void
{
    $csv  = g2ml_buildCsv([['She said "hello"', 3]], ['Quote', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_true(str_contains($csv, '""'), 'An embedded double-quote must be doubled ("") somewhere in the raw CSV');
    assert_same('She said "hello"', $rows[1][0], 'The re-parsed value must exactly match the original, unescaped');
});

test('buildCsv: a value ending in a double-quote does not gain a spurious backslash', function (): void
{
    // fputcsv()'s LEGACY default $escape ("\\") is well known to insert an
    // extra backslash before a trailing enclosure character — g2ml_buildCsv()
    // disables that (empty $escape) so only RFC-4180 doubling ever applies.
    $csv  = g2ml_buildCsv([['ends with "', 1]], ['Value', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_false(str_contains($csv, '\\"'), 'No backslash-escaped quote should ever appear in the output');
    assert_same('ends with "', $rows[1][0], 'The trailing-quote value must round-trip exactly');
});

test('buildCsv: a value containing an embedded newline is quoted and round-trips as ONE field, not split across rows', function (): void
{
    $csv  = g2ml_buildCsv([["line one\nline two", 7]], ['Notes', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(2, count($rows), 'The embedded newline must NOT be mistaken for a second CSV row');
    assert_same("line one\nline two", $rows[1][0], 'The multi-line value must round-trip as a single field');
    assert_same('7', $rows[1][1], 'The second column of the same row must still be intact');
});

test('buildCsv: multiple rows with mixed comma/quote/newline values all round-trip correctly', function (): void
{
    $csv = g2ml_buildCsv(
        [
            ['plain', 1],
            ['has, a comma', 2],
            ['has "quotes"', 3],
            ["has\na newline", 4],
        ],
        ['Value', 'Clicks']
    );

    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(5, count($rows), 'Header + four data rows, none corrupted by another row\'s special characters');
    assert_same('plain', $rows[1][0]);
    assert_same('has, a comma', $rows[2][0]);
    assert_same('has "quotes"', $rows[3][0]);
    assert_same("has\na newline", $rows[4][0]);
});

// ============================================================================
// 🛡️ g2ml_buildCsv() — CSV/formula-injection defence (documented choice)
// ============================================================================
//
// A value whose FIRST character is '=', '+', '-', '@', a tab, or a carriage
// return is interpreted as a FORMULA by Excel/LibreOffice/Google Sheets, not
// as plain text — see export.php's own docblock (OWASP: CSV Injection).
// g2ml_buildCsv() prefixes such values with a leading single-quote. These
// tests assert that documented choice is actually applied — see export.php's
// docblock for the reasoning (requestReferer/browserName/osName/deviceType
// all originate from attacker-influenced request headers).
// ============================================================================

test('buildCsv: a value starting with "=" is prefixed with a single-quote (CSV-injection defence)', function (): void
{
    $csv  = g2ml_buildCsv([['=cmd|\' /C calc\'!A0', 1]], ['Value', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same("'=cmd|' /C calc'!A0", $rows[1][0], 'A leading "=" must be neutralised with a prefixed single-quote');
});

test('buildCsv: values starting with "+", "-", "@", tab, or CR are all prefixed the same way', function (): void
{
    $csv  = g2ml_buildCsv(
        [
            ['+1234', 1],
            ['-5678', 2],
            ['@SUM(A1)', 3],
            ["\tstart-tab", 4],
            ["\rstart-cr", 5],
        ],
        ['Value', 'Clicks']
    );
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same("'+1234", $rows[1][0]);
    assert_same("'-5678", $rows[2][0]);
    assert_same("'@SUM(A1)", $rows[3][0]);
    assert_same("'\tstart-tab", $rows[4][0]);
    assert_same("'\rstart-cr", $rows[5][0]);
});

test('buildCsv: an ordinary value NOT starting with a dangerous character is emitted as data, unprefixed', function (): void
{
    $csv  = g2ml_buildCsv([['g2my.link/abc', 1], ['browser=Chrome', 2]], ['Value', 'Clicks']);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same('g2my.link/abc', $rows[1][0], 'A value with no dangerous leading character must be left exactly as-is');
    assert_same('browser=Chrome', $rows[2][0], 'An "=" appearing MID-value (not leading) must not trigger the defence');
});

test('buildCsv: null and boolean cell values are coerced sensibly', function (): void
{
    $csv  = g2ml_buildCsv([[null, true, false]]);
    $rows = g2ml_export_test_parse_csv($csv);

    assert_same(['', '1', '0'], $rows[0], 'null becomes an empty string; true/false become "1"/"0"');
});

// ============================================================================
// 🏷️ g2ml_csvHumaniseHeader() — column-header formatting helper
// ============================================================================

test('csvHumaniseHeader: converts camelCase data keys into Title Case column headers', function (): void
{
    assert_same('Short Code', g2ml_csvHumaniseHeader('shortCode'));
    assert_same('Total Clicks', g2ml_csvHumaniseHeader('totalClicks'));
    assert_same('Scan Source', g2ml_csvHumaniseHeader('scanSource'));
    assert_same('Clicks', g2ml_csvHumaniseHeader('clicks'));
});

test('csvHumaniseHeader: a run of consecutive uppercase letters (an acronym) is kept as one word, casing preserved', function (): void
{
    assert_same('Unique IPs', g2ml_csvHumaniseHeader('uniqueIPs'));
});

test('csvHumaniseHeader: snake_case keys are also humanised', function (): void
{
    assert_same('Country Code', g2ml_csvHumaniseHeader('country_code'));
});

test('csvHumaniseHeader: an empty key returns an empty string', function (): void
{
    assert_same('', g2ml_csvHumaniseHeader(''));
});
