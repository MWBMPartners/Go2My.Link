-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed: API access depends on the plan (#328)
-- =============================================================================
-- Words for owner decision D-025: the message the API and the API Keys page
-- give when the plan has no API access (or the plan cannot be read), the API
-- Keys page's notice and empty state, the status of a key whose owner has left
-- the organisation or is no longer an administrator (D-033), the refusal to
-- make a key for such a person, and two paragraphs on the API help page.
--
-- None of them offers an upgrade, because none can be bought yet.
--
-- Apply this file to an EXISTING database too. Without it the API Keys page and
-- the API help page show key names instead of words (the API's own messages
-- fall back to English). Safe to run more than once.
--
-- Dependencies: web/_sql/schema/035_translations.sql and
-- web/_sql/seeds/005_languages.sql (tblTranslations, en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#328)
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'api.error_plan_check_failed', 'We could not check whether the plan includes API access. Please try again shortly.', 'API', 1),
('en-GB', 'api.error_plan_not_included', 'The current plan does not include API access, so API keys cannot be created or used.', 'API', 1),
('en-GB', 'apikeys.empty_next_step', 'Create your first key', 'API keys page', 1),
('en-GB', 'apikeys.error_not_admin', 'Only an administrator of the organisation can create API keys.', 'API keys page', 1),
('en-GB', 'apikeys.plan_check_heading', 'We could not check your plan', 'API keys page', 1),
('en-GB', 'apikeys.plan_check_retry', 'Try again', 'API keys page', 1),
('en-GB', 'apikeys.plan_contact_button', 'Contact us', 'API keys page', 1),
('en-GB', 'apikeys.plan_heading', 'API access is not part of your plan', 'API keys page', 1),
('en-GB', 'apikeys.plan_links_button', 'Go to My Links', 'API keys page', 1),
('en-GB', 'apikeys.plan_message', 'Your organisation''s current plan does not include API access. New keys cannot be created, and the API refuses any key made earlier.', 'API keys page', 1),
('en-GB', 'apikeys.plan_next_step', 'You can still create and manage your short links here in the dashboard. If you rely on an existing key, please contact us.', 'API keys page', 1),
('en-GB', 'apikeys.status_not_checked', 'Not checked', 'API keys page', 1),
('en-GB', 'apikeys.status_owner_not_admin', 'Owner is not an administrator', 'API keys page', 1),
('en-GB', 'apikeys.status_owner_left', 'Owner has left', 'API keys page', 1),
('en-GB', 'apikeys.status_not_in_plan', 'Not in plan', 'API keys page', 1),
('en-GB', 'help.api.s3_admin', 'A key works only while the person who made it is still an administrator of the organisation. If they stop being one, or leave, their keys are refused, and work again if the role is given back. Keys made by a platform administrator are not affected.', 'Help — API', 1),
('en-GB', 'help.api.s3_plan', 'Not every plan includes API access. The Free plan does not, and people who are not in an organisation count as being on the Free plan. Without API access you cannot create a key, and the API refuses any key made earlier, with a message saying so. Your short links and the dashboard are not affected.', 'Help — API', 1),
('en-GB', 'org.members_error_global_admin_role', 'A global administrator''s role cannot be changed here.', 'Organisation members', 1),
('en-GB', 'org.members_error_role_unchanged', 'The role was not changed: this person may still hold another account type that gives them a different role.', 'Organisation members', 1);
