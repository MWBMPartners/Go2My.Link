# 📊 Feature Gap Ledger — Go2My.Link

> Seed ledger produced in the **DISCOVER** phase of a `dev-team-autopilot` run. **Doc-only — nothing here is approved to build.** featurefind proposes and scores; the conductor's hybrid gate decides. IDs use the `FG-` namespace.

## 🎯 Aim  (the yardstick every gap is measured against)

Go2My.Link is a multi-tenant **URL-shortening service** (a Bit.ly-style product by MWBM Partners Ltd) spanning three domains: **A** go2my.link (the marketing/management face + admin dashboard), **B** g2my.link (the short-link redirect engine), and **C** lnks.page (a LinkTree-style LinksPage). Its core user task is *create, manage, redirect and measure short links* — for anonymous users, registered individuals, and organisations — backed by a secure REST API (used internally by A and externally by first-party services such as CueRCode).  ·  **category:** URL shortener / link-management SaaS.

## 🗒️ Run record

- **last-run:** 2026-06-28 (ledger content); **status annotations refreshed 2026-07-19** against verified code/issue state — see the `status:` lines added to Tier 2/3/5 gaps below. The ledger's gap analysis and scoring are otherwise as originally produced; re-run featurefind for a fresh gap-finding pass if needed.
- **scope-setting:** strict ("when in doubt, out of scope")  ·  **depth:** brief-led (the intended feature set is well-defined in the Project Brief + audit §8; not open-ended competitor research).
- **method:** scored brief §8 conformance + open roadmap issues against verified code state. Each gap's build/schema state was spot-checked in the codebase (see `purpose-fit` evidence).
- **launch focus (as of 2026-06-28):** Components A + B; Component C was essentially unbuilt. **⚠️ 2026-07-19: this is now stale.** Component C shipped in full (6/6, Tier 5 below); the Tier 2 API/analytics/geo/UTM gaps also shipped. Payments/subscriptions + advanced auth (SIGNula, Phases 10–11) remain `gate-for-approval` and unbuilt (accurate).

## 📚 Comparables (category norms, not researched fresh)

The brief already names the reference products, so this is a brief-led pass rather than a market sweep:

- **Bitly** — leader — link shortening + custom domains + click analytics + API; the brief's primary template.
- **TinyURL / Rebrandly / Short.io** — direct — custom suffixes, custom domains, API, basic analytics (table-stakes signal for suffix/API/analytics).
- **Linktree / Beacons / Bio.link** — adjacent — the LinksPage (Component C) model.
- **Coverage:** core workflow (create/manage/redirect/measure), API surface, analytics, multi-tenant org features, LinksPage. **Not covered / deliberately out of brief:** social-graph/content features, generic marketing-automation.

---

## 🥇 In-scope gaps  (ranked — buildable candidates)

Ranked by importance within the aim, then favouring lower effort/risk. Autonomy-eligible table-stakes for A+B are at the top.

### Tier 1 — autonomy-eligible table-stakes (A + B launch polish)

- **id:** FG-001
  **feature:** Authenticated custom short-suffix / alias
  **what:** Let a logged-in user (individual or org) type a custom `<linksuffix>` when creating a link, instead of only an auto-generated code.
  **class:** table-stakes
  **importance:** High  **prevalence:** ~all comparables (Bitly/Rebrandly/Short.io/TinyURL)
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief lines 90–92 explicitly require it; `tblShortURLs.shortCode` + `UQ_shortcode_org` already enforce per-domain uniqueness and `createShortURL()` already accepts/validates a code path — the create form (`web/Go2My.Link/_admin/public_html/pages/links/create/index.php`) simply never exposes the input. Non-destructive, fits existing data model.
  **confidence:** high
  **spec-seed:** Add an optional "custom suffix" text field to the authenticated create form; on submit, validate charset/length and uniqueness against `(shortCode, orgHandle)`, surfacing a friendly "already taken" error on collision (reuse the #124 TOCTOU retry/lock). Hide the field for anonymous users (brief restricts the feature to registered users).
  **gate:** autonomy-eligible
  **status:** ✅ BUILT — cycle 10 (2026-06-29), branch `autopilot/2026-06-05`. `createShortURL()` gains a `customCode` option: validates `^[A-Za-z0-9_-]{3,50}$`, blocks reserved words via `g2ml_isReservedShortCode()`, returns "That alias is already taken." on duplicate (errno 1062) with no random fallback. Dashboard create form adds an optional "Custom alias" field. Anonymous/public API path untouched. 110 unit + 11 integration tests pass; 5 acceptance criteria demonstrated.

- **id:** FG-002
  **feature:** Tags on short links (consume the existing schema)
  **what:** Let users attach/edit tags on a link and filter the link list by tag, alongside the already-wired categories.
  **class:** table-stakes
  **importance:** Med  **prevalence:** common (Bitly tags, Rebrandly tags)
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief line 91 (categories *and* tags). `tblTags` + `tblShortURLTags` junction already exist (`web/_sql/schema/020_*`) but no PHP reads/writes them — categories are wired, tags are defined-only. Pure additive use of existing tables.
  **confidence:** high
  **spec-seed:** Add a tag input (comma/chip entry) to create/edit forms; upsert into `tblTags` and link rows in `tblShortURLTags`; add a tag filter to the links index. Mirror the existing category code paths and prepared-statement style.
  **gate:** autonomy-eligible
  **status:** ✅ BUILT — cycle 13 (2026-07-04), branch `autopilot/2026-06-05`. `createShortURL()` attaches tags after the row insert via new helpers (`g2ml_slugifyTag`, `g2ml_normaliseTags`, `g2ml_findOrCreateTag`, `g2ml_attachTagsToShortURL`): find-or-create per org via `UQ_tag_org`, junction insert via `INSERT IGNORE`, each tag wrapped in its own try/catch so a tag failure never rolls back the short URL (capped at `G2ML_MAX_TAGS_PER_LINK` = 10). Dashboard create form gains an optional comma-separated "Tags" field; links index renders WCAG-accessible tag badges (`role="list"`) via a single dynamically-sized bound `IN(...)` query across the page's short-URL UIDs — no N+1, no interpolation. 132 unit + 18 integration tests pass; 5 acceptance criteria demonstrated. Edit-form tag management and tag-based filtering deferred as explicit follow-ups.

- **id:** FG-003
  **feature:** Info-page public-vs-authenticated view (#23)
  **what:** Show the short-code info/preview page differently to the link's owner (richer detail) vs an anonymous visitor (minimal).
  **class:** table-stakes
  **importance:** Med  **prevalence:** common (preview pages on Bitly/TinyURL)
  **effort:** S  **risk:** Low
  **purpose-fit:** Issue #23. `web/Go2My.Link/public_html/pages/info/index.php` exists and renders one identical view for everyone; session/login state is already available app-wide. Branching is additive and non-destructive.
  **confidence:** high
  **spec-seed:** Detect login + ownership (or GlobalAdmin) of the looked-up code; for owners show destination/notes/created/click-count and a "manage" link, for others keep the masked/minimal view. No schema change.
  **gate:** autonomy-eligible
  **status:** ✅ BUILT — cycle 14 (2026-07-04), branch `autopilot/2026-06-05`. New pure helper `g2ml_infoDisplayDestination()` (`web/Go2My.Link/_functions/info_display.php`) returns the full destination when the viewer is authenticated (ANY authenticated viewer, not owner-only — short URLs redirect publicly so the destination is not secret) and a masked domain otherwise. `pages/info/index.php` renders the full destination as escaped text (not a clickable link) for authed viewers and adds an accessible "Log in to see the full destination" prompt (no redirect-back param) for anonymous ones. 141 unit pass (+9); lint clean; hostile payload confirmed fully HTML-escaped in a render harness.

- **id:** FG-004
  **feature:** API XML output with embedded XSLT
  **what:** Let API responses be returned as browser-viewable XML (XSLT-styled) in addition to the default JSON.
  **class:** table-stakes (brief-mandated)
  **importance:** Med  **prevalence:** uncommon in comparables, but explicit brief requirement
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief §8.2 / lines 222–224 require JSON (default) + XML with embedded XSLT. The existing `api/create/index.php` already centralises response emission; adding a content-negotiated XML serialiser is additive and touches no data model.
  **confidence:** high
  **spec-seed:** Add an `Accept:`/`?format=xml` switch to the API response layer; serialise the same payload via a small XML builder, prepend an `<?xml-stylesheet?>` referencing a self-hosted XSLT, set `Content-Type: application/xml`. Keep JSON the default. (Best landed *with* the API expansion FG-008/FG-009.)
  **gate:** autonomy-eligible
  **status:** ✅ BUILT — cycle 15 (2026-07-04), branch `autopilot/2026-06-05`. New `web/_functions/api_response.php` (`g2ml_apiRespond()` + pure `g2ml_apiWantsXml()`/`g2ml_arrayToXml()`/`g2ml_buildApiXmlDocument()`); new XSLT `web/Go2My.Link/public_html/api/create/response.xsl`; refactored `api/create/index.php` (10 repeated `json_encode` blocks → one `g2ml_apiRespond()` call; 0 `json_encode` left; the structured branch now also triggers on `?format=xml` / `Accept: application/xml` so API clients get structured responses); helper registered in `page_init.php`. JSON stays the default and is byte-identical to before (subprocess diff); XML emits a `<response>` document with a stylesheet PI, all values `htmlspecialchars` ENT_XML1-escaped (hostile payload proven escaped, lossless); no-JS redirect fallback and all status codes (405/403/422/429/201) + messages preserved. Regression: `tests/unit/api_response_test.php` (25 new tests). Lead re-ran: 166 unit pass (was 141); lint clean; `xmllint` confirms well-formed output; dedup confirmed (0 `json_encode` remaining in `api/create/index.php`).

**Tier 1 status (2026-07-04, end of POLISH cycle 18):** FG-001, FG-002, FG-003, FG-004 and FG-005 — all five autonomy-eligible Tier-1 gaps — are now ✅ BUILT.

**⚠️ 2026-07-19 update:** the note that "all Tier 2–5 gaps remain gate-for-approval, unchanged" is now **stale**. After separate owner-approved build cycles (2026-07-09/10/18), FG-006 through FG-012 (analytics, geolocation, API framework + endpoints, UTM, CSV export, OpenAPI docs) and FG-019 through FG-024 (all of Component C / Tier 5) are also now ✅ BUILT — see each gap's `status:` line below. Tier 4 (advanced redirects, FG-014–FG-018) and the payments/SIGNula items in "Out-of-scope / deferred-by-design" remain unbuilt, as originally gated.

- **id:** FG-005
  **feature:** Avatar priority cascade
  **what:** Resolve a user's avatar in priority order — local stored picture → MS365/Google → Gravatar → default/initials placeholder.
  **class:** table-stakes
  **importance:** Low  **prevalence:** common
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief lines 324–325. Today only a single stored URL + initials placeholder exists (`web/_functions/auth.php`). The cascade is a self-contained helper; the Gravatar tier is a deterministic hash with no external dependency to provision. MS365/Google tiers degrade gracefully until SSO (Phase 10) lands.
  **confidence:** high
  **spec-seed:** Add `g2ml_resolveAvatar($user)` returning the first available source; Gravatar = `https://www.gravatar.com/avatar/<md5(lowercased email)>?d=404` with the initials placeholder as final fallback. Wire it into the navbar/profile partial. MS365/Google tiers are no-ops until SSO exists.
  **gate:** autonomy-eligible
  **status:** ✅ BUILT — cycle 18 (2026-07-04), branch `autopilot/2026-06-05`. New `web/_functions/avatar.php` (`declare(strict_types=1)`) implements the full cascade: `g2ml_resolveAvatar()` returns a structured `image|initials` array in priority order — local stored avatar → MS365/Google SSO (no-ops until Phase 10) → Gravatar (gated OFF by default via `getSetting('avatar.gravatar_enabled', false)`, no resolve-time network call) → initials monogram as the effective fallback. Initials are multibyte-safe and colour-deterministic from a 10-entry palette WCAG-AA-verified for white text (≥4.5:1) at runtime by the test suite. `g2ml_renderAvatar()`/`g2ml_avatarEscape()` escape all output; wired into shared `web/_includes/nav.php` (main site + admin) via a guarded `require_once`, degrading to a Font Awesome icon if unavailable; old K&R shorthand in that partial replaced with Allman if/else. Built via a build → adversarial-verify workflow (correctness, security/privacy, house-rule lenses); the correctness lens caught a CONFIRMED defect — initials were capped to 2 chars BEFORE uppercasing, so multibyte expansions on uppercasing (ß→SS, ﬃ→FFI) overflowed the monogram to up to 6 glyphs — fixed with `g2ml_avatarCapInitials()` (uppercase-then-cap on all 4 return paths) and strengthened test coverage. New `tests/unit/avatar_test.php` (23 tests: initials tiers/edge cases incl. ß + ligatures, colour determinism + palette contrast, Gravatar hash, resolve cascade, render escaping). Verified after fix: `php -l` clean; overflow cases confirmed ≤2 chars; `php tests/run.php` → 189 passed / 0 failed (was 166, +23).

### Tier 2 — high importance, gate-for-approval (risk ≥ Med, larger, or external-facing)

- **id:** FG-006
  **feature:** Analytics data functions (#41) + dashboard UI (#42)
  **what:** Turn the raw activity log into per-link analytics — clicks over time (hour/day/week/month), charts, and a dashboard view.
  **class:** table-stakes
  **importance:** High  **prevalence:** ~all comparables
  **effort:** L  **risk:** Med
  **purpose-fit:** Brief lines 155–178; issues #41/#42. `tblActivityLog` already records clicks and `home.php` shows basic count cards — but there is no presentation layer, time-bucketing, or charts. Large (new query layer + charting library, self-hosted per Dreamhost no-CDN-dependency rule) and read-heavy on a 429K-row log (needs the #125 composite index first).
  **confidence:** high
  **spec-seed:** Add analytics functions (time-bucketed aggregates per short code, scoped by org) and an `_admin/.../analytics/` dashboard with self-hosted chart rendering. Depends on #125 index; respect org-scoping (cf. #121 cross-org leak). Gate because of scale/perf risk and a new front-end dependency.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09`. `web/_functions/analytics.php` (time-bucketed aggregates) + `/api/v1/analytics` + the #125 composite index; dashboard at `web/Go2My.Link/_admin/public_html/pages/analytics/` (Chart.js, accessible tables, theme-aware). Streaming CSV export added 2026-07-18 (#44, xlsx deferred to #152).

- **id:** FG-007
  **feature:** IP geolocation + VPN/proxy flag + richer user-agent breakdown (#43)
  **what:** Enrich each click with geographic location, a VPN/proxy flag, and device/browser/model detail.
  **class:** table-stakes
  **importance:** High  **prevalence:** ~all comparables
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief lines 157–172; issue #43. UA parsing partly exists (`_g2ml_parseUserAgent()` in `web/_functions/activity_logger.php`); geo/VPN do not. Requires bundling a GeoIP database (MaxMind GeoLite2) — a vendored data file on Dreamhost (no Composer) — plus a privacy stance (brief forbids street-level). External data dependency → gate.
  **confidence:** high
  **spec-seed:** Vendor GeoLite2 (or equivalent) under `_libraries`; add `g2ml_geolocate($ip)` returning country→city granularity (never street); store on the activity row; add a coarse VPN/proxy heuristic flag. Document the licence/update path. Privacy-review the granularity defaults.
  **gate:** gate-for-approval
  **status:** ✅ BUILT (geolocation only; VPN/proxy flag NOT built) — 2026-07-10, branch `launch-prep/2026-07-09`. Vendored pure-PHP `MaxMind\Db\Reader` at `web/_libraries/maxminddb/` (no C extension); `web/_functions/geolocation.php` (`g2ml_geolocateIP()`) behind `analytics.geolocation_enabled` (**OFF by default**) + a `.mmdb` file-exists check — a total no-op when off/absent. Country added to the analytics dashboard breakdown. CI-fetched via `scripts/fetch-geoip.sh` + a non-`--delete` deploy step so a failed fetch can't wipe a working DB. A coarse VPN/proxy heuristic was not built.

- **id:** FG-008
  **feature:** API framework + API-key authentication (#38)
  **what:** Issue, hash, store, and verify API keys against `tblAPIKeys`; a request-auth middleware + rate limiting for the public API.
  **class:** table-stakes
  **importance:** High  **prevalence:** ~all comparables
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief §"API" (lines 213–228); issue #38. `tblAPIKeys`/`tblAPIRequestLog` are schema-only with zero PHP references. This is the foundation for *all* external integration including CueRCode (already schema-wired via `createdViaAPIKeyUID`). Auth-surface change touching credentials → gate.
  **confidence:** high
  **spec-seed:** Add key generation (prefix + secret, store only an Argon2/hash), a bearer/header verifier resolving to an org/scope, per-key rate limiting via `tblAPIRequestLog`. Reuse `createShortURL()` for the create path. Security-review before exposure (cf. global pre-PR security rules).
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09`. `web/_functions/api_auth.php` (key auth `g2ml_<prefix>_<sha256>`, scopes, `hash_equals`) + `api_ratelimit.php` (DB rate-limit), `public_html/api/v1/` front controller, redacted request log, JSON envelope. Passed a dedicated adversarial security cycle (1 Medium fixed; Low residuals in #149).

- **id:** FG-009
  **feature:** Public REST API endpoints — modify / disable / analytics (#39)
  **what:** Beyond `create`, expose modify-short-URL, disable-short-URL, and analytics-retrieval endpoints.
  **class:** table-stakes
  **importance:** High  **prevalence:** ~all comparables
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief lines 217–220; issue #39. Today only `api/create/` and `api/consent/` exist. Depends on FG-008 (key auth) and reuses existing dashboard mutation logic. Write-capable external endpoints → gate.
  **confidence:** high
  **spec-seed:** Add `api/modify/`, `api/disable/`, `api/analytics/` (clean-URL `index.php` per brief), each authorised via FG-008 and scoped to the key's org. Reuse the dashboard's update/disable functions and FG-006 aggregates. Emit JSON (default) + FG-004 XML.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09`. URL CRUD/bulk/list + org read endpoints under `/api/v1`, cursor-paginated, BOLA-safe org-scoping (cross-org access → generic 404), pre-auth IP backoff. Key-management dashboard UI (#40) also shipped.

- **id:** FG-010
  **feature:** UTM capture & forwarding on redirect (#92)
  **what:** Capture inbound UTM/tracking params at click time and (configurably) forward them onto the destination URL.
  **class:** table-stakes
  **importance:** High  **prevalence:** common (Bitly, Rebrandly)
  **effort:** M  **risk:** Med
  **purpose-fit:** Issue #92; brief analytics intent. `tblShortURLs` has stored `utm*` columns (schema 020) but Component B does **not** capture/forward at redirect time (MEMORY.md #120 correction confirms). Touches the redirect hot path (302 latency, query-string merge correctness) → gate.
  **confidence:** high
  **spec-seed:** In the B resolver, parse inbound query params, log tracking attribution on the activity row, and if `redirect.forward_utm_params` is enabled merge configured params into the destination query string (preserve existing dest params). Settings-gated, off by default. Performance-review the hot path.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09`. `g2ml_extractTrackingParams()` (capture) + `g2ml_appendUtmToDestination()` (forward) in the B resolver; both settings-gated, **OFF by default**. **Residual:** captured UTM lands in `tblActivityLog.logData` (JSON blob), not yet an indexed analytics dimension or dashboard breakdown — tracked in follow-up #151.

### Tier 3 — medium/low, gate-for-approval

- **id:** FG-011
  **feature:** Analytics data export (CSV/Excel) (#44)
  **what:** Export per-link analytics to CSV/spreadsheet.
  **class:** table-stakes
  **importance:** Med  **prevalence:** common
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief line 175; issue #44. Only GDPR JSON export exists (`data_rights.php`). Depends on FG-006 aggregates; Excel generation on Dreamhost needs a vendored writer library. Gate on dependency + depends-on-FG-006.
  **confidence:** high
  **spec-seed:** Add a CSV exporter (native PHP, no dependency) first; offer XLSX via a vendored writer as a follow-up. Stream large result sets; org-scope the query.
  **gate:** gate-for-approval
  **status:** ✅ BUILT (CSV only; xlsx deferred) — 2026-07-18, branch `launch-prep/2026-07-09`. Streaming CSV export at `web/Go2My.Link/_admin/public_html/analytics-export.php` (#44). XLSX via a vendored writer vs native SpreadsheetML is an open follow-up decision — tracked in #152.

- **id:** FG-012
  **feature:** OpenAPI / Swagger API docs (#75)
  **what:** Machine-readable OpenAPI spec + a browsable `/api/docs` page.
  **class:** table-stakes
  **importance:** Med  **prevalence:** common for API products
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief implies developer-facing API; issue #75. No `openapi.*` or docs page exists. Low risk but only meaningful once FG-008/FG-009 define real endpoints → sequence after them. (Could be autonomy-eligible *after* the API exists; gated now because it documents endpoints that aren't built yet.)
  **confidence:** high
  **spec-seed:** Author `openapi.yaml` describing the create/modify/disable/analytics endpoints + key auth; serve a self-hosted Swagger-UI / Redoc at `/api/docs`. Keep the spec in sync with FG-008/009.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09`. OpenAPI 3.1 spec (9 endpoints, 26 schemas, authored from the live handlers, validated by redocly + openapi-spec-validator) + self-hosted Redoc at `/api/docs` (vendored 2.5.3, post-CVE-2024-57083; directory-scoped CSP).

- **id:** FG-013
  **feature:** Additional i18n locales (#71)
  **what:** Complete UI translations for the 9 seeded-but-inactive locales (en-US, es, fr, de, pt-BR, ar, zh-CN, ja, hi).
  **class:** nice-to-have
  **importance:** Low  **prevalence:** varies
  **effort:** M  **risk:** Low
  **purpose-fit:** Brief §"Terms/global"; issue #71. Infrastructure (`tblLanguages`/`tblTranslations`, `__()`) is built and en-GB is 100%; the other locales are seeded inactive at 0%. Additive content work (incl. RTL handling for `ar`). Bulk translation is large/low-impact pre-launch → gate.
  **confidence:** high
  **spec-seed:** Populate translations per locale, activate progressively, verify RTL layout for Arabic. No code-architecture change; primarily content + a few layout fixes.
  **gate:** gate-for-approval

### Tier 4 — advanced redirects (Phase 9) — gate-for-approval

- **id:** FG-014
  **feature:** Scheduled redirects (#51)
  **what:** A single short code resolves to different destinations by time-of-day / day / recurring schedule.
  **class:** differentiator  **importance:** Med  **prevalence:** uncommon
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief lines 135–141; issue #51. `tblShortURLSchedules` exists (schema 021) but no PHP evaluates it. Touches the redirect hot path + timezone correctness. Premium feature. Gate.
  **confidence:** high
  **spec-seed:** In the B resolver, after base lookup, evaluate active schedule rows (recurring + one-off, timezone-aware) and override the destination; fall back to the default when none match. Cache-friendly; off unless rules exist.
  **gate:** gate-for-approval

- **id:** FG-015
  **feature:** Device-based redirects (#52)
  **what:** Resolve to different destinations by visitor device/OS (iPhone vs Android vs desktop, etc.).
  **class:** differentiator  **importance:** Med  **prevalence:** uncommon (Bitly deep-linking adjacent)
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief lines 142–152; issue #52. `tblShortURLDeviceRedirects` schema-only. Reuses the existing UA parser but adds hot-path branching. Premium. Gate.
  **confidence:** high
  **spec-seed:** Map parsed UA → device class; pick the matching device-redirect row, else default. Reuse `_g2ml_parseUserAgent()`.
  **gate:** gate-for-approval

- **id:** FG-016
  **feature:** Geo-based redirects & geo restrictions (#53)
  **what:** Resolve/allow/deny by visitor country/region (ISO 3166-1/-2), with live-search country picker in the UI.
  **class:** differentiator  **importance:** Med  **prevalence:** uncommon
  **effort:** L  **risk:** Med
  **purpose-fit:** Brief lines 153–154; issue #53. `tblShortURLGeoRedirects` schema-only and **depends on FG-007** (geolocation). Premium. Gate.
  **confidence:** high
  **spec-seed:** Resolve visitor country via FG-007; apply allow/deny lists and per-region destination overrides; friendly block page on deny. Requires FG-007 first.
  **gate:** gate-for-approval

- **id:** FG-017
  **feature:** Age-verification gate on short links (#54)
  **what:** Prompt for date of birth before redirecting age-restricted links; block if under the configured minimum age.
  **class:** differentiator  **importance:** Med  **prevalence:** uncommon
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief line 134; issue #54. `tblShortURLAgeGates` schema-only. Compliance-sensitive (don't disclose the threshold; date-picker for format consistency). Premium. Gate.
  **confidence:** high
  **spec-seed:** Interstitial DOB date-picker → compute age server-side → redirect or show a friendly "not permitted" page. Don't reveal the minimum age. Session-scope a pass to avoid re-prompting.
  **gate:** gate-for-approval

- **id:** FG-018
  **feature:** Zoom-meeting-code shortlink (#56)
  **what:** Accept a Zoom meeting code and auto-build the full Zoom join URL as the destination.
  **class:** nice-to-have  **importance:** Low  **prevalence:** rare
  **effort:** S  **risk:** Low
  **purpose-fit:** Brief line 132; issue #56. No code references Zoom. Self-contained convenience helper; possibly premium. Niche → gate (low priority, not table-stakes).
  **confidence:** high
  **spec-seed:** Detect a Zoom-code input on create, expand to the canonical `zoom.us/j/<id>?pwd=` form, store as a normal destination.
  **gate:** gate-for-approval

### Tier 5 — Component C / LinksPage (Phase 8) — all originally gated (large); **✅ ALL 6/6 SHIPPED 2026-07 — see `status:` lines below, this header is historical**

- **id:** FG-019
  **feature:** LinksPage renderer (#45)
  **what:** Render a user/org's selected links (those flagged `allowLinksPage`) as a public LinkTree-style page at lnks.page.
  **class:** differentiator  **importance:** High (it is the entire third product)  **prevalence:** Linktree/Beacons category
  **effort:** L  **risk:** Med
  **purpose-fit:** Brief §C; issue #45. `web/Lnks.page/public_html/` is a coming-soon placeholder only — no renderer. `tblShortURLs.allowLinksPage` exists. Entire net-new product surface → gate per run-owner scope guidance.
  **confidence:** high
  **spec-seed:** Resolve a handle → org/user; query `allowLinksPage` links + manual links; render a responsive page with favicon detection (brief line 312). Must not be advertised until built.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09` (#45). `web/Lnks.page/_functions/linkspage_{resolver,renderer}.php`; `web/Lnks.page/public_html/index.php` is now the live renderer, not a coming-soon placeholder.

- **id:** FG-020
  **feature:** Custom-domain LinksPage fallback (#46)
  **what:** Visiting a bare org custom short-domain (no suffix) shows that org's LinksPage.
  **class:** differentiator  **importance:** Med  **prevalence:** category-adjacent
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief line 122; issue #46. Depends on FG-019 + the existing custom-domain resolver. Possible premium. Gate.
  **confidence:** high
  **spec-seed:** In B, when a custom domain is hit with no `<linksuffix>`, route to the org's rendered LinksPage (FG-019) instead of 404. Depends on FG-019.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09` (#46). `web/G2My.Link/_functions/linkspage_fallback.php`.

- **id:** FG-021
  **feature:** LinksPage system templates (#47)
  **what:** A set of pre-built, themeable LinksPage layouts users can pick from.
  **class:** differentiator  **importance:** Med  **prevalence:** category norm (Linktree themes)
  **effort:** M  **risk:** Low
  **purpose-fit:** Brief line 310; issue #47. Depends on FG-019. Gate (depends on unbuilt C).
  **confidence:** high
  **spec-seed:** Define N responsive templates with placeholder markers; store the chosen template per page; render via FG-019. Depends on FG-019.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09` (#47). Template picker + owner-only IDOR-safe preview.

- **id:** FG-022
  **feature:** LinksPage management UI (#48)
  **what:** Dashboard UI (Component A) to curate which links appear on the LinksPage, ordering, and page settings.
  **class:** differentiator  **importance:** Med  **prevalence:** category norm
  **effort:** M  **risk:** Low
  **purpose-fit:** Brief §C; issue #48. Depends on FG-019. Gate.
  **confidence:** high
  **spec-seed:** Add an `_admin/.../linkspage/` area to toggle `allowLinksPage`, add manual links, reorder, and pick a template. Depends on FG-019/FG-021.
  **gate:** gate-for-approval
  **status:** ✅ BUILT — 2026-07-10, branch `launch-prep/2026-07-09` (#48). Ownership-enforced CRUD, `maxLinksPages`-gated, at `web/Go2My.Link/_admin/public_html/pages/linkspage/`.

- **id:** FG-023
  **feature:** WYSIWYG template editor + HTML upload (#49)
  **what:** Block-based WYSIWYG LinksPage builder, plus the option to upload a custom HTML template with placeholder markers.
  **class:** differentiator  **importance:** Low  **prevalence:** premium-tier in category
  **effort:** L  **risk:** High
  **purpose-fit:** Brief line 310; issue #49. Large + a stored-XSS / template-injection surface (user-uploaded HTML). Gate (high risk + large + depends on C).
  **confidence:** high
  **spec-seed:** Block editor producing a sanitised template; uploaded HTML strictly sanitised + placeholder-substituted at render. Heavy security review required.
  **gate:** gate-for-approval
  **status:** ✅ BUILT (custom-HTML upload path; no separate block-based WYSIWYG editor) — 2026-07-10, branch `launch-prep/2026-07-09` (#49). DOM-allowlist sanitiser (`web/_functions/html_sanitiser.php`) + `script-src 'none'` CSP; **premium-gated with a kill switch OFF by default**, pending a dedicated security sign-off before enable — this is the product's highest stored-XSS surface (see `SECURITY.md`). Migration **016** must run before deploying to an existing DB or tier gating fails open.

- **id:** FG-024
  **feature:** LinksPage age verification (#50)
  **what:** DOB age-gate for individual LinksPage links, with auto-flagging of known adult destinations.
  **class:** differentiator  **importance:** Low  **prevalence:** rare
  **effort:** M  **risk:** Med
  **purpose-fit:** Brief line 313; issue #50. Depends on FG-019; mirrors FG-017. Gate.
  **confidence:** high
  **spec-seed:** Reuse the FG-017 DOB gate at the LinksPage link level; maintain an auto-flag list for adult domains. Depends on FG-019.
  **gate:** gate-for-approval
  **status:** ✅ BUILT (good-faith signed-cookie gate + adult-domain auto-flag; no DOB collection) — 2026-07-10, branch `launch-prep/2026-07-09` (#50). `web/_functions/adult_content.php`.

---

## 🚫 Out-of-scope / deferred-by-design (surfaced, NOT recommended for autonomous build)

Captured so the "no" is on the record; these are explicitly deferred in the brief + roadmap to the **SIGNula** phases and must go through the user's approval gate.

- **2FA / TOTP (#34)** — Phase 10 (SIGNula). Auth-surface change; brief defers to SIGNula. **why parked:** explicitly future + risky.
- **Social login, 6 providers (#35)** — Phase 10. External OAuth provisioning, out-of-scope for the A+B launch.
- **SSO: MS365 / Google Workspace / WordPress (#36)** — Phase 10. Large external-integration subsystem.
- **PassKey / WebAuthn (#37)** — Phase 10. Net-new auth subsystem.
- **Subscription tiers (#57), payment integrations (#58), billing UI (#59), feature gating (#60)** — Phase 11 (SIGNula). PayPal/Apple Pay/Google Pay/crypto + money movement; highest risk; the pricing page is presentational only today. **why parked:** payments are destructive/external and brief defers to SIGNula.
- **SIGNula account linking** — explicitly future in the brief.
- **Domain custom-domain "finalisation" (#91)** — *not a gap*: the custom-domain resolver and DNS-TXT verification are **already built** (`web/G2My.Link/_functions/domain_resolver.php`, `verifyDomain()` in `web/_functions/org.php`); #91 is documentation/setup finalisation, not net-new feature work.

> **Note on launch-hardening bug/security issues (#93–#128):** these are remediation/quality items (bugs, security, a11y, schema), not feature gaps, so they are intentionally excluded from this ledger — they belong to the iterate/security/review modes, not featurefind.

## ⏱️ Snapshot caveat

Code state verified against branch `autopilot/2026-06-05` on 2026-06-28; brief/roadmap as of the 2026-06-04 audit. Comparable feature sets are category norms from the brief, not a fresh market sweep — re-run to refresh.
