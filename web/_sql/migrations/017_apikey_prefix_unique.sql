-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 017: tblAPIKeys.apiKeyPrefix UNIQUE index (#149.4)
-- =============================================================================
-- Adds UQ_apikey_prefix (apiKeyPrefix), replacing the old non-unique
-- IDX_api_key_prefix. The prefix is only a 48-bit random value (6 bytes,
-- G2ML_API_KEY_PREFIX_BYTES in web/_functions/api_auth.php) — a collision is
-- astronomically unlikely but was previously Informational-severity: a
-- collision would silently let g2ml_apiVerifyKey()'s
-- "WHERE apiKeyPrefix = ?" lookup return either row (never a forgery vector,
-- since the full apiKey hash comparison still gates auth — but an
-- availability edge for whichever key sorts second). This migration turns
-- that edge case into a hard database guarantee instead.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same UNIQUE key is already
--     part of the base schema (web/_sql/schema/031_api.sql). Run this
--     migration ONLY to bring an ALREADY-IMPORTED earlier schema up to date.
--
-- IDEMPOTENT BY DESIGN (mirrors migration 011): checks information_schema
-- first and only acts when UQ_apikey_prefix is absent, via a throwaway
-- stored procedure that drops itself afterwards. Safe to re-run any number
-- of times.
--
-- ⚠️  PRE-REQUISITE: this ALTER will fail with errno 1062 if the deployed
--     table already contains two rows sharing the same apiKeyPrefix (a
--     genuine — if astronomically unlikely — collision that predates this
--     fix). If that happens, an operator must first identify and regenerate
--     one of the colliding keys (g2ml_apiGenerateKey() mints a fresh prefix
--     each time) before re-running this migration.
--
-- g2ml_apiGenerateKey() (web/_functions/api_auth.php) was updated alongside
-- this migration to retry generation with a freshly-drawn prefix+secret on a
-- duplicate-key collision (mirrors the shortCode TOCTOU retry pattern in
-- web/Go2My.Link/_functions/shorturl_create.php), so this UNIQUE constraint
-- can never surface to a caller as an unhandled 500.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      launch-prep/2026-07-09 (#149.4)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_migration_017_add_apikey_prefix_unique`//

CREATE PROCEDURE `sp_migration_017_add_apikey_prefix_unique`()
BEGIN
    DECLARE v_uniqueIndexExists INT DEFAULT 0;
    DECLARE v_oldIndexExists    INT DEFAULT 0;

    SELECT COUNT(*)
    INTO   v_uniqueIndexExists
    FROM   information_schema.STATISTICS
    WHERE  TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'tblAPIKeys'
       AND INDEX_NAME   = 'UQ_apikey_prefix';

    IF v_uniqueIndexExists = 0 THEN

        SELECT COUNT(*)
        INTO   v_oldIndexExists
        FROM   information_schema.STATISTICS
        WHERE  TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'tblAPIKeys'
           AND INDEX_NAME   = 'IDX_api_key_prefix';

        IF v_oldIndexExists > 0 THEN
            ALTER TABLE `tblAPIKeys`
                DROP INDEX `IDX_api_key_prefix`;
        END IF;

        ALTER TABLE `tblAPIKeys`
            ADD UNIQUE KEY `UQ_apikey_prefix` (`apiKeyPrefix`);

    END IF;
END//

DELIMITER ;

CALL `sp_migration_017_add_apikey_prefix_unique`();

DROP PROCEDURE IF EXISTS `sp_migration_017_add_apikey_prefix_unique`;
