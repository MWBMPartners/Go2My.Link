-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: home page search description (en-GB)
-- ============================================================================
--
-- Issue #282: the home page used the three-word tagline as its search and
-- sharing description. It now has a sentence of its own. Checked against the
-- code (#210's rule that marketing text must be true): shortening needs no
-- account (api/create), and the dashboard's My Links list shows each link's
-- click count to any signed-in user, on the free plan too.
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install:
-- on a real page a missing key shows the key name itself (see the seed table
-- in docs/DEPLOYMENT.md). It is INSERT IGNORE, so re-running it is safe.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-068 are taken. This file takes 069.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening (#282)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'home.meta_description', 'Shorten long web addresses for free, with no account needed. Sign up free to keep all your links in one place and see how often each one is clicked.', 'Homepage', 1);
