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
 * 📤 Go2My.Link — CSV Export Helpers (#44)
 * ============================================================================
 *
 * Pure CSV-formatting + thin HTTP-streaming helpers, deliberately split so the
 * formatting half is unit-testable without a request/DB context:
 *
 *   - g2ml_buildCsv()             — PURE. array of rows -> a correct
 *                                    RFC-4180 CSV string. No I/O, no
 *                                    superglobals, no headers. This is what
 *                                    tests/unit/export_test.php exercises.
 *   - g2ml_csvHumaniseHeader()    — PURE. 'shortCode' -> 'Short Code'.
 *   - g2ml_streamCsvDownload()    — NOT pure. Sends download headers + the
 *                                    CSV body for a browser download. Callers
 *                                    (e.g. analytics-export.php) build the CSV
 *                                    with g2ml_buildCsv() FIRST, then hand the
 *                                    finished string to this function.
 *
 * xlsx (Excel/PhpSpreadsheet) export is deliberately DEFERRED — see the
 * follow-up issue filed alongside #44's commit. PhpSpreadsheet is a heavy,
 * memory-hungry dependency and this app runs on Dreamhost shared hosting with
 * no Composer, so vendoring it is an owner decision, not something to bundle
 * silently as a side effect of the CSV feature.
 *
 * ----------------------------------------------------------------------------
 * CSV / formula injection (OWASP)
 * ----------------------------------------------------------------------------
 * Several analytics fields fed into these helpers ultimately derive from
 * attacker-influenced request data — requestReferer, browserName, osName, and
 * deviceType all originate from a request's Referer/User-Agent headers (see
 * web/_functions/analytics.php's own docblock). A crafted value such as
 * "=cmd|' /C calc'!A0" opened in Excel/LibreOffice/Google Sheets is
 * interpreted as a FORMULA, not as plain text — a well-known class of attack
 * ("CSV Injection"). g2ml_buildCsv() therefore prefixes any cell value whose
 * FIRST character is one of '=', '+', '-', '@', a tab, or a carriage return
 * with a leading single-quote ('), the same mitigation OWASP documents. This
 * is applied unconditionally to every data cell (never to header labels,
 * which are always our own fixed strings, never attacker input).
 * 📖 Reference: https://owasp.org/www-community/attacks/CSV_Injection
 *
 * Dependencies: none (pure PHP — fputcsv()/php://temp for the formatting
 * half; header()/echo for the streaming half).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#44)
 *
 * 📖 References:
 *     - RFC 4180 (CSV format):  https://www.rfc-editor.org/rfc/rfc4180
 *     - fputcsv():              https://www.php.net/manual/en/function.fputcsv.php
 *     - Consumer:               web/Go2My.Link/_admin/public_html/analytics-export.php
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🧱 Internal — a single cell's CSV-injection defence (see docblock above)
// ============================================================================

/**
 * Coerce a raw cell value to a string, then neutralise it against CSV/formula
 * injection by prefixing a leading single-quote when the FIRST character is
 * one of the dangerous leading characters ('=', '+', '-', '@', tab, CR).
 *
 * Internal to g2ml_buildCsv(); not part of the public contract.
 *
 * @param  mixed $value
 * @return string
 */
function _g2ml_csvSanitiseCell(mixed $value): string
{
    if ($value === null)
    {
        return '';
    }

    if (is_bool($value))
    {
        if ($value === true)
        {
            return '1';
        }

        return '0';
    }

    $stringValue = (string) $value;

    if ($stringValue === '')
    {
        return $stringValue;
    }

    $dangerousLeadingCharacters = ['=', '+', '-', '@', "\t", "\r"];
    $firstCharacter             = $stringValue[0];

    if (in_array($firstCharacter, $dangerousLeadingCharacters, true))
    {
        return "'" . $stringValue;
    }

    return $stringValue;
}

// ============================================================================
// 📄 g2ml_buildCsv() — PURE — array of rows -> an RFC-4180 CSV string
// ============================================================================

/**
 * Build a correct RFC-4180 CSV string from an array of rows.
 *
 * Every row is written via fputcsv() against an in-memory php://temp stream
 * (never a hand-rolled string-concatenation escaper), which correctly:
 *   - wraps any field containing a comma, a double-quote, or a newline in
 *     double quotes;
 *   - doubles ("") any double-quote character embedded within a field.
 *
 * fputcsv()'s legacy backslash-escape behaviour is disabled here (an empty
 * $escape argument) so quoting always follows the plain RFC-4180 doubling
 * rule above, with no extra backslash ever inserted before a trailing quote
 * character.
 *
 * @param  array<int, array<int|string, mixed>> $rows     Row data. Each row's
 *                                                          values are written
 *                                                          in array_values()
 *                                                          order (i.e. by
 *                                                          POSITION, not by
 *                                                          key) — callers
 *                                                          must build each
 *                                                          row in the SAME
 *                                                          column order as
 *                                                          $headers.
 * @param  array<int, string>                    $headers  Optional column
 *                                                          header labels,
 *                                                          written verbatim
 *                                                          as the first CSV
 *                                                          row (headers are
 *                                                          always our own
 *                                                          fixed strings, so
 *                                                          they are NOT run
 *                                                          through the
 *                                                          CSV-injection
 *                                                          cell defence).
 *                                                          Omit (or pass an
 *                                                          empty array) for a
 *                                                          headerless CSV.
 * @return string  A complete CSV document. An empty $rows with no $headers
 *                 returns an empty string.
 */
function g2ml_buildCsv(array $rows, array $headers = []): string
{
    if (count($rows) === 0 && count($headers) === 0)
    {
        return '';
    }

    $stream = fopen('php://temp', 'r+');

    if ($stream === false)
    {
        return '';
    }

    if (count($headers) > 0)
    {
        fputcsv($stream, array_values($headers), ',', '"', '');
    }

    foreach ($rows as $row)
    {
        $sanitisedRow = [];

        foreach (array_values($row) as $cellValue)
        {
            $sanitisedRow[] = _g2ml_csvSanitiseCell($cellValue);
        }

        fputcsv($stream, $sanitisedRow, ',', '"', '');
    }

    rewind($stream);

    $csv = stream_get_contents($stream);

    fclose($stream);

    if ($csv === false)
    {
        return '';
    }

    return $csv;
}

// ============================================================================
// 🏷️ g2ml_csvHumaniseHeader() — PURE — 'shortCode' -> 'Short Code'
// ============================================================================

/**
 * Convert a camelCase or snake_case data key into a human-readable column
 * header for a CSV header row — 'shortCode' -> 'Short Code', 'totalClicks' ->
 * 'Total Clicks', 'uniqueIPs' -> 'Unique IPs' (a run of consecutive uppercase
 * letters is treated as a single acronym "word" and is never itself
 * lowercased, so an acronym's own casing survives unchanged).
 *
 * @param  string $key
 * @return string
 */
function g2ml_csvHumaniseHeader(string $key): string
{
    if ($key === '')
    {
        return '';
    }

    $normalisedKey = str_replace('_', ' ', $key);

    // Insert a space at every lowercase/digit -> uppercase boundary (but NOT
    // between two consecutive uppercase letters, so an acronym such as "IPs"
    // is kept together as one word rather than split letter-by-letter).
    $spacedKey = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $normalisedKey);

    if ($spacedKey === null)
    {
        $spacedKey = $normalisedKey;
    }

    $words       = explode(' ', $spacedKey);
    $titledWords = [];

    foreach ($words as $word)
    {
        if ($word === '')
        {
            continue;
        }

        $titledWords[] = ucfirst($word);
    }

    if (count($titledWords) === 0)
    {
        return $key;
    }

    return implode(' ', $titledWords);
}

// ============================================================================
// 🧱 Internal — filename sanitisation for the Content-Disposition header
// ============================================================================

/**
 * Sanitise a caller-supplied filename for safe use inside a
 * Content-Disposition header value.
 *
 * Strips every character outside [A-Za-z0-9._-] — this alone blocks header
 * injection (no CR/LF can survive), path traversal characters (no '/' or
 * '\'), and the '"' character that could otherwise break out of the
 * filename="..." attribute. A run of the now-permitted '.' characters is
 * then collapsed to one, and any leading/trailing '.' trimmed, so a
 * traversal-shaped ".." segment cannot survive either. Finally, a single
 * ".csv" suffix is enforced.
 *
 * @param  string $filename
 * @return string  Always non-empty and always ends in exactly one ".csv".
 */
function _g2ml_csvSafeFilename(string $filename): string
{
    $stripped = preg_replace('/[^A-Za-z0-9._-]/', '', $filename);

    if ($stripped === null)
    {
        $stripped = '';
    }

    $stripped = preg_replace('/\.{2,}/', '.', $stripped);

    if ($stripped === null)
    {
        $stripped = '';
    }

    $stripped = trim($stripped, '.');

    if ($stripped === '')
    {
        $stripped = 'export';
    }

    if (!str_ends_with(strtolower($stripped), '.csv'))
    {
        $stripped = $stripped . '.csv';
    }

    return $stripped;
}

// ============================================================================
// 📥 g2ml_streamCsvDownload() — send download headers + the CSV body
// ============================================================================

/**
 * Stream a pre-built CSV string to the browser as a file download.
 *
 * NOT pure — this function calls header() and echoes output. Build the CSV
 * with g2ml_buildCsv() first; this function only sends it.
 *
 * A buffered string (rather than a true chunked/incremental writer) is fine
 * at this application's current data scale — every caller's row count is
 * bounded by the same clamped LIMIT (max 100) that every #41 analytics
 * aggregate function already enforces (see analytics.php's
 * _g2ml_analyticsClampLimit()). If exports grow to genuinely large row
 * counts in future, this function's body can be replaced with a
 * fputcsv()-per-row writer directly against php://output — no caller or
 * signature change would be required.
 *
 * @param  string $csv       The complete CSV document (e.g. from g2ml_buildCsv()).
 * @param  string $filename  A caller-supplied filename hint (sanitised here —
 *                            see _g2ml_csvSafeFilename()).
 * @return void
 */
function g2ml_streamCsvDownload(string $csv, string $filename): void
{
    $safeFilename = _g2ml_csvSafeFilename($filename);

    // A UTF-8 BOM prefix — without it, Excel on Windows guesses the file's
    // codepage rather than reading it as UTF-8, and any non-ASCII character
    // (e.g. in a requestReferer value) renders as mojibake for a large share
    // of users. Most other CSV consumers (LibreOffice, Google Sheets,
    // Python's csv module) tolerate a leading BOM without issue.
    $byteOrderMark = "\xEF\xBB\xBF";
    $payload       = $byteOrderMark . $csv;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . (string) strlen($payload));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    echo $payload;
}
