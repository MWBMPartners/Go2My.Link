-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — IP Geolocation Settings (#43)
-- ============================================================================
-- Both settings gate the geolocation feature and are OFF (or unset) by
-- default — the redirect hot path (web/G2My.Link/public_html/index.php) only
-- ever attempts a lookup when analytics.geolocation_enabled is explicitly
-- turned on AND the configured `.mmdb` database file exists and is readable
-- (g2ml_geolocationAvailable(), web/_functions/geolocation.php). Either
-- condition being false is a TOTAL no-op — no error, no extra query, every
-- tblActivityLog geo column stays NULL.
--
--   - analytics.geolocation_enabled — master on/off switch. Deliberately
--     defaults to '0': the `.mmdb` database is a licensed, deploy-time-only
--     artefact (see scripts/fetch-geoip.sh and
--     .github/workflows/sftp-deploy.yml) that is NOT committed to git, so a
--     fresh install or a deploy that has not yet run the GeoIP fetch step
--     must never attempt a lookup against a file that may not exist yet.
--     Turn this on manually (via the admin settings screen, or
--     setSetting('analytics.geolocation_enabled', '1', 'System')) only AFTER
--     confirming the database has actually been deployed.
--
--   - analytics.geoip_db_path — optional override for the `.mmdb` file's
--     filesystem path. Left UNSET here (NULL settingValue/settingDefault)
--     so getSetting()'s cascade falls through to
--     _g2ml_geoipDbPath()'s own portable, deployment-relative default
--     (web/_libraries/maxminddb/GeoLite2-Country.mmdb, resolved as an
--     ABSOLUTE path from geolocation.php's own directory) — see that
--     function's docblock. Only set this if the database is deployed
--     somewhere else on the server.
--
-- Uses ON DUPLICATE KEY UPDATE for idempotent re-runs.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (#43)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('analytics.geolocation_enabled', 'System', NULL,
 '0', '0', 'Whether IP-to-country geolocation is attempted on redirects. Requires a GeoLite2 .mmdb database to also be deployed (see scripts/fetch-geoip.sh) — this setting alone does not fetch or activate anything if the database file is absent. OFF by default.',
 'boolean', 0, 1),
('analytics.geoip_db_path', 'System', NULL,
 NULL, NULL, 'Optional absolute filesystem path override for the GeoIP .mmdb database. Leave unset to use the default location (web/_libraries/maxminddb/GeoLite2-Country.mmdb, resolved relative to the shared _functions directory).',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
