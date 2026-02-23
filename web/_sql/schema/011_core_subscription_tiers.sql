-- =============================================================================
-- Go2My.Link — Subscription Tiers Table
-- =============================================================================
-- Defines the available subscription tiers and their feature limits.
-- Referenced by tblOrganisations and tblSubscriptions.
-- Created early as it's a FK dependency for organisations.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

CREATE TABLE IF NOT EXISTS `tblSubscriptionTiers` (
    `tierUID`               BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `tierID`                VARCHAR(50)         NOT NULL
        COMMENT 'Tier slug (e.g., free, basic, premium, enterprise)',

    `tierName`              VARCHAR(100)        NOT NULL
        COMMENT 'Display name (e.g., Free, Basic, Premium, Enterprise)',

    `tierDescription`       TEXT                DEFAULT NULL
        COMMENT 'Marketing description of this tier',

    `tierPriceMonthly`      DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Monthly price in GBP',

    `tierPriceAnnual`       DECIMAL(10, 2)      NOT NULL DEFAULT 0.00
        COMMENT 'Annual price in GBP (discounted)',

    `tierCurrency`          CHAR(3)             NOT NULL DEFAULT 'GBP'
        COMMENT 'ISO 4217 currency code',

    `maxLinks`              INT UNSIGNED        DEFAULT NULL
        COMMENT 'Maximum short URLs allowed (NULL = unlimited)',

    `maxCustomDomains`      INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Maximum custom short domains allowed',

    `maxAPIRequestsPerDay`  INT UNSIGNED        DEFAULT NULL
        COMMENT 'Maximum API requests per day (NULL = unlimited)',

    `maxLinksPages`         INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Maximum LinksPage pages allowed',

    `hasAdvancedRedirects`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether scheduled/device/geo redirects are available',

    `hasAnalytics`          TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether detailed analytics are available',

    `hasQRCodes`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether QR code integration is available (via external Go2My.Link QR service)',

    `hasAPIAccess`          TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether API access is available',

    `hasPrioritySupport`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether priority support is included',

    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Display sort order on pricing page',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this tier is currently offered',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`tierUID`),
    UNIQUE KEY `UQ_tier_id` (`tierID`),
    INDEX `IDX_tier_active` (`isActive`, `sortOrder`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Subscription tier definitions with feature limits';
