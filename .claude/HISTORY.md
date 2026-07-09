# 🕓 Go2My.Link — Claude Work History

> Chronological log of significant Claude-assisted work, newest first. Portable
> (repo-tracked) so the project's working history is available on every machine.
> Companion to [.claude/memory/MEMORY.md](memory/MEMORY.md). Last updated **2026-07-09**.

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
