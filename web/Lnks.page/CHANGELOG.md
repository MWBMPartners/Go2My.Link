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
