# 🤝 HANDOFF — Go2My.Link launch-prep cycle

> **Purpose:** durable pick-up point so any session (or a fresh start) can continue
> without re-deriving state. Companion to `docs/LAUNCH_PLAN_2026-07-09.md` (the full
> strategic plan) and `.claude/memory/MEMORY.md` (project memory).
> **Last updated:** 2026-07-10 · **Branch:** `launch-prep/2026-07-09` (off `hardening/cycle-2-2026-07-04` @ `46fe7a5`) · **HEAD** `66b58da` (+ this handoff commit).
> **Status:** everything buildable WITHOUT owner input is DONE. Remaining work needs owner credentials/decisions (SIGNula, billing, tier naming) or is owner ops/legal (below).

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

## ✅ Where things actually stand (current — 2026-07-10; code wins over docs)

- **A + B are code-ready for launch.** All 2026-07-09 launch-hardening fixes verified in-tree;
  3 fresh-install P0/P1 blockers found+fixed (#135/#136/#138) + a column-audit sweep (0 P0).
- **Public API: BUILT + security-audited.** #38 framework + #39 endpoints + #40 key-mgmt UI +
  #75 OpenAPI/Redoc at `/api/docs`; a full adversarial audit (`bad789a`) passed (1 Medium fixed).
  **CueRCode integrates now** (#145) via `/api/v1` with a `qr:link` key.
- **Analytics: BUILT** (#41 data + `/api/v1/analytics` + #42 dashboard); **IP geolocation BUILT**
  (#43, gated off, CI-fetched `.mmdb`); **UTM capture/forward BUILT** (#92, gated off).
- **Premium tiers: entitlement gating ENFORCED** (`entitlements.php` #146 — `maxLinks`/API-daily
  (per-org)/domain-cap, fail-open). Pricing-page reconcile still pending (needs owner tier naming/currency).
- **Component C (LinksPage): 6/6 BUILT** (#45/#48/#47/#46/#50/#49). `customHTML`/WYSIWYG is
  premium-gated + kill-switch OFF (needs owner security sign-off before enabling).
- **Custom domains: DONE** (#91 verification + verified-only routing + partner docs); the two-domain
  disconnect **resolved** by deprecating `tblOrgDomains` (GT-6, `migrations/018_deprecate_org_domains.sql`).
- **SIGNula / billing: zero code — need owner** (SIGNula OIDC endpoints/creds; Stripe/PayPal/SIGNula keys).
- **Auth-dir refactor:** per-component `_auth_keys/` → `.auth/` (`98b7909`); owner must run 4 `mv`s
  (3 dir renames + `dbConfig.php` → `web/_auth_keys/`). Shared `web/_auth_keys/` unchanged.

## 🔴 Genuinely outstanding before an A+B launch (small)

| Item | Owner | Status |
|---|---|---|
| **#135** login blocker (`avatarURL`→`avatarPath`) + login integration test | dev | ✅ **DONE** `13f21c4` (unit 189/0, integ 22/0) |
| **#136** registration blocker (missing `NOT NULL` `username`) + register test | dev | ✅ **DONE** `f7b9e07` (unit 189/0, integ 23/0) |
| **#138** GDPR data-export broken (non-existent columns) | dev | ✅ **DONE** `602573e` (php -l clean, unit 189/0) — integration test still TODO |
| Column-audit sweep (find sibling schema/code mismatches) | dev | ✅ **DONE** — 0 P0, 1 P1 (#138). Core flows all verified column-correct. |
| **#93** rotate leaked legacy DB password on host + remove `public_html_legacy/` | **owner (ops)** | pending — instruct-don't-execute |
| Migration dry-run + full 480-URL migration; force-reset 7 plaintext passwords | dev + owner | pending |
| Legal sign-off on 5 `{{LEGAL_REVIEW_NEEDED}}` docs | **owner/legal** | pending |
| Close ~21 fixed-but-open issues with commit refs (#94–#124 cluster + #113) | dev | ⏸ **awaiting owner go-ahead** (guardrail blocked mass close) |
| Doc drift: pricing USD→GBP/tier names, API envelope, DNS TXT prefix, MEMORY UTM | dev | queued |
| Data-export integration test (`#138` follow-up) | dev | queued |

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

- **P0:** finish #135; close ~24 issues; reconcile doc drift + stale `main`; low a11y/hygiene
  (#114–119); (owner) #93 rotation, migration, legal.
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
