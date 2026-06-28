-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 📧 Go2My.Link — Organisation Invitations Table
-- ============================================================================
--
-- Tracks pending, accepted, expired, and cancelled organisation invitations.
-- Tokens are stored as SHA-256 hashes; plaintext is only sent via email.
--
-- Dependencies: tblOrganisations (012), tblUsers (013)
--
-- @package    Go2My.Link
-- @subpackage Database
-- @version    0.6.0
-- @since      Phase 5
-- ============================================================================

USE `mwtools_Go2MyLink`;

CREATE TABLE IF NOT EXISTS `tblOrgInvitations` (
    -- ========================================================================
    -- Primary Key
    -- ========================================================================
    `invitationUID`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- ========================================================================
    -- Invitation Details
    -- ========================================================================
    `orgHandle`         VARCHAR(50)     NOT NULL                             COMMENT 'FK → tblOrganisations.orgHandle',
    `email`             VARCHAR(255)    NOT NULL                             COMMENT 'Invitee email address (lowercased)',
    `role`              ENUM('User', 'Admin') NOT NULL DEFAULT 'User'       COMMENT 'Role assigned on acceptance',
    `invitationToken`   VARCHAR(255)    NOT NULL                             COMMENT 'SHA-256 hash of plaintext token',
    `invitedByUserUID`  BIGINT UNSIGNED NOT NULL                             COMMENT 'FK → tblUsers.userUID (who sent invite)',
    `status`            ENUM('pending', 'accepted', 'expired', 'cancelled')
                        NOT NULL DEFAULT 'pending'                           COMMENT 'Invitation lifecycle status',
    `expiresAt`         DATETIME        NOT NULL                             COMMENT 'Token expiry (default 7 days)',
    `acceptedAt`        DATETIME        NULL DEFAULT NULL                    COMMENT 'When invitation was accepted',
    `createdAt`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP   COMMENT 'When invitation was sent',

    -- ========================================================================
    -- Single-Pending Guard (generated column)
    -- ========================================================================
    -- Holds the (orgHandle, email) pair ONLY while the invitation is pending,
    -- otherwise NULL. Putting the UNIQUE key on this column lets MySQL/InnoDB
    -- enforce "at most ONE pending invite per org+email" (NULLs are exempt from
    -- UNIQUE), while any number of accepted/expired/cancelled rows may coexist
    -- — so cancel → re-invite cycles work. The separator is a newline, which
    -- cannot appear in a valid org handle or email address.
    -- Size 355 ≈ 50 (handle) + 1 (separator) + 255 (email), rounded up.
    -- 📖 Reference: https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html
    `pendingKey`        VARCHAR(355)
                        GENERATED ALWAYS AS (
                            CASE
                                WHEN `status` = 'pending'
                                THEN CONCAT(`orgHandle`, '\n', `email`)
                                ELSE NULL
                            END
                        ) VIRTUAL
                        COMMENT 'orgHandle\\nemail while pending, else NULL — enforces single pending invite',

    -- ========================================================================
    -- Keys & Indexes
    -- ========================================================================
    PRIMARY KEY (`invitationUID`),

    -- Prevent duplicate PENDING invitations for the same email+org
    -- (allows re-inviting after cancellation/expiry — terminal-state rows are
    -- exempt because pendingKey is NULL for them).
    UNIQUE KEY `UQ_org_email_pending` (`pendingKey`),

    -- Supporting index for the FK_invitation_org foreign key on orgHandle.
    -- Previously the FK leaned on the (orgHandle, email, status) composite key;
    -- now that the unique key is on pendingKey, orgHandle needs its own index.
    INDEX `IDX_invitation_org` (`orgHandle`),

    -- Token lookup for acceptance
    INDEX `IDX_invitation_token` (`invitationToken`),

    -- Cleanup queries: find expired pending invitations
    INDEX `IDX_invitation_status` (`status`, `expiresAt`),

    -- Inviter lookup
    INDEX `IDX_invited_by` (`invitedByUserUID`),

    -- ========================================================================
    -- Foreign Keys
    -- ========================================================================
    CONSTRAINT `FK_invitation_org`
        FOREIGN KEY (`orgHandle`) REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT `FK_invitation_inviter`
        FOREIGN KEY (`invitedByUserUID`) REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Organisation membership invitations with tokenised email acceptance';
