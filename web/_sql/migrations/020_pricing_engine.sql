-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 020: Flexible Pricing & Entitlement Engine (scaffold)
-- =============================================================================
-- Brings an ALREADY-IMPORTED database up to date with the additive pricing
-- engine scaffold: the 10 new tables + verification view from
-- web/_sql/schema/036_pricing_engine.sql, the feature-registry seed + legacy
-- backfill from web/_sql/seeds/018_pricing_feature_registry.sql, and the nine
-- billing.* engine-switch settings (ALL seeded OFF/'0') from
-- web/_sql/seeds/019_pricing_settings.sql — reproduced here VERBATIM as
-- CREATE TABLE IF NOT EXISTS + idempotent INSERTs, so running this file is
-- byte-for-byte equivalent to those three files having been present at
-- install time.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: schema 036 + seeds 018/019 are
--     already part of the base install (the web installer globs
--     web/_sql/schema/ and web/_sql/seeds/ in filename-sort order, so a fresh
--     database already has every table and seed row below). Run this
--     migration ONLY to bring an ALREADY-IMPORTED earlier schema (one deployed
--     before this pricing-engine phase existed) up to date.
--
-- NO ALTERs, NO information_schema GUARD PROCEDURES NEEDED. Every statement
-- below is either `CREATE TABLE IF NOT EXISTS`, `CREATE OR REPLACE VIEW`, or
-- an idempotent `INSERT … ON DUPLICATE KEY UPDATE` / `INSERT … SELECT …
-- ON DUPLICATE KEY UPDATE`. Unlike migration 019 (which needed a collapse-
-- then-constrain ALTER sequence), this migration only ADDS brand-new objects,
-- so there is nothing to guard against a partial prior run — re-running this
-- entire file is always safe.
--
-- NO ZERO-DATE GUARDS NEEDED. No column here is populated from a legacy DATE/
-- DATETIME column that might hold MySQL's zero-date sentinel ('0000-00-00') —
-- every DATETIME column added below is either DEFAULT NULL or
-- DEFAULT CURRENT_TIMESTAMP, so the zero-date guard pattern used by other
-- migrations in this directory does not apply here.
--
-- ORDER MATTERS — TABLES, THEN THE VIEW, THEN SEED DATA. Tables are created in
-- FK dependency order (features → price plans → tier features → org overrides
-- → coupons → coupon redemptions → subscription plans → usage counters →
-- usage credits → usage events), matching web/_sql/schema/036_pricing_engine.sql
-- exactly. The verification view is created after all ten tables exist. The
-- feature-registry seed runs before the legacy backfill (the backfill's
-- INSERT…SELECT joins against the just-seeded tblFeatures.legacyColumn), and
-- both run before the settings block (unrelated tables, but kept in the same
-- relative order as the fresh-install file-sort sequence for clarity).
--
-- ZERO BEHAVIOUR CHANGE. Every billing.* setting below seeds to '0' (except
-- the two currency/region display defaults). Until an operator flips
-- billing.pricing_engine_enabled to '1', web/_functions/entitlements.php
-- resolves tiers exactly as it did before this migration ran.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.6.0 — Pricing Engine phase (scaffold)
--
-- 📖 References:
--     - Schema (fresh-install source of truth): web/_sql/schema/036_pricing_engine.sql
--     - Feature/backfill seed:                  web/_sql/seeds/018_pricing_feature_registry.sql
--     - Engine-switch seed:                     web/_sql/seeds/019_pricing_settings.sql
--     - Resolver:                               web/_functions/pricing.php
--     - Migration-header wording precedent:      web/_sql/migrations/016_linkspage_custom_html.sql
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- PART 1 — TABLES (verbatim from web/_sql/schema/036_pricing_engine.sql).
-- See that file for the full per-table design rationale; only short pointer
-- comments are kept here to avoid duplicating hundreds of lines of prose.
-- =============================================================================

-- 1. tblFeatures — the feature registry (see schema 036 §1)
CREATE TABLE IF NOT EXISTS `tblFeatures` (
    `featureUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `featureSlug`           VARCHAR(100)        NOT NULL
        COMMENT 'Stable dot-namespaced identifier (e.g. links.max, redirects.geo, api.requests_per_day). NEVER renamed once live — display text changes go in featureName.',
    `featureName`           VARCHAR(150)        NOT NULL
        COMMENT 'Human-readable name for admin UI / pricing page rows',
    `featureDescription`    TEXT                DEFAULT NULL
        COMMENT 'What this feature gates, for admin UI and pricing-page tooltips',
    `valueType`             ENUM('boolean', 'limit', 'quota', 'enum', 'config')
                            NOT NULL DEFAULT 'boolean'
        COMMENT 'How entitlement values for this feature are typed and enforced',
    `valueUnit`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'Display/metering unit for limit/quota types (e.g. links, domains, requests, days)',
    `quotaPeriod`           ENUM('day', 'month', 'billing_period')
                            DEFAULT NULL
        COMMENT 'For valueType=quota only: the window the allowance renews over (NULL otherwise)',
    `allowedValues`         TEXT                DEFAULT NULL
        COMMENT 'For valueType=enum only: newline-separated permitted values; NULL = not applicable',
    `defaultValueBoolean`   TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Safe-floor default for boolean features when a tier has no row (normally 0 = off)',
    `defaultValueInt`       BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Safe-floor default for limit/quota features when a tier has no row (normally 0)',
    `defaultValueString`    VARCHAR(255)        DEFAULT NULL
        COMMENT 'Safe-floor default for enum features when a tier has no row',
    `defaultValueJSON`      JSON                DEFAULT NULL
        COMMENT 'Safe-floor default for config features when a tier has no row',
    `defaultIsUnlimited`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the safe-floor default is "unlimited" (1) — kept EXPLICIT rather than overloading NULL',
    `category`              VARCHAR(50)         NOT NULL DEFAULT 'general'
        COMMENT 'Grouping for admin UI / pricing matrix (links, domains, redirects, analytics, api, qr, linkspage, org, support)',
    `isMeterable`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this feature may serve as a PAYG metering dimension',
    `legacyColumn`          VARCHAR(50)         DEFAULT NULL
        COMMENT 'The hard-coded tblSubscriptionTiers column this row replaces; drives the backfill and legacy-shape output; NULL for new granular features',
    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display order within category on pricing matrix / admin UI',
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this feature participates in resolution (0 = parked; resolver ignores it entirely)',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`featureUID`),
    UNIQUE KEY `UQ_feature_slug` (`featureSlug`),
    UNIQUE KEY `UQ_feature_legacy_column` (`legacyColumn`),
    INDEX `IDX_feature_category` (`category`, `sortOrder`),
    INDEX `IDX_feature_active` (`isActive`),
    INDEX `IDX_feature_meterable` (`isMeterable`, `isActive`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Feature registry — every gateable capability is one row; adding a feature never needs a schema change';

-- 2. tblPricePlans — the flexible price book (see schema 036 §2)
CREATE TABLE IF NOT EXISTS `tblPricePlans` (
    `planUID`               BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `planSlug`              VARCHAR(100)        NOT NULL
        COMMENT 'Stable identifier (e.g. pro-monthly-gbp, pro-lifetime-founder, payg-capped-pro-gbp)',
    `planName`              VARCHAR(150)        NOT NULL
        COMMENT 'Display name (e.g. "Pro — Monthly", "Founding Member Lifetime")',
    `planDescription`       TEXT                DEFAULT NULL
        COMMENT 'Marketing/admin description of this price plan',
    `tierID`                VARCHAR(50)         DEFAULT NULL
        COMMENT 'FK to tblSubscriptionTiers.tierID — the tier this plan sells. NULL for tier-less plans (addon, credit_pack, one_off)',
    `planType`              ENUM('recurring', 'lifetime', 'one_off', 'payg', 'payg_capped', 'addon', 'credit_pack')
                            NOT NULL DEFAULT 'recurring'
        COMMENT 'Price structure (see schema 036 §2 for full semantics)',
    `billingInterval`       ENUM('month', 'year')
                            DEFAULT NULL
        COMMENT 'Recurring interval unit; NULL for lifetime/one_off/credit_pack. PAYG plans bill monthly in arrears',
    `intervalCount`         TINYINT UNSIGNED    NOT NULL DEFAULT 1
        COMMENT 'Multiplier on billingInterval (quarterly = month × 3); ignored when billingInterval is NULL',
    `amount`                DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Flat price per interval (or once, for lifetime/one_off/credit_pack) in `currency`. 0.00 for pure PAYG',
    `currency`              CHAR(3)             NOT NULL DEFAULT 'GBP'
        COMMENT 'ISO 4217 currency code — one plan row per currency variant',
    `region`                VARCHAR(10)         DEFAULT NULL
        COMMENT 'Region code this plan is offered in (GB, EU, US, ROW…); NULL = all regions',
    `perSeatAmount`         DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'Per-seat price per interval. NULL = per-org flat pricing (amount only)',
    `minSeats`              SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Minimum billable seats when perSeatAmount is set (NULL = 1)',
    `maxSeats`              SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Maximum purchasable seats on this plan (NULL = no cap)',
    `trialDays`             SMALLINT UNSIGNED   NOT NULL DEFAULT 0
        COMMENT 'Free-trial length in days (feeds tblSubscriptions.trialEndsAt); 0 = no trial',
    `meteredFeatureUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblFeatures.featureUID — the metering dimension for payg/payg_capped/credit_pack',
    `unitPrice`             DECIMAL(12, 6)      DEFAULT NULL
        COMMENT 'PAYG price per unitSize units of the metered feature',
    `unitSize`              INT UNSIGNED        DEFAULT NULL
        COMMENT 'Units per unitPrice (e.g. 1000 → price per 1,000 tracked clicks). NULL when not metered',
    `includedUnits`         BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Free allowance per period before PAYG metering starts (NULL = none)',
    `capAmount`             DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'payg_capped: maximum metered spend per period. NULL + equivalentPlanUID set = cap equals that plan''s CURRENT amount',
    `equivalentPlanUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Self-FK to the flat tblPricePlans row this PAYG cap is pegged to',
    `capBehaviour`          ENUM('flat_convert', 'hard_stop', 'notify_only')
                            DEFAULT NULL
        COMMENT 'What happens when the cap is reached (payg_capped only)',
    `creditUnits`           BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'credit_pack only: metered units granted per purchase (lands in tblUsageCredits)',
    `validFrom`             DATETIME            DEFAULT NULL
        COMMENT 'Offer window start (limited-time / intro pricing); NULL = always offerable while isActive',
    `validUntil`            DATETIME            DEFAULT NULL
        COMMENT 'Offer window end — existing subscriptions are grandfathered via tblSubscriptionPlans',
    `maxSubscriptions`      INT UNSIGNED        DEFAULT NULL
        COMMENT 'Scarcity cap on total take-up (e.g. 500 founding lifetime deals); NULL = uncapped',
    `currentSubscriptions`  INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Running take-up count, maintained by the purchase flow',
    `isDefault`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'The advertised plan for its (tierID, currency, region, billingInterval) group on the pricing page',
    `isVisible`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Publicly listed (1) vs hidden/deal-only/grandfather-only (0)',
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'DISABLED BY DEFAULT (0): a plan must be explicitly activated by an operator before it can be offered',
    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display order on the pricing page',
    `metadataJSON`          JSON                DEFAULT NULL
        COMMENT 'Provider mapping and future extension data (e.g. {"provider":"stripe","priceId":"price_..."})',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`planUID`),
    UNIQUE KEY `UQ_priceplan_slug` (`planSlug`),
    INDEX `IDX_priceplan_tier` (`tierID`, `isActive`, `isDefault`),
    INDEX `IDX_priceplan_type` (`planType`, `isActive`),
    INDEX `IDX_priceplan_currency_region` (`currency`, `region`, `isActive`),
    INDEX `IDX_priceplan_window` (`validFrom`, `validUntil`),
    CONSTRAINT `FK_priceplan_tier`
        FOREIGN KEY (`tierID`)
        REFERENCES `tblSubscriptionTiers` (`tierID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `FK_priceplan_metered_feature`
        FOREIGN KEY (`meteredFeatureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `FK_priceplan_equivalent_plan`
        FOREIGN KEY (`equivalentPlanUID`)
        REFERENCES `tblPricePlans` (`planUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Price book — any number of simultaneous price plans per tier: recurring, lifetime, one-off, PAYG, PAYG-capped, add-ons, credit packs, per currency/region/offer-window';

-- 3. tblTierFeatures — tier↔feature entitlement matrix (see schema 036 §3)
CREATE TABLE IF NOT EXISTS `tblTierFeatures` (
    `tierFeatureUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `tierID`                VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblSubscriptionTiers.tierID',
    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID',
    `valueBoolean`          TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Typed value for boolean features (NULL = not this type)',
    `valueInt`              BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Typed value for limit/quota features (NULL = not this type OR unlimited — see isUnlimited)',
    `valueString`           VARCHAR(255)        DEFAULT NULL
        COMMENT 'Typed value for enum features',
    `valueJSON`             JSON                DEFAULT NULL
        COMMENT 'Value for config features / complex values beyond the typed columns',
    `isUnlimited`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Explicit unlimited marker for limit/quota features (1 = ∞; resolver outputs legacy NULL)',
    `effectiveFrom`         DATETIME            DEFAULT NULL
        COMMENT 'When this entitlement value starts applying (NULL = since forever)',
    `effectiveUntil`        DATETIME            DEFAULT NULL
        COMMENT 'When this entitlement value stops applying (NULL = open-ended)',
    -- #183: explicit CAST(... AS DATETIME) for MariaDB compatibility (see the
    -- matching note in web/_sql/schema/036_pricing_engine.sql).
    `effectiveFromKey`      DATETIME
                            GENERATED ALWAYS AS (COALESCE(`effectiveFrom`, CAST('1000-01-01 00:00:00' AS DATETIME))) STORED
        COMMENT 'NULL-collapsed mirror of effectiveFrom so undated rows dedupe in UQ_tierfeature (settings #150 idiom)',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tierFeatureUID`),
    UNIQUE KEY `UQ_tierfeature` (`tierID`, `featureUID`, `effectiveFromKey`),
    INDEX `IDX_tierfeature_feature` (`featureUID`),
    INDEX `IDX_tierfeature_window` (`effectiveFrom`, `effectiveUntil`),
    CONSTRAINT `FK_tierfeature_tier`
        FOREIGN KEY (`tierID`)
        REFERENCES `tblSubscriptionTiers` (`tierID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_tierfeature_feature`
        FOREIGN KEY (`featureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tier↔feature entitlement matrix as data — any feature grantable to any tier at any granularity, with effective-dating';

-- 4. tblOrgFeatureOverrides — per-org overrides/add-ons/custom deals (see schema 036 §4)
CREATE TABLE IF NOT EXISTS `tblOrgFeatureOverrides` (
    `overrideUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle — the single org this override applies to',
    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — the single feature being overridden',
    `overrideMode`          ENUM('grant', 'deny', 'set', 'adjust')
                            NOT NULL DEFAULT 'set'
        COMMENT 'How this override combines with the tier value',
    `valueBoolean`          TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'set-mode replacement value for boolean features',
    `valueInt`              BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'set-mode replacement value for limit/quota features',
    `valueString`           VARCHAR(255)        DEFAULT NULL
        COMMENT 'set-mode replacement value for enum features',
    `valueJSON`             JSON                DEFAULT NULL
        COMMENT 'set-mode replacement value for config features / complex values',
    `isUnlimited`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'set-mode: explicit unlimited (1 = ∞ for this org, this feature)',
    `adjustDelta`           BIGINT              DEFAULT NULL
        COMMENT 'adjust-mode: SIGNED delta applied to the tier''s numeric value; NULL for other modes',
    `effectiveFrom`         DATETIME            DEFAULT NULL
        COMMENT 'When the override starts (NULL = immediately)',
    `effectiveUntil`        DATETIME            DEFAULT NULL
        COMMENT 'When the override lapses (NULL = until removed)',
    `sourceType`            ENUM('addon', 'comp', 'custom_deal', 'trial', 'support', 'migration')
                            NOT NULL DEFAULT 'custom_deal'
        COMMENT 'Why this override exists (audit/reporting)',
    `sourcePlanUID`         BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPricePlans.planUID when sourceType=addon/credit purchase',
    `notes`                 TEXT                DEFAULT NULL
        COMMENT 'Free-text audit note (who agreed what, ticket ref, etc.)',
    `createdByUserUID`      BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID — the admin who created this override (NULL = system)',
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Soft on/off without losing the audit row',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`overrideUID`),
    INDEX `IDX_orgoverride_org_feature` (`orgHandle`, `featureUID`, `isActive`),
    INDEX `IDX_orgoverride_feature` (`featureUID`),
    INDEX `IDX_orgoverride_window` (`effectiveFrom`, `effectiveUntil`),
    CONSTRAINT `FK_orgoverride_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_orgoverride_feature`
        FOREIGN KEY (`featureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_orgoverride_source_plan`
        FOREIGN KEY (`sourcePlanUID`)
        REFERENCES `tblPricePlans` (`planUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT `FK_orgoverride_created_by`
        FOREIGN KEY (`createdByUserUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-org feature overrides — add-ons, comps, custom deals, feature trials — applied on top of tier entitlements, with effective-dating and audit trail';

-- 5. tblCoupons — discount/coupon engine (see schema 036 §5)
CREATE TABLE IF NOT EXISTS `tblCoupons` (
    `couponUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `couponCode`            VARCHAR(50)         DEFAULT NULL
        COMMENT 'Redeemable code (NULL = automatic discount applied by rule)',
    `couponName`            VARCHAR(255)        NOT NULL
        COMMENT 'Display name (e.g. "Launch offer — 25% off 3 months")',
    `discountKind`          ENUM('percentage', 'fixed_amount', 'free_periods', 'override_price')
                            NOT NULL DEFAULT 'percentage'
        COMMENT 'Discount mechanics (see schema 036 §5)',
    `discountValue`         DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Percentage (0–100), fixed amount off, or override price; ignored for free_periods',
    `durationType`          ENUM('once', 'first_n_periods', 'forever', 'until_date')
                            NOT NULL DEFAULT 'once'
        COMMENT 'How long the discount keeps applying to a subscription (forever = lifetime discount)',
    `durationPeriods`       SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'first_n_periods: number of billing periods discounted (also the N for free_periods)',
    `durationUntil`         DATETIME            DEFAULT NULL
        COMMENT 'until_date: last date the discount applies to renewals',
    `applicablePlanUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Restrict to one price plan (NULL = no plan restriction)',
    `applicableTierID`      VARCHAR(50)         DEFAULT NULL
        COMMENT 'Restrict to any plan of one tier (NULL = no tier restriction)',
    `applicablePlanType`    ENUM('recurring', 'lifetime', 'one_off', 'payg', 'payg_capped', 'addon', 'credit_pack')
                            DEFAULT NULL
        COMMENT 'Restrict to a plan type; NULL = any',
    `minAmount`             DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'Minimum pre-discount charge for the coupon to apply (NULL = none)',
    `maxUses`               INT UNSIGNED        DEFAULT NULL
        COMMENT 'Global redemption cap (NULL = unlimited)',
    `currentUses`           INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Running redemption count',
    `perOrgMaxUses`         SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Per-organisation redemption cap (NULL = unlimited; typical value 1)',
    `isStackable`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this coupon may combine with others',
    `stackingGroup`         VARCHAR(50)         DEFAULT NULL
        COMMENT 'Coupons sharing a group are mutually exclusive even when stackable',
    `legacyDiscountUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPaymentDiscounts.discountUID when migrated from the legacy table',
    `validFrom`             DATETIME            DEFAULT NULL
        COMMENT 'Redemption window start (limited-time offers)',
    `validUntil`            DATETIME            DEFAULT NULL
        COMMENT 'Redemption window end',
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'DISABLED BY DEFAULT (0) — a coupon must be explicitly activated',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`couponUID`),
    UNIQUE KEY `UQ_coupon_code` (`couponCode`),
    INDEX `IDX_coupon_active_window` (`isActive`, `validFrom`, `validUntil`),
    INDEX `IDX_coupon_plan` (`applicablePlanUID`),
    INDEX `IDX_coupon_tier` (`applicableTierID`),
    CONSTRAINT `FK_coupon_plan`
        FOREIGN KEY (`applicablePlanUID`)
        REFERENCES `tblPricePlans` (`planUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT `FK_coupon_tier`
        FOREIGN KEY (`applicableTierID`)
        REFERENCES `tblSubscriptionTiers` (`tierID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT `FK_coupon_legacy_discount`
        FOREIGN KEY (`legacyDiscountUID`)
        REFERENCES `tblPaymentDiscounts` (`discountUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Coupon/discount engine — intro, limited-time, lifetime, first-N-periods, price-override and free-period discounts with stacking rules and per-plan/tier/type applicability';

-- 6. tblCouponRedemptions — enforces coupon usage caps (see schema 036 §6)
CREATE TABLE IF NOT EXISTS `tblCouponRedemptions` (
    `redemptionUID`         BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `couponUID`             BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblCoupons.couponUID',
    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle — the redeeming org',
    `subscriptionUID`       BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblSubscriptions.subscriptionUID the coupon is attached to (NULL for one-off purchases)',
    `paymentUID`            BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPayments.paymentUID of the first discounted payment (NULL until first charge)',
    `amountDiscounted`      DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Total discounted so far under this redemption',
    `periodsApplied`        SMALLINT UNSIGNED   NOT NULL DEFAULT 0
        COMMENT 'How many billing periods have received this discount',
    `redeemedAt`            DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When the coupon was redeemed',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`redemptionUID`),
    INDEX `IDX_redemption_coupon_org` (`couponUID`, `orgHandle`),
    INDEX `IDX_redemption_org` (`orgHandle`),
    INDEX `IDX_redemption_subscription` (`subscriptionUID`),
    CONSTRAINT `FK_redemption_coupon`
        FOREIGN KEY (`couponUID`)
        REFERENCES `tblCoupons` (`couponUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_redemption_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_redemption_subscription`
        FOREIGN KEY (`subscriptionUID`)
        REFERENCES `tblSubscriptions` (`subscriptionUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT `FK_redemption_payment`
        FOREIGN KEY (`paymentUID`)
        REFERENCES `tblPayments` (`paymentUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Coupon redemption ledger — enforces global/per-org usage caps and first-N-periods duration, and audits discount attribution';

-- 7. tblSubscriptionPlans — subscription ↔ price-plan pin / grandfathering (see schema 036 §7)
CREATE TABLE IF NOT EXISTS `tblSubscriptionPlans` (
    `subscriptionPlanUID`   BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `subscriptionUID`       BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblSubscriptions.subscriptionUID — the legacy subscription row this plan context attaches to',
    `planUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblPricePlans.planUID — the exact plan subscribed',
    `priceAtSubscription`   DECIMAL(10, 2)      NOT NULL
        COMMENT 'GRANDFATHER PIN: the per-interval amount agreed at purchase',
    `currencyAtSubscription` CHAR(3)            NOT NULL DEFAULT 'GBP'
        COMMENT 'Currency of the pinned price (ISO 4217)',
    `seats`                 SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Purchased seat count for per-seat plans (NULL = per-org plan)',
    `perSeatPriceAtSubscription` DECIMAL(10, 2) DEFAULT NULL
        COMMENT 'GRANDFATHER PIN for the per-seat component (NULL = plan had none)',
    `capAmountAtSubscription` DECIMAL(10, 2)    DEFAULT NULL
        COMMENT 'GRANDFATHER PIN for a payg_capped cap',
    `isCurrent`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1 = the active plan row for this subscription; plan changes close the old row and insert a new one',
    `effectiveFrom`         DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this plan attachment began',
    `effectiveUntil`        DATETIME            DEFAULT NULL
        COMMENT 'When it ended (NULL while current)',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`subscriptionPlanUID`),
    INDEX `IDX_subplan_subscription` (`subscriptionUID`, `isCurrent`),
    INDEX `IDX_subplan_plan` (`planUID`),
    CONSTRAINT `FK_subplan_subscription`
        FOREIGN KEY (`subscriptionUID`)
        REFERENCES `tblSubscriptions` (`subscriptionUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_subplan_plan`
        FOREIGN KEY (`planUID`)
        REFERENCES `tblPricePlans` (`planUID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Attaches price-plan context (with grandfather price pins and plan history) to unmodified tblSubscriptions rows';

-- 8. tblUsageCounters — aggregated usage metering (see schema 036 §8)
CREATE TABLE IF NOT EXISTS `tblUsageCounters` (
    `counterUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',
    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — must be an isMeterable feature',
    `periodType`            ENUM('day', 'month', 'billing_period')
                            NOT NULL DEFAULT 'month'
        COMMENT 'Aggregation window kind',
    `periodStart`           DATE                NOT NULL
        COMMENT 'First day of the window',
    `usedCount`             BIGINT UNSIGNED     NOT NULL DEFAULT 0
        COMMENT 'Units consumed in this window (atomic UPSERT)',
    `lastEventAt`           DATETIME            DEFAULT NULL
        COMMENT 'Timestamp of the most recent metered event',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`counterUID`),
    UNIQUE KEY `UQ_usage_counter` (`orgHandle`, `featureUID`, `periodType`, `periodStart`),
    INDEX `IDX_usage_feature_period` (`featureUID`, `periodType`, `periodStart`),
    CONSTRAINT `FK_usage_counter_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_usage_counter_feature`
        FOREIGN KEY (`featureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Aggregated per-org usage counters per meterable feature and period — powers quotas, PAYG billing, and PAYG caps';

-- 9. tblUsageCredits — purchased/granted unit credits (see schema 036 §9)
CREATE TABLE IF NOT EXISTS `tblUsageCredits` (
    `creditUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',
    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — the meterable feature these credits apply to',
    `unitsGranted`          BIGINT UNSIGNED     NOT NULL
        COMMENT 'Units in the bucket at purchase/grant time',
    `unitsRemaining`        BIGINT UNSIGNED     NOT NULL
        COMMENT 'Units left (decremented as usage draws down)',
    `sourcePlanUID`         BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPricePlans.planUID of the credit_pack purchased (NULL for goodwill/referral grants)',
    `sourcePaymentUID`      BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPayments.paymentUID that bought this bucket (NULL for grants)',
    `expiresAt`             DATETIME            DEFAULT NULL
        COMMENT 'When unused credits lapse (NULL = never)',
    `notes`                 VARCHAR(255)        DEFAULT NULL
        COMMENT 'Audit note (e.g. "referral reward", ticket ref)',
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Soft disable without losing the audit row',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`creditUID`),
    INDEX `IDX_credit_org_feature` (`orgHandle`, `featureUID`, `isActive`, `expiresAt`),
    CONSTRAINT `FK_credit_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_credit_feature`
        FOREIGN KEY (`featureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_credit_source_plan`
        FOREIGN KEY (`sourcePlanUID`)
        REFERENCES `tblPricePlans` (`planUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT `FK_credit_source_payment`
        FOREIGN KEY (`sourcePaymentUID`)
        REFERENCES `tblPayments` (`paymentUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Prepaid metered-unit buckets (credit packs, referral/goodwill grants) drawn down before PAYG charging';

-- 10. tblUsageEvents — optional fine-grained usage ledger (see schema 036 §10)
CREATE TABLE IF NOT EXISTS `tblUsageEvents` (
    `eventUID`              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',
    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',
    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — the metered dimension',
    `quantity`              INT UNSIGNED        NOT NULL DEFAULT 1
        COMMENT 'Units consumed by this event (bulk API call may consume many)',
    `eventRef`              VARCHAR(100)        DEFAULT NULL
        COMMENT 'Caller-supplied idempotency reference',
    `contextJSON`           JSON                DEFAULT NULL
        COMMENT 'Optional event context for audit',
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`eventUID`),
    UNIQUE KEY `UQ_usage_event_ref` (`featureUID`, `eventRef`),
    INDEX `IDX_usage_event_org` (`orgHandle`, `featureUID`, `createdAt`),
    INDEX `IDX_usage_event_created` (`createdAt`),
    CONSTRAINT `FK_usage_event_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `FK_usage_event_feature`
        FOREIGN KEY (`featureUID`)
        REFERENCES `tblFeatures` (`featureUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Optional fine-grained usage event ledger for PAYG audit/disputes — counters remain the operational metering source';

-- 11. Verification view (see schema 036 §11)
CREATE OR REPLACE VIEW `vwSubscriptionTierEntitlements` AS
SELECT
    tf.`tierID` AS `tierID`,
    MAX(CASE WHEN f.`featureSlug` = 'links.max'
             THEN CASE WHEN tf.`isUnlimited` = 1 THEN NULL ELSE tf.`valueInt` END END) AS `maxLinks`,
    MAX(CASE WHEN f.`featureSlug` = 'domains.custom_max'
             THEN CASE WHEN tf.`isUnlimited` = 1 THEN NULL ELSE tf.`valueInt` END END) AS `maxCustomDomains`,
    MAX(CASE WHEN f.`featureSlug` = 'api.requests_per_day'
             THEN CASE WHEN tf.`isUnlimited` = 1 THEN NULL ELSE tf.`valueInt` END END) AS `maxAPIRequestsPerDay`,
    MAX(CASE WHEN f.`featureSlug` = 'linkspage.pages_max'
             THEN CASE WHEN tf.`isUnlimited` = 1 THEN NULL ELSE tf.`valueInt` END END) AS `maxLinksPages`,
    MAX(CASE WHEN f.`featureSlug` = 'redirects.advanced'  THEN tf.`valueBoolean` END) AS `hasAdvancedRedirects`,
    MAX(CASE WHEN f.`featureSlug` = 'analytics.enabled'   THEN tf.`valueBoolean` END) AS `hasAnalytics`,
    MAX(CASE WHEN f.`featureSlug` = 'qr.dynamic'          THEN tf.`valueBoolean` END) AS `hasQRCodes`,
    MAX(CASE WHEN f.`featureSlug` = 'api.access'          THEN tf.`valueBoolean` END) AS `hasAPIAccess`,
    MAX(CASE WHEN f.`featureSlug` = 'support.priority'    THEN tf.`valueBoolean` END) AS `hasPrioritySupport`,
    MAX(CASE WHEN f.`featureSlug` = 'linkspage.custom_html' THEN tf.`valueBoolean` END) AS `hasCustomHTML`
FROM `tblTierFeatures` tf
INNER JOIN `tblFeatures` f
        ON f.`featureUID` = tf.`featureUID`
       AND f.`isActive`   = 1
WHERE (tf.`effectiveFrom`  IS NULL OR tf.`effectiveFrom`  <= NOW())
  AND (tf.`effectiveUntil` IS NULL OR tf.`effectiveUntil` >  NOW())
GROUP BY tf.`tierID`;

-- =============================================================================
-- PART 2 — SEED DATA (verbatim from web/_sql/seeds/018_pricing_feature_registry.sql
-- and web/_sql/seeds/019_pricing_settings.sql).
-- =============================================================================

-- 2a. Feature-registry rows replacing the legacy hard-coded columns
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

-- 2b. New granular registry rows (no legacy column)
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

-- 2c. Backfill tblTierFeatures from the legacy hard-coded columns
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxLinks`,
       CASE WHEN t.`maxLinks` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxLinks'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxCustomDomains`,
       CASE WHEN t.`maxCustomDomains` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxCustomDomains'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxAPIRequestsPerDay`,
       CASE WHEN t.`maxAPIRequestsPerDay` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxAPIRequestsPerDay'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`, t.`maxLinksPages`,
       CASE WHEN t.`maxLinksPages` IS NULL THEN 1 ELSE 0 END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'maxLinksPages'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAdvancedRedirects`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAdvancedRedirects'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAnalytics`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAnalytics'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasQRCodes`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasQRCodes'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasAPIAccess`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasAPIAccess'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasPrioritySupport`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasPrioritySupport'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`, t.`hasCustomHTML`
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`legacyColumn` = 'hasCustomHTML'
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- 2d. Engine-switch settings (ALL seeded OFF except the two display defaults)
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

-- =============================================================================
-- Verification (informational — safe to run, changes nothing).
-- =============================================================================
-- After this migration, every seeded tier's legacy columns must match the
-- verification view column-for-column; this SELECT should return rows where
-- every diff* column is 0 (see tests/integration/pricing_backfill_verify.php
-- for the automated version of this check).
SELECT
    t.`tierID`,
    (t.`maxLinks` <=> v.`maxLinks`)                         AS `maxLinksMatches`,
    (t.`maxCustomDomains` <=> v.`maxCustomDomains`)         AS `maxCustomDomainsMatches`,
    (t.`maxAPIRequestsPerDay` <=> v.`maxAPIRequestsPerDay`) AS `maxAPIRequestsPerDayMatches`,
    (t.`maxLinksPages` <=> v.`maxLinksPages`)                AS `maxLinksPagesMatches`,
    (t.`hasAdvancedRedirects` <=> v.`hasAdvancedRedirects`)  AS `hasAdvancedRedirectsMatches`,
    (t.`hasAnalytics` <=> v.`hasAnalytics`)                   AS `hasAnalyticsMatches`,
    (t.`hasQRCodes` <=> v.`hasQRCodes`)                       AS `hasQRCodesMatches`,
    (t.`hasAPIAccess` <=> v.`hasAPIAccess`)                   AS `hasAPIAccessMatches`,
    (t.`hasPrioritySupport` <=> v.`hasPrioritySupport`)       AS `hasPrioritySupportMatches`,
    (t.`hasCustomHTML` <=> v.`hasCustomHTML`)                 AS `hasCustomHTMLMatches`
FROM `tblSubscriptionTiers` t
LEFT JOIN `vwSubscriptionTierEntitlements` v USING (`tierID`);

-- End of file.
