# 🔗 Go2My.Link

A comprehensive URL shortening web service by **MWBM Partners Ltd** (trading as MWservices).

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
- 📧 **Transactional Email** — Verification, password reset, password change notifications, new login alerts
- 📝 **Static Pages** — About, Features, Pricing, Contact (with email), Legal (Terms, Privacy, Cookies)
- 🔍 **URL Info** — Public short code lookup with masked destination, status badges
- 📐 **JSON Schema Validation** — Schema definitions (draft 2020-12) with pure-PHP validator for API responses and database JSON

### 🔜 Planned

- 🏢 **Organisations** — Multi-org accounts, team management, custom short domains
- 🔐 **Advanced Auth** — Social login (Google/GitHub), 2FA (TOTP), passkeys (WebAuthn), SSO
- 📡 **REST API** — JSON/XML endpoints, API key auth, OpenAPI/Swagger docs (#75)
- 📊 **Analytics** — Click tracking, geographic maps, device breakdown, data export
- 📑 **LinksPage** — Template system, WYSIWYG editor, custom domains, age verification
- 🔀 **Advanced Redirects** — Scheduled, device-based, geo-based routing, age gates
- 💰 **Payments** — Subscription tiers, PayPal, Apple Pay, Google Pay, crypto
- ⚖️ **Legal & Launch** — Cookie consent, GDPR, DNT hardening, PWA, WCAG audit

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
| 🔜 | 5 | v0.6.0 | Organisation Management | 1 | Next Up |
| 📋 | 6 | v0.7.0 | Compliance, Legal & Pre-Launch | 8 | Planned |
| 🏁 | — | v1.0.0-rc | **PRE-RELEASE CANDIDATE** | — | — |
| 📋 | 7 | v1.1.0 | Advanced Authentication | 4 | Post-Launch |
| 📋 | 8 | v1.2.0 | API & Analytics | 8 | Post-Launch |
| 📋 | 9 | v1.3.0 | LinksPage | 6 | Post-Launch |
| 📋 | 10 | v1.4.0 | Advanced Redirects | 6 | Post-Launch |
| 📋 | 11 | v1.5.0 | Payments & Subscriptions | 4 | Post-Launch |

> **42 of 78 issues complete (54%)** — tracked on the [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)

### ✅ Phase 0 — Scaffolding (v0.1.0)

- 📁 Full `web/` directory structure for all 3 components
- 🔒 Auth credentials templates with direct-access guards
- 📝 Documentation framework (README, CHANGELOG, PROJECT_STATUS, DEV_NOTES, docs/)
- 🏗️ GitHub infrastructure (issues, milestones, project board, labels, Actions)
- 🌐 "Coming Soon" landing pages for all 3 domains
- 🔀 `.htaccess` foundation (HTTPS, security headers, clean URLs, routing)
- 🎨 Brand guidelines and logo assets

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

### 🔜 Phase 5 — Organisation Management (v0.6.0)

- 🏢 Organisation management, team accounts, custom short domains (#32)

### 📋 Phase 6 — Compliance, Legal & Pre-Launch (v0.7.0)

- ⚖️ Cookie consent banner (#61)
- 🔒 GDPR compliance tools (#62)
- 🕵️ DNT/GPC hardening (#63)
- 📱 Progressive Web App (#64)
- ♿ WCAG audit (#65)
- 🌍 User-facing translations (#66)
- 🔧 Production hardening (#67)
- 📋 Pre-launch checklist (#71)

> 🏁 **v1.0.0-rc — Pre-Release Candidate** after Phase 6

### 📋 Phases 7–11 (Post-Launch Enhancements)

- **Phase 7** — Advanced Authentication: 2FA/TOTP, social login, SSO, passkeys
- **Phase 8** — API & Analytics: REST API, OpenAPI/Swagger docs, API keys, analytics dashboard
- **Phase 9** — LinksPage: renderer, templates, WYSIWYG editor, custom domains
- **Phase 10** — Advanced Redirects: scheduled, device, geo, age gates
- **Phase 11** — Payments & Subscriptions: tiers, PayPal, Apple Pay, Google Pay, crypto

---

## 📁 Repository Structure

```text
Go2My.Link/
├── 🎨 assets/BrandKit/          ← Logos and branding (see BRAND_GUIDELINES.md)
├── 🤖 .claude/                  ← Claude AI context (project brief, plans, memory)
├── 🤖 .openai/                  ← OpenAI context
├── ⚙️ .github/workflows/        ← CI/CD (PHP lint, release, SFTP deploy)
├── 📚 docs/                     ← ARCHITECTURE, DATABASE, API, DEPLOYMENT
├── 🌐 web/
│   ├── ⚙️ _functions/           ← Shared PHP functions (10 files)
│   ├── 📦 _includes/            ← Shared templates + email templates
│   ├── 📦 _libraries/           ← Local fallback libraries (Bootstrap, jQuery, FA, Chart.js)
│   ├── 📐 _schemas/             ← JSON Schema definitions (api, database, external)
│   ├── 🗄️ _sql/                 ← Schema, migrations, seeds, stored procedures
│   ├── 🏠 Go2My.Link/           ← Component A (Main Website + Admin Dashboard)
│   │   ├── public_html/         ← go2my.link web root
│   │   └── _admin/public_html/  ← admin.go2my.link web root
│   ├── 🔗 G2My.Link/            ← Component B (Redirect Engine)
│   │   └── public_html/         ← g2my.link web root
│   └── 📑 Lnks.page/            ← Component C (LinksPage)
│       └── public_html/         ← lnks.page web root
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

2. Copy the auth credentials template:

   ```bash
   cp web/_auth_keys/auth_creds.php web/_auth_keys/auth_creds.local.php
   ```

3. Edit `web/_auth_keys/auth_creds.local.php` with your database credentials.

4. Import the database schema from `web/_sql/schema/`.

5. Run the seed scripts from `web/_sql/seeds/`.

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
- 🐛 **Issues:** 78 issues tracked with phase labels (`phase-0` through `phase-11`)
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
