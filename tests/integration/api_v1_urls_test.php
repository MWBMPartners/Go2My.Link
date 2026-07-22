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
 * 🧪 Integration tests — Public API v1 urls CRUD/bulk, org read, backoff (#39)
 * ============================================================================
 *
 * Drives the REAL handler functions from
 * web/Go2My.Link/public_html/api/v1/handlers/{urls,urls_bulk,org}.php against
 * a freshly imported test database, covering:
 *
 *   - g2ml_apiHandleUrlsCreate() happy path, including that the created row
 *     carries createdVia='api' + createdViaAPIKeyUID = the calling key.
 *   - createShortURL()'s createdVia/createdViaAPIKeyUID extension defaults to
 *     'web'/NULL for a caller that never supplies them (regression guard —
 *     every pre-#39 caller must be unaffected).
 *   - Alias collision -> G2mlApiHandlerException(409).
 *   - g2ml_apiHandleUrlsList() cursor pagination (?limit=/?after=).
 *   - g2ml_apiHandleUrlsGet()/Update()/Delete() — ORG-SCOPED BOLA guard: a key
 *     for org A gets 404 (never a different status/shape) for org B's code.
 *   - Delete is idempotent (repeating it on an already-inactive link in the
 *     SAME org still succeeds).
 *   - g2ml_apiHandleUrlsBulkCreate() partial failure (mixed success/failure
 *     rows in one request).
 *   - g2ml_apiHandleOrgRead() — self-scoped, no client-suppliable identifier.
 *   - g2ml_apiCheckPreAuthBackoff() — trips 429-eligible once the configured
 *     401-per-minute threshold for an IP is reached, and the limit=0 escape
 *     hatch disables the guard entirely.
 *
 * Handler functions are called DIRECTLY (not over HTTP) — the front
 * controller's own dispatch (route matching, scope enforcement, the 400/401/
 * 403/404 short-circuits) is covered separately: the parameterised route
 * matcher by tests/unit/api_v1_routing_test.php (pure function), and key
 * verification/scopes/rate-limiting by tests/integration/api_key_test.php
 * (#38). This mirrors the established pattern in this suite (e.g.
 * cross_org_isolation_test.php drives createShortURL() directly rather than
 * the dashboard page that calls it).
 *
 * Registration model mirrors cross_org_isolation_test.php: cases register at
 * INCLUDE time using the $db handle from run_integration.php's script scope.
 * Helper names are prefixed g2ml_apiurls_test_* to avoid redeclaration
 * alongside sibling integration files. With no reachable test DB the runner
 * skips before this file is ever included.
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.1.0 — Phase 7 (#39)
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
// Align this raw connection's session time zone with the app layer's own
// convention (web/_functions/db_connect.php runs "SET time_zone = '+00:00'"
// on every getDB() connection). Without this, DATE_SUB(NOW(), INTERVAL ...)
// evaluated HERE (on $db, which otherwise defaults to the server's SYSTEM
// time zone) can differ from NOW() as evaluated by g2ml_apiCheckPreAuthBackoff()
// (via getDB(), forced to UTC) by a full time-zone offset — on a host whose
// SYSTEM zone is not UTC, a row seeded as "5 minutes ago" on $db can land
// AHEAD of getDB()'s UTC "now", making a should-be-excluded row look
// in-window. This only affects this test harness: in real operation both the
// write (g2ml_apiLogRequest()) and the read (g2ml_apiCheckPreAuthBackoff())
// go through the SAME getDB() connection, so production never sees this.
// ----------------------------------------------------------------------------
mysqli_query($db, "SET time_zone = '+00:00'");

// ----------------------------------------------------------------------------
// Point the application DB layer (getDB) at the same throwaway server. Each
// constant is guarded individually so this file composes with any sibling
// integration file that already defined them.
// ----------------------------------------------------------------------------
if (!defined('DB_HOST'))
{
    $g2mlApiUrlsTestHost = getenv('G2ML_TEST_DB_HOST');

    if ($g2mlApiUrlsTestHost === false || $g2mlApiUrlsTestHost === '')
    {
        $g2mlApiUrlsTestHost = '127.0.0.1';
    }

    define('DB_HOST', $g2mlApiUrlsTestHost);
}

if (!defined('DB_PORT'))
{
    $g2mlApiUrlsTestPortRaw = getenv('G2ML_TEST_DB_PORT');

    if ($g2mlApiUrlsTestPortRaw === false || $g2mlApiUrlsTestPortRaw === '')
    {
        $g2mlApiUrlsTestPortRaw = '3306';
    }

    define('DB_PORT', (int) $g2mlApiUrlsTestPortRaw);
}

if (!defined('DB_USER'))
{
    $g2mlApiUrlsTestUser = getenv('G2ML_TEST_DB_USER');

    if ($g2mlApiUrlsTestUser === false || $g2mlApiUrlsTestUser === '')
    {
        $g2mlApiUrlsTestUser = 'root';
    }

    define('DB_USER', $g2mlApiUrlsTestUser);
}

if (!defined('DB_PASS'))
{
    $g2mlApiUrlsTestPass = getenv('G2ML_TEST_DB_PASS');

    if ($g2mlApiUrlsTestPass === false)
    {
        $g2mlApiUrlsTestPass = '';
    }

    define('DB_PASS', $g2mlApiUrlsTestPass);
}

if (!defined('DB_NAME'))
{
    $g2mlApiUrlsTestName = getenv('G2ML_TEST_DB_NAME');

    if ($g2mlApiUrlsTestName === false || $g2mlApiUrlsTestName === '')
    {
        $g2mlApiUrlsTestName = 'mwtools_Go2MyLink';
    }

    define('DB_NAME', $g2mlApiUrlsTestName);
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// ----------------------------------------------------------------------------
// Load the real application code under test and its dependencies.
// ----------------------------------------------------------------------------
$g2mlApiUrlsFunctionsDir = dirname(__DIR__, 2) . '/web/_functions';
$g2mlApiUrlsHandlersDir  = dirname(__DIR__, 2) . '/web/Go2My.Link/public_html/api/v1/handlers';

require_once $g2mlApiUrlsFunctionsDir . '/db_connect.php';
require_once $g2mlApiUrlsFunctionsDir . '/db_query.php';
require_once $g2mlApiUrlsFunctionsDir . '/security.php';
require_once $g2mlApiUrlsFunctionsDir . '/settings.php';
require_once $g2mlApiUrlsFunctionsDir . '/activity_logger.php';
require_once $g2mlApiUrlsFunctionsDir . '/api_response.php';
require_once $g2mlApiUrlsFunctionsDir . '/api_auth.php';
require_once $g2mlApiUrlsFunctionsDir . '/api_ratelimit.php';
require_once $g2mlApiUrlsFunctionsDir . '/org.php';
require_once dirname(__DIR__, 2) . '/web/Go2My.Link/_functions/shorturl_create.php';
require_once $g2mlApiUrlsHandlersDir . '/urls.php';
require_once $g2mlApiUrlsHandlersDir . '/urls_bulk.php';
require_once $g2mlApiUrlsHandlersDir . '/org.php';

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
function g2ml_apiurls_test_exec(mysqli $db, string $sql): void
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
function g2ml_apiurls_test_insert_user(mysqli $db, string $orgHandle, string $marker): int
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
    $email        = $marker . '@apiurls.test';
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
function g2ml_apiurls_test_delete_code(mysqli $db, string $shortCode): void
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
 * Mint a real API key (a valid tblAPIKeys row, needed because
 * tblShortURLs.createdViaAPIKeyUID carries a real FK to tblAPIKeys) and
 * return the VERIFIED key row exactly as the front controller would produce
 * it via g2ml_apiVerifyKey().
 *
 * @param  string   $orgHandle
 * @param  int      $userUID
 * @param  string[] $scopes
 * @return array               The verified key row.
 */
function g2ml_apiurls_test_make_key(string $orgHandle, int $userUID, array $scopes): array
{
    $created = g2ml_apiGenerateKey($userUID, $orgHandle, 'urls-test key', $scopes, null);

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
function g2ml_apiurls_test_context(array $body = [], array $routeParams = []): array
{
    return [
        'requestId'   => 'test-request',
        'routeParams' => $routeParams,
        'body'        => $body,
    ];
}

/**
 * Insert one throwaway tblAPIRequestLog row directly with a given
 * ipAddress/responseCode, used to seed the pre-auth backoff counter.
 *
 * @param  mysqli $db
 * @param  string $ipAddress
 * @param  int    $responseCode
 * @param  string $createdAtOffset  A MySQL DATE_SUB()-compatible interval, e.g. '10 SECOND'.
 * @return void
 */
function g2ml_apiurls_test_seed_log_row(mysqli $db, string $ipAddress, int $responseCode, string $createdAtOffset): void
{
    $sql = 'INSERT INTO `tblAPIRequestLog` '
        . '(`apiKeyUID`, `endpoint`, `httpMethod`, `responseCode`, `ipAddress`, `createdAt`) '
        . "VALUES (NULL, '/api/v1/urls', 'GET', " . $responseCode . ", '" . mysqli_real_escape_string($db, $ipAddress) . "', "
        . 'DATE_SUB(NOW(), INTERVAL ' . $createdAtOffset . '))';

    g2ml_apiurls_test_exec($db, $sql);
}

/**
 * Delete all tblAPIRequestLog rows for an IP (cleanup between tests).
 *
 * @param  mysqli $db
 * @param  string $ipAddress
 * @return void
 */
function g2ml_apiurls_test_clear_log(mysqli $db, string $ipAddress): void
{
    $statement = mysqli_prepare($db, 'DELETE FROM `tblAPIRequestLog` WHERE `ipAddress` = ?');
    mysqli_stmt_bind_param($statement, 's', $ipAddress);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

// ----------------------------------------------------------------------------
// Shared setup: the free tier, two organisations (A = [default], B =
// orgbapiurls), and a default short domain for A (createShortURL falls back
// to 'g2my.link' for an org without its own domain, so B needs no row).
// ----------------------------------------------------------------------------
g2ml_apiurls_test_exec(
    $db,
    "INSERT INTO `tblSubscriptionTiers` (`tierID`, `tierName`) "
    . "VALUES ('free', 'Free') "
    . "ON DUPLICATE KEY UPDATE `tierName` = VALUES(`tierName`)"
);

g2ml_apiurls_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('[default]', 'Default Test Org', 'https://go2my.link/fallback', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_apiurls_test_exec(
    $db,
    "INSERT INTO `tblOrganisations` (`orgHandle`, `orgName`, `orgFallbackURL`, `tierID`, `isActive`) "
    . "VALUES ('orgbapiurls', 'Org B (API urls isolation test)', 'https://go2my.link/orgb', 'free', 1) "
    . "ON DUPLICATE KEY UPDATE `orgFallbackURL` = VALUES(`orgFallbackURL`)"
);

g2ml_apiurls_test_exec(
    $db,
    "INSERT INTO `tblOrgShortDomains` (`orgHandle`, `shortDomain`, `isDefault`, `isActive`) "
    . "VALUES ('[default]', 'g2my.link', 1, 1) "
    . "ON DUPLICATE KEY UPDATE `isActive` = 1"
);

$g2mlApiUrlsUserA = g2ml_apiurls_test_insert_user($db, '[default]', 'apiurlsA_' . substr(hash('sha256', (string) microtime(true)), 0, 10));
$g2mlApiUrlsUserB = g2ml_apiurls_test_insert_user($db, 'orgbapiurls', 'apiurlsB_' . substr(hash('sha256', (string) microtime(true) . 'b'), 0, 10));

$g2mlApiUrlsKeyAFull = g2ml_apiurls_test_make_key('[default]', $g2mlApiUrlsUserA, ['urls:read', 'urls:write', 'urls:delete', 'org:read']);
$g2mlApiUrlsKeyBFull = g2ml_apiurls_test_make_key('orgbapiurls', $g2mlApiUrlsUserB, ['urls:read', 'urls:write', 'urls:delete', 'org:read']);

// ============================================================================
// ✨ POST /api/v1/urls — create
// ============================================================================

test('urlsCreate: happy path returns short_code/short_url/destination_url and records createdVia=api + the calling key', function () use ($g2mlApiUrlsKeyAFull, $g2mlApiUrlsUserA, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apicreate1');

    $result = g2ml_apiHandleUrlsCreate(
        $g2mlApiUrlsKeyAFull,
        g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apicreate1', 'custom_code' => 'apicreate1', 'title' => 'API create test'])
    );

    assert_same('apicreate1', $result['short_code'], 'The custom_code must be used verbatim as the short code');
    assert_true(str_contains($result['short_url'], 'apicreate1'), 'short_url must embed the short code');
    assert_same('https://example.com/apicreate1', $result['destination_url'], 'destination_url must echo the stored (sanitised) destination');

    $row = dbSelectOne(
        'SELECT createdVia, createdViaAPIKeyUID, createdByUserUID, orgHandle, title FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?',
        'ss',
        ['apicreate1', '[default]']
    );

    assert_true(is_array($row), 'The created row must be readable');
    assert_same('api', $row['createdVia'], 'createdVia must be recorded as "api"');
    assert_same((int) $g2mlApiUrlsKeyAFull['apiKeyUID'], (int) $row['createdViaAPIKeyUID'], 'createdViaAPIKeyUID must be the calling key');
    assert_same((int) $g2mlApiUrlsUserA, (int) $row['createdByUserUID'], "createdByUserUID must be the key's own owning user");
    assert_same('[default]', $row['orgHandle'], "orgHandle must be the key's own org, never client-supplied");
    assert_same('API create test', $row['title'], 'title must be persisted');

    g2ml_apiurls_test_delete_code($db, 'apicreate1');
});

test('createShortURL: omitting createdVia/createdViaAPIKeyUID still defaults to web/NULL (regression guard — #39 must not break pre-#39 callers)', function () use ($db): void
{
    g2ml_apiurls_test_delete_code($db, 'apidefaultweb');

    $result = createShortURL('https://example.com/apidefaultweb', [
        'orgHandle'  => '[default]',
        'customCode' => 'apidefaultweb',
    ]);

    assert_true($result['success'], 'Create without createdVia/createdViaAPIKeyUID must still succeed');

    $row = dbSelectOne(
        'SELECT createdVia, createdViaAPIKeyUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?',
        'ss',
        ['apidefaultweb', '[default]']
    );

    assert_same('web', $row['createdVia'], 'A caller that never supplies createdVia must still get the column default "web"');
    assert_same(null, $row['createdViaAPIKeyUID'], 'A caller that never supplies createdViaAPIKeyUID must still get NULL');

    g2ml_apiurls_test_delete_code($db, 'apidefaultweb');
});

test('urlsCreate: an alias collision throws G2mlApiHandlerException with HTTP 409', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apidupe');

    $first = g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/first', 'custom_code' => 'apidupe']));
    assert_same('apidupe', $first['short_code'], 'The first create with this alias must succeed');

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/second', 'custom_code' => 'apidupe']));
    }
    catch (G2mlApiHandlerException $collisionException)
    {
        $threw      = true;
        $statusCode = $collisionException->getHttpStatusCode();
    }

    assert_true($threw, 'A duplicate alias must throw G2mlApiHandlerException');
    assert_same(409, $statusCode, 'An alias collision must map to HTTP 409');

    g2ml_apiurls_test_delete_code($db, 'apidupe');
});

test('urlsCreate: a missing destination_url throws G2mlApiHandlerException with HTTP 422 and field destination_url', function () use ($g2mlApiUrlsKeyAFull): void
{
    $threw      = false;
    $statusCode = null;
    $field      = null;

    try
    {
        g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([]));
    }
    catch (G2mlApiHandlerException $validationException)
    {
        $threw      = true;
        $statusCode = $validationException->getHttpStatusCode();
        $field      = $validationException->getField();
    }

    assert_true($threw, 'A missing destination_url must throw');
    assert_same(422, $statusCode, 'A missing required field must map to HTTP 422');
    assert_same('destination_url', $field, 'The offending field must be named');
});

// ============================================================================
// 📋 GET /api/v1/urls — list, cursor-paginated
// ============================================================================

test('urlsList: cursor pagination — limit caps the page and nextCursor advances correctly', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    // Org [default] is shared with every OTHER integration test file (they all
    // use the same seeded org), so this test must not assume it is the only
    // thing populating tblShortURLs for that org. Tagging all three rows with
    // a unique tag and filtering the list by it (?tag=) makes the result set
    // exactly these three rows regardless of anything else in the org — while
    // also exercising the tag filter itself.
    g2ml_apiurls_test_delete_code($db, 'apilist1');
    g2ml_apiurls_test_delete_code($db, 'apilist2');
    g2ml_apiurls_test_delete_code($db, 'apilist3');

    $uniqueTag = 'apilistpagtest' . substr(hash('sha256', (string) microtime(true)), 0, 8);

    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apilist1', 'custom_code' => 'apilist1', 'tags' => [$uniqueTag]]));
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apilist2', 'custom_code' => 'apilist2', 'tags' => [$uniqueTag]]));
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apilist3', 'custom_code' => 'apilist3', 'tags' => [$uniqueTag]]));

    $savedGet   = $_GET;
    $_GET       = ['limit' => '2', 'tag' => $uniqueTag];
    $firstPage  = g2ml_apiHandleUrlsList($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context());
    $_GET       = $savedGet;

    assert_same(2, count($firstPage['items']), 'limit=2 must cap the (tag-filtered) page at 2 items');
    assert_true($firstPage['nextCursor'] !== null, 'A next page must be signalled when more tagged rows exist');

    $savedGet    = $_GET;
    $_GET        = ['limit' => '2', 'after' => $firstPage['nextCursor'], 'tag' => $uniqueTag];
    $secondPage  = g2ml_apiHandleUrlsList($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context());
    $_GET        = $savedGet;

    assert_same(1, count($secondPage['items']), 'The second (tag-filtered) page must return exactly the one remaining row');
    assert_same(null, $secondPage['nextCursor'], 'Once every tagged row is exhausted, nextCursor must be null');

    $firstPageCodes  = array_column($firstPage['items'], 'short_code');
    $secondPageCodes = array_column($secondPage['items'], 'short_code');
    $overlap         = array_intersect($firstPageCodes, $secondPageCodes);

    assert_same(0, count($overlap), 'The two pages must not overlap');
    assert_same(3, count(array_unique(array_merge($firstPageCodes, $secondPageCodes))), 'Together the two pages must cover exactly the three tagged codes');

    g2ml_apiurls_test_delete_code($db, 'apilist1');
    g2ml_apiurls_test_delete_code($db, 'apilist2');
    g2ml_apiurls_test_delete_code($db, 'apilist3');
});

test('urlsList: org-scoping — org A never sees org B\'s links', function () use ($g2mlApiUrlsKeyAFull, $g2mlApiUrlsKeyBFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apilistb1');

    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyBFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apilistb1', 'custom_code' => 'apilistb1']));

    $savedGet = $_GET;
    $_GET     = ['limit' => '100'];
    $listA    = g2ml_apiHandleUrlsList($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context());
    $_GET     = $savedGet;

    $codesSeenByA = array_column($listA['items'], 'short_code');
    assert_false(in_array('apilistb1', $codesSeenByA, true), "Org A's list must never include org B's code");

    g2ml_apiurls_test_delete_code($db, 'apilistb1');
});

// ============================================================================
// 🔍 GET /api/v1/urls/{code} — read one, org-scoped (BOLA)
// ============================================================================

test('urlsGet: happy path returns the link for the owning org', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apiget1');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apiget1', 'custom_code' => 'apiget1']));

    $result = g2ml_apiHandleUrlsGet($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'apiget1']));

    assert_same('apiget1', $result['short_code'], 'The correct link must be returned');
    assert_same('https://example.com/apiget1', $result['destination_url'], 'The destination must round-trip');

    g2ml_apiurls_test_delete_code($db, 'apiget1');
});

test('urlsGet: BOLA guard — org A gets 404 (not 403, not a different shape) for org B\'s code', function () use ($g2mlApiUrlsKeyAFull, $g2mlApiUrlsKeyBFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apibola1');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyBFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apibola1', 'custom_code' => 'apibola1']));

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsGet($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'apibola1']));
    }
    catch (G2mlApiHandlerException $bolaException)
    {
        $threw      = true;
        $statusCode = $bolaException->getHttpStatusCode();
    }

    assert_true($threw, "A cross-org GET must throw (never silently return org B's data)");
    assert_same(404, $statusCode, 'A cross-org GET must be indistinguishable from "does not exist" — 404, never 403');

    g2ml_apiurls_test_delete_code($db, 'apibola1');
});

test('urlsGet: a code that does not exist anywhere also 404s', function () use ($g2mlApiUrlsKeyAFull): void
{
    $threw = false;

    try
    {
        g2ml_apiHandleUrlsGet($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'nosuchcodeatall']));
    }
    catch (G2mlApiHandlerException $notFoundException)
    {
        $threw = ($notFoundException->getHttpStatusCode() === 404);
    }

    assert_true($threw, 'A wholly non-existent code must 404 identically to a cross-org one');
});

// ============================================================================
// ✏️ PUT /api/v1/urls/{code} — update, org-scoped
// ============================================================================

test('urlsUpdate: happy path updates destination_url/title/tags and reloads the row', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apiupdate1');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/before', 'custom_code' => 'apiupdate1']));

    $updated = g2ml_apiHandleUrlsUpdate(
        $g2mlApiUrlsKeyAFull,
        g2ml_apiurls_test_context(['destination_url' => 'https://example.com/after', 'title' => 'Updated title', 'tags' => ['apiupdatetag']], ['code' => 'apiupdate1'])
    );

    assert_same('https://example.com/after', $updated['destination_url'], 'destination_url must be updated');
    assert_same('Updated title', $updated['title'], 'title must be updated');

    $tagRow = dbSelectOne(
        'SELECT t.tagSlug FROM tblShortURLTags st '
        . 'INNER JOIN tblTags t ON st.tagUID = t.tagUID '
        . 'INNER JOIN tblShortURLs s ON st.urlUID = s.urlUID '
        . 'WHERE s.shortCode = ? AND s.orgHandle = ? LIMIT 1',
        'ss',
        ['apiupdate1', '[default]']
    );

    assert_true(is_array($tagRow), 'The supplied tag must have been attached');
    assert_same('apiupdatetag', $tagRow['tagSlug'], 'The attached tag must match what was submitted');

    g2ml_apiurls_test_delete_code($db, 'apiupdate1');
});

test('urlsUpdate: re-applies the SSRF/scheme guard — a private destination is rejected with 422', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apissrf1');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/safe', 'custom_code' => 'apissrf1']));

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsUpdate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'http://127.0.0.1/'], ['code' => 'apissrf1']));
    }
    catch (G2mlApiHandlerException $ssrfException)
    {
        $threw      = true;
        $statusCode = $ssrfException->getHttpStatusCode();
    }

    assert_true($threw, 'A loopback destination must be rejected by the same SSRF guard used on create');
    assert_same(422, $statusCode, 'An SSRF-blocked destination on update must map to 422');

    $unchangedRow = dbSelectOne('SELECT destinationURL FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['apissrf1', '[default]']);
    assert_same('https://example.com/safe', $unchangedRow['destinationURL'], 'A rejected update must never mutate the stored destination');

    g2ml_apiurls_test_delete_code($db, 'apissrf1');
});

test('urlsUpdate: BOLA guard — org A gets 404 for org B\'s code, and org B\'s data is untouched', function () use ($g2mlApiUrlsKeyAFull, $g2mlApiUrlsKeyBFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apibola2');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyBFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/orgb-original', 'custom_code' => 'apibola2']));

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsUpdate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/hijacked'], ['code' => 'apibola2']));
    }
    catch (G2mlApiHandlerException $bolaException)
    {
        $threw      = true;
        $statusCode = $bolaException->getHttpStatusCode();
    }

    assert_true($threw, 'A cross-org UPDATE must throw');
    assert_same(404, $statusCode, 'A cross-org UPDATE must 404, never partially apply');

    $orgBRow = dbSelectOne('SELECT destinationURL FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['apibola2', 'orgbapiurls']);
    assert_same('https://example.com/orgb-original', $orgBRow['destinationURL'], "Org B's link must be completely untouched by org A's attempt");

    g2ml_apiurls_test_delete_code($db, 'apibola2');
});

// ============================================================================
// 🗑️ DELETE /api/v1/urls/{code} — soft delete, org-scoped, idempotent
// ============================================================================

test('urlsDelete: happy path soft-deletes (isActive=0) and is idempotent on repeat', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apidelete1');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apidelete1', 'custom_code' => 'apidelete1']));

    $firstDelete = g2ml_apiHandleUrlsDelete($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'apidelete1']));
    assert_same('apidelete1', $firstDelete['short_code'], 'The response must echo the short code');
    assert_false($firstDelete['is_active'], 'is_active must report false after delete');

    $row = dbSelectOne('SELECT isActive FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['apidelete1', '[default]']);
    assert_same(0, (int) $row['isActive'], 'isActive must be persisted as 0');

    // Repeating the delete on the SAME org must succeed again (idempotent),
    // not throw a confusing 404 just because affected_rows is now 0.
    $secondDelete = g2ml_apiHandleUrlsDelete($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'apidelete1']));
    assert_false($secondDelete['is_active'], 'Deleting an already-inactive link in the SAME org must succeed again, not throw');

    g2ml_apiurls_test_delete_code($db, 'apidelete1');
});

test('urlsDelete: BOLA guard — org A gets 404 for org B\'s code, and org B\'s link stays active', function () use ($g2mlApiUrlsKeyAFull, $g2mlApiUrlsKeyBFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apibola3');
    g2ml_apiHandleUrlsCreate($g2mlApiUrlsKeyBFull, g2ml_apiurls_test_context(['destination_url' => 'https://example.com/apibola3', 'custom_code' => 'apibola3']));

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsDelete($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context([], ['code' => 'apibola3']));
    }
    catch (G2mlApiHandlerException $bolaException)
    {
        $threw      = true;
        $statusCode = $bolaException->getHttpStatusCode();
    }

    assert_true($threw, 'A cross-org DELETE must throw');
    assert_same(404, $statusCode, 'A cross-org DELETE must 404, never deactivate the other tenant\'s link');

    $orgBRow = dbSelectOne('SELECT isActive FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['apibola3', 'orgbapiurls']);
    assert_same(1, (int) $orgBRow['isActive'], "Org B's link must remain active — no cross-tenant deactivation");

    g2ml_apiurls_test_delete_code($db, 'apibola3');
});

// ============================================================================
// 📦 POST /api/v1/urls/bulk — partial failure
// ============================================================================

test('urlsBulkCreate: a mixed batch reports per-row success/failure without aborting sibling rows', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    g2ml_apiurls_test_delete_code($db, 'apibulkgood');

    $result = g2ml_apiHandleUrlsBulkCreate(
        $g2mlApiUrlsKeyAFull,
        g2ml_apiurls_test_context([
            'items' => [
                ['destination_url' => 'https://example.com/apibulkgood', 'custom_code' => 'apibulkgood'],
                ['title' => 'missing destination_url'],
            ],
        ])
    );

    assert_same(2, count($result['results']), 'Every submitted row must produce exactly one result entry');

    assert_same(0, $result['results'][0]['input_index'], 'The first result must carry input_index 0');
    assert_true($result['results'][0]['success'], 'The valid first row must succeed');
    assert_same('apibulkgood', $result['results'][0]['short_code'], 'The successful row must report its short_code');

    assert_same(1, $result['results'][1]['input_index'], 'The second result must carry input_index 1');
    assert_false($result['results'][1]['success'], 'The row missing destination_url must fail');
    assert_true(isset($result['results'][1]['error']), 'A failed row must carry an error message');

    $goodRow = dbSelectOne('SELECT urlUID FROM tblShortURLs WHERE shortCode = ? AND orgHandle = ?', 'ss', ['apibulkgood', '[default]']);
    assert_true(is_array($goodRow), 'The valid row must actually have been created despite its sibling failing');

    g2ml_apiurls_test_delete_code($db, 'apibulkgood');
});

test('urlsBulkCreate: an oversized batch (>100 items) throws 422 before any row is processed', function () use ($g2mlApiUrlsKeyAFull): void
{
    $items = [];

    for ($i = 0; $i < 101; $i++)
    {
        $items[] = ['destination_url' => 'https://example.com/bulk-overflow-' . $i];
    }

    $threw      = false;
    $statusCode = null;

    try
    {
        g2ml_apiHandleUrlsBulkCreate($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context(['items' => $items]));
    }
    catch (G2mlApiHandlerException $overflowException)
    {
        $threw      = true;
        $statusCode = $overflowException->getHttpStatusCode();
    }

    assert_true($threw, 'A batch over the cap must be rejected outright');
    assert_same(422, $statusCode, 'An oversized batch must map to 422');
});

// ============================================================================
// 🏢 GET /api/v1/org — self-scoped org summary
// ============================================================================

test('orgRead: returns the calling key\'s OWN organisation summary', function () use ($g2mlApiUrlsKeyAFull, $db): void
{
    // '[default]' is ALSO the production base org seeded by
    // web/_sql/seeds/002_default_organisation.sql — this suite's own setup
    // INSERT ... ON DUPLICATE KEY UPDATE only refreshes orgFallbackURL (never
    // orgName), so whichever orgName the org already carries is authoritative
    // here. Compare against a fresh read rather than hardcoding a name, so
    // this test is correct whether it runs against a truly fresh DB (which
    // never had 002_default_organisation.sql's seed applied) or the normal
    // case (which did).
    $expectedOrgRow = dbSelectOne('SELECT orgName FROM tblOrganisations WHERE orgHandle = ?', 's', ['[default]']);

    $result = g2ml_apiHandleOrgRead($g2mlApiUrlsKeyAFull, g2ml_apiurls_test_context());

    assert_same('[default]', $result['org_handle'], 'org_handle must be the key\'s own org');
    assert_same($expectedOrgRow['orgName'], $result['org_name'], 'org_name must match the organisation\'s actual stored name');
    assert_same('free', $result['tier_id'], 'tier_id must match the seeded tier');
    assert_true(array_key_exists('tier_name', $result), 'tier_name must be present (even if null)');
});

test('orgRead: org B\'s key sees ONLY org B\'s summary', function () use ($g2mlApiUrlsKeyBFull): void
{
    $result = g2ml_apiHandleOrgRead($g2mlApiUrlsKeyBFull, g2ml_apiurls_test_context());
    assert_same('orgbapiurls', $result['org_handle'], "Org B's key must never see org A's handle");
    assert_same('Org B (API urls isolation test)', $result['org_name'], 'org_name must match org B specifically');
});

// ============================================================================
// 🧯 Pre-auth IP failed-auth backoff
// ============================================================================

test('preAuthBackoff: allowed=true when well under the threshold', function () use ($db): void
{
    $ip = '203.0.113.201';
    g2ml_apiurls_test_clear_log($db, $ip);

    // A plain UPDATE, not INSERT ... ON DUPLICATE KEY UPDATE: the row is
    // already seeded by web/_sql/seeds/015_api_settings.sql, and
    // tblSettings.UQ_setting_scope (settingID, settingScope, settingScopeRef)
    // cannot be relied on to de-duplicate here — settingScopeRef is NULL for
    // every System-scope setting, and MySQL never treats two NULLs as equal
    // for unique-key purposes, so an INSERT ... ON DUPLICATE KEY UPDATE would
    // silently insert a SECOND row instead of updating the existing one.
    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '5' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    $backoff = g2ml_apiCheckPreAuthBackoff($ip);
    assert_true($backoff['allowed'], 'With zero prior 401s logged, the IP must be allowed');
    assert_same(0, $backoff['retryAfter'], 'retryAfter must be 0 when allowed');

    g2ml_apiurls_test_clear_log($db, $ip);
});

test('preAuthBackoff: trips to allowed=false once the per-minute 401 threshold is reached', function () use ($db): void
{
    $ip = '203.0.113.202';
    g2ml_apiurls_test_clear_log($db, $ip);

    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '3' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    // Three 401s within the last 60 seconds meets the (overridden) threshold of 3.
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '5 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '10 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '15 SECOND');

    $backoff = g2ml_apiCheckPreAuthBackoff($ip);
    assert_false($backoff['allowed'], 'Reaching the per-minute 401 threshold must deny (429-eligible) further attempts');
    assert_same(60, $backoff['retryAfter'], 'A tripped backoff must report a 60s Retry-After');

    // Restore the default and clean up so this never leaks into sibling tests.
    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '20' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();
    g2ml_apiurls_test_clear_log($db, $ip);
});

test('preAuthBackoff: 401s older than the 60-second window do not count', function () use ($db): void
{
    $ip = '203.0.113.203';
    g2ml_apiurls_test_clear_log($db, $ip);

    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '2' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '5 MINUTE');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '10 MINUTE');

    $backoff = g2ml_apiCheckPreAuthBackoff($ip);
    assert_true($backoff['allowed'], '401s outside the 60-second window must not count toward the threshold');

    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '20' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();
    g2ml_apiurls_test_clear_log($db, $ip);
});

test('preAuthBackoff: a non-positive setting disables the guard entirely', function () use ($db): void
{
    $ip = '203.0.113.204';
    g2ml_apiurls_test_clear_log($db, $ip);

    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '0' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();

    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '1 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '2 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '3 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '4 SECOND');
    g2ml_apiurls_test_seed_log_row($db, $ip, 401, '5 SECOND');

    $backoff = g2ml_apiCheckPreAuthBackoff($ip);
    assert_true($backoff['allowed'], 'A setting of 0 must disable the guard regardless of how many 401s are logged');

    g2ml_apiurls_test_exec($db, "UPDATE `tblSettings` SET `settingValue` = '20' WHERE `settingID` = 'api.preauth_fail_per_minute'");
    loadSettingsCache();
    g2ml_apiurls_test_clear_log($db, $ip);
});

// ----------------------------------------------------------------------------
// NOTE: the shared fixtures (the two API keys + their owning users) are
// deliberately NOT deleted here. test() only REGISTERS a callback — it does
// not execute it — so any cleanup written at this top level (include time)
// would run BEFORE the tests above ever execute, deleting the very keys they
// depend on (tblShortURLs.createdViaAPIKeyUID carries a real FK to
// tblAPIKeys, so a deleted key would fail every create() call's FK check).
// This mirrors cross_org_isolation_test.php, which likewise leaves its
// shared seed users in place — the throwaway test database is torn down
// entirely after the suite runs (see tests/README.md).
// ----------------------------------------------------------------------------
