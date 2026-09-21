# 📁 `.OpenAI/` — Codex context for Go2My.Link

This folder is the **Codex** side of the project notes. Codex finds it through the root
[`AGENTS.md`](../AGENTS.md), which Codex reads automatically.

## 🔄 Almost everything here is generated — do not edit it

| File | Copied from | By |
| --- | --- | --- |
| `CONTEXT.md` | the root `CLAUDE.md` (plus a two-line "do not edit" header) | `scripts/sync-ai-context.sh` |
| `memory/*.md` | `.claude/memory/*.md` (exact copies) | `scripts/sync-ai-context.sh` |
| `HISTORY.md` | `.claude/HISTORY.md` (exact copy) | `scripts/sync-ai-context.sh` |

**To change what Codex sees, edit the Claude side** (`CLAUDE.md` or `.claude/`) and then run:

```sh
sh scripts/sync-ai-context.sh
```

The GitHub check in `.github/workflows/ai-context.yml` runs `sh scripts/sync-ai-context.sh --check`
and fails if this folder is out of step, so a forgotten sync is caught rather than silently left to
go stale.

**Why copy at all, instead of pointing Codex straight at `.claude/`?** The owner's standing rule is
that each assistant has its own context folder, kept identical. The copy makes that true without
anyone having to remember to do it by hand — a sister project that relied on hand-copying found its
two copies had drifted badly.

## 📄 Files that are NOT generated

- `README.md` — this file.
- `logo_design_chat.gpt` — an older ChatGPT logo-design conversation link, kept for the record.
- `.gitkeep` — keeps the folder present in git.

The folder was renamed from `.openai/` to `.OpenAI/` on 2026-09-21 to match the owner's naming in
their other projects. On a Mac the two spellings are the same folder; on Linux they are not, so
always use `.OpenAI/`.
