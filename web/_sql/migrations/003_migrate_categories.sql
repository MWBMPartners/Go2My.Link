-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 003: Categories
-- =============================================================================
-- Migrates 4 category records from mwtools_mwlink.tblCategories.
-- Adds organisation FK from the legacy custOrgHandle field.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

START TRANSACTION;

INSERT INTO `tblCategories` (
    `categoryID`,
    `orgHandle`,
    `categoryName`,
    `categoryDescription`,
    `categoryFallbackURL`,
    `isActive`,
    `createdAt`,
    `updatedAt`
)
SELECT
    old.`categoryID`            AS `categoryID`,
    IFNULL(old.`custOrgHandle`, '[default]')
                                AS `orgHandle`,
    old.`categoryName`          AS `categoryName`,
    old.`categoryDesc`          AS `categoryDescription`,
    old.`categoryFallbackURL`   AS `categoryFallbackURL`,
    1                           AS `isActive`,
    old.`custDateCreated`       AS `createdAt`,
    old.`custLastUpdated`       AS `updatedAt`
FROM `mwtools_mwlink`.`tblCategories` old
ON DUPLICATE KEY UPDATE
    `categoryName` = VALUES(`categoryName`),
    `updatedAt` = NOW();

COMMIT;

-- =========================================================================
-- Verification
-- =========================================================================
SELECT 'Migration 003 — Categories' AS `migration`,
       COUNT(*) AS `category_count`
FROM `tblCategories`;
