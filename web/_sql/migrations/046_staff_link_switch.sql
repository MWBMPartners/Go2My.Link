-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 046: staff can switch any short link off (#325)
-- =============================================================================
-- Owner decisions D-023 and D-026. Adds four columns to tblShortURLs for a staff
-- block (staffDisabledAt, staffDisabledByUserUID, staffDisableReason, and
-- staffPreviousIsActive, which is what the link goes back to), and the
-- check CK_url_staff_disabled_off, which stops a blocked link being switched on
-- until staff lift the block. No existing link changes: every row starts with
-- no block.
--
-- ⚠️  RUN MIGRATION 044 FIRST (checklist A33): the procedure re-imported below
--     reads its sharedShortCode column too.
--
-- ⚠️  RUN THIS BEFORE UPLOADING THE #325 CODE. My Links, link editing and
--     switching off, the information page, the staff page and account erasure
--     use the new columns; without them they fail.
--     AFTER this file, re-import
--     web/_sql/procedures/sp_lookupShortURL.sql, which reads staffDisabledAt.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the columns and the check are
--     already part of the base schema
--     (web/_sql/schema/020_shorturls_categories_tags.sql).
--
-- IDEMPOTENT BY DESIGN (mirrors migration 045): checks information_schema first
-- and only adds what is absent, via a throwaway stored procedure that drops
-- itself afterwards. Safe to re-run any number of times.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#325)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_046_add_staff_link_switch`//

CREATE PROCEDURE `sp_migration_046_add_staff_link_switch`()
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_exists
    FROM   `information_schema`.`COLUMNS`
    WHERE  `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME`   = 'tblShortURLs'
       AND `COLUMN_NAME`  = 'staffDisabledAt';

    IF v_exists = 0 THEN

        ALTER TABLE `tblShortURLs`
            ADD COLUMN `staffDisabledAt` DATETIME DEFAULT NULL
                COMMENT 'UTC time Go2My.Link staff switched this link off (#325); NULL when there is no staff block'
                AFTER `isActive`;

    END IF;

    SELECT COUNT(*)
    INTO   v_exists
    FROM   `information_schema`.`COLUMNS`
    WHERE  `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME`   = 'tblShortURLs'
       AND `COLUMN_NAME`  = 'staffDisabledByUserUID';

    IF v_exists = 0 THEN

        ALTER TABLE `tblShortURLs`
            ADD COLUMN `staffDisabledByUserUID` BIGINT UNSIGNED DEFAULT NULL
                COMMENT 'tblUsers.userUID of the staff member who switched it off (#325); no foreign key, kept as a record'
                AFTER `staffDisabledAt`;

    END IF;

    SELECT COUNT(*)
    INTO   v_exists
    FROM   `information_schema`.`COLUMNS`
    WHERE  `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME`   = 'tblShortURLs'
       AND `COLUMN_NAME`  = 'staffDisableReason';

    IF v_exists = 0 THEN

        ALTER TABLE `tblShortURLs`
            ADD COLUMN `staffDisableReason` VARCHAR(500) DEFAULT NULL
                COMMENT 'Optional reason the staff member gave (#325); not shown to the link''s owner'
                AFTER `staffDisabledByUserUID`;

    END IF;

    SELECT COUNT(*)
    INTO   v_exists
    FROM   `information_schema`.`COLUMNS`
    WHERE  `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME`   = 'tblShortURLs'
       AND `COLUMN_NAME`  = 'staffPreviousIsActive';

    IF v_exists = 0 THEN

        ALTER TABLE `tblShortURLs`
            ADD COLUMN `staffPreviousIsActive` TINYINT(1) UNSIGNED DEFAULT NULL
                COMMENT 'What isActive goes back to when staff remove the block (#325, D-026): 1 on, 0 off; set to 0 if the owner or account erasure switches the link off meanwhile; NULL when there is no staff block'
                AFTER `staffDisableReason`;

    END IF;

    SELECT COUNT(*)
    INTO   v_exists
    FROM   `information_schema`.`TABLE_CONSTRAINTS`
    WHERE  `CONSTRAINT_SCHEMA` = DATABASE()
       AND `TABLE_NAME`        = 'tblShortURLs'
       AND `CONSTRAINT_NAME`   = 'CK_url_staff_disabled_off';

    IF v_exists = 0 THEN

        ALTER TABLE `tblShortURLs`
            ADD CONSTRAINT `CK_url_staff_disabled_off`
                CHECK (`staffDisabledAt` IS NULL OR `isActive` = 0);

    END IF;
END//

DELIMITER ;

CALL `sp_migration_046_add_staff_link_switch`();

DROP PROCEDURE IF EXISTS `sp_migration_046_add_staff_link_switch`;
