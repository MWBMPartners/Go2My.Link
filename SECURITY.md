# 🔒 SECURITY.md — Go2My.Link

> **Owner:** `dev-team-security` (Phase 0 — threat model & attack-surface setup).
> **Mode:** DOC-ONLY this cycle. No attacks, no exploits, no fixes, no code changes.
> Purple-team (red-team + remediation) cycles come later.
> **Scope:** test-mode / local sandbox only.
> **Date:** 2026-06-28 · **Branch:** `autopilot/2026-06-05`
> **ID prefix:** `F-` (security findings). Mapped to GitHub issue # where one exists.
> **Inputs reconciled:** `docs/AUDIT_2026-06-04.md` §3, `docs/SCHEMA_REVIEW_2026-06-04.md`, issues #93–#128.

---

## 0. 🚨 Incident / active-compromise check

**No active-compromise signal found.** No backdoor, no unexplained outbound
exfiltration path, no committed live secret in the working tree or git history:

- `git log --all --full-history -- '**/dbConfig.php'` → **empty** (the legacy
  plaintext credential was **never committed**).
- `git check-ignore` now matches both `web/G2My.Link/public_html_legacy/dbConfig.php`
  and `web/_auth_keys/auth_creds.php` (both untracked + gitignored).
- No `eval`/`exec`/`shell_exec`/`system`/`passthru`/`popen`/`proc_open` in any
  tracked PHP file. No `unserialize()` of untrusted input. No CORS wildcard.

The only standing real-world action remains **#93**: rotate the legacy MWlink DB
credential on the host (treat as compromised because it sat in plaintext on disk)
and archive `public_html_legacy/` out of the deploy tree. This is an
operations/rotation task, **not** an incident — proceed normally.

---

## 1. 🧭 Threat model

### 1.1 Assets (crown jewels marked 👑)

| Asset | Where it lives | Why it matters |
|---|---|---|
| 👑 Short URLs + their destinations | `tblShortURLs` (resolved on the redirect hot path) | Integrity of every redirect; a tampered destination weaponises every link/QR already in the wild. |
| 👑 User credentials & sessions | `tblUsers.passwordHash` (Argon2id), `tblUserSessions` (sha256 token) | Account takeover; admin/GlobalAdmin takeover = full platform control. |
| 👑 Encryption keys / DB creds | `web/_auth_keys/auth_creds.php` (0600, gitignored) | AES-256-GCM salt/key + DB password; compromise decrypts all sensitive settings. |
| 👑 The installer | `web/Go2My.Link/public_html/install/` | Pre-lock, can create a GlobalAdmin and write `auth_creds.php`. Re-runnable = full takeover. |
| Org data & memberships | `tblOrganisations`, `tblOrgMembers`, `tblOrgShortDomains`, invitations | Cross-org (tenant) data exposure / IDOR. |
| API keys (future) | `tblAPIKeys` (schema-only; #38 unbuilt) | CueRCode / external API auth once built. **Not yet an attack surface.** |
| PII | `tblUsers`, `tblActivityLog`, data-export JSON | GDPR/CCPA exposure; data in logs. |

### 1.2 Actors / roles

- **Anonymous** — creates short URLs (rate-limited + CAPTCHA), visits redirects, hits public APIs.
- **User** — owns links, edits own profile/sessions, member of one org (`orgHandle`).
- **Admin** (of an org) — manages own org, members, invites, short domains.
- **GlobalAdmin** — cross-org; breach-response and platform tooling.
- **External API / CueRCode** — authenticates via `tblAPIKeys` (**not live** — #38 unbuilt).

### 1.3 Trust boundaries

1. **Component A** (go2my.link) — public site + create/consent APIs + auth pages.
2. **Component A Admin** (admin.go2my.link) — gated dashboard; `requireAuth()` at the router.
3. **Component B** (g2my.link) — the **redirect hot path**; takes a short code from the URL and emits a `Location:` to an attacker-influenceable stored destination. Highest blast radius.
4. **Component C** (lnks.page) — **scaffolding only**; must not be advertised. Minimal surface (logs a slug, 404s).
5. **The installer** — privileged bootstrap; self-locking trust boundary.
6. **App ↔ DB** — MySQLi prepared statements throughout (verified).
7. **App ↔ third-party** — Turnstile/reCAPTCHA siteverify, optional destination HEAD fetch (off by default), CDN assets.

### 1.4 Adversaries / abuse cases

- Unauthenticated attacker poisoning logs / evading rate-limits via spoofed `X-Forwarded-For` (**F-001**).
- Malicious authenticated user attempting cross-org IDOR (mitigated by `canManageOrg()` / `createdByUserUID` ownership checks — verified).
- Attacker tricking a victim's browser into a state-changing GET (deletion-cancel CSRF — **F-002**).
- Malicious/compromised destination (migrated legacy `javascript:` URL) surfacing on an interstitial (**F-003**).
- Admin/SSRF-enabled fetch reaching internal hosts (**F-004**, **F-005**).
- Future: API-key abuse once #38 ships (not yet live).

### 1.5 Crown-jewel paths to red-team hardest

1. Redirect resolution → `Location:` emission (Component B).
2. Auth (login/register/reset) + session lifecycle.
3. The installer (pre-lock).
4. Cross-org IDOR on link/org CRUD.

---

## 2. 🗺️ Attack-surface map

Every untrusted input → sensitive sink, with the controls verified present.

| Surface | Entry / file | AuthN/Z | CSRF | Input validation | Output enc. | Rate limit |
|---|---|---|---|---|---|---|
| Redirect resolve | `web/G2My.Link/_functions/redirect_resolver.php`, `domain_resolver.php` | n/a (public) | n/a | shortCode bound via prepared stmt; **`^https?://` scheme guard at L273-280** | n/a (`Location:`) | n/a |
| Interstitials | `expired.php`, `validating.php` | n/a | n/a | ✅ destination/fallback scheme-guarded via `g2ml_sanitiseURL()` + `preg_match('^https?://')` on all sinks (F-003 fixed, cycle 7) | `htmlspecialchars`+`json_encode` ✅ | n/a |
| Create API | `public_html/api/create/index.php` + `_functions/shorturl_create.php` | public | ✅ token | `g2ml_sanitiseURL` (http/https only); `g2ml_destinationHostIsAllowed()` rejects private-IP/userinfo (F-005 fixed, cycle 8) ✅ | JSON | ✅ per-IP (IP spoofable — F-001 fixed, cycle 6) |
| Consent API | `public_html/api/consent/index.php` | public | ✅ `cookie_consent` token + POST gate | JSON validated | referer host allowlisted ✅ | — |
| Login | `pages/login/index.php`, `auth.php` | n/a | ✅ `login_form` | email/pwd | redirect allowlisted (relative-only) ✅ | account-level lockout ✅ |
| Register | `pages/register/index.php` | n/a | ✅ `register_form` | ✅ | ✅ | CAPTCHA ✅ |
| Forgot / Reset / Verify | `pages/forgot-password,reset-password,verify-email` | n/a / token | ✅ | ✅ | ✅; enumeration-safe generic responses ✅ | — |
| Dashboard link CRUD | `_admin/.../pages/links/{edit,create}` | `requireAuth('User')` | ✅ | `urlNotes` bug fixed (#94) | ✅ | — |
| Link ownership | `links/edit` SELECT+UPDATE | **`createdByUserUID = ?` on both** ✅ (no IDOR) | ✅ | — | — | — |
| Profile / sessions | `_admin/.../pages/profile/*` | `requireAuth` | ✅ | — | ✅ | — |
| Org / invite | `_functions/org.php`, `_admin/.../pages/org/*`, `pages/invite/index.php` | **`canManageOrg()` on every mutator** ✅ | ✅ | accept checks email match + `[default]` org | ✅ | — |
| Admin pages | `_admin/public_html/index.php` (router gate `requireAuth('User')`) + per-page `requireAuth('GlobalAdmin')` | ✅ defence-in-depth | ✅ | — | `g2ml_sanitiseOutput` ✅ | — |
| Breach-response | `_admin/.../security/breach-response.php` | `requireAuth('GlobalAdmin')` | ✅ `breach_response_form` + POST gate | — | escaped ✅ | — |
| Privacy / deletion | `_admin/.../privacy/delete/index.php` | `requireAuth` + ownership | ✅ cancel is now a CSRF-protected POST (`account_delete_cancel` form; GET `?cancel` path removed — F-002 fixed, cycle 7) | int-cast | — | — |
| Contact form | `public_html/pages/contact/index.php` | public | — | **subject stripped of `\r\n\0`** ✅; CAPTCHA server-verified ✅ | — | per-IP (spoofable) |
| Installer | `install/index.php`, `install/.htaccess` | proof-of-control token + HTTPS-required + self-lock | — | — | — | — |
| robots / favicon | `robots.php`, `favicon.php` | public | n/a | favicon fallback chain ✅; org-logo path confined with `basename()`+`realpath()` inside uploads dir (F-006 fixed, cycle 8) ✅ | n/a | n/a |

### 2.1 Vulnerability-class checklist (CWE-tagged)

| Class | CWE | Applies? | Notes / files |
|---|---|---|---|
| SQL injection | 89 | **N/A — controlled** | MySQLi prepared statements + `bind_param` throughout; 6-query spot-check clean. |
| OS command injection | 78 | **N/A** | No `exec`/`shell_exec`/`system`/`popen`/`proc_open` in tracked code. |
| SSTI / EL injection | 1336 | **N/A** | No template engine; raw PHP with explicit escaping. |
| Path traversal | 22 | **N/A — controlled** | `favicon.php` org-logo path confined with `basename()`+`realpath()` inside uploads dir (F-006 fixed, cycle 8). Installer/email template names validated (#81). |
| Broken access control (IDOR/BOLA) | 639 | **N/A — controlled** | `createdByUserUID` on link CRUD; `canManageOrg()` on org mutators; admin role re-checks. |
| Privilege escalation / forced browsing | 269 | **N/A — controlled** | Router gate + per-page role checks. |
| AuthN weaknesses | 287/307 | **partial** | Argon2id; account lockout; enumeration-safe. Rate-limit keyed on spoofable IP (**F-001**). |
| Session management | 384/613 | **N/A — controlled** | `session_regenerate_id(true)` on login ✅. `validateUserSession()` now re-binds DB `userUID` to `$_SESSION['user_uid']` (F-007 fixed, cycle 9). |
| JWT | 347 | **N/A** | No JWT; DB-backed opaque session tokens. |
| OAuth/SSO redirect | 601 | **N/A** | Deferred (Phase 10). |
| XSS (stored/reflected/DOM) | 79 | **N/A — controlled** | `htmlspecialchars(ENT_QUOTES)` on output; `app.js` uses `escapeHTML()`; `document.write` is constant strings; no `eval`/`new Function`. |
| CSRF | 352 | **N/A — controlled** | Tokens on all POST handlers. Deletion-cancel converted to CSRF-protected POST (F-002 fixed, cycle 7). |
| Clickjacking / headers / CSP | 1021/693 | **N/A — controlled** | `frame-ancestors 'none'`; Component B CSP now allows the CDN it loads (#103 fixed). |
| CORS | 942 | **N/A** | No `Access-Control-Allow-Origin` set anywhere. |
| Open redirect | 601 | **N/A — controlled** | Login redirect relative-only ✅; consent referer allowlisted ✅. Interstitial destination/fallback scheme-guarded (F-003 fixed, cycle 7). |
| SSRF | 918 | **N/A — controlled** | `validateDestination()` now calls `g2ml_destinationHostIsAllowed()` before any HEAD fetch (F-004 fixed, cycle 8); `createShortURL()` rejects disallowed hosts at creation time (F-005 fixed, cycle 8); loopback/link-local/metadata/reserved always blocked; RFC1918/ULA blocked by default; fails closed. |
| Insecure deserialization | 502 | **N/A** | No `unserialize()` of untrusted input. |
| Mass assignment | 915 | **N/A — controlled** | Explicit column allowlists in INSERT/UPDATE. |
| XXE | 611 | **N/A** | No XML parsing of untrusted input (XML API output is deferred/unbuilt). |
| Business-logic / TOCTOU | 367 | **investigate** | Short-code generation check-then-insert without 1062 retry (schema review; ties to #124). |
| Sensitive data exposure | 200/532 | **partial** | Spoofable client IP poisons logs (**F-001**). No raw passwords/tokens logged (verified). |
| Weak crypto | 327/916 | **N/A — controlled** | Argon2id + AES-256-GCM; no MD5/SHA1 for passwords. |
| Insecure randomness | 338 | **N/A** | `random_bytes`/`bin2hex` for tokens (security.php). |
| Hardcoded secrets | 798 | **N/A — controlled** | Creds gitignored + untracked; none in tracked code or history (see §0). |
| Dependency CVEs | 1035/1104 | **N/A — controlled** | All pinned & current (see §3.3). |
| Security misconfig | 16 | **partial** | Installer well-locked. SRI corrected on all CDN tags (F-008 fixed, cycle 9 — Bootstrap CSS hash was wrong/inconsistent and would have blocked the asset). Non-shipping `public_html_*` variants remain a hygiene risk. |
| Container/IaC | — | **N/A** | Shared hosting (Dreamhost); no Docker/k8s/Terraform. |
| Missing rate-limit / ReDoS | 770/1333 | **partial** | Anon create + login limited (IP key spoofable — F-001). No obvious ReDoS in own regexes. |
| API excessive exposure / inventory | 200 | **investigate (future)** | `docs/API.md` documents endpoints that don't exist (doc/code drift); `tblAPIKeys` schema-only. Re-audit when #38 ships. |

---

## 3. 🛠️ Tooling sweep

### 3.1 Secret scan

- **Method:** `git grep` for `define/password/secret/salt/key` + manual review of
  `.gitignore` + `git log --all --full-history`. `gitleaks`/`trufflehog` **not
  installed** (coverage gap — recommend installing the gitleaks CLI for history scan).
- **Result — committed secrets: NO.** No credential, key, or token is present in any
  tracked file or in git history.
  - `web/_auth_keys/auth_creds.php` — real local dev creds on disk, **untracked + gitignored** (matches `**/auth_creds.php`). Type: DB password + AES salt/key + 3rd-party secrets. **Value not recorded.**
  - `web/G2My.Link/public_html_legacy/dbConfig.php` — legacy plaintext DB password on disk, **untracked + gitignored** (matches `**/dbConfig.php` and `**/public_html_legacy/`). **Never committed.** Still must be **rotated** (#93) and the dir archived.
  - No `auth_creds.example.php` template exists yet (onboarding gap, not a leak).

### 3.2 SAST-style grep

| Check | Result |
|---|---|
| `eval/exec/system/shell_exec/passthru/popen/proc_open/assert` | **0 hits** in tracked PHP. |
| SQL built by concatenation/interpolation | **0** — all queries use prepared statements + `bind_param`. |
| `unserialize()` of untrusted input | **0**. |
| Dynamic `include/require $var` | Only fixed bootstrap paths (`G2ML_ROOT`, `__DIR__`, validated router map) — no user-controlled include. |
| `header('Location: $var')` | Reviewed all: redirect_resolver (scheme-guarded ✅), consent (host-allowlisted ✅), login (relative-only ✅), create API (constant/urlencoded ✅). |
| `mail()` with user header | 1 call (`contact/index.php`) — subject **stripped of `\r\n\0`** before use (#97 fixed). `email.php` `g2ml_sendEmail` strips CRLF from all headers (#80). |
| JS `innerHTML=` / `document.write` | `app.js` dynamic value via `escapeHTML()` ✅; `document.write` writes constant local-fallback strings ✅. |
| JS `eval` / `new Function` | **0**. |
| Open redirect (`Location:` from user input w/o allowlist) | none unguarded (see above). |

### 3.3 Dependency check (vendored + CDN)

| Library | Version | Pinned? | SRI on CDN tag? | CVE verdict |
|---|---|---|---|---|
| Bootstrap | 5.3.3 (vendored + jsdelivr) | ✅ | ✅ **corrected (cycle 9)** — CSS SRI hash was wrong/inconsistent across 4 files (would have blocked the asset); now `sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH` verified against vendored copy + live CDN. JS re-verified correct. | No known high-sev CVE. |
| jQuery | 3.7.1 (vendored + code.jquery.com) | ✅ | ✅ **verified correct (cycle 9)** | No known high-sev CVE (≥3.5 patched the prior XSS). |
| Font Awesome | 6.5.1 (vendored + cdnjs) | ✅ | ✅ **verified correct (cycle 9)** | No known high-sev CVE. |
| Chart.js | vendored `chart.umd.min.js` (version not stamped) | local-only | n/a | Verify exact version when analytics ships; no CDN tag in use. |

**Dependency-CVE verdict: PASS.** All three primary libs are current, pinned
versions with no known high-severity CVE. F-008 resolved (cycle 9) — the Bootstrap
CSS SRI hash was incorrect across 4 files and has been corrected; all other CDN
hashes re-verified. Chart.js carries no embedded version stamp — record its version
when the analytics layer is built.

---

## 4. 👥 Multi-role fixtures plan (for purple-team)

Stand up a throwaway MySQL via `/opt/homebrew/opt/mysql/bin/mysqld` (the
`tests/README.md` one-liner already does init → start → import schema/procedures →
run → teardown over a short `/tmp` socket). Then seed these identities:

| Fixture | Role | Org | How to seed | Tests it unlocks |
|---|---|---|---|---|
| `anon` | anonymous | — | no session | rate-limit/CAPTCHA bypass, redirect resolution, public APIs |
| `userA` | User | `orgA` | `registerUser()` → verify → `loginUser()`; create links via `createShortURL()` under orgA | own-object baseline |
| `userB` | User | `orgB` | same, second org | **cross-org IDOR**: userB reaches userA's link/org objects |
| `adminA` | Admin | `orgA` | seed `tblUsers.role=Admin` + `tblUserAccountTypes(admin)` scoped to orgA | BFLA: adminA mutates orgB; `canManageOrg` boundary |
| `globalAdmin` | GlobalAdmin | `[default]` | installer's `g2ml_hashPassword()` + INSERT `tblUsers(role=GlobalAdmin)` + `tblUserAccountTypes(globaladmin)` | vertical-escalation target; breach-response access |

Seed links via `createShortURL()` (not raw INSERT) so `createdByUserUID`/`orgHandle`
ownership columns are populated correctly — that is exactly what the IDOR tests probe.
Authenticate each by driving `loginUser()` and reusing the issued session token, or by
seeding a `tblUserSessions` row with a known sha256 token. **GlobalAdmin and a
second-org user are the two fixtures the current test suite does not create** — they
are the prerequisite for the access-control class.

---

## 5. 📊 Coverage ledger

Existing suite: `tests/unit/` (DB-free) covers `security.php` only — password
(Argon2id), sanitisers, AES encryption, CSRF token round-trip. `tests/integration/`
covers `sp_lookupShortURL` (redirect lookup compiles + returns status). Pure-PHP
micro-framework (`tests/bootstrap.php`); MySQL available locally.

| Class | Tested? | How | Result | Gaps (purple-team priorities) |
|---|---|---|---|---|
| SQL injection (CWE-89) | partial | manual grep + prepared-stmt review | clean | no automated injection probe |
| Path traversal (CWE-22) | **yes** | cycle-8 regression tests | F-006 fixed — `basename()`+`realpath()` containment; 4 unit tests (`tests/unit/favicon_path_traversal_test.php`) | traversal fuzz beyond the unit fixture |
| Broken access control (CWE-639) | **no** | manual read of ownership checks | looks controlled | **no userB / GlobalAdmin fixtures → IDOR untested end-to-end** |
| Privilege escalation (CWE-269) | no | manual read | router+page gates present | no test driving adminA→orgB / user→admin route |
| AuthN / rate-limit (CWE-307) | partial | unit (hash/CSRF) | Argon2id confirmed | login lockout + spoofed-IP rate-limit (F-001) untested |
| Session mgmt (CWE-384) | **yes** | cycle-9 DB-backed regression test | `validateUserSession()` re-binds `userUID` from DB row (F-007 fixed); `session_regenerate_id(true)` on login ✅; new `tests/integration/session_rebind_test.php` — negative-control proven | full session-fixation end-to-end probe (purple-team priority) |
| XSS (CWE-79) | no | manual read + grep | output escaped | no DOM/stored XSS probe |
| CSRF (CWE-352) | **yes** | unit token round-trip + cycle-7 fix | tokens on all POST handlers; deletion-cancel → CSRF POST (F-002 fixed) | — |
| Open redirect (CWE-601) | **yes** | manual read + cycle-7 regression tests | login/consent guarded; interstitial scheme-guarded (F-003 fixed); 8 new tests | — |
| SSRF (CWE-918) | **yes** | cycle-8 regression tests | F-004 + F-005 fixed — shared `g2ml_destinationHostIsAllowed()` guard; 25 unit tests (`tests/unit/security_ssrf_host_guard_test.php`) covering loopback/link-local/metadata/RFC1918/ULA/userinfo/IPv4-mapped and public-IP allow cases | live DNS resolution fuzz (unit tests use controlled stubs) |
| Crypto/secrets (CWE-798/327) | partial | unit (encryption) + secret scan | clean | no gitleaks history scan (tool absent) |
| Dependencies (CWE-1035) | **yes** | version/SRI grep + cycle-9 hash verification | PASS; F-008 fixed (Bootstrap CSS hash corrected — wrong hash would have blocked the asset in production; all other CDN hashes re-verified correct) | gitleaks/semgrep/osv-scanner not installed |
| Misconfig/installer (CWE-16) | no | manual read | well-locked | no test that re-run installer refuses post-lock |
| Container/IaC | n/a | — | shared hosting | n/a |
| API inventory (CWE-200) | n/a (future) | — | #38 unbuilt | re-audit when API-key auth ships |

---

## 6. 🎯 Findings register (`F-`)

Severity = impact on assets. Status reconciled against **current** branch code
(2026-06-05/06-28), not the original audit snapshot.
**Open:** 0 (0 Critical / 0 High / 0 Med / 0 Low). **Fixed on branch:** 16.

> 🔒 **SECURE phase complete** — all 8 F- register findings (F-001 through F-008) are now either fixed or verified intact. No open findings remain. Purple-team cycles 5–9 covered: Phase-0 threat model (cycle 5), F-001 High (cycle 6), F-002/F-003 Med (cycle 7), F-004/F-005/F-006 Med/Low (cycle 8), F-007/F-008 Low (cycle 9). Register is fully closed; the run advances to COMPLETE.

| ID | Title | Sev | Status | CWE | Evidence (file:line) | GH # |
|---|---|---|---|---|---|---|
| **F-001** | Spoofable client IP — `g2ml_getClientIP()` trusts `X-Forwarded-For`/`X-Real-IP` with no trusted-proxy allowlist; poisons activity/consent/breach logs, `lastLoginIP`, new-login heuristic, and lets anon rate-limit be evaded | **High** | ✅ **FIXED** (cycle 6, branch `autopilot/2026-06-05`) — `REMOTE_ADDR` default; XFF honoured only when `REMOTE_ADDR` is in `TRUSTED_PROXIES` allowlist; CIDR helper via `inet_pton`; 18 regression tests added (53 total) | 290/348 | `web/_functions/security.php` — `g2ml_getClientIP()`, `g2ml_isTrustedProxy()`, `g2ml_ipInRange()` | #95 |
| **F-002** | Account-deletion **cancel** is a state-changing GET with no CSRF token — forged GET (img/link) cancels a victim's own pending deletion | **Med** | ✅ **FIXED** (cycle 7) — cancellation converted to a CSRF-protected POST (form name `account_delete_cancel`, distinct from `account_delete`); GET `?cancel` path removed; ownership/not-cancellable/activity-log checks preserved | 352 | `web/Go2My.Link/_admin/public_html/pages/privacy/delete/index.php` | #98 |
| **F-003** | Interstitials emit redirect destination into `href`/`window.location.href` with `htmlspecialchars` but **no `^https?://` scheme allowlist** — a migrated legacy `javascript:`/`data:` destination renders as a clickable/auto-followed link | **Med** | ✅ **FIXED** (cycle 7) — every destination/fallback sink (`href`, JS `window.location`, `meta-refresh`, noscript) scheme-guarded via `g2ml_sanitiseURL()` (http(s) only) with `preg_match('#^https?://#i')` fallback; rejected destination → no link; rejected fallback → `https://go2my.link`; `htmlspecialchars` retained; 8 new regression tests added | 79/601 | `web/G2My.Link/public_html/validating.php`, `expired.php` | #99 |
| **F-004** | SSRF in `validateDestination()` server-side HEAD fetch — `get_headers()` with no private/loopback/link-local/reserved-range guard. **OFF by default** (`redirect.validate_destination=false`); becomes an authenticated SSRF oracle if enabled | **Med** | ✅ **FIXED** (cycle 8) — `g2ml_destinationHostIsAllowed()` + `g2ml_isPrivateOrReservedIp()` added to `security.php`; `validateDestination()` calls the guard BEFORE the `get_headers()` HEAD fetch; disallowed host → existing failure shape, no network call; loopback/link-local/metadata (169.254.169.254)/reserved always blocked; RFC1918/ULA blocked by default (override via `redirect.allow_private_destinations`); IPv4-mapped-IPv6 unwrapped; fails closed when settings/DNS unavailable; seed `014_redirect_ssrf_settings.sql`; 25 unit regression tests added | 918 | `web/G2My.Link/_functions/redirect_resolver.php` (validateDestination); `web/_functions/security.php` (g2ml_destinationHostIsAllowed, g2ml_isPrivateOrReservedIp) | #100 |
| **F-005** | Created-URL host validation lacks private-IP/loopback/reserved-literal rejection and `user:pass@` userinfo stripping — stored destinations can point at internal hosts (feeds F-004 when fetch enabled) | **Low** | ✅ **FIXED** (cycle 8) — `shorturl_create.php` calls `g2ml_destinationHostIsAllowed()` before creating a row; disallowed host → existing return contract (no row inserted); userinfo rejection included in the shared guard | 918 | `web/Go2My.Link/_functions/shorturl_create.php` | #100 |
| **F-006** | Latent path traversal — `favicon.php` concatenates DB `orgLogoPath` into `readfile()`/`filesize()` with no `basename()`/`realpath()` containment; `getOrgFavicon()` returns the raw stored value. Latent today (no code writes `orgLogoPath`); live once org-logo upload ships | **Low** | ✅ **FIXED** (cycle 8) — `favicon.php` now confines the DB-sourced path with `basename()` + `realpath()` inside the uploads dir; paths that escape the uploads dir or do not exist fall through to the default favicon; 4 unit regression tests added | 22 | `web/G2My.Link/public_html/favicon.php` | #101 |
| **F-007** | `validateUserSession()` validates the DB session token but does not re-bind the session's `userUID` to `$_SESSION['user_uid']` (defence-in-depth; not exploitable today) | **Low** | ✅ **FIXED** (cycle 9) — `validateUserSession()` now re-binds `$_SESSION['user_uid'] = (int) $session['userUID']` from the authoritative DB session row after the token check, matching the `loginUser()`/`auth.php` convention; defence-in-depth against session confusion/fixation. New DB-backed regression test `tests/integration/session_rebind_test.php`; negative-control proven: disabling the re-bind line fails the test. | 384 | `web/_functions/session.php` | — (audit §3.3) |
| **F-008** | Bootstrap 5.3.3 CSS SRI hash was **wrong and inconsistent** across `header.php` and the 3 Component-B error pages (two different incorrect hashes); browsers would have **blocked Bootstrap CSS in production** (broken styling). Additionally, missing SRI on jQuery + Bootstrap JS in `footer.php` and FA CSS in `header.php` and B error pages. | **Low** | ✅ **FIXED** (cycle 9) — all 4 affected files corrected to the independently verified hash `sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH` (confirmed against both the vendored copy and the live jsdelivr CDN). Other assets (Bootstrap JS, RTL, jQuery 3.7.1, FA 6.5.1) re-verified correct. `footer.php` was untouched (already correct). | 353/494 | `web/_includes/header.php`; `web/G2My.Link/public_html/404.php`, `expired.php`, `validating.php` | — (new) |
| F-101 | Contact-form CRLF header injection | Med | **FIXED-on-branch** | 93 | subject stripped `\r\n\0` at `contact/index.php:152` | #97 |
| F-102 | Contact form CAPTCHA not verified server-side | Med | **FIXED-on-branch** | 290 | `verifyCaptcha()` enforced `contact/index.php:83-98` | #96 |
| F-103 | Component B CSP blocked its own error-page CDN CSS | Low | **FIXED-on-branch** | 693 | `style-src`/`font-src` now allow jsdelivr/cdnjs — `G2My.Link/public_html/.htaccess` CSP | #103 |
| F-104 | Component B default favicon always 404 | Low | **FIXED-on-branch** | — | fallback chain → `img/logo.png` at `favicon.php:122-143` | #102 |
| F-105 | Link-edit `notes`→`urlNotes` (broke editing; also IDOR check) | (functional) | **FIXED-on-branch** | 639 | `links/edit/index.php:55` (`s.urlNotes AS notes`); SELECT+UPDATE both `createdByUserUID = ?` | #94 |
| F-106 | Redirect destination `Location:` scheme guard | Med | **FIXED-on-branch** (resolver layer; F-003 is the residual interstitial gap) | 601 | `redirect_resolver.php:273-280` `^https?://` allowlist | — |
| F-107 | Email-header CRLF / template-path / salt-rotation / breach-TOCTOU / reset-token-in-URL hardening (#80–#84) | (various) | **FIXED — verified intact** | 93/22/362 | `email.php:93,155-202`; `breach_response.php`; `auth.php` | #80-#90 |
| F-108 | Legacy plaintext DB password in `public_html_legacy/dbConfig.php` | High | **MITIGATED-in-repo / rotation OPEN** | 798 | now gitignored + untracked + never committed (§0); **must still rotate the credential** | #93 |

### 6.1 Prioritised purple-team target list (highest severity first)

All findings F-001 through F-008 are now fixed. **Register is fully closed — 0 open findings.**

> The FIXED-on-branch set (F-101…F-107) remains unregressed (re-confirmed at cycle 9: 90 unit + 5 integration pass). The **rotation** action behind F-108 (#93) remains a manual operations task for the user.

---

## 7. 🧪 Sandbox & test setup

- **Run app locally:** Dreamhost-style PHP; no Composer. Use the web installer
  (`install/index.php`) against a throwaway DB, or seed manually.
- **Tests:** `php tests/run.php` (unit, DB-free); `php tests/run_integration.php`
  (DB-backed, skips cleanly with exit 0 if no DB). Lint: `find tests -name '*.php' -print0 | xargs -0 -n1 php -l`.
- **Throwaway MySQL:** `/opt/homebrew/opt/mysql/bin/mysqld` (available); the
  `tests/README.md` one-liner imports `web/_sql/schema/*` then `procedures/*` over a
  short `/tmp` socket and tears down.
- **Fixtures:** see §4 — seed anon/userA/userB/adminA/globalAdmin (the last two are
  the gap the current suite leaves open for access-control testing).

---

*SECURE phase complete (cycles 5–9). Phase 0 threat model + 8 findings fixed (F-001–F-008); findings register now 0 open. No secrets/active-compromise signal found; deps PASS. Advancing to COMPLETE.*
