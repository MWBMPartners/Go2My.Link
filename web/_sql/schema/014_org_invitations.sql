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
    -- Keys & Indexes
    -- ========================================================================
    PRIMARY KEY (`invitationUID`),

    -- Prevent duplicate pending invitations for the same email+org
    -- (allows re-inviting after cancellation/expiry)
    UNIQUE KEY `UQ_org_email_pending` (`orgHandle`, `email`, `status`),

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
