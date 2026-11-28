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
 * ✂️ Go2My.Link — Fit Values Into Their Database Columns (#312, #331)
 * ============================================================================
 *
 * The database runs in strict mode. A value that is too long, or that is not
 * valid UTF-8 text, makes the whole INSERT fail and the row is lost. Some of
 * those rows are what a security control counts (the API rate limit, the
 * failed-sign-in backoff, the failed-login CAPTCHA, the anonymous link limit),
 * so a visitor who can make a row fail can slip past the control. Any text that
 * comes from a request must go through one of these functions before it is
 * written to a log, consent or session table.
 *
 * These functions use no database and no other file.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.2.0 (#331; first written for the error log in #312)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * The deepest nesting a value written to a JSON column may have. MariaDB, which
 * the live host runs, refuses a JSON value nested 32 or more levels deep (it
 * fails the column's own JSON_VALID check); MySQL's limit is 100. Measured on
 * MariaDB 11.4 and MySQL 8.4 (#330).
 */
const G2ML_JSON_COLUMN_MAX_DEPTH = 31;

/**
 * The longest encoded JSON, in bytes, that is stored in a log table.
 */
const G2ML_JSON_COLUMN_MAX_BYTES = 60000;

/**
 * Make a value valid UTF-8 and cut it to a number of characters (for VARCHAR
 * and CHAR columns), without splitting a multi-byte character.
 *
 * @param  string|null $value          The value to fit
 * @param  int         $maxCharacters  The column's size in characters
 * @return string|null                 The value that is safe to insert
 */
function g2ml_fitCharacters(?string $value, int $maxCharacters): ?string
{
    if ($value === null)
    {
        return null;
    }

    if (function_exists('mb_substr') && function_exists('mb_scrub'))
    {
        return mb_substr(mb_scrub($value, 'UTF-8'), 0, $maxCharacters, 'UTF-8');
    }

    // Without the mbstring extension, keep plain ASCII only.
    return substr((string) preg_replace('/[^\x00-\x7F]/', '?', $value), 0, $maxCharacters);
}

/**
 * Make a value valid UTF-8 and cut it to a number of bytes (for TEXT columns,
 * which are measured in bytes), without splitting a multi-byte character.
 *
 * @param  string|null $value     The value to fit
 * @param  int         $maxBytes  The largest number of bytes to keep
 * @return string|null            The value that is safe to insert
 */
function g2ml_fitBytes(?string $value, int $maxBytes): ?string
{
    if ($value === null)
    {
        return null;
    }

    if (function_exists('mb_strcut') && function_exists('mb_scrub'))
    {
        return mb_strcut(mb_scrub($value, 'UTF-8'), 0, $maxBytes, 'UTF-8');
    }

    return substr((string) preg_replace('/[^\x00-\x7F]/', '?', $value), 0, $maxBytes);
}

/**
 * Turn a value into text that a JSON column in a log table will accept.
 *
 * Bytes that are not valid UTF-8 are replaced. A value nested too deeply, or
 * too large, is replaced by a short note saying so. A value that cannot be
 * written as JSON for any other reason (an infinite number, for example) gives
 * null, so the column is left empty. In every case the row can still be saved.
 *
 * @param  mixed  $value  The value to store
 * @param  string $what   What the value is, for the note: for example "log data"
 * @param  string $log    Which log it is for, for the note: for example "activity log"
 * @return string|null    Valid JSON, or null when there is nothing safe to store
 */
function g2ml_encodeJSONColumn(mixed $value, string $what, string $log): ?string
{
    $encoded = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE, G2ML_JSON_COLUMN_MAX_DEPTH);

    if ($encoded === false)
    {
        if (json_last_error() === JSON_ERROR_DEPTH)
        {
            return '{"_note":"' . $what . ' omitted from ' . $log . ' (nested too deeply)"}';
        }

        error_log('[Go2My.Link] WARNING: ' . $what . ' for the ' . $log . ' could not be written as JSON (' . json_last_error_msg() . '); the row is kept without it.');

        return null;
    }

    // A JSON column must stay VALID JSON, so a value that is too long is
    // replaced whole rather than cut part of the way through.
    if (strlen($encoded) > G2ML_JSON_COLUMN_MAX_BYTES)
    {
        return '{"_note":"' . $what . ' omitted from ' . $log . ' (too large)"}';
    }

    return $encoded;
}
