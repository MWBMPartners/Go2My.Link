-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Migration 010: Org-invite single-pending key (#122 / B-004)
-- =============================================================================
-- Fixes the re-invite breakage caused by UQ_org_email_pending including the
-- `status` column. With `status` in the key, only ONE row per
-- (orgHandle, email, status) could exist — so once an (org, email) already had
-- a terminal row (cancelled/expired/accepted), expiring or cancelling the next
-- pending invite collided, and the cancel → re-invite cycle failed.
--
-- This migration replaces that key with a VIRTUAL generated column
-- (`pendingKey`) that carries the (orgHandle, email) pair ONLY while pending,
-- otherwise NULL. A UNIQUE key on `pendingKey` enforces "at most ONE pending
-- invite per org+email" (InnoDB exempts NULLs from UNIQUE), while any number of
-- terminal-state rows coexist freely.
--
-- ⚠️  FRESH INSTALLS DO NOT NEED THIS FILE: the same generated column and key
--     are already part of the base schema (014_org_invitations.sql).
--     Run this migration ONLY to bring an ALREADY-IMPORTED earlier schema
--     (3-column UQ_org_email_pending) up to date.
--
-- Additive / ALTER only: ADD INDEX → DROP INDEX → ADD COLUMN → ADD UNIQUE KEY.
--
-- ⚠️  ORDER MATTERS: in the old schema the FK_invitation_org foreign key (on
--     orgHandle) is supported by the composite UQ_org_email_pending index
--     (orgHandle is its leftmost column). MySQL refuses to drop an index a
--     foreign key still needs (errno 1553), so we FIRST add a dedicated
--     IDX_invitation_org index on orgHandle, then drop the composite key.
--
-- NOTE: MySQL does NOT support "ADD COLUMN IF NOT EXISTS"; re-running this
--       migration on a schema that already has `pendingKey` will error (which
--       is the intended guard against double application).
--
-- @package    Go2My.Link
-- @subpackage Migrations
-- @version    1.0.0
-- @since      v1.0.0 — Launch Hardening
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- -----------------------------------------------------------------------------
-- 1. Add a dedicated supporting index for FK_invitation_org on orgHandle
-- -----------------------------------------------------------------------------
-- Must exist BEFORE dropping the composite key, which currently supports the
-- foreign key. Without this, the DROP INDEX below fails with errno 1553.
ALTER TABLE `tblOrgInvitations`
    ADD INDEX `IDX_invitation_org` (`orgHandle`);

-- -----------------------------------------------------------------------------
-- 2. Drop the old 3-column unique key (orgHandle, email, status)
-- -----------------------------------------------------------------------------
ALTER TABLE `tblOrgInvitations`
    DROP INDEX `UQ_org_email_pending`;

-- -----------------------------------------------------------------------------
-- 3. Add the VIRTUAL generated column that is non-NULL only while pending
-- -----------------------------------------------------------------------------
ALTER TABLE `tblOrgInvitations`
    ADD COLUMN `pendingKey` VARCHAR(355)
        GENERATED ALWAYS AS (
            CASE
                WHEN `status` = 'pending'
                THEN CONCAT(`orgHandle`, '\n', `email`)
                ELSE NULL
            END
        ) VIRTUAL
        COMMENT 'orgHandle\\nemail while pending, else NULL — enforces single pending invite'
        AFTER `createdAt`;

-- -----------------------------------------------------------------------------
-- 4. Recreate the unique key on the generated column
-- -----------------------------------------------------------------------------
-- NB: this will fail if the existing data already contains more than one
-- pending invite for the same (orgHandle, email). If that happens, expire the
-- duplicates first:
--     UPDATE tblOrgInvitations SET status = 'expired'
--      WHERE status = 'pending'
--        AND invitationUID NOT IN (
--            SELECT maxUID FROM (
--                SELECT MAX(invitationUID) AS maxUID FROM tblOrgInvitations
--                 WHERE status = 'pending' GROUP BY orgHandle, email
--            ) AS keep
--        );
ALTER TABLE `tblOrgInvitations`
    ADD UNIQUE KEY `UQ_org_email_pending` (`pendingKey`);
