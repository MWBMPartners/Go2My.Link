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
 * 🧪 Integration tests — UTM capture + forwarding on the redirect hot path (#92)
 * ============================================================================
 *
 * Exercises the REAL, DB-backed resolveShortCode() (→ sp_lookupShortURL),
 * g2ml_appendUtmToDestination(), g2ml_extractTrackingParams(), and
 * logActivity() together, composed in the SAME sequence and gated by the SAME
 * two getSetting() checks that web/G2My.Link/public_html/index.php uses on
 * its success branch:
 *
 *   $destinationForRedirect = $destination;
 *   if (getSetting('redirect.forward_utm_params', false)) { ... g2ml_appendUtmToDestination(...) ... }
 *   ...
 *   if (getSetting('analytics.capture_tracking_params', false)) { ... g2ml_extractTrackingParams($_GET) ... }
 *   logActivity('redirect', 'success', $redirectCode, [ ..., 'logData' => $logDataForRedirect ]);
 *
 * ⚠️  COUPLING NOTE: this file intentionally mirrors index.php's composition
 * rather than executing index.php itself (index.php calls exit() and depends
 * on web/_auth_keys/auth_creds.php, which is gitignored and — on a developer
 * machine — points at a REAL local database that this suite must never touch
 * or overwrite). If index.php's gating logic around these two settings is
 * ever restructured, this file should be re-diffed against it.
 *
 * Proves the two headline guarantees from #92:
 *   (a) with BOTH settings at their default (off), the computed redirect
 *       destination is BYTE-IDENTICAL to the raw stored destinationURL, and
 *       the logged tblActivityLog row's logData is NULL — i.e. a normal
 *       redirect is completely unaffected by this feature existing;
 *   (b) with forwarding on, the link's configured UTM values are appended to
 *       (and override same-named params on) the destination;
 *   (c) with capture on, whitelisted tracking params from the incoming
 *       request are stored in logData — and are absent when capture is off,
 *       even though the identical query string was present on the request.
 *
 * Registration model mirrors activity_log_test.php / cross_org_isolation_test.php:
 * cases register at INCLUDE time using the $db handle from
 * run_integration.php's script scope. Helper names are unique
 * (g2ml_utmredirect_*) to avoid redeclaration alongside the other integration
 * files. With no reachable test DB the runner skips before this file is ever
 * included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#92)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// If it is somehow unavailable, register nothing rather than fataling.
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the SAME throwaway server the
// runner uses. Guarded so this composes with any other integration file that
// already defined these constants (require_once/definition order across
// files is not guaranteed, so every file that needs them defines its own
// guarded copy — mirrors activity_log_test.php / cross_org_isolation_test.php).
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlUtmRedirectTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlUtmRedirectTestHost === false || $g2mlUtmRedirectTestHost === '')
    {
        $g2mlUtmRedirectTestHost = '127.0.0.1';
    }

    $g2mlUtmRedirectTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlUtmRedirectTestPortRaw === false || $g2mlUtmRedirectTestPortRaw === '')
    {
        $g2mlUtmRedirectTestPortRaw = '3306';
    }

    $g2mlUtmRedirectTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlUtmRedirectTestName === false || $g2mlUtmRedirectTestName === '')
    {
        $g2mlUtmRedirectTestName = 'mwtools_Go2MyLink';
    }

    $g2mlUtmRedirectTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlUtmRedirectTestUser === false || $g2mlUtmRedirectTestUser === '')
    {
        $g2mlUtmRedirectTestUser = 'root';
    }

    $g2mlUtmRedirectTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlUtmRedirectTestPass === false)
    {
        $g2mlUtmRedirectTestPass = '';
    }

    define('DB_HOST', $g2mlUtmRedirectTestHost);
    define('DB_PORT', (int) $g2mlUtmRedirectTestPortRaw);
    define('DB_NAME', $g2mlUtmRedirectTestName);
    define('DB_USER', $g2mlUtmRedirectTestUser);
    define('DB_PASS', $g2mlUtmRedirectTestPass);
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the application code under test.
// ----------------------------------------------------------------------------
$g2mlUtmRedirectFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlUtmRedirectComponentBDir = dirname(__DIR__, 2) . '/web/G2My.Link/_functions';

require_once $g2mlUtmRedirectFunctionsDir . '/db_connect.php';
require_once $g2mlUtmRedirectFunctionsDir . '/db_query.php';
require_once $g2mlUtmRedirectFunctionsDir . '/security.php';
require_once $g2mlUtmRedirectFunctionsDir . '/settings.php';
require_once $g2mlUtmRedirectFunctionsDir . '/activity_logger.php';
require_once $g2mlUtmRedirectComponentBDir . '/redirect_resolver.php';

// ----------------------------------------------------------------------------
// Helpers.
// ----------------------------------------------------------------------------

/**
 * Run a query and abort the suite loudly if the DB rejects it — setup errors
 * are not characterised behaviour.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_utmredirect_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert (or refresh) a test short-URL row, with optional configured UTM
 * columns, using a prepared statement.
 *
 * @param  mysqli      $db
 * @param  string      $shortCode
 * @param  string      $destinationURL
 * @param  string|null $utmSource
 * @param  string|null $utmMedium
 * @param  string|null $utmCampaign
 * @param  string|null $utmTerm
 * @param  string|null $utmContent
 * @return void
 */
function g2ml_utmredirect_seed_link(mysqli $db, string $shortCode, string $destinationURL, ?string $utmSource, ?string $utmMedium, ?string $utmCampaign, ?string $utmTerm, ?string $utmContent): void
{
    $deleteStatement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ? AND `orgHandle` = ?');
    $orgHandle       = '[default]';
    mysqli_stmt_bind_param($deleteStatement, 'ss', $shortCode, $orgHandle);
    mysqli_stmt_execute($deleteStatement);
    mysqli_stmt_close($deleteStatement);

    $sql = 'INSERT INTO `tblShortURLs` '
        . '(`orgHandle`, `shortCode`, `destinationURL`, `destinationType`, `isActive`, '
        . '`utmSource`, `utmMedium`, `utmCampaign`, `utmTerm`, `utmContent`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $statement       = mysqli_prepare($db, $sql);
    $destinationType = 'url';
    $isActive        = 1;

    mysqli_stmt_bind_param(
        $statement,
        'ssssisssss',
        $orgHandle,
        $shortCode,
        $destinationURL,
        $destinationType,
        $isActive,
        $utmSource,
        $utmMedium,
        $utmCampaign,
        $utmTerm,
        $utmContent
    );

    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert failed: ' . $error);
    }

    mysqli_stmt_close($statement);
}

/**
 * Fetch the single most recent tblActivityLog row for a short-code marker.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return array<string, string|null>
 */
function g2ml_utmredirect_fetch_log(mysqli $db, string $shortCode): array
{
    $statement = mysqli_prepare(
        $db,
        'SELECT `logAction`, `logStatus`, `destinationURL`, `logData` '
        . 'FROM `tblActivityLog` WHERE `shortCode` = ? ORDER BY `logUID` DESC LIMIT 1'
    );

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);

    $result = mysqli_stmt_get_result($statement);
    $row    = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    if ($row === null)
    {
        throw new RuntimeException('Expected an activity-log row for shortCode ' . $shortCode . ' but found none');
    }

    return $row;
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier and the [default] org (idempotent — mirrors
// cross_org_isolation_test.php / shorturl_lookup_test.php), plus a single
// test short-URL row with UTM values configured on the link itself, reused
// across the scenarios below (each test reads the MOST RECENT log row for
// this shortCode immediately after its own logActivity() call, so sequential
// execution keeps each assertion scoped to the right row).
// ----------------------------------------------------------------------------
g2ml_utmredirect_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_utmredirect_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

$g2mlUtmRedirectShortCode    = 'itutmwire';
$g2mlUtmRedirectDestination  = 'https://example.com/utm-wire-destination';

g2ml_utmredirect_seed_link(
    $db,
    $g2mlUtmRedirectShortCode,
    $g2mlUtmRedirectDestination,
    'twitter',
    'social',
    'launch',
    null,
    null
);

// Ensure loadSettingsCache() has run once so getSetting() has a Default/System
// cache to fall back against (mirrors page_init.php Step 8). Both #92 settings
// were seeded OFF ('0') by web/_sql/seeds/016_utm_tracking_settings.sql.
loadSettingsCache();

// ----------------------------------------------------------------------------
// (a) BOTH settings at their seeded default (off): the redirect destination
//     must be byte-identical to the raw stored destination, and logData must
//     be NULL — even though the incoming request carries tracking params.
// ----------------------------------------------------------------------------
test('UTM redirect (#92): both settings off — destination and logData are byte-identical to pre-#92 behaviour', function () use ($db, $g2mlUtmRedirectShortCode, $g2mlUtmRedirectDestination): void
{
    setSetting('redirect.forward_utm_params', false, 'System');
    setSetting('analytics.capture_tracking_params', false, 'System');

    $incomingGet = ['utm_source' => 'visitor-value', 'utm_campaign' => 'visitor-campaign'];

    $resolved = resolveShortCode('g2my.link', $g2mlUtmRedirectShortCode);
    assert_same('success', $resolved['status'], 'The seeded link resolves successfully');
    assert_same($g2mlUtmRedirectDestination, $resolved['destination'], 'sp_lookupShortURL returns the raw stored destination');

    // Mirrors index.php's forward-gating block exactly.
    $destinationForRedirect = $resolved['destination'];

    if (getSetting('redirect.forward_utm_params', false))
    {
        $destinationForRedirect = 'THIS BRANCH MUST NOT RUN WHEN THE SETTING IS OFF';
    }

    assert_same($g2mlUtmRedirectDestination, $destinationForRedirect, 'With forwarding off, the destination is untouched — byte-identical');

    // Mirrors index.php's capture-gating block exactly.
    $logDataForRedirect = null;

    if (getSetting('analytics.capture_tracking_params', false))
    {
        $logDataForRedirect = ['trackingParams' => g2ml_extractTrackingParams($incomingGet)];
    }

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => $g2mlUtmRedirectShortCode,
        'destinationURL' => $resolved['destination'],
        'logData'        => $logDataForRedirect,
    ]);

    $row = g2ml_utmredirect_fetch_log($db, $g2mlUtmRedirectShortCode);
    assert_same($g2mlUtmRedirectDestination, $row['destinationURL'], 'The logged destinationURL is the raw stored value');
    assert_same(null, $row['logData'], 'logData must be NULL when capture is off — byte-identical to a pre-#92 redirect, even with tracking params on the incoming request');
});

// ----------------------------------------------------------------------------
// (b) Forwarding ON, capture OFF: the destination is decorated with the
//     link's configured UTM values; logData remains NULL.
// ----------------------------------------------------------------------------
test('UTM redirect (#92): forwarding on — the configured UTM values are appended to the destination', function () use ($db, $g2mlUtmRedirectShortCode, $g2mlUtmRedirectDestination): void
{
    setSetting('redirect.forward_utm_params', true, 'System');
    setSetting('analytics.capture_tracking_params', false, 'System');

    $resolved = resolveShortCode('g2my.link', $g2mlUtmRedirectShortCode);
    assert_same('success', $resolved['status'], 'The seeded link still resolves successfully');

    $destinationForRedirect = $resolved['destination'];

    if (getSetting('redirect.forward_utm_params', false))
    {
        $linkUtm = [
            'utmSource'   => $resolved['utmSource'],
            'utmMedium'   => $resolved['utmMedium'],
            'utmCampaign' => $resolved['utmCampaign'],
            'utmTerm'     => $resolved['utmTerm'],
            'utmContent'  => $resolved['utmContent'],
        ];

        $linkHasAnyUtmValue = false;

        foreach ($linkUtm as $linkUtmValue)
        {
            if ($linkUtmValue !== null && $linkUtmValue !== '')
            {
                $linkHasAnyUtmValue = true;
                break;
            }
        }

        if ($linkHasAnyUtmValue)
        {
            $destinationForRedirect = g2ml_appendUtmToDestination($resolved['destination'], $linkUtm);
        }
    }

    assert_same(
        $g2mlUtmRedirectDestination . '?utm_source=twitter&utm_medium=social&utm_campaign=launch',
        $destinationForRedirect,
        'The link own configured UTM values (twitter/social/launch) are appended to the destination'
    );

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => $g2mlUtmRedirectShortCode,
        'destinationURL' => $resolved['destination'],
        'logData'        => null,
    ]);

    $row = g2ml_utmredirect_fetch_log($db, $g2mlUtmRedirectShortCode);
    assert_same($g2mlUtmRedirectDestination, $row['destinationURL'], 'The LOGGED destinationURL stays the raw stored value — only the emitted Location header is decorated');
    assert_same(null, $row['logData'], 'logData is unaffected by the forward setting — it is governed only by the capture setting');
});

// ----------------------------------------------------------------------------
// (c) Capture ON, forwarding OFF: whitelisted tracking params from the
//     incoming request are stored in logData; the destination is untouched.
// ----------------------------------------------------------------------------
test('UTM redirect (#92): capture on — whitelisted tracking params from the request are stored in logData', function () use ($db, $g2mlUtmRedirectShortCode, $g2mlUtmRedirectDestination): void
{
    setSetting('redirect.forward_utm_params', false, 'System');
    setSetting('analytics.capture_tracking_params', true, 'System');

    $incomingGet = ['utm_source' => 'newsletter', 'gclid' => 'g-abc123', 'ignored_param' => 'x'];

    $resolved = resolveShortCode('g2my.link', $g2mlUtmRedirectShortCode);
    assert_same('success', $resolved['status'], 'The seeded link still resolves successfully');

    $destinationForRedirect = $resolved['destination'];

    if (getSetting('redirect.forward_utm_params', false))
    {
        $destinationForRedirect = 'THIS BRANCH MUST NOT RUN — forwarding is off in this scenario';
    }

    assert_same($g2mlUtmRedirectDestination, $destinationForRedirect, 'Forwarding is off in this scenario — the destination is untouched');

    $logDataForRedirect = null;

    if (getSetting('analytics.capture_tracking_params', false))
    {
        $capturedTrackingParams = g2ml_extractTrackingParams($incomingGet);

        if (count($capturedTrackingParams) > 0)
        {
            $logDataForRedirect = ['trackingParams' => $capturedTrackingParams];
        }
    }

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => $g2mlUtmRedirectShortCode,
        'destinationURL' => $resolved['destination'],
        'logData'        => $logDataForRedirect,
    ]);

    $row = g2ml_utmredirect_fetch_log($db, $g2mlUtmRedirectShortCode);
    // NOTE: tblActivityLog.logData is a native MySQL JSON column — MySQL
    // canonicalises stored JSON on read-back (a space after each ':', and
    // object keys sorted alphabetically within each object), so the expected
    // string below is MySQL's normalised form, not raw PHP json_encode()
    // output ({"utm_source":...,"gclid":...} insertion order).
    assert_same(
        '{"trackingParams": {"gclid": "g-abc123", "utm_source": "newsletter"}}',
        $row['logData'],
        'logData stores exactly the whitelisted captured params — ignored_param never appears'
    );
});

// ----------------------------------------------------------------------------
// (d) BOTH settings on: forwarding and capture operate independently and
//     simultaneously.
// ----------------------------------------------------------------------------
test('UTM redirect (#92): both settings on — forwarding and capture both apply, independently', function () use ($db, $g2mlUtmRedirectShortCode, $g2mlUtmRedirectDestination): void
{
    setSetting('redirect.forward_utm_params', true, 'System');
    setSetting('analytics.capture_tracking_params', true, 'System');

    $incomingGet = ['utm_campaign' => 'visitor-campaign-override-attempt'];

    $resolved = resolveShortCode('g2my.link', $g2mlUtmRedirectShortCode);

    $destinationForRedirect = $resolved['destination'];

    if (getSetting('redirect.forward_utm_params', false))
    {
        $linkUtm = [
            'utmSource'   => $resolved['utmSource'],
            'utmMedium'   => $resolved['utmMedium'],
            'utmCampaign' => $resolved['utmCampaign'],
            'utmTerm'     => $resolved['utmTerm'],
            'utmContent'  => $resolved['utmContent'],
        ];
        $destinationForRedirect = g2ml_appendUtmToDestination($resolved['destination'], $linkUtm);
    }

    assert_same(
        $g2mlUtmRedirectDestination . '?utm_source=twitter&utm_medium=social&utm_campaign=launch',
        $destinationForRedirect,
        'Forwarding still uses the LINK configured value, never the visitor query-string value (they are two entirely separate data paths)'
    );

    $logDataForRedirect = null;

    if (getSetting('analytics.capture_tracking_params', false))
    {
        $capturedTrackingParams = g2ml_extractTrackingParams($incomingGet);

        if (count($capturedTrackingParams) > 0)
        {
            $logDataForRedirect = ['trackingParams' => $capturedTrackingParams];
        }
    }

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => $g2mlUtmRedirectShortCode,
        'destinationURL' => $resolved['destination'],
        'logData'        => $logDataForRedirect,
    ]);

    $row = g2ml_utmredirect_fetch_log($db, $g2mlUtmRedirectShortCode);
    // See the NOTE above about MySQL's JSON canonicalisation on read-back.
    assert_same(
        '{"trackingParams": {"utm_campaign": "visitor-campaign-override-attempt"}}',
        $row['logData'],
        'Capture reflects the VISITOR own query string, independent of the link own configured forwarding values'
    );
});

// ----------------------------------------------------------------------------
// Restore both settings to their off default so this file never leaves the
// shared settings cache in a non-default state for any test that runs after
// it in the same process.
// ----------------------------------------------------------------------------
test('UTM redirect (#92): cleanup — settings are restored to off', function (): void
{
    setSetting('redirect.forward_utm_params', false, 'System');
    setSetting('analytics.capture_tracking_params', false, 'System');

    assert_same(false, getSetting('redirect.forward_utm_params', false), 'redirect.forward_utm_params is restored to off');
    assert_same(false, getSetting('analytics.capture_tracking_params', false), 'analytics.capture_tracking_params is restored to off');
});
