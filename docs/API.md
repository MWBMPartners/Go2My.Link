# GoToMyLink — API Documentation

> RESTful API reference for the GoToMyLink platform.

## Overview

The GoToMyLink API provides programmatic access to URL shortening, link management, and analytics.

| Property | Value |
| --- | --- |
| **Base URL** | `https://go2my.link/api/v1/` |
| **Authentication** | API key via `X-API-Key` header |
| **Response formats** | JSON (default), XML (with embedded XSLT) |
| **Rate limiting** | Per subscription tier |

> **Note:** The API is implemented in Phase 6. This document serves as the design reference.

## Authentication

All API requests require an API key passed via the `X-API-Key` HTTP header:

```
X-API-Key: your-api-key-here
```

API keys are managed through the user dashboard under **Settings > API Keys**.

### Rate Limits

| Tier | Requests/minute | Requests/day |
| --- | --- | --- |
| Free | 10 | 100 |
| Basic | 60 | 5,000 |
| Premium | 300 | 50,000 |
| Enterprise | 1,000 | Unlimited |

## Response Format

### JSON (Default)

```json
{
    "status": "success",
    "data": { ... },
    "meta": {
        "timestamp": "2026-02-22T19:00:00Z",
        "requestId": "abc123"
    }
}
```

### XML

Request XML by setting `Accept: application/xml` header or appending `?format=xml` to the URL.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="/api/v1/transform.xslt"?>
<response>
    <status>success</status>
    <data>...</data>
</response>
```

### Error Response

```json
{
    "status": "error",
    "error": {
        "code": 400,
        "message": "Invalid URL provided",
        "field": "destination_url"
    }
}
```

## Endpoints

### URLs

#### Create Short URL

```
POST /api/v1/urls/
```

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `destination_url` | string | Yes | The long URL to shorten |
| `custom_code` | string | No | Custom short code (if available) |
| `title` | string | No | Descriptive title for the link |
| `category_id` | int | No | Category to assign |
| `tags` | array | No | Tags to assign |
| `expires_at` | datetime | No | Expiration date (ISO 8601) |

**Response:**

```json
{
    "status": "success",
    "data": {
        "short_url": "https://g2my.link/abc123",
        "short_code": "abc123",
        "destination_url": "https://example.com/long-page",
        "created_at": "2026-02-22T19:00:00Z"
    }
}
```

#### Get Short URL Details

```
GET /api/v1/urls/{code}
```

#### Update Short URL

```
PUT /api/v1/urls/update/{code}
```

#### Disable Short URL

```
DELETE /api/v1/urls/disable/{code}
```

> **Note:** DELETE does not permanently remove the URL. It sets `isActive = 0`.

### Analytics

#### Get Link Analytics

```
GET /api/v1/analytics/{code}
```

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `period` | string | No | Time period: `7d`, `30d`, `90d`, `1y`, `all` (default: `30d`) |
| `group_by` | string | No | Grouping: `day`, `week`, `month` (default: `day`) |

**Response:**

```json
{
    "status": "success",
    "data": {
        "short_code": "abc123",
        "total_clicks": 1234,
        "period": "30d",
        "clicks_by_date": [ ... ],
        "top_referrers": [ ... ],
        "devices": { ... },
        "countries": { ... }
    }
}
```

#### Export Analytics

```
GET /api/v1/analytics/export/{code}
```

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `format` | string | No | Export format: `csv`, `xlsx` (default: `csv`) |
| `period` | string | No | Time period (same as above) |

### Account

#### Get Account Details

```
GET /api/v1/account/
```

**Response:**

```json
{
    "status": "success",
    "data": {
        "user_id": "...",
        "email": "user@example.com",
        "tier": "premium",
        "links_created": 45,
        "links_limit": 1000,
        "api_calls_today": 23,
        "api_calls_limit": 50000
    }
}
```

## Error Codes

| HTTP Status | Meaning |
| --- | --- |
| 200 | Success |
| 201 | Created (new short URL) |
| 400 | Bad request (invalid parameters) |
| 401 | Unauthorised (invalid/missing API key) |
| 403 | Forbidden (insufficient permissions or tier) |
| 404 | Not found (short code doesn't exist) |
| 409 | Conflict (custom code already taken) |
| 429 | Too many requests (rate limit exceeded) |
| 500 | Internal server error |

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- [DATABASE.md](DATABASE.md) — Database schema reference
- [DEPLOYMENT.md](DEPLOYMENT.md) — Deployment and hosting guide
