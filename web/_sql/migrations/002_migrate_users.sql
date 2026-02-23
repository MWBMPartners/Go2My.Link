-- =============================================================================
-- Go2My.Link — Migration 002: Users
-- =============================================================================
-- Migrates 7 user records from mwtools_mwlink.tblCustomers.
-- ALL passwords are invalidated (legacy system stored plaintext).
-- Users must reset their passwords on first login.
--
-- Role mapping:
--   - Users matching custAdminUsername in tblCustomerOrg → Admin
--   - All others → User
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
--
-- Prerequisites:
--   - Migration 001 (organisations) must be completed first
-- =============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

-- =========================================================================
-- Step 1: Migrate users with invalidated passwords
-- =========================================================================
-- Password hash is set to a known-invalid value that cannot match any
-- Argon2id or bcrypt hash, forcing a password reset.
-- =========================================================================
INSERT INTO `tblUsers` (
    `orgHandle`,
    `username`,
    `email`,
    `emailVerified`,
    `passwordHash`,
    `forcePasswordReset`,
    `firstName`,
    `lastName`,
    `role`,
    `isActive`,
    `userNotes`,
    `createdAt`,
    `updatedAt`
)
SELECT
    IFNULL(old.`custOrgHandle`, '[default]')
                                AS `orgHandle`,
    old.`custUsername`           AS `username`,
    old.`custContactEmail`      AS `email`,
    1                           AS `emailVerified`,    -- Existing users are pre-verified
    '$INVALID$migrated$password$must$reset$'
                                AS `passwordHash`,     -- Invalid hash — forces reset
    1                           AS `forcePasswordReset`,
    old.`custFirstName`         AS `firstName`,
    old.`custLastName`          AS `lastName`,
    -- Map role: if this user is the org admin, set Admin role
    CASE
        WHEN org.`custAdminUsername` IS NOT NULL
             AND org.`custAdminUsername` = old.`custUsername`
        THEN 'Admin'
        ELSE 'User'
    END                         AS `role`,
    1                           AS `isActive`,
    CONCAT('[Migrated from MWlink] Password force-reset required. ',
           IFNULL(old.`custNotes`, ''))
                                AS `userNotes`,
    old.`custDateCreated`       AS `createdAt`,
    old.`custLastUpdated`       AS `updatedAt`
FROM `mwtools_mwlink`.`tblCustomers` old
LEFT JOIN `mwtools_mwlink`.`tblCustomerOrg` org
    ON old.`custOrgHandle` = org.`custOrgHandle`
ON DUPLICATE KEY UPDATE
    `username` = VALUES(`username`),
    `updatedAt` = NOW();

-- =========================================================================
-- Verification
-- =========================================================================
-- Expected: 7 users, all with forcePasswordReset = 1
SELECT 'Migration 002 — Users' AS `migration`,
       COUNT(*) AS `user_count`,
       SUM(`forcePasswordReset`) AS `force_reset_count`
FROM `tblUsers`;
