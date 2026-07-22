-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Flexible Pricing & Entitlement Engine — Tables + Verification View
-- =============================================================================
-- WHAT THIS IS
-- ------------
-- A fully data-driven replacement for the HARD-CODED entitlement columns on
-- tblSubscriptionTiers (maxLinks, maxCustomDomains, maxAPIRequestsPerDay,
-- maxLinksPages, hasAdvancedRedirects, hasAnalytics, hasQRCodes, hasAPIAccess,
-- hasPrioritySupport, hasCustomHTML) and the 2-value discountType ENUM on
-- tblPaymentDiscounts. After this schema:
--
--   * Adding a FEATURE          = INSERT one row into tblFeatures.
--   * Adding a TIER             = INSERT into tblSubscriptionTiers (unchanged)
--                                 + rows in tblTierFeatures.
--   * Adding a PRICE STRUCTURE  = INSERT one row into tblPricePlans
--                                 (recurring / lifetime / one-off / PAYG /
--                                  PAYG-capped / add-on / credit pack, any
--                                  currency, any region, any validity window).
--   * A custom deal for one org = INSERT one row into tblOrgFeatureOverrides.
--
--   NO schema change is ever needed again for tiers, features, or prices.
--
-- COMPATIBILITY GUARANTEES (non-negotiable)
-- -----------------------------------------
--   1. 100% ADDITIVE. No existing table is ALTERed, dropped, or rewritten.
--      tblSubscriptionTiers keeps its columns (they remain the LEGACY read
--      path and stay authoritative while the engine is OFF).
--   2. tblSubscriptions / tblPayments / tblPaymentDiscounts are untouched.
--      These new tables REFERENCE them; nothing changes their shape or
--      semantics.
--   3. DISABLED BY DEFAULT. The resolver in web/_functions/pricing.php only
--      activates when tblSettings 'billing.pricing_engine_enabled' = '1'
--      (seeded '0' in web/_sql/seeds/019_pricing_settings.sql). Until then,
--      entitlements.php behaves EXACTLY as before this file was added, byte
--      for byte. Every table below is inert data until that flag flips.
--   4. entitlements.php's public API is PRESERVED: g2ml_getOrgTier(),
--      g2ml_canUseFeature(), g2ml_checkLimit(), the two whitelist constants,
--      and the FAIL-OPEN contract all keep their exact signatures/behaviour.
--
-- LOAD-ORDER SAFETY
-- -----------------
-- This file loads AFTER 011 (tblSubscriptionTiers), 012 (tblOrganisations),
-- 013 (tblUsers), and 033 (tblSubscriptions/tblPaymentDiscounts/tblPayments),
-- so every FK target below already exists. WITHIN this file, tables are
-- ordered so every FK resolves during a clean import with
-- foreign_key_checks = 1:
--
--    1. tblFeatures            (no deps on new tables)
--    2. tblPricePlans          (FK → tblFeatures, tblSubscriptionTiers, self)
--    3. tblTierFeatures        (FK → tblFeatures, tblSubscriptionTiers)
--    4. tblOrgFeatureOverrides (FK → tblFeatures, tblOrganisations, tblUsers,
--                                    tblPricePlans)
--    5. tblCoupons             (FK → tblPricePlans, tblSubscriptionTiers,
--                                    tblPaymentDiscounts)
--    6. tblCouponRedemptions   (FK → tblCoupons, tblOrganisations,
--                                    tblSubscriptions, tblPayments)
--    7. tblSubscriptionPlans   (FK → tblSubscriptions, tblPricePlans)
--    8. tblUsageCounters       (FK → tblOrganisations, tblFeatures)
--    9. tblUsageCredits        (FK → tblOrganisations, tblFeatures, tblPricePlans)
--   10. tblUsageEvents         (FK → tblOrganisations, tblFeatures)
--   11. vwSubscriptionTierEntitlements (verification view, reads tables above)
--
-- The feature-registry seed + legacy backfill (INSERT…SELECT from
-- tblSubscriptionTiers' has*/max* columns into tblTierFeatures) lives in
-- web/_sql/seeds/018_pricing_feature_registry.sql — it runs AFTER this file
-- and AFTER the tier seed (001_subscription_tiers.sql) so the JOIN has rows to
-- copy. The nine billing.* engine-switch settings (all seeded OFF) live in
-- web/_sql/seeds/019_pricing_settings.sql. All CREATEs here use IF NOT EXISTS
-- and all seed INSERTs are idempotent, so re-running the whole set is safe —
-- the same property lets web/_sql/migrations/020_pricing_engine.sql reuse this
-- content verbatim for already-deployed databases.
--
-- HOW entitlements.php READS THIS MODEL (resolver contract — implemented in
-- web/_functions/pricing.php; see that file's header and the guarded hook in
-- _g2ml_resolveOrgTier()):
--
--   PUBLIC SIGNATURES PRESERVED (unchanged, callers untouched):
--     g2ml_getOrgTier(string $orgHandle): array
--     g2ml_canUseFeature(string $orgHandle, string $flag): bool
--     g2ml_checkLimit(string $orgHandle, string $limit, int $currentCount): array
--     constants G2ML_ENTITLEMENT_FEATURE_FLAGS / G2ML_ENTITLEMENT_LIMIT_FIELDS
--
--   HOOK POINT: inside _g2ml_resolveOrgTier(), AFTER the '[default]'-org and
--   GlobalAdmin sentinel branches (those short-circuit exactly as today), and
--   BEFORE the legacy JOIN lookup — only when pricing.php is loaded AND
--   getSetting('billing.pricing_engine_enabled', '0') === '1'. Any failure
--   (false/non-array) falls through to the legacy path (fail OPEN to the OLD
--   behaviour, never to a block). With the setting at its seeded '0' value
--   this hook is dead code — current behaviour is preserved byte-for-byte.
--
--   RESOLUTION (g2ml_pricingResolveOrgTier), all reads prepared-statement
--   MySQLi, every failure returning false so the caller falls back:
--     1. org → tierID via tblOrganisations (as today; inactive/missing tier →
--        lowest-sortOrder ACTIVE tier fallback, as today).
--     2. Load ACTIVE tblFeatures (request-cached; one small table scan).
--     3. Load the tier's CURRENTLY-EFFECTIVE tblTierFeatures rows (latest
--        effectiveFrom <= NOW(), effectiveUntil NULL or > NOW()).
--     4. Load the org's ACTIVE, currently-effective tblOrgFeatureOverrides.
--     5. MERGE per feature: safe-floor default (tblFeatures.default*) → tier
--        value → override ('deny' forces off; 'grant' forces on; 'set'
--        replaces incl. isUnlimited; 'adjust' adds adjustDelta to the numeric
--        value, floored at 0; multiple 'adjust' rows accumulate).
--     6. EMIT the legacy-shaped array (tierID, tierName, maxLinks,
--        maxCustomDomains, maxAPIRequestsPerDay, maxLinksPages, has*-flags,
--        unlimited, source='pricing_engine') using tblFeatures.legacyColumn as
--        the mapping; isUnlimited=1 → NULL (legacy unlimited semantics);
--        hasAdvancedRedirects = umbrella row OR any granular redirects.* on.
--        Granular slugs are ADDITIONALLY exposed under a 'features' sub-array
--        for new callers (g2ml_pricingCanUse()/g2ml_pricingGetLimit()) —
--        legacy keys stay primitive so existing callers notice nothing.
--   FAIL-OPEN: any DB/system error at steps 1–5 → return false → caller falls
--   back to the legacy resolver, which itself fails open to the unlimited
--   sentinel — strictly no new blocking failure mode.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.6.0 — Pricing Engine phase (scaffold — all tables inert until
--             billing.pricing_engine_enabled is switched on)
--
-- 📖 References:
--     - Legacy tier columns:   web/_sql/schema/011_core_subscription_tiers.sql
--     - Legacy discounts:      web/_sql/schema/033_payments.sql (tblPaymentDiscounts)
--     - Read API to preserve:  web/_functions/entitlements.php
--     - Resolver:              web/_functions/pricing.php
--     - Feature/backfill seed: web/_sql/seeds/018_pricing_feature_registry.sql
--     - Engine-switch seed:    web/_sql/seeds/019_pricing_settings.sql
--     - Already-deployed DBs:  web/_sql/migrations/020_pricing_engine.sql
--     - Strategy rationale:    Pricing_Strategy.md (repo root)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- 1. tblFeatures — THE FEATURE REGISTRY
-- =============================================================================
-- Flexibility requirement satisfied: "Adding a feature = a row, never a schema
-- change." Every gateable capability — boolean flags, numeric limits, metered
-- quotas, enum choices, structured config — is ONE row here, identified by a
-- stable dot-namespaced slug (e.g. 'links.max', 'redirects.geo').
--
-- valueType semantics:
--   'boolean' — on/off entitlement            (uses *Boolean value columns)
--   'limit'   — standing cap on a COUNT of things that exist
--               (active links, domains, pages, seats)   (uses *Int columns)
--   'quota'   — consumable allowance per period (API req/day, clicks/month)
--               (uses *Int columns + quotaPeriod; meterable for PAYG)
--   'enum'    — one-of-N choice (e.g. support level)    (uses *String columns;
--               allowed values listed in allowedValues, newline-separated,
--               mirroring tblSettings.settingValuesAllowed)
--   'config'  — structured JSON entitlement (e.g. per-template allowances)
--               (uses *JSON columns)
--
-- isMeterable = 1 marks a feature as a legal PAYG metering dimension: a
-- tblPricePlans row may point its meteredFeatureUID here, and tblUsageCounters
-- accumulates consumption against it. Adding a NEW metering dimension (QR
-- scans, LinksPage views, …) = flipping/adding a row — no schema change.
--
-- legacyColumn maps a registry row back to the hard-coded
-- tblSubscriptionTiers column it replaces — this drives the automated
-- backfill (seed 018) AND lets the resolver emit the legacy-shaped tier array
-- entitlements.php callers expect. NULL for brand-new granular features.
--
-- Default-value columns are the SAFE FLOOR used when a tier simply has no
-- tblTierFeatures row for a feature (e.g. a feature added after a tier was
-- configured): the resolver falls back to these — which default to
-- "off"/0/not-unlimited — so a forgotten mapping can never accidentally grant
-- a premium capability.
-- =============================================================================
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
        COMMENT 'How entitlement values for this feature are typed and enforced (see header)',

    `valueUnit`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'Display/metering unit for limit/quota types (e.g. links, domains, requests, days)',

    `quotaPeriod`           ENUM('day', 'month', 'billing_period')
                            DEFAULT NULL
        COMMENT 'For valueType=quota only: the window the allowance renews over (NULL otherwise)',

    `allowedValues`         TEXT                DEFAULT NULL
        COMMENT 'For valueType=enum only: newline-separated permitted values (mirrors tblSettings.settingValuesAllowed idiom); NULL = not applicable',

    `defaultValueBoolean`   TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Safe-floor default for boolean features when a tier has no row (normally 0 = off)',

    `defaultValueInt`       BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Safe-floor default for limit/quota features when a tier has no row (normally 0)',

    `defaultValueString`    VARCHAR(255)        DEFAULT NULL
        COMMENT 'Safe-floor default for enum features when a tier has no row',

    `defaultValueJSON`      JSON                DEFAULT NULL
        COMMENT 'Safe-floor default for config features when a tier has no row',

    `defaultIsUnlimited`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the safe-floor default is "unlimited" (1) — kept EXPLICIT rather than overloading NULL, so "no value configured" can never be misread as "unlimited"',

    `category`              VARCHAR(50)         NOT NULL DEFAULT 'general'
        COMMENT 'Grouping for admin UI / pricing matrix (links, domains, redirects, analytics, api, qr, linkspage, org, support)',

    `isMeterable`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this feature may serve as a PAYG metering dimension (tblPricePlans.meteredFeatureUID / tblUsageCounters)',

    `legacyColumn`          VARCHAR(50)         DEFAULT NULL
        COMMENT 'The hard-coded tblSubscriptionTiers column this row replaces (maxLinks, hasAnalytics, …); drives the seed-018 backfill and legacy-shape output; NULL for new granular features',

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

-- =============================================================================
-- 2. tblPricePlans — THE FLEXIBLE PRICE BOOK
-- =============================================================================
-- Flexibility requirements satisfied here:
--   * "a tier can have MANY simultaneous price plans" — plans are rows keyed
--     by tierID (nullable for tier-less add-ons / credit packs), so one tier
--     can carry monthly GBP + annual GBP + intro EUR + lifetime + PAYG-capped
--     rows all at once.
--   * planType covers: 'recurring' (month/year via billingInterval ×
--     intervalCount — quarterly = month×3), 'lifetime' (pay once, keep tier;
--     tblSubscriptions.billingCycle ENUM already includes lifetime),
--     'one_off' (single purchase, e.g. a setup fee or paid migration),
--     'payg' (pure metered), 'payg_capped' (metered with a monthly cap equal
--     to a flat tier price — see cap columns), plus two additive extensions:
--     'addon' (recurring bolt-on that grants org feature overrides while
--     active) and 'credit_pack' (one-off purchase of metered units →
--     tblUsageCredits).
--   * PAYG: meteredFeatureUID + unitPrice/unitSize + includedUnits define
--     "£unitPrice per unitSize units of <feature> beyond includedUnits".
--   * THE "PAYG CAPPED AT AN EQUIVALENT FLAT TIER PRICE" CASE:
--     capAmount is the hard monthly spend ceiling; when capAmount is NULL and
--     equivalentPlanUID is set, the cap IS that flat plan's current amount —
--     so "PAYG, never more than Pro monthly" stays true automatically when
--     the Pro price changes. capBehaviour states what happens at the cap:
--       'flat_convert' — org is billed the cap and treated as entitled to the
--                        equivalent plan's tier for the remainder of the period
--       'hard_stop'    — metered feature blocks at the cap (fail closed on a
--                        GENUINE limit, per the entitlements contract)
--       'notify_only'  — keep metering, alert the owner (soft cap)
--   * Limited-time / intro pricing: validFrom/validUntil bound offer windows;
--     an expired plan stops being OFFERED but existing subscriptions keep it
--     (grandfathering via tblSubscriptionPlans pinning).
--   * Currency/region variants: (currency, region) rows per tier; region NULL
--     = all regions. isDefault marks the advertised plan per currency/region.
--   * Per-seat vs per-org: perSeatAmount (+ min/maxSeats) prices per user;
--     NULL perSeatAmount = flat per-org amount. Both can combine (base + seats).
--   * Scarcity (founding-member lifetime deals): maxSubscriptions caps total
--     take-up; currentSubscriptions is maintained by the purchase flow.
--   * DISABLED BY DEFAULT: isActive defaults to 0 — a newly inserted plan is
--     inert until an operator activates it, matching the launch posture.
-- =============================================================================
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
        COMMENT 'Price structure (see table header for full semantics)',

    `billingInterval`       ENUM('month', 'year')
                            DEFAULT NULL
        COMMENT 'Recurring interval unit; NULL for lifetime/one_off/credit_pack. PAYG plans bill monthly in arrears → month',

    `intervalCount`         TINYINT UNSIGNED    NOT NULL DEFAULT 1
        COMMENT 'Multiplier on billingInterval (quarterly = month × 3); ignored when billingInterval is NULL',

    `amount`                DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Flat price per interval (or once, for lifetime/one_off/credit_pack) in `currency`. 0.00 for pure PAYG (spend comes from usage)',

    `currency`              CHAR(3)             NOT NULL DEFAULT 'GBP'
        COMMENT 'ISO 4217 currency code — one plan row per currency variant',

    `region`                VARCHAR(10)         DEFAULT NULL
        COMMENT 'Region code this plan is offered in (GB, EU, US, ROW…); NULL = all regions. Enables regional price books',

    `perSeatAmount`         DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'Per-seat price per interval. NULL = per-org flat pricing (amount only); set = amount + (seats × perSeatAmount)',

    `minSeats`              SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Minimum billable seats when perSeatAmount is set (NULL = 1)',

    `maxSeats`              SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Maximum purchasable seats on this plan (NULL = no cap)',

    `trialDays`             SMALLINT UNSIGNED   NOT NULL DEFAULT 0
        COMMENT 'Free-trial length in days (feeds tblSubscriptions.trialEndsAt); 0 = no trial',

    `meteredFeatureUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblFeatures.featureUID — the metering dimension for payg/payg_capped/credit_pack (must have isMeterable=1; enforced in PHP, not a CHECK, for MySQL-version safety)',

    `unitPrice`             DECIMAL(12, 6)      DEFAULT NULL
        COMMENT 'PAYG price per unitSize units of the metered feature (6dp for sub-penny unit rates, e.g. £0.000100 per API request)',

    `unitSize`              INT UNSIGNED        DEFAULT NULL
        COMMENT 'Units per unitPrice (e.g. 1000 → price per 1,000 tracked clicks). NULL when not metered',

    `includedUnits`         BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Free allowance per period before PAYG metering starts (NULL = none)',

    `capAmount`             DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'payg_capped: maximum metered spend per period. NULL + equivalentPlanUID set = cap equals that plan''s CURRENT amount (auto-tracks flat-price changes)',

    `equivalentPlanUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Self-FK to the flat tblPricePlans row this PAYG cap is pegged to ("PAYG, never more than Pro monthly"); also names the tier entitlement granted on flat_convert',

    `capBehaviour`          ENUM('flat_convert', 'hard_stop', 'notify_only')
                            DEFAULT NULL
        COMMENT 'What happens when the cap is reached (payg_capped only — see table header)',

    `creditUnits`           BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'credit_pack only: metered units granted per purchase (lands in tblUsageCredits)',

    `validFrom`             DATETIME            DEFAULT NULL
        COMMENT 'Offer window start (limited-time / intro pricing); NULL = always offerable while isActive',

    `validUntil`            DATETIME            DEFAULT NULL
        COMMENT 'Offer window end — after this the plan cannot be NEWLY subscribed; existing subscriptions are grandfathered via tblSubscriptionPlans',

    `maxSubscriptions`      INT UNSIGNED        DEFAULT NULL
        COMMENT 'Scarcity cap on total take-up (e.g. 500 founding lifetime deals); NULL = uncapped',

    `currentSubscriptions`  INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Running take-up count, maintained by the purchase flow (mirrors tblPaymentDiscounts.currentUses idiom)',

    `isDefault`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'The advertised plan for its (tierID, currency, region, billingInterval) group on the pricing page',

    `isVisible`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Publicly listed (1) vs hidden/deal-only/grandfather-only (0) — hidden plans are still subscribable via direct link/admin',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'DISABLED BY DEFAULT (0): a plan must be explicitly activated by an operator before it can be offered — safe-launch posture',

    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display order on the pricing page',

    `metadataJSON`          JSON                DEFAULT NULL
        COMMENT 'Provider mapping and future extension data (e.g. {"provider":"stripe","priceId":"price_..."}) — no schema change for provider IDs',

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

-- =============================================================================
-- 3. tblTierFeatures — TIER ↔ FEATURE ENTITLEMENTS
-- =============================================================================
-- Flexibility requirement satisfied: "any feature can be granted to any tier
-- at any granularity" — the tier/feature matrix is DATA. A tier's entitlement
-- for a feature is one row carrying a TYPED value:
--
--   valueBoolean — for valueType=boolean features
--   valueInt     — for limit/quota features (the numeric cap/allowance)
--   valueString  — for enum features
--   valueJSON    — for config features AND any complex/experimental value a
--                  simple column cannot express (the "normalised value + JSON
--                  for complex" requirement — resolver prefers the typed
--                  column, falls back to JSON)
--   isUnlimited  — EXPLICIT unlimited marker (1 → limit/quota is ∞). Kept
--                  separate from NULL so "unset" can never mean "unlimited"
--                  (the legacy schema overloads NULL=unlimited; the resolver
--                  maps isUnlimited=1 back to NULL in its legacy-shaped output)
--
-- EFFECTIVE-DATING (entitlement effective-dating requirement): a (tier,
-- feature) pair may hold MULTIPLE rows with different effectiveFrom values —
-- e.g. schedule "Pro gets 10 domains from 1 September" today. The resolver
-- picks the row with the latest effectiveFrom <= NOW() whose effectiveUntil
-- is NULL or > NOW(). The UNIQUE key includes a NULL-collapsed mirror of
-- effectiveFrom (same #150 idiom as tblSettings.settingScopeRefKey, using the
-- sentinel '1000-01-01' — a VALID date under NO_ZERO_DATE sql_mode) so two
-- undated rows for the same pair are impossible.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblTierFeatures` (
    `tierFeatureUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `tierID`                VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblSubscriptionTiers.tierID (same key tblSubscriptions/tblOrganisations use)',

    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID',

    `valueBoolean`          TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Typed value for boolean features (NULL = not this type)',

    `valueInt`              BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Typed value for limit/quota features (NULL = not this type OR unlimited — see isUnlimited)',

    `valueString`           VARCHAR(255)        DEFAULT NULL
        COMMENT 'Typed value for enum features (must be one of tblFeatures.allowedValues; enforced in PHP)',

    `valueJSON`             JSON                DEFAULT NULL
        COMMENT 'Value for config features / complex values beyond the typed columns',

    `isUnlimited`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Explicit unlimited marker for limit/quota features (1 = ∞; resolver outputs legacy NULL)',

    `effectiveFrom`         DATETIME            DEFAULT NULL
        COMMENT 'When this entitlement value starts applying (NULL = since forever) — enables scheduled tier changes',

    `effectiveUntil`        DATETIME            DEFAULT NULL
        COMMENT 'When this entitlement value stops applying (NULL = open-ended)',

    -- NULL-collapsing mirror of effectiveFrom for the UNIQUE key (#150 idiom
    -- from tblSettings): MySQL treats NULLs as distinct inside UNIQUE indexes,
    -- so without this two undated rows for the same (tier, feature) pair would
    -- both insert. '1000-01-01' is a legal DATETIME under the project's
    -- NO_ZERO_DATE sql_mode (000_create_database.sql) — NOT a zero-date.
    `effectiveFromKey`      DATETIME
                            GENERATED ALWAYS AS (COALESCE(`effectiveFrom`, '1000-01-01 00:00:00')) STORED
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

-- =============================================================================
-- 4. tblOrgFeatureOverrides — PER-ORG OVERRIDES / ADD-ONS / CUSTOM DEALS
-- =============================================================================
-- Flexibility requirement satisfied: "grant/deny/adjust a single feature for a
-- single org beyond its tier". This is the add-on / comp / custom-deal /
-- support-goodwill layer, applied ON TOP of the tier's entitlements by the
-- resolver (merge order: feature default → tier value → org override).
--
-- overrideMode semantics:
--   'grant'  — force a boolean feature ON for this org
--   'deny'   — force a feature OFF (e.g. abuse response: pull custom-HTML from
--              one org without touching its tier)
--   'set'    — replace the tier value outright (typed columns / isUnlimited),
--              e.g. a custom deal: maxLinks = 100,000 on Pro
--   'adjust' — ADD adjustDelta to the tier's numeric value (stackable
--              add-ons: "+5 domains" purchased twice = +10). Negative deltas
--              reduce. Resolver floors the result at 0
--
-- Effective-dating gives free trials of single features, seasonal comps, and
-- automatic expiry of purchased add-ons (billing renews by extending
-- effectiveUntil). sourceType+sourcePlanUID record WHY the override exists —
-- 'addon' rows point at the tblPricePlans addon plan that sold it.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblOrgFeatureOverrides` (
    `overrideUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle — the single org this override applies to',

    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — the single feature being overridden',

    `overrideMode`          ENUM('grant', 'deny', 'set', 'adjust')
                            NOT NULL DEFAULT 'set'
        COMMENT 'How this override combines with the tier value (see table header)',

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
        COMMENT 'adjust-mode: SIGNED delta applied to the tier''s numeric value (+5 domains, -100 links); NULL for other modes',

    `effectiveFrom`         DATETIME            DEFAULT NULL
        COMMENT 'When the override starts (NULL = immediately)',

    `effectiveUntil`        DATETIME            DEFAULT NULL
        COMMENT 'When the override lapses (NULL = until removed) — add-on renewals extend this',

    `sourceType`            ENUM('addon', 'comp', 'custom_deal', 'trial', 'support', 'migration')
                            NOT NULL DEFAULT 'custom_deal'
        COMMENT 'Why this override exists (audit/reporting): purchased add-on, goodwill comp, negotiated deal, feature trial, support action, data migration',

    `sourcePlanUID`         BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPricePlans.planUID when sourceType=addon/credit purchase — links the override to the plan that sold it',

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

-- =============================================================================
-- 5. tblCoupons — DISCOUNT/COUPON ENGINE (supersedes 2-type tblPaymentDiscounts)
-- =============================================================================
-- Flexibility requirement satisfied: "extend beyond the current 2 ENUM types to
-- cover intro, limited-time, lifetime, first-N-periods, stacking rules,
-- per-plan applicability" — WITHOUT altering tblPaymentDiscounts (additive
-- rule). tblPaymentDiscounts stays for historical rows and the legacy path;
-- legacyDiscountUID links a migrated coupon back to its old row.
--
--   discountKind:  'percentage' | 'fixed_amount' (the two legacy kinds)
--                  + 'free_periods'   (N periods at £0 — classic intro offer)
--                  + 'override_price' (charge exactly discountValue instead of
--                                      the plan price — clean intro pricing
--                                      without percentage rounding artefacts)
--   durationType:  'once'            (first payment only)
--                  'first_n_periods' (durationPeriods payments)
--                  'forever'         (every payment, i.e. a LIFETIME discount)
--                  'until_date'      (every payment until durationUntil)
--   Applicability: any combination of applicablePlanUID (one plan),
--                  applicableTierID (any plan of a tier), applicablePlanType
--                  (e.g. recurring only — keeps coupons off lifetime deals).
--                  All NULL = applies to everything.
--   Stacking:      isStackable=0 → never combines with another coupon.
--                  isStackable=1 → combines ONLY with coupons whose
--                  stackingGroup differs (same group = mutually exclusive,
--                  e.g. two 'sector' discounts can never stack). The master
--                  switch billing.coupon_stacking_enabled (seeded OFF) disables
--                  all stacking regardless.
--   Windows/caps:  validFrom/validUntil (limited-time), maxUses (global),
--                  perOrgMaxUses (per-customer), enforced via
--                  tblCouponRedemptions.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblCoupons` (
    `couponUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `couponCode`            VARCHAR(50)         DEFAULT NULL
        COMMENT 'Redeemable code (NULL = automatic discount applied by rule, mirroring tblPaymentDiscounts.discountCode)',

    `couponName`            VARCHAR(255)        NOT NULL
        COMMENT 'Display name (e.g. "Launch offer — 25% off 3 months")',

    `discountKind`          ENUM('percentage', 'fixed_amount', 'free_periods', 'override_price')
                            NOT NULL DEFAULT 'percentage'
        COMMENT 'Discount mechanics (see table header)',

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
        COMMENT 'Restrict to any plan of one tier (NULL = no tier restriction; replaces tblPaymentDiscounts.applicableTier as a real FK)',

    `applicablePlanType`    ENUM('recurring', 'lifetime', 'one_off', 'payg', 'payg_capped', 'addon', 'credit_pack')
                            DEFAULT NULL
        COMMENT 'Restrict to a plan type (e.g. recurring only, keeping coupons off lifetime deals); NULL = any',

    `minAmount`             DECIMAL(10, 2)      DEFAULT NULL
        COMMENT 'Minimum pre-discount charge for the coupon to apply (NULL = none)',

    `maxUses`               INT UNSIGNED        DEFAULT NULL
        COMMENT 'Global redemption cap (NULL = unlimited)',

    `currentUses`           INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Running redemption count (kept in step with tblCouponRedemptions by the redemption flow)',

    `perOrgMaxUses`         SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Per-organisation redemption cap (NULL = unlimited; typical value 1)',

    `isStackable`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this coupon may combine with others (subject to stackingGroup + master switch)',

    `stackingGroup`         VARCHAR(50)         DEFAULT NULL
        COMMENT 'Coupons sharing a group are mutually exclusive even when stackable (e.g. all sector discounts in group "sector")',

    `legacyDiscountUID`     BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblPaymentDiscounts.discountUID when this coupon was migrated from the legacy table (audit/continuity)',

    `validFrom`             DATETIME            DEFAULT NULL
        COMMENT 'Redemption window start (limited-time offers)',

    `validUntil`            DATETIME            DEFAULT NULL
        COMMENT 'Redemption window end',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'DISABLED BY DEFAULT (0) — a coupon must be explicitly activated, matching the plan posture',

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

-- =============================================================================
-- 6. tblCouponRedemptions — WHO USED WHICH COUPON (enforces caps)
-- =============================================================================
-- Enforcement backing for tblCoupons.maxUses / perOrgMaxUses, and the audit
-- trail tying a discount to the subscription/payment it touched (tblPayments
-- keeps its own discountAmount column untouched — compatibility rule).
-- =============================================================================
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
        COMMENT 'Total discounted so far under this redemption (updated on each discounted renewal)',

    `periodsApplied`        SMALLINT UNSIGNED   NOT NULL DEFAULT 0
        COMMENT 'How many billing periods have received this discount (drives first_n_periods expiry)',

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

-- =============================================================================
-- 7. tblSubscriptionPlans — SUBSCRIPTION ↔ PRICE-PLAN PIN (GRANDFATHERING)
-- =============================================================================
-- Compatibility requirement satisfied: "Keep tblSubscriptions/tblPayments
-- compatible" — instead of ALTERing tblSubscriptions to add a planUID column,
-- this bridge table attaches plan context to an existing subscription row.
-- tblSubscriptions keeps carrying tierID/billingCycle/status/periods exactly
-- as today (legacy code keeps working); the pricing engine additionally knows
-- WHICH plan, at WHAT pinned price.
--
-- GRANDFATHERING: priceAtSubscription / capAmountAtSubscription freeze the
-- deal at purchase time. Repricing a plan or retiring it (isActive=0 /
-- validUntil passed) never changes what an existing subscriber pays — their
-- pinned row rules until THEY change plan (which writes a new row and closes
-- the old one via isCurrent/effectiveUntil, preserving full plan history).
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblSubscriptionPlans` (
    `subscriptionPlanUID`   BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `subscriptionUID`       BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblSubscriptions.subscriptionUID — the legacy subscription row this plan context attaches to',

    `planUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblPricePlans.planUID — the exact plan subscribed',

    `priceAtSubscription`   DECIMAL(10, 2)      NOT NULL
        COMMENT 'GRANDFATHER PIN: the per-interval amount agreed at purchase — future plan repricing never touches this subscriber',

    `currencyAtSubscription` CHAR(3)            NOT NULL DEFAULT 'GBP'
        COMMENT 'Currency of the pinned price (ISO 4217)',

    `seats`                 SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'Purchased seat count for per-seat plans (NULL = per-org plan)',

    `perSeatPriceAtSubscription` DECIMAL(10, 2) DEFAULT NULL
        COMMENT 'GRANDFATHER PIN for the per-seat component (NULL = plan had none)',

    `capAmountAtSubscription` DECIMAL(10, 2)    DEFAULT NULL
        COMMENT 'GRANDFATHER PIN for a payg_capped cap — "never more than Pro at the price Pro was when I signed up"',

    `isCurrent`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1 = the active plan row for this subscription; plan changes close the old row (0) and insert a new one — full history retained',

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

-- =============================================================================
-- 8. tblUsageCounters — AGGREGATED USAGE METERING (PAYG + caps + quotas)
-- =============================================================================
-- Flexibility requirement satisfied: "Usage metering to support PAYG + caps."
-- One row per (org, meterable feature, period) holding an aggregate count —
-- cheap to read on every gate check, cheap to bump on every metered action
-- (single UPSERT), suitable for Dreamhost shared hosting (no cron assumed;
-- period rows are created lazily on first use, old rows pruned
-- probabilistically like tblAPIRequestLog's 1-in-100 sweep).
--
-- periodType 'day' backs daily quotas (api.requests_per_day), 'month' backs
-- calendar-month PAYG billing, 'billing_period' backs metering aligned to a
-- subscription's own anniversary (periodStart = currentPeriodStart date).
-- The PAYG invoice for a period = SUM over counters × plan unitPrice, minus
-- includedUnits and credits, capped per plan capAmount/equivalentPlan.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblUsageCounters` (
    `counterUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `featureUID`            BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblFeatures.featureUID — must be an isMeterable feature (enforced in PHP)',

    `periodType`            ENUM('day', 'month', 'billing_period')
                            NOT NULL DEFAULT 'month'
        COMMENT 'Aggregation window kind (see table header)',

    `periodStart`           DATE                NOT NULL
        COMMENT 'First day of the window (the day itself for day; month start for month; subscription anniversary for billing_period)',

    `usedCount`             BIGINT UNSIGNED     NOT NULL DEFAULT 0
        COMMENT 'Units consumed in this window (atomic UPSERT: INSERT … ON DUPLICATE KEY UPDATE usedCount = usedCount + n)',

    `lastEventAt`           DATETIME            DEFAULT NULL
        COMMENT 'Timestamp of the most recent metered event (staleness checks/debug)',

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

-- =============================================================================
-- 9. tblUsageCredits — PURCHASED/GRANTED UNIT CREDITS (credit packs)
-- =============================================================================
-- Backs the credit-pack mechanic (tblPricePlans.planType='credit_pack') and
-- goodwill/referral unit grants: a bucket of prepaid units for one meterable
-- feature, drawn down before PAYG charging kicks in. Oldest non-expired
-- bucket is consumed first (FIFO in PHP).
-- =============================================================================
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

-- =============================================================================
-- 10. tblUsageEvents — OPTIONAL FINE-GRAINED USAGE LEDGER (audit / disputes)
-- =============================================================================
-- The counters table is the OPERATIONAL source; this ledger is the AUDIT
-- source for PAYG billing disputes ("show me the 4,213 API calls you billed").
-- Writing here is gated by 'billing.usage_metering_enabled' AND is safe to
-- disable independently — billing maths only reads counters. eventRef gives
-- idempotent recording (retried requests carrying the same ref insert once:
-- multiple NULLs are permitted in a UNIQUE index, real refs dedupe).
-- Pruned probabilistically (1-in-100 request sweep, retention setting below),
-- mirroring the tblAPIRequestLog pattern — no cron dependency on shared
-- hosting.
-- =============================================================================
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
        COMMENT 'Caller-supplied idempotency reference (NULL allowed repeatedly; non-NULL values are unique per feature)',

    `contextJSON`           JSON                DEFAULT NULL
        COMMENT 'Optional event context for audit (endpoint, shortURL id, …) — never PII beyond what tblActivityLog already holds',

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

-- =============================================================================
-- 11. VERIFICATION VIEW — legacy-shaped read of the NEW model
-- =============================================================================
-- vwSubscriptionTierEntitlements pivots tblTierFeatures back into the exact
-- column shape of tblSubscriptionTiers' hard-coded columns. Purpose:
--   (a) MIGRATION VERIFICATION: after the seed-018 backfill,
--         SELECT ... FROM tblSubscriptionTiers t
--         JOIN vwSubscriptionTierEntitlements v USING (tierID)
--       must show identical values column-for-column (see
--       tests/integration/pricing_backfill_verify.php);
--   (b) TRANSITION AID: any legacy reporting query can point at the view.
-- The view only reads CURRENTLY-EFFECTIVE undated-or-started rows; the PHP
-- resolver (not this view) is the runtime authority.
-- CREATE OR REPLACE is idempotent; a view is additive (no data, no triggers).
-- =============================================================================
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

-- End of file.
