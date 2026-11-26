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
 * 🚦 Go2My.Link — Public Form Rate Limiting
 * ============================================================================
 *
 * Counts attempts at the three public forms that can be abused by an
 * automated script — registration, the contact form, and forgot-password —
 * against tblFormAttempts, and refuses once a per-IP-per-hour setting is
 * reached (#212).
 *
 * WHY THIS FILE EXISTS, AND WHY NOT tblActivityLog. Registration had no rate
 * limit at all; the contact form and forgot-password each counted their own
 * limit by counting rows in tblActivityLog. That table is written by
 * logActivity(), which SKIPS most actions for a visitor who has sent Do Not
 * Track or Global Privacy Control (activity_logger.php's "always log" list
 * is a short set of security actions; neither 'contact_form' nor
 * 'password_reset_request' was on it). A visitor who simply turns on "Do Not
 * Track" was therefore never counted by either form's old check. This file
 * writes to a dedicated table instead, with no DNT check at all, so every
 * attempt counts regardless of the visitor's tracking preference — see
 * web/_sql/schema/044_form_attempts.sql for the table itself and why it
 * stores a hash of the IP address rather than the address.
 *
 * Dependencies: security.php (g2ml_getClientIP()), db_query.php (dbSelectOne(),
 *               dbInsert(), dbDelete()), the ENCRYPTION_KEY_SECONDARY constant
 *               (defined in each component's auth_creds.php, loaded before
 *               this file).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#212)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

// ============================================================================
// 📋 Whitelist
// ============================================================================

/**
 * Check whether $formKey is one of the forms this file is allowed to count
 * attempts for.
 *
 * A PURE function (no I/O) so it can be unit-tested directly. Kept as an
 * explicit whitelist, rather than trusting whatever a caller passes in,
 * because a typo'd or attacker-influenced form key must never silently start
 * a brand-new, unrelated attempt counter — see g2ml_formRateLimitTake(),
 * which refuses outright on an unrecognised key rather than counting it.
 *
 * @param  string $formKey
 * @return bool
 */
function g2ml_formRateLimitKeyIsValid(string $formKey): bool
{
    $validFormKeys = ['register', 'contact', 'password_reset'];

    return in_array($formKey, $validFormKeys, true);
}

// ============================================================================
// 🌐 Group addresses that belong to one visitor
// ============================================================================

/**
 * Reduce an IP address to the part that identifies one connection.
 *
 * An IPv6 connection is normally given a whole block of addresses (the first
 * 64 bits stay the same), so counting full addresses would let one visitor
 * start a fresh count on every request. The last 64 bits are therefore zeroed.
 * An IPv4 address written in IPv6 form (::ffff:192.0.2.1) is turned back into
 * plain IPv4. Anything that is not an IP address is returned unchanged.
 *
 * A PURE function (no I/O) so it can be unit-tested directly.
 *
 * @param  string $ip
 * @return string
 */
function g2ml_formRateLimitNormaliseIp(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false)
    {
        return $ip;
    }

    $packed = inet_pton($ip);

    if ($packed === false || strlen($packed) !== 16)
    {
        return $ip;
    }

    if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff")
    {
        return (string) inet_ntop(substr($packed, 12));
    }

    return (string) inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
}

// ============================================================================
// 🔑 Hash the visitor's IP address
// ============================================================================

/**
 * Return a keyed (HMAC) hash of the current visitor's IP address, after
 * g2ml_formRateLimitNormaliseIp().
 *
 * The key is ENCRYPTION_KEY_SECONDARY with a fixed suffix, so this hash
 * differs from a hash of the same address made for another purpose.
 *
 * If ENCRYPTION_KEY_SECONDARY is missing or empty, counting still works but
 * the hash is no longer secret: anyone holding a stored ipHash could recover
 * an IPv4 address by trying every one. That is logged once per request.
 *
 * @return string  64 lower-case hex characters (a SHA-256 HMAC digest)
 */
function g2ml_formRateLimitIpHash(): string
{
    static $loggedMissingSecret = false;

    $secret = '';

    if (defined('ENCRYPTION_KEY_SECONDARY'))
    {
        $secret = (string) ENCRYPTION_KEY_SECONDARY;
    }

    if ($secret === '' && !$loggedMissingSecret)
    {
        error_log('[Go2My.Link] ERROR: ENCRYPTION_KEY_SECONDARY is missing or empty, so the stored form-attempt hashes are not secret until it is set.');
        $loggedMissingSecret = true;
    }

    return hash_hmac('sha256', g2ml_formRateLimitNormaliseIp(g2ml_getClientIP()), $secret . '|form-attempt');
}

// ============================================================================
// 🔢 Count recent attempts
// ============================================================================

/**
 * Count how many attempts at $formKey the current visitor has made within the
 * last $windowSeconds.
 *
 * @param  string $formKey       One of g2ml_formRateLimitKeyIsValid()'s keys
 * @param  int    $windowSeconds How far back to count, in seconds
 * @return int|null              The count, or null on a database fault
 */
function g2ml_formRateLimitCount(string $formKey, int $windowSeconds): ?int
{
    $countRow = dbSelectOne(
        "SELECT COUNT(*) AS `attemptCount`
         FROM `tblFormAttempts`
         WHERE `formKey` = ?
           AND `ipHash` = ?
           AND `createdAt` >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? SECOND)",
        'ssi',
        [$formKey, g2ml_formRateLimitIpHash(), $windowSeconds]
    );

    if ($countRow === false || $countRow === null)
    {
        return null;
    }

    return (int) $countRow['attemptCount'];
}

// ============================================================================
// 🚦 Take one attempt
// ============================================================================

/**
 * Ask for permission to make one attempt at $formKey, and record it if allowed.
 *
 * The count and the insert happen while holding a database lock named after
 * this visitor and form, so requests sent at the same moment are handled one
 * at a time and cannot all see the same free place.
 *
 * Anything that goes wrong (unknown form, no lock, failed read or write) gives
 * 'unavailable', and the caller must refuse. Refused attempts are not recorded.
 *
 * Rows older than a day are never counted. Up to 100 of them are deleted each
 * time an attempt is recorded, so on a quiet site an old row stays until the
 * next submission of any of these forms. This avoids needing a scheduled job
 * (#178).
 *
 * @param  string $formKey    One of g2ml_formRateLimitKeyIsValid()'s keys
 * @param  int    $maxPerHour The limit from the matching System setting; 0 or
 *                            less means no limit, and nothing is recorded
 * @return string             'allowed', 'limited' or 'unavailable'
 */
#[\NoDiscard('the form must be refused unless this says allowed')]
function g2ml_formRateLimitTake(string $formKey, int $maxPerHour): string
{
    if (!g2ml_formRateLimitKeyIsValid($formKey))
    {
        error_log('[Go2My.Link] ERROR: g2ml_formRateLimitTake() called with an unrecognised formKey "' . $formKey . '".');

        return 'unavailable';
    }

    if ($maxPerHour <= 0)
    {
        return 'allowed';
    }

    $ipHash   = g2ml_formRateLimitIpHash();
    $lockName = 'g2ml_form_' . substr(hash('sha256', $formKey . '|' . $ipHash), 0, 40);

    $lockRow = dbSelectOne('SELECT GET_LOCK(?, 5) AS `gotLock`', 's', [$lockName]);

    if (!is_array($lockRow) || (int) $lockRow['gotLock'] !== 1)
    {
        error_log('[Go2My.Link] ERROR: g2ml_formRateLimitTake() could not get its database lock for "' . $formKey . '".');

        return 'unavailable';
    }

    try
    {
        $currentCount = g2ml_formRateLimitCount($formKey, 3600);

        if ($currentCount === null)
        {
            return 'unavailable';
        }

        if ($currentCount >= $maxPerHour)
        {
            return 'limited';
        }

        $inserted = dbInsert(
            "INSERT INTO `tblFormAttempts` (`formKey`, `ipHash`) VALUES (?, ?)",
            'ss',
            [$formKey, $ipHash]
        );

        if ($inserted === false)
        {
            return 'unavailable';
        }

        dbDelete(
            "DELETE FROM `tblFormAttempts` WHERE `createdAt` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) LIMIT 100"
        );

        return 'allowed';
    }
    finally
    {
        dbSelectOne('SELECT RELEASE_LOCK(?) AS `released`', 's', [$lockName]);
    }
}
