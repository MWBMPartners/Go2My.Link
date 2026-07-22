# Lnks.page (Links Page) — Changelog

All notable changes to this component will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial directory structure and scaffolding (Phase 0)
- LinksPage renderer (Phase 8 / #45): `_functions/linkspage_resolver.php` (published-page +
  system-template + active-items lookup) and `_functions/linkspage_renderer.php` (the 7-placeholder
  substitution engine — `{{avatar}}`, `{{name}}`, `{{bio}}`, `{{links}}`, `{{social}}`, `{{theme}}`,
  `{{background}}` — consuming the 5 seeded system templates). `public_html/index.php` now resolves
  and renders `lnks.page/<slug>`; `public_html/404.php` is a new self-contained branded 404. Every
  user-authored value is escaped; every URL (item links, social links, avatar, icons) is scheme-
  allowlisted (http/https only); theme/background colours are hex-validated and fontFamily is
  validated against a CSS-safe character allowlist. Favicons are never fetched at render time — only
  already-stored `itemIcon`/`faviconCacheURL` values are consumed. `customHTML`/`customCSS` are
  deliberately never read or rendered (deferred to C.6 / #49, which needs its own sanitiser and a
  security review). Tightened this component's CSP (`default-src 'self'`, no third-party origins,
  `object-src 'none'`) now that every page it serves is self-contained.
- Age verification (Phase 8 / #50): a whole-page, good-faith, JavaScript-free age gate. If any active
  item on a resolved page is flagged `requiresAgeGate = 1`, the entire page is set aside behind a
  self-contained interstitial (`linkspage_renderer.php`'s `g2ml_linkspageRenderAgeGateInterstitial()`)
  until the visitor confirms via a same-origin, CSRF-protected POST — gated (and non-gated) item
  destinations are never selected into the interstitial at all, so they cannot leak pre-confirmation.
  Confirmation sets a short-lived (24h), HMAC-SHA256-signed `g2ml_agegate` cookie (new
  `web/_functions/adult_content.php`) — a single boolean fact, never a date of birth. New items are
  auto-flagged `requiresAgeGate = 1` when their destination matches a curated, operator-configurable
  adult-domain allowlist (`g2ml_isAdultDomain()`, `linkspage.adult_domains` setting); the owner can
  still enable/disable the gate manually for anything not on that list via a new checkbox in the
  admin item form.
