-- =============================================================================
-- GoToMyLink — Seed Data: Default Settings
-- =============================================================================
-- Platform-wide default settings.
-- These are Default-scope entries — can be overridden at System/Org/User level.
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSettings` (
    `settingID`, `settingScope`, `settingScopeRef`,
    `settingValue`, `settingDefault`, `settingDescription`,
    `settingDataType`, `isSensitive`, `isEditable`
) VALUES
-- =========================================================================
-- Site settings
-- =========================================================================
('site.name', 'Default', NULL,
 'GoToMyLink', 'GoToMyLink', 'Platform display name',
 'string', 0, 1),

('site.tagline', 'Default', NULL,
 'Shorten. Track. Manage.', 'Shorten. Track. Manage.', 'Platform tagline',
 'string', 0, 1),

('site.contact_email', 'Default', NULL,
 'support@go2my.link', 'support@go2my.link', 'Primary contact email',
 'email', 0, 1),

('site.default_locale', 'Default', NULL,
 'en-GB', 'en-GB', 'Default locale for the platform',
 'string', 0, 1),

('site.timezone', 'Default', NULL,
 'UTC', 'UTC', 'Default timezone (IANA format)',
 'string', 0, 1),

-- =========================================================================
-- Redirect settings
-- =========================================================================
('redirect.max_hops', 'Default', NULL,
 '3', '3', 'Maximum alias chain hops before failing',
 'integer', 0, 1),

('redirect.validate_destination', 'Default', NULL,
 '1', '1', 'Whether to validate destination URL accessibility before redirecting',
 'boolean', 0, 1),

('redirect.default_code', 'Default', NULL,
 '302', '302', 'Default HTTP redirect status code (301 or 302)',
 'integer', 0, 1),

('redirect.fallback_url', 'Default', NULL,
 'https://go2my.link', 'https://go2my.link', 'Fallback URL when a short code cannot be resolved',
 'url', 0, 1),

-- =========================================================================
-- Short code settings
-- =========================================================================
('shortcode.default_length', 'Default', NULL,
 '7', '7', 'Default length for auto-generated short codes',
 'integer', 0, 1),

('shortcode.allowed_chars', 'Default', NULL,
 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
 'Characters allowed in auto-generated short codes',
 'string', 0, 0),

-- =========================================================================
-- Security settings
-- =========================================================================
('security.session_lifetime', 'Default', NULL,
 '86400', '86400', 'Session lifetime in seconds (default 24 hours)',
 'integer', 0, 1),

('security.max_login_attempts', 'Default', NULL,
 '5', '5', 'Maximum failed login attempts before lockout',
 'integer', 0, 1),

('security.lockout_duration', 'Default', NULL,
 '900', '900', 'Account lockout duration in seconds (default 15 minutes)',
 'integer', 0, 1),

('security.password_min_length', 'Default', NULL,
 '8', '8', 'Minimum password length',
 'integer', 0, 1),

('security.require_2fa_admins', 'Default', NULL,
 '0', '0', 'Whether 2FA is required for Admin and GlobalAdmin roles',
 'boolean', 0, 1),

-- =========================================================================
-- Analytics settings
-- =========================================================================
('analytics.log_activity', 'Default', NULL,
 '1', '1', 'Whether to log redirect activity',
 'boolean', 0, 1),

('analytics.geoip_enabled', 'Default', NULL,
 '1', '1', 'Whether GeoIP lookup is enabled for analytics',
 'boolean', 0, 1),

('analytics.ua_parsing_enabled', 'Default', NULL,
 '1', '1', 'Whether User-Agent parsing is enabled for analytics',
 'boolean', 0, 1),

-- =========================================================================
-- API settings
-- =========================================================================
('api.enabled', 'Default', NULL,
 '1', '1', 'Whether the API is enabled globally',
 'boolean', 0, 1),

('api.default_format', 'Default', NULL,
 'json', 'json', 'Default API response format',
 'string', 0, 1),

-- =========================================================================
-- LinksPage settings
-- =========================================================================
('linkspage.enabled', 'Default', NULL,
 '1', '1', 'Whether LinksPage service is enabled',
 'boolean', 0, 1),

('linkspage.default_template', 'Default', NULL,
 'default', 'default', 'Default template for new LinksPages',
 'string', 0, 1),

-- =========================================================================
-- Debug/maintenance settings
-- =========================================================================
('debug.enabled', 'Default', NULL,
 '0', '0', 'Whether debug mode is enabled globally (?debug=true still requires admin IP)',
 'boolean', 0, 1),

('maintenance.enabled', 'Default', NULL,
 '0', '0', 'Whether maintenance mode is active',
 'boolean', 0, 1),

('maintenance.message', 'Default', NULL,
 'We are currently performing scheduled maintenance. Please check back shortly.',
 'We are currently performing scheduled maintenance. Please check back shortly.',
 'Message displayed during maintenance mode',
 'string', 0, 1)

ON DUPLICATE KEY UPDATE
    `settingValue` = VALUES(`settingValue`),
    `updatedAt` = NOW();
