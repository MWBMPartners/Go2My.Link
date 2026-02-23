-- =============================================================================
-- Go2My.Link — Stored Procedure: sp_logActivity
-- =============================================================================
-- Simplified structured insert into tblActivityLog.
-- Replaces the legacy logActivity procedure which had copy-paste bugs
-- (wrong variable names in INSERT VALUES).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_logActivity`//

CREATE PROCEDURE `sp_logActivity`(
    IN `inputAction`        VARCHAR(100),
    IN `inputStatus`        VARCHAR(50),
    IN `inputStatusCode`    SMALLINT,
    IN `inputOrgHandle`     VARCHAR(50),
    IN `inputUserUID`       BIGINT,
    IN `inputShortCode`     VARCHAR(50),
    IN `inputDestURL`       TEXT,
    IN `inputDomain`        VARCHAR(255),
    IN `inputPath`          VARCHAR(500),
    IN `inputMethod`        VARCHAR(10),
    IN `inputReferer`       VARCHAR(500),
    IN `inputUserAgent`     VARCHAR(500),
    IN `inputIP`            VARCHAR(45),
    IN `inputApiKeyUID`     BIGINT,
    IN `inputLogData`       JSON
)
    MODIFIES SQL DATA
    COMMENT 'Insert a structured activity log entry'
BEGIN
    -- Ensure UTC
    SET time_zone = '+00:00';

    INSERT INTO tblActivityLog (
        logAction,
        logStatus,
        statusCode,
        orgHandle,
        userUID,
        shortCode,
        destinationURL,
        requestDomain,
        requestPath,
        requestMethod,
        requestReferer,
        requestUserAgent,
        ipAddress,
        apiKeyUID,
        logData
    )
    VALUES (
        inputAction,
        inputStatus,
        inputStatusCode,
        inputOrgHandle,
        inputUserUID,
        inputShortCode,
        inputDestURL,
        inputDomain,
        inputPath,
        inputMethod,
        inputReferer,
        inputUserAgent,
        inputIP,
        inputApiKeyUID,
        inputLogData
    );
END//

DELIMITER ;
