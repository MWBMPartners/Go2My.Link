# GoToMyLink — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial repository setup with README.md (Phase 0)
- Full `web/` directory structure for all 3 components (Phase 0.1)
- Server-wide and per-component `auth_creds.php` templates with direct-access guards (Phase 0.1)
- Overhauled `.gitignore` for PHP/MySQL project on macOS/Windows with VS Code (Phase 0.2)
- Documentation framework: README.md, CHANGELOG.md, PROJECT_STATUS.md, DEV_NOTES.md (Phase 0.3)
- Architecture, database, API, and deployment documentation stubs (Phase 0.3)
- GitHub infrastructure: 72 issues, 11 milestones, project board, labels (Phase 0.4)
- Per-component README.md and CHANGELOG.md files (Phase 0.1)
- Full documentation stubs: docs/ARCHITECTURE.md, docs/DATABASE.md, docs/API.md, docs/DEPLOYMENT.md (Phase 0.3)
- GitHub Actions: `php-lint.yml` (PHP syntax check on push), `sftp-deploy.yml` (manual SFTP deploy, disabled) (Phase 0.4)
- Dependabot configuration for GitHub Actions monitoring (Phase 0.4)
- Branch protection on `main` (block force push and deletion) (Phase 0.4)
- Secret scanning and push protection enabled (Phase 0.4)
- "Coming Soon" landing pages for all 3 domains with branded styling and email capture (Phase 0.5)
- `.htaccess` foundation for all 3 components: HTTPS enforcement, security headers, private directory blocking, clean URLs, compression, caching (Phase 0.6)
- Component-specific URL routing: `?route=` (go2my.link), `?code=` (g2my.link), `?slug=` (lnks.page) (Phase 0.6)
- Brand guidelines document (assets/BrandKit/BRAND_GUIDELINES.md) with colour palette, typography, and logo usage rules (Phase 0.7)
- New database schema `mwtools_Go2MyLink` — 30 tables across InnoDB with utf8mb4 (Phase 1.1–1.3)
  - Core: tblSettings (merged dictionary+values), tblOrganisations, tblUsers (Argon2id, 2FA, PassKey), tblUserSocialLogins, tblUserPassKeys, tblUserSessions, tblOrgDomains, tblOrgShortDomains
  - Short URLs: tblShortURLs (enhanced), tblCategories, tblTags, tblShortURLTags, tblShortURLSchedules, tblShortURLDeviceRedirects, tblShortURLGeoRedirects, tblShortURLAgeGates
  - Analytics: tblActivityLog (structured geo/UA), tblErrorLog (PHP errors with backtrace)
  - API: tblAPIKeys, tblAPIRequestLog
  - LinksPage: tblLinksPageTemplates, tblLinksPages, tblLinksPageItems
  - Payments: tblSubscriptionTiers, tblSubscriptions, tblPayments, tblPaymentDiscounts
  - Legal: tblConsentRecords, tblDataDeletionRequests
  - Translation: tblLanguages, tblTranslations
- 3 redesigned stored procedures: sp_lookupShortURL, sp_logActivity, sp_generateShortCode (Phase 1.4)
- 6 data migration scripts for existing MWlink data (orgs, users, categories, short URLs, settings, activity log) (Phase 1.5)
- Seed data: subscription tiers (Free/Basic/Premium/Enterprise), default org, 25 platform settings, 5 LinksPage templates, 10 languages (Phase 1.6)
- Admin subdomain structure: `web/Go2My.Link/_admin/public_html/` for admin.go2my.link

### Changed

- Renamed directory `web/GoToMy.Link` to `web/Go2My.Link` to match actual domain name
- Removed tblQRCodes schema and migration — QR codes will be a separate first-party service with future integration via `hasQRCodes` feature flag in tblSubscriptionTiers
- Updated all documentation, .htaccess, and .gitignore references from GoToMy.Link to Go2My.Link
- Migration scripts reduced from 7 to 6 (QR codes migration removed)
- Database schema reduced from 31 to 30 tables (tblQRCodes removed)
