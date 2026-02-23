-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Legal & Compliance Tables
-- =============================================================================
-- GDPR/CCPA consent tracking and data deletion requests.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Consent Records (GDPR/CCPA cookie and processing consent)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblConsentRecords` (
    `consentUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID (NULL for anonymous visitors)',

    `sessionID`             VARCHAR(255)        DEFAULT NULL
        COMMENT 'Session identifier (for anonymous consent tracking)',

    `consentType`           ENUM('essential', 'analytics', 'functional', 'marketing')
                            NOT NULL
        COMMENT 'Type of consent',

    `consentGiven`          TINYINT(1) UNSIGNED NOT NULL
        COMMENT '1 = consent given, 0 = consent refused',

    `consentMethod`         VARCHAR(50)         NOT NULL DEFAULT 'banner'
        COMMENT 'How consent was obtained (banner, settings, registration)',

    `ipAddress`             VARCHAR(45)         DEFAULT NULL
        COMMENT 'IP at time of consent (for audit)',

    `userAgent`             VARCHAR(500)        DEFAULT NULL
        COMMENT 'User agent at time of consent (for audit)',

    `jurisdiction`          VARCHAR(50)         DEFAULT NULL
        COMMENT 'Detected legal jurisdiction (EU, UK, US-CA, etc.)',

    `consentVersion`        VARCHAR(20)         NOT NULL DEFAULT '1.0'
        COMMENT 'Version of the consent policy agreed to',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When consent was given/refused',

    `expiresAt`             DATETIME            DEFAULT NULL
        COMMENT 'When this consent expires and needs renewal',

    PRIMARY KEY (`consentUID`),
    INDEX `IDX_consent_user` (`userUID`),
    INDEX `IDX_consent_session` (`sessionID`),
    INDEX `IDX_consent_type` (`consentType`),
    INDEX `IDX_consent_created` (`createdAt`),

    CONSTRAINT `FK_consent_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='GDPR/CCPA consent tracking for cookie and data processing consent';

-- =============================================================================
-- Data Deletion Requests (GDPR Article 17, CCPA right to delete)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblDataDeletionRequests` (
    `requestUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID (requesting user)',

    `requestType`           ENUM('export', 'deletion', 'anonymisation')
                            NOT NULL DEFAULT 'deletion'
        COMMENT 'Type of data subject request',

    `status`                ENUM('pending', 'processing', 'completed', 'rejected')
                            NOT NULL DEFAULT 'pending',

    `requestReason`         TEXT                DEFAULT NULL
        COMMENT 'User-provided reason for the request',

    `adminNotes`            TEXT                DEFAULT NULL
        COMMENT 'Internal notes from admin processing',

    `processedByUserUID`    BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Admin user who processed the request',

    `processedAt`           DATETIME            DEFAULT NULL
        COMMENT 'When the request was processed',

    `exportFilePath`        VARCHAR(500)        DEFAULT NULL
        COMMENT 'Path to export ZIP file (for export requests)',

    `exportExpiresAt`       DATETIME            DEFAULT NULL
        COMMENT 'When the export file expires and should be deleted',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`requestUID`),
    INDEX `IDX_deletion_user` (`userUID`),
    INDEX `IDX_deletion_status` (`status`),
    INDEX `IDX_deletion_created` (`createdAt`),

    CONSTRAINT `FK_deletion_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `FK_deletion_admin`
        FOREIGN KEY (`processedByUserUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Data subject requests (GDPR deletion, export, anonymisation)';
