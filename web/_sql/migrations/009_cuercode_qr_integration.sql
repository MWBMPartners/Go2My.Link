-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 009: CueRCode Dynamic-QR Integration
-- =============================================================================
-- Adds the hooks that let the external CueRCode QR service own a short code,
-- reference its QR record, and have scans attributed in analytics.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same columns/indexes are already
--     part of the base schema (020_shorturls_categories_tags.sql, 030_analytics.sql,
--     031_api.sql) and the settings are seeded by 013_cuercode_settings.sql.
--     Run this migration ONLY to bring an ALREADY-IMPORTED earlier schema up to date.
--
-- Additive and non-breaking: ADD COLUMN / ADD INDEX / ADD CONSTRAINT / INSERT only.
-- All new columns are nullable or have defaults, so existing rows and existing
-- INSERTs (createShortURL(), sp_logActivity) keep working unchanged.
--
-- NOTE: MySQL 8 does NOT support "ADD COLUMN IF NOT EXISTS"; re-running this
--       migration on a schema that already has these columns will error (which
--       is the intended guard against double application).
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. tblShortURLs — provenance + external QR reference
-- -----------------------------------------------------------------------------
ALTER TABLE `tblShortURLs`
    ADD COLUMN `createdVia` ENUM('web', 'api', 'import', 'admin', 'cuercode')
        NOT NULL DEFAULT 'web'
        COMMENT 'How this short URL was created; cuercode = minted by the CueRCode QR service'
        AFTER `createdByUserUID`,
    ADD COLUMN `createdViaAPIKeyUID` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'FK to tblAPIKeys.apiKeyUID — which API key minted this code'
        AFTER `createdVia`,
    ADD COLUMN `qrCodeExternalID` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Opaque id of the owning CueRCode QR record (NULL = not QR-backed)'
        AFTER `createdViaAPIKeyUID`,
    ADD COLUMN `qrCodeExternalUUID` CHAR(36) DEFAULT NULL
        COMMENT 'Opaque UUID of the owning CueRCode QR record (NULL = not QR-backed)'
        AFTER `qrCodeExternalID`,
    ADD COLUMN `qrCodeLinkedAt` DATETIME DEFAULT NULL
        COMMENT 'UTC timestamp when a CueRCode QR was linked to this short URL'
        AFTER `qrCodeExternalUUID`,
    ADD INDEX `IDX_url_created_via` (`createdVia`),
    ADD INDEX `IDX_url_apikey` (`createdViaAPIKeyUID`),
    ADD INDEX `IDX_url_qr_extid` (`qrCodeExternalID`),
    ADD UNIQUE KEY `UQ_url_qr_uuid` (`qrCodeExternalUUID`),
    ADD CONSTRAINT `FK_url_apikey`
        FOREIGN KEY (`createdViaAPIKeyUID`)
        REFERENCES `tblAPIKeys` (`apiKeyUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- 2. tblActivityLog — QR scan attribution
-- -----------------------------------------------------------------------------
ALTER TABLE `tblActivityLog`
    ADD COLUMN `scanSource` VARCHAR(50) DEFAULT NULL
        COMMENT 'Click/scan channel (e.g. qr) — set when a CueRCode QR scan is detected'
        AFTER `apiKeyUID`,
    ADD COLUMN `qrCodeExternalID` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'CueRCode QR record id this scan is attributed to (NULL = not a QR scan)'
        AFTER `scanSource`,
    ADD INDEX `IDX_log_scan_source` (`scanSource`),
    ADD INDEX `IDX_log_qr_extid` (`qrCodeExternalID`);

-- -----------------------------------------------------------------------------
-- 3. Settings (idempotent)
-- -----------------------------------------------------------------------------
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
