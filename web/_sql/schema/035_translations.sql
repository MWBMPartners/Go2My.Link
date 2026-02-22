-- =============================================================================
-- GoToMyLink — Translation / i18n Tables
-- =============================================================================
-- Supports the __('key') translation function and locale management.
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

-- =============================================================================
-- Languages
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblLanguages` (
    `languageUID`           BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `localeCode`            VARCHAR(10)         NOT NULL
        COMMENT 'BCP 47 locale code (e.g., en-GB, es, fr, ar, zh-CN)',

    `languageName`          VARCHAR(100)        NOT NULL
        COMMENT 'Language name in English (e.g., English, Spanish)',

    `nativeName`            VARCHAR(100)        NOT NULL
        COMMENT 'Language name in its own language (e.g., English, Espanol)',

    `direction`             ENUM('ltr', 'rtl')  NOT NULL DEFAULT 'ltr'
        COMMENT 'Text direction',

    `isDefault`             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this is the default/base language',

    `isActive`              TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Whether this language is available for selection',

    `completionPercent`     TINYINT UNSIGNED    NOT NULL DEFAULT 0
        COMMENT 'Percentage of strings translated (0-100)',

    `sortOrder`             INT UNSIGNED        NOT NULL DEFAULT 0,
    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`languageUID`),
    UNIQUE KEY `UQ_locale_code` (`localeCode`),
    INDEX `IDX_language_active` (`isActive`, `sortOrder`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Supported languages for UI translation';

-- =============================================================================
-- Translations (key-value translation strings)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `tblTranslations` (
    `translationUID`        BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    `localeCode`            VARCHAR(10)         NOT NULL
        COMMENT 'FK to tblLanguages.localeCode',

    `translationKey`        VARCHAR(255)        NOT NULL
        COMMENT 'Dot-notation key (e.g., home.hero.title, nav.login, error.404.message)',

    `translationValue`      TEXT                NOT NULL
        COMMENT 'Translated string (supports {placeholder} syntax)',

    `context`               VARCHAR(255)        DEFAULT NULL
        COMMENT 'Context hint for translators (e.g., "Button label on homepage")',

    `isVerified`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Whether this translation has been verified by a human',

    `createdAt`             DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt`             DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`translationUID`),
    UNIQUE KEY `UQ_locale_key` (`localeCode`, `translationKey`),
    INDEX `IDX_translation_key` (`translationKey`),
    INDEX `IDX_translation_locale` (`localeCode`),

    CONSTRAINT `FK_translation_locale`
        FOREIGN KEY (`localeCode`)
        REFERENCES `tblLanguages` (`localeCode`)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='UI translation strings per locale using dot-notation keys';
