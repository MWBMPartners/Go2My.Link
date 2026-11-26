-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 043: Form attempts rate-limit table (#212)
-- =============================================================================
-- Brings an ALREADY-IMPORTED database up to date with
-- web/_sql/schema/044_form_attempts.sql: creates tblFormAttempts, the table
-- that registration, the contact form and forgot-password now use to count
-- attempts per IP address per hour. The CREATE TABLE statement below is
-- copied UNCHANGED from that schema file — see its own header for why the
-- table exists, why it stores a hash of the IP address rather than the
-- address itself, and why rows are short-lived.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: schema 044 is already part of the
--     base install (the web installer and CI import web/_sql/schema/ in
--     filename order). Run this migration ONLY on a database installed
--     before schema 044 existed. Running it on a fresh install anyway is
--     harmless — CREATE TABLE IF NOT EXISTS does nothing when the table is
--     already there.
--
-- WHY THIS MATTERS ON AN EXISTING DATABASE. Without this table the limit
-- cannot be checked, so all three forms refuse every submission until this
-- migration (or the fresh-install schema file) has run.
--
-- NO information_schema GUARD PROCEDURE NEEDED. This migration only adds a
-- brand-new table with CREATE TABLE IF NOT EXISTS; there is no existing
-- column to check for and nothing partial to guard against, so it is always
-- safe to re-run.
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.2.0 — Phase 8 (#212)
-- =============================================================================

USE `mwtools_Go2MyLink`;

CREATE TABLE IF NOT EXISTS `tblFormAttempts` (
    `attemptUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `formKey`               VARCHAR(40)         NOT NULL
        COMMENT 'Which form: register, contact, password_reset',

    `ipHash`                CHAR(64)            NOT NULL
        COMMENT 'Keyed hash of the visitor IP address, never the address itself',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`attemptUID`),

    -- Read by g2ml_formRateLimitCount(): "how many attempts for this form,
    -- from this hashed IP address, since the start of the window?"
    INDEX `IDX_form_attempts_lookup` (`formKey`, `ipHash`, `createdAt`),

    -- Read by the housekeeping delete in g2ml_formRateLimitTake(), which
    -- removes rows older than a day regardless of which form they belong to.
    INDEX `IDX_form_attempts_created` (`createdAt`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-IP attempt counts for rate-limited public forms (#212). Rows hold a keyed hash of the IP address, never the address itself, and are deleted after a day, when a form is next used.';
