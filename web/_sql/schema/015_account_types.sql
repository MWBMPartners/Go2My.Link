-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Account Types & User Account Type Assignments
-- =============================================================================
-- Introduces a many-to-many relationship between users and account types,
-- allowing a single user to hold multiple account types simultaneously.
-- Types are org-scoped for future multi-org readiness.
--
-- The legacy tblUsers.role ENUM column is retained as a cached "effective role"
-- representing the user's highest-privilege account type. It is kept in sync
-- by application code (syncEffectiveRole) whenever account types change.
--
-- Dependencies: tblOrganisations (012), tblUsers (013)
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.1.0
-- @since      Phase 7
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Account Types (Reference Table)
-- =============================================================================
-- Defines the available account types that can be assigned to users.
-- The four system types (anonymous, user, admin, global-admin) correspond
-- to the legacy tblUsers.role ENUM values and cannot be deleted.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tblAccountTypes` (
    `accountTypeUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `accountTypeID`             VARCHAR(50)         NOT NULL
        COMMENT 'Unique slug identifier (e.g. user, admin, link-manager)',

    `accountTypeName`           VARCHAR(100)        NOT NULL
        COMMENT 'Human-readable display name',

    `accountTypeDescription`    TEXT                DEFAULT NULL
        COMMENT 'Longer description of what this type grants',

    `roleLevel`                 TINYINT UNSIGNED    NOT NULL DEFAULT 1
        COMMENT 'Maps to G2ML_ROLE_LEVELS hierarchy: 0=Anonymous, 1=User, 2=Admin, 3=GlobalAdmin',

    `roleName`                  ENUM('GlobalAdmin', 'Admin', 'User', 'Anonymous')
                                NOT NULL DEFAULT 'User'
        COMMENT 'Legacy role name this type maps to (for effective role sync)',

    `isSystemType`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'System types cannot be deleted or renamed (1=yes)',

    `sortOrder`                 INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display order in UI lists',

    `isActive`                  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this account type is available for assignment',

    `createdAt`                 DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`                 DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`accountTypeUID`),
    UNIQUE KEY `UQ_account_type_id` (`accountTypeID`),
    INDEX `IDX_account_type_role` (`roleName`),
    INDEX `IDX_account_type_active` (`isActive`, `sortOrder`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Reference table of available account types for user assignment';

-- =============================================================================
-- User Account Type Assignments (Junction Table)
-- =============================================================================
-- Links users to account types within an org context. A user can hold
-- multiple account types simultaneously (e.g. admin + api-user).
-- Assignments can optionally expire (expiresAt) and are soft-deleted
-- via the isActive flag.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tblUserAccountTypes` (
    `userAccountTypeUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `userUID`                   BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID',

    `orgHandle`                 VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle — org context for this assignment',

    `accountTypeID`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblAccountTypes.accountTypeID',

    `grantedByUserUID`          BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID — who assigned this type (NULL = system)',

    `grantedAt`                 DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this type was assigned',

    `expiresAt`                 DATETIME            DEFAULT NULL
        COMMENT 'Optional expiry for time-limited assignments (NULL = permanent)',

    `isActive`                  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Soft delete flag (0 = revoked)',

    `createdAt`                 DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`userAccountTypeUID`),

    -- Prevent duplicate active assignments of the same type for the same user+org
    UNIQUE KEY `UQ_user_org_type` (`userUID`, `orgHandle`, `accountTypeID`),

    INDEX `IDX_uat_user` (`userUID`),
    INDEX `IDX_uat_org` (`orgHandle`),
    INDEX `IDX_uat_type` (`accountTypeID`),
    INDEX `IDX_uat_active` (`isActive`, `expiresAt`),

    CONSTRAINT `FK_uat_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_uat_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_uat_type`
        FOREIGN KEY (`accountTypeID`)
        REFERENCES `tblAccountTypes` (`accountTypeID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `FK_uat_granter`
        FOREIGN KEY (`grantedByUserUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table linking users to account types within an org context';
