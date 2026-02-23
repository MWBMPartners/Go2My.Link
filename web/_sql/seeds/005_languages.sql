-- =============================================================================
-- Go2My.Link — Seed Data: Languages
-- =============================================================================
-- Default supported languages for the i18n system.
-- English (en-GB) is the base/default language.
-- Others are seeded but inactive until formal translations are available.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblLanguages` (
    `localeCode`, `languageName`, `nativeName`,
    `direction`, `isDefault`, `isActive`, `completionPercent`, `sortOrder`
) VALUES
('en-GB', 'English (UK)',           'English',       'ltr', 1, 1, 100, 1),
('en-US', 'English (US)',           'English',       'ltr', 0, 0,   0, 2),
('es',    'Spanish',                'Espanol',       'ltr', 0, 0,   0, 3),
('fr',    'French',                 'Francais',      'ltr', 0, 0,   0, 4),
('de',    'German',                 'Deutsch',       'ltr', 0, 0,   0, 5),
('pt-BR', 'Portuguese (Brazil)',    'Portugues',     'ltr', 0, 0,   0, 6),
('ar',    'Arabic',                 'العربية',       'rtl', 0, 0,   0, 7),
('zh-CN', 'Chinese (Simplified)',   '简体中文',       'ltr', 0, 0,   0, 8),
('ja',    'Japanese',               '日本語',         'ltr', 0, 0,   0, 9),
('hi',    'Hindi',                  'हिन्दी',         'ltr', 0, 0,   0, 10)
ON DUPLICATE KEY UPDATE
    `languageName` = VALUES(`languageName`),
    `updatedAt` = NOW();
