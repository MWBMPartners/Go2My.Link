-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed Data: Phase 3 Settings
-- =============================================================================
-- Additional settings introduced in Phase 3 (Core Product).
-- These are System-scope entries that override Default values.
--
-- Uses INSERT ... ON DUPLICATE KEY UPDATE to safely re-run.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.4.0
-- @since      Phase 3
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Indexer / Bot settings (Component B)
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('indexer.allow_robots_txt', 'System', NULL,
 '1', '1', 'Serve dynamic robots.txt on short domains',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('indexer.allow_favicon', 'System', NULL,
 '1', '1', 'Serve dynamic favicon on short domains',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('indexer.block_suspicious', 'System', NULL,
 '0', '0', 'Block suspicious bot user agents in robots.txt',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- Analytics settings
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('analytics.respect_dnt', 'System', NULL,
 '1', '1', 'Respect Do Not Track (DNT) header — skip activity logging for DNT users',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- Redirect settings (additions to existing defaults)
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('redirect.fallback_delay', 'System', NULL,
 '5', '5', 'Countdown seconds on error/expired pages before redirecting to fallback',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- Override destination validation to OFF at System scope (performance)
-- The Default scope has it ON; this System override disables it by default
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('redirect.validate_destination', 'System', NULL,
 '0', '1', 'Validate destination URL before redirecting (OFF by default for performance)',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingValue` = '0',
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- CAPTCHA / Bot protection settings
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('captcha.turnstile_site_key', 'System', NULL,
 '', '', 'Cloudflare Turnstile site key (leave empty to disable)',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('captcha.turnstile_secret_key', 'System', NULL,
 '', '', 'Cloudflare Turnstile secret key',
 'string', 1, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('captcha.recaptcha_site_key', 'System', NULL,
 '', '', 'Google reCAPTCHA v2 site key (leave empty to disable)',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('captcha.recaptcha_secret_key', 'System', NULL,
 '', '', 'Google reCAPTCHA v2 secret key',
 'string', 1, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- Rate limiting settings
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('ratelimit.anonymous_per_hour', 'System', NULL,
 '10', '10', 'Maximum anonymous short URL creations per IP per hour',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('ratelimit.anonymous_per_day', 'System', NULL,
 '50', '50', 'Maximum anonymous short URL creations per IP per day',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- Short code settings
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('shortcode.anonymous_length', 'System', NULL,
 '7', '7', 'Short code length for anonymous (non-logged-in) users',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- =============================================================================
-- Contact form settings
-- =============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('contact.email_recipient', 'System', NULL,
 'support@go2my.link', 'support@go2my.link', 'Email address for contact form submissions',
 'email', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
