# 📁 `.claude/` — Portable Claude Context for Go2My.Link

This directory is **committed to git on purpose** (see `.gitignore` → "Claude and
OpenAI context directories ARE tracked") so the project's Claude **memory,
context, and history travel to every machine and platform** that clones the repo
— macOS, Linux/Raspberry Pi, the Claude web app, or any other Claude Code host.

## What's here

| Path | What it is |
|---|---|
| `HANDOFF.md` | 🤝 **The project's one session handoff** — what is in progress, what was tried and rejected, what to do next. Moved here from the repository root on 2026-09-21 (the root `HANDOFF.md` is now the dev-team plugin's ignored scratch card). |
| `memory/MEMORY.md` | 🧠 **Portable project memory** — current state, file map, conventions, gotchas, issue ranges. The first thing to read. |
| `memory/patterns.md` | 🔧 Coding conventions (the no-shorthand rule, DB/frontend/a11y/i18n standards, emoji vocabulary). |
| `memory/working-rules.md` | 🧭 Standing working rules for every assistant (handoff, planning/building/review, branch and pull-request rules, AI-service fallback). |
| `memory/audit-2026-06-04.md` | 📑 Memory: the deployment-readiness audit (issues #93–#120). |
| `memory/installer-schema-cuercode-2026-06.md` | 🗄️ Memory: installer + schema fixes + CueRCode (issues #121–#128). |
| `HISTORY.md` | 🕓 Chronological work log. |
| `ProjectBrief_Chat.claude` | 📋 Original product brief / vision (source of truth for intended scope). |
| `settings.local.json` | ⚙️ Machine-local Claude Code settings. `.gitignore` lists it as not-to-be-tracked (since 2026-07-18), **but it is in fact tracked**: it was deliberately added on 2026-07-20, after that rule, when recovered settings were restored. It contains file paths that include the owner's real name. Whether to stop tracking it is a suggestion raised 2026-09-21; until then, do not add anything machine-specific or secret to it. |

The repo root `CLAUDE.md` is a thin entry point that **auto-loads on any machine**
and points Claude here.

## Relationship to the device-local auto-memory

On a given machine, Claude Code's auto-memory lives **outside the repo** at the path below, where the
repository path has every character that is not a letter or digit turned into a dash:

```
~/.claude/projects/<repo-path-with-slashes-as-dashes>/memory/
```

e.g. on a Mac, for a clone at `/Users/<you>/Projects/Go2My.Link`:
`~/.claude/projects/-Users-<you>-Projects-Go2My-Link/memory/`

That path is **device-local and not synced**, so the files here in `.claude/memory/`
are the portable canonical copies. Keep them in step with the device-local memory.

## Using this on another platform / machine

1. **Any platform (always works):** clone the repo. The root `CLAUDE.md` auto-loads
   and references `.claude/memory/MEMORY.md` + `.claude/HISTORY.md`, so Claude has
   the context immediately. No extra setup needed.

2. **Optional, per-Mac — single source of truth + auto-memory:** symlink the
   device-local memory directory to this repo copy so the harness auto-memory IS
   the repo memory (and edits sync via git). Derive the path by turning every
   character of the **absolute repo path** that is not a letter or digit into `-`
   (slashes, dots, spaces, `&` and so on — replacing only `/` gives a folder name
   Claude Code never reads when the path contains spaces or `&`):

   ```bash
   REPO="$(pwd)"                      # run from the repo root
   HASH="$(printf '%s' "$REPO" | sed 's/[^A-Za-z0-9]/-/g')"
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
here (and the matching device-local memory if not symlinked), then run
`sh scripts/sync-ai-context.sh`, commit, and push to the working branch.

## The Codex copy (`.OpenAI/`)

Codex (OpenAI's coding assistant) reads the root `AGENTS.md`, which points it at
`.OpenAI/`. Three things in `.OpenAI/` are a **generated copy** of the Claude side
(the folder's README, `.gitkeep` and an old chat log are kept by hand): `CONTEXT.md` is the root `CLAUDE.md`, `memory/` is this
folder's `memory/`, and `HISTORY.md` is this folder's `HISTORY.md`. Run
`sh scripts/sync-ai-context.sh` after editing anything here; the GitHub check
`.github/workflows/ai-context.yml` fails if the copy is out of step (a warning
only — `alpha` has no required checks, so it cannot block a merge by itself). Never edit
the copies directly — the next sync overwrites them.

The standing working rules for every assistant are in
[`memory/working-rules.md`](memory/working-rules.md).
