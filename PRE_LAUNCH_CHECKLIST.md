# 🚦 Go2My.Link — Pre-Launch Checklist, Owner Decisions & Actions

> **Purpose:** the single page to run through **before launching the service**. It captures
> (a) decisions only the owner (Lance) can make, (b) manual actions outside the codebase
> (GitHub settings, DNS, credentials, payment providers), and (c) a log of what automation
> did on your behalf. Companion to `HANDOFF.md` (technical pick-up state), `PROJECT_STATUS.md`,
> and `docs/LAUNCH_PLAN_2026-07-09.md`.
>
> **Legend:** 🔴 launch-blocking · 🟠 important · 🟢 nice-to-have · ✅ done · ⏳ in progress · ❓ needs your decision
>
> **Last updated:** 2026-07-22

---

## ❓ Owner decisions needed (please read first)

| # | Decision | Why it matters | Options / recommendation |
|---|---|---|---|
| D1 🔴 | **How does periodic/scheduled work run on Dreamhost shared hosting?** (no assumable cron) | Blocks GDPR **account-deletion execution (#163)** and **data-retention enforcement (#167)** — the privacy policy legally commits to both. Also affects log purging, trial expiry, subscription renewals. | (a) Dreamhost Panel cron jobs (simplest, native); (b) external scheduler (cron-job.org / GitHub Actions `schedule:`) hitting a **token-guarded** `/_cron/run.php` endpoint; (c) run-on-request "lazy cron". **Recommendation: (b)** — portable, testable, provider-agnostic, works today. Decide once → unblocks #163, #167 and future billing jobs. |
| D2 🔴 | **When do we promote `alpha → beta → main` for the real production launch?** | `main` is **stale** (still the legacy engine + the #93 credential file — HANDOFF warns *do not merge main*). All the real launch-prep work lives on `alpha` (90 commits ahead). Production go-live = a deliberate promotion, not automatic. | Needs owner sign-off + a cutover window. Dependabot/CI now cover all tiers so the mechanics are ready. **Do not** let anything merge `main` back down. |
| D3 🟠 | **Payment provider** for paid tiers (Stripe / Paddle / other)? | The billing schema is provider-agnostic (`paymentProvider` column) but integration code + webhook endpoints need a concrete choice. Paddle acts as merchant-of-record (handles VAT/MoSS) — attractive for a UK/EU SaaS. | Choose before enabling any paid tier. Pricing model (below) is being designed provider-neutral. |
| D4 🟠 | **Pricing & tier sign-off** | The flexible tiering/pricing model + `Pricing_Strategy.md` are being authored this session. Feature→tier mapping and price points need your approval before go-live. | Review `Pricing_Strategy.md` when delivered; the entitlement scaffolding ships **disabled** until you say go. |
| D5 🟠 | **`beta` branch drift** — align it to `alpha` conventions? | `beta` uses floating action tags (`@v7`/`@v4`) and lacks the `lint.yml` (actionlint) workflow that `alpha`/`main` have — a source of the exact variance you flagged. | Recommend a one-off "align beta CI + SHA-pin actions" PR (tracked as an issue this session). Low risk. |

---

## 🔒 Manual actions outside the codebase (owner must do in GitHub / hosting)

| # | Action | Status |
|---|---|---|
| A1 🔴 | **Rotate the legacy DB credential (#93)** that was in `web/G2My.Link/public_html_legacy/dbConfig.php` (treat as compromised) and remove/archive that legacy dir. | ❓ verify done |
| A2 🟠 | **GitHub → Settings → Security:** enable **Dependabot security updates**, **Secret scanning**, and **Push protection**. Dependabot *version* updates now target all 3 tiers (done in code); *security* updates are a repo setting and only target the default branch. | ⏳ owner toggle |
| A3 🟠 | **Legal review** of the legal pages (terms/privacy/cookies/copyright/acceptable-use) — they contain `{{LEGAL_REVIEW_NEEDED}}` placeholders and publish retention promises that must match what the code enforces (see #167). | ⏳ |
| A4 🟢 | Confirm **DNS / TLS** for all three domains (go2my.link, g2my.link, lnks.page) + admin subdomain before public launch. | ⏳ |
| A5 🟢 | Provide **MaxMind license/secret** if country-level analytics geolocation (#43) should be enabled (currently gated off, graceful-absent). | ⏳ |

---

## 🤖 Done autonomously this session (2026-07-22) — for your visibility

| Change | Detail |
|---|---|
| ✅ **PR #171 → `main`** (merged) | Combined the two Dependabot bumps `actions/setup-node`→v7.0.0 (#168) + `actions/checkout`→v7.0.1 (#169) into **one** mergeable PR to remove the PR race; #168/#169 closed as superseded. |
| ✅ **PR #170 → `alpha`** (merged) | Propagated the still-needed `checkout` v7.0.1 bump onto the launch-prep branch (setup-node v7.0.0 was already there), CI green, merged the full launch-prep integration into `alpha`. |
| ✅ **PR #172 → `main`** (merged) | Rewrote `.github/dependabot.yml` to cover **all three tiers** (`target-branch: main/alpha/beta`) and **group** action bumps into one PR per branch — so future weeks won't recreate the #168/#169 race. |
| ⏳ **Flexible pricing/tiering** | `Pricing_Strategy.md` + an unlimited-tiers/unlimited-features/flexible-pricing data model are being designed and scaffolded **disabled** (see D4). |
| ⏳ **Backlog review + next steps** | A full open+closed GitHub-issue review is running to drive prioritized follow-up work (each item gets an issue, commit, and handoff update). |

---

## 📌 Open backlog highlights (populated from the issue review — see HANDOFF.md for the live list)

_This section is filled in as the backlog review completes; the authoritative live list lives in
`HANDOFF.md` and the GitHub issue tracker. Known launch-relevant items carried over from the
2026-07-19 conformance audit:_

- 🔴 **#163** — account-deletion requests are never executed (blocked on D1).
- 🔴 **#167** — published data-retention periods are not enforced (blocked on D1).
- 🟠 **#165** — individually-registered users (the `[default]` org) get **no analytics**.
- 🟠 **#166** — short URLs can be minted on an **unverified** custom domain → dead links.
- 🟠 **#164** — analytics KPI tiles render **raw translation keys** (keys used but never seeded).
- ✅ **#162** — GDPR data-export download — **fixed** in the launch-prep merge (verify issue closed).

---

_This file is maintained continuously. If a session ends unexpectedly, start here + `HANDOFF.md`._
