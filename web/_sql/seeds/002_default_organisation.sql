-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed Data: Default Organisation
-- =============================================================================
-- Creates the [default] organisation used for anonymous link creation
-- and the default g2my.link short domain.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- Default organisation (used for links not assigned to any org)
INSERT INTO `tblOrganisations` (
    `orgHandle`, `orgName`, `orgURL`, `orgDomain`, `orgFallbackURL`,
    `tierID`, `isVerified`, `isActive`, `orgNotes`
) VALUES (
    '[default]',
    'Go2My.Link (Default)',
    'https://go2my.link',
    'go2my.link',
    'https://go2my.link',
    'free',
    1,
    1,
    'System default organisation for anonymous and unassigned links'
)
ON DUPLICATE KEY UPDATE
    `orgName` = VALUES(`orgName`),
    `updatedAt` = NOW();

-- Default short domain (g2my.link)
--
-- 🔒 #91 — this row must be pre-verified: sp_lookupShortURL / getOrgByDomain
-- now require verificationStatus='verified' AND isActive=1 before ANY Host
-- header (including this system's own default host) maps to an org. Without
-- explicitly setting verificationStatus/verifiedAt here, a FRESH install
-- would seed this row at the new column's default ('pending') and every
-- redirect on g2my.link itself would 404 with "domain not configured" until
-- someone manually verified it — there is no DNS-TXT ownership proof to run
-- for a domain we operate ourselves, so it is verified at seed time instead.
-- (An UPGRADE from an older schema is covered separately by migration 013's
-- grandfather backfill, which does the equivalent UPDATE for existing rows.)
INSERT INTO `tblOrgShortDomains` (
    `orgHandle`, `shortDomain`, `isDefault`, `isActive`,
    `verificationStatus`, `verifiedAt`
) VALUES (
    '[default]',
    'g2my.link',
    1,
    1,
    'verified',
    NOW()
)
ON DUPLICATE KEY UPDATE
    `isDefault` = VALUES(`isDefault`);
