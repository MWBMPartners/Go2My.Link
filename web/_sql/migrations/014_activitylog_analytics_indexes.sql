-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 014: tblActivityLog analytics indexes (#41 / #125)
-- =============================================================================
-- Adds the two composite indexes the analytics data layer
-- (web/_functions/analytics.php) relies on for every aggregate query, so a
-- per-code or per-org date-range scan hits a single composite-index range
-- scan instead of filtering a single-column index result against 429K+ rows:
--
--   - IDX_log_shortcode_created (shortCode, createdAt) — the per-code
--     date-range queries (every analytics.php function when $shortCode is
--     supplied). This is the composite index #125 asked for explicitly.
--   - IDX_log_org_created (orgHandle, createdAt) — the whole-org date-range
--     queries (every analytics.php function when $shortCode is null, plus
--     g2ml_analyticsTopLinks(), which is always org-wide). Added alongside
--     #125's own ask because the analytics data layer needs an equivalent
--     composite for the "org + time" access pattern, not only "code + time".
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: both indexes are already part of
--     the base schema (web/_sql/schema/030_analytics.sql). Run this
--     migration ONLY to bring an ALREADY-IMPORTED earlier schema up to date.
--
-- IDEMPOTENT BY DESIGN (mirrors migrations 011/012): checks
-- information_schema first and only adds an index when it is absent, via a
-- throwaway stored procedure that drops itself afterwards. Safe to re-run
-- any number of times.
--
-- -----------------------------------------------------------------------------
-- Decision — standalone IDX_log_shortcode / IDX_log_org (#125's "review
-- whether these remain justified given write cost")
-- -----------------------------------------------------------------------------
-- KEPT for now, not dropped. Once the composites above exist, MySQL CAN
-- satisfy an shortCode-only or orgHandle-only equality lookup from the
-- composite's leftmost column alone, which makes the single-column indexes
-- largely redundant for READS. However, no call site in the current
-- codebase was found that queries tblActivityLog by shortCode or orgHandle
-- ALONE (grep across web/ turned up only ipAddress/userUID-scoped queries —
-- see checkAnonymousRateLimit() in shorturl_create.php and the login/contact/
-- forgot-password rate limiters), so dropping them is very likely safe, but
-- "very likely" is not "confirmed" for a production table with an existing
-- deployment history. Given the modest write-cost delta of two extra
-- single-column indexes versus the risk of removing an index some
-- un-audited call site still depends on, this migration ADDS the composites
-- without also dropping the single-column ones. Revisit as a dedicated,
-- narrowly-scoped follow-up once every tblActivityLog call site has been
-- inventoried with certainty.
--
-- -----------------------------------------------------------------------------
-- Decision — monthly partitioning (#125's other acceptance criterion)
-- -----------------------------------------------------------------------------
-- NOT enabled. tblActivityLog's own schema comment
-- (web/_sql/schema/030_analytics.sql) has carried a commented-out
-- `PARTITION BY RANGE (YEAR(createdAt) * 100 + MONTH(createdAt))` suggestion
-- since Phase 1. At the current production volume (429K+ rows, one
-- comparatively young/pre-launch product), the two composite indexes added
-- here are sufficient to keep every analytics query an index range scan
-- rather than a table scan. Partitioning adds real operational complexity
-- (partition maintenance/pruning, and MySQL requires every UNIQUE key —
-- there are none on this table today, so no immediate conflict — to include
-- the partitioning column) for no measurable benefit yet. Revisit if/when
-- tblActivityLog reaches the multi-million-row range, or when a retention/
-- pruning policy is introduced (partition-drop is a much cheaper bulk-delete
-- than a row-by-row DELETE at that point).
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (#41 / #125)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_014_add_activitylog_analytics_indexes`//

CREATE PROCEDURE `sp_migration_014_add_activitylog_analytics_indexes`()
BEGIN
    DECLARE v_shortcodeCreatedExists INT DEFAULT 0;
    DECLARE v_orgCreatedExists       INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_shortcodeCreatedExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblActivityLog'
       AND INDEX_NAME   = 'IDX_log_shortcode_created';

    IF v_shortcodeCreatedExists = 0 THEN
        ALTER TABLE `tblActivityLog`
            ADD INDEX `IDX_log_shortcode_created` (`shortCode`, `createdAt`);
    END IF;

    SELECT COUNT(*)
    INTO   v_orgCreatedExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblActivityLog'
       AND INDEX_NAME   = 'IDX_log_org_created';

    IF v_orgCreatedExists = 0 THEN
        ALTER TABLE `tblActivityLog`
            ADD INDEX `IDX_log_org_created` (`orgHandle`, `createdAt`);
    END IF;
END//

DELIMITER ;

CALL `sp_migration_014_add_activitylog_analytics_indexes`();

DROP PROCEDURE IF EXISTS `sp_migration_014_add_activitylog_analytics_indexes`;
