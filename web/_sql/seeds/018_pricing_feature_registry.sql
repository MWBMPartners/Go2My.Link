-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Pricing Engine: Feature Registry Seed + Legacy Backfill
-- =============================================================================
-- MIGRATION PATH FROM THE FIXED COLUMNS (full plan in the pricing-engine
-- scaffold build plan, kept alongside Pricing_Strategy.md):
--
--   STEP 1 (THIS FILE, automatic): seed tblFeatures — one row per legacy
--     tblSubscriptionTiers column (legacyColumn set) plus the new granular
--     features — then copy every tier's current column values into
--     tblTierFeatures via idempotent INSERT…SELECTs. Legacy NULL (=unlimited)
--     becomes isUnlimited=1. Booleans copy verbatim. After this file runs, the
--     new model REPRODUCES the legacy model exactly; nothing reads it yet
--     (billing.pricing_engine_enabled is still '0' — seeded by
--     019_pricing_settings.sql).
--
--   STEP 2 (operator, later): flip billing.pricing_engine_enabled to '1'.
--     entitlements.php now resolves through web/_functions/pricing.php (merge
--     order: feature safe-floor default → tier value → org override). Because
--     STEP 1 copied values verbatim, resolution output is IDENTICAL — flip is
--     a no-op until tier/feature data is deliberately changed. Flip back to
--     '0' at any time (instant rollback; legacy columns were never modified).
--
--   STEP 3 (cleanup, MUCH later, separate sign-off): once the engine has been
--     ON and stable for a full release cycle, the legacy has*/max* columns can
--     be retired — recommended END STATE is to KEEP the columns frozen (cheap,
--     zero risk, still additive) or replace reads with
--     vwSubscriptionTierEntitlements (schema 036); physically dropping columns
--     would be a destructive change and is deliberately NOT part of this
--     proposal.
--
-- This file requires web/_sql/schema/036_pricing_engine.sql (tblFeatures,
-- tblTierFeatures) and web/_sql/seeds/001_subscription_tiers.sql (the tier
-- rows being backfilled from) to have already run — both are earlier in
-- filename sort order, so a fresh install picks this up automatically.
--
-- Every INSERT below is idempotent (unique keys + ON DUPLICATE KEY no-op),
-- so re-running is safe and never overwrites an operator's later edits —
-- the same content is reused verbatim by
-- web/_sql/migrations/020_pricing_engine.sql for already-deployed databases.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.6.0 — Pricing Engine phase (scaffold)
--
-- 📖 References:
--     - Tables:        web/_sql/schema/036_pricing_engine.sql
--     - Engine switch:  web/_sql/seeds/019_pricing_settings.sql
--     - Legacy tiers:   web/_sql/schema/011_core_subscription_tiers.sql
--     - Tier seed:      web/_sql/seeds/001_subscription_tiers.sql
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Registry rows replacing the legacy hard-coded columns (legacyColumn set)
-- -----------------------------------------------------------------------------
INSERT INTO `tblFeatures` (
    `featureSlug`, `featureName`, `featureDescription`,
    `valueType`, `valueUnit`, `quotaPeriod`,
    `defaultValueBoolean`, `defaultValueInt`, `defaultIsUnlimited`,
    `category`, `isMeterable`, `legacyColumn`, `sortOrder`, `isActive`
) VALUES
('links.max', 'Active short links', 'Maximum simultaneously active short URLs for the organisation.',
 'limit', 'links', NULL, NULL, 0, 0, 'links', 1, 'maxLinks', 10, 1),
('domains.custom_max', 'Custom short domains', 'Maximum ownership-verified custom short domains.',
 'limit', 'domains', NULL, NULL, 0, 0, 'domains', 0, 'maxCustomDomains', 10, 1),
('api.requests_per_day', 'API requests per day', 'Daily API request allowance across the organisation''s keys.',
 'quota', 'requests', 'day', NULL, 0, 0, 'api', 1, 'maxAPIRequestsPerDay', 20, 1),
('linkspage.pages_max', 'LinksPages', 'Maximum LinksPage (link-in-bio) pages.',
 'limit', 'pages', NULL, NULL, 0, 0, 'linkspage', 0, 'maxLinksPages', 10, 1),
('redirects.advanced', 'Advanced redirects (umbrella)', 'Legacy umbrella flag for scheduled/device/geo/age-gate redirects. Superseded by the granular redirects.* flags; resolver reports it ON when ANY granular redirect flag is ON.',
 'boolean', NULL, NULL, 0, NULL, 0, 'redirects', 0, 'hasAdvancedRedirects', 5, 1),
('analytics.enabled', 'Analytics dashboard', 'Org-scoped click/human/bot/unique-IP analytics dashboard.',
 'boolean', NULL, NULL, 0, NULL, 0, 'analytics', 0, 'hasAnalytics', 10, 1),
('qr.dynamic', 'Dynamic QR codes', 'CueRCode dynamic-QR integration (re-pointable codes).',
 'boolean', NULL, NULL, 0, NULL, 0, 'qr', 0, 'hasQRCodes', 10, 1),
('api.access', 'API access', 'Public API v1 access (key auth, scopes).',
 'boolean', NULL, NULL, 0, NULL, 0, 'api', 0, 'hasAPIAccess', 10, 1),
('support.priority', 'Priority support', 'Priority support queue.',
 'boolean', NULL, NULL, 0, NULL, 0, 'support', 0, 'hasPrioritySupport', 10, 1),
('linkspage.custom_html', 'LinksPage custom HTML/CSS', 'High-risk premium feature: free-form custom HTML/CSS on LinksPages (Component C.6, #49). Also governed by the linkspage.custom_html_enabled kill-switch.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, 'hasCustomHTML', 40, 1)
ON DUPLICATE KEY UPDATE
    `featureDescription` = VALUES(`featureDescription`);

-- -----------------------------------------------------------------------------
-- 2. NEW granular registry rows (no legacy column; enforcement arrives with
--    the engine). Adding these here proves the core claim: features are rows,
--    not schema.
-- -----------------------------------------------------------------------------
INSERT INTO `tblFeatures` (
    `featureSlug`, `featureName`, `featureDescription`,
    `valueType`, `valueUnit`, `quotaPeriod`,
    `defaultValueBoolean`, `defaultValueInt`, `defaultIsUnlimited`,
    `category`, `isMeterable`, `legacyColumn`, `sortOrder`, `isActive`
) VALUES
('links.custom_alias', 'Custom aliases', 'Choose custom short-link aliases.',
 'boolean', NULL, NULL, 1, NULL, 0, 'links', 0, NULL, 20, 1),
('redirects.scheduled', 'Scheduled redirects', 'Time-window destination switching (schema 021).',
 'boolean', NULL, NULL, 0, NULL, 0, 'redirects', 0, NULL, 10, 1),
('redirects.device', 'Device-based redirects', 'Destination by device class (schema 021).',
 'boolean', NULL, NULL, 0, NULL, 0, 'redirects', 0, NULL, 20, 1),
('redirects.geo', 'Geo-based redirects', 'Destination by visitor country (schema 021).',
 'boolean', NULL, NULL, 0, NULL, 0, 'redirects', 0, NULL, 30, 1),
('redirects.agegate', 'Age-gate redirects', 'Age-verification interstitial before redirect (schema 021).',
 'boolean', NULL, NULL, 0, NULL, 0, 'redirects', 0, NULL, 40, 1),
('analytics.retention_days', 'Analytics retention', 'How many days of analytics history are visible.',
 'limit', 'days', NULL, NULL, 30, 0, 'analytics', 0, NULL, 20, 1),
('analytics.export_csv', 'CSV export', 'Analytics CSV export.',
 'boolean', NULL, NULL, 0, NULL, 0, 'analytics', 0, NULL, 30, 1),
('analytics.geo_country', 'Country geolocation', 'Country-level click geolocation (MaxMind, gated).',
 'boolean', NULL, NULL, 0, NULL, 0, 'analytics', 0, NULL, 40, 1),
('api.bulk_endpoints', 'Bulk API endpoints', 'Bulk create/update API endpoints.',
 'boolean', NULL, NULL, 0, NULL, 0, 'api', 0, NULL, 30, 1),
('qr.scan_attribution', 'QR scan attribution', 'Scan-source attribution analytics for dynamic QR codes.',
 'boolean', NULL, NULL, 0, NULL, 0, 'qr', 0, NULL, 20, 1),
('linkspage.all_templates', 'All LinksPage templates', 'Access to the full system template library (vs starter subset).',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 20, 1),
('linkspage.custom_domain', 'LinksPage custom domain', 'Serve a LinksPage on a verified custom domain.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 30, 1),
('linkspage.agegate', 'LinksPage age verification', 'Age-verification gate on LinksPages.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 50, 1),
('org.seats', 'Team seats', 'Maximum users per organisation.',
 'limit', 'seats', NULL, NULL, 1, 0, 'org', 0, NULL, 10, 1),
('clicks.tracked_per_month', 'Tracked clicks per month', 'Metering dimension for click-based PAYG (not gated at launch).',
 'quota', 'clicks', 'month', NULL, NULL, 1, 'analytics', 1, NULL, 50, 1),
('qr.scans_per_month', 'QR scans per month', 'Metering dimension for scan-based PAYG (not gated at launch).',
 'quota', 'scans', 'month', NULL, NULL, 1, 'qr', 1, NULL, 30, 1)
ON DUPLICATE KEY UPDATE
    `featureDescription` = VALUES(`featureDescription`);

-- -----------------------------------------------------------------------------
-- 3. Backfill tblTierFeatures from the legacy hard-coded columns.
-- One INSERT…SELECT per legacy column: every ROW of tblSubscriptionTiers ×
-- that column becomes one tier-feature row. Idempotent via the UQ_tierfeature
-- unique key + ON DUPLICATE KEY no-op (an operator's later edits are never
-- overwritten by a re-run). Legacy NULL on max* columns = unlimited →
-- isUnlimited=1 with valueInt NULL, preserving exact semantics.
-- -----------------------------------------------------------------------------

-- maxLinks → links.max
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxLinks`,
       CASE WHEN t.`maxLinks` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxLinks'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- maxCustomDomains → domains.custom_max
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxCustomDomains`,
       CASE WHEN t.`maxCustomDomains` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxCustomDomains'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- maxAPIRequestsPerDay → api.requests_per_day
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxAPIRequestsPerDay`,
       CASE WHEN t.`maxAPIRequestsPerDay` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxAPIRequestsPerDay'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- maxLinksPages → linkspage.pages_max
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxLinksPages`,
       CASE WHEN t.`maxLinksPages` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxLinksPages'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasAdvancedRedirects → redirects.advanced (umbrella)
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAdvancedRedirects`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAdvancedRedirects'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasAnalytics → analytics.enabled
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAnalytics`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAnalytics'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasQRCodes → qr.dynamic
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasQRCodes`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasQRCodes'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasAPIAccess → api.access
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAPIAccess`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAPIAccess'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasPrioritySupport → support.priority
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasPrioritySupport`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasPrioritySupport'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- hasCustomHTML → linkspage.custom_html
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasCustomHTML`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasCustomHTML'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;
