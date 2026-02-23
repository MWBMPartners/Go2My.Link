-- =============================================================================
-- Go2My.Link — Advanced Redirect Tables
-- =============================================================================
-- Scheduled, device-based, geo-based redirects, and age verification gates.
-- These are premium features gated behind subscription tiers.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Short URL Schedules (time-based redirect rules)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLSchedules` (
    `scheduleUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `urlUID`                BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblShortURLs.urlUID',

    `scheduleName`          VARCHAR(255)        DEFAULT NULL
        COMMENT 'Human-readable schedule name',

    `destinationURL`        TEXT                NOT NULL
        COMMENT 'Destination URL when this schedule is active',

    `scheduleRules`         JSON                NOT NULL
        COMMENT 'JSON schedule definition: {daysOfWeek: [1-7], timeStart, timeEnd, dateStart, dateEnd, recurrence, timezone}',

    `priority`              INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Priority order (higher = evaluated first)',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`scheduleUID`),
    INDEX `IDX_schedule_url` (`urlUID`),
    INDEX `IDX_schedule_priority` (`urlUID`, `priority` DESC),

    CONSTRAINT `FK_schedule_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Scheduled redirect rules with priority-ordered evaluation';

-- =============================================================================
-- Short URL Device Redirects (device/OS-based routing)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLDeviceRedirects` (
    `deviceRedirectUID`     BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `urlUID`                BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblShortURLs.urlUID',

    `deviceType`            ENUM('windows', 'macos', 'iphone', 'ipad', 'android_phone', 'android_tablet', 'linux', 'other')
                            NOT NULL
        COMMENT 'Target device/OS type',

    `destinationURL`        TEXT                NOT NULL
        COMMENT 'Destination URL for this device type',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`deviceRedirectUID`),
    UNIQUE KEY `UQ_device_url` (`urlUID`, `deviceType`),

    CONSTRAINT `FK_device_redirect_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Device/OS-based redirect rules per short URL';

-- =============================================================================
-- Short URL Geo Redirects (geographic routing + restrictions)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLGeoRedirects` (
    `geoRedirectUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `urlUID`                BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblShortURLs.urlUID',

    `geoType`               ENUM('country', 'region')
                            NOT NULL DEFAULT 'country'
        COMMENT 'Geographic scope of this rule',

    `geoCode`               VARCHAR(10)         NOT NULL
        COMMENT 'ISO 3166-1 alpha-2 (country) or ISO 3166-2 (region) code',

    `geoAction`             ENUM('redirect', 'allow', 'block')
                            NOT NULL DEFAULT 'redirect'
        COMMENT 'What to do for visitors from this location',

    `destinationURL`        TEXT                DEFAULT NULL
        COMMENT 'Redirect destination (required if geoAction = redirect)',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`geoRedirectUID`),
    INDEX `IDX_geo_url` (`urlUID`),
    INDEX `IDX_geo_code` (`geoCode`),

    CONSTRAINT `FK_geo_redirect_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Geographic routing and restriction rules per short URL';

-- =============================================================================
-- Short URL Age Gates (age verification)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLAgeGates` (
    `ageGateUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `urlUID`                BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblShortURLs.urlUID',

    `minimumAge`            TINYINT UNSIGNED    NOT NULL DEFAULT 18
        COMMENT 'Minimum age required (NOT disclosed to the user in the UI)',

    `verificationMethod`    ENUM('dob_picker', 'checkbox', 'external')
                            NOT NULL DEFAULT 'dob_picker'
        COMMENT 'How age is verified',

    `sessionDuration`       INT UNSIGNED        NOT NULL DEFAULT 86400
        COMMENT 'How long the age verification is valid in seconds (default 24h)',

    `customMessage`         TEXT                DEFAULT NULL
        COMMENT 'Custom message displayed on the age gate page',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`ageGateUID`),
    UNIQUE KEY `UQ_agegate_url` (`urlUID`),

    CONSTRAINT `FK_agegate_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Age verification gate configuration per short URL';
