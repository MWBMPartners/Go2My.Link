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

### 🔤 Database collation (required)

The database, every table, and every text column must use character set `utf8mb4`
with collation **`utf8mb4_unicode_ci`**. This is not a style preference: a
stored procedure created from files older than #196 can fail short-link
creation when the database's default is a different utf8mb4 collation
(see below).

**What a collation is, in plain words:** it is the rule a database uses to
compare and sort text — whether `"a"` equals `"A"`, whether an accented letter
sorts next to its plain form, and so on. Comparing two pieces of text that
carry different collations can be an error: when MySQL cannot tell which
collation should win, it refuses the comparison.

**Why it matters here (#196, #197).** A stored procedure's own local
variables take their collation from the *database's* default, not from
whichever table column they end up compared against. `sp_generateShortCode`
and `sp_lookupShortURL` both compare a variable (a candidate short code, an
org handle, a domain) against a table column. If the database's default is
a different utf8mb4 collation, such as `utf8mb4_0900_ai_ci` — which usually
happens when a hosting panel creates the database itself, using the
server's own default, rather than our
`web/_sql/schema/000_create_database.sql` — that comparison fails with
"illegal mix of collations". Both procedures' own error handlers swallow
that error, so the visible symptom is every attempt to create a link with a
generated short code failing (a custom alias is not affected — it is
inserted directly and never calls the procedure) with "Failed to generate a
unique short code. Please try again." — and, on a database that already
holds links, every redirect lookup failing the same way, with nothing in
any log explaining why. Both procedures now also convert the variable to
utf8mb4 and state `COLLATE utf8mb4_unicode_ci` explicitly on every such
comparison, as a second, independent safeguard — but the database itself
should still be right; see their own file headers.

**The check:**

```sql
SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'mwtools_Go2MyLink';
```

It must return `utf8mb4_unicode_ci`.

**Fixing a wrong database:** run `web/_sql/migrations/042_database_collation.sql`,
or make the same change in the hosting panel:

```sql
ALTER DATABASE `mwtools_Go2MyLink`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Either way, then **re-import both stored procedure files** (a procedure keeps
the collation that was current when it was created, not the database's
current one): `web/_sql/procedures/sp_generateShortCode.sql` and
`web/_sql/procedures/sp_lookupShortURL.sql`.

**What now enforces this:** `.github/workflows/ci.yml`'s integration job
fails the build if the imported database's collation is wrong; the web
installer (`web/Go2My.Link/public_html/install/index.php`) checks the
collation after connecting, corrects it where the database user is allowed
to, and otherwise refuses to move on to importing anything. Neither of those
helps the production database on its own — see `PRE_LAUNCH_CHECKLIST.md`,
item A8, for the manual check before the first import and again before
launch.

**Every new `CREATE TABLE`** must state
`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` explicitly,
the same as every existing table in `web/_sql/schema/`.

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

### 📧 Email System (Multipart MIME)

Emails are sent via PHP `mail()` using `g2ml_sendEmail()` in `web/_functions/email.php`. The system produces **RFC 2046 multipart/alternative** emails with up to three MIME parts:

1. **text/plain** — Auto-generated from HTML via `g2ml_htmlToPlainText()` (headings → UPPERCASE, links → `text [url]`, lists → `- item`, tables → tab-separated)
2. **text/x-amp-html** — AMP for Email variant (if template exists in `email_templates/amp/` and `email.amp_enabled` is on)
3. **text/html** — Full HTML template with dark mode CSS and preheader text

**Template structure:**

- HTML templates: `web/_includes/email_templates/{template}.php` — output buffered with `extract($data)`
- AMP templates: `web/_includes/email_templates/amp/{template}.php` — must be valid AMP4Email (`<html ⚡4email>`, max 75KB, no external CSS, no inline `<style>` in body)
- Dark mode: `@media (prefers-color-scheme: dark)` in `<style>` block with `g2ml-*` class selectors

**Modern headers added automatically:**

- `List-Unsubscribe` / `List-Unsubscribe-Post` — one-click unsubscribe (RFC 8058)
- `X-Entity-Ref-ID` — unique per email for threading prevention
- `Precedence: bulk` — signals bulk mail to receiving servers
- `Auto-Submitted: auto-generated` — identifies automated email (RFC 3834)

**Security hardening:**

- CRLF injection prevention on all header values (`$to`, `$subject`, From/Reply-To, extra headers)
- Template name validated with `^[a-zA-Z0-9_-]+$` (prevents path traversal)
- Blocked headers list prevents `$extraHeaders` from overriding `From`, `To`, `Cc`, `Bcc`, `Content-Type`, etc.
- `@mail()` suppression removed for proper error visibility

**Settings** (in `tblSettings` via `012_email_settings.sql`):

- `email.from_address`, `email.from_name`, `email.reply_to` — sender configuration
- `email.amp_enabled` (boolean) — include AMP MIME part
- `email.plaintext_fallback` (boolean) — include text/plain part
- `email.list_unsubscribe_url` — List-Unsubscribe header URL
- `email.preheader_enabled` (boolean) — include preheader text in HTML templates

### 🚨 Breach Response (Mass Credential Reset)

`web/_functions/breach_response.php` provides a GlobalAdmin-only emergency system for security incidents. The `g2ml_breachResponse()` function orchestrates the full process:

**Execution flow:**

1. Validate caller is GlobalAdmin + check cooldown (configurable via `security.breach_response_cooldown`)
2. Set cooldown timestamp **immediately** (prevents TOCTOU race condition with concurrent requests)
3. Invalidate ALL passwords — `UPDATE tblUsers SET passwordHash = '[INVALIDATED]', forcePasswordReset = 1` (includes inactive users)
4. Revoke ALL sessions — `UPDATE tblUserSessions SET isActive = 0`
5. Send batch notification emails (50 users per batch) — each user gets an individual reset token
6. Optionally rotate ENCRYPTION_SALT — re-encrypts all `isSensitive = 1` settings in a database transaction
7. Log completion with summary stats to audit file + activity log

**Security measures:**

- Transaction wrapping for salt rotation (rollback on any failure prevents mixed-key state)
- Plaintext memory clearing after re-encryption (`str_repeat("\0", strlen($plaintext))` + `unset()`)
- Control character stripping from reason (`[\x00-\x1F\x7F]`)
- UTC timestamps throughout (`gmdate()`)
- `set_time_limit(3600)` (bounded, not unlimited)
- Token storage verified before email dispatch (skips user on failure)
- Audit logging on disabled/cooldown rejection paths (not just success)

**Login flow integration:**

After `loginUser()` verifies the password, it checks `forcePasswordReset`. If set, it generates a reset token, stores it in `$_SESSION['forced_reset_token']` (NOT in URL — prevents Referer/log leakage), and returns `forcePasswordReset => true`. The login page redirects to `/reset-password?forced=1`, where the reset-password page reads the token from `$_SESSION` (one-time use — immediately `unset()`).

**Admin page:** `web/Go2My.Link/_admin/public_html/pages/security/breach-response.php` — no-cache headers, reason validation (`maxlength="500"` + `g2ml_sanitiseInput()`), confirmation checkbox, CSRF protection.

### 🏷️ Account Types (Multi-Type Support)

Users can hold **multiple account types** simultaneously via the `tblUserAccountTypes` junction table. This replaces the single-role ENUM model while maintaining full backward compatibility.

**Architecture:**

- `tblAccountTypes` — reference table defining available types (4 system types + custom)
- `tblUserAccountTypes` — junction table (user ↔ type, org-scoped)
- `tblUsers.role` — retained as a cached "effective role" = highest-privilege type held
- `syncEffectiveRole()` — automatically keeps `tblUsers.role` in sync after any type change

**Session storage:**

- `$_SESSION['user_role']` — effective role string (unchanged, backward compatible)
- `$_SESSION['user_account_types']` — array of all active type assignments (new)

**Key functions** in `web/_functions/account_types.php`:

- `getUserAccountTypes($userUID, $orgHandle)` — get all active types
- `hasAccountType($userUID, $accountTypeID, $orgHandle)` — check specific type
- `assignAccountType($userUID, $typeID, $orgHandle, $grantedBy)` — assign + sync
- `revokeAccountType($userUID, $typeID, $orgHandle)` — revoke + sync
- `syncEffectiveRole($userUID)` — recalculate tblUsers.role from junction table

**Why keep tblUsers.role?** The `hasMinimumRole()` function is called on every authenticated request. Reading a single ENUM column is faster than JOINing the junction table on every page load. The junction table is queried only on login and when types change.

### 🏢 Organisation Management (Phase 5)

Organisations are the multi-tenancy layer. Each user belongs to ONE org via `tblUsers.orgHandle`. New users default to the `[default]` org. Creating an org moves the user out of `[default]` and promotes them to Admin.

**Core function file:** `web/_functions/org.php` (18+ functions). Key permission check is `canManageOrg($orgHandle)` — returns true if the user is Admin of that specific org OR a GlobalAdmin.

**Invitation flow:** Admin sends invite → `inviteMember()` generates a 32-byte token (SHA-256 hash stored in `tblOrgInvitations`, plaintext in email link) → invitee clicks accept link → `/invite?token=...` validates token, checks user is in `[default]` org → `acceptInvitation()` moves user to org with assigned role. Invitations expire after 7 days (configurable via `org.invitation_expiry` setting).

**Custom domains:** Orgs can add custom domains verified via DNS TXT records. `verifyDomain()` uses PHP's `dns_get_record()` to look up `_g2ml-verify.{domain}` for the verification token. No external dependencies.

**Short domains:** Orgs can configure multiple short URL domains. One is marked as default (`isDefault`). The default cannot be removed without first designating another.

**Dashboard pages:** All under `web/Go2My.Link/_admin/public_html/pages/org/` — overview, create, settings, members, members/invite, domains, short-domains.

### ⚖️ Compliance & Privacy (Phase 6)

**DNT/GPC Detection:** `web/_functions/dnt.php` checks both `HTTP_DNT` and `HTTP_SEC_GPC` headers. `g2ml_shouldTrack()` combines DNT detection with the `analytics.respect_dnt` and `compliance.always_assume_dnt` settings. When tracking is disabled, `activity_logger.php` skips non-critical logging but ALWAYS logs security events (`login_failed`, `csrf_failure`, `rate_limited`, `consent_recorded`, etc.).

**Cookie Consent:** `web/_functions/cookie_consent.php` implements jurisdiction-aware consent. `g2ml_detectJurisdiction()` maps Accept-Language headers to jurisdictions. EU/UK/BR/KR/JP require opt-in (explicit consent before non-essential cookies); US/CA/AU default to opt-out model. Consent records go to `tblConsentRecords` with full audit trail (IP, user agent, method, consent version).

**Data Subject Rights:** `web/_functions/data_rights.php` provides GDPR Article 15-22 compliance. Data exports collect from `tblUsers`, `tblShortURLs`, `tblConsentRecords`, `tblUserSessions` → JSON file at `_uploads/exports/`. Deletion requests have a configurable grace period (default 30 days via `compliance.data_deletion_grace_days`). Anonymisation replaces PII with `[DELETED]` / `deleted_{uid}@anonymised.invalid` patterns in a transaction.

**Legal Documents:** All 5 legal pages use structured PHP templates with `{{LEGAL_REVIEW_NEEDED}}` placeholders in `alert alert-warning` blocks. Each has a TOC with jump links, version badge from settings, and last-updated date. Ready for professional legal review before launch.

**CSP Headers:** Content-Security-Policy is set in `.htaccess` files. Component B (redirect engine) has a very tight policy (`default-src 'none'`). Components A/Admin/C allow CDN sources for Bootstrap, jQuery, and Font Awesome. `'unsafe-inline'` is required for the FOUC-prevention inline script in `header.php`.

### 📱 Progressive Web App (Phase 6)

PWA support is provided via `manifest.json` + `sw.js` for Components A, Admin, and C (not B — the redirect engine has no user-facing UI). Each component has its own manifest with appropriate `start_url`, `scope`, `theme_color`, and `background_color`. Service workers provide a minimal offline fallback — caching the offline page and returning it when the network is unavailable.

**App Icons:** 192×192 and 512×512 PNG icons in each component's `/icons/` directory. Linked via `<link rel="manifest">` in `header.php`.

### ♿ WCAG 2.1 AA Audit (Phase 6)

A comprehensive accessibility audit was performed across all components. Key fixes applied to 23+ files:

- **Semantic Landmarks:** Replaced `<div role="main">` with `<main>` on Component B error pages (404, expired, validating)
- **Heading Hierarchy:** Fixed h1→h3 skips in dashboard (stat cards changed to `<p>`), legal ToC headings standardised to `<h2>`
- **Colour Contrast:** Fixed `btn-outline-warning` (1.56:1 ratio) → `btn-warning text-dark` (7.1:1), added `text-dark` to `badge bg-info`
- **ARIA Labels:** Added `aria-label` with context to all icon-only buttons (edit, delete, remove), copy buttons, and read-only fields
- **Table Accessibility:** Added `scope="col"` to all `<th>` elements and `aria-label` to `<table>` elements
- **Form Accessibility:** Fixed `for`/`id` associations on read-only fields, added `aria-required="true"`, fixed asterisk markup to use `aria-hidden="true"` + visually-hidden "(required)"
- **`formField()` Textarea Fix:** Refactored the accessibility helper to correctly render `<textarea>` elements (was generating invalid `<input type="textarea">`)
- **Noscript Links:** Replaced "Click here" with descriptive link text in Component B fallback pages

### 🌍 Translation System (Phase 6)

The en-GB baseline translation seed contains ~1,075 keys in `web/_sql/seeds/010_phase6_translations.sql`. Keys follow dot-notation (`page.section_element`). 10 languages are registered in `tblLanguages` but only `en-GB` is active. The 9 additional locales are deferred to post-launch — the interim Google Translate widget provides machine translation coverage. See `docs/TRANSLATION.md` for the full guide.

#### 🔄 Locale Resolution & Language-Family Fallback

The i18n system (`web/_functions/i18n.php`) uses a **language-family fallback** strategy via `_g2ml_resolveLocale()`. This means users can request a base language code (e.g., `?lang=en`) and receive the best available regional variant, without needing every locale to have its own complete translation set.

**Locale resolution order** (applied to URL params, session, cookie, Accept-Language header):

1. **Exact match** — `en-GB` → `en-GB` (active locale found directly)
2. **Base language → regional** — `en` → `en-GB` (no exact `en`, but `en-GB` is an active `en-*` variant)
3. **Inactive regional → sibling** — `en-US` (inactive) → `en-GB` (first active `en-*` variant; prefers the site default if it shares the same base language)

**Translation lookup fallback** (`__()` function):

1. **Current locale** — e.g., look up key in `en-US` translations
2. **Language-family sibling** — e.g., try `en-GB` translations (shares the `en` base)
3. **Default locale** — `en-GB` (site-wide fallback)
4. **Key itself** — returns the dot-notation key (makes missing translations visible in the UI)

**Practical effect:** When adding a new regional variant (e.g., `en-US`), you only need to seed the strings that differ from the existing variant (e.g., "colour" → "color"). All other keys automatically fall through to the family sibling. This applies to all language families, not just English:

| Request | Resolves To | Reason |
| --- | --- | --- |
| `en-GB` | `en-GB` | Exact match |
| `en` | `en-GB` | Base language → first active `en-*` |
| `en-US` (inactive) | `en-GB` | Sibling fallback (default preferred) |
| `pt` | `pt-BR` | Base language → first active `pt-*` |
| `zh` | `zh-CN` | Base language → first active `zh-*` |
| `fr` (inactive) | `null` → site default | No active `fr-*` variant yet |

### 🔒 Pre-Release Audit (Phase 6)

Two comprehensive security audits have been performed:

**Audit 1 (Pre-Release, v1.0.0-rc):** Six parallel agents covered W3C/HTML5 standards, semantic landmarks, ARIA/forms, keyboard/focus, colour contrast, and OWASP security. **20 files modified, 56 insertions, 40 deletions.**

**Audit 2 (Post-Email/Breach, commit `1ab9a50`):** Four parallel agents covered breach_response.php (20 findings: 1 CRITICAL, 3 HIGH), email.php (14 findings: 3 HIGH), admin pages + auth.php (19 findings: 3 HIGH), email templates + SQL seed (9 findings). **25 files modified** with 16 actionable fixes applied.

**Security fixes:**

- `innerHTML` → `textContent` on copy button in admin link creation (DOM-based XSS prevention)
- Regex validation (`/^@[a-zA-Z_][a-zA-Z0-9_]*$/`) for OUT parameter names in `dbCallProcedure()` (SQL injection prevention)
- Same-origin referer allowlist on consent API redirect (open redirect prevention)
- SRI `integrity` hash added to Bootstrap RTL CSS CDN link (was missing on the dynamic include)
- `noreferrer` added to all `target="_blank"` external links across 7 files

**Accessibility refinements:**

- `aria-hidden="true"` on navbar toggler icon (decorative element)
- `aria-live="assertive"` on Component B expired page countdown (time-sensitive notification)
- Debug panel row number contrast (#666 → #999, 5.16:1 ratio)
- Footer link hover/focus state with `var(--bs-light)` + underline (WCAG 1.4.1)
- Bootstrap `text-muted` → `text-body-secondary` migration in footer

**Email template fixes:**

- Footer text colour changed from `#6c757d` to `#5a6268` across all 7 email templates (4.58:1 → 5.74:1 contrast on `#f8f9fa` background)

**Post-launch recommendations** (non-blocking):

- Nonce-based CSP to replace `'unsafe-inline'` for inline scripts
- Replace browser `confirm()` dialogs with Bootstrap modals
- Session cleanup probability tuning under production load
- Professional legal review of all 5 legal documents

**Audit 2 — Key fixes (email + breach response):**

- 🔒 **CRITICAL:** Salt rotation transaction wrapping (prevents irrecoverable mixed-key encryption)
- 🔒 **HIGH:** CRLF header injection (email recipient, subject, DB-sourced values, extra headers)
- 🔒 **HIGH:** Path traversal in template loading (regex validation on template names)
- 🔒 **HIGH:** TOCTOU race condition in breach response cooldown (timestamp set at start)
- 🔒 **HIGH:** Reset token URL leakage (moved to `$_SESSION` transport)
- 🔒 **MEDIUM:** Control character sanitisation, memory clearing, UTC timestamps, error suppression removal
- 🔒 **LOW:** Double-encoding on login page, missing dark mode CSS classes, AMP preheader null check

### 🎨 Logo & Branding Integration

**Logo approach:** All components use a `<picture>` element with SVG source and PNG fallback:

```html
<picture>
    <source srcset="https://go2my.link/img/logo.svg" type="image/svg+xml">
    <img src="https://go2my.link/img/logo.png" alt="Go2My.Link" height="36" width="auto" loading="eager">
</picture>
```

**Logo file locations:** Each component has its own `/img/logo.svg` + `/img/logo.png` (copied from BrandKit). Absolute URLs (`https://go2my.link/img/...`) are used in shared includes (`nav.php`, `footer.php`) for cross-domain consistency.

**Source assets:** `web/assets/BrandKit/` contains the full brand kit (not web-accessible — assets are copied to component `public_html/img/` directories for serving).

**Dynamic favicon:** `header.php` checks for `favicon.png` existence via `file_exists()` on `DOCUMENT_ROOT` and conditionally includes a PNG favicon link alongside the standard ICO.

### 🌐 Landing Pages ("Coming Soon")

Each component has a `public_html_landing/` directory with a standalone `index.php`. When ready to launch, the domain's document root is switched from `public_html_landing/` to `public_html/`.

**Features:**

- SVG + PNG logo via `<picture>` element
- Auto-refresh every 15 minutes (`<meta http-equiv="refresh" content="900">`) — ensures seamless transition when going live
- Countdown ring: 28px SVG circle in bottom-right corner, fills via `requestAnimationFrame` animation, hidden on mobile (`max-width: 768px`), duration dynamically read from the meta refresh tag
- Dark mode via `@media (prefers-color-scheme: dark)` CSS custom properties
- Footer pinned to bottom via flex `margin-top: auto`
- Main content vertically centred via `margin-top: auto; margin-bottom: auto` on `.container`
- Reduced motion support (`prefers-reduced-motion: reduce`)
- Accessibility: skip-to-content link, semantic landmarks, `aria-hidden` on decorative elements

**Brand colours:** Components A and B use `--brand-blue` (#1E88E5); Component C uses `--brand-green` (#43A047).

**Domain testing:** Component B can run at any domain (e.g., `b.g2my.link` for testing) without code changes — all routing is relative and domain is read dynamically from `$_SERVER['HTTP_HOST']`.

### 🗄️ Data Migration (Phase 6)

`docs/MIGRATION_PLAN.md` documents the 7-step migration from the legacy MWlink database. `web/_sql/dry_run.sql` provides a non-destructive read-only validation script. Key decisions: all passwords force-reset (legacy is plaintext), `tblLicenses` skipped, activity log migrated in 10K-row batches (429K total). See the migration plan for rollback procedures.

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
| Org Invitation | `database/org-invitation.schema.json` | tblOrgInvitations record structure |

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
