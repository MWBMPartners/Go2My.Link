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
- Brand guidelines document (.BrandKit/BRAND_GUIDELINES.md) with colour palette, typography, and logo usage rules (Phase 0.7)
