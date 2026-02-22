-- =============================================================================
-- GoToMyLink — Stored Procedure: sp_generateShortCode
-- =============================================================================
-- Generates a unique random alphanumeric short code.
-- Checks for collisions against existing codes in the given org.
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
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
        WHERE  shortCode = v_candidate
           AND orgHandle = inputOrgHandle;

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
