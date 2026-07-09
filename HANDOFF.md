# 🤝 HANDOFF — Go2My.Link launch-prep cycle

> **Purpose:** durable pick-up point so any session (or a fresh start) can continue
> without re-deriving state. Companion to `docs/LAUNCH_PLAN_2026-07-09.md` (the full
> strategic plan) and `.claude/memory/MEMORY.md` (project memory).
> **Last updated:** 2026-07-09 · **Branch:** `launch-prep/2026-07-09` (off `hardening/cycle-2-2026-07-04` @ `46fe7a5`).

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

## ✅ Where things actually stand (verified 2026-07-09, code wins over docs)

- **A + B are code-complete for launch.** The `autopilot/2026-06-05` + `hardening/cycle-2`
  runs reached **VERIFY PASS / COMPLETE** (merged PR #130; cycle-2 = `46fe7a5`).
- **~19–24 launch-hardening issues are fixed in code but still OPEN on GitHub**
  (#94–#124, SEC-RECHECK-01, the SSRF/CRLF/XFF/CSP/a11y cluster) — verified file-by-file
  in the plan §1.1. They just need **closing with commit refs**.
- **Component C = scaffolding only** — must not be advertised beyond its landing page.
- **API is 100% greenfield** on a good schema (`tblAPIKeys`/`tblAPIRequestLog` have zero
  PHP references). **CueRCode is blocked until #38/#39 ship.**
- **Premium tiers:** full data model (`tblSubscriptionTiers`, 4 GBP tiers) but **almost
  no enforcement** (no `canUseFeature()`), and the pricing page is mismatched (USD/3-tier).
- **SIGNula / advanced auth:** zero code. Custom domains: ~70% (routing works;
  verification + partner docs missing; two domain tables disconnected — plan §5/GT-6).

## 🔴 Genuinely outstanding before an A+B launch (small)

| Item | Owner | Status |
|---|---|---|
| **#135** login blocker (`avatarURL`→`avatarPath`) + login integration test | dev | **IN FLIGHT** (Sonnet) |
| **#93** rotate leaked legacy DB password on host + remove `public_html_legacy/` | **owner (ops)** | pending — instruct-don't-execute |
| Migration dry-run + full 480-URL migration; force-reset 7 plaintext passwords | dev + owner | pending |
| Legal sign-off on 5 `{{LEGAL_REVIEW_NEEDED}}` docs | **owner/legal** | pending |
| Close ~24 fixed-but-open issues with commit refs | dev | queued (next) |
| Doc drift: pricing USD→GBP/tier names, API envelope, DNS TXT prefix, MEMORY UTM | dev | queued (next) |

---

## 🔄 Done this session (2026-07-09)

- Reverted a CI-breaking YAML typo in `.github/workflows/ci.yml` (stray indent on `uses:`).
- Full no-assumptions review of issues/milestones/project + codebase.
- **Fable 5 deep plan** → `docs/LAUNCH_PLAN_2026-07-09.md` (also in scratchpad).
- Filed **#135** (P0 login blocker). Updated `.claude/memory/MEMORY.md` + this HANDOFF.
- Delegated **#135 fix** to a Sonnet implementation agent.

## ⏭️ Immediate next steps (execution queue — see plan §10)

- **P0:** finish #135; close ~24 issues; reconcile doc drift + stale `main`; low a11y/hygiene
  (#114–119); (owner) #93 rotation, migration, legal.
- **P1:** API framework **#38** (Opus) → endpoints **#39** → key UI **#40** → **CueRCode wiring**
  → analytics **#41/#42** → geo/UA **#43** → UTM **#92** → custom-domain verify+docs **#91**
  → **OpenAPI/Swagger #75** (capstone) → API security cycle.
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
