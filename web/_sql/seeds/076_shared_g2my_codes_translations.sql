-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔗 Go2My.Link — Seed: the shared g2my.link endings, explained (#289)
-- ============================================================================
--
-- Owner decision D-003: a member of an organisation with no short web
-- address of its own gets g2my.link links that work like anybody else's,
-- each ending on g2my.link is used once across everybody, and the
-- organisation still sees the link and its statistics. This file adds the
-- one paragraph on the Short Links help page (/help/short-links, section 3,
-- "Choosing your own ending") that says so.
--
-- Contents:
--   - help.short_links.s3_shared_codes  the new help paragraph
--
-- ⚠️ Apply this file to EVERY existing database, not only a fresh install.
-- Until it is applied, the help page shows the English written in the page
-- itself, so nothing breaks. It is safe to re-run: INSERT IGNORE keeps a row
-- that is already there.
--
-- Numbering: 075 is left free for other work in progress; this file takes
-- 076.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      #289 (owner decision D-003)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'help.short_links.s3_shared_codes', 'Each ending on g2my.link can be used only once, by anybody. That includes links made by members of an organisation: if your organisation does not have a short web address of its own, your links are g2my.link links like everybody else''s, and your organisation still sees them and their click statistics. If your organisation does have its own verified short web address, the links made there have their own separate set of endings, so an ending that is taken on g2my.link may still be free on your address.', 'Help — Short Links', 1);
