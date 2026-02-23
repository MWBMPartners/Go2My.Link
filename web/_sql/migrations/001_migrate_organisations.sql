-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 001: Organisations
-- =============================================================================
-- Migrates 5 organisation records from mwtools_mwlink.tblCustomerOrg
-- to mwtools_Go2MyLink.tblOrganisations and tblOrgShortDomains.
--
-- Preserves custOrgHandle as orgHandle for FK compatibility.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
--
-- Prerequisites:
--   - New schema must be created (run all schema/*.sql files first)
--   - Seed data for subscription tiers must be loaded (seeds/001_*.sql)
--   - Old database mwtools_mwlink must be accessible
-- =============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

START TRANSACTION;

-- =========================================================================
-- Step 1: Migrate organisations
-- =========================================================================
INSERT INTO `tblOrganisations` (
    `orgHandle`,
    `orgName`,
    `orgURL`,
    `orgDomain`,
    `orgFallbackURL`,
    `tierID`,
    `isVerified`,
    `isActive`,
    `orgNotes`,
    `createdAt`,
    `updatedAt`
)
SELECT
    old.`custOrgHandle`         AS `orgHandle`,
    old.`custOrgName`           AS `orgName`,
    old.`custOrgURL`            AS `orgURL`,
    old.`custOrgDomain`         AS `orgDomain`,
    old.`custOrgFallbackURL`    AS `orgFallbackURL`,
    'free'                      AS `tierID`,       -- All migrated orgs start on Free tier
    1                           AS `isVerified`,   -- Existing orgs are pre-verified
    1                           AS `isActive`,
    CONCAT('[Migrated from MWlink] ', IFNULL(old.`custOrgNotes`, ''))
                                AS `orgNotes`,
    old.`custOrgDateCreated`    AS `createdAt`,
    old.`custOrgLastUpdated`    AS `updatedAt`
FROM `mwtools_mwlink`.`tblCustomerOrg` old
ON DUPLICATE KEY UPDATE
    `orgName` = VALUES(`orgName`),
    `updatedAt` = NOW();

-- =========================================================================
-- Step 2: Migrate custom short URL domains to tblOrgShortDomains
-- =========================================================================
INSERT INTO `tblOrgShortDomains` (
    `orgHandle`,
    `shortDomain`,
    `isDefault`,
    `isActive`,
    `createdAt`
)
SELECT
    old.`custOrgHandle`             AS `orgHandle`,
    old.`custOrgShortURLDomain`     AS `shortDomain`,
    1                               AS `isDefault`,
    1                               AS `isActive`,
    old.`custOrgDateCreated`        AS `createdAt`
FROM `mwtools_mwlink`.`tblCustomerOrg` old
WHERE old.`custOrgShortURLDomain` IS NOT NULL
  AND old.`custOrgShortURLDomain` != ''
ON DUPLICATE KEY UPDATE
    `shortDomain` = VALUES(`shortDomain`);

COMMIT;

-- =========================================================================
-- Verification
-- =========================================================================
-- Expected: 5 organisations, 4-5 short domains (depending on how many have custom domains)
SELECT 'Migration 001 — Organisations' AS `migration`,
       COUNT(*) AS `org_count`
FROM `tblOrganisations`;

SELECT 'Migration 001 — Short Domains' AS `migration`,
       COUNT(*) AS `domain_count`
FROM `tblOrgShortDomains`;
