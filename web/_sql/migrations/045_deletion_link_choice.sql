-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 045: link choice on account deletion requests (#246)
-- =============================================================================
-- Adds tblDataDeletionRequests.linkChoice, which records what the person wants
-- done with their short links when their account is erased: switched off (the
-- default, owner decision D-017), kept working for 30 days, 90 days or 1 year,
-- or kept for ever (D-021).
--
-- Requests that are already waiting get 'off', which is what the Delete
-- Account page told those people would happen.
--
-- ⚠️  RUN THIS BEFORE UPLOADING THE #246 CODE. The code reads and writes the
--     column; without it, the privacy pages cannot list or record requests and
--     the scheduled erasure job cannot run.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the column is already part of the
--     base schema (web/_sql/schema/034_legal_compliance.sql).
--
-- IDEMPOTENT BY DESIGN (mirrors migration 017): checks information_schema first
-- and only acts when the column is absent, via a throwaway stored procedure
-- that drops itself afterwards. Safe to re-run any number of times.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#246)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_045_add_deletion_link_choice`//

CREATE PROCEDURE `sp_migration_045_add_deletion_link_choice`()
BEGIN
    DECLARE v_columnExists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_columnExists
    FROM   `information_schema`.`COLUMNS`
    WHERE  `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME`   = 'tblDataDeletionRequests'
       AND `COLUMN_NAME`  = 'linkChoice';

    IF v_columnExists = 0 THEN

        ALTER TABLE `tblDataDeletionRequests`
            ADD COLUMN `linkChoice` ENUM('off', '30_days', '90_days', '1_year', 'forever')
                NOT NULL DEFAULT 'off'
                COMMENT 'Deletion requests only (#246): what happens to the person''s short links when the account is erased'
                AFTER `requestReason`;

    END IF;
END//

DELIMITER ;

CALL `sp_migration_045_add_deletion_link_choice`();

DROP PROCEDURE IF EXISTS `sp_migration_045_add_deletion_link_choice`;
