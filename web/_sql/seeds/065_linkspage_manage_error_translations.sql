-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: LinksPage management error message translations (en-GB)
-- ============================================================================
--
-- CX-01 (#218): Codex's whole-branch catch-up review (2026-09-21) found
-- that web/_functions/linkspage_manage.php returned several user-facing
-- 'error' messages as hard-coded English literals, never routed through
-- __('key'). Checking the rest of the file against the same house rule
-- (every string a person sees goes through __() — CLAUDE.md / patterns.md)
-- found the same fault throughout. Every one of those messages now goes
-- through __() with a linkspage.error.* key, each call guarded by
-- `if (function_exists('__'))` with the original English kept as the
-- `else` fallback (see linkspage_manage.php's own file-header comment for
-- why that guard exists). This file adds the en-GB text for every one of
-- those new keys.
--
-- INSERT IGNORE, like every other translation seed here, so re-running
-- this file is safe and never overwrites a row that already exists. A
-- later wording change belongs in a NEW seed with a NEW key, not an edit
-- here — once a translationValue has shipped, another language's own
-- translation work may already depend on it staying put.
--
-- This file MUST be applied to every EXISTING database, not only a fresh
-- install. On a real request, page_init.php always loads i18n.php, so the
-- PHP `else` fallback above never runs there — __() resolves every message
-- from this table instead, and a database missing this seed shows each raw
-- key name (for example "linkspage.error.slug_reserved") instead of
-- English, because that is __()'s own last-resort behaviour for a missing
-- key (see the "Return the key itself as a last resort" comment in
-- web/_functions/i18n.php).
--
-- The en-GB value in each row below matches, word for word, the English
-- fallback linkspage_manage.php uses when __() is not available, so
-- applying this seed changes nothing an English-speaking owner sees today
-- — it only stops a raw key name appearing on a database that lacked it.
--
-- The plan-limit message is two keys, one for when the limit is known and
-- one for when it is not, so each is a whole sentence a translator can
-- word freely.
--
-- A few sentences are returned, verbatim, by more than one mutation
-- function (for example the "LinksPage not found..." message). Each such
-- sentence shares ONE key across every call site that returns it, rather
-- than one key per call site — the same convention seed 064 established.
-- The short comments in the VALUES list below name which function(s) each
-- group of rows belongs to.
--
-- Numbering note: seed numbers 030-063 are reserved by other programme
-- items' own plans, and 064 was taken by #221/#273's avatar/icon seed
-- before this one was written. This file takes the next free number, 065.
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
-- @since      v1.2.0 — Phase 8 (CX-01, #218 Codex catch-up finding)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
-- _g2ml_linkspageManageValidateFields() — shared page-field validation.
('en-GB', 'linkspage.error.slug_reserved', 'That slug is reserved. Please choose a different one.', 'LinksPage create/edit — slug validation', 1),
('en-GB', 'linkspage.error.slug_invalid', 'Please enter a URL slug using only letters, numbers, hyphens, and underscores (1-100 characters).', 'LinksPage create/edit — slug validation', 1),
('en-GB', 'linkspage.error.page_title_required', 'Please enter a page title.', 'LinksPage create/edit — page title validation', 1),
('en-GB', 'linkspage.error.page_title_too_long', 'Page title must be 255 characters or fewer.', 'LinksPage create/edit — page title validation', 1),
('en-GB', 'linkspage.error.page_description_too_long', 'Page description must be {max} characters or fewer.', 'LinksPage create/edit — page description validation', 1),
('en-GB', 'linkspage.error.template_invalid', 'Please choose a valid template.', 'LinksPage create/edit — template validation', 1),
('en-GB', 'linkspage.error.theme_colour_invalid', 'Theme colour must be a hex value like #1E88E5.', 'LinksPage create/edit — theme colour validation', 1),
('en-GB', 'linkspage.error.background_colour_invalid', 'Background colour must be a hex value like #FFFFFF.', 'LinksPage create/edit — background colour validation', 1),
('en-GB', 'linkspage.error.font_family_invalid', 'Font family may only contain letters, numbers, spaces, commas, hyphens, and quotes.', 'LinksPage create/edit — font family validation', 1),
-- g2ml_linkspageManageCreatePage().
('en-GB', 'linkspage.error.page_limit_reached_with_limit', 'You have reached your plan''s LinksPage limit of {limit}. Please upgrade your plan to create more LinksPages.', 'LinksPage create — plan limit reached (known limit)', 1),
('en-GB', 'linkspage.error.page_limit_reached_no_limit', 'You have reached your plan''s LinksPage limit. Please upgrade your plan to create more LinksPages.', 'LinksPage create — plan limit reached (limit not reported)', 1),
-- Shared "slug taken" text (CreatePage, UpdatePage).
('en-GB', 'linkspage.error.slug_taken', 'That URL slug is already taken. Please choose a different one.', 'LinksPage create/edit — slug uniqueness (MySQL duplicate key)', 1),
-- g2ml_linkspageManageCreatePage() only.
('en-GB', 'linkspage.error.create_failed', 'Could not create the LinksPage. Please try again.', 'LinksPage create — database write failure', 1),
-- Shared "page not found" text (UpdatePage, SetPublished, AddItem, custom HTML save).
('en-GB', 'linkspage.error.page_not_found_edit', 'LinksPage not found, or you do not have permission to edit it.', 'LinksPage edit/publish/add-item/custom-HTML — ownership check', 1),
-- Shared "could not update the page" text (UpdatePage, SetPublished).
('en-GB', 'linkspage.error.update_failed', 'Could not update the LinksPage. Please try again.', 'LinksPage edit/publish — database write failure', 1),
-- g2ml_linkspageManageDeletePage().
('en-GB', 'linkspage.error.page_not_found_delete', 'LinksPage not found, or you do not have permission to delete it.', 'LinksPage delete — ownership check', 1),
-- g2ml_linkspageManageAddItem().
('en-GB', 'linkspage.error.items_max', 'This page already has the maximum of {max} links.', 'LinksPage add item — per-page abuse cap (#218)', 1),
('en-GB', 'linkspage.error.shorturl_required', 'Please choose one of your short URLs.', 'LinksPage add item — short URL source required', 1),
('en-GB', 'linkspage.error.shorturl_not_found', 'That short URL was not found, or you do not have permission to use it.', 'LinksPage add item — short URL ownership check', 1),
-- Shared "invalid manual URL" text (AddItem, UpdateItem).
('en-GB', 'linkspage.error.url_invalid', 'Please enter a valid http:// or https:// URL.', 'LinksPage add/edit item — manual URL validation', 1),
-- g2ml_linkspageManageAddItem() only.
('en-GB', 'linkspage.error.source_required', 'Please choose a link source.', 'LinksPage add item — link source required', 1),
-- Shared item title/description text (AddItem, UpdateItem).
('en-GB', 'linkspage.error.item_title_required', 'Please enter a title for this link.', 'LinksPage add/edit item — title validation', 1),
('en-GB', 'linkspage.error.item_title_too_long', 'The link title must be 255 characters or fewer.', 'LinksPage add/edit item — title validation', 1),
('en-GB', 'linkspage.error.item_description_too_long', 'The link description must be {max} characters or fewer.', 'LinksPage add/edit item — description validation', 1),
-- g2ml_linkspageManageAddItem() only.
('en-GB', 'linkspage.error.item_add_failed', 'Could not add the link. Please try again.', 'LinksPage add item — database write failure', 1),
-- Shared "link not found" text (UpdateItem, ToggleItemActive, MoveItem).
('en-GB', 'linkspage.error.item_not_found_edit', 'Link not found, or you do not have permission to edit it.', 'LinksPage edit/toggle/move item — ownership check', 1),
-- Shared "could not update the link" text (UpdateItem, ToggleItemActive).
('en-GB', 'linkspage.error.item_update_failed', 'Could not update the link. Please try again.', 'LinksPage edit/toggle item — database write failure', 1),
-- g2ml_linkspageManageDeleteItem().
('en-GB', 'linkspage.error.item_not_found_delete', 'Link not found, or you do not have permission to delete it.', 'LinksPage delete item — ownership check', 1),
-- g2ml_linkspageManageMoveItem().
('en-GB', 'linkspage.error.move_direction_invalid', 'Invalid move direction.', 'LinksPage reorder item — direction validation', 1),
('en-GB', 'linkspage.error.reorder_failed', 'Could not reorder the links. Please try again.', 'LinksPage reorder item — database write failure', 1),
-- _g2ml_linkspageManageStoreSanitisedCustom() (Component C.6, #49).
('en-GB', 'linkspage.error.custom_html_unavailable', 'Custom HTML is not available on your current plan, or has been disabled by the administrator.', 'LinksPage custom HTML — feature gate', 1),
('en-GB', 'linkspage.error.custom_html_too_large', 'The custom HTML is too large. Please keep it under {max} KB.', 'LinksPage custom HTML — size cap', 1),
('en-GB', 'linkspage.error.custom_css_too_large', 'The custom CSS is too large. Please keep it under {max} KB.', 'LinksPage custom HTML — CSS size cap', 1),
('en-GB', 'linkspage.error.custom_html_sanitiser_unavailable', 'The custom HTML editor is temporarily unavailable. Please try again later.', 'LinksPage custom HTML — sanitiser fail-closed', 1),
('en-GB', 'linkspage.error.custom_html_save_failed', 'Could not save the custom HTML. Please try again.', 'LinksPage custom HTML — database write failure', 1),
-- g2ml_linkspageManageSaveCustomHTMLFromUpload().
('en-GB', 'linkspage.error.upload_no_file', 'Please choose an HTML file to upload.', 'LinksPage custom HTML upload — no file chosen', 1),
('en-GB', 'linkspage.error.upload_incomplete', 'The file upload did not complete. Please try again.', 'LinksPage custom HTML upload — PHP upload error', 1),
('en-GB', 'linkspage.error.upload_size_range', 'The HTML file must be between 1 byte and {max} KB.', 'LinksPage custom HTML upload — size range', 1),
('en-GB', 'linkspage.error.upload_wrong_type', 'Only .html files are accepted.', 'LinksPage custom HTML upload — extension check', 1),
('en-GB', 'linkspage.error.upload_unreadable', 'The uploaded file could not be read. Please try again.', 'LinksPage custom HTML upload — file read failure', 1),
('en-GB', 'linkspage.error.upload_too_large', 'The HTML file is too large. Please keep it under {max} KB.', 'LinksPage custom HTML upload — final size check', 1);
