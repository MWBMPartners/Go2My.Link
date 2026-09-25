-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: org/members "Unknown action" translation (en-GB)
-- ============================================================================
--
-- Issue #147: the Organisation Members page's POST handler used to fall
-- through a switch statement with no default case, so posting
-- an action_type the switch did not recognise did nothing and showed no
-- message at all. Namespacing each row's CSRF form name by action and row
-- id (the #147 fix itself) added a branch ahead of that switch, and that
-- branch needed its own message for "the posted action_type matched none
-- of the CSRF form names this page knows how to build" — a case the old
-- code never surfaced. That message is new, so — unlike the plain-English
-- literals already sitting elsewhere on this page, which #147 did not
-- touch — it goes through __() from the moment it is added, per the
-- house rule that every user-facing string goes through the translation
-- system. web/Go2My.Link/_admin/public_html/pages/org/members/index.php
-- already follows the function_exists('__') guard/fallback pattern for
-- $pageTitle and $pageDesc above; this message uses the same pattern.
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED into an EXISTING database: on a real page
-- (as opposed to the unit tests), web/_includes/page_init.php always loads
-- web/_functions/i18n.php, so __() does NOT fall back to the plain-English
-- default written at the call site — it falls back to i18n.php's own last
-- resort, which returns the key itself unchanged. That means, on a
-- database missing this seed, an unrecognised action_type on this page
-- would show the literal text "org.members_error_unknown_action" instead
-- of English. This file must therefore be applied to every existing
-- database, not only to a fresh install (see the seed table in
-- docs/DEPLOYMENT.md and the owner-action row in PRE_LAUNCH_CHECKLIST.md).
-- It is INSERT IGNORE, so re-running it on a database that already has
-- this row changes nothing and is safe.
--
-- New key:
--   org.members_error_unknown_action  Shown when a POST to this page's
--                                     handler carries an action_type the
--                                     handler does not recognise.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064 and 065 are already taken (#221/#273, #218).
-- This file takes 066, the next number free.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language), 010_phase6_translations.sql (the rest of the org.members_*
-- keys, which this file's key sits alongside but does not modify).
--
-- Safe to re-run: the one statement below is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening (#147)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'org.members_error_unknown_action', 'Unknown action.', 'Organisation pages', 1);
