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
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
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
    DECLARE v_orgHandle     VARCHAR(50)     DEFAULT NULL;
    DECLARE v_destination   TEXT            DEFAULT NULL;
    DECLARE v_alias         VARCHAR(50)     DEFAULT NULL;
    DECLARE v_isActive      TINYINT         DEFAULT 0;
    DECLARE v_startDate     DATETIME        DEFAULT NULL;
    DECLARE v_endDate       DATETIME        DEFAULT NULL;
    DECLARE v_hopCount      INT             DEFAULT 0;
    DECLARE v_maxHops       INT             DEFAULT 3;
    DECLARE v_currentCode   VARCHAR(50);
    DECLARE v_orgFallback   VARCHAR(500)    DEFAULT NULL;
    DECLARE v_catFallback   VARCHAR(500)    DEFAULT NULL;
    DECLARE v_found         TINYINT         DEFAULT 0;

    -- Ensure UTC timezone for date comparisons
    SET time_zone = '+00:00';

    -- =========================================================================
    -- Step 1: Resolve domain to organisation
    -- =========================================================================
    IF inputDomain IS NOT NULL AND inputDomain != '' THEN
        SELECT osd.orgHandle
        INTO   v_orgHandle
        FROM   tblOrgShortDomains osd
        WHERE  osd.shortDomain = inputDomain
           AND osd.isActive = 1
        LIMIT  1;
    END IF;

    -- Fall back to default org if domain not found
    IF v_orgHandle IS NULL THEN
        SET v_orgHandle = '[default]';
    END IF;

    -- Get org fallback URL
    SELECT o.orgFallbackURL
    INTO   v_orgFallback
    FROM   tblOrganisations o
    WHERE  o.orgHandle = v_orgHandle
    LIMIT  1;

    -- =========================================================================
    -- Step 2: Resolve short code (with alias chain, max 3 hops)
    -- =========================================================================
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

END//

DELIMITER ;
