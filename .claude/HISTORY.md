# 🕓 Go2My.Link — Claude Work History

> Chronological log of significant Claude-assisted work, newest first. Portable
> (repo-tracked) so the project's working history is available on every machine.
> Companion to [.claude/memory/MEMORY.md](memory/MEMORY.md). Last updated **2026-07-18**.

---

## 2026-07-18 — Launch-prep close-out: ~22 issues closed, hygiene/db cluster fixed, PHPStan gate enforced

Branch: **`launch-prep/2026-07-09`** — **20 commits** (`feab6b1` → `cc3e2e7`), all committed,
**NOT pushed**. Open issues **61 → 28**. All dev-buildable, owner-input-free launch-prep work
is now complete.

### Closed ~22 built-but-open issues with evidence
- Phase-7/8 features that were built in the 2026-07-10 session but left open on GitHub:
  **#38, #39, #40, #41, #42, #43, #45, #46, #47, #48, #49, #50, #75, #91, #92, #135, #136,
  #137, #145, #146, #147**.
- **#120** (doc drift) closed via `fd17a42`. **#138** (GDPR export) closed via the existing fix
  `602573e` plus a new regression test `cd2a337`.

### Hygiene / accessibility / db cluster — built and closed
- #116 favicon (`1cb9790`), #119 lang/dir attributes (`fc3fac0`), #109 `<picture>` PNG fallback
  (`832dab7`), #110 forced-colors gradient fallback (`3ff0fb0`), #112 phpcs/phpstan legacy-dir
  excludes (`241731d`), #115 robots.txt/sitemap.xml (`4f4ae38`), #126 removed dead
  `sp_logActivity` stored procedure (`3f43c53`) + dry-run proc-count fix (`2002a66`), #118
  client-IP fallback helper dedupe (`1b50eef`), #114 branded error pages across A/B/C
  (`4cb068d`), #150 System-scope settings dedupe via a COALESCE generated-column unique key +
  migration `019` (`6de4e0e`), #128 alias-chain integrity migration checks (`ce7b756`), #44
  streaming CSV analytics export (`55bd5af`), #117 No-Shorthand house-rule sweep (`3fe0334`).

### #76 PHPStan — repaired, resolved, and promoted to a hard CI gate
- `42bebb1`: `phpstan.neon` repaired for phpstan 2.x (two removed-in-2.0 config keys were
  aborting the run entirely) + the legacy-dir exclude glob fixed so it actually matched files,
  not just the directory — net effect: phpstan L5 now runs cleanly and reports 45 real errors
  in shipping code (down from an unanalysed ~140).
- `cc3e2e7`: all 45 resolved root-cause (no `@phpstan-ignore`, no baseline, no widening) and
  the CI PHPStan step flipped from advisory (`continue-on-error: true`) to a hard gate. Two
  real bugs surfaced along the way: (1) `public_html_landing/index.php` — the coming-soon
  page's logo `alt` text was blank because `$siteName` was never defined; (2)
  `analytics/index.php` — the date-range preset "active" highlighting was silently dead, a
  string-vs-int compare caused by PHP auto-casting decimal-string array keys. Also introduced
  typed accessors `g2ml_getEnvironment()`/`g2ml_getComponent()` in `page_init.php`, root-causing
  a set of cross-file constant-narrowing false positives instead of suppressing them.
- The phpcs half (9,694 errors / 1,307 warnings / 148 files, 9,354 phpcbf-auto-fixable) is
  deferred to new follow-up **#153**; **#76 stays open** for that half only — the PHPStan half
  is done and enforced.

### Left open deliberately
- **#149** API Low-severity residuals — all fixed or accept-documented; awaiting owner
  ratification of the remaining two (per-org vs per-key rate limiting; `maxLinks` TOCTOU).

### New follow-up issues filed
- **#151** expose captured UTM as an analytics dimension (from #92); **#152** xlsx export via
  PhpSpreadsheet vs native (from #44); **#153** phpcs conformance + flip the PHPCS CI gate
  (from #76).

### Correction to the record
- Reconciliation found the leaked legacy `web/G2My.Link/public_html_legacy/dbConfig.php` is
  already **gone from disk** — deleted outside this repo's tracked work, roughly 2026-07-10;
  the rest of the legacy dir remains. **#93 stays open** for the actual credential rotation +
  dir archival (owner ops) — the file's absence is not evidence the password was rotated.

### New owner deploy note
- Run migration **`019_settings_scope_dedupe.sql`** on any existing DB before deploying
  (collapses duplicate System-scope settings rows via a COALESCE generated-column unique key).
  Also: `sp_logActivity` was removed, so a correctly-provisioned DB now has **2** stored
  procedures, not 3 (`dry_run.sql` updated to match).

### Remaining 28 open issues
- Owner-blocked: #93 (cred rotation), #71 (translations), #57–60 (Phase-11 SIGNula billing),
  #34–37 (Phase-10 SIGNula auth). Post-launch phase: #51–56 (Phase-9 advanced redirects).
  For-consideration/owner-triage: #139–144, #149. Dev follow-ups: #151, #152, #153, #76
  (phpcs half), #127 (`orgHandle` tech-debt decision).

---

## 2026-07-10 — Execution: 3 launch-blockers, full public API, CueRCode, custom domains, analytics, premium tiers, Component C (in progress)

Branch: **`launch-prep/2026-07-09`** — all committed, **NOT pushed**. Sequential Sonnet implementation agents, one issue + one commit + an Opus review per piece.

### Launch-blockers found + fixed (all broke a fresh install; none were tracked)
- **#135** login (`avatarURL`→`avatarPath`), **#136** registration (missing `NOT NULL username` → auto-derive unique), **#138** GDPR export (non-existent cols). Column-audit sweep → **0 remaining P0**. Also fixed `g2my.link` self-404 (custom-domain seed) + a redoc CVE + a `#38` key-prefix parse bug + seed-010 missing `USE`.

### Public API (Phase 7) — built, documented, self-serve, security-reviewed
- **#38** framework (`api_auth.php`/`api_ratelimit.php`, key auth `g2ml_<prefix>_<sha256>`, scopes, DB rate-limit, request log, envelope, `/api/v1` front controller). **#39** endpoints (URL CRUD/bulk/list + org, BOLA-safe org-scoping + pre-auth IP backoff). **#40** key-management dashboard (one-time secret). **#75** OpenAPI 3.1 spec + self-hosted Redoc at `/api/docs`.
- **#145 CueRCode wiring** — QR-link create/re-point/scan attribution (`createShortURL()` + `logActivity()` extended, hot-path-safe). **CueRCode integrates via `/api/v1` with a `qr:link` key.**

### Custom domains, analytics, tiers, UTM
- **#91** ownership verification (DNS TXT) + verified-only routing (no namespace leak) + grandfather migration + `docs/CUSTOM_DOMAINS.md`.
- **#41** analytics data layer + `/api/v1/analytics` + indexes (closed **#125**). **#42** analytics dashboard (Chart.js + accessible tables, theme-aware).
- **#146** feature-entitlement/premium-tier gating (`entitlements.php`, fail-open; enforces `maxLinks`/API-daily/domain-cap). ⚠️ Free tier now ENFORCED — migrated orgs need paid tiers assigned.
- **#92** UTM capture + forwarding on redirect (settings-gated, off by default, byte-identical-when-off).
- **#147** CSRF token-overwrite bug (per-row same-named forms) — fixed across all 4 affected pages.

### Component C (LinksPage) — 6/6 COMPLETE
- **#45** renderer (system templates + escaped 7 placeholders), **#48** management UI (ownership-enforced CRUD, `maxLinksPages`-gated), **#47** template picker + owner-only IDOR-safe preview, **#46** custom-domain LinksPage fallback, **#50** age-gate (good-faith signed-cookie + adult-domain auto-flag, no DOB), **#49** custom-HTML/WYSIWYG (Opus — DOM allowlist sanitiser + `script-src 'none'` CSP + premium-gated + kill-switch off by default; adversarial XSS battery passed).
- ⚠️ **#49 deploy notes:** run **migration 016** before deploying to an existing DB (else `getOrgTier` fails-open → all gating disabled, since it now selects `hasCustomHTML`); keep custom-HTML OFF (default) until a human/security sign-off (highest XSS surface).

### IP geolocation (#43)
- Vendored the pure-PHP `MaxMind\Db\Reader` (maxmind-db/reader-php **v1.13.1**, Apache-2.0, checksum-documented) at `web/_libraries/maxminddb/` — no `ext-maxminddb` C extension required, matching Dreamhost's no-CLI/no-Composer constraint.
- `web/_functions/geolocation.php` — `g2ml_geolocateIP()` behind `analytics.geolocation_enabled` (OFF by default) AND a `.mmdb` file-exists check (`analytics.geoip_db_path`); a `$GLOBALS['g2ml_geoip_reader_override']` seam (mirrors the DNS/entitlements idiom) lets tests fully avoid a real database. Private/reserved IPs (reuses `g2ml_isPrivateOrReservedIp()`) and any decoder failure resolve to all-null, never throw.
- `logActivity()` INSERT extended from 23 → **26** bound columns (countryCode/regionCode/cityName trailing) — recounted and proven via an extended `activity_log_test.php`.
- Wired into the Component B redirect hot path (`index.php`) — computed only inside the already-existing `$shouldLog` branch, so it adds zero new tracking surface and is byte-identical when off/absent (proven by 4 new end-to-end integration tests using a mocked reader).
- `countryCode` added to the `g2ml_analyticsBreakdown()` whitelist (was explicitly out-of-scope pending this) + a country doughnut widget on the `#42` dashboard.
- CI/deploy: `scripts/fetch-geoip.sh` (never fails the job) + a `sftp-deploy.yml` step using the `MAXMIND_LICENSE_KEY` org secret — the `.mmdb` is git-ignored and deployed via its OWN non-`--delete` mirror, so a failed/skipped fetch can never wipe a previously-working production database. The setting must be turned on manually, post-deploy, once the database has actually landed.
- 502 unit / 171 integration tests green (up from 422/147).

### Housekeeping
- Closed **22** verified-fixed issues; filed enhancement issues **#139–144** + tracking issues **#145–147, #146** (entitlements). Test coverage grew **189/21 → 502 unit / 171 integration** (all green). Strategic plan `docs/LAUNCH_PLAN_2026-07-09.md`; `HANDOFF.md` kept current.
- **Owner actions still pending (deferred):** #93 credential rotation, 480-URL migration (+ assign tiers), legal sign-off, push/review the branch.

---

## 2026-07-09 — Launch-readiness review, strategic roadmap & P0 login-blocker

Branch: **`launch-prep/2026-07-09`** (off `hardening/cycle-2-2026-07-04` @ `46fe7a5`) — committed, **not pushed**.

### Full no-assumptions review (issues + milestones + Project #4 + codebase)
- Reconciled GitHub state against the tree: the `autopilot/2026-06-05` + `hardening/cycle-2` runs reached **VERIFY PASS / COMPLETE** (merged PR #130). **~19–24 launch-hardening issues are fixed in code but still OPEN** — need closing with commit refs. **A + B are code-complete for launch.**
- Confirmed: API framework is 100% greenfield (`tblAPIKeys` has zero PHP refs) → **hard blocker for CueRCode**; Component C scaffolding-only; custom domains ~70% (verification + docs missing, two domain tables disconnected); premium tiers = full data model / ~no enforcement; SIGNula = zero code.

### Fable 5 deep strategic plan → `docs/LAUNCH_PLAN_2026-07-09.md`
- 11 sections: per-component verdict, **Option A recommendation (ship A+B now, fast-follow)**, API framework architecture for Dreamhost/MySQLi (key hashing/scopes/DB rate-limit/versioning/envelope), **CueRCode create/re-point/scan contract**, **SIGNula OIDC + account-linking** model, **custom-domain DNS/TLS** mechanism (Cloudflare-for-SaaS recommended), tier ladder + gating layer, Component C build plan, security/lint sweep, 15 scored enhancement ideas, and a **model-tagged execution backlog P0–P4**.

### 🔴 NEW P0 launch-blocker found — #135
- `loginUser()` (`auth.php:219`) + `data_rights.php:324` + `profile/index.php:41` select/update a **non-existent `avatarURL` column** (schema is `avatarPath`) → **login broken on fresh install**; not caught because the test suite never drives `loginUser()`. Filed **#135**; fix + login integration test delegated to Sonnet.

### Housekeeping
- Reverted a CI-breaking YAML indent typo in `.github/workflows/ci.yml`.
- Wrote **`HANDOFF.md`**; updated `.claude/memory/MEMORY.md`.
- Do **NOT** merge stale local `main` — it would resurrect `public_html_legacy/` (the #93 credential) + an old conflicting UTM impl.

### Decisions pending from owner (block P1+ direction)
- Launch sequencing (rec: Option A); SIGNula's IdP-broker role (collapses #34–37); billing (Stripe vs SIGNula); Cloudflare-for-SaaS approval; final tier naming/currency; migration cutover window; Component C priority.

---

## 2026-06-04 / 05 — Deployment-readiness audit, launch hardening, installer & CueRCode

Branch: **`audit/launch-hardening-2026-06-04`** (commits `6897165`, `9f58807`) — committed, **not pushed**.

### Full deployment-readiness audit (`docs/AUDIT_2026-06-04.md`)
- Multi-agent audit across code quality, security, accessibility, conformance vs all 91 GitHub issues, and the project brief, for Components A/B/C.
- Verdict: **A + B launchable after a small fix set; C not built** (Phase 8 not started — must not be advertised beyond its landing page).
- Headline findings: untracked **plaintext legacy DB credential** in `web/G2My.Link/public_html_legacy/dbConfig.php` (verified never committed to git; now gitignored — still must be rotated, #93); link-edit `notes`→`urlNotes` bug; contact-form CRLF + missing server-side CAPTCHA; #80–#90 security hardening verified intact; **#92 UTM was wrongly recorded as done — corrected**.
- **28 issues filed (#93–#120)**, milestoned (new **v1.0.0 — Launch Hardening** #13 + v1.1.0), added to Project #4.

### P0/P1 fixes (commit `6897165`)
- Link edit `notes`→`urlNotes` (#94); contact-form CRLF subject strip (#97) + server-side CAPTCHA via shared `verifyCaptcha()` (#96); Component B favicon → `img/logo.png` fallback (#102); Component B CSP allows jsDelivr/cdnjs + inline (#103); `release.yml` lint tool → `parallel-lint` (#111); `.gitignore` guards for `**/dbConfig.php` + `**/public_html_legacy/` (#93).

### Schema review (`docs/SCHEMA_REVIEW_2026-06-04.md`) + installer + CueRCode (commit `9f58807`)
- **Empirical MySQL 9.6 verification** caught two critical blockers a read-only pass missed, both fixed: `sp_lookupShortURL` handler-before-DECLARE (proc wouldn't compile → broke all B redirects); `033_payments.sql` FK-before-table (aborted import).
- **Web installer** `web/Go2My.Link/public_html/install/` — self-locking, HTTPS-required, proof-of-control-token-gated full bootstrap; writes the shared `auth_creds.php` for all 3 components and creates the GlobalAdmin. Adversarial security review → hardened. Docs `docs/INSTALL.md`.
- **CueRCode dynamic-QR** schema hooks folded into base schema + clean migration `009_cuercode_qr_integration.sql` + seed `013_cuercode_settings.sql`; verified on MySQL 9.6. No local `tblQRCodes`.
- **8 schema issues filed (#121–#128)**, added to Project #4.

### Still outstanding
- 🔴 Rotate the legacy DB credential and remove/archive `public_html_legacy/` (#93, manual).
- Push/merge the launch-hardening branch (user pushes manually).

---

## Earlier (build phases — see MEMORY.md for detail)

- **2026-02 → 2026-06:** Phases 0–6 built (scaffolding, database, PHP framework, core product, auth & dashboard, organisation management, compliance/legal/pre-launch). Tagged **v0.7.0** and **v1.0.0-rc**.
- **Phase 7 early work:** email modernisation (multipart MIME + AMP, #88), breach-response system (#89–#90), security hardening (#79–#90), multi-account-type support.
- **2026-02-23:** phase restructuring (org mgmt + compliance pulled before launch; API/analytics prioritised post-launch; advanced auth + payments deferred to SIGNula).
- Initial scaffolding: 3-component `web/` tree, GitHub project #4, CI workflows (php-lint, release), branding/BrandKit.

> For the authoritative current state, file map, and conventions, read
> [.claude/memory/MEMORY.md](memory/MEMORY.md) and
> [.claude/memory/patterns.md](memory/patterns.md). Detailed root docs:
> `README.md`, `PROJECT_STATUS.md`, `CHANGELOG.md`, `DEV_NOTES.md`, `docs/`.
