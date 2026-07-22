# 🤝 HANDOFF — Go2My.Link launch-prep cycle

> **Purpose:** durable pick-up point so any session (or a fresh start) can continue
> without re-deriving state. Companion to `docs/LAUNCH_PLAN_2026-07-09.md` (the full
> strategic plan) and `.claude/memory/MEMORY.md` (project memory).
> **Last updated:** 2026-07-22 (automation session: PR merges, Dependabot 3-tier, pricing) · earlier: 2026-07-19 (post-recovery conformance audit) · **Branch:** `alpha` (launch-prep merged in).
> **Status:** recovery + cross-device reconciliation complete and **independently verified**. A
> full conformance audit of **all 156 issues + the project brief against the actual code** then
> ran. It found **no closed issue whose code is missing** — the recovery is sound — but it did
> surface **two GDPR launch blockers** and several product/compliance gaps that were previously
> untracked. Open issues **38**. **PHPStan is an enforced CI gate.**

---

## ▶️ START HERE — pick-up point (next session / owner)

### 🗓️ 2026-07-22 update (automation session)

**Merged to `alpha` this session (in order):** #170 (launch-prep integration) → #176 (CI runs the
528-test unit suite + advisory `mysql:8` integration job; #175 + analytics i18n #164) → #177
(unverified-domain dead-link fix #166) → #179 (**flexible pricing engine**, disabled — #180).
**Merged to `main`:** #171 (combined Dependabot bumps; #168/#169 closed) + #172 (Dependabot 3-tier
+ grouped). **Merged to `beta`:** #173 (first grouped Dependabot PR — proves the 3-tier config works).

- **Pricing engine (#179/#180):** 10-table data-driven model on `alpha` — unlimited tiers/features/
  price structures (PAYG-capped, lifetime, coupons, usage metering), resolver `web/_functions/pricing.php`,
  backfill from legacy columns, `Pricing_Strategy.md`. **Additive & DISABLED** (`billing.pricing_engine_enabled='0'`);
  entitlements.php behaviour byte-unchanged until the owner flips it. Enable pending sign-off (D4).
- **GDPR launch-blockers CLOSED (#182 → alpha):** #163 (deletion execution) + #167 (retention) now
  have a **token-guarded cron endpoint** + jobs library wiring the existing `data_rights.php`
  functions, shipped **fully inert** (all `cron.*`/`gdpr.*`/`retention.*` settings OFF, deletion in
  dry-run). 45 unit + 17 integration tests. **Activation** (enable + turn dry-run off + wire a daily
  trigger) is an owner action — D1/#178. Deletion is endpoint-only; a 1-in-500 `page_init.php`
  fallback runs retention only.
- **#183 MariaDB portability (schema FIXED; CI validation deferred):** `036_pricing_engine.sql` +
  `migrations/020` used a STORED generated column with an implicit string→DATETIME coercion that
  MySQL 8 accepts but MariaDB (Dreamhost's engine) rejects. **Fixed with an explicit
  `CAST('1000-01-01 00:00:00' AS DATETIME)` (#186, green on `mysql:8`).** An attempt to add a
  `mariadb:11` CI leg (#186/#187) was **reverted** — this sandbox runner can't initialise a MariaDB
  service container (`io_uring EPERM`; it reaches "ready" then GitHub tears it down), so `alpha`'s CI
  is back to `mysql:8`-only + green. **#183 stays OPEN** for two remainders: (a) re-add a MariaDB CI
  leg on a capable runner (or start MariaDB as a step, not a GH service), (b) import the full schema
  on the real Dreamhost MariaDB once before cutover. Also landed: **#185** (gitignore agent worktrees).
- **🎉 ALL launch-gating CODE items are now CLOSED:** #163, #164, #165, #166, #167 (+ #159/#162).
  **#165 (#189):** individually-registered (`[default]`-org) users now get analytics scoped to their
  OWN links across dashboard, export, and API — plus two ownership leaks closed (the `?code=`
  drill-down and the API existence-check verified only `orgHandle`); isolation regression test green.
- **Issue review (Fable):** 158 issues, **0 wrongly-closed**. Closed #159/#163/#164/#165/#166/#167/#175;
  filed #178 (scheduling decision), #180 (pricing engine), #183 (MariaDB portability — still open).
- **Still-open next steps (non-launch-blocking):** #153 phpcs conformance (large, mechanical —
  ~9.3k `phpcbf`-auto-fixable + flip the gate; best done by a dedicated agent/clean context) →
  #183 remainder (re-add MariaDB CI on a capable runner + import once on the real Dreamhost MariaDB)
  → post-launch phases (#51–#56, #34–#37 SIGNula, #57–#60 billing) + #139–#144 triage. See
  `PRE_LAUNCH_CHECKLIST.md` for owner decisions D1–D7 + the **cross-project integration contract**
  (CueRCode / SIGNula — repo access declined via add-repo, D6).
- ⚠️ Still true: do **NOT** merge stale `main` down into `alpha`/`beta`.

**State in one line:** the codebase is in good shape and the recovery is verified, but launch is
**no longer** gated only on owner actions — the conformance audit found real code gaps, two of
them GDPR blockers (#162, #163).

⚠️ Do **NOT** merge the stale `main` (it would resurrect the legacy engine + the #93 credential
file).

### ✅ Verified independently this session (not taken on trust)

| Check | Result |
|---|---|
| Conflict markers / rebase artifacts | none |
| `php -l` across 143 files | **0 errors** |
| Unit suite | **519 passed / 0 failed** |
| PHPStan level 5 | **0 errors**, gate enforced (`continue-on-error: false`) |
| `actionlint` | clean |
| Deploy simulation (real lftp 4.9.2, seeded "server") | all live-only files survive; no secret leakage |
| All 156 issues vs actual code | 93 implemented · 34 partial · 22 not implemented · **0 needing reopen** |

### 🔴 NEW launch blockers found by the audit (were untracked)

| # | Blocker |
|---|---|
| **#162** | **GDPR data export cannot be downloaded** — the UI emits `/privacy/export?download=<uid>` but **no code anywhere reads that parameter**. The privacy policy promises this. |
| **#163** | **Account-deletion requests are never executed** — `g2ml_processDataDeletion()` and `g2ml_anonymiseUserData()` have **zero callers**. The policy commits to erasure within 30 days. |
| #167 | Privacy policy publishes retention periods (90-day log purge, etc.) that **nothing enforces** — only the API request log has any retention code. |
| #165 | **Individual (non-org) users get no analytics at all** — the dashboard hard-blocks the `[default]` org, i.e. every individually-registered user. |
| #166 | Short URLs can be minted on an **unverified** custom domain that the resolver then refuses → dead links. (Creation-path mirror of #160.) |
| #164 | Analytics dashboard renders **raw translation keys** on its four KPI tiles + CSV button (keys used but never seeded). |

Issues #162, #163 and #167 share one unanswered question: **how does periodic work run on
Dreamhost shared hosting?** (no assumable cron). Decide that once and all three become tractable.

### ✅ Fixed this session

- **#156** SFTP deploy was broken 100% — lftp parsed the `|` in `--exclude '(^|/)…'` as a pipe operator, aborting all four mirror phases. Failed closed, so nothing was ever uploaded or deleted.
- **#158** (code half) — a dry run showed **47 removals** against live Dreamhost. `--delete` dropped from the Phase-2 mirror; `_auth_keys/`, `.auth/`, `private_html/`, `.dh-diag` excluded.
- **#159** `phpstan.neon` used PHPStan 1.x-only keys, so the pinned 2.2.4 aborted on config while `continue-on-error: true` hid it — **CI had been running zero static analysis**.
- **#160** migrated partner domains landed `pending` and were unroutable (fixed + verified on MariaDB); `.auth/` rename given its installer + deploy + documented server step.
- **#161** `setSetting()` silently downgraded `isSensitive` and stored secrets in **plaintext**.
- 42 issues had every cited commit SHA remapped to live ones (the rebase invalidated them all).

### ♻️ Recovery + other-device reconciliation (2026-07-19)

- Original recovered tip `737c010` was preserved in a verified Git bundle before rewriting.
- All 72 recovered commits were rebased onto remote `launch-prep/2026-07-09` at `52d1b89`;
  the remote tip is an ancestor of the combined branch, so no force-push is required.
- The deployment-workflow resolution keeps both sets of safety work: the other device's
  quoted lftp excludes and additive Phase-2 mirror (#156/#158), plus the recovered,
  fetch-gated, non-deleting GeoIP database deployment (#43).
- The PHPStan resolution keeps the other device's PHPStan 2.x/dynamic-constant repair
  (#159) plus the recovered legacy-tree exclusions and enforced clean Level-5 gate (#76).

### 🧑‍✈️ Owner actions to unblock the A+B launch

1. **Review** the combined `launch-prep/2026-07-09` branch on GitHub (72 recovered commits on top of the latest remote work).
2. **#93** — rotate the leaked legacy DB password on the host; archive `public_html_legacy/`.
   The `dbConfig.php` file is already gone from disk, **but the password rotation is still owed** —
   do not assume it was rotated.
3. **DB migrations at cutover** (existing DB): run **`016`** (custom-HTML gating — else `getOrgTier`
   fails-open and ALL tier gating silently disables) and **`019`** (System-scope settings dedupe)
   **before** deploy; then the **`004`** 480-URL data migration + force-reset the 7 plaintext
   passwords. Do a `dry_run.sql` pass first; pick the cutover window (the real launch event).
4. **Assign paid tiers** to migrated orgs — the Free tier is ENFORCED (`maxLinks=50`,
   `maxCustomDomains=0`), so default-`free` orgs are capped until assigned.
5. **`.auth/` refactor (`98b7909`):** run the 4 `mv`s — 3 per-component `_auth_keys/` → `.auth/`
   dir renames + `dbConfig.php` → `web/_auth_keys/`.
6. **Legal sign-off** on the 5 `{{LEGAL_REVIEW_NEEDED}}` legal docs.
7. **Keep OFF until sign-off:** custom-HTML/WYSIWYG (highest stored-XSS surface) and IP
   geolocation (`analytics.geolocation_enabled` — only after confirming the CI-fetched `.mmdb` landed).

### ❓ Owner decisions still blocking P2+ (see `docs/LAUNCH_PLAN_2026-07-09.md` §11)

- Final **tier naming + currency (GBP)** → reconcile pricing page + DB seeds + gating.
- **SIGNula:** OIDC endpoints/creds; does it broker the other IdPs (collapses #34–37 into one)?
- **Billing** provider keys (Stripe / PayPal / SIGNula).
- Ratify **#149** API Low residuals 2 & 3 (per-org-vs-per-key rate limit; `maxLinks` TOCTOU) → then it closes.

### 🛠️ Dev-buildable next (no owner input — optional, if continuing to build)

- **#153** phpcs conformance (9,694 errors / 1,307 warnings / 148 files; 9,354 phpcbf-auto-fixable) —
  best as ONE dedicated, reviewed `phpcbf` reformat pass, then flip the PHPCS gate. (PHPStan gate already enforced.)
- **#151** expose captured UTM as an analytics dimension · **#152** xlsx analytics export.
- **#127** `orgHandle` immutability vs surrogate-FK migration — needs a design decision first.
- Post-launch: **#51–56** advanced redirects (Phase 9). **#34–37 / #57–60** (SIGNula auth + billing) need owner creds.

---

## 🎯 Mission (this program of work)

Take the Go2My.Link suite to a **secure, robust public launch** and build out the
post-launch roadmap the owner asked for:

1. **Launch A + B** (go2my.link + g2my.link). C stays a coming-soon landing page.
2. **API framework** (#38/#39) that covers **all** product functionality — the hard
   dependency for **CueRCode** dynamic-QR integration.
3. **Partner custom domains** (#91) + a well-documented **DNS/TLS onboarding guide**
   (Dreamhost shared + Cloudflare).
4. **SIGNula.id SSO** sign-in (thin OIDC integration; broader providers via SIGNula).
5. **Premium tiered feature system** (gating + billing).
6. **Component C** (LinksPage) build.
7. A **fresh security/lint sweep** — fix everything found, any severity/age.
8. **OpenAPI/Swagger docs** — thorough, once the API endpoints exist (capstone of P1).

**Working preferences (owner):** deep planning on **Fable 5** (sequential, not parallel);
implementation on **Sonnet/Haiku**, **Opus only when necessary**; **one GitHub issue +
one commit per piece of work** (high detail); **commit, never push** (owner pushes);
keep `.claude/` context + this HANDOFF current; steer via the **dev-team-plugin**
artifacts (`PROJECT.md`, `FEATURES.md`, `SECURITY.md`, `.dev-team/autopilot.json`).

---

## ✅ Where things actually stand (current — 2026-07-18; code wins over docs)

- **A + B are code-ready for launch.** All 2026-07-09 launch-hardening fixes verified in-tree;
  3 fresh-install P0/P1 blockers found+fixed (#135/#136/#138) + a column-audit sweep (0 P0).
- **Public API: BUILT + security-audited.** #38 framework + #39 endpoints + #40 key-mgmt UI +
  #75 OpenAPI/Redoc at `/api/docs`; a full adversarial audit (`bad789a`) passed (1 Medium fixed).
  **CueRCode integrates now** (#145) via `/api/v1` with a `qr:link` key. #149 Low residuals
  documented, awaiting owner ratification (kept open on purpose).
- **Analytics: BUILT** (#41 data + `/api/v1/analytics` + #42 dashboard, streaming CSV export #44);
  **IP geolocation BUILT** (#43, gated off, CI-fetched `.mmdb`); **UTM capture/forward BUILT**
  (#92, gated off; captured UTM not yet a dashboard dimension — follow-up **#151**).
- **Premium tiers: entitlement gating ENFORCED** (`entitlements.php` #146 — `maxLinks`/API-daily
  (per-org)/domain-cap, fail-open). Pricing-page reconcile still pending (needs owner tier naming/currency).
- **Component C (LinksPage): 6/6 BUILT** (#45/#48/#47/#46/#50/#49). `customHTML`/WYSIWYG is
  premium-gated + kill-switch OFF (needs owner security sign-off before enabling).
- **Custom domains: DONE** (#91 verification + verified-only routing + partner docs); the two-domain
  disconnect **resolved** by deprecating `tblOrgDomains` (GT-6, `migrations/018_deprecate_org_domains.sql`).
- **SIGNula / billing: zero code — need owner** (SIGNula OIDC endpoints/creds; Stripe/PayPal/SIGNula keys).
- **Auth-dir refactor:** per-component `_auth_keys/` → `.auth/` (`98b7909`); owner must run 4 `mv`s
  (3 dir renames + `dbConfig.php` → `web/_auth_keys/`). Shared `web/_auth_keys/` unchanged.
- **✅ 2026-07-18 launch-prep close-out COMPLETE:** ~22 built-but-open issues closed with evidence;
  a hygiene/a11y/db cluster fixed; **PHPStan is now an enforced CI gate** (0 shipping-code errors);
  phpcs conformance deferred to **#153**. Open issues **61 → 28** — all remaining are owner-blocked,
  post-launch-phase, or for-consideration/triage. See "Done this session (2026-07-18)" below.

## 🔴 Genuinely outstanding before an A+B launch (small)

| Item | Owner | Status |
|---|---|---|
| **#135** login blocker (`avatarURL`→`avatarPath`) + login integration test | dev | ✅ **DONE** `13f21c4` (unit 189/0, integ 22/0) |
| **#136** registration blocker (missing `NOT NULL` `username`) + register test | dev | ✅ **DONE** `f7b9e07` (unit 189/0, integ 23/0) |
| **#138** GDPR data-export broken (non-existent columns) | dev | ✅ **DONE** `602573e` fix + `cd2a337` regression test — **CLOSED** |
| Column-audit sweep (find sibling schema/code mismatches) | dev | ✅ **DONE** — 0 P0, 1 P1 (#138). Core flows all verified column-correct. |
| **#93** rotate leaked legacy DB password on host + remove `public_html_legacy/` | **owner (ops)** | pending — the leaked file itself is already gone from disk (deleted outside this repo's work, ~2026-07-10); rotation + dir archival are still owed. **Do not infer the password was rotated.** |
| Migration dry-run + full 480-URL migration; force-reset 7 plaintext passwords | dev + owner | pending |
| **Run migration `019_settings_scope_dedupe.sql`** on any existing DB before deploying (#150) | **owner (ops)** | pending — collapses duplicate System-scope settings rows via a COALESCE generated-column unique key |
| Legal sign-off on 5 `{{LEGAL_REVIEW_NEEDED}}` docs | **owner/legal** | pending |
| ~~Close ~21 fixed-but-open issues with commit refs~~ | dev | ✅ **DONE this session** — ~22 closed with evidence; open issues 61→28 |
| Doc drift: pricing USD→GBP/tier names, API envelope, DNS TXT prefix, MEMORY UTM | dev | queued |
| **#76** phpcs conformance (9,694 errors / 1,307 warnings / 148 files, 9,354 phpcbf-auto-fixable) + flip PHPCS CI gate | dev | tracked in **#153**; #76 stays open for this half (PHPStan half is done + enforced) |
| **#127** `orgHandle` tech-debt decision | dev/owner | queued (triage) |

---

## 🔄 Done this session (2026-07-18) — launch-prep close-out

Branch: **`launch-prep/2026-07-09`**, all committed, **NOT pushed**. **20 commits** landed
(`feab6b1` → `cc3e2e7`). **Open issues 61 → 28.** All dev-buildable, owner-input-free
launch-prep work is now complete.

- **Closed ~22 built-but-open issues with evidence** (code already shipped, GitHub hadn't
  caught up):
  - Phase-7/8 features: #38, #39, #40, #41, #42, #43, #45, #46, #47, #48, #49, #50, #75, #91,
    #92, #135, #136, #137, #145, #146, #147.
  - #120 (doc drift, `fd17a42`); #138 (GDPR export) closed via fix `602573e` + new regression
    test `cd2a337`.
- **Hygiene / a11y / db cleanups — built + closed:**
  - #116 favicon (`1cb9790`), #119 lang/dir (`fc3fac0`), #109 picture fallback (`832dab7`),
    #110 forced-colors (`3ff0fb0`), #112 lint excludes (`241731d`), #115 robots/sitemap
    (`4f4ae38`), #126 remove dead `sp_logActivity` (`3f43c53` + dry_run count fix `2002a66`),
    #118 client-IP helper dedupe (`1b50eef`), #114 branded error pages A/B/C (`4cb068d`),
    #150 System-scope settings dedupe via generated column + migration `019` (`6de4e0e`),
    #128 alias-chain integrity migration checks (`ce7b756`), #44 streaming CSV analytics
    export (`55bd5af`), #117 No-Shorthand house-rule sweep (`3fe0334`).
- **#76 (PHPStan) — HALF done + ENFORCED:** `phpstan.neon` repaired for phpstan 2.x + legacy
  dirs actually excluded (`42bebb1`); the resulting 45 shipping-code level-5 errors resolved
  to 0, root-cause (no `@phpstan-ignore`/baseline/widening) (`cc3e2e7`). **The CI PHPStan step
  is now a hard gate** (`continue-on-error: false`). The phpcs half (9,694 errors / 1,307
  warnings / 148 files, 9,354 auto-fixable via phpcbf) is deferred to new issue **#153**;
  #76 stays open for that half only.
  - **Two real bugs found+fixed while resolving phpstan:** (1) `public_html_landing/index.php`
    — the coming-soon page's logo `alt` text rendered blank because `$siteName` was never
    defined; fixed by defining it (`'Go2My.link'`). (2) `analytics/index.php` — the date-range
    preset ("7/30/90 days") "active" highlighting was silently dead because PHP auto-casts
    the decimal-string preset-label array keys to int, so a string-vs-int compare could never
    match; fixed with an explicit cast.
  - Introduced typed accessors `g2ml_getEnvironment()` / `g2ml_getComponent()` in
    `web/_includes/page_init.php` (root-caused a set of cross-file constant-narrowing false
    positives instead of suppressing them).
- **Left OPEN deliberately:**
  - **#149** — API Low residuals: all fixed or accept-documented; commented, awaiting owner
    ratification of residuals 2 & 3 (per-org vs per-key rate limiting; `maxLinks` TOCTOU).
  - **#76** — stays open for the phpcs half (see above).
- **New follow-up issues filed:** **#151** (expose captured UTM as an analytics dimension,
  from #92), **#152** (xlsx export via PhpSpreadsheet vs native, from #44), **#153** (phpcs
  conformance + flip the PHPCS CI gate, from #76).
- **Correction to the record (discrepancy found during reconciliation):** the leaked legacy
  `web/G2My.Link/public_html_legacy/dbConfig.php` is already **gone from disk** — deleted
  outside this repo's tracked work, roughly 2026-07-10; the rest of the legacy dir remains.
  **#93 stays open** for the actual credential **rotation** + dir archival (owner ops) — do
  **not** infer the password itself was rotated just because the file is gone.
- **New owner deploy note:** run migration **`019_settings_scope_dedupe.sql`** on any existing
  DB before deploying (collapses duplicate System-scope settings rows, adds the COALESCE
  generated-column unique key) — alongside the existing migration-016/geolocation/customHTML/
  tier-assignment notes below. Also: the `sp_logActivity` stored procedure was removed, so a
  correctly-provisioned DB now has **2** stored procedures, not 3 (`dry_run.sql` updated to
  match).
- **Remaining 28 open issues** = owner-blocked (#93 cred rotation, #71 translations, #57–60
  Phase-11 SIGNula billing, #34–37 Phase-10 SIGNula auth), post-launch phases (#51–56 Phase-9
  advanced redirects), for-consideration/owner-triage (#139–144, #149), and dev follow-ups
  (#151, #152, #153, #76 phpcs half, #127 `orgHandle` tech-debt decision).

---

## 🔄 Done this session (2026-07-09 → 07-10)

- Reverted a CI-breaking YAML typo in `.github/workflows/ci.yml` (stray indent on `uses:`).
- Full no-assumptions review of issues/milestones/project + codebase.
- **Fable 5 deep plan** → `docs/LAUNCH_PLAN_2026-07-09.md` (`082c310`).
- Fixed **3 launch-hardening defects** (each: own issue, own commit, tests):
  - **#135** login blocker `avatarURL`→`avatarPath` (`13f21c4`) + `auth_login_test.php`.
  - **#136** registration blocker — auto-derive unique `username` (`f7b9e07`) + `auth_register_test.php`.
  - **#138** GDPR data-export non-existent columns (`602573e`).
- **Column-audit sweep** across all PHP SQL vs schema DDL: **0 P0, 1 P1** (was #138). Every core
  write path (register/login/session/create-URL/redirect/org/installer) verified column-correct
  with matching bind counts — see plan for the CLEAN list.
- Owner locked 4 roadmap decisions (2026-07-09): ship A+B now + plan rest; custom-domain
  verify/routing now + manual TLS first + Cloudflare-for-SaaS later; multi-provider billing
  (Stripe + PayPal + SIGNula); SIGNula as single OIDC broker (collapses #34–37).
- Owner green-lit (2026-07-10) proceeding with ALL dev-side work; ops/legal (their actions) deferred.
- **Closed 21 verified-fixed launch-hardening issues** with evidence (#94–#124 cluster + #113).
- Filed **6 `for consideration` enhancement issues**: #139 password-protected links, #140 rich
  expiry/click-cap, #141 bulk import, #142 branded interstitials, #143 audit-log export, #144 A/B split.
- **Built + security-reviewed the API v1 framework (#38)** — `34453d8`. New: `api_auth.php`,
  `api_ratelimit.php`, `public_html/api/v1/` (front controller + ping/account handlers),
  migration 011 (composite index), seed 015, 28 unit + 16 integration tests. Key auth
  (prefix + sha256/`hash_equals`), scopes, DB rate-limiting, redacted request log, envelope.
  **Passed adversarial review** (see #38 comment); residual pre-auth IP throttle folded into #39.
- **Built API endpoints (#39)** — `0d495c1`. URL CRUD/bulk/list + org read, cursor-paginated,
  BOLA-safe org-scoping (cross-org → generic 404), pre-auth IP backoff. Found+fixed a real #38
  defect (base64url `_` in prefix broke ~1/9 keys). 234 unit / 60 integration green.
- **Built CueRCode wiring (#145)** — `50f2427`. QR-link create (kill-switch + `qr:link` scope +
  UUID-uniqueness 409), re-point, scan attribution (forge-proof); `logActivity()` hot-path INSERT
  extended safely (23=23=23 bind-string verified). 244 unit / 74 integration green.
- **Built OpenAPI/Swagger docs (#75)** — `5d229d7`. OpenAPI 3.1 spec (9 endpoints, 26 schemas,
  authored from the handlers, validated by redocly + openapi-spec-validator) + self-hosted Redoc
  at `/api/docs` (vendored 2.5.3 after catching CVE-2024-57083; directory-scoped CSP; site-wide
  CSP untouched). **Milestone: public API + CueRCode integration + API docs all READY.**

## ⏭️ Immediate next steps (execution queue — see plan §10)

> 📌 **Historical — superseded by "▶️ START HERE" at the top of this file for current next-actions.**
> Kept below as the P0–P4 roadmap record; most P0/P1 items are ✅ done (2026-07-18 close-out).

- **P0:** ✅ finish #135 (done); ✅ close ~22 issues (**done 2026-07-18**, see close-out block above,
  61→28 open); ✅ low a11y/hygiene #114–119 (**done 2026-07-18**); reconcile remaining doc drift +
  stale `main`; (owner) #93 rotation, migration, legal — still pending.
- **P1:** ✅ API framework **#38** (`34453d8`) → ✅ endpoints **#39** (`0d495c1`) → ✅ **CueRCode wiring #145**
  (`50f2427`) → ✅ **OpenAPI/Swagger #75** (`5d229d7`, spec + self-hosted Redoc at `/api/docs`) →
  ✅ **key mgmt UI #40** (`8bc8dff`, create/list/revoke, one-time secret, CSRF, `canManageOrg` authz —
  ⚠️ ordinary members can't mint keys yet) → ✅ **custom domains #91** (`c52429e`, verify + verified-only
  routing + grandfather migration + `docs/CUSTOM_DOMAINS.md`; caught a fresh-install g2my.link 404 bug) →
  ✅ **analytics data #41** (`eee8272`; closed #125) → ✅ **analytics dashboard #42** (`b90b55f`, Chart.js +
  accessible tables, theme-aware). **P1 core is essentially COMPLETE** (API + CueRCode + docs + key UI +
  custom domains + analytics).
- **P1 follow-ups (smaller):** UTM #92 (hot-path capture/forward); geo #43 **needs owner decision** (MaxMind
  GeoLite2 DB ~70MB + license — do NOT auto-build); an API adversarial security cycle over the full surface.
- **P2:** ✅ **entitlement/gating layer #146** (`9522d96`, `entitlements.php` fail-open; enforces
  `maxLinks`/API-daily/domain-cap from `tblSubscriptionTiers`; 317u/109i green). ⚠️ **Free tier now
  ENFORCED** (`maxLinks=50`, `maxCustomDomains=0`) — migrated orgs (default `'free'`) need paid tiers
  assigned or they can't create >50 links / any custom domain. → next: pricing-page reconcile + usage
  meters (needs owner: final tier naming/currency) → SIGNula OIDC (needs owner: endpoints/client creds)
  → multi-provider billing Stripe+PayPal+SIGNula (needs owner: provider keys).
- **P1 follow-ups:** ✅ **UTM #92** (`ea857f1`); ✅ **API adversarial security cycle** (`bad789a` — surface
  well-hardened; 1 Medium fixed (audit-log INSERT length-bound → closed a backoff-bypass + `error_log`
  injection); Low residuals tracked in **#149**, test-flake in **#148**); ✅ **geo #43** (`89fb2e1`, vendored
  pure-PHP MaxMind reader, gated OFF + graceful-no-op, CI-fetched `.mmdb`, country analytics widget).
- **Small tracked cleanups (buildable, low priority):** #148 test-flake; #149 API Low residuals; dormant
  `analytics.geoip_enabled` scaffolding setting (remove). ✅ **GT-6 two-domain-table
  assessment DONE** (2026-07-10) — `tblOrgDomains` deprecated (not unified): confirmed
  zero FKs/seeds/migrations/tests touch it; `org/domains/index.php` no longer accepts
  new rows; `web/_sql/migrations/018_deprecate_org_domains.sql` documents the
  owner-reviewed reconciliation path for any pre-existing rows (none expected —
  no seed/migration ever populated it). unit 502/0, integ 175/0 unchanged.
- **✅ Component C (LinksPage) — 6/6 COMPLETE:** C.1 renderer #45 (`e37134f`) · C.2 mgmt UI #48 (`40181cc`)
  · C.3 template picker + preview #47 (`1dd6634`) · C.4 custom-domain fallback #46 (`99a10c4`) · C.5 age-gate
  #50 (`d68dfc0`) · **C.6 custom-HTML/WYSIWYG #49 (`16984aa`, Opus)** — DOM allowlist sanitiser +
  `script-src 'none'` CSP + premium-gated + kill-switch OFF by default. 480u/161i green.
  Files: `web/Lnks.page/_functions/linkspage_{resolver,renderer}.php`, `web/_functions/{linkspage_manage,html_sanitiser,adult_content}.php`,
  `web/G2My.Link/_functions/linkspage_fallback.php`, `web/Lnks.page/public_html/index.php`, `_admin/.../pages/linkspage/`.
  - 🔴 **C.6 owner actions:** (1) **run migration 016 BEFORE deploying to an existing DB** — else `getOrgTier`
    fails-open (all gating disabled) because it now selects `hasCustomHTML`; (2) **keep custom-HTML OFF
    (default) until a security sign-off** — it's the product's highest XSS surface (raw user HTML).
- **✅ #147 CSRF token-overwrite FIXED** across all 4 affected pages (`83655b1` api-keys/links/org-domains,
  `bd601cd` org/short-domains) — per-row form names namespaced; `security.php` unchanged (single-use intended).
- **P3:** Component C (LinksPage) — large greenfield (renderer/UI/templates/custom-domain fallback/
  age-gate/WYSIWYG — the HTML-upload piece is the highest stored-XSS risk in the whole product).
  - 🎉 **Milestone: public API + CueRCode integration READY.** CueRCode integrates via `/api/v1` with a
    `qr:link`-scoped key. `createShortURL()` accepts `createdVia`/`createdViaAPIKeyUID`/QR columns;
    `logActivity()` carries `scanSource`/`qrCodeExternalID` (bind-string re-verified 23=23=23).
- **P2:** entitlement/gating layer → pricing/usage UI → **SIGNula OIDC** → billing (Stripe or SIGNula).
- **P3:** Component C (#45–50) → advanced redirects (#51–56).
- **P4:** roadmap + `for consideration` enhancement ideas (plan §9).

## ❓ Decisions pending from owner (block P1+ direction — see plan §11)

1. Launch sequencing — recommend **Option A (ship A+B now, fast-follow)**.
2. Does **SIGNula broker** the other IdPs (collapses #34–37 into one OIDC)?
3. **Billing:** Stripe Checkout vs SIGNula-owned billing API?
4. **Custom-domain model:** approve **Cloudflare for SaaS** (scales) vs manual Dreamhost per-domain?
5. Final **tier naming + currency** (GBP) to align DB seeds + pricing page + gating.
6. **Migration cutover window** (the real launch event; force-resets 7 users).
7. **Component C priority** — build now or defer to P3?
