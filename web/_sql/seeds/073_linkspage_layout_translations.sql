-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: LinksPage public page "Profile picture" alt text (en-GB)
-- ============================================================================
--
-- Issue #287: a LinksPage with no avatar used to show the site logo, and every
-- avatar's alt text repeated the page's name right beside the heading that
-- already says it, so a screen reader read the name twice. The renderer
-- (web/Lnks.page/_functions/linkspage_renderer.php) now gives the default
-- avatar an empty alt text, because it is only decoration, and gives a real
-- uploaded picture the short text below instead of the name. The text goes
-- through __() like every other string a visitor can hear.
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED into an EXISTING database: the renderer
-- falls back to the same English words ("Profile picture") when the row is
-- missing, so a visitor never sees the raw key. The seed is still needed so
-- the text can be translated and edited like the rest.
-- It is INSERT IGNORE, so re-running it on a database that already has this
-- row changes nothing and is safe.
--
-- ⚠️ The same change also rewrote the five LinksPage templates in
-- 004_linkspage_templates.sql. Re-import that file too, or an existing
-- database keeps the old templates (they still show the person's name as the
-- avatar's alt text, and four of the five have no <main> landmark).
--
-- New key:
--   linkspage.avatar_alt  Alt text of a LinksPage's uploaded avatar picture.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-072 are already taken. This file takes 073.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language).
--
-- Safe to re-run: the one statement below is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening (#287)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'linkspage.avatar_alt', 'Profile picture', 'LinksPage public page — alt text of an uploaded avatar', 1);
