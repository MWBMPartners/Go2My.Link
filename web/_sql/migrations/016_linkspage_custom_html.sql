-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 016: LinksPage custom-HTML entitlement + kill-switch (#49)
-- =============================================================================
-- Component C.6 (#49) lets a premium org supply free-form custom HTML/CSS for a
-- LinksPage. This migration wires the two GATES that keep that safe onto an
-- ALREADY-IMPORTED earlier schema:
--
--   1. tblSubscriptionTiers.hasCustomHTML — the per-tier premium entitlement
--      flag (consumed by web/_functions/entitlements.php's g2ml_canUseFeature()
--      via the new 'hasCustomHTML' whitelist entry). Backfilled ON (1) for the
--      'premium' and 'enterprise' tiers to match the fresh-install seed
--      (web/_sql/seeds/001_subscription_tiers.sql); left OFF (0) for every
--      other tier.
--
--   2. tblSettings 'linkspage.custom_html_enabled' — the operator KILL-SWITCH,
--      inserted OFF ('0') so the feature stays disabled everywhere until an
--      operator explicitly enables it (matching
--      web/_sql/seeds/003_default_settings.sql).
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the hasCustomHTML column is already
--     in the base schema (web/_sql/schema/011_core_subscription_tiers.sql), the
--     tier values are seeded by 001_subscription_tiers.sql, and the kill-switch
--     is seeded by 003_default_settings.sql. Run this migration ONLY to bring an
--     ALREADY-IMPORTED earlier schema up to date.
--
-- IDEMPOTENT BY DESIGN (mirrors migration 013's pattern): the ADD COLUMN step is
-- guarded by information_schema via a throwaway stored procedure that drops
-- itself afterwards, and the backfill UPDATE / settings INSERT are naturally
-- idempotent (the UPDATE's WHERE excludes already-set rows; the INSERT uses
-- ON DUPLICATE KEY UPDATE and never clobbers an operator's chosen value).
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#49)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Add the hasCustomHTML entitlement column, guarded so this is safe to
--    re-run against a schema that already has it (e.g. a fresh import).
-- -----------------------------------------------------------------------------
DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_016_add_has_custom_html_column`//

CREATE PROCEDURE `sp_migration_016_add_has_custom_html_column`()
BEGIN
    DECLARE v_columnExists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_columnExists
    FROM   information_schema.COLUMNS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblSubscriptionTiers'
       AND COLUMN_NAME  = 'hasCustomHTML';

    IF v_columnExists = 0 THEN
        ALTER TABLE `tblSubscriptionTiers`
            ADD COLUMN `hasCustomHTML` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'Whether LinksPage custom-HTML/CSS (Component C.6, #49) is available — high-risk, premium only'
                AFTER `hasPrioritySupport`;
    END IF;
END//

DELIMITER ;

CALL `sp_migration_016_add_has_custom_html_column`();

DROP PROCEDURE IF EXISTS `sp_migration_016_add_has_custom_html_column`;

-- -----------------------------------------------------------------------------
-- 2. Backfill the premium/enterprise tiers to grant the entitlement, matching
--    the fresh-install seed. Naturally idempotent (only touches rows not
--    already set to 1).
-- -----------------------------------------------------------------------------
UPDATE `tblSubscriptionTiers`
   SET `hasCustomHTML` = 1
 WHERE `tierID` IN ('premium', 'enterprise')
   AND `hasCustomHTML` <> 1;

-- -----------------------------------------------------------------------------
-- 3. Insert the operator kill-switch OFF by default. ON DUPLICATE KEY UPDATE
--    only refreshes the description — it never resets an operator's own chosen
--    settingValue on a re-run.
-- -----------------------------------------------------------------------------
INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
('linkspage.custom_html_enabled', 'Default', NULL,
 '0', '0', 'Master kill-switch for LinksPage custom HTML/CSS (Component C.6, #49). OFF by default — the single highest stored-XSS-risk feature. Even when ON, custom HTML is premium-gated (hasCustomHTML), sanitised on input and output, and served under a strict script-src none CSP.',
 'boolean', 0, 1)
ON DUPLICATE KEY UPDATE
    `settingDescription` = VALUES(`settingDescription`);
