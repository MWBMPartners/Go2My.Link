-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — API Tables
-- =============================================================================
-- API key management and request logging.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- API Keys
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblAPIKeys` (
    `apiKeyUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID (key owner)',

    `orgHandle`             VARCHAR(50)         NOT NULL DEFAULT '[default]'
        COMMENT 'FK to tblOrganisations.orgHandle',

    `apiKey`                VARCHAR(255)        NOT NULL
        COMMENT 'API key value (hashed — raw key shown only once at creation)',

    `apiKeyPrefix`          VARCHAR(10)         NOT NULL
        COMMENT 'First 8 chars of the key (for identification in UI)',

    `keyName`               VARCHAR(255)        NOT NULL DEFAULT 'Default'
        COMMENT 'User-friendly name for this key',

    `permissions`           JSON                DEFAULT NULL
        COMMENT 'JSON array of allowed API scopes (e.g., ["urls:read", "urls:write", "analytics:read"])',

    `rateLimitOverride`     INT UNSIGNED        DEFAULT NULL
        COMMENT 'Custom rate limit for this key (NULL = use tier default)',

    `lastUsedAt`            DATETIME            DEFAULT NULL
        COMMENT 'When this key was last used',

    `expiresAt`             DATETIME            DEFAULT NULL
        COMMENT 'When this key expires (NULL = never)',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`apiKeyUID`),
    UNIQUE KEY `UQ_api_key` (`apiKey`),
    UNIQUE KEY `UQ_apikey_prefix` (`apiKeyPrefix`)
        COMMENT 'Prevents two keys from ever sharing a lookup prefix (#149.4) — a UNIQUE index also serves every lookup the old non-unique IDX_api_key_prefix provided',
    INDEX `IDX_api_key_user` (`userUID`),
    INDEX `IDX_api_key_org` (`orgHandle`),
    INDEX `IDX_api_key_active` (`isActive`),

    CONSTRAINT `FK_apikey_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_apikey_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='API key storage with scoped permissions';

-- =============================================================================
-- API Request Log (audit trail for API usage)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblAPIRequestLog` (
    `requestUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `apiKeyUID`             BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblAPIKeys.apiKeyUID',

    `endpoint`              VARCHAR(255)        NOT NULL
        COMMENT 'API endpoint called (e.g., /api/v1/urls)',

    `httpMethod`            VARCHAR(10)         NOT NULL
        COMMENT 'HTTP method (GET, POST, PUT, DELETE)',

    `responseCode`          SMALLINT UNSIGNED   NOT NULL
        COMMENT 'HTTP response code returned',

    `responseTimeMs`        INT UNSIGNED        DEFAULT NULL
        COMMENT 'Response time in milliseconds',

    `ipAddress`             VARCHAR(45)         NOT NULL,
    `userAgent`             VARCHAR(500)        DEFAULT NULL,

    `requestBody`           JSON                DEFAULT NULL
        COMMENT 'Request body (sanitised, sensitive fields removed)',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`requestUID`),
    INDEX `IDX_apireq_key` (`apiKeyUID`),
    INDEX `IDX_apireq_endpoint` (`endpoint`),
    INDEX `IDX_apireq_created` (`createdAt`),
    INDEX `IDX_apireq_key_created` (`apiKeyUID`, `createdAt`)
        COMMENT 'Composite index for the DB-backed rate limiter (#38) — one range scan per apiKeyUID+window instead of a full-table filter',
    INDEX `IDX_apireq_ip_created` (`ipAddress`, `createdAt`)
        COMMENT 'Composite index for the pre-auth IP failed-auth backoff (#39) — one range scan per ipAddress+window instead of a full-table filter',

    CONSTRAINT `FK_apireq_key`
        FOREIGN KEY (`apiKeyUID`)
        REFERENCES `tblAPIKeys` (`apiKeyUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='API request audit trail for rate limiting and analytics';

-- =============================================================================
-- Deferred foreign key: tblShortURLs.createdViaAPIKeyUID -> tblAPIKeys
-- =============================================================================
-- Added here (after tblAPIKeys exists) because tblShortURLs is defined in
-- 020_shorturls_categories_tags.sql, which is imported before this file.
-- Records which API key (e.g. the CueRCode integration) minted a short code;
-- ON DELETE SET NULL so removing a key never deletes live links.
-- =============================================================================
ALTER TABLE `tblShortURLs`
    ADD CONSTRAINT `FK_url_apikey`
        FOREIGN KEY (`createdViaAPIKeyUID`)
        REFERENCES `tblAPIKeys` (`apiKeyUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
