-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: truthful marketing text for security and analytics (en-GB)
-- ============================================================================
--
-- #210: the 2026-09-21 issue sweep found the homepage, features page and
-- about page each claiming two-factor authentication and SSO — neither is
-- built (#34, #36) — and each describing "geographic data" as if every
-- visitor gets it, when that only happens once an administrator switches on
-- IP geolocation and supplies a GeoIP database, which is off by default.
--
-- INSERT IGNORE, like every other translation seed here, so re-running this
-- file is safe and never overwrites a row that already exists. The six old
-- keys this replaces (home.feature_secure_desc, home.feature_analytics_desc,
-- features.security_desc, features.analytics_desc, about.offer_security_desc,
-- about.offer_analytics_desc, all in 010_phase6_translations.sql) are left in
-- the database exactly as they are — a translation seed never edits a row
-- that has already shipped, because another language's own translation work
-- may already depend on it staying put. The corrected wording goes in a new
-- key for each, suffixed "_v2", and the three pages this seed serves switch
-- to the new key.
--
-- This file MUST be applied to every EXISTING database, not only a fresh
-- install: on a real request page_init.php always loads i18n.php, so the
-- pages' PHP `else` fallback never runs there, and a database missing this
-- seed would show each raw key name instead of English (__()'s documented
-- last-resort behaviour for a missing key — see web/_functions/i18n.php).
-- The en-GB value in each row below matches, word for word, the fallback
-- each page uses when __() is not available.
--
-- Each claim below was checked against the code before this file was
-- written: encryption — g2ml_encrypt(), AES-256-GCM, web/_functions/
-- security.php; role-based access — hasMinimumRole(), web/_functions/
-- auth.php; the activity log — logActivity() writing to tblActivityLog,
-- web/_functions/activity_logger.php.
--
-- Restore wording naming two-factor authentication and SSO once #34 and #36
-- ship; a country/geographic breakdown can be named unconditionally once
-- IP geolocation ships switched on by default.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language), 010_phase6_translations.sql (the old keys this leaves in place).
--
-- Safe to re-run: every statement is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (SX-210, #210 issue sweep)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
-- Homepage (web/Go2My.Link/public_html/pages/home.php).
('en-GB', 'home.feature_secure_desc_v2', 'Encryption for sensitive data, role-based access and an activity log.', 'Homepage', 1),
('en-GB', 'home.feature_analytics_desc_v2', 'Track clicks, devices, referrers and more. Country breakdown where enabled.', 'Homepage', 1),
-- Features page (web/Go2My.Link/public_html/pages/features/index.php).
('en-GB', 'features.security_desc_v2', 'Sensitive data encrypted with AES-256, role-based access for teams, and an activity log of important actions.', 'Features page', 1),
('en-GB', 'features.analytics_desc_v2', 'Track clicks, device types, referrers and more on a clear dashboard. Country breakdown where enabled.', 'Features page', 1),
-- About page (web/Go2My.Link/public_html/pages/about/index.php).
('en-GB', 'about.offer_security_desc_v2', 'Sensitive data encrypted at rest with AES-256, role-based access, and an activity log of important actions.', 'About page', 1),
('en-GB', 'about.offer_analytics_desc_v2', 'Understand your audience with click tracking, device breakdowns and referrer insights, plus a country breakdown where enabled.', 'About page', 1);
