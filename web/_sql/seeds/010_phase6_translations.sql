-- ============================================================================
-- 🌍 Go2My.Link — Phase 6 Seed: en-GB Translations
-- ============================================================================
-- Seeds the English (UK) baseline translations for all UI strings.
-- Total: ~1075 translation keys.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB language)
-- @version    0.7.0
-- @since      Phase 6
-- ============================================================================

-- ============================================================================
-- 🔧 Accessibility (a11y.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES ('en-GB', 'a11y.skip_to_content', 'Skip to main content', 'Accessibility', 1);

-- ============================================================================
-- ℹ️ About Page (about.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'about.contact_cta', 'Get in Touch', 'About page', 1),
('en-GB', 'about.description', 'Learn about Go2My.Link — the smart URL shortening platform.', 'About page', 1),
('en-GB', 'about.heading', 'About Go2My.Link', 'About page', 1),
('en-GB', 'about.mission_heading', 'Our Mission', 'About page', 1),
('en-GB', 'about.mission_text', 'Go2My.Link was built to make link management simple, secure, and powerful. Whether you''re sharing a single URL or managing thousands of links across an organisation, we provide the tools you need to shorten, track, and manage your links with confidence.', 'About page', 1),
('en-GB', 'about.offer_analytics', 'Detailed Analytics', 'About page', 1),
('en-GB', 'about.offer_analytics_desc', 'Understand your audience with click tracking, geographic data, device breakdowns, and referrer insights.', 'About page', 1),
('en-GB', 'about.offer_heading', 'What We Offer', 'About page', 1),
('en-GB', 'about.offer_orgs', 'Organisation Management', 'About page', 1),
('en-GB', 'about.offer_orgs_desc', 'Manage links across teams with role-based access, custom domains per organisation, and shared link libraries.', 'About page', 1),
('en-GB', 'about.offer_security', 'Enterprise Security', 'About page', 1),
('en-GB', 'about.offer_security_desc', 'AES-256 encryption at rest, two-factor authentication, SSO integration, and comprehensive audit logging.', 'About page', 1),
('en-GB', 'about.offer_shorten', 'URL Shortening', 'About page', 1),
('en-GB', 'about.offer_shorten_desc', 'Create clean, memorable short links from long URLs. Use our default g2my.link domain or bring your own custom domain.', 'About page', 1),
('en-GB', 'about.subtitle', 'Smarter links for a connected world.', 'About page', 1),
('en-GB', 'about.team_heading', 'Built by MWBM Partners', 'About page', 1),
('en-GB', 'about.team_text', 'Go2My.Link is developed and maintained by MWBM Partners Ltd (trading as MWservices), a technology company focused on building practical, reliable web tools. We believe in clean architecture, strong security, and accessible design.', 'About page', 1),
('en-GB', 'about.title', 'About', 'About page', 1);

-- ============================================================================
-- 🔄 Common (common.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'common.cancel', 'Cancel', 'Common UI', 1),
('en-GB', 'common.close', 'Close', 'Common UI', 1);

-- ============================================================================
-- 🍪 Consent Preferences (consent.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'consent.always_on', 'Always On', 'Cookie consent preferences', 1),
('en-GB', 'consent.analytics_desc', 'Help us understand how visitors interact with the site by collecting anonymous usage statistics.', 'Cookie consent preferences', 1),
('en-GB', 'consent.analytics_label', 'Analytics Cookies', 'Cookie consent preferences', 1),
('en-GB', 'consent.back_privacy', 'Back to Privacy & Data', 'Cookie consent preferences', 1),
('en-GB', 'consent.breadcrumb_consent', 'Cookie Preferences', 'Cookie consent preferences', 1),
('en-GB', 'consent.breadcrumb_privacy', 'Privacy & Data', 'Cookie consent preferences', 1),
('en-GB', 'consent.close', 'Close', 'Cookie consent preferences', 1),
('en-GB', 'consent.col_date', 'Date', 'Cookie consent preferences', 1),
('en-GB', 'consent.col_decision', 'Decision', 'Cookie consent preferences', 1),
('en-GB', 'consent.col_method', 'Method', 'Cookie consent preferences', 1),
('en-GB', 'consent.col_type', 'Category', 'Cookie consent preferences', 1),
('en-GB', 'consent.description', 'Manage your cookie consent preferences.', 'Cookie consent preferences', 1),
('en-GB', 'consent.error_csrf', 'Session expired. Please try again.', 'Cookie consent preferences', 1),
('en-GB', 'consent.error_partial', 'Some preferences could not be saved. Please try again.', 'Cookie consent preferences', 1),
('en-GB', 'consent.essential_desc', 'Required for the site to function. Includes session management, CSRF protection, and authentication cookies.', 'Cookie consent preferences', 1),
('en-GB', 'consent.essential_label', 'Essential Cookies', 'Cookie consent preferences', 1),
('en-GB', 'consent.functional_desc', 'Enable enhanced functionality and personalisation, such as remembering your preferences and settings.', 'Cookie consent preferences', 1),
('en-GB', 'consent.functional_label', 'Functional Cookies', 'Cookie consent preferences', 1),
('en-GB', 'consent.granted', 'Granted', 'Cookie consent preferences', 1),
('en-GB', 'consent.heading', 'Cookie Preferences', 'Cookie consent preferences', 1),
('en-GB', 'consent.history_heading', 'Consent History', 'Cookie consent preferences', 1),
('en-GB', 'consent.history_table_label', 'Consent history', 'Cookie consent preferences', 1),
('en-GB', 'consent.intro', 'Choose which types of cookies you allow. Essential cookies are required for the site to function and cannot be disabled. Changes take effect immediately.', 'Cookie consent preferences', 1),
('en-GB', 'consent.marketing_desc', 'Used to deliver relevant advertisements and measure their effectiveness. May be shared with third-party advertising partners.', 'Cookie consent preferences', 1),
('en-GB', 'consent.marketing_label', 'Marketing Cookies', 'Cookie consent preferences', 1),
('en-GB', 'consent.no_history', 'No consent history recorded yet.', 'Cookie consent preferences', 1),
('en-GB', 'consent.preferences_heading', 'Your Preferences', 'Cookie consent preferences', 1),
('en-GB', 'consent.refused', 'Refused', 'Cookie consent preferences', 1),
('en-GB', 'consent.save_preferences', 'Save Preferences', 'Cookie consent preferences', 1),
('en-GB', 'consent.success_updated', 'Cookie preferences updated successfully.', 'Cookie consent preferences', 1),
('en-GB', 'consent.title', 'Cookie Preferences', 'Cookie consent preferences', 1);

-- ============================================================================
-- 📬 Contact Page (contact.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'contact.description', 'Get in touch with the Go2My.Link team.', 'Contact page', 1),
('en-GB', 'contact.error_csrf', 'Your session has expired. Please reload the page and try again.', 'Contact page', 1),
('en-GB', 'contact.error_email', 'Please enter a valid email address.', 'Contact page', 1),
('en-GB', 'contact.error_rate_limit', 'Too many messages sent. Please try again later.', 'Contact page', 1),
('en-GB', 'contact.error_required', 'Please fill in all required fields.', 'Contact page', 1),
('en-GB', 'contact.error_send', 'Failed to send your message. Please try again later.', 'Contact page', 1),
('en-GB', 'contact.form_heading', 'Send a Message', 'Contact page', 1),
('en-GB', 'contact.heading', 'Contact Us', 'Contact page', 1),
('en-GB', 'contact.label_email', 'Email Address', 'Contact page', 1),
('en-GB', 'contact.label_message', 'Message', 'Contact page', 1),
('en-GB', 'contact.label_name', 'Your Name', 'Contact page', 1),
('en-GB', 'contact.label_subject', 'Subject', 'Contact page', 1),
('en-GB', 'contact.placeholder_email', 'you@example.com', 'Contact page', 1),
('en-GB', 'contact.placeholder_message', 'Your message...', 'Contact page', 1),
('en-GB', 'contact.placeholder_name', 'John Doe', 'Contact page', 1),
('en-GB', 'contact.placeholder_subject', 'How can we help?', 'Contact page', 1),
('en-GB', 'contact.send_button', 'Send Message', 'Contact page', 1),
('en-GB', 'contact.subtitle', 'Have a question or feedback? We''d love to hear from you.', 'Contact page', 1),
('en-GB', 'contact.success', 'Your message has been sent. We''ll get back to you as soon as possible.', 'Contact page', 1),
('en-GB', 'contact.title', 'Contact Us', 'Contact page', 1);

-- ============================================================================
-- 🍪 Cookie Banner (cookie.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'cookie.accept_all', 'Accept All', 'Cookie banner', 1),
('en-GB', 'cookie.analytics', 'Analytics', 'Cookie banner', 1),
('en-GB', 'cookie.analytics_desc', 'Help us understand how visitors use the site so we can improve it.', 'Cookie banner', 1),
('en-GB', 'cookie.banner_message', 'We use cookies to improve your experience. Essential cookies are required for the site to function. You can choose which optional cookies to allow.', 'Cookie banner', 1),
('en-GB', 'cookie.banner_title', 'We Use Cookies', 'Cookie banner', 1),
('en-GB', 'cookie.customise', 'Customise', 'Cookie banner', 1),
('en-GB', 'cookie.essential', 'Essential', 'Cookie banner', 1),
('en-GB', 'cookie.essential_desc', 'Required for the site to function. Includes session cookies and CSRF protection.', 'Cookie banner', 1),
('en-GB', 'cookie.functional', 'Functional', 'Cookie banner', 1),
('en-GB', 'cookie.functional_desc', 'Enable enhanced features like theme preferences and language settings.', 'Cookie banner', 1),
('en-GB', 'cookie.learn_more', 'Learn more', 'Cookie banner', 1),
('en-GB', 'cookie.marketing', 'Marketing', 'Cookie banner', 1),
('en-GB', 'cookie.marketing_desc', 'Used to deliver relevant advertising and track campaign effectiveness.', 'Cookie banner', 1),
('en-GB', 'cookie.preferences_desc', 'Choose which categories of cookies you want to allow. Essential cookies cannot be disabled as they are required for the site to function.', 'Cookie banner', 1),
('en-GB', 'cookie.preferences_title', 'Cookie Preferences', 'Cookie banner', 1),
('en-GB', 'cookie.reject_optional', 'Essential Only', 'Cookie banner', 1),
('en-GB', 'cookie.save_preferences', 'Save Preferences', 'Cookie banner', 1);

-- ============================================================================
-- 🔗 Create Link Page (create_link.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'create_link.description', 'Create a new short link with full options.', 'Create link page', 1),
('en-GB', 'create_link.heading', 'Create a New Link', 'Create link page', 1),
('en-GB', 'create_link.title', 'Create Link', 'Create link page', 1);

-- ============================================================================
-- 📊 Dashboard (dashboard.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'dashboard.active', 'Active', 'Dashboard', 1),
('en-GB', 'dashboard.col_clicks', 'Clicks', 'Dashboard', 1),
('en-GB', 'dashboard.col_created', 'Created', 'Dashboard', 1),
('en-GB', 'dashboard.col_destination', 'Destination', 'Dashboard', 1),
('en-GB', 'dashboard.col_short_url', 'Short URL', 'Dashboard', 1),
('en-GB', 'dashboard.col_status', 'Status', 'Dashboard', 1),
('en-GB', 'dashboard.create_first', 'Create Your First Link', 'Dashboard', 1),
('en-GB', 'dashboard.create_link', 'Create Link', 'Dashboard', 1),
('en-GB', 'dashboard.description', 'Overview of your short links and activity.', 'Dashboard', 1),
('en-GB', 'dashboard.heading', 'Dashboard', 'Dashboard', 1),
('en-GB', 'dashboard.inactive', 'Inactive', 'Dashboard', 1),
('en-GB', 'dashboard.no_links', 'No links yet.', 'Dashboard', 1),
('en-GB', 'dashboard.recent_links', 'Recent Links', 'Dashboard', 1),
('en-GB', 'dashboard.stat_active_links', 'Active Links', 'Dashboard', 1),
('en-GB', 'dashboard.stat_total_clicks', 'Total Clicks', 'Dashboard', 1),
('en-GB', 'dashboard.stat_total_links', 'Total Links', 'Dashboard', 1),
('en-GB', 'dashboard.title', 'Dashboard', 'Dashboard', 1),
('en-GB', 'dashboard.view_all', 'View All', 'Dashboard', 1),
('en-GB', 'dashboard.welcome', 'Welcome back!', 'Dashboard', 1);

-- ============================================================================
-- 🗑️ Delete Account (delete.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'delete.back_privacy', 'Back to Privacy & Data', 'Delete account page', 1),
('en-GB', 'delete.breadcrumb_delete', 'Delete Account', 'Delete account page', 1),
('en-GB', 'delete.breadcrumb_privacy', 'Privacy & Data', 'Delete account page', 1),
('en-GB', 'delete.cancel_button', 'Cancel Deletion Request', 'Delete account page', 1),
('en-GB', 'delete.cancel_confirm', 'Cancel your deletion request and keep your account?', 'Delete account page', 1),
('en-GB', 'delete.cancel_success', 'Deletion request cancelled. Your account will not be deleted.', 'Delete account page', 1),
('en-GB', 'delete.close', 'Close', 'Delete account page', 1),
('en-GB', 'delete.confirm_heading', 'Confirm Account Deletion', 'Delete account page', 1),
('en-GB', 'delete.confirm_intro', 'To proceed, please enter your password to confirm your identity.', 'Delete account page', 1),
('en-GB', 'delete.days', 'days', 'Delete account page', 1),
('en-GB', 'delete.description', 'Request permanent deletion of your account.', 'Delete account page', 1),
('en-GB', 'delete.error_cancel_failed', 'Failed to cancel the deletion request. Please try again.', 'Delete account page', 1),
('en-GB', 'delete.error_csrf', 'Session expired. Please try again.', 'Delete account page', 1),
('en-GB', 'delete.error_generic', 'Failed to submit deletion request. Please try again later.', 'Delete account page', 1),
('en-GB', 'delete.error_not_cancellable', 'This request can no longer be cancelled.', 'Delete account page', 1),
('en-GB', 'delete.error_not_found', 'Deletion request not found.', 'Delete account page', 1),
('en-GB', 'delete.error_password_required', 'Please enter your password to confirm account deletion.', 'Delete account page', 1),
('en-GB', 'delete.error_user_not_found', 'Unable to verify your identity. Please try again.', 'Delete account page', 1),
('en-GB', 'delete.error_wrong_password', 'Incorrect password. Please try again.', 'Delete account page', 1),
('en-GB', 'delete.grace_info_message', 'After submitting your request, you have %d days to change your mind. During this period, you can cancel the request from this page. After the grace period, deletion is irreversible.', 'Delete account page', 1),
('en-GB', 'delete.grace_info_title', 'Grace Period', 'Delete account page', 1),
('en-GB', 'delete.grace_period', 'Grace period', 'Delete account page', 1),
('en-GB', 'delete.heading', 'Delete Your Account', 'Delete account page', 1),
('en-GB', 'delete.optional', 'optional', 'Delete account page', 1),
('en-GB', 'delete.password_help', 'Required to verify your identity before processing the deletion request.', 'Delete account page', 1),
('en-GB', 'delete.password_label', 'Your Password', 'Delete account page', 1),
('en-GB', 'delete.password_placeholder', 'Enter your current password', 'Delete account page', 1),
('en-GB', 'delete.pending_heading', 'Deletion Request Pending', 'Delete account page', 1),
('en-GB', 'delete.pending_message', 'Your account is scheduled for deletion. During the grace period, you can cancel this request and keep your account.', 'Delete account page', 1),
('en-GB', 'delete.reason_help', 'Your feedback is anonymous and helps us improve our service.', 'Delete account page', 1),
('en-GB', 'delete.reason_label', 'Reason for Leaving', 'Delete account page', 1),
('en-GB', 'delete.reason_placeholder', 'Help us improve by sharing why you''re leaving...', 'Delete account page', 1),
('en-GB', 'delete.requested_on', 'Requested on', 'Delete account page', 1),
('en-GB', 'delete.submit_button', 'Request Account Deletion', 'Delete account page', 1),
('en-GB', 'delete.submit_confirm', 'Are you sure you want to request account deletion? You can cancel within the grace period.', 'Delete account page', 1),
('en-GB', 'delete.success_message', 'Your account deletion request has been submitted. You have %d days to cancel before your data is permanently removed.', 'Delete account page', 1),
('en-GB', 'delete.title', 'Delete Your Account', 'Delete account page', 1),
('en-GB', 'delete.warning_analytics', 'Click analytics and usage data associated with your account will be anonymised.', 'Delete account page', 1),
('en-GB', 'delete.warning_heading', 'Warning: This Action is Permanent', 'Delete account page', 1),
('en-GB', 'delete.warning_intro', 'Deleting your account will result in the following:', 'Delete account page', 1),
('en-GB', 'delete.warning_links', 'All your short links will be deactivated and will no longer redirect.', 'Delete account page', 1),
('en-GB', 'delete.warning_profile', 'Your profile and personal information will be permanently removed.', 'Delete account page', 1),
('en-GB', 'delete.warning_sessions', 'All active sessions will be terminated across all devices.', 'Delete account page', 1);

-- ============================================================================
-- ✏️ Edit Link Page (edit_link.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'edit_link.description', 'Edit your short link settings.', 'Edit link page', 1),
('en-GB', 'edit_link.heading', 'Edit Link', 'Edit link page', 1),
('en-GB', 'edit_link.title', 'Edit Link', 'Edit link page', 1);

-- ============================================================================
-- ⚠️ Error Pages (error.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'error.400_description', 'The request could not be understood by the server.', 'Error page — 400', 1),
('en-GB', 'error.400_heading', 'Bad Request', 'Error page — 400', 1),
('en-GB', 'error.400_message', 'The server could not understand your request. Please check the URL and try again.', 'Error page — 400', 1),
('en-GB', 'error.400_title', '400 — Bad Request', 'Error page — 400', 1),
('en-GB', 'error.403_description', 'You do not have permission to access this resource.', 'Error page — 403', 1),
('en-GB', 'error.403_heading', 'Access Denied', 'Error page — 403', 1),
('en-GB', 'error.403_message', 'You don''t have permission to access this page. If you believe this is an error, please contact support.', 'Error page — 403', 1),
('en-GB', 'error.403_title', '403 — Forbidden', 'Error page — 403', 1),
('en-GB', 'error.404.message', 'The page you are looking for could not be found.', 'Error page — 404', 1),
('en-GB', 'error.500_description', 'Something went wrong on our end.', 'Error page — 500', 1),
('en-GB', 'error.500_heading', 'Something Went Wrong', 'Error page — 500', 1),
('en-GB', 'error.500_message', 'We encountered an unexpected error. Our team has been notified. Please try again later.', 'Error page — 500', 1),
('en-GB', 'error.500_title', '500 — Server Error', 'Error page — 500', 1),
('en-GB', 'error.back_home', 'Back to Home', 'Error pages', 1),
('en-GB', 'error.contact_us', 'Contact Us', 'Error pages', 1),
('en-GB', 'error.go_home', 'Go Home', 'Error pages', 1);

-- ============================================================================
-- 📦 Data Export (export.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'export.action_heading', 'Request or Download', 'Data export page', 1),
('en-GB', 'export.back_privacy', 'Back to Privacy & Data', 'Data export page', 1),
('en-GB', 'export.breadcrumb_export', 'Export Your Data', 'Data export page', 1),
('en-GB', 'export.breadcrumb_privacy', 'Privacy & Data', 'Data export page', 1),
('en-GB', 'export.close', 'Close', 'Data export page', 1),
('en-GB', 'export.col_completed', 'Completed', 'Data export page', 1),
('en-GB', 'export.col_expires', 'Expires', 'Data export page', 1),
('en-GB', 'export.col_requested', 'Requested', 'Data export page', 1),
('en-GB', 'export.col_status', 'Status', 'Data export page', 1),
('en-GB', 'export.description', 'Request a copy of your personal data.', 'Data export page', 1),
('en-GB', 'export.download_button', 'Download Export', 'Data export page', 1),
('en-GB', 'export.download_ready', 'Your data export is ready for download.', 'Data export page', 1),
('en-GB', 'export.error_csrf', 'Session expired. Please try again.', 'Data export page', 1),
('en-GB', 'export.error_generic', 'Failed to generate export. Please try again later.', 'Data export page', 1),
('en-GB', 'export.expired', 'Expired', 'Data export page', 1),
('en-GB', 'export.expires', 'Expires', 'Data export page', 1),
('en-GB', 'export.format_note', 'The export is provided as a JSON file. Download links expire after 48 hours for security.', 'Data export page', 1),
('en-GB', 'export.generated', 'Generated', 'Data export page', 1),
('en-GB', 'export.heading', 'Export Your Data', 'Data export page', 1),
('en-GB', 'export.history_heading', 'Export History', 'Data export page', 1),
('en-GB', 'export.history_table_label', 'Export request history', 'Data export page', 1),
('en-GB', 'export.includes_consent', 'Cookie consent records (type, decision, method, dates)', 'Data export page', 1),
('en-GB', 'export.includes_links', 'All short URLs you have created (codes, destinations, click counts, dates)', 'Data export page', 1),
('en-GB', 'export.includes_profile', 'Your profile information (name, email, timezone, account dates)', 'Data export page', 1),
('en-GB', 'export.includes_sessions', 'Login sessions (device info, dates — tokens excluded for security)', 'Data export page', 1),
('en-GB', 'export.intro', 'Under data protection regulations such as GDPR (Article 20) and CCPA, you have the right to receive a copy of all personal data we hold about you in a structured, commonly used, and machine-readable format.', 'Data export page', 1),
('en-GB', 'export.no_export_message', 'You have not requested a data export yet. Click the button below to generate a copy of all your data.', 'Data export page', 1),
('en-GB', 'export.no_history', 'No export requests yet.', 'Data export page', 1),
('en-GB', 'export.pending_message', 'Your data export is currently being prepared. Please check back shortly.', 'Data export page', 1),
('en-GB', 'export.request_button', 'Request Export', 'Data export page', 1),
('en-GB', 'export.request_new', 'Request New Export', 'Data export page', 1),
('en-GB', 'export.request_new_note', 'Need a fresh copy? You can request a new export below.', 'Data export page', 1),
('en-GB', 'export.requested_at', 'Requested', 'Data export page', 1),
('en-GB', 'export.success', 'Your data export has been generated. You can download it below.', 'Data export page', 1),
('en-GB', 'export.title', 'Export Your Data', 'Data export page', 1),
('en-GB', 'export.whats_included', 'What''s Included', 'Data export page', 1);

-- ============================================================================
-- ⭐ Features Page (features.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'features.analytics_desc', 'Track clicks, geographic data, device types, referrers, and more with real-time dashboards.', 'Features page', 1),
('en-GB', 'features.analytics_title', 'Detailed Analytics', 'Features page', 1),
('en-GB', 'features.api_desc', 'Integrate URL shortening into your own applications with our RESTful API. Full documentation included.', 'Features page', 1),
('en-GB', 'features.api_title', 'REST API', 'Features page', 1),
('en-GB', 'features.cta_button', 'Shorten a URL', 'Features page', 1),
('en-GB', 'features.cta_heading', 'Ready to get started?', 'Features page', 1),
('en-GB', 'features.cta_text', 'Start shortening URLs for free — no account required.', 'Features page', 1),
('en-GB', 'features.description', 'Discover the powerful features of Go2My.Link.', 'Features page', 1),
('en-GB', 'features.domains_desc', 'Use your own branded short domain instead of g2my.link. Multiple domains per organisation supported.', 'Features page', 1),
('en-GB', 'features.domains_title', 'Custom Domains', 'Features page', 1),
('en-GB', 'features.grid_heading', 'Feature List', 'Features page', 1),
('en-GB', 'features.heading', 'Features', 'Features page', 1),
('en-GB', 'features.security_desc', 'AES-256 encryption, two-factor authentication, SSO support, and comprehensive audit trails.', 'Features page', 1),
('en-GB', 'features.security_title', 'Enterprise Security', 'Features page', 1),
('en-GB', 'features.shorten_desc', 'Create short, memorable links from any URL. Supports custom short codes for registered users.', 'Features page', 1),
('en-GB', 'features.shorten_title', 'URL Shortening', 'Features page', 1),
('en-GB', 'features.subtitle', 'Everything you need to shorten, track, and manage your links.', 'Features page', 1),
('en-GB', 'features.teams_desc', 'Invite team members with role-based access. Owners, admins, and members each have appropriate permissions.', 'Features page', 1),
('en-GB', 'features.teams_title', 'Team Management', 'Features page', 1),
('en-GB', 'features.title', 'Features', 'Features page', 1);

-- ============================================================================
-- 🦶 Footer (footer.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'footer.aup', 'Acceptable Use', 'Footer', 1),
('en-GB', 'footer.contact', 'Contact', 'Footer', 1),
('en-GB', 'footer.cookies', 'Cookie Policy', 'Footer', 1),
('en-GB', 'footer.copyright', 'Copyright', 'Footer', 1),
('en-GB', 'footer.legal', 'Legal', 'Footer', 1),
('en-GB', 'footer.privacy', 'Privacy Policy', 'Footer', 1),
('en-GB', 'footer.quick_links', 'Quick Links', 'Footer', 1),
('en-GB', 'footer.rights', 'All rights reserved.', 'Footer', 1),
('en-GB', 'footer.terms', 'Terms of Use', 'Footer', 1);

-- ============================================================================
-- 🔑 Forgot Password (forgot_password.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'forgot_password.back_to_login', 'Back to Login', 'Forgot password page', 1),
('en-GB', 'forgot_password.description', 'Reset your Go2My.Link account password.', 'Forgot password page', 1),
('en-GB', 'forgot_password.error_captcha', 'CAPTCHA verification failed. Please try again.', 'Forgot password page', 1),
('en-GB', 'forgot_password.error_csrf', 'Your session has expired. Please reload the page and try again.', 'Forgot password page', 1),
('en-GB', 'forgot_password.error_email', 'Please enter a valid email address.', 'Forgot password page', 1),
('en-GB', 'forgot_password.error_rate_limit', 'Too many reset requests. Please try again later.', 'Forgot password page', 1),
('en-GB', 'forgot_password.form_heading', 'Reset Your Password', 'Forgot password page', 1),
('en-GB', 'forgot_password.heading', 'Forgot Password', 'Forgot password page', 1),
('en-GB', 'forgot_password.label_email', 'Email Address', 'Forgot password page', 1),
('en-GB', 'forgot_password.login_link', 'Log in', 'Forgot password page', 1),
('en-GB', 'forgot_password.placeholder_email', 'you@example.com', 'Forgot password page', 1),
('en-GB', 'forgot_password.remember_password', 'Remember your password?', 'Forgot password page', 1),
('en-GB', 'forgot_password.submit_button', 'Send Reset Link', 'Forgot password page', 1),
('en-GB', 'forgot_password.subtitle', 'Enter your email and we''ll send you a reset link.', 'Forgot password page', 1),
('en-GB', 'forgot_password.success', 'If an account exists with that email, we''ve sent a password reset link. Please check your inbox (and spam folder).', 'Forgot password page', 1),
('en-GB', 'forgot_password.title', 'Forgot Password', 'Forgot password page', 1);

-- ============================================================================
-- 🏠 Homepage (home.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'home.copy_button', 'Copy', 'Homepage', 1),
('en-GB', 'home.copy_to_clipboard', 'Copy short URL to clipboard', 'Homepage', 1),
('en-GB', 'home.feature_analytics', 'Detailed Analytics', 'Homepage', 1),
('en-GB', 'home.feature_analytics_desc', 'Track clicks, geographic data, devices, and more.', 'Homepage', 1),
('en-GB', 'home.feature_fast', 'Fast Redirects', 'Homepage', 1),
('en-GB', 'home.feature_fast_desc', 'Lightning-fast URL resolution with alias chain support.', 'Homepage', 1),
('en-GB', 'home.feature_secure', 'Enterprise Security', 'Homepage', 1),
('en-GB', 'home.feature_secure_desc', 'AES-256 encryption, 2FA, SSO, and role-based access.', 'Homepage', 1),
('en-GB', 'home.features_heading', 'Features', 'Homepage', 1),
('en-GB', 'home.result_label', 'Shortened URL result', 'Homepage', 1),
('en-GB', 'home.result_success', 'Your short URL is ready!', 'Homepage', 1),
('en-GB', 'home.shorten_button', 'Shorten URL', 'Homepage', 1),
('en-GB', 'home.shorten_url', 'Shorten a URL', 'Homepage', 1),
('en-GB', 'home.url_help', 'Paste the URL you want to shorten', 'Homepage', 1),
('en-GB', 'home.url_label', 'Enter your long URL', 'Homepage', 1);

-- ============================================================================
-- 🔎 Misc Keys (greeting, key, welcome)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'greeting', 'Hello', 'General', 1),
('en-GB', 'key', 'Key', 'General', 1),
('en-GB', 'welcome', 'Welcome', 'General', 1);

-- ============================================================================
-- 🔍 Info Page (info.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'info.create_link', 'Create a Short Link', 'Link info page', 1),
('en-GB', 'info.description', 'Preview and inspect a Go2My.Link short URL before visiting.', 'Link info page', 1),
('en-GB', 'info.details_heading', 'Link Details', 'Link info page', 1),
('en-GB', 'info.error_invalid_url', 'Could not extract a short code from that URL. Please enter a valid Go2My.Link short URL.', 'Link info page', 1),
('en-GB', 'info.error_not_found', 'No link found with that short code.', 'Link info page', 1),
('en-GB', 'info.heading', 'Link Info', 'Link info page', 1),
('en-GB', 'info.how_heading', 'How It Works', 'Link info page', 1),
('en-GB', 'info.how_step1', '1. Paste a Short URL', 'Link info page', 1),
('en-GB', 'info.how_step1_desc', 'Enter any Go2My.Link short URL in the search box above.', 'Link info page', 1),
('en-GB', 'info.how_step2', '2. Preview the Destination', 'Link info page', 1),
('en-GB', 'info.how_step2_desc', 'See where the link goes before clicking — the destination domain is displayed.', 'Link info page', 1),
('en-GB', 'info.how_step3', '3. Visit Safely', 'Link info page', 1),
('en-GB', 'info.how_step3_desc', 'If the link looks safe, click to visit. If not, close the tab and stay protected.', 'Link info page', 1),
('en-GB', 'info.label_active_period', 'Active Period', 'Link info page', 1),
('en-GB', 'info.label_category', 'Category', 'Link info page', 1),
('en-GB', 'info.label_created', 'Created', 'Link info page', 1),
('en-GB', 'info.label_destination', 'Destination', 'Link info page', 1),
('en-GB', 'info.label_short_url', 'Short URL', 'Link info page', 1),
('en-GB', 'info.label_status', 'Status', 'Link info page', 1),
('en-GB', 'info.label_title', 'Title', 'Link info page', 1),
('en-GB', 'info.search_button', 'Look Up', 'Link info page', 1),
('en-GB', 'info.search_heading', 'Look Up a Link', 'Link info page', 1),
('en-GB', 'info.search_help', 'Enter a Go2My.Link short URL or just the short code.', 'Link info page', 1),
('en-GB', 'info.search_label', 'Short URL or Code', 'Link info page', 1),
('en-GB', 'info.status_active', 'Active', 'Link info page', 1),
('en-GB', 'info.status_expired', 'Expired', 'Link info page', 1),
('en-GB', 'info.status_inactive', 'Inactive', 'Link info page', 1),
('en-GB', 'info.status_scheduled', 'Scheduled', 'Link info page', 1),
('en-GB', 'info.subtitle', 'Preview a short link before visiting.', 'Link info page', 1),
('en-GB', 'info.title', 'Link Info', 'Link info page', 1),
('en-GB', 'info.visit_link', 'Visit Link', 'Link info page', 1);

-- ============================================================================
-- 📨 Invite Page (invite.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'invite.page_description', 'Join an organisation on Go2My.Link.', 'Invite page', 1),
('en-GB', 'invite.page_title', 'Accept Invitation', 'Invite page', 1);

-- ============================================================================
-- ⚖️ Legal — Acceptable Use Policy (legal.aup_*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.aup_description', 'Go2My.Link Acceptable Use Policy — rules governing use of our services.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_heading', 'Acceptable Use Policy', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s1_p1', 'This Acceptable Use Policy ("AUP") governs your use of all services provided by <strong>MWBM Partners Ltd</strong>, trading as <strong>MWservices</strong>, through the <strong>go2my.link</strong>, <strong>g2my.link</strong>, and <strong>lnks.page</strong> domains (collectively, the "Service"). This AUP applies to all users, including those using the Service without an account.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s1_p2', 'This policy supplements our <a href="/legal/terms">Terms of Use</a> and is incorporated by reference into those Terms. In the event of any conflict between this AUP and the Terms of Use, the Terms of Use shall prevail unless this AUP explicitly states otherwise.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s1_p3', 'By using the Service, you agree to comply with this AUP. Failure to comply may result in the suspension or termination of your access to the Service.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s1_title', 'Purpose', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_alert_heading', 'The following content is strictly prohibited:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li1', '<strong>Malware and malicious software</strong> &mdash; Viruses, ransomware, trojans, spyware, adware, cryptominers, or any other software designed to damage, disrupt, or gain unauthorised access to computer systems.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li2', '<strong>Phishing and credential harvesting</strong> &mdash; Pages designed to deceive users into revealing personal information, login credentials, financial details, or other sensitive data.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li3', '<strong>Child sexual abuse material (CSAM)</strong> &mdash; Any content that sexually exploits or depicts minors. We report all instances to the relevant authorities, including the National Crime Agency (NCA) and the Internet Watch Foundation (IWF).', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li4', '<strong>Terrorism and violent extremism</strong> &mdash; Content that promotes, supports, incites, or glorifies terrorism, terrorist organisations, or acts of violent extremism.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li5', '<strong>Illegal content</strong> &mdash; Content that is illegal under the laws of England and Wales, or under the laws of the jurisdiction in which the user is located.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li6', '<strong>Copyright-infringing material</strong> &mdash; Content that infringes upon the copyrights, trademarks, or other intellectual property rights of any third party. See our <a href="/legal/copyright">Copyright Policy</a> for more information.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li7', '<strong>Defamatory or harassing content</strong> &mdash; Content intended to defame, bully, harass, threaten, stalk, or intimidate any individual or group.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_li8', '<strong>Self-harm and suicide</strong> &mdash; Content that promotes, encourages, or provides instructions for self-harm or suicide.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_p1', 'All URLs shortened through the Service, LinksPage profile content, custom aliases, and any other content submitted to the Service must not link to, host, promote, or contain any of the following:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_p2', 'This list is not exhaustive. We reserve the right to determine, at our sole discretion, whether any content violates this policy.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s2_title', 'Prohibited Content', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li1', '<strong>Spam and unsolicited messages</strong> &mdash; Using the Service to send, facilitate, or distribute spam, unsolicited bulk messages, or unsolicited commercial communications.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li2', '<strong>Bulk URL creation for spam</strong> &mdash; Creating short URLs in bulk for the purpose of spam distribution, link manipulation, or artificially inflating click counts.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li3', '<strong>Bypassing rate limits</strong> &mdash; Attempting to circumvent, bypass, or exceed rate limits, usage quotas, or other technical restrictions imposed by the Service.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li4', '<strong>Scraping and data mining</strong> &mdash; Scraping, crawling, harvesting, or data-mining the Service or its content without our prior written permission.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li5', '<strong>Service disruption</strong> &mdash; Interfering with, disrupting, or attempting to gain unauthorised access to the Service infrastructure, servers, networks, or connected systems.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li6', '<strong>Impersonation</strong> &mdash; Impersonating any person, company, or organisation, or falsely stating or misrepresenting your affiliation with any person or entity.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li7', '<strong>Illegal purposes</strong> &mdash; Using the Service for any purpose that is unlawful under applicable law, including but not limited to fraud, money laundering, or the facilitation of criminal activity.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li8', '<strong>Deceptive redirect chains</strong> &mdash; Creating deceptive or misleading redirect chains that obscure the true destination or purpose of a link.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_li9', '<strong>Deceptive URL shortening</strong> &mdash; Using URL shortening to deliberately obscure the true destination of a link in a manner intended to deceive, mislead, or defraud users.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_p1', 'When using the Service, you must not engage in any of the following activities:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s3_title', 'Prohibited Activities', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_p1', 'Short URLs that are found to violate this Acceptable Use Policy may be disabled, deactivated, or removed without prior notice. We are under no obligation to notify the URL creator before or after taking such action.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_p2', 'Custom short URL aliases (vanity URLs) must not impersonate or be confusingly similar to the names, brands, trademarks, or identities of other individuals, companies, or organisations. We reserve the right to reclaim or reject any custom alias at our sole discretion.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_p3', 'URLs linking to legal adult content must be flagged appropriately using the content classification tools provided by the Service. Failure to flag adult content may result in URL deactivation and account sanctions.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_p4', 'Redirect chains consisting of more than three (3) hops may be blocked or flagged for review. This includes chains where multiple URL shortening services are used in sequence to obscure the final destination.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_sub1', 'URL Deactivation', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_sub2', 'Custom Aliases', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_sub3', 'Adult Content', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_sub4', 'Redirect Chains', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s4_title', 'URL &amp; Link Policies', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_li1', 'The short URL or LinksPage URL in question.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_li2', 'A description of the violation and which section of this policy it breaches.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_li3', 'Any supporting evidence, such as screenshots or additional URLs.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_li4', 'Your contact information so we can follow up if needed.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_p1', 'If you believe that a short URL, LinksPage profile, or any other content on the Service violates this Acceptable Use Policy, we encourage you to report it to us promptly.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_p2', 'Please send violation reports to:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_p3', 'To help us investigate your report efficiently, please include the following information:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_p4', 'We aim to acknowledge all violation reports within 24 hours and to complete our investigation within 48 hours of receipt. In cases involving illegal content or imminent harm, we will take immediate action where possible.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_sub1', 'How to Report', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_sub2', 'What to Include', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_sub3', 'Investigation Timeline', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s5_title', 'Reporting Violations', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_p1', 'Violations of this Acceptable Use Policy will be addressed in proportion to their severity. The following table outlines our general enforcement approach:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_p2', 'We may report any content we believe to be illegal to the appropriate law enforcement authorities, including but not limited to the National Crime Agency (NCA), the Internet Watch Foundation (IWF), and Action Fraud. We will cooperate fully with law enforcement investigations.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_review', 'This section requires professional legal review to ensure enforcement discretion language is appropriately drafted. In particular, legal counsel should review: (a) the extent of discretion reserved by the Company in determining violation severity, (b) whether the enforcement tiers create any binding obligations or implied commitments, and (c) compliance with the Online Safety Act 2023 reporting requirements.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row1_action', 'Written warning and request to remediate', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row1_example', 'Unflagged adult content, mildly misleading alias', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row1_severity', 'Minor (first offence)', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row2_action', 'URL deactivation and/or temporary account suspension', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row2_example', 'Spam distribution, repeated minor violations, rate limit abuse', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row2_severity', 'Moderate', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row3_action', 'Immediate URL removal and account suspension', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row3_example', 'Phishing, malware distribution, impersonation, deceptive redirects', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row3_severity', 'Severe', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row4_action', 'Permanent ban and referral to law enforcement', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row4_example', 'CSAM, terrorism content, illegal activity facilitation', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_row4_severity', 'Critical', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_th_action', 'Action', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_th_example', 'Example', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_th_severity', 'Severity', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s6_title', 'Enforcement', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_p1', 'API access is provided on a fair-use basis. You must use the API in a manner that is consistent with its intended purpose of URL shortening, link management, and analytics. Usage that places an unreasonable or disproportionate burden on our infrastructure may be restricted.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_p2', 'Rate limits are enforced on all API endpoints. The specific limits applicable to your account are determined by your subscription plan. Exceeding these limits may result in temporary blocking of your API requests. Persistent or intentional abuse of rate limits may result in revocation of your API key.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_p3', 'Automated creation of URLs via the API must comply with all provisions of this Acceptable Use Policy and our <a href="/legal/terms">Terms of Use</a>. You are responsible for ensuring that all URLs created programmatically through your API key comply with our policies, regardless of whether those URLs were created directly by you or by an automated system operating on your behalf.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_p4', 'If your API usage is deemed excessive or places an undue strain on the Service, we may contact you to discuss your usage patterns. If the issue is not resolved, we reserve the right to temporarily or permanently revoke your API access.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_sub1', 'Fair Use', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_sub2', 'Rate Limits', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_sub3', 'Automated URL Creation', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_sub4', 'Excessive Use', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s7_title', 'API Usage Limits', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s8_p1', 'We reserve the right to update or modify this Acceptable Use Policy at any time. When we make changes, we will update the "Last Updated" date at the top of this page and increment the version number.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s8_p2', 'Your continued use of the Service after any changes to this AUP constitutes your acceptance of the revised policy. If you do not agree with the updated AUP, you must stop using the Service.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s8_p3', 'For material changes that significantly affect what is considered acceptable use, we will make reasonable efforts to notify you in advance by sending an email to the address associated with your account or by displaying a prominent notice within the Service.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s8_title', 'Changes to This Policy', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s9_p1', 'If you have any questions about this Acceptable Use Policy or wish to report a violation, please contact us:', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s9_p2', 'This Acceptable Use Policy should be read in conjunction with our <a href="/legal/terms">Terms of Use</a>, <a href="/legal/privacy">Privacy Policy</a>, and <a href="/legal/cookies">Cookie Policy</a>.', 'Legal — AUP', 1),
('en-GB', 'legal.aup_s9_title', 'Contact', 'Legal — AUP', 1),
('en-GB', 'legal.aup_title', 'Acceptable Use Policy', 'Legal — AUP', 1);

-- ============================================================================
-- ⚖️ Legal — Shared Keys (legal.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.back_to_legal', 'Back to Legal', 'Legal — shared', 1),
('en-GB', 'legal.company_label', 'Company', 'Legal — shared', 1),
('en-GB', 'legal.contact_company_label', 'Company:', 'Legal — shared', 1),
('en-GB', 'legal.contact_cta', 'Contact Us', 'Legal — shared', 1),
('en-GB', 'legal.contact_email_label', 'Email:', 'Legal — shared', 1),
('en-GB', 'legal.contact_website_label', 'Website:', 'Legal — shared', 1),
('en-GB', 'legal.cookie_policy_link', 'Cookie Policy', 'Legal — shared', 1),
('en-GB', 'legal.email_label', 'Email', 'Legal — shared', 1),
('en-GB', 'legal.last_updated', 'Last Updated', 'Legal — shared', 1),
('en-GB', 'legal.last_updated_label', 'Last updated:', 'Legal — shared', 1),
('en-GB', 'legal.link_privacy', 'Privacy Policy', 'Legal — shared', 1),
('en-GB', 'legal.link_terms', 'Terms of Use', 'Legal — shared', 1),
('en-GB', 'legal.toc_heading', 'Table of Contents', 'Legal — shared', 1),
('en-GB', 'legal.trading_as', 'trading as', 'Legal — shared', 1),
('en-GB', 'legal.trading_as_label', 'Trading As', 'Legal — shared', 1),
('en-GB', 'legal.version', 'Version', 'Legal — shared', 1),
('en-GB', 'legal.version_label', 'Version', 'Legal — shared', 1),
('en-GB', 'legal.website_label', 'Website', 'Legal — shared', 1);

-- ============================================================================
-- 🍪 Legal — Cookie Policy (legal.cookies_*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.cookies_browser_block', 'Blocking all cookies or only third-party cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_browser_clear_ls', 'Clearing localStorage data via your browser''s developer tools', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_browser_delete', 'Deleting some or all cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_browser_view', 'Viewing all cookies stored on your device', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_col_duration', 'Duration', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_col_name', 'Name', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_col_purpose', 'Purpose', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_col_type', 'Type', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_consent_duration', '1 year', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_consent_purpose', 'Records your cookie consent preferences so we respect your choices on subsequent visits.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_csrf_duration', 'Session (deleted when you close your browser)', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_csrf_purpose', 'Provides cross-site request forgery (CSRF) protection by validating that form submissions originate from our service.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_description', 'Go2My.Link Cookie Policy — how we use cookies and similar technologies.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_first_party', 'First-party cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_first_party_desc', 'Set by the website you are visiting (in this case, go2my.link, g2my.link, or lnks.page). All cookies we currently use are first-party cookies.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_heading', 'Cookie Policy', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_link', 'Cookie Policy', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_locale_duration', '1 year', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_locale_purpose', 'Stores your preferred language so content is displayed in your chosen language on subsequent visits.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_ls_theme_duration', 'Persistent (until cleared)', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_ls_theme_purpose', 'A client-side mirror of your theme preference, used by JavaScript to apply the theme instantly without waiting for a server response, preventing a flash of unstyled content (FOUC).', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_manage_button', 'Manage Cookie Preferences', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_first_vs_third_heading', 'First-Party vs Third-Party Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_heading', '1. What Are Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_p1', 'Cookies are small text files that are placed on your device (computer, tablet, or mobile phone) when you visit a website. They are widely used to make websites work more efficiently, provide a better user experience, and supply information to website operators.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_p2', 'In addition to cookies, we also use <strong>localStorage</strong>, a similar browser-based storage mechanism that allows websites to store data locally on your device. Unlike cookies, localStorage data is not sent to the server with every request, but it serves a similar purpose for preserving your preferences.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_p3', 'Throughout this policy, when we refer to "cookies", we include both traditional HTTP cookies and localStorage unless otherwise stated.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s1_title', 'What Are Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s2_heading', '2. How We Use Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s2_intro', 'We use cookies and similar technologies on our service for the following purposes:', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s2_title', 'How We Use Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3_heading', '3. Cookie Categories &amp; Inventory', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3_intro', 'Below is a complete inventory of the cookies and similar technologies used by our service, organised by category.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3_title', 'Cookie Categories &amp; Inventory', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3a_desc', 'These cookies are strictly necessary for the operation of our service. They cannot be disabled as the service would not function correctly without them. They do not store any personally identifiable information.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3a_heading', 'Essential Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3b_desc', 'These cookies enable enhanced functionality and personalisation. They may be set by us or by third-party providers whose services we have added to our pages. If you disable these cookies, some or all of these features may not function properly.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3b_heading', 'Functional Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3c_heading', 'Analytics Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3c_none', 'We do not currently use any analytics cookies. If we introduce analytics cookies in the future, this section will be updated and your consent will be requested before any such cookies are set.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3d_heading', 'Marketing Cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s3d_none', 'We do not use marketing or advertising cookies. We do not track you across websites, build advertising profiles, or sell your data to third parties.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_banner_desc', 'When you first visit our service, you will be presented with a cookie consent banner that allows you to accept or decline non-essential cookies. Essential cookies cannot be disabled as they are required for the service to function.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_banner_heading', 'Cookie Consent Banner', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_browser_desc', 'Most web browsers allow you to control cookies through their settings. You can typically find these options in the "Privacy" or "Security" section of your browser preferences. Common actions include:', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_browser_heading', 'Browser Settings', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_browser_warning', 'Please note that blocking or deleting essential cookies may prevent you from using certain features of our service, such as staying logged in or maintaining your session.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_change_desc', 'You can change your cookie preferences at any time by clicking the button below, or by visiting this page and using the cookie consent controls.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_change_heading', 'Changing Your Preferences', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_heading', '4. Managing Your Cookie Preferences', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_review_note', 'Cookie consent mechanism details (banner behaviour, granularity of controls, re-consent intervals) should be reviewed for compliance with UK GDPR, the Privacy and Electronic Communications Regulations 2003 (PECR), and ePrivacy requirements.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s4_title', 'Managing Your Cookie Preferences', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_heading', '5. Do Not Track &amp; Global Privacy Control', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_p1', 'We respect the <strong>Do Not Track (DNT)</strong> signal sent by your browser. When we detect that DNT is enabled, we will not set any non-essential cookies or engage in any cross-site tracking.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_p2', 'We also honour the <strong>Global Privacy Control (GPC)</strong> signal, a newer standard that communicates your privacy preferences to websites. When we detect a GPC signal, we treat it as a request to opt out of non-essential cookies and any data sharing.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_p3', 'For more information about how we handle these signals and your broader privacy rights, please see our', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_privacy_link', 'Privacy Policy (Do Not Track section)', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_review_note', 'DNT/GPC implementation details and the legal effect of these signals under UK GDPR and applicable regulations should be confirmed by legal counsel.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s5_title', 'Do Not Track &amp; Global Privacy Control', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_heading', '6. Changes to This Policy', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_p1', 'We may update this Cookie Policy from time to time to reflect changes in our practices, the cookies we use, or for other operational, legal, or regulatory reasons. When we make changes, we will update the "Last updated" date at the top of this page and increment the version number.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_p2', 'If we make material changes to this policy, such as introducing new cookie categories or changing how we use existing cookies, we will notify you by displaying a prominent notice on our service or, where appropriate, requesting your consent again.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_p3', 'We encourage you to review this page periodically to stay informed about our use of cookies.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_review_note', 'Notification and re-consent requirements for material cookie policy changes should be reviewed for UK GDPR and PECR compliance.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s6_title', 'Changes to This Policy', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s7_heading', '7. Contact', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s7_p1', 'If you have any questions about this Cookie Policy or our use of cookies, please contact us:', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s7_review_note', 'Contact information, registered address, and data protection officer details (if applicable) should be confirmed. Consider whether an ICO registration number should be included.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_s7_title', 'Contact', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_session_duration', 'Session (deleted when you close your browser)', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_session_purpose', 'Maintains your server-side session, keeping you logged in and preserving your state across page requests.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_subtitle', 'How we use cookies and similar technologies.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_theme_duration', '1 year', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_theme_purpose', 'Stores your display theme preference (light, dark, or auto/system) so the correct theme is applied on each visit, including on the initial page load before JavaScript runs.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_third_party', 'Third-party cookies', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_third_party_desc', 'Set by a domain other than the one you are visiting. We do not currently use any third-party cookies.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_title', 'Cookie Policy', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_type_http', 'HTTP Cookie', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_type_localstorage', 'localStorage', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_analytics', 'Analytics (Future)', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_analytics_desc', 'In the future, we may use analytics cookies to understand how visitors interact with our service, helping us improve the user experience. These will only be set with your consent.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_consent', 'Cookie Consent', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_consent_desc', 'To remember whether you have accepted or declined non-essential cookies, so we do not ask you repeatedly.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_preferences', 'Preferences', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_preferences_desc', 'To remember your settings and preferences, such as your chosen theme (light or dark mode) and language preference, so you do not need to set them each time you visit.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_security', 'Security', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_security_desc', 'To protect you against cross-site request forgery (CSRF) attacks and other security threats by generating and validating security tokens.', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_session', 'Session Management', 'Legal — Cookie Policy', 1),
('en-GB', 'legal.cookies_use_session_desc', 'To maintain your session while you use the service, keeping you logged in and preserving your state as you navigate between pages.', 'Legal — Cookie Policy', 1);

-- ============================================================================
-- ©️ Legal — Copyright Notice (legal.copyright_*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.copyright_description', 'Go2My.Link Copyright Notice and DMCA takedown procedures.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_heading', 'Copyright Notice', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s1_p1', 'All content, source code, software, graphics, logos, branding materials, page designs, and documentation comprising the GoToMyLink service are the copyright of <strong>MWBM Partners Ltd</strong> (trading as <strong>MWservices</strong>) unless otherwise stated.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s1_p3', 'These marks may not be used in connection with any product or service that is not provided by MWBM Partners Ltd, in any manner that is likely to cause confusion, or in any manner that disparages or discredits MWBM Partners Ltd.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s1_rights', 'All rights reserved.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s1_title', 'Copyright Ownership', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s1_trademarks', 'The following names and associated logos are trademarks or service marks of MWBM Partners Ltd:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_item1', 'Destination URLs submitted for shortening', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_item2', 'LinksPage profile content, descriptions, and custom imagery', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_item3', 'Custom metadata such as link titles, descriptions, and tags', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_p1', 'Users retain full copyright ownership of their own content, including but not limited to:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_p2', 'By using the GoToMyLink service, you grant MWBM Partners Ltd a non-exclusive, worldwide, royalty-free licence to display, reproduce, cache, and distribute your content solely as necessary to operate, maintain, and provide the service. This licence continues for as long as your content remains on the service and terminates when your content is deleted.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_p3', 'You represent and warrant that you own or have the necessary rights, licences, and permissions to submit content to the service. You must not upload, share, or link to any content that infringes the copyright, trademark, or other intellectual property rights of any third party.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s2_title', 'User-Generated Content', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_agent_attn', 'Attn: Copyright Agent', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_agent_email_label', 'Email:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_agent_heading', 'Designated Agent', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_agent_p1', 'Takedown notices should be sent to our designated copyright agent at:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_heading', 'Counter-Notification', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_p1', 'If you believe that your content was removed or disabled as a result of a mistake or misidentification, you may submit a counter-notification to our designated agent. Your counter-notification must include:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_p2', 'Upon receipt of a valid counter-notification, we will forward it to the original complainant and restore the removed content within 10 to 14 business days, unless we receive notice that the complainant has filed a court action seeking to restrain you from engaging in the allegedly infringing activity.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_req1', 'Your full name, postal address, telephone number, and email address.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_req2', 'Identification of the material that was removed or disabled, and the URL where it previously appeared.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_req3', 'A statement under penalty of perjury that you have a good faith belief the material was removed or disabled as a result of mistake or misidentification.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_req4', 'A statement that you consent to the jurisdiction of the courts in your locality and that you will accept service of process from the person who filed the original takedown notice.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_counter_req5', 'Your physical or electronic signature.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_notice_heading', 'Filing a Takedown Notice', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_notice_p1', 'To file a copyright takedown notice, please send a written communication to our designated agent that includes the following information:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_p1', 'MWBM Partners Ltd respects the intellectual property rights of others and expects users of the GoToMyLink service to do the same. If you believe that content available through our service infringes your copyright, you may submit a takedown notice to our designated agent.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req1', 'A description of the copyrighted work that you claim has been infringed, or, if multiple works are covered by a single notification, a representative list of such works.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req2', 'The specific URL(s) or short link(s) on the GoToMyLink service that you claim are infringing or are the subject of infringing activity, with enough detail for us to locate the material.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req3', 'Your full name, postal address, telephone number, and email address so that we may contact you.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req4', 'A statement that you have a good faith belief that the use of the material in the manner complained of is not authorised by the copyright owner, its agent, or the law.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req5', 'A statement that the information in the notification is accurate, and under penalty of perjury, that you are authorised to act on behalf of the owner of the copyright that is allegedly infringed.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_req6', 'A physical or electronic signature of the copyright owner or a person authorised to act on their behalf.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_review_note', 'Formal DMCA agent designation and registration details require professional legal review and must be filed with the U.S. Copyright Office if applicable.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s3_title', 'DMCA &amp; Copyright Takedown', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s4_p1', 'MWBM Partners Ltd maintains a policy for the termination of accounts belonging to users who are repeat copyright infringers. If a user is found to have repeatedly uploaded, linked to, or facilitated access to infringing content, their account and all associated short links may be permanently terminated at our sole discretion.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s4_p2', 'We reserve the right to disable any short link, LinksPage profile, or user account at any time if we reasonably believe that the content infringes the intellectual property rights of others, regardless of whether a formal takedown notice has been received.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s4_review_note', 'Repeat infringer policy thresholds, appeals processes, and specific procedural details require professional legal review to ensure compliance with applicable safe harbour provisions.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s4_title', 'Repeat Infringer Policy', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_bootstrap', 'Licensed under the MIT Licence. Copyright &copy; Bootstrap Authors.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_fontawesome', 'Icons licensed under CC BY 4.0, fonts under SIL OFL 1.1, and code under the MIT Licence. Copyright &copy; Fonticons, Inc.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_p1', 'Links shortened through the GoToMyLink service redirect users to third-party websites and content. MWBM Partners Ltd does not own, control, or endorse any third-party content accessible through short links created on the service.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_p2', 'The presence of a short link on Go2My.Link, G2My.Link, or Lnks.page does not imply any affiliation with, endorsement of, or responsibility for the linked content or the practices of the third-party website operators.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_p3', 'The GoToMyLink service makes use of the following third-party software and assets, each used under their respective licences:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_p4', 'All third-party trademarks, logos, and brand names referenced on this site are the property of their respective owners and are used for identification purposes only.', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s5_title', 'Third-Party Content', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s6_email_label', 'Email:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s6_p1', 'If you have any questions about this Copyright Notice, or if you wish to report a copyright concern, please contact us:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s6_title', 'Contact', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_s6_web_label', 'Website:', 'Legal — Copyright', 1),
('en-GB', 'legal.copyright_title', 'Copyright Notice', 'Legal — Copyright', 1);

-- ============================================================================
-- 🔒 Legal — Privacy Policy (legal.privacy_*) — Part 1: Sections 1–7
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.privacy_description', 'Go2My.Link Privacy Policy — how we collect, use, and protect your personal data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_domain_a', 'Main website, account management, and URL creation', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_domain_b', 'Short link redirection service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_domain_c', 'LinksPage public profile pages', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_heading', 'Privacy Policy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_no_sale', 'We do not sell your personal data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_no_sale_detail', 'We have never sold personal data and have no plans to do so. We do not share your data with third parties for their own marketing purposes.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_title', 'Privacy Policy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_trading_as', 'Trading as', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s1_consent', 'By accessing or using the Service, you acknowledge that you have read and understood this Privacy Policy. If you do not agree with our data practices, please do not use the Service.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s1_controller', 'For the purposes of applicable data protection legislation (including the UK General Data Protection Regulation, the EU General Data Protection Regulation, and the Data Protection Act 2018), the data controller is:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s1_intro', 'Welcome to ', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s1_scope', 'This Privacy Policy explains how we collect, use, store, share, and protect your personal data when you use our URL shortening service across our three domains:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s1_title', 'Introduction', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_desc', 'When you create an account, we collect:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_email', 'Email address', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_name', 'Full name (first name and surname)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_org', 'Organisation name (if you create or join an organisation)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_password', 'Password (stored as a one-way cryptographic hash &mdash; we never store your plaintext password)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_prefs', 'Account preferences and settings', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_account_title', 'Account Data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_cookies_desc', 'We use cookies and browser local storage to provide essential functionality. This includes session management, theme preferences (dark/light mode), and CSRF protection tokens. For full details, see our', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_cookies_title', 'Cookies &amp; Local Storage', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_dnt_desc', 'We respect Do Not Track (DNT) and Global Privacy Control (GPC) signals sent by your browser. When we detect these signals, we limit data collection to what is strictly necessary for the Service to function. See Section 12 for more details.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_dnt_title', 'Do Not Track &amp; Global Privacy Control Signals', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_intro', 'We collect different types of data depending on how you interact with the Service. Below is a summary of the categories of personal data we may collect.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_clicks', 'Click counts and click metadata (timestamp, IP, user agent, referrer)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_code', 'Generated short codes and any custom aliases', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_desc', 'When you create or interact with short URLs, we collect:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_dest', 'Destination (long) URLs you submit for shortening', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_meta', 'URL metadata (title, description, creation date, expiry settings)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_shorturl_title', 'Short URL Data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_title', 'Data We Collect', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_desc', 'When you interact with the Service, we automatically collect:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_device', 'Device type, operating system, and screen resolution', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_ip', 'IP address (may be truncated or anonymised for analytics)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_pages', 'Pages and features you access within the Service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_referrer', 'Referring URL (the page that linked you to us)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_time', 'Date and time of access (timestamps)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_title', 'Usage Data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s2_usage_ua', 'Browser type and user agent string', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_analytics_aggregate', 'Generating aggregate, anonymised usage statistics', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_analytics_clicks', 'Tracking click statistics for your shortened URLs (visible in your dashboard)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_analytics_improve', 'Understanding how users interact with the Service to improve features and performance', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_analytics_title', 'Analytics &amp; Improvement', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_comms_service', 'Sending service-related notifications (e.g., account verification, password resets)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_comms_support', 'Responding to your enquiries and support requests', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_comms_title', 'Communications', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_comms_updates', 'Notifying you of important changes to the Service or this policy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_intro', 'We use the personal data we collect for the following purposes:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_legal_comply', 'Complying with applicable laws, regulations, and legal processes', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_legal_enforce', 'Enforcing our Terms of Use and other agreements', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_legal_protect', 'Protecting the rights, property, and safety of our users and the public', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_legal_title', 'Legal Obligations', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_security_audit', 'Maintaining audit logs for security monitoring and incident response', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_security_rate', 'Rate limiting to prevent abuse of the URL creation service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_security_spam', 'Detecting and blocking spam, malicious URLs, and fraudulent activity', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_security_title', 'Security &amp; Abuse Prevention', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_service_accounts', 'Managing user accounts, authentication, and session management', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_service_linkspage', 'Rendering LinksPage profiles on lnks.page', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_service_redirect', 'Processing redirect requests through g2my.link', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_service_shorten', 'Creating, managing, and resolving shortened URLs', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_service_title', 'Providing the Service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s3_title', 'How We Use Your Data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_col_basis', 'Legal Basis', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_col_examples', 'Examples', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_col_purpose', 'Purpose', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_consent', 'Consent', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_consent_examples', 'Optional marketing communications, non-essential cookies, analytics beyond basic service operation', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_consent_purpose', 'Processing based on your explicit, freely given consent', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_contract', 'Performance of Contract', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_contract_examples', 'Creating your account, shortening URLs, providing redirect services, managing your dashboard', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_contract_purpose', 'Processing necessary to fulfil our agreement with you', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_intro', 'Under the UK GDPR and EU GDPR, we rely on the following legal bases when processing your personal data:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_legitimate', 'Legitimate Interest', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_legitimate_examples', 'Security monitoring, abuse prevention, service improvement, aggregate analytics', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_legitimate_purpose', 'Processing necessary for our legitimate business interests, balanced against your rights', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_obligation', 'Legal Obligation', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_obligation_examples', 'Responding to lawful data subject requests, cooperating with law enforcement when legally required, tax and accounting records', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_obligation_purpose', 'Processing required to comply with legal requirements', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s4_title', 'Legal Basis for Processing (GDPR Art. 6)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_business_desc', 'In the event of a merger, acquisition, reorganisation, or sale of assets, your personal data may be transferred as part of the transaction. We will notify you before your data becomes subject to a different privacy policy.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_business_title', 'Business Transfers', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_col_location', 'Location', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_col_provider', 'Provider', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_col_purpose', 'Purpose', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_dreamhost', 'DreamHost', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_dreamhost_location', 'United States', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_dreamhost_purpose', 'Web hosting, database hosting, and email services', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_intro', 'We may share your data with the following categories of recipients, strictly as necessary:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_comply', 'Comply with a legal obligation, court order, or lawful government request', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_desc', 'We may disclose your personal data if required to do so by law, or if we believe in good faith that such disclosure is necessary to:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_protect', 'Protect and defend the rights or property of ', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_public', 'Protect the personal safety of users or the public', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_safety', 'Prevent or investigate potential wrongdoing in connection with the Service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_law_title', 'Law Enforcement &amp; Legal Requirements', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_providers_desc', 'We use trusted third-party providers to help operate the Service. These providers only process data on our behalf and under our instructions:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_providers_title', 'Service Providers', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_review', 'This section should be reviewed by a qualified legal professional to ensure all data sharing arrangements are accurately disclosed, and that appropriate Data Processing Agreements (DPAs) are in place with all third-party providers.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s5_title', 'Data Sharing', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_adequacy', 'Transfers to countries that have been deemed to provide an adequate level of data protection by the UK Secretary of State or the European Commission', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_intro', 'MWBM Partners Ltd is based in the United Kingdom. However, our hosting infrastructure is located in the United States (DreamHost). This means your personal data may be transferred to, stored, and processed in a country outside the United Kingdom and the European Economic Area (EEA).', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_measures', 'Supplementary technical and organisational measures to ensure your data remains protected', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_review', 'A legal professional should review the specific transfer mechanisms in use (e.g., UK International Data Transfer Agreement, EU SCCs, or adequacy decisions) and confirm that a Transfer Impact Assessment (TIA) has been conducted for US transfers, particularly in light of the UK-US Data Bridge and EU-US Data Privacy Framework.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_safeguards', 'When we transfer personal data internationally, we ensure that appropriate safeguards are in place to protect your data, including:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_sccs', 'Standard Contractual Clauses (SCCs) approved by the UK Information Commissioner''s Office (ICO) or the European Commission, as applicable', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s6_title', 'International Data Transfers', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_account', 'Account data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_account_notes', 'After you close your account, we retain your data for a 30-day grace period to allow for account recovery, after which it is permanently deleted.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_account_period', 'Duration of account + 30 days', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_activity', 'Activity/audit logs', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_activity_notes', 'System audit logs are automatically purged after 90 days. Security-critical logs may be retained longer if required for ongoing investigations.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_activity_period', '90 days', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_clicks', 'Click/analytics data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_clicks_notes', 'Detailed click logs (including IP and user agent) are retained for 90 days, after which they are aggregated into anonymised statistics. Aggregate data is retained indefinitely.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_clicks_period', 'Configurable (default: 90 days detailed, aggregated thereafter)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_col_data', 'Data Type', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_col_notes', 'Notes', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_col_period', 'Retention Period', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_deletion', 'You may request deletion of your data at any time (see Section 8). We will honour deletion requests within 30 days, subject to any legal obligations that require us to retain certain data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_intro', 'We retain your personal data only for as long as necessary to fulfil the purposes for which it was collected, or as required by law. The specific retention periods are:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_session', 'Session data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_session_notes', 'Session data is deleted when you log out or when the session expires (whichever comes first).', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_session_period', 'Duration of session', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_title', 'Data Retention', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_urls', 'Short URLs &amp; metadata', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_urls_notes', 'You may set expiration dates on individual short URLs. Expired URLs are soft-deleted and permanently removed after 30 days. Anonymous (non-account) URLs follow the system-configured retention policy.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s7_urls_period', 'Configurable (default: indefinite while account active)', 'Legal — Privacy Policy', 1);

-- ============================================================================
-- 🔒 Legal — Privacy Policy (legal.privacy_*) — Part 2: Sections 8–14
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.privacy_s8_ccpa_delete', 'Right to Delete', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_delete_desc', 'You have the right to request deletion of your personal information, subject to certain exceptions.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_know', 'Right to Know', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_know_desc', 'You have the right to know what personal information we collect, use, disclose, and sell (if applicable) about you.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_nondiscrim', 'Right to Non-Discrimination', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_nondiscrim_desc', 'We will not discriminate against you for exercising any of your CCPA rights. You will not receive different pricing or quality of service for making a rights request.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_note', 'If you are a California resident, you have the following rights under the CCPA, as amended by the CPRA:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_optout', 'Right to Opt-Out of Sale', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_optout_desc', 'We do not sell personal information. If this changes, you will have the right to opt-out of any such sale.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_ccpa_title', 'Rights Under the California Consumer Privacy Act (CCPA/CPRA)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_dashboard', 'Privacy Dashboard', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_dashboard_desc', 'Log in to your account and visit your privacy settings to download, modify, or delete your data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_desc', 'You can exercise your data rights in any of the following ways:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_email', 'Email', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_email_desc', 'Send a request to', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_email_detail', 'with the subject line "Data Rights Request". Please include sufficient information for us to verify your identity.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_timeframe', 'We will respond to all verified rights requests within 30 days. If the request is complex or we receive a large number of requests, we may extend this period by an additional 60 days, and we will inform you of the extension.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_exercise_title', 'How to Exercise Your Rights', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_access', 'Right of Access (Art. 15)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_access_desc', 'You have the right to request a copy of the personal data we hold about you.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_erase', 'Right to Erasure (Art. 17)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_erase_desc', 'You have the right to request deletion of your personal data in certain circumstances (the "right to be forgotten").', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_note', 'If you are located in the United Kingdom or the European Economic Area, you have the following rights under the General Data Protection Regulation:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_object', 'Right to Object (Art. 21)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_object_desc', 'You have the right to object to processing of your personal data based on legitimate interests or for direct marketing purposes.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_port', 'Right to Data Portability (Art. 20)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_port_desc', 'You have the right to receive your personal data in a structured, commonly used, machine-readable format and to transmit it to another controller.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_rectify', 'Right to Rectification (Art. 16)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_rectify_desc', 'You have the right to request correction of inaccurate or incomplete personal data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_restrict', 'Right to Restrict Processing (Art. 18)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_restrict_desc', 'You have the right to request that we limit the processing of your personal data in certain situations.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_title', 'Rights Under UK GDPR &amp; EU GDPR', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_withdraw', 'Right to Withdraw Consent', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_gdpr_withdraw_desc', 'Where processing is based on your consent, you may withdraw that consent at any time without affecting the lawfulness of prior processing.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_intro', 'Depending on your location, you may have specific rights regarding your personal data under applicable data protection laws. Below is a summary of rights under the key frameworks we comply with.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_access', 'Access to your personal data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_anon', 'Anonymisation, blocking, or deletion of unnecessary or excessive data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_confirm', 'Confirmation of the existence of data processing', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_correct', 'Correction of incomplete, inaccurate, or outdated data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_delete', 'Deletion of personal data processed with your consent', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_info', 'Information about public and private entities with which we have shared your data', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_note', 'If you are located in Brazil, you have rights under the Lei Geral de Prote&ccedil;&atilde;o de Dados (LGPD), including:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_port', 'Data portability to another service or product provider', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_title', 'Rights Under Brazil''s LGPD', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_lgpd_withdraw', 'Revocation of consent', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s8_title', 'Your Rights', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_coppa', 'COPPA (United States)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_coppa_desc', 'We do not knowingly collect personal information from children under the age of 13. If you are under 13, please do not use the Service or provide any personal information.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_discovery', 'If we discover that we have inadvertently collected personal data from a child below the applicable age threshold, we will take immediate steps to delete such data. If you believe that a child has provided us with personal data, please contact us at', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_gdpr_age', 'GDPR (UK/EU)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_gdpr_age_desc', 'We do not knowingly process personal data of individuals under the age of 16 without parental consent. In jurisdictions where the age of digital consent is lower (but not below 13), we comply with the local threshold.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_intro', 'The Service is not directed at children. We take the protection of children''s privacy seriously and comply with applicable child protection laws.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s9_title', 'Children''s Privacy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_csrf', 'CSRF Protection', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_csrf_desc', 'All forms and state-changing API requests are protected with Cross-Site Request Forgery (CSRF) tokens.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_disclaimer', 'While we strive to protect your personal data, no method of transmission over the Internet or method of electronic storage is 100% secure. We cannot guarantee absolute security, but we are committed to maintaining a high standard of protection.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_https', 'HTTPS Encryption', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_https_desc', 'All communication between your browser and our servers is encrypted using TLS/SSL. All three domains (go2my.link, g2my.link, lnks.page) enforce HTTPS.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_input', 'Input Validation', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_input_desc', 'All user input is validated and sanitised to prevent injection attacks (SQL injection, XSS, etc.).', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_intro', 'We take the security of your personal data seriously and implement appropriate technical and organisational measures to protect it against unauthorised access, alteration, disclosure, or destruction.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_org_access', 'Access to personal data is restricted to authorised personnel on a need-to-know basis', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_org_incident', 'Incident response procedures for detecting and responding to data breaches', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_org_review', 'Regular review of security practices and access controls', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_org_title', 'Organisational Measures', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_passwords', 'Password Hashing', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_passwords_desc', 'User passwords are stored using industry-standard one-way cryptographic hashing (bcrypt). We never store or have access to your plaintext password.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_review', 'A security professional and legal counsel should review this section to ensure all technical and organisational measures are accurately described, and that the disclaimer language is appropriate for the jurisdictions in which the Service operates.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_sessions', 'Secure Sessions', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_sessions_desc', 'Session tokens are generated using cryptographically secure random number generators. Session cookies are marked as Secure, HttpOnly, and SameSite.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_technical_title', 'Technical Measures', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s10_title', 'Security', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_col_cookie', 'Cookie / Storage', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_col_purpose', 'Purpose', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_col_type', 'Type', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_csrf_desc', 'CSRF protection token for form submissions', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_essential', 'Essential', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_full_policy', 'For a comprehensive list of all cookies, their lifetimes, and your options for managing them, please see our full', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_functional', 'Functional', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_intro', 'We use cookies and similar technologies to provide essential functionality and improve your experience. Here is a brief summary:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_localstorage_desc', 'Client-side theme preference for instant rendering (prevents flash of unstyled content)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_preferences', 'You can manage your cookie preferences at any time through your browser settings or by visiting our', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_preferences_link', 'cookie preferences centre', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_session_desc', 'Session management &mdash; maintains your authenticated state', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_theme_desc', 'Stores your dark/light mode preference for server-side rendering', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s11_title', 'Cookie Policy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_dnt_desc', 'Do Not Track is a browser setting that sends a signal to websites requesting that they do not track your browsing activity. When we detect a DNT signal (the <code>DNT: 1</code> HTTP header), we:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_dnt_essential', 'Continue to use only essential cookies required for the Service to function', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_dnt_no_analytics', 'Disable non-essential analytics collection', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_dnt_no_tracking', 'Do not record detailed click metadata beyond what is necessary for the redirect service', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_dnt_title', 'Do Not Track (DNT)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_enable_desc', 'Most modern browsers support DNT and/or GPC settings:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_enable_dnt', 'Usually found in your browser''s privacy or security settings under "Send a Do Not Track request" or similar', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_enable_gpc', 'Supported natively in some browsers (e.g., Firefox, Brave) or via browser extensions. Visit <a href="https://globalprivacycontrol.org/" rel="noopener noreferrer" target="_blank">globalprivacycontrol.org</a> for more information', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_enable_title', 'How to Enable These Signals', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_gpc_desc', 'Global Privacy Control is a newer browser signal (the <code>Sec-GPC: 1</code> HTTP header) that communicates your privacy preferences under laws like the CCPA. When we detect a GPC signal, we treat it as:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_gpc_object', 'An objection to processing for targeted advertising purposes', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_gpc_optout', 'A valid opt-out of the sale or sharing of personal information (CCPA)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_gpc_title', 'Global Privacy Control (GPC)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_intro', 'We respect your privacy preferences as expressed through browser signals.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s12_title', 'Do Not Track', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_archive', 'Previous versions of this Privacy Policy are available upon request by contacting us at', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_continued', 'Your continued use of the Service after the effective date of any updated Privacy Policy constitutes your acceptance of the changes. If you do not agree with the updated policy, you should stop using the Service and may request deletion of your account and personal data.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_intro', 'We may update this Privacy Policy from time to time to reflect changes in our practices, technology, legal requirements, or other factors.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_material_desc', 'For significant changes that affect how we collect, use, or share your personal data, we will provide prominent notice through one or more of the following methods: a banner on the Service, an email to registered account holders, or a notification in your account dashboard.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_material_title', 'Material Changes', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_minor_desc', 'We will update the "Last Updated" date at the top of this policy and increment the version number.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_minor_title', 'Minor Changes', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_notification', 'When we make changes:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s13_title', 'Changes to This Policy', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_company_heading', 'Data Controller', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_complaints_eu', 'If you are located in the EU, you may also contact your local Data Protection Authority. A list of EU DPAs is available at', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_complaints_intro', 'If you are not satisfied with our response to your enquiry or believe we are processing your personal data unlawfully, you have the right to lodge a complaint with a supervisory authority.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_complaints_title', 'Complaints', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_dpo_desc', 'For data protection enquiries specifically, including exercising your rights under GDPR, CCPA, or LGPD, please contact our Data Protection Officer:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_dpo_subject', '(subject line: "Data Protection Enquiry")', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_dpo_title', 'Data Protection Officer', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_encourage', 'We encourage you to contact us first so we can try to resolve your concern directly.', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_ico_heading', 'UK Information Commissioner''s Office (ICO)', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_ico_phone', '0303 123 1113', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_intro', 'If you have questions, concerns, or requests regarding this Privacy Policy or our data practices, you can reach us through the following channels:', 'Legal — Privacy Policy', 1),
('en-GB', 'legal.privacy_s14_title', 'Contact &amp; Data Protection Officer', 'Legal — Privacy Policy', 1);

-- ============================================================================
-- ⚖️ Legal — Terms of Use (legal.terms_*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'legal.terms_description', 'Go2My.Link Terms of Use — governing use of our URL shortening and link management services.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_heading', 'Terms of Use', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_link', 'Terms of Use', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s10_p1', 'To the maximum extent permitted by applicable law, <strong>MWBM Partners Ltd</strong>, its directors, employees, partners, agents, and affiliates shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, use, goodwill, or other intangible losses.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s10_p2', 'Our total aggregate liability to you for all claims arising out of or relating to the Service shall not exceed the greater of: (a) the total amount you have paid to us in the twelve (12) months preceding the claim, or (b) one hundred pounds sterling (&pound;100).', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s10_p3', 'Nothing in these Terms shall exclude or limit our liability for death or personal injury caused by our negligence, fraud or fraudulent misrepresentation, or any other liability that cannot be excluded or limited by the laws of England and Wales.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s10_review', 'This section requires professional legal review to ensure the limitation of liability provisions are enforceable under the laws of England and Wales, comply with the Consumer Rights Act 2015 and the Unfair Contract Terms Act 1977, and appropriately address both consumer and business users.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s10_title', 'Limitation of Liability', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_li1', 'Your use of the Service.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_li2', 'Your violation of these Terms or any applicable law or regulation.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_li3', 'Your violation of any rights of a third party, including intellectual property rights.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_li4', 'Any content you submit, post, or transmit through the Service.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_p1', 'You agree to defend, indemnify, and hold harmless <strong>MWBM Partners Ltd</strong>, its directors, employees, partners, agents, and affiliates from and against any and all claims, damages, obligations, losses, liabilities, costs, and expenses (including but not limited to legal fees) arising from:', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_review', 'This section requires professional legal review to ensure the indemnification clause is reasonable and enforceable under the laws of England and Wales, particularly with respect to consumer users where such clauses may be considered unfair terms.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s11_title', 'Indemnification', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s12_p1', 'These Terms shall be governed by and construed in accordance with the laws of England and Wales, without regard to its conflict of law provisions.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s12_p2', 'Any disputes arising out of or in connection with these Terms, including any question regarding their existence, validity, or termination, shall be subject to the exclusive jurisdiction of the courts of England and Wales.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s12_p3', 'If you are a consumer, you may also be entitled to bring proceedings in the courts of the country in which you reside, and nothing in these Terms affects your statutory rights as a consumer.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s12_title', 'Governing Law', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s13_p1', 'We reserve the right to update or modify these Terms at any time. When we make changes, we will update the "Last Updated" date at the top of this page and increment the version number.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s13_p2', 'Your continued use of the Service after any changes to these Terms constitutes your acceptance of the revised Terms. If you do not agree with the updated Terms, you must stop using the Service.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s13_p3', 'For material changes that significantly affect your rights or obligations, we will make reasonable efforts to notify you in advance by sending an email to the address associated with your account or by displaying a prominent notice within the Service.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s13_title', 'Changes to Terms', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s14_p1', 'If you have any questions, concerns, or requests regarding these Terms of Use, please contact us:', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s14_title', 'Contact', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s1_p1', 'By accessing or using the services provided at <strong>go2my.link</strong>, <strong>g2my.link</strong>, and <strong>lnks.page</strong> (collectively, the "Service"), you agree to be bound by these Terms of Use ("Terms"). If you do not agree to all of these Terms, you must not use the Service.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s1_p2', 'These Terms constitute a legally binding agreement between you ("User", "you", or "your") and <strong>MWBM Partners Ltd</strong>, trading as <strong>MWservices</strong> ("Company", "we", "us", or "our").', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s1_p3', 'You must be at least 13 years of age to use the Service. By using the Service, you represent and warrant that you are at least 13 years old. If you are under 18, you represent that your parent or legal guardian has reviewed and agreed to these Terms on your behalf.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s1_title', 'Acceptance of Terms', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li1_desc', 'Create shortened URLs via <strong>g2my.link</strong> that redirect to your original destination URLs.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li1_label', 'URL Shortening', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li2_desc', 'Manage, edit, and organise your shortened URLs through the <strong>go2my.link</strong> dashboard.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li2_label', 'Link Management', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li3_desc', 'View click statistics and performance data for your shortened URLs.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li3_label', 'Analytics', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li4_desc', 'Create customisable profile landing pages hosted on <strong>lnks.page</strong>.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li4_label', 'LinksPage Profiles', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li5_desc', 'Programmatic access to URL creation and management via our REST API (subject to plan limits).', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_li5_label', 'API Access', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_p1', 'The Service provides URL shortening, link management, and related tools. Our core offerings include:', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_p2', 'We reserve the right to modify, suspend, or discontinue any part of the Service at any time, with or without notice. Certain features may only be available to users on paid subscription plans.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s2_title', 'Description of Services', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_p1', 'Some features of the Service require you to create an account. When registering, you must provide accurate, current, and complete information. You agree to update your information to keep it accurate and current.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_p2', 'You are responsible for maintaining the confidentiality of your account credentials, including your password. You agree to notify us immediately of any unauthorised use of your account. We are not liable for any loss or damage arising from your failure to protect your account credentials.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_p3', 'Each individual may maintain only one personal account. Creating multiple accounts to circumvent limits, restrictions, or bans is prohibited and may result in termination of all associated accounts.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_sub1', 'Registration', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_sub2', 'Account Security', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_sub3', 'One Account Per Person', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s3_title', 'User Accounts', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_li1', 'Distribute malware, viruses, or other harmful software.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_li2', 'Conduct phishing attacks or fraudulent schemes.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_li3', 'Link to illegal content or facilitate illegal activities.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_li4', 'Send unsolicited bulk messages (spam) using shortened URLs.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_li5', 'Infringe upon the copyrights, trademarks, or other intellectual property rights of any third party.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_p1', 'Your use of the Service is governed by our <a href="/legal/acceptable-use">Acceptable Use Policy</a> (AUP), which is incorporated into these Terms by reference. You agree to comply with the AUP at all times.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_p2', 'Without limiting the AUP, you must not use the Service to:', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_p3', 'We reserve the right to investigate and take appropriate action against anyone who, in our sole discretion, violates these provisions, including but not limited to removing offending content, suspending or terminating accounts, and reporting violations to law enforcement authorities.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s4_title', 'Acceptable Use', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_p1', 'The Service, including its original content, features, functionality, design, logos, and trademarks, is owned by <strong>MWBM Partners Ltd</strong> and is protected by international copyright, trademark, and other intellectual property laws. You may not copy, modify, distribute, sell, or lease any part of the Service without our prior written consent.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_p2', 'You retain all ownership rights to the content you submit, post, or display through the Service. This includes your destination URLs, custom aliases, LinksPage profile content, and any other materials you provide.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_p3', 'By submitting content to the Service, you grant <strong>MWBM Partners Ltd</strong> a worldwide, non-exclusive, royalty-free licence to use, host, store, reproduce, and display your content solely for the purpose of operating and providing the Service. This licence continues for as long as your content remains on the Service and for a reasonable period thereafter to allow for removal.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_sub1', 'Our Intellectual Property', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_sub2', 'Your Content', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_sub3', 'Licence Grant', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s5_title', 'Intellectual Property', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_p1', 'Short URLs that are found to violate these Terms or our <a href="/legal/acceptable-use">Acceptable Use Policy</a> may be deactivated, disabled, or removed at any time without prior notice. We may also deactivate short URLs in response to valid legal requests or abuse reports.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_p2', 'While we strive to maintain the availability of all short URLs, we do not guarantee the permanent availability of short URLs created on free-tier accounts. Free-tier short URLs may be subject to expiration after a period of inactivity or if the Service is discontinued. Paid plans may offer extended or permanent URL retention as described in the applicable plan details.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_p3', 'Custom short URL aliases (vanity URLs) are subject to availability and are provided on a first-come, first-served basis. We reserve the right to reclaim, reassign, or reject any custom alias at our sole discretion, including aliases that may cause confusion, infringe trademarks, or violate our policies.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_sub1', 'Deactivation for Violations', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_sub2', 'Availability', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_sub3', 'Custom Aliases', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s6_title', 'Short URL Policies', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_p1', 'The LinksPage service allows you to create customisable profile landing pages hosted on <strong>lnks.page</strong>. These pages serve as a centralised hub for your links and online presence.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_p2', 'All content displayed on your LinksPage profile must comply with these Terms and our <a href="/legal/acceptable-use">Acceptable Use Policy</a>. You must not use your LinksPage profile to display misleading, harmful, offensive, or illegal content. Profile pages that impersonate other individuals, brands, or organisations are strictly prohibited.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_p3', '<strong>MWBM Partners Ltd</strong> reserves the right to remove, disable, or modify any LinksPage profile or its content at any time and for any reason, including but not limited to violations of these Terms, abuse reports, legal requirements, or inactivity.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_sub1', 'Profile Pages', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_sub2', 'Content Guidelines', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_sub3', 'Right to Remove', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s7_title', 'LinksPage Service', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_p1', 'Access to the API is subject to rate limits that vary by subscription plan. You must not attempt to circumvent, bypass, or exceed these rate limits. Exceeding rate limits may result in temporary or permanent suspension of your API access.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_p2', 'Your API keys are confidential credentials. You must not share, publish, or expose your API keys in public repositories, client-side code, or any other publicly accessible location. You are responsible for all activity that occurs using your API keys. If you believe your API key has been compromised, you must regenerate it immediately.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_p3', 'API access is provided for legitimate use cases related to URL shortening and link management. Automated or bulk usage that places an unreasonable burden on the Service, or usage that is inconsistent with the intended purpose of the API, may be restricted at our discretion.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_sub1', 'Rate Limits', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_sub2', 'API Key Confidentiality', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_sub3', 'Fair Use', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s8_title', 'API Usage', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s9_p1', 'The Service is provided on an "as is" and "as available" basis, without warranties of any kind, either express or implied. We do not guarantee that the Service will be uninterrupted, error-free, secure, or free from viruses or other harmful components.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s9_p2', 'We do not guarantee any specific level of uptime or availability. While we make reasonable efforts to maintain the Service, scheduled and unscheduled maintenance, technical issues, or circumstances beyond our control may result in temporary unavailability.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s9_review', 'This section requires professional legal review to include specific statutory disclaimer language applicable under the laws of England and Wales, including but not limited to disclaimers of implied warranties of merchantability, fitness for a particular purpose, and non-infringement.', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_s9_title', 'Disclaimers &amp; Warranties', 'Legal — Terms of Use', 1),
('en-GB', 'legal.terms_title', 'Terms of Use', 'Legal — Terms of Use', 1);

-- ============================================================================
-- 🔗 Links Page (links.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'links.create_new', 'Create Link', 'My Links page', 1),
('en-GB', 'links.description', 'Manage your short links.', 'My Links page', 1),
('en-GB', 'links.heading', 'My Links', 'My Links page', 1),
('en-GB', 'links.no_links', 'No links found.', 'My Links page', 1),
('en-GB', 'links.search_placeholder', 'Search by short code, URL, or title...', 'My Links page', 1),
('en-GB', 'links.title', 'My Links', 'My Links page', 1);

-- ============================================================================
-- 📄 LinksPage (linkspage.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'linkspage.coming_soon', 'LinksPage is coming soon. Create your own customisable link listing page.', 'LinksPage', 1),
('en-GB', 'linkspage.learn_more', 'Learn More', 'LinksPage', 1),
('en-GB', 'linkspage.not_found', 'This LinksPage does not exist or has not been set up yet.', 'LinksPage', 1);

-- ============================================================================
-- 🔐 Login Page (login.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'login.description', 'Log in to your Go2My.Link account.', 'Login page', 1),
('en-GB', 'login.error_captcha', 'CAPTCHA verification failed. Please try again.', 'Login page', 1),
('en-GB', 'login.error_csrf', 'Your session has expired. Please reload the page and try again.', 'Login page', 1),
('en-GB', 'login.flash_email_verified', 'Email verified successfully! You can now log in.', 'Login page', 1),
('en-GB', 'login.flash_password_reset', 'Your password has been reset. You can now log in with your new password.', 'Login page', 1),
('en-GB', 'login.flash_registered', 'Account created! Please check your email to verify your address, then log in.', 'Login page', 1),
('en-GB', 'login.forgot_password', 'Forgot password?', 'Login page', 1),
('en-GB', 'login.form_heading', 'Sign In', 'Login page', 1),
('en-GB', 'login.heading', 'Log In', 'Login page', 1),
('en-GB', 'login.label_email', 'Email Address', 'Login page', 1),
('en-GB', 'login.label_password', 'Password', 'Login page', 1),
('en-GB', 'login.locked_message', 'Account temporarily locked. Please try again later or reset your password.', 'Login page', 1),
('en-GB', 'login.no_account', 'Don''t have an account?', 'Login page', 1),
('en-GB', 'login.placeholder_email', 'you@example.com', 'Login page', 1),
('en-GB', 'login.placeholder_password', 'Your password', 'Login page', 1),
('en-GB', 'login.register_link', 'Sign up', 'Login page', 1),
('en-GB', 'login.remember_me', 'Remember me', 'Login page', 1),
('en-GB', 'login.submit_button', 'Log In', 'Login page', 1),
('en-GB', 'login.subtitle', 'Sign in to manage your short links and analytics.', 'Login page', 1),
('en-GB', 'login.title', 'Log In', 'Login page', 1);

-- ============================================================================
-- 🚪 Logout Page (logout.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'logout.description', 'You are being logged out.', 'Logout page', 1),
('en-GB', 'logout.title', 'Logging Out', 'Logout page', 1);

-- ============================================================================
-- 🧭 Navigation (nav.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'nav.about', 'About', 'Main navigation', 1),
('en-GB', 'nav.aria_label', 'Main navigation', 'Main navigation', 1),
('en-GB', 'nav.dashboard', 'Dashboard', 'Main navigation', 1),
('en-GB', 'nav.features', 'Features', 'Main navigation', 1),
('en-GB', 'nav.home', 'Home', 'Main navigation', 1),
('en-GB', 'nav.language', 'Language', 'Main navigation', 1),
('en-GB', 'nav.login', 'Log In', 'Main navigation', 1),
('en-GB', 'nav.logout', 'Log Out', 'Main navigation', 1),
('en-GB', 'nav.my_links', 'My Links', 'Main navigation', 1),
('en-GB', 'nav.organisation', 'Organisation', 'Main navigation', 1),
('en-GB', 'nav.pricing', 'Pricing', 'Main navigation', 1),
('en-GB', 'nav.privacy', 'Privacy & Data', 'Main navigation', 1),
('en-GB', 'nav.profile', 'Profile', 'Main navigation', 1),
('en-GB', 'nav.register', 'Sign Up', 'Main navigation', 1),
('en-GB', 'nav.theme_toggle', 'Toggle theme (light/dark/auto)', 'Main navigation', 1),
('en-GB', 'nav.toggle', 'Toggle navigation', 'Main navigation', 1);

-- ============================================================================
-- 🏢 Organisation (org.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'org.create_btn', 'Create Organisation', 'Organisation pages', 1),
('en-GB', 'org.create_description', 'Set up a new organisation for your team.', 'Organisation pages', 1),
('en-GB', 'org.create_heading', 'Create Organisation', 'Organisation pages', 1),
('en-GB', 'org.create_title', 'Create Organisation', 'Organisation pages', 1),
('en-GB', 'org.description', 'Manage your organisation settings and members.', 'Organisation pages', 1),
('en-GB', 'org.domains_description', 'Manage your organisation''s custom domains.', 'Organisation pages', 1),
('en-GB', 'org.domains_title', 'Custom Domains', 'Organisation pages', 1),
('en-GB', 'org.invite_description', 'Send an invitation to join your organisation.', 'Organisation pages', 1),
('en-GB', 'org.invite_title', 'Invite Member', 'Organisation pages', 1),
('en-GB', 'org.members_description', 'Manage your organisation members.', 'Organisation pages', 1),
('en-GB', 'org.members_title', 'Members', 'Organisation pages', 1),
('en-GB', 'org.no_org_desc', 'You''re not currently part of an organisation. Create one to manage team members, custom domains, and branded short links.', 'Organisation pages', 1),
('en-GB', 'org.no_org_heading', 'No Organisation', 'Organisation pages', 1),
('en-GB', 'org.settings_description', 'Update your organisation details.', 'Organisation pages', 1),
('en-GB', 'org.settings_heading', 'Organisation Settings', 'Organisation pages', 1),
('en-GB', 'org.settings_title', 'Organisation Settings', 'Organisation pages', 1),
('en-GB', 'org.short_domains_description', 'Manage your organisation''s short URL domains.', 'Organisation pages', 1),
('en-GB', 'org.short_domains_title', 'Short Domains', 'Organisation pages', 1),
('en-GB', 'org.title', 'Organisation', 'Organisation pages', 1);

-- ============================================================================
-- 💰 Pricing Page (pricing.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'pricing.coming_soon', 'Coming Soon', 'Pricing page', 1),
('en-GB', 'pricing.contact_sales', 'Contact Sales', 'Pricing page', 1),
('en-GB', 'pricing.description', 'Go2My.Link pricing plans — free and premium options.', 'Pricing page', 1),
('en-GB', 'pricing.enterprise_feature_1', 'Everything in Pro', 'Pricing page', 1),
('en-GB', 'pricing.enterprise_feature_2', 'SSO / SAML integration', 'Pricing page', 1),
('en-GB', 'pricing.enterprise_feature_3', 'Dedicated support', 'Pricing page', 1),
('en-GB', 'pricing.enterprise_feature_4', 'Custom SLA', 'Pricing page', 1),
('en-GB', 'pricing.free_feature_1', 'Unlimited short links', 'Pricing page', 1),
('en-GB', 'pricing.free_feature_2', 'Basic click analytics', 'Pricing page', 1),
('en-GB', 'pricing.free_feature_3', 'g2my.link short domain', 'Pricing page', 1),
('en-GB', 'pricing.get_started', 'Get Started Free', 'Pricing page', 1),
('en-GB', 'pricing.heading', 'Pricing', 'Pricing page', 1),
('en-GB', 'pricing.phase9_notice', 'Detailed pricing and subscription management are coming in a future update. Anonymous URL shortening is free and unlimited (rate limits apply).', 'Pricing page', 1),
('en-GB', 'pricing.pro_feature_1', 'Everything in Free', 'Pricing page', 1),
('en-GB', 'pricing.pro_feature_2', 'Custom short domains', 'Pricing page', 1),
('en-GB', 'pricing.pro_feature_3', 'Advanced analytics', 'Pricing page', 1),
('en-GB', 'pricing.pro_feature_4', 'API access', 'Pricing page', 1),
('en-GB', 'pricing.subtitle', 'Simple, transparent pricing for everyone.', 'Pricing page', 1),
('en-GB', 'pricing.tier_enterprise', 'Enterprise', 'Pricing page', 1),
('en-GB', 'pricing.tier_enterprise_desc', 'For organisations with advanced needs.', 'Pricing page', 1),
('en-GB', 'pricing.tier_free', 'Free', 'Pricing page', 1),
('en-GB', 'pricing.tier_free_desc', 'Perfect for personal use and trying out the platform.', 'Pricing page', 1),
('en-GB', 'pricing.tier_pro', 'Pro', 'Pricing page', 1),
('en-GB', 'pricing.tier_pro_desc', 'For professionals and small teams who need more.', 'Pricing page', 1),
('en-GB', 'pricing.tiers_heading', 'Plans', 'Pricing page', 1),
('en-GB', 'pricing.title', 'Pricing', 'Pricing page', 1);

-- ============================================================================
-- 🔒 Privacy Dashboard (privacy.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'privacy.actions_label', 'Privacy actions', 'Privacy dashboard', 1),
('en-GB', 'privacy.analytics', 'Analytics', 'Privacy dashboard', 1),
('en-GB', 'privacy.back_dashboard', 'Back to Dashboard', 'Privacy dashboard', 1),
('en-GB', 'privacy.col_processed', 'Processed', 'Privacy dashboard', 1),
('en-GB', 'privacy.col_requested', 'Requested', 'Privacy dashboard', 1),
('en-GB', 'privacy.col_status', 'Status', 'Privacy dashboard', 1),
('en-GB', 'privacy.col_type', 'Type', 'Privacy dashboard', 1),
('en-GB', 'privacy.consent_granted', 'Granted', 'Privacy dashboard', 1),
('en-GB', 'privacy.consent_not_set', 'Not Set', 'Privacy dashboard', 1),
('en-GB', 'privacy.consent_refused', 'Refused', 'Privacy dashboard', 1),
('en-GB', 'privacy.cookies_desc', 'Control which types of cookies you allow on this site.', 'Privacy dashboard', 1),
('en-GB', 'privacy.cookies_title', 'Cookie Preferences', 'Privacy dashboard', 1),
('en-GB', 'privacy.delete_account', 'Delete Account', 'Privacy dashboard', 1),
('en-GB', 'privacy.delete_desc', 'You have the right to request permanent deletion of your account and all associated data. This action cannot be undone after the grace period.', 'Privacy dashboard', 1),
('en-GB', 'privacy.delete_title', 'Delete Account', 'Privacy dashboard', 1),
('en-GB', 'privacy.description', 'Manage your privacy settings, cookie preferences, and data rights.', 'Privacy dashboard', 1),
('en-GB', 'privacy.essential', 'Essential', 'Privacy dashboard', 1),
('en-GB', 'privacy.export_desc', 'You have the right to receive a copy of all personal data we hold about you. Request an export in a machine-readable JSON format.', 'Privacy dashboard', 1),
('en-GB', 'privacy.export_title', 'Data Export', 'Privacy dashboard', 1),
('en-GB', 'privacy.functional', 'Functional', 'Privacy dashboard', 1),
('en-GB', 'privacy.heading', 'Privacy & Data', 'Privacy dashboard', 1),
('en-GB', 'privacy.intro', 'Manage your cookie preferences, request a copy of your data, or delete your account. These rights are provided under applicable data protection laws including GDPR and CCPA.', 'Privacy dashboard', 1),
('en-GB', 'privacy.manage_cookies', 'Manage Cookies', 'Privacy dashboard', 1),
('en-GB', 'privacy.marketing', 'Marketing', 'Privacy dashboard', 1),
('en-GB', 'privacy.no_requests', 'You have not made any data requests yet.', 'Privacy dashboard', 1),
('en-GB', 'privacy.recent_requests', 'Recent Requests', 'Privacy dashboard', 1),
('en-GB', 'privacy.request_export', 'Request Export', 'Privacy dashboard', 1),
('en-GB', 'privacy.requests_table_label', 'Data requests', 'Privacy dashboard', 1),
('en-GB', 'privacy.title', 'Privacy & Data', 'Privacy dashboard', 1),
('en-GB', 'privacy.type_deletion', 'Account Deletion', 'Privacy dashboard', 1),
('en-GB', 'privacy.type_export', 'Data Export', 'Privacy dashboard', 1);

-- ============================================================================
-- 👤 Profile Page (profile.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'profile.description', 'Manage your account settings.', 'Profile page', 1),
('en-GB', 'profile.heading', 'Profile Settings', 'Profile page', 1),
('en-GB', 'profile.title', 'Profile', 'Profile page', 1);

-- ============================================================================
-- 📝 Registration Page (register.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'register.description', 'Sign up for a free Go2My.Link account.', 'Registration page', 1),
('en-GB', 'register.error_captcha', 'CAPTCHA verification failed. Please try again.', 'Registration page', 1),
('en-GB', 'register.error_csrf', 'Your session has expired. Please reload the page and try again.', 'Registration page', 1),
('en-GB', 'register.error_password_mismatch', 'Passwords do not match.', 'Registration page', 1),
('en-GB', 'register.form_heading', 'Sign Up', 'Registration page', 1),
('en-GB', 'register.go_to_login', 'Go to Login', 'Registration page', 1),
('en-GB', 'register.has_account', 'Already have an account?', 'Registration page', 1),
('en-GB', 'register.heading', 'Create Your Account', 'Registration page', 1),
('en-GB', 'register.label_confirm_password', 'Confirm Password', 'Registration page', 1),
('en-GB', 'register.label_email', 'Email Address', 'Registration page', 1),
('en-GB', 'register.label_first_name', 'First Name', 'Registration page', 1),
('en-GB', 'register.label_last_name', 'Last Name', 'Registration page', 1),
('en-GB', 'register.label_password', 'Password', 'Registration page', 1),
('en-GB', 'register.login_link', 'Log in', 'Registration page', 1),
('en-GB', 'register.password_help', 'Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.', 'Registration page', 1),
('en-GB', 'register.placeholder_confirm_password', 'Re-enter your password', 'Registration page', 1),
('en-GB', 'register.placeholder_email', 'you@example.com', 'Registration page', 1),
('en-GB', 'register.placeholder_first_name', 'John', 'Registration page', 1),
('en-GB', 'register.placeholder_last_name', 'Doe', 'Registration page', 1),
('en-GB', 'register.placeholder_password', 'At least 8 characters', 'Registration page', 1),
('en-GB', 'register.submit_button', 'Create Account', 'Registration page', 1),
('en-GB', 'register.subtitle', 'Sign up to manage your short links, view analytics, and more.', 'Registration page', 1),
('en-GB', 'register.success', 'Account created! Please check your email to verify your address, then log in.', 'Registration page', 1),
('en-GB', 'register.title', 'Create Account', 'Registration page', 1);

-- ============================================================================
-- 🔑 Reset Password Page (reset_password.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'reset_password.description', 'Choose a new password for your account.', 'Reset password page', 1),
('en-GB', 'reset_password.error_csrf', 'Your session has expired. Please reload the page and try again.', 'Reset password page', 1),
('en-GB', 'reset_password.error_no_token', 'No reset token provided. Please use the link from your email.', 'Reset password page', 1),
('en-GB', 'reset_password.error_password_mismatch', 'Passwords do not match.', 'Reset password page', 1),
('en-GB', 'reset_password.form_heading', 'New Password', 'Reset password page', 1),
('en-GB', 'reset_password.go_to_login', 'Go to Login', 'Reset password page', 1),
('en-GB', 'reset_password.heading', 'Reset Password', 'Reset password page', 1),
('en-GB', 'reset_password.label_confirm_password', 'Confirm New Password', 'Reset password page', 1),
('en-GB', 'reset_password.label_password', 'New Password', 'Reset password page', 1),
('en-GB', 'reset_password.password_help', 'Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.', 'Reset password page', 1),
('en-GB', 'reset_password.placeholder_confirm_password', 'Re-enter your new password', 'Reset password page', 1),
('en-GB', 'reset_password.placeholder_password', 'At least 8 characters', 'Reset password page', 1),
('en-GB', 'reset_password.request_new', 'Request a New Link', 'Reset password page', 1),
('en-GB', 'reset_password.submit_button', 'Reset Password', 'Reset password page', 1),
('en-GB', 'reset_password.subtitle', 'Choose a new password for your account.', 'Reset password page', 1),
('en-GB', 'reset_password.success', 'Your password has been reset successfully. You can now log in with your new password.', 'Reset password page', 1),
('en-GB', 'reset_password.title', 'Reset Password', 'Reset password page', 1);

-- ============================================================================
-- 🖥️ Sessions Page (sessions.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'sessions.description', 'View and manage your active sessions.', 'Sessions page', 1),
('en-GB', 'sessions.heading', 'Active Sessions', 'Sessions page', 1),
('en-GB', 'sessions.intro', 'These are the devices currently signed in to your account. If you see a device you don''t recognise, revoke it and change your password.', 'Sessions page', 1),
('en-GB', 'sessions.title', 'Active Sessions', 'Sessions page', 1);

-- ============================================================================
-- 🎨 Theme (theme.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'theme.auto', 'Auto (system)', 'Theme toggle', 1);

-- ============================================================================
-- 🌍 Translation (translate.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'translate.powered_by', 'Machine translation powered by', 'Translation', 1);

-- ============================================================================
-- ✉️ Email Verification (verify_email.*)
-- ============================================================================

INSERT IGNORE INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
('en-GB', 'verify_email.description', 'Verify your email address.', 'Email verification page', 1),
('en-GB', 'verify_email.error_heading', 'Verification Failed', 'Email verification page', 1),
('en-GB', 'verify_email.error_no_token', 'No verification token provided. Please use the link from your email.', 'Email verification page', 1),
('en-GB', 'verify_email.go_to_dashboard', 'Go to Dashboard', 'Email verification page', 1),
('en-GB', 'verify_email.go_to_login', 'Log In', 'Email verification page', 1),
('en-GB', 'verify_email.heading', 'Email Verification', 'Email verification page', 1),
('en-GB', 'verify_email.register_again', 'Register Again', 'Email verification page', 1),
('en-GB', 'verify_email.success_heading', 'Email Verified!', 'Email verification page', 1),
('en-GB', 'verify_email.success_message', 'Your email address has been verified successfully. You can now log in and access all features.', 'Email verification page', 1),
('en-GB', 'verify_email.title', 'Verify Email', 'Email verification page', 1);
