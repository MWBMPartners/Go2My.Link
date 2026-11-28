-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed: form rate-limit settings and their words
-- (#212)
-- =============================================================================
-- Three System settings hold the per-IP-per-hour limit for each of the three
-- forms web/_functions/form_rate_limit.php now protects (registration,
-- contact, password reset). Values match owner decision 43 on #243: 10 for
-- registration (offices and schools share one address, so it is set higher
-- than the others), 5 for contact and password reset. Setting any of them to
-- 0 switches that form's limit off entirely — see g2ml_formRateLimitTake()
-- in web/_functions/form_rate_limit.php, which treats 0 as "no limit" rather
-- than "block everything".
--
-- These settings use ON DUPLICATE KEY UPDATE on settingDescription ONLY (the
-- convention most settings seeds in this directory follow, for example seed
-- 017) — a value the owner has already changed through the admin screen is
-- never silently overwritten by re-running this file, only the stored
-- explanation of what the setting does.
--
-- The translation rows add the registration page's rate-limit message, the
-- "cannot process this form" message, and the Privacy Policy's words about the
-- attempt records (those need the owner's legal review). The forgot-password
-- and contact pages keep their existing rate-limit messages from seed 010.
--
-- This file MUST be applied to an EXISTING database too, not only a fresh
-- install (see PRE_LAUNCH_CHECKLIST.md). Without it, getSetting() falls back
-- to the hard-coded default each call site already passes as its own second
-- argument, so the limits still apply. The settings rows are missing until this
-- seed runs, and so are its six texts: the refusal messages and the Privacy
-- Policy then show key names instead of words.
--
-- Dependencies: web/_sql/schema/010_core_settings.sql (tblSettings),
-- web/_sql/schema/035_translations.sql + web/_sql/seeds/005_languages.sql
-- (tblTranslations, en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#212)
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('security.register_max_per_ip_per_hour', 'System', NULL,
 '10', '10', 'Most new accounts one IP address may create in an hour. Offices and schools share one address, so do not set this too low. 0 means no limit.',
 'integer', 0, 1),
('security.contact_max_per_ip_per_hour', 'System', NULL,
 '5', '5', 'Most contact-form messages one IP address may send in an hour. 0 means no limit.',
 'integer', 0, 1),
('security.password_reset_max_per_ip_per_hour', 'System', NULL,
 '5', '5', 'Most password-reset requests one IP address may make in an hour. 0 means no limit.',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'register.error_rate_limit', 'Too many sign-ups from your network in the last hour. Please try again later.', 'Registration page', 1),
('en-GB', 'form.error_unavailable', 'We cannot process this form just now. Please try again later.', 'Public forms', 1),
('en-GB', 'legal.privacy_s3_security_forms', 'Limiting how often the sign-up, contact and password-reset forms can be used from one connection, using a scrambled (keyed hash) form of your IP address', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_forms', 'Form attempt records', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_forms_period', 'About 1 day', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_forms_notes', 'Used only for 1 hour. Deleted after a day, when one of these forms is next used or when the scheduled clean-up runs.', 'Legal — Privacy Policy', 1);
