-- =============================================================================
-- GoToMyLink — Users & Authentication Tables
-- =============================================================================
-- Migrated from tblCustomers. Adds Argon2id hashing, role-based access,
-- 2FA (TOTP), PassKey (WebAuthn), social login, and session management.
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Users
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblUsers` (
    `userUID`               BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT
        COMMENT 'Unique row identifier',

    `orgHandle`             VARCHAR(50)         NOT NULL DEFAULT '[default]'
        COMMENT 'FK to tblOrganisations.orgHandle',

    `username`              VARCHAR(50)         NOT NULL
        COMMENT 'Unique username for login (migrated from custUsername)',

    `email`                 VARCHAR(255)        NOT NULL
        COMMENT 'Primary email address',

    `emailVerified`         TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the email address has been verified',

    `emailVerifiedAt`       DATETIME            DEFAULT NULL
        COMMENT 'When the email was verified',

    `emailVerifyToken`      VARCHAR(255)        DEFAULT NULL
        COMMENT 'Token sent for email verification',

    `emailVerifyExpiry`     DATETIME            DEFAULT NULL
        COMMENT 'When the verification token expires',

    `passwordHash`          VARCHAR(255)        NOT NULL
        COMMENT 'Argon2id password hash (bcrypt fallback for PHP 8.4)',

    `passwordResetToken`    VARCHAR(255)        DEFAULT NULL
        COMMENT 'Token for password reset flow',

    `passwordResetExpiry`   DATETIME            DEFAULT NULL
        COMMENT 'When the password reset token expires',

    `passwordChangedAt`     DATETIME            DEFAULT NULL
        COMMENT 'When the password was last changed',

    `forcePasswordReset`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Force user to change password on next login (1 = yes)',

    `firstName`             VARCHAR(255)        NOT NULL DEFAULT ''
        COMMENT 'First/given name',

    `lastName`              VARCHAR(255)        NOT NULL DEFAULT ''
        COMMENT 'Last/family name',

    `displayName`           VARCHAR(255)        DEFAULT NULL
        COMMENT 'Public display name (defaults to firstName + lastName)',

    `avatarPath`            VARCHAR(500)        DEFAULT NULL
        COMMENT 'Path to avatar image (relative to _uploads/)',

    `role`                  ENUM('GlobalAdmin', 'Admin', 'User', 'Anonymous')
                            NOT NULL DEFAULT 'User'
        COMMENT 'User role within their organisation',

    `timezone`              VARCHAR(50)         DEFAULT 'UTC'
        COMMENT 'User preferred timezone (IANA format)',

    `locale`                VARCHAR(10)         DEFAULT 'en-GB'
        COMMENT 'User preferred locale (BCP 47)',

    -- 2FA (TOTP)
    `twoFactorEnabled`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether 2FA is enabled',

    `twoFactorSecret`       VARCHAR(255)        DEFAULT NULL
        COMMENT 'TOTP secret key (encrypted)',

    `twoFactorRecoveryCodes` TEXT               DEFAULT NULL
        COMMENT 'JSON array of hashed recovery codes',

    `twoFactorVerifiedAt`   DATETIME            DEFAULT NULL
        COMMENT 'When 2FA was last verified',

    -- Account status
    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether the account is active',

    `isSuspended`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether the account is suspended by admin',

    `suspendedReason`       TEXT                DEFAULT NULL
        COMMENT 'Reason for suspension',

    `lastLoginAt`           DATETIME            DEFAULT NULL
        COMMENT 'Last successful login timestamp',

    `lastLoginIP`           VARCHAR(45)         DEFAULT NULL
        COMMENT 'IP address of last login (supports IPv6)',

    `failedLoginAttempts`   INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Consecutive failed login attempts',

    `lockedUntil`           DATETIME            DEFAULT NULL
        COMMENT 'Account locked until this time (after too many failures)',

    `userNotes`             TEXT                DEFAULT NULL
        COMMENT 'Internal admin notes',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`userUID`),
    UNIQUE KEY `UQ_username` (`username`),
    UNIQUE KEY `UQ_email` (`email`),
    INDEX `IDX_user_org` (`orgHandle`),
    INDEX `IDX_user_role` (`role`),
    INDEX `IDX_user_active` (`isActive`),
    INDEX `IDX_user_email_verify_token` (`emailVerifyToken`),
    INDEX `IDX_user_password_reset_token` (`passwordResetToken`),

    CONSTRAINT `FK_user_org`
        FOREIGN KEY (`orgHandle`)
        REFERENCES `tblOrganisations` (`orgHandle`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='User accounts with authentication, roles, and 2FA';

-- =============================================================================
-- User Social Logins (OAuth 2.0 providers)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblUserSocialLogins` (
    `socialLoginUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID',

    `provider`              VARCHAR(50)         NOT NULL
        COMMENT 'OAuth provider name (microsoft, apple, google, facebook, yahoo, amazon)',

    `providerUserID`        VARCHAR(255)        NOT NULL
        COMMENT 'User ID from the OAuth provider',

    `providerEmail`         VARCHAR(255)        DEFAULT NULL
        COMMENT 'Email from the OAuth provider',

    `accessToken`           TEXT                DEFAULT NULL
        COMMENT 'OAuth access token (encrypted)',

    `refreshToken`          TEXT                DEFAULT NULL
        COMMENT 'OAuth refresh token (encrypted)',

    `tokenExpiresAt`        DATETIME            DEFAULT NULL
        COMMENT 'When the access token expires',

    `providerData`          JSON                DEFAULT NULL
        COMMENT 'Additional provider-specific data',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`socialLoginUID`),
    UNIQUE KEY `UQ_provider_user` (`provider`, `providerUserID`),
    INDEX `IDX_social_user` (`userUID`),

    CONSTRAINT `FK_social_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='User OAuth/social login provider links';

-- =============================================================================
-- User PassKeys (WebAuthn)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblUserPassKeys` (
    `passKeyUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID',

    `credentialID`          VARCHAR(500)        NOT NULL
        COMMENT 'WebAuthn credential ID (base64url encoded)',

    `publicKey`             TEXT                NOT NULL
        COMMENT 'WebAuthn public key (PEM or CBOR encoded)',

    `signCounter`           INT UNSIGNED        NOT NULL DEFAULT 0
        COMMENT 'Signature counter for replay protection',

    `deviceName`            VARCHAR(255)        DEFAULT NULL
        COMMENT 'User-friendly name for this passkey (e.g., "MacBook Pro")',

    `lastUsedAt`            DATETIME            DEFAULT NULL
        COMMENT 'When this passkey was last used',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`passKeyUID`),
    UNIQUE KEY `UQ_credential_id` (`credentialID`(255)),
    INDEX `IDX_passkey_user` (`userUID`),

    CONSTRAINT `FK_passkey_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='User WebAuthn/PassKey credentials';

-- =============================================================================
-- User Sessions
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblUserSessions` (
    `sessionUID`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `userUID`               BIGINT UNSIGNED     NOT NULL
        COMMENT 'FK to tblUsers.userUID',

    `sessionToken`          VARCHAR(255)        NOT NULL
        COMMENT 'Unique session token (hashed)',

    `ipAddress`             VARCHAR(45)         DEFAULT NULL
        COMMENT 'IP address at session start (supports IPv6)',

    `userAgent`             VARCHAR(500)        DEFAULT NULL
        COMMENT 'User agent string at session start',

    `deviceInfo`            VARCHAR(255)        DEFAULT NULL
        COMMENT 'Parsed device info (e.g., "Chrome 120 on macOS")',

    `lastActivityAt`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'Last activity timestamp (for session timeout)',

    `expiresAt`             DATETIME            NOT NULL
        COMMENT 'When this session expires',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this session is active (0 = logged out)',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`sessionUID`),
    UNIQUE KEY `UQ_session_token` (`sessionToken`),
    INDEX `IDX_session_user` (`userUID`),
    INDEX `IDX_session_active` (`isActive`, `expiresAt`),

    CONSTRAINT `FK_session_user`
        FOREIGN KEY (`userUID`)
        REFERENCES `tblUsers` (`userUID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Active user sessions for security tracking';
