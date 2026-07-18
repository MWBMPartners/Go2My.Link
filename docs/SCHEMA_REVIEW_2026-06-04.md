# 🗄️ Go2My.Link — Schema Launch-Readiness & CueRCode Integration Review

**Date:** 4 June 2026
**Scope:** All 15 schema files, 7 migrations, 13 seeds, 3 stored procedures in `web/_sql/`.
**Method:** Multi-agent review + **empirical verification on a throwaway MySQL 9.6 instance** (the full import set was actually executed, not just read).

---

## 🎯 Verdict

The core data model is sound — `tblShortURLs.UQ_shortcode_org (shortCode, orgHandle)` is a perfect index for the redirect hot path, conventions are consistent (InnoDB, `utf8mb4_unicode_ci`, `tblPascalCase`/`camelCase`, `IDX_/UQ_/FK_` prefixes), and FK actions are sensible.

**Two critical blockers** were found that a read-only review would miss — both confirmed by actually running the SQL — and **both are now fixed and re-verified** in this branch:

| # | Blocker | Impact | Status |
|---|---|---|---|
| 1 | `sp_lookupShortURL` declared its `EXIT HANDLER` **before** the variable `DECLARE`s (illegal MySQL ordering) | `CREATE PROCEDURE` failed → **every Component B redirect would error** | ✅ Fixed — handler moved after the variables; `CALL sp_lookupShortURL(...)` now compiles and returns a status |
| 2 | `033_payments.sql` defined `tblPayments` with an FK to `tblPaymentDiscounts` **before** that table was created | Schema import **aborted** under `foreign_key_checks=1` (breaking the installer's import) | ✅ Fixed — `tblPaymentDiscounts` reordered above `tblPayments`; full import now succeeds |

After these fixes, the full schema + procedures + seeds import cleanly end-to-end on MySQL 9.6. **The schema is launch-ready for Components A + B.**

---

## ✅ Empirical verification performed

- Imported `web/_sql/schema/*` → `procedures/*` → `seeds/*` (the installer's exact set) into a fresh MySQL 9.6 DB → **0 errors**.
- All 3 stored procedures created; `CALL sp_lookupShortURL('g2my.link','x',@d,@s,@o)` returned `not_found` (compiles + runs).
- CueRCode columns present on `tblShortURLs` and `tblActivityLog`; `FK_url_apikey` created; 5 `cuercode.*` settings seeded.
- Migration `009` applied cleanly to a **pre-CueRCode (git HEAD) schema** → columns + FK + settings added, no error.

---

## 🔗 CueRCode dynamic-QR integration (added)

CueRCode mints a Go2My.Link short code in the background and encodes it in a QR image; editing the short URL's destination changes where the QR points. The hooks are **additive and nullable** (existing rows/inserts unaffected) and have been folded into the **base schema** (so fresh installs are integration-ready) plus a standalone migration for already-deployed DBs.

**Chosen design:** nullable columns on `tblShortURLs` (1:0..1 relationship, keeps the resolve a single indexed row read — no extra JOIN on the hot path), **no** local `tblQRCodes` (that record lives in CueRCode).

- `tblShortURLs`: `createdVia` ENUM (provenance, incl. `cuercode`), `createdViaAPIKeyUID` (FK → `tblAPIKeys`, `ON DELETE SET NULL`), `qrCodeExternalID`, `qrCodeExternalUUID` (UNIQUE — NULLs exempt, enforces 1:0..1), `qrCodeLinkedAt`.
- `tblActivityLog`: `scanSource`, `qrCodeExternalID` (+ indexes) for scan attribution.
- Settings: `cuercode.integration_enabled` (off by default), `allow_external_shortcode`, `api_base_url`, `scan_source_param` (`src`), `scan_source_value` (`qr`).
- Auth: CueRCode authenticates via `tblAPIKeys` (see open issue **#38** — the API framework that issues/verifies keys is still unbuilt; **CueRCode cannot go live until #38/#39 ship**, but the schema is ready).

**Files:** base schema `020/030/031`; `web/_sql/migrations/009_cuercode_qr_integration.sql`; seed `web/_sql/seeds/013_cuercode_settings.sql`.

---

## ⚠️ Remaining findings to track (not launch blockers for A+B)

### High
- **Cross-org category leak** — `tblShortURLs.categoryID` is JOINed to `tblCategories` without `orgHandle`; since category IDs are only unique per-org, the admin list can fan out rows and the public info page can show another org's category name. *Fix: store surrogate `categoryUID` + real FK, or add `AND s.orgHandle = c.orgHandle` to the JOINs.*
- **Org-invitation re-invite** — `UQ_org_email_pending (orgHandle, email, status)` includes `status`, so a second cancel/expire for the same org+email collides and re-invite cycles fail. *Fix: enforce "one PENDING per org+email" via a generated/partial key.*
- **Migration date guards** — migrations copy legacy dates straight into `NOT NULL createdAt/updatedAt` under STRICT mode; a single `0000-00-00`/NULL legacy row aborts the whole batch (risks the 480-URL migration). *Fix: `IFNULL(NULLIF(old.date,'0000-00-00 00:00:00'), NOW())` + a post-migration row-count assertion.*
- **Short-code TOCTOU** — `sp_generateShortCode` is check-then-insert with no retry on a unique-key collision; concurrent creates surface a generic failure. *Fix: catch errno 1062 in `createShortURL()` and retry a bounded number of times.*
- **API rate-limit index** — `tblAPIRequestLog` lacks a composite `(apiKeyUID, createdAt)` for the per-day limit query (ties into #38/#39).
- **`tblAPIKeys` is schema-only** — no app code issues/verifies keys yet (this *is* open issue **#38**); required before CueRCode or any external API goes live.

### Medium (selected)
- FKs target the mutable business key `orgHandle` rather than surrogate `orgUID` — handle renames cascade widely. Decide pre-launch whether to document `orgHandle` as immutable or migrate to `orgUID`.
- `tblActivityLog` lacks a composite `(shortCode, createdAt)` for the dominant analytics query, and is not partitioned (confirms earlier audit #12). Add the index before analytics ships (#41/#42).
- `tblAPIKeys`: `apiKeyPrefix` width (`VARCHAR(10)`) disagrees with its "first 8 chars" comment; no hash scheme pinned for `apiKey`. Clarify before #38.
- `sp_logActivity` omits the new columns and can fail `NOT NULL ipAddress` (errors swallowed). It is currently dead code (the app uses a direct INSERT) — sync or remove it. **Resolved (#126): removed** — the procedure had zero callers, so it was deleted rather than synced.
- `sp_lookupShortURL` does not return `urlUID`/`destinationType`/UTM, so the redirect engine can't attribute clicks to the resolved alias target or forward UTM (ties into #92).

The full 50-finding set (incl. 19 low / 13 info) is available in the review run; the items above are the ones worth tracking before/just-after launch.

---

## 📌 Recommendation

Components A + B are schema-ready to launch with the two critical fixes already applied. The high/medium items are not A+B launch blockers but should be filed and scheduled — several (API key auth, analytics indexes, UTM in the resolver) naturally fold into the already-open Phase 7 issues (#38–#44, #92).
