-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- ⏰ Go2My.Link — Scheduled Jobs / GDPR Deletion + Retention Settings (#163, #167)
-- ============================================================================
-- Every setting below defaults OFF/safe. A fresh install with this seed
-- applied changes NOTHING until an operator explicitly flips settings, in the
-- order documented in the design runbook (HANDOFF.md / issue #178):
--
--   1. cron.enabled = '0' and cron.dispatch_token = NULL — the dispatch
--      endpoint (web/Go2My.Link/_admin/public_html/cron.php) answers a
--      byte-identical generic 404 to every request until BOTH are set.
--   2. gdpr.deletion_job_enabled = '0' AND gdpr.deletion_job_dry_run = '1' —
--      the #163 deletion executor is double-gated. Even once "enabled", it
--      only LOGS what it would do (data_deletion_due_dryrun) until dry_run is
--      explicitly set to the string '0' (which getSetting() casts to boolean
--      false — the ONLY value that unlocks live/irreversible processing).
--   3. retention.enforcement_enabled = '0' — the #167 master gate for every
--      retention sweep, on BOTH the endpoint and the probabilistic
--      page_init.php fallback path.
--
-- gdpr.deletion_job_actor_uid is seeded NULL deliberately: a live deletion
-- run refuses to start at all while it is unset (see cron.php's
-- g2ml_cronRunDeletionJob() — I5), because tblDataDeletionRequests.
-- processedByUserUID is a real FK to tblUsers and a fake/absent UID must fail
-- cleanly before touching any row, not rely on the FK to reject it.
--
-- Uses ON DUPLICATE KEY UPDATE (description only) for idempotent re-runs —
-- exact house pattern from 017_geolocation_settings.sql — so re-applying this
-- seed on an already-configured instance never clobbers an operator's chosen
-- values.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.7.0 — GDPR Scheduled Jobs (#163, #167)
--
-- 📖 References:
--     - Job library:   web/_functions/cron.php
--     - Endpoint:      web/Go2My.Link/_admin/public_html/cron.php
--     - tblSettings:   web/_sql/schema/010_core_settings.sql
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('cron.enabled', 'System', NULL, '0', '0',
 'Master switch for the scheduled-jobs dispatch endpoint (admin.go2my.link/cron.php). OFF by default — the endpoint answers a byte-identical generic 404 for every request until this is 1 AND cron.dispatch_token is set.',
 'boolean', 0, 1),

('cron.dispatch_token', 'System', NULL, NULL, NULL,
 'Shared-secret token external schedulers present via the X-G2ML-Cron-Token header (preferred) or ?token= (fallback, but visible in Apache access logs). Set with setSetting(\'cron.dispatch_token\', g2ml_generateToken(), \'System\', NULL, true) so it is stored AES-256-GCM encrypted. Unset (NULL) means the endpoint can never authenticate anyone. Minimum accepted length is 32 characters — a shorter stored token fails closed rather than "works".',
 'string', 1, 1),

('cron.max_runtime_seconds', 'System', NULL, '50', '50',
 'Soft time budget (seconds) per dispatch-endpoint run. Dreamhost FastCGI kills long-running requests, so jobs stop cleanly between batches and report partial:true rather than being killed mid-write. Clamped to [1, 300].',
 'integer', 0, 1),

('gdpr.deletion_job_enabled', 'System', NULL, '0', '0',
 'Master gate for the #163 GDPR data-deletion executor (independent of cron.enabled — both must be on, plus a valid token, for the job to ever run). OFF by default.',
 'boolean', 0, 1),

('gdpr.deletion_job_dry_run', 'System', NULL, '1', '1',
 'When true (the default), the deletion job only LOGS which pending requests it would process (data_deletion_due_dryrun) and touches nothing. ONLY an explicit boolean false unlocks live/irreversible processing — any other value (missing, null, "0" as a loosely-typed value, an integer) is treated as "still dry-run" as a fail-safe.',
 'boolean', 0, 1),

('gdpr.deletion_job_batch', 'System', NULL, '10', '10',
 'Maximum number of pending deletion requests processed per run. Clamped to [1, 100] to bound the blast radius of a single run on shared hosting.',
 'integer', 0, 1),

('gdpr.deletion_job_actor_uid', 'System', NULL, NULL, NULL,
 'The tblUsers.userUID recorded as processedByUserUID for automated deletions (recommend a dedicated system/admin account, not a personal login). A LIVE run refuses to start at all while this is unset or does not reference a real user — dry-run mode is unaffected. Left unset (NULL) by default.',
 'integer', 0, 1),

('retention.enforcement_enabled', 'System', NULL, '0', '0',
 'Master gate for ALL #167 retention sweeps (activity-log anonymisation/purge, expired export cleanup, session cleanup), on both the dispatch endpoint and the probabilistic page_init.php fallback. OFF by default.',
 'boolean', 0, 1),

('retention.activity_log_anonymise_days', 'System', NULL, '90', '90',
 'Age (days) after which tblActivityLog.ipAddress is set to 0.0.0.0 and requestUserAgent to NULL, matching the published "90 days detailed, then aggregated" promise. Rows themselves are KEPT so aggregate analytics survive. 0 disables this sweep.',
 'integer', 0, 1),

('retention.activity_log_purge_days', 'System', NULL, '0', '0',
 'Age (days) after which tblActivityLog ROWS are permanently deleted. Defaults to 0 (never) — see the design owner sign-off item on the anonymise-vs-purge policy mismatch. If enabled, must be >= retention.activity_log_anonymise_days or the sweep skips itself with a logged warning.',
 'integer', 0, 1),

('retention.batch_size', 'System', NULL, '500', '500',
 'Rows per UPDATE/DELETE chunk for every retention sweep. Clamped to [50, 2000].',
 'integer', 0, 1),

('retention.probabilistic_divisor', 'System', NULL, '500', '500',
 'The probabilistic page_init.php retention fallback fires on a 1-in-N chance per ordinary request (mirrors the existing 1-in-100 session-cleanup idiom). 0 disables the fallback path entirely (the dispatch endpoint is unaffected).',
 'integer', 0, 1),

('retention.fallback_time_budget_seconds', 'System', NULL, '2', '2',
 'Time budget (seconds) for a retention sweep riding an ordinary visitor request via the probabilistic fallback (vs cron.max_runtime_seconds on the dedicated dispatch endpoint). Clamped to [1, 30].',
 'integer', 0, 1)

ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
