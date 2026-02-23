-- =============================================================================
-- Go2My.Link — Migration 004: Short URLs
-- =============================================================================
-- Migrates 480 short URL records from mwtools_mwlink.tblShortURLs.
-- Preserves urlUID for FK compatibility. Sets isActive = 1 for all.
-- Maps orgHandle, preserves alias chains and UTM parameters.
--
-- These are LIVE production URLs — preservation is critical.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

INSERT INTO `tblShortURLs` (
    `urlUID`,
    `orgHandle`,
    `shortCode`,
    `destinationURL`,
    `destinationType`,
    `redirectAlias`,
    `title`,
    `categoryID`,
    `utmSource`,
    `utmMedium`,
    `utmCampaign`,
    `utmTerm`,
    `utmContent`,
    `validateDestination`,
    `allowLinksPage`,
    `isActive`,
    `startDate`,
    `endDate`,
    `urlNotes`,
    `createdAt`,
    `updatedAt`
)
SELECT
    old.`urlUID`                    AS `urlUID`,
    IFNULL(old.`urlOrgID`, '[default]')
                                    AS `orgHandle`,
    old.`urlID`                     AS `shortCode`,
    old.`urlLong`                   AS `destinationURL`,
    CASE
        WHEN old.`urlRedirAlias` IS NOT NULL AND old.`urlRedirAlias` != ''
        THEN 'alias'
        ELSE 'url'
    END                             AS `destinationType`,
    NULLIF(old.`urlRedirAlias`, '')  AS `redirectAlias`,
    old.`urlDescription`             AS `title`,
    old.`categoryID`                 AS `categoryID`,
    old.`utmSource`                  AS `utmSource`,
    old.`utmMedium`                  AS `utmMedium`,
    old.`utmCampaign`                AS `utmCampaign`,
    old.`utmTerm`                    AS `utmTerm`,
    old.`utmContent`                 AS `utmContent`,
    IFNULL(old.`urlValidateRedirURL`, 1)
                                    AS `validateDestination`,
    IFNULL(old.`allowLinksPage`, 0) AS `allowLinksPage`,
    1                               AS `isActive`,
    old.`urlStartDate`              AS `startDate`,
    old.`urlEndDate`                AS `endDate`,
    CONCAT('[Migrated from MWlink] ', IFNULL(old.`urlNotes`, ''))
                                    AS `urlNotes`,
    old.`urlDateAdded`              AS `createdAt`,
    old.`urlLastUpdated`            AS `updatedAt`
FROM `mwtools_mwlink`.`tblShortURLs` old
ON DUPLICATE KEY UPDATE
    `destinationURL` = VALUES(`destinationURL`),
    `updatedAt` = NOW();

-- =========================================================================
-- Verification
-- =========================================================================
-- Expected: 480 short URLs, all with isActive = 1
SELECT 'Migration 004 — Short URLs' AS `migration`,
       COUNT(*) AS `url_count`,
       SUM(`isActive`) AS `active_count`,
       SUM(CASE WHEN `destinationType` = 'alias' THEN 1 ELSE 0 END) AS `alias_count`
FROM `tblShortURLs`;
