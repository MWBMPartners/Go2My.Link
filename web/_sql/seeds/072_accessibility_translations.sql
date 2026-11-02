-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: accessibility wording (en-GB)
-- ============================================================================
--
-- Issue #287: the site-wide part of the contrast, focus ring and layout
-- fixes needed three new pieces of wording. Each is read by a screen reader,
-- or shows only after a click, so none of them is easy to spot on the page.
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED into an EXISTING database: on a real page
-- web/_includes/page_init.php always loads web/_functions/i18n.php, so __()
-- does NOT fall back to the plain-English default written at the call site.
-- It falls back to i18n.php's own last resort, which returns the key itself
-- unchanged. On a database missing this seed:
--   - the cookie banner's hidden link words would read "cookie.learn_more_suffix"
--     to a screen reader;
--   - a copy button would show "common.copied" after it is clicked.
-- (The "opens in a new tab" words are safe either way: the helper
-- newTabNotice() in web/_includes/accessibility.php notices the key coming
-- back and uses the English instead.) So this file must be applied to every
-- existing database, not only to a fresh install. It is INSERT IGNORE, so
-- re-running it on a database that already has these rows changes nothing
-- and is safe.
--
-- New keys:
--   common.opens_new_tab          Hidden words added to the end of every link
--                                 that opens a new tab. Includes the brackets.
--   common.copied                 The words a copy button shows, and uses as
--                                 its accessible name, just after it has copied.
--   cookie.learn_more_suffix      Hidden words added after the cookie banner's
--                                 visible "Learn more", so the link's name says
--                                 what it opens ("Learn more about our cookie
--                                 policy"). Keep the visible words first.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-071 are taken by other pieces of work already in
-- progress. This file takes 072, the next number free.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language), 010_phase6_translations.sql (the other cookie.* keys, which
-- these keys sit alongside but do not modify).
--
-- Safe to re-run: every statement below is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Accessibility (#287)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'common.opens_new_tab', '(opens in a new tab)', 'Common UI', 1),
('en-GB', 'common.copied', 'Copied!', 'Common UI', 1),
('en-GB', 'cookie.learn_more_suffix', 'about our cookie policy', 'Cookie banner', 1);
