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
-- 🔗 #92 UTM forwarding: the link's configured tblShortURLs.utm* columns are
-- projected as five extra OUT parameters alongside the existing three. This
-- is added to the SAME per-hop SELECT that already reads destinationURL —
-- NOT a second query — so a normal redirect still pays for exactly one
-- lookup. The values are only ever populated on the 'success' outcome (the
-- final resolved hop of an alias chain); every other status leaves them NULL,
-- so a caller can safely treat "any outputUtm* is non-NULL" as "this was a
-- successful resolution AND the link owner configured at least one value".
-- The caller (resolveShortCode() in redirect_resolver.php) decides whether to
-- actually forward them onto the destination, gated by the
-- redirect.forward_utm_params setting — this procedure just returns what is
-- stored, unconditionally.
--
-- 📝 #128 destinationType (documentation only, no logic here changed):
-- tblShortURLs.destinationType ('url'/'alias') is NOT read anywhere in this
-- procedure — Step 2's alias-follow below is gated purely on
-- s.redirectAlias being non-NULL/non-empty. This was evaluated for #128 and
-- deliberately left as-is: migration 004_migrate_shorturls.sql guarantees
-- destinationType='alias' exactly when redirectAlias is meaningful for every
-- row currently written by the application (see that migration's own
-- alias-integrity verification section, and shorturl_create.php /
-- urls.php's UPDATE handler, neither of which ever sets redirectAlias), so
-- adding destinationType as a second gate here would buy no correctness
-- today — only extra column reads and a wider WHERE/IF condition on the
-- hot path for every redirect, for a case that cannot currently occur. If a
-- future write path ever sets redirectAlias without also keeping
-- destinationType in sync, the migration's verification queries are the
-- place to catch that drift before it reaches production, not this
-- procedure.
--
-- 🔤 #196 — every comparison below between a local variable/parameter and a
-- table column (the domain lookup, the org fallback lookup, and the short-
-- code/org lookup inside the alias-chain loop) wraps the variable side in
-- CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci. A stored
-- procedure's own variables and IN parameters take their character set and
-- collation from the DATABASE's default, not from the column they are
-- compared against, so on a database whose default is a different utf8mb4
-- collation (utf8mb4_0900_ai_ci, say) those comparisons used to fail with
-- "illegal mix of collations" — before #197, swallowed by an EXIT HANDLER,
-- so every redirect looked up as an ordinary "error" with no further
-- detail. COLLATE alone is not enough on a database whose default
-- character set is not utf8mb4 at all (latin1, say): MySQL rejects
-- utf8mb4_unicode_ci as invalid for a non-utf8mb4 value (error 1253) before
-- it gets as far as comparing anything, so CONVERT(... USING utf8mb4) puts
-- the variable into utf8mb4 first, and the COLLATE that follows then always
-- applies to a value it is valid for, whatever character set and collation
-- the database defaults to. The database itself should still be
-- utf8mb4_unicode_ci (see DEV_NOTES.md); this is a second, independent
-- safeguard, not a replacement for that.
--
-- 🔇 #197 — this used to catch every SQL error and return status 'error'
-- with no further detail, which made a wrong collation, a missing table or
-- a permissions problem indistinguishable from any other resolver failure.
-- Errors now reach PHP, where dbCallProcedure() logs the MySQL message.
-- Every status this procedure sets on purpose (not_found, expired, and so
-- on) is unchanged — only the swallowed catch-all is gone, so a caller that
-- checks the resolver's status set (redirect_resolver.php) sees the same
-- behaviour it always did.
--
-- 🔗 #289 / owner decision D-003 — the shared g2my.link codes. When the
-- Host is g2my.link itself (its tblOrgShortDomains row belongs to
-- '[default]'), or a host nobody has claimed, the FIRST lookup is by
-- tblShortURLs.sharedShortCode, which holds every link that opens on
-- g2my.link whichever organisation owns it. Before #289 this lookup was
-- "shortCode in the '[default]' organisation", so a link made by a member of
-- an organisation with no short domain of its own was given a g2my.link
-- address that never opened. Once that first row is found:
--   - outputOrgHandle is the organisation that OWNS the link, not
--     '[default]'. The redirect page uses it to count the click and to log
--     it, so the organisation sees its own link's statistics. For a code
--     that is not found at all it is still '[default]', as before.
--   - any further alias hops are looked up inside that same owning
--     organisation (shortCode + orgHandle), exactly as an organisation's own
--     domain does, so an alias can never jump into somebody else's link.
--   - when the link does not open (disabled, expired and so on), the visitor
--     goes to the owning organisation's own fallback address when it has
--     set one, and to the default organisation's fallback when it has not.
-- On an organisation's own verified domain nothing changes: every hop is
-- looked up by shortCode + that organisation's handle, which finds the
-- organisation's own links and also any it made on g2my.link before it had
-- a domain of its own.
--
-- 🛡️ #325 — a link Go2My.Link staff have switched off (staffDisabledAt is set)
-- gives its own status, 'staff_disabled', and NO fallback address. An
-- organisation chooses its own fallback address, so sending the visitor there
-- would let the owner of a blocked link still decide where visitors go. This
-- applies at every hop of an alias chain. Needs the column from schema 020 or
-- migration 046.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.8.0
-- @since      Phase 1 (ownership-verification gate added v1.1.0 / #91; UTM
--             projection added v1.1.0 / #92; catch-all error handler
--             removed #197; shared g2my.link codes #289)
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
    OUT `outputOrgHandle`    VARCHAR(50),
    OUT `outputUtmSource`    VARCHAR(255),
    OUT `outputUtmMedium`    VARCHAR(255),
    OUT `outputUtmCampaign`  VARCHAR(255),
    OUT `outputUtmTerm`      VARCHAR(255),
    OUT `outputUtmContent`   VARCHAR(255)
)
    READS SQL DATA
    COMMENT 'Resolve a short code to its destination URL with alias chain support (max 3 hops); also projects the link''s configured UTM columns (#92)'
BEGIN
    -- Local variables
    -- (MySQL requires all variable DECLAREs to appear BEFORE any handler DECLARE)
    DECLARE v_orgHandle       VARCHAR(50)     DEFAULT NULL;
    DECLARE v_destination     TEXT            DEFAULT NULL;
    DECLARE v_alias           VARCHAR(50)     DEFAULT NULL;
    DECLARE v_isActive        TINYINT         DEFAULT 0;
    DECLARE v_staffDisabledAt DATETIME        DEFAULT NULL;
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
    DECLARE v_utmSource       VARCHAR(255)    DEFAULT NULL;
    DECLARE v_utmMedium       VARCHAR(255)    DEFAULT NULL;
    DECLARE v_utmCampaign     VARCHAR(255)    DEFAULT NULL;
    DECLARE v_utmTerm         VARCHAR(255)    DEFAULT NULL;
    DECLARE v_utmContent      VARCHAR(255)    DEFAULT NULL;
    -- #289: 1 when this request is for the shared g2my.link codes (see the
    -- header); the owner found by that first lookup; whether the owner is a
    -- different organisation from '[default]'; and the owner's own fallback.
    DECLARE v_sharedLookup    TINYINT         DEFAULT 0;
    DECLARE v_linkOrgHandle   VARCHAR(50)     DEFAULT NULL;
    DECLARE v_ownerChanged    TINYINT         DEFAULT 0;
    DECLARE v_ownerFallback   VARCHAR(500)    DEFAULT NULL;

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
        WHERE  osd.shortDomain = CONVERT(inputDomain USING utf8mb4) COLLATE utf8mb4_unicode_ci
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
        SET outputUtmSource = NULL;
        SET outputUtmMedium = NULL;
        SET outputUtmCampaign = NULL;
        SET outputUtmTerm = NULL;
        SET outputUtmContent = NULL;
    END IF;

    -- =========================================================================
    -- Steps 2 & 3 only run when Step 1 did not already terminate resolution
    -- (i.e. outputStatus is still NULL — same guard style used below for the
    -- max-hops-exceeded case).
    -- =========================================================================
    IF outputStatus IS NULL THEN

        -- #289: g2my.link itself (its row belongs to '[default]') and any
        -- host nobody has claimed both use the shared g2my.link codes.
        IF v_orgHandle = '[default]' THEN
            SET v_sharedLookup = 1;
        END IF;

        -- Get org fallback URL
        SELECT o.orgFallbackURL
        INTO   v_orgFallback
        FROM   tblOrganisations o
        WHERE  o.orgHandle = CONVERT(v_orgHandle USING utf8mb4) COLLATE utf8mb4_unicode_ci
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
            SET v_staffDisabledAt = NULL;
            SET v_startDate = NULL;
            SET v_endDate = NULL;
            SET v_utmSource = NULL;
            SET v_utmMedium = NULL;
            SET v_utmCampaign = NULL;
            SET v_utmTerm = NULL;
            SET v_utmContent = NULL;
            SET v_linkOrgHandle = NULL;

            IF v_sharedLookup = 1 AND v_hopCount = 0 THEN
                -- #289: the first code asked for on g2my.link is looked up
                -- in the shared codes (one indexed lookup on
                -- UQ_url_shared_code), and the row says who owns it.
                SELECT
                    s.`destinationURL`,
                    s.`redirectAlias`,
                    s.`isActive`,
                    s.`staffDisabledAt`,
                    s.`startDate`,
                    s.`endDate`,
                    s.`utmSource`,
                    s.`utmMedium`,
                    s.`utmCampaign`,
                    s.`utmTerm`,
                    s.`utmContent`,
                    s.`orgHandle`,
                    1
                INTO
                    v_destination,
                    v_alias,
                    v_isActive,
                    v_staffDisabledAt,
                    v_startDate,
                    v_endDate,
                    v_utmSource,
                    v_utmMedium,
                    v_utmCampaign,
                    v_utmTerm,
                    v_utmContent,
                    v_linkOrgHandle,
                    v_found
                FROM   `tblShortURLs` s
                WHERE  s.`sharedShortCode` = CONVERT(v_currentCode USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LIMIT  1;

                -- From here on the link's owner is the organisation: its
                -- click is counted for it, and any alias hop is looked up
                -- among its own links only.
                IF v_found = 1 AND v_linkOrgHandle IS NOT NULL THEN
                    IF v_linkOrgHandle <> v_orgHandle THEN
                        SET v_ownerChanged = 1;
                    END IF;
                    SET v_orgHandle = v_linkOrgHandle;
                END IF;
            ELSE
                SELECT
                    s.`destinationURL`,
                    s.`redirectAlias`,
                    s.`isActive`,
                    s.`staffDisabledAt`,
                    s.`startDate`,
                    s.`endDate`,
                    s.`utmSource`,
                    s.`utmMedium`,
                    s.`utmCampaign`,
                    s.`utmTerm`,
                    s.`utmContent`,
                    1
                INTO
                    v_destination,
                    v_alias,
                    v_isActive,
                    v_staffDisabledAt,
                    v_startDate,
                    v_endDate,
                    v_utmSource,
                    v_utmMedium,
                    v_utmCampaign,
                    v_utmTerm,
                    v_utmContent,
                    v_found
                FROM   `tblShortURLs` s
                WHERE  s.`shortCode` = CONVERT(v_currentCode USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   AND s.`orgHandle` = CONVERT(v_orgHandle USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LIMIT  1;
            END IF;

            -- Short code not found
            IF v_found = 0 THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'not_found';
                SET outputOrgHandle = v_orgHandle;
                SET outputUtmSource = NULL;
                SET outputUtmMedium = NULL;
                SET outputUtmCampaign = NULL;
                SET outputUtmTerm = NULL;
                SET outputUtmContent = NULL;
                LEAVE resolve_loop;
            END IF;

            -- Switched off by staff (#325): no fallback address
            IF v_staffDisabledAt IS NOT NULL THEN
                SET outputDestination = NULL;
                SET outputStatus = 'staff_disabled';
                SET outputOrgHandle = v_orgHandle;
                SET outputUtmSource = NULL;
                SET outputUtmMedium = NULL;
                SET outputUtmCampaign = NULL;
                SET outputUtmTerm = NULL;
                SET outputUtmContent = NULL;
                LEAVE resolve_loop;
            END IF;

            -- Check if active
            IF v_isActive = 0 THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'inactive';
                SET outputOrgHandle = v_orgHandle;
                SET outputUtmSource = NULL;
                SET outputUtmMedium = NULL;
                SET outputUtmCampaign = NULL;
                SET outputUtmTerm = NULL;
                SET outputUtmContent = NULL;
                LEAVE resolve_loop;
            END IF;

            -- Check date range validity
            IF v_startDate IS NOT NULL AND v_startDate > NOW() THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'not_yet_active';
                SET outputOrgHandle = v_orgHandle;
                SET outputUtmSource = NULL;
                SET outputUtmMedium = NULL;
                SET outputUtmCampaign = NULL;
                SET outputUtmTerm = NULL;
                SET outputUtmContent = NULL;
                LEAVE resolve_loop;
            END IF;

            IF v_endDate IS NOT NULL AND v_endDate < NOW() THEN
                SET outputDestination = v_orgFallback;
                SET outputStatus = 'expired';
                SET outputOrgHandle = v_orgHandle;
                SET outputUtmSource = NULL;
                SET outputUtmMedium = NULL;
                SET outputUtmCampaign = NULL;
                SET outputUtmTerm = NULL;
                SET outputUtmContent = NULL;
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
                -- #92: only the FINAL resolved hop's UTM columns are ever
                -- surfaced — an alias chain's intermediate hops never
                -- contribute (each iteration resets v_utm* to NULL above, so
                -- these are always this hop's own stored values).
                SET outputUtmSource = v_utmSource;
                SET outputUtmMedium = v_utmMedium;
                SET outputUtmCampaign = v_utmCampaign;
                SET outputUtmTerm = v_utmTerm;
                SET outputUtmContent = v_utmContent;
                LEAVE resolve_loop;
            END IF;

            -- No destination and no alias — broken link
            SET outputDestination = v_orgFallback;
            SET outputStatus = 'no_destination';
            SET outputOrgHandle = v_orgHandle;
            SET outputUtmSource = NULL;
            SET outputUtmMedium = NULL;
            SET outputUtmCampaign = NULL;
            SET outputUtmTerm = NULL;
            SET outputUtmContent = NULL;
            LEAVE resolve_loop;

        END WHILE;

        -- Max hops exceeded
        IF v_hopCount >= v_maxHops AND outputStatus IS NULL THEN
            SET outputDestination = v_orgFallback;
            SET outputStatus = 'max_hops_exceeded';
            SET outputOrgHandle = v_orgHandle;
            SET outputUtmSource = NULL;
            SET outputUtmMedium = NULL;
            SET outputUtmCampaign = NULL;
            SET outputUtmTerm = NULL;
            SET outputUtmContent = NULL;
        END IF;

        -- #289: a g2my.link link owned by another organisation that did not
        -- open sends the visitor to that organisation's own fallback
        -- address, when it has set one (the same address its own domain
        -- would use). This extra lookup only happens when the link did NOT
        -- open, so a normal redirect pays nothing for it.
        IF v_ownerChanged = 1 AND outputStatus NOT IN ('success', 'staff_disabled') THEN
            SELECT o.`orgFallbackURL`
            INTO   v_ownerFallback
            FROM   `tblOrganisations` o
            WHERE  o.`orgHandle` = CONVERT(v_orgHandle USING utf8mb4) COLLATE utf8mb4_unicode_ci
            LIMIT  1;

            IF v_ownerFallback IS NOT NULL AND v_ownerFallback <> '' THEN
                SET outputDestination = v_ownerFallback;
            END IF;
        END IF;

    END IF;

END//

DELIMITER ;
