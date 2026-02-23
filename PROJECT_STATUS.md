# 📊 Go2My.Link — Project Status

> Last updated: 2026-02-23

## 🏗️ Current Phase

**Phase 6: Compliance, Legal & Pre-Launch** — 🔄 In Progress (Batch 1 of 2 complete)

## 📋 Build Progress

| Phase | Milestone | Status | Issues | Est. Hours |
| --- | --- | --- | --- | --- |
| Phase 0 | v0.1.0 — Scaffolding | ✅ **Complete** | 7 issues | — |
| Phase 1 | v0.2.0 — Database | ✅ **Complete** | 5 issues | — |
| Phase 2 | v0.3.0 — PHP Framework | ✅ **Complete** | 11 issues | — |
| Phase 3 | v0.4.0 — Core Product | ✅ **Complete** | 10 issues | — |
| Phase 4 | v0.5.0 — User System: Auth & Dashboard | ✅ **Complete** | 4 issues | — |
| **Phase 5** | **v0.6.0 — Organisation Management** | ✅ **Complete** | **1 issue** | **20h** |
| **Phase 6** | **v0.7.0 — Compliance, Legal & Pre-Launch** | 🔄 **In Progress** | **8 issues** | **99h** |
| — | **v1.0.0-rc — PRE-RELEASE CANDIDATE** | — | — | — |
| Phase 7 | v1.1.0 — Advanced Authentication | 🔜 Not Started | 4 issues | 68h |
| Phase 8 | v1.2.0 — API & Analytics | 🔜 Not Started | 8 issues | 116h |
| Phase 9 | v1.3.0 — LinksPage | 🔜 Not Started | 6 issues | 84h |
| Phase 10 | v1.4.0 — Advanced Redirects | 🔜 Not Started | 6 issues | 70h |
| Phase 11 | v1.5.0 — Payments & Subscriptions | 🔜 Not Started | 4 issues | 60h |

## 🔄 In Progress

### v0.7.0 — ⚖️ Compliance, Legal & Pre-Launch (Phase 6) — Batch 1 Complete

- [x] 6.1 — 🛡️ DNT/GPC support & production hardening: `dnt.php` (3 functions), CSP headers on all 4 .htaccess files, HSTS enabled, custom error pages (400/403/500), 12 new compliance settings (#64)
- [x] 6.2 — 🍪 Cookie consent system: `cookie_consent.php` (7 functions), cookie banner + customise modal, `cookie-consent.js`, consent API endpoint, GDPR opt-in/opt-out jurisdiction detection (#62)
- [x] 6.3 — 🔐 Data subject rights (GDPR/CCPA/LGPD): `data_rights.php` (6 functions), data export, deletion requests with grace period, anonymisation, privacy dashboard (4 pages), email templates (#63)
- [x] 6.4 — 📜 Legal document templates: Terms of Use (14 sections), Privacy Policy (14 sections), Cookie Policy (7 sections), Copyright Notice (6 sections), Acceptable Use Policy (9 sections) — all with `{{LEGAL_REVIEW_NEEDED}}` placeholders (#61)
- [ ] 6.5 — 📱 PWA manifest & service worker (#65)
- [ ] 6.6 — ♿ WCAG 2.1 AA audit & fixes (#66)
- [ ] 6.7 — 🌍 Seed key translations (#71)
- [ ] 6.8 — 🗄️ Data migration plan & dry-run (#67)

## ✅ Completed Milestones

### v0.6.0 — 🏢 Organisation Management (Phase 5)

- [x] 5.1 — 🏢 Organisation management: create org, edit settings, overview dashboard, member management, email invitations with tokenised accept flow, custom domain DNS verification, short domain management, role enforcement (GlobalAdmin > Admin > User) (#32)

### v0.5.0 — 🔑 User System: Auth & Dashboard (Phase 4)

- [x] 4.1 — 🔑 Auth functions: `registerUser()`, `loginUser()`, `logoutUser()`, `isAuthenticated()`, `requireAuth()`, role hierarchy, account lockout, email verification, password reset, password change (#25)
- [x] 4.2 — 🔐 Session management: `createUserSession()`, `validateUserSession()`, `listUserSessions()`, `revokeSession()`, `revokeAllOtherSessions()`, device parsing, probabilistic cleanup (#26)
- [x] 4.3 — 📝 Auth pages: Register, Login (adaptive CAPTCHA), Logout, Forgot Password (rate-limited), Reset Password (token-based), Verify Email — all CSRF-protected with i18n (#28)
- [x] 4.4 — 🖥️ Admin dashboard: Overview (stats + recent links), Link CRUD (search, filter, paginate), Profile (personal info + password), Session management (list + revoke) (#30)

### v0.4.0 — 🚀 Core Product (Phase 3)

- [x] 3.1 — 🔀 Redirect resolver & domain resolver functions: `resolveShortCode()`, `validateDestination()`, `getOrgByDomain()`, `getDomainFallbackURL()` (#10)
- [x] 3.2 — 🔀 Redirect processor refactor: resolver-based flow, DNT respect, destination validation gate, `lastClickAt` tracking (#8)
- [x] 3.3 — 🚫 Branded error/fallback pages: 404, expired/scheduled, validation failure — self-contained HTML with countdown timers (#11)
- [x] 3.4 — 🤖 Dynamic robots.txt & favicon handlers: settings-based, org-specific favicon support (#13)
- [x] 3.5 — ✨ Anonymous short URL creation: `createShortURL()`, rate limiting, CAPTCHA verification (#16)
- [x] 3.6 — 📡 Internal API endpoint: `POST /api/create/` with CSRF, CAPTCHA, rate limiting, no-JS fallback (#18)
- [x] 3.7 — 🏠 Homepage with URL shortening form: AJAX, copy-to-clipboard, conditional CAPTCHA, no-JS fallback (#15)
- [x] 3.8 — 📝 Static pages: About, Features, Pricing, Contact, Legal placeholders (#20)
- [x] 3.9 — 🔍 URL info/preview page: short code lookup, masked destination, status badges (#23)
- [x] 3.10 — 🎨 Dark/light mode theme system: Bootstrap 5.3 colour modes, theme toggle, FOUC prevention (#74)

### v0.3.0 — 🛠️ PHP Framework (Phase 2)

- [x] 2.1 — 🗄️ Database connection layer: MySQLi singleton via `getDB()`, utf8mb4, UTC timezone (#19)
- [x] 2.2 — 🗄️ Prepared statement query wrappers: `dbSelect()`, `dbInsert()`, `dbUpdate()`, `dbDelete()`, `dbCallProcedure()` (#21)
- [x] 2.3 — ⚙️ Settings manager: `getSetting()`/`setSetting()` with scope cascade + encryption (#22)
- [x] 2.4 — 🐛 Error and activity logging: custom error/exception handlers → tblErrorLog, `logActivity()` with basic UA parsing → tblActivityLog (#24)
- [x] 2.5 — 🔒 Security utilities: AES-256-GCM encrypt/decrypt, Argon2id hashing, CSRF tokens, input sanitisation (#27)
- [x] 2.6 — 🎨 Template/layout engine: header.php (Bootstrap 5 + FA6 CDN + fallback), nav.php, footer.php with debug panel (#29)
- [x] 2.7 — 🔀 Router and entry points: file-based `resolveRoute()` for Components A/Admin, direct routing for B/C, all 4 index.php files (#31)
- [x] 2.8 — 📦 Third-party libraries: Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.1, Chart.js 4.4.7 (local fallback copies) (#33)
- [x] 2.9 — ♿ Accessibility foundation: WCAG 2.1 AA helpers (`srOnly()`, `ariaLiveRegion()`, `formField()`, `skipToContent()`), docs/ACCESSIBILITY.md (#68)
- [x] 2.10 — 🌍 i18n/translation infrastructure: `__()`, `_n()`, `_e()`, locale detection, language switcher dropdown (#69)
- [x] 2.11 — 🌍 Interim Google Translate widget for non-translated locales (#70)

### v0.2.0 — 🗄️ Database (Phase 1)

- [x] 1.1 — 🔧 Core tables: tblSettings, tblSubscriptionTiers, tblOrganisations, tblOrgDomains, tblOrgShortDomains, tblUsers, tblUserSocialLogins, tblUserPassKeys, tblUserSessions
- [x] 1.2 — 🔗 Short URL tables: tblShortURLs, tblCategories, tblTags, tblShortURLTags, tblShortURLSchedules, tblShortURLDeviceRedirects, tblShortURLGeoRedirects, tblShortURLAgeGates
- [x] 1.3 — 📊 Extended tables: tblActivityLog, tblErrorLog, tblAPIKeys, tblAPIRequestLog, tblLinksPageTemplates, tblLinksPages, tblLinksPageItems, tblSubscriptions, tblPayments, tblPaymentDiscounts, tblConsentRecords, tblDataDeletionRequests, tblLanguages, tblTranslations
- [x] 1.4 — ⚡ Stored procedures: sp_lookupShortURL, sp_logActivity, sp_generateShortCode
- [x] 1.5 — 📦 Migration scripts (6 scripts for orgs, users, categories, URLs, settings, activity log)
- [x] 1.6 — 🌱 Seed data (subscription tiers, default org, settings, LinksPage templates, languages)

### v0.1.0 — 📁 Scaffolding (Phase 0)

- [x] 0.1 — 📁 Full `web/` directory structure for all 3 components
- [x] 0.1 — 🔒 Server-wide and per-component `auth_creds.php` templates
- [x] 0.1 — 📝 Per-component README.md and CHANGELOG.md
- [x] 0.2 — 🚫 Comprehensive `.gitignore` for PHP/MySQL project
- [x] 0.3 — 📝 Documentation framework (README, CHANGELOG, PROJECT_STATUS, DEV_NOTES)
- [x] 0.3 — 📝 docs/ directory with ARCHITECTURE.md, DATABASE.md, API.md, DEPLOYMENT.md
- [x] 0.4 — 🏗️ GitHub infrastructure (72 issues, 11 milestones, project board, 38 labels)
- [x] 0.4 — ⚡ GitHub Actions (php-lint.yml, sftp-deploy.yml)
- [x] 0.4 — 🔒 Branch protection, secret scanning, Dependabot
- [x] 0.5 — 🌐 "Coming Soon" landing pages for all 3 domains
- [x] 0.6 — 🔀 .htaccess foundation with HTTPS, security headers, clean URLs, routing
- [x] 0.7 — 🎨 Brand guidelines document and branding asset catalogue

## ❌ Current Blockers

None.

## 📝 Recent Decisions

- 🔑 **Token storage** — All tokens (session, email verify, password reset) stored as SHA-256 hashes in DB; plaintext only in `$_SESSION` or email links
- 🔒 **Cross-subdomain sessions** — Cookie domain `.go2my.link` in production for sharing between go2my.link and admin.go2my.link
- 🛡️ **Email enumeration prevention** — Generic errors on registration, login, and forgot-password; timing-safe dummy hash on user-not-found
- 🔐 **Account lockout** — After 5 failed login attempts, account locked for 15 minutes (configurable via settings)
- 🎨 **Dark/light mode** required for all web UI — manual toggle + automatic system preference detection (Bootstrap 5.3 `data-bs-theme`)
- 📋 **Phase restructuring (Feb 2026)** — Merged old Phases 3+4 into new Phase 3; split old Phase 5 into Phases 4+5
- 📋 **Phase restructuring (Feb 2026)** — Prioritised org management (Phase 5) + compliance (Phase 6, was Phase 10) before pre-release; advanced auth, API, LinksPage, advanced redirects, payments become post-launch Phases 7-11
- 🚀 **Pre-release marker** — v1.0.0-rc after Phase 6 (compliance); sufficient for minimum launchable product
- 📊 **Code quality baseline** — PHP lint (60 files clean), JSON Schemas (5 schema files + validator), W3C compliance verified, CI enhanced with PHPStan + PHPCS
- 🔍 **JSON Schema validation** — All JSON structures have matching schemas in `web/_schemas/`; pure-PHP validator `g2ml_validateJSON()` for Dreamhost
- ♿ Accessibility (WCAG 2.1 AA) is a foundational requirement from Phase 2 onwards
- 🌍 i18n infrastructure built into Phase 2; formal translations in Phase 6
- 🌍 Interim Google/Bing/AI translation widget until formal translations are ready
- 🎨 Branding/logo design included in Phase 0
- 🔒 All passwords from existing database will be force-reset during migration (currently plaintext)
- 🗑️ `tblLicenses` (legacy NetPLAYER data) will NOT be migrated
- 📁 Branding directory is `assets/BrandKit/` (moved from `.BrandKit/`)
- 🗄️ New database uses InnoDB (replacing MyISAM) with proper FK constraints
- ⚙️ Settings merged into single table with scope hierarchy (Default > System > Organisation > User)
- 📊 Activity log migrated with batch approach (10K rows per batch) due to volume
- 🔗 QR codes excluded from project — will be a separate first-party service with future integration
- 📁 Component A directory renamed from `GoToMy.Link` to `Go2My.Link` (domain name match)
- 🏢 Admin dashboard separated to `admin.go2my.link` subdomain (`_admin/public_html/`)
- 🚀 **Release workflow** — GitHub Actions `release.yml` supports per-component releases (A, B, C, Admin, All) with PHP lint, tagging, and auto-generated release notes
- 📡 **OpenAPI/Swagger docs** — Issue #75 added to Phase 6 for interactive API documentation at `/api/docs`
- 🏷️ **Product rename** — Corrected from "GoToMyLink" to "Go2My.Link" across all files, repo, and project board
- 📋 **Issue templates** — Added Bug Report, Feature Request, Phase Task forms + PR template

## 🔜 Next Up

**Phase 6 Batch 2** — PWA manifest, WCAG audit, seed translations, migration plan. 4 remaining issues.

**v1.0.0-rc Pre-Release** — After Phase 6 is fully complete, the product is legally compliant and functionally complete for public launch.

**Post-launch enhancements:** Phase 7 (Advanced Auth), Phase 8 (API & Analytics + Swagger #75), Phase 9 (LinksPage), Phase 10 (Advanced Redirects), Phase 11 (Payments).

## 🔗 Links

- [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)
- [Build Plan](.claude/plans/parsed-squishing-platypus.md)
