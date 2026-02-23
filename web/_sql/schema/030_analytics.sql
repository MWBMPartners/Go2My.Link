-- =============================================================================
-- Go2My.Link — Analytics Tables
-- =============================================================================
-- Activity logging (migrated from tblActivityLog) and error logging.
-- InnoDB with structured columns replacing the legacy catch-all design.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Activity Log (migrated and restructured from tblActivityLog)
-- =============================================================================
-- Note: For very large datasets, consider partitioning by month:
--   PARTITION BY RANGE (YEAR(createdAt) * 100 + MONTH(createdAt))
-- This is optional and can be added later without schema changes.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblActivityLog` (
    `logUID`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- What happened
    `logAction`             VARCHAR(100)        NOT NULL
        COMMENT 'Action type (redirect, create_link, login, api_call, etc.)',

    `logStatus`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'Outcome status (success, error, not_found, expired, etc.)',

    `statusCode`            SMALLINT UNSIGNED   DEFAULT NULL
        COMMENT 'HTTP status code or custom error code',

    -- What was involved
    `orgHandle`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'Organisation handle (if applicable)',

    `userUID`               BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'User UID (if authenticated)',

    `shortCode`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'Short code involved (if applicable)',

    `destinationURL`        TEXT                DEFAULT NULL
        COMMENT 'Destination URL (for redirect actions)',

    -- Request details
    `requestDomain`         VARCHAR(255)        DEFAULT NULL
        COMMENT 'Domain the request was made to',

    `requestPath`           VARCHAR(500)        DEFAULT NULL
        COMMENT 'Request path/URI',

    `requestMethod`         VARCHAR(10)         DEFAULT NULL
        COMMENT 'HTTP method (GET, POST, etc.)',

    `requestReferer`        VARCHAR(500)        DEFAULT NULL
        COMMENT 'HTTP Referer header value',

    `requestUserAgent`      VARCHAR(500)        DEFAULT NULL
        COMMENT 'Raw User-Agent string',

    -- Parsed user agent (populated by UA parser)
    `browserName`           VARCHAR(100)        DEFAULT NULL,
    `browserVersion`        VARCHAR(50)         DEFAULT NULL,
    `osName`                VARCHAR(100)        DEFAULT NULL,
    `osVersion`             VARCHAR(50)         DEFAULT NULL,
    `deviceType`            VARCHAR(50)         DEFAULT NULL
        COMMENT 'desktop, mobile, tablet, bot, etc.',

    -- Network/geo
    `ipAddress`             VARCHAR(45)         NOT NULL
        COMMENT 'Client IP address (supports IPv6)',

    `countryCode`           CHAR(2)             DEFAULT NULL
        COMMENT 'ISO 3166-1 alpha-2 country code (from GeoIP)',

    `regionCode`            VARCHAR(10)         DEFAULT NULL
        COMMENT 'ISO 3166-2 region code (from GeoIP)',

    `cityName`              VARCHAR(255)        DEFAULT NULL
        COMMENT 'City name (from GeoIP)',

    `latitude`              DECIMAL(10, 7)      DEFAULT NULL
        COMMENT 'Latitude (from GeoIP)',

    `longitude`             DECIMAL(10, 7)      DEFAULT NULL
        COMMENT 'Longitude (from GeoIP)',

    `isVPN`                 TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Whether the IP is detected as a VPN/proxy',

    `isBot`                 TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'Whether the request is from a known bot',

    -- API context (if applicable)
    `apiKeyUID`             BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'API key used (if API request)',

    -- Extra data
    `logData`               JSON                DEFAULT NULL
        COMMENT 'Additional structured data (varies by action type)',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this event occurred (UTC)',

    PRIMARY KEY (`logUID`),
    INDEX `IDX_log_action` (`logAction`),
    INDEX `IDX_log_shortcode` (`shortCode`),
    INDEX `IDX_log_org` (`orgHandle`),
    INDEX `IDX_log_user` (`userUID`),
    INDEX `IDX_log_ip` (`ipAddress`),
    INDEX `IDX_log_country` (`countryCode`),
    INDEX `IDX_log_created` (`createdAt`),
    INDEX `IDX_log_domain` (`requestDomain`),
    INDEX `IDX_log_status` (`logStatus`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Request/redirect activity log with structured geo/UA data';

-- =============================================================================
-- Error Log (PHP errors with backtrace)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblErrorLog` (
    `errorUID`              BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `errorSeverity`         ENUM('notice', 'warning', 'error', 'critical', 'exception')
                            NOT NULL DEFAULT 'error'
        COMMENT 'Error severity level',

    `errorCode`             INT                 DEFAULT NULL
        COMMENT 'PHP error code or custom error code',

    `errorTitle`            VARCHAR(500)        NOT NULL
        COMMENT 'Error message or exception class',

    `errorDetail`           TEXT                DEFAULT NULL
        COMMENT 'Detailed error message',

    `errorFile`             VARCHAR(500)        DEFAULT NULL
        COMMENT 'File where the error occurred',

    `errorLine`             INT UNSIGNED        DEFAULT NULL
        COMMENT 'Line number where the error occurred',

    `errorBacktrace`        TEXT                DEFAULT NULL
        COMMENT 'Stack trace (truncated if very long)',

    -- Request context
    `requestURL`            VARCHAR(500)        DEFAULT NULL
        COMMENT 'URL being processed when the error occurred',

    `requestMethod`         VARCHAR(10)         DEFAULT NULL
        COMMENT 'HTTP method',

    `requestHeaders`        JSON                DEFAULT NULL
        COMMENT 'Relevant request headers (sanitised)',

    `ipAddress`             VARCHAR(45)         DEFAULT NULL
        COMMENT 'Client IP address',

    `userUID`               BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Authenticated user (if any)',

    `phpVersion`            VARCHAR(20)         DEFAULT NULL
        COMMENT 'PHP version running at time of error',

    `isResolved`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this error has been investigated/resolved',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`errorUID`),
    INDEX `IDX_error_severity` (`errorSeverity`),
    INDEX `IDX_error_created` (`createdAt`),
    INDEX `IDX_error_resolved` (`isResolved`),
    INDEX `IDX_error_file` (`errorFile`(255))

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='PHP error and exception log with backtrace and request context';
