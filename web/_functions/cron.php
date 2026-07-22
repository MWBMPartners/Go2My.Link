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
 * ⏰ Go2My.Link — Scheduled Jobs (GDPR Deletion + Retention) (#163, #167)
 * ============================================================================
 *
 * Provider-agnostic scheduled-jobs library. Holds ALL job logic — pure helpers
 * plus the DB sweeps themselves — for two callers:
 *
 *   (a) the token-guarded standalone endpoint
 *       web/Go2My.Link/_admin/public_html/cron.php, hit by any external
 *       scheduler (Dreamhost Panel cron, cron-job.org, GitHub Actions
 *       `schedule:`);
 *   (b) a probabilistic fallback hook called once per request from
 *       _includes/page_init.php (mirrors the existing 1-in-100 session/API
 *       log idioms — see session.php::cleanExpiredSessions() and
 *       api_ratelimit.php's own probabilistic prune) that runs RETENTION ONLY.
 *
 * The #163 deletion executor is deliberately endpoint-only — irreversible
 * work must never ride an anonymous visitor's request. Every entry function
 * re-checks its own gate setting and returns a no-op report when its feature
 * is off, so requiring this file has ZERO effect until an operator explicitly
 * flips the relevant settings (see web/_sql/seeds/020_cron_gdpr_settings.sql
 * — every one of them seeds OFF/safe).
 *
 * Concurrency across both callers is a single MySQL advisory lock
 * (GET_LOCK/RELEASE_LOCK, timeout 0 — never blocks, always short-circuits to
 * "skipped"), which is crash-safe (MySQL releases it automatically if the
 * holding connection dies) and works unmodified on Dreamhost shared hosting.
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne/dbUpdate/dbDelete),
 *               settings.php (getSetting), activity_logger.php (logActivity),
 *               data_rights.php (g2ml_processDataDeletion — #163's existing,
 *               untouched executor), session.php (cleanExpiredSessions).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.7.0 — GDPR Scheduled Jobs (#163, #167)
 *
 * 📖 References:
 *     - GDPR Art 17 (erasure SLA):  https://gdpr-info.eu/art-17-gdpr/
 *     - GDPR Recital 26 (anon.):    https://gdpr-info.eu/recitals/no-26/
 *     - MySQL GET_LOCK:             https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html
 *     - Existing executor:          web/_functions/data_rights.php
 *                                   (g2ml_processDataDeletion, g2ml_anonymiseUserData)
 *     - Standalone-endpoint model:  web/Go2My.Link/_admin/public_html/analytics-export.php
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
// 🔑 Auth / Parsing Helpers (PURE unless noted)
// ============================================================================

/**
 * Constant-time comparison of the presented cron token against the stored
 * token.
 *
 * PURE — no DB, no settings lookups; every input is a parameter so this is
 * fully unit-testable without a database.
 *
 * Refuses (returns false) on: a null/empty provided token; a null/empty
 * stored token (the fail-safe that keeps the endpoint unreachable while
 * cron.dispatch_token is unset — its seeded default); and a stored token
 * shorter than 32 characters (a weak owner-set token must fail closed rather
 * than "work").
 *
 * Both sides are hashed (SHA-256, raw binary) BEFORE hash_equals() so the
 * comparison is constant-time end to end and never leaks the stored token's
 * true length via timing.
 *
 * @param  string|null $providedToken  The token presented by the caller.
 * @param  string|null $storedToken    The token from getSetting('cron.dispatch_token').
 * @return bool                        True only on an exact, well-formed match.
 *
 * 📖 Reference: https://www.php.net/manual/en/function.hash-equals.php
 */
function g2ml_cronVerifyToken(?string $providedToken, ?string $storedToken): bool
{
    if (!is_string($providedToken) || $providedToken === '')
    {
        return false;
    }

    if (!is_string($storedToken) || $storedToken === '')
    {
        return false;
    }

    if (strlen($storedToken) < 32)
    {
        return false;
    }

    $providedDigest = hash('sha256', $providedToken, true);
    $storedDigest   = hash('sha256', $storedToken, true);

    return hash_equals($storedDigest, $providedDigest);
}

/**
 * PURE — read the presented cron token from the request: the
 * X-G2ML-Cron-Token header takes priority, falling back to ?token= for
 * header-less schedulers, else null.
 *
 * @param  array $server  The $_SERVER superglobal (or an equivalent test array).
 * @param  array $get     The $_GET superglobal (or an equivalent test array).
 * @return string|null    The presented token, or null when neither is set.
 */
function g2ml_cronReadPresentedToken(array $server, array $get): ?string
{
    if (isset($server['HTTP_X_G2ML_CRON_TOKEN'])
        && is_string($server['HTTP_X_G2ML_CRON_TOKEN'])
        && $server['HTTP_X_G2ML_CRON_TOKEN'] !== '')
    {
        return $server['HTTP_X_G2ML_CRON_TOKEN'];
    }

    if (isset($get['token']) && is_string($get['token']) && $get['token'] !== '')
    {
        return $get['token'];
    }

    return null;
}

/**
 * PURE — normalise the caller-supplied ?job= value to one of 'deletion',
 * 'retention', or 'all'.
 *
 * An absent value (null) or an empty string both mean "run everything" and
 * normalise to 'all'. Any value that is not a string, or a string that is not
 * exactly one of the three accepted (lower-case only — 'ALL' is rejected, not
 * silently folded) is invalid and returns null so the caller can 400.
 *
 * @param  mixed $rawJob  The raw $_GET['job'] value (or an equivalent test value).
 * @return string|null    'deletion'|'retention'|'all', or null when invalid.
 */
function g2ml_cronNormaliseJobName(mixed $rawJob): ?string
{
    if ($rawJob === null)
    {
        return 'all';
    }

    if (!is_string($rawJob))
    {
        return null;
    }

    if ($rawJob === '')
    {
        return 'all';
    }

    $validJobNames = ['deletion', 'retention', 'all'];

    if (in_array($rawJob, $validJobNames, true))
    {
        return $rawJob;
    }

    return null;
}

/**
 * Emit a byte-identical generic 404 and exit.
 *
 * NOT pure — sends headers and terminates the request. Used for EVERY
 * authorisation failure path (feature off, no token configured, no token
 * presented, wrong token) so the endpoint's response can never be used as an
 * oracle for which of those is true — it is indistinguishable from a file
 * that does not exist.
 *
 * @return void  Always exits — never returns.
 */
function g2ml_cronEmitNotFound(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

// ============================================================================
// 🔒 Concurrency — MySQL Advisory Lock
// ============================================================================

/**
 * Acquire the shared cron advisory lock with a zero-second timeout.
 *
 * Uses GET_LOCK(CONCAT(DATABASE(), ':g2ml_cron'), 0). The DATABASE() prefix
 * namespaces the lock name on a shared MySQL server (Dreamhost hosts many
 * schemas per server); a zero timeout means this call NEVER blocks — it
 * either acquires immediately or fails immediately, so a caller that loses
 * the race short-circuits to a 'skipped' report rather than queuing. The lock
 * is automatically released by MySQL if the holding connection dies, so a
 * killed FastCGI worker can never leave the job permanently locked.
 *
 * @return bool  True only when GET_LOCK() returned integer 1.
 *
 * 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html#function_get-lock
 */
function g2ml_cronAcquireLock(): bool
{
    $row = dbSelectOne("SELECT GET_LOCK(CONCAT(DATABASE(), ':g2ml_cron'), 0) AS lockResult", '', []);

    if (!is_array($row))
    {
        return false;
    }

    return ((int) $row['lockResult']) === 1;
}

/**
 * Release the shared cron advisory lock. Best-effort — the lock self-releases
 * on connection close regardless, so a failure here is not fatal to anything.
 *
 * @return void
 *
 * 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html#function_release-lock
 */
function g2ml_cronReleaseLock(): void
{
    dbSelectOne("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':g2ml_cron')) AS releaseResult", '', []);
}

// ============================================================================
// 🧮 Setting Clamps (PURE — unit-testable fail-safes)
// ============================================================================

/**
 * PURE — resolve the effective grace-period (in days) a deletion request must
 * sit for before it becomes eligible for execution.
 *
 * Any non-numeric value, or a value outside the sane [1, 365] window, falls
 * back to the published default of 30 — this NEVER lets a misconfigured
 * setting shrink the grace window to 0 (which would let a request be
 * executed the instant it is created, defeating the cancellation window the
 * confirmation email promises) or silently balloon it to something absurd.
 *
 * @param  mixed $settingValue  The raw compliance.data_deletion_grace_days value.
 * @return int                  A grace period in days, always within [1, 365].
 */
function g2ml_cronEffectiveGraceDays(mixed $settingValue): int
{
    if (!is_numeric($settingValue))
    {
        return 30;
    }

    $graceDays = (int) $settingValue;

    if ($graceDays < 1)
    {
        return 30;
    }

    if ($graceDays > 365)
    {
        return 30;
    }

    return $graceDays;
}

/**
 * PURE — resolve the deletion executor's operating mode from its two gate
 * settings.
 *
 * Fail-safe by construction: the enabled flag must be the exact boolean
 * `true` to leave 'disabled', and the dry-run flag must be the exact boolean
 * `false` to unlock 'live' — anything else (missing, null, the string '0', an
 * integer, an object) is treated as "still dry-run", never as "live". This
 * means a setting read that returns an unexpected type (e.g. a settings-cache
 * miss returning the caller's own default) can never accidentally authorise
 * an irreversible run.
 *
 * @param  mixed $enabledSetting  getSetting('gdpr.deletion_job_enabled', false).
 * @param  mixed $dryRunSetting   getSetting('gdpr.deletion_job_dry_run', true).
 * @return string                 'disabled' | 'dry-run' | 'live'.
 */
function g2ml_cronDeletionMode(mixed $enabledSetting, mixed $dryRunSetting): string
{
    if ($enabledSetting !== true)
    {
        return 'disabled';
    }

    if ($dryRunSetting !== false)
    {
        return 'dry-run';
    }

    return 'live';
}

/**
 * PURE — clamp a raw setting value to an integer within [$min, $max],
 * falling back to $fallback when the raw value is not numeric.
 *
 * Used for every bounded integer setting in this file: the deletion batch
 * size (1–100), the retention batch size (50–2000), time budgets, AND the
 * probabilistic divisor (called with $min = 0 so a configured value of 0 or
 * below clamps to exactly 0 — the divisor's own "disabled" sentinel — while a
 * non-numeric value still falls back to a sane default rather than to 0).
 *
 * @param  mixed $value     The raw setting value.
 * @param  int   $min       The minimum accepted value (inclusive).
 * @param  int   $max       The maximum accepted value (inclusive).
 * @param  int   $fallback  Returned when $value is not numeric.
 * @return int              A value within [$min, $max], or $fallback.
 */
function g2ml_cronClampInt(mixed $value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value))
    {
        return $fallback;
    }

    $intValue = (int) $value;

    if ($intValue < $min)
    {
        return $min;
    }

    if ($intValue > $max)
    {
        return $max;
    }

    return $intValue;
}

// ============================================================================
// 🚦 Dispatch
// ============================================================================

/**
 * Run the requested job(s) and assemble the run report.
 *
 * Under job='all', deletion runs BEFORE retention — the erasure SLA outranks
 * housekeeping. Each sub-job re-checks its OWN enable gate internally;
 * dispatch never overrides or bypasses them. Retention receives whatever
 * time budget remains after deletion returns (floored at 1 second) so a slow
 * deletion pass cannot starve retention of its own accounting entirely.
 *
 * @param  string $jobName            'deletion' | 'retention' | 'all'.
 * @param  int    $timeBudgetSeconds  The overall soft time budget for this run.
 * @return array                      {status, environment, jobName, startedAt,
 *                                     elapsedMs, partial, jobs: {...}}
 */
function g2ml_cronRunJobs(string $jobName, int $timeBudgetSeconds): array
{
    $startedAt = gmdate('Y-m-d H:i:s');
    $startTime = microtime(true);
    $deadline  = $startTime + $timeBudgetSeconds;

    $environment = 'unknown';

    if (function_exists('g2ml_getEnvironment'))
    {
        $environment = g2ml_getEnvironment();
    }

    $jobs    = [];
    $partial = false;

    if ($jobName === 'all' || $jobName === 'deletion')
    {
        $jobs['deletion'] = g2ml_cronRunDeletionJob($timeBudgetSeconds);

        if (($jobs['deletion']['partial'] ?? false) === true)
        {
            $partial = true;
        }
    }

    if ($jobName === 'all' || $jobName === 'retention')
    {
        $remainingSeconds = (int) round($deadline - microtime(true));

        if ($remainingSeconds < 1)
        {
            $remainingSeconds = 1;
        }

        $batchSize = g2ml_cronClampInt(getSetting('retention.batch_size', 500), 50, 2000, 500);

        $jobs['retention'] = g2ml_cronRunRetentionJob($remainingSeconds, $batchSize);

        if (($jobs['retention']['partial'] ?? false) === true)
        {
            $partial = true;
        }
    }

    $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

    return [
        'status'      => 'ok',
        'environment' => $environment,
        'jobName'     => $jobName,
        'startedAt'   => $startedAt,
        'elapsedMs'   => $elapsedMs,
        'partial'     => $partial,
        'jobs'        => $jobs,
    ];
}

// ============================================================================
// 🗑️ #163 Deletion Executor
// ============================================================================

/**
 * Run the GDPR deletion executor, subject to ALL of interlocks I3–I11 (I1/I2
 * — the endpoint master switch and token auth — are enforced by the endpoint
 * BEFORE this is ever called; see cron.php's own docblock for the full list).
 *
 * Control flow:
 *   1. Resolve the mode (disabled/dry-run/live) — disabled returns immediately,
 *      touching nothing.
 *   2. Resolve the grace period and select due rows (requestType='deletion',
 *      status='pending', older than the grace window) — I6.
 *   3. Dry-run: log what WOULD be processed per row (data_deletion_due_dryrun)
 *      and return — NO writes to any subject data, NO status change.
 *   4. Live: resolve and validate the configured actor UID — refuses to start
 *      (I5) unless it references a real tblUsers row. Then, per row, bounded
 *      by the batch cap (I10) and the time budget, calls the EXISTING
 *      g2ml_processDataDeletion() (data_rights.php) — no reimplementation.
 *      That function re-checks status='pending' itself (I8) and
 *      g2ml_anonymiseUserData() is already transactional (I9). One failed row
 *      is logged and does not stop the batch.
 *   5. Full logging throughout (I11): per-row activity rows plus a run
 *      summary.
 *
 * Known edge case (by design, not a bug): if g2ml_processDataDeletion()'s
 * internal status UPDATE fails after a successful anonymise, the next run
 * re-selects the same row and re-anonymises it — harmless, because
 * g2ml_anonymiseUserData() is idempotent, making this a self-healing retry
 * rather than a hazard.
 *
 * The tblDataDeletionRequests.requestType enum also allows 'anonymisation',
 * but no code path in this application ever inserts that value — this
 * function deliberately only ever selects requestType='deletion'.
 *
 * @param  int $timeBudgetSeconds  Soft time budget for the live-mode loop.
 * @return array                   A job report (shape varies by mode/outcome).
 */
function g2ml_cronRunDeletionJob(int $timeBudgetSeconds): array
{
    $enabledSetting = getSetting('gdpr.deletion_job_enabled', false);
    $dryRunSetting  = getSetting('gdpr.deletion_job_dry_run', true);

    $mode = g2ml_cronDeletionMode($enabledSetting, $dryRunSetting);

    if ($mode === 'disabled')
    {
        return ['enabled' => false];
    }

    $graceDays  = g2ml_cronEffectiveGraceDays(getSetting('compliance.data_deletion_grace_days', 30));
    $batchLimit = g2ml_cronClampInt(getSetting('gdpr.deletion_job_batch', 10), 1, 100, 10);

    $dueRequests = g2ml_cronFindDueDeletionRequests($graceDays, $batchLimit);

    if ($dueRequests === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_cronRunDeletionJob — failed to query due deletion requests.');

        return [
            'enabled' => true,
            'mode'    => $mode,
            'error'   => 'query_failed',
        ];
    }

    if ($mode === 'dry-run')
    {
        $wouldProcess = [];

        foreach ($dueRequests as $dueRequest)
        {
            $requestUID = (int) $dueRequest['requestUID'];

            logActivity('data_deletion_due_dryrun', 'success', null, [
                'userUID' => (int) $dueRequest['userUID'],
                'logData' => [
                    'requestUID'        => $requestUID,
                    'requestedAt'       => $dueRequest['createdAt'],
                    'eligibleSinceDays' => $graceDays,
                ],
            ]);

            $wouldProcess[] = $requestUID;
        }

        return [
            'enabled'      => true,
            'mode'         => $mode,
            'due'          => count($dueRequests),
            'wouldProcess' => $wouldProcess,
        ];
    }

    // ------------------------------------------------------------------------
    // Live mode only from here — I5: refuse to start unless the configured
    // actor UID references a real tblUsers row.
    // ------------------------------------------------------------------------
    $actorUIDSetting = getSetting('gdpr.deletion_job_actor_uid', null);

    $actorUID = 0;

    if (is_numeric($actorUIDSetting))
    {
        $actorUID = (int) $actorUIDSetting;
    }

    $actorRow = null;

    if ($actorUID > 0)
    {
        $actorRow = dbSelectOne('SELECT userUID FROM tblUsers WHERE userUID = ?', 'i', [$actorUID]);
    }

    if ($actorUID <= 0 || !is_array($actorRow))
    {
        error_log('[Go2My.Link] ERROR: g2ml_cronRunDeletionJob — gdpr.deletion_job_actor_uid is unset or does not reference a real user; refusing to run live.');

        logActivity('cron_deletion_job_run', 'error', null, [
            'logData' => ['error' => 'actor_not_configured'],
        ]);

        return [
            'enabled' => true,
            'mode'    => $mode,
            'error'   => 'actor_not_configured',
        ];
    }

    // ------------------------------------------------------------------------
    // I10 — batch cap already applied to the SELECT (LIMIT $batchLimit);
    // I7 is the caller's advisory lock; I6 is already baked into the WHERE
    // clause of g2ml_cronFindDueDeletionRequests(). Bound the LOOP itself by
    // the time budget so a slow batch stops cleanly rather than overrunning
    // the FastCGI request limit.
    // ------------------------------------------------------------------------
    $deadline  = microtime(true) + $timeBudgetSeconds;
    $processed = 0;
    $failed    = 0;
    $partial   = false;

    foreach ($dueRequests as $dueRequest)
    {
        if (microtime(true) >= $deadline)
        {
            $partial = true;
            break;
        }

        $requestUID = (int) $dueRequest['requestUID'];
        $userUID    = (int) $dueRequest['userUID'];

        // I8/I9 — g2ml_processDataDeletion() re-reads and requires
        // status='pending' itself, and its anonymisation call is already
        // transactional. No reimplementation here.
        $success = g2ml_processDataDeletion($requestUID, $actorUID);

        if ($success === true)
        {
            $status    = 'success';
            $processed = $processed + 1;
        }
        else
        {
            $status = 'failed';
            $failed = $failed + 1;
            error_log('[Go2My.Link] ERROR: g2ml_cronRunDeletionJob — g2ml_processDataDeletion failed for requestUID ' . $requestUID);
        }

        logActivity('data_deletion_executed', $status, null, [
            'userUID' => $userUID,
            'logData' => ['requestUID' => $requestUID],
        ]);
    }

    logActivity('cron_deletion_job_run', 'success', null, [
        'logData' => [
            'mode'      => $mode,
            'due'       => count($dueRequests),
            'processed' => $processed,
            'failed'    => $failed,
            'partial'   => $partial,
        ],
    ]);

    return [
        'enabled'   => true,
        'mode'      => $mode,
        'due'       => count($dueRequests),
        'processed' => $processed,
        'failed'    => $failed,
        'partial'   => $partial,
    ];
}

/**
 * Select the oldest pending deletion requests that have already cleared their
 * grace period, oldest (closest to SLA breach) first.
 *
 * Only requestType='deletion' rows are ever selected — 'export' rows and the
 * (never-inserted) 'anonymisation' enum value are excluded by the WHERE
 * clause itself, not by post-filtering.
 *
 * @param  int         $graceDays   The grace period in days (already clamped
 *                                  by g2ml_cronEffectiveGraceDays()).
 * @param  int         $batchLimit  Maximum rows to return (already clamped by
 *                                  g2ml_cronClampInt()).
 * @return array|false              Rows of {requestUID, userUID, createdAt},
 *                                  or false on a query error.
 */
function g2ml_cronFindDueDeletionRequests(int $graceDays, int $batchLimit): array|false
{
    return dbSelect(
        "SELECT requestUID, userUID, createdAt
           FROM tblDataDeletionRequests
          WHERE requestType = 'deletion'
            AND status = 'pending'
            AND createdAt <= DATE_SUB(NOW(), INTERVAL ? DAY)
          ORDER BY createdAt ASC
          LIMIT ?",
        'ii',
        [$graceDays, $batchLimit]
    );
}

// ============================================================================
// 🧹 #167 Retention Sweeps
// ============================================================================

/**
 * Run every retention sweep, gated behind the single master switch
 * retention.enforcement_enabled. All sweeps are idempotent — re-running
 * converges (their WHERE clauses exclude already-treated rows) — so there is
 * no harm in this being called from BOTH the endpoint and the probabilistic
 * page_init.php hook.
 *
 * @param  int $timeBudgetSeconds  Overall soft time budget for this call.
 * @param  int $batchSize          LIMIT per UPDATE/DELETE chunk (already
 *                                 clamped by the caller).
 * @return array                   {enabled, partial, anonymise, purge, exports, sessions}
 */
function g2ml_cronRunRetentionJob(int $timeBudgetSeconds, int $batchSize): array
{
    $enforcementEnabled = getSetting('retention.enforcement_enabled', false);

    if ($enforcementEnabled !== true)
    {
        return ['enabled' => false];
    }

    $deadline = microtime(true) + $timeBudgetSeconds;

    $anonymiseDays = g2ml_cronClampInt(getSetting('retention.activity_log_anonymise_days', 90), 0, 3650, 90);
    $purgeDays     = g2ml_cronClampInt(getSetting('retention.activity_log_purge_days', 0), 0, 3650, 0);

    $anonymiseReport = g2ml_retentionAnonymiseActivityLog($anonymiseDays, $batchSize, $deadline);

    $purgeReport = [];

    if ($purgeDays <= 0)
    {
        $purgeReport = ['skipped' => 'disabled'];
    }
    elseif ($purgeDays < $anonymiseDays)
    {
        // Misconfiguration guard: purging rows the anonymise sweep has not
        // yet reached would destroy detailed data the policy still promises
        // to keep (anonymised) up to the anonymise window.
        error_log(
            '[Go2My.Link] WARNING: g2ml_cronRunRetentionJob — retention.activity_log_purge_days ('
            . $purgeDays . ') is less than retention.activity_log_anonymise_days ('
            . $anonymiseDays . '); skipping the purge sweep this run.'
        );

        $purgeReport = ['skipped' => 'misconfigured'];
    }
    else
    {
        $purgeReport = g2ml_retentionPurgeActivityLog($purgeDays, $batchSize, $deadline);
    }

    $exportReport   = g2ml_retentionPurgeExpiredExports($deadline);
    $sessionsReport = g2ml_retentionCleanSessions();

    $partial = false;

    $partialCandidates = [$anonymiseReport, $purgeReport, $exportReport];

    foreach ($partialCandidates as $subReport)
    {
        if (($subReport['partial'] ?? false) === true)
        {
            $partial = true;
        }
    }

    return [
        'enabled'   => true,
        'partial'   => $partial,
        'anonymise' => $anonymiseReport,
        'purge'     => $purgeReport,
        'exports'   => $exportReport,
        'sessions'  => $sessionsReport,
    ];
}

/**
 * Anonymise (NOT delete) tblActivityLog rows older than $olderThanDays:
 * ipAddress → '0.0.0.0' (the same sentinel g2ml_anonymiseUserData() already
 * uses) and requestUserAgent → NULL. Rows themselves are kept so the
 * aggregate columns (countryCode, deviceType, browserName, …) survive for
 * analytics, matching the published "90 days detailed, then aggregated"
 * promise.
 *
 * Idempotent by construction: the `ipAddress <> '0.0.0.0'` predicate drops
 * already-anonymised rows out of the working set, so re-running converges to
 * zero affected rows and stays cheap. Uses the existing IDX_log_created index
 * — no new index or migration required.
 *
 * @param  int   $olderThanDays  Age threshold in days. 0 disables this sweep.
 * @param  int   $batchSize      LIMIT per UPDATE chunk.
 * @param  float $deadline       microtime(true) value to stop looping by.
 * @return array                 {swept, batches, partial} or {skipped: 'disabled'}
 */
function g2ml_retentionAnonymiseActivityLog(int $olderThanDays, int $batchSize, float $deadline): array
{
    if ($olderThanDays <= 0)
    {
        return ['skipped' => 'disabled'];
    }

    $totalSwept = 0;
    $batches    = 0;
    $partial    = false;

    while (true)
    {
        if (microtime(true) >= $deadline)
        {
            $partial = true;
            break;
        }

        $affected = dbUpdate(
            "UPDATE tblActivityLog
                SET ipAddress = '0.0.0.0', requestUserAgent = NULL
              WHERE createdAt < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND ipAddress <> '0.0.0.0'
              LIMIT ?",
            'ii',
            [$olderThanDays, $batchSize]
        );

        if ($affected === false)
        {
            error_log('[Go2My.Link] ERROR: g2ml_retentionAnonymiseActivityLog — UPDATE failed.');
            break;
        }

        $totalSwept = $totalSwept + $affected;
        $batches    = $batches + 1;

        if ($affected < $batchSize)
        {
            break;
        }
    }

    return ['swept' => $totalSwept, 'batches' => $batches, 'partial' => $partial];
}

/**
 * Permanently DELETE tblActivityLog rows older than $olderThanDays.
 *
 * Defaults to OFF (retention.activity_log_purge_days = '0') — see the
 * design's owner sign-off item on the anonymise-vs-purge policy mismatch.
 * The caller (g2ml_cronRunRetentionJob()) already refuses to invoke this
 * unless the purge window is >= the anonymise window, so a row reaching this
 * DELETE has already been anonymised for at least that many days.
 *
 * @param  int   $olderThanDays  Age threshold in days. 0 disables this sweep.
 * @param  int   $batchSize      LIMIT per DELETE chunk.
 * @param  float $deadline       microtime(true) value to stop looping by.
 * @return array                 {swept, batches, partial} or {skipped: 'disabled'}
 */
function g2ml_retentionPurgeActivityLog(int $olderThanDays, int $batchSize, float $deadline): array
{
    if ($olderThanDays <= 0)
    {
        return ['skipped' => 'disabled'];
    }

    $totalSwept = 0;
    $batches    = 0;
    $partial    = false;

    while (true)
    {
        if (microtime(true) >= $deadline)
        {
            $partial = true;
            break;
        }

        $affected = dbDelete(
            "DELETE FROM tblActivityLog
              WHERE createdAt < DATE_SUB(NOW(), INTERVAL ? DAY)
              LIMIT ?",
            'ii',
            [$olderThanDays, $batchSize]
        );

        if ($affected === false)
        {
            error_log('[Go2My.Link] ERROR: g2ml_retentionPurgeActivityLog — DELETE failed.');
            break;
        }

        $totalSwept = $totalSwept + $affected;
        $batches    = $batches + 1;

        if ($affected < $batchSize)
        {
            break;
        }
    }

    return ['swept' => $totalSwept, 'batches' => $batches, 'partial' => $partial];
}

/**
 * Sweep expired data-export bundles: for every tblDataDeletionRequests row
 * with requestType='export' whose exportExpiresAt has passed, confine the
 * stored path to the exports directory (g2ml_retentionPathIsConfined() — the
 * same strncmp guard g2ml_streamExportDownload() already uses), unlink the
 * file if present, then NULL exportFilePath on the row (the row itself is
 * kept as an audit trail). A path that fails confinement is NEVER unlinked —
 * only logged — and its DB path is left untouched for investigation.
 *
 * Also sweeps orphaned export_*.json files with no matching DB row at all
 * (covers files left behind by any pre-#162-era code path), once they are
 * older than the configured export-expiry window
 * (compliance.data_export_expiry_hours).
 *
 * @param  float $deadline  microtime(true) value to stop looping by.
 * @return array            {swept, orphansSwept, partial}
 */
function g2ml_retentionPurgeExpiredExports(float $deadline): array
{
    $exportsDir     = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';
    $realExportsDir = realpath($exportsDir);

    $swept   = 0;
    $partial = false;

    $expiredRows = dbSelect(
        "SELECT requestUID, exportFilePath
           FROM tblDataDeletionRequests
          WHERE requestType = 'export'
            AND exportFilePath IS NOT NULL
            AND exportExpiresAt IS NOT NULL
            AND exportExpiresAt < NOW()
          LIMIT 50",
        '',
        []
    );

    if ($expiredRows === false)
    {
        error_log('[Go2My.Link] ERROR: g2ml_retentionPurgeExpiredExports — failed to query expired export rows.');
        $expiredRows = [];
    }

    foreach ($expiredRows as $expiredRow)
    {
        if (microtime(true) >= $deadline)
        {
            $partial = true;
            break;
        }

        $requestUID = (int) $expiredRow['requestUID'];
        $filePath   = (string) $expiredRow['exportFilePath'];

        if ($realExportsDir !== false)
        {
            $realFilePath = realpath($filePath);

            if ($realFilePath !== false && g2ml_retentionPathIsConfined($realExportsDir, $realFilePath))
            {
                if (is_file($realFilePath))
                {
                    unlink($realFilePath);
                }
            }
            elseif ($realFilePath !== false)
            {
                error_log(
                    '[Go2My.Link] ERROR: g2ml_retentionPurgeExpiredExports — exportFilePath escapes the exports '
                    . 'directory for requestUID ' . $requestUID . '; leaving the file untouched.'
                );
            }
        }

        dbUpdate(
            "UPDATE tblDataDeletionRequests SET exportFilePath = NULL WHERE requestUID = ?",
            'i',
            [$requestUID]
        );

        $swept = $swept + 1;
    }

    // ------------------------------------------------------------------------
    // Orphan sweep — files on disk with no matching DB row at all.
    // ------------------------------------------------------------------------
    $orphansSwept = 0;

    $expiryHours = g2ml_cronClampInt(getSetting('compliance.data_export_expiry_hours', 48), 1, 8760, 48);
    $expirySeconds = $expiryHours * 3600;

    if ($realExportsDir !== false && is_dir($realExportsDir))
    {
        $entries = scandir($realExportsDir);

        if ($entries !== false)
        {
            foreach ($entries as $entry)
            {
                if (microtime(true) >= $deadline)
                {
                    $partial = true;
                    break;
                }

                if (preg_match('/^export_.*\.json$/', $entry) !== 1)
                {
                    continue;
                }

                $entryPath = $realExportsDir . DIRECTORY_SEPARATOR . $entry;

                if (!is_file($entryPath))
                {
                    continue;
                }

                $existingRow = dbSelectOne(
                    "SELECT requestUID FROM tblDataDeletionRequests WHERE exportFilePath = ? LIMIT 1",
                    's',
                    [$entryPath]
                );

                if ($existingRow !== null && $existingRow !== false)
                {
                    continue;
                }

                $mtime = filemtime($entryPath);

                if ($mtime !== false && $mtime < (time() - $expirySeconds))
                {
                    unlink($entryPath);
                    $orphansSwept = $orphansSwept + 1;
                }
            }
        }
    }

    return ['swept' => $swept, 'orphansSwept' => $orphansSwept, 'partial' => $partial];
}

/**
 * Thin wrapper over the existing cleanExpiredSessions() (session.php),
 * exposed here so g2ml_cronRunRetentionJob() reports a session-sweep count
 * alongside the other retention sweeps. The existing 1-in-100 probabilistic
 * call already inside page_init.php is left completely untouched (belt and
 * braces — this is deliberately a duplicate safety net, not a replacement).
 *
 * @return array  {swept}
 */
function g2ml_retentionCleanSessions(): array
{
    $swept = 0;

    if (function_exists('cleanExpiredSessions'))
    {
        $swept = cleanExpiredSessions();
    }

    return ['swept' => $swept];
}

/**
 * PURE — confinement check: is $realFilePath located inside $realBaseDir?
 *
 * Mirrors g2ml_streamExportDownload()'s own strncmp() guard
 * (data_rights.php) exactly, including appending the trailing directory
 * separator to the base directory before comparing — WITHOUT that trailing
 * separator, a sibling directory that merely shares the same string prefix
 * (e.g. "/exports" matching against "/exports_evil/file") would incorrectly
 * pass as "confined". Both arguments are expected to already be realpath()-
 * resolved by the caller; this function does no I/O itself, so it is fully
 * unit-testable without touching disk.
 *
 * @param  string $realBaseDir   The confining directory (already realpath()'d).
 * @param  string $realFilePath  The candidate file path (already realpath()'d).
 * @return bool                  True only when $realFilePath is inside $realBaseDir.
 */
function g2ml_retentionPathIsConfined(string $realBaseDir, string $realFilePath): bool
{
    if ($realBaseDir === '' || $realFilePath === '')
    {
        return false;
    }

    $confinedPrefix = rtrim($realBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return (strncmp($realFilePath, $confinedPrefix, strlen($confinedPrefix)) === 0);
}

// ============================================================================
// 🎲 Probabilistic Fallback (page_init.php hook)
// ============================================================================

/**
 * Called once per request from _includes/page_init.php, AFTER
 * loadSettingsCache() has run (unlike the pre-settings 1% session hook this
 * mirrors — retention needs its settings available first). Runs RETENTION
 * ONLY — this NEVER touches the #163 deletion job; that executor is
 * endpoint-only by design (see this file's own top docblock).
 *
 * Early-out order (cheapest checks first, mirroring api_ratelimit.php's own
 * probabilistic prune):
 *   1. The dispatch endpoint marks itself with G2ML_CRON_DISPATCH before
 *      page_init.php runs — if set, this request already IS the endpoint
 *      run, so return immediately (no self-race for the advisory lock).
 *   2. Clamp retention.probabilistic_divisor; <= 0 means the fallback path is
 *      disabled entirely.
 *   3. Roll mt_rand(1, divisor) — only a 1-in-divisor chance continues.
 *   4. Check retention.enforcement_enabled — the master gate.
 *   5. Try to acquire the advisory lock — if another run already holds it,
 *      skip quietly rather than compete or queue.
 *   6. Run the retention job with the fallback time budget, then release the
 *      lock in a finally block.
 *
 * @return void
 */
function g2ml_cronMaybeRunProbabilisticRetention(): void
{
    if (defined('G2ML_CRON_DISPATCH'))
    {
        return;
    }

    $divisor = g2ml_cronClampInt(getSetting('retention.probabilistic_divisor', 500), 0, PHP_INT_MAX, 500);

    if ($divisor <= 0)
    {
        return;
    }

    if (mt_rand(1, $divisor) !== 1)
    {
        return;
    }

    $enforcementEnabled = getSetting('retention.enforcement_enabled', false);

    if ($enforcementEnabled !== true)
    {
        return;
    }

    if (g2ml_cronAcquireLock() !== true)
    {
        return;
    }

    try
    {
        $timeBudget = g2ml_cronClampInt(getSetting('retention.fallback_time_budget_seconds', 2), 1, 30, 2);
        $batchSize  = g2ml_cronClampInt(getSetting('retention.batch_size', 500), 50, 2000, 500);

        g2ml_cronRunRetentionJob($timeBudget, $batchSize);
    }
    finally
    {
        g2ml_cronReleaseLock();
    }
}
