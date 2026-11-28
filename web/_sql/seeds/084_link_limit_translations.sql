-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: the limit on switched-on links (en-GB)
-- ============================================================================
--
-- Issue #327, decision D-024: the Free plan's link limit applies to people
-- without an organisation, and switching a link back on is refused at the
-- limit.
--
-- Checked against web/Go2My.Link/_functions/link_limit.php:
--   * "Switched on" is isActive = 1, so a link past its end date still counts.
--   * Links outside an organisation are counted per person. Links made on the
--     home page without an account have no owner and are not counted.
--   * An organisation's links are counted together.
--   * The messages take {limit} from the plan data. The pricing and help texts
--     say 50 (seed 001).
--
-- New key names are used, so an existing database picks up the new words
-- although this file never overwrites a row. pricing.free_limit_v3 and
-- help.short_links.s9_item6_v3 replace two texts of seed 082.
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install:
-- without it the pages show key names. It is INSERT IGNORE, so re-running it
-- is safe.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch (#327)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'pricing.free_limit_v3', 'You can create a link, or switch one back on, while fewer than 50 are switched on. A link past its end date still counts until it is switched off. Without an organisation the 50 are your own; in an organisation they are shared by all members.', 'Pricing page', 1),
('en-GB', 'help.short_links.s7_p3_v2', 'To turn a link back on, open it from the Links list and switch Active back on in the edit page. If you have reached your link limit (see Things that can go wrong below), your other changes are saved but the link stays off.', 'Help — Short Links', 1),
('en-GB', 'help.short_links.s9_item6_v3', '<strong>A message saying you have reached a link limit</strong> — a plan allows only so many switched-on links (50 on the Free plan). Links outside an organisation are counted for you alone, including links you make on the home page while signed in. An organisation''s links are shared by its members. A link past its end date still counts until it is turned off. At the limit you cannot create a link or switch one back on. Turn off links that are no longer needed to make room. Paid plans are coming and cannot be bought yet.', 'Help — Short Links', 1),
('en-GB', 'links.error_personal_limit', 'You have reached the Free plan''s limit of {limit} switched-on links of your own. To make room, switch off a link you no longer need (a link past its end date still counts until it is switched off). Paid plans are coming and cannot be bought yet.', 'Create link — personal limit reached', 1),
('en-GB', 'links.error_limit_busy', 'The link was not created: other links were being saved at the same moment. Please try again.', 'Create link — try again', 1),
('en-GB', 'edit_link.error_switch_on_limit_personal', 'Your other changes were saved, but the link was not switched on. You have reached the Free plan''s limit of {limit} switched-on links of your own. To make room, switch off a link you no longer need. Paid plans are coming and cannot be bought yet.', 'Edit link — personal limit reached', 1),
('en-GB', 'edit_link.error_switch_on_limit_org', 'Your other changes were saved, but the link was not switched on. Your organisation has reached its plan''s limit of {limit} switched-on links. To make room, switch off a link that is no longer needed. Paid plans are coming and cannot be bought yet.', 'Edit link — organisation limit reached', 1),
('en-GB', 'edit_link.error_switch_on_busy', 'Your other changes were saved, but the link was not switched on: other links were being saved at the same moment. Please try again.', 'Edit link — try again', 1);
