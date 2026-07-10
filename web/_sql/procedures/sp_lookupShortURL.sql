-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Stored Procedure: sp_lookupShortURL
-- =============================================================================
-- Resolves a short code to its destination URL.
-- Handles: domain-to-org mapping, alias chains (max 3 hops), date validation,
-- active status, and fallback URLs.
--
-- Redesigned from the legacy lookupShortURL procedure:
--   - Removed cross-database dependency (syscheck.tblUserAgents)
--   - Removed copy-paste bugs in variable names
--   - Simplified and clarified alias chain logic
--   - Returns structured result set instead of relying on side effects
--
-- 🔒 #91 ownership-verification gate (v1.1.0): a Host header only maps to an
-- organisation when tblOrgShortDomains has a row for it with
-- verificationStatus = 'verified' AND isActive = 1. This is deliberately a
-- SINGLE unique-key lookup (UQ_short_domain) whose result columns are then
-- branched on in application logic below — NOT an extra query — so the normal
-- redirect hot path pays no additional round trip. Three outcomes:
--   1. No row at all for this exact host  → the system's own default host
--      (g2my.link) or a stray/unknown host → resolve in the '[default]' org
--      namespace, unchanged from before.
--   2. A row exists and is verified+active → resolve in THAT org's namespace
--      (this also covers migration 013's grandfathered pre-existing domains).
--   3. A row exists but is NOT verified+active → an org has claimed this
--      host but has not proven DNS control yet. This must NEVER fall through
--      to '[default]' (that would let an unverified claim read the default
--      org's — or, before this fix, effectively anyone's — short-code
--      namespace merely by pointing DNS at us). Returns a distinct
--      'domain_not_configured' status with a NULL org handle instead, so the
--      caller (redirect_resolver.php / index.php) renders a branded
--      "domain not configured" page rather than resolving anything.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.3.0
-- @since      Phase 1 (ownership-verification gate added v1.1.0 / #91)
--
-- Reference: https://dev.mysql.com/doc/refman/8.0/en/create-procedure.html
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_lookupShortURL`//

CREATE PROCEDURE `sp_lookupShortURL`(
    IN  `inputDomain`       VARCHAR(255),
    IN  `inputShortCode`    VARCHAR(50),
    OUT `outputDestination`  TEXT,
    OUT `outputStatus`       VARCHAR(50),
    OUT `outputOrgHandle`    VARCHAR(50)
)
    READS SQL DATA
    COMMENT 'Resolve a short code to its destination URL with alias chain support (max 3 hops)'
BEGIN
    -- Local variables
    -- (MySQL requires all variable DECLAREs to appear BEFORE any handler DECLARE)
    DECLARE v_orgHandle       VARCHAR(50)     DEFAULT NULL;
    DECLARE v_destination     TEXT            DEFAULT NULL;
    DECLARE v_alias           VARCHAR(50)     DEFAULT NULL;
    DECLARE v_isActive        TINYINT         DEFAULT 0;
    DECLARE v_startDate       DATETIME        DEFAULT NULL;
    DECLARE v_endDate         DATETIME        DEFAULT NULL;
    DECLARE v_hopCount        INT             DEFAULT 0;
    DECLARE v_maxHops         INT             DEFAULT 3;
    DECLARE v_currentCode     VARCHAR(50);
    DECLARE v_orgFallback     VARCHAR(500)    DEFAULT NULL;
    DECLARE v_catFallback     VARCHAR(500)    DEFAULT NULL;
    DECLARE v_found           TINYINT         DEFAULT 0;
    DECLARE v_domainFound     TINYINT         DEFAULT 0;
    DECLARE v_domainVerified  VARCHAR(20)     DEFAULT NULL;
    DECLARE v_domainIsActive  TINYINT         DEFAULT 0;

    -- Exception handler: return error status on any SQL failure
    -- (declared AFTER the variables above, as MySQL requires)
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET outputDestination = NULL;
        SET outputStatus = 'error';
        SET outputOrgHandle = NULL;
    END;

    -- Ensure UTC timezone for date comparisons
    SET time_zone = '+00:00';

    -- =========================================================================
    -- Step 1: Resolve domain to organisation (see the ownership-verification
    -- gate note in the procedure header above)
    -- =========================================================================
    IF inputDomain IS NOT NULL AND inputDomain != '' THEN
        SELECT osd.orgHandle, osd.verificationStatus, osd.isActive, 1
        INTO   v_orgHandle, v_domainVerified, v_domainIsActive, v_domainFound
        FROM   tblOrgShortDomains osd
        WHERE  osd.shortDomain = inputDomain
        LIMIT  1;
    END IF;

    IF v_domainFound = 0 THEN
        -- No custom short domain claims this exact host — the system's own
        -- default host (g2my.link) or a stray/unknown host either way.
        SET v_orgHandle = '[default]';
    ELSEIF NOT (v_domainVerified = 'verified' AND v_domainIsActive = 1) THEN
        -- An org has claimed this host but it is not verified+active yet.
        -- Do NOT fall back to '[default]' — surface a distinct status so the
        -- caller shows a branded "domain not configured" page instead.
        SET outputDestination = NULL;
        SET outputStatus = 'domain_not_configured';
        SET outputOrgHandle = NULL;
    END IF;

    -- =========================================================================
    -- Steps 2 & 3 only run when Step 1 did not already terminate resolution
    -- (i.e. outputStatus is still NULL — same guard style used below for the
    -- max-hops-exceeded case).
    -- =========================================================================
    IF outputStatus IS NULL THEN

        -- Get org fallback URL
        SELECT o.orgFallbackURL
        INTO   v_orgFallback
        FROM   tblOrganisations o
        WHERE  o.orgHandle = v_orgHandle
        LIMIT  1;

        -- =====================================================================
        -- Step 2: Resolve short code (with alias chain, max 3 hops)
        -- =====================================================================
        SET v_currentCode = inputShortCode;

        resolve_loop: WHILE v_hopCount < v_maxHops DO
            SET v_found = 0;
            SET v_destination = NULL;
            SET v_alias = NULL;
            SET v_isActive = 0;
            SET v_startDate = NULL;
            SET v_endDate = NULL;

            SELECT
                s.destinationURL,
                s.redirectAlias,
                s.isActive,
                s.startDate,
                s.endDate,
                1
            INTO
                v_destination,
                v_alias,
                v_isActive,
                v_startDate,
                v_endDate,
                v_found
            FROM   tblShortURLs s
            WHERE  s.shortCode = v_currentCode
               AND s.orgHandle = v_orgHandle
            LIMIT  1;

            -- Short code not found
            IF v_found = 0 THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'not_found';
                SET outputOrgHandle = v_orgHandle;
                LEAVE resolve_loop;
            END IF;

            -- Check if active
            IF v_isActive = 0 THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'inactive';
                SET outputOrgHandle = v_orgHandle;
                LEAVE resolve_loop;
            END IF;

            -- Check date range validity
            IF v_startDate IS NOT NULL AND v_startDate > NOW() THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'not_yet_active';
                SET outputOrgHandle = v_orgHandle;
                LEAVE resolve_loop;
            END IF;

            IF v_endDate IS NOT NULL AND v_endDate < NOW() THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'expired';
                SET outputOrgHandle = v_orgHandle;
                LEAVE resolve_loop;
            END IF;

            -- If this is an alias, follow the chain
            IF v_alias IS NOT NULL AND v_alias != '' THEN
                SET v_currentCode = v_alias;
                SET v_hopCount = v_hopCount + 1;
                ITERATE resolve_loop;
            END IF;

            -- We have a direct destination
            IF v_destination IS NOT NULL AND v_destination != '' THEN
                SET outputDestination = v_destination;
                SET outputStatus = 'success';
                SET outputOrgHandle = v_orgHandle;
                LEAVE resolve_loop;
            END IF;

            -- No destination and no alias — broken link
            SET outputDestination = v_orgFallback;
            SET outputStatus = 'no_destination';
            SET outputOrgHandle = v_orgHandle;
            LEAVE resolve_loop;

        END WHILE;

        -- Max hops exceeded
        IF v_hopCount >= v_maxHops AND outputStatus IS NULL THEN
            SET outputDestination = v_orgFallback;
            SET outputStatus = 'max_hops_exceeded';
            SET outputOrgHandle = v_orgHandle;
        END IF;

    END IF;

END//

DELIMITER ;
