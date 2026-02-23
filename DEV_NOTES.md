# Go2My.Link — Developer Notes

> Working notes, decisions, gotchas, and tips for the development team.

## 🖥️ Environment

- **Primary OS:** macOS (Windows occasionally)
- **IDE:** Visual Studio Code with FTP Sync extension
- **🚢 Hosting:** Dreamhost Shared Hosting
  - ❌ No CLI access (no Composer, no npm, no artisan)
  - 📦 Libraries must be manually downloaded and uploaded
  - 🌐 CDN-first with local fallback pattern for all third-party libraries
- **🖥️ PHP Version:** 8.5+ (with 8.4 backward compatibility via `version_compare()`)
- **🗄️ MySQL Version:** 8.0+

## 🏗️ Key Architecture Decisions

### ❌ No Composer

Dreamhost shared hosting doesn't provide CLI access. All third-party PHP libraries must be:

1. 📥 Downloaded manually
2. 📁 Placed in `web/_libraries/` (server-wide) or `web/{component}/_libraries/` (component-specific)
3. 📋 Included via `require_once` with existence checks

### 🗄️ MySQLi Only

PDO is not used. All database interactions go through MySQLi with prepared statements exclusively.
This is both a project requirement and a security measure against SQL injection.

### ⚙️ Settings in Database

All configuration (except DB connection credentials) is stored in `tblSettings` with a scope
hierarchy: User > Organisation > System > Default (from `tblSettingsDictionary`).

🔒 Sensitive settings are encrypted with AES-256-GCM using the SALT from `auth_creds.php`.

### 🔀 Clean URLs

No `.php` extensions visible to users. Achieved via:

- ⚙️ `.htaccess` RewriteRules (primary method)
- 📁 Directory-based routing (`/something/index.php` serves `/something`)

### 🐛 Error Handling Strategy

- 🔴 PHP errors → `tblErrorLog` (severity, code, title, detail, backtrace, request URL, headers)
- 📊 Activity logging → `tblActivityLog` (all request data, IP, UA, geo)
- 🐛 Debug mode → `?debug=true` URL parameter (restricted to admin/allowed IPs)
- 👤 User-facing errors → Graceful branded error pages

### 📡 QR Codes — External Service

> ⚠️ **Important:** QR code functionality is NOT part of this project. It will be a separate first-party service.

The `hasQRCodes` column in `tblSubscriptionTiers` is retained as a feature flag for future
integration with that external service.

### ⚙️ Admin Dashboard Subdomain

The admin dashboard (user dashboard, link management, settings) is served from
`web/Go2My.Link/_admin/public_html/` at `admin.go2my.link`. This is part of Component A
but separated from the public-facing website.

### 🔑 Authentication & Sessions (Phase 4)

All auth tokens (session, email verification, password reset) are stored as `hash('sha256', $plaintext)` in the database. The plaintext token is only ever in `$_SESSION` or in email links. This means a database leak does not compromise active tokens.

Sessions are dual-layered: PHP session + database-backed token in `tblUserSessions`. Every authenticated request validates the `$_SESSION['session_token']` against the DB hash. Sessions can be revoked remotely (the sessions management page at `/profile/sessions`).

Cross-subdomain session sharing uses cookie domain `.go2my.link` in production (set in `page_init.php`). This enables users to log in on go2my.link and access admin.go2my.link without re-authenticating.

### 📧 Email System

Emails are sent via PHP `mail()` using `g2ml_sendEmail()` with HTML templates in `web/_includes/email_templates/`. Template rendering uses output buffering with `extract($data)` for variable injection. Settings for From/Reply-To are in `tblSettings` (`email.from_address`, `email.from_name`, `email.reply_to`).

## 🚀 Release Process

Releases are managed via the **"🚀 Create Release"** GitHub Actions workflow (`.github/workflows/release.yml`). Each component can be released independently, allowing separate deployment cycles.

### 📋 How to Create a Release

1. Go to **Actions** → **"🚀 Create Release"** → **"Run workflow"**
2. Select the **component** to release:
   - `all — Full Platform` → tags as `v0.5.0`
   - `component-a — Main Website (go2my.link)` → tags as `component-a/v0.5.0`
   - `component-a-admin — Admin Dashboard (admin.go2my.link)` → tags as `component-a-admin/v0.5.0`
   - `component-b — Redirect Engine (g2my.link)` → tags as `component-b/v0.5.0`
   - `component-c — LinksPage (lnks.page)` → tags as `component-c/v0.5.0`
3. Enter the **version number** (e.g., `0.5.0` — no `v` prefix)
4. Optionally mark as **pre-release** and add **release notes**
5. Click **"Run workflow"**

### ⚙️ What the Workflow Does

1. **📥 Checkout** — Full git history for changelog generation
2. **🔍 Parse inputs** — Determines tag format, release name, and component path
3. **🔎 Tag check** — Verifies the tag doesn't already exist
4. **🔍 PHP Lint** — Validates PHP syntax in the component's directory before release
5. **📝 Release notes** — Auto-generates changelog from commits since last tag for that component
6. **🏷️ Create tag** — Creates annotated Git tag and pushes to origin
7. **📦 GitHub Release** — Creates a GitHub Release with the generated notes

### 🏷️ Tag Format Summary

| Scope | Tag Example | Component Path |
| --- | --- | --- |
| Full platform | `v0.5.0` | `web/` |
| Main Website | `component-a/v0.5.0` | `web/Go2My.Link/public_html/` |
| Admin Dashboard | `component-a-admin/v0.5.0` | `web/Go2My.Link/_admin/public_html/` |
| Redirect Engine | `component-b/v0.5.0` | `web/G2My.Link/public_html/` |
| LinksPage | `component-c/v0.5.0` | `web/Lnks.page/public_html/` |

> 💡 **Tip:** Concurrent releases are prevented — only one release can run at a time. The workflow uses `actions/checkout@v6` and runs PHP lint with `php-parallel-lint` on PHP 8.4.

## 📋 Issue Closure Protocol

Every time a GitHub issue is closed, the following must be done:

1. ✅ **Check all task boxes** — All `- [ ]` checkboxes in the issue body must be marked `- [x]`
2. 💬 **Add a closing comment** — Include links to the specific commit(s) and/or PR that completed the work
3. 📊 **Update project board** — Set the issue status to "Done" on the org-level project (#4)
4. 🏗️ **Update milestone** — Verify the milestone reflects the closure

### Comment Template

```markdown
## ✅ Retrospective Completion Notes

All tasks completed as part of Phase X work.

**Relevant commits:**
- [`abc1234`](https://github.com/MWBMPartners/Go2My.Link/commit/abc1234) — Commit message
```

## 📐 JSON Schema Validation

All JSON structures in the project have corresponding JSON Schema files (draft 2020-12) in `web/_schemas/`:

| Schema | File | Purpose |
| --- | --- | --- |
| API Create Response | `api/create-response.schema.json` | Success/error responses from POST /api/create/ |
| Activity Log Data | `database/activity-log-data.schema.json` | tblActivityLog.logData column |
| Error Log Headers | `database/error-log-headers.schema.json` | tblErrorLog.requestHeaders column |
| Settings Value | `database/settings-value.schema.json` | tblSettings JSON values |
| CAPTCHA Response | `external/captcha-response.schema.json` | Turnstile/reCAPTCHA siteverify response |

**Validator:** `web/_functions/json_validator.php` provides `g2ml_validateJSON($data, $schemaPath)` — pure PHP, no Composer.

**Rule:** All new JSON structures MUST have a corresponding schema file. Add schemas before or alongside the code that produces/consumes the JSON.

## 💡 Gotchas & Tips

### ⚠️ PHP 8.5 vs 8.4

Use `version_compare(PHP_VERSION, '8.5.0', '>=')` for features only available in 8.5.
Always provide a fallback for 8.4 compatibility.

### 🔑 Auth Credentials

The `auth_creds.php` files use `if (!defined('CONSTANT'))` guards. Per-component files
can override server-wide values by defining constants BEFORE including the server-wide file.

> 💡 **Tip:** Define component-specific constants BEFORE the `require_once` for the server-wide `auth_creds.php`.

### ⚙️ .htaccess

Each component's `public_html/` needs its own `.htaccess` for URL routing.
Dreamhost respects `.htaccess` files with `AllowOverride All`.

## 📏 Coding Standards Quick Reference

- ✅ Full `if/else` blocks (no shorthand)
- 📝 Detailed inline comments with official documentation links
- 🖥️ Use PHP predefined constants (`DIRECTORY_SEPARATOR`, `PHP_EOL`, etc.)
- 🌍 All UI strings use `__('key')` translation function
- ♿ All form fields have associated `<label>` elements (WCAG)
- 😊 Emojis are OK in code comments
