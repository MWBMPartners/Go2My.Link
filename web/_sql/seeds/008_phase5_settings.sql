-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🏢 Go2My.Link — Phase 5 Settings Seed (Organisation Management)
-- ============================================================================
--
-- Settings for organisation management, invitations, and domain verification.
-- All settings use the Default scope (settingScope = 'Default').
--
-- Uses INSERT ... ON DUPLICATE KEY UPDATE to safely re-run.
--
-- Dependencies: tblSettings (from schema), seeds 001–007 must run first.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.6.0
-- @since      Phase 5
-- ============================================================================

USE `mwtools_Go2MyLink`;

-- ============================================================================
-- 🏢 Organisation Settings
-- ============================================================================

-- Invitation token expiry (seconds) — default 7 days
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.invitation_expiry', 'Default', NULL,
 '604800', '604800', 'Organisation invitation token expiry in seconds (default: 7 days)',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Maximum pending invitations per organisation
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.max_pending_invitations', 'Default', NULL,
 '50', '50', 'Maximum number of pending invitations per organisation',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- DNS verification TXT record prefix
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.dns_verify_prefix', 'Default', NULL,
 '_g2ml-verify', '_g2ml-verify', 'DNS TXT record subdomain prefix for domain verification',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- DNS verification check timeout (seconds)
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.dns_verify_timeout', 'Default', NULL,
 '10', '10', 'Timeout in seconds for DNS TXT record lookups',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Maximum custom domains per org (free tier override — 0 = use tier limit)
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.max_custom_domains', 'Default', NULL,
 '0', '0', 'Maximum custom domains per org (0 = use subscription tier limit)',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Maximum short domains per org (free tier override — 0 = use tier limit)
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.max_short_domains', 'Default', NULL,
 '0', '0', 'Maximum short domains per org (0 = use subscription tier limit)',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Organisation handle reserved words (comma-separated)
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.reserved_handles', 'Default', NULL,
 'admin,api,app,default,system,support,help,www,mail,ftp,test,demo,null,undefined',
 'admin,api,app,default,system,support,help,www,mail,ftp,test,demo,null,undefined',
 'Comma-separated list of reserved organisation handles',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Organisation handle minimum length
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.handle_min_length', 'Default', NULL,
 '3', '3', 'Minimum length for organisation handles',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Organisation handle maximum length
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.handle_max_length', 'Default', NULL,
 '50', '50', 'Maximum length for organisation handles',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Allow users to create organisations (can be disabled globally)
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.allow_creation', 'Default', NULL,
 '1', '1', 'Whether users can create new organisations (1=yes, 0=no)',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Require email verification before creating an org
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.require_email_verified', 'Default', NULL,
 '1', '1', 'Require email verification before creating an organisation (1=yes, 0=no)',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Invitation email subject template
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('org.invitation_email_subject', 'Default', NULL,
 'You''ve been invited to join {orgName} on Go2My.Link',
 'You''ve been invited to join {orgName} on Go2My.Link',
 'Email subject for organisation invitations ({orgName} is replaced)',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
