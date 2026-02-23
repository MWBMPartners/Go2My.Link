-- =============================================================================
-- Go2My.Link — Database Creation
-- =============================================================================
-- Creates the new database with InnoDB defaults and utf8mb4 character set.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
--
-- Reference: https://dev.mysql.com/doc/refman/8.0/en/create-database.html
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `mwtools_Go2MyLink`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `mwtools_Go2MyLink`;

-- Set session defaults
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 1;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
