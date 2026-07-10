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

1. 🌐 Organisation points their domain's DNS at Go2My.Link (CNAME to `g2my.link`
   for a subdomain, or A/ALIAS to the Dreamhost IP for an apex/root domain)
2. ✅ DNS TXT verification record: `{org.dns_verify_prefix}.{domain}` → a
   **per-domain random verification token** (the code's actual behaviour is
   `_g2ml-verify.{domain}` by default — a prior draft of this doc said the
   value was the organisation handle; it is not, it is `verificationToken`,
   see `addOrgShortDomain()` / `verifyOrgShortDomain()` in
   `web/_functions/org.php`)
3. ⚙️ Domain added in the Dreamhost panel as a hosted domain (manual step
   today — see `docs/CUSTOM_DOMAINS.md` for the full walkthrough and the
   planned Cloudflare-for-SaaS automated path)
4. 🗄️ Domain registered in `tblOrgShortDomains` (short-URL routing domains) —
   **only a `verified` + active short domain is routable** (#91); an
   unverified or unknown Host never falls back to the `[default]` org's
   namespace. (`tblOrgDomains` is a separate, ⚠️ **DEPRECATED (GT-6)** table
   — see `docs/DATABASE.md` — nothing routes off it; do not register a
   domain there expecting it to become routable.)

📖 **Full partner-facing guide:** [`docs/CUSTOM_DOMAINS.md`](CUSTOM_DOMAINS.md).

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

Deployment is automated by **`.github/workflows/sftp-deploy.yml`** (lftp mirror over
SFTP to Dreamhost). Manual VS Code FTP-Sync is no longer the deployment path.

### 🛠️ Development Workflow

1. ✏️ **Edit locally** and commit to a feature branch
2. 🔀 **Merge to `alpha`** → deploys to the `public_html_dev_alpha` channel web roots
3. 🧪 **Test on staging** (`alpha.go2my.link` / `beta.go2my.link`)
4. 🟢 **Promote to `main`** → deploys to the production `public_html` web roots

### 🤖 CI/CD workflows

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| 🔍 `ci.yml` | Push to `main`/`alpha`/`beta`, all PRs | Frontend + Backend gates (PHPStan, PHPCS) |
| 🔍 `php-lint.yml` | Push / PR | PHP syntax validation |
| 🧹 `lint.yml` | Changes under `.github/workflows/**` | `actionlint` workflow linting |
| 🚢 `sftp-deploy.yml` | Push to `main`/`alpha`/`beta` touching `web/**`; manual dispatch | SFTP deployment (**gated by `vars.SFTP_ENABLED`**) |
| 🏷️ `release.yml` | Tag push | Per-component releases |

### 🔀 How the mirror maps repo → server

Two phases run against a single remote root (`secrets.SFTP_BASE_PATH`):

| Phase | Source | Destination | `--delete`? |
| --- | --- | --- | --- |
| **1** | `web/<Comp>/public_html/` | `<Comp>/public_html[_dev_alpha\|_dev_beta]/` per branch | ✅ **Yes** — these roots are wholly repo-owned |
| **2** | everything else under `web/` | `SFTP_BASE_PATH/` | ❌ **No** — additive only |

> ⚠️ Phase 2 is **deliberately additive**. See [#158](https://github.com/MWBMPartners/Go2My.Link/issues/158):
> `SFTP_BASE_PATH` also hosts unrelated live content, and repo directories that
> contain only `.gitkeep` read as *empty* to lftp (because `.gitkeep` is excluded
> and `mirror:no-empty-dirs` is set), so `--delete` there prunes live server
> state — including the per-component `_auth_keys/` directories, which would
> cause an immediate total outage. **Do not re-add `--delete` to Phase 2.**

### 🔐 Arming and running a deploy

The pipeline ships **disarmed**. `vars.SFTP_ENABLED` must be `'true'` for the
deploy job to run at all (the pre-deploy PHP/JS/JSON checks always run).

**Mandatory procedure — never skip the dry run:**

**1. 🔎 Dry run first.** Actions → *SFTP Deploy* → *Run workflow* → pick the
branch → leave `dry_run` = **true** (the default). Or:

```bash
gh workflow run sftp-deploy.yml --ref alpha -f dry_run=true
```

The job is skipped unless `SFTP_ENABLED` is `'true'`, so arm it first if needed:

```bash
gh api -X PATCH repos/MWBMPartners/Go2My.Link/actions/variables/SFTP_ENABLED \
  -f name=SFTP_ENABLED -f value=true
```

**2. 📋 Read the removal list.** Pull the log and inspect every deletion:

```bash
gh run view <run-id> --log | grep -oE '(rm -r|rm) "[^"]+"'
```

✋ **Every removal must be understood and intended.** If anything on that list
is server-owned or unfamiliar, **stop** and fix the excludes before arming.

**3. 🚀 Deploy for real** — re-run the dispatch with `dry_run` = `false`, or push
to `main`/`alpha`/`beta`.

**4. 🔒 Disarm again** if you do not want subsequent pushes auto-deploying:

```bash
gh api -X PATCH repos/MWBMPartners/Go2My.Link/actions/variables/SFTP_ENABLED \
  -f name=SFTP_ENABLED -f value=false
```

### 🛡️ Paths the mirror never touches

Excluded from **every** phase, so a deploy can never destroy live state:

| Path | Why |
| --- | --- |
| `_auth_keys/` | Installer-written DB credentials + the per-component thin includes |
| `private_html/` | Dreamhost private area |
| `_uploads/` | User uploads |
| `_backups/` | Server backups |
| `.dh-diag` | Dreamhost-generated diagnostics |
| `.git/`, `.github/`, `.vscode/`, `.idea/` | VCS / tooling metadata |
| `README.md`, `DEV_NOTES.md`, `CLAUDE.md`, `.gitignore`, `.gitkeep`, `.gitattributes` | Repo docs and markers |

> 🧪 The exclude patterns are **single-quoted** in the workflow env blocks. This is
> load-bearing: lftp tokenises the generated command script with its own lexer and
> treats an unquoted `|` as a pipe operator, which silently corrupts any
> `(^|/)…` regex. See [#156](https://github.com/MWBMPartners/Go2My.Link/issues/156).

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

> 📋 **See [MIGRATION_PLAN.md](MIGRATION_PLAN.md) for the comprehensive step-by-step migration execution plan**, including pre-migration checklist, DNS cutover, rollback procedures, and post-launch monitoring.

**Summary of migration steps:**

1. 🗄️ Create new database alongside existing `mwtools_mwlink`
2. 📋 Import schema files in order (`000` through `035`)
3. 🔧 Import stored procedures (`sp_lookupShortURL`, `sp_logActivity`, `sp_generateShortCode`)
4. 🌱 Import seed data in order (`001` through `010`)
5. 🧪 Run dry-run verification: `web/_sql/dry_run.sql`
6. ▶️ Run migration scripts from `web/_sql/migrations/` in order (`001` through `006`)
7. ⏳ Optional: Run activity log migration (`007`) in batches
8. ✅ Verify all 480 URLs resolve correctly
9. ✅ Verify organisation domain mappings
10. 🔀 Switch application to new database
11. 🗑️ Decommission old database after 30-day verification period

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
- [ ] 🔒 Private directories (`_auth_keys`, `.auth`, `_includes`, `_functions`) not web-accessible
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
- 🚀 [MIGRATION_PLAN.md](MIGRATION_PLAN.md) — Migration execution plan & launch checklist
- 🌍 [TRANSLATION.md](TRANSLATION.md) — Translation guide
- ♿ [ACCESSIBILITY.md](ACCESSIBILITY.md) — Accessibility standards
