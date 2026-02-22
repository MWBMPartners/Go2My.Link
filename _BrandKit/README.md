<!--
File: /BrandKit/README.md
Purpose: Root readme for Go2My.link branding assets (repo-ready folder)
(C) 2025–present MWBM Partners Ltd (d/b/a MW Services)
Version: 1.0
-->

# Go2My.link BrandKit 🔗✨

This folder contains **all branding assets** for **Go2My.link**, organised for easy use across:
- the marketing site
- the redirect domain UI
- dashboards/admin tools
- documentation and press

## Folder map

- `logos/`
  - `svg/` — primary vector logos (transparent background)
  - `png/` — transparent PNG exports
  - `animated/` — animated / hover / auto-theme SVG variants
  - `optimized/` — minified SVG for production web use
- `icons/`
  - `app/` — app icons (multiple sizes)
  - `maskable/` — Android/PWA maskable icons (safe padding)
  - `favicon/` — favicon PNG/ICO (and any favicon SVG if used)
- `pwa/` — PWA bundle (manifest + service worker + icons)
- `press-kit/`
  - `mockups/` — ready-to-use mockups (browser/phone/card/social)
  - `media/` — social banners / OpenGraph assets
- `video/`
  - `logo-intro/` — mini animated logo intro (MP4/WebM/H.265 + frames)
- `docs/` — brand kit PDF, font info, reference notes

## Quick usage

**Website header / nav:** use `logos/optimized/*.svg`  
**Dark UI:** use `logos/animated/*AutoTheme*.svg` or `logos/svg/*Dark*.svg`  
**Social sharing:** use `press-kit/media/*OG*.png`  
**Favicon:** use `icons/favicon/*.ico` and `*.png`  
**PWA:** copy `pwa/` into your public web root and link `manifest.json`

---

(C) 2025–present MWBM Partners Ltd (d/b/a MW Services)
Generated: 2026-02-22T23:09:05.540956Z
