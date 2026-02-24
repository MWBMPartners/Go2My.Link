-- ============================================================================
-- 📧 Go2My.Link — Email & Security Settings (Phase 7)
-- ============================================================================
--
-- New settings for:
--   1. Multipart MIME email support (AMP, plaintext fallback)
--   2. Modern email headers (List-Unsubscribe, preheader)
--   3. Mass credential reset / breach response controls
--
-- Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      Phase 7
-- ============================================================================

USE `mwtools_Go2MyLink`;

-- ============================================================================
-- 📧 Email Feature Settings
-- ============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('email.amp_enabled', 'System', NULL,
 '0', '0', 'Enable AMP for Email MIME part (text/x-amp-html) in outgoing emails. Requires Google sender registration for AMP to render in Gmail.',
 'boolean', 0, 1),
('email.plaintext_fallback', 'System', NULL,
 '1', '1', 'Include an auto-generated text/plain MIME part in all outgoing emails for clients that do not render HTML.',
 'boolean', 0, 1),
('email.list_unsubscribe_url', 'System', NULL,
 '', '', 'URL for the List-Unsubscribe email header. Leave empty to omit the header. Should point to a one-click unsubscribe endpoint.',
 'url', 0, 1),
('email.preheader_enabled', 'System', NULL,
 '1', '1', 'Include hidden preheader/preview text in HTML email templates for improved inbox previews.',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);

-- ============================================================================
-- 🛡️ Breach Response / Mass Credential Reset Settings
-- ============================================================================

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('security.breach_response_enabled', 'System', NULL,
 '1', '1', 'Allow GlobalAdmin to trigger a mass credential reset (breach response) from the admin dashboard.',
 'boolean', 0, 1),
('security.breach_email_batch_size', 'System', NULL,
 '50', '50', 'Number of users processed per batch when sending mass breach notification emails. Lower values reduce server load.',
 'integer', 0, 1),
('security.breach_response_cooldown', 'System', NULL,
 '3600', '3600', 'Minimum number of seconds that must elapse between consecutive breach response triggers. Prevents accidental double-activation.',
 'integer', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
