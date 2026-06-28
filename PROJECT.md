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
   backlog, seed `PROJECT.md`/`FEATURES.md`. — status: **complete**
2. **STABILIZE** — clear correctness + security launch-blockers (the High-impact
   B- items: #93 rotation, #95 XFF, #121 cross-org leak, #94/#122/#123/#124
   bugs). Verify shorten→redirect + auth + link CRUD live on MySQL. — status:
   **complete** (cycles 2–4)
3. **SECURE** — security Phase-0 (threat model + scanner sweep + attack-surface
   map → SECURITY.md), then targeted remediations starting with B-003/#95 XFF
   spoofing, then SSRF, CSRF, CRLF, cross-org, a11y items
   (#96–#110). — status: **active**
4. **FEATURE-FILL (in-scope)** — close in-scope feature gaps surfaced by audit
   §7.2/§8 and `FEATURES.md` via the conductor gate (e.g. #23 info-page auth view,
   #30 dashboard custom suffix/alias/tags, #91 custom-domain resolution). Large
   roadmap (Phases 7–11, Component C) stays queued. — status: planned
5. **VERIFY & DOCUMENT** — independent review pass; finalise install/run docs;
   confirm Definition of done. — status: planned

## 📌 Current status

- **Active stage:** 3 — SECURE (purple-team in progress; F-001/#95 fixed, 0 open High).
- **Done so far:** Bootstrap complete. Codebase Map written and spot-verified
  against the tree. Backlog re-derived from the live GitHub issues (#1–#128) and
  the two 2026-06-04 audits. Confirmed via direct code inspection that commit
  `6897165` already landed several launch-hardening fixes (see Decision log).
  Cycle 2 resolved B-002 (#121 cross-org category leak) and B-005 (#123 migration
  zero-date guards) — both MySQL-verified. Cycle 3 resolved B-004 (#122 org
  re-invite via generated-column unique key) and B-006 (#124 short-code TOCTOU
  retry) — both MySQL-verified. Cycle 4 established the project's first test
  safety net: pure-PHP no-Composer harness under `tests/` with 35 unit tests
  (security.php) + 4 integration tests (sp_lookupShortURL), all green. Cycle 5
  (SECURE Phase 0): `SECURITY.md` written — threat model, attack-surface map,
  tooling sweep, multi-role fixtures plan, coverage ledger, findings register.
  8 open findings (top = F-001/#95 spoofable client IP); 8 verified fixed-on-branch;
  no secrets or active-compromise signal; deps PASS. Cycle 6 (SECURE — purple-team):
  F-001/#95 (High) fixed — `REMOTE_ADDR` default + `TRUSTED_PROXIES` allowlist +
  CIDR helper; rate-limit/audit spoofing closed; 53 unit tests pass (+18).
- **In progress / next:** SECURE phase — F-001/#95 (High) fixed. **No open High
  findings remaining.** Remaining open findings: F-003/#99 (interstitial scheme
  guard, Med) next, then F-002/#98 (deletion-cancel CSRF, Med), F-004/#100 (SSRF,
  Med), F-005/#101 (Low), F-006 (Low, latent), F-007 (Low), F-008 (Low).
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

### 2026-06-28 — Org re-invite: VIRTUAL generated `pendingKey` + NULL-exempt UNIQUE (not status-in-key)

- **Decision:** Enforce at most one PENDING invitation per (org, email) using a
  VIRTUAL generated column `pendingKey` that is non-NULL only when
  `status = 'pending'`, backed by a UNIQUE index. Cancelled/expired rows store NULL
  and are exempt from the constraint, so re-invite cycles succeed.
- **Why not status-in-key:** Including `status` in the composite UNIQUE allowed
  multiple active pending rows per (org, email) if the status value differed — the
  original bug — and would silently admit a second concurrent pending invite.
- **Index replacement required:** Dropping a composite unique index that serves as
  the supporting index for a FK (`FK_invitation_org`) fails with MySQL errno 1553
  unless a replacement index is added first. Added `IDX_invitation_org` before
  dropping the old unique.
- **Revisit if:** a future MySQL version handles generated-column NULLs differently
  in unique indexes, or if the invitation model grows a second concurrent-pending
  use case.

### 2026-06-28 — `dbLastErrno()` added so callers can detect duplicate-key (errno 1062) for bounded retry

- **Decision:** Added `dbLastErrno()` helper + `$GLOBALS['_g2ml_last_errno']` to
  `db_query.php` so that `createShortURL()` — and any future caller — can
  distinguish a duplicate-key failure (errno 1062) from other insert errors without
  inspecting the raw MySQLi object.
- **Why:** The existing `dbInsert()` return contract (affected rows / false) gives
  no error code. Exposing errno via a thin global sidechannel is additive and does
  not break any existing caller.
- **Revisit if:** the project adopts an ORM or a DB-layer class that surfaces errno
  on the return object natively.

### 2026-06-28 — Test strategy: pure-PHP harness under `tests/` (no Composer; characterisation-first)

- **Decision:** Establish the test safety net as a pure-PHP micro-framework
  (`tests/bootstrap.php` + assert helpers) with two runners: `tests/run.php`
  (unit, DB-free, exits 1 on failure) and `tests/run_integration.php`
  (env-DSN MySQL, skips cleanly when `G2ML_TEST_DSN` is absent). No PHPUnit or
  Composer — Dreamhost shared hosting cannot run them. Characterisation-first
  approach: lock current behaviour (including any quirks) before refactoring.
- **Characterisation facts recorded as intended behaviour:**
  - CSRF tokens are **single-use** — `g2ml_validateCsrfToken()` unsets the
    session token on first successful validate; a second call with the same token
    fails. Callers that need reuse must regenerate.
  - `g2ml_sanitiseInput()` = `trim(strip_tags())` and **preserves internal
    CR/LF** — the contact-form CRLF injection defence lives at the call site
    (`$safeSubject` stripping), not in the sanitiser. Tests confirm this.
  - `g2ml_sanitiseURL()` rejects `javascript:`, `data:`, `ftp:`, `mailto:`, and
    relative/malformed URLs; allows `http://` and `https://` schemes only.
  - AES-256-GCM round-trip uses a random IV per encrypt call; identical plaintext
    produces different ciphertext each time (verified by the unit tests).
- **Options considered:** PHPUnit via phar (portability concerns; no CLI on
  Dreamhost prod); skip tests entirely (no regression net, too risky at this
  stage); pure-PHP micro-framework (chosen — zero dependencies, runs anywhere
  PHP 8.4 does).
- **Revisit if:** the project migrates off Dreamhost to a hosting environment
  with Composer/CLI, at which point PHPUnit integration becomes viable.

### 2026-06-28 — Client IP trust: REMOTE_ADDR default; XFF only from TRUSTED_PROXIES; CIDR via inet_pton

- **Decision:** `g2ml_getClientIP()` returns `REMOTE_ADDR` by default. `X-Forwarded-For` and `X-Real-IP` headers are honoured **only** when `REMOTE_ADDR` is present in the optional `TRUSTED_PROXIES` constant (an array of IP addresses or CIDR ranges). If the constant is undefined or empty, all forwarded headers are ignored. When multiple XFF hops are present, the right-most untrusted entry is chosen. CIDR matching is performed via binary `inet_pton` comparison, covering both IPv4 and IPv6. Dreamhost shared hosting has no trusted upstream proxy, so the effective default is: trust none.
- **Why not per-call allowlist or ini-driven config:** A single well-named constant declared alongside the credentials file is discoverable, zero-dependency, and matches the project's no-Composer constraint. A DB-driven or ini-driven list adds a fetch on every request to the hot path.
- **Revisit if:** the app is placed behind a known proxy/CDN (e.g. Cloudflare) — define `TRUSTED_PROXIES` with the CDN's published CIDR ranges.

### 2026-06-28 — SECURE Phase 0: SECURITY.md is the findings register; purple-team order established

- **Decision:** `SECURITY.md` is the single findings register using `F-` IDs mapped to GitHub issue numbers. Purple-team remediation order: F-001 (#95 spoofable client IP) → F-003 (#99 interstitial scheme guard) → F-002 (#98 deletion-cancel CSRF) → F-004 (#100 SSRF in validateDestination) → F-005 (#100 created-URL internal-host/userinfo) → F-006 (#101 favicon path-traversal latent). Active-compromise signal: none.
- **Reason:** A single authoritative register prevents the findings list fragmenting across memory files and GitHub comments; `F-` IDs give stable cross-reference anchors across SECURITY.md, PROJECT.md, and GitHub issues. Ordering by exploitability × reach puts the spoofable-IP finding (poisons logs, rate-limit, login-alert — remotely exploitable with zero auth) first.
- **Revisit if:** a second audit produces findings that conflict with the ordering, or if the user re-prioritises the purple-team queue.

### 2026-06-28 — STRICT-mode zero-date guard: CAST AS CHAR before NULLIF

- **Decision:** When guarding legacy zero-date columns in data-migration SQL under
  STRICT `sql_mode` (NO_ZERO_DATE active), cast the source column `AS CHAR` before
  comparing with `NULLIF`, rather than comparing the bare date literal
  `'0000-00-00'` or `'0000-00-00 00:00:00'` directly.
- **Why:** Under NO_ZERO_DATE STRICT mode, the bare zero-date literal inside a
  `NULLIF()` call is itself rejected with errno 1292 before the comparison even
  runs. Casting to CHAR first makes the comparison a string operation, which is
  never mode-restricted.
- **Canonical idiom (NOT NULL target):**
  `IFNULL(NULLIF(NULLIF(CAST(col AS CHAR),'0000-00-00 00:00:00'),'0000-00-00'),NOW())`
- **Canonical idiom (nullable target):**
  `NULLIF(NULLIF(CAST(col AS CHAR),'0000-00-00 00:00:00'),'0000-00-00')`
- **Applied in:** `web/_sql/migrations/001`, `002`, `003`, `004`, `006`, `007`.
- **Revisit if:** a future MySQL version rejects string-mode CAST — switch to
  `STR_TO_DATE` with `%Y-%m-%d %H:%i:%s` and an explicit NULL fallback.

## ⛳ Checkpoint log

### Cycle 2 — 2026-06-28 — STABILIZE

- **Items resolved:** B-002 (#121 cross-org category leak), B-005 (#123 migration zero-date guards).
- **Evidence:** old JOIN returned 2 rows (cross-org leak); new JOIN returns 1 (correct org only). Unguarded INSERT → errno 1292 under STRICT; guarded INSERT succeeds. `004_migrate_shorturls.sql` ran end-to-end against a legacy stub → exit 0. Both PHP files lint clean (`php -l`).
- **Remaining High correctness in STABILIZE:** B-004 (#122 org re-invite partial-key), B-006 (#124 short-code TOCTOU retry). Security High still open: B-003 (#95 XFF/trusted-proxy).
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually).

### Cycle 3 — 2026-06-28 — STABILIZE

- **Items resolved:** B-004 (#122 org re-invite via generated-column unique key), B-006 (#124 short-code TOCTOU retry).
- **Evidence:** B-004 — cancel→re-invite SUCCEEDS; second concurrent pending REJECTED (errno 1062); migration 010 upgrades a HEAD-schema DB cleanly. B-006 — harness: 2 collisions → regenerate → 3rd insert succeeds; 5 collisions → graceful failure. Both PHP files lint clean.
- **Remaining in STABILIZE:** B-003 (#95 XFF/trusted-proxy, SECURE phase). B-007 (#104 landing auto-refresh, POLISH phase). B-001 (#93 legacy-cred rotation, manual user action). B-008 (#91 custom-domains, docs clarification).
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually).

### Cycle 4 — 2026-06-28 — STABILIZE (completes STABILIZE)

- **Items resolved:** B-041 — pure-PHP test safety net established under `tests/`.
- **Evidence:** `php tests/run.php` → 35 passed / 0 failed, exit 0. `php tests/run_integration.php` (no DSN) → 4 tests SKIPPED cleanly. Lint clean across all new files. Lead independently re-ran the suite: 35 passed / 0 failed, exit 0.
- **Characterisation coverage:** `security.php` — `g2ml_hashPassword`/`verifyPassword` (Argon2id), `g2ml_sanitiseInput` (trim+strip_tags, preserves internal CRLF), `g2ml_sanitiseOutput` (htmlspecialchars), `g2ml_sanitiseURL` (scheme allowlist), CSRF token generate/validate (single-use confirmed), `g2ml_encrypt`/`g2ml_decrypt` AES-256-GCM round-trip. Integration: `sp_lookupShortURL` (active→200, expired→410, not_found→404, not_yet_active→404/pending).
- **STABILIZE stage status:** COMPLETE. All High correctness B- items resolved (B-002, B-004, B-005, B-006 done; B-001 manual; B-003 deferred to SECURE). Test safety net in place.
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually). Advancing to SECURE.

### Cycle 6 — 2026-06-28 — SECURE (purple-team, F-001/#95)

- **Items resolved:** B-003 (#95 spoofable client IP — `g2ml_getClientIP()` trusted-proxy allowlist fix).
- **Evidence:** RED PoC (before fix): `g2ml_getClientIP()` returned forged `1.2.3.4` from a crafted `X-Forwarded-For` header; rotating IPs bypassed the per-IP rate-limit. VERIFY (after fix): genuine `203.0.113.9` (`REMOTE_ADDR`) returned; forged header ignored. Regression: `tests/unit/security_clientip_test.php` — 18 new tests covering REMOTE_ADDR baseline, XFF trusted/untrusted proxy cases, CIDR matching, fallback chain. `php tests/run.php` → 53 passed / 0 failed (was 35). `php -l` clean on all changed files. `TRUSTED_PROXIES` documented in installer creds heredoc and `docs/INSTALL.md`.
- **Remaining open High findings:** none (F-001 was the only High; 0 open High now).
- **Next purple-team target:** F-003 (#99 — interstitial scheme guard, Med), then F-002 (#98 — deletion-cancel CSRF, Med), then F-004 (#100 — SSRF), then F-005/#101 Low etc.
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually).

### Cycle 5 — 2026-06-28 — SECURE (Phase 0 — doc-only)

- **Items resolved:** SECURITY.md written — threat model, attack-surface map, tooling sweep, multi-role fixtures plan, coverage ledger, and findings register.
- **Evidence:** SECURITY.md present in repo root. Findings register: 8 OPEN (F-001–F-008, mapped to GitHub issues #95–#101); 8 verified FIXED-on-branch (F-101–F-108, mapped to #80–#103). Dependency sweep: jQuery 3.7.1, Bootstrap 5.3.3, Font Awesome 6.5.1 — all pinned and current; Chart.js version unstamped (note for when analytics ships). No committed secret found; no active-compromise signal. Coverage gap noted: GlobalAdmin + second-org fixtures (for IDOR/BFLA testing) not yet seeded; existing tests cover `security.php` + `sp_lookupShortURL` only.
- **Artifacts added:** `SECURITY.md` (repo root).
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually). Next: purple-team cycle targeting F-001 (#95 spoofable client IP — `g2ml_getClientIP()` trusted-proxy fix).

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
    {index,sessions}, `org/*`, invite/accept, `privacy/*` (data rights, delete),
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

- **last-run:** 2026-06-28
- **map-commit:** `7b67ad5`
- **cycles-done:** 6  (0=DISCOVER, 2–3=STABILIZE correctness, 4=STABILIZE test harness, 5=SECURE Phase 0, 6=SECURE purple-team F-001)
- **branch:** `autopilot/2026-06-05`
- **working tree at seed:** clean except untracked `.claude/agents/`,
  `.claude/settings.json` (autopilot scaffolding — not production code).
- **artifacts added (cycle 5):** `SECURITY.md` (repo root — threat model, attack-surface map, tooling sweep, multi-role fixtures plan, coverage ledger, findings register F-001–F-008 open / F-101–F-108 fixed).
- **artifacts added (cycle 6):** `tests/unit/security_clientip_test.php` (18 regression tests for `g2ml_getClientIP`, `g2ml_isTrustedProxy`, `g2ml_ipInRange`); updated `web/_functions/security.php` (trusted-proxy allowlist); `docs/INSTALL.md` + installer heredoc (TRUSTED_PROXIES documentation).

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
  **source:** #121, schema-review §High · **status:** ✅ Done (fixed cycle 2; MySQL-verified).
  Added `AND c.orgHandle = s.orgHandle` to LEFT JOIN ON-clause in
  `pages/links/index.php:140` and `pages/info/index.php:120`; swept all other
  `tblCategories` PHP queries — no other leaks. Old JOIN returned 2 rows (leaked
  another org's category name); new JOIN returns exactly 1 (correct org only).

- **id:** B-003
  **title:** `g2ml_getClientIP()` trusts spoofable X-Forwarded-For / X-Real-Ip with no trusted-proxy check
  **category:** security · **impact:** High · **component:** shared
  **source:** #95 · **status:** ✅ Done (cycle 6). `REMOTE_ADDR` default; XFF/XRI
  honoured only when `REMOTE_ADDR` is in the optional `TRUSTED_PROXIES` constant
  (undefined/empty = trust none); right-most-untrusted XFF entry chosen; CIDR match
  via `inet_pton` (IPv4+IPv6); helpers `g2ml_isTrustedProxy()` and `g2ml_ipInRange()`
  added. Rate-limit/audit-log spoofing closed across all 13 callers. 18 regression
  tests added; suite now 53 unit / 0 failed. Dreamhost default = trust none.

- **id:** B-004
  **title:** Org invitations: re-invite blocked by `UQ_org_email_pending` including `status`
  **category:** correctness · **impact:** High · **component:** A (database)
  **source:** #122, schema-review §High · **status:** ✅ Done (fixed cycle 3; MySQL-verified).
  Added a VIRTUAL generated column `pendingKey` (= `CONCAT(orgHandle,':',email)` while
  `status='pending'`, else NULL) and moved `UQ_org_email_pending` onto it (NULLs are
  exempt, so cancelled/expired rows coexist). Replaced the dropped composite unique
  with `IDX_invitation_org` to satisfy the FK backing-index requirement (errno 1553
  if dropped bare). Forward migration `010_org_invite_pending_key.sql` for deployed
  DBs; documents a one-line pre-flight UPDATE if a deployed DB already has 2+
  concurrent pendings for the same (org, email). `org.php` required no change.
  Verified: cancel→re-invite SUCCEEDS; second concurrent pending REJECTED (errno 1062);
  migration 010 upgrades a HEAD-schema DB cleanly.

- **id:** B-005
  **title:** Data migration: guard zero-date / NULL legacy dates under STRICT sql_mode
  **category:** correctness · **impact:** High · **component:** database
  **source:** #123, schema-review §High · **status:** ✅ Done (fixed cycle 2; MySQL-verified).
  Wrapped legacy date columns in migrations 001–004, 006–007 with
  `IFNULL(NULLIF(NULLIF(CAST(col AS CHAR),'0000-00-00 00:00:00'),'0000-00-00'),NOW())`
  (nullable targets omit the outer `IFNULL`). CAST AS CHAR strategy avoids the
  bare `'0000-00-00'` literal being rejected by STRICT mode itself. Verified:
  unguarded INSERT errors 1292 under STRICT; guarded succeeds; real
  004_migrate_shorturls.sql ran end-to-end → exit 0.

- **id:** B-006
  **title:** Short-code generation TOCTOU — retry on unique-key collision
  **category:** correctness · **impact:** High · **component:** B/shared
  **source:** #124, schema-review §High · **status:** ✅ Done (fixed cycle 3; MySQL-verified).
  `shorturl_create.php`: wrapped generate→insert in a bounded 5-attempt `for` loop
  that regenerates on a duplicate-key collision (errno 1062). `db_query.php`: added
  `dbLastErrno()` + `$GLOBALS['_g2ml_last_errno']` so callers can distinguish errno
  1062 (additive; `dbInsert` return contract unchanged). Verified via a harness
  loading the real files: 2 collisions → regenerate → 3rd insert succeeds; 5
  collisions → graceful failure. Both PHP files lint clean.

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

- **id:** B-041
  **title:** Establish pure-PHP test safety net (no Composer/PHPUnit; Dreamhost-compatible)
  **category:** quality (testing) · **impact:** High · **component:** shared
  **source:** autopilot cycle 4 · **status:** ✅ Done (cycle 4).
  Bootstrap micro-framework under `tests/` (assert helpers, `run.php` unit runner
  exits 1 on failure, `run_integration.php` env-driven MySQL runner that SKIPs
  cleanly with no DB, `tests/README.md`). Characterisation tests: 35 unit tests
  covering `security.php` (`g2ml_hashPassword`/`verifyPassword`, `g2ml_sanitiseInput`,
  `g2ml_sanitiseOutput`, `g2ml_sanitiseURL`, CSRF token generate/validate,
  `g2ml_encrypt`/`g2ml_decrypt` AES-256-GCM round-trip) + 4 integration tests
  (`sp_lookupShortURL` success/expired/not_found/not_yet_active). All green; lint
  clean. Lead re-ran independently: 35 passed / 0 failed, exit 0.

## 💡 Proposed-Features ledger

<!-- `propose`-disposition / spec-gate feature candidates live in FEATURES.md
     (owned by dev-team-featurefind). This section is intentionally empty here so
     the two don't drift. -->

_(empty — feature gaps are tracked in `FEATURES.md`)_

## 📈 Trajectory ledger

Baseline metrics measured at DISCOVER seed; every cycle appends a row.

| Date | Cycle | Stage | Open #93–#128 | High B- open | Med B- open | Low B- open | Lint (parallel-lint) | Coverage | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-06-05 | 0 | DISCOVER | 36 (all OPEN on GitHub; 8 fixed-on-branch pending close) | 8 (1 ✅) | 21 (5 ✅) | 10 (0 ✅) | not-run-this-cycle | none (no test suite) | Baseline seed; map-commit 7b67ad5 |
| 2026-06-28 | 2 | STABILIZE | 34 (B-002 + B-005 resolved this cycle) | 6 (3 ✅) | 21 (5 ✅) | 10 (0 ✅) | clean (php -l on changed files) | none (no test suite) | Cross-org category isolation (B-002/#121) + migration zero-date guards (B-005/#123); old-vs-new JOIN row counts; STRICT-mode guarded-vs-unguarded INSERT (errno 1292); real 004 migration exit 0 — all MySQL-verified |
| 2026-06-28 | 3 | STABILIZE | 32 (B-004 + B-006 resolved this cycle) | 4 (5 ✅) | 21 (5 ✅) | 10 (0 ✅) | clean (php -l on changed files) | none (no test suite) | Org re-invite via generated-column unique key (B-004/#122) + short-code TOCTOU retry (B-006/#124); cancel→re-invite SUCCESS + double-pending errno 1062; migration 010 clean; retry regenerated after 2 collisions, graceful after 5 — all MySQL-verified |
| 2026-06-28 | 4 | STABILIZE → completes STABILIZE | 32 (no GitHub issues closed this cycle) | 4 (5 ✅) | 21 (5 ✅) | 10 (0 ✅) | clean (php -l on all new tests/ files) | 35 unit / 4 integration (new — all green) | Test harness + characterisation (B-041): pure-PHP harness under tests/; `php tests/run.php` → 35 passed / 0 failed exit 0; integration 4 passed (or SKIPs cleanly with no DSN). STABILIZE stage now COMPLETE; advancing to SECURE |
| 2026-06-28 | 5 | SECURE — Phase 0 (doc-only) | 32 (unchanged) | 4 open | 21 open | 10 open | n/a (doc-only cycle) | 35 unit / 4 integration (unchanged) | Threat model + attack-surface map + tooling sweep + multi-role fixtures plan + coverage ledger + findings register → SECURITY.md written. 8 OPEN findings (1 High: F-001/#95; 3 Med: F-002/#98, F-003/#99, F-004/#100; 4 Low: F-005/#100, F-006/#101, F-007, F-008); 8 verified FIXED-on-branch (F-101..F-108). No secrets/active-compromise signal; deps PASS (jQuery 3.7.1, Bootstrap 5.3.3, FA 6.5.1 pinned & current; Chart.js unstamped — note for analytics). Evidence: SECURITY.md#Findings |
| 2026-06-28 | 6 | SECURE — purple-team (F-001/#95) | 31 (B-003 resolved this cycle) | 0 open (3 ✅ including this cycle) | 21 open | 10 open | clean (php -l on changed files) | 53 unit / 4 integration (18 new: `tests/unit/security_clientip_test.php`) | Spoofable client IP fixed (B-003/#95) — `REMOTE_ADDR` default + `TRUSTED_PROXIES` allowlist + CIDR helper via `inet_pton`; right-most-untrusted XFF entry; covers all 13 callers. RED PoC: forged 1.2.3.4 returned before fix; VERIFY: genuine 203.0.113.9 returned after. 53 unit tests pass / 0 failed (was 35). TRUSTED_PROXIES documented in installer creds heredoc + docs/INSTALL.md. No open High findings remaining. |
