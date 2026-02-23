# Go2My.Link — Deployment Guide

> Deployment procedures, hosting configuration, and environment setup for the Go2My.Link platform.

## 🚢 Hosting Environment

| Property | Value |
| --- | --- |
| **🏢 Provider** | Dreamhost Shared Hosting |
| **🖥️ PHP version** | 8.4+ / 8.5+ |
| **🗄️ MySQL version** | 8.0+ |
| **❌ CLI access** | None (no Composer, npm, or artisan) |
| **📤 File upload** | FTP/SFTP via VS Code FTP Sync extension |
| **⚙️ .htaccess** | Supported (`AllowOverride All`) |

## 🌐 Domain Configuration

### 📡 DNS Records

| Domain | Type | Points to | Purpose |
| --- | --- | --- | --- |
| 🌐 `go2my.link` | A | Dreamhost IP | Main website (Component A) |
| ⚙️ `admin.go2my.link` | CNAME | go2my.link | Admin dashboard (Component A) |
| 🔗 `g2my.link` | A | Dreamhost IP | Shortlink redirect engine (Component B) |
| 📄 `lnks.page` | A | Dreamhost IP | LinksPage service (Component C) |

### 🏢 Custom Organisation Domains

Organisations can use custom short domains (e.g., `camsda.link`, `tyney.link`). These require:

1. 🌐 Organisation points their domain's A record to the Dreamhost IP
2. ✅ DNS TXT verification record: `_gotomylink-verify.{domain}` → organisation handle
3. ⚙️ Domain added in Dreamhost panel
4. 🗄️ Domain registered in `tblOrgDomains` with verification status

## 📁 Document Root Mapping

Each domain maps to a specific `public_html` directory:

| Domain | Document Root |
| --- | --- |
| 🌐 `go2my.link` | `web/Go2My.Link/public_html/` |
| ⚙️ `admin.go2my.link` | `web/Go2My.Link/_admin/public_html/` |
| 🟡 `alpha.go2my.link` | `web/Go2My.Link/public_html_dev_alpha/` |
| 🟠 `beta.go2my.link` | `web/Go2My.Link/public_html_dev_beta/` |
| 🔗 `g2my.link` | `web/G2My.Link/public_html/` |
| 📄 `lnks.page` | `web/Lnks.page/public_html/` |

## 📁 Directory Layout on Server

```
~/                              ← Dreamhost home directory
├── go2my.link/                 ← Mapped from web/Go2My.Link/public_html/
├── admin.go2my.link/           ← Mapped from web/Go2My.Link/_admin/public_html/
├── g2my.link/                  ← Mapped from web/G2My.Link/public_html/
├── lnks.page/                  ← Mapped from web/Lnks.page/public_html/
└── _shared/                    ← Mapped from web/ (shared includes, outside web root)
    ├── _auth_keys/
    ├── _includes/
    ├── _functions/
    ├── _libraries/
    └── _sql/
```

> 📝 **Note:** The exact server-side directory structure may differ. Paths in `auth_creds.php` use `dirname(__DIR__)` for portability.

## 🚀 Deployment Process

### 🛠️ Development Workflow

1. ✏️ **Edit locally** in VS Code
2. 📤 **Sync via FTP** using the FTP Sync extension (`ftp-sync.json` config, excluded from git)
3. 🧪 **Test on staging** (`alpha.go2my.link` or `beta.go2my.link`)
4. 🟢 **Promote to production** by syncing to `public_html/`

### 📋 Manual Deployment Steps

1. 📤 Upload changed files via SFTP to the appropriate `public_html/` directory
2. 🔒 Verify file permissions (644 for files, 755 for directories)
3. ✅ Test the deployed changes on the live site
4. 🗑️ Clear any server-side caches if applicable

### 🤖 GitHub Actions (Future)

Two GitHub Actions workflows are planned:

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| 🔍 `php-lint.yml` | Push to any branch | PHP syntax validation |
| 🚢 `sftp-deploy.yml` | Manual trigger | SFTP deployment (disabled initially) |

## 🔍 Environment Detection

The application detects its environment from the hostname:

| Hostname pattern | Environment | Debug mode |
| --- | --- | --- |
| 🟡 `alpha.*` | Development (Alpha) | ✅ Enabled |
| 🟠 `beta.*` | Development (Beta) | ✅ Enabled |
| 🖥️ `localhost` / `127.0.0.1` | Local development | ✅ Enabled |
| 🟢 Everything else | Production | ❌ Disabled (unless `?debug=true` with admin IP) |

## 🗄️ Database Setup

### 🆕 New Installation

1. 🗄️ Create the MySQL database: `mwtools_Go2MyLink`
2. 📋 Import schema files from `web/_sql/schema/` in order
3. 🌱 Import seed data from `web/_sql/seeds/`
4. 🔧 Import stored procedures from `web/_sql/procedures/`

### 🔄 Migration (from MWlink)

1. 🗄️ Create new database alongside existing `mwtools_mwlink`
2. 📋 Import new schema
3. ▶️ Run migration scripts from `web/_sql/migrations/` in order (1-6)
4. ✅ Verify all 480 URLs resolve correctly
5. ✅ Verify organisation domain mappings
6. 🔀 Switch application to new database
7. 🗑️ Decommission old database after verification period

## 🔑 Credentials Setup

1. 📋 Copy the template:
   ```
   web/_auth_keys/auth_creds.php → (edit with real credentials)
   ```
2. 🗄️ Set database credentials: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
3. 🔐 Set encryption salt: `ENCRYPTION_SALT` (generate a random 64-character hex string)
4. 🔑 Set third-party API keys as needed (reCAPTCHA, Turnstile, OAuth providers)

> 🔒 **Security:** `auth_creds.php` files are excluded from git via `.gitignore` and blocked from web access via `.htaccess`.

## 🔒 SSL / HTTPS

- ✅ All three domains use HTTPS (Let's Encrypt via Dreamhost)
- ✅ HTTPS is enforced via `.htaccess`:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- ✅ HSTS headers set in `.htaccess`

## 🛡️ Security Headers

Applied via `.htaccess` on all domains:

```apache
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"
```

Content Security Policy (CSP) is configured per-component to allow required CDN sources.

## 📊 Monitoring

- 🐛 **PHP errors:** Logged to `tblErrorLog` (custom error handler)
- 📊 **Activity:** Logged to `tblActivityLog` (all requests)
- 📋 **Server logs:** Dreamhost provides access/error logs via panel
- ⏱️ **Uptime:** External monitoring recommended (e.g., UptimeRobot)

## ⏪ Rollback Procedure

1. 🔍 Identify the issue and affected files
2. 🔄 Revert files via SFTP (restore from `_backups/` or git)
3. 🗄️ If database changes are involved, restore from backup
4. ✅ Verify rollback resolved the issue
5. 📝 Document the incident in `DEV_NOTES.md`

## ✅ Pre-Launch Checklist

- [ ] ✅ All 480 migrated URLs resolve correctly
- [ ] 🌐 Custom organisation domains working
- [ ] 🔒 HTTPS enforced on all domains
- [ ] 🛡️ Security headers in place
- [ ] 🔒 Private directories (`_auth_keys`, `_includes`, `_functions`) not web-accessible
- [ ] 🐛 Error logging to database working
- [ ] ❌ Debug mode disabled in production
- [ ] 🔒 `auth_creds.php` not accessible via browser
- [ ] 📋 `.gitignore` excludes all sensitive files
- [ ] 🌐 DNS cutover plan documented and tested
- [ ] ⏪ Old service rollback procedure documented

## 📚 Related Documentation

- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- 🗄️ [DATABASE.md](DATABASE.md) — Database schema reference
- 📡 [API.md](API.md) — API endpoint reference
