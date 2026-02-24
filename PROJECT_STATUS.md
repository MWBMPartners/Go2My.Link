# 📊 Go2My.Link — Project Status

> Last updated: 2026-02-24

## 🏗️ Current Phase

**Phase 7: API & Analytics** — ⏳ In Progress (early work: email modernization + breach response)

## 📋 Build Progress

| Phase | Milestone | Status | Issues | Est. Hours |
| --- | --- | --- | --- | --- |
| Phase 0 | v0.1.0 — Scaffolding | ✅ **Complete** | 7 issues | — |
| Phase 1 | v0.2.0 — Database | ✅ **Complete** | 5 issues | — |
| Phase 2 | v0.3.0 — PHP Framework | ✅ **Complete** | 11 issues | — |
| Phase 3 | v0.4.0 — Core Product | ✅ **Complete** | 10 issues | — |
| Phase 4 | v0.5.0 — User System: Auth & Dashboard | ✅ **Complete** | 4 issues | — |
| **Phase 5** | **v0.6.0 — Organisation Management** | ✅ **Complete** | **1 issue** | **20h** |
| **Phase 6** | **v0.7.0 — Compliance, Legal & Pre-Launch** | ✅ **Complete** | **7/8 issues** | **99h** |
| — | **v1.0.0-rc — PRE-RELEASE CANDIDATE** | ✅ **Tagged** | — | — |
| Phase 7 | v1.1.0 — API & Analytics | ⏳ **In Progress** | 8 issues | 116h |
| Phase 8 | v1.2.0 — LinksPage | 🔜 Not Started | 6 issues | 84h |
| Phase 9 | v1.3.0 — Advanced Redirects | 🔜 Not Started | 6 issues | 70h |
| Phase 10 | v1.4.0 — Advanced Authentication (SIGNula) | 🔜 Not Started | 4 issues | 68h |
| Phase 11 | v1.5.0 — Payments & Subscriptions (SIGNula) | 🔜 Not Started | 4 issues | 60h |

## 🔄 In Progress

### v1.1.0 — 📡 API & Analytics (Phase 7) — Early Work

Cross-cutting infrastructure improvements completed ahead of main Phase 7 API work:

#### 📧 Email System Modernization (#88) ✅

- [x] Multipart MIME rewrite: text/plain + text/x-amp-html + text/html (RFC 2046)
- [x] HTML-to-plaintext converter (`g2ml_htmlToPlainText()`)
- [x] AMP for Email templates (8 templates in `email_templates/amp/`)
- [x] Dark mode CSS in all 7 HTML email templates
- [x] Preheader text support in all templates
- [x] Modern headers: List-Unsubscribe, X-Entity-Ref-ID, Precedence, Auto-Submitted
- [x] New settings seed: `012_email_settings.sql` (7 settings)

#### 🚨 Mass Credential Reset / Breach Response (#89) ✅

- [x] `breach_response.php` with 6 functions (invalidate passwords, revoke sessions, batch emails, salt rotation)
- [x] Admin page at `/security/breach-response` (GlobalAdmin only)
- [x] `forcePasswordReset` wired into `loginUser()` → session-based token redirect
- [x] Breach notification email template (HTML + AMP)
- [x] ENCRYPTION_SALT rotation with transaction wrapping
- [x] Audit logging to dedicated log file (UTC timestamps)

#### 🔒 Security Hardening (#79–#87) ✅

- [x] CRLF header injection prevention in email system (recipient, subject, DB-sourced values, extra headers)
- [x] Path traversal prevention via template name regex validation
- [x] Transaction wrapping for salt rotation (prevents irrecoverable mixed-key state)
- [x] TOCTOU race condition fix (cooldown timestamp set at start)
- [x] Session-based forced reset token transport (prevents URL/Referer leakage)
- [x] Control character sanitisation, memory clearing, UTC timestamps
- [x] Error suppression removal, input bounds validation, double-encoding fix

#### 🔧 CI/CD Fix (#76) ✅

- [x] PHP Lint workflow: `php-parallel-lint` → `parallel-lint` binary name fix

## ✅ Completed (Previous Phases)

### 🏷️ Infrastructure: Multi-Account-Type Support ✅

Cross-phase infrastructure improvement — enables users to hold multiple account types simultaneously:

- [x] Database schema: `tblAccountTypes` reference table + `tblUserAccountTypes` junction table (`015_account_types.sql`)
- [x] Seed data: 4 system account types matching legacy ENUM roles (`011_account_types.sql`)
- [x] Migration: Backfill junction table from existing `tblUsers.role` (`008_migrate_account_types.sql`)
- [x] PHP library: `account_types.php` with 9 functions (assign, revoke, sync, query)
- [x] Auth integration: session loading, getCurrentUser(), registerUser()
- [x] Org integration: createOrg, acceptInvitation, changeMemberRole, removeMember
- [x] Admin UI: members page multi-type badges, profile page type display
- [x] JSON schemas: `account-type.schema.json`, `user-account-type.schema.json`
- [x] Documentation: CHANGELOG, DATABASE, DEV_NOTES, ARCHITECTURE, MEMORY

### v0.7.0 — ⚖️ Compliance, Legal & Pre-Launch (Phase 6) — 7/8 Issues Done ✅

- [x] 6.1 — 🛡️ DNT/GPC support & production hardening: `dnt.php` (3 functions), CSP headers on all 4 .htaccess files, HSTS enabled, custom error pages (400/403/500), 12 new compliance settings (#64)
- [x] 6.2 — 🍪 Cookie consent system: `cookie_consent.php` (7 functions), cookie banner + customise modal, `cookie-consent.js`, consent API endpoint, GDPR opt-in/opt-out jurisdiction detection (#62)
- [x] 6.3 — 🔐 Data subject rights (GDPR/CCPA/LGPD): `data_rights.php` (6 functions), data export, deletion requests with grace period, anonymisation, privacy dashboard (4 pages), email templates (#63)
- [x] 6.4 — 📜 Legal document templates: Terms of Use (14 sections), Privacy Policy (14 sections), Cookie Policy (7 sections), Copyright Notice (6 sections), Acceptable Use Policy (9 sections) — all with `{{LEGAL_REVIEW_NEEDED}}` placeholders (#61)
- [x] 6.5 — 📱 PWA manifest & service worker: manifest.json + sw.js for Components A, Admin, and C with offline fallback, app icons (192/512px), theme colour integration (#65)
- [x] 6.6 — ♿ WCAG 2.1 AA audit & fixes: 23 files fixed — semantic landmarks, heading hierarchy, colour contrast (4.5:1+), ARIA labels, `scope="col"` on tables, `formField()` textarea support, noscript descriptive links (#66)
- [ ] 6.7 — 🌍 Seed key translations: en-GB baseline complete (~1,075 keys in `010_phase6_translations.sql`), 9 additional locales deferred to post-launch (#71)
- [x] 6.8 — 🗄️ Data migration plan & dry-run: `docs/MIGRATION_PLAN.md` + `web/_sql/dry_run.sql` — 7-step process for 480 URLs, 5 orgs, 7 users (password force-reset), 429K activity log rows (#67)

### 🔒 Pre-Release Audit ✅

Comprehensive security, WCAG, and W3C compliance audit across all components. **20 files modified** with fixes:

- 🔒 **Security:** innerHTML → textContent (XSS), OUT param regex validation (SQLi), referer allowlist (open redirect), SRI hash on RTL CSS, `noreferrer` on 7 external links
- ♿ **Accessibility:** `aria-hidden` on toggler icon, `aria-live="assertive"` on countdown, debug panel contrast, footer link hover contrast, Bootstrap `text-muted` → `text-body-secondary`
- 📧 **Email:** Footer text contrast fixed across all 7 templates (#6c757d → #5a6268)
- ✅ **PHP lint:** All 87 PHP files pass syntax check (PHP 8.4)

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

**Phase 7 remaining work** — REST API endpoints, OpenAPI/Swagger docs (#75), API key auth, analytics dashboard, click tracking, geographic maps, device breakdown, data export.

**#71 Translations** — en-GB baseline seeded with ~1,075 keys. The 9 additional locales deferred to post-launch. Interim Google Translate widget covers machine translation.

**Post-launch suggestions** (non-blocking):

- 🔒 Nonce-based CSP to replace `'unsafe-inline'` for scripts (requires server-side nonce generation)
- 🔒 Replace `confirm()` dialogs with Bootstrap modals for better UX/accessibility
- 🔒 Session cleanup probability tuning (currently 1/100, review under production load)
- ⚖️ Professional legal review of all 5 legal documents (`{{LEGAL_REVIEW_NEEDED}}` placeholders)

**Future phases:** Phase 8 (LinksPage), Phase 9 (Advanced Redirects), Phase 10 (Advanced Auth via SIGNula), Phase 11 (Payments via SIGNula).

## 🔗 Links

- [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)
- [Build Plan](.claude/plans/parsed-squishing-platypus.md)
