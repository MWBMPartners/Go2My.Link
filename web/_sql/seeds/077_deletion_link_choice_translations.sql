-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed: what happens to short links on account deletion (#246)
-- =============================================================================
-- Words for the choice on the Delete Account page (owner decisions D-017 and
-- D-021), the confirmation email line, the Privacy dashboard card and the
-- Privacy Policy retention table. The Privacy Policy and Delete Account wording
-- is NEW and needs the owner's legal review (PRE_LAUNCH_CHECKLIST.md A3, A32).
--
-- Changed wording uses new key names, so INSERT IGNORE is enough. The old keys
-- from seed 010 (delete.warning_links, privacy.delete_desc,
-- legal.privacy_s7_account_notes) stay in place but are no longer used.
--
-- Apply this file to an EXISTING database too. Without it those places show key
-- names instead of words. Safe to run more than once.
--
-- Dependencies: web/_sql/schema/035_translations.sql and
-- web/_sql/seeds/005_languages.sql (tblTranslations, en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#246)
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'delete.links_error_invalid', 'Please choose what should happen to your short links.', 'Delete account page', 1),
('en-GB', 'delete.links_help', 'One choice covers all of your links. It takes effect when your account is erased, not today, and any period is counted from that day. A link that already expires sooner keeps its own expiry date. A link that is switched off stays off.', 'Delete account page', 1),
('en-GB', 'delete.links_kept_note', 'Links you keep are disconnected from your account, so you cannot change them, switch them off or see their statistics afterwards. Their titles and private notes are removed. The destination addresses stay as you entered them, so check that they contain nothing personal. Our terms still apply, and a link that breaks them can still be switched off.', 'Delete account page', 1),
('en-GB', 'delete.links_legend', 'What should happen to your short links?', 'Delete account page', 1),
('en-GB', 'delete.links_option_1_year', 'Keep them working for 1 year', 'Delete account page', 1),
('en-GB', 'delete.links_option_30_days', 'Keep them working for 30 days', 'Delete account page', 1),
('en-GB', 'delete.links_option_90_days', 'Keep them working for 90 days', 'Delete account page', 1),
('en-GB', 'delete.links_option_forever', 'Keep them working for ever', 'Delete account page', 1),
('en-GB', 'delete.links_option_off', 'Switch them off (default)', 'Delete account page', 1),
('en-GB', 'delete.links_org_note', 'Links that belong to an organisation with other active members stay with that organisation. This choice does not change them; they are only disconnected from your account.', 'Delete account page', 1),
('en-GB', 'delete.links_pending_change', 'To change this choice, cancel the request and submit a new one. The grace period then starts again.', 'Delete account page', 1),
('en-GB', 'delete.links_summary_1_year', 'Your short links keep working for 1 year after your account is erased, then stop.', 'Delete account page and email', 1),
('en-GB', 'delete.links_summary_30_days', 'Your short links keep working for 30 days after your account is erased, then stop.', 'Delete account page and email', 1),
('en-GB', 'delete.links_summary_90_days', 'Your short links keep working for 90 days after your account is erased, then stop.', 'Delete account page and email', 1),
('en-GB', 'delete.links_summary_forever', 'Your short links keep working for ever. A link with its own expiry date still stops on that date.', 'Delete account page and email', 1),
('en-GB', 'delete.links_summary_off', 'Your short links are switched off when your account is erased.', 'Delete account page and email', 1),
('en-GB', 'delete.warning_links_choice', 'Your short links will be switched off when your account is erased, unless you choose below to keep them working.', 'Delete account page', 1),
('en-GB', 'privacy.delete_desc_links', 'You can ask for your account to be erased, and choose whether your short links are switched off or keep working. This cannot be undone after the grace period.', 'Privacy dashboard', 1),
('en-GB', 'legal.privacy_s7_account_notes_links', 'After you ask us to close your account there is a 30-day grace period in which you can cancel. We then erase your account by removing your name and sign-in details from it. What happens to your short links is described in the next row.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_urls_erasure', 'When your account is erased your links are switched off, unless you chose to keep them working for 30 days, 90 days, 1 year or for ever. A kept link is disconnected from your account and its title and notes are removed; its destination address is kept so that it can work. Links belonging to an organisation with other active members stay with that organisation.', 'Legal — Privacy Policy', 1);
