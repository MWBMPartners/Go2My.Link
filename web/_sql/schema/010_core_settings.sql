-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Core Settings Table
-- =============================================================================
-- Merged settings dictionary and values into a single table.
-- Supports scope hierarchy: User > Organisation > System > Default.
-- Sensitive values are encrypted with AES-256-GCM (isSensitive flag).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.3.0
-- @since      Phase 1
--
-- Reference: https://dev.mysql.com/doc/refman/8.0/en/create-table.html
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Settings Definition + Values (merged from tblSettings + tblSettingsDictionary)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblSettings` (
    `settingUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `settingID`             VARCHAR(100)        NOT NULL
        COMMENT 'Setting key identifier (e.g., site.name, redirect.max_hops)',

    `settingScope`          ENUM('Default', 'System', 'Organisation', 'User')
                            NOT NULL DEFAULT 'Default'
        COMMENT 'Scope level at which this setting applies',

    `settingScopeRef`       VARCHAR(100)        DEFAULT NULL
        COMMENT 'Reference for scope context — orgHandle for Organisation, userUID for User, NULL for Default/System',

    -- NULL-collapsing mirror of settingScopeRef (#150). MySQL treats NULLs as
    -- DISTINCT inside a UNIQUE index, so a raw settingScopeRef column never
    -- deduplicates System/Default rows (where settingScopeRef IS NULL) — a
    -- unique key including it silently permits duplicate (settingID, 'System',
    -- NULL) rows. This STORED generated column rewrites NULL to '' so those
    -- rows collide in UQ_setting_scope, while the base settingScopeRef column
    -- is left completely untouched (every "settingScopeRef IS NULL" read path
    -- keeps its exact semantics). It inherits the table's utf8mb4_unicode_ci
    -- collation, so the '' comparison in the unique key is exact.
    `settingScopeRefKey`    VARCHAR(100)
                            GENERATED ALWAYS AS (COALESCE(`settingScopeRef`, '')) STORED
        COMMENT 'NULL-collapsed mirror of settingScopeRef (NULL becomes empty string) so System/Default rows dedupe in UQ_setting_scope (#150)',

    `settingValue`          TEXT                DEFAULT NULL
        COMMENT 'Current active value (encrypted if isSensitive = 1)',

    `settingDefault`        VARCHAR(500)        DEFAULT NULL
        COMMENT 'Default value used if no override exists at this scope',

    `settingValuesAllowed`  TEXT                DEFAULT NULL
        COMMENT 'Newline-separated list of allowed values (NULL = any)',

    `settingDescription`    TEXT                DEFAULT NULL
        COMMENT 'Human-readable description of this setting',

    `settingDataType`       ENUM('string', 'integer', 'float', 'boolean', 'json', 'url', 'email')
                            NOT NULL DEFAULT 'string'
        COMMENT 'Expected data type for validation',

    `isSensitive`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this value should be stored encrypted (1 = yes)',

    `isEditable`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this setting can be changed via the UI (0 = code-only)',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Record creation timestamp (UTC)',

    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'Last modification timestamp (UTC)',

    PRIMARY KEY (`settingUID`),

    -- Each setting key is unique within its scope + scope reference. The scope
    -- reference is compared via settingScopeRefKey (COALESCE(settingScopeRef,''))
    -- so System/Default rows (settingScopeRef IS NULL) also deduplicate — a raw
    -- settingScopeRef column would let NULL-scope rows slip past the UNIQUE
    -- index and accumulate duplicates (#150).
    UNIQUE KEY `UQ_setting_scope` (`settingID`, `settingScope`, `settingScopeRefKey`),

    -- Fast lookup by scope
    INDEX `IDX_settings_scope` (`settingScope`),

    -- Fast lookup by setting key
    INDEX `IDX_settings_id` (`settingID`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Application settings with scope hierarchy and encrypted sensitive values';
