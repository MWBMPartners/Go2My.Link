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
| Interstitials | `expired.php`, `validating.php` | n/a | n/a | ⚠️ destination/fallback **not** re-checked for scheme (**F-003**) | `htmlspecialchars`+`json_encode` ✅ | n/a |
| Create API | `public_html/api/create/index.php` + `_functions/shorturl_create.php` | public | ✅ token | `g2ml_sanitiseURL` (http/https only); ⚠️ **no private-IP/userinfo block** (**F-005**) | JSON | ✅ per-IP (IP spoofable — F-001) |
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
| Privacy / deletion | `_admin/.../privacy/delete/index.php` | `requireAuth` + ownership | ⚠️ **cancel is state-changing GET, no CSRF** (**F-002**) | int-cast | — | — |
| Contact form | `public_html/pages/contact/index.php` | public | — | **subject stripped of `\r\n\0`** ✅; CAPTCHA server-verified ✅ | — | per-IP (spoofable) |
| Installer | `install/index.php`, `install/.htaccess` | proof-of-control token + HTTPS-required + self-lock | — | — | — | — |
| robots / favicon | `robots.php`, `favicon.php` | public | n/a | favicon fallback chain ✅; ⚠️ org-logo path **not** `basename()/realpath()` confined (**F-006**, latent) | n/a | n/a |

### 2.1 Vulnerability-class checklist (CWE-tagged)

| Class | CWE | Applies? | Notes / files |
|---|---|---|---|
| SQL injection | 89 | **N/A — controlled** | MySQLi prepared statements + `bind_param` throughout; 6-query spot-check clean. |
| OS command injection | 78 | **N/A** | No `exec`/`shell_exec`/`system`/`popen`/`proc_open` in tracked code. |
| SSTI / EL injection | 1336 | **N/A** | No template engine; raw PHP with explicit escaping. |
| Path traversal | 22 | **investigate** | `favicon.php` org-logo path unconfined (**F-006**, latent — no writer yet). Installer/email template names validated (#81). |
| Broken access control (IDOR/BOLA) | 639 | **N/A — controlled** | `createdByUserUID` on link CRUD; `canManageOrg()` on org mutators; admin role re-checks. |
| Privilege escalation / forced browsing | 269 | **N/A — controlled** | Router gate + per-page role checks. |
| AuthN weaknesses | 287/307 | **partial** | Argon2id; account lockout; enumeration-safe. Rate-limit keyed on spoofable IP (**F-001**). |
| Session management | 384/613 | **investigate** | `session_regenerate_id(true)` on login ✅. ⚠️ `validateUserSession()` does not re-bind DB `userUID` to `$_SESSION['user_uid']` (**F-007**, defence-in-depth). |
| JWT | 347 | **N/A** | No JWT; DB-backed opaque session tokens. |
| OAuth/SSO redirect | 601 | **N/A** | Deferred (Phase 10). |
| XSS (stored/reflected/DOM) | 79 | **N/A — controlled** | `htmlspecialchars(ENT_QUOTES)` on output; `app.js` uses `escapeHTML()`; `document.write` is constant strings; no `eval`/`new Function`. |
| CSRF | 352 | **partial** | Tokens on all POST handlers. ⚠️ deletion-cancel GET (**F-002**). |
| Clickjacking / headers / CSP | 1021/693 | **N/A — controlled** | `frame-ancestors 'none'`; Component B CSP now allows the CDN it loads (#103 fixed). |
| CORS | 942 | **N/A** | No `Access-Control-Allow-Origin` set anywhere. |
| Open redirect | 601 | **partial** | Login redirect relative-only ✅; consent referer allowlisted ✅. Interstitial scheme gap is **F-003**. |
| SSRF | 918 | **investigate** | `validateDestination()` HEAD fetch has no private-IP guard (**F-004**, OFF by default); created-URL host validation lacks private-IP/userinfo block (**F-005**). |
| Insecure deserialization | 502 | **N/A** | No `unserialize()` of untrusted input. |
| Mass assignment | 915 | **N/A — controlled** | Explicit column allowlists in INSERT/UPDATE. |
| XXE | 611 | **N/A** | No XML parsing of untrusted input (XML API output is deferred/unbuilt). |
| Business-logic / TOCTOU | 367 | **investigate** | Short-code generation check-then-insert without 1062 retry (schema review; ties to #124). |
| Sensitive data exposure | 200/532 | **partial** | Spoofable client IP poisons logs (**F-001**). No raw passwords/tokens logged (verified). |
| Weak crypto | 327/916 | **N/A — controlled** | Argon2id + AES-256-GCM; no MD5/SHA1 for passwords. |
| Insecure randomness | 338 | **N/A** | `random_bytes`/`bin2hex` for tokens (security.php). |
| Hardcoded secrets | 798 | **N/A — controlled** | Creds gitignored + untracked; none in tracked code or history (see §0). |
| Dependency CVEs | 1035/1104 | **N/A — controlled** | All pinned & current (see §3.3). |
| Security misconfig | 16 | **partial** | Installer well-locked. ⚠️ SRI missing on some CDN tags (**F-008**). Non-shipping `public_html_*` variants are hygiene risk. |
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
| Bootstrap | 5.3.3 (vendored + jsdelivr) | ✅ | ⚠️ present on most, **missing on `header.php`/`footer.php` JS+CSS** (**F-008**) | No known high-sev CVE. |
| jQuery | 3.7.1 (vendored + code.jquery.com) | ✅ | ⚠️ **missing** on `footer.php` script tag (**F-008**) | No known high-sev CVE (≥3.5 patched the prior XSS). |
| Font Awesome | 6.5.1 (vendored + cdnjs) | ✅ | ⚠️ missing on several `<link>` tags (**F-008**) | No known high-sev CVE. |
| Chart.js | vendored `chart.umd.min.js` (version not stamped) | local-only | n/a | Verify exact version when analytics ships; no CDN tag in use. |

**Dependency-CVE verdict: PASS.** All three primary libs are current, pinned
versions with no known high-severity CVE. The only dependency-class finding is the
**missing SRI** on a subset of CDN tags (F-008, Low). Chart.js carries no embedded
version stamp — record its version when the analytics layer is built.

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
| Path traversal (CWE-22) | no | manual read | F-006 (latent) | no test for favicon path; no traversal fuzz |
| Broken access control (CWE-639) | **no** | manual read of ownership checks | looks controlled | **no userB / GlobalAdmin fixtures → IDOR untested end-to-end** |
| Privilege escalation (CWE-269) | no | manual read | router+page gates present | no test driving adminA→orgB / user→admin route |
| AuthN / rate-limit (CWE-307) | partial | unit (hash/CSRF) | Argon2id confirmed | login lockout + spoofed-IP rate-limit (F-001) untested |
| Session mgmt (CWE-384) | no | manual read | regenerate-id present; F-007 | no session-fixation/binding test |
| XSS (CWE-79) | no | manual read + grep | output escaped | no DOM/stored XSS probe |
| CSRF (CWE-352) | partial | unit token round-trip | tokens present; F-002 | no test that deletion-cancel rejects forged GET |
| Open redirect (CWE-601) | no | manual read | login/consent guarded; F-003 | no interstitial `javascript:`-scheme test |
| SSRF (CWE-918) | no | manual read | F-004/F-005 | no private-IP/userinfo fetch test |
| Crypto/secrets (CWE-798/327) | partial | unit (encryption) + secret scan | clean | no gitleaks history scan (tool absent) |
| Dependencies (CWE-1035) | yes | version/SRI grep | PASS; F-008 | gitleaks/semgrep/osv-scanner not installed |
| Misconfig/installer (CWE-16) | no | manual read | well-locked | no test that re-run installer refuses post-lock |
| Container/IaC | n/a | — | shared hosting | n/a |
| API inventory (CWE-200) | n/a (future) | — | #38 unbuilt | re-audit when API-key auth ships |

---

## 6. 🎯 Findings register (`F-`)

Severity = impact on assets. Status reconciled against **current** branch code
(2026-06-05/06-28), not the original audit snapshot.

| ID | Title | Sev | Status | CWE | Evidence (file:line) | GH # |
|---|---|---|---|---|---|---|
| **F-001** | Spoofable client IP — `g2ml_getClientIP()` trusts `X-Forwarded-For`/`X-Real-IP` with no trusted-proxy allowlist; poisons activity/consent/breach logs, `lastLoginIP`, new-login heuristic, and lets anon rate-limit be evaded | **High** | **OPEN** | 290/348 | `web/_functions/security.php:472-501` (returns first XFF hop if it parses as an IP, no `REMOTE_ADDR` proxy gate) | #95 |
| **F-002** | Account-deletion **cancel** is a state-changing GET with no CSRF token — forged GET (img/link) cancels a victim's own pending deletion | **Med** | **OPEN** | 352 | `web/Go2My.Link/_admin/public_html/pages/privacy/delete/index.php:52-114` (`isset($_GET['cancel'])` → UPDATE status='rejected') | #98 |
| **F-003** | Interstitials emit redirect destination into `href`/`window.location.href` with `htmlspecialchars` but **no `^https?://` scheme allowlist** — a migrated legacy `javascript:`/`data:` destination renders as a clickable/auto-followed link | **Med** | **OPEN** | 79/601 | `validating.php:182` (`href` of `$destination`), `validating.php:224`/`251` (JS); `expired.php:163,197,220` | #99 |
| **F-004** | SSRF in `validateDestination()` server-side HEAD fetch — `get_headers()` with no private/loopback/link-local/reserved-range guard. **OFF by default** (`redirect.validate_destination=false`); becomes an authenticated SSRF oracle if enabled | **Med** | **OPEN** | 918 | `web/G2My.Link/_functions/redirect_resolver.php:117-236` (fetch ~173/181); default at `index.php:190` | #100 |
| **F-005** | Created-URL host validation lacks private-IP/loopback/reserved-literal rejection and `user:pass@` userinfo stripping — stored destinations can point at internal hosts (feeds F-004 when fetch enabled) | **Low** | **OPEN** | 918 | `web/Go2My.Link/_functions/shorturl_create.php:88-144` (only blocks own short-domains; scheme http/https enforced in `g2ml_sanitiseURL`) | #100 |
| **F-006** | Latent path traversal — `favicon.php` concatenates DB `orgLogoPath` into `readfile()`/`filesize()` with no `basename()`/`realpath()` containment; `getOrgFavicon()` returns the raw stored value. Latent today (no code writes `orgLogoPath`); live once org-logo upload ships | **Low** | **OPEN** | 22 | `web/G2My.Link/public_html/favicon.php:87-116`; `domain_resolver.php:161-186` | #101 |
| **F-007** | `validateUserSession()` validates the DB session token but does not re-bind the session's `userUID` to `$_SESSION['user_uid']` (defence-in-depth; not exploitable today) | **Low** | **OPEN** | 384 | `web/_functions/session.php:133-165` | — (audit §3.3) |
| **F-008** | Missing SRI on a subset of CDN tags (jQuery + Bootstrap JS in `footer.php`; Bootstrap/FA CSS in `header.php` and the 3 Component-B error pages) — CDN compromise = script/style injection | **Low** | **OPEN** | 353/494 | `web/_includes/footer.php:131,145`; `web/_includes/header.php:158,169`; `404.php:63,68`, `expired.php:89,94`, `validating.php:88,93` | — (new) |
| F-101 | Contact-form CRLF header injection | Med | **FIXED-on-branch** | 93 | subject stripped `\r\n\0` at `contact/index.php:152` | #97 |
| F-102 | Contact form CAPTCHA not verified server-side | Med | **FIXED-on-branch** | 290 | `verifyCaptcha()` enforced `contact/index.php:83-98` | #96 |
| F-103 | Component B CSP blocked its own error-page CDN CSS | Low | **FIXED-on-branch** | 693 | `style-src`/`font-src` now allow jsdelivr/cdnjs — `G2My.Link/public_html/.htaccess` CSP | #103 |
| F-104 | Component B default favicon always 404 | Low | **FIXED-on-branch** | — | fallback chain → `img/logo.png` at `favicon.php:122-143` | #102 |
| F-105 | Link-edit `notes`→`urlNotes` (broke editing; also IDOR check) | (functional) | **FIXED-on-branch** | 639 | `links/edit/index.php:55` (`s.urlNotes AS notes`); SELECT+UPDATE both `createdByUserUID = ?` | #94 |
| F-106 | Redirect destination `Location:` scheme guard | Med | **FIXED-on-branch** (resolver layer; F-003 is the residual interstitial gap) | 601 | `redirect_resolver.php:273-280` `^https?://` allowlist | — |
| F-107 | Email-header CRLF / template-path / salt-rotation / breach-TOCTOU / reset-token-in-URL hardening (#80–#84) | (various) | **FIXED — verified intact** | 93/22/362 | `email.php:93,155-202`; `breach_response.php`; `auth.php` | #80-#90 |
| F-108 | Legacy plaintext DB password in `public_html_legacy/dbConfig.php` | High | **MITIGATED-in-repo / rotation OPEN** | 798 | now gitignored + untracked + never committed (§0); **must still rotate the credential** | #93 |

### 6.1 Prioritised purple-team target list (highest severity first)

1. **F-001 — Spoofable client IP** (High, `security.php:472-501`) — broadest blast radius: log poisoning + anon rate-limit/CAPTCHA-IP evasion. Crown-jewel adjacent (auth heuristics).
2. **F-003 — Interstitial scheme gap** (Med, `validating.php:182` / `expired.php:163`) — `javascript:` destination → clickable XSS-equivalent; on the redirect crown-jewel path.
3. **F-002 — Deletion-cancel CSRF** (Med, `privacy/delete/index.php:52-114`) — easy PoC; abuses a user's own destructive workflow.
4. **F-004 — SSRF in destination validation** (Med, `redirect_resolver.php:117-236`) — off by default; prove the internal-fetch oracle when enabled.
5. **F-005 — Created-URL internal-host acceptance** (Low, `shorturl_create.php:88-144`) — pairs with F-004; verify private-IP/userinfo acceptance.
6. **F-006 — Favicon path traversal** (Low, `favicon.php:87-116`) — latent; confirm `../` reaches outside `_uploads/` to scope the future risk.

> Then re-confirm the FIXED-on-branch set (F-101…F-107) is not regressed, and the
> **rotation** action behind F-108 (#93) is closed operationally.

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

*Phase 0 setup only. No exploitation, no remediation, no code changes performed.*
