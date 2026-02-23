# GoToMyLink

A comprehensive URL shortening web service by **MWBM Partners Ltd** (trading as MWservices).

> Successor to the internal "MWlink" service — now expanded into a full-featured, multi-domain platform.

## 📋 Overview

GoToMyLink is a URL shortening platform comprising three interconnected web properties:

| Domain | Component | Purpose |
| --- | --- | --- |
| 🌐 [go2my.link](https://go2my.link) | 🏠 Main Website (A) | Public face, sign-up, short link creation |
| 🔧 [admin.go2my.link](https://admin.go2my.link) | 📊 Admin Dashboard (A) | User/org dashboard, link management, settings |
| 🔗 [g2my.link](https://g2my.link) | 🔀 Shortlink Domain (B) | Default domain for shortened URLs (redirect engine) |
| 📄 [lnks.page](https://lnks.page) | 📑 Links Page (C) | LinkTree-like customisable link listing pages |

## 🚀 Key Features

- 🔗 **URL Shortening** — Random or custom short codes, organisation custom domains, alias chains
- 🔀 **Advanced Redirects** — Scheduled, device-based, geo-based routing, age verification gates
- 👤 **User System** — Individual and organisation accounts, role-based access, SSO, social login, 2FA, PassKey
- 📡 **API** — RESTful API with JSON/XML responses, API key authentication
- 📊 **Analytics** — Click tracking, geographic maps, device/browser breakdown, data export
- 📑 **LinksPage** — Customisable link listing pages with template system and WYSIWYG editor
- 💰 **Payments** — Subscription tiers (Free/Basic/Premium/Enterprise), PayPal, Apple Pay, Google Pay, crypto
- ♿ **Accessibility** — WCAG 2.1 AA compliant, colour-blind mode, screen reader support
- 🌍 **i18n** — Translation-ready with interim Google/Bing Translate widget

## 🛠️ Tech Stack

- 🐘 **Backend:** PHP 8.4+ / 8.5+ (MySQLi, prepared statements)
- 🗄️ **Database:** MySQL 8.0+ (InnoDB, utf8mb4)
- 🎨 **Frontend:** HTML5, CSS3, Bootstrap 5, jQuery, Font Awesome 6
- 📈 **Charts:** Chart.js, Leaflet.js
- 🚢 **Hosting:** Dreamhost Shared Hosting (no CLI/Composer)

## 📁 Repository Structure

```
GoToMyLink/
├── 🎨 assets/
│   └── 🖼️ BrandKit/            ← Logo and branding assets (see BRAND_GUIDELINES.md)
├── 🤖 .claude/                 ← Claude AI context (project brief, plans, memory)
├── 🤖 .openai/                 ← OpenAI context
├── 🗄️ .sql/                    ← Database dumps (archived)
├── 📚 docs/                    ← Project documentation
│   ├── 🏗️ ARCHITECTURE.md
│   ├── 🗄️ DATABASE.md
│   ├── 📡 API.md
│   └── 🚢 DEPLOYMENT.md
├── 🌐 web/                     ← All web-deployable files
│   ├── 🔒 _auth_keys/          ← Server-wide credentials (outside web root)
│   ├── 📦 _includes/           ← Server-wide shared includes
│   ├── ⚙️ _functions/          ← Server-wide shared functions
│   ├── 📦 _libraries/          ← Server-wide shared libraries
│   ├── 📤 _uploads/            ← Server-wide uploads (not web-accessible)
│   ├── 💾 _backups/            ← Server-wide backups (not web-accessible)
│   ├── 🗄️ _sql/                ← Database schema, migrations, seeds, procedures
│   ├── 🏠 Go2My.Link/          ← Main Website (Component A)
│   ├── 🔗 G2My.Link/           ← Shortlink Domain (Component B)
│   └── 📑 Lnks.page/           ← Links Page (Component C)
├── 📝 CHANGELOG.md
├── 📊 PROJECT_STATUS.md
├── 🗒️ DEV_NOTES.md
└── 📋 README.md                ← This file
```

## ⚙️ Development

### 📌 Prerequisites

- 🐘 PHP 8.4+ or 8.5+
- 🗄️ MySQL 8.0+
- 🖥️ Visual Studio Code (recommended) with FTP Sync extension
- 🔀 Git

### 🏁 Getting Started

1. Clone the repository:
   ```bash
   git clone https://github.com/MWBMPartners/GoToMyLink.git
   ```

2. Copy the auth credentials template:
   ```bash
   cp web/_auth_keys/auth_creds.php web/_auth_keys/auth_creds.local.php
   ```

3. Edit `web/_auth_keys/auth_creds.local.php` with your database credentials.

4. Import the database schema from `web/_sql/schema/`.

5. Point your web server document roots to the appropriate `public_html/` directories.

### 🌿 Branch Strategy

- `main` — Production-ready code
- Feature branches for development, merged via pull request

## 📋 Project Management

- 📌 **GitHub Project:** [GoToMyLink Development](https://github.com/orgs/MWBMPartners/projects/4)
- 🐛 **Issues:** Tracked with phase labels (phase-3 Core Product through phase-10)
- 🏁 **Milestones:** v0.1.0 (Scaffold) through v1.0.0 (Launch)

### 🗺️ Phase Roadmap

| Phase | Name | Description |
| --- | --- | --- |
| Phase 3 | 🏠 Core Product | Redirect Engine + Main Website + Dark Mode |
| Phase 4 | 👤 User System | Auth & Basic Dashboard |
| Phase 5 | 🏢 Organisations | Organisations, Admin & Advanced Auth |
| Phase 6–10 | 🚀 Advanced Features | Analytics, API, Payments, LinksPage, Launch |

## ⚖️ License

Proprietary — MWBM Partners Ltd. All rights reserved.

## 🔗 Links

- 🏢 **Organisation:** [github.com/MWBMPartners](https://github.com/MWBMPartners)
- 📌 **Project Board:** [GoToMyLink Development](https://github.com/orgs/MWBMPartners/projects/4)
