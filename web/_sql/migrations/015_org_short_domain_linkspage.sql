-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 015: Custom-domain LinksPage fallback (#46)
-- =============================================================================
-- Adds `tblOrgShortDomains.linksPageUID` — the org's PUBLISHED LinksPage shown
-- when a VERIFIED custom short domain is visited at its root, or a requested
-- path does not resolve to a short code, instead of a 404 (Component C.4).
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same column/index/FK are
--     already part of the base schema
--     (web/_sql/schema/012_core_organisations.sql for the column/index;
--     web/_sql/schema/032_linkspage.sql for the deferred FK, added there
--     because tblLinksPages does not exist yet when 012 is imported). Run
--     this migration ONLY to bring an ALREADY-IMPORTED earlier schema up to
--     date.
--
-- IDEMPOTENT BY DESIGN (mirrors migrations 013/014): every ADD COLUMN /
-- ADD INDEX / ADD CONSTRAINT step is guarded by information_schema via a
-- throwaway stored procedure that drops itself afterwards, so it is safe to
-- re-run against a database that already has some or all of these changes
-- (e.g. one provisioned by a fresh schema import).
--
-- NULL is the default and remains harmless: a domain with no designated
-- LinksPage keeps its EXISTING behaviour (root -> redirect.fallback_url
-- setting; an unresolvable code -> 404, unless the org has its own
-- orgFallbackURL configured) completely unchanged.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#46)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Add the linksPageUID column and its supporting index.
-- -----------------------------------------------------------------------------
DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_015_add_short_domain_linkspage_column`//

CREATE PROCEDURE `sp_migration_015_add_short_domain_linkspage_column`()
BEGIN
    DECLARE v_columnExists INT DEFAULT 0;
    DECLARE v_indexExists  INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_columnExists
    FROM   information_schema.COLUMNS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblOrgShortDomains'
       AND COLUMN_NAME  = 'linksPageUID';

    IF v_columnExists = 0 THEN
        ALTER TABLE `tblOrgShortDomains`
            ADD COLUMN `linksPageUID` BIGINT UNSIGNED DEFAULT NULL
                COMMENT 'Component C.4 (#46) — the org''s published LinksPage shown at this domain''s root, or for a path that does not resolve to a short code, instead of a 404. NULL = no fallback configured (existing 404 behaviour is unchanged).'
                AFTER `isActive`;
    END IF;

    SELECT COUNT(*)
    INTO   v_indexExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblOrgShortDomains'
       AND INDEX_NAME   = 'IDX_short_domain_linkspage';

    IF v_indexExists = 0 THEN
        ALTER TABLE `tblOrgShortDomains`
            ADD INDEX `IDX_short_domain_linkspage` (`linksPageUID`);
    END IF;
END//

DELIMITER ;

CALL `sp_migration_015_add_short_domain_linkspage_column`();

DROP PROCEDURE IF EXISTS `sp_migration_015_add_short_domain_linkspage_column`;

-- -----------------------------------------------------------------------------
-- 2. Add the deferred foreign key — ONLY once tblLinksPages actually exists
--    (it is a Phase 8 table; this migration must not fail on a schema that
--    predates it) AND only if the constraint is not already present.
-- -----------------------------------------------------------------------------
DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_015_add_short_domain_linkspage_fk`//

CREATE PROCEDURE `sp_migration_015_add_short_domain_linkspage_fk`()
BEGIN
    DECLARE v_targetTableExists INT DEFAULT 0;
    DECLARE v_constraintExists  INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_targetTableExists
    FROM   information_schema.TABLES
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblLinksPages';

    SELECT COUNT(*)
    INTO   v_constraintExists
    FROM   information_schema.TABLE_CONSTRAINTS
    WHERE  TABLE_SCHEMA      = DATABASE()
       AND TABLE_NAME        = 'tblOrgShortDomains'
       AND CONSTRAINT_NAME   = 'FK_short_domain_linkspage'
       AND CONSTRAINT_TYPE   = 'FOREIGN KEY';

    IF v_targetTableExists = 1 AND v_constraintExists = 0 THEN
        ALTER TABLE `tblOrgShortDomains`
            ADD CONSTRAINT `FK_short_domain_linkspage`
                FOREIGN KEY (`linksPageUID`)
                REFERENCES `tblLinksPages` (`pageUID`)
                ON UPDATE CASCADE
                ON DELETE SET NULL;
    END IF;
END//

DELIMITER ;

CALL `sp_migration_015_add_short_domain_linkspage_fk`();

DROP PROCEDURE IF EXISTS `sp_migration_015_add_short_domain_linkspage_fk`;
