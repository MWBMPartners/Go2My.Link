# 📁 `.claude/` — Portable Claude Context for Go2My.Link

This directory is **committed to git on purpose** (see `.gitignore` → "Claude and
OpenAI context directories ARE tracked") so the project's Claude **memory,
context, and history travel to every machine and platform** that clones the repo
— macOS, Linux/Raspberry Pi, the Claude web app, or any other Claude Code host.

## What's here

| Path | What it is |
|---|---|
| `memory/MEMORY.md` | 🧠 **Portable project memory** — current state, file map, conventions, gotchas, issue ranges. The first thing to read. |
| `memory/patterns.md` | 🔧 Coding conventions (the no-shorthand rule, DB/frontend/a11y/i18n standards, emoji vocabulary). |
| `memory/audit-2026-06-04.md` | 📑 Memory: the deployment-readiness audit (issues #93–#120). |
| `memory/installer-schema-cuercode-2026-06.md` | 🗄️ Memory: installer + schema fixes + CueRCode (issues #121–#128). |
| `HISTORY.md` | 🕓 Chronological work log. |
| `ProjectBrief_Chat.claude` | 📋 Original product brief / vision (source of truth for intended scope). |
| `settings.local.json` | ⚙️ Machine-local Claude Code settings — **not tracked**, do not commit. |

The repo root `CLAUDE.md` is a thin entry point that **auto-loads on any machine**
and points Claude here.

## Relationship to the device-local auto-memory

On a given machine, Claude Code's auto-memory lives **outside the repo** at:

```
~/.claude/projects/<repo-path-with-slashes-as-dashes>/memory/
```

e.g. on the original dev Mac:
`~/.claude/projects/-Users-lance-manasse-Projects-Coding-and-Development-MWBM-Partners-Ltd-GitHub-GoToMyLink/memory/`

That path is **device-local and not synced**, so the files here in `.claude/memory/`
are the portable canonical copies. Keep them in step with the device-local memory.

## Using this on another platform / machine

1. **Any platform (always works):** clone the repo. The root `CLAUDE.md` auto-loads
   and references `.claude/memory/MEMORY.md` + `.claude/HISTORY.md`, so Claude has
   the context immediately. No extra setup needed.

2. **Optional, per-Mac — single source of truth + auto-memory:** symlink the
   device-local memory directory to this repo copy so the harness auto-memory IS
   the repo memory (and edits sync via git). Derive the path by replacing every
   `/` in the **absolute repo path** with `-`:

   ```bash
   REPO="$(pwd)"                      # run from the repo root
   HASH="$(printf '%s' "$REPO" | tr '/' '-')"
   DEST="$HOME/.claude/projects/$HASH"
   mkdir -p "$DEST"
   # back up any existing device memory, then point it at the repo copy:
   [ -e "$DEST/memory" ] && [ ! -L "$DEST/memory" ] && mv "$DEST/memory" "$DEST/memory.bak"
   ln -s "$REPO/.claude/memory" "$DEST/memory"
   ```

   (This mirrors the user's existing iCloud-symlink approach for the global
   `~/.claude/CLAUDE.md`. It's optional — step 1 already makes the context available.)

## Keeping it current (standing practice #3)

After each significant piece of work, update `memory/MEMORY.md` and `HISTORY.md`
here (and the matching device-local memory if not symlinked), then commit. The
`.openai/` directory is the equivalent for ChatGPT/OpenAI context.
