-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔎 Go2My.Link — Seed: the link check when a short link is followed (#308)
-- ============================================================================
--
-- When a short link is followed, the redirect page now runs the same link
-- check that runs when a link is created or edited. A failure is always
-- written to the server log. The visitor is refused ONLY when the setting
-- below is on. It is OFF by default, so visitors see no change until the
-- owner switches it on.
--
-- Turning it on also affects old links that were saved before the check
-- existed: a link whose host looks like a number, or that has an odd port,
-- stops redirecting. Read the log first.
--
-- Contents:
--   - the setting redirect.enforce_destination_check (System scope, off)
--   - three English texts:
--       redirect.destination_refused_title    page title on the refusal page
--       redirect.destination_refused_message  sentence on the refusal page
--       info.destination_refused              shown on the link info page
--         in place of the destination
--
-- ⚠️ Apply this file to EVERY existing database, not only a fresh install.
-- Until it is applied, the setting is missing (which counts as off) and the
-- three texts fall back to the English written in the code. It is safe to
-- re-run: the setting uses ON DUPLICATE KEY UPDATE (it keeps the value the
-- owner chose) and the texts are INSERT IGNORE.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      #308 (F1b)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('redirect.enforce_destination_check', 'System', NULL,
 '0', '0', 'When a short link is followed, refuse it (404 page) if its stored destination fails the link check, and say so on the link info page. OFF by default. The check always runs and a failure is always written to the server log as: [Go2My.Link] WARNING: short link CODE has a destination that fails the link check (REASON). With this setting off, visitors are sent on as before. Read the log before switching it on: old links with a number-like host or an odd port will stop redirecting.',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'redirect.destination_refused_title', 'Link Cannot Be Opened', 'Redirect error page', 1),
('en-GB', 'redirect.destination_refused_message', 'This short link''s destination address is not valid, so we cannot send you there.', 'Redirect error page', 1),
('en-GB', 'info.destination_refused', 'This link''s destination address is not valid, so the link will not open.', 'Link info page', 1);
