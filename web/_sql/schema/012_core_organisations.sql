-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Organisations Table
-- =============================================================================
-- Migrated from tblCustomerOrg. Organisations can have custom short domains,
-- subscription tiers, and multiple users.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

CREATE TABLE IF NOT EXISTS `tblOrganisations` (
    `orgUID`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'Unique organisation handle/slug (migrated from custOrgHandle)',

    `orgName`               VARCHAR(255)        NOT NULL
        COMMENT 'Organisation display name',

    `orgURL`                VARCHAR(500)        DEFAULT NULL
        COMMENT 'Organisation primary website URL',

    `orgDomain`             VARCHAR(255)        DEFAULT NULL
        COMMENT 'Organisation primary domain (without protocol/www)',

    `orgFallbackURL`        VARCHAR(500)        DEFAULT NULL
        COMMENT 'Fallback URL for expired/invalid short URLs under this org',

    `orgDescription`        TEXT                DEFAULT NULL
        COMMENT 'Organisation description or bio',

    `orgLogoPath`           VARCHAR(500)        DEFAULT NULL
        COMMENT 'Path to organisation logo file (relative to _uploads/)',

    `tierID`                VARCHAR(50)         NOT NULL DEFAULT 'free'
        COMMENT 'Subscription tier FK (references tblSubscriptionTiers.tierID)',

    `isVerified`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the organisation has been verified',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether the organisation is active',

    `orgNotes`              TEXT                DEFAULT NULL
        COMMENT 'Internal admin notes',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`orgUID`),
    UNIQUE KEY `UQ_org_handle` (`orgHandle`),
    INDEX `IDX_org_tier` (`tierID`),
    INDEX `IDX_org_active` (`isActive`),

    CONSTRAINT `FK_org_tier`
        FOREIGN KEY (`tierID`)
        REFERENCES `tblSubscriptionTiers` (`tierID`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Organisations with custom domains and subscription tiers';

-- =============================================================================
-- Organisation Domains (DNS verification for custom domains)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblOrgDomains` (
    `domainUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `domainName`            VARCHAR(255)        NOT NULL
        COMMENT 'Custom domain name (e.g., mycompany.com)',

    `domainType`            ENUM('primary', 'redirect', 'linkspage')
                            NOT NULL DEFAULT 'primary'
        COMMENT 'What this domain is used for',

    `verificationToken`     VARCHAR(255)        DEFAULT NULL
        COMMENT 'DNS TXT record verification token',

    `verificationStatus`    ENUM('pending', 'verified', 'failed', 'expired')
                            NOT NULL DEFAULT 'pending'
        COMMENT 'Current DNS verification status',

    `verifiedAt`            DATETIME            DEFAULT NULL
        COMMENT 'When DNS verification succeeded',

    `lastCheckedAt`         DATETIME            DEFAULT NULL
        COMMENT 'When DNS was last checked',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`domainUID`),
    UNIQUE KEY `UQ_domain_name` (`domainName`),
    INDEX `IDX_domain_org` (`orgHandle`),
    INDEX `IDX_domain_status` (`verificationStatus`),

    CONSTRAINT `FK_domain_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Organisation custom domain DNS verification';

-- =============================================================================
-- Organisation Short Domains (custom short URL domains)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblOrgShortDomains` (
    `shortDomainUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `shortDomain`           VARCHAR(255)        NOT NULL
        COMMENT 'Custom short URL domain (e.g., camsda.link, tyney.link)',

    `isDefault`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this is the default short domain for the org',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`shortDomainUID`),
    UNIQUE KEY `UQ_short_domain` (`shortDomain`),
    INDEX `IDX_short_domain_org` (`orgHandle`),

    CONSTRAINT `FK_short_domain_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Organisation custom short URL domains (migrated from custOrgShortURLDomain)';
