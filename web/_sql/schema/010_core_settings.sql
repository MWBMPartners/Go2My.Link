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
-- @version    0.2.0
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

    -- Each setting key is unique within its scope + scope reference
    UNIQUE KEY `UQ_setting_scope` (`settingID`, `settingScope`, `settingScopeRef`),

    -- Fast lookup by scope
    INDEX `IDX_settings_scope` (`settingScope`),

    -- Fast lookup by setting key
    INDEX `IDX_settings_id` (`settingID`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Application settings with scope hierarchy and encrypted sensitive values';
