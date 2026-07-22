# 🚀 Go2My.Link — Migration Execution Plan & Launch Checklist

> Complete step-by-step guide for migrating from the legacy MWlink service to the new Go2My.Link platform and launching v1.0.0.

## 📋 Document Info

| Property | Value |
| --- | --- |
| **📅 Created** | 2026-02-23 |
| **📌 Phase** | Phase 6 (Pre-Launch) |
| **🔗 Related Issue** | [#67 — Migration execution plan and launch checklist](https://github.com/MWBMPartners/GoToMyLink/issues/67) |
| **📄 See Also** | [DEPLOYMENT.md](DEPLOYMENT.md), [DATABASE.md](DATABASE.md) |

---

## 1️⃣ Pre-Migration Checklist

Complete **all** items before beginning the migration:

### 🗄️ Database

- [ ] Back up the legacy `mwtools_mwlink` database (full mysqldump)
- [ ] Store backup in at least 2 locations (local + cloud)
- [ ] Verify backup integrity (test restore on a separate database)
- [ ] Create the new `mwtools_Go2MyLink` database on Dreamhost
- [ ] Confirm MySQL version is 8.0+ on the production server
- [ ] Confirm InnoDB storage engine is available

### 📁 Application Files

- [ ] All 15 schema files (`web/_sql/schema/` — `000`, `010`–`015`, `020`–`021`, `030`–`035`) are current and tested
- [ ] All 17 seed files (`web/_sql/seeds/001-017`) are current and tested
- [ ] Both stored procedures (`web/_sql/procedures/sp_generateShortCode.sql`, `sp_lookupShortURL.sql`) are current — ⚠️ `sp_logActivity.sql` no longer exists (DELETED; direct `INSERT` now)
- [ ] All 18 migration scripts (`web/_sql/migrations/001-004,006,008-019` — there is no `005`) match legacy schema, with `016` and `019` confirmed MANDATORY (see Phase D)
- [ ] `auth_creds.php` populated with production database credentials
- [ ] `ENCRYPTION_SALT` generated: `php -r "echo bin2hex(random_bytes(32));"`
- [ ] CAPTCHA keys configured (Turnstile or reCAPTCHA)

### 🚨 Blocking Pre-Cutover Server Steps

- [ ] 🚨 **`.auth/` directory migration run on the server.** The per-component
      credential directory was renamed `_auth_keys/` → `.auth/`; the app boots
      only from `<Comp>/.auth/auth_creds.php` and the SFTP mirror **cannot**
      create this directory (untracked + excluded from every mirror phase).
      Run, once, BEFORE the first armed deploy against the renamed layout:
      ```bash
      mv Go2My.Link/_auth_keys Go2My.Link/.auth
      mv G2My.Link/_auth_keys  G2My.Link/.auth
      mv Lnks.page/_auth_keys  Lnks.page/.auth
      ```
      The **shared** `web/_auth_keys/auth_creds.php` is UNCHANGED — do NOT
      move it. Skipping this step takes all three sites down. See
      `docs/DEPLOYMENT.md`'s BLOCKING PRE-DEPLOY STEP.
- [ ] 🚨 **#93 — legacy DB credential rotated and file removed.**
      `web/G2My.Link/public_html_legacy/dbConfig.php` still holds a
      real-looking live database credential. It is untracked and gitignored
      (git-safe), but has **not** been rotated or deleted from the server.
      Rotate the credential at the database and delete this file/directory
      from the server before cutover.

### 🌐 Infrastructure

- [ ] All three domains configured in Dreamhost panel (go2my.link, g2my.link, lnks.page)
- [ ] Admin subdomain configured (admin.go2my.link)
- [ ] SSL certificates active on all domains (Let's Encrypt)
- [ ] `.htaccess` files deployed to all `public_html/` directories
- [ ] PHP version set to 8.4+ on Dreamhost panel

### 🧪 Testing

- [ ] Run `web/_sql/dry_run.sql` against legacy database to verify readiness
- [ ] PHP lint passes on all files: `php-parallel-lint web/`
- [ ] Test new database schema import on a staging database first

---

## 2️⃣ Migration Execution Steps

> ⏱️ **Estimated time:** 1-2 hours (excluding optional activity log migration)

### Phase A: Schema & Seeds (New Database)

Execute these SQL files **in order** against `mwtools_Go2MyLink`:

```
Step A1: web/_sql/schema/000_create_database.sql
Step A2: web/_sql/schema/010_core_settings.sql
Step A3: web/_sql/schema/011_core_subscription_tiers.sql
Step A4: web/_sql/schema/012_core_organisations.sql
Step A5: web/_sql/schema/013_core_users.sql
Step A6: web/_sql/schema/014_org_invitations.sql
Step A7: web/_sql/schema/015_account_types.sql
Step A8: web/_sql/schema/020_shorturls_categories_tags.sql
Step A9: web/_sql/schema/021_shorturls_advanced_redirects.sql
Step A10: web/_sql/schema/030_analytics.sql
Step A11: web/_sql/schema/031_api.sql
Step A12: web/_sql/schema/032_linkspage.sql
Step A13: web/_sql/schema/033_payments.sql
Step A14: web/_sql/schema/034_legal_compliance.sql
Step A15: web/_sql/schema/035_translations.sql
```

⚠️ **A7 (`015_account_types.sql`) is not optional** — its absence was
previously undocumented here, but Phase D's `008_migrate_account_types.sql`
hard-depends on the `tblAccountTypes` / `tblUserAccountTypes` tables it
creates and will fail without it (see that migration's own header).

### Phase B: Stored Procedures

```
Step B1: web/_sql/procedures/sp_generateShortCode.sql
Step B2: web/_sql/procedures/sp_lookupShortURL.sql
```

⚠️ **Historical:** `sp_logActivity.sql` was **DELETED** — it does not exist
in `web/_sql/procedures/` any more. The application performs a direct
`INSERT` into `tblActivityLog` instead (`web/_functions/activity_logger.php`).
Only **2** stored procedures exist today; do not attempt to import a third.

### Phase C: Seed Data

```
Step C1:  web/_sql/seeds/001_subscription_tiers.sql
Step C2:  web/_sql/seeds/002_default_organisation.sql
Step C3:  web/_sql/seeds/003_default_settings.sql
Step C4:  web/_sql/seeds/004_linkspage_templates.sql
Step C5:  web/_sql/seeds/005_languages.sql
Step C6:  web/_sql/seeds/006_phase3_settings.sql
Step C7:  web/_sql/seeds/007_phase4_settings.sql
Step C8:  web/_sql/seeds/008_phase5_settings.sql
Step C9:  web/_sql/seeds/009_phase6_settings.sql
Step C10: web/_sql/seeds/010_phase6_translations.sql
Step C11: web/_sql/seeds/011_account_types.sql
Step C12: web/_sql/seeds/012_email_settings.sql
Step C13: web/_sql/seeds/013_cuercode_settings.sql
Step C14: web/_sql/seeds/014_redirect_ssrf_settings.sql
Step C15: web/_sql/seeds/015_api_settings.sql
Step C16: web/_sql/seeds/016_utm_tracking_settings.sql
Step C17: web/_sql/seeds/017_geolocation_settings.sql
```

⚠️ This list previously stopped at `010`; **7 more seed files exist**
(`011`–`017`, added across later phases). All 17 must be loaded — several
later migrations/features (account types, CueRCode, SSRF guard, public API,
UTM capture, geolocation) read settings these seeds define.

### Phase D: Data Migration & Schema Catch-Up

> ⚠️ **Requires** both `mwtools_mwlink` and `mwtools_Go2MyLink` to be accessible
> (data-migration steps D1–D6 only — the schema-catch-up steps D7–D17 touch
> only `mwtools_Go2MyLink`).

⚠️ This list previously stopped at `006`; **12 more migrations exist**
(`008`–`019`, added across later hardening cycles) and were never added
here. Execute ALL migration scripts below **in order** — this list also
previously stopped after Step D5 (migration `006`):

```
Step D1:  web/_sql/migrations/001_migrate_organisations.sql        (5 orgs; grandfathers pre-existing short domains as verificationStatus='verified' — #160)
Step D2:  web/_sql/migrations/002_migrate_users.sql                (7 users, passwords INVALIDATED)
Step D3:  web/_sql/migrations/003_migrate_categories.sql           (4 categories)
Step D4:  web/_sql/migrations/004_migrate_shorturls.sql            (480 URLs — CRITICAL)
Step D5:  web/_sql/migrations/006_migrate_settings.sql             (23 settings definitions)
Step D6:  web/_sql/migrations/008_migrate_account_types.sql        (backfills tblUserAccountTypes from tblUsers.role; needs schema 015 + seed 011 — Phase A7/C11)
Step D7:  web/_sql/migrations/009_cuercode_qr_integration.sql       ⚠️ SKIP if Phase A/B/C imported today's schema fresh — NOT idempotent, ERRORS if the CueRCode columns already exist
Step D8:  web/_sql/migrations/010_org_invite_pending_key.sql        ⚠️ SKIP if genuinely fresh — NOT idempotent, ERRORS if pendingKey already exists
Step D9:  web/_sql/migrations/011_api_request_log_index.sql        (idempotent no-op if already applied)
Step D10: web/_sql/migrations/012_api_request_log_ip_index.sql     (idempotent no-op if already applied)
Step D11: web/_sql/migrations/013_short_domain_verification.sql    (idempotent; also grandfathers pre-existing domains to 'verified')
Step D12: web/_sql/migrations/014_activitylog_analytics_indexes.sql (idempotent no-op if already applied)
Step D13: web/_sql/migrations/015_org_short_domain_linkspage.sql   (idempotent no-op if already applied)
Step D14: web/_sql/migrations/016_linkspage_custom_html.sql        🚨 MANDATORY — see below
Step D15: web/_sql/migrations/017_apikey_prefix_unique.sql         (idempotent no-op if already applied)
Step D16: web/_sql/migrations/018_deprecate_org_domains.sql        (Part 1 only — table COMMENT; Part 2 is a manual, owner-reviewed audit, never auto-run)
Step D17: web/_sql/migrations/019_settings_scope_dedupe.sql        🚨 MANDATORY — see below
```

🚨 **`016` and `019` are MANDATORY at this cutover — do not skip them even
though their own headers say "fresh installs do not need this file":**

- **`016_linkspage_custom_html.sql`** — without it, `tblSubscriptionTiers.hasCustomHTML`
  does not exist; `g2ml_getOrgTier()`'s lookup query then errors and the
  function **fails OPEN**, silently disabling ALL tier gating (link/domain/API
  limits + feature flags) for every organisation platform-wide.
- **`019_settings_scope_dedupe.sql`** — without it, duplicate System-scope
  `tblSettings` rows can coexist (the old UNIQUE key treats NULL as distinct),
  and `getSetting()` may read whichever stale/ambiguous duplicate the query
  happens to return first for platform-wide kill-switches and feature toggles.

`009` and `010` are the opposite case: they are **NOT idempotent** and will
**error** if run against a database that already has their fix (i.e. one
provisioned from today's schema/seed files) — confirm via
`SHOW COLUMNS`/`SHOW CREATE TABLE` before running them if there is any doubt
about this database's schema history.

### Phase E: Optional — Activity Log Migration

> ⏱️ **Estimated time:** 30-60 minutes (429K rows in batches of 10K)

```
Step E1: web/_sql/migrations/007_migrate_activity_log.sql    (run in 43 batches)
```

This migration is optional. The activity log is large (429,611 rows) and is migrated in batches of 10,000 rows using `LIMIT/OFFSET`. Run the script 43 times, incrementing the offset each time.

---

## 3️⃣ Post-Migration Verification

### ✅ Data Integrity Checks

Run these queries against `mwtools_Go2MyLink` to verify migration success:

```sql
-- Verify URL count (expect 480)
SELECT COUNT(*) AS total_urls FROM tblShortURLs;

-- Verify all URLs are active
SELECT COUNT(*) AS active_urls FROM tblShortURLs WHERE isActive = 1;

-- Verify user count (expect 7)
SELECT COUNT(*) AS total_users FROM tblUsers;

-- Verify all passwords are invalidated (forcePasswordReset = 1)
SELECT COUNT(*) AS users_needing_reset FROM tblUsers WHERE forcePasswordReset = 1;

-- Verify org count (expect 6: 5 migrated + 1 default)
SELECT COUNT(*) AS total_orgs FROM tblOrganisations;

-- Verify category count (expect 4)
SELECT COUNT(*) AS total_categories FROM tblCategories;

-- Verify settings loaded
SELECT COUNT(*) AS total_settings FROM tblSettings;

-- Verify translations loaded
SELECT COUNT(*) AS total_translations FROM tblTranslations;

-- Verify subscription tiers (expect 4)
SELECT COUNT(*) AS total_tiers FROM tblSubscriptionTiers;

-- Verify languages seeded (expect 10)
SELECT COUNT(*) AS total_languages FROM tblLanguages;
```

### 🔗 URL Resolution Testing

Test a random sample of migrated short URLs:

1. Select 10 random URLs: `SELECT shortCode, destinationURL FROM tblShortURLs ORDER BY RAND() LIMIT 10;`
2. Visit `https://g2my.link/{shortCode}` for each
3. Verify redirect goes to the correct `destinationURL`
4. Check that click counters increment in `tblShortURLs`

### 🏢 Organisation Domain Verification

For each migrated organisation with custom domains:

1. Verify `tblOrgShortDomains` entries exist with correct default flags
   (migration `001_migrate_organisations.sql` populates this table only —
   `tblOrgDomains` is a separate, ⚠️ **DEPRECATED (GT-6)** table that the
   legacy-data migration never touches; see `docs/DATABASE.md`)
2. Confirm migrated domains show `verificationStatus = 'verified'` —
   migration `001` now **grandfathers** every pre-existing short domain to
   `'verified'` (#160), since it was already live and serving traffic on the
   legacy platform and must not be forced to re-prove DNS ownership at
   cutover. Only `verified` + active domains are routable
   (`domain_resolver.php`), so this is what makes migrated partner domains
   resolve immediately rather than 404 until each org re-verifies.
3. Test custom domain resolution if DNS is already configured

---

## 4️⃣ DNS Cutover Plan

### 📅 Timing

- **When:** Weekday, early morning UTC (minimal traffic)
- **Duration:** Allow 48 hours for full DNS propagation
- **Rollback window:** 72 hours (keep old hosting active)

### 🔄 Preparation (24 Hours Before)

1. Reduce DNS TTL on all domains to **300 seconds** (5 minutes)
2. Wait for old TTL to expire (typically 24 hours)
3. Verify reduced TTL is propagated: `dig +short go2my.link`

### 🔀 Cutover Steps

1. **Update A records** for all three domains to point to Dreamhost IP
2. **Verify CNAME** for `admin.go2my.link` → `go2my.link`
3. **Wait** for Let's Encrypt to issue SSL certificates (auto-provisioned by Dreamhost)
4. **Test HTTPS** on all domains — certificates must be valid
5. **Test redirects** — `http://` must redirect to `https://`
6. **Test short URL resolution** — `https://g2my.link/abc123` must redirect correctly

### 📊 DNS Propagation Monitoring

Check propagation status every 2 hours for the first 12 hours:

```bash
# Check A record propagation
dig +short go2my.link
dig +short g2my.link
dig +short lnks.page

# Check HTTPS is working
curl -sI https://go2my.link | head -5
curl -sI https://g2my.link/test | head -5
```

### ⏪ Post-Propagation (48+ Hours)

1. Restore DNS TTL to default (3600 seconds / 1 hour)
2. Confirm all domains resolve correctly from multiple locations
3. Decommission old hosting (but keep backups for 30 days)

---

## 5️⃣ Rollback Procedure

If critical issues are discovered after migration:

### 🗄️ Database Rollback

1. Stop the application (set maintenance mode via setting)
2. Restore `mwtools_mwlink` from pre-migration backup
3. Update `auth_creds.php` to point back to the legacy database
4. Restart the application

### 🌐 DNS Rollback

1. Revert A records to old hosting IP
2. Wait for DNS propagation (5 minutes if TTL was reduced)
3. Verify old service is responding

### 📁 Application Rollback

1. `git revert` to the last known-good commit
2. Re-deploy via SFTP
3. Verify the application is functioning

### 📝 Incident Documentation

After any rollback:

1. Document the issue in `DEV_NOTES.md`
2. Create a GitHub issue for the root cause
3. Determine fix before re-attempting migration

---

## 6️⃣ Post-Launch Monitoring

### ⏱️ First 4 Hours (Active Monitoring)

Check every **30 minutes**:

- [ ] `tblErrorLog` — No new critical errors
- [ ] `tblActivityLog` — Request patterns look normal
- [ ] All 3 domains responding with HTTPS
- [ ] Short URL redirects working (test 3 random URLs)
- [ ] Login flow working (test forgot-password email delivery)
- [ ] Cookie consent banner appearing for new visitors
- [ ] Theme toggle working (light/dark/auto)

### 📅 First 24 Hours

Check every **2 hours**:

- [ ] Dreamhost server access logs — No 500 errors
- [ ] Dreamhost server error logs — No PHP fatal errors
- [ ] SSL certificates valid on all domains
- [ ] DNS resolution stable from multiple geographic locations

### 📅 First 7 Days

Check **daily**:

- [ ] Error log trends — No recurring issues
- [ ] Activity log volume — Consistent with expected traffic
- [ ] All migrated URLs still resolving correctly
- [ ] Email delivery working (verification, password reset)
- [ ] No user reports of broken links

---

## 7️⃣ Launch Checklist

> ✅ Complete all items before announcing the launch.

### 🗄️ Database & Data

- [ ] New database created and schema imported
- [ ] All seed data loaded (subscriptions, settings, languages, translations)
- [ ] All stored procedures installed
- [ ] All 480 URLs migrated and verified
- [ ] All 7 users migrated (passwords invalidated)
- [ ] All 5 organisations migrated
- [ ] All 4 categories migrated
- [ ] 🚨 Migration `016` (LinksPage custom-HTML entitlement gates) applied — confirm `tblSubscriptionTiers.hasCustomHTML` exists and tier gating (`g2ml_getOrgTier()`) is NOT failing open
- [ ] 🚨 Migration `019` (System-scope settings dedupe) applied — confirm no duplicate `(settingID, 'System', NULL)` rows remain in `tblSettings`

### 🌐 Infrastructure

- [ ] DNS A records pointing to Dreamhost for all 3 domains
- [ ] Admin subdomain CNAME configured
- [ ] SSL certificates active and valid on all domains
- [ ] HTTPS enforcement working (HTTP → HTTPS redirect)
- [ ] HSTS headers present in responses

### 🔒 Security

- [ ] `auth_creds.php` has production credentials
- [ ] `ENCRYPTION_SALT` is a unique 64-character hex string
- [ ] `auth_creds.php` not accessible via browser (returns redirect)
- [ ] Private directories (`_auth_keys`, `.auth`, `_functions`, `_includes`) return 403
- [ ] 🚨 Each component's `.auth/` directory renamed from `_auth_keys/` on the
      server (BLOCKING PRE-CUTOVER SERVER STEP, §1, complete) and
      `<Comp>/.auth/auth_creds.php` confirmed present on all 3 components
- [ ] 🚨 #93 — legacy credential rotated and `public_html_legacy/` removed
      from the server (BLOCKING PRE-CUTOVER SERVER STEP, §1, complete)
- [ ] CSP headers present on all domains
- [ ] CSRF protection working on all forms
- [ ] Rate limiting active on URL creation and login
- [ ] Account lockout working after failed login attempts

### 🖥️ Application

- [ ] Homepage loads and URL shortening form works
- [ ] Registration → Email verification → Login flow works
- [ ] Dashboard shows correct link counts
- [ ] Link creation, editing, and deletion work
- [ ] Short URL redirects work on g2my.link
- [ ] Info/preview page works at `/info/{code}`
- [ ] All static pages load (about, features, pricing, contact)
- [ ] Organisation creation and management works

### ⚖️ Compliance

- [ ] Cookie consent banner appears for new visitors
- [ ] Cookie preferences modal works (accept/reject/customise)
- [ ] DNT/GPC headers respected (non-essential tracking suppressed)
- [ ] Privacy dashboard accessible (consent, export, deletion)
- [ ] Data export generates downloadable JSON
- [ ] All 5 legal pages load with full content (terms, privacy, cookies, copyright, AUP)
- [ ] Legal pages display correct version numbers

### 📱 PWA & Accessibility

- [ ] PWA manifest.json loads on all 3 domains
- [ ] Service worker registers without errors
- [ ] Skip-to-content link works (Tab key on page load)
- [ ] All forms have associated labels
- [ ] Screen reader can navigate all pages
- [ ] Theme toggle works in all three modes

### 🐛 Monitoring

- [ ] `tblErrorLog` logging PHP errors correctly
- [ ] `tblActivityLog` logging requests correctly
- [ ] Debug mode disabled in production (`?debug=true` only for admin IPs)
- [ ] External uptime monitoring configured (UptimeRobot or similar)

---

## 📚 Related Documentation

- 📋 [DEPLOYMENT.md](DEPLOYMENT.md) — Hosting and deployment procedures
- 🗄️ [DATABASE.md](DATABASE.md) — Database schema reference
- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- ♿ [ACCESSIBILITY.md](ACCESSIBILITY.md) — Accessibility standards
- 🌍 [TRANSLATION.md](TRANSLATION.md) — Translation guide
