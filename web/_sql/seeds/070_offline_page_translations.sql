-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: offline page wording (en-GB)
-- ============================================================================
--
-- Issue #283: the service workers no longer keep copies of pages. When a page
-- cannot load because the network is down, they show the new offline page
-- at /offline on go2my.link and on the dashboard (the shared page is
-- web/_includes/offline_page.php), whose three strings are seeded here.
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install
-- (see the seed table in docs/DEPLOYMENT.md). Without it the offline page
-- falls back to its built-in English, so it never shows key names, but it
-- cannot be translated. It is INSERT IGNORE, so re-running it is safe.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-069 and 071 are taken. This file takes 070.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening (#283)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'offline.title', 'You are offline', 'Offline page', 1),
('en-GB', 'offline.message', 'This page needs an internet connection. Check your connection, then try again.', 'Offline page', 1),
('en-GB', 'offline.retry', 'Try again', 'Offline page', 1);
