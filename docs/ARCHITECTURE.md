# Go2My.Link — Architecture

> Technical architecture overview for the Go2My.Link platform.

## 📋 System Overview

Go2My.Link is a URL shortening platform comprising three interconnected web properties, sharing a common PHP backend and MySQL database.

### 🧩 Components

| Component | Domain | Role |
| --- | --- | --- |
| **A — 🌐 Main Website** | [go2my.link](https://go2my.link) | Public face, short link creation, API |
| **A — ⚙️ Admin Dashboard** | [admin.go2my.link](https://admin.go2my.link) | User/org dashboard, link management, settings |
| **B — 🔗 Shortlink Domain** | [g2my.link](https://g2my.link) | Redirect engine for shortened URLs |
| **C — 📄 LinksPage** | [lnks.page](https://lnks.page) | Customisable LinkTree-like link listing pages |

### 🗺️ Component Dependency Graph

```
           ┌──────────────────────┐
           │   Shared Layer       │
           │  (_auth_keys,        │
           │   _includes,         │
           │   _functions,        │
           │   _libraries)        │
           └──────┬───────────────┘
                  │
      ┌───────────┼───────────────┐
      │           │               │
      ▼           ▼               ▼
 ┌─────────┐ ┌─────────┐  ┌──────────┐
 │  Comp A  │ │  Comp B  │  │  Comp C  │
 │go2my.link│ │g2my.link │  │lnks.page │
 └─────────┘ └─────────┘  └──────────┘
```

All three components include the shared layer via PHP `require_once` with portable paths using `dirname(__DIR__)` and `DIRECTORY_SEPARATOR`.

## 🚢 Hosting Environment

- **Provider:** Dreamhost Shared Hosting
- **Constraints:**
  - ❌ No CLI access (no Composer, no npm, no artisan)
  - 📦 Libraries must be manually uploaded
  - 🌐 CDN-first with local fallback for all third-party assets
  - ⚙️ `.htaccess` with `AllowOverride All` supported
- **Document roots:** Each component has multiple web roots for staging:
  - 🟢 `public_html/` — Production
  - 🟡 `public_html_dev_alpha/` — Alpha development
  - 🟠 `public_html_dev_beta/` — Beta development
  - 📄 `public_html_landing/` — Pre-launch landing page
  - 🔀 `public_html_redir/` — Redirection (limited use)

## 📁 Directory Structure

```
web/
├── _auth_keys/          ← Server-wide credentials (outside web root)
├── _includes/           ← Server-wide shared includes (headers, footers, nav)
├── _functions/          ← Server-wide shared functions (DB, security, i18n)
├── _libraries/          ← Server-wide shared libraries (Bootstrap, jQuery, etc.)
├── _uploads/            ← Server-wide uploads (not web-accessible)
├── _backups/            ← Server-wide backups (not web-accessible)
├── _sql/                ← Database schema, migrations, seeds, procedures
│   ├── schema/          ← Table definitions
│   ├── migrations/      ← Data migration scripts
│   ├── seeds/           ← Default/sample data
│   └── procedures/      ← Stored procedures
├── Go2My.Link/          ← Component A
│   ├── _admin/          ← Admin/Dashboard application
│   │   └── public_html/ ← Admin web root (admin.go2my.link)
│   ├── _auth_keys/      ← Component-specific credential overrides
│   ├── _includes/       ← Component-specific includes
│   ├── _functions/      ← Component-specific functions
│   ├── _libraries/      ← Component-specific libraries
│   ├── public_html/     ← Production web root (go2my.link)
│   └── ...              ← Other web roots
├── G2My.Link/           ← Component B (same sub-structure)
└── Lnks.page/           ← Component C (same sub-structure)
```

### 🔒 Private Directories (Underscore Prefix)

Directories prefixed with `_` are not web-accessible. They are blocked via `.htaccess` rules:

```apache
RewriteRule ^_auth_keys/ - [F,L]
RewriteRule ^_includes/ - [F,L]
RewriteRule ^_functions/ - [F,L]
RewriteRule ^_libraries/ - [F,L]
```

## 🛠️ Tech Stack

| Layer | Technology | Notes |
| --- | --- | --- |
| 🖥️ Backend | PHP 8.4+ / 8.5+ | No framework; custom modular architecture |
| 🗄️ Database | MySQL 8.0+ (InnoDB, utf8mb4) | MySQLi only (no PDO) |
| 🎨 Frontend | HTML5, CSS3, Bootstrap 5.3 | Responsive, WCAG 2.1 AA compliant |
| 📜 JavaScript | jQuery 3.7, Chart.js, Leaflet.js | AJAX with graceful no-JS fallback |
| 🎭 Icons | Font Awesome 6 | CDN with local fallback |
| 🚢 Hosting | Dreamhost Shared Hosting | No CLI/Composer |

## 🔀 Request Flow

### 🔗 URL Shortening (Component A)

```
User → go2my.link → page_init.php → router.php → controller → response
                        │
                        ├── Session start
                        ├── DB connection (db_connect.php)
                        ├── Settings load (settings.php)
                        ├── Error handler registration
                        └── Environment detection
```

### ↪️ Short URL Redirect (Component B)

```
User → g2my.link/{code} → .htaccess → index.php?code={code}
                                          │
                                          ├── Extract short code
                                          ├── Determine org from domain
                                          ├── Resolve short code (alias chains, max 3 hops)
                                          ├── Check date-range validity + isActive
                                          ├── Optional: Validate destination URL
                                          ├── Log activity
                                          └── 302 Redirect (or error page)
```

### 📄 LinksPage (Component C)

```
User → lnks.page/{slug} → .htaccess → index.php?slug={slug}
                                          │
                                          ├── Resolve slug to user/org
                                          ├── Load template
                                          ├── Render links page
                                          └── Log activity
```

## 🔒 Security Architecture

### 🔑 Authentication

- **Password hashing:** Argon2id (bcrypt fallback for PHP 8.4)
- **Session management:** PHP native sessions with secure configuration
- **2FA:** TOTP with QR provisioning + recovery codes
- **PassKey:** WebAuthn registration/authentication
- **Social login:** OAuth 2.0 (Microsoft, Apple, Google, Facebook, Yahoo, Amazon)
- **SSO:** MS365 (Azure AD), Google Workspace, WordPress

> 📝 **Note:** Authentication is implemented in Phase 4: User System — Auth & Dashboard.

### 🏷️ Account Types & Role Hierarchy

Users can hold **multiple account types** simultaneously via the `tblUserAccountTypes` junction table (org-scoped). The `tblUsers.role` ENUM column is retained as a cached "effective role" (highest privilege), kept in sync by `syncEffectiveRole()`. This ensures the existing `hasMinimumRole()` hierarchy (Anonymous < User < Admin < GlobalAdmin) works without modification. See `web/_functions/account_types.php` for the full API.

### 🔐 Encryption

- **Algorithm:** AES-256-GCM
- **Key management:** ENCRYPTION_SALT in `auth_creds.php` (outside web root)
- **Usage:** Sensitive database values (`isSensitive` flag in settings dictionary)

### 🛡️ Input Security

- ✅ CSRF tokens on all forms
- ✅ Prepared statements for all SQL queries (MySQLi)
- ✅ Input sanitisation functions
- ✅ Content Security Policy (CSP) headers
- ✅ HTTPS enforcement via `.htaccess`

### 🔧 Credential Override Pattern

```php
// Per-component auth_creds.php can override server-wide values:
define('DB_NAME', 'component_specific_db');  // Override BEFORE include

// Server-wide auth_creds.php uses guards:
if (!defined('DB_NAME')) {
    define('DB_NAME', 'default_database');
}
```

## ♿ Accessibility (Cross-Cutting)

WCAG 2.1 AA compliance is a foundational requirement from Phase 2 onwards:

- ✅ Semantic HTML5 elements
- ✅ ARIA landmarks on all layout sections
- ✅ Skip-to-content link on every page
- ✅ Keyboard navigation with visible focus indicators
- ✅ Colour contrast: 4.5:1 (normal text), 3:1 (large text)
- ✅ Colour-blind mode toggle
- ✅ `prefers-reduced-motion` and `prefers-color-scheme` support
- ✅ Screen reader compatible (ARIA live regions for dynamic content)

## 🌍 i18n / Translation (Cross-Cutting)

- ✅ All UI strings use `__('key')` translation function
- ✅ English (en-GB) as base language
- ✅ RTL language support
- ⏳ Interim: Google/Bing/AI translation widget
- 🔜 Formal translations in Phase 10

## 🐛 Error Handling

| Error Type | Destination | Method |
| --- | --- | --- |
| 🔴 PHP errors/exceptions | `tblErrorLog` | Custom error handler |
| 📊 Activity/requests | `tblActivityLog` | Activity logger |
| 🐛 Debug info | Browser (admin only) | `?debug=true` parameter |
| 👤 User-facing errors | Branded error pages | Graceful fallback |

## 📚 Related Documentation

- 🗄️ [DATABASE.md](DATABASE.md) — Database schema and migration details
- 📡 [API.md](API.md) — API endpoint reference
- 🚢 [DEPLOYMENT.md](DEPLOYMENT.md) — Deployment and hosting guide
