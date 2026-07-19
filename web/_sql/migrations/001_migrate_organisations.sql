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
    -- Zero-date/NULL guard: NOT NULL target → fall back to NOW() (#123)
    IFNULL(NULLIF(NULLIF(CAST(old.`custOrgDateCreated` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00'), NOW())
                                AS `createdAt`,
    -- Zero-date/NULL guard: nullable target → strip zero-dates to NULL (#123)
    NULLIF(NULLIF(CAST(old.`custOrgLastUpdated` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00')
                                AS `updatedAt`
FROM `mwtools_mwlink`.`tblCustomerOrg` old
ON DUPLICATE KEY UPDATE
    `orgName` = VALUES(`orgName`),
    `updatedAt` = NOW();

-- =========================================================================
-- Step 2: Migrate custom short URL domains to tblOrgShortDomains
-- =========================================================================
-- ⚠️  GRANDFATHERING (#91): these domains are ALREADY live and serving traffic on
--     the legacy platform, so they are trusted by virtue of having been in
--     production — they must NOT be made to re-prove ownership at cutover.
--
--     `verificationStatus` defaults to 'pending' (schema/012_core_organisations.sql),
--     but the redirect router admits a domain ONLY when it is BOTH 'verified' and
--     active — see domain_resolver.php (`$row['verificationStatus'] === 'verified'`)
--     and sp_lookupShortURL. Inserting without an explicit status would therefore
--     leave every migrated partner short domain unroutable: each one would fall
--     through to '[default]' and 404 the moment the new platform went live.
--
--     migrations/013 carries the same backfill, but its header states that fresh
--     installs do not need it — and the documented cutover path is a FRESH schema
--     import plus the data migrations, so 013 is skipped and cannot be relied on.
--     Setting the status here makes this migration correct standalone.
INSERT INTO `tblOrgShortDomains` (
    `orgHandle`,
    `shortDomain`,
    `isDefault`,
    `isActive`,
    `verificationStatus`,
    `verifiedAt`,
    `createdAt`
)
SELECT
    old.`custOrgHandle`             AS `orgHandle`,
    old.`custOrgShortURLDomain`     AS `shortDomain`,
    1                               AS `isDefault`,
    1                               AS `isActive`,
    -- Grandfathered: already live on the legacy platform (see note above).
    'verified'                      AS `verificationStatus`,
    NOW()                           AS `verifiedAt`,
    -- Zero-date/NULL guard: NOT NULL target → fall back to NOW() (#123)
    IFNULL(NULLIF(NULLIF(CAST(old.`custOrgDateCreated` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00'), NOW())
                                    AS `createdAt`
FROM `mwtools_mwlink`.`tblCustomerOrg` old
WHERE old.`custOrgShortURLDomain` IS NOT NULL
  AND old.`custOrgShortURLDomain` != ''
ON DUPLICATE KEY UPDATE
    `shortDomain`         = VALUES(`shortDomain`),
    -- Re-running must not demote an already-verified domain, and must repair a
    -- row left 'pending' by an earlier run of this migration.
    `verificationStatus`  = 'verified',
    `verifiedAt`          = COALESCE(`verifiedAt`, NOW());

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
