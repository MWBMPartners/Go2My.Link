-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — LinksPage Tables
-- =============================================================================
-- LinkTree-like customisable link listing pages for lnks.page.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- LinksPage Templates
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblLinksPageTemplates` (
    `templateUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `templateSlug`          VARCHAR(50)         NOT NULL
        COMMENT 'URL-safe template identifier',

    `templateName`          VARCHAR(100)        NOT NULL
        COMMENT 'Display name',

    `templateDescription`   TEXT                DEFAULT NULL
        COMMENT 'Template description for selection UI',

    `templateHTML`          LONGTEXT            NOT NULL
        COMMENT 'HTML template with placeholder markers (e.g., {{username}}, {{links}}, {{avatar}})',

    `templateCSS`           LONGTEXT            DEFAULT NULL
        COMMENT 'Custom CSS for this template',

    `templateThumbnail`     VARCHAR(500)        DEFAULT NULL
        COMMENT 'Path to preview thumbnail image',

    `isSystem`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this is a built-in system template (not editable by users)',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`templateUID`),
    UNIQUE KEY `UQ_template_slug` (`templateSlug`),
    INDEX `IDX_template_active` (`isActive`, `sortOrder`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='LinksPage template definitions (system + custom)';

-- =============================================================================
-- LinksPage Pages
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblLinksPages` (
    `pageUID`               BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblUsers.userUID (page owner)',

    `orgHandle`             VARCHAR(50)         DEFAULT NULL
        COMMENT 'FK to tblOrganisations.orgHandle (org page)',

    `slug`                  VARCHAR(100)        NOT NULL
        COMMENT 'URL slug (e.g., lnks.page/slug)',

    `pageTitle`             VARCHAR(255)        NOT NULL
        COMMENT 'Page title / display name',

    `pageDescription`       TEXT                DEFAULT NULL
        COMMENT 'Page bio / description',

    `avatarPath`            VARCHAR(500)        DEFAULT NULL
        COMMENT 'Path to profile avatar image',

    `templateUID`           BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblLinksPageTemplates.templateUID',

    `customHTML`            LONGTEXT            DEFAULT NULL
        COMMENT 'Custom HTML override (from WYSIWYG editor)',

    `customCSS`             LONGTEXT            DEFAULT NULL
        COMMENT 'Custom CSS override',

    `themeColour`           VARCHAR(7)          DEFAULT NULL
        COMMENT 'Theme accent colour (hex)',

    `backgroundColour`      VARCHAR(7)          DEFAULT NULL
        COMMENT 'Background colour (hex)',

    `fontFamily`            VARCHAR(100)        DEFAULT NULL
        COMMENT 'Custom font family',

    `showSocialIcons`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether to show social media icons',

    `socialLinks`           JSON                DEFAULT NULL
        COMMENT 'JSON object of social media links {twitter: url, instagram: url, ...}',

    `isPublished`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the page is publicly visible',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`pageUID`),
    UNIQUE KEY `UQ_page_slug` (`slug`),
    INDEX `IDX_page_user` (`userUID`),
    INDEX `IDX_page_org` (`orgHandle`),
    INDEX `IDX_page_template` (`templateUID`),
    INDEX `IDX_page_published` (`isPublished`),

    CONSTRAINT `FK_page_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT `FK_page_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT `FK_page_template`
        FOREIGN KEY (`templateUID`)
        REFERENCES `tblLinksPageTemplates` (`templateUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='LinksPage page definitions per user or organisation';

-- =============================================================================
-- LinksPage Items (individual links on a page)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblLinksPageItems` (
    `itemUID`               BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `pageUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblLinksPages.pageUID',

    `urlUID`                BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'FK to tblShortURLs.urlUID (NULL for manual/external links)',

    `itemTitle`             VARCHAR(255)        NOT NULL
        COMMENT 'Link display title',

    `itemURL`               TEXT                NOT NULL
        COMMENT 'Link destination URL (short URL or manual URL)',

    `itemDescription`       TEXT                DEFAULT NULL
        COMMENT 'Optional description shown below the title',

    `itemIcon`              VARCHAR(500)        DEFAULT NULL
        COMMENT 'Custom icon URL/path (auto-detected favicon if NULL)',

    `faviconCacheURL`       VARCHAR(500)        DEFAULT NULL
        COMMENT 'Cached favicon URL (auto-fetched from destination)',

    `faviconCachedAt`       DATETIME            DEFAULT NULL
        COMMENT 'When the favicon was last cached',

    `requiresAgeGate`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this link requires age verification (auto for adult domains)',

    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display order on the page',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`itemUID`),
    INDEX `IDX_item_page` (`pageUID`, `sortOrder`),
    INDEX `IDX_item_url` (`urlUID`),

    CONSTRAINT `FK_item_page`
        FOREIGN KEY (`pageUID`)
        REFERENCES `tblLinksPages` (`pageUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT `FK_item_url`
        FOREIGN KEY (`urlUID`)
        REFERENCES `tblShortURLs` (`urlUID`)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual link items on a LinksPage';
