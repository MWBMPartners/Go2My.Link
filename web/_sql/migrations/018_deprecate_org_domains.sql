-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 018: Deprecate tblOrgDomains (GT-6)
-- =============================================================================
-- Background (docs/LAUNCH_PLAN_2026-07-09.md §5 / GT-6, GitHub issue #91):
--
-- `tblOrgDomains` and `tblOrgShortDomains` were two parallel "custom domain"
-- tables. Only `tblOrgShortDomains` was ever wired into the redirect hot path
-- (sp_lookupShortURL / getOrgByDomain / domain_resolver.php) and, later, into
-- the Component C.4 (#46) custom-domain LinksPage fallback
-- (linkspage_fallback.php / linkspage_resolver.php via
-- tblOrgShortDomains.linksPageUID). `tblOrgDomains` had its own, entirely
-- separate DNS-TXT verification flow (verifyDomain() in web/_functions/
-- org.php) and a `domainType` ENUM including 'linkspage' — but NOTHING ever
-- read that column, and NOTHING ever routed a single request off this table.
--
-- The 2026-07-10 GT-6 assessment confirmed, by grepping the entire codebase
-- and the integration test suite:
--   - No FK anywhere references tblOrgDomains.domainUID.
--   - migrations/001_migrate_organisations.sql (the real legacy-data import
--     of 5 organisations) only ever writes tblOrgShortDomains — it never
--     populates tblOrgDomains, contrary to docs/MIGRATION_PLAN.md's old
--     "verify tblOrgDomains entries exist" checklist item (fixed alongside
--     this migration — see that doc's changelog).
--   - No seed file populates tblOrgDomains.
--   - No integration test (tests/integration/*.php) exercises tblOrgDomains,
--     addOrgDomain(), verifyDomain(), removeOrgDomain(), or getOrgDomains().
--   - Its only consumers are its own CRUD functions in web/_functions/org.php
--     and its own admin page (org/domains/index.php).
--
-- Decision: DEPRECATE, do not drop. The admin page (org/domains/index.php)
-- no longer accepts new rows as of this change (its "Add Domain" form was
-- removed and the add_domain POST case rejects the action server-side); it
-- still lets an org VIEW, VERIFY (harmlessly — verification here has no
-- routing effect), and REMOVE any row it already has, so nothing already
-- present is silently orphaned or hidden. The table, its columns, and its
-- backing functions in org.php are left completely intact — this migration
-- changes ONLY the table's COMMENT metadata (see Part 1) and offers an
-- OPTIONAL, manually-reviewed reconciliation audit (see Part 2). No table is
-- dropped, no column is dropped, no row is deleted, and no row is
-- automatically copied anywhere by this file.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED PART 1: the same COMMENT is already part of
--     the base schema (web/_sql/schema/012_core_organisations.sql). Run Part
--     1 only to bring an ALREADY-IMPORTED earlier schema's table comment up
--     to date — it is a metadata-only change, safe to run any number of
--     times, and carries zero risk to data or behaviour.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.1.0 — Phase 7 (GT-6)
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- Part 1 — SAFE, idempotent, auto-appliable: mark the table deprecated.
-- -----------------------------------------------------------------------------
-- A table COMMENT is pure metadata (visible via SHOW CREATE TABLE / SHOW
-- TABLE STATUS / information_schema.TABLES). Setting it — even repeatedly —
-- touches no rows, no columns, no indexes, and no foreign keys. This is the
-- ONLY statement in this file that is safe to run unattended.
-- -----------------------------------------------------------------------------
ALTER TABLE `tblOrgDomains`
    COMMENT = 'DEPRECATED (GT-6) — superseded by tblOrgShortDomains for routing and LinksPage designation; kept for existing rows only, see table comment in web/_sql/schema/012_core_organisations.sql';

-- =============================================================================
-- Part 2 — 🔴 REVIEW BEFORE RUNNING — owner-run reconciliation AUDIT ONLY.
-- =============================================================================
-- Everything below this line is intentionally NOT wired to auto-run (it is
-- not wrapped in a guarded stored procedure the way Part 1 or migration 013
-- are). It is a documented starting point for a human decision, not a script
-- to pipe into `mysql` unattended.
--
-- WHY there is no automatic data-copy INSERT here: a tblOrgDomains row only
-- ever records a hostname + a `domainType` of 'primary', 'redirect', or
-- 'linkspage' — none of which map 1:1 onto "this is meant to receive
-- short-link redirect traffic" (tblOrgShortDomains' entire reason to exist).
-- A 'primary' or 'redirect' brand domain the org registered here may never
-- have been intended to serve g2my.link-style short codes at all. Blindly
-- INSERTing every tblOrgDomains row into tblOrgShortDomains could silently
-- make a brand/marketing hostname routable for short-code lookups — a
-- behaviour change, not a neutral data migration. Each row needs a human,
-- per-row judgment call.
--
-- STEP 1 — see whether this environment has ANY legacy rows at all. On a
-- fresh install, or any environment that never used the org/domains/ admin
-- page before this change, this returns zero rows and nothing further here
-- is needed.
/*
SELECT `domainUID`, `orgHandle`, `domainName`, `domainType`,
       `verificationStatus`, `verifiedAt`, `createdAt`
FROM   `tblOrgDomains`
ORDER BY `orgHandle`, `createdAt`;
*/

-- STEP 2 — for whichever rows STEP 1 returned, check whether the SAME
-- org already registered the SAME hostname as a short domain (the two
-- verification flows are independent, so this is possible even though it
-- would be redundant). Rows returned here are the safest, lowest-judgment
-- candidates to simply remove from tblOrgDomains (the org already has a
-- live, routable equivalent).
/*
SELECT  d.`domainUID`, d.`orgHandle`, d.`domainName`, d.`domainType`,
        sd.`shortDomainUID`, sd.`verificationStatus` AS `shortDomainStatus`,
        sd.`isActive`                                AS `shortDomainIsActive`
FROM    `tblOrgDomains` d
INNER JOIN `tblOrgShortDomains` sd
        ON  sd.`orgHandle`   = d.`orgHandle`
        AND sd.`shortDomain` = d.`domainName`;
*/

-- STEP 3 — for whichever rows STEP 1 returned that did NOT show up in
-- STEP 2 (no equivalent short domain exists yet), decide PER ROW:
--   (a) the org actually wants this hostname to route short links →
--       have the org (or an admin on their behalf) add it fresh via
--       Organisation → Short Domains (org/short-domains/index.php), which
--       runs the REAL verification flow (#91) end to end — do NOT hand-
--       insert a row, because addOrgShortDomain() also issues a fresh,
--       correctly-generated verificationToken and enforces the org's
--       quota (org.max_short_domains / tier maxCustomDomains, #146); a
--       hand-written INSERT would bypass both.
--   (b) the row was only ever a brand/marketing domain with no short-link
--       intent → leave it in tblOrgDomains (harmless, inert) or remove it
--       via the org/domains/ admin page's existing Remove action.
--
-- There is deliberately no "just run this" statement for STEP 3 — it is a
-- product decision per row, not a mechanical migration.
