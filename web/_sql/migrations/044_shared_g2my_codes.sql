-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 044: organisation links share the g2my.link codes
-- (#289, owner decision D-003)
-- =============================================================================
-- WHAT WAS WRONG. A member of an organisation with no verified short web
-- address of its own was handed a g2my.link/CODE address that never opened:
-- the redirect only looked for CODE among the default organisation's links.
--
-- WHAT THE OWNER DECIDED (D-003). Their g2my.link links work like anyone
-- else's. Each code on g2my.link is used once across everybody. The
-- organisation still sees the link and its statistics. An organisation with
-- its own verified short domain keeps its own set of codes on that domain.
--
-- WHAT THIS FILE DOES to an ALREADY-IMPORTED database (a fresh install gets
-- all of it from web/_sql/schema/020_shorturls_categories_tags.sql):
--   1. adds tblShortURLs.sharedShortCode — a copy of the link's code when
--      the link opens on g2my.link, NULL when it lives only on its
--      organisation's own domain;
--   2. adds the unique key UQ_url_shared_code on it, so each code is used
--      once on g2my.link, and two people creating the same code at the same
--      moment cannot both succeed;
--   3. adds the check CK_url_shared_code_matches (the column is empty or
--      equal to shortCode, never a different code);
--   4. fills the column in for the links that belong on g2my.link (below);
--   5. lists the links it could NOT put on g2my.link, because another link
--      already holds the same code there.
--
-- WHICH EXISTING LINKS GO ON g2my.link (step 4), in this order:
--   a. every link of the default organisation ('[default]') — these are the
--      links that open on g2my.link today, so they always keep their code;
--   b. then every link of an organisation that has NO verified, active,
--      default short domain, active links before disabled ones and, within
--      that, the oldest first (smallest urlUID). When two such
--      organisations hold the same code, the first one in that order gets
--      it; the others are left out and listed by step 5.
--   Links of an organisation WITH a verified, active, default short domain
--   are left alone: they keep opening on that domain only. That is the same
--   rule createShortURL() now uses for new links (see
--   g2ml_linkUsesSharedShortCodes() in
--   web/Go2My.Link/_functions/shorturl_create.php), so old and new links
--   follow one rule.
--   Filling the column in never stops a link that works today from working:
--   an organisation's own domain still finds every one of its links by code
--   and organisation, whether or not the column is filled in, and every
--   default-organisation link keeps its g2my.link code.
--
-- WHAT THE LEFT-OUT LINKS MEAN (step 5). Before this migration none of them
-- opened on g2my.link anyway (they were not default-organisation links), so
-- nothing that worked stops working. They still need a decision: each one
-- needs a new code, or its organisation needs its own short domain. Step 5
-- prints each of them beside the link that holds the code.
--
-- SAFE TO RUN MORE THAN ONCE. Steps 1-3 look in information_schema first and
-- do nothing when the column, key or check is already there. Step 4 only
-- touches rows whose column is still empty, and its UPDATE IGNORE skips a
-- row whose code is already taken instead of failing. Run it AGAIN after the
-- old MWlink links are brought across by migration 004 (issue #317): 004
-- does not know about this column, so its rows arrive with it empty, and a
-- second run of this file puts them on g2my.link by the rule above.
-- Re-running also applies the rule to the links of an organisation whose own
-- domain has since been switched off: they start opening on g2my.link too
-- (when their codes are free there), which is what D-003 asks for.
--
-- NO SPECIAL PRIVILEGES. It uses plain ALTER TABLE, UPDATE and SELECT, run
-- through PREPARE / EXECUTE, and creates no stored procedure, trigger or
-- function. Tested on MySQL 8.4; written for MySQL 8.0 or newer and for
-- MariaDB 10.2 or newer (both enforce the check from those versions; an older
-- server accepts the check and ignores it).
--
-- AFTER RUNNING IT, re-import BOTH stored procedures:
--   web/_sql/procedures/sp_generateShortCode.sql
--   web/_sql/procedures/sp_lookupShortURL.sql
-- The new redirect lookup reads sharedShortCode, so this migration must run
-- BEFORE the new sp_lookupShortURL is imported, and before the new PHP is
-- deployed (the new createShortURL() writes the column).
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      #289 (owner decision D-003)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- Step 1: the column
-- -----------------------------------------------------------------------------
SET @g2ml_044_column_count = (
    SELECT COUNT(*)
    FROM   information_schema.COLUMNS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblShortURLs'
       AND COLUMN_NAME  = 'sharedShortCode'
);

SET @g2ml_044_sql = IF(
    @g2ml_044_column_count = 0,
    'ALTER TABLE `tblShortURLs` ADD COLUMN `sharedShortCode` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT ''The same value as shortCode when this link opens on g2my.link, where each code is used once across everybody (#289, D-003); NULL when the link lives only in its own organisation''''s codes on that organisation''''s verified short domain'' AFTER `shortCode`',
    'DO 0'
);

PREPARE g2ml_044_statement FROM @g2ml_044_sql;
EXECUTE g2ml_044_statement;
DEALLOCATE PREPARE g2ml_044_statement;

-- -----------------------------------------------------------------------------
-- Step 2: the unique key (added before step 4, which relies on it to skip a
-- code that is already taken)
-- -----------------------------------------------------------------------------
SET @g2ml_044_index_count = (
    SELECT COUNT(*)
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblShortURLs'
       AND INDEX_NAME   = 'UQ_url_shared_code'
);

SET @g2ml_044_sql = IF(
    @g2ml_044_index_count = 0,
    'ALTER TABLE `tblShortURLs` ADD UNIQUE KEY `UQ_url_shared_code` (`sharedShortCode`)',
    'DO 0'
);

PREPARE g2ml_044_statement FROM @g2ml_044_sql;
EXECUTE g2ml_044_statement;
DEALLOCATE PREPARE g2ml_044_statement;

-- -----------------------------------------------------------------------------
-- Step 3: the check
-- -----------------------------------------------------------------------------
SET @g2ml_044_check_count = (
    SELECT COUNT(*)
    FROM   information_schema.TABLE_CONSTRAINTS
    WHERE  CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME        = 'tblShortURLs'
       AND CONSTRAINT_NAME   = 'CK_url_shared_code_matches'
);

SET @g2ml_044_sql = IF(
    @g2ml_044_check_count = 0,
    'ALTER TABLE `tblShortURLs` ADD CONSTRAINT `CK_url_shared_code_matches` CHECK (`sharedShortCode` IS NULL OR `sharedShortCode` = `shortCode`)',
    'DO 0'
);

PREPARE g2ml_044_statement FROM @g2ml_044_sql;
EXECUTE g2ml_044_statement;
DEALLOCATE PREPARE g2ml_044_statement;

-- -----------------------------------------------------------------------------
-- Step 4a: the default organisation's links keep their g2my.link codes
-- -----------------------------------------------------------------------------
UPDATE IGNORE `tblShortURLs`
SET    `sharedShortCode` = `shortCode`
WHERE  `sharedShortCode` IS NULL
   AND `orgHandle` = '[default]'
ORDER BY `urlUID` ASC;

-- -----------------------------------------------------------------------------
-- Step 4b: links of organisations with no verified, active, default short
-- domain of their own; active links first, then the oldest first
-- -----------------------------------------------------------------------------
UPDATE IGNORE `tblShortURLs`
SET    `sharedShortCode` = `shortCode`
WHERE  `sharedShortCode` IS NULL
   AND `orgHandle` <> '[default]'
   AND NOT EXISTS (
        SELECT 1
        FROM   `tblOrgShortDomains` `d`
        WHERE  `d`.`orgHandle`          = `tblShortURLs`.`orgHandle`
           AND `d`.`isDefault`          = 1
           AND `d`.`isActive`           = 1
           AND `d`.`verificationStatus` = 'verified'
   )
ORDER BY `isActive` DESC, `urlUID` ASC;

-- -----------------------------------------------------------------------------
-- Step 5: report
-- -----------------------------------------------------------------------------
-- 5a. The links that belong on g2my.link but could not be put there, because
-- another link already holds the same code. Expected on a database that has
-- never had two organisations use the same code: no rows. Each row needs a
-- decision (a new code, or a short domain for its organisation); until then
-- it does not open on g2my.link, exactly as before this migration.
SELECT 'Migration 044 — links left off g2my.link because the code is taken' AS `report`,
       `s`.`urlUID`                 AS `leftOutUrlUID`,
       `s`.`orgHandle`              AS `leftOutOrgHandle`,
       `s`.`shortCode`              AS `shortCode`,
       `s`.`isActive`               AS `leftOutIsActive`,
       `h`.`urlUID`                 AS `holderUrlUID`,
       `h`.`orgHandle`              AS `holderOrgHandle`
FROM   `tblShortURLs` `s`
JOIN   `tblShortURLs` `h`
       ON  `h`.`sharedShortCode` = `s`.`shortCode`
WHERE  `s`.`sharedShortCode` IS NULL
   AND (
        -- The default organisation owns the g2my.link domain row itself, so
        -- it is named here; the test below would otherwise hide its rows.
        `s`.`orgHandle` = '[default]'
        OR NOT EXISTS (
            SELECT 1
            FROM   `tblOrgShortDomains` `d`
            WHERE  `d`.`orgHandle`          = `s`.`orgHandle`
               AND `d`.`isDefault`          = 1
               AND `d`.`isActive`           = 1
               AND `d`.`verificationStatus` = 'verified'
        )
   )
ORDER BY `s`.`shortCode`, `s`.`urlUID`;

-- 5b. Totals: how many links open on g2my.link, and how many live only on
-- an organisation's own domain.
SELECT 'Migration 044 — totals' AS `report`,
       COUNT(*)                                                   AS `allLinks`,
       SUM(CASE WHEN `sharedShortCode` IS NOT NULL THEN 1 ELSE 0 END) AS `onG2myLink`,
       SUM(CASE WHEN `sharedShortCode` IS NULL     THEN 1 ELSE 0 END) AS `ownDomainOrLeftOut`
FROM   `tblShortURLs`;

-- 5c. A safety check: the column must be empty or equal to the link's own
-- code. Expected: 0. (On a server that ignores CHECK constraints this is the
-- only place a mismatch would show.)
SELECT 'Migration 044 — mismatched codes (expected 0)' AS `report`,
       COUNT(*) AS `mismatched`
FROM   `tblShortURLs`
WHERE  `sharedShortCode` IS NOT NULL
   AND `sharedShortCode` <> `shortCode`;
