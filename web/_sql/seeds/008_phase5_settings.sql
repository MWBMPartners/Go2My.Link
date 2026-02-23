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
-- Dependencies: tblSettings (from schema), seeds 001–007 must run first.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @version    0.6.0
-- @since      Phase 5
-- ============================================================================

-- ============================================================================
-- 🏢 Organisation Settings
-- ============================================================================

INSERT INTO `tblSettings` (`settingKey`, `settingValue`, `settingScope`, `settingDescription`) VALUES
-- Invitation token expiry (seconds) — default 7 days
('org.invitation_expiry', '604800', 'Default', 'Organisation invitation token expiry in seconds (default: 7 days)'),

-- Maximum pending invitations per organisation
('org.max_pending_invitations', '50', 'Default', 'Maximum number of pending invitations per organisation'),

-- DNS verification TXT record prefix
('org.dns_verify_prefix', '_g2ml-verify', 'Default', 'DNS TXT record subdomain prefix for domain verification'),

-- DNS verification check timeout (seconds)
('org.dns_verify_timeout', '10', 'Default', 'Timeout in seconds for DNS TXT record lookups'),

-- Maximum custom domains per org (free tier override — 0 = use tier limit)
('org.max_custom_domains', '0', 'Default', 'Maximum custom domains per org (0 = use subscription tier limit)'),

-- Maximum short domains per org (free tier override — 0 = use tier limit)
('org.max_short_domains', '0', 'Default', 'Maximum short domains per org (0 = use subscription tier limit)'),

-- Organisation handle reserved words (comma-separated)
('org.reserved_handles', 'admin,api,app,default,system,support,help,www,mail,ftp,test,demo,null,undefined', 'Default', 'Comma-separated list of reserved organisation handles'),

-- Organisation handle minimum length
('org.handle_min_length', '3', 'Default', 'Minimum length for organisation handles'),

-- Organisation handle maximum length
('org.handle_max_length', '50', 'Default', 'Maximum length for organisation handles'),

-- Allow users to create organisations (can be disabled globally)
('org.allow_creation', '1', 'Default', 'Whether users can create new organisations (1=yes, 0=no)'),

-- Require email verification before creating an org
('org.require_email_verified', '1', 'Default', 'Require email verification before creating an organisation (1=yes, 0=no)'),

-- Invitation email subject template
('org.invitation_email_subject', 'You\'ve been invited to join {orgName} on Go2My.Link', 'Default', 'Email subject for organisation invitations ({orgName} is replaced)');
