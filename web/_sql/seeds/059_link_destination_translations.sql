-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: dashboard destination-check error translations (en-GB)
-- ============================================================================
--
-- #205: editing a link on the dashboard used to run only ONE of the three
-- checks creating a link runs (sanitise the URL; refuse our own short
-- domains; refuse internal/metadata hosts) — and the API's PUT handler
-- skipped the own-domain check too. All three checks now live in one
-- function, g2ml_validateLinkDestination(), which create, dashboard edit,
-- and the API update handler all call. This seed carries the en-GB text for
-- the three error messages the dashboard edit page shows, one per
-- errorCode the shared function can return.
--
-- The API is not translated and returns the shared function's English
-- 'error' text directly, so it needs no row here.
--
-- INSERT IGNORE, like every other translation seed here, so re-running this
-- file is safe and never overwrites a row that already exists.
--
-- This file MUST be applied to every EXISTING database, not only a fresh
-- install. On a real request, page_init.php always loads i18n.php, so the
-- PHP `else` fallback in pages/links/edit/index.php never runs there —
-- __() resolves the message from this table instead, and a database
-- missing this seed shows the raw key name (for example
-- "links.error_destination_own_domain") instead of English, which is
-- __()'s own last-resort behaviour for a missing key (see the "Return the
-- key itself as a last resort" comment in web/_functions/i18n.php).
--
-- The en-GB value in each row below matches, word for word, the English
-- text g2ml_validateLinkDestination() (web/Go2My.Link/_functions/
-- shorturl_create.php) returns for that errorCode, which is also the exact
-- message the CREATE form has always shown for the same failure — this
-- seed makes the dashboard EDIT form show the identical wording.
--
-- Numbering note: this is seed 059, the number this item's plan reserved
-- for it; seed numbers 060 upward were reserved by other programme items'
-- own plans and 064-066 were already taken by items built before this one.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB
-- language).
--
-- Safe to re-run: every statement is INSERT IGNORE.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (SX-205, #205)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'links.error_destination_invalid_url', 'Invalid URL format. Please enter a valid HTTP or HTTPS URL.', 'Dashboard — edit link', 1),
('en-GB', 'links.error_destination_own_domain', 'Cannot shorten URLs that point to this service.', 'Dashboard — edit link', 1),
('en-GB', 'links.error_destination_blocked_destination', 'That destination is not permitted. Please enter a public HTTP or HTTPS URL.', 'Dashboard — edit link', 1);
