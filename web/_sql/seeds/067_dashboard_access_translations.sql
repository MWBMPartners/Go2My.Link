-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: dashboard "access refused" translations (en-GB)
-- ============================================================================
--
-- Issue #279: six dashboard pages (API Keys, Custom Domains, Members, Invite
-- Member, Organisation Settings and Short Domains) refused access with a bare
-- one-line red box. Five of them had English-only wording, and a person who
-- simply has no organisation yet was told "You do not have permission",
-- which is the wrong reason. All six now call one shared function,
-- g2ml_renderOrgAccessDenied() in web/_functions/org.php, which shows a
-- proper heading and a button. This file holds every string that function
-- and those pages use. (The sixth page, API Keys, already had its message
-- as apikeys.error_forbidden in 010_phase6_translations.sql.)
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED into an EXISTING database: on a real page
-- (as opposed to the unit tests), web/_includes/page_init.php always loads
-- web/_functions/i18n.php, so __() does NOT fall back to the plain-English
-- default written at the call site — it falls back to i18n.php's own last
-- resort, which returns the key itself unchanged. That means, on a database
-- missing this seed, a refused dashboard page would show the literal text
-- "access.denied_heading" instead of "Access Denied". This file must
-- therefore be applied to every existing database, not only to a fresh
-- install (see the seed table in docs/DEPLOYMENT.md). It is INSERT IGNORE,
-- so re-running it on a database that already has these rows changes
-- nothing and is safe.
--
-- New keys:
--   access.not_in_org_heading         Heading shown to a person whose
--                                     organisation is still '[default]'.
--   access.not_in_org_message         The line under that heading.
--   access.create_org_button          Button that goes to /org/create.
--   access.denied_heading             Heading shown to a person who belongs
--                                     to an organisation but may not manage
--                                     it.
--   access.back_to_dashboard          Button back to the dashboard home.
--   org.domains_error_forbidden       Custom Domains page's refusal message.
--   org.members_error_forbidden       Members page's refusal message.
--   org.invite_error_forbidden        Invite Member page's refusal message.
--   org.settings_error_forbidden      Organisation Settings page's refusal
--                                     message.
--   org.short_domains_error_forbidden Short Domains page's refusal message.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064, 065 and 066 are already taken (#221/#273, #218,
-- #147). This file takes 067, the next number free.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language), 010_phase6_translations.sql (the rest of the org.* keys, which
-- these sit alongside but do not modify).
--
-- Safe to re-run: the one statement below is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Dashboard fixes (#279)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'access.not_in_org_heading', 'You are not in an organisation yet', 'Dashboard access', 1),
('en-GB', 'access.not_in_org_message', 'API keys, domains and members belong to an organisation. Create one to use this page.', 'Dashboard access', 1),
('en-GB', 'access.create_org_button', 'Create an organisation', 'Dashboard access', 1),
('en-GB', 'access.denied_heading', 'Access Denied', 'Dashboard access', 1),
('en-GB', 'access.back_to_dashboard', 'Back to Dashboard', 'Dashboard access', 1),
('en-GB', 'org.domains_error_forbidden', 'You do not have permission to manage domains.', 'Organisation pages', 1),
('en-GB', 'org.members_error_forbidden', 'You do not have permission to manage members.', 'Organisation pages', 1),
('en-GB', 'org.invite_error_forbidden', 'You do not have permission to invite members.', 'Organisation pages', 1),
('en-GB', 'org.settings_error_forbidden', 'You do not have permission to manage organisation settings.', 'Organisation pages', 1),
('en-GB', 'org.short_domains_error_forbidden', 'You do not have permission to manage short domains.', 'Organisation pages', 1);
