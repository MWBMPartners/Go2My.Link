# GoToMyLink — Project Status

> Last updated: 2026-02-22

## Current Phase

**Phase 1: Database Schema Design & Migration** — Complete

## Build Progress

| Phase | Milestone | Status | Issues |
| --- | --- | --- | --- |
| Phase 0 | v0.1.0 — Scaffolding | **Complete** | 7 issues |
| Phase 1 | v0.2.0 — Database | **Complete** | 5 issues |
| Phase 2 | v0.3.0 — PHP Framework | Not Started | 11 issues |
| Phase 3 | v0.4.0 — Redirect Engine | Not Started | 4 issues |
| Phase 4 | v0.5.0 — Main Website | Not Started | 5 issues |
| Phase 5 | v0.6.0 — User System | Not Started | 9 issues |
| Phase 6 | v0.7.0 — API & Analytics | Not Started | 7 issues |
| Phase 7 | v0.8.0 — LinksPage | Not Started | 6 issues |
| Phase 8 | v0.9.0 — Advanced Redirects | Not Started | 6 issues |
| Phase 9 | v0.10.0 — Payments | Not Started | 4 issues |
| Phase 10 | v1.0.0 — Legal & Launch | Not Started | 8 issues |

## Completed Milestones

### v0.2.0 — Database (Phase 1)

- [x] 1.1 — Core tables: tblSettings, tblSubscriptionTiers, tblOrganisations, tblOrgDomains, tblOrgShortDomains, tblUsers, tblUserSocialLogins, tblUserPassKeys, tblUserSessions
- [x] 1.2 — Short URL tables: tblShortURLs, tblCategories, tblTags, tblShortURLTags, tblShortURLSchedules, tblShortURLDeviceRedirects, tblShortURLGeoRedirects, tblShortURLAgeGates
- [x] 1.3 — Extended tables: tblActivityLog, tblErrorLog, tblAPIKeys, tblAPIRequestLog, tblLinksPageTemplates, tblLinksPages, tblLinksPageItems, tblSubscriptions, tblPayments, tblPaymentDiscounts, tblConsentRecords, tblDataDeletionRequests, tblLanguages, tblTranslations
- [x] 1.4 — Stored procedures: sp_lookupShortURL, sp_logActivity, sp_generateShortCode
- [x] 1.5 — Migration scripts (6 scripts for orgs, users, categories, URLs, settings, activity log)
- [x] 1.6 — Seed data (subscription tiers, default org, settings, LinksPage templates, languages)

### v0.1.0 — Scaffolding (Phase 0)

- [x] 0.1 — Full `web/` directory structure for all 3 components
- [x] 0.1 — Server-wide and per-component `auth_creds.php` templates
- [x] 0.1 — Per-component README.md and CHANGELOG.md
- [x] 0.2 — Comprehensive `.gitignore` for PHP/MySQL project
- [x] 0.3 — Documentation framework (README, CHANGELOG, PROJECT_STATUS, DEV_NOTES)
- [x] 0.3 — docs/ directory with ARCHITECTURE.md, DATABASE.md, API.md, DEPLOYMENT.md
- [x] 0.4 — GitHub infrastructure (72 issues, 11 milestones, project board, 38 labels)
- [x] 0.4 — GitHub Actions (php-lint.yml, sftp-deploy.yml)
- [x] 0.4 — Branch protection, secret scanning, Dependabot
- [x] 0.5 — "Coming Soon" landing pages for all 3 domains
- [x] 0.6 — .htaccess foundation with HTTPS, security headers, clean URLs, routing
- [x] 0.7 — Brand guidelines document and branding asset catalogue

## Current Blockers

None.

## Recent Decisions

- Accessibility (WCAG 2.1 AA) is a foundational requirement from Phase 2 onwards
- i18n infrastructure built into Phase 2; formal translations in Phase 10
- Interim Google/Bing/AI translation widget until formal translations are ready
- Branding/logo design included in Phase 0
- All passwords from existing database will be force-reset during migration (currently plaintext)
- `tblLicenses` (legacy NetPLAYER data) will NOT be migrated
- Branding directory is `assets/BrandKit/` (moved from `.BrandKit/`)
- New database uses InnoDB (replacing MyISAM) with proper FK constraints
- Settings merged into single table with scope hierarchy (Default > System > Organisation > User)
- Activity log migrated with batch approach (10K rows per batch) due to volume
- QR codes excluded from project — will be a separate first-party service with future integration
- Component A directory renamed from `GoToMy.Link` to `Go2My.Link` (domain name match)
- Admin dashboard separated to `admin.go2my.link` subdomain (`_admin/public_html/`)

## Next Up

**Phase 2: Core PHP Framework & Shared Infrastructure** — Build foundational PHP classes/functions shared by all 3 web properties: DB connection, settings, error handling, security, routing, accessibility helpers, and i18n infrastructure.

## Links

- [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)
- [Build Plan](.claude/plans/parsed-squishing-platypus.md)
