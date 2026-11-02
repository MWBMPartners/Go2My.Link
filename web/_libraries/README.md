# Go2My.Link — Third-Party Libraries

> Master copies of the CDN-hosted libraries. The pages do not load anything from
> this folder: it sits outside every web root, and `.htaccess` blocks `/_libraries/`
> on purpose. The copies that pages use when a CDN is unreachable are in each web
> root's own `vendor/` folder (see "Fallback Pattern" below).

## 📦 Library Inventory

| Library | Version | CDN Source | Local Path | Purpose |
| --- | --- | --- | --- | --- |
| 🎨 Bootstrap CSS | 5.3.3 | jsdelivr.net | `bootstrap/css/bootstrap.min.css` | UI framework |
| 🔄 Bootstrap RTL CSS | 5.3.3 | jsdelivr.net | `bootstrap/css/bootstrap.rtl.min.css` | RTL language support |
| ⚙️ Bootstrap JS (Bundle) | 5.3.3 | jsdelivr.net | `bootstrap/js/bootstrap.bundle.min.js` | UI components (includes Popper) |
| 🎭 Font Awesome CSS | 6.5.1 | cdnjs.cloudflare.com | `fontawesome/css/all.min.css` | Icon library |
| 🔤 Font Awesome Webfonts | 6.5.1 | cdnjs.cloudflare.com | `fontawesome/webfonts/*.woff2` | Icon font files |
| 📊 Chart.js | 4.4.7 | jsdelivr.net | `chartjs/chart.umd.min.js` | Charts and data visualisation |

## 🌐 CDN URLs

### 🎨 Bootstrap 5.3.3

- CSS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css`
- JS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js`
- RTL: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css`
- Docs: <https://getbootstrap.com/docs/5.3/>

### 📜 jQuery (removed)

jQuery 3.7.1 used to be loaded on every page, but nothing used it, so it was
removed (#285), together with its CDN address in the Content-Security-Policy and
its folder here.

### 🎭 Font Awesome 6.5.1

- CSS: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css`
- Docs: <https://fontawesome.com/docs/web/>

### 📊 Chart.js 4.4.7

- JS: `https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js`
- Docs: <https://www.chartjs.org/docs/>

## 🔜 Planned Libraries (Future Phases)

| Library | Phase | Purpose |
| --- | --- | --- |
| 🗺️ Leaflet.js | 6 | Geographic maps for analytics |
| 🌍 MaxMind GeoLite2 | 6 | IP geolocation database |
| 🔍 WhichBrowser | 6 | Accurate User-Agent parsing |
| 📊 PhpSpreadsheet | 6 | Excel/CSV export |
| 🎨 GrapesJS | 7 | WYSIWYG LinksPage editor |

## 🔄 Fallback Pattern

Libraries are loaded from the CDN first. If a CDN file cannot be fetched, the page loads a copy from the `vendor/` folder of its **own** web root (`/vendor/...`), because a web root is served on its own and cannot reach this folder. See `_includes/header.php` (style sheets, with an `onerror` handler) and `_includes/footer.php` (the Bootstrap script, with a check for `bootstrap` being undefined).

The `vendor/` folders hold only the files the pages load, and each copy must be identical to the master here:

| Web root | Files in `vendor/` |
| --- | --- |
| `Go2My.Link/public_html` (main site) | `bootstrap/css/bootstrap.min.css`, `bootstrap/css/bootstrap.rtl.min.css`, `bootstrap/js/bootstrap.bundle.min.js`, `fontawesome/css/all.min.css`, `fontawesome/webfonts/fa-solid-900.woff2` |
| `Go2My.Link/_admin/public_html` (dashboard) | the same five files |
| `G2My.Link/public_html` (g2my.link's own pages) | `bootstrap/css/bootstrap.min.css`, `fontawesome/css/all.min.css`, `fontawesome/webfonts/fa-solid-900.woff2` |

Only the Font Awesome "solid" font file is copied, because the pages use only the `fas` icon style. If a page starts using `far` or `fab` icons, copy the matching `.woff2` file as well. `tests/unit/library_fallbacks_test.php` checks the copies byte for byte, checks them against the integrity hashes in the pages, and fails if a style is used without its font file. Lnks.page loads no CDN library, so it has no `vendor/` folder.

## 🛠️ Updating Libraries

Since this project runs on shared hosting without CLI access:

1. 📥 Download the new version from the CDN/official source
2. 🔄 Replace the files in the appropriate subdirectory
3. 📋 Copy the changed files over the same path in every web root's `vendor/` folder (the table above)
4. 🌐 Update the CDN URLs in `_includes/header.php` and `_includes/footer.php` (and in the g2my.link pages)
5. 🔒 Update the SRI hashes in the same places
6. 📝 Update this README with the new version number
7. ✅ Run the unit tests (they check the copies and the hashes), then test all three components
