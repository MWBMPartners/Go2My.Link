-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 048: dead short links show a page (#290, D-006)
-- =============================================================================
-- Older installs seeded the default organisation with the fallback address
-- https://go2my.link. Because of it, a short link that did not exist, had
-- expired or was switched off sent the visitor to the homepage with no
-- explanation. This clears that seeded value, so g2my.link shows a page saying
-- why, with a 404 or 410 status.
--
-- Only the exact seeded address is cleared (with or without a slash on the
-- end), and only on the '[default]' organisation. Any other address an owner
-- set there is kept, and no other organisation is touched. An owner who had
-- typed the homepage address there themselves cannot be told apart from the
-- seed; D-006 rules that behaviour out for the default organisation either
-- way.
--
-- The setting redirect.fallback_url is NOT changed. It does not decide what a
-- dead link does; it is where a bare g2my.link visit (no code) goes.
--
-- FRESH INSTALLS DO NOT NEED THIS FILE (seed 002 now stores NULL). Run it
-- after any data migration from the old system. Safe to run more than once.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.0.0 (#290)
-- =============================================================================

USE `mwtools_Go2MyLink`;

UPDATE `tblOrganisations`
SET    `orgFallbackURL` = NULL,
       `updatedAt`      = NOW()
WHERE  `orgHandle` = '[default]'
   AND `orgFallbackURL` IN ('https://go2my.link', 'https://go2my.link/');
