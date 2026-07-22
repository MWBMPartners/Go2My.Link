# 🔗 Go2My.Link

A comprehensive URL shortening web service by **MWBM Partners Ltd** (t/a MWservices).

> Successor to the internal "MWlink" service — now expanded into a full-featured, multi-domain platform.

---

## 📋 Overview

Go2My.Link is a URL shortening platform comprising three interconnected web properties:

| Domain | Component | Purpose |
| --- | --- | --- |
| 🌐 [go2my.link](https://go2my.link) | 🏠 Main Website (A) | Public face, sign-up, short link creation |
| 🔧 [admin.go2my.link](https://admin.go2my.link) | 📊 Admin Dashboard (A) | User/org dashboard, link management, settings |
| 🔗 [g2my.link](https://g2my.link) | 🔀 Redirect Engine (B) | Default domain for shortened URLs |
| 📄 [lnks.page](https://lnks.page) | 📑 LinksPage (C) | LinkTree-like customisable link listing pages |

---

## 🚀 Features

### ✅ Implemented

- 🔗 **URL Shortening** — Random short codes, anonymous + authenticated creation, copy-to-clipboard
- 🔀 **Redirect Engine** — Fast redirect resolution via stored procedure, DNT respect, destination validation
- 🔑 **Authentication** — Registration, login (with adaptive CAPTCHA), logout, email verification, password reset
- 🔐 **Session Management** — Database-backed sessions, multi-device tracking, remote revoke
- 🖥️ **Admin Dashboard** — Link CRUD (create/edit/list/delete), profile settings, password change, session management
- 🎨 **Dark/Light Mode** — Bootstrap 5.3 colour modes, auto/light/dark toggle, FOUC prevention
- ♿ **Accessibility** — WCAG 2.1 AA compliant, screen reader support, skip-to-content, ARIA live regions
- 🌍 **i18n** — Translation-ready `__()` function, locale detection, interim Google Translate widget
- 🔒 **Security** — Argon2id hashing, SHA-256 token storage, CSRF protection, account lockout, rate limiting
- 📧 **Transactional Email** — Multipart MIME (text/plain + AMP + HTML), dark mode, preheader text, modern headers
- 🚨 **Breach Response** — GlobalAdmin mass credential reset, session revocation, ENCRYPTION_SALT rotation, batch notification emails
- 📝 **Static Pages** — About, Features, Pricing, Contact (with email), Legal (Terms, Privacy, Cookies)
- 🔍 **URL Info** — Public short code lookup with masked destination, status badges
- 📐 **JSON Schema Validation** — Schema definitions (draft 2020-12) with pure-PHP validator for API responses and database JSON
- 🏢 **Organisations** — Create/manage organisations, member invitations (email with tokenised accept), role enforcement, custom domain DNS verification, short domain management
- 🛡️ **DNT/GPC** — Do Not Track & Global Privacy Control detection, CSP headers, HSTS, custom error pages
- 🍪 **Cookie Consent** — GDPR opt-in/opt-out jurisdiction detection, consent banner, preferences modal, consent API
- 🔐 **Data Rights** — GDPR Article 15-22 compliance: data export, deletion requests, anonymisation, privacy dashboard
- 📜 **Legal Documents** — Terms of Use, Privacy Policy, Cookie Policy, Copyright Notice, Acceptable Use Policy
- 📱 **PWA** — Progressive Web App manifests and service workers for all 3 web properties
- ♿ **WCAG 2.1 AA** — Full accessibility audit with 23+ files fixed: landmarks, ARIA, contrast, headings, forms
- 🔒 **Pre-Release Audit** — Security hardening (XSS, SQLi, open redirect), WCAG refinements, W3C compliance, email template contrast
- 🎨 **Logo Integration** — SVG + PNG `<picture>` fallback in navbar, footer, and all landing pages
- 🌐 **Landing Pages** — Auto-refresh, countdown ring, dark mode, footer pinning, vertical centering

### 🔜 Planned

- 🌐 **Custom Domain Integration** — Finalise client domain setup flow, DNS verification automation, redirect engine integration, setup documentation (#91)
- 📡 **REST API** — JSON/XML endpoints, API key auth, OpenAPI/Swagger docs (#75)
- 📊 **Analytics** — Click tracking, geographic maps, device breakdown, data export
- 📑 **LinksPage** — Template system, WYSIWYG editor, custom domains, age verification
- 🔀 **Advanced Redirects** — Scheduled, device-based, geo-based routing, age gates
- 🔐 **Advanced Auth (SIGNula)** — Social login (Google/GitHub), 2FA (TOTP), passkeys (WebAuthn), SSO
- 💰 **Payments (SIGNula)** — Subscription tiers, PayPal, Apple Pay, Google Pay, crypto

---

## 🛠️ Tech Stack

| Layer | Technology |
| --- | --- |
| 🐘 **Backend** | PHP 8.4+ / 8.5+ (MySQLi, prepared statements only) |
| 🗄️ **Database** | MySQL 8.0+ (InnoDB, utf8mb4, stored procedures) |
| 🎨 **Frontend** | HTML5, CSS3, Bootstrap 5.3, jQuery 3.7, Font Awesome 6 |
| 📈 **Charts** | Chart.js 4.4, Leaflet.js (planned) |
| 🚢 **Hosting** | Dreamhost Shared Hosting (no CLI/Composer) |
| 🤖 **CI/CD** | GitHub Actions (PHP lint, release, SFTP deploy) |

---

## 📊 Build Progress

| | Phase | Version | Name | Issues | Status |
| --- | --- | --- | --- | --- | --- |
| ✅ | 0 | v0.1.0 | Scaffolding | 7 | **Complete** |
| ✅ | 1 | v0.2.0 | Database | 5 | **Complete** |
| ✅ | 2 | v0.3.0 | PHP Framework | 11 | **Complete** |
| ✅ | 3 | v0.4.0 | Core Product | 10 | **Complete** |
| ✅ | 4 | v0.5.0 | User System: Auth & Dashboard | 4 | **Complete** |
| ✅ | 5 | v0.6.0 | Organisation Management | 1 | **Complete** |
| ✅ | 6 | v0.7.0 | Compliance, Legal & Pre-Launch | 7/8 | **Complete** |
| ✅ | — | v1.0.0-rc | **PRE-RELEASE CANDIDATE** | — | **Tagged** |
| ⏳ | 7 | v1.1.0 | API & Analytics | 8 | In Progress |
| 📋 | 8 | v1.2.0 | LinksPage | 6 | Post-Launch |
| 📋 | 9 | v1.3.0 | Advanced Redirects | 6 | Post-Launch |
| 📋 | 10 | v1.4.0 | Advanced Authentication (SIGNula) | 4 | Post-Launch |
| 📋 | 11 | v1.5.0 | Payments & Subscriptions (SIGNula) | 4 | Post-Launch |

> **59 of 89 issues complete (66%)** — tracked on the [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)

### ✅ Phase 0 — Scaffolding (v0.1.0)

- 📁 Full `web/` directory structure for all 3 components
- 🔒 Auth credentials templates with direct-access guards
- 📝 Documentation framework (README, CHANGELOG, PROJECT_STATUS, DEV_NOTES, docs/)
- 🏗️ GitHub infrastructure (issues, milestones, project board, labels, Actions)
- 🌐 "Coming Soon" landing pages for all 3 domains (auto-refresh, countdown ring, dark mode)
- 🔀 `.htaccess` foundation (HTTPS, security headers, clean URLs, routing)
- 🎨 Brand guidelines, logo assets, and full BrandKit (SVG/PNG logos, app icons, favicons, PWA icons)

### ✅ Phase 1 — Database (v0.2.0)

- 🗄️ 30-table schema (`mwtools_Go2MyLink`) with InnoDB + utf8mb4
- ⚡ 3 stored procedures (lookupShortURL, logActivity, generateShortCode)
- 📦 6 migration scripts for existing MWlink data (480 URLs, 5 orgs, 7 users)
- 🌱 Seed data (subscription tiers, settings, LinksPage templates, languages)

### ✅ Phase 2 — PHP Framework (v0.3.0)

- 🛠️ 8 shared function files: DB connection/queries, settings, security, error handling, activity logging, i18n, routing
- 🎨 Template engine: header, nav, footer with Bootstrap 5.3 CDN + local fallback
- ♿ Accessibility helpers (formField, ARIA, skip-to-content)
- 🌍 i18n with `__()` translation function + locale detection + language switcher
- 📦 Third-party libraries: Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.1, Chart.js 4.4.7

### ✅ Phase 3 — Core Product (v0.4.0)

- 🔀 Redirect engine with resolver functions + stored procedure lookup
- 🚫 Branded error pages (404, expired, validating) with countdown timers
- 🤖 Dynamic robots.txt + org-specific favicon handlers
- ✨ Anonymous URL creation with rate limiting + CAPTCHA (Turnstile/reCAPTCHA)
- 📡 Internal API (`POST /api/create/`) with CSRF + CAPTCHA + no-JS fallback
- 🏠 Homepage with AJAX URL shortening form + copy-to-clipboard
- 📝 Static pages: About, Features, Pricing, Contact, Legal
- 🔍 URL info/preview page with status badges
- 🎨 Dark/light mode theme system

### ✅ Phase 4 — User System: Auth & Dashboard (v0.5.0)

- 🔑 Auth engine: register, login, logout, password reset/change, email verification
- 🔐 Database-backed session management with multi-device tracking + revoke
- 📧 Transactional email system with 4 HTML templates
- 📝 Auth pages: Register, Login, Forgot Password, Reset Password, Verify Email, Logout
- 🖥️ Admin dashboard: stats overview, link CRUD, profile + password change, session management
- 🌱 14 database settings for auth, security, email, and password policy

### ✅ Phase 5 — Organisation Management (v0.6.0)

- 🏢 Organisation CRUD: create, edit settings, overview dashboard with stats
- 👥 Member management: invite (email with tokenised accept), role change (User ↔ Admin), remove
- 🌐 Custom domain management: add, DNS TXT verification, remove
- 🔗 Short domain management: add, set default, remove
- 🛡️ Role enforcement: GlobalAdmin > Admin > User with `canManageOrg()` permission check
- 🗄️ tblOrgInvitations schema + 12 org settings + JSON Schema

### ✅ Phase 6 — Compliance, Legal & Pre-Launch (v0.7.0) — Complete

- ✅ 📜 Legal document templates — Terms, Privacy, Cookies, Copyright, AUP (#61)
- ✅ 🍪 Cookie consent system — jurisdiction-aware banner, preferences modal, consent API (#62)
- ✅ 🔐 Data subject rights — GDPR export, deletion, anonymisation, privacy dashboard (#63)
- ✅ 🛡️ DNT/GPC support & production hardening — CSP headers, HSTS, error pages (#64)
- ✅ 📱 PWA manifest & service worker — offline fallback, app icons for all 3 properties (#65)
- ✅ ♿ WCAG 2.1 AA audit — 23 files fixed: landmarks, ARIA, contrast, headings, forms (#66)
- ✅ 🗄️ Migration plan & dry-run SQL — 7-step process, rollback safeguards (#67)
- 🔄 🌍 Translation seed — en-GB baseline (~1,075 keys) done; 9 locales deferred post-launch (#71)
- ✅ 🔒 Pre-release audit — security hardening, WCAG refinements, W3C compliance (20 files)

> 🏁 **v1.0.0-rc — Pre-Release Candidate** after Phase 6

### 📋 Phases 7–11 (Post-Launch Enhancements)

- **Phase 7** — API & Analytics: REST API, OpenAPI/Swagger docs, API keys, analytics dashboard
- **Phase 8** — LinksPage: renderer, templates, WYSIWYG editor, custom domains
- **Phase 9** — Advanced Redirects: scheduled, device, geo, age gates
- **Phase 10** — Advanced Authentication (SIGNula): 2FA/TOTP, social login, SSO, passkeys
- **Phase 11** — Payments & Subscriptions (SIGNula): tiers, PayPal, Apple Pay, Google Pay, crypto

---

## 📁 Repository Structure

```text
Go2My.Link/
├── 🎨 assets/BrandKit/          ← Full brand kit: logos, icons, favicons, PWA icons, press kit
├── 🤖 .claude/                  ← Claude AI context (project brief, plans, memory)
├── 🤖 .openai/                  ← OpenAI context
├── ⚙️ .github/workflows/        ← CI/CD (PHP lint, release, SFTP deploy)
├── 📚 docs/                     ← ARCHITECTURE, DATABASE, API, DEPLOYMENT
├── 🌐 web/
│   ├── ⚙️ _functions/           ← Shared PHP functions (13 files)
│   ├── 📦 _includes/            ← Shared templates + email templates
│   ├── 📦 _libraries/           ← Local fallback libraries (Bootstrap, jQuery, FA, Chart.js)
│   ├── 📐 _schemas/             ← JSON Schema definitions (api, database, external)
│   ├── 🗄️ _sql/                 ← Schema, migrations, seeds, stored procedures
│   ├── 🏠 Go2My.Link/           ← Component A (Main Website + Admin Dashboard)
│   │   ├── public_html/         ← go2my.link web root (+ img/ for logos)
│   │   ├── public_html_landing/ ← "Coming Soon" landing page
│   │   └── _admin/public_html/  ← admin.go2my.link web root (+ img/ for logos)
│   ├── 🔗 G2My.Link/            ← Component B (Redirect Engine)
│   │   ├── public_html/         ← g2my.link web root (+ img/ for logos)
│   │   └── public_html_landing/ ← "Coming Soon" landing page
│   └── 📑 Lnks.page/            ← Component C (LinksPage)
│       ├── public_html/         ← lnks.page web root (+ img/ for logos)
│       └── public_html_landing/ ← "Coming Soon" landing page
├── 📝 CHANGELOG.md
├── 📊 PROJECT_STATUS.md
├── 🗒️ DEV_NOTES.md
└── 📋 README.md
```

---

## ⚙️ Development

### 📌 Prerequisites

- 🐘 PHP 8.4+ or 8.5+
- 🗄️ MySQL 8.0+
- 🖥️ Visual Studio Code (recommended) with FTP Sync extension
- 🔀 Git

### 🏁 Getting Started

1. Clone the repository:

   ```bash
   git clone https://github.com/MWBMPartners/Go2My.Link.git
   ```

2. Copy the auth credentials template — `auth_creds.php` itself is gitignored
   and does **not** exist on a fresh clone; only the example template is
   tracked:

   ```bash
   cp web/_auth_keys/auth_creds.example.php web/_auth_keys/auth_creds.php
   ```

   > ⚠️ The app loads `auth_creds.php` directly (there is no
   > `auth_creds.local.php` mechanism — do not create one, it is never
   > `require`d). See [`docs/INSTALL.md`](docs/INSTALL.md) for the full
   > installer-driven setup, including the per-component
   > `<Component>/.auth/auth_creds.php` thin includes each site needs to boot.

3. Edit `web/_auth_keys/auth_creds.php` with your database credentials.

4. Import the database schema from `web/_sql/schema/` (15 files).

5. Run the seed scripts from `web/_sql/seeds/` (17 files).

6. Point your web server document roots to the appropriate `public_html/` directories.

### 🌿 Branch Strategy

- `main` — Production-ready code
- Feature branches for development, merged via pull request

### 🚀 Releasing

Releases are managed via GitHub Actions. Each component can be released independently:

1. Go to **Actions** → **"🚀 Create Release"** → **"Run workflow"**
2. Select the component (A, B, C, Admin, or All)
3. Enter the version number (e.g., `0.5.0`)
4. Optionally mark as pre-release and add notes
5. The workflow will lint PHP, create a Git tag, and publish a GitHub Release

**Tag format:**

| Scope | Tag Example |
| --- | --- |
| Full platform | `v0.5.0` |
| Component A (Main Website) | `component-a/v0.5.0` |
| Component A (Admin Dashboard) | `component-a-admin/v0.5.0` |
| Component B (Redirect Engine) | `component-b/v0.5.0` |
| Component C (LinksPage) | `component-c/v0.5.0` |

> See [DEV_NOTES.md](DEV_NOTES.md) for detailed release process documentation.

---

## 📋 Project Management

- 📌 **GitHub Project:** [Go2My.Link Development](https://github.com/orgs/MWBMPartners/projects/4)
- 🐛 **Issues:** 89 issues tracked with phase labels (`phase-0` through `phase-11`)
- 🏁 **Milestones:** v0.1.0 (Scaffold) through v1.5.0 (Payments), with v1.0.0-rc pre-release marker

---

## ⚖️ License

Proprietary — MWBM Partners Ltd. All rights reserved.

## 🔗 Links

- 🏢 **Organisation:** [github.com/MWBMPartners](https://github.com/MWBMPartners)
- 📌 **Project Board:** [Go2My.Link Development](https://github.com/orgs/MWBMPartners/projects/4)
- 📝 **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- 📊 **Status:** [PROJECT_STATUS.md](PROJECT_STATUS.md)
- 🗒️ **Dev Notes:** [DEV_NOTES.md](DEV_NOTES.md)
