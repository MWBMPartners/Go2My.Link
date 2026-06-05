# CLAUDE.md — Go2My.Link

> Project context entry point. This file auto-loads on **any** machine/platform
> that opens the repo, so the project's Claude memory/history travels via git.
> (Personal/global preferences still come from `~/.claude/CLAUDE.md` where present.)

## Read these first

- 🧠 **[.claude/memory/MEMORY.md](.claude/memory/MEMORY.md)** — current state, file map, gotchas, open-issue ranges. **Start here.**
- 🔧 **[.claude/memory/patterns.md](.claude/memory/patterns.md)** — coding conventions (esp. the strict **no-shorthand** rule for all languages).
- 🕓 **[.claude/HISTORY.md](.claude/HISTORY.md)** — chronological work log.
- 📋 **[.claude/ProjectBrief_Chat.claude](.claude/ProjectBrief_Chat.claude)** — product vision / intended scope.
- 📁 **[.claude/README.md](.claude/README.md)** — how this portable context is structured and synced.

## Project at a glance

- **Product:** Go2My.Link — URL shortener by MWBM Partners Ltd (MWservices). 3 components/domains: **go2my.link** (main, A), **g2my.link** (shortlinks, B), **lnks.page** (LinksPage, C).
- **Stack:** PHP 8.4+/8.5+, MySQLi only (prepared statements), Bootstrap 5.3, vanilla JS. **Dreamhost shared hosting** (no Composer/CLI assumed).
- **State (2026-06-05):** A built to v1.0.0-rc; B core redirect built; **C not built** (Phase 8). A+B launchable after the `audit/launch-hardening-2026-06-04` branch fixes.
- **GitHub Project #4** (MWBMPartners org) — keep it maintained; create issues for all work and close with commit refs.

## Non-negotiable house rules (see patterns.md for the full list)

- 🚫 **No shorthand notation** in any language: no PHP alternative syntax / ternary / Elvis / short-echo / short-open-tag; no JS ternary / `||` default / braceless ifs / one-line arrows. Use full `if/else` with braces (Allman). `??` only when both sides are simple values.
- 🗄️ **MySQLi only**, prepared statements for every query; InnoDB + utf8mb4_unicode_ci; sensitive values AES-256-GCM encrypted. DB credentials live only in `web/_auth_keys/auth_creds.php` (never committed).
- ♿ **WCAG 2.1 AA** built-in (labelled as `compliance` in issues — there is no `accessibility` label). 🌍 All UI strings via `__('key')`. 🌓 Dark/light via `data-bs-theme`.
- 🔍 **Lint everything**; fix all errors/warnings. 📝 `.md` files use the emoji vocabulary in patterns.md.
- 💾 **Commit (don't push)** after each piece of work — the user pushes manually. 🔒 Never write/commit real credentials; never push or run destructive git ops without explicit go-ahead.
