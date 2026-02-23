# 📊 GoToMyLink — Project Status

> Last updated: 2026-02-23

## 🏗️ Current Phase

**Phase 4: User System — Auth & Dashboard** — ✅ Complete

## 📋 Build Progress

| Phase | Milestone | Status | Issues |
| --- | --- | --- | --- |
| Phase 0 | v0.1.0 — Scaffolding | ✅ **Complete** | 7 issues |
| Phase 1 | v0.2.0 — Database | ✅ **Complete** | 5 issues |
| Phase 2 | v0.3.0 — PHP Framework | ✅ **Complete** | 11 issues |
| Phase 3 | v0.4.0 — Core Product | ✅ **Complete** | 10 issues |
| Phase 4 | v0.5.0 — User System: Auth & Dashboard | ✅ **Complete** | 4 issues |
| Phase 5 | v0.6.0 — Orgs, Admin & Advanced Auth | 🔜 Not Started | 5 issues |
| Phase 6 | v0.7.0 — API & Analytics | 🔜 Not Started | 7 issues |
| Phase 7 | v0.8.0 — LinksPage | 🔜 Not Started | 6 issues |
| Phase 8 | v0.9.0 — Advanced Redirects | 🔜 Not Started | 6 issues |
| Phase 9 | v0.10.0 — Payments | 🔜 Not Started | 4 issues |
| Phase 10 | v1.0.0 — Legal & Launch | 🔜 Not Started | 8 issues |

## ✅ Completed Milestones

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
- 📋 **Phase restructuring** — Merged old Phases 3+4 into new Phase 3 (Core Product); split old Phase 5 into new Phases 4+5 (basic auth vs org/admin)
- ♿ Accessibility (WCAG 2.1 AA) is a foundational requirement from Phase 2 onwards
- 🌍 i18n infrastructure built into Phase 2; formal translations in Phase 10
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

## 🔜 Next Up

**Phase 5: Organisations, Admin & Advanced Auth** — Organisation management, admin panel, social login (Google/GitHub), 2FA (TOTP), passkeys, and advanced user management. 5 issues.

## 🔗 Links

- [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)
- [Build Plan](.claude/plans/parsed-squishing-platypus.md)
