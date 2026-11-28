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
 * 🌍 Go2My.Link — Which copy of the site is running? (#158)
 * ============================================================================
 *
 * The live site and the three test copies (alpha, beta and release-candidate)
 * run the same code and share one database (D-029, D-032). Two things tell them
 * apart.
 *
 * 1. WHERE THE CODE RUNS FROM (the "channel"). A test copy on the server sits in
 *    a folder named alpha, beta or rc. Otherwise the file .g2ml-channel at the
 *    top of the copy (beside _functions/) names it: live, alpha, beta or rc.
 *    The upload writes that file for every copy; the installer writes "live" on
 *    a new installation that has none. A copy with no such file is "unknown".
 *    ONLY a copy known to be live may run timed jobs and clean-ups, so anything
 *    unrecognised does nothing to the data.
 *
 * 2. THE WEB ADDRESS (the "environment"), used for debug output and the sign-in
 *    cookie when the folder does not already say "test copy". Test addresses
 *    exist only on our own domains (go2my.link, g2my.link, lnks.page) and on
 *    local names (localhost, *.local): one part of the name must be exactly
 *    "alpha", "beta" or "rc". An organisation's own domain is always live,
 *    whatever it starts with.
 *
 * No dependencies.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @since      v1.0.0 — Launch Hardening (#158)
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
 * Work out the environment from the host name a request was sent to.
 *
 * @param  string $hostname  The Host header, which may carry a port number.
 * @return string            'alpha', 'beta', 'rc', 'local' or 'production'.
 */
function g2ml_environmentForHost(string $hostname): string
{
    // The name on its own: lower case, no port number, no trailing dot.
    $name = strtolower(trim($hostname));
    $name = (string) preg_replace('/:[0-9]+$/', '', $name);
    $name = rtrim($name, '.');

    // The parts of the name that may mark a test copy: those in front of one of
    // our own domains, or of a local name. Any other domain has none.
    $testPart = '';

    foreach (['go2my.link', 'g2my.link', 'lnks.page', 'localhost', 'local'] as $ownDomain)
    {
        $suffix = '.' . $ownDomain;

        if (str_ends_with($name, $suffix))
        {
            $testPart = substr($name, 0, -strlen($suffix));
            break;
        }
    }

    $labels = explode('.', $testPart);

    foreach (['alpha', 'beta', 'rc'] as $testName)
    {
        if (in_array($testName, $labels, true))
        {
            return $testName;
        }
    }

    // Local addresses are matched exactly as sent: one with a port number is
    // treated as the live site (see docs/DEPLOYMENT.md).
    if (in_array($hostname, ['localhost', '127.0.0.1', '::1'], true))
    {
        return 'local';
    }

    if (preg_match('/\.local$/', $hostname) === 1)
    {
        return 'local';
    }

    return 'production';
}

/**
 * Whether the environment is one of the test copies on the server.
 *
 * @param  string $environment  The value of g2ml_getEnvironment().
 * @return bool
 */
function g2ml_isTestEnvironment(string $environment): bool
{
    return in_array($environment, ['alpha', 'beta', 'rc'], true);
}

/**
 * Which copy of the site is in this folder, judged only by where the code is.
 *
 * @param  string $root  The copy's top folder (web/ in the repository).
 * @return string        'live', 'alpha', 'beta', 'rc' or 'unknown'.
 */
function g2ml_copyChannel(string $root): string
{
    // A folder named after a test copy wins over anything written inside it.
    $folder = basename($root);

    if (g2ml_isTestEnvironment($folder))
    {
        return $folder;
    }

    $markerFile = $root . DIRECTORY_SEPARATOR . '.g2ml-channel';

    if (!is_file($markerFile) || !is_readable($markerFile))
    {
        return 'unknown';
    }

    $channel = trim((string) file_get_contents($markerFile));

    if ($channel === 'live' || g2ml_isTestEnvironment($channel))
    {
        return $channel;
    }

    return 'unknown';
}

/**
 * The channel of the copy that is running now. page_init.php sets
 * G2ML_COPY_CHANNEL; without it the folder holding this file is examined.
 *
 * @return string
 */
function g2ml_currentCopyChannel(): string
{
    if (defined('G2ML_COPY_CHANNEL'))
    {
        return (string) constant('G2ML_COPY_CHANNEL');
    }

    return g2ml_copyChannel(dirname(__DIR__));
}

/**
 * The environment to use: a test copy stays a test copy whatever address it is
 * reached by; otherwise the address decides.
 *
 * @param  string $channel   From g2ml_copyChannel().
 * @param  string $hostname  The Host header.
 * @return string            'alpha', 'beta', 'rc', 'local' or 'production'.
 */
function g2ml_resolveEnvironment(string $channel, string $hostname): string
{
    if (g2ml_isTestEnvironment($channel))
    {
        return $channel;
    }

    return g2ml_environmentForHost($hostname);
}

/**
 * Whether timed jobs and clean-ups may change data. Both must agree: the copy is
 * known to be the live one, and the address is not a test address.
 *
 * @param  string $channel      From g2ml_copyChannel().
 * @param  string $environment  From g2ml_resolveEnvironment().
 * @return bool
 */
function g2ml_jobsAllowed(string $channel, string $environment): bool
{
    if ($channel !== 'live')
    {
        return false;
    }

    return !g2ml_isTestEnvironment($environment);
}

/**
 * g2ml_jobsAllowed() for the copy and request that are running now.
 *
 * @return bool
 */
function g2ml_cleanupAllowed(): bool
{
    $environment = 'production';

    if (function_exists('g2ml_getEnvironment'))
    {
        $environment = g2ml_getEnvironment();
    }

    return g2ml_jobsAllowed(g2ml_currentCopyChannel(), $environment);
}

/**
 * The name of the sign-in session cookie. Each test copy has its own, because a
 * browser also sends the live site's go2my.link cookie to addresses under
 * go2my.link; with one shared name the two would overwrite each other.
 *
 * @param  string $environment  From g2ml_resolveEnvironment().
 * @return string
 */
function g2ml_sessionCookieName(string $environment): string
{
    if (g2ml_isTestEnvironment($environment))
    {
        return 'G2ML_SESSION_' . strtoupper($environment);
    }

    return 'G2ML_SESSION';
}

/**
 * Whether the sign-in cookie is marked "Secure" (sent over HTTPS only). Always
 * on the live site; on a test copy whenever the request came over HTTPS; never
 * on a local address.
 *
 * @param  string $environment  From g2ml_resolveEnvironment().
 * @param  array  $server       The $_SERVER array.
 * @return bool
 */
function g2ml_sessionCookieSecure(string $environment, array $server): bool
{
    if ($environment === 'production')
    {
        return true;
    }

    if (!g2ml_isTestEnvironment($environment))
    {
        return false;
    }

    $https = strtolower((string) ($server['HTTPS'] ?? ''));

    if ($https !== '' && $https !== 'off')
    {
        return true;
    }

    return (($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Whether an address is one of the live site's own addresses. Used where a copy
 * must be positively recognised as live; anything else is not.
 *
 * @param  string $hostname  The Host header, which may carry a port number.
 * @return bool
 */
function g2ml_isLiveAddress(string $hostname): bool
{
    $name = strtolower(trim($hostname));
    $name = (string) preg_replace('/:[0-9]+$/', '', $name);
    $name = rtrim($name, '.');

    $liveNames = [
        'go2my.link', 'www.go2my.link', 'admin.go2my.link',
        'g2my.link', 'www.g2my.link',
        'lnks.page', 'www.lnks.page',
    ];

    return in_array($name, $liveNames, true);
}

/**
 * The installer's last step: write .g2ml-channel containing "live" on a copy
 * that nothing identifies yet, but only when it is clearly the live site: its
 * folder is named "live", or the installer was opened on a live address.
 *
 * @param  string $copyRoot  The copy's top folder.
 * @param  string $hostname  The Host header of the installer request.
 * @return string            '' when nothing needs saying, otherwise a notice
 *                           for the person installing.
 */
function g2ml_installerMarkCopy(string $copyRoot, string $hostname): string
{
    if (g2ml_copyChannel($copyRoot) !== 'unknown')
    {
        return '';
    }

    if (basename($copyRoot) !== 'live' && !g2ml_isLiveAddress($hostname))
    {
        return 'web/.g2ml-channel was not written, because this is neither a folder named "live" nor a live address. '
            . 'If this is the live site, create that file containing the single word live; without it, timed jobs '
            . 'and clean-ups (including account erasure and data retention) never run.';
    }

    $markerFile = $copyRoot . DIRECTORY_SEPARATOR . '.g2ml-channel';

    if (@file_put_contents($markerFile, "live\n") === false)
    {
        return 'Could not write web/.g2ml-channel. Create it containing the single word live, or timed jobs and '
            . 'clean-ups (including account erasure and data retention) will not run.';
    }

    @chmod($markerFile, 0644);

    return '';
}
