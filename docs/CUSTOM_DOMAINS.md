# 🌐 Go2My.Link — Custom Short-Domain Setup Guide

> A step-by-step guide for organisations bringing their own domain
> (e.g. `links.acme.com` or `acme.link`) for their short links, instead of
> using the shared `g2my.link` domain. Covers DNS, ownership verification,
> and TLS/HTTPS.
>
> Related: [`docs/DEPLOYMENT.md`](DEPLOYMENT.md) (hosting-side domain
> configuration) · GitHub issue **#91**.

## 📋 Overview

Adding a custom short domain is a five-step process:

| Step | What you do | Where |
| --- | --- | --- |
| 1 | Add the domain in your dashboard | **Org → Short Domains** |
| 2 | Add a **TXT** record to prove you own the domain | Your DNS provider |
| 3 | Add a **routing** record (CNAME or A/ALIAS) so traffic reaches us | Your DNS provider |
| 4 | Click **Verify** | **Org → Short Domains** |
| 5 | Enable **TLS/HTTPS** for the domain | Dreamhost panel (manual today — see [§5](#5--tls--https)) |

Until step 4 succeeds, the domain is **not routable** — visiting it will show
a "Domain Not Configured" page rather than resolving any short link. This is
deliberate: an unverified domain must never be able to read another
organisation's (or the shared `g2my.link` default's) short-code namespace
just by pointing DNS at us.

## 1. 🏢 Add the domain in your dashboard

1. Sign in to **admin.go2my.link**.
2. Go to **Organisation → Short Domains**.
3. Under **Add Short Domain**, enter your domain (e.g. `links.acme.com` or
   `acme.link`) — no `https://`, no trailing slash — and click **Add**.
4. The page immediately shows you the two DNS records you need to set (also
   always available afterwards from that domain's row via **Show DNS
   records**).

Your organisation's plan has a maximum number of short domains
(`org.max_short_domains`, 0 = unlimited on your plan); adding beyond that cap
is rejected with an explanatory error.

## 2. ✅ Set the TXT verification record

This proves to us that you control the domain's DNS, before we ever route
traffic for it.

| Field | Value |
| --- | --- |
| **Type** | `TXT` |
| **Host / Name** | `_g2ml-verify.<your-domain>` (the exact prefix is shown on the Add-domain screen; the operator-configurable default is `_g2ml-verify`) |
| **Value** | The random verification token shown on the Add-domain screen (a long hex string, unique to this domain — never reused, never guessable) |

> ⚠️ **Do not** use your organisation handle, account name, or anything else
> as the TXT value — it must be the exact token shown in the dashboard.

## 3. 🔀 Set the routing record

This is what actually sends visitor traffic to the Go2My.Link redirect
engine. Which record type you need depends on whether you're using a
subdomain or an apex (root) domain:

| Your domain looks like | Record type | Host / Name | Value |
| --- | --- | --- | --- |
| `links.acme.com` (a **subdomain**) | `CNAME` | `links.acme.com` | `g2my.link` |
| `acme.link` (an **apex/root domain**) | `A` or `ALIAS`/`ANAME` | `@` / your bare domain | The Dreamhost server IP (get the current IP from your Dreamhost panel — *Manage Domains* — since it can change; do not hardcode an old value) |

**Why apex domains are different:** the DNS standard does not allow a `CNAME`
record at the zone apex (the bare domain with no subdomain) alongside the
other records a domain needs (`MX`, `NS`, etc.). If your registrar supports
`ALIAS`/`ANAME`/"CNAME flattening", use that and point it at `g2my.link` the
same as a CNAME. Otherwise use a plain `A` record pointed at the Dreamhost
server IP. **Prefer a subdomain** (`links.acme.com`) over an apex domain when
you have the choice — it is simpler, portable, and does not require an IP
that can change under you.

## 4. 🔍 Click Verify

1. Wait for DNS propagation. This is usually minutes, but can take up to
   **48 hours** depending on your provider and any existing TTL on the
   record you replaced.
2. Back on **Organisation → Short Domains**, click **Verify** on the
   domain's row.
3. On success, the domain's badge changes to **Verified** and **Routing
   live** — it is now resolving short links.
4. On failure, the error message tells you why (no TXT record found yet, or
   found but the value didn't match) — fix the record and click **Verify**
   again. Verification is safe to retry as many times as you need.

### 📡 Provider-specific notes

- **Cloudflare** — DNS → Records → Add record. Cloudflare auto-appends your
  zone, so enter just the prefix (`_g2ml-verify`), not the full domain, in
  the Name field. For the routing CNAME, set the proxy status to **DNS
  only** (grey cloud) — a proxied (orange cloud) CNAME sends traffic through
  Cloudflare's edge first, which works but means TLS is Cloudflare's, not
  ours (see [§5.3](#53-model--partner-fronts-with-their-own-cloudflare)).
- **GoDaddy** — My Products → DNS → Add New Record. Also wants just the
  Host prefix, not the full domain.
- **Namecheap** — Domain List → Manage → Advanced DNS → Add New Record.
  Namecheap does not support a plain CNAME at the apex — use their `ALIAS`
  or `URL Redirect` record type for an apex domain, or use a subdomain
  instead.
- **Route 53 / other registrars** — the same TXT + CNAME/A shape applies;
  consult your provider's docs for the equivalent of "Add record" and
  whether they support ALIAS/ANAME for apex CNAME-like routing.

## 5. 🔐 TLS / HTTPS

This is the part that is **honestly not yet self-serve** on Dreamhost shared
hosting. There is no wildcard certificate and no API-driven automatic TLS
for arbitrary partner domains today. Two models exist:

### 5.1 Model ② (current) — Manual Dreamhost hosted-domain + Let's Encrypt

This is what you get **today**. It requires a manual step by the Go2My.Link
operator for **each** partner domain — it does not scale to self-serve, and
we say so plainly here rather than pretending otherwise.

1. Once your domain is **Verified** in the dashboard, contact
   support@go2my.link (or your account contact) referencing your
   organisation and domain.
2. **Operator step:** the domain is added as a **hosted/mirror domain** in
   the Dreamhost panel (*Manage Domains → Add Hosted Domain*), with its
   document root pointed at `web/G2My.Link/public_html/` (the same redirect
   engine that serves `g2my.link`) — the routing DNS you set in
   [§3](#3--set-the-routing-record) is what makes traffic actually arrive
   there.
3. **Operator step:** Let's Encrypt is enabled for the domain from the same
   Dreamhost panel screen (*Secure your domain with Let's Encrypt free
   SSL*). Dreamhost auto-renews Let's Encrypt certificates it manages, so
   this is a one-time step per domain, not a recurring one.
4. Once done, `https://` on your domain serves your organisation's short
   links with a valid certificate. `tlsStatus` on your domain's row moves
   from `none` → `pending` → `active` as this happens.

**Honest limitation:** every domain needs this manual panel step from us —
there is no self-serve path to TLS today. If you need TLS live faster than
our normal turnaround, say so when you contact us.

### 5.2 Model ① (roadmap, recommended) — Cloudflare for SaaS ⭐

**This is documented as the planned direction, not built yet.** Once
implemented, this is intended to replace the manual step above for new
domains:

- You CNAME your domain to a Cloudflare-managed fallback origin (instead of,
  or in addition to, `g2my.link` directly).
- Go2My.Link calls the Cloudflare API to register your domain as a **Custom
  Hostname**; Cloudflare then automatically issues and renews a TLS
  certificate for it and proxies traffic to our origin.
- **Why this is the target model:** fully automated TLS at scale, no manual
  per-domain panel step, and Cloudflare's WAF/CDN in front of every partner
  domain "for free" as a side effect (Cloudflare already sits in front of
  our own domains).
- **Why it isn't built yet:** it requires a paid Cloudflare for SaaS
  subscription, an API integration (issuing/monitoring Custom Hostnames),
  and a Cloudflare API token stored in `auth_creds.php` — real scope on its
  own, tracked separately from this issue (#91) as a fast-follow.

If you're evaluating whether to wait for automated TLS or go with the manual
path now: the manual path works fine for a handful of domains today; if
you're a platform partner expecting many organisations to each bring their
own domain, talk to us about timeline for the Cloudflare-for-SaaS rollout.

### 5.3 Model ③ — Partner fronts with their own Cloudflare

If you already run your domain through your **own** Cloudflare account
(free tier is fine), you get automated TLS today with zero work on our
side:

1. Complete steps 1–4 above (ownership verification + routing still work
   the same way — Cloudflare is just proxying, not replacing, the CNAME).
2. In your Cloudflare dashboard, set the routing CNAME's proxy status to
   **Proxied** (orange cloud) instead of DNS-only.
3. Cloudflare issues and manages your domain's certificate; your visitors'
   browsers see a certificate from Cloudflare, terminating TLS at
   Cloudflare's edge and connecting to us over its own connection.

This requires a bit more DNS sophistication than the fully-managed models,
and is a good fit for technical partners who already use Cloudflare for
their main site.

## 🐛 Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| **Verify fails: "No TXT record found"** | DNS hasn't propagated yet, or the record was added at the wrong host/name | Double-check the exact host shown in the dashboard (it includes the `_g2ml-verify.` prefix *and* your domain); wait and retry — propagation can take up to 48 hours |
| **Verify fails: "value does not match"** | A TXT record exists at the right host, but its value is wrong (typo, or a leftover record from a previous attempt) | Some DNS panels silently keep old TXT records at the same host if you don't delete the old one first — check for and remove duplicates, then retry |
| **Domain shows "Domain Not Configured" after adding DNS** | You haven't clicked **Verify** yet, or verification hasn't succeeded | Adding DNS records alone does not make a domain routable — verification must succeed first (this is deliberate; see the overview above) |
| **CNAME "not allowed" error at my apex domain** | DNS forbids CNAME at the zone apex | Use `A`/`ALIAS`/`ANAME` at the apex instead (see [§3](#3--set-the-routing-record)), or use a subdomain |
| **Works over `http://` but not `https://`** | TLS has not been provisioned yet for the domain | See [§5](#5--tls--https) — TLS is a separate, currently-manual step after DNS verification |
| **Propagation is taking a long time** | Your previous TTL on that host, or your resolver's cache | DNS propagation is capped by the TTL of any record it's replacing — if you're testing, temporarily lower the TTL on the old record before you plan to make the change, then raise it back afterwards |
| **DNSSEC-signed zone rejects new records** | Some DNSSEC-aware panels require re-signing after adding records | Consult your provider's DNSSEC documentation; this is provider-specific and outside Go2My.Link's control |

## 🗺️ Roadmap (not built yet)

The following are planned improvements tracked separately from the current
build (see GitHub issue #91 and its linked follow-ups) — not available
today:

- **Cloudflare for SaaS automation** — see [§5.2](#52-model--roadmap-recommended--cloudflare-for-saas-).
- **In-app setup wizard** with copy-to-clipboard DNS records and inline
  propagation-status checking.
- **Background re-verification** — a periodic job that re-checks DNS for
  already-verified domains and flags a domain if its TXT/routing record is
  later removed, rather than only checking on demand.
- **`tblOrgDomains` (brand/primary/redirect/linkspage domains) unification**
  with the short-domain routing table — today these are two separate
  tables with two separate verification flows; unifying them is tracked as
  a follow-up rather than part of this change.
- Punycode/IDN domain support, a reserved-domain list, and a pre-flight
  conflict check before adding a domain.
