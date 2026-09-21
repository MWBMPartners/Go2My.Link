---
name: installer-schema-cuercode-2026-06
description: 2026-06 web installer, two critical schema fixes, and CueRCode dynamic-QR integration (issues #121–#128)
metadata:
  type: project
---

Work done 2026-06-04/05 on branch `audit/launch-hardening-2026-06-04` (commit `9f58807`; not pushed). Follows the [[audit-2026-06-04]] deployment audit.

## 🧰 Web installer

`web/Go2My.Link/public_html/install/index.php` (+ `.htaccess`). Self-locking, full-bootstrap wizard for all three components. Steps: requirements → DB credentials + live test → import `web/_sql/schema/*` + `procedures/*` + `seeds/*` (custom SQL splitter that honours `DELIMITER` and skips `CREATE DATABASE`/`USE`) → create GlobalAdmin → generate `ENCRYPTION_SALT`/`ENCRYPTION_KEY_SECONDARY` + write the shared `web/_auth_keys/auth_creds.php` (0600) → write `.installed` lock.

**Hardened after an adversarial security review** (found C1 replayable steps / C2 no-HTTPS):
- HTTPS required before any secret-accepting action.
- **Proof-of-control token**: written to `web/_auth_keys/.install_token` on the server; the operator must read it off disk and paste it in — a remote visitor to `/install/` cannot drive the wizard. Gates every state-changing action (plus CSRF).
- Hardened session cookie + `session_regenerate_id`; refuses to create a second GlobalAdmin; `_auth_keys` deny-all `.htaccess` + `index.php` written at finalise; 0600 perms verified.

Key facts: the 3 components share ONE `web/_auth_keys/auth_creds.php` (per-component files just `require_once` it), so the installer writes only that file. GlobalAdmin = `tblUsers` role `GlobalAdmin` + `tblUserAccountTypes` accountTypeID `globaladmin`, org `[default]`, hashed via `g2ml_hashPassword()`. Runtime files `_auth_keys/.install_token` + `.installed` are gitignored. Docs: `docs/INSTALL.md`.

## 🐛 Two critical schema launch blockers (FIXED + empirically verified on MySQL 9.6)

1. **`sp_lookupShortURL`** declared its `DECLARE EXIT HANDLER` *before* the variable `DECLARE`s — illegal MySQL ordering, so `CREATE PROCEDURE` failed and **every Component B redirect would error**. Handler moved after the variables. (Other two procs were already correct.)
2. **`033_payments.sql`** defined `tblPayments` with `FK_payment_discount → tblPaymentDiscounts` before that table was created in the same file → import aborted under `foreign_key_checks=1` (would break the installer's import). Reordered `tblPaymentDiscounts` above `tblPayments`.

Verified by standing up a throwaway MySQL 9.6 and importing the full set (0 errors; all 3 procs compile; `CALL sp_lookupShortURL(...)` runs).

## 🔗 CueRCode dynamic-QR integration

Folded additive, nullable hooks into the **base schema** (so fresh installs are ready) + a clean `web/_sql/migrations/009_cuercode_qr_integration.sql` for already-deployed DBs (the multi-agent-generated first draft used Postgres `$$`-quoting and was replaced). Columns: `tblShortURLs.createdVia/createdViaAPIKeyUID/qrCodeExternalID/qrCodeExternalUUID/qrCodeLinkedAt` (FK to `tblAPIKeys` added at end of `031_api.sql`); `tblActivityLog.scanSource/qrCodeExternalID`. Settings `cuercode.*` (off by default) in `web/_sql/seeds/013_cuercode_settings.sql`. No local `tblQRCodes` — the QR record lives in CueRCode, which authenticates via `tblAPIKeys` (needs #38/#39 before it can go live).

## 📋 Issues filed (#121–#128, schema review)

#121 cross-org category leak (JOIN omits orgHandle, can show another org's category name), #122 org-invite re-invite blocked by `UQ_org_email_pending` including `status`, #123 migration zero-date/NULL guards (protects the 480-URL migration), #124 short-code TOCTOU retry, #125 `tblActivityLog (shortCode,createdAt)` index + partitioning, #126 `sp_logActivity` schema drift, #127 orgHandle-vs-orgUID FK decision, #128 alias-chain integrity. (#121–#124 high → v1.0.0 Launch Hardening; #125–#128 medium → v1.1.0.) Full review: `docs/SCHEMA_REVIEW_2026-06-04.md`.

## ⚙️ Verification tooling note

A throwaway MySQL (`/opt/homebrew/opt/mysql/bin/mysqld`, v9.6) is available locally for empirical SQL import tests — the gold-standard check the read-only first audit lacked. Reusable test pattern: init insecure datadir, start with `--socket --skip-networking`, create the DB, run each file with the DB selected (the installer connects with the DB already selected, so files without a `USE` work).
