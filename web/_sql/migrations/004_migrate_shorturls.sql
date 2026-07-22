-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

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

START TRANSACTION;

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
    -- Zero-date/NULL guard: nullable target → strip zero-dates to NULL (#123)
    NULLIF(NULLIF(CAST(old.`urlStartDate` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00')
                                    AS `startDate`,
    NULLIF(NULLIF(CAST(old.`urlEndDate` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00')
                                    AS `endDate`,
    CONCAT('[Migrated from MWlink] ', IFNULL(old.`urlNotes`, ''))
                                    AS `urlNotes`,
    -- Zero-date/NULL guard: NOT NULL target → fall back to NOW() (#123)
    IFNULL(NULLIF(NULLIF(CAST(old.`urlDateAdded` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00'), NOW())
                                    AS `createdAt`,
    -- Zero-date/NULL guard: nullable target → strip zero-dates to NULL (#123)
    NULLIF(NULLIF(CAST(old.`urlLastUpdated` AS CHAR), '0000-00-00 00:00:00'), '0000-00-00')
                                    AS `updatedAt`
FROM `mwtools_mwlink`.`tblShortURLs` old
ON DUPLICATE KEY UPDATE
    `destinationURL` = VALUES(`destinationURL`),
    `updatedAt` = NOW();

COMMIT;

-- =========================================================================
-- Verification
-- =========================================================================
-- Expected: 480 short URLs, all with isActive = 1
SELECT 'Migration 004 — Short URLs' AS `migration`,
       COUNT(*) AS `url_count`,
       SUM(`isActive`) AS `active_count`,
       SUM(CASE WHEN `destinationType` = 'alias' THEN 1 ELSE 0 END) AS `alias_count`
FROM `tblShortURLs`;

-- =========================================================================
-- Alias-chain integrity verification (#128)
-- =========================================================================
-- READ-ONLY, SELECT-only checks — no repair, no DELETE/UPDATE. This is a
-- pre-cutover safety net so the owner catches broken alias data BEFORE go
-- live, not an auto-fix. Every offender listed here should be corrected by
-- editing that row's redirectAlias/destinationType, not by deleting rows.
--
-- Context: the redirect hot path (web/_sql/procedures/sp_lookupShortURL.sql)
-- follows a chain purely off `redirectAlias` being non-NULL/non-empty (it
-- does not read destinationType at all) and caps following at 3 lookups
-- (v_maxHops). A cycle or an over-long chain already fails CLEANLY there —
-- it returns status 'max_hops_exceeded' with the org's fallback URL, never a
-- wrong/partial destination — but the affected link is still functionally
-- broken (it never resolves), so it is worth surfacing here before cutover.
-- All checks below scope the alias target lookup to the SAME orgHandle,
-- mirroring the procedure's own per-hop match (shortCode + orgHandle).

-- 4.1 Consistency: does destinationType agree with whether redirectAlias is
-- meaningful? (This is the invariant migration 004's own INSERT above relies
-- on — see the destinationType CASE expression above — and the one that the
-- procedure's documentation now cites as the reason it is safe to leave the
-- gate keyed on redirectAlias alone; see #128 note in that procedure's
-- header comment.)
SELECT 'Alias integrity — destinationType/redirectAlias mismatch' AS `check_name`,
       COUNT(*) AS `offender_count`,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — see detail query below' END AS `status`
FROM   `tblShortURLs` s
WHERE  (s.`destinationType` = 'alias' AND (s.`redirectAlias` IS NULL OR s.`redirectAlias` = ''))
    OR (s.`destinationType` != 'alias' AND s.`redirectAlias` IS NOT NULL AND s.`redirectAlias` != '');

SELECT s.`orgHandle`, s.`shortCode`, s.`destinationType`, s.`redirectAlias`
FROM   `tblShortURLs` s
WHERE  (s.`destinationType` = 'alias' AND (s.`redirectAlias` IS NULL OR s.`redirectAlias` = ''))
    OR (s.`destinationType` != 'alias' AND s.`redirectAlias` IS NOT NULL AND s.`redirectAlias` != '')
ORDER BY s.`orgHandle`, s.`shortCode`;

-- 4.2 Dangling aliases: redirectAlias does not match any existing short code
-- in the same org (following this chain hits 'not_found' partway through —
-- see sp_lookupShortURL Step 2's "short code not found" branch).
SELECT 'Alias integrity — dangling aliases' AS `check_name`,
       COUNT(*) AS `offender_count`,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — see detail query below' END AS `status`
FROM   `tblShortURLs` s
WHERE  s.`redirectAlias` IS NOT NULL
  AND  s.`redirectAlias` != ''
  AND  NOT EXISTS (
           SELECT 1
           FROM   `tblShortURLs` target
           WHERE  target.`shortCode` = s.`redirectAlias`
             AND  target.`orgHandle` = s.`orgHandle`
       );

SELECT s.`orgHandle`, s.`shortCode`, s.`redirectAlias` AS `danglingTarget`
FROM   `tblShortURLs` s
WHERE  s.`redirectAlias` IS NOT NULL
  AND  s.`redirectAlias` != ''
  AND  NOT EXISTS (
           SELECT 1
           FROM   `tblShortURLs` target
           WHERE  target.`shortCode` = s.`redirectAlias`
             AND  target.`orgHandle` = s.`orgHandle`
       )
ORDER BY s.`orgHandle`, s.`shortCode`;

-- 4.3 Self-referencing aliases (1-hop cycle): a short code whose
-- redirectAlias points back at itself.
SELECT 'Alias integrity — self-referencing aliases (A→A)' AS `check_name`,
       COUNT(*) AS `offender_count`,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — see detail query below' END AS `status`
FROM   `tblShortURLs` s
WHERE  s.`redirectAlias` IS NOT NULL
  AND  s.`redirectAlias` != ''
  AND  s.`redirectAlias` = s.`shortCode`;

SELECT s.`orgHandle`, s.`shortCode`
FROM   `tblShortURLs` s
WHERE  s.`redirectAlias` IS NOT NULL
  AND  s.`redirectAlias` != ''
  AND  s.`redirectAlias` = s.`shortCode`
ORDER BY s.`orgHandle`, s.`shortCode`;

-- 4.4 2-hop cycles: A → B → A within the same org (excludes the 1-hop
-- self-alias already reported in 4.3).
SELECT 'Alias integrity — 2-hop cycles (A→B→A)' AS `check_name`,
       COUNT(*) AS `offender_count`,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — see detail query below' END AS `status`
FROM   `tblShortURLs` a
INNER JOIN `tblShortURLs` b
        ON  b.`shortCode` = a.`redirectAlias`
       AND  b.`orgHandle` = a.`orgHandle`
WHERE  a.`redirectAlias` IS NOT NULL
  AND  a.`redirectAlias` != ''
  AND  b.`redirectAlias`   = a.`shortCode`
  AND  a.`shortCode`      != a.`redirectAlias`;

SELECT a.`orgHandle`, a.`shortCode` AS `codeA`, b.`shortCode` AS `codeB`
FROM   `tblShortURLs` a
INNER JOIN `tblShortURLs` b
        ON  b.`shortCode` = a.`redirectAlias`
       AND  b.`orgHandle` = a.`orgHandle`
WHERE  a.`redirectAlias` IS NOT NULL
  AND  a.`redirectAlias` != ''
  AND  b.`redirectAlias`   = a.`shortCode`
  AND  a.`shortCode`      != a.`redirectAlias`
ORDER BY a.`orgHandle`, a.`shortCode`;

-- 4.5 3-hop cycles: A → B → C → A within the same org — the deepest cycle
-- the procedure's 3-hop cap (v_maxHops) can actually walk into before it
-- gives up with 'max_hops_exceeded' (excludes 1-hop and 2-hop cycles already
-- reported in 4.3/4.4).
SELECT 'Alias integrity — 3-hop cycles (A→B→C→A)' AS `check_name`,
       COUNT(*) AS `offender_count`,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL — see detail query below' END AS `status`
FROM   `tblShortURLs` a
INNER JOIN `tblShortURLs` b
        ON  b.`shortCode` = a.`redirectAlias`
       AND  b.`orgHandle` = a.`orgHandle`
INNER JOIN `tblShortURLs` c
        ON  c.`shortCode` = b.`redirectAlias`
       AND  c.`orgHandle` = a.`orgHandle`
WHERE  a.`redirectAlias` IS NOT NULL AND a.`redirectAlias` != ''
  AND  b.`redirectAlias` IS NOT NULL AND b.`redirectAlias` != ''
  AND  c.`redirectAlias`   = a.`shortCode`
  AND  a.`shortCode` != b.`shortCode`
  AND  b.`shortCode` != c.`shortCode`
  AND  a.`shortCode` != c.`shortCode`;

SELECT a.`orgHandle`, a.`shortCode` AS `codeA`, b.`shortCode` AS `codeB`, c.`shortCode` AS `codeC`
FROM   `tblShortURLs` a
INNER JOIN `tblShortURLs` b
        ON  b.`shortCode` = a.`redirectAlias`
       AND  b.`orgHandle` = a.`orgHandle`
INNER JOIN `tblShortURLs` c
        ON  c.`shortCode` = b.`redirectAlias`
       AND  c.`orgHandle` = a.`orgHandle`
WHERE  a.`redirectAlias` IS NOT NULL AND a.`redirectAlias` != ''
  AND  b.`redirectAlias` IS NOT NULL AND b.`redirectAlias` != ''
  AND  c.`redirectAlias`   = a.`shortCode`
  AND  a.`shortCode` != b.`shortCode`
  AND  b.`shortCode` != c.`shortCode`
  AND  a.`shortCode` != c.`shortCode`
ORDER BY a.`orgHandle`, a.`shortCode`;
