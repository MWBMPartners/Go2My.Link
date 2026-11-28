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
 * 🧪 Integration tests — test copies leave the live data alone (#158)
 * ============================================================================
 *
 * The alpha, beta and release-candidate copies share the live database (D-029,
 * D-032). These tests ask for real pages over HTTP, using the temporary copy of
 * the site and the web servers from debug_access_test.php, and check that only a
 * copy marked as live, on a live address, runs the clean-up or accepts a timer.
 * They also check the sign-in cookie of a test copy.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * ============================================================================
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

require_once __DIR__ . '/debug_access_test.php';
require_once dirname(__DIR__, 2) . '/web/_functions/cron.php';

/**
 * Mark the temporary copy of the site as a channel, or remove the mark ('').
 */
function g2mlTestCopies_mark(string $channel): void
{
    $file = g2mlDebugTest_site()['web'] . '/.g2ml-channel';

    if (is_file($file))
    {
        unlink($file);
    }

    if ($channel !== '')
    {
        file_put_contents($file, $channel . "\n");
    }
}

/**
 * Add one form attempt that is two days old, which any clean-up removes.
 */
function g2mlTestCopies_addOldAttempt(mysqli $db, string $marker): void
{
    $statement = $db->prepare(
        'INSERT INTO `tblFormAttempts` (`formKey`, `ipHash`, `createdAt`) '
        . "VALUES ('contact', ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY))"
    );
    $statement->bind_param('s', $marker);

    if ($statement->execute() === false)
    {
        throw new RuntimeException('Could not add a form attempt: ' . $statement->error);
    }

    $statement->close();
}

/**
 * How many form attempts carry this marker.
 */
function g2mlTestCopies_countAttempts(mysqli $db, string $marker): int
{
    $statement = $db->prepare('SELECT COUNT(*) FROM `tblFormAttempts` WHERE `ipHash` = ?');
    $statement->bind_param('s', $marker);
    $statement->execute();
    $count = (int) $statement->get_result()->fetch_row()[0];
    $statement->close();

    return $count;
}

test('test copies (#158): a visit to an alpha, beta or rc address runs no clean-up; a live visit does', function () use ($db): void
{
    $marker     = hash('sha256', 'g2ml-test-copies-158');
    $oldDivisor = getSetting('retention.probabilistic_divisor', 500);
    $oldSwitch  = getSetting('retention.enforcement_enabled', false);

    try
    {
        // Every page visit is now chosen for the clean-up.
        setSetting('retention.probabilistic_divisor', 1, 'System');
        setSetting('retention.enforcement_enabled', false, 'System');
        g2mlTestCopies_addOldAttempt($db, $marker);

        // No mark, or a test mark: nothing is cleaned up, even on a live or
        // unrecognised address.
        foreach (array('', 'alpha', 'rc') as $channel)
        {
            g2mlTestCopies_mark($channel);

            foreach (array('go2my.link', 'unknown.example.com') as $host)
            {
                $response = g2mlDebugTest_get('main', $host, '/index.php?route=about');

                assert_contains('200', $response['status'], $host);
                assert_same(1, g2mlTestCopies_countAttempts($db, $marker), 'mark "' . $channel . '" on ' . $host);
            }
        }

        // Marked live, but asked for by a test address: still nothing.
        g2mlTestCopies_mark('live');

        foreach (array('alpha.go2my.link', 'beta.go2my.link', 'rc.go2my.link', 'admin.alpha.go2my.link', 'rc.admin.go2my.link') as $host)
        {
            $response = g2mlDebugTest_get('main', $host, '/index.php?route=about');

            assert_contains('200', $response['status'], $host);
            assert_same(1, g2mlTestCopies_countAttempts($db, $marker), $host . ' must not clean up');
        }

        foreach (array('alpha', 'beta', 'rc') as $environment)
        {
            g2ml_cronMaybeRunProbabilisticRetention($environment, 'live');
            g2ml_cronMaybeRunProbabilisticRetention('production', $environment);
            g2ml_cronMaybeRunProbabilisticRetention('production', 'unknown');
            assert_same(1, g2mlTestCopies_countAttempts($db, $marker), $environment . ' must not clean up');
        }

        // The live copy on its live address proves the checks above could fail.
        $response = g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');

        assert_contains('200', $response['status']);
        assert_same(0, g2mlTestCopies_countAttempts($db, $marker), 'The live site still cleans up');
    }
    finally
    {
        g2mlTestCopies_mark('');
        setSetting('retention.probabilistic_divisor', $oldDivisor, 'System');
        setSetting('retention.enforcement_enabled', $oldSwitch, 'System');
        $db->query("DELETE FROM `tblFormAttempts` WHERE `ipHash` = '" . $db->real_escape_string($marker) . "'");
    }
});

/**
 * Store the cron token as plain, unencrypted text, so that this runner needs no
 * encryption keys. Returns the row as it was, to put back afterwards.
 *
 * @return array{settingValue: string|null, isSensitive: int}
 */
function g2mlTestCopies_swapCronToken(mysqli $db, ?string $value, int $isSensitive): array
{
    $where  = "WHERE `settingID` = 'cron.dispatch_token' AND `settingScope` = 'System'";
    $result = $db->query('SELECT `settingValue`, `isSensitive` FROM `tblSettings` ' . $where);
    $old    = $result->fetch_assoc();

    if (!is_array($old))
    {
        throw new RuntimeException('The cron.dispatch_token setting is missing (seed 020)');
    }

    $statement = $db->prepare('UPDATE `tblSettings` SET `settingValue` = ?, `isSensitive` = ? ' . $where);
    $statement->bind_param('si', $value, $isSensitive);

    if ($statement->execute() === false)
    {
        throw new RuntimeException('Could not change the cron token: ' . $statement->error);
    }

    $statement->close();

    return array('settingValue' => $old['settingValue'], 'isSensitive' => (int) $old['isSensitive']);
}

test('test copies (#158): the timed-job address refuses a correct token on a test address', function () use ($db): void
{
    $token      = bin2hex(random_bytes(32));
    $oldEnabled = getSetting('cron.enabled', false);
    $oldToken   = g2mlTestCopies_swapCronToken($db, $token, 0);

    try
    {
        setSetting('cron.enabled', true, 'System');

        $path = '/cron.php?job=retention&token=' . $token;

        foreach (array('', 'beta') as $channel)
        {
            g2mlTestCopies_mark($channel);

            $response = g2mlDebugTest_get('admin', 'admin.go2my.link', $path);

            assert_contains('404', $response['status'], 'mark "' . $channel . '"');
        }

        g2mlTestCopies_mark('live');

        foreach (array('alpha.admin.go2my.link', 'admin.beta.go2my.link', 'rc.admin.go2my.link', 'admin.rc.go2my.link') as $host)
        {
            $response = g2mlDebugTest_get('admin', $host, $path);

            assert_contains('404', $response['status'], $host);
        }

        // The live address accepting the same token proves the 404s above are
        // the test-copy refusal and not a wrong token.
        $response = g2mlDebugTest_get('admin', 'admin.go2my.link', $path);

        assert_contains('200', $response['status'], 'The live address accepts the same token');
        assert_contains('"environment": "production"', $response['body']);
    }
    finally
    {
        g2mlTestCopies_mark('');
        setSetting('cron.enabled', $oldEnabled, 'System');
        g2mlTestCopies_swapCronToken($db, $oldToken['settingValue'], $oldToken['isSensitive']);
    }
});

test('test copies (#158): a test copy sets its own Secure sign-in cookie and ignores the live one', function (): void
{
    // A browser sends the live go2my.link cookie to a test address as well.
    $context = stream_context_create(array('http' => array(
        'header'        => "Host: rc.go2my.link\r\nCookie: G2ML_SESSION=livecookievalue158\r\nX-Forwarded-Proto: https\r\n",
        'ignore_errors' => true,
        'timeout'       => 20,
    )));

    $stream = fopen('http://127.0.0.1:' . g2mlDebugTest_site()['ports']['main'] . '/index.php?route=about', 'r', false, $context);
    $meta   = stream_get_meta_data($stream);
    fclose($stream);

    $headers = implode("\n", $meta['wrapper_data']);

    assert_true(preg_match('/^Set-Cookie: G2ML_SESSION_RC=[^\n]*; secure/mi', $headers) === 1, $headers);
    assert_true(preg_match('/^Set-Cookie: G2ML_SESSION=/mi', $headers) === 0, 'The live cookie must be left alone');
});

/**
 * How many error log rows say this copy is not marked as live.
 */
function g2mlTestCopies_countWarnings(mysqli $db): int
{
    $result = $db->query("SELECT COUNT(*) FROM `tblErrorLog` WHERE `errorTitle` LIKE 'This copy is not marked as live%'");

    return (int) $result->fetch_row()[0];
}

test('test copies (#158): a live address on an unmarked copy is recorded in the error log once a day', function () use ($db): void
{
    $delete = "DELETE FROM `tblErrorLog` WHERE `errorTitle` LIKE 'This copy is not marked as live%'";

    try
    {
        $db->query($delete);

        // A marked copy, and a test address, record nothing.
        g2mlTestCopies_mark('live');
        g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');
        g2mlTestCopies_mark('');
        g2mlDebugTest_get('main', 'alpha.go2my.link', '/index.php?route=about');
        assert_same(0, g2mlTestCopies_countWarnings($db));

        // Unmarked on a live address: one row, however many visits.
        g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');
        g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');
        assert_same(1, g2mlTestCopies_countWarnings($db));

        // A day later it is said again.
        $db->query("UPDATE `tblErrorLog` SET `createdAt` = DATE_SUB(`createdAt`, INTERVAL 25 HOUR) WHERE `errorTitle` LIKE 'This copy is not marked as live%'");
        g2mlDebugTest_get('main', 'go2my.link', '/index.php?route=about');
        assert_same(2, g2mlTestCopies_countWarnings($db));
    }
    finally
    {
        g2mlTestCopies_mark('');
        $db->query($delete);
    }
});

test('test copies (#158): the cookie policy page names the session cookie in use', function (): void
{
    foreach (array('go2my.link' => 'G2ML_SESSION', 'rc.go2my.link' => 'G2ML_SESSION_RC', 'beta.go2my.link' => 'G2ML_SESSION_BETA') as $host => $name)
    {
        $response = g2mlDebugTest_get('main', $host, '/index.php?route=legal/cookies');

        assert_contains('200', $response['status'], $host);
        assert_contains('<code>' . $name . '</code>', $response['body'], $host);
    }
});
