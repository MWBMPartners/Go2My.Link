# 💷 Go2My.Link — Pricing & Monetisation Strategy

> ⚠️ **Status: DRAFT for owner review.** The **tier names**, **GBP price points**, and the
> **decision to launch/enable billing** in this document are **NOT approved** and must not be
> treated as final. Everything else here (the competitor scan, positioning, the pricing
> *mechanics* the data model supports, and the launch posture) reflects the current build.
> See [§7 Owner sign-off checklist](#7--owner-sign-off-checklist-answer-before-flipping-any-switch)
> at the end of this document for the specific decisions required before any price is shown to
> a customer or `billing.pricing_engine_enabled` is switched on.
>
> **Companion artifacts:**
> [`web/_sql/schema/036_pricing_engine.sql`](web/_sql/schema/036_pricing_engine.sql) (data
> model), [`web/_functions/pricing.php`](web/_functions/pricing.php) (resolver). Both ship as
> **additive, disabled-by-default scaffolding** — see
> [§6 Launch recommendation](#6--launch-recommendation).
>
> **Author context:** MWBM Partners Ltd (MWservices), UK. Base currency **GBP**.
> Three components: A = go2my.link (main), B = g2my.link (redirect engine),
> C = lnks.page (LinksPage / link-in-bio).

---

## 1. 🔍 Competitor scan (as of mid-2026)

⚠️ Prices below are **indicative, as-of-2026**, gathered from public pricing pages and
third-party pricing round-ups (sources at the end of this section). Competitors change
prices frequently; re-verify before publishing any comparison. All competitor prices in
**USD** as published (≈ £0.78/$1 at time of writing).

| Competitor | Free tier | Entry paid | Mid | Top self-serve | Enterprise | What they gate hardest |
|---|---|---|---|---|---|---|
| **Bitly** | 10 links/mo, basic analytics | Core **$10/mo** (100 links, no custom domain) | Growth **$29–35/mo** (500 links, 1 custom domain, 4-mo click history) | Premium **$199/mo** (3k links, city analytics, 1-yr history) | Custom (~$10k+/yr) | Link volume, custom domains, click-history retention |
| **Rebrandly** | ~500 branded links, 5k tracked clicks, watermarked QR | Essentials **$13/mo** ($8 annual) — 250 links, 1 domain, 10k clicks | Professional **$39/mo** — workspaces, teammates, password-protect | Growth (top self-serve) — deep links, roles | Custom | Tracked clicks, teammates, deep linking |
| **Short.io** | 1,000 links, 5 domains, 50k clicks | Hobby **$5/mo** | Pro **$18/mo** — unlimited links/clicks, 10 domains | Team **$48/mo** — unlimited members, 50 domains, SLA | **$148/mo** — unlimited domains, SSO, S3 export | Domains, team size, data export |
| **Dub.co** | 25 links/mo, 1k events, 3 domains, API + QR | Pro **$25/mo** (annual) — 1k links/mo, 50k events, 10 domains, 3 users | Business **$75/mo** — 10k links/mo, 250k events, webhooks, conversion tracking | Advanced **$250/mo** — 50k links/mo, 1M events | Custom — SSO/SAML, audit logs | "Events" (tracked clicks), links **per month**, retention, users |
| **TinyURL** | Basic shortening + QR, no analytics | Pro **$16/mo** — 500 active URLs, custom domains, 10k API calls | Bulk **$83/mo** — 300k URLs, 100k API calls | — | Custom | Active-URL count, API calls |
| **BL.INK** | None (21-day trial) | Expert+ **$48/mo** — 1 user, 1 domain, 10k links | SMB **$99/mo** — dynamic links | Team **$299/mo** (10 users) | Custom (compliance-focused) | Users, dynamic links, compliance |

**Market observations that shape our design:**

- 📊 Two dominant metering models: **active links** (Bitly, TinyURL) vs **links-created-per-month + tracked clicks/events** (Dub, Rebrandly, Short.io). Our schema must support *any* metering dimension — competitors have changed theirs repeatedly, and we should be able to as well without a schema change.
- 🌐 Custom domains are the near-universal upgrade trigger; free tiers vary wildly (Short.io very generous, Bitly very stingy).
- 🕓 **Analytics retention** is a quiet but effective gate (Bitly 30 days → 4 months → 1 year).
- 💰 Annual discount is universally ~17–20% ("2 months free").
- 🧩 Nobody in this set bundles link-in-bio **and** dynamic QR **and** shortener well at low price points — Bitly bundles all three but prices aggressively upward.

**Sources** (retrieved 2026-07; indicative):
[replug.io/blog/bitly-pricing](https://replug.io/blog/bitly-pricing) · [redirhub.com/blog/bitly-pricing-2026](https://www.redirhub.com/blog/bitly-pricing-2026) · [linklyhq.com/blog/bitly-enterprise-pricing](https://linklyhq.com/blog/bitly-enterprise-pricing) · [replug.io/blog/rebrandly-pricing](https://replug.io/blog/rebrandly-pricing) · [propicked.com/marketing/rebrandly/pricing](https://propicked.com/marketing/rebrandly/pricing) · [linklyhq.com/review/shortio](https://linklyhq.com/review/shortio) · [frontdeskreview.com/software/link-shorteners/short-io](https://frontdeskreview.com/software/link-shorteners/short-io/) · [linklyhq.com/review/dub](https://linklyhq.com/review/dub) · [dub.co/blog/new-pricing](https://dub.co/blog/new-pricing) · [linklyhq.com/review/tinyurl](https://linklyhq.com/review/tinyurl) · [spotsaas.com/product/tinyurl/pricing](https://www.spotsaas.com/product/tinyurl/pricing) · [softwaresuggest.com/bl-ink](https://www.softwaresuggest.com/bl-ink) · [capterra.com/p/189131/BL-INK](https://capterra.com/p/189131/BL-INK/)

---

## 2. 🎯 Positioning

**Go2My.Link is the privacy-first, GDPR-native link platform — three products, one subscription, honest UK pricing.**

- 🛡️ **Privacy-first / GDPR-native.** UK company, UK/EU data posture, consent-aware analytics (DNT respected, cookie-consent + data-rights tooling already shipped), country-level (not creepy person-level) geolocation via MaxMind. This is a *product* differentiator versus US incumbents, and a compliance necessity for UK/EU SMEs, agencies, public sector and charities.
- 🧩 **Three products in one plan:** short links (A+B) + **dynamic QR with re-point & scan attribution** (CueRCode) + **LinksPage link-in-bio** (C) with custom domains and age-verification. Competitors charge separately or price the bundle high.
- 🆓 **Unlimited anonymous shortening** stays free forever (top-of-funnel goodwill + brand distribution) — accounts add management, branding, analytics, and the two extra products.
- 🇬🇧 **GBP-first, transparent pricing**, undercutting Bitly's ladder at every rung while being more generous than Bitly free and more honest than "unlimited*" claims.
- 🔞 **Regulated-content readiness** (age-gate redirects, LinksPage age-verification) is a niche nobody above serves explicitly — price it into upper tiers.

---

## 3. 🪜 Recommended tier ladder — ⚠️ DRAFT, names/prices pending sign-off

The data model supports **any number of tiers**; this is the recommended *launch* ladder. It
reconciles today's mismatch (DB seeds `free/basic/premium/enterprise` vs marketing page
`Free/Pro/Enterprise`) by **renaming, not deleting**: existing `tierID` slugs are kept as
stable keys where possible, display names change.

| | **Free** | **Starter** | **Pro** | **Business** | **Enterprise** |
|---|---|---|---|---|---|
| tierID (slug) | `free` (keep) | `basic` → display "Starter" | `premium` → display "Pro" | `business` (new row) | `enterprise` (keep) |
| **Monthly (GBP)** | £0 | **£4** | **£12** | **£29** | from **£99** (custom) |
| **Annual (GBP)** | £0 | **£40** (2 mo free) | **£120** (2 mo free) | **£290** (2 mo free) | custom |
| **Lifetime** | — | £99 (founding, capped) | £249 (founding, capped 500) | — | — |
| Target buyer | individuals, tyre-kickers | creators, sole traders | SMEs, marketers, devs | agencies, teams, regulated content | orgs needing DPA/SLA/volume |

**Price rationale (proposal, not sign-off):**

- **Starter £4** (~$5): matches Short.io Hobby, less than half Bitly Core — *and* includes a custom domain (Bitly Core has none). The "first paid pound" must be trivially easy to justify.
- **Pro £12** (~$15): below Rebrandly Essentials-annualised + TinyURL Pro + Short.io Pro, while bundling advanced redirects, dynamic QR and LinksPage — features that cost $29–75/mo elsewhere. This is the anchor tier; most revenue should land here.
- **Business £29** (~$37): agency/team price point at Bitly-Growth money but with 10 seats, custom-HTML LinksPage, priority support — an order of magnitude cheaper than functional equivalents (BL.INK Team $299, Dub Business $75).
- **Enterprise from £99**: signal that a real enterprise offer exists (DPA, SLA, custom limits) without publishing a ceiling. Custom quotes via sales contact.
- **Annual = exactly 2 months free (16.7%)** — industry-standard, easy to market ("2 months free"), simple maths in GBP.
- **Lifetime (founding-member) deals**: `billingCycle ENUM` already includes `lifetime`. Cap units (`maxSubscriptions` in the price book) — e.g. 500 lifetime-Pro at £249 (≈21 months of Pro) — great early cash + community without long-tail liability. Retire the plan by flipping `isActive` off; existing holders are grandfathered automatically because subscriptions pin their plan.

---

## 4. 🧮 Feature-to-tier matrix — ⚠️ DRAFT values pending sign-off

Granular feature slugs match the **feature registry** seeded by
[`web/_sql/seeds/018_pricing_feature_registry.sql`](web/_sql/seeds/018_pricing_feature_registry.sql).
`∞` = unlimited (NULL). Existing seed values are kept where sensible so current orgs see no
regression.

| Feature (registry slug) | Type | Free | Starter | Pro | Business | Enterprise |
|---|---|---|---|---|---|---|
| `links.max` (active short links) | limit | 50 | 500 | 5,000 | 25,000 | ∞ |
| `links.custom_alias` | boolean | ✅ | ✅ | ✅ | ✅ | ✅ |
| `domains.custom_max` (verified short domains) | limit | 0 | 1 | 5 | 20 | ∞ |
| `redirects.scheduled` | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `redirects.device` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `redirects.geo` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `redirects.agegate` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `analytics.enabled` (dashboard) | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `analytics.retention_days` | limit | 30 | 365 | 730 | 1,095 | ∞ |
| `analytics.export_csv` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `analytics.geo_country` (MaxMind) | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `api.access` | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `api.requests_per_day` | quota | 100 | 5,000 | 50,000 | 250,000 | ∞ |
| `api.bulk_endpoints` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `qr.dynamic` (CueRCode) | boolean | ✅ | ✅ | ✅ | ✅ | ✅ |
| `qr.scan_attribution` | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `linkspage.pages_max` | limit | 1 | 3 | 10 | 50 | ∞ |
| `linkspage.all_templates` | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `linkspage.custom_domain` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `linkspage.agegate` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `linkspage.custom_html` ⚠️ high-risk | boolean | ❌ | ❌ | ⚠️ see note | ✅ | ✅ |
| `org.seats` (users per org) | limit | 1 | 1 | 3 | 10 | ∞ |
| `support.priority` | boolean | ❌ | ❌ | ❌ | ✅ | ✅ |

> ⚠️ **`linkspage.custom_html` placement:** current seed grants `hasCustomHTML` at
> `premium` (= new "Pro"). Recommendation: **move it up to Business** — it is the
> highest stored-XSS-risk feature and belongs with a smaller, higher-trust cohort;
> a paid add-on can offer it to Pro orgs case-by-case (per-org override). This is an
> **open question for the owner** ([§7, Q3](#7--owner-sign-off-checklist-answer-before-flipping-any-switch)) — it changes an already-seeded grant.

Legacy `has*`/`max*` columns map 1:1 into the registry (`hasAdvancedRedirects` becomes the
umbrella; granular `redirects.*` slugs supersede it — the resolver treats "any granular
redirect flag on" as the legacy flag for compatibility).

---

## 5. ⚙️ Pricing mechanics the data model supports

Every mechanic below maps to a concrete structure in
[`web/_sql/schema/036_pricing_engine.sql`](web/_sql/schema/036_pricing_engine.sql). These are
**capabilities the schema was built to support**, not launch commitments — see
[§6](#6--launch-recommendation) for what actually ships enabled.

| # | Mechanic | How the model supports it |
|---|---|---|
| 1 | One-off discounts | `tblCoupons.discountKind` + `durationType='once'` |
| 2 | Limited-time discounts | `tblCoupons.validFrom/validUntil` |
| 3 | Lifetime discounts (forever off recurring) | `durationType='forever'` |
| 4 | **Lifetime tiers** (pay once, keep tier) | `tblPricePlans.planType='lifetime'` (+ `maxSubscriptions` scarcity cap) |
| 5 | Pay-as-you-go (PAYG) | `planType='payg'` + `meteredFeatureUID` + `unitPrice/unitSize` + `tblUsageCounters` |
| 6 | **PAYG capped at flat-tier equivalent** | `planType='payg_capped'` + `capAmount` + `equivalentPlanUID` (cap defaults to that plan's price; `capBehaviour='flat_convert'` = "never pay more than Pro") |
| 7 | Intro pricing | a second recurring plan on the same tier with `validUntil` + coupon `first_n_periods` |
| 8 | Coupons / promo codes | `tblCoupons` (+ `tblCouponRedemptions` for per-org/total caps) |
| 9 | Add-ons (e.g. +5 domains, custom-HTML for Pro) | `planType='addon'` plan → active sub grants a `tblOrgFeatureOverrides` row |
| 10 | Annual discount | separate plan row `billingInterval='year'` priced at 10× monthly |
| 11 | Per-seat vs per-org | `tblPricePlans.perSeatAmount` + `minSeats/maxSeats`; per-org = flat `amount` only |
| 12 | Currency/region variants | many plan rows per tier: `currency` + `region` (e.g. GBP/GB default, EUR/EU, USD/ROW) |
| 13 | Grandfathering | subscriptions pin `planUID` + `priceAtSubscription` in `tblSubscriptionPlans`; retiring a plan (`isActive=0`) never touches existing subs |
| 14 | First-N-periods discounts | `tblCoupons.durationType='first_n_periods'` + `durationPeriods` |
| 15 | Stacking rules | `tblCoupons.isStackable` + `stackingGroup` (same group never stacks) |

**Additional flexibility, also supported by the model:**

- 📏 **Arbitrary usage-metering dimensions** — any `tblFeatures` row with `isMeterable=1` can be metered (links created, tracked clicks, API calls, QR scans, LinksPage views…). Adding a dimension = 1 row.
- 📅 **Entitlement effective-dating** — `tblTierFeatures` and `tblOrgFeatureOverrides` carry `effectiveFrom/effectiveUntil`: schedule a tier upgrade announcement ("from 1 Sep, Pro gets 10 domains") or a temporary comp, with zero code.
- 🧪 **Trial→paid** — `tblPricePlans.trialDays` (feeds existing `tblSubscriptions.trialEndsAt`); recommend 14-day Pro trial, no card, for launch.
- ➗ **Proration** — credit issuance as negative `one_off` payments referencing the sub; policy documented, executed at billing-integration time.
- 🎟️ **Credit packs** — `planType='credit_pack'` (e.g. API-request top-ups) credit `tblUsageCredits`.
- 🧬 **Price experiments** — multiple simultaneous active plans per tier; `isDefault` picks the advertised one per currency/region; cohorts keep whatever they signed on (grandfathering does the rest).
- 🤝 **Referral / goodwill credits** — same credit mechanism as packs, `sourceType='comp'` overrides for feature comps.
- 🎓 **Sector pricing** (charity/EDU) — dedicated coupons (`stackingGroup='sector'`) or hidden plans (`isVisible=0`).
- 🚦 **Overage behaviour is data** — `capBehaviour ENUM('flat_convert','hard_stop','notify_only')` decides what happens at a cap, per plan, not per code path.

---

## 6. 🚀 Launch recommendation

**Ships now, DISABLED (data present, switches off — zero behaviour change):**

- The full data model (10 tables + verification view — schema `036`), the feature-registry
  seed + legacy backfill (seed `018`), and the nine `billing.*` engine switches, **all seeded
  `'0'`** (seed `019`).
- The resolver (`web/_functions/pricing.php`) and its single guarded hook inside
  `web/_functions/entitlements.php`'s `_g2ml_resolveOrgTier()`.
- **The entire pricing engine remains behind `billing.pricing_engine_enabled=0`** until the
  payment provider is integrated and the items in §7 are signed off — the platform keeps its
  current "gate first, bill later" behaviour (GlobalAdmin assigns tiers manually) with
  **zero behaviour change** on deploy. `entitlements.php`'s output is byte-identical to
  before this scaffold landed.

**Recommended to enable at launch, once §7 is signed off:**

- Tiers Free/Starter/Pro/Business (Enterprise = "contact us" page, manual tier assignment — already possible today).
- Monthly + annual GBP recurring plans; annual = 2 months free.
- 14-day Pro trial (no card).
- One launch coupon: e.g. `LAUNCH25` — 25% off first 3 months (`first_n_periods`), expiring 60–90 days post-launch.
- Founding-member lifetime Pro (£249, cap 500) **only if** owner wants early cash-flow; otherwise defer.

**Build now, keep DISABLED beyond launch:**

- PAYG + PAYG-capped plans (`billing.payg_enabled=0`) — needs metering counters proven in production first.
- Regional/currency price books (GBP only at launch; EUR/USD rows staged `isActive=0`).
- Add-on & credit-pack plans; coupon stacking (`billing.coupon_stacking_enabled=0`).

**Explicitly out of scope until later:** SSO/SAML, invoicing beyond provider receipts,
VAT-inclusive display logic (needs owner/accountant decision — recommend showing ex-VAT for
business tiers, and note UK VAT registration threshold considerations), crypto payments.

**Recommended activation sequence** (see §7, Q10): deploy → run migration `020` (already-deployed
DBs only; fresh installs pick up schema `036`/seeds `018`–`019` automatically) → run
`tests/integration/pricing_backfill_verify.php` → flip `billing.pricing_engine_enabled` ON in a
staging org only (Organisation-scope settings row) → flip System-wide once verified.

---

## 7. ❓ Owner sign-off checklist — answer before flipping any switch

1. **Final tier names & slug policy** — keep slugs `free/basic/premium/enterprise`
   with display renames (Starter/Pro), or mint new tier rows (`starter`, `pro`,
   `business`) and retire old ones? (Slug-keep is zero-migration; new-slug is
   cleaner but needs org reassignment.) Also: add the proposed **Business**
   tier at all?
2. **GBP price points** — sign off £4 / £12 / £29 (+ annual = 10× monthly) or
   amend; Enterprise "from £99" vs pure "contact us".
3. **`linkspage.custom_html` placement** — stay at Pro (parity with today's
   `premium` seed) or move up to Business as recommended (risk containment)?
   Moving it changes an already-seeded grant for existing `premium` orgs.
4. **Free-tier API inconsistency (pre-existing)** — seed has `hasAPIAccess=0`
   but `maxAPIRequestsPerDay=100` on `free`. This document's proposal is Free =
   API access ON with 100/day; confirm or zero it.
5. **Payment provider** — Stripe vs PayPal vs GoCardless (`tblSubscriptions`
   provider fields currently name paypal/apple_pay/google_pay/crypto). The
   schema is provider-agnostic (`metadataJSON` carries provider price IDs), but
   the provider choice gates the billing-integration phase and webhook design —
   and Dreamhost shared hosting favours redirect/hosted-checkout flows over
   long-running webhook processors.
6. **Lifetime founding-member deal** — run it? Cap (500?), price (£249 Pro /
   £99 Starter), and whether `billing.lifetime_plans_enabled` flips at launch.
7. **Launch coupon** — `LAUNCH25` 25%-off-3-months yes/no, expiry window.
8. **VAT treatment** — display prices ex-VAT or inc-VAT; UK VAT registration
   status/threshold plan (affects pricing-page copy, not schema).
9. **Marketing-page reconciliation** — pricing page currently shows
   Free/Pro/Enterprise vs 4 seeded tiers; when to update it to the final
   ladder (needs `__('key')` i18n strings + WCAG-checked table).
10. **When to flip** `billing.pricing_engine_enabled` — recommended: deploy →
    run migration `020` → run `pricing_backfill_verify` → flip ON in staging org
    only (Organisation-scope settings row) → flip System-wide.
