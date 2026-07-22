-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔗 Go2My.Link — UTM Capture + Forwarding Settings (#92)
-- ============================================================================
-- Both settings gate the two independent halves of #92 and are OFF by
-- default:
--
--   - redirect.forward_utm_params      — append the link's OWN configured
--     tblShortURLs.utm* values onto the destination URL, just before the
--     Location header is emitted. See
--     web/G2My.Link/_functions/redirect_resolver.php —
--     g2ml_appendUtmToDestination().
--
--   - analytics.capture_tracking_params — whitelist-extract tracking params
--     (utm_source/utm_medium/utm_campaign/utm_term/utm_content, gclid,
--     fbclid, msclkid) from the INCOMING (untrusted) visit query string and
--     store them in tblActivityLog.logData. See
--     web/G2My.Link/_functions/redirect_resolver.php —
--     g2ml_extractTrackingParams().
--
-- Both are read via a single cached getSetting() boolean check on the
-- redirect hot path — no additional database query is added either way, and
-- the entire redirect is byte-for-byte unchanged while both remain off.
--
-- Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (#92)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('redirect.forward_utm_params', 'System', NULL,
 '0', '0', 'Append the short link''s own configured UTM values (tblShortURLs.utmSource/utmMedium/utmCampaign/utmTerm/utmContent) onto the destination URL''s query string before redirecting. A value already present on the destination is overridden by the link''s configured value. OFF by default.',
 'boolean', 0, 1),
('analytics.capture_tracking_params', 'System', NULL,
 '0', '0', 'Whitelist-extract tracking query params from the incoming visit (utm_source, utm_medium, utm_campaign, utm_term, utm_content, gclid, fbclid, msclkid) and store them in tblActivityLog.logData for the redirect. Untrusted input — capped length, whitelisted keys only, no other query params are ever captured. OFF by default.',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
