-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Stored Procedure: sp_generateShortCode
-- =============================================================================
-- Generates a unique random alphanumeric short code.
-- Checks for collisions against existing codes in the given org.
--
-- 🔤 #196 — the collision check below wraps its local variable and its
-- parameter in CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci. A
-- variable or parameter inside a stored procedure takes ITS character set
-- and collation from the database's default, not from the table column it
-- is compared against, so on a database whose default is a different
-- utf8mb4 collation (a hosting panel's own default, say) the comparison
-- used to fail with "illegal mix of collations" — an error this procedure's
-- own handler below swallows, so the only symptom was this procedure
-- silently returning NULL. COLLATE alone is not enough on a database whose
-- default character set is not utf8mb4 at all (latin1, say): MySQL rejects
-- utf8mb4_unicode_ci as invalid for a non-utf8mb4 value (error 1253) before
-- it gets as far as comparing anything, so CONVERT(... USING utf8mb4) puts
-- the variable into utf8mb4 first, and the COLLATE that follows then always
-- applies to a value it is valid for, whatever character set and collation
-- the database defaults to. The database itself should still be
-- utf8mb4_unicode_ci (see DEV_NOTES.md); this is a second, independent
-- safeguard, not a replacement for that.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.4.0
-- @since      Phase 1 (collation safeguard added #196)
-- =============================================================================

USE `mwtools_Go2MyLink`;

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_generateShortCode`//

CREATE PROCEDURE `sp_generateShortCode`(
    IN  `inputOrgHandle`    VARCHAR(50),
    IN  `inputLength`       INT,
    OUT `outputCode`        VARCHAR(50)
)
    READS SQL DATA
    COMMENT 'Generate a unique random alphanumeric short code for an organisation'
BEGIN
    DECLARE v_candidate     VARCHAR(50);
    DECLARE v_exists        INT DEFAULT 1;
    DECLARE v_attempts      INT DEFAULT 0;
    DECLARE v_maxAttempts   INT DEFAULT 20;
    DECLARE v_chars         VARCHAR(62) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    DECLARE v_i             INT;
    DECLARE v_len           INT;

    -- Exception handler: return NULL on any SQL error
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET outputCode = NULL;
    END;

    -- Default length = 7 characters
    SET v_len = IFNULL(inputLength, 7);
    IF v_len < 4 THEN SET v_len = 4; END IF;
    IF v_len > 20 THEN SET v_len = 20; END IF;

    -- Default org
    IF inputOrgHandle IS NULL OR inputOrgHandle = '' THEN
        SET inputOrgHandle = '[default]';
    END IF;

    -- Generate candidates until we find one that doesn't exist
    WHILE v_exists > 0 AND v_attempts < v_maxAttempts DO
        SET v_candidate = '';
        SET v_i = 0;

        -- Build random string character by character
        WHILE v_i < v_len DO
            SET v_candidate = CONCAT(
                v_candidate,
                SUBSTRING(v_chars, FLOOR(1 + RAND() * 62), 1)
            );
            SET v_i = v_i + 1;
        END WHILE;

        -- Check if this code already exists in the org
        SELECT COUNT(*)
        INTO   v_exists
        FROM   tblShortURLs
        WHERE  shortCode = CONVERT(v_candidate USING utf8mb4) COLLATE utf8mb4_unicode_ci
           AND orgHandle = CONVERT(inputOrgHandle USING utf8mb4) COLLATE utf8mb4_unicode_ci;

        SET v_attempts = v_attempts + 1;
    END WHILE;

    -- Return the unique code (or NULL if max attempts exceeded)
    IF v_exists = 0 THEN
        SET outputCode = v_candidate;
    ELSE
        SET outputCode = NULL;
    END IF;

END//

DELIMITER ;
