# GoToMyLink — Project Status

> Last updated: 2026-02-22

## Current Phase

**Phase 0: Project Scaffolding** — Complete

## Build Progress

| Phase | Milestone | Status | Issues |
| --- | --- | --- | --- |
| Phase 0 | v0.1.0 — Scaffolding | **Complete** | 7 issues |
| Phase 1 | v0.2.0 — Database | Not Started | 5 issues |
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
- Branding directory is `.BrandKit/` (not `_branding/` as originally planned)

## Next Up

**Phase 1: Database Schema Design & Migration** — Design new `mwtools_Go2MyLink` database (InnoDB, utf8mb4) with migration scripts for the existing 480 short URLs, 5 organisations, and 7 user accounts.

## Links

- [GitHub Project Board](https://github.com/orgs/MWBMPartners/projects/4)
- [Build Plan](.claude/plans/parsed-squishing-platypus.md)
