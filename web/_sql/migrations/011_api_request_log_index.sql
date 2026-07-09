-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 011: tblAPIRequestLog composite rate-limit index (#38)
-- =============================================================================
-- Adds IDX_apireq_key_created (apiKeyUID, createdAt) so the DB-backed API rate
-- limiter (g2ml_apiCheckRateLimit() in web/_functions/api_ratelimit.php) can
-- satisfy its per-key daily/burst window COUNT(*) queries with a single
-- composite-index range scan instead of relying on the separate
-- IDX_apireq_key / IDX_apireq_created indexes independently.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same index is already part of
--     the base schema (web/_sql/schema/031_api.sql). Run this migration ONLY
--     to bring an ALREADY-IMPORTED earlier schema up to date.
--
-- IDEMPOTENT BY DESIGN (unlike most migrations in this directory): this one
-- may run against databases that already picked up the index via a fresh
-- 031_api.sql import, so it checks information_schema first and only adds
-- the index when it is absent, via a throwaway stored procedure that drops
-- itself afterwards. Safe to re-run any number of times.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (#38)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_011_add_apireq_composite_index`//

CREATE PROCEDURE `sp_migration_011_add_apireq_composite_index`()
BEGIN
    DECLARE v_indexExists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_indexExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblAPIRequestLog'
       AND INDEX_NAME   = 'IDX_apireq_key_created';

    IF v_indexExists = 0 THEN
        ALTER TABLE `tblAPIRequestLog`
            ADD INDEX `IDX_apireq_key_created` (`apiKeyUID`, `createdAt`);
    END IF;
END//

DELIMITER ;

CALL `sp_migration_011_add_apireq_composite_index`();

DROP PROCEDURE IF EXISTS `sp_migration_011_add_apireq_composite_index`;
