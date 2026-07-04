-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🛡️ Go2My.Link — Redirect SSRF / Destination Host Policy Settings
-- ============================================================================
-- Controls the anti-SSRF destination-host guard shared by Component A's
-- short-URL creation (createShortURL) and Component B's destination validator
-- (validateDestination). See web/_functions/security.php —
-- g2ml_destinationHostIsAllowed().
--
-- Loopback, link-local (incl. the 169.254.169.254 cloud-metadata endpoint),
-- unspecified, and reserved ranges are ALWAYS blocked and cannot be enabled by
-- any setting. This flag governs only the RFC1918 / IPv6-ULA private ranges,
-- which are blocked by default. Enable it only on a deployment that
-- deliberately shortens links to private/intranet hosts and understands the
-- SSRF risk.
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
('redirect.allow_private_destinations', 'System', NULL,
 '0', '0', 'Allow short-URL destinations that resolve to RFC1918/IPv6-ULA private hosts (10/8, 172.16/12, 192.168/16, fc00::/7). OFF by default. Loopback, link-local, cloud-metadata, unspecified and reserved ranges are ALWAYS blocked regardless of this setting.',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
