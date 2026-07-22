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
 * ⏰ Go2My.Link — Scheduled Jobs Dispatch Endpoint (Admin Dashboard) (#163, #167)
 * ============================================================================
 *
 * A token-guarded standalone endpoint any external scheduler can hit —
 * Dreamhost Panel cron `curl`, cron-job.org, GitHub Actions `schedule:` (see
 * HANDOFF.md #178 for the scheduler decision). Reachable at
 * https://admin.go2my.link/cron.php (+ the alpha subdomain equivalent).
 *
 * ----------------------------------------------------------------------------
 * Why this is a standalone script, not a pages/* route
 * ----------------------------------------------------------------------------
 * Identical reasoning to analytics-export.php (#44) — this file is the
 * template it was copied from. Every route the file-based router resolves is
 * required AFTER header.php has ALREADY echoed HTML; a route file cannot
 * therefore send its own JSON Content-Type/404/405 status codes. This file is
 * instead a second, independent entry point in public_html/, served directly
 * by the existing `.htaccess` clean-URL rule (RewriteCond
 * %{REQUEST_FILENAME} !-f) — because cron.php IS a real file, that rule never
 * routes it through index.php at all.
 *
 * ----------------------------------------------------------------------------
 * Safety model — every failure path is a byte-identical generic 404
 * ----------------------------------------------------------------------------
 * cron.enabled defaults OFF and cron.dispatch_token defaults to NULL (see
 * web/_sql/seeds/020_cron_gdpr_settings.sql) — a fresh install's endpoint
 * answers 404 to every request, indistinguishable from a file that does not
 * exist. Feature-off is NOT logged (not an attack); a wrong/missing token
 * WITH the feature on IS logged (cron_dispatch_denied) before the same 404.
 * No rate limiting is added deliberately — a 256-bit token is not
 * brute-forceable, and a failure sleep() would tie up an Apache/FastCGI
 * worker (a self-inflicted DoS lever on shared hosting).
 *
 * The #163 deletion executor only ever runs from THIS endpoint — never from
 * the probabilistic page_init.php fallback (retention-only) — because
 * irreversible work must never ride an anonymous visitor's request.
 * G2ML_CRON_DISPATCH is defined BEFORE page_init.php runs specifically so
 * that fallback hook can detect an endpoint run already in progress and skip
 * itself (no self-race for the advisory lock).
 *
 * ----------------------------------------------------------------------------
 * Parameters
 * ----------------------------------------------------------------------------
 *   Header X-G2ML-Cron-Token   Preferred token transport.
 *   ?token=                    Fallback transport for header-less schedulers.
 *                              NOTE: this form lands in Apache access logs —
 *                              the header is strongly recommended instead.
 *   ?job=                      Optional. One of 'deletion' | 'retention' |
 *                              'all' (default). Anything else is a 400 —
 *                              evaluated ONLY after authentication succeeds,
 *                              so an invalid job name can never be used to
 *                              probe for the endpoint's existence.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.0.0
 * @since      v1.7.0 — GDPR Scheduled Jobs (#163, #167)
 *
 * 📖 References:
 *     - Job library:                web/_functions/cron.php
 *     - Existing #163 executor:     web/_functions/data_rights.php
 *     - Standalone-entry precedent: web/Go2My.Link/_admin/public_html/analytics-export.php
 *     - Settings seed (all OFF):    web/_sql/seeds/020_cron_gdpr_settings.sql
 * ============================================================================
 */

// ============================================================================
// 🚩 Step 0: Mark this request as the dispatch entry point BEFORE
// page_init.php runs, so the probabilistic retention hook there skips itself
// and cannot race this request for the advisory lock.
// ============================================================================

define('G2ML_CRON_DISPATCH', true);

// ============================================================================
// 📦 Step 1: Load Component Auth Credentials (mirrors analytics-export.php)
// ============================================================================

$componentAuthPath = dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR . '.auth'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}
else
{
    error_log('[Go2My.Link] CRITICAL: Component A auth_creds.php not found at: ' . $componentAuthPath);
}

// ============================================================================
// 🏷️ Step 2: Define Component Constants (mirrors analytics-export.php)
// ============================================================================

define('G2ML_COMPONENT',        'Admin');
define('G2ML_COMPONENT_NAME',   'Admin Dashboard');
define('G2ML_COMPONENT_DOMAIN', 'admin.go2my.link');
define('G2ML_ROOT', dirname(__DIR__, 3));

// ============================================================================
// 🚀 Step 3: Bootstrap the Application (loads _functions/*.php incl.
// cron.php, and the settings cache).
// ============================================================================

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// ============================================================================
// 🔐 Step 4: Method Guard — GET and POST only.
// ============================================================================

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

if ($requestMethod !== 'GET' && $requestMethod !== 'POST')
{
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    header('Allow: GET, POST');
    echo 'Method Not Allowed';
    exit;
}

// ============================================================================
// 🔒 Step 5: Authorisation.
//
// EVERY failure path below converges on g2ml_cronEmitNotFound() — a
// byte-identical generic 404 (no distinction between "feature off", "no
// token configured", "no token presented", and "wrong token"). The endpoint
// is deliberately indistinguishable from a file that does not exist.
// ============================================================================

$cronEnabled = getSetting('cron.enabled', false);

if ($cronEnabled !== true)
{
    // Feature off is not an attack signal — no logging, straight to the 404.
    g2ml_cronEmitNotFound();
}

$providedToken = g2ml_cronReadPresentedToken($_SERVER, $_GET);
$storedToken   = getSetting('cron.dispatch_token', null);

if (g2ml_cronVerifyToken($providedToken, $storedToken) !== true)
{
    // The feature IS on but auth failed — log the denial (no token material
    // is ever logged), then the SAME 404.
    logActivity('cron_dispatch_denied', 'denied', 404, [
        'logData' => ['reason' => 'auth'],
    ]);

    g2ml_cronEmitNotFound();
}

// ============================================================================
// 🧭 Step 6: Job selection — evaluated ONLY post-auth, so an invalid job name
// can never be used as an existence oracle for the endpoint.
// ============================================================================

$rawJob  = $_GET['job'] ?? null;
$jobName = g2ml_cronNormaliseJobName($rawJob);

if ($jobName === null)
{
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'invalid_job']);
    exit;
}

// ============================================================================
// 🔒 Step 7: Advisory lock — timeout 0, never queues. A concurrent run
// (another scheduler firing early, or an overlapping fallback hook — though
// G2ML_CRON_DISPATCH already prevents that specific race) short-circuits to
// a deliberate 200 'skipped' so schedulers do not retry-storm.
// ============================================================================

if (g2ml_cronAcquireLock() !== true)
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'skipped', 'reason' => 'already_running']);
    exit;
}

// ============================================================================
// 🏃 Step 8: Run the requested job(s) and report. The lock is released in a
// finally block regardless of outcome; connection close is the backstop.
// ============================================================================

try
{
    $maxRuntimeSeconds = g2ml_cronClampInt(getSetting('cron.max_runtime_seconds', 50), 1, 300, 50);
    $report            = g2ml_cronRunJobs($jobName, $maxRuntimeSeconds);
}
finally
{
    g2ml_cronReleaseLock();
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($report, JSON_PRETTY_PRINT);
exit;
