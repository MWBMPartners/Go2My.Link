-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔑 Go2My.Link — Public API Framework Settings (#38 / #39)
-- ============================================================================
-- Controls the versioned public API (/api/v1/*): the master kill switch, the
-- default per-key rate limits (overridable per key via
-- tblAPIKeys.rateLimitOverride), the audit-log retention window, and (#39)
-- the pre-authentication IP failed-auth backoff. See
-- web/_functions/api_auth.php, web/_functions/api_ratelimit.php, and
-- web/Go2My.Link/public_html/api/v1/index.php.
--
-- Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.1.0
-- @since      v1.1.0 — Phase 7 (#38, extended #39)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('api.public_enabled', 'System', NULL,
 '1', '1', 'Master switch for the versioned public API (/api/v1/*). When off, every request receives a 503 before authentication is attempted.',
 'boolean', 0, 1),
('api.default_daily_limit', 'System', NULL,
 '5000', '5000', 'Default maximum API requests per rolling 24-hour window per key, used when tblAPIKeys.rateLimitOverride is NULL.',
 'integer', 0, 1),
('api.default_per_minute', 'System', NULL,
 '60', '60', 'Default maximum API requests per rolling 60-second window per key (burst limit), applied to every key regardless of rateLimitOverride.',
 'integer', 0, 1),
('api.request_log_retention_days', 'System', NULL,
 '30', '30', 'How many days of tblAPIRequestLog rows to retain. Older rows are swept by a probabilistic (1-in-100 request) prune rather than a scheduled job.',
 'integer', 0, 1),
('api.preauth_fail_per_minute', 'System', NULL,
 '20', '20', 'Maximum 401 (failed-authentication) responses tolerated from a single IP address within a rolling 60-second window before the pre-auth backoff short-circuits further requests with 429 — BEFORE g2ml_apiVerifyKey() is ever called. Set to 0 to disable the guard.',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
