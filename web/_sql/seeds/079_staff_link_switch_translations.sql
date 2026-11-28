-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed: the staff screen for switching short links off (#325)
-- =============================================================================
-- Words for the staff "Find a Short Link" page (owner decisions D-023 and
-- D-026), the notices a link's owner sees when staff have switched it off, the
-- information page's blocked-link wording (D-027), and the changed
-- visitor and help wording, which no longer says a disabled link was disabled
-- by its owner.
--
-- Changed wording uses a new key name, so INSERT IGNORE is enough. The old key
-- help.short_links.s7_p2 from seed 022 stays in place but is no longer used.
--
-- Apply this file to an EXISTING database too. Without it the redirect error
-- page, the information page, the help page, the menu and My Links can show key names instead of
-- words. Safe to run more than once.
--
-- Dependencies: web/_sql/schema/035_translations.sql and
-- web/_sql/seeds/005_languages.sql (tblTranslations, en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#325)
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'edit_link.staff_disabled_notice', 'Go2My.Link staff have switched this link off, so it does not redirect. You cannot switch it back on. If you think this is a mistake, please contact us.', 'Edit link', 1),
('en-GB', 'help.short_links.s7_p2_v2', 'While a link is switched off, anyone who visits its short address sees a page saying the link has been disabled — this is a different message from the one shown for a link that simply has not started yet or has already ended (see Start and end dates above). Go2My.Link staff can also switch a link off. If they do, your dashboard says so and you cannot switch it back on. If staff remove the block, the link goes back to how it was before, so a link you had switched off stays off.', 'Help — Short Links', 1),
('en-GB', 'info.staff_blocked_destination', 'Go2My.Link staff have switched this link off, so its destination is not shown.', 'Link info page', 1),
('en-GB', 'info.status_staff_blocked', 'Switched off by staff', 'Link info page', 1),
('en-GB', 'links.status_staff_disabled', 'Switched off by staff', 'My Links', 1),
('en-GB', 'nav.staff_links', 'Staff: find a link', 'Navigation', 1),
('en-GB', 'redirect.link_disabled_message', 'This short link has been disabled.', 'Redirect error page', 1),
('en-GB', 'staff_links.after_unblock_off', 'The link stays switched off', 'Staff — find a link', 1),
('en-GB', 'staff_links.after_unblock_on', 'The link is switched on again', 'Staff — find a link', 1),
('en-GB', 'staff_links.button_off', 'Switch off', 'Staff — find a link', 1),
('en-GB', 'staff_links.button_unblock', 'Remove staff block', 'Staff — find a link', 1),
('en-GB', 'staff_links.cancel', 'Cancel', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_off_button', 'Yes, switch it off', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_off_heading', 'Switch this link off?', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_off_text', 'The short address stops redirecting, and so does any short code that forwards to it. The owner cannot switch it back on.', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_unblock_button', 'Yes, remove the block', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_unblock_heading', 'Remove the staff block?', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_unblock_off_text', 'The link goes back to how it was before the block: switched off. It does not start redirecting. Its owner, if it has one, can then switch it on.', 'Staff — find a link', 1),
('en-GB', 'staff_links.confirm_unblock_on_text', 'The link goes back to how it was before the block: switched on. The short address starts redirecting to the destination above again, if its start and end dates allow.', 'Staff — find a link', 1),
('en-GB', 'staff_links.description', 'Staff only: find any short link, switch it off, or remove a staff block.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_failed', 'The link could not be changed. Please try again.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_no_change', 'Nothing changed: the link was already in that state.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_not_found', 'That link no longer exists.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_search_failed', 'The search could not be run. Please try again.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_search_invalid', 'Enter one short code, such as abc123, or one full short address, such as g2my.link/abc123.', 'Staff — find a link', 1),
('en-GB', 'staff_links.error_session', 'Your session has expired. Please try again.', 'Staff — find a link', 1),
('en-GB', 'staff_links.intro', 'Staff only. Searches the links of every person and organisation.', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_after_unblock', 'If the block is removed', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_alias', 'Forwards to the code', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_clicks', 'Clicks', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_created', 'Created', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_destination', 'Destination', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_disabled_at', 'Switched off by staff at (UTC)', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_disabled_by', 'Staff member', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_organisation', 'Organisation', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_owner', 'Owner', 'Staff — find a link', 1),
('en-GB', 'staff_links.label_reason', 'Reason', 'Staff — find a link', 1),
('en-GB', 'staff_links.narrowed', 'That address belongs to the organisation {org}, so only its link is shown. Search for the code on its own to see every organisation.', 'Staff — find a link', 1),
('en-GB', 'staff_links.no_destination', 'None recorded', 'Staff — find a link', 1),
('en-GB', 'staff_links.no_owner', 'No owner', 'Staff — find a link', 1),
('en-GB', 'staff_links.no_results', 'No link has that code. Check the spelling, or search for the code on its own.', 'Staff — find a link', 1),
('en-GB', 'staff_links.reason_help', 'Recorded in the activity log with your account. The owner is not shown it.', 'Staff — find a link', 1),
('en-GB', 'staff_links.reason_label', 'Reason (optional)', 'Staff — find a link', 1),
('en-GB', 'staff_links.result_count', 'Links found: {count}', 'Staff — find a link', 1),
('en-GB', 'staff_links.search_button', 'Find link', 'Staff — find a link', 1),
('en-GB', 'staff_links.search_help', 'For example abc123 or https://g2my.link/abc123.', 'Staff — find a link', 1),
('en-GB', 'staff_links.search_label', 'Short code or full short address', 'Staff — find a link', 1),
('en-GB', 'staff_links.status_ended', 'Switched on, but its end date has passed', 'Staff — find a link', 1),
('en-GB', 'staff_links.status_off', 'Switched off (not by staff)', 'Staff — find a link', 1),
('en-GB', 'staff_links.status_on', 'Switched on', 'Staff — find a link', 1),
('en-GB', 'staff_links.status_scheduled', 'Switched on, not started yet', 'Staff — find a link', 1),
('en-GB', 'staff_links.status_staff_off', 'Switched off by staff', 'Staff — find a link', 1),
('en-GB', 'staff_links.success_off', 'The link is switched off. Its owner cannot switch it back on.', 'Staff — find a link', 1),
('en-GB', 'staff_links.success_unblock_off', 'The block is removed. The link stays switched off, as it was before the block or as it was switched off since.', 'Staff — find a link', 1),
('en-GB', 'staff_links.success_unblock_on', 'The block is removed. The link is switched on again, as it was before. Its start and end dates still apply.', 'Staff — find a link', 1),
('en-GB', 'staff_links.title', 'Find a Short Link', 'Staff — find a link', 1),
('en-GB', 'staff_links.warning_not_logged', 'The change was made, but it could not be written to the activity log. Tell whoever looks after the server.', 'Staff — find a link', 1);
