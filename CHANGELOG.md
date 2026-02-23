# 📝 Go2My.Link — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### ✨ Added (Phase 6 — Compliance, Legal & Pre-Launch — Batch 2)

- 📱 **Progressive Web App (PWA)** — Manifests, service workers, and icon sets for all 3 domains: go2my.link, admin.go2my.link, and lnks.page; offline fallback pages, install prompts (#65)
- 🌍 **en-GB translation seed** — Complete baseline of ~1,075 translation keys in `010_phase6_translations.sql` covering all UI strings across all pages; `docs/TRANSLATION.md` with contribution guidelines, key naming conventions, and language status table (#71)
- 🗄️ **Migration plan** — `docs/MIGRATION_PLAN.md` documenting strategy for migrating 480 short URLs, 7 users, 5 orgs from legacy database; includes dry-run SQL script (#67)

### ♿ Fixed (Phase 6 — WCAG 2.1 AA Accessibility Audit, #66)

- 🎨 **Colour contrast** — Fixed brand accent (`#1E88E5` → `#1976D2`, 4.60:1), `.btn-primary` background (5.46:1), navbar inactive links (0.90 opacity), footer link hover underline, `badge bg-info` text contrast, `btn-outline-warning` → `btn-warning text-dark` on validating page
- 🏗️ **Landmarks & semantics** — Added `<header>` wrapper around `<nav>`, replaced `<div role="main">` with `<main>` on Component B error pages, fixed ToC heading levels on legal pages
- 🎯 **Focus management** — Added focus to copy button after AJAX URL creation, focus to error div on failures, focus return to main content on cookie banner dismiss
- ♿ **ARIA & screen readers** — Fixed duplicate announcements on homepage, cookie banner role (`alertdialog` → `region`), homepage title duplication, `aria-label` on all icon-only buttons (edit/deactivate/remove), `aria-label` on copy buttons and result inputs
- 📝 **Form accessibility** — Fixed `for`/`id` associations on read-only fields (profile email, edit link short URL, org handle), required field markers (`aria-hidden` + visually-hidden), `aria-required="true"` on password fields, `aria-hidden` on decorative verification icons
- 🏗️ **Heading hierarchy** — Fixed dashboard stat card `<h3>` → `<p>` (heading level skip), legal page ToC `<h3>` → `<h2>`
- 📋 **Tables** — Added `scope="col"` to all table headers across admin pages, `aria-label` on data tables
- 🔧 **`formField()` textarea support** — Fixed `formField()` generating invalid `<input type="textarea">` — now renders proper `<textarea>` element with `rows` attribute
- 🔗 **Link text** — Replaced "Click here" noscript links in Component B with descriptive text

### ✨ Added (Phase 6 — Compliance, Legal & Pre-Launch — Batch 1)

- 🛡️ **DNT/GPC privacy support** — `web/_functions/dnt.php` with 3 functions: `g2ml_detectDNT()`, `g2ml_shouldTrack()`, `g2ml_isCookieAllowed()` — respects Do Not Track and Global Privacy Control browser signals (#64)
- 🔒 **Content Security Policy headers** — CSP added to all 4 component `.htaccess` files; tight policy for Component B (redirect engine), standard policy for A/Admin/C with CDN allowlists (#64)
- 🔒 **HSTS enabled** — Strict-Transport-Security headers active across all 4 components (#64)
- 🚫 **Custom error pages** — Branded 400, 403, 500 error pages at `/pages/errors/` routed through `index.php` for full template (#64)
- 🌱 **Phase 6 settings seed** — 12 new settings in `009_phase6_settings.sql`: compliance (DNT, consent, jurisdiction, deletion grace, export expiry) and legal versioning (terms, privacy, cookies, AUP) (#64)
- 🍪 **Cookie consent system** — `web/_functions/cookie_consent.php` with 7 functions: consent status, recording, revocation, summary, validation, jurisdiction detection, opt-in model check (#62)
- 🍪 **Cookie consent banner** — `web/_includes/cookie_banner.php` with Bootstrap fixed-bottom banner + customise modal, 4 category toggles (essential/analytics/functional/marketing), WCAG accessible (#62)
- 🍪 **Cookie consent JS** — `web/Go2My.Link/public_html/js/cookie-consent.js` — vanilla JS with AJAX consent recording and fallback form POST (#62)
- 📡 **Consent API endpoint** — `POST /api/consent/` — CSRF-protected, records all 4 consent categories, supports JSON and form-encoded requests (#62)
- 🔐 **Data subject rights** — `web/_functions/data_rights.php` with 6 functions: data export (JSON), deletion request (grace period), anonymisation (PII replacement), admin processing, consent history, request listing (#63)
- 📧 **Privacy email templates** — `data_export_ready.php` (download link + expiry) and `data_deletion_requested.php` (grace period + cancel link) (#63)
- 🖥️ **Privacy dashboard** — 4 pages at `admin.go2my.link/privacy/`: overview, cookie consent preferences, data export, account deletion (#63)
- 📜 **Terms of Use** — 14-section structured template replacing placeholder, with TOC navigation and `{{LEGAL_REVIEW_NEEDED}}` placeholders for lawyer review (#61)
- 📜 **Privacy Policy** — 14-section GDPR/CCPA/LGPD-compliant template with legal basis table, rights breakdown per jurisdiction, ICO complaint reference (#61)
- 📜 **Cookie Policy** — 7-section template with complete cookie inventory tables, manage preferences button, DNT/GPC cross-reference (#61)
- 📜 **Copyright Notice** — 6-section template with DMCA takedown procedure, counter-notification process, repeat infringer policy (#61)
- 📜 **Acceptable Use Policy** — 9-section template with prohibited content/activities lists, enforcement tier table, API usage limits (#61)

### 🔄 Changed (Phase 6 — Batch 1)

- 🔀 **page_init.php** — Added `dnt.php` to Layer 2; added Layer 4 with `cookie_consent.php` + `data_rights.php`; session validation refreshes user data
- 🔀 **activity_logger.php** — Added DNT check: non-critical logging skipped when DNT active; critical security actions always logged regardless
- 🔀 **header.php** — Added CSRF meta tag for AJAX requests
- 🔀 **footer.php** — Added cookie banner include, cookie-consent.js, and Acceptable Use/Copyright legal links
- 🔀 **nav.php** — Added "Privacy & Data" link to logged-in user dropdown (between Organisation and Profile)
- 📋 Version bumped to 0.7.0 across modified files

### ✨ Added (Phase 5 — Organisation Management)

- 🏢 **Organisation management functions** — 18+ functions in `web/_functions/org.php`: `createOrganisation()`, `getOrganisation()`, `updateOrganisation()`, `canManageOrg()`, member/invitation/domain management (#32)
- 👥 **Member management** — `inviteMember()`, `acceptInvitation()`, `removeMember()`, `changeMemberRole()`, `getPendingInvitations()` with email invitation flow (#32)
- 🌐 **Custom domain management** — `addOrgDomain()`, `verifyDomain()` (DNS TXT lookup), `removeOrgDomain()` with verification token system (#32)
- 🔗 **Short domain management** — `addOrgShortDomain()`, `removeOrgShortDomain()`, `setDefaultShortDomain()` (#32)
- 🗄️ **tblOrgInvitations schema** — New table for email-based org invitations with SHA-256 hashed tokens, status tracking, and 7-day expiry (#32)
- 🌱 **Phase 5 settings seed** — 12 new settings: invitation expiry, max invitations, DNS verify prefix, max domains/short domains per tier, reserved handles, handle validation, org creation control (#32)
- 🖥️ **Admin dashboard — Org pages** — 7 new pages at admin.go2my.link/org/ (#32):
  - Organisation overview with stats cards and quick links
  - Create Organisation form with handle validation
  - Organisation settings (basic info for Admin, admin controls for GlobalAdmin)
  - Member management with role change, remove, and pending invitations table
  - Invite Member form with role assignment
  - Custom domain management with DNS verification instructions
  - Short domain management with default selection
- 📧 **Invitation email template** — Branded HTML email with accept button, org name, inviter details, and 7-day expiry notice (#32)
- 🌐 **Public invitation accept page** — Token validation, login redirect for unauthenticated users, org membership check, acceptance flow (#32)
- 📐 **JSON Schema** — `org-invitation.schema.json` for tblOrgInvitations records (#32)

### 🔄 Changed (Phase 5)

- 🔀 **page_init.php** — Added `org.php` to Layer 3 function loading (after auth.php)
- 🔀 **nav.php** — Added "Organisation" link to logged-in user dropdown (between "My Links" and "Profile")
- 📋 Version bumped to 0.6.0 across modified files

### 🏗️ Infrastructure

- 🏷️ **Product rename** — Corrected product name from "GoToMyLink" to "Go2My.Link" across all ~116 files (PHP, SQL, MD, YAML, JS, CSS, HTML, htaccess)
- 🏷️ **GitHub repo renamed** — `MWBMPartners/GoToMyLink` → `MWBMPartners/Go2My.Link` (old URLs auto-redirect)
- 🏷️ **Project board renamed** — "GoToMyLink Development" → "Go2My.Link Development"
- 📋 **Issue templates** — Added 3 YAML-based issue forms: Bug Report, Feature Request, Phase Task
- 📋 **Issue chooser config** — Links to Project Board, Project Status, and Dev Notes
- 📋 **PR template** — Standardised pull request template with summary, changes, component checkboxes, testing checklist
- 📋 **Phase restructuring** — Renumbered Phases 5-11, prioritised org management + compliance before pre-release; advanced auth/API/LinksPage/payments moved to post-launch
- 📊 **Timeline estimates** — Added milestone due dates and per-issue hour estimates (512h total) to GitHub Project
- 📊 **Project board fields** — Added "Target Date" and "Estimated Hours" custom fields
- 📋 **Retrospective issue updates** — Checked all task boxes and added commit-link comments on all 42 completed issues
- 🔍 **PHP lint fix** — Renamed `setLocale()` → `g2ml_setLocale()` to resolve conflict with PHP built-in `setlocale()`
- 🔍 **PHPStan + PHPCS** — Created `phpstan.neon` (level 5) and `phpcs.xml` (PSR-12 adapted) configs; enhanced CI workflow
- 🌐 **W3C fix** — Replaced broken Bootstrap CSS fallback script in `header.php` with `onerror` attribute
- 📐 **JSON Schemas** — Created 5 JSON Schema files (draft 2020-12) in `web/_schemas/` for API responses, database columns, and external CAPTCHA responses
- 📐 **JSON validator** — Created `g2ml_validateJSON()` pure-PHP validator (no Composer) for server-side schema validation

### ✨ Added

- 🔑 **Authentication functions** — `registerUser()`, `loginUser()`, `logoutUser()`, `isAuthenticated()`, `getCurrentUser()`, `requireAuth()`, role-based access, account lockout, email verification, password reset (#25)
- 🔐 **Session management** — Database-backed sessions with `createUserSession()`, `validateUserSession()`, `listUserSessions()`, `revokeSession()`, `revokeAllOtherSessions()`, device parsing, IP masking (#26)
- 📧 **Email system** — `g2ml_sendEmail()` with templated HTML emails: verification, password reset, password changed notification, new login alert (#25)
- 📝 **Auth pages** — Register, Login (with CAPTCHA after N failures), Logout, Forgot Password (rate-limited, enumeration-safe), Reset Password, Verify Email — all with CSRF, i18n, and formField() (#28)
- 🖥️ **Admin dashboard** — auth-gated at admin.go2my.link with file-based routing (#30)
  - Dashboard home: link count, click stats, active links, recent links table
  - Link management: paginated list with search/filter, create with full options, edit with ownership check, delete with confirmation
  - Profile: personal info editing, password change, account info
  - Session management: view all active sessions, revoke individual or all others
- 🔒 **Security features** — SHA-256 hashed token storage, timing-safe email enumeration prevention, Argon2id password rehash on login, session regeneration, cross-subdomain session sharing (#25, #26)
- 🌱 **Phase 4 settings seed** — 14 new settings: password policy (4), security/lockout (4), email config (3), auth behaviour (3) (#30)

### 🔄 Changed

- 🔀 **page_init.php** — Added Layer 3 function loading (email, session, auth); cross-subdomain session cookie (`.go2my.link`); authenticated session validation + refresh; probabilistic session cleanup
- 🔀 **nav.php** — Enhanced logged-in dropdown: avatar, email display, Dashboard/My Links/Profile links to admin.go2my.link
- 🔀 **Admin index.php** — Replaced auth placeholder with `requireAuth('User')`; replaced inline HTML with file-based routing to dashboard pages
- 📋 Version bumped to 0.5.0 across modified files

### ✨ Previously Added

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
