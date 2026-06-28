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
 * 🧪 Regression tests — g2ml_getClientIP() trusted-proxy hardening (F-001 / #95)
 * ============================================================================
 *
 * Pins the post-fix behaviour of g2ml_getClientIP() and its helpers:
 *
 *   - With NO TRUSTED_PROXIES configured (the Dreamhost default), forwarded
 *     headers (X-Forwarded-For, X-Real-Ip) are IGNORED and REMOTE_ADDR is
 *     returned. This is the security fix: a client cannot poison the activity
 *     log or evade per-IP rate limits by forging headers.
 *
 *   - With TRUSTED_PROXIES set to include REMOTE_ADDR, the RIGHT-most
 *     untrusted X-Forwarded-For entry is returned (validated). An invalid
 *     forwarded value falls back to REMOTE_ADDR.
 *
 * TRUSTED_PROXIES is a real PHP constant (define), which cannot be redefined
 * within one process. The "no constant defined" cases run inline in THIS
 * process (the runner never defines TRUSTED_PROXIES). The "constant defined"
 * cases run in short child PHP processes so each gets a clean constant state;
 * the helper g2ml_clientip_child() shells out to PHP for those.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/../web/_functions/security.php';

/**
 * Run g2ml_getClientIP() in a clean child PHP process with a chosen
 * TRUSTED_PROXIES value and request server vars, returning the printed result.
 *
 * Running in a child process is what lets us exercise different TRUSTED_PROXIES
 * values without colliding with PHP's one-time define() semantics.
 *
 * @param  string|null $trustedProxies  Value for TRUSTED_PROXIES, or null to leave it undefined.
 * @param  string      $remoteAddr      REMOTE_ADDR to set.
 * @param  string|null $xff             X-Forwarded-For to set, or null to omit.
 * @param  string|null $xRealIp         X-Real-Ip to set, or null to omit.
 * @return string                       The trimmed stdout (the resolved IP).
 */
function g2ml_clientip_child(?string $trustedProxies, string $remoteAddr, ?string $xff, ?string $xRealIp): string
{
    $securityPath = dirname(__DIR__, 1) . '/../web/_functions/security.php';

    $script  = '<?php' . "\n";
    $script .= 'declare(strict_types=1);' . "\n";
    // Satisfy the direct-access guard inside security.php.
    $script .= '$_SERVER["SCRIPT_FILENAME"] = __FILE__;' . "\n";

    if ($trustedProxies !== null)
    {
        $script .= 'define("TRUSTED_PROXIES", ' . var_export($trustedProxies, true) . ');' . "\n";
    }

    $script .= 'require ' . var_export($securityPath, true) . ';' . "\n";
    $script .= '$_SERVER["REMOTE_ADDR"] = ' . var_export($remoteAddr, true) . ';' . "\n";

    if ($xff !== null)
    {
        $script .= '$_SERVER["HTTP_X_FORWARDED_FOR"] = ' . var_export($xff, true) . ';' . "\n";
    }
    else
    {
        $script .= 'unset($_SERVER["HTTP_X_FORWARDED_FOR"]);' . "\n";
    }

    if ($xRealIp !== null)
    {
        $script .= '$_SERVER["HTTP_X_REAL_IP"] = ' . var_export($xRealIp, true) . ';' . "\n";
    }
    else
    {
        $script .= 'unset($_SERVER["HTTP_X_REAL_IP"]);' . "\n";
    }

    $script .= 'echo g2ml_getClientIP();' . "\n";

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_clientip_');

    if ($tempFile === false)
    {
        return '';
    }

    file_put_contents($tempFile, $script);

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>/dev/null';
    $output  = shell_exec($command);

    unlink($tempFile);

    if ($output === null || $output === false)
    {
        return '';
    }

    return trim($output);
}

// ----------------------------------------------------------------------------
// No TRUSTED_PROXIES configured (runs inline — runner never defines it).
// This is the Dreamhost default and the heart of the F-001 fix.
// ----------------------------------------------------------------------------

test('g2ml_getClientIP: no TRUSTED_PROXIES → forged X-Forwarded-For is IGNORED (returns REMOTE_ADDR)', function (): void
{
    assert_false(defined('TRUSTED_PROXIES'), 'Test process must not define TRUSTED_PROXIES');

    $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
    unset($_SERVER['HTTP_X_REAL_IP']);

    assert_same('203.0.113.9', g2ml_getClientIP(), 'Forged XFF must not override REMOTE_ADDR');
});

test('g2ml_getClientIP: no TRUSTED_PROXIES → forged X-Real-Ip is IGNORED (returns REMOTE_ADDR)', function (): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    $_SERVER['HTTP_X_REAL_IP'] = '9.9.9.9';

    assert_same('203.0.113.9', g2ml_getClientIP(), 'Forged X-Real-Ip must not override REMOTE_ADDR');
});

test('g2ml_getClientIP: no TRUSTED_PROXIES → rotating XFF cannot evade per-IP rate limits', function (): void
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    unset($_SERVER['HTTP_X_REAL_IP']);

    $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
    $first = g2ml_getClientIP();

    $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.250';
    $second = g2ml_getClientIP();

    assert_same('203.0.113.9', $first, 'First rotated XFF ignored');
    assert_same($first, $second, 'Both requests resolve to the same genuine peer');
});

test('g2ml_getClientIP: missing REMOTE_ADDR falls back to 0.0.0.0', function (): void
{
    unset($_SERVER['REMOTE_ADDR']);
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    unset($_SERVER['HTTP_X_REAL_IP']);

    assert_same('0.0.0.0', g2ml_getClientIP(), 'Absent REMOTE_ADDR yields the sentinel');

    // Restore a sane value for any later tests.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
});

// ----------------------------------------------------------------------------
// CIDR / IP range helper (inline — pure, no constant dependency).
// ----------------------------------------------------------------------------

test('g2ml_ipInRange: exact IPv4 match', function (): void
{
    assert_true(g2ml_ipInRange('203.0.113.9', '203.0.113.9'), 'Exact address matches');
});

test('g2ml_ipInRange: exact IPv4 non-match', function (): void
{
    assert_false(g2ml_ipInRange('203.0.113.9', '203.0.113.10'), 'Different address does not match');
});

test('g2ml_ipInRange: IPv4 inside a /24 CIDR', function (): void
{
    assert_true(g2ml_ipInRange('10.0.0.42', '10.0.0.0/24'), 'Address within the /24 matches');
});

test('g2ml_ipInRange: IPv4 outside a /24 CIDR', function (): void
{
    assert_false(g2ml_ipInRange('10.0.1.42', '10.0.0.0/24'), 'Address outside the /24 does not match');
});

test('g2ml_ipInRange: non-byte-aligned /20 prefix', function (): void
{
    assert_true(g2ml_ipInRange('10.0.15.255', '10.0.0.0/20'), '10.0.15.255 is inside 10.0.0.0/20');
    assert_false(g2ml_ipInRange('10.0.16.0', '10.0.0.0/20'), '10.0.16.0 is outside 10.0.0.0/20');
});

test('g2ml_ipInRange: /0 matches any same-family address', function (): void
{
    assert_true(g2ml_ipInRange('8.8.8.8', '0.0.0.0/0'), 'A /0 matches everything in the family');
});

test('g2ml_ipInRange: IPv4 vs IPv6 families never match', function (): void
{
    assert_false(g2ml_ipInRange('203.0.113.9', '::1'), 'IPv4 candidate vs IPv6 range');
    assert_false(g2ml_ipInRange('::1', '203.0.113.0/24'), 'IPv6 candidate vs IPv4 range');
});

test('g2ml_ipInRange: IPv6 inside a /64 CIDR', function (): void
{
    assert_true(g2ml_ipInRange('2001:db8::1', '2001:db8::/32'), 'IPv6 within the /32 matches');
});

test('g2ml_ipInRange: malformed inputs return false', function (): void
{
    assert_false(g2ml_ipInRange('not-an-ip', '10.0.0.0/24'), 'Bad candidate');
    assert_false(g2ml_ipInRange('10.0.0.1', '10.0.0.0/notaprefix'), 'Bad prefix');
    assert_false(g2ml_ipInRange('', '10.0.0.0/24'), 'Empty candidate');
});

// ----------------------------------------------------------------------------
// TRUSTED_PROXIES configured (runs in clean child processes).
// ----------------------------------------------------------------------------

test('g2ml_getClientIP: TRUSTED_PROXIES includes REMOTE_ADDR → returns validated forwarded IP', function (): void
{
    $result = g2ml_clientip_child('203.0.113.9', '203.0.113.9', '1.2.3.4', null);
    assert_same('1.2.3.4', $result, 'A trusted peer lets the forwarded client IP through');
});

test('g2ml_getClientIP: trusted CIDR proxy → right-most untrusted XFF entry is chosen', function (): void
{
    // Chain: real client 1.2.3.4, then through trusted proxies 10.0.0.7 and 10.0.0.8.
    // REMOTE_ADDR (10.0.0.8) is trusted; the right-most non-trusted entry is 1.2.3.4.
    $result = g2ml_clientip_child('10.0.0.0/24', '10.0.0.8', '1.2.3.4, 10.0.0.7, 10.0.0.8', null);
    assert_same('1.2.3.4', $result, 'Right-most untrusted hop is the genuine client');
});

test('g2ml_getClientIP: trusted proxy but invalid forwarded value → falls back to REMOTE_ADDR', function (): void
{
    $result = g2ml_clientip_child('203.0.113.9', '203.0.113.9', 'garbage-not-an-ip', null);
    assert_same('203.0.113.9', $result, 'Invalid forwarded value is rejected; peer address used');
});

test('g2ml_getClientIP: trusted proxy but NO forwarded header → returns REMOTE_ADDR', function (): void
{
    $result = g2ml_clientip_child('203.0.113.9', '203.0.113.9', null, null);
    assert_same('203.0.113.9', $result, 'No XFF means the trusted peer address is used');
});

test('g2ml_getClientIP: REMOTE_ADDR not in TRUSTED_PROXIES → forged XFF still ignored', function (): void
{
    // Constant is defined, but the peer is not in it, so headers must be ignored.
    $result = g2ml_clientip_child('192.168.1.0/24', '203.0.113.9', '1.2.3.4', null);
    assert_same('203.0.113.9', $result, 'Untrusted peer cannot have its forwarded header believed');
});
