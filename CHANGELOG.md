# 📝 GoToMyLink — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### ✨ Added

- 🔀 **Redirect resolver functions** — `resolveShortCode()`, `validateDestination()`, `buildRedirectResponse()` for Component B (#10)
- 🌐 **Domain resolver functions** — `getOrgByDomain()`, `getDomainFallbackURL()`, `getOrgFavicon()` for multi-domain org support (#10)
- 🚫 **Branded error pages** for Component B — self-contained 404, expired/scheduled, and destination validation pages with countdown timers and ARIA live regions (#11)
- 🤖 **Dynamic robots.txt** — settings-based, with optional suspicious bot blocking (#13)
- 🎨 **Dynamic favicon handler** — org-specific favicons from `_uploads/`, fallback to default (#13)
- ✨ **Short URL creation** — `createShortURL()` with URL validation, self-reference blocking, SP-based code generation, activity logging (#16)
- ⏱️ **Anonymous rate limiting** — IP-based hourly/daily limits via `tblActivityLog` indexes (no Redis) (#16)
- 🤖 **Bot protection** — Conditional Cloudflare Turnstile / Google reCAPTCHA with server-side verification (#16)
- 📡 **Internal API endpoint** — `POST /api/create/` with CSRF, CAPTCHA, rate limiting, JSON response, no-JS fallback (#18)
- 🏠 **Functional homepage** — URL shortening form with AJAX submission, copy-to-clipboard, conditional CAPTCHA widget, no-JS fallback via query params (#15)
- 📝 **Static pages** — About, Features, Pricing (placeholder), Contact (with email sending), Legal placeholders (Terms, Privacy, Cookies) (#20)
- 🔍 **URL info/preview page** — Look up short codes, see destination domain (masked path), status, creation date, category (#23)
- 🔗 `.htaccess` rewrite for `/info/SHORTCODE` route on Component A (#23)
- 🌱 **Phase 3 settings seed** — 14 new settings: indexer, analytics, redirect, CAPTCHA, rate limiting, short code, contact (#23)
- 🎨 Dark/light mode theme system using Bootstrap 5.3 colour modes (`data-bs-theme`) (#74)
  - CSS custom properties (`--g2ml-*`) for all brand colours with light/dark variants
  - `theme.js` client-side controller: auto/light/dark states, localStorage + cookie persistence
  - FOUC-prevention inline script in `<head>` for instant theme application
  - Theme toggle button in navbar (sun/moon/auto icon, keyboard accessible, ARIA announced)
  - Navbar and footer pinned to `data-bs-theme="dark"` for brand identity
  - WCAG 2.1 AA contrast verified for all dark mode colour pairs
- 📋 Emoji and symbol enhancement across all documentation files for visual readability

### 🔄 Changed

- 🔀 **Redirect processor refactored** — Component B `index.php` now uses resolver functions instead of direct SP calls; added DNT respect, optional destination validation gate, `lastClickAt` tracking (#8)
- 🔀 **Component A entry point** — `index.php` now auto-loads `_functions/*.php` files; version bumped to 0.4.0 (#15)
- 🔀 **Component B .htaccess** — `robots.txt` and `favicon.ico` now route to PHP handlers instead of static files (#13)
- 🔀 **Component A .htaccess** — Added `/info/SHORTCODE` rewrite rule before catch-all (#23)
- 🎨 `app.js` — Added AJAX form handler, copy-to-clipboard, CAPTCHA callbacks (~230 lines) (#15)
- 🎨 `style.css` — Added URL form result card, static page, legal placeholder, and contact form styles (#15)
- 📋 **Phase restructuring** — Merged old Phase 3 (Redirect Engine) + old Phase 4 (Main Website) into **new Phase 3: Core Product** (10 issues) for a faster path to a working URL shortener
- 📋 **Phase restructuring** — Split old Phase 5 (User System, 9 issues) into **new Phase 4: User System — Auth & Basic Dashboard** (4 issues) and **new Phase 5: Organisations, Admin & Advanced Auth** (5 issues)
- 🏗️ GitHub milestones renamed: v0.4.0 → Core Product, v0.5.0 → User System: Auth & Dashboard, v0.6.0 → Orgs/Admin/Advanced Auth
- 🏗️ GitHub milestones closed: v0.1.0 (Scaffolding), v0.2.0 (Database) — all issues already complete
- 🏷️ GitHub issue labels updated: 5 issues moved from phase-4 → phase-3, 4 issues from phase-5 → phase-4
- 🎨 `style.css` rewritten from hardcoded hex colours to CSS custom properties with dark/light variants
- 🎨 `header.php` updated: reads theme cookie, sets `data-bs-theme` on `<html>`, includes FOUC script + theme.js
- 🎨 `nav.php` updated: theme toggle button added, `data-bs-theme="dark"` on `<nav>` element, phase references updated
- 🎨 `footer.php` updated: `data-bs-theme="dark"` on `<footer>` element
- 📝 Phase references updated in `home.php`, `nav.php` to reflect restructured phases

### ✨ Previously Added

- Initial repository setup with README.md (Phase 0)
- Full `web/` directory structure for all 3 components (Phase 0.1)
- Server-wide and per-component `auth_creds.php` templates with direct-access guards (Phase 0.1)
- Overhauled `.gitignore` for PHP/MySQL project on macOS/Windows with VS Code (Phase 0.2)
- Documentation framework: README.md, CHANGELOG.md, PROJECT_STATUS.md, DEV_NOTES.md (Phase 0.3)
- Architecture, database, API, and deployment documentation stubs (Phase 0.3)
- GitHub infrastructure: 72 issues, 11 milestones, project board, labels (Phase 0.4)
- Per-component README.md and CHANGELOG.md files (Phase 0.1)
- Full documentation stubs: docs/ARCHITECTURE.md, docs/DATABASE.md, docs/API.md, docs/DEPLOYMENT.md (Phase 0.3)
- GitHub Actions: `php-lint.yml` (PHP syntax check on push), `sftp-deploy.yml` (manual SFTP deploy, disabled) (Phase 0.4)
- Dependabot configuration for GitHub Actions monitoring (Phase 0.4)
- Branch protection on `main` (block force push and deletion) (Phase 0.4)
- Secret scanning and push protection enabled (Phase 0.4)
- "Coming Soon" landing pages for all 3 domains with branded styling and email capture (Phase 0.5)
- `.htaccess` foundation for all 3 components: HTTPS enforcement, security headers, private directory blocking, clean URLs, compression, caching (Phase 0.6)
- Component-specific URL routing: `?route=` (go2my.link), `?code=` (g2my.link), `?slug=` (lnks.page) (Phase 0.6)
- Brand guidelines document (assets/BrandKit/BRAND_GUIDELINES.md) with colour palette, typography, and logo usage rules (Phase 0.7)
- New database schema `mwtools_Go2MyLink` — 30 tables across InnoDB with utf8mb4 (Phase 1.1–1.3)
  - Core: tblSettings (merged dictionary+values), tblOrganisations, tblUsers (Argon2id, 2FA, PassKey), tblUserSocialLogins, tblUserPassKeys, tblUserSessions, tblOrgDomains, tblOrgShortDomains
  - Short URLs: tblShortURLs (enhanced), tblCategories, tblTags, tblShortURLTags, tblShortURLSchedules, tblShortURLDeviceRedirects, tblShortURLGeoRedirects, tblShortURLAgeGates
  - Analytics: tblActivityLog (structured geo/UA), tblErrorLog (PHP errors with backtrace)
  - API: tblAPIKeys, tblAPIRequestLog
  - LinksPage: tblLinksPageTemplates, tblLinksPages, tblLinksPageItems
  - Payments: tblSubscriptionTiers, tblSubscriptions, tblPayments, tblPaymentDiscounts
  - Legal: tblConsentRecords, tblDataDeletionRequests
  - Translation: tblLanguages, tblTranslations
- 3 redesigned stored procedures: sp_lookupShortURL, sp_logActivity, sp_generateShortCode (Phase 1.4)
- 6 data migration scripts for existing MWlink data (orgs, users, categories, short URLs, settings, activity log) (Phase 1.5)
- Seed data: subscription tiers (Free/Basic/Premium/Enterprise), default org, 25 platform settings, 5 LinksPage templates, 10 languages (Phase 1.6)
- Admin subdomain structure: `web/Go2My.Link/_admin/public_html/` for admin.go2my.link
- Core PHP framework — 8 shared function files in `web/_functions/` (Phase 2.1–2.5, 2.7, 2.10)
  - `security.php`: AES-256-GCM encrypt/decrypt, Argon2id password hashing, CSRF token management, input sanitisation, IP detection
  - `db_connect.php`: MySQLi singleton via `getDB()`, utf8mb4 charset, UTC timezone, auto-close at shutdown
  - `db_query.php`: Prepared statement wrappers (`dbSelect`, `dbSelectOne`, `dbInsert`, `dbUpdate`, `dbDelete`, `dbCallProcedure`, `dbRawQuery`), transaction support, debug query log
  - `error_handler.php`: Custom PHP error/exception/shutdown handlers → tblErrorLog with fallback to `error_log()`
  - `settings.php`: `getSetting()`/`setSetting()` with User > Org > System > Default scope cascade, encrypted sensitive values, in-memory cache
  - `activity_logger.php`: `logActivity()` with direct INSERT populating all columns including parsed UA fields, basic regex-based browser/OS/device detection
  - `i18n.php`: `__()` translation with `{placeholder}` syntax, `_n()` pluralisation, `_e()` HTML-safe output, locale detection (URL/session/cookie/Accept-Language), language cache
  - `router.php`: `resolveRoute()` file-based routing with directory traversal prevention, route helpers
- Shared includes — 7 files in `web/_includes/` (Phase 2.6, 2.9–2.11)
  - `page_init.php`: Master bootstrap (path constants, environment detection, debug mode, function loading, error handlers, session, settings cache, locale)
  - `accessibility.php`: WCAG 2.1 AA helpers (`srOnly()`, `srHeading()`, `ariaLiveRegion()`, `skipToContent()`, `formField()`, `accessibleAlert()`)
  - `header.php`: HTML5 doctype, meta tags, Bootstrap 5.3 + Font Awesome 6 CDN with local fallback, RTL support
  - `nav.php`: Responsive Bootstrap navbar with i18n, login state placeholder, language switcher integration
  - `footer.php`: Footer with quick links/legal, jQuery + Bootstrap JS CDN with fallback, debug panel
  - `language_switcher.php`: Bootstrap dropdown for language switching, persists via cookie/session
  - `translate_widget.php`: Interim Google Translate widget for non-translated locales
- Entry points — 4 index.php files for all components (Phase 2.7)
  - `web/Go2My.Link/public_html/index.php`: Main website with file-based routing
  - `web/G2My.Link/public_html/index.php`: Shortlink redirect engine calling sp_lookupShortURL
  - `web/Lnks.page/public_html/index.php`: LinksPage placeholder (full implementation Phase 7)
  - `web/Go2My.Link/_admin/public_html/index.php`: Admin dashboard with auth placeholder
- Third-party libraries — local fallback copies in `web/_libraries/` (Phase 2.8)
  - Bootstrap 5.3.3 (CSS, RTL CSS, JS bundle)
  - jQuery 3.7.1
  - Font Awesome 6.5.1 (CSS + webfonts)
  - Chart.js 4.4.7
- Library inventory document: `web/_libraries/README.md` (Phase 2.8)
- Accessibility standards document: `docs/ACCESSIBILITY.md` (Phase 2.9)
- Test homepage: `web/Go2My.Link/public_html/pages/home.php` exercising framework features
- Custom CSS: `web/Go2My.Link/public_html/css/style.css` with brand colours and focus indicators
- Custom JS: `web/Go2My.Link/public_html/js/app.js` with screen reader announcements

### Changed

- Renamed directory `web/GoToMy.Link` to `web/Go2My.Link` to match actual domain name
- Removed tblQRCodes schema and migration — QR codes will be a separate first-party service with future integration via `hasQRCodes` feature flag in tblSubscriptionTiers
- Updated all documentation, .htaccess, and .gitignore references from GoToMy.Link to Go2My.Link
- Migration scripts reduced from 7 to 6 (QR codes migration removed)
- Database schema reduced from 31 to 30 tables (tblQRCodes removed)
