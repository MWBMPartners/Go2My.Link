-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: LinksPage privacy disclosure strings (en-GB)
-- ============================================================================
--
-- Issue #219 (GDPR/CCPA — LinksPage data export and account deletion): the
-- code fix in web/_functions/data_rights.php makes LinksPage data (a page's
-- title, bio, avatar, colours, font, social links, and its individual link
-- items) part of the subject-access export and the right-to-erasure delete.
-- That data was already being collected before this fix — the fix only
-- corrected what export/deletion DID with it — but three user-facing pages
-- never said so:
--
--   1. The Privacy Policy's "Data We Collect" section
--      (web/Go2My.Link/public_html/pages/legal/privacy/index.php) never
--      disclosed LinksPage data as a category at all, alongside "Account
--      Data" and "Short URL Data". Fixed with a new section 2.4 (the
--      existing Cookies and DNT subsections move from 2.4/2.5 to 2.5/2.6 in
--      the page markup, which needs no seed of its own — the h3 headings
--      only carry a literal number, not a translation key, and no other
--      page links to those subsections by anchor).
--
--   2. Review round 1 on #219 found the Delete Account page's warning list
--      (web/Go2My.Link/_admin/public_html/pages/privacy/delete/index.php)
--      still said nothing about LinksPages, even though deleting an account
--      now permanently deletes them too — someone could delete their
--      account without knowing their public lnks.page address, and any
--      traffic still reaching it from social media bios or business cards,
--      would disappear with it. Fixed with one more warning list item.
--
--   3. The same review round found the Export page's "What's Included" list
--      (web/Go2My.Link/_admin/public_html/pages/privacy/export/index.php)
--      still named only four categories, although the export file has
--      carried LinksPage data since this same issue's code fix. Fixed with
--      one more list item.
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED, all three of the above show their raw key
-- names (e.g. "legal.privacy_s2_linkspage_title") instead of English text —
-- the same class of fault as #164 and #199. On an existing database this
-- file must be applied along with the code.
--
-- New keys:
--   legal.privacy_s2_linkspage_title    Privacy Policy — "2.4" heading
--   legal.privacy_s2_linkspage_desc     Privacy Policy — intro sentence
--   legal.privacy_s2_linkspage_profile  Privacy Policy — page title/bio/avatar bullet
--   legal.privacy_s2_linkspage_design   Privacy Policy — template/colours/font/socials bullet
--   legal.privacy_s2_linkspage_items    Privacy Policy — the page's link items bullet
--   legal.privacy_s2_linkspage_public   Privacy Policy — "a published page is public" bullet
--   delete.warning_linkspages           Delete Account page — LinksPages warning bullet
--   export.includes_linkspages          Export page — "What's Included" LinksPages bullet
--
-- Numbering note: seed numbers 024–028 are reserved (not used here) for
-- other LinksPage programme items' own translation seeds (LP-03/04/05/06/07
-- — see the LinksPage programme's gating design). This file takes the next
-- free number, 029, so it cannot collide with any of those when they land.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language), 010_phase6_translations.sql (the rest of the legal.*,
-- delete.* and export.* keys these strings sit beside).
--
-- Safe to re-run: every statement is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.1.0
-- @since      v1.2.0 — Phase 8 (#219)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.privacy_s2_linkspage_title', 'LinksPage Data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_linkspage_desc', 'When you create a LinksPage — a public profile page on lnks.page — we collect:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_linkspage_profile', 'Your page title, bio/description, and profile avatar image', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_linkspage_design', 'Your chosen template, theme and background colours, font, any social media links you add, and any custom HTML or CSS you add (where that feature is available on your plan)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_linkspage_items', 'The links you add to the page, including each one''s title, destination URL, description, and icon', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_linkspage_public', 'A published LinksPage is a public page: everything on it is visible to anyone who opens its address, not only to you', 'Legal — Privacy Policy', 1),
('en-GB', 'delete.warning_linkspages', 'Your LinksPages, and every link on them, will be permanently deleted and will stop being visible at their lnks.page address.', 'Delete account page', 1),
('en-GB', 'export.includes_linkspages', 'Your LinksPages and the links on them (title, bio, avatar address, colours, font, social links, and each link''s title, address and description)', 'Data export page', 1);
