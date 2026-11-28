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

    -- 🔗 #289 / owner decision D-003: the shared g2my.link codes.
    -- Every link that opens on g2my.link holds its code here as well as in
    -- shortCode, and the unique key UQ_url_shared_code below lets each code
    -- be used only once there, by everybody together: the default
    -- organisation, people with no organisation, and members of an
    -- organisation that has no verified short web address of its own.
    -- NULL means the link lives only in its own organisation's set of codes,
    -- which is the case for an organisation that has its own verified
    -- default short domain (acme.link, say): its links open on that domain
    -- and their codes do not use up anybody's g2my.link codes.
    -- shortCode + orgHandle stays unique too (UQ_shortcode_org), so the
    -- dashboard, the API and the statistics, which all find a link by its
    -- code and its organisation, still find exactly one row.
    `sharedShortCode`       VARCHAR(50)         DEFAULT NULL
        COMMENT 'The same value as shortCode when this link opens on g2my.link, where each code is used once across everybody (#289, D-003); NULL when the link lives only in its own organisation''s codes on that organisation''s verified short domain',

    `destinationURL`        TEXT                DEFAULT NULL
        COMMENT 'Full destination URL including protocol (migrated from urlLong)',

    `destinationType`       ENUM('url', 'alias')
                            NOT NULL DEFAULT 'url'
        COMMENT 'Alias-chain discriminator (#128): informational only — sp_lookupShortURL follows redirectAlias directly and does not read this column. Migration 004_migrate_shorturls.sql guarantees this is alias exactly when redirectAlias is meaningful; see that migration''s alias-integrity verification section for the pre-cutover safety check that guards the invariant',

    `redirectAlias`         VARCHAR(50)         DEFAULT NULL
        COMMENT 'Another short code to chain to (migrated from urlRedirAlias)',

    `title`                 VARCHAR(255)        DEFAULT NULL
        COMMENT 'Descriptive title (migrated from urlDescription)',

    `categoryID`            VARCHAR(50)         DEFAULT NULL
        COMMENT 'FK to tblCategories.categoryID',

    `createdByUserUID`      BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID (who created this link)',

    -- CueRCode dynamic-QR integration (provenance + external QR reference)
    `createdVia`            ENUM('web', 'api', 'import', 'admin', 'cuercode')
                            NOT NULL DEFAULT 'web'
        COMMENT 'How this short URL was created; cuercode = minted by the CueRCode QR service',

    `createdViaAPIKeyUID`   BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblAPIKeys.apiKeyUID — which API key minted this code; constraint added in 031_api.sql',

    `qrCodeExternalID`      BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Opaque id of the owning CueRCode QR record (NULL = not QR-backed); the QR record lives in CueRCode',

    `qrCodeExternalUUID`    CHAR(36)            DEFAULT NULL
        COMMENT 'Opaque UUID of the owning CueRCode QR record (NULL = not QR-backed)',

    `qrCodeLinkedAt`        DATETIME            DEFAULT NULL
        COMMENT 'UTC timestamp when a CueRCode QR was linked to this short URL',

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

    -- 🛡️ #325 / owner decision D-023: Go2My.Link staff can switch any link
    -- off. staffDisabledAt is set while a staff block is in place, and the
    -- check CK_url_staff_disabled_off below stops the link being switched on
    -- until staff lift the block, whichever code tries.
    `staffDisabledAt`       DATETIME            DEFAULT NULL
        COMMENT 'UTC time Go2My.Link staff switched this link off (#325); NULL when there is no staff block',

    `staffDisabledByUserUID` BIGINT UNSIGNED    DEFAULT NULL
        COMMENT 'tblUsers.userUID of the staff member who switched it off (#325); no foreign key, kept as a record',

    `staffDisableReason`    VARCHAR(500)        DEFAULT NULL
        COMMENT 'Optional reason the staff member gave (#325); not shown to the link''s owner',

    `staffPreviousIsActive` TINYINT(1) UNSIGNED DEFAULT NULL
        COMMENT 'What isActive goes back to when staff remove the block (#325, D-026): 1 on, 0 off; set to 0 if the owner or account erasure switches the link off meanwhile; NULL when there is no staff block',

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
    -- #289: one row per code on g2my.link. Many rows may hold NULL (links
    -- that live on an organisation's own domain); MySQL and MariaDB both
    -- allow any number of NULLs in a unique key. This key is also what makes
    -- two people creating the same code at the same moment safe: the second
    -- INSERT fails with a duplicate-key error instead of both succeeding.
    UNIQUE KEY `UQ_url_shared_code` (`sharedShortCode`),
    INDEX `IDX_url_org` (`orgHandle`),
    INDEX `IDX_url_category` (`categoryID`, `orgHandle`),
    INDEX `IDX_url_creator` (`createdByUserUID`),
    INDEX `IDX_url_active` (`isActive`),
    INDEX `IDX_url_dates` (`startDate`, `endDate`),
    INDEX `IDX_url_alias` (`redirectAlias`),
    INDEX `IDX_url_created_via` (`createdVia`),
    INDEX `IDX_url_apikey` (`createdViaAPIKeyUID`),
    INDEX `IDX_url_qr_extid` (`qrCodeExternalID`),
    UNIQUE KEY `UQ_url_qr_uuid` (`qrCodeExternalUUID`),

    CONSTRAINT `FK_url_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `FK_url_creator`
        FOREIGN KEY (`createdByUserUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    -- #289: sharedShortCode is either empty or a copy of shortCode, never a
    -- different code, so a link can never open on g2my.link under a code
    -- other than its own. (Enforced by MySQL 8.0.16 or newer and by
    -- MariaDB 10.2 or newer; an older server accepts and ignores it.)
    CONSTRAINT `CK_url_shared_code_matches`
        CHECK (`sharedShortCode` IS NULL OR `sharedShortCode` = `shortCode`),
    -- #325: a link under a staff block is always switched off. (Enforced by
    -- MySQL 8.0.16 or newer and by MariaDB 10.2 or newer.)
    CONSTRAINT `CK_url_staff_disabled_off`
        CHECK (`staffDisabledAt` IS NULL OR `isActive` = 0)

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
