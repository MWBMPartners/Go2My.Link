-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 021: LinksPage feature registry and plan values (LP-01, #216)
-- =============================================================================
-- Brings an ALREADY-INSTALLED database up to date with
-- web/_sql/seeds/023_linkspage_feature_registry.sql: it registers every
-- LinksPage programme feature in tblFeatures and gives the four shipped plans
-- (free / basic / premium / enterprise) an explicit value for each one in
-- tblTierFeatures. The statements below are copied UNCHANGED from that seed —
-- see its header for the table of proposed values, why the registry is used
-- instead of new has* / max* columns, and how the owner moves a feature
-- between plans with one UPDATE.
--
-- WHY A LIVE DATABASE NEEDS THIS. Without these rows every LinksPage feature
-- gated through web/_functions/entitlements.php's g2ml_featureAllowed() is
-- answered "no" for every customer: that function fails closed on a feature
-- name it cannot find, so a paid plan would silently lose the extras it pays
-- for. (It can never block creating a link, a redirect or a login.)
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: seed 023 is part of the base
--     install (the web installer and CI import web/_sql/seeds/ in filename
--     order). Run this migration ONLY on a database installed before seed 023
--     existed. Running it on a fresh install anyway is harmless.
--
-- PREREQUISITE: migration 020 (web/_sql/migrations/020_pricing_engine.sql) —
-- or a fresh install of schema 036 and seed 018 — must already have been
-- applied. It creates tblFeatures and tblTierFeatures; without tblFeatures
-- the first statement below fails with "table doesn't exist" and nothing is
-- written. If seed 018's three older LinksPage rows (all_templates, agegate,
-- custom_domain) are missing, section 3 simply writes nothing for them.
--
-- RE-RUN MIGRATION 020 BEFORE THIS FILE, EVEN IF IT WAS APPLIED LONG AGO.
-- LP-01 corrected the stored description of billing.pricing_engine_enabled,
-- which used to say the pricing tables are "completely inert" with the switch
-- off; that stopped being true when the gate below started reading them.
-- This file deliberately does not touch tblSettings, because its statements
-- must stay identical to seed 023's, so a re-run of 020 is the only way the
-- corrected text reaches an existing database. A re-run of 020 needs
-- migration 019 in place first. docs/DEPLOYMENT.md ("Migration 021 — the
-- LinksPage feature registry", step 2) has the checks for both.
--
-- ⚠️  MARIADB (Dreamhost) — FIXED 2026-09-21, ISSUE #183. Schema 036 /
--     migration 020 used to fail to create tblTierFeatures on MariaDB 11.4
--     (its generated column effectiveFromKey was refused with ERROR 1901,
--     even with the earlier #186 CAST); on such a database section 1 below
--     used to succeed (tblFeatures exists) while every tblTierFeatures
--     statement failed, so the gate denied every LinksPage extra for every
--     plan (it fails closed; links, redirects and logins were never
--     affected). #183 replaced that CAST with a plain TIMESTAMP literal,
--     which both engines accept — see the comment above effectiveFromKey in
--     web/_sql/schema/036_pricing_engine.sql for the full story. Confirmed
--     fixed on 2026-09-21 by importing schema 036 and this file's statements
--     into a throwaway MariaDB 11.4 container: 0 errors, tblTierFeatures
--     created, every row written. .github/workflows/mariadb-import.yml
--     checks the fresh-install files (web/_sql/schema, procedures, seeds) on
--     MariaDB 11.4 on every change under web/_sql/, but it does NOT import
--     web/_sql/migrations — this file is never run by that workflow, so a
--     MariaDB-only regression written into a migration still has to be
--     checked on MariaDB by hand, and even for the files it does cover the
--     workflow is a warning check, not a required one.
--     If you are running this migration against a database that still fails
--     here, it was installed before the #183 fix — apply the corrected
--     migration 020 first, then re-run this file.
--
-- NO ALTERs, NO GUARD PROCEDURES NEEDED. Like migration 020, this only ADDS
-- rows with idempotent INSERT … ON DUPLICATE KEY UPDATE statements, so there
-- is no half-applied state to guard against: re-running the whole file is
-- always safe. A re-run refreshes feature descriptions only and NEVER
-- overwrites a plan value an operator has changed since.
--
-- ZERO SETTING CHANGES. The pricing engine's master switch
-- (billing.pricing_engine_enabled) stays exactly as it is. The new gate reads
-- these rows with the switch off or on.
--
-- ⚠️  BUT CHECK THE SWITCHES BEFORE DEPLOYING THIS RELEASE. This file changes
--     no setting, but the PHP code shipped with it changes what a stored
--     switch value MEANS. Until LP-01, web/_functions/pricing.php ignored a
--     stored "on" ('1', 'true', 'yes' or 'on') for
--     billing.pricing_engine_enabled, billing.usage_metering_enabled and
--     billing.usage_event_log_enabled, because of a bug. After LP-01, a
--     stored "on" takes effect at once. Before deploying, confirm all three
--     rows in the live tblSettings are '0'. The query, and what to do if one
--     is not, are in docs/DATABASE.md under "Before deploying LP-01".
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      2026-09-21 — LinksPage programme, LP-01 (#216)
--
-- 📖 References:
--     - Fresh-install source of truth: web/_sql/seeds/023_linkspage_feature_registry.sql
--     - Tables:                        web/_sql/schema/036_pricing_engine.sql
--     - Previous migration:            web/_sql/migrations/020_pricing_engine.sql
--     - The gate:                      web/_functions/entitlements.php
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Registry rows — one per LinksPage feature, including the ones that are
--    registered now but not built yet (so their plan values can be agreed and
--    stored before the code exists).
--
--    Every default below is the SAFE FLOOR: "off" for yes/no features, and the
--    Free plan's 30 days for the statistics window. The default is what a plan
--    gets if it has NO tblTierFeatures row, so a plan added later can never be
--    handed a paid extra by accident. linkspage.click_tracking is "on" for
--    every shipped plan (section 2) but its default is still "off", for that
--    same reason.
--
--    A re-run only refreshes the description. It never changes a name, a
--    type, a default or isActive that an operator may have edited.
-- -----------------------------------------------------------------------------
INSERT INTO `tblFeatures` (
    `featureSlug`, `featureName`, `featureDescription`,
    `valueType`, `valueUnit`, `quotaPeriod`,
    `defaultValueBoolean`, `defaultValueInt`, `defaultIsUnlimited`,
    `category`, `isMeterable`, `legacyColumn`, `sortOrder`, `isActive`
) VALUES
('linkspage.hide_branding', 'Hide Lnks.page branding',
 'Leaves the "Powered by Lnks.page" line off the public LinksPage (LP-03).',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 60, 1),
('linkspage.seo', 'LinksPage search and sharing controls',
 'Custom page title, description and share image for search engines and social previews, and the option to ask search engines not to list the page (LP-04).',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 70, 1),
('linkspage.click_tracking', 'LinksPage click tracking',
 'Counts clicks on each link on a LinksPage (LP-05). The default is off as a safe floor; every shipped plan has an explicit row turning it on.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 80, 1),
('linkspage.analytics_retention_days', 'LinksPage statistics history',
 'How many days of LinksPage click statistics the organisation can see. Unlimited means all of it (LP-06).',
 'limit', 'days', NULL, NULL, 30, 0, 'linkspage', 0, NULL, 90, 1),
('linkspage.scheduled_links', 'LinksPage scheduled links',
 'Show or hide a link on a LinksPage between chosen dates and times (LP-07). Only setting a schedule is gated; a schedule already saved is always honoured.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 100, 1),
('linkspage.password_protect', 'Password-protected LinksPage',
 'Visitors must enter a password before they can see the page (LP-13). Registered now; not built yet.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 110, 1),
('linkspage.image_upload', 'LinksPage image upload',
 'Upload the avatar and link icon images instead of pointing at an image elsewhere on the web (LP-16). Registered now; not built yet.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 120, 1),
('linkspage.verified_badge', 'LinksPage verified badge',
 'A badge showing the page owner has been verified (LP-17). Registered now; not built yet.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 130, 1),
('linkspage.lead_capture', 'LinksPage contact capture form',
 'A form on the page that collects visitor email addresses or contact details (LP-18). Registered now; not built yet; needs legal sign-off first.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 140, 1),
('linkspage.tracking_pixels', 'LinksPage tracking pixels',
 'Third-party analytics and advertising pixels on the page (LP-19). Registered now; not built yet; needs legal sign-off first.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 150, 1),
('linkspage.embeds', 'LinksPage rich embeds',
 'Embedded players such as YouTube and Spotify on the page (LP-20). Registered now; not built yet.',
 'boolean', NULL, NULL, 0, NULL, 0, 'linkspage', 0, NULL, 160, 1)
ON DUPLICATE KEY UPDATE
    `featureDescription` = VALUES(`featureDescription`);

-- -----------------------------------------------------------------------------
-- 2. Per-plan rows — the values the four shipped plans actually get.
--
--    THESE ARE THE PROPOSED VALUES FROM ISSUE #216, FOR THE OWNER TO CONFIRM.
--    Changing one later is a single UPDATE on tblTierFeatures; no code change.
--
--    One INSERT … SELECT per feature, written out plan by plan so each value
--    can be read at a glance. Only the four shipped plans are touched (the
--    WHERE clause), so a throwaway or custom plan never gets rows it did not
--    ask for — it falls back to the safe-floor default from section 1.
--
--    Idempotent via the UQ_tierfeature unique key (tierID, featureUID,
--    undated effectiveFrom) + an ON DUPLICATE KEY no-op: a re-run inserts
--    nothing and NEVER overwrites a value an operator has since changed. A
--    plan or feature that does not exist is simply skipped by the JOIN.
-- -----------------------------------------------------------------------------

-- linkspage.hide_branding: Free no · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.hide_branding'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.seo: Free no · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.seo'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.click_tracking: Free yes · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 1
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.click_tracking'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.analytics_retention_days: Free 30 · Basic 90 · Premium 365 ·
-- Enterprise unlimited. Unlimited is stored as isUnlimited = 1 with valueInt
-- NULL — the same convention seed 018 uses for the legacy NULL-means-unlimited
-- limits — and g2ml_featureLimit() reports it as 'limit' => null.
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueInt`, `isUnlimited`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 30
           WHEN 'basic'      THEN 90
           WHEN 'premium'    THEN 365
           WHEN 'enterprise' THEN NULL
       END,
       CASE t.`tierID`
           WHEN 'enterprise' THEN 1
           ELSE 0
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.analytics_retention_days'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.scheduled_links: Free no · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.scheduled_links'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.password_protect: Free no · Basic no · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 0
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.password_protect'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.image_upload: Free no · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.image_upload'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.verified_badge: Free no · Basic no · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 0
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.verified_badge'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.lead_capture: Free no · Basic no · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 0
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.lead_capture'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.tracking_pixels: Free no · Basic no · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 0
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.tracking_pixels'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.embeds: Free no · Basic no · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 0
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.embeds'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- -----------------------------------------------------------------------------
-- 3. Truthful rows for three LinksPage features seed 018 registered but never
--    gave any plan a value. Without these, the registry says "off for every
--    plan" (the safe-floor default), which does not match what the product
--    actually does today. Nothing in the LinksPage programme enforces these
--    three through the registry; the rows exist so the registry DESCRIBES
--    today's behaviour correctly for anyone reading it or switching the
--    pricing engine on.
--      linkspage.all_templates — every plan sees all system templates today.
--      linkspage.agegate       — every plan can use the age gate today.
--      linkspage.custom_domain — needs a verified custom domain, and Free
--                                allows 0 custom domains (maxCustomDomains).
-- -----------------------------------------------------------------------------

-- linkspage.all_templates: Free yes · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 1
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.all_templates'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.agegate: Free yes · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 1
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.agegate'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;

-- linkspage.custom_domain: Free no · Basic yes · Premium yes · Enterprise yes
INSERT INTO `tblTierFeatures` (`tierID`, `featureUID`, `valueBoolean`)
SELECT t.`tierID`, f.`featureUID`,
       CASE t.`tierID`
           WHEN 'free'       THEN 0
           WHEN 'basic'      THEN 1
           WHEN 'premium'    THEN 1
           WHEN 'enterprise' THEN 1
       END
FROM   `tblSubscriptionTiers` t
JOIN   `tblFeatures` f ON f.`featureSlug` = 'linkspage.custom_domain'
WHERE  t.`tierID` IN ('free', 'basic', 'premium', 'enterprise')
ON DUPLICATE KEY UPDATE `tierFeatureUID` = `tblTierFeatures`.`tierFeatureUID`;
