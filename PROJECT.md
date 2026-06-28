# 🔗 Go2My.Link — Autopilot Project Record

> Machine-and-human source of truth for the `dev-team-autopilot` run on
> branch `autopilot/2026-06-05`. The conductor's machine state lives in
> `.dev-team/autopilot.json`; this file is the human-readable mirror. British
> English (en-GB) throughout. Emoji headings follow `.claude/memory/patterns.md`.

## 🎯 Goal

Go2My.Link is a multi-component URL-shortening platform by MWBM Partners Ltd
(MWservices), deployed on **Dreamhost shared hosting** (PHP 8.4+/8.5+, MySQLi,
Bootstrap 5.3, vanilla JS; no Composer/CLI assumed). It comprises three
components on three domains: **A = go2my.link** (the main marketing site, URL
creation, user/org dashboard, and `admin.go2my.link`), **B = g2my.link** (the
shortlink redirect router), and **C = lnks.page** (LinksPage — largely unbuilt
scaffolding, Phase 8). **The launch focus is Components A + B**; Component C must
not be advertised beyond its coming-soon landing page.

The aim of this run: drive A + B to a clean, defensible production launch by
resolving the launch-hardening backlog (audit + schema findings, issues
#93–#128), then build or queue the in-scope feature gaps — without violating the
house rules below.

## ✅ Definition of done

- [ ] All launch-blocking audit/schema items (#93–#128) resolved.
- [ ] No unresolved Critical or High security finding.
- [ ] Lint clean (`parallel-lint`, PHP 8.4) across shipping code; PHPStan /
      PHP_CodeSniffer advisories triaged.
- [ ] Core flows verified live on MySQL: shorten → redirect (A create → B
      resolve), auth/login, dashboard link CRUD, installer schema import.
- [ ] In-scope feature gaps built or explicitly queued (Feature Spec Gate /
      conductor gate) — large roadmap features tracked in `FEATURES.md`, not built
      unprompted.
- [ ] Documentation sufficient to install and run (INSTALL.md, README, schema
      import path, credential model).

## 🔒 Constraints

House rules (verbatim-important — see `.claude/memory/patterns.md` for the full
list; these OVERRIDE default behaviour):

- 🚫 **No shorthand notation in ANY language.** Full `if/else` with Allman braces.
  Banned: PHP alternative/template syntax (`if(): … endif;`), ternary
  (`a ? b : c`), Elvis (`?:`), `|| default`, braceless single-line `if`, one-line
  braceless arrow functions / implicit returns, short-echo `<?=`, short-open-tag
  `<?`. `??` (null-coalescing) is allowed **only when both sides are simple
  values** (no function-call operand).
- 🗄️ **MySQLi only** (no PDO); **prepared statements for every query**; InnoDB +
  `utf8mb4_unicode_ci`; sensitive values **AES-256-GCM** encrypted with SALT.
- 🔑 **DB credentials live only in `web/_auth_keys/auth_creds.php`** (the three
  components share one server-wide file via thin `require_once` includes). **Never
  committed.** Never write/commit real credentials.
- ♿ **WCAG 2.1 AA** built-in, not retrofitted (labelled `compliance` in issues —
  there is **no `accessibility` label**).
- 🌍 **All UI strings via `__('key')`** (dot-notation keys; `{name}` placeholders);
  base language en-GB.
- 🌓 **Dark/light via `data-bs-theme`** (three states auto/light/dark; CSS custom
  props `--g2ml-*`; navbar/footer pinned dark).
- 🔍 **Lint everything**; fix all errors/warnings/recommendations.
- 💾 **Commit per cycle, NEVER push.** The user pushes manually. Never run
  destructive git ops, never modify `.git/config`, never merge to `main` without
  explicit go-ahead. Outward-facing / credentialled ops are instruct-don't-execute.
- 🧪 A local throwaway **MySQL 9.6** (`/opt/homebrew/opt/mysql/bin/mysqld`) is
  available for empirical DB tests (schema import, stored-proc compile, redirect
  hot-path).

## 🧰 Skills in play

- **dev-team-autopilot** (`~/.claude/plugins/cache/dev-team/…/dev-team-autopilot`)
  → conductor of the whole run (DISCOVER → STABILIZE → … loop).
- **dev-team-iterate** → improvement cycles against the B- backlog below.
- **dev-team-security** → adversarial security audit / remediation (XFF spoofing,
  SSRF, CRLF, CSRF, cross-org leak findings).
- **dev-team-review** → independent verification of completed cycles.
- **dev-team-featurefind** → competitive feature-gap discovery → `FEATURES.md`.
- Project house rules: `.claude/memory/patterns.md` → governs all code style.

## 🎨 House style (existing-repo conventions)

- **Language/stack:** PHP 8.4+/8.5+ (8.4 backward-compat via `version_compare()`),
  MySQLi, Bootstrap 5.3, HTML5/CSS3, vanilla JS. No build step; CDN-first with
  local fallback for libraries.
- **Lint / static analysis:** `parallel-lint` (NOT `php-parallel-lint`) via
  `shivammathur/setup-php@v2` in `.github/workflows/php-lint.yml`; PHPStan
  (`phpstan.neon`) and PHP_CodeSniffer (`phpcs.xml`) advisory. No unit-test
  framework in the repo — verification is empirical (run the SQL, exercise the
  flow). There is **no automated test suite or coverage number**.
- **Directory layout:** private dirs use a leading underscore (`_functions`,
  `_includes`, `_sql`, `_auth_keys`, `_libraries`, `_schemas`). Web roots:
  `public_html` (prod), `public_html_dev_alpha`, `public_html_dev_beta`,
  `public_html_landing`, `public_html_redir` (and the non-shipping stray
  `public_html_legacy` under Component B).
- **Naming:** `tblPascalCase` tables, `camelCase` columns, `IDX_/UQ_/FK_` index
  prefixes; PHP global functions inconsistently prefixed `g2ml_` (≈51 of ≈154 —
  standardisation is backlog item B-024).
- **No `.php` in URLs** — `.htaccess` rewrites / directory routing.
- **Debug mode:** `?debug=true`; errors → `tblErrorLog`, activity → `tblActivityLog`.

## 🗺️ Stage plan

1. **DISCOVER** — reverse-engineer state, build the Codebase Map + scored
   backlog, seed `PROJECT.md`/`FEATURES.md`. — status: **in-progress**
2. **STABILIZE** — clear correctness + security launch-blockers (the High-impact
   B- items: #93 rotation, #95 XFF, #121 cross-org leak, #94/#122/#123/#124
   bugs). Verify shorten→redirect + auth + link CRUD live on MySQL. — status:
   planned
3. **HARDEN** — remaining security/a11y items (#96–#110, SSRF, CSRF, reduced-motion,
   contrast), then standards/lint cleanup (#117–#119, B-024/B-025). — status:
   planned
4. **FEATURE-FILL (in-scope)** — close in-scope feature gaps surfaced by audit
   §7.2/§8 and `FEATURES.md` via the conductor gate (e.g. #23 info-page auth view,
   #30 dashboard custom suffix/alias/tags, #91 custom-domain resolution). Large
   roadmap (Phases 7–11, Component C) stays queued. — status: planned
5. **VERIFY & DOCUMENT** — independent review pass; finalise install/run docs;
   confirm Definition of done. — status: planned

## 📌 Current status

- **Active stage:** 1 — DISCOVER (bootstrap complete; seeding artefacts).
- **Done so far:** Bootstrap complete. Codebase Map written and spot-verified
  against the tree. Backlog re-derived from the live GitHub issues (#1–#128) and
  the two 2026-06-04 audits. Confirmed via direct code inspection that commit
  `6897165` already landed several launch-hardening fixes (see Decision log).
- **In progress / next:** Finish DISCOVER seeding (this file + `FEATURES.md`).
  Next phase: **STABILIZE** — start with the residual launch-blockers that are
  *not* yet fixed (#93 manual credential rotation, #95 XFF trust, #121 cross-org
  category leak, #122/#123/#124 schema/migration bugs).
- **Open threads:**
  - 🔴 **#93 manual action outstanding** — the plaintext legacy DB credential in
    `web/G2My.Link/public_html_legacy/dbConfig.php` is now gitignored and was
    never committed, **but the file still physically exists in the working tree
    and the credential has not been confirmed rotated.** Treat as compromised;
    rotation is instruct-don't-execute (user runs it).
  - GitHub issues #93–#128 are all still marked **OPEN** even though some are
    fixed on-branch — they need closing-comment + commit refs once verified.
  - Component C (lnks.page) is scaffolding only; must not be advertised.
  - No automated test suite — every fix needs empirical verification evidence.

## 🧾 Decision log

### 2026-06-05 — Treat audit/schema fixes in commit `6897165` / `9f58807` as done-on-branch but issues still open

- **Decision:** The backlog records which #93–#128 items are already fixed on
  this branch (verified by reading the post-fix code), while noting the GitHub
  issues remain OPEN until closed with commit refs per the project's issue
  protocol.
- **Options considered:** (a) trust the issue OPEN state and re-do the work
  (wasteful, risks regressions); (b) trust the audit's "fixed" claims blindly
  (violates evidence-not-claims). Chose (c): verify each claimed fix against the
  actual code now.
- **Reason:** Evidence-not-claims discipline. Verified in code: #94 (link-edit
  uses `urlNotes`), #96 (contact CAPTCHA verified server-side), #97 (subject
  CR/LF/NUL stripped before the Subject header), #102 (favicon falls back to
  `logo.png`), #103 (CSP permits the CDN/inline the error pages use), #111
  (release.yml uses `parallel-lint`), #93/#112 (.gitignore guards present), plus
  both schema criticals (`sp_lookupShortURL` handler order; `tblPaymentDiscounts`
  before `tblPayments`). Confirmed NOT yet fixed: #93 rotation (manual), #95 XFF,
  #23, #30, #91, #104/#105/#106, #99/#100/#101, #98, #121–#128 (most).
- **Revisit if:** independent review (`dev-team-review`) disproves any "fixed"
  claim with fresh evidence — that item returns to the backlog as a regression.

### 2026-06-05 — Launch scope = Components A + B only

- **Decision:** Definition of done targets A + B for launch; Component C and
  Phases 7–11 stay roadmap (tracked in `FEATURES.md`, not built unprompted).
- **Options considered:** ship all three (C is unbuilt — not viable); ship A only
  (B is the redirect engine, inseparable from the product).
- **Reason:** Matches the 2026-06-04 audit verdict and the project brief's
  minimum-launchable-product framing.
- **Revisit if:** the user expands scope to include Component C / a roadmap phase.

## ⛳ Checkpoint log

_(none yet — first checkpoint will be recorded at the end of STABILIZE)_

## 🧩 Feature Specs

<!-- one block per approved/auto-cleared feature, anchored #spec-<gate-id>.
     Populated by the conductor gate or the Feature Spec Gate. Empty at DISCOVER. -->

_(none yet)_

---

## 🗂️ Codebase Map  (cached reference — delta-updated each run)

- **map-commit:** `7b67ad5`    **updated:** 2026-06-05
- **Stack & build:** PHP 8.4+/8.5+, MySQLi (no PDO), Bootstrap 5.3, vanilla JS.
  No build/bundler; CDN-first with local fallback. Deploy = SFTP to Dreamhost
  (`.github/workflows/sftp-deploy.yml`: alpha→`public_html_dev_alpha`,
  beta→`public_html_dev_beta`, prod→`public_html`). Lint via
  `php-lint.yml` (`parallel-lint`). No test framework / coverage.
- **Architecture:** Three independent web properties sharing one code spine.
  - **Component A — `web/Go2My.Link/`** (NOT `GoToMy.Link`): marketing site +
    URL creation + user/org dashboard. Public root `public_html/`; admin root
    `web/Go2My.Link/_admin/public_html/` → `admin.go2my.link`.
  - **Component B — `web/G2My.Link/`**: shortlink redirect router (g2my.link).
    Core resolvers in `_functions/` (`redirect_resolver.php`,
    `domain_resolver.php`); error/handler pages in `public_html/`.
  - **Component C — `web/Lnks.page/`**: LinksPage. **Scaffolding only** —
    `public_html/index.php` reads `?slug`, logs `linkspage_view`/`not_implemented`
    and returns a 404 placeholder. DB tables `tblLinksPages*` have zero PHP refs.
  - **Shared spine (`web/`):** `_functions/` (18 modules), `_includes/`
    (header/footer/nav/cookie_banner/accessibility/email templates), `_sql/`
    (schema/procedures/seeds/migrations), `_schemas/` (JSON Schemas),
    `_auth_keys/` (the single shared `auth_creds.php` — gitignored),
    `_libraries/` (vendored Bootstrap/FA with CDN fallback), `assets/BrandKit/`.
- **Surfaces:**
  - A public: home (shorten form + AJAX/no-JS), about, features, pricing,
    contact, info/preview, legal/{terms,privacy,cookies,copyright,acceptable-use},
    auth pages (register/login/logout/forgot/reset/verify-email), landing page.
  - A admin (`_admin`): dashboard home, links/{index,create,edit}, profile/
    {index,sessions}, org/*, invite/accept, privacy/* (data rights, delete),
    security/breach-response. Web installer at `public_html/install/`.
  - B: redirect (`/<code>`), 404.php, expired.php, validating.php, robots.php,
    favicon.php, landing page.
  - C: coming-soon landing only.
- **Data model:** DB `mwtools_Go2MyLink` (InnoDB, utf8mb4_unicode_ci). Schema
  files `web/_sql/schema/000–035` (core settings/tiers/orgs/users/invitations/
  account-types; shorturls+categories+tags; advanced-redirects; analytics; api;
  linkspage; payments; legal; translations). Stored procedures
  `sp_generateShortCode`, `sp_logActivity`, `sp_lookupShortURL`. 13 seeds, 8
  migrations (incl. `009_cuercode_qr_integration`). Hot path:
  `sp_lookupShortURL` + `tblShortURLs.UQ_shortcode_org (shortCode, orgHandle)`.
  Core entities: `tblUsers` (single-org via `orgHandle`, `[default]` = unassigned),
  `tblOrganisations`, `tblShortURLs`, `tblCategories`, `tblActivityLog` (~429K
  legacy rows to migrate), `tblAccountTypes`/`tblUserAccountTypes`, `tblAPIKeys`
  (schema-only — #38 unbuilt). Existing data to migrate: 480 short URLs, 5 orgs,
  7 users (plaintext pw → force-reset), 4 categories.
- **Key modules / file map:**
  - Redirect/resolve: `web/G2My.Link/_functions/redirect_resolver.php`,
    `domain_resolver.php`; `sp_lookupShortURL.sql`.
  - URL creation: `web/Go2My.Link/_functions/shorturl_create.php`
    (`createShortURL`, `rateLimit`, `verifyCaptcha`); API
    `public_html/api/create/index.php`.
  - Auth/session: `web/_functions/auth.php`, `session.php`, `security.php`
    (Argon2id `g2ml_hashPassword`/`verifyPassword`, AES-256-GCM, CSRF, sanitisers,
    `g2ml_getClientIP`).
  - DB: `web/_functions/db_connect.php` (MySQLi singleton), `db_query.php`
    (prepared-statement wrappers), `settings.php` (DB-driven, encrypted
    `isSensitive`).
  - Org: `web/_functions/org.php` (18+ fns; `canManageOrg`). Account types:
    `account_types.php`. Email: `email.php` (multipart MIME + AMP). Breach:
    `breach_response.php`. Compliance: `dnt.php`, `cookie_consent.php`,
    `data_rights.php`. i18n: `i18n.php`. Routing: `router.php`. Logging:
    `activity_logger.php`, `error_handler.php`.
  - Theme controller: `web/Go2My.Link/public_html/js/theme.js`.
  - Installer: `web/Go2My.Link/public_html/install/index.php` (+ `.htaccess`).
- **Conventions (house style):** see `## House style` above and
  `.claude/memory/patterns.md` (the no-shorthand rule is the dominant constraint).
- **External integrations:** Cloudflare Turnstile / Google reCAPTCHA (bot
  protection); `mail()` / multipart-MIME email; DNS TXT verification for org
  custom domains; **CueRCode** (first-party dynamic-QR service, schema-ready,
  gated on the unbuilt API framework #38); SFTP deploy to Dreamhost.
- **Known characteristics:** Redirect hot path is the perf-sensitive surface
  (single indexed row read via `sp_lookupShortURL`). Fragile areas: the
  `public_html_*` web-root proliferation (hygiene risk, incl. stray
  `public_html_legacy` with a live credential), migration zero-date handling
  under STRICT mode, short-code generation TOCTOU, and code/doc drift (MEMORY.md
  once over-claimed UTM forwarding). No automated tests → regressions are easy to
  introduce silently; verify empirically.

## 🏃 Run record

- **last-run:** 2026-06-05
- **map-commit:** `7b67ad5`
- **cycles-done:** 0  (DISCOVER seeding; STABILIZE not yet started)
- **branch:** `autopilot/2026-06-05`
- **working tree at seed:** clean except untracked `.claude/agents/`,
  `.claude/settings.json` (autopilot scaffolding — not production code).

## 📋 Backlog

Scored, ordered improvement items (`B-` namespace). Derived from the live GitHub
issues (#93–#128 launch-hardening + selected closed-but-partial), the
2026-06-04 deployment audit, and the schema review. **Large roadmap features
(Phases 7–11, Component C build) are NOT here — they live in `FEATURES.md`.**
Items marked **✅ fixed-on-branch** were verified in code (commit `6897165` /
`9f58807`) but their GitHub issues are still OPEN pending closing refs.

Ordered High → Medium → Low; within a tier, correctness/security first.

### 🔴 High impact

- **id:** B-001
  **title:** Rotate leaked legacy production DB password; remove/archive `public_html_legacy/`
  **category:** security · **impact:** High · **component:** B/config
  **source:** #93 · **status:** OPEN (manual — instruct-don't-execute). `.gitignore`
  guards landed (✅) and file was never committed, but the plaintext credential
  still exists in the working tree and is unrotated — treat as compromised.

- **id:** B-002
  **title:** Cross-org category leak — short-URL↔category JOIN omits `orgHandle`
  **category:** security · **impact:** High · **component:** A/shared (database)
  **source:** #121, schema-review §High · **status:** OPEN. Category IDs are only
  per-org-unique; admin list can fan out and public info page can show another
  org's category name. Fix: add `AND s.orgHandle = c.orgHandle`, or surrogate
  `categoryUID` FK.

- **id:** B-003
  **title:** `g2ml_getClientIP()` trusts spoofable X-Forwarded-For / X-Real-Ip with no trusted-proxy check
  **category:** security · **impact:** High · **component:** shared
  **source:** #95 · **status:** OPEN. Docblock claims a trusted-proxy check the
  code does not perform; poisons activity/consent/error/breach logs, `lastLoginIP`,
  new-login-alert heuristic, and contact-form rate-limit. Fix: only honour XFF/XRI
  when `REMOTE_ADDR` is in a trusted-proxy allowlist.

- **id:** B-004
  **title:** Org invitations: re-invite blocked by `UQ_org_email_pending` including `status`
  **category:** correctness · **impact:** High · **component:** A (database)
  **source:** #122, schema-review §High · **status:** OPEN. A second cancel/expire
  for the same org+email collides → re-invite cycles fail. Fix: enforce one
  PENDING per org+email via generated/partial key.

- **id:** B-005
  **title:** Data migration: guard zero-date / NULL legacy dates under STRICT sql_mode
  **category:** correctness · **impact:** High · **component:** database
  **source:** #123, schema-review §High · **status:** OPEN. A single
  `0000-00-00`/NULL legacy row aborts the whole batch (risks the 480-URL
  migration). Fix: `IFNULL(NULLIF(old.date,'0000-00-00 00:00:00'), NOW())` +
  post-migration row-count assertion.

- **id:** B-006
  **title:** Short-code generation TOCTOU — retry on unique-key collision
  **category:** correctness · **impact:** High · **component:** B/shared
  **source:** #124, schema-review §High · **status:** OPEN. `sp_generateShortCode`
  is check-then-insert with no retry; concurrent creates surface a generic
  failure. Fix: catch errno 1062 in `createShortURL()` and retry bounded.

- **id:** B-007
  **title:** Landing pages auto-refresh every 15 min (`<meta refresh content="900">`) with no pause (WCAG 2.2.1 Level A)
  **category:** a11y · **impact:** High · **component:** A/B/C
  **source:** #104, audit §4 · **status:** OPEN (confirmed present in all three
  `public_html_landing/index.php`). Reloads discard the email field and reset
  focus. Fix: remove the meta refresh, or replace with a visibility/focus-aware,
  reduced-motion-disabled JS reload.

- **id:** B-008
  **title:** Custom-domain resolution incomplete — `getOrgByDomain()` queries only `tblOrgShortDomains`
  **category:** correctness · **impact:** High · **component:** B/shared
  **source:** #91, audit §7.2 · **status:** OPEN (closed-but-partial). The
  `tblOrgDomains(redirect)` table is not wired into resolution; `docs/CUSTOM_DOMAINS.md`
  absent. In-scope feature gap for the A+B launch.

- **id:** B-009
  **title:** Link-edit silently failed — UPDATE referenced non-existent column `notes`
  **category:** correctness · **impact:** High · **component:** A (database)
  **source:** #94 · **status:** ✅ fixed-on-branch (verified: SELECT/UPDATE now use
  `urlNotes`). Issue still OPEN — close with commit ref `6897165`.

### 🟡 Medium impact

- **id:** B-010
  **title:** Contact form had no server-side CAPTCHA verification (spoofable IP rate-limit only)
  **category:** security · **impact:** Medium · **component:** A
  **source:** #96 · **status:** ✅ fixed-on-branch (verified: server-side
  Turnstile/reCAPTCHA block present). Issue OPEN — close with ref.

- **id:** B-011
  **title:** Contact-form subject allowed CRLF header injection into `mail()`
  **category:** security · **impact:** Medium · **component:** A
  **source:** #97 · **status:** ✅ fixed-on-branch (verified: CR/LF/NUL stripped to
  `$safeSubject` before the Subject header; raw subject only appears in the body
  after the blank-line separator). Issue OPEN — close with ref.

- **id:** B-012
  **title:** Interstitial pages emit redirect destination into href/JS without the http(s) scheme guard
  **category:** security · **impact:** Medium · **component:** B
  **source:** #99, audit §3.2 · **status:** OPEN. `validating.php`/`expired.php`
  lack `buildRedirectResponse()`'s `^https?://` allowlist — defends against
  `javascript:` schemes in migrated legacy URLs.

- **id:** B-013
  **title:** Harden against SSRF — guard server-side destination fetch and validate created URLs against internal/private hosts
  **category:** security · **impact:** Medium · **component:** B/shared
  **source:** #100, audit §3.3 · **status:** OPEN. `validateDestination()` HTTP
  HEAD and `createShortURL()` accept internal/loopback/link-local hosts. OFF by
  default today; authenticated SSRF oracle if the fetch is enabled.

- **id:** B-014
  **title:** Path-traversal latent in dynamic favicon handler — confine `readfile()` to uploads dir
  **category:** security · **impact:** Medium · **component:** B
  **source:** #101, audit §3.2 · **status:** OPEN. DB `orgLogoPath` concatenated
  into `readfile()` with no guard; latent until the org-logo upload feature lands.

- **id:** B-015
  **title:** Account-deletion cancellation is a state-changing GET with no CSRF token
  **category:** security · **impact:** Medium · **component:** A
  **source:** #98, audit §3.3 · **status:** OPEN. Convert to a CSRF-protected POST
  or signed one-time token.

- **id:** B-016
  **title:** `sp_logActivity` drifts from schema (missing cols; NOT NULL ipAddress) — sync or remove
  **category:** correctness · **impact:** Medium · **component:** database
  **source:** #126, schema-review §Medium · **status:** OPEN. Currently dead code
  (app uses a direct INSERT); errors swallowed. Sync to current schema or remove.

- **id:** B-017
  **title:** `tblActivityLog` add `(shortCode, createdAt)` composite index; decide partitioning
  **category:** quality (perf) · **impact:** Medium · **component:** database
  **source:** #125, schema-review §Medium, audit §7.2 (#12) · **status:** OPEN.
  Needed before analytics (Phase 7) but cheap and safe to add now.

- **id:** B-018
  **title:** Decide `orgHandle` immutability vs migrate child FKs to surrogate `orgUID`
  **category:** quality (tech-debt) · **impact:** Medium · **component:** database
  **source:** #127, schema-review §Medium · **status:** OPEN. FKs target the
  mutable business key; a handle rename cascades widely. Decide pre-launch.

- **id:** B-019
  **title:** Public-vs-authenticated info/preview view not implemented (#23) — always masks destination
  **category:** correctness (feature gap) · **impact:** Medium · **component:** A
  **source:** #23, audit §7.2 · **status:** OPEN (closed-but-partial). No
  `isAuthenticated()` branch in `pages/info/index.php`. In-scope.

- **id:** B-020
  **title:** Dashboard create/edit do not expose custom suffix, alias, or tags (#30)
  **category:** correctness (feature gap) · **impact:** Medium · **component:** A
  **source:** #30, audit §7.2/§8.3 · **status:** OPEN (closed-but-partial). The
  brief's headline authenticated feature is absent from the forms. In-scope.

- **id:** B-021
  **title:** Alias-chain integrity: app-level cycle/target validation; `destinationType` unused
  **category:** correctness · **impact:** Medium · **component:** B
  **source:** #128, schema-review · **status:** OPEN. Max-3-hop chain has no
  cycle/target validation at the app layer.

- **id:** B-022
  **title:** Main site (app.js + style.css) ignores `prefers-reduced-motion`
  **category:** a11y · **impact:** Medium · **component:** A
  **source:** #106, audit §4 (WCAG 2.3.3) · **status:** OPEN. Add a global
  reduced-motion CSS block and gate the smooth-scroll in JS.

- **id:** B-023
  **title:** Landing-page countdown ring keeps animating under `prefers-reduced-motion`
  **category:** a11y · **impact:** Medium · **component:** A/B/C
  **source:** #105, audit §4 · **status:** OPEN. CSS query gives a false
  impression of compliance while the rAF ring keeps sweeping.

- **id:** B-024
  **title:** Per-second countdown announcements on B interstitials spam screen readers
  **category:** a11y · **impact:** Medium · **component:** B
  **source:** #107, audit §4 (WCAG 4.1.3) · **status:** OPEN. Remove `aria-live`
  from the visible per-second counter; rely on the throttled status region.

- **id:** B-025
  **title:** `bg-secondary` badges fall below 4.5:1 contrast and are used pervasively
  **category:** a11y · **impact:** Medium · **component:** A
  **source:** audit §4 (WCAG 1.4.3) · **status:** OPEN. Darken the secondary badge
  (≈`#565e64`); verify `bg-info`/`bg-warning`.

- **id:** B-026
  **title:** Go2My.Link landing logo has empty `alt` text (undefined `$siteName`)
  **category:** a11y · **impact:** Medium · **component:** A
  **source:** #108, audit §4 (WCAG 1.1.1) · **status:** OPEN. Use a literal
  `alt="Go2My.link"` or define `$siteName`.

- **id:** B-027
  **title:** Component B default favicon always 404'd (`favicon_default.ico` missing)
  **category:** correctness · **impact:** Medium · **component:** B
  **source:** #102, #116 · **status:** ✅ fixed-on-branch (verified: falls back to
  `img/logo.png`). Issues OPEN — close with ref.

- **id:** B-028
  **title:** Component B error pages loaded CDN CSS their own CSP forbids (rendered unstyled)
  **category:** correctness · **impact:** Medium · **component:** B
  **source:** #103 · **status:** ✅ fixed-on-branch (verified: `.htaccess` CSP now
  permits the CDN origins + inline FOUC/countdown scripts). Issue OPEN — close.

- **id:** B-029
  **title:** Align `release.yml` PHP-lint tool with `php-lint.yml` (`parallel-lint`)
  **category:** quality (CI) · **impact:** Medium · **component:** config
  **source:** #111 · **status:** ✅ fixed-on-branch (verified: `release.yml` uses
  `parallel-lint`). Issue OPEN — close.

- **id:** B-030
  **title:** Add `.gitignore` guards + lint/analysis exclusions for non-shipping `public_html_*` variants
  **category:** quality (hygiene) · **impact:** Medium · **component:** config
  **source:** #112, audit §6 · **status:** PARTIAL. `**/public_html_legacy/` +
  `**/dbConfig.php` guards landed (✅); `phpcs.xml`/`phpstan.neon` exclusions for
  dev/landing/redir variants still pending.

### 🔵 Low impact

- **id:** B-031
  **title:** Lnks.page landing `<picture>` uses the SVG URL for both source and img fallback
  **category:** a11y/correctness · **impact:** Low · **component:** C
  **source:** #109, audit §4 · **status:** OPEN. Point `<img src>` at `logo.png`.

- **id:** B-032
  **title:** Gradient-clipped transparent-fill landing headings vanish in forced-colors mode
  **category:** a11y · **impact:** Low · **component:** A/C
  **source:** #110, audit §4 · **status:** OPEN. Add `@media (forced-colors: active)`
  fallback restoring `currentColor`/`CanvasText`.

- **id:** B-033
  **title:** Add branded error pages + `ErrorDocument` directives across components
  **category:** quality · **impact:** Low · **component:** A/B/C
  **source:** #114, audit §6 · **status:** OPEN. A lacks a branded 404; B/C/admin
  declare no `ErrorDocument`.

- **id:** B-034
  **title:** Component C whitelists `robots.txt`/`sitemap.xml` but the files don't exist
  **category:** quality · **impact:** Low · **component:** C
  **source:** #115, audit §6 · **status:** OPEN. They fall through and are treated
  as slugs.

- **id:** B-035
  **title:** G2My.Link landing references `/favicon.ico` that does not exist in the landing dir
  **category:** quality · **impact:** Low · **component:** B
  **source:** #116, audit §6 · **status:** OPEN.

- **id:** B-036
  **title:** Enforce No-Shorthand house rules across shared + A/B/C (alt PHP syntax, ternary/Elvis, JS shorthand)
  **category:** quality (standards) · **impact:** Low · **component:** A/B/C/shared
  **source:** #117, audit §5 · **status:** OPEN. Ternaries/Elvis in `org.php`,
  `account_types.php`, `breach_response.php`, breach-response page; alt syntax in
  email templates; JS shorthand in `cookie-consent.js`/`app.js`/`theme.js`/inline.

- **id:** B-037
  **title:** Standardise shared global-function naming (`g2ml_` prefix) and dedupe the `g2ml_getClientIP` fallback block
  **category:** quality (code-redundancy) · **impact:** Low · **component:** shared
  **source:** #118, audit §5 · **status:** OPEN. ≈51/154 fns prefixed; the
  client-IP fallback is duplicated 9× across 6 files.

- **id:** B-038
  **title:** Component B error pages declare `lang="en"` (and omit `dir`) instead of `en-GB`
  **category:** quality (standards/a11y) · **impact:** Low · **component:** B
  **source:** #119, audit §4 · **status:** OPEN. Set `lang="en-GB"` + `dir="ltr"`.

- **id:** B-039
  **title:** Commit a tracked `auth_creds.example.php` template referenced by `.gitignore`
  **category:** docs · **impact:** Low · **component:** config/shared
  **source:** #113, audit §6 · **status:** OPEN. `.gitignore` already negates
  `!**/auth_creds.example.php` but no template file exists (onboarding gap).

- **id:** B-040
  **title:** Correct MEMORY.md UTM-forwarding claim for Component B (feature not implemented)
  **category:** docs · **impact:** Low · **component:** shared
  **source:** #120, #92, audit §7.3 · **status:** OPEN. MEMORY.md has been
  corrected; verify the note is consistent and `redirect.forward_utm_params`/
  `analytics.capture_tracking_params` are not claimed as built.

## 💡 Proposed-Features ledger

<!-- `propose`-disposition / spec-gate feature candidates live in FEATURES.md
     (owned by dev-team-featurefind). This section is intentionally empty here so
     the two don't drift. -->

_(empty — feature gaps are tracked in `FEATURES.md`)_

## 📈 Trajectory ledger

Baseline metrics measured at DISCOVER seed; every cycle appends a row.

| Date | Cycle | Stage | Open #93–#128 | High B- open | Med B- open | Low B- open | Lint (parallel-lint) | Coverage | Notes |
|---|---|---|---|---|---|---|---|---|---|
| 2026-06-05 | 0 | DISCOVER | 36 (all OPEN on GitHub; 8 fixed-on-branch pending close) | 8 (1 ✅) | 21 (5 ✅) | 10 (0 ✅) | not-run-this-cycle | none (no test suite) | Baseline seed; map-commit 7b67ad5 |
