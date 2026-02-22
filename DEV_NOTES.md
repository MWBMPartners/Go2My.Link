# GoToMyLink — Developer Notes

> Working notes, decisions, gotchas, and tips for the development team.

## Environment

- **Primary OS:** macOS (Windows occasionally)
- **IDE:** Visual Studio Code with FTP Sync extension
- **Hosting:** Dreamhost Shared Hosting
  - No CLI access (no Composer, no npm, no artisan)
  - Libraries must be manually downloaded and uploaded
  - CDN-first with local fallback pattern for all third-party libraries
- **PHP Version:** 8.5+ (with 8.4 backward compatibility via `version_compare()`)
- **MySQL Version:** 8.0+

## Key Architecture Decisions

### No Composer
Dreamhost shared hosting doesn't provide CLI access. All third-party PHP libraries must be:
1. Downloaded manually
2. Placed in `web/_libraries/` (server-wide) or `web/{component}/_libraries/` (component-specific)
3. Included via `require_once` with existence checks

### MySQLi Only
PDO is not used. All database interactions go through MySQLi with prepared statements exclusively.
This is both a project requirement and a security measure against SQL injection.

### Settings in Database
All configuration (except DB connection credentials) is stored in `tblSettings` with a scope
hierarchy: User > Organisation > System > Default (from `tblSettingsDictionary`).

Sensitive settings are encrypted with AES-256-GCM using the SALT from `auth_creds.php`.

### Clean URLs
No `.php` extensions visible to users. Achieved via:
- `.htaccess` RewriteRules (primary method)
- Directory-based routing (`/something/index.php` serves `/something`)

### Error Handling Strategy
- PHP errors → `tblErrorLog` (severity, code, title, detail, backtrace, request URL, headers)
- Activity logging → `tblActivityLog` (all request data, IP, UA, geo)
- Debug mode → `?debug=true` URL parameter (restricted to admin/allowed IPs)
- User-facing errors → Graceful branded error pages

## Gotchas & Tips

### PHP 8.5 vs 8.4
Use `version_compare(PHP_VERSION, '8.5.0', '>=')` for features only available in 8.5.
Always provide a fallback for 8.4 compatibility.

### Auth Credentials
The `auth_creds.php` files use `if (!defined('CONSTANT'))` guards. Per-component files
can override server-wide values by defining constants BEFORE including the server-wide file.

### .htaccess
Each component's `public_html/` needs its own `.htaccess` for URL routing.
Dreamhost respects `.htaccess` files with `AllowOverride All`.

## Coding Standards Quick Reference

- Full `if/else` blocks (no shorthand)
- Detailed inline comments with official documentation links
- Use PHP predefined constants (`DIRECTORY_SEPARATOR`, `PHP_EOL`, etc.)
- All UI strings use `__('key')` translation function
- All form fields have associated `<label>` elements (WCAG)
- Emojis are OK in code comments
