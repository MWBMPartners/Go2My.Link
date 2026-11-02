-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: form feedback and confirm-box translations (en-GB)
-- ============================================================================
--
-- Issue #286: forms gave no sign of progress and could be sent twice, and two
-- risky changes happened with no "are you sure?" step. This file holds the
-- text that fix added:
--
--   * The "Working…" label that web/Go2My.Link/public_html/js/forms.js puts on
--     a submit button while a form is being sent. web/_includes/footer.php
--     prints it into the script tag, so it must exist for every page.
--   * The "Change role" control on the Organisation Members page, which used
--     to change a member's role the moment the dropdown moved. It now has its
--     own button, a hidden label for screen readers, and a confirm box.
--   * The confirm box on the LinksPage list's "Unpublish" button.
--
-- Every one of these is read through __() with the plain-English default
-- written beside it in the page, as the other pages do.
--
-- ⚠️ IF THIS FILE IS NOT IMPORTED into an EXISTING database: on a real page
-- (as opposed to the unit tests), web/_includes/page_init.php always loads
-- web/_functions/i18n.php, so __() does NOT fall back to the plain-English
-- default written at the call site — it falls back to i18n.php's own last
-- resort, which returns the key itself unchanged. On a database missing this
-- seed, every submit button would show the literal text "common.working"
-- while a form is sent, and the Members and LinksPage controls below would
-- show their key names instead of English. This file must therefore be
-- applied to every existing database, not only to a fresh install (see the
-- seed table in docs/DEPLOYMENT.md). It is INSERT IGNORE, so re-running it
-- changes nothing and is safe.
--
-- New keys:
--   common.working                    Label on a submit button while its
--                                     form is being sent.
--   org.members_new_role_label        Hidden label on each member's role
--                                     dropdown (the member's name follows it).
--   org.members_change_role_button    Visible text of each member's
--                                     "Change role" button.
--   org.members_change_role_confirm   Confirm box shown before a role changes.
--   linkspage.unpublish_confirm       Confirm box shown before a LinksPage is
--                                     unpublished.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans; 064-070 are taken by other pieces of work. This file
-- takes 071.
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
-- @since      v1.0.0 — Launch Hardening (#286)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'common.working', 'Working…', 'Common UI', 1),
('en-GB', 'org.members_new_role_label', 'New role', 'Organisation pages', 1),
('en-GB', 'org.members_change_role_button', 'Change role', 'Organisation pages', 1),
('en-GB', 'org.members_change_role_confirm', 'Change the role of this member? Their access to the organisation changes as soon as you confirm.', 'Organisation pages', 1),
('en-GB', 'linkspage.unpublish_confirm', 'Unpublish this LinksPage? Visitors will no longer be able to see it at its public address. You can publish it again later.', 'LinksPage management (admin)', 1);
