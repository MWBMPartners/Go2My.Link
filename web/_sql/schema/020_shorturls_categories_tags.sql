-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Categories, Tags, and Short URLs
-- =============================================================================
-- Core short URL tables with enhanced fields for the new platform.
-- Migrated from tblShortURLs and tblCategories.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Categories (migrated from tblCategories, adds org FK)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblCategories` (
    `categoryUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `categoryID`            VARCHAR(50)         NOT NULL
        COMMENT 'Category slug/identifier (migrated from categoryID)',

    `orgHandle`             VARCHAR(50)         NOT NULL DEFAULT '[default]'
        COMMENT 'FK to tblOrganisations.orgHandle',

    `categoryName`          VARCHAR(200)        NOT NULL
        COMMENT 'Display name',

    `categoryDescription`   TEXT                DEFAULT NULL
        COMMENT 'Category description',

    `categoryFallbackURL`   VARCHAR(500)        DEFAULT NULL
        COMMENT 'Fallback URL for links in this category',

    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display sort order',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`categoryUID`),
    UNIQUE KEY `UQ_category_org` (`categoryID`, `orgHandle`),
    INDEX `IDX_category_org` (`orgHandle`),

    CONSTRAINT `FK_category_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Link categories per organisation';

-- =============================================================================
-- Tags (new — flexible tagging system for links)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblTags` (
    `tagUID`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `tagName`               VARCHAR(100)        NOT NULL
        COMMENT 'Tag display name',

    `tagSlug`               VARCHAR(100)        NOT NULL
        COMMENT 'URL-safe tag slug (lowercase, hyphenated)',

    `orgHandle`             VARCHAR(50)         NOT NULL DEFAULT '[default]'
        COMMENT 'FK to tblOrganisations.orgHandle',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`tagUID`),
    UNIQUE KEY `UQ_tag_org` (`tagSlug`, `orgHandle`),
    INDEX `IDX_tag_org` (`orgHandle`),

    CONSTRAINT `FK_tag_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tags for organising short URLs';

-- =============================================================================
-- Short URLs (migrated and enhanced from tblShortURLs)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLs` (
    `urlUID`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier (preserves legacy urlUID for migration)',

    `orgHandle`             VARCHAR(50)         NOT NULL DEFAULT '[default]'
        COMMENT 'FK to tblOrganisations.orgHandle (migrated from urlOrgID)',

    `shortCode`             VARCHAR(50)         NOT NULL
        COMMENT 'Short URL code (migrated from urlID)',

    `destinationURL`        TEXT                DEFAULT NULL
        COMMENT 'Full destination URL including protocol (migrated from urlLong)',

    `destinationType`       ENUM('url', 'alias')
                            NOT NULL DEFAULT 'url'
        COMMENT 'Whether this resolves to a URL or an alias chain',

    `redirectAlias`         VARCHAR(50)         DEFAULT NULL
        COMMENT 'Another short code to chain to (migrated from urlRedirAlias)',

    `title`                 VARCHAR(255)        DEFAULT NULL
        COMMENT 'Descriptive title (migrated from urlDescription)',

    `categoryID`            VARCHAR(50)         DEFAULT NULL
        COMMENT 'FK to tblCategories.categoryID',

    `createdByUserUID`      BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID (who created this link)',

    -- UTM parameters
    `utmSource`             VARCHAR(255)        DEFAULT NULL,
    `utmMedium`             VARCHAR(255)        DEFAULT NULL,
    `utmCampaign`           VARCHAR(255)        DEFAULT NULL,
    `utmTerm`               VARCHAR(255)        DEFAULT NULL,
    `utmContent`            VARCHAR(255)        DEFAULT NULL,

    -- Behaviour flags
    `validateDestination`   TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether to validate the destination URL before redirecting',

    `allowLinksPage`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this link can appear on a LinksPage',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this short URL is active (soft delete)',

    -- Date range
    `startDate`             DATETIME            DEFAULT NULL
        COMMENT 'When this short URL becomes active (NULL = immediately)',

    `endDate`               DATETIME            DEFAULT NULL
        COMMENT 'When this short URL expires (NULL = never)',

    -- Analytics cache
    `clickCount`            BIGINT UNSIGNED     NOT NULL DEFAULT 0
        COMMENT 'Cached total click count (updated periodically)',

    `lastClickAt`           DATETIME            DEFAULT NULL
        COMMENT 'Timestamp of last click',

    -- Metadata
    `urlNotes`              TEXT                DEFAULT NULL
        COMMENT 'Internal notes',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`urlUID`),
    UNIQUE KEY `UQ_shortcode_org` (`shortCode`, `orgHandle`),
    INDEX `IDX_url_org` (`orgHandle`),
    INDEX `IDX_url_category` (`categoryID`, `orgHandle`),
    INDEX `IDX_url_creator` (`createdByUserUID`),
    INDEX `IDX_url_active` (`isActive`),
    INDEX `IDX_url_dates` (`startDate`, `endDate`),
    INDEX `IDX_url_alias` (`redirectAlias`),

    CONSTRAINT `FK_url_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `FK_url_creator`
        FOREIGN KEY (`createdByUserUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

    -- NOTE: categoryID is a logical FK to tblCategories.categoryID but cannot
    -- be enforced as a database constraint because tblCategories uses a composite
    -- unique key (categoryID + orgHandle). A simple FK would fail since categoryID
    -- alone is not unique. Application-level validation is used instead.

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Short URL records with enhanced tracking and redirect options';

-- =============================================================================
-- Short URL Tags (junction table)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblShortURLTags` (
    `urlUID`                BIGINT UNSIGNED     NOT NULL,
    `tagUID`                BIGINT UNSIGNED     NOT NULL,

    PRIMARY KEY (`urlUID`, `tagUID`),
    INDEX `IDX_tag_urls` (`tagUID`),

    CONSTRAINT `FK_urltag_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_urltag_tag`
        FOREIGN KEY (`tagUID`)
        REFERENCES `tblTags` (`tagUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table linking short URLs to tags';
