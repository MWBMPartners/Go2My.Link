-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Pricing Engine: Master Switch + Secondary Settings (all OFF)
-- =============================================================================
-- Requirement satisfied: "Everything additive and DISABLED by default (a
-- billing.pricing_engine_enabled style setting; no behaviour change until
-- switched on)." Idempotent inserts into the existing tblSettings table —
-- ON DUPLICATE KEY UPDATE refreshes descriptions only and NEVER clobbers an
-- operator's chosen settingValue (matches the seed/migration house pattern,
-- e.g. web/_sql/seeds/017_geolocation_settings.sql).
--
-- Every switch below seeds '0' (OFF) except the two currency/region defaults,
-- which are informational display defaults, not behaviour switches:
--
--   billing.pricing_engine_enabled   — MASTER switch. OFF (0) = entitlements.php
--     resolves tiers from the legacy tblSubscriptionTiers has*/max* columns
--     exactly as before this phase — web/_sql/schema/036_pricing_engine.sql's
--     tables are completely inert. ON (1) = web/_functions/pricing.php
--     resolves entitlements from tblFeatures + tblTierFeatures +
--     tblOrgFeatureOverrides (fail-open contract preserved throughout).
--   billing.payg_enabled             — pay-as-you-go / PAYG-capped plans.
--   billing.usage_metering_enabled   — writes to tblUsageCounters (can run ON
--     ahead of PAYG launch purely to gather baseline usage, no billing effect).
--   billing.usage_event_log_enabled  — the optional fine-grained tblUsageEvents
--     audit ledger, independent of counter metering.
--   billing.usage_event_retention_days — retention window for that ledger
--     (default 90; only meaningful once event logging is switched on).
--   billing.lifetime_plans_enabled   — offering planType='lifetime' price plans.
--   billing.coupon_stacking_enabled  — master permission for combining coupons
--     (per-coupon isStackable/stackingGroup still apply underneath).
--   billing.default_currency / billing.default_region — GBP/GB display
--     defaults for the price book; not behaviour switches, safe to seed a
--     real value rather than '0'.
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      v1.6.0 — Pricing Engine phase (scaffold)
--
-- 📖 References:
--     - Tables:          web/_sql/schema/036_pricing_engine.sql
--     - Feature/backfill: web/_sql/seeds/018_pricing_feature_registry.sql
--     - Resolver:         web/_functions/pricing.php
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('billing.pricing_engine_enabled', 'System', NULL,
 '0', '0', 'MASTER SWITCH for the flexible pricing/entitlement engine. OFF (0) = entitlements.php resolves tiers from the legacy tblSubscriptionTiers has*/max* columns exactly as before — the new tables are completely inert. ON (1) = web/_functions/pricing.php resolves entitlements from tblFeatures + tblTierFeatures + tblOrgFeatureOverrides (fail-open contract preserved).',
 'boolean', 0, 1),
('billing.payg_enabled', 'System', NULL,
 '0', '0', 'Enables pay-as-you-go and PAYG-capped price plans (planType payg / payg_capped). OFF by default — requires usage metering proven in production first.',
 'boolean', 0, 1),
('billing.usage_metering_enabled', 'System', NULL,
 '0', '0', 'Enables writing usage to tblUsageCounters (and, when event logging is also on, tblUsageEvents). Can run ON ahead of PAYG launch to gather baseline usage with no billing effect.',
 'boolean', 0, 1),
('billing.usage_event_log_enabled', 'System', NULL,
 '0', '0', 'Enables the fine-grained tblUsageEvents audit ledger in addition to counters. Independent of counter metering; counters alone are sufficient for quotas and PAYG maths.',
 'boolean', 0, 1),
('billing.usage_event_retention_days', 'System', NULL,
 '90', '90', 'How many days of tblUsageEvents rows to retain; older rows are swept by a probabilistic (1-in-100 request) prune, mirroring api.request_log_retention_days.',
 'integer', 0, 1),
('billing.lifetime_plans_enabled', 'System', NULL,
 '0', '0', 'Enables offering lifetime price plans (planType lifetime). OFF by default pending owner decision on founding-member deals.',
 'boolean', 0, 1),
('billing.coupon_stacking_enabled', 'System', NULL,
 '0', '0', 'Master permission for combining multiple coupons on one purchase (per-coupon isStackable/stackingGroup still apply). OFF = one coupon per purchase, full stop.',
 'boolean', 0, 1),
('billing.default_currency', 'System', NULL,
 'GBP', 'GBP', 'Default ISO 4217 currency for the price book and pricing page.',
 'string', 0, 1),
('billing.default_region', 'System', NULL,
 'GB', 'GB', 'Default region code used to select regional price-plan variants when no better match applies.',
 'string', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
