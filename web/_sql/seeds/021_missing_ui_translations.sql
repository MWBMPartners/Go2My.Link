-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: five UI strings that were used but never translated
-- ============================================================================
--
-- WHY THIS FILE EXISTS
--
-- The translation helper `__('some.key')` returns the key itself when it
-- cannot find a translation for it (web/_functions/i18n.php, near the end of
-- the __() function — "Return the key itself as a last resort (makes missing
-- translations visible)"). That is a sensible choice for spotting gaps during
-- development, but it means any key a developer uses in a page and forgets to
-- add here is shown to the visitor, raw, exactly as typed.
--
-- That is what happened in #164, where four figures on the analytics dashboard
-- and a button read as translation keys instead of words. This file closes the
-- five remaining gaps found by comparing every `__('...')` in the shipping code
-- against every key present in the database after all seeds are imported.
--
-- The wording below is not invented. Each string is the exact English text the
-- page itself already names as its own fallback, so nothing here changes what
-- the product is trying to say — it only makes the words actually appear.
--
-- FOUR OF THE FIVE ARE ACCESSIBILITY LABELS. They are read out by screen
-- readers and are invisible to everybody else, which is why they went unnoticed.
-- Until this seed is applied, a screen-reader user hears "create_link.copy_url"
-- where they should hear "Copy short URL to clipboard".
--
-- THE FIFTH IS THE HOMEPAGE TITLE, which is the opposite — highly visible. It
-- becomes the browser tab caption, the search-engine result heading and the
-- preview title when somebody shares the homepage on social media. Without
-- this row that title reads "home.title — Go2My.Link".
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB),
--               010_phase6_translations.sql (the main body of translations)
--
-- Safe to re-run: every statement is INSERT IGNORE, so applying this to a
-- database that already has these rows changes nothing.
--
-- @version    1.0.0
-- @since      2026-09-07
-- ============================================================================

USE `mwtools_Go2MyLink`;

-- ============================================================================
-- 🏠 Homepage (home.*)
-- ----------------------------------------------------------------------------
-- Used at: web/Go2My.Link/public_html/pages/home.php:30
--
-- header.php builds the finished title as "<pageTitle> — <site name>", so this
-- row produces "Home — Go2My.Link". That matches how every other page is named
-- (see 'about.title' = 'About' in 010_phase6_translations.sql) and is the exact
-- word home.php names as its own fallback.
--
-- If a richer title is wanted for search engines later, changing it is a
-- one-line edit here and needs no code change.
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'home.title', 'Home', 'Homepage', 1);

-- ============================================================================
-- ♿ Shared accessibility wording (common.*)
-- ----------------------------------------------------------------------------
-- Used at: web/Go2My.Link/_admin/public_html/pages/privacy/delete/index.php:417
--
-- Sits inside a visually-hidden span next to a required field's asterisk, so a
-- screen reader announces the field as required. Sighted visitors never see it.
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'common.required', 'required', 'Shared form wording', 1);

-- ============================================================================
-- 🔗 Create-a-link page (create_link.*)
-- ----------------------------------------------------------------------------
-- Both are aria-label attributes on the "your new link" box and its copy
-- button, at web/Go2My.Link/_admin/public_html/pages/links/create/index.php
-- lines 196 and 198.
--
-- The wording deliberately matches the equivalent controls on the public
-- homepage, which already have translations ('home.result_label' and
-- 'home.copy_to_clipboard'), so the same control is described the same way
-- wherever a visitor meets it.
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'create_link.result_url', 'Created short URL', 'Create link page', 1),
('en-GB', 'create_link.copy_url', 'Copy short URL to clipboard', 'Create link page', 1);

-- ============================================================================
-- 🏷️ Links list (links.*)
-- ----------------------------------------------------------------------------
-- Used at: web/Go2My.Link/_admin/public_html/pages/links/index.php:327
--
-- An aria-label on the group of tag badges shown against each link, so a screen
-- reader announces what the badges are rather than reading them as loose text.
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'links.tags_label', 'Tags', 'Links list', 1);
