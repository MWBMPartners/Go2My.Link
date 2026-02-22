-- =============================================================================
-- GoToMyLink — Migration 007: Activity Log (Optional)
-- =============================================================================
-- Migrates ~429K activity log entries from mwtools_mwlink.tblActivityLog.
--
-- THIS MIGRATION IS OPTIONAL due to the large volume of data.
-- Run in batches if the server has limited resources.
--
-- Maps legacy columns to the new structured schema.
-- Note: Geo/UA data (browserName, osName, etc.) is NOT populated during
-- migration — those columns are populated by the new application.
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
-- Batch migration: Insert in chunks of 10,000 rows
-- Adjust the LIMIT and OFFSET as needed for your server.
-- Run this script multiple times, incrementing the offset.
-- =========================================================================

-- Batch 1 (first 10,000 rows)
-- To run subsequent batches, change OFFSET to 10000, 20000, etc.

INSERT INTO `tblActivityLog` (
    `logAction`,
    `logStatus`,
    `shortCode`,
    `destinationURL`,
    `requestDomain`,
    `requestPath`,
    `requestMethod`,
    `requestReferer`,
    `requestUserAgent`,
    `ipAddress`,
    `logData`,
    `createdAt`
)
SELECT
    IFNULL(old.`logAction`, 'redirect')
                                    AS `logAction`,
    IFNULL(old.`logStatus`, 'unknown')
                                    AS `logStatus`,
    old.`logRequestShortCode`       AS `shortCode`,
    old.`logDestinationURL`         AS `destinationURL`,
    old.`logRequestDomain`          AS `requestDomain`,
    old.`logInitiatingURL`          AS `requestPath`,
    old.`logInitiatingMethod`       AS `requestMethod`,
    old.`logReqRefURL`              AS `requestReferer`,
    old.`logReqUserAgent`           AS `requestUserAgent`,
    IFNULL(old.`logReqIP`, '0.0.0.0')
                                    AS `ipAddress`,
    -- Pack remaining legacy fields into logData JSON
    JSON_OBJECT(
        'legacy_logUID', old.`logUID`,
        'errorCode', old.`errorCode`,
        'errorType', old.`errorType`,
        'errorDebug', old.`errorDebug`,
        'logUser', old.`logUser`,
        'custAPIKey', CASE WHEN old.`custAPIKey` = '' THEN NULL ELSE old.`custAPIKey` END,
        'toolID', old.`toolID`,
        'toolVersion', old.`toolVersion`,
        'toolDevStatus', old.`toolDevStatus`,
        'logLicenseID', old.`logLicenseID`
    )                               AS `logData`,
    old.`activityStart`             AS `createdAt`
FROM `mwtools_mwlink`.`tblActivityLog` old
ORDER BY old.`logUID` ASC
LIMIT 10000 OFFSET 0;

-- =========================================================================
-- To continue migration, run additional batches:
-- =========================================================================
-- LIMIT 10000 OFFSET 10000
-- LIMIT 10000 OFFSET 20000
-- ...
-- LIMIT 10000 OFFSET 420000
-- LIMIT 10000 OFFSET 429000
-- =========================================================================

-- =========================================================================
-- Verification (run after all batches complete)
-- =========================================================================
SELECT 'Migration 007 — Activity Log' AS `migration`,
       COUNT(*) AS `log_count`
FROM `tblActivityLog`;

-- Compare with source
SELECT 'Source count' AS `check`,
       COUNT(*) AS `source_count`
FROM `mwtools_mwlink`.`tblActivityLog`;
