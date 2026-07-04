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

- **Active stage:** COMPLETE phase: FG-001 + FG-002 + FG-003 built. Next: FG-004 (XML+XSLT API output) — the last small autonomy-eligible gap; after that the remaining feature work is the gated G-001..G-005 (Component C, payments, advanced auth, public API, analytics) awaiting user approval, so the loop will then move to POLISH. Local branch ahead of draft PR #130 (do not merge) — re-push to refresh.
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
  CIDR helper; rate-limit/audit spoofing closed; 53 unit tests pass (+18). Cycle 7
  (SECURE — purple-team): F-002/#98 + F-003/#99 (both Med) fixed — deletion-cancel
  CSRF→POST; interstitial destination/fallback scheme-guarded; 8 new regression
  tests; 61 unit tests pass. Cycle 8 (SECURE — purple-team, destination/path-safety
  cluster): F-004/F-005 (#100) + F-006 (#101) fixed — shared anti-SSRF host guard
  (`g2ml_destinationHostIsAllowed`) on both `validateDestination` and `createShortURL`;
  favicon `orgLogoPath` confined with `basename()`+`realpath()`; 90 unit tests pass
  (+29). Cycle 8 was interrupted once by a monthly spend-limit; partial work discarded
  and cycle retried cleanly. Cycle 9: SECURE COMPLETE (F-007 session re-bind + F-008
  Bootstrap CSS SRI hash corrected; findings register 0 open; 90 unit + 5 integration
  tests pass). Cycle 10: COMPLETE phase — FG-001 (custom short-suffix/alias) auto-built
  under the autonomy test; 110 unit + 11 integration tests pass; 5 acceptance criteria
  demonstrated. New bug B-042 surfaced: `logActivity()` bind-type mismatch silently
  breaks audit logging and undermines per-IP rate-limiting — re-opening STABILIZE next.
  Cycle 11 (STABILIZE re-open): B-042 fixed — bind-param type-string was 20 chars
  against 21 columns/placeholders/variables (off-by-one) and mis-typed `ipAddress`
  as `i`; corrected to the 21-char `'ssisisssssssssssssiis'` with each char matching
  its column type. Verified on MySQL 9.6: before → type-string error, 0 rows
  inserted; after → 1 row inserted; 5 create events now produce 5 countable
  `tblActivityLog` rows (per-IP rate-limit restored). 110 unit + 13 integration
  tests pass. New bug B-043 surfaced: `_g2ml_parseUserAgent()` regex delimiter bug
  (`preg_quote()` missing `/` arg) breaks bot detection silently.
- **In progress / next:** STABILIZE re-open — B-042 fixed (audit logging + rate-limit
  restored). Next: fix B-043 (parseUserAgent regex — quick correctness, same file),
  then resume COMPLETE with FG-002 (tags), FG-003 (info-page auth view), FG-004
  (XML+XSLT). Gated G-001..G-005 await approval. Note: branch pushed + draft PR #130
  open (do not merge).
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

### 2026-07-04 — FG-003 (info-page public-vs-authenticated view, #23) auto-built under the autonomy test; full destination revealed to ANY authenticated viewer, rendered as escaped text not a link

- **Decision:** FG-003 was auto-built without user gate approval under the autopilot autonomy test: table-stakes feature, explicitly in-scope per issue #23, risk Low, non-destructive, and the page's own docblock already documented the intent. The full destination is revealed to **any authenticated viewer**, not just the link's owner — because short URLs redirect publicly on visit, the destination is not a secret gated behind ownership; the only thing worth gating behind login is convenience (avoiding the click-through). The full destination is rendered as **escaped text**, not a clickable `<a href>`, closing off any scheme/XSS vector from a stored destination. Anonymous viewers keep the existing masked domain view and get a new accessible "Log in to see the full destination" prompt linking to `/login` with **no redirect-back parameter**, to avoid introducing an open-redirect surface on a page that is otherwise pure read-only lookup. The decision logic (mask vs reveal) was extracted into a pure, dependency-free helper (`g2ml_infoDisplayDestination()`) specifically so it is unit-testable without a session/DB fixture.
- **Why any-authenticated rather than owner-only:** Owner-only would require an ownership lookup (extra query, extra edge cases for org-shared links) to protect information that is not actually secret — the short code already forwards anyone to the destination. Scoping to "any authenticated" matches the page docblock's stated intent and keeps the change additive/non-destructive with no new query.
- **Scope boundary:** The redirect path, the create flow, and the DB query layer were untouched — this is purely a display-branching change on the existing info/preview page.
- **Revisit if:** a future privacy requirement narrows this to owner-only (e.g. an org wants destinations hidden even from other logged-in users) — the pure helper already isolates the decision so that would be a small, testable change.

### 2026-07-04 — FG-002 (tags on links) auto-built under the autonomy test; find-or-create per org, post-commit attach with per-tag isolation

- **Decision:** FG-002 (tags on short links) was auto-built without user gate approval under the autopilot autonomy test: table-stakes feature, explicitly in-scope per brief line 91 (categories *and* tags), risk Low, non-destructive. The `tblTags`/`tblShortURLTags` schema (schema 020) existed but had zero PHP references — categories were wired, tags were defined-only. Tags find-or-create per org via the existing `UQ_tag_org` unique key; the junction insert uses `INSERT IGNORE` (idempotent, no duplicate-key noise); attaching tags happens strictly AFTER the short-URL row commits, and each tag is wrapped in its own try/catch so a single tag failure never rolls back or fails the short-URL create. Slugs are ASCII-only (`g2ml_slugifyTag()`); the display name preserves the user's original casing/spacing. A hard cap of `G2ML_MAX_TAGS_PER_LINK` = 10 prevents unbounded junction growth from a single request.
- **Why post-commit + per-tag try/catch:** Tags are a secondary enrichment, not core to the short URL's existence — the create form's primary promise (a working short link) must not fail because of a tag-layer problem (e.g. a pathological slug collision or a transient DB hiccup on one tag among several). Mirrors the same "never let the enrichment path break the core path" principle already used for activity logging.
- **Scope boundary:** Edit-form tag management (add/remove tags on an existing link) and tag-based filtering on the links index are explicit follow-ups, not built this cycle — captured as the FG-002 out-of-scope note in `FEATURES.md` rather than silently left undone.
- **Revisit if:** tag volume per org grows large enough that the `IN(...)` badge-rendering query needs pagination-aware batching, or if the edit-form/filtering follow-ups surface a need to revisit the find-or-create/junction approach.

### 2026-07-04 — `logActivity()` bind_param type-string must equal column/placeholder/variable count AND match each column type

- **Decision:** Fixed B-042 by rebuilding the `logActivity()` bind-param type
  string in `web/_functions/activity_logger.php` from 20 chars (one short of
  the 21 columns/placeholders/variables) to the correct 21-char
  `'ssisisssssssssssssiis'`, with each character matching its column's actual
  type (`statusCode`/`userUID`/`isBot`/`apiKeyUID` = `i`; `ipAddress` +
  `logData`/JSON + the rest = `s` — `ipAddress` had been mis-typed `i`).
- **Why it matters:** Audit logging and rate-limit correctness both depend on
  this string being right — `bind_param` throws when the type-string length
  doesn't match the variable count, and the exception was being caught and
  swallowed, so every short-URL create silently failed to log while the
  per-IP rate limiter's `COUNT(*)` over `tblActivityLog` under-counted.
- **Revisit if:** a future change adds/removes a column from `tblActivityLog`
  without updating both the placeholder count and the type-string in lock-step
  — treat any edit to that INSERT as touching three things at once (columns,
  placeholders, type-string).
- **Follow-on:** B-043 (`_g2ml_parseUserAgent()` regex delimiter bug) queued
  next — same file, quick correctness fix.

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

### 2026-06-28 — Interstitial output: scheme-guard all destination/fallback sinks; deletion-cancel is a CSRF POST

- **Decision:** All destination and fallback URL sinks in `validating.php` and `expired.php` (`href` attributes, JS `window.location` assignments, `meta-refresh` content, noscript text) are scheme-guarded via `g2ml_sanitiseURL()` (http(s) only) before output, with a `preg_match('#^https?://#i')` inline fallback. A rejected destination produces no link; a rejected fallback falls back to `https://go2my.link`. Account-deletion cancellation is converted to a CSRF-protected POST using a dedicated form name (`account_delete_cancel`), distinct from the deletion-request form (`account_delete`); the GET `?cancel` path is removed entirely.
- **Why:** A legacy-migrated `javascript:` or `data:` URL stored in `tblShortURLs` would otherwise render as a clickable (or auto-followed via `window.location`) link on the interstitial pages — `htmlspecialchars` alone does not neutralise URI scheme injection. A forged GET request (e.g. via an `<img>` tag) to the cancel URL would silently cancel a victim's own pending account-deletion with no user action.
- **Revisit if:** a future interstitial redesign introduces a new URL output sink that bypasses the guard, or if the cancellation flow gains additional steps that require re-evaluation of the form structure.

### 2026-06-29 — Anti-SSRF: destination host validation on both create and validateDestination; fail-closed

- **Decision:** Destination hosts are validated at two chokepoints via a shared helper `g2ml_destinationHostIsAllowed()` in `security.php`: (1) in `validateDestination()` in `redirect_resolver.php`, before any `get_headers()` HEAD fetch; (2) in `createShortURL()` in `shorturl_create.php`, before any row is inserted. Loopback, link-local (including `169.254.169.254` metadata), and other reserved ranges are **always** blocked with no override. RFC1918 private IPv4 and IPv6 ULA ranges are blocked by default and may be overridden per-instance via the new `redirect.allow_private_destinations` setting (default `'0'`). Userinfo components (`user:pass@`) are rejected. All resolved A/AAAA IP addresses must pass the check (all-must-pass, not any-must-pass). IPv4-mapped IPv6 addresses are unwrapped before comparison. The helper fails closed when settings or DNS are unavailable.
- **Why not allow-list approach:** The platform is a URL shortener deployed on shared hosting — the attack surface for SSRF is the stored destination of any short URL. A block-list of always-bad ranges (loopback, metadata, reserved) plus a configurable block of RFC1918 covers the realistic risk without requiring an explicit allow-list of every legitimate destination prefix, which would be unmanageable for a public shortener.
- **Revisit if:** the app is deployed in an environment where RFC1918 destinations are legitimately needed (e.g. an intranet shortener) — set `redirect.allow_private_destinations` = `'1'`; the always-blocked ranges (loopback/link-local/metadata/reserved) remain in force regardless.

### 2026-06-29 — FG-001 auto-built under autonomy test; B-042 (logActivity bind mismatch) surfaces; re-opening STABILIZE

- **Decision:** FG-001 (authenticated custom short-suffix/alias) was auto-built without user gate approval under the autopilot autonomy test: table-stakes feature, explicitly in-scope per brief lines 90–92, risk Low, non-destructive (additive to existing `createShortURL()` and the dashboard create form; anonymous/public API path untouched). Custom codes are validated against `^[A-Za-z0-9_-]{3,50}$`, blocked against reserved words via `g2ml_isReservedShortCode()` (robots/favicon/index/sitemap/validating/expired/404/api/admin/install/www etc.), and on a duplicate (errno 1062) return "That alias is already taken." with no random fallback — a user-chosen code is never silently changed.
- **B-042 surfaced:** `logActivity()` in `web/_functions/activity_logger.php` has a bind-param type-string vs params mismatch that throws on every short-URL create. The exception is caught and swallowed (non-fatal) but means activity logging silently fails on creates AND likely undermines the per-IP rate limit (`rateLimit()` counts `tblActivityLog` rows by IP). This is broader than the existing #126 (which tracks the `sp_logActivity` stored-procedure drift); the live PHP function/direct-INSERT path is affected. Classified High correctness/security.
- **Stage decision:** Re-opening STABILIZE to fix B-042 (it undermines a security control — rate-limiting — so the safety floor requires it before resuming COMPLETE). After B-042 is fixed, resume COMPLETE: auto-build FG-002 (tags), FG-003 (info-page auth view), FG-004 (XML+XSLT), then queue gated gaps G-001..G-005 for user approval.
- **Revisit if:** B-042 turns out to be already fixed by a concurrent change, or if the rate-limit is determined to have a separate enforcement path not dependent on `tblActivityLog`.

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

### Cycle 14 — 2026-07-04 — COMPLETE (auto-built FG-003; info-page public-vs-authenticated view, #23)

- **Items resolved:** FG-003 — info-page public-vs-authenticated view (auto-built under the autonomy test); B-019 (#23) marked done.
- **Evidence:** New file `web/Go2My.Link/_functions/info_display.php` — pure helper `g2ml_infoDisplayDestination(array $linkData, bool $isAuthenticated): string` returns the full `destinationURL` when authenticated, a masked `domain[/...]` when not, and `''` when there is no destination; the old inline masking logic was moved into it (Component A auto-loads `_functions/*.php`, no wiring needed). `pages/info/index.php` — authenticated viewers (ANY authenticated viewer, not owner-only, matching the page docblock's stated intent — short URLs redirect publicly so the destination is not secret) see the full destination rendered as escaped text via `g2ml_sanitiseOutput()`, not a clickable link (removes any scheme/XSS vector); anonymous viewers keep the masked view plus a new accessible "Log in to see the full destination" prompt linking to `/login` with no redirect-back parameter (avoids introducing an open-redirect). New i18n key `info.login_for_full` added to seed `010_phase6_translations.sql`. Regression: `tests/unit/info_display_destination_test.php` (9 new tests). Lead re-ran: 141 unit pass (was 132); lint clean; a manual render harness confirmed a hostile payload (`<script>` in a stored destination) is fully HTML-escaped in the authenticated view.
- **Remaining:** Next: FG-004 (XML+XSLT API output) — the last small autonomy-eligible gap. After that the remaining feature work is the gated G-001..G-005 (Component C, payments, advanced auth, public API, analytics) awaiting user approval, so the loop then moves to POLISH.
- **Branch state:** clean commit on `autopilot/2026-06-05`; local branch is ahead of the draft PR #130 (do not merge) — needs re-push to refresh.

### Cycle 13 — 2026-07-04 — COMPLETE (auto-built FG-002; tags on links)

- **Items resolved:** FG-002 — tags on short links (auto-built under the autonomy test); B-020 marked done for the create-path (custom suffix/alias from FG-001 + tags from FG-002).
- **Evidence:** `web/Go2My.Link/_functions/shorturl_create.php` — new helpers `g2ml_slugifyTag()`, `g2ml_normaliseTags()`, `g2ml_findOrCreateTag()`, `g2ml_attachTagsToShortURL()`; `createShortURL()` attaches tags AFTER the row insert, find-or-create per org via `UQ_tag_org`, junction via `INSERT IGNORE`, each tag in its own try/catch so a tag failure never rolls back the short URL; capped at `G2ML_MAX_TAGS_PER_LINK` = 10. `web/Go2My.Link/_admin/public_html/pages/links/create/index.php` — optional comma-separated "Tags" field. `web/Go2My.Link/_admin/public_html/pages/links/index.php` — tag badges rendered via ONE query joining `tblShortURLTags`→`tblTags` for the page's short-URL UIDs, using a dynamically-sized bound `IN(...)` clause (no N+1; confirmed placeholder-only, no interpolation); badges use `role="list"` for accessibility. Regression: `tests/unit/tags_normalise_test.php` (19 tests) + `tests/integration/tags_create_test.php` (5 tests). Lead re-ran: 132 unit + 18 integration pass; lint clean. 5 acceptance criteria verified.
- **Remaining:** Edit-form tag management and tag-based filtering deferred as explicit follow-ups (out of scope this cycle; captured in `FEATURES.md`). Next: FG-003 (info-page public-vs-authenticated view, #23), then FG-004 (XML+XSLT API output). Gated gaps G-001..G-005 still awaiting user approval.
- **Branch state:** clean commit on `autopilot/2026-06-05`; local branch is ahead of the draft PR #130 (do not merge) — needs re-push to refresh.

### Cycle 11 — 2026-07-04 — STABILIZE re-open (fixed B-042; surfaced B-043)

- **Items resolved:** B-042 — `logActivity()` bind-param type-string off-by-one (20 chars against 21 columns/placeholders/variables) and mis-typed `ipAddress` (`i` instead of `s`), corrected to the 21-char `'ssisisssssssssssssiis'` with each char matching its column type.
- **New bug surfaced:** B-043 — `_g2ml_parseUserAgent()` (same file, ~line 288) builds its bot regex with `preg_quote()` missing the `/` delimiter arg; the `'Java/'` pattern's unescaped `/` prematurely closes the `/…/` delimiter → `preg_match(): Unknown modifier '|'` on every call → bot detection silently broken (`isBot` never set). Medium correctness (analytics/bot-signal integrity); out of scope this cycle.
- **Evidence:** `web/_functions/activity_logger.php` — BEFORE: bind-param type-string error thrown, 0 rows inserted. AFTER: `logActivity()` returns true, 1 row inserted with correct values. Rate-limit impact resolved: 5 create events now produce 5 countable `tblActivityLog` rows (the per-IP limiter's `COUNT(*)` was effectively 0 while logging failed — abuse-prevention gap closed). Regression: `tests/integration/activity_log_test.php` (2 new cases). Lead re-ran: 110 unit + 13 integration pass; lint clean. Sibling `error_handler.php` `tblErrorLog` INSERT checked — aligned, no bug.
- **Remaining:** Fix B-043 (quick correctness, same file) then resume COMPLETE: auto-build FG-002 (tags), FG-003 (info-page auth view), FG-004 (XML+XSLT). Gated gaps G-001..G-005 still awaiting user approval.
- **Branch state:** clean commit on `autopilot/2026-06-05`; branch pushed with draft PR #130 open (do not merge).

### Cycle 10 — 2026-06-29 — COMPLETE (auto-built FG-001; surfaced B-042)

- **Items resolved:** FG-001 — authenticated custom short-suffix/alias (auto-built under autonomy test).
- **New bug surfaced:** B-042 — `logActivity()` bind-type mismatch throws on every create (swallowed silently; breaks audit logging; undermines per-IP rate-limit).
- **Evidence:** `web/Go2My.Link/_functions/shorturl_create.php` — `createShortURL()` `customCode` option with `^[A-Za-z0-9_-]{3,50}$` validation + `g2ml_isReservedShortCode()` + hard duplicate error (no random fallback). `web/Go2My.Link/_admin/public_html/pages/links/create/index.php` — optional "Custom alias" field. `tests/unit/custom_alias_test.php` (25 tests) + `tests/integration/custom_alias_create_test.php` (6 tests). `php tests/run.php` → 110 passed / 0 failed; integration 11 passed. 5 acceptance criteria demonstrated. Lint clean.
- **Remaining:** Re-opening STABILIZE to fix B-042 before resuming COMPLETE (FG-002 tags, FG-003 info-page auth view, FG-004 XML+XSLT). Gated gaps G-001..G-005 still awaiting user approval.
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually).

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

### Cycle 8 — 2026-06-29 — SECURE (purple-team, destination/path-safety cluster: F-004/F-005 #100 + F-006 #101)

- **Items resolved:** B-013 (#100 SSRF guard on validateDestination + createShortURL), B-014 (#101 favicon path-traversal containment).
- **Note:** cycle 8 was interrupted once by a monthly spend-limit; partial work was discarded and the cycle retried cleanly from the cycle-7 checkpoint (no partial state carried forward).
- **Evidence:** F-004/F-005 — `g2ml_destinationHostIsAllowed()` and `g2ml_isPrivateOrReservedIp()` added to `security.php`; `validateDestination()` calls the guard before any HEAD fetch; `createShortURL()` rejects disallowed hosts at creation time. Blocked: `169.254.169.254`, `127.0.0.1`, `10.x.x.x`, `192.168.x.x`, userinfo in URL. Allowed: public IPs. Seed `014_redirect_ssrf_settings.sql` adds `redirect.allow_private_destinations` (default `'0'`). F-006 — `favicon.php` confines `orgLogoPath` with `basename()`+`realpath()` inside the uploads dir; out-of-dir or missing paths fall through to the default favicon. Regression: `php tests/run.php` → 90 passed / 0 failed (was 61; +25 SSRF unit tests, +4 favicon traversal tests). `php -l` clean on all 4 changed PHP files. Bucket 1.
- **Remaining open security findings:** 0 Critical, 0 High, 0 Med. 2 Low: F-007 (`validateUserSession()` no userUID re-bind), F-008 (missing SRI on CDN tags).
- **Next purple-team target:** F-008 (SRI on CDN tags in `footer.php`, `header.php`, and B error pages) — one quick cycle to clear both Low findings and reach SECURE exit gate.
- **Branch state:** clean commit on `autopilot/2026-06-05`; not pushed (user pushes manually).

### Cycle 7 — 2026-06-28 — SECURE (purple-team, F-002/#98 + F-003/#99)

- **Items resolved:** B-015 (#98 deletion-cancel CSRF→POST), B-012 (#99 interstitial scheme guard).
- **Evidence:** F-002 — GET `?cancel` path removed from `privacy/delete/index.php`; cancellation now requires a CSRF-verified POST via form name `account_delete_cancel` (distinct from `account_delete`); ownership, not-cancellable, and activity-log logic preserved. F-003 — every destination/fallback URL sink in `validating.php` and `expired.php` (href, JS `window.location`, `meta-refresh`, noscript) scheme-guarded via `g2ml_sanitiseURL()` (reachable in Component B via `page_init`) plus `preg_match('#^https?://#i')` inline fallback; rejected destination → no link rendered; rejected fallback → `https://go2my.link`. Regression: `tests/unit/redirect_scheme_guard_test.php` — 8 new tests. `php tests/run.php` → 61 passed / 0 failed (was 53). `php -l` clean on all 4 changed files. Bucket 1.
- **Remaining open Med findings:** F-004/#100 (SSRF in `validateDestination`, off by default). No open High/Critical.
- **Next purple-team target:** F-004 + F-005 + F-006 as a destination-safety cluster, then F-008 SRI (Low). After that, SECURE exit gate.
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
- **cycles-done:** 7  (0=DISCOVER, 2–3=STABILIZE correctness, 4=STABILIZE test harness, 5=SECURE Phase 0, 6=SECURE purple-team F-001, 7=SECURE purple-team F-002+F-003)
- **branch:** `autopilot/2026-06-05`
- **working tree at seed:** clean except untracked `.claude/agents/`,
  `.claude/settings.json` (autopilot scaffolding — not production code).
- **artifacts added (cycle 5):** `SECURITY.md` (repo root — threat model, attack-surface map, tooling sweep, multi-role fixtures plan, coverage ledger, findings register F-001–F-008 open / F-101–F-108 fixed).
- **artifacts added (cycle 6):** `tests/unit/security_clientip_test.php` (18 regression tests for `g2ml_getClientIP`, `g2ml_isTrustedProxy`, `g2ml_ipInRange`); updated `web/_functions/security.php` (trusted-proxy allowlist); `docs/INSTALL.md` + installer heredoc (TRUSTED_PROXIES documentation).
- **artifacts added (cycle 7):** `tests/unit/redirect_scheme_guard_test.php` (8 regression tests for interstitial scheme guard); updated `web/G2My.Link/public_html/validating.php` + `expired.php` (scheme guard on all URL sinks); updated `web/Go2My.Link/_admin/public_html/pages/privacy/delete/index.php` (deletion-cancel CSRF→POST).

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
  **source:** #99, audit §3.2 · **status:** ✅ Done (cycle 7). All destination/fallback sinks in `validating.php` and `expired.php` (`href`, JS `window.location`, `meta-refresh`, noscript) scheme-guarded via `g2ml_sanitiseURL()` (http(s) only) with `preg_match('#^https?://#i')` fallback; rejected destination → no link; rejected fallback → `https://go2my.link`; `htmlspecialchars` retained. 8 regression tests added; suite → 61 unit / 0 failed.

- **id:** B-013
  **title:** Harden against SSRF — guard server-side destination fetch and validate created URLs against internal/private hosts
  **category:** security · **impact:** Medium · **component:** B/shared
  **source:** #100, audit §3.3 · **status:** ✅ Done (cycle 8). Shared helper `g2ml_destinationHostIsAllowed()` + `g2ml_isPrivateOrReservedIp()` added to `security.php` (inet_pton-based range checks; http/https only; rejects userinfo; resolves A/AAAA, requires ALL IPs to pass; IPv4-mapped-IPv6 unwrapped; fails closed). `validateDestination()` calls the guard BEFORE the `get_headers()` HEAD fetch; disallowed → existing failure shape, no network call. `createShortURL()` rejects disallowed hosts at creation time (no row inserted). New setting `redirect.allow_private_destinations` (default '0'; overrides RFC1918/ULA only — loopback/link-local/metadata/reserved always blocked). Seed `web/_sql/seeds/014_redirect_ssrf_settings.sql`. 25 unit regression tests (`tests/unit/security_ssrf_host_guard_test.php`); suite → 90 passed / 0 failed (was 61). `php -l` clean on all changed files.

- **id:** B-014
  **title:** Path-traversal latent in dynamic favicon handler — confine `readfile()` to uploads dir
  **category:** security · **impact:** Medium · **component:** B
  **source:** #101, audit §3.2 · **status:** ✅ Done (cycle 8). `favicon.php` now confines the DB-sourced `orgLogoPath` with `basename()` + `realpath()` inside the uploads dir; paths that escape the dir or do not exist fall through to the default favicon. 4 unit regression tests (`tests/unit/favicon_path_traversal_test.php`). `php -l` clean.

- **id:** B-015
  **title:** Account-deletion cancellation is a state-changing GET with no CSRF token
  **category:** security · **impact:** Medium · **component:** A
  **source:** #98, audit §3.3 · **status:** ✅ Done (cycle 7). Cancellation converted to a CSRF-protected POST (form name `account_delete_cancel`, distinct from the `account_delete` request form); GET `?cancel` path removed; ownership/not-cancellable/activity-log checks preserved.

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
  **source:** #23, audit §7.2 · **status:** ✅ Done (cycle 14). Built as FG-003:
  `pages/info/index.php` now branches on authentication via new pure helper
  `g2ml_infoDisplayDestination()` — see FEATURES.md FG-003 and the cycle-14
  Decision log entry.

- **id:** B-020
  **title:** Dashboard create/edit do not expose custom suffix, alias, or tags (#30)
  **category:** correctness (feature gap) · **impact:** Medium · **component:** A
  **source:** #30, audit §7.2/§8.3 · **status:** ✅ Done (cycle 13, create-path).
  Custom suffix/alias built on the create form (FG-001, cycle 10); tags built on
  the create form + links index (FG-002, cycle 13). Edit-form alias/tag
  management and tag-based filtering remain deferred (see FEATURES.md FG-002
  out-of-scope note) — track any further work there, not against this item.

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

- **id:** B-043
  **title:** `_g2ml_parseUserAgent()` regex delimiter bug (preg_quote missing '/' arg) — bot detection silently broken (isBot never set)
  **category:** correctness · **impact:** Medium · **component:** shared
  **source:** found cycle 11 · **status:** OPEN. `_g2ml_parseUserAgent()`
  (`web/_functions/activity_logger.php`, ~line 288) builds its bot regex with
  `preg_quote()` missing the `/` delimiter argument; the `'Java/'` pattern's
  unescaped `/` prematurely closes the `/…/` delimiter, causing
  `preg_match(): Unknown modifier '|'` on every call — bot detection silently
  broken (`isBot` never set). Fix is `preg_quote($p, '/')`.

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

- **id:** B-042
  **title:** `logActivity()` bind-type mismatch throws on every create — audit logging fails; per-IP rate-limit likely undermined
  **category:** correctness/security · **impact:** High · **component:** shared
  **source:** found cycle 10 (broader than #126 — #126 covers `sp_logActivity` procedure drift; this is the live `logActivity()` PHP function/direct-INSERT path) · **status:** ✅ Done (fixed cycle 11). The bind-param type string in `logActivity()` (`web/_functions/activity_logger.php`) was 20 chars against 21 columns/placeholders/variables (off-by-one) and mis-typed `ipAddress` as `i`; corrected to the 21-char `'ssisisssssssssssssiis'` with each char matching its column type (`statusCode`/`userUID`/`isBot`/`apiKeyUID` = `i`; `ipAddress` + `logData`/JSON + rest = `s`). Verified on MySQL 9.6: BEFORE → type-string error thrown, 0 rows inserted; AFTER → returns true, 1 row inserted with correct values; 5 create events now produce 5 countable `tblActivityLog` rows (the per-IP rate-limiter's `COUNT(*)` was effectively 0 while logging failed — abuse-prevention gap closed). Regression: `tests/integration/activity_log_test.php` (2 cases). 110 unit + 13 integration pass; lint clean. Sibling `error_handler.php` `tblErrorLog` INSERT checked — aligned, no bug.

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
| 2026-06-28 | 7 | SECURE — purple-team (F-002/#98 + F-003/#99) | 29 (B-012 + B-015 resolved this cycle) | 0 open | 19 open | 10 open | clean (php -l on all 4 changed files) | 61 unit / 4 integration (8 new: `tests/unit/redirect_scheme_guard_test.php`) | Deletion-cancel CSRF→POST (B-015/#98) + interstitial scheme guard (B-012/#99) fixed. GET `?cancel` path removed; CSRF-protected POST form `account_delete_cancel` added. All destination/fallback sinks (`href`, JS `window.location`, `meta-refresh`, noscript) in `validating.php` + `expired.php` scheme-guarded via `g2ml_sanitiseURL()` + `preg_match('^https?://')` fallback; rejected destination → no link; rejected fallback → `https://go2my.link`. 61 tests pass / 0 failed (was 53). No open High/Critical findings. |
| 2026-06-29 | 8 | SECURE — purple-team (F-004/F-005 #100 + F-006 #101; destination/path-safety cluster) | 27 (B-013 + B-014 resolved this cycle) | 0 open | 17 open | 10 open | clean (php -l on all 4 changed files) | 90 unit / 4 integration (29 new: 25 in `tests/unit/security_ssrf_host_guard_test.php` + 4 in `tests/unit/favicon_path_traversal_test.php`) | Shared anti-SSRF host guard (`g2ml_destinationHostIsAllowed` + `g2ml_isPrivateOrReservedIp`) on both `validateDestination()` (before HEAD fetch) and `createShortURL()` (at creation); loopback/link-local/metadata (169.254.169.254)/reserved always blocked; RFC1918/ULA blocked by default (override `redirect.allow_private_destinations`); rejects userinfo (`user:pass@`); IPv4-mapped-IPv6 unwrapped; fails closed when settings/DNS unavailable; seed `014_redirect_ssrf_settings.sql`. Favicon `orgLogoPath` confined with `basename()`+`realpath()` inside uploads dir (F-006). 90 tests pass / 0 failed (+29). 0 Critical/High/Med findings remaining; 2 Low open (F-007 session re-bind, F-008 SRI). Note: cycle 8 was interrupted once by a monthly spend-limit; partial work was discarded and the cycle retried cleanly from the cycle-7 checkpoint. |
| 2026-06-29 | 9 | SECURE — purple-team (F-007 + F-008); **completes SECURE** | 27 (F-007/F-008 have no GitHub #) | 0 open | 17 open | 10 open | clean (php -l on all 6 changed files) | 90 unit / 5 integration (1 new: `tests/integration/session_rebind_test.php`) | Final 2 Low fixed → **findings register 0 open**. F-007: `validateUserSession()` re-binds `$_SESSION['user_uid']` from the DB session row (defence-in-depth; negative-control test proven). F-008: the Bootstrap 5.3.3 CSS SRI hash was **wrong & inconsistent** across `header.php` + the 3 B error pages — would have **blocked the CSS in production** — corrected to the hash verified against the vendored copy AND the live jsdelivr CDN; other assets re-verified. 90 unit + 5 integration pass. Note: cycle 9's documentarian was interrupted by the monthly spend-limit; SECURITY.md had already been written, PROJECT.md completed inline on retry. SECURE COMPLETE; advancing to COMPLETE. |
| 2026-06-29 | 10 | COMPLETE — auto-built FG-001 (autonomy-eligible); surfaced B-042 | 27 (unchanged on GitHub) | 1 open (B-042 new) | 17 open | 10 open | clean (php -l across all changed files + new tests) | 110 unit / 11 integration (25 new unit: `tests/unit/custom_alias_test.php`; 6 new integration: `tests/integration/custom_alias_create_test.php`) | FG-001 custom short-suffix/alias BUILT under the autonomy test (table-stakes, in-scope per brief lines 90–92, risk:Low, non-destructive). `createShortURL()` gains `customCode` option — validates `^[A-Za-z0-9_-]{3,50}$`, blocks reserved words via `g2ml_isReservedShortCode()`, hard-errors "That alias is already taken." on duplicate (errno 1062, no random fallback); empty/absent → existing random+retry path unchanged. Dashboard create form adds optional "Custom alias" field with label + help text + error re-display; anonymous/public API path untouched. 5 acceptance criteria demonstrated. New bug B-042 surfaced: `logActivity()` bind-type mismatch throws on every create — swallowed silently but breaks audit logging and likely undermines per-IP rate-limit. Re-opening STABILIZE next to fix B-042. |
| 2026-07-04 | 11 | STABILIZE (re-open) — fixed B-042; surfaced B-043 | 27 (unchanged on GitHub) | 0 open | 18 open (B-043 new) | 10 open | clean (php -l on changed files) | 110 unit / 13 integration (2 new integration: `tests/integration/activity_log_test.php`) | `logActivity()` bind-param type-string off-by-one fixed (B-042) — 20-char string against 21 columns/placeholders/variables, plus `ipAddress` mis-typed `i`, corrected to `'ssisisssssssssssssiis'`. Evidence: BEFORE → type-string error, 0 rows inserted; AFTER → 1 row inserted; 5 create events → 5 countable `tblActivityLog` rows (per-IP rate-limit restored). 110 unit + 13 integration pass. New bug surfaced: B-043 — `_g2ml_parseUserAgent()` regex delimiter bug (`preg_quote()` missing `/` arg) breaks bot detection silently (`isBot` never set); queued next (same file, quick correctness fix). |
| 2026-07-04 | 12 | STABILIZE (re-open) — fixed B-043; **re-open resolved** | 27 (unchanged on GitHub) | 0 open | 17 open (B-043 ✅) | 10 open | clean (php -l on changed files) | 113 unit / 13 integration (1 new unit: `tests/unit/user_agent_bot_test.php`) | B-043 fixed: `_g2ml_parseUserAgent()` now escapes each bot pattern with `preg_quote($p, '/')` (full closure, no shorthand), so `'Java/'` no longer closes the `/…/` delimiter — bot detection works (`isBot` set) with no PCRE warning. Regression test (Java/ bot, Googlebot, non-bot Chrome). 113 unit pass; lint clean. STABILIZE re-open resolved; resuming COMPLETE (FG-002 tags next). |
| 2026-07-04 | 13 | COMPLETE — auto-built FG-002 (autonomy-eligible) | 27 (unchanged on GitHub) | 0 open | 16 open (B-020 ✅ create-path) | 10 open | clean (php -l on all changed files + new tests) | 132 unit / 18 integration (19 new unit: `tests/unit/tags_normalise_test.php`; 5 new integration: `tests/integration/tags_create_test.php`) | FG-002 tags on links BUILT under the autonomy test (Bucket 1: table-stakes + in-scope + risk:Low + non-destructive) — `tblTags`/`tblShortURLTags` schema existed since schema 020 but had zero PHP references. Wired up via find-or-create per org (`UQ_tag_org`), junction insert via `INSERT IGNORE`, tags attached AFTER the short-URL row insert with each tag in its own try/catch (a tag failure never rolls back the create); slug ASCII-only via `g2ml_slugifyTag()`, display name preserves original casing/spacing; capped at `G2ML_MAX_TAGS_PER_LINK` = 10. Dashboard create form gains an optional comma-separated "Tags" field; links index renders WCAG-accessible tag badges (`role="list"`) via one dynamically-sized bound `IN(...)` query for the page's short-URL UIDs — no N+1, confirmed placeholder-only (no interpolation). 132 unit + 18 integration pass (was 113/13); 5 acceptance criteria demonstrated. B-020 (dashboard alias/tags gap) marked done for the create-path; edit-form tag management and tag-based filtering deferred as explicit follow-ups. |
| 2026-07-04 | 14 | COMPLETE — auto-built FG-003 (autonomy-eligible) | 27 (unchanged on GitHub) | 0 open | 15 open (B-019 ✅) | 10 open | clean (php -l on all changed files + new tests) | 141 unit / 18 integration (9 new unit: `tests/unit/info_display_destination_test.php`) | FG-003 info-page public-vs-authenticated view (#23) BUILT under the autonomy test (Bucket 1: table-stakes + in-scope + risk:Low + non-destructive) — the page docblock already documented the intent and short URLs redirect publicly so the destination is not secret. New pure helper `g2ml_infoDisplayDestination(array $linkData, bool $isAuthenticated): string` in new file `web/Go2My.Link/_functions/info_display.php` (returns the full destination when authenticated, masked domain[/...] when not, '' when none — old inline masking moved into it). `pages/info/index.php`: authenticated viewers (ANY authenticated viewer, not owner-only, per the docblock intent) see the full destination as escaped text (`g2ml_sanitiseOutput`, not a clickable link — no scheme/XSS vector); anonymous viewers keep the masked domain plus a new accessible "Log in to see the full destination" prompt linking to `/login` (no redirect-back param, avoiding open-redirect). New i18n key `info.login_for_full` in seed `010_phase6_translations.sql`. 141 unit pass (+9, was 132); lint clean; render harness confirmed a hostile payload is fully HTML-escaped for the authed view. B-019 (#23) marked done. |
