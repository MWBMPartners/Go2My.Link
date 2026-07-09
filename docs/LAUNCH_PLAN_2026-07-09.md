# 🚀 Go2My.Link — Strategic Launch & Roadmap Plan

> **Author:** Lead-architect planning pass (read-only; no code changed).
> **Date:** 2026-07-09 · **Branch inspected:** `hardening/cycle-2-2026-07-04` (= `origin`, head `46fe7a5`).
> **Method:** Every claim below was spot-checked against the working tree (schema files, `_functions/*`, admin pages, seeds, CI, git history). Where the repo contradicts MEMORY/docs, the **code wins** and the discrepancy is called out.
> **Scope of launch:** Components **A + B** only. C stays a coming-soon landing page.

---

## 0. 🔑 Ground-truth corrections found this pass (read before anything else)

These change the launch picture versus the memory notes:

| # | Finding | Evidence | Impact |
|---|---|---|---|
| **GT-1** | 🔴 **NEW CRITICAL — login is broken on a fresh install.** `loginUser()` SELECTs `avatarURL` from `tblUsers`, but the schema column is `avatarPath`. On a clean schema import the prepared statement fails at PREPARE → nobody can log in. | `web/_functions/auth.php:219` (`… lockedUntil, avatarURL, timezone …`) + `:426`; schema `web/_sql/schema/013_core_users.sql:74` (`avatarPath`). Same class as the CR-1 bug VERIFY caught; the test suite never drives `loginUser()` end-to-end so it stayed hidden. Also affects `avatar.php`, `data_rights.php`, `nav.php`, `profile/index.php`. | **P0 launch-blocker.** Not tracked by any issue. |
| **GT-2** | ✅ All launch-hardening code fixes (#94–#124, SEC-RECHECK-01) are genuinely present in the tree — but their **GitHub issues are still OPEN**. | Verified file-by-file (see §1 table). | Housekeeping: close ~24 issues with commit refs. |
| **GT-3** | 🟠 `web/G2My.Link/public_html_legacy/dbConfig.php` still physically exists with a **real-looking live credential** (`Pass 5xPICC5Ia54Q61mGivvavvrgnvmd8ck`, host `mysql.mwhost.online`, db `mwtools_mwlink`). It is untracked + gitignored (git-safe) but **not rotated/removed**. | `.gitignore:29-30`; file on disk. | **P0 ops action (#93):** rotate + delete. Instruct-don't-execute. |
| **GT-4** | 🟠 **Local `main` is a stale divergent branch** (ahead of `origin/main` by 5 old Feb-2026 commits, behind by 31). One of those commits (`4cb3a49`) **re-adds `public_html_legacy/` with the legacy engine**, and another (`05c805b`) is an **older, different UTM #92 implementation** that is NOT on the launch branch. | `git rev-list --left-right --count origin/main...HEAD` = `0 1`; `git log origin/main..main`. | Do **not** fast-forward/merge stale `main` into the launch line — it would resurrect the #93 credential file. Reconcile deliberately. |
| **GT-5** | UTM capture/forwarding (#92) is **genuinely unbuilt on the launch branch** (0 hits for `utm`/`extractTracking` in `redirect_resolver.php`/`activity_logger.php`). The only UTM code lives on stale `main`. `tblShortURLs` has stored `utm*` columns (schema 020) but nothing populates them at redirect time. | grep on branch. | Confirms MEMORY #120; #92 remains real work. |
| **GT-6** | The **two "domain" tables are disconnected.** `tblOrgShortDomains` (unverified, drives routing) vs `tblOrgDomains` (DNS-TXT-verified via real `verifyDomain()`, but nothing routes off it). The `org.max_short_domains` quota setting is **dead code** — never read. | `domain_resolver.php`, `sp_lookupShortURL.sql`, `org.php:1002` `addOrgShortDomain` (no quota/verification). | Central design constraint for §5 (#91). |
| **GT-7** | API response envelope **drift**: code emits flat `{"success":bool,…}`; `docs/API.md` specifies `{"status":"success","data":{…},"meta":{…}}`. XSLT PI points at `/api/create/response.xsl`, docs say `/api/v1/transform.xslt`. Pricing page shows `$0` (USD); DB tiers are **GBP**; page advertises `Free/Pro/Enterprise` (3) vs DB `free/basic/premium/enterprise` (4); page says "unlimited" free links, DB says `free.maxLinks=50`. | `api_response.php`, `pages/pricing/index.php`, `001_subscription_tiers.sql`. | Reconcile before publishing API/pricing (§3, §6). |

---

## 1. 🚦 Launch-readiness verdict per component

RAG = Red (blocked) / Amber (ready-with-fixes) / Green (ship).

| Component | Domain | RAG | One-line verdict |
|---|---|---|---|
| **A** — main site + admin | go2my.link / admin.go2my.link | 🟠 **Amber** | Feature-complete for MLP; **one new P0 (GT-1 login) + close-out housekeeping** stand between it and green. |
| **B** — redirect engine | g2my.link | 🟠 **Amber** | Hot path solid & hardened; blocked only by the **#93 credential rotation (ops)** and the migration dry-run of 480 URLs. |
| **C** — LinksPage | lnks.page | ⛔ **Red (by design)** | Scaffolding only; serve coming-soon landing, do **not** advertise the product. |

### 1.1 What is actually already fixed (verified in code) vs genuinely outstanding

**✅ Code-FIXED in the tree (just need GitHub issues closed with commit refs):**

| Issue | Fix verified at |
|---|---|
| #94 link-edit `notes`→`urlNotes` **and** the `shortURLUID`→`urlUID` load bug (CR-1) | `links/edit/index.php:55,58,149,151` |
| #95 XFF trusted-proxy allowlist | `security.php` `g2ml_getClientIP()` L1160-1207 (REMOTE_ADDR default; XFF only from `TRUSTED_PROXIES`) |
| #96 contact CAPTCHA server-verify | `pages/contact/index.php:61-101` |
| #97 CRLF mail injection | `email.php:86,93,155-201`; `contact/index.php:152` |
| #98 deletion-cancel CSRF→POST | `privacy/delete/index.php:62-66,338-347` |
| #99 interstitial scheme guard | `validating.php:76-113`, `expired.php`; test `redirect_scheme_guard_test.php` |
| #100 SSRF host guard (create + validate + DNS-rebind pin) | `security.php:725-884`, `shorturl_create.php:488`, `redirect_resolver.php:204,269-292`; seed `014` |
| #101 favicon path traversal | `favicon.php:110-128` (`basename`+`realpath` confinement) |
| #102 favicon 404 fallback → logo.png | `favicon.php:163-179` |
| #103 Component-B CSP allows its CDN | `.htaccess` CSP (all 4 components) |
| #104 landing auto-refresh removed | no `http-equiv=refresh` in any landing |
| #105/#106 prefers-reduced-motion | landing pages + `app.js`/`style.css` `matchMedia` guards |
| #107 SR countdown spam | `validating.php:285,318` (polite region, announce at 3/1 only) |
| #108 empty alt | landing logos carry descriptive alt |
| #111 release.yml lint tool | `release.yml:174,180` (`parallel-lint`) |
| #112 / #93-gitignore | `.gitignore:29-30`; `git ls-files` shows 0 tracked legacy files |
| #121 cross-org category leak | `links/index.php:140` (JOIN scoped `c.orgHandle = s.orgHandle`) |
| #122 org re-invite unique key | schema `014:73` VIRTUAL `pendingKey` + migration `010` |
| #123 migration zero-date guards | `004_migrate_shorturls.sql:75-86` + migrations 001-007 |
| #124 short-code TOCTOU retry | `shorturl_create.php:599-723` (errno-1062 bounded retry) |
| SEC-RECHECK-01 cross-org deactivation DoS | `shorturl_create.php:509-523,691-693` (isActive bound into INSERT; unscoped UPDATE deleted) |

**🔴 Genuinely outstanding for A+B launch:**

| Item | Type | Owner | Note |
|---|---|---|---|
| **GT-1 — `avatarURL`/`avatarPath` login blocker** | code (new) | dev | **Hard P0.** Rename the column reference in `auth.php`/`avatar.php`/`data_rights.php`/`nav.php`/`profile` to `avatarPath` (or add a migration + settle on one name). Must add an integration test that actually calls `loginUser()`. |
| **#93 — rotate + remove legacy credential** | ops (manual) | **user** | Rotate the `mwtools_mwlink` password on the host; delete/archive `public_html_legacy/` out of the deploy tree. Instruct-don't-execute. |
| **Migration dry-run of 480 URLs / 5 orgs / 7 users** | ops | user + dev | `docs/MIGRATION_PLAN.md` + `dry_run.sql` exist; the run itself + "all 480 resolve" verification has not happened. Force-reset 7 plaintext passwords. |
| **Legal review** | legal | user | 5 legal docs still carry `{{LEGAL_REVIEW_NEEDED}}` placeholders. |
| **Close ~24 open issues** | housekeeping | dev | Every fixed item above is still OPEN on GitHub. |
| **114/115/116/119 low a11y/hygiene** | code (low) | dev | Non-blocking; e.g. A has no `pages/errors/404/` dir though `.htaccess` routes to it; C whitelists a non-existent `robots.txt`; B error pages declare `lang="en"` not `en-GB`. |

### 1.2 Minimal critical path to a public A+B launch

```
GT-1 login fix (+ integration test)  ─┐
#93 credential rotation (user/ops)    ─┼─►  Migration dry-run (480 URLs) ─► Legal sign-off ─► DNS cutover ─► LAUNCH A+B
Close-out issues + docs reconcile     ─┘
```

Everything else on the roadmap (API, CueRCode, custom domains, tiers, SIGNula, C) is **fast-follow** — none of it blocks the A+B minimum-launchable product. Realistic critical path: **~3-5 working days of engineering + the user's ops/legal actions.**

---

## 2. 🧭 Recommended launch sequencing — three options

| | **Option A — Ship A+B now, fast-follow** | **Option B — Hold for API + CueRCode + custom domains** | **Option C — Hold for full suite (C + SIGNula + payments)** |
|---|---|---|---|
| **Adds before launch** | GT-1 fix, #93, migration, legal | + #38/#39 API, CueRCode, #91 domains, analytics | + Component C, SIGNula SSO, payments/tiers enforcement |
| **Extra effort** | ~1 week | ~6-9 weeks | ~4-6 months |
| **Revenue at launch** | £0 direct (free MLP) but **live product, real users, migrated base of 480 links** | Enables CueRCode (first-party QR) + partner domains = first monetisable hooks | Full paid tiers |
| **Risk** | Low — surface is small, hardened, tested | Medium — API is a new external attack surface; needs its own security pass | High — long runway, C is a stored-XSS-heavy greenfield, payments are money-movement risk |
| **Time-to-feedback** | **Immediate** | Delayed 2 months | Delayed months |

### ✅ Recommendation: **Option A — ship A+B now, fast-follow the rest.**

Reasoning:
1. **The MLP is real and hardened.** 17 security findings closed, 189 unit + 21 integration green, prepared statements throughout. Holding it back earns nothing.
2. **The blockers are tiny and mostly not code** (one login bug + ops/legal). Sitting on a launchable product to bundle deferred phases is the classic mistake.
3. **Migrating the 480 existing links is itself the launch event** — those links and their in-the-wild QR codes need the new engine live; every week of delay is a week the legacy engine (with the plaintext-cred history) stays in production.
4. **The monetisation path (Option B work) is genuinely valuable but sequential** — the API framework (#38) is a hard blocker for CueRCode, and it deserves a dedicated build + security cycle rather than being rushed into a launch gate. Do it as **P1 immediately after launch**, not before.
5. **Option C's payload (C, SIGNula, payments) is high-risk and low-marginal-urgency** — defer behind real user demand and the SIGNula programme.

**Sequenced roadmap:** P0 (launch A+B) → P1 (API + CueRCode + custom domains + analytics v1) → P2 (premium tiers + SIGNula thin SSO) → P3 (Component C + advanced redirects) → P4 (roadmap polish).

---

## 3. 🏗️ API framework architecture (#38 / #39) — Dreamhost-shared design

**State today:** `tblAPIKeys` + `tblAPIRequestLog` are schema-only with **zero PHP references**. `api/create` + `api/consent` are **session/CSRF** endpoints, not key-authed. There is **no `/api/v1` router, no `X-API-Key` reader, no webhook code anywhere.** This is greenfield on top of a good schema.

### 3.1 Key model — `tblAPIKeys` (columns already exist)

Columns present: `apiKeyUID, userUID, orgHandle, apiKey (hashed), apiKeyPrefix (8 chars), keyName, permissions (JSON), rateLimitOverride, lastUsedAt, expiresAt, isActive, createdAt, updatedAt`.

| Concern | Design |
|---|---|
| **Key format** | `g2ml_` + 8-char public prefix + `_` + 32-byte `random_bytes` secret, e.g. `g2ml_ab12cd34_<43-char-base64url>`. Show **once** at creation. |
| **Storage** | Store **only `hash('sha256', secret)`** in `apiKey` (fast, constant-time compare; the secret has 256 bits of entropy so a slow hash is unnecessary and would add per-request latency on shared hosting). `apiKeyPrefix` stored plaintext for O(1) lookup + UI display. |
| **Verification** | Look up by `apiKeyPrefix` (indexed) → `hash_equals(stored, sha256(presented))`. Reject if `isActive=0`, `expiresAt` passed, or org/user suspended. |
| **Scopes** | Use the existing `permissions` JSON column: `["urls:read","urls:write","urls:delete","analytics:read","domains:read","domains:write","org:read","qr:link","linkspage:write"]`. Enforce per-endpoint with a `g2ml_apiKeyHasScope($key, 'urls:write')` helper. **CueRCode key = `["urls:write","urls:read","qr:link","analytics:read"]`.** |
| **Rate limits** | Per-key: `rateLimitOverride` else the org's `tblSubscriptionTiers.maxAPIRequestsPerDay`. |
| **Revocation / rotation** | Set `isActive=0` (soft). Rotation = issue new key + deprecate old with a grace `expiresAt`. |

### 3.2 Authentication scheme

- **Primary:** `Authorization: Bearer g2ml_<prefix>_<secret>` (industry-standard; also accept `X-API-Key` for `docs/API.md` compatibility — pick Bearer as canonical, document X-API-Key as alias).
- **Not HMAC-signing** for v1 — HMAC request-signing adds client complexity with little gain over Bearer-over-TLS on a shared host with no replay-sensitive money movement. Revisit if webhooks or high-value mutations arrive.
- TLS is already enforced via `.htaccess`; reject any non-HTTPS API request early.

### 3.3 Routing & versioning

No router change needed to `router.php` (which only serves `pages/`). Instead:
- New front controller `web/Go2My.Link/public_html/api/v1/index.php` + an `.htaccess` rewrite (`RewriteRule ^api/v1/(.*)$ api/v1/index.php?_apiroute=$1 [QSA,L]`).
- Dispatch table maps `METHOD + path` → handler file under `api/v1/handlers/`. Sanitise `_apiroute` to `[a-zA-Z0-9/_-]` (reuse the router's traversal-safe pattern).
- Version in the path (`/api/v1/`) — matches `docs/API.md`. Keep the legacy internal `api/create` untouched for the website's own AJAX.

### 3.4 Response envelope (reconcile the drift — GT-7)

Build a thin envelope on top of the existing `g2ml_apiRespond()` (do **not** re-implement — reuse gives JSON/XML parity for free):

```
Success: {"status":"success","data":{…},"meta":{"timestamp":"…Z","requestId":"…","rateLimit":{"limit":N,"remaining":M,"resetAt":"…Z"}}}
Error:   {"status":"error","error":{"code":<http>,"message":"…","field":"…"}}
```

Adopt the **documented `status/data/meta` shape for `/api/v1`** (it is what `docs/API.md` publishes) while leaving the website's internal flat `{success:…}` alone. Add a `g2ml_apiEnvelope($data, $meta)` builder that wraps then calls `g2ml_apiRespond()`.

### 3.5 Rate limiting without a daemon (DB-backed)

- **Count against `tblAPIRequestLog`** per `apiKeyUID`: `SELECT COUNT(*) … WHERE apiKeyUID=? AND createdAt > NOW() - INTERVAL 1 DAY` (mirrors the existing `checkAnonymousRateLimit()` pattern on `tblActivityLog`). Add a per-minute window for the documented tier limits (Free 10/min etc.).
- **Every request writes one `tblAPIRequestLog` row** (endpoint, method, responseCode, responseTimeMs, ip, ua, sanitised body) — this doubles as the rate-limit source and the audit trail.
- **Index requirement:** add `INDEX IDX_apireq_key_created (apiKeyUID, createdAt)` (the schema has `IDX_apireq_key` + `IDX_apireq_created` separately; a composite makes the window count a single range scan). Prune old rows via the same probabilistic-cleanup trick already used for sessions (1/100 requests trigger a `DELETE … WHERE createdAt < …`), since Dreamhost cron is limited.

### 3.6 Idempotency, errors, pagination

- **Idempotency:** accept an optional `Idempotency-Key` header on POST; store `(apiKeyUID, idempotencyKey) → cached response` in a small new table `tblAPIIdempotency` (or reuse `requestBody` hash). MVP: at minimum make `create` idempotent on `(apiKeyUID, customCode)` via the existing `UQ_shortcode_org`.
- **Error taxonomy:** reuse `docs/API.md` codes — 400 validation, 401 bad/missing key, 403 scope/tier, 404 not found, 409 alias taken (maps to `createShortURL()` "already taken"), 422 semantic, 429 rate limit (+ `Retry-After`), 500. Every error carries `error.code` + `error.field`.
- **Pagination:** cursor-based on `urlUID` (`?limit=50&after=<urlUID>`) — cheaper than OFFSET on a large `tblShortURLs`; return `meta.nextCursor`.

### 3.7 Webhooks — viable on shared hosting?

**Verdict: defer to P4, and even then use a pull/poll model or Cloudflare Workers, not in-process delivery.** Dreamhost shared has no long-running workers and limited cron, so outbound webhook delivery with retries/backoff is fragile. If needed for CueRCode scan events, prefer: (a) CueRCode **pulls** analytics via `GET /api/v1/analytics/{code}`, or (b) a Cloudflare Worker in front acts as the webhook dispatcher. Do not build in-PHP webhook queues on the shared host.

### 3.8 Full endpoint surface (covers all product functionality)

| Group | Endpoint | Scope | Reuses |
|---|---|---|---|
| Links | `POST /api/v1/urls` | urls:write | `createShortURL()` (add `createdVia='api'`, `createdViaAPIKeyUID`) |
| | `GET /api/v1/urls` (list, paged, filter tag/category) | urls:read | new query layer |
| | `GET /api/v1/urls/{code}` | urls:read | |
| | `PUT /api/v1/urls/{code}` | urls:write | dashboard edit logic (org-scoped) |
| | `DELETE /api/v1/urls/{code}` (soft `isActive=0`) | urls:delete | ownership-scoped UPDATE |
| | `POST /api/v1/urls/bulk` (batch create/import) | urls:write | loop over `createShortURL()` in a txn |
| Analytics | `GET /api/v1/analytics/{code}` (period/group_by) | analytics:read | #41 analytics fns |
| | `GET /api/v1/analytics/export/{code}` (csv) | analytics:read | #44 export |
| Domains | `GET/POST /api/v1/domains`, `POST /api/v1/domains/{id}/verify` | domains:read/write | `addOrgDomain`/`verifyDomain` |
| Org/Account | `GET /api/v1/account`, `GET /api/v1/org` | org:read | `getOrganisation()` |
| **QR (CueRCode)** | `POST /api/v1/urls` w/ QR fields; `PUT /api/v1/urls/{code}` re-point; `GET /api/v1/analytics/{code}?scanSource=qr` | qr:link, urls:write, analytics:read | §3b |
| LinksPage (when built) | `GET/PUT /api/v1/linkspages/{slug}` | linkspage:* | Phase 8 |
| Keys (dashboard, session-authed not key-authed) | `#40` API-key mgmt UI under `_admin/.../api-keys/` | — | issue/revoke/rotate |

---

## 3b. 🔗 CueRCode integration contract

CueRCode is a first-party dynamic-QR service. "Dynamic QR" = the QR encodes a Go2My.Link **short code**; editing the short URL's destination re-points every printed QR. The QR record lives in **CueRCode** (no local `tblQRCodes`); it authenticates to Go2My.Link via a `tblAPIKeys` key with a `cuercode` scope. Schema hooks already exist (`createdVia='cuercode'`, `createdViaAPIKeyUID`, `qrCodeExternalID`, `qrCodeExternalUUID` UNIQUE, `qrCodeLinkedAt`; `tblActivityLog.scanSource`+`qrCodeExternalID`; `cuercode.*` settings, off by default). **Zero PHP reads these today** — they must be wired.

### Flow (a) — create a short code for a dynamic QR

```
CueRCode ──► POST /api/v1/urls   (Bearer <cuercode-key>, scope qr:link+urls:write)
  body: { destination_url, createdVia:"cuercode",
          qr_external_id:<CueRCode QR id>, qr_external_uuid:<uuid>, custom_code?:… }
Go2My.Link:
  • gate on getSetting('cuercode.integration_enabled') — reject 403 if off
  • createShortURL(dest, { orgHandle:<key org>, createdVia:'cuercode',
        createdViaAPIKeyUID:<key uid>, qrCodeExternalID, qrCodeExternalUUID,
        qrCodeLinkedAt: now, customCode?: (only if cuercode.allow_external_shortcode) })
  ◄── 201 { data:{ short_code, short_url, qr_code_external_uuid } }
```
**Work needed:** extend `createShortURL()` `$options` to accept `createdVia`, `createdViaAPIKeyUID`, `qrCodeExternalID`, `qrCodeExternalUUID`, `qrCodeLinkedAt` and bind them into the INSERT (currently they fall to DB defaults).

### Flow (b) — re-point the QR

```
CueRCode ──► PUT /api/v1/urls/{code}   (scope urls:write)
  body: { destination_url:<new> }
Go2My.Link: UPDATE tblShortURLs SET destinationURL=? WHERE shortCode=? AND orgHandle=?  (org-scoped!)
  ◄── 200 { data:{ short_code, destination_url } }
```
Every QR in the wild now points to the new destination — no reprint.

### Flow (c) — scan attribution

Two options; recommend **(c1)** for launch:
- **(c1) Query-param tagging on the redirect hot path.** CueRCode appends `?src=qr` (settings `cuercode.scan_source_param`/`scan_source_value`) to the short URL it encodes. Component B's resolver reads that param, and `logActivity()` writes `scanSource='qr'` + `qrCodeExternalID`. **Work needed:** extend `logActivity()` bind string (currently 21 cols, no `scanSource`/`qrCodeExternalID`) and have `redirect_resolver`/`index.php` pass them through.
- **(c2) CueRCode pulls** `GET /api/v1/analytics/{code}?scanSource=qr` for its own dashboards (no push infra).

### Auth
Dedicated `tblAPIKeys` row owned by the CueRCode service's org, `permissions=["urls:write","urls:read","qr:link","analytics:read"]`. Master kill-switch `cuercode.integration_enabled` (default 0) — when off, the API rejects `createdVia:"cuercode"` requests. Hard dependency: **#38/#39 must ship first.**

---

## 4. 🔐 SIGNula.id SSO integration

**State:** zero SIGNula code anywhere (only roadmap docs). Auth today is **local Argon2id email/password only**. The empty `tblUserSocialLogins` and `tblUserPassKeys` tables exist and are the natural landing spots. Blockers to solve first: `tblUsers.passwordHash` is **`NOT NULL`** (SSO-only users have no password); no `authProvider`/`externalID` columns; and the GT-1 `avatarURL` bug lives in the same auth code you'd touch.

### 4.1 Model — OIDC, not raw OAuth2

Integrate SIGNula.id as an **OpenID Connect provider** (Authorization Code + PKCE). OIDC gives an `id_token` (identity) on top of OAuth2 (authorization), which is exactly what an IdP relationship needs. Coexists with local auth as a **parallel login path**, not a replacement.

```
Login page ──► "Sign in with SIGNula" ──► SIGNula /authorize (code+PKCE, state, nonce)
   ◄── redirect back with code ──► exchange for id_token+access_token (server-side)
   verify id_token (iss, aud, exp, nonce, signature via SIGNula JWKS)
   ├─ existing tblUserSocialLogins(provider='signula', providerUserID=sub)? ─► log that user in
   ├─ email matches an existing local user? ─► ACCOUNT LINKING (require a confirmation step)
   └─ new? ─► provision tblUsers (see §4.3) + tblUserSocialLogins row ─► log in
```

### 4.2 Schema changes required (small, additive)

| Change | Why |
|---|---|
| `ALTER tblUsers MODIFY passwordHash VARCHAR(255) NULL` | SSO-only users have no local password. |
| Add `authProvider VARCHAR(50) DEFAULT 'local'` to `tblUsers` (optional convenience) | Distinguish local vs SSO-primary. |
| Use existing `tblUserSocialLogins` (provider=`signula`, `providerUserID`=`sub`, tokens, `providerData` JSON) | Identity linking — no new table needed. |
| Fix GT-1 `avatarURL`→`avatarPath` first | You will be editing the same SELECT. |

### 4.3 Account linking rules

- **Same verified email** → offer to link (must confirm ownership, e.g. re-enter password or email a confirm link) — never silently merge.
- **SSO-only signup** → create `tblUsers` with `passwordHash=NULL`, `emailVerified=1` (trust SIGNula's verified email), `orgHandle='[default]'`, `assignAccountType('user')`.
- **Unlink** guard: refuse to unlink the last remaining auth method if `passwordHash IS NULL`.
- **Open-redirect safety:** the OAuth `redirect_uri` must be an allowlisted constant, and post-login redirect stays relative-only (the login page already enforces relative-only redirects after the cycle-2 fix).

### 4.4 Relation to Phases 10/11 — thin launch vs full defer

| Layer | Verdict |
|---|---|
| **SIGNula OIDC sign-in (this §4)** | **Thin, P2-worthy.** ~1 new provider integration; reuses existing session/account-type machinery. Deliver as "Sign in with SIGNula" + linking. |
| **Full social login (6 providers, #35), MS365/Google/WordPress SSO (#36), 2FA (#34), PassKey (#37)** | **Defer to Phase 10 / SIGNula programme.** The SIGNula OIDC path is the strategic wedge — once it works, additional providers can be brokered *by SIGNula* rather than each integrated into Go2My.Link. |
| **Payments/tiers (#57-60, Phase 11)** | Independent of SSO; see §6. |

**Strategic note:** if SIGNula itself brokers Microsoft/Google/etc., Go2My.Link only ever integrates **one** IdP (SIGNula) and inherits the rest — a much smaller, safer surface than #34-37 imply. Recommend the thin OIDC integration in P2 and let SIGNula own the provider fan-out.

---

## 5. 🌐 Partner custom-domain + DNS mechanism (#91)

**Goal:** a partner brings their own domain (e.g. `links.acme.com` or `acme.link`) for their short links instead of `g2my.link`.

**Reality check (GT-6):** the routing table `tblOrgShortDomains` is **unverified** (anyone can claim a globally-unique host; `addOrgShortDomain` has no ownership check, no quota — `org.max_short_domains` is dead code, no TLS columns). A separate table `tblOrgDomains` has a **real DNS-TXT `verifyDomain()`** but nothing routes off it. The two must be **unified/bridged** so a domain is *verified before it becomes routable*.

### 5.1 End-to-end onboarding flow (target)

```
1. Partner adds domain in dashboard  ──► row in tblOrgShortDomains with NEW columns:
      verificationStatus='pending', verificationToken=<random>, isActive=0  (NOT routable yet)
2. UI shows exact DNS records to set (see 5.2)
3. Partner sets DNS at their registrar
4. Partner clicks "Verify" ──► verifyDomain()-style dns_get_record TXT check
      on success: verificationStatus='verified', isActive=1  (now routable)
5. TLS provisioning (see 5.3)
6. B's resolver maps host→org (already works via getOrgByDomain / sp_lookupShortURL,
      but MUST additionally require verificationStatus='verified')
```

### 5.2 DNS records the partner sets

| Purpose | Record | Value |
|---|---|---|
| **Ownership proof** | `TXT` at `_g2ml-verify.<domain>` | the random `verificationToken` (matches existing `verifyDomain()` + `org.dns_verify_prefix`) |
| **Routing (subdomain)** | `CNAME` `links.acme.com` | → `g2my.link` (or a Cloudflare-for-SaaS fallback origin) |
| **Routing (apex/root)** | `A`/`ALIAS` `acme.link` | → the Dreamhost IP (apex can't CNAME; use ALIAS/flattening if the registrar supports it) |

Reconcile the doc drift: `docs/DEPLOYMENT.md` says the TXT is `_gotomylink-verify.{domain}` = org handle, but the **code** uses `_g2ml-verify` = random token. **Code wins — update the docs.**

### 5.3 TLS provisioning on Dreamhost shared + Cloudflare — honest options

This is the hard part on shared hosting. Three viable models, best first:

| Model | How | Pros | Cons |
|---|---|---|---|
| **① Cloudflare for SaaS (Custom Hostnames)** ⭐ recommended | Partner CNAMEs to a Cloudflare fallback origin; Cloudflare issues+renews per-hostname TLS automatically and proxies to the Dreamhost origin. Go2My.Link calls the CF API to add the custom hostname. | Fully automated TLS at scale; no per-domain manual step; WAF/CDN included (Cloudflare is already in front). | Costs (CF for SaaS pricing); one external API dependency; requires CF API token in `auth_creds.php`. |
| **② Dreamhost hosted-domain + Let's Encrypt** | Add each partner domain as a hosted/mirror domain in the Dreamhost panel, point its doc-root at `web/G2My.Link/public_html/`, enable Let's Encrypt. | No extra vendor; uses existing hosting. | **Manual per-domain panel step** (no API/wildcard) → does not self-serve, does not scale. |
| **③ Partner fronts with their own Cloudflare** | Partner proxies their domain through *their* Cloudflare to the Dreamhost origin (or to `g2my.link`). | Zero cost to MWBM; partner owns TLS. | Requires partner sophistication; inconsistent. |

**Recommendation:** **Model ① (Cloudflare for SaaS)** for the productised self-serve path, with **Model ②** as the manual fallback for a handful of high-touch partners while ① is built. Document ③ for technical partners. **Call out honestly:** without ①, custom domains cannot be truly self-serve on Dreamhost shared — every domain needs a manual panel entry. This is the single biggest architectural constraint for #91.

### 5.4 Resolver mapping (B)

Already 90% there: `getOrgByDomain()` and `sp_lookupShortURL` map `Host` → org via `tblOrgShortDomains WHERE isActive=1`. **Two required changes:** (a) require `verificationStatus='verified'` (add the column), and (b) decide the unknown-host behaviour — today it silently falls back to `[default]` org's namespace, which means an unregistered host looks up codes in the default org. For custom domains, an unverified/unknown host should hit a branded "domain not configured" page, not leak the default namespace. Add caching (the resolver is uncached — every redirect is a live query; acceptable now, but a per-request static cache or APCu-style memo helps at volume).

### 5.5 Schema + code work

- `ALTER tblOrgShortDomains ADD verificationStatus ENUM('pending','verified','failed') DEFAULT 'pending', ADD verificationToken VARCHAR(255), ADD verifiedAt DATETIME, ADD tlsStatus ENUM('none','pending','active') DEFAULT 'none'`.
- Wire the dead `org.max_short_domains` quota into `addOrgShortDomain()` (tier-gated).
- Self-serve onboarding UI: extend `pages/org/short-domains/index.php` with the verify flow + per-provider DNS instructions (GoDaddy/Cloudflare/Namecheap copy-paste).
- **Partner integration guide:** new `docs/CUSTOM_DOMAINS.md` (the "client setup documentation" #91 explicitly asks for).

---

## 6. 💎 Premium / tiered feature system

**State:** the **data model is complete** (`tblSubscriptionTiers` with 4 GBP tiers, `tblSubscriptions`, `tblPayments`, org-scoped `tierID` FK) but **enforcement is almost nonexistent** — only `maxCustomDomains` is checked (in `org.php`). There is **no `canUseFeature()` helper**. The public pricing page is presentational and mismatched (GT-7: USD `$0`, 3 tiers named Free/Pro/Enterprise, "unlimited" free links). Payments are deferred to SIGNula Phase 11.

### 6.1 Proposed tier ladder (align DB + pricing page + gating)

Keep the DB's 4-tier structure; rename for market clarity and fix the pricing page to match:

| Tier | £/mo | Links | Custom domains | Analytics | API/day | Team seats | LinksPage | Adv. redirects | QR (CueRCode) | Data retention | Support |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Free** | £0 | 50 | 0 | Basic (30d) | 100 | 1 | 1 | ✗ | ✓ basic | 90d | community |
| **Basic / Starter** | £4.99 | 500 | 1 | Standard (1y) | 5,000 | 3 | 3 | ✗ | ✓ | 1y | email |
| **Premium / Pro** | £14.99 | 5,000 | 5 | Advanced + export | 50,000 | 10 | 10 | ✓ | ✓ styled | 2y | priority |
| **Enterprise** | £49.99 | ∞ | ∞ | Advanced + export + SLA | ∞ | ∞ | ∞ | ✓ | ✓ | custom | dedicated + SSO/SAML |

(Branded interstitials, audit-log export, webhooks → Premium+; SSO/SAML → Enterprise, ties to §4.)

### 6.2 Feature-gating architecture

Build the missing enforcement layer (greenfield):

- **`web/_functions/entitlements.php`** (new): 
  - `g2ml_getOrgTier($orgHandle)` → cached tier row (reuse `getOrganisation()`'s already-joined tier columns).
  - `g2ml_canUseFeature($orgHandle, 'hasAdvancedRedirects')` → bool (reads `tblSubscriptionTiers` flags).
  - `g2ml_checkLimit($orgHandle, 'maxLinks', $currentCount)` → `['allowed'=>bool,'limit'=>N]`.
- **Enforce server-side at every mutation:** `createShortURL()` checks `maxLinks`; API middleware checks `hasAPIAccess` + `maxAPIRequestsPerDay`; domain add checks `maxCustomDomains` (already done) + `maxShortDomains`; advanced-redirect/analytics/LinksPage endpoints check their flags. **Never gate in UI only** — the UI hides, the server enforces.
- **UI:** show tier badges, "upgrade to unlock" CTAs on gated features, usage meters (links used / limit).

### 6.3 Billing approach given Dreamhost + SIGNula deferral

- **Launch:** no billing. Free tier only; higher tiers assigned manually by GlobalAdmin (the `org/settings` dropdown already does this). Enforcement layer (§6.2) can ship **before** payments — gate features by the manually-assigned tier.
- **Then:** the schema names PayPal/Apple Pay/Google Pay/crypto (no Stripe). **Recommendation: use Stripe** anyway (Checkout + Billing) — it is the lowest-effort, best-supported option and there is a first-party `dev-team-stripe` skill + Stripe MCP available. Stripe Checkout is a hosted redirect flow that works fine on Dreamhost shared (no server-side card handling, PCI scope minimal). Webhooks land via a single `api/webhooks/stripe` endpoint with signature verification. Reconcile with SIGNula: if SIGNula is to own billing, Go2My.Link calls a SIGNula billing API instead — but **do not block the tier-enforcement layer on that decision.**
- **Sequence:** entitlement enforcement (P2) → manual tier assignment (works today) → Stripe/SIGNula billing (P2/P3, its own security cycle).

---

## 7. 📄 Component C (LinksPage) build plan (#45-50)

**State:** scaffolding only. 3 tables (`tblLinksPages`, `tblLinksPageItems`, `tblLinksPageTemplates`) + **5 seeded system templates** exist; `index.php` is a 404 placeholder with **zero DB reads**. `tblShortURLs.allowLinksPage` and `tblLinksPageItems.requiresAgeGate` columns already exist to build against. Renderer (#45) is the keystone dependency for everything else.

| Phase | Issue | Work | Effort | Risk | Model |
|---|---|---|---|---|---|
| **C.1** | **#45 renderer** | Resolve `lnks.page/<slug>` → page → render selected `allowLinksPage` short links + manual items, favicon detection, template substitution (`{{avatar}}{{name}}{{bio}}{{links}}{{social}}`). Consume the 5 seeded templates. Escape everything. | L | Med | Sonnet |
| **C.2** | **#48 mgmt UI** | `_admin/.../linkspage/`: toggle `allowLinksPage`, add manual links, reorder (drag), pick template, page settings. | M | Low | Sonnet |
| **C.3** | **#47 system templates** | Wire the 5 seeded templates into a picker + preview (data already exists). | S | Low | Haiku/Sonnet |
| **C.4** | **#46 custom-domain fallback** | Bare org custom short-domain (no suffix) → org's LinksPage instead of 404. Depends on §5 + C.1. `tblOrgDomains.domainType` already has `'linkspage'`. | M | Med | Sonnet |
| **C.5** | **#50 age verification** | DOB gate per `tblLinksPageItems.requiresAgeGate`; auto-flag known adult domains; don't disclose threshold. | M | Med | Sonnet |
| **C.6** | **#49 WYSIWYG + HTML upload** | ⚠️ **Highest risk — stored-XSS / template-injection.** Block editor → sanitised template; uploaded HTML strictly sanitised + placeholder-substituted. **Needs a dedicated security review** (dev-team-security). | L | **High** | **Opus** |

**Sequencing:** C.1 → C.2 → C.3 → C.4 → C.5 → C.6. Do **not** advertise C until at least C.1-C.3 are live and security-reviewed. C.6 last (it's the biggest attack surface in the whole product).

---

## 8. 🛡️ Security & lint sweep plan

The autopilot closed 17 findings, but a **fresh adversarial pass** should target what changed since and what was never exercised:

### 8.1 Beyond the known findings — target list

| Target | Why |
|---|---|
| **GT-1 `avatarURL`/`avatarPath`** | Proves the test suite doesn't exercise `loginUser()`; grep for **other schema/code column mismatches** the same way (compare every `SELECT … FROM tbl*` column against the schema DDL). |
| **API framework (when built, #38/#39)** | Brand-new external attack surface: key leakage in logs, scope-bypass, IDOR across orgs on `/api/v1/urls/{code}`, rate-limit bypass, timing on key compare, `tblAPIRequestLog` storing secrets in `requestBody`. Run a dedicated `dev-team-security` cycle on the API before it goes public. |
| **CueRCode create path** | Ensure `createdVia='cuercode'` requests can't set arbitrary `orgHandle` (must be the key's org); `qrCodeExternalUUID` UNIQUE-collision handling. |
| **Custom-domain resolver** | Unverified-host → default-namespace leak (§5.4); host-header injection into `sp_lookupShortURL`. |
| **LinksPage #49** | Stored XSS in user-uploaded HTML/WYSIWYG — the single biggest future risk. |
| **Multi-tenant IDOR end-to-end** | SECURITY.md admits the suite has no `userB`/`GlobalAdmin` fixtures — cross-org IDOR is only manually reviewed, not tested. Build those fixtures. |
| **Migration data** | Normalise the 480 legacy destinations through `g2ml_sanitiseURL()` (some may be `javascript:`/`data:` from the old engine). |
| **Session fixation / installer re-run** | Full end-to-end probes (currently manual-read only). |

### 8.2 Running the tools with no Composer on the host

Tools run **in CI**, not on Dreamhost (which has no CLI/Composer). Current state verified:
- **`.github/workflows/ci.yml`** already runs two gates: **Backend** (`parallel-lint web/` as a hard gate; **PHPStan** + **PHP_CodeSniffer** advisory via version-pinned, SHA256-checksummed phars — the no-Composer-friendly route) and **Frontend** (`node --check` JS, JSON validity, stylelint advisory).
- **`.github/workflows/php-lint.yml`** — `parallel-lint` via `shivammathur/setup-php@v2`.
- **Local:** the pure-PHP harness (`php tests/run.php` = 189 unit; `php tests/run_integration.php` = 21 on a throwaway MySQL 9.6). Keep extending it — it caught nothing about GT-1 because it never logs a user in.
- **Recommendations:** (a) make PHPStan a **hard gate at level 5+** once the tree is clean (currently advisory); (b) add a **`gitleaks` CLI** history scan (the free MIT binary, not the licensed action — per the org CI notes) so the #93-class credential can never slip in; (c) add an integration test that drives `registerUser()`→`loginUser()` (would have caught GT-1); (d) add the `userB`/`GlobalAdmin` fixtures for IDOR tests.

---

## 9. 💡 New feature / enhancement ideas (capture as `for consideration` issues)

Score: **V** = value (product impact), **E** = effort. All should be filed as individual GitHub issues labelled `for consideration` (create the label if missing) per standing practice.

| Idea | What | V | E | Notes / dependency |
|---|---|---|---|---|
| **Link expiry rules** | Rich expiry: max clicks, expire-on-date, expire-after-duration | High | M | `startDate`/`endDate` exist; add click-cap |
| **Password-protected links** | Prompt for a passphrase before redirect | High | M | New column + interstitial; hot-path aware |
| **UTM builder** | UI to compose UTM-tagged destinations + the #92 capture/forward | High | M | Pairs with #92 (unbuilt); `utm*` columns exist |
| **QR styling** | Colour/logo/shape options (via CueRCode) | High | M | Belongs in CueRCode; Go2My.Link exposes the link |
| **Deep-link / mobile app redirects** | iOS/Android app-scheme fallbacks | Med | M | Extends `tblShortURLDeviceRedirects` (schema exists) |
| **A/B split redirects** | One code → weighted destinations | Med | L | **No table exists** — net-new (schema has no split table) |
| **Link-in-bio analytics** | Per-item click analytics on LinksPage | Med | M | Depends on C build |
| **Team roles / granular permissions** | Beyond Admin/User — editor/viewer/analyst | Med | M | `tblAccountTypes` supports it; add roles |
| **Audit-log export** | Org admins export their activity log | Med | S | Premium-gated |
| **Webhooks** | Outbound events (link created, threshold clicks) | Med | L | ⚠️ shared-hosting constraint (§3.7) — via CF Worker |
| **Bulk import** | CSV/paste bulk link creation | High | M | Reuse `createShortURL()` in a txn; API `/urls/bulk` |
| **Browser extension** | Shorten current tab via the API | Med | M | Needs #38 API first |
| **Native mgmt apps (iOS/Android)** | Thin clients over the API | Low | XL | Needs #38/#39 + OpenAPI (#75) |
| **Branded interstitials** | Org-styled pre-redirect page | Med | S | Premium-gated; `validating.php` already themable |
| **Bio/profile short links** | vanity `go2my.link/@handle` | Low | S | |

---

## 10. 🗺️ Execution plan — phased, ordered backlog

Effort: **S** ≤0.5d · **M** 1-2d · **L** 3-5d · **XL** 1-2wk+. Model: **Haiku** (mechanical) · **Sonnet** (standard feature/fix) · **Opus** (genuinely complex/high-risk).

### P0 — Launch-blockers for A+B (do first, ~1 week)

| # | Title | Maps to | Effort | Risk | Deps | Model |
|---|---|---|---|---|---|---|
| P0-1 | **Fix `avatarURL`→`avatarPath` across auth/avatar/data_rights/nav/profile + add `loginUser()` integration test** | GT-1 (new issue) | S | **High if missed** | — | **Sonnet** (auth-critical) |
| P0-2 | **Rotate legacy DB credential + remove `public_html_legacy/`** | #93 | S | High | user/ops | Haiku (draft the commands; **user executes**) |
| P0-3 | **Run migration dry-run + full 480-URL migration; force-reset 7 plaintext passwords; verify all resolve** | DEPLOYMENT checklist | M | High | P0-2 | Sonnet + user |
| P0-4 | **Close ~24 fixed launch-hardening issues with commit refs** | #94-#124, SEC-RECHECK-01 | S | Low | — | Haiku |
| P0-5 | **Reconcile stale `main` divergence** (don't resurrect legacy engine / old UTM) | GT-4 | S | Med | — | Sonnet |
| P0-6 | **Reconcile doc drift** (pricing USD→GBP + tier names; API envelope; DNS prefix; MEMORY UTM) | #120, GT-7 | S | Low | — | Haiku |
| P0-7 | **Legal review of 5 `{{LEGAL_REVIEW_NEEDED}}` docs** | #61 | — | Med | user/legal | (human) |
| P0-8 | **Low a11y/hygiene: A `errors/404` page, B `lang=en-GB`, C robots.txt, landing favicons** | #114-119 | S | Low | — | Haiku |

### P1 — API + CueRCode + custom domains + analytics v1 (~4-6 weeks)

| # | Title | Maps to | Effort | Risk | Deps | Model |
|---|---|---|---|---|---|---|
| P1-1 | **API framework: key issuance/hash/verify, Bearer auth, scopes, DB rate-limit, `/api/v1` front controller, envelope on `g2ml_apiRespond`** | #38 | L | **High** (new auth surface) | — | **Opus** |
| P1-2 | **API endpoints: urls CRUD + bulk + list, account, domains** | #39 | L | Med | P1-1 | Sonnet |
| P1-3 | **API-key management UI** (session-authed) | #40 | M | Low | P1-1 | Sonnet |
| P1-4 | **CueRCode wiring: extend `createShortURL()` + `logActivity()` for `createdVia`/QR cols/`scanSource`; QR create/re-point/scan flows; cuercode key + kill-switch** | new (schema ready) | M | Med | P1-1,P1-2 | Sonnet |
| P1-5 | **Analytics data functions (time-bucketed aggregates, org-scoped)** + composite index `(shortCode,createdAt)` | #41, #125 | L | Med | index | Sonnet |
| P1-6 | **Analytics dashboard UI (self-hosted Chart.js)** | #42 | L | Med | P1-5 | Sonnet |
| P1-7 | **IP geolocation + UA breakdown (vendored GeoLite2)** | #43 | M | Med | licence/privacy | Sonnet |
| P1-8 | **UTM capture + forwarding on redirect (settings-gated, off by default)** | #92 | M | Med | hot-path perf | Sonnet |
| P1-9 | **Custom-domain verification + routing unification** (add verify cols to `tblOrgShortDomains`; require verified before routable; wire `max_short_domains`) | #91 | L | Med | — | Sonnet |
| P1-10 | **Cloudflare-for-SaaS TLS automation + `docs/CUSTOM_DOMAINS.md` partner guide** | #91 | L | Med | P1-9, CF acct | **Opus** (external infra) |
| P1-11 | **OpenAPI/Swagger spec + `/api/docs`** | #75 | M | Low | P1-2 | Sonnet |
| P1-12 | **Data export (CSV)** | #44 | M | Low | P1-5 | Sonnet |
| P1-13 | **API security cycle (adversarial)** | §8 | M | — | P1-1..P1-4 | Opus (dev-team-security) |

### P2 — Premium tiers + SIGNula thin SSO (~4-6 weeks)

| # | Title | Maps to | Effort | Risk | Deps | Model |
|---|---|---|---|---|---|---|
| P2-1 | **Entitlement/feature-gating layer** (`entitlements.php`, `canUseFeature`, `checkLimit`; enforce `maxLinks`, API, domains, flags server-side) | #60 (partial) | L | Med | — | Sonnet |
| P2-2 | **Usage meters + upgrade CTAs + fix pricing page to DB tiers** | #59 | M | Low | P2-1 | Sonnet |
| P2-3 | **SIGNula OIDC sign-in** (Auth Code + PKCE; `passwordHash` nullable; `tblUserSocialLogins` linking) | #36 (subset) | L | **High** (auth) | GT-1 fixed | **Opus** |
| P2-4 | **Account linking UX + unlink guards** | #36 | M | Med | P2-3 | Sonnet |
| P2-5 | **Billing (Stripe Checkout + Billing + webhook) OR SIGNula billing API** | #57,#58 | L | **High** (money) | P2-1; owner decision | Opus (dev-team-stripe) |

### P3 — Component C + advanced redirects + analytics depth (~6-10 weeks)

| # | Title | Maps to | Effort | Risk | Deps | Model |
|---|---|---|---|---|---|---|
| P3-1 | LinksPage renderer | #45 | L | Med | — | Sonnet |
| P3-2 | LinksPage mgmt UI + template picker | #48,#47 | M | Low | P3-1 | Sonnet |
| P3-3 | Custom-domain LinksPage fallback | #46 | M | Med | P3-1, P1-9 | Sonnet |
| P3-4 | Scheduled / device / geo redirects + engine integration | #51,#52,#53,#55 | L | Med | P1-7 (geo) | Sonnet |
| P3-5 | Age-gate (short links + LinksPage) | #54,#50 | M | Med | — | Sonnet |
| P3-6 | **LinksPage WYSIWYG + HTML upload (stored-XSS heavy)** | #49 | L | **High** | P3-1 | **Opus** + security cycle |

### P4 — Roadmap / nice-to-have

| # | Title | Maps to | Effort | Model |
|---|---|---|---|---|
| P4-1 | Full 2FA / PassKey / social fan-out (or delegate to SIGNula) | #34,#35,#37 | XL | Opus |
| P4-2 | Schema tech-debt: `sp_logActivity` drift, orgHandle-vs-orgUID FK, alias-chain integrity, log partitioning | #126,#127,#128,#125 | M | Sonnet |
| P4-3 | 9 additional i18n locales | #71 | M | Haiku/Sonnet |
| P4-4 | Enhancement ideas from §9 (bulk import, password links, expiry rules, A/B, webhooks, extension) | new | varies | Sonnet |
| P4-5 | Zoom-code shortlinks | #56 | S | Haiku |

---

## 11. ⚠️ Risks & open questions for the product owner

**Risks:**
1. **🔴 GT-1 login blocker** — a fresh install cannot log anyone in (`avatarURL` vs `avatarPath`). This is the single most important pre-launch fix and is *not* tracked by any issue. It also means the "189/21 green" test baseline never exercised login — treat green-tests as necessary-not-sufficient.
2. **🟠 #93 credential still live on disk** — the legacy password is git-safe but has sat in plaintext; treat as compromised, rotate + delete before launch.
3. **🟠 Stale `main` divergence (GT-4)** — merging it would re-add the legacy engine + credential file and an old, conflicting UTM implementation. The launch line is the `hardening/cycle-2` branch, not `main`.
4. **API = new external attack surface** — do not ship #38/#39 without a dedicated security cycle (P1-13).
5. **Custom-domain TLS at scale is the real constraint** — without Cloudflare-for-SaaS, every partner domain needs a manual Dreamhost panel step; self-serve is not possible on shared hosting alone.
6. **LinksPage #49 (HTML upload)** is the highest-risk feature in the whole roadmap (stored XSS) — gate it, review it hard, ship it last.
7. **No automated login/IDOR coverage** — the suite lacks `userB`/`GlobalAdmin` fixtures and end-to-end auth; several DB-only bugs (CR-1, GT-1) slipped past unit-green.

**Open questions:**
1. **Does SIGNula broker the other IdPs?** If yes, Go2My.Link integrates only SIGNula (one OIDC) and inherits Microsoft/Google/etc. — collapsing #34-37 into one small integration. Please confirm SIGNula's role.
2. **Billing: Stripe or SIGNula-owned?** The schema names PayPal/Apple/Google/crypto (no Stripe). Recommend Stripe Checkout; but if SIGNula owns billing, we call its API. This decision gates P2-5 but **not** the tier-enforcement layer (P2-1 ships regardless).
3. **CueRCode go-live timing** — it hard-depends on the API framework (#38). Confirm the desired sequence: API-first (P1-1) then CueRCode (P1-4).
4. **Custom-domain model** — approve Cloudflare-for-SaaS (adds a vendor + cost) vs manual Dreamhost per-domain (doesn't scale)?
5. **Tier naming/pricing** — confirm the final ladder (§6.1) and currency (GBP) so the pricing page, DB seeds, and gating all agree.
6. **Migration window** — when to cut over the 480 links + force-reset the 7 users' passwords? This is the actual launch event.
7. **Component C priority** — genuine demand, or defer to P3+ behind API/tiers? It is the largest greenfield with the highest security cost.

---

*End of plan. All claims grounded in the working tree of `hardening/cycle-2-2026-07-04` as of 2026-07-09; code wins over docs/memory where they disagreed (see §0).*
