# GoToMyLink — Third-Party Libraries

> Local fallback copies of CDN-hosted libraries. Used when CDN is unreachable.

## Library Inventory

| Library | Version | CDN Source | Local Path | Purpose |
| --- | --- | --- | --- | --- |
| Bootstrap CSS | 5.3.3 | jsdelivr.net | `bootstrap/css/bootstrap.min.css` | UI framework |
| Bootstrap RTL CSS | 5.3.3 | jsdelivr.net | `bootstrap/css/bootstrap.rtl.min.css` | RTL language support |
| Bootstrap JS (Bundle) | 5.3.3 | jsdelivr.net | `bootstrap/js/bootstrap.bundle.min.js` | UI components (includes Popper) |
| jQuery | 3.7.1 | code.jquery.com | `jquery/jquery.min.js` | DOM manipulation, AJAX |
| Font Awesome CSS | 6.5.1 | cdnjs.cloudflare.com | `fontawesome/css/all.min.css` | Icon library |
| Font Awesome Webfonts | 6.5.1 | cdnjs.cloudflare.com | `fontawesome/webfonts/*.woff2` | Icon font files |
| Chart.js | 4.4.7 | jsdelivr.net | `chartjs/chart.umd.min.js` | Charts and data visualisation |

## CDN URLs

### Bootstrap 5.3.3
- CSS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css`
- JS: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js`
- RTL: `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css`
- Docs: https://getbootstrap.com/docs/5.3/

### jQuery 3.7.1
- JS: `https://code.jquery.com/jquery-3.7.1.min.js`
- Docs: https://api.jquery.com/

### Font Awesome 6.5.1
- CSS: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css`
- Docs: https://fontawesome.com/docs/web/

### Chart.js 4.4.7
- JS: `https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js`
- Docs: https://www.chartjs.org/docs/

## Planned Libraries (Future Phases)

| Library | Phase | Purpose |
| --- | --- | --- |
| Leaflet.js | 6 | Geographic maps for analytics |
| MaxMind GeoLite2 | 6 | IP geolocation database |
| WhichBrowser | 6 | Accurate User-Agent parsing |
| PhpSpreadsheet | 6 | Excel/CSV export |
| GrapesJS | 7 | WYSIWYG LinksPage editor |

## Fallback Pattern

All libraries are loaded from CDN first, with inline JavaScript checks that load the local copy if the CDN fails. See `_includes/header.php` and `_includes/footer.php` for the implementation.

## Updating Libraries

Since this project runs on shared hosting without CLI access:

1. Download the new version from the CDN/official source
2. Replace the files in the appropriate subdirectory
3. Update the CDN URLs in `_includes/header.php` and `_includes/footer.php`
4. Update the SRI hashes in `_includes/header.php` and `_includes/footer.php`
5. Update this README with the new version number
6. Test all three components
