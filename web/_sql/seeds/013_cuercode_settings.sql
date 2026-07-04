-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔗 Go2My.Link — CueRCode Dynamic-QR Integration Settings
-- ============================================================================
-- Controls for the CueRCode dynamic-QR integration. Disabled by default; a
-- GlobalAdmin enables it when the CueRCode service is wired up. See
-- web/_sql/migrations/009_cuercode_qr_integration.sql and DATABASE.md.
--
-- Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('cuercode.integration_enabled', 'System', NULL,
 '0', '0', 'Master switch for the CueRCode dynamic-QR integration. When off, the API rejects QR-provenance short-URL create requests.',
 'boolean', 0, 1),
('cuercode.allow_external_shortcode', 'System', NULL,
 '0', '0', 'Allow CueRCode to supply/own the short code on create instead of Go2My.Link generating one.',
 'boolean', 0, 1),
('cuercode.api_base_url', 'System', NULL,
 '', '', 'Base URL for CueRCode callbacks/status checks. Empty disables callbacks.',
 'url', 0, 1),
('cuercode.scan_source_param', 'System', NULL,
 'src', 'src', 'Query-string parameter CueRCode appends on a scan so the redirect handler can mark the click as a QR scan.',
 'string', 0, 1),
('cuercode.scan_source_value', 'System', NULL,
 'qr', 'qr', 'Expected value of the scan-source parameter that marks a request as a CueRCode QR scan.',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
