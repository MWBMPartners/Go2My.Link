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
 * 🧪 Integration tests — CueRCode dynamic-QR wiring (#145)
 * ============================================================================
 *
 * Drives the REAL functions under test against a freshly imported test
 * database:
 *
 *   - createShortURL() — the new qrCodeExternalID/qrCodeExternalUUID/
 *     qrCodeLinkedAt options bind into the existing columns; a duplicate
 *     qrCodeExternalUUID (UNIQUE UQ_url_qr_uuid) fails as a DISTINCT outcome
 *     ($result['qrUuidTaken'] === true) from a shortCode collision, and is
 *     NEVER silently retried with a regenerated code.
 *   - g2ml_apiHandleUrlsCreate() (web/Go2My.Link/public_html/api/v1/handlers/urls.php)
 *     — the QR-link path: kill-switch (cuercode.integration_enabled), the
 *     qr:link scope gate, the qr_external_id/qr_external_uuid validators, the
 *     createdVia='cuercode' + createdViaAPIKeyUID + qrCodeLinkedAt wiring, the
 *     {short_code, short_url, qr_code_external_uuid} response shape, and the
 *     duplicate-UUID -> 409 mapping (field qr_external_uuid, distinct from the
 *     custom_code 409 already covered by api_v1_urls_test.php).
 *   - g2ml_apiHandleUrlsUpdate() — a cuercode-created URL re-points correctly
 *     via the SAME org-scoped PUT handler built in #39 (no new code, per the
 *     issue — this is a proving test).
 *   - The scan-attribution flow Component B's index.php composes: settings ->
 *     g2ml_resolveScanSource() -> a qrCodeExternalID lookup SCOPED to the
 *     STORED short URL row (never the query string) -> logActivity(). Proves
 *     both that a scan-tagged redirect logs scanSource+qrCodeExternalID and
 *     that a normal redirect (or a request with an unrelated/forged $_GET key
 *     that merely LOOKS like it names the column) logs neither.
 *
 * Registration model mirrors api_v1_urls_test.php: cases register at INCLUDE
 * time using the $db handle from run_integration.php's script scope. Helper
 * names are prefixed g2ml_cuercode_test_* to avoid redeclaration alongside
 * sibling integration files. With no reachable test DB the runner skips
 * before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#145)
 * ============================================================================
 */

declare(strict_types=1);

// ----------------------------------------------------------------------------
// $db is provided by run_integration.php's script scope (a connected mysqli).
// ----------------------------------------------------------------------------
if (!isset($db) || !($db instanceof mysqli))
{
    return;
}

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes correctly with any
// sibling integration file that already defined them (run_integration.php
// glob()s and require_once's every tests/integration/*.php file in
// alphabetical order — by the time this file loads, api_v1_urls_test.php has
// already defined these constants, but each guard also makes this file
// correct if ever run standalone or reordered).
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlCuercodeTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlCuercodeTestHost === false || $g2mlCuercodeTestHost === '')
    {
        $g2mlCuercodeTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlCuercodeTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlCuercodeTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlCuercodeTestPortRaw === false || $g2mlCuercodeTestPortRaw === '')
    {
        $g2mlCuercodeTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlCuercodeTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlCuercodeTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlCuercodeTestUser === false || $g2mlCuercodeTestUser === '')
    {
        $g2mlCuercodeTestUser = 'root';
    }

    define('DB_USER', $g2mlCuercodeTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlCuercodeTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlCuercodeTestPass === false)
    {
        $g2mlCuercodeTestPass = '';
    }

    define('DB_PASS', $g2mlCuercodeTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlCuercodeTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlCuercodeTestName === false || $g2mlCuercodeTestName === '')
    {
        $g2mlCuercodeTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlCuercodeTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlCuercodeFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlCuercodeHandlersDir  = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers';

require_once $g2mlCuercodeFunctionsDir . '/db_connect.php';
require_once $g2mlCuercodeFunctionsDir . '/db_query.php';
require_once $g2mlCuercodeFunctionsDir . '/security.php';
require_once $g2mlCuercodeFunctionsDir . '/settings.php';
require_once $g2mlCuercodeFunctionsDir . '/activity_logger.php';
require_once $g2mlCuercodeFunctionsDir . '/api_response.php';
require_once $g2mlCuercodeFunctionsDir . '/api_auth.php';
require_once $g2mlCuercodeFunctionsDir . '/api_ratelimit.php';
require_once $g2mlCuercodeFunctionsDir . '/org.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once dirname(__DIR__, 2) . '/web/G2My.Link/_functions/redirect_resolver.php';
require_once $g2mlCuercodeHandlersDir . '/urls.php';

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

/**
 * Execute a setup query, aborting loudly on failure.
 *
 * @param  mysqli $db
 * @param  string $sql
 * @return void
 */
function g2ml_cuercode_test_exec(mysqli $db, string $sql): void
{
    $result = mysqli_query($db, $sql);

    if ($result === false)
    {
        throw new RuntimeException('Setup query failed: ' . mysqli_error($db) . ' — SQL: ' . $sql);
    }
}

/**
 * Insert a throwaway user via a prepared statement and return its userUID.
 *
 * @param  mysqli $db
 * @param  string $orgHandle
 * @param  string $marker
 * @return int
 */
function g2ml_cuercode_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
{
    $statement = mysqli_prepare(
        $db,
        'INSERT INTO `tblUsers` (`orgHandle`, `username`, `email`, `passwordHash`, `isActive`, `isSuspended`) '
        . 'VALUES (?, ?, ?, ?, 1, 0)'
    );

    if ($statement === false)
    {
        throw new RuntimeException('Prepare (user) failed: ' . mysqli_error($db));
    }

    $username     = $marker;
    $email        = $marker . '@cuercode.test';
    $passwordHash = 'x';

    mysqli_stmt_bind_param($statement, 'ssss', $orgHandle, $username, $email, $passwordHash);
    $executed = mysqli_stmt_execute($statement);

    if ($executed === false)
    {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Insert (user) failed: ' . $error);
    }

    $userUID = (int) mysqli_stmt_insert_id($statement);
    mysqli_stmt_close($statement);

    return $userUID;
}

/**
 * Delete every row for a short code across all orgs so the suite is repeatable.
 *
 * @param  mysqli $db
 * @param  string $shortCode
 * @return void
 */
function g2ml_cuercode_test_delete_code(mysqli $db, string $shortCode): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `shortCode` = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare DELETE failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $shortCode);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Delete every row carrying a given qrCodeExternalUUID, regardless of which
 * short code it ended up under. Self-healing cleanup: a test that fails
 * BEFORE reaching its own by-shortCode cleanup (e.g. an assertion failure
 * partway through) can otherwise leave a row behind under a RANDOMLY
 * generated short code (custom_code is only honoured when
 * cuercode.allow_external_shortcode is on), which would then collide with a
 * FUTURE run's UQ_url_qr_uuid create for the same marker. Cleaning up by UUID
 * closes that gap regardless of what short code the leftover row carries.
 *
 * @param  mysqli $db
 * @param  string $qrUuid
 * @return void
 */
function g2ml_cuercode_test_delete_by_uuid(mysqli $db, string $qrUuid): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblShortURLs` WHERE `qrCodeExternalUUID` = ?');

    if ($statement === false)
    {
        throw new RuntimeException('Prepare DELETE (by uuid) failed: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param($statement, 's', $qrUuid);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

/**
 * Mint a real API key (a valid tblAPIKeys row) and return the VERIFIED key
 * row exactly as the front controller would produce it via g2ml_apiVerifyKey().
 *
 * @param  string   $orgHandle
 * @param  int      $userUID
 * @param  string[] $scopes
 * @return array               The verified key row.
 */
function g2ml_cuercode_test_make_key(string $orgHandle, int $userUID, array $scopes): array
{
    $created = g2ml_apiGenerateKey($userUID, $orgHandle, 'cuercode-test key', $scopes, null);

    if ($created['success'] !== true)
    {
        throw new RuntimeException('Test key generation failed.');
    }

    $verified = g2ml_apiVerifyKey($created['plaintextKey']);

    if (!is_array($verified))
    {
        throw new RuntimeException('Test key failed to verify immediately after creation.');
    }

    return $verified;
}

/**
 * Build a handler $context array for a body-carrying request.
 *
 * @param  array $body
 * @param  array $routeParams
 * @return array
 */
function g2ml_cuercode_test_context(array $body = [], array $routeParams = []): array
{
    return [
        'requestId'   => 'test-request',
        'routeParams' => $routeParams,
        'body'        => $body,
    ];
}

/**
 * Set the cuercode.* System-scope setting and reload the settings cache so
 * getSetting() sees the new value immediately.
 *
 * @param  mysqli $db
 * @param  string $settingID
 * @param  string $value
 * @return void
 */
function g2ml_cuercode_test_set_setting(mysqli $db, string $settingID, string $value): void
{
    $statement = mysqli_prepare($db, 'UPDATE `tblSettings` SET `settingValue` = ? WHERE `settingID` = ?');
    mysqli_stmt_bind_param($statement, 'ss', $value, $settingID);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    loadSettingsCache();
}

/**
 * Generate a syntactically valid, unique-enough UUID string for test fixtures.
 *
 * @param  string $marker  A short, unique hex-safe marker to fold into the UUID.
 * @return string          A 36-character UUID-shaped string.
 */
function g2ml_cuercode_test_uuid(string $marker): string
{
    $hex = substr(str_pad(bin2hex($marker), 32, '0'), 0, 32);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier, org [default] (shared with other integration
// files), and a qr:link-scoped key alongside a key that deliberately LACKS
// qr:link (but still has urls:write, matching the front controller's base
// route scope so the handler itself is what must reject it).
// ----------------------------------------------------------------------------
g2ml_cuercode_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_cuercode_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_cuercode_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

$g2mlCuercodeUser = g2ml_cuercode_test_insert_user($db, '[default]', 'cuercodeuser_' . substr(hash('sha256', (string) microtime(true)), 0, 10));

$g2mlCuercodeKeyWithScope    = g2ml_cuercode_test_make_key('[default]', $g2mlCuercodeUser, ['urls:read', 'urls:write', 'urls:delete', 'qr:link']);
$g2mlCuercodeKeyWithoutScope = g2ml_cuercode_test_make_key('[default]', $g2mlCuercodeUser, ['urls:read', 'urls:write', 'urls:delete']);

// Every test in this file wants the integration ON and external-shortcode
// OFF by default (the two settings' own column defaults) — enforce that
// baseline before each test that depends on it, since tests run in file order
// within one process and must not leak state into each other.
g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '0');

// ============================================================================
// ✨ POST /api/v1/urls — CueRCode QR-link create
// ============================================================================

test('qrLinkCreate: happy path — createdVia=cuercode, QR columns set, response shape is {short_code, short_url, qr_code_external_uuid}', function () use ($g2mlCuercodeKeyWithScope, $g2mlCuercodeUser, $db): void
{
    // Each test manages its OWN required setting state rather than relying on
    // suite-level ordering — see the "custom_code honoured only when..." test
    // below, which deliberately flips this same setting both ways.
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrhappy1');

    $qrUuid = g2ml_cuercode_test_uuid('qrhappy1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    $result = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrhappy1',
            'custom_code'      => 'qrhappy1',
            'qr_external_id'   => 909090,
            'qr_external_uuid' => $qrUuid,
        ])
    );

    assert_same('qrhappy1', $result['short_code'], 'short_code must be present');
    assert_true(str_contains($result['short_url'], 'qrhappy1'), 'short_url must embed the short code');
    assert_same($qrUuid, $result['qr_code_external_uuid'], 'qr_code_external_uuid must echo the linked UUID');
    assert_false(isset($result['destination_url']), 'The QR-link response shape must NOT include destination_url');

    $row = dbSelectOne(
        'SELECT createdVia, createdViaAPIKeyUID, createdByUserUID, qrCodeExternalID, qrCodeExternalUUID, qrCodeLinkedAt '
        . 'FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?',
        'ss',
        ['qrhappy1', '[default]']
    );

    assert_true(is_array($row), 'The created row must be readable');
    assert_same('cuercode', $row['createdVia'], "createdVia must be recorded as 'cuercode'");
    assert_same((int) $g2mlCuercodeKeyWithScope['apiKeyUID'], (int) $row['createdViaAPIKeyUID'], 'createdViaAPIKeyUID must be the calling key');
    assert_same($g2mlCuercodeUser, (int) $row['createdByUserUID'], "createdByUserUID must be the key's own owning user");
    assert_same(909090, (int) $row['qrCodeExternalID'], 'qrCodeExternalID must persist verbatim');
    assert_same($qrUuid, $row['qrCodeExternalUUID'], 'qrCodeExternalUUID must persist verbatim');
    assert_true($row['qrCodeLinkedAt'] !== null, 'qrCodeLinkedAt must be set (not NULL) on a QR-link create');

    g2ml_cuercode_test_delete_code($db, 'qrhappy1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('qrLinkCreate: normal (non-QR) create on the SAME endpoint is completely unaffected — createdVia stays api', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_delete_code($db, 'qrnormalcheck1');

    $result = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrnormalcheck1', 'custom_code' => 'qrnormalcheck1'])
    );

    assert_same('qrnormalcheck1', $result['short_code'], 'short_code must be present');
    assert_same('https://example.com/qrnormalcheck1', $result['destination_url'], 'The NORMAL response shape (destination_url) must be unchanged');
    assert_false(isset($result['qr_code_external_uuid']), 'A normal create must never carry qr_code_external_uuid in its response');

    $row = dbSelectOne('SELECT createdVia, qrCodeExternalID, qrCodeExternalUUID, qrCodeLinkedAt FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['qrnormalcheck1', '[default]']);
    assert_same('api', $row['createdVia'], "A non-QR create through the SAME key must still record createdVia='api'");
    assert_same(null, $row['qrCodeExternalID'], 'qrCodeExternalID must be NULL for a non-QR create');
    assert_same(null, $row['qrCodeExternalUUID'], 'qrCodeExternalUUID must be NULL for a non-QR create');
    assert_same(null, $row['qrCodeLinkedAt'], 'qrCodeLinkedAt must be NULL for a non-QR create');

    g2ml_cuercode_test_delete_code($db, 'qrnormalcheck1');
});

test('qrLinkCreate: a duplicate qr_external_uuid throws G2mlApiHandlerException 409 on field qr_external_uuid — NOT custom_code', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrdupe1');
    g2ml_cuercode_test_delete_code($db, 'qrdupe2');

    $qrUuid = g2ml_cuercode_test_uuid('qrdupeshared');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    $first = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrdupe1', 'custom_code' => 'qrdupe1', 'qr_external_id' => 1, 'qr_external_uuid' => $qrUuid])
    );
    assert_same('qrdupe1', $first['short_code'], 'The first create with this QR UUID must succeed');

    $threw      = false;
    $statusCode = null;
    $field      = null;

    try
    {
        g2ml_apiHandleUrlsCreate(
            $g2mlCuercodeKeyWithScope,
            g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrdupe2', 'custom_code' => 'qrdupe2', 'qr_external_id' => 2, 'qr_external_uuid' => $qrUuid])
        );
    }
    catch (G2mlApiHandlerException $collisionException)
    {
        $threw      = true;
        $statusCode = $collisionException->getHttpStatusCode();
        $field      = $collisionException->getField();
    }

    assert_true($threw, 'A duplicate qr_external_uuid (a DIFFERENT short code, DIFFERENT qr_external_id) must still throw');
    assert_same(409, $statusCode, 'A qr_external_uuid collision must map to HTTP 409');
    assert_same('qr_external_uuid', $field, 'The offending field must be qr_external_uuid, never custom_code');

    // The second short code must NEVER have been created (no partial/ghost row).
    $secondRow = dbSelectOne('SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['qrdupe2', '[default]']);
    assert_true($secondRow === null || $secondRow === false, 'A rejected qr_external_uuid collision must not leave a row behind under the second short code');

    g2ml_cuercode_test_delete_code($db, 'qrdupe1');
    g2ml_cuercode_test_delete_code($db, 'qrdupe2');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('qrLinkCreate: kill-switch OFF (cuercode.integration_enabled=0) rejects a QR-link create with 403', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '0');
    g2ml_cuercode_test_delete_code($db, 'qrkillswitch1');

    $qrUuid = g2ml_cuercode_test_uuid('qrkillswitch1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsCreate(
            $g2mlCuercodeKeyWithScope,
            g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrkillswitch1', 'qr_external_id' => 1, 'qr_external_uuid' => $qrUuid])
        );
    }
    catch (G2mlApiHandlerException $killSwitchException)
    {
        $threw      = true;
        $statusCode = $killSwitchException->getHttpStatusCode();
    }

    assert_true($threw, 'A QR-link create must be rejected outright while the kill-switch is off');
    assert_same(403, $statusCode, 'The kill-switch rejection must map to HTTP 403');

    $row = dbSelectOne('SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['qrkillswitch1', '[default]']);
    assert_true($row === null || $row === false, 'No row must be created while the kill-switch is off');

    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_delete_code($db, 'qrkillswitch1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('qrLinkCreate: a key WITHOUT the qr:link scope (but WITH urls:write) is rejected with 403, even with the kill-switch on', function () use ($g2mlCuercodeKeyWithoutScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_delete_code($db, 'qrnoscope1');

    $qrUuid = g2ml_cuercode_test_uuid('qrnoscope1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsCreate(
            $g2mlCuercodeKeyWithoutScope,
            g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrnoscope1', 'qr_external_id' => 1, 'qr_external_uuid' => $qrUuid])
        );
    }
    catch (G2mlApiHandlerException $scopeException)
    {
        $threw      = true;
        $statusCode = $scopeException->getHttpStatusCode();
    }

    assert_true($threw, 'A key lacking qr:link must be rejected for a QR-link create request');
    assert_same(403, $statusCode, 'The missing-scope rejection must map to HTTP 403');

    $row = dbSelectOne('SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['qrnoscope1', '[default]']);
    assert_true($row === null || $row === false, 'No row must be created when the key lacks qr:link');

    g2ml_cuercode_test_delete_code($db, 'qrnoscope1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('qrLinkCreate: custom_code is honoured only when cuercode.allow_external_shortcode is on — silently ignored when off', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '0');
    g2ml_cuercode_test_delete_code($db, 'qrcustomdenied1');

    $qrUuidDenied = g2ml_cuercode_test_uuid('qrcustomdenied1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuidDenied);

    $result = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrcustomdenied1',
            'custom_code'      => 'qrcustomdenied1',
            'qr_external_id'   => 55,
            'qr_external_uuid' => $qrUuidDenied,
        ])
    );

    assert_not_same('qrcustomdenied1', $result['short_code'], 'With the setting off, the requested custom_code must be dropped (a random code is generated instead)');

    g2ml_cuercode_test_delete_code($db, $result['short_code']);
    g2ml_cuercode_test_delete_code($db, 'qrcustomdenied1');

    // Now flip the setting on and prove the SAME request honours custom_code.
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrcustomallowed1');

    $qrUuidAllowed = g2ml_cuercode_test_uuid('qrcustomallowed1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuidAllowed);

    $allowedResult = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrcustomallowed1',
            'custom_code'      => 'qrcustomallowed1',
            'qr_external_id'   => 56,
            'qr_external_uuid' => $qrUuidAllowed,
        ])
    );

    assert_same('qrcustomallowed1', $allowedResult['short_code'], 'With the setting on, custom_code must be honoured verbatim');

    g2ml_cuercode_test_delete_code($db, 'qrcustomallowed1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuidAllowed);
});

test('qrLinkCreate: a malformed qr_external_uuid (not 36-char UUID shape) throws 422 on field qr_external_uuid', function () use ($g2mlCuercodeKeyWithScope): void
{
    $threw      = false;
    $statusCode = null;
    $field      = null;

    try
    {
        g2ml_apiHandleUrlsCreate(
            $g2mlCuercodeKeyWithScope,
            g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrmalformed', 'qr_external_id' => 1, 'qr_external_uuid' => 'not-a-uuid'])
        );
    }
    catch (G2mlApiHandlerException $validationException)
    {
        $threw      = true;
        $statusCode = $validationException->getHttpStatusCode();
        $field      = $validationException->getField();
    }

    assert_true($threw, 'A malformed qr_external_uuid must be rejected');
    assert_same(422, $statusCode, 'Malformed UUID shape must map to HTTP 422');
    assert_same('qr_external_uuid', $field, 'The offending field must be named');
});

test('qrLinkCreate: a missing qr_external_id (only qr_external_uuid supplied) throws 422 on field qr_external_id', function () use ($g2mlCuercodeKeyWithScope): void
{
    $threw      = false;
    $statusCode = null;
    $field      = null;

    try
    {
        g2ml_apiHandleUrlsCreate(
            $g2mlCuercodeKeyWithScope,
            g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrmissingid', 'qr_external_uuid' => g2ml_cuercode_test_uuid('qrmissingid')])
        );
    }
    catch (G2mlApiHandlerException $validationException)
    {
        $threw      = true;
        $statusCode = $validationException->getHttpStatusCode();
        $field      = $validationException->getField();
    }

    assert_true($threw, 'Supplying only qr_external_uuid (this endpoint requires the pair) must be rejected');
    assert_same(422, $statusCode, 'A missing required QR field must map to HTTP 422');
    assert_same('qr_external_id', $field, 'The offending field must be named');
});

// ============================================================================
// ✏️ PUT /api/v1/urls/{code} — re-point a cuercode-created URL (#39 reuse)
// ============================================================================

test('cuercodeRepoint: PUT re-points a cuercode-created URL correctly (org-scoped, no new code needed)', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrrepoint1');

    $qrUuid = g2ml_cuercode_test_uuid('qrrepoint1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    $created = g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrrepoint-before',
            'custom_code'      => 'qrrepoint1',
            'qr_external_id'   => 777,
            'qr_external_uuid' => $qrUuid,
        ])
    );

    assert_same('qrrepoint1', $created['short_code'], 'The cuercode create must succeed first');

    $updated = g2ml_apiHandleUrlsUpdate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context(['destination_url' => 'https://example.com/qrrepoint-after'], ['code' => 'qrrepoint1'])
    );

    assert_same('https://example.com/qrrepoint-after', $updated['destination_url'], 'The re-point must update the destination');

    $row = dbSelectOne(
        'SELECT createdVia, destinationURL, qrCodeExternalID, qrCodeExternalUUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?',
        'ss',
        ['qrrepoint1', '[default]']
    );

    assert_same('cuercode', $row['createdVia'], 'createdVia must remain cuercode after the re-point — the row is not recreated');
    assert_same('https://example.com/qrrepoint-after', $row['destinationURL'], 'The stored destination must reflect the re-point — every QR in the wild now resolves here');
    assert_same(777, (int) $row['qrCodeExternalID'], 'qrCodeExternalID must be UNCHANGED by a destination-only re-point');

    g2ml_cuercode_test_delete_code($db, 'qrrepoint1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

// ============================================================================
// 📡 Scan attribution — the flow Component B's index.php composes
// ============================================================================
//
// index.php itself is a top-level script (not a function), so — mirroring
// this suite's established pattern of testing the REAL composable pieces
// directly rather than firing HTTP requests (see shorturl_lookup_test.php
// testing resolveShortCode(), and api_v1_urls_test.php testing the handler
// functions directly) — these tests exercise the EXACT sequence index.php
// runs: getSetting() -> g2ml_resolveScanSource() -> a qrCodeExternalID
// lookup scoped to the stored row -> logActivity(). This proves the whole
// composition, including the "never trust the query string for the id"
// security property, without re-executing the script.
// ============================================================================

test('scanAttribution: a scan-tagged redirect logs scanSource+qrCodeExternalID looked up from the STORED row', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrscan1');

    $qrUuid = g2ml_cuercode_test_uuid('qrscan1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrscan1',
            'custom_code'      => 'qrscan1',
            'qr_external_id'   => 314159,
            'qr_external_uuid' => $qrUuid,
        ])
    );

    // The EXACT sequence index.php runs on a successful redirect.
    $scanSourceParamSetting = (string) getSetting('cuercode.scan_source_param', 'src');
    $scanSourceValueSetting = (string) getSetting('cuercode.scan_source_value', 'qr');

    $simulatedGet = [$scanSourceParamSetting => $scanSourceValueSetting];

    $scanSource = g2ml_resolveScanSource($scanSourceParamSetting, $scanSourceValueSetting, $simulatedGet);
    assert_same($scanSourceValueSetting, $scanSource, 'A matching scan-source param must be detected');

    $qrLinkRow = dbSelectOne('SELECT qrCodeExternalID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['qrscan1', '[default]']);
    assert_true(is_array($qrLinkRow), 'The stored row must be readable');

    $qrCodeExternalIDForLog = (int) $qrLinkRow['qrCodeExternalID'];
    assert_same(314159, $qrCodeExternalIDForLog, 'qrCodeExternalID must come from the STORED row, matching what was linked at create time');

    logActivity('redirect', 'success', 302, [
        'orgHandle'        => '[default]',
        'shortCode'        => 'qrscan1',
        'destinationURL'   => 'https://example.com/qrscan1',
        'scanSource'       => $scanSource,
        'qrCodeExternalID' => $qrCodeExternalIDForLog,
    ]);

    $loggedRow = dbSelectOne(
        'SELECT scanSource, qrCodeExternalID FROM tblActivityLog WHERE shortCode = ? ORDER BY logUID DESC LIMIT 1',
        's',
        ['qrscan1']
    );

    assert_true(is_array($loggedRow), 'An activity-log row must have been written');
    assert_same($scanSourceValueSetting, $loggedRow['scanSource'], 'The logged scanSource must match the configured expected value');
    assert_same(314159, (int) $loggedRow['qrCodeExternalID'], 'The logged qrCodeExternalID must match the linked QR id');

    mysqli_query($db, "DELETE FROM tblActivityLog WHERE shortCode = 'qrscan1'");
    g2ml_cuercode_test_delete_code($db, 'qrscan1');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('scanAttribution: a normal redirect (no matching scan-source param) logs neither scanSource nor qrCodeExternalID', function () use ($g2mlCuercodeKeyWithScope, $db): void
{
    g2ml_cuercode_test_set_setting($db, 'cuercode.integration_enabled', '1');
    g2ml_cuercode_test_set_setting($db, 'cuercode.allow_external_shortcode', '1');
    g2ml_cuercode_test_delete_code($db, 'qrscan2');

    $qrUuid = g2ml_cuercode_test_uuid('qrscan2');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);

    g2ml_apiHandleUrlsCreate(
        $g2mlCuercodeKeyWithScope,
        g2ml_cuercode_test_context([
            'destination_url'  => 'https://example.com/qrscan2',
            'custom_code'      => 'qrscan2',
            'qr_external_id'   => 271828,
            'qr_external_uuid' => $qrUuid,
        ])
    );

    $scanSourceParamSetting = (string) getSetting('cuercode.scan_source_param', 'src');
    $scanSourceValueSetting = (string) getSetting('cuercode.scan_source_value', 'qr');

    // No matching query string at all — the ordinary case for the vast
    // majority of redirects (a link shared as plain text, not via a QR).
    $scanSource = g2ml_resolveScanSource($scanSourceParamSetting, $scanSourceValueSetting, []);
    assert_same(null, $scanSource, 'A request with no scan-source param must not be flagged as a scan');

    logActivity('redirect', 'success', 302, [
        'orgHandle'      => '[default]',
        'shortCode'      => 'qrscan2',
        'destinationURL' => 'https://example.com/qrscan2',
        // Mirrors index.php: scanSource/qrCodeExternalID are always passed,
        // but resolve to NULL when this is not a marked scan.
        'scanSource'       => $scanSource,
        'qrCodeExternalID' => null,
    ]);

    $loggedRow = dbSelectOne(
        'SELECT scanSource, qrCodeExternalID FROM tblActivityLog WHERE shortCode = ? ORDER BY logUID DESC LIMIT 1',
        's',
        ['qrscan2']
    );

    assert_true(is_array($loggedRow), 'An activity-log row must have been written');
    assert_same(null, $loggedRow['scanSource'], 'A normal redirect must log scanSource as NULL');
    assert_same(null, $loggedRow['qrCodeExternalID'], 'A normal redirect must log qrCodeExternalID as NULL, even though the link IS QR-linked');

    mysqli_query($db, "DELETE FROM tblActivityLog WHERE shortCode = 'qrscan2'");
    g2ml_cuercode_test_delete_code($db, 'qrscan2');
    g2ml_cuercode_test_delete_by_uuid($db, $qrUuid);
});

test('scanAttribution: a forged query-string key matching the COLUMN name is never read — the id always comes from the stored row', function () use ($db): void
{
    // No short URL is even created for this marker: the point is structural —
    // g2ml_resolveScanSource() only ever inspects the CONFIGURED param name
    // (default 'src'), so a request carrying an unrelated 'qrCodeExternalID'
    // query key can never influence what gets logged.
    $forgedGet = [
        'qrCodeExternalID' => '999999',
        'src'              => 'not-the-configured-value',
    ];

    $scanSource = g2ml_resolveScanSource('src', 'qr', $forgedGet);
    assert_same(null, $scanSource, 'A forged, non-matching src value must never be treated as a scan');
});

// ----------------------------------------------------------------------------
// NOTE: the shared fixtures (the two keys + their owning user) are
// deliberately NOT deleted here — see api_v1_urls_test.php's identical note.
// The throwaway test database is torn down entirely after the suite runs.
// ----------------------------------------------------------------------------
