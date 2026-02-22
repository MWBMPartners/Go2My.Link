-- =============================================================================
-- GoToMyLink — Migration 006: Settings
-- =============================================================================
-- Migrates 23 setting definitions from tblSettingsDictionary and 1 setting
-- value from tblSettings. Expands with new settings for the platform.
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

SET time_zone = '+00:00';

-- =========================================================================
-- Step 1: Migrate setting definitions as Default-scope entries
-- =========================================================================
INSERT INTO `tblSettings` (
    `settingID`,
    `settingScope`,
    `settingScopeRef`,
    `settingValue`,
    `settingDefault`,
    `settingValuesAllowed`,
    `settingDescription`,
    `settingDataType`,
    `isSensitive`,
    `createdAt`,
    `updatedAt`
)
SELECT
    old.`settingsID`                AS `settingID`,
    'Default'                       AS `settingScope`,
    NULL                            AS `settingScopeRef`,
    old.`settingsValueDefault`      AS `settingValue`,
    old.`settingsValueDefault`      AS `settingDefault`,
    old.`settingValuesAllowed`      AS `settingValuesAllowed`,
    old.`settingsDesc`              AS `settingDescription`,
    'string'                        AS `settingDataType`,
    0                               AS `isSensitive`,
    old.`settingsDateCreated`       AS `createdAt`,
    old.`settingsLastUpdated`       AS `updatedAt`
FROM `mwtools_mwlink`.`tblSettingsDictionary` old
ON DUPLICATE KEY UPDATE
    `settingDefault` = VALUES(`settingDefault`),
    `updatedAt` = NOW();

-- =========================================================================
-- Step 2: Migrate active setting values as System-scope overrides
-- =========================================================================
INSERT INTO `tblSettings` (
    `settingID`,
    `settingScope`,
    `settingScopeRef`,
    `settingValue`,
    `settingDescription`,
    `settingDataType`,
    `createdAt`,
    `updatedAt`
)
SELECT
    old.`settingsID`                AS `settingID`,
    CASE old.`settingsScope`
        WHEN 'System' THEN 'System'
        WHEN 'Organisation' THEN 'Organisation'
        WHEN 'User' THEN 'User'
        ELSE 'System'
    END                             AS `settingScope`,
    old.`settingsGroup`             AS `settingScopeRef`,
    old.`settingsValue`             AS `settingValue`,
    NULL                            AS `settingDescription`,
    'string'                        AS `settingDataType`,
    old.`settingsDateCreated`       AS `createdAt`,
    old.`settingsLastUpdated`       AS `updatedAt`
FROM `mwtools_mwlink`.`tblSettings` old
ON DUPLICATE KEY UPDATE
    `settingValue` = VALUES(`settingValue`),
    `updatedAt` = NOW();

-- =========================================================================
-- Verification
-- =========================================================================
SELECT 'Migration 006 — Settings' AS `migration`,
       COUNT(*) AS `total_settings`,
       SUM(CASE WHEN `settingScope` = 'Default' THEN 1 ELSE 0 END) AS `defaults`,
       SUM(CASE WHEN `settingScope` != 'Default' THEN 1 ELSE 0 END) AS `overrides`
FROM `tblSettings`;
