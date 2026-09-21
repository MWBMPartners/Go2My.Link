# Go2My.Link — Database Documentation

> Database schema, migration strategy, and conventions for the Go2My.Link platform.

## 📋 Overview

| Property | Value |
| --- | --- |
| **🗄️ Database name** | `mwtools_Go2MyLink` |
| **⚙️ Engine** | InnoDB (all tables) |
| **🔤 Character set** | utf8mb4 |
| **🔤 Collation** | utf8mb4_unicode_ci |
| **🗄️ MySQL version** | 8.0+ |
| **🔌 Access method** | MySQLi only (no PDO) |
| **🔒 Connection** | Prepared statements exclusively |

## 📦 Legacy Database

The existing `mwtools_mwlink` database (MyISAM, utf8mb4) contains data to be migrated:

| Table | Records | Migrated? |
| --- | --- | --- |
| `tblShortURLs` | 480 | ✅ Yes — core short URL records |
| `tblActivityLog` | 429,611 | ⏳ Optional — large volume, batched |
| `tblQRCodes` | 55 | ❌ **NO** — QR codes handled by separate first-party service |
| `tblSettingsDictionary` | 23 | ✅ Yes — expanded with new settings |
| `tblCustomerOrg` | 5 | ✅ Yes — mapped to tblOrganisations |
| `tblCustomers` | 7 | ✅ Yes — passwords force-reset |
| `tblCategories` | 4 | ✅ Yes — with org FK added |
| `tblSettings` | 1 | ✅ Yes — merged into new schema |
| `tblCustomerAPIs` | 0 | 📝 Schema only — no data |
| `tblLicenses` | 2 | ❌ **NO** — legacy NetPLAYER data |

## 🗄️ New Schema

Schema files are located in `web/_sql/schema/`.

> 🔗 **CueRCode dynamic-QR integration** (v1.0.0 — Launch Hardening): `tblShortURLs`
> and `tblActivityLog` carry nullable hooks so the external CueRCode QR service can
> own a short code and have scans attributed. There is **no local `tblQRCodes`** —
> the QR record lives in CueRCode. See `web/_sql/migrations/009_cuercode_qr_integration.sql`,
> seed `013_cuercode_settings.sql`, and `docs/SCHEMA_REVIEW_2026-06-04.md`.

### 📂 Table Groups

#### 🔧 Core

| Table | Purpose |
| --- | --- |
| `tblSettings` | ⚙️ Settings dictionary + values merged, `isSensitive` flag, encrypted values |
| `tblOrganisations` | 🏢 Organisations with custom domains, subscription tier, verification |
| `tblUsers` | 👤 User accounts (Argon2id hashing, roles, 2FA, PassKey, avatar) |
| `tblUserSocialLogins` | 🔗 OAuth provider links, encrypted tokens |
| `tblUserSessions` | 🔐 Active session tracking |
| `tblOrgDomains` | 🌐 ⚠️ **DEPRECATED (GT-6)** — legacy Phase 5 domain DNS verification; superseded by `tblOrgShortDomains`, gates no routing. See `web/_sql/migrations/018_deprecate_org_domains.sql`. |
| `tblOrgShortDomains` | 🔗 Organisation custom short domains — the ONLY table the redirect resolver and LinksPage custom-domain fallback (#46) read (`isActive` + `verificationStatus='verified'`, #91) |
| `tblAccountTypes` | 🏷️ Reference table of available account types (system + custom) |
| `tblUserAccountTypes` | 🔀 Junction table linking users to account types (org-scoped, multi-type) |

#### 🔗 Short URLs

| Table | Purpose |
| --- | --- |
| `tblShortURLs` | 🔗 Enhanced short URL records (`createdByUserUID` FK, `isActive`, `clickCount` cache) + CueRCode provenance/QR columns (`createdVia`, `createdViaAPIKeyUID`, `qrCodeExternalID`/`qrCodeExternalUUID`, `qrCodeLinkedAt`) |
| `tblCategories` | 🏷️ Link categories |
| `tblTags` | 🏷️ Link tags |
| `tblShortURLTags` | 🔀 Junction table (short URLs ↔ tags) |
| `tblShortURLSchedules` | 📅 JSON schedule definitions for scheduled redirects |
| `tblShortURLDeviceRedirects` | 📱 Device-based redirect rules |
| `tblShortURLGeoRedirects` | 🌍 Geo-based redirect rules |
| `tblShortURLAgeGates` | 🔞 Age verification gate configuration |

#### 📊 Analytics

| Table | Purpose |
| --- | --- |
| `tblActivityLog` | 📊 Request/redirect logging (InnoDB, structured geo/UA columns; monthly partitioning available but **not enabled**) + CueRCode scan attribution (`scanSource`, `qrCodeExternalID`) |
| `tblErrorLog` | 🐛 PHP errors with backtrace |

#### 📡 API

| Table | Purpose |
| --- | --- |
| `tblAPIKeys` | 🔑 API key storage and metadata |
| `tblAPIRequestLog` | 📋 API request audit trail |

#### 📄 LinksPage

| Table | Purpose |
| --- | --- |
| `tblLinksPages` | 📄 LinksPage definitions per user/org |
| `tblLinksPageItems` | 🔗 Individual links on a LinksPage |
| `tblLinksPageTemplates` | 🎨 Template definitions (5 system templates) |

#### 💰 Payments

| Table | Purpose |
| --- | --- |
| `tblSubscriptionTiers` | 📊 Tier definitions (Free/Basic/Premium/Enterprise) |
| `tblSubscriptions` | 📝 User/org subscriptions |
| `tblPayments` | 💳 Payment transaction records |
| `tblPaymentDiscounts` | 🏷️ Per payment method discounts |

#### ⚖️ Legal / Compliance

| Table | Purpose |
| --- | --- |
| `tblConsentRecords` | ✅ GDPR/CCPA consent tracking |
| `tblDataDeletionRequests` | 🗑️ Data subject deletion requests |

> ✅ **LinksPages are covered by data export and erasure (fixed 2026-09-21, issue #219).**
> `g2ml_requestDataExport()` and `g2ml_anonymiseUserData()`
> (`web/_functions/data_rights.php`) used to work only against
> `tblUsers`/`tblShortURLs`/`tblConsentRecords`/`tblUserSessions` — they never
> touched `tblLinksPages` or `tblLinksPageItems`. That meant a subject-access
> export left out a person's own LinksPage profile pages entirely (a GDPR
> Article 20 gap), and deleting an account left their LinksPage published —
> still showing their real name, bio, avatar and links — because nothing ever
> removed it (a GDPR Article 17 gap). The export now includes a
> `linkspages` section (slug, title, bio, avatar, colours, font, social
> links, published state, and plain `hasCustomHTML`/`hasCustomCSS` flags —
> the raw custom HTML and CSS columns are left out of the export itself as a
> size trade-off, since both are content the user wrote but are also capped
> in size, see `web/_functions/html_sanitiser.php`; whether to include the
> actual content despite that trade-off is an open follow-up, tracked as
> **#245**) and a `linkspage_items` section (each
> link's title, URL, description, icon, age-gate flag and sort order), both
> scoped to the requesting user's own pages only. Deletion now removes the
> user's `tblLinksPages` rows inside the same transaction as the rest of the
> anonymisation; their items go with them through `FK_item_page` (`ON DELETE
> CASCADE`), and a custom domain that had designated one of those pages as
> its root has that designation cleared through `FK_short_domain_linkspage`
> (`ON DELETE SET NULL`). The domain then behaves as if no page had ever been
> chosen: its bare address redirects to the site's fallback address
> (`redirect.fallback_url`), and an unknown path gets the not-found page
> unless the organisation has set its own fallback address (`orgFallbackURL`),
> in which case the visitor is redirected there — see
> `web/_sql/schema/032_linkspage.sql`.
>
> ✅ **Review round 1 (2026-09-21) also updated the Delete Account and Export
> pages themselves** — `web/Go2My.Link/_admin/public_html/pages/privacy/
> delete/index.php` and `.../export/index.php` — to say, in plain words, that
> LinksPages are part of what gets deleted and part of what gets exported.
> Before this, the code carried out both correctly but neither page told the
> person about it, so someone could delete their account without knowing
> their public lnks.page address would stop working. See seed
> `029_linkspage_privacy_translations.sql` for the new strings.
>
> ⚠️ **Known trade-off, not yet solved (tracked as #244):** deleting a
> LinksPage frees its slug immediately, so anyone can register the same
> `lnks.page/<slug>` right away and receive whatever traffic the deleted
> person's old links still send it. This is not new — deleting a page by
> hand already does the same thing — and it is tracked as a follow-up rather
> than fixed here, because holding a freed slug back for a cooling-off
> period is a product decision (how long, whether it also applies to manual
> deletes), not a one-line code change.

#### 🌍 Translation

| Table | Purpose |
| --- | --- |
| `tblLanguages` | 🌐 Supported languages |
| `tblTranslations` | 🔤 Translation strings per language |

## 👤 User Roles & Account Types

### 🏷️ Account Types (Multi-Type Model)

Users can hold **multiple account types** simultaneously via the `tblUserAccountTypes` junction table. Account types are org-scoped and support optional expiry and audit trails.

The four **system account types** map to the legacy role hierarchy:

| Account Type ID | Display Name | Role Level | Legacy Role | System? |
| --- | --- | --- | --- | --- |
| `anonymous` | Anonymous | 0 | ⚪ Anonymous | ✅ |
| `user` | User | 1 | 🟢 User | ✅ |
| `admin` | Admin | 2 | 🟠 Admin | ✅ |
| `global-admin` | Global Admin | 3 | 🔴 GlobalAdmin | ✅ |

### 🔄 Effective Role (Backward Compatibility)

The `tblUsers.role` ENUM column is retained as a cached **"effective role"** — the highest-privilege account type the user holds. This column is automatically kept in sync by `syncEffectiveRole()` whenever account types change, ensuring `hasMinimumRole()` continues to work without modification.

### 📖 Legacy Role Hierarchy

| Role | Level | Description |
| --- | --- | --- |
| 🔴 `GlobalAdmin` | Highest | Full org control (domains, members, SSO, billing, all links) |
| 🟠 `Admin` | High | Link management + member management (limited) |
| 🟢 `User` | Standard | Create links only (modify if permitted) |
| ⚪ `Anonymous` | Lowest | Basic link creation, no management |

## ⚙️ Settings System

Settings use a dictionary pattern with scope hierarchy:

```text
Resolution order: User > Organisation > System > Default
```

- **📌 Default:** Defined in `tblSettings` (`settingDefault` column)
- **🖥️ System:** System-level override (`settingValue` column)
- **🏢 Organisation:** Per-org override
- **👤 User:** Per-user override

🔒 Sensitive settings (where `isSensitive = 1`) are encrypted with AES-256-GCM using the `ENCRYPTION_SALT` from `auth_creds.php`.

## 🧩 Feature Registry for LinksPage (LP-01, #216)

### 📋 Two kinds of feature

Some features are only on some plans. There are two ways the code decides who gets what:

| Kind of feature | Where the plan values live | How the code checks it |
| --- | --- | --- |
| **Older features** (link limits, analytics, API access, custom HTML …) | A fixed column on `tblSubscriptionTiers`, such as `hasAnalytics` or `maxLinks` | `g2ml_canUseFeature()` / `g2ml_checkLimit()` in `web/_functions/entitlements.php` |
| **New features** (everything the LinksPage programme adds) | One row per feature in `tblFeatures` (the feature registry), plus one row per plan in `tblTierFeatures` | `g2ml_featureAllowed()` / `g2ml_featureLimit()` in the same file |

New features deliberately do **not** get a new column. Adding a column for every feature means a schema change and a deploy each time; the registry means the owner moves a feature between plans by changing **one row**, with no code change:

```sql
UPDATE tblTierFeatures
   SET valueBoolean = 1
 WHERE tierID = 'basic'
   AND featureUID = (SELECT featureUID FROM tblFeatures
                      WHERE featureSlug = 'linkspage.hide_branding');
```

A change takes effect on the next page view: the checks run when a setting is saved **and** again when the public page is shown, so a customer who moves to a cheaper plan loses the extra straight away, with no clean-up job.

### 🔌 Works with the pricing engine off or on

The registry tables belong to the pricing engine (`web/_sql/schema/036_pricing_engine.sql`), whose master switch `billing.pricing_engine_enabled` ships **off**. The two new functions give the **same answer either way**:

- With the switch **off**, they ask `g2ml_pricingResolveOrgTier()` in `web/_functions/pricing.php` directly. That function never checks the switch itself.
- With the switch **on**, the organisation's tier already carries the same resolved values.

In both cases the same rows are merged in the same order: the registry default, then the plan's row, then any per-organisation override in `tblOrgFeatureOverrides`.

> 🐛 **Fixed in LP-01:** three switches could never actually be turned on: the master switch, and the two usage-metering switches (`billing.usage_metering_enabled` and `billing.usage_event_log_enabled`). They are `boolean` settings, `getSetting()` returns a real PHP `true` for them, and `pricing.php` compared the value with the text `'1'`. The switches now work. They are still seeded **off**, and turning any of them on is a separate owner decision.

#### ⚠️ Before deploying LP-01: check that all three switches are still off

Until now, a stored "on" value (`1`, `true`, `yes` or `on`) was silently ignored because of the bug above. After this release it takes effect straight away. So if anyone ever set one of these directly in the live database, deploying would quietly switch the pricing engine or usage metering on. No admin screen writes these settings, so only a direct database edit could have done it, but it costs one query to be sure:

```sql
SELECT settingID, settingScope, settingScopeRef, settingValue
  FROM tblSettings
 WHERE settingID IN ('billing.pricing_engine_enabled',
                     'billing.usage_metering_enabled',
                     'billing.usage_event_log_enabled');
```

Every row returned should have `settingValue` = `0`. No rows at all is also fine, because a missing setting counts as off. The code only reads the `System` and `Default` rows here, but a row at any level that is not `0` is worth asking about. If one is not `0`, ask the owner whether it was meant, and set it back to `0` before deploying unless they say otherwise.

The same check is step 1 of the deploy steps in [DEPLOYMENT.md](DEPLOYMENT.md) ("Migration `021` — the LinksPage feature registry") and owner action **A10** in [PRE_LAUNCH_CHECKLIST.md](../PRE_LAUNCH_CHECKLIST.md), which are the pages followed during a deploy.

### 🛡️ What happens when something goes wrong

| Situation | `g2ml_featureAllowed()` (yes/no) | `g2ml_featureLimit()` (numbers) |
| --- | --- | --- |
| The `[default]` organisation, or a GlobalAdmin | ✅ Allowed | ✅ Unlimited |
| A database or system error | ❌ Denied, and one line in the error log | ✅ Allowed, unlimited |
| A feature name that is not in the registry (usually a typo) | ❌ Denied, and one line in the error log | ✅ Allowed, unlimited, and one line in the error log |
| A plan with no row for the feature | The registry default (off for every LinksPage feature) | The registry default |

The yes/no check fails **closed** on purpose: every feature behind it is an optional extra on a page that still works without it, so a fault can only hide a paid extra — it can never block creating a link, a redirect or a login. The number check fails **open**, like the older `g2ml_checkLimit()`, because a limit only ever blocks creating something, and a fault in this system must never block a legitimate action.

To read a limit's value rather than test a count against it (for example how many days of statistics a plan may see), call `g2ml_featureLimit($org, 'linkspage.analytics_retention_days', 0)` and use `['limit']`. `null` means unlimited.

A LinksPage whose organisation was deleted has a `NULL` `orgHandle`. Callers pass `''` in that case, which resolves to the Free plan; they never skip the check.

### 📋 The registered features and their proposed plan values

These values are a **proposal for the owner to confirm** (issue #216). Each one is a single row and can be changed later without any code change.

| Feature name (`featureSlug`) | Free | Basic | Premium | Enterprise | Built in |
| --- | --- | --- | --- | --- | --- |
| `linkspage.hide_branding` | No | Yes | Yes | Yes | LP-03 |
| `linkspage.seo` | No | Yes | Yes | Yes | LP-04 |
| `linkspage.click_tracking` | Yes | Yes | Yes | Yes | LP-05 |
| `linkspage.analytics_retention_days` | 30 days | 90 days | 365 days | All time | LP-06 |
| `linkspage.scheduled_links` | No | Yes | Yes | Yes | LP-07 |
| `linkspage.password_protect` | No | No | Yes | Yes | 🔜 not built yet |
| `linkspage.image_upload` | No | Yes | Yes | Yes | 🔜 not built yet |
| `linkspage.verified_badge` | No | No | Yes | Yes | 🔜 not built yet |
| `linkspage.lead_capture` | No | No | Yes | Yes | ❌ needs legal sign-off |
| `linkspage.tracking_pixels` | No | No | Yes | Yes | ❌ needs legal sign-off |
| `linkspage.embeds` | No | No | Yes | Yes | 🔜 not built yet |
| `linkspage.all_templates` | Yes | Yes | Yes | Yes | describes today; not enforced through the registry |
| `linkspage.agegate` | Yes | Yes | Yes | Yes | describes today; not enforced through the registry |
| `linkspage.custom_domain` | No | Yes | Yes | Yes | describes today; not enforced through the registry |

### 📁 Files

- 🗄️ **Fresh installs:** `web/_sql/seeds/023_linkspage_feature_registry.sql`
- 🚢 **Databases installed before it:** `web/_sql/migrations/021_linkspage_feature_registry.sql` (the same statements; safe to run more than once). Run migration `020` first, **even on a database that already has it**: a re-run is the only thing that corrects the master switch's stored description, which used to say the pricing tables were "completely inert" with the switch off. The deploy steps, including the check that migration `019` is in place first, are in [DEPLOYMENT.md](DEPLOYMENT.md) ("Migration `021` — the LinksPage feature registry").
- 🔧 **The checks:** `web/_functions/entitlements.php` — `g2ml_featureAllowed()`, `g2ml_featureLimit()`
- 🔧 **The merge rules:** `web/_functions/pricing.php` — `g2ml_pricingResolveOrgTier()`

Re-running the seed or the migration never overwrites a plan value someone has changed since; it only refreshes each feature's description.

> ✅ **MariaDB (Dreamhost) — fixed 2026-09-21, issue #183.** Schema `036` used to fail to create `tblTierFeatures` on MariaDB 11.4 (its generated column `effectiveFromKey` was refused). Without that table no plan had any LinksPage extra: the yes/no check denied them all (short links, redirects and logins were never affected — they do not use this table). The fix replaces a `CAST` the schema used to rely on with a plain `TIMESTAMP` literal, which both MariaDB and MySQL accept; confirmed by importing the full schema into a throwaway MariaDB 11.4 container with 0 errors. `.github/workflows/mariadb-import.yml` now checks the fresh-install files — `web/_sql/schema`, `web/_sql/procedures`, `web/_sql/seeds` — against MariaDB 11.4 on every change under `web/_sql/`, but it does **not** import `web/_sql/migrations`, so a MariaDB-only fault written into a migration still needs checking by hand, and the workflow is a warning check, not a required one (a red result does not by itself block a pull request). Still check that `tblTierFeatures` exists after installing on the real production database before relying on these features — that workflow proves MariaDB 11.4 in general, not the exact point release Dreamhost runs.

## 🔧 Stored Procedures

Located in `web/_sql/procedures/`.

| Procedure | Purpose |
| --- | --- |
| `sp_lookupShortURL` | 🔍 Resolve short code with alias chain (max 3 hops), date validation, domain lookup |
| `sp_logActivity` | 📝 Insert structured activity log entry |
| `sp_generateShortCode` | 🎲 Generate unique random alphanumeric short code |

## 🚀 Migration Strategy

Migration scripts are located in `web/_sql/migrations/`.

### 📋 Migration Sequence

1. 🏢 **Organisations** (5 records) — preserve `custOrgHandle`
2. 👤 **Users** (7 records) — invalidate all passwords, map roles
3. 🏷️ **Categories** (4 records) — add organisation FK
4. 🔗 **Short URLs** (480 records) — preserve `urlUID`, set `isActive=1`, map org FK
5. ⚙️ **Settings** (23 definitions + 1 value) — expand with new settings
6. 📊 **Activity Log** (429K records) — optional batch import
7. ⏭️ **Skip** `tblQRCodes` (handled by separate first-party QR service)
8. ⏭️ **Skip** `tblLicenses` (legacy NetPLAYER)

### 🚀 Zero-Downtime Cutover

1. 🚢 Deploy new redirect engine to staging subdomain
2. ▶️ Run migration scripts against new database
3. ✅ Test all 480 URLs against staging environment
4. 🌐 DNS cutover (old domains → new service)
5. ⏳ Old service remains active during DNS propagation
6. 🗑️ Decommission old service after verification

## 📐 Conventions

### 🏷️ Naming

- 📋 Table names: `tblPascalCase` (e.g., `tblShortURLs`, `tblUserSessions`)
- 📋 Column names: `camelCase` (e.g., `shortCode`, `createdAt`, `isActive`)
- 🔑 Foreign keys: `FK_{child}_{parent}` naming convention
- 📇 Indexes: `IDX_{table}_{column}` naming convention
- 🔧 Stored procedures: `sp_camelCase` (e.g., `sp_lookupShortURL`)

### 🔢 Data Types

- 🔑 Primary keys: `INT UNSIGNED AUTO_INCREMENT` or `CHAR(36)` UUID
- ✅ Booleans: `TINYINT(1)` with 0/1 values
- 🕐 Timestamps: `DATETIME` with UTC timezone
- 🔗 URLs: `TEXT` (not VARCHAR, to support long URLs)
- 🌐 IP addresses: `VARCHAR(45)` (supports IPv6)
- 📄 JSON data: `JSON` column type where appropriate

### 🔒 Security

- ✅ All queries use MySQLi prepared statements
- ❌ No raw SQL string concatenation
- 🔐 Sensitive columns encrypted with AES-256-GCM
- 🔑 Passwords hashed with Argon2id (bcrypt fallback)

## 📚 Related Documentation

- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- 📡 [API.md](API.md) — API endpoint reference
- 🚢 [DEPLOYMENT.md](DEPLOYMENT.md) — Deployment and hosting guide
