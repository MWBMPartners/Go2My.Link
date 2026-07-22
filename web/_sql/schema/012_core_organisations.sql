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
-- ⚠️  DEPRECATED (GT-6, docs/LAUNCH_PLAN_2026-07-09.md §5 / issue #91) — kept
--     for backward compatibility with any pre-existing row only. This table
--     was the Phase 5 (#32) custom-domain concept: an org registers a
--     hostname here, proves ownership via its own verifyDomain() DNS-TXT
--     flow, and tags it with a `domainType` (including 'linkspage').
--
--     NOTHING routes off this table. The redirect hot path (sp_lookupShortURL),
--     getOrgByDomain() / domain_resolver.php, and the Component C.4 (#46)
--     custom-domain LinksPage fallback (linkspage_fallback.php,
--     linkspage_resolver.php) all resolve a Host header EXCLUSIVELY via
--     `tblOrgShortDomains` (see that table below, and its own
--     verifyOrgShortDomain() flow added by #91). `domainType = 'linkspage'`
--     here is stored but has never been read by any code path — the actual
--     custom-domain-to-LinksPage link is `tblOrgShortDomains.linksPageUID`
--     (added by migration 015 for #46).
--
--     Confirmed at the 2026-07-10 GT-6 assessment: zero FKs reference
--     `domainUID`; the legacy-data migration (`migrations/001_migrate_
--     organisations.sql`) only ever populates `tblOrgShortDomains`, never
--     this table; no seed populates it; no integration test exercises it.
--     Its only consumers are its own CRUD functions in `web/_functions/
--     org.php` (`addOrgDomain()`, `verifyDomain()`, `removeOrgDomain()`,
--     `getOrgDomains()`) and its own admin page (`org/domains/index.php`),
--     which as of GT-6 no longer accepts new rows — see that page and
--     `web/_sql/migrations/018_deprecate_org_domains.sql` for the full
--     write-up and the reconciliation-audit query for any environment that
--     already has rows here.
--
--     DO NOT build new functionality against this table. DO NOT drop it or
--     its data automatically — see migration 018 for the reviewed,
--     owner-run reconciliation path.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblOrgDomains` (
    `domainUID`             BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `orgHandle`             VARCHAR(50)         NOT NULL
        COMMENT 'FK to tblOrganisations.orgHandle',

    `domainName`            VARCHAR(255)        NOT NULL
        COMMENT 'Custom domain name (e.g., mycompany.com)',

    `domainType`            ENUM('primary', 'redirect', 'linkspage')
                            NOT NULL DEFAULT 'primary'
        COMMENT 'DEPRECATED field — never read by any resolver; kept as-is for existing rows only (see table-level GT-6 comment above)',

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
  COMMENT='DEPRECATED (GT-6) — superseded by tblOrgShortDomains for routing and LinksPage designation; kept for existing rows only, see table comment above';

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

    `verificationStatus`    ENUM('pending', 'verified', 'failed')
                            NOT NULL DEFAULT 'pending'
        COMMENT 'DNS-TXT ownership verification status — only verified domains are routable (#91)',

    `verificationToken`     VARCHAR(255)        DEFAULT NULL
        COMMENT 'Per-domain unguessable DNS TXT record verification token',

    `verifiedAt`            DATETIME            DEFAULT NULL
        COMMENT 'When DNS ownership verification succeeded',

    `tlsStatus`             ENUM('none', 'pending', 'active')
                            NOT NULL DEFAULT 'none'
        COMMENT 'TLS provisioning status — manual Dreamhost hosted-domain today (docs/CUSTOM_DOMAINS.md); Cloudflare-for-SaaS is the roadmap automated path',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Routable flag — a NEWLY added domain starts at 0 until verified; grandfathered pre-verification domains keep resolving (see migration 013)',

    `linksPageUID`          BIGINT UNSIGNED     DEFAULT NULL
        COMMENT 'Component C.4 (#46) — the org''s published LinksPage shown at this domain''s root, or for a path that does not resolve to a short code, instead of a 404. FK to tblLinksPages.pageUID added as a deferred ALTER at the end of 032_linkspage.sql (tblLinksPages is defined later in import order). NULL = no fallback configured (existing 404 behaviour is unchanged).',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`shortDomainUID`),
    UNIQUE KEY `UQ_short_domain` (`shortDomain`),
    INDEX `IDX_short_domain_org` (`orgHandle`),
    INDEX `IDX_short_domain_status` (`verificationStatus`),
    INDEX `IDX_short_domain_linkspage` (`linksPageUID`),

    CONSTRAINT `FK_short_domain_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Organisation custom short URL domains (migrated from custOrgShortURLDomain)';
