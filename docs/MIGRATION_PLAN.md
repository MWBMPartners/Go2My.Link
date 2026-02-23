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

- [ ] All schema files (`web/_sql/schema/000-035`) are current and tested
- [ ] All seed files (`web/_sql/seeds/001-010`) are current and tested
- [ ] All stored procedures (`web/_sql/procedures/`) are current
- [ ] All migration scripts (`web/_sql/migrations/001-007`) match legacy schema
- [ ] `auth_creds.php` populated with production database credentials
- [ ] `ENCRYPTION_SALT` generated: `php -r "echo bin2hex(random_bytes(32));"`
- [ ] CAPTCHA keys configured (Turnstile or reCAPTCHA)

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
Step A7: web/_sql/schema/020_shorturls_categories_tags.sql
Step A8: web/_sql/schema/021_shorturls_advanced_redirects.sql
Step A9: web/_sql/schema/030_analytics.sql
Step A10: web/_sql/schema/031_api.sql
Step A11: web/_sql/schema/032_linkspage.sql
Step A12: web/_sql/schema/033_payments.sql
Step A13: web/_sql/schema/034_legal_compliance.sql
Step A14: web/_sql/schema/035_translations.sql
```

### Phase B: Stored Procedures

```
Step B1: web/_sql/procedures/sp_lookupShortURL.sql
Step B2: web/_sql/procedures/sp_logActivity.sql
Step B3: web/_sql/procedures/sp_generateShortCode.sql
```

### Phase C: Seed Data

```
Step C1: web/_sql/seeds/001_subscription_tiers.sql
Step C2: web/_sql/seeds/002_default_organisation.sql
Step C3: web/_sql/seeds/003_default_settings.sql
Step C4: web/_sql/seeds/004_linkspage_templates.sql
Step C5: web/_sql/seeds/005_languages.sql
Step C6: web/_sql/seeds/006_phase3_settings.sql
Step C7: web/_sql/seeds/007_phase4_settings.sql
Step C8: web/_sql/seeds/008_phase5_settings.sql
Step C9: web/_sql/seeds/009_phase6_settings.sql
Step C10: web/_sql/seeds/010_phase6_translations.sql
```

### Phase D: Data Migration (from Legacy)

> ⚠️ **Requires** both `mwtools_mwlink` and `mwtools_Go2MyLink` to be accessible.

Execute migration scripts **in order**:

```
Step D1: web/_sql/migrations/001_migrate_organisations.sql   (5 orgs)
Step D2: web/_sql/migrations/002_migrate_users.sql           (7 users, passwords INVALIDATED)
Step D3: web/_sql/migrations/003_migrate_categories.sql      (4 categories)
Step D4: web/_sql/migrations/004_migrate_shorturls.sql       (480 URLs — CRITICAL)
Step D5: web/_sql/migrations/006_migrate_settings.sql        (23 settings definitions)
```

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

1. Verify `tblOrgDomains` entries exist
2. Verify `tblOrgShortDomains` entries exist with correct default flags
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
- [ ] Private directories (`_auth_keys`, `_functions`, `_includes`) return 403
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
