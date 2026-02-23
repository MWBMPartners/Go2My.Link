-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- Go2My.Link — Account Types Seed Data
-- ============================================================================
-- Seeds the four system account types that correspond to the legacy
-- tblUsers.role ENUM values: Anonymous, User, Admin, GlobalAdmin.
--
-- These are marked as isSystemType = 1 and cannot be deleted.
-- Additional custom account types can be added without modifying this file.
--
-- Uses INSERT ... ON DUPLICATE KEY UPDATE to safely re-run.
--
-- Dependencies: tblAccountTypes (from schema 015)
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.1.0
-- @since      Phase 7
-- ============================================================================

USE `mwtools_Go2MyLink`;

-- ============================================================================
-- 🏷️ System Account Types
-- ============================================================================

-- Anonymous — unauthenticated visitor (application state only, not stored in DB)
INSERT INTO `tblAccountTypes` (
    `accountTypeID`, `accountTypeName`, `accountTypeDescription`,
    `roleLevel`, `roleName`, `isSystemType`, `sortOrder`, `isActive`
) VALUES (
    'anonymous', 'Anonymous',
    'Unauthenticated visitor with no account privileges. Used as an application state only.',
    0, 'Anonymous', 1, 0, 1
) ON DUPLICATE KEY UPDATE
    `accountTypeName`        = VALUES(`accountTypeName`),
    `accountTypeDescription` = VALUES(`accountTypeDescription`);

-- User — standard authenticated user
INSERT INTO `tblAccountTypes` (
    `accountTypeID`, `accountTypeName`, `accountTypeDescription`,
    `roleLevel`, `roleName`, `isSystemType`, `sortOrder`, `isActive`
) VALUES (
    'user', 'User',
    'Standard user — can create and manage their own short links and view their analytics.',
    1, 'User', 1, 10, 1
) ON DUPLICATE KEY UPDATE
    `accountTypeName`        = VALUES(`accountTypeName`),
    `accountTypeDescription` = VALUES(`accountTypeDescription`);

-- Admin — organisation administrator
INSERT INTO `tblAccountTypes` (
    `accountTypeID`, `accountTypeName`, `accountTypeDescription`,
    `roleLevel`, `roleName`, `isSystemType`, `sortOrder`, `isActive`
) VALUES (
    'admin', 'Admin',
    'Organisation administrator — can manage org settings, members, domains, and all org links.',
    2, 'Admin', 1, 20, 1
) ON DUPLICATE KEY UPDATE
    `accountTypeName`        = VALUES(`accountTypeName`),
    `accountTypeDescription` = VALUES(`accountTypeDescription`);

-- Global Admin — platform-wide system administrator
INSERT INTO `tblAccountTypes` (
    `accountTypeID`, `accountTypeName`, `accountTypeDescription`,
    `roleLevel`, `roleName`, `isSystemType`, `sortOrder`, `isActive`
) VALUES (
    'global-admin', 'Global Admin',
    'System-wide administrator with full platform access across all organisations.',
    3, 'GlobalAdmin', 1, 30, 1
) ON DUPLICATE KEY UPDATE
    `accountTypeName`        = VALUES(`accountTypeName`),
    `accountTypeDescription` = VALUES(`accountTypeDescription`);
