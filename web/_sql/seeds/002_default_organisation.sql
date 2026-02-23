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
INSERT INTO `tblOrgShortDomains` (
    `orgHandle`, `shortDomain`, `isDefault`, `isActive`
) VALUES (
    '[default]',
    'g2my.link',
    1,
    1
)
ON DUPLICATE KEY UPDATE
    `isDefault` = VALUES(`isDefault`);
