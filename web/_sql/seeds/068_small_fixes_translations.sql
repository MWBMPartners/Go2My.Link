-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: wording for the #284 small fixes (en-GB)
-- ============================================================================
--
-- Issue #284 fixed several small things that looked finished but were not.
-- Four of the fixes show text through __(), so it is seeded here:
--   - the new legal hub page at /legal (the "Back to Legal" button on the
--     Terms and Acceptable Use pages used to lead to a 404);
--   - the LinksPage editor's message after deleting a link (it used to say
--     "Link saved.");
--   - My Links showing "Expired" or "Scheduled" instead of "Active" for a
--     switched-on link outside its start and end dates;
--   - My Links' "Prev" and "Next" page buttons (same wording as before, now
--     through __(), including the greyed-out versions on the first and last
--     page).
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install:
-- on a real page a missing key shows the key name itself (see the seed table
-- in docs/DEPLOYMENT.md). It is INSERT IGNORE, so re-running it is safe.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-067 are taken. This file takes 068.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening (#284)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.hub_title', 'Legal', 'Legal hub page', 1),
('en-GB', 'legal.hub_description', 'The terms, privacy and cookie policies, acceptable use rules and copyright notice for Go2My.Link.', 'Legal hub page', 1),
('en-GB', 'legal.hub_intro', 'The rules for using Go2My.Link, and how we look after your information.', 'Legal hub page', 1),
('en-GB', 'linkspage.item_saved', 'Link saved.', 'LinksPage editor', 1),
('en-GB', 'linkspage.item_deleted', 'Link deleted.', 'LinksPage editor', 1),
('en-GB', 'links.status_expired', 'Expired', 'My Links', 1),
('en-GB', 'links.status_scheduled', 'Scheduled', 'My Links', 1),
('en-GB', 'links.page_prev', 'Prev', 'My Links', 1),
('en-GB', 'links.page_next', 'Next', 'My Links', 1);
