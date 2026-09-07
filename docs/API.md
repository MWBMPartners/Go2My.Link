# Go2My.Link — API Documentation

> An overview of the public API, written for a person deciding whether and how to use it.
> **Last checked against the code:** 2026-09-07.

## 📋 Overview

The public API lets another program do the things you would otherwise do by hand in the
dashboard: create short links, read them back, change where they point, remove them, and read
your click figures.

| Property | Value |
| --- | --- |
| **🌐 Address** | `https://go2my.link/api/v1/` |
| **🔑 Proving who you are** | An API key, sent as `Authorization: Bearer <key>`. `X-API-Key: <key>` also works. |
| **📄 Reply format** | JSON by default. XML if you ask for it. |
| **⏱️ How often you can call it** | A shared daily allowance per organisation, plus a per-key burst limit. See below. |
| **📊 How many routes** | 11 |

### 📖 Three ways to read the reference

There is one specification file, `web/Go2My.Link/public_html/api/openapi.yaml`, and three ways
to look at it. They cannot disagree with each other, because they are all reading the same file.

| Where | What it is | Best for |
| --- | --- | --- |
| **[`/api/docs/`](https://go2my.link/api/docs/)** | Redoc — a three-column reference manual | Reading the API end to end, comparing schemas |
| **[`/api/docs/swagger/`](https://go2my.link/api/docs/swagger/)** | Swagger UI — an interactive console | Trying a request and seeing the real reply |
| `/api/openapi.yaml` | The specification file itself | Feeding into your own tooling or code generator |

> ⚠️ **The Swagger console sends real requests.** There is no practice mode. If you create a
> link there, you have created a link. It needs a real API key, and every call it makes is
> counted against that key's allowance and written to the audit log just like any other.

**This page is a plain-English overview, not the authoritative reference.** Where this page and
the specification disagree, the specification is right — it is generated from the shipped code.

## 🔑 Proving who you are

Every route needs a valid API key. There is no anonymous access to `/api/v1/`.

```http
Authorization: Bearer g2ml_<8 characters>_<43 characters>
```

`X-API-Key` is accepted as an alternative, and is only looked at when there is no
`Authorization: Bearer` header.

A key looks like `g2ml_`, then an 8-character prefix, then the secret. The prefix is not
secret — it exists so the server can find the right row quickly. Only a one-way hash of the
whole key is stored, so a stolen copy of the database does not hand anybody a working key.

Keys are created and withdrawn in the dashboard, under **API Keys**.

> ⚠️ **A key is shown once, when it is created, and cannot be retrieved afterwards.** If it is
> lost, withdraw it and make a new one.

### 🎫 Permissions

Each key carries a list of permissions, and a route refuses a key that does not hold the right
one. Grant a key only what the program using it actually needs.

| Permission | Lets the key |
| --- | --- |
| `account:read` | Read the key's own account summary |
| `org:read` | Read the organisation summary |
| `urls:read` | List links, and read one link |
| `urls:write` | Create and change links |
| `urls:delete` | Remove links |
| `analytics:read` | Read click figures |
| `qr:link` | Attach a link to a QR code managed elsewhere (the CueRCode integration) |
| `domains:read` | — |
| `domains:write` | — |

> ⚠️ **`domains:read` and `domains:write` do nothing yet.** They can be granted on the API-keys
> page and the key system accepts them, but no route in the API looks at them, because the
> custom-domain routes have not been built. Granting them neither helps nor harms; it just has
> no effect.

### ⏱️ How often you can call it

Two separate limits apply, and both are measured over a *rolling* window rather than resetting
at midnight.

**A daily allowance, shared across the organisation.** Every key belonging to an organisation
draws from one pot, so creating more keys does not buy more requests. The size of the pot comes
from the organisation's tier:

| Tier | Requests per rolling 24 hours |
| --- | --- |
| Free | 100 |
| Basic | 5,000 |
| Premium | 50,000 |
| Enterprise | no limit |

If the tier does not set a figure, the site-wide default `api.default_daily_limit` is used
(5,000 as shipped).

**A burst limit, per key.** 60 requests per rolling 60 seconds, from
`api.default_per_minute`. This applies to every key, always.

A single key can be given its own daily allowance (`rateLimitOverride`). When it is, that key
is carved out of the shared pot entirely — its requests neither draw from nor count against the
organisation's allowance.

There is also a guard in front of authentication: 20 failed attempts a minute from one address
(`api.preauth_fail_per_minute`), which slows down anyone guessing at keys.

Going over any limit gives you `429` and a `Retry-After` header saying how long to wait.

## 📄 What a reply looks like

Every successful reply is wrapped the same way:

```json
{
    "status": "success",
    "data": { },
    "meta": {
        "requestId": "…",
        "rateLimit": {
            "limit": 5000,
            "remaining": 4987,
            "resetAt": "2026-09-08T12:00:00Z"
        }
    }
}
```

The `meta.rateLimit` block tells you where you stand without having to guess.

For XML instead, send `Accept: application/xml` or add `?format=xml` to the address.

An error looks like this. `field` is only present when the problem is one specific value you
sent:

```json
{
    "status": "error",
    "error": {
        "code": 422,
        "message": "A short code may contain only letters, numbers and hyphens.",
        "field": "custom_code"
    }
}
```

## 📡 The routes

All 11, exactly as the code defines them.

### Housekeeping

| Route | Permission needed | What it does |
| --- | --- | --- |
| `GET /api/v1/ping` | any valid key | Confirms the API is up and your key works |
| `GET /api/v1/account` | `account:read` | The key's own account: user, email, display name, organisation, key name, permissions |
| `GET /api/v1/org` | `org:read` | The organisation: handle, name, tier, whether it is verified, when it was created |

`GET /account` takes no parameters and cannot be pointed at anybody else's account — it always
describes the key being used.

### 🔗 Links

| Route | Permission needed | What it does |
| --- | --- | --- |
| `GET /api/v1/urls` | `urls:read` | List your organisation's links |
| `POST /api/v1/urls` | `urls:write` | Create one link. Replies `201` |
| `POST /api/v1/urls/bulk` | `urls:write` | Create up to 100 links at once. Replies `201` |
| `GET /api/v1/urls/{code}` | `urls:read` | Read one link |
| `PUT /api/v1/urls/{code}` | `urls:write` | Change one link |
| `DELETE /api/v1/urls/{code}` | `urls:delete` | Remove one link |

**Listing** accepts `limit`, `after` (a cursor for paging), `active`, and `tag`.

**Creating** takes a JSON body. The fields the code actually reads are:

| Field | Required | Notes |
| --- | --- | --- |
| `destination_url` | ✅ | Where the link should send people |
| `custom_code` | ❌ | Your own ending instead of random letters |
| `title` | ❌ | A label, for your own benefit |
| `tags` | ❌ | Tags to file it under |
| `qr_external_id` | ❌ | Only for the QR integration; needs `qr:link` as well |
| `qr_external_uuid` | ❌ | Only for the QR integration; needs `qr:link` as well |

**Bulk creation** takes `{"items": [ … ]}` with at most 100 entries. Each entry is checked on
its own, and the reply's `results` array says what happened to each one, so a single bad entry
does not lose the rest. Your organisation's link allowance is applied per row.

**A code belonging to another organisation gives exactly the same `404` as a code that does not
exist**, so the API never reveals whether somebody else's link exists.

`DELETE` deactivates the link rather than erasing it.

### 📊 Click figures

| Route | Permission needed | What it does |
| --- | --- | --- |
| `GET /api/v1/analytics` | `analytics:read` | A summary across the organisation, plus its top links |
| `GET /api/v1/analytics/{code}` | `analytics:read` | One link: totals, clicks over time, scan sources |

The summary takes `from`, `to` and `limit` (1 to 100, default 10).

The per-link route takes `from`, `to`, `bucket` (`day`, `week` or `month`), `limit`, and
`dimension`. Setting `dimension` adds a `breakdown` to the reply and may be one of
`browserName`, `osName`, `deviceType`, `requestReferer`, `scanSource` or `countryCode`.

> 📝 For a key belonging to an individually-registered user (the `[default]` organisation),
> both routes are narrowed to that person's own links.
>
> 📝 **There is no analytics export route.** Downloading figures as a CSV file is a dashboard
> feature only. An earlier version of this document listed
> `GET /api/v1/analytics/export/{code}`; that route has never existed.

## ⚠️ What can come back

| Status | What it means |
| --- | --- |
| ✅ 200 | It worked |
| ✨ 201 | Created — a new link, or a bulk batch |
| ⚠️ 400 | The JSON body could not be read |
| 🔒 401 | No key, or the key is invalid, expired, withdrawn, suspended, or its owner or organisation is inactive |
| 🚫 403 | The key is valid but lacks the permission this route needs. Also returned if the request is not over HTTPS |
| 🔍 404 | No such route, or no such link *for you* |
| ⚔️ 409 | Something clashes — usually a custom ending already in use |
| 📋 422 | A value you sent is not acceptable. `error.field` names it |
| ⏱️ 429 | Too many requests. Check `Retry-After` |
| 🔧 503 | The operator has switched the public API off (`api.public_enabled`) |
| 💥 500 | Something went wrong at our end. The cause is never revealed to the caller |

## 🔒 What happens before your request reaches a route

Every call runs the same gauntlet, and every outcome is written to the audit log whether it
succeeded or not:

1. HTTPS is required (outside local development) — otherwise `403`.
2. An unrecognised or malformed route is treated as `404`, never as a more specific error.
3. The master switch `api.public_enabled` — `503` when the operator has switched the API off.
4. The failed-attempt guard, by address, before any key is looked at — `429`.
5. The key is read from `Authorization: Bearer`, or failing that `X-API-Key` — `401` if neither.
6. The key is checked — `401` if invalid, expired, withdrawn, suspended, or its owner or
   organisation is inactive.
7. The rate limits — `429` with `Retry-After`.
8. Route and permission — `404` for an unknown route, `403` for a missing permission.
9. For `POST` and `PUT`, the JSON body is read — `400` if it cannot be. An empty body is
   treated as `{}`; a missing required field is then reported by the route itself as `422`.
10. The route runs.

## 📚 Related documentation

- 📖 [`/api/docs/`](https://go2my.link/api/docs/) — the full reference manual
- ⚙️ [`/api/docs/swagger/`](https://go2my.link/api/docs/swagger/) — the interactive console
- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — how the three components fit together
- 🗄️ [DATABASE.md](DATABASE.md) — the database schema
- 🚢 [DEPLOYMENT.md](DEPLOYMENT.md) — deploying and hosting
- 🌐 [CUSTOM_DOMAINS.md](CUSTOM_DOMAINS.md) — using your own short domain
