-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 013: Short-domain ownership verification (#91)
-- =============================================================================
-- Adds ownership-verification + TLS-status tracking to tblOrgShortDomains so a
-- custom short domain must prove DNS control before it becomes routable
-- (sp_lookupShortURL / getOrgByDomain now require verificationStatus='verified'
-- AND isActive=1 before a Host header maps to an org — see redirect_resolver.php
-- and domain_resolver.php).
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same columns/index are already
--     part of the base schema (web/_sql/schema/012_core_organisations.sql).
--     Run this migration ONLY to bring an ALREADY-IMPORTED earlier schema up
--     to date.
--
-- IDEMPOTENT BY DESIGN (mirrors migration 011's pattern): the ADD COLUMN /
-- ADD INDEX step is guarded by information_schema via a throwaway stored
-- procedure that drops itself afterwards, so it is safe to re-run against a
-- database that already has these columns (e.g. one provisioned by a fresh
-- schema import). The backfill UPDATE below is naturally idempotent too (its
-- own WHERE clause excludes rows it has already touched).
--
-- 🔴 CRITICAL — GRANDFATHERING: every pre-existing `isActive = 1` short-domain
-- row was live and routable under the OLD (unverified) resolver contract. The
-- instant the resolver starts requiring verificationStatus='verified', any of
-- those rows that were left at the new column's default ('pending') would go
-- dark — a real partner's redirects would silently break. This migration
-- backfills every such row to 'verified' (with verifiedAt set, if not already)
-- in the SAME script that adds the columns, so there is never a window where
-- the stricter resolver check is live but grandfathered domains are not yet
-- marked verified.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (#91)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Add the verification + TLS columns and the supporting index, guarded so
--    the migration is safe to re-run against a schema that already has them.
-- -----------------------------------------------------------------------------
DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_013_add_short_domain_verification_columns`//

CREATE PROCEDURE `sp_migration_013_add_short_domain_verification_columns`()
BEGIN
    DECLARE v_columnExists INT DEFAULT 0;
    DECLARE v_indexExists  INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_columnExists
    FROM   information_schema.COLUMNS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblOrgShortDomains'
       AND COLUMN_NAME  = 'verificationStatus';

    IF v_columnExists = 0 THEN
        ALTER TABLE `tblOrgShortDomains`
            ADD COLUMN `verificationStatus` ENUM('pending', 'verified', 'failed')
                NOT NULL DEFAULT 'pending'
                COMMENT 'DNS-TXT ownership verification status — only verified domains are routable (#91)'
                AFTER `isDefault`,
            ADD COLUMN `verificationToken` VARCHAR(255) DEFAULT NULL
                COMMENT 'Per-domain unguessable DNS TXT record verification token'
                AFTER `verificationStatus`,
            ADD COLUMN `verifiedAt` DATETIME DEFAULT NULL
                COMMENT 'When DNS ownership verification succeeded'
                AFTER `verificationToken`,
            ADD COLUMN `tlsStatus` ENUM('none', 'pending', 'active')
                NOT NULL DEFAULT 'none'
                COMMENT 'TLS provisioning status — manual Dreamhost hosted-domain today; Cloudflare-for-SaaS is the roadmap automated path'
                AFTER `verifiedAt`;
    END IF;

    SELECT COUNT(*)
    INTO   v_indexExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblOrgShortDomains'
       AND INDEX_NAME   = 'IDX_short_domain_status';

    IF v_indexExists = 0 THEN
        ALTER TABLE `tblOrgShortDomains`
            ADD INDEX `IDX_short_domain_status` (`verificationStatus`);
    END IF;
END//

DELIMITER ;

CALL `sp_migration_013_add_short_domain_verification_columns`();

DROP PROCEDURE IF EXISTS `sp_migration_013_add_short_domain_verification_columns`;

-- -----------------------------------------------------------------------------
-- 2. 🔴 GRANDFATHER BACKFILL — every already-active short domain is deemed
--    verified so live partner routing is not broken by the stricter resolver.
--    Naturally idempotent: the WHERE clause only ever matches rows this
--    migration has not already marked, so re-running it is a no-op the second
--    time.
-- -----------------------------------------------------------------------------
UPDATE `tblOrgShortDomains`
   SET `verificationStatus` = 'verified',
       `verifiedAt`         = COALESCE(`verifiedAt`, NOW())
 WHERE `isActive` = 1
   AND `verificationStatus` <> 'verified';
