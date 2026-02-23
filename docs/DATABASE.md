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

### 📂 Table Groups

#### 🔧 Core

| Table | Purpose |
| --- | --- |
| `tblSettings` | ⚙️ Settings dictionary + values merged, `isSensitive` flag, encrypted values |
| `tblOrganisations` | 🏢 Organisations with custom domains, subscription tier, verification |
| `tblUsers` | 👤 User accounts (Argon2id hashing, roles, 2FA, PassKey, avatar) |
| `tblUserSocialLogins` | 🔗 OAuth provider links, encrypted tokens |
| `tblUserSessions` | 🔐 Active session tracking |
| `tblOrgDomains` | 🌐 Organisation domain DNS verification |
| `tblOrgShortDomains` | 🔗 Organisation custom short domains |
| `tblAccountTypes` | 🏷️ Reference table of available account types (system + custom) |
| `tblUserAccountTypes` | 🔀 Junction table linking users to account types (org-scoped, multi-type) |

#### 🔗 Short URLs

| Table | Purpose |
| --- | --- |
| `tblShortURLs` | 🔗 Enhanced short URL records with `createdByUserUID` FK, `isActive`, `clickCount` cache |
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
| `tblActivityLog` | 📊 Request/redirect logging (InnoDB, structured geo/UA columns, partitioned by month) |
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

```
Resolution order: User > Organisation > System > Default
```

- **📌 Default:** Defined in `tblSettings` (`settingDefault` column)
- **🖥️ System:** System-level override (`settingValue` column)
- **🏢 Organisation:** Per-org override
- **👤 User:** Per-user override

🔒 Sensitive settings (where `isSensitive = 1`) are encrypted with AES-256-GCM using the `ENCRYPTION_SALT` from `auth_creds.php`.

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
