# 🚦 Go2My.Link — Pre-Launch Checklist, Owner Decisions & Actions

> **Purpose:** the single page to run through **before launching the service**. It captures
> (a) decisions only the owner (Lance) can make, (b) manual actions outside the codebase
> (GitHub settings, DNS, credentials, payment providers), and (c) a log of what automation
> did on your behalf. Companion to `HANDOFF.md` (technical pick-up state), `PROJECT_STATUS.md`,
> and `docs/LAUNCH_PLAN_2026-07-09.md`.
>
> **Legend:** 🔴 launch-blocking · 🟠 important · 🟢 nice-to-have · ✅ done · ⏳ in progress · ❓ needs your decision
>
> **Last updated:** 2026-08-04 (`release-candidate` branch cut from `alpha`; four-tier Dependabot + dependency-backport workflow synced onto `alpha`)

---

## ❓ Owner decisions needed (please read first)

| # | Decision | Why it matters | Options / recommendation |
|---|---|---|---|
| D1 🔴 | **How does periodic/scheduled work run on Dreamhost shared hosting?** (no assumable cron) — tracked in **#178** | Blocks GDPR **account-deletion execution (#163)** and **data-retention enforcement (#167)** — the privacy policy legally commits to both. Also affects log purging, trial expiry, subscription renewals. | (a) Dreamhost Panel cron; (b) external scheduler (cron-job.org / GitHub Actions `schedule:`) hitting a **token-guarded** `/_cron/run.php` endpoint; (c) run-on-request "lazy cron". **Recommendation: (b)** — portable, testable, provider-agnostic, works today. The *code* can be built now (the endpoint works with any trigger); only wiring the trigger is an owner action. |
| D2 🔴 | **When do we promote `alpha → beta → release-candidate → main` for the real production launch?** | `main` is **stale** (legacy engine + #93 credential file — HANDOFF warns *do not merge main*). All real work lives on `alpha` (now ~95 commits ahead). The **`release-candidate`** pre-production tier now exists (cut from `alpha` 2026-08-04). Production go-live = a deliberate promotion + cutover window. | Needs owner sign-off. Dependabot/CI now cover **all four tiers** (main/alpha/beta/release-candidate) so the mechanics are ready. **Do not** let anything merge `main` back down. |
| D3 🟠 | **Payment provider** for paid tiers (Stripe / Paddle / **SIGNula**)? | The pricing engine is provider-agnostic (`paymentProvider` column) but integration + webhooks need a concrete choice. Paddle = merchant-of-record (handles UK/EU VAT). SIGNula (your own) is an option — see the cross-project section. | Choose before enabling any paid tier. |
| D4 🟠 | **Pricing & tier sign-off** — tracked in **#180** | The flexible pricing engine is **built and merged DISABLED** (see below). Final tier names/slugs, **GBP** prices, custom-HTML tier placement, VAT handling, lifetime-deal, and the enable sequence need your approval before the master switch is flipped. | Review **`Pricing_Strategy.md`** (repo root). The engine stays inert until `billing.pricing_engine_enabled='1'`. |
| D5 🟠 | **`beta` branch drift** — finish aligning it to `alpha`? | `beta` still uses floating action tags (`@v7` for checkout) and lacks the `lint.yml` (actionlint) workflow. Dependabot already opened & I merged **#173** (setup-node 4→7 on beta), but full alignment (SHA-pin + add lint.yml) remains. | Recommend a one-off "align beta CI + SHA-pin actions" PR. Low risk. |
| D6 🟠 | **Cross-project repo access** (CueRCode, SIGNula.id) | You asked me to log/implement integration issues in those repos, but the *add-repo (clone)* step was declined, so I can't reach them yet. The required-features contract is captured in the section below so nothing is lost. | Choose: (a) re-approve add-repo so I can review their code + log/implement accurately; (b) I create issues via the GitHub API only (no clone); (c) keep the contract here and you port it. |
| D7 🟢 | **Enable the `dev-team-plugin`** | It exists as a private repo (`MWBMPartners/dev-team-plugin`) but is **not enabled as a live plugin** in this session, so its slash-commands/agents aren't directly invocable. I'm running its playbook manually (Fable-sequential analysis → Sonnet/Haiku implementation). | Enable it in Claude settings to have me drive it directly. |

---

## 🔒 Manual actions outside the codebase (owner must do in GitHub / hosting)

| # | Action | Status |
|---|---|---|
| A1 🔴 | **Rotate the legacy DB credential (#93)** that was in `web/G2My.Link/public_html_legacy/dbConfig.php` (treat as compromised) and remove/archive that legacy dir. | ❓ verify done |
| A2 🟠 | **GitHub → Settings → Security:** enable **Dependabot security updates**, **Secret scanning**, **Push protection**. Dependabot *version* updates now target **all four tiers** (main/alpha/beta/release-candidate, done in code); *security* updates are a repo setting, default-branch (`main`) only — the **backport workflow** (below) fans a merged security/dependency PR out to the other three tiers automatically. | ⏳ owner toggle |
| A3 🟠 | **Legal review** of legal pages (terms/privacy/cookies/copyright/acceptable-use) — `{{LEGAL_REVIEW_NEEDED}}` placeholders; retention promises must match what the code enforces (see #167). | ⏳ |
| A4 🟢 | Confirm **DNS / TLS** for all three domains (go2my.link, g2my.link, lnks.page) + admin subdomain before public launch. | ⏳ |
| A5 🟢 | Provide **MaxMind license/secret** if country-level analytics geolocation (#43) should be enabled (currently gated off, graceful-absent). | ⏳ |
| A6 🟠 | **Add repo secret `BACKPORT_TOKEN`** (fine-grained PAT: `contents:write` + `pull-requests:write`) so the dependency-backport workflow's cherry-pick PRs run CI and can be reviewed/merged like any PR. Without it the workflow falls back to `GITHUB_TOKEN`, whose pushes **don't trigger CI** on the new backport branch. | ⏳ owner toggle |
| A7 🟠 | **`main`'s `sftp-deploy.yml` sends an armed `release-candidate` push to PRODUCTION.** `main` lists `release-candidate` in the deploy `push:` branches (via #191) but its channel `case` has no `release-candidate)` arm, so it falls through `*) → public_html`. Before ever setting `SFTP_ENABLED=true`, either add the `release-candidate) TARGET="public_html_dev_rc"` case to `main` (as already done on `alpha`), or drop `release-candidate` from `main`'s deploy `push:` list. If you want RC to auto-deploy at all, first provision `SFTP_BASE_PATH/<Comp>/public_html_dev_rc/` on the server. (Deploy is off by default, so this is not yet live — but fix before arming.) | ⏳ owner action |

---

## 🤖 Done autonomously (2026-08-04) — four-tier CI/security + RC branch

| Change | Detail |
|---|---|
| ✅ **`release-candidate` branch cut** | New pre-production tier branched from `alpha` (at `f3646d7`). Flow is now `alpha → beta → release-candidate → main`. |
| ✅ **#191 → `main`** | Dependabot config extended to **four tiers** (adds a `release-candidate` `target-branch` entry) + new **`backport-dependencies.yml`** workflow that cherry-picks a merged dependency/security PR from `main` onto `alpha`/`beta`/`release-candidate` (opens a tracking issue on conflict). Also added `lint.yml` (actionlint) to `main` and `release-candidate` to `ci.yml`/`sftp-deploy.yml` push triggers. |
| ✅ **four-tier infra synced onto `alpha`** | `alpha` (the real source of truth) now carries the same **4-tier `dependabot.yml`** + **`backport-dependencies.yml`**, `release-candidate` in `ci.yml` push, and a **safe `release-candidate) → public_html_dev_rc` channel case** in `sftp-deploy.yml` (RC deploy is *not* auto-triggered and can never fall through to production). Eliminates alpha-vs-main infra drift. |
| ✅ **#165 → `alpha`** (#189) | Per-user analytics for individually-registered (`[default]`-org) users, with a proven data-isolation guarantee + two extra ownership-leak fixes. |
| ⏳ **New owner actions logged** | A6 (add `BACKPORT_TOKEN` secret so backport PRs run CI) and A7 (fix `main`'s `sftp-deploy.yml` RC→production fall-through before arming SFTP). |

## 🤖 Done autonomously (2026-07-22) — for your visibility

| Change | Detail |
|---|---|
| ✅ **#171 → `main`** | Combined Dependabot bumps `setup-node`→7.0.0 (#168) + `checkout`→7.0.1 (#169) into one PR; #168/#169 closed superseded. |
| ✅ **#170 → `alpha`** | Merged the full launch-prep integration into `alpha` (API v1, Component C, analytics, entitlements, GDPR export…) + the `checkout` bump. |
| ✅ **#172 → `main`** | Dependabot now covers **all three tiers** + groups bumps into one PR per branch. Verified working: it auto-opened **#173** (beta), which I merged. |
| ✅ **#174 → `alpha`** | Added this checklist + synced `dependabot.yml` onto `alpha`. |
| ✅ **#176 → `alpha`** (#175, #164) | CI now runs the **528-test unit suite** (required) + an **advisory `mysql:8` integration job**; seeded the 5 missing analytics i18n keys. |
| ✅ **#177 → `alpha`** (#166) | Short URLs can no longer be minted on **unverified** custom domains (creation now mirrors the resolver's `verificationStatus='verified'` gate → no more dead links). |
| ✅ **#179 → `alpha`** (#180) | **Flexible pricing/entitlement engine** — 10-table data-driven model (unlimited tiers/features/price structures incl. PAYG-capped, lifetime, coupons, usage metering), resolver, backfill, `Pricing_Strategy.md`. **Additive & DISABLED by default**; CI green incl. schema import. |
| ✅ **#182 → `alpha`** (#163, #167) | **GDPR scheduled-jobs engine** — token-guarded cron endpoint + jobs wiring the existing `data_rights.php` deletion/anonymisation + retention sweeps. **Ships fully inert** (all settings OFF, deletion dry-run); 45 unit + 17 integration tests. Activation is an owner action (D1). |
| ✅ **Issue hygiene** | Closed #159/#163/#164/#166/#167/#175; filed #178 (scheduling), #180 (pricing engine), #183 (MariaDB portability). Full open+closed backlog review (Fable) — **0 wrongly-closed**. |

---

## 📌 Open backlog highlights (authoritative live list in `HANDOFF.md` + the tracker)

**Launch-gating, still open:**
- 🔴 **#183** — `036_pricing_engine.sql` STORED generated column may fail to import on **MariaDB** (our production host); CI only tests `mysql:8`. Fix = explicit `CAST` + add MariaDB to CI. **Recommended next task** (also confirm target DB: MariaDB vs MySQL).
- 🟠 **#165** — individually-registered users (`[default]` org) get **no analytics** (per-user scoping fix).
- 🟠 **#158** — SFTP deploy: remaining owner decisions (base path, branch gate, re-arm after dry-run).

**Now DONE (activation is owner-gated, not code):**
- ✅ **#163 / #167** — GDPR deletion + retention engine built & merged (#182), shipped **disabled**. Enable per D1/#178.

**Quality / near-launch:** #153 (phpcs conformance + flip the gate; ~9.3k auto-fixable), #151 (UTM analytics dimension), #152 (xlsx export), #127 (orgHandle-vs-surrogate-FK design), #71 (9 locales).

**Post-launch (correctly open):** #51–#56 Phase-9 advanced redirects; #34–#37 SIGNula auth; #57–#60 billing; #139–#144 enhancements (need owner triage).

**Fixed & closed this session:** #159, #162 (verified), #163, #164, #166, #167, #175.

---

## 🔗 Cross-project integration contract (captured here until repo access is decided — D6)

> These are the **Go2My.Link-side requirements** each sister project must satisfy. Accurate from
> our code/schema; once I can reach those repos I'll open detailed issues there (or implement).

### 🔳 CueRCode — dynamic-QR service (Go2My.Link side built in #145; `cuercode.*` settings OFF)

Go2My.Link stores no `tblQRCodes`; the QR record lives in CueRCode, which drives Go2My.Link over the **public API v1** (Bearer key, `qr:link` scope). CueRCode must implement:

1. **API client** — authenticate to `https://go2my.link/api/v1/*` with a Go2My.Link API key (Bearer). Handle the pre-auth IP backoff + the standard envelope.
2. **QR-link create** — `POST /api/v1/urls` with `createdVia=cuercode` and a CueRCode-generated `qrCodeExternalUUID`; store the returned short code + UUID linkage (`tblShortURLs.qrCodeExternalID/UUID/LinkedAt` on our side).
3. **Re-point** — `PUT /api/v1/urls/<code>` to change the destination (this is what makes the QR "dynamic"); the encoded QR (which points at the short URL) is unchanged.
4. **Scan attribution** — the QR encodes the Go2My.Link short URL, so scans hit our redirect directly; we attribute via `tblActivityLog.scanSource` + `qrCodeExternalID`. CueRCode should confirm whether it needs a scan-source query param convention, or reads scan stats back via an analytics endpoint.
5. **Lifecycle** — deletion/expiry semantics when a QR or its short URL is removed (we set `createdViaAPIKeyUID` FK `ON DELETE SET NULL`).

### 🆔 SIGNula (`SIGNula.id` / `webSIGNula.com`) — auth & (optionally) payments broker

Roadmap Phases 10–11 (#34–#37 auth, #57–#59 billing). SIGNula must provide:

1. **OIDC provider endpoints** — authorize / token / userinfo / JWKS, so Go2My.Link integrates as **one** OIDC client and SIGNula brokers the downstream IdPs (Google, etc.). Register Go2My.Link as a client (redirect URIs, client credentials).
2. **(If chosen as payment provider, D3)** — a payment/subscription API + **webhooks** Go2My.Link's provider-agnostic pricing engine can consume (`paymentProvider='signula'`), covering checkout, subscription lifecycle, and refunds.

### 📎 Also present in the org (not yet wired): `MWBM-intAppsAPI`

Possible shared internal-API hub. Worth confirming whether Go2My.Link's API keys / SIGNula OIDC should route through it rather than each service integrating point-to-point.

---

_This file is maintained continuously. If a session ends unexpectedly, start here + `HANDOFF.md`._
