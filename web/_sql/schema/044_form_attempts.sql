-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Form Attempts (rate limiting for registration, contact,
-- password reset) — #212
-- =============================================================================
-- WHY THIS TABLE EXISTS, AND WHY NOT tblActivityLog
-- --------------------------------------------------
-- Registration had NO rate limit at all before #212. The contact form and
-- forgot-password each had one, but counted it by counting rows in
-- tblActivityLog — a table written by logActivity(), which SKIPS most
-- actions for a visitor who has sent Do Not Track or Global Privacy Control
-- (web/_functions/activity_logger.php's "always log" list is a short set of
-- security actions such as 'login_failed' and 'account_locked'; neither
-- 'contact_form' nor the password-reset action was on it). A visitor who
-- simply turned on "Do Not Track" in their browser was therefore never
-- counted by either of those two forms' old checks, on top of registration
-- having no check to evade in the first place. This table is written
-- directly by web/_functions/form_rate_limit.php, with no DNT check at all,
-- so every attempt at all three forms now counts regardless of the
-- visitor's tracking preference.
--
-- WHY A HASH, NEVER THE ADDRESS ITSELF
-- -------------------------------------
-- ipHash stores hash_hmac('sha256', <the visitor's IP address>, <a server-
-- side secret>) — see g2ml_formRateLimitIpHash() in web/_functions/form_rate_
-- limit.php. Counting attempts only needs to tell "the same visitor asked
-- again" from "a different visitor asked", not who that visitor is. Storing
-- the address itself would mean this table held a second, unprotected copy of
-- personal data (an IP address) that tblActivityLog already collects — a
-- hash that nobody can reverse without the secret key does not need that
-- same protection, and there is no working list to leak.
--
-- WHY ROWS ARE SHORT-LIVED
-- --------------------------
-- No form limit reads this table over more than one hour. Rows older than a
-- day are deleted by g2ml_formRateLimitTake() when a form is next used, so no
-- scheduled job is needed (#178).
--
-- COLUMNS
-- --------
--   formKey    Which form the attempt was against: 'register', 'contact', or
--              'password_reset' today (web/_functions/form_rate_limit.php
--              refuses any other value rather than silently counting it
--              somewhere unexpected). VARCHAR(40), not an ENUM, so a future
--              form can be added without an ALTER TABLE.
--   ipHash     A keyed hash of the visitor's IP address — see above. Never
--              the address itself, and never reversible without the server's
--              own secret key (ENCRYPTION_KEY_SECONDARY).
--   createdAt  When the attempt was recorded, in UTC (the database session
--              already runs in UTC — see web/_functions/db_connect.php).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
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
