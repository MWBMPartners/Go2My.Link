# 🧠 Go2My.Link Project Memory

> 📦 **Portable copy** — this directory is the repo-tracked mirror of the Claude
> project memory so it travels to every machine/platform via git. See
> [README.md](../README.md) for how it maps to the device-local auto-memory.
> Last synced: **2026-07-09**.

> 🚀 **[Launch Plan 2026-07-09](../../docs/LAUNCH_PLAN_2026-07-09.md)** — Fable-5 strategic launch & roadmap plan (11 sections: verdict, API arch, CueRCode contract, SIGNula OIDC, custom-domain DNS, tier ladder, Component C, exec backlog P0–P4). **Read this for current direction.**
> 🤝 **[HANDOFF.md](../../HANDOFF.md)** — session pick-up point: what's done, in-flight, decisions pending, next steps.
> 📑 [Audit 2026-06-04](audit-2026-06-04.md) — full deployment-readiness audit; A+B launchable after fixes, C unbuilt; issues #93–#120 filed.
> 🗄️ [Installer, schema & CueRCode 2026-06](installer-schema-cuercode-2026-06.md) — web installer + 2 critical schema fixes + CueRCode dynamic-QR integration; issues #121–#128.
> 🕓 [HISTORY.md](../HISTORY.md) — chronological work log.

> ⚠️ **2026-07-09 current state (supersedes the 2026-06-05 notes below):** the
> `autopilot/2026-06-05` + `hardening/cycle-2` runs reached **VERIFY PASS / COMPLETE**
> (merged PR #130; cycle-2 = commit `46fe7a5`). **~19–24 launch-hardening issues are
> fixed in CODE but still OPEN on GitHub** — close them with commit refs. A+B are
> **code-complete for launch**. Found + fixed **3 launch-blockers** (#135 login
> `avatarURL`→`avatarPath`, #136 registration missing `NOT NULL username`, #138 GDPR export
> non-existent cols) + a column-audit sweep (0 remaining P0). Closed 21 verified-fixed issues;
> filed 6 enhancement issues (#139–144).
> 🎉 **P1 API milestone shipped (2026-07-10):** #38 framework (key auth/scopes/DB rate-limit/log/
> envelope — `web/_functions/api_auth.php` + `api_ratelimit.php`, `public_html/api/v1/`) → #39
> endpoints (URL CRUD/bulk/list + org, BOLA-safe) → #145 CueRCode wiring (QR-link create/re-point/
> scan attribution; `createShortURL()` + `logActivity()` extended) → #75 OpenAPI 3.1 + self-hosted
> Redoc at `/api/docs`. All security-reviewed; 244 unit / 74 integration green. **CueRCode integrates
> via `/api/v1` with a `qr:link`-scoped key.**
> **Remaining:** owner ops/legal deferred (#93 cred rotation, 480-URL migration, legal sign-off);
> next dev = **#40 API-key mgmt UI** (keys are backend-only today) → analytics #41/#42 → custom
> domains #91 → P2 (entitlements → SIGNula OIDC → multi-provider billing) → P3 Component C.
> See **[HANDOFF.md](../../HANDOFF.md)** for the live pick-up point. Do **NOT** merge stale local
> `main` (would resurrect the legacy engine + #93 credential file).

## 📋 Project Overview

- **Product:** Go2My.Link (URL shortening service) by MWBM Partners Ltd (MWservices)
- **Repo:** https://github.com/MWBMPartners/Go2My.Link
- **Tech Stack:** PHP 8.4+/8.5+, MySQL (MySQLi only), Bootstrap 5.3, HTML5/CSS3
- **Hosting:** Dreamhost shared hosting (no CLI/Composer)
- **3 Domains:** go2my.link (main, Component A), g2my.link (shortlinks, Component B), lnks.page (LinksPage, Component C)
- **Current state (2026-06-05):** A built through Phase 6 (v1.0.0-rc); B core redirect engine built; **C is scaffolding only** (Phase 8 not started). A+B are launchable after the launch-hardening fixes below.

## 🏗️ GitHub Project Management

- **Org-level Project:** "Go2My.Link Development" (project #4, MWBMPartners org)
  - URL: https://github.com/orgs/MWBMPartners/projects/4
  - **Must be maintained throughout development** — update issue statuses as work progresses
- **~125 issues** across **13 milestones** (Phase 0–11 + **v1.0.0 — Launch Hardening**, milestone #13)
  - #1–#92: original build (≈59 closed / 30 open as of pre-June-2026)
  - **#93–#120:** deployment-readiness audit findings (2026-06-04) — all open
  - **#121–#128:** schema-review findings (2026-06-04) — all open
- Milestone assignment: launch-blocking/high/medium audit + schema items → **v1.0.0 — Launch Hardening** (#13); low/roadmap → v1.1.0+
- Labels: phase-0..11, component-A/B/C/shared/api/database, tier-1..4, priority-*, security, bug, infrastructure, compliance, documentation, etc. (NB: **no `accessibility` label** — WCAG work uses `compliance`)
- **Issue protocol:** Create issues for all bugs/features, close with commit refs, update project board

## 📊 Build Plan & Progress (Restructured 2026-02-23)

| Phase | Version | Name | Status |
|---|---|---|---|
| 0 | v0.1.0 | Scaffolding | ✅ Complete (7 issues) |
| 1 | v0.2.0 | Database | ✅ Complete (5 issues) |
| 2 | v0.3.0 | PHP Framework | ✅ Complete (11 issues) |
| 3 | v0.4.0 | Core Product | ✅ Complete (10 issues) |
| 4 | v0.5.0 | User System: Auth & Basic Dashboard | ✅ Complete (4 issues) |
| 5 | v0.6.0 | Organisation Management | ✅ Complete (1 issue) |
| **6** | **v0.7.0** | **Compliance, Legal & Pre-Launch** | ✅ Complete (v0.7.0 + v1.0.0-rc tagged) |
| — | **v1.0.0-rc** | **PRE-RELEASE CANDIDATE** | ✅ Tagged |
| — | **v1.0.0 — Launch Hardening** | **Audit + schema remediation (#13)** | ⏳ In progress (branch `audit/launch-hardening-2026-06-04`) |
| 7 | v1.1.0 | API & Analytics | ⏳ In Progress (early work) |
| 8 | v1.2.0 | LinksPage | 🔜 6 issues (Component C unbuilt) |
| 9 | v1.3.0 | Advanced Redirects | 🔜 6 issues |
| 10 | v1.4.0 | Advanced Authentication (SIGNula) | 🔜 4 issues |
| 11 | v1.5.0 | Payments & Subscriptions (SIGNula) | 🔜 4 issues |

**Key restructuring (2026-02-23):** org mgmt (Phase 5) + legal/compliance (Phase 6) → v1.0.0-rc; post-launch phases reordered to prioritise API & Analytics; Advanced Auth and Payments deferred to SIGNula (Phases 10-11).

## 🚦 Launch Readiness (2026-06-04 audit verdict)

- **Component A (go2my.link):** 🟡 ready-with-fixes. **Component B (g2my.link):** 🔴 was blocked (legacy DB password + 2 schema bugs) — fixes applied on the branch. **Component C (lnks.page):** ⛔ not built — must NOT be advertised beyond its coming-soon landing page.
- Fix branch **`audit/launch-hardening-2026-06-04`** (commits `6897165`, `9f58807`) — not yet pushed/merged.
- 🔴 **Outstanding manual action (#93):** rotate the plaintext legacy DB credential that was in `web/G2My.Link/public_html_legacy/dbConfig.php` (never committed to git; now gitignored; treat as compromised) and remove/archive that legacy dir.
- See [audit-2026-06-04](audit-2026-06-04.md) and [installer-schema-cuercode-2026-06](installer-schema-cuercode-2026-06.md) for detail; reports in `docs/AUDIT_2026-06-04.md` and `docs/SCHEMA_REVIEW_2026-06-04.md`.

## 🧰 Installer (2026-06)

- **Web installer:** `web/Go2My.Link/public_html/install/index.php` (+ `.htaccess`) — self-locking, HTTPS-required, proof-of-control-token-gated, full-bootstrap wizard for all 3 components. Steps: requirements → DB test → import schema/procedures/seeds → create GlobalAdmin → generate keys + write shared `auth_creds.php` (0600) → lock.
- **Credential model:** the 3 components share ONE server-wide `web/_auth_keys/auth_creds.php`; each component's `<Component>/_auth_keys/auth_creds.php` is a thin `require_once` include of it. The installer writes only the shared file.
- **GlobalAdmin creation:** `g2ml_hashPassword()` (Argon2id) + INSERT into `tblUsers` (role `GlobalAdmin`, org `[default]`) + `tblUserAccountTypes` (accountTypeID `globaladmin`).
- Docs: `docs/INSTALL.md`. Runtime files `_auth_keys/.install_token` + `.installed` are gitignored.

## 🎨 Dark/Light Mode

- Bootstrap 5.3 `data-bs-theme` on `<html>`; three states (auto/light/dark); persistence localStorage `g2ml-theme` + cookie `g2ml_theme`; CSS custom props `--g2ml-*`; navbar/footer pinned dark; FOUC prevention inline `<script>` + PHP cookie. Controller: `web/Go2My.Link/public_html/js/theme.js`.

## 🚀 Phase 3 — Core Product (key files)

- Component B resolvers: `web/G2My.Link/_functions/redirect_resolver.php`, `domain_resolver.php`
- Component B error pages: `web/G2My.Link/public_html/404.php`, `expired.php`, `validating.php`
- Component B handlers: `web/G2My.Link/public_html/robots.php`, `favicon.php` (now falls back to `img/logo.png`)
- URL creation: `web/Go2My.Link/_functions/shorturl_create.php` (`createShortURL`, `rateLimit`, `verifyCaptcha`)
- API: `web/Go2My.Link/public_html/api/create/index.php` (POST JSON, CSRF, server-side CAPTCHA, no-JS fallback)
- Homepage form: `web/Go2My.Link/public_html/pages/home.php` + `js/app.js` (AJAX + copy)
- Static pages: about, features, pricing, contact, legal/{terms,privacy,cookies}
- Info page: `web/Go2My.Link/public_html/pages/info/index.php` (short code lookup)
- Settings seed: `web/_sql/seeds/006_phase3_settings.sql`

## 📡 Phase 7 — API & Analytics (early work, key files)

- **Email modernization:** `web/_functions/email.php` — multipart MIME (text/plain + AMP + HTML), dark mode, preheader, modern headers (#88)
- **Breach response:** `web/_functions/breach_response.php` — 6 functions (#89)
- **Breach admin page:** `web/Go2My.Link/_admin/public_html/pages/security/breach-response.php`
- **AMP templates:** `web/_includes/email_templates/amp/` (8 AMP4Email templates)
- **Settings seed:** `web/_sql/seeds/012_email_settings.sql`
- **Auth changes:** `loginUser()` checks `forcePasswordReset`, stores token in `$_SESSION`
- **Security audit:** issues #79–#90 created and closed (CRLF injection, path traversal, TOCTOU, transaction wrapping) — re-verified intact by the 2026-06-04 audit
- **UTM tracking (#92):** ⚠️ **NOT IMPLEMENTED** (corrected 2026-06-04). `g2ml_extractTrackingParams()` / `g2ml_appendUtmToDestination()`, migration `009_add_utm…`, activity-log UTM capture, and the `redirect.forward_utm_params` / `analytics.capture_tracking_params` settings **do not exist**. (`tblShortURLs` does have stored `utm*` columns from schema 020, but redirect-time capture/forwarding is unbuilt.) #92 remains OPEN. See [[audit-2026-06-04]].
- **API framework (#38/#39) still unbuilt:** `tblAPIKeys` is schema-only (no app code issues/verifies keys) — required before CueRCode or any external API goes live.

## 🔗 CueRCode Dynamic-QR Integration (2026-06, schema-ready)

- CueRCode is a separate first-party QR service. "Dynamic QR" = a QR encodes a Go2My.Link short code; editing the short URL's destination changes where the QR points. **No local `tblQRCodes`** — the QR record lives in CueRCode; it authenticates via `tblAPIKeys`.
- **Schema hooks (folded into base schema so fresh installs are ready):**
  - `tblShortURLs`: `createdVia` ENUM (incl. `cuercode`), `createdViaAPIKeyUID` (FK→`tblAPIKeys`, `ON DELETE SET NULL`, constraint added at end of `031_api.sql`), `qrCodeExternalID`, `qrCodeExternalUUID` (UNIQUE), `qrCodeLinkedAt`
  - `tblActivityLog`: `scanSource`, `qrCodeExternalID` (scan attribution)
  - Settings: `cuercode.*` (off by default) — seed `web/_sql/seeds/013_cuercode_settings.sql`
- **Migration (for already-deployed DBs):** `web/_sql/migrations/009_cuercode_qr_integration.sql` (clean, additive; the workflow-generated first draft was buggy `$$`-quoting and was replaced).
- Empirically verified on MySQL 9.6 (fresh import + migration on a pre-CueRCode schema). See [[installer-schema-cuercode-2026-06]].

## 🔑 Phase 4 — User System: Auth & Dashboard (key files)

- Auth engine: `web/_functions/auth.php` · Session: `web/_functions/session.php` · Email: `web/_functions/email.php`
- Email templates: `web/_includes/email_templates/` (8 HTML + 8 AMP)
- Auth pages: `web/Go2My.Link/public_html/pages/` (login, register, logout, forgot-password, reset-password, verify-email)
- Dashboard: `web/Go2My.Link/_admin/public_html/pages/` (home, links/{index,create,edit}, profile/{index,sessions})
  - ⚠️ link **edit** had a `notes`→`urlNotes` column bug (broke editing) — fixed on branch (#94)
- Password hashing: `g2ml_hashPassword()` / `g2ml_verifyPassword()` in `web/_functions/security.php` (Argon2id; bcrypt fallback)

## 🏢 Phase 5 — Organisation Management (key files)

- Core functions: `web/_functions/org.php` (18+ functions) · Invitation schema: `web/_sql/schema/014_org_invitations.sql`
- Settings seed: `web/_sql/seeds/008_phase5_settings.sql` · Email template: `org_invitation.php`
- Dashboard pages: `web/Go2My.Link/_admin/public_html/pages/org/` · Public accept: `pages/invite/index.php`
- Key permission: `canManageOrg($orgHandle)` — Admin of org OR GlobalAdmin
- Single-org model: `tblUsers.orgHandle` (`[default]` for unassigned)
- ⚠️ `UQ_org_email_pending` includes `status` → blocks re-invite cycles (#122, on branch backlog)

## ⚖️ Phase 6 — Compliance, Legal & Pre-Launch (key files) ✅

- DNT/GPC: `web/_functions/dnt.php` · Cookie consent: `cookie_consent.php` + `web/_includes/cookie_banner.php`
- Data rights: `data_rights.php` · Consent API: `api/consent/index.php` · Privacy dashboard: `_admin/.../privacy/`
- Legal pages: `legal/{terms,privacy,cookies,copyright,acceptable-use}/index.php` (`{{LEGAL_REVIEW_NEEDED}}` placeholders; need professional review)
- Settings seed: `009_phase6_settings.sql` · PWA: `manifest.json` + `sw.js` (A/Admin/C) · WCAG audit done
- Migration plan: `docs/MIGRATION_PLAN.md` + `web/_sql/dry_run.sql`
- Translations: en-GB seed (~1,075 keys); 9 locales deferred (#71)
- **Releases tagged:** v0.7.0, v1.0.0-rc

## 🏷️ Account Types (Multi-Type Support)

- `tblAccountTypes` (reference) + `tblUserAccountTypes` (junction, org-scoped). System IDs: `anonymous`, `user`, `admin`, `globaladmin`.
- Effective role cache: `tblUsers.role`, synced by `syncEffectiveRole()`. Schema `015_account_types.sql`; seed `011_account_types.sql`; functions `web/_functions/account_types.php` (`assignAccountType()` takes nullable `grantedByUserUID`, no caller-permission check).

## 🚀 CI/CD & Releases

- **PHP Lint workflow:** `.github/workflows/php-lint.yml` — uses `parallel-lint` (NOT `php-parallel-lint`) via `shivammathur/setup-php@v2`
- **Release workflow:** `.github/workflows/release.yml` — per-component releases; lint tool aligned to `parallel-lint` (#111)
- **OpenAPI/Swagger docs:** Issue #75 (Phase 7)

## 🗄️ Database — key facts & gotchas

- New DB: `mwtools_Go2MyLink` (InnoDB, utf8mb4_unicode_ci). Schema in `web/_sql/schema/` (000–035), procedures in `procedures/`, seeds in `seeds/`, data-migrations in `migrations/`.
- Redirect hot path: `sp_lookupShortURL` + `tblShortURLs.UQ_shortcode_org (shortCode, orgHandle)`.
- **Fixed on branch (2026-06-04, verified on MySQL 9.6):** `sp_lookupShortURL` declared its EXIT HANDLER before variable DECLAREs (proc wouldn't compile → broke all B redirects); `033_payments.sql` had `tblPayments` FK to `tblPaymentDiscounts` before that table was created (aborted import).
- Stored procedures use `DELIMITER //`; schema files begin with `USE mwtools_Go2MyLink;` and `000_create_database.sql` has `CREATE DATABASE`.
- Open schema-review findings: cross-org category JOIN leak (#121), migration zero-date guards (#123), short-code TOCTOU (#124), activity-log composite index (#125), `sp_logActivity` drift (#126), orgHandle-vs-orgUID FK decision (#127), alias-chain integrity (#128).

## 🗄️ Existing Data to Migrate

- 480 short URLs, 5 orgs, 7 users (plaintext passwords → force-reset), 4 categories, 429K activity-log rows (optional, batched). Skip `tblLicenses` (legacy NetPLAYER) and `tblQRCodes` (separate service). Migrations need zero-date guards (#123).

## 📁 Key Directory Notes

- Component A: `web/Go2My.Link/` (NOT `GoToMy.Link`) · Admin: `web/Go2My.Link/_admin/public_html/` → admin.go2my.link
- Shared: `web/_functions`, `web/_includes`, `web/_sql`, `web/_schemas`, `web/_auth_keys`
- BrandKit: `web/assets/BrandKit/` · Web logos: `web/{component}/public_html/img/logo.{svg,png}`
- Landing pages: `web/{component}/public_html_landing/index.php`
- ⚠️ Non-shipping web-root variants exist (`public_html_dev_alpha/_dev_beta/_redir/_landing`, and the untracked `public_html_legacy`) — hygiene risk (#112, #20-era).

## 📌 Standing Practices (Apply Every Session)

1. **🏗️ GitHub Project Board** — Update org-level project (#4) as work progresses
2. **📝 Documentation** — Keep updated: README.md, CHANGELOG.md, PROJECT_STATUS.md, DEV_NOTES.md
3. **🧠 Claude Context** — Keep `.claude/` files current (this portable memory, ProjectBrief, plans, [HISTORY.md](../HISTORY.md))
4. **🤖 OpenAI Context** — Keep `.openai/` files current if also using OpenAI/ChatGPT
5. **🚫 .gitignore** — Dynamically maintain as new tools introduced
6. **🔗 PR Linking** — When closing issues, link the corresponding PR
7. **💾 Commit Often** — Commit (but don't push) after each piece of work; user pushes manually
8. **✅ Issue Closure Protocol** — Check task checkboxes, add closing comment with commit/PR links, update board
9. **🚫 No Shorthand** — No shorthand notation in ANY language — see [patterns.md](patterns.md)
10. **🔍 Lint Everything** — Thorough syntax/lint/static analysis on ALL changes; fix all errors/warnings/recommendations

## 🔧 Key Conventions

- See [patterns.md](patterns.md) for PHP/CSS/JS/SQL coding conventions
- 📝 All `.md` files use emojis for visual readability (vocabulary in patterns.md)
