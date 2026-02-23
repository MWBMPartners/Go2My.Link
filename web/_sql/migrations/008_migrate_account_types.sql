-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- Go2My.Link — Migration: Backfill User Account Types
-- ============================================================================
-- Populates the tblUserAccountTypes junction table from the existing
-- tblUsers.role ENUM column, mapping each legacy role to its corresponding
-- account type ID.
--
-- This migration is idempotent (uses ON DUPLICATE KEY UPDATE) and can be
-- safely re-run. The tblUsers.role column is NOT removed — it is retained
-- as a cached "effective role" for backward compatibility with hasMinimumRole().
--
-- Dependencies: schema 015 (tblAccountTypes, tblUserAccountTypes),
--               seeds 011 (system account types must be seeded first)
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.1.0
-- @since      Phase 7
-- ============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

START TRANSACTION;

-- =========================================================================
-- Step 1: Backfill junction table from existing tblUsers.role values
-- =========================================================================
-- Maps each user's current legacy role to the corresponding accountTypeID:
--   GlobalAdmin → global-admin
--   Admin       → admin
--   User        → user
--   Anonymous   → anonymous (should not exist in tblUsers, but handled)
-- =========================================================================

INSERT INTO `tblUserAccountTypes` (
    `userUID`,
    `orgHandle`,
    `accountTypeID`,
    `grantedByUserUID`,
    `grantedAt`,
    `isActive`
)
SELECT
    u.`userUID`,
    u.`orgHandle`,
    CASE u.`role`
        WHEN 'GlobalAdmin' THEN 'global-admin'
        WHEN 'Admin'       THEN 'admin'
        WHEN 'User'        THEN 'user'
        WHEN 'Anonymous'   THEN 'anonymous'
    END AS `accountTypeID`,
    NULL,
    u.`createdAt`,
    1
FROM `tblUsers` u
WHERE u.`isActive` = 1
ON DUPLICATE KEY UPDATE
    `isActive` = 1;

COMMIT;

-- =========================================================================
-- Verification
-- =========================================================================

SELECT
    'tblUserAccountTypes' AS `table`,
    COUNT(*) AS `migrated_rows`
FROM `tblUserAccountTypes`;

SELECT
    uat.`accountTypeID`,
    COUNT(*) AS `user_count`
FROM `tblUserAccountTypes` uat
GROUP BY uat.`accountTypeID`
ORDER BY uat.`accountTypeID`;
