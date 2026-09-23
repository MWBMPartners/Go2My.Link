# 🤖 AGENTS.md — how Codex works in this repository

This file is for **Codex** (OpenAI's coding assistant) and any other tool that reads `AGENTS.md`.
Codex reads `AGENTS.md` files automatically — the machine-wide `~/.codex/AGENTS.md`, then this one
at the repository root — and nothing else unless asked. Claude Code gets the same project context
through the root `CLAUDE.md` and `.claude/`. **Both tools are meant to work by the same rules.**

Added 2026-09-21. Before this, Codex had no project context here at all: the `.OpenAI/` folder held
only an old chat log.

## 📋 Read these before doing any work

1. **[`.github/HANDOFF.md`](.github/HANDOFF.md)** — the one session handoff (read it where it is; it is
   deliberately not copied into `.OpenAI/`, because a second copy would drift). Its "START HERE" section says what is in
   progress, what was tried and rejected, and what to do next. Start here.
2. **[`.OpenAI/CONTEXT.md`](.OpenAI/CONTEXT.md)** — the project entry point (a generated copy of the
   root `CLAUDE.md`).
3. **[`.OpenAI/memory/working-rules.md`](.OpenAI/memory/working-rules.md)** — the full standing
   working rules, with the reasons. The list below is the short form.
4. **[`.OpenAI/memory/MEMORY.md`](.OpenAI/memory/MEMORY.md)** — project memory: current state, file
   map, traps. **[`.OpenAI/memory/patterns.md`](.OpenAI/memory/patterns.md)** — coding conventions,
   including the strict no-shorthand rule.

`.OpenAI/CONTEXT.md`, `.OpenAI/HISTORY.md` and everything in `.OpenAI/memory/` are **copied from the
Claude side by `scripts/sync-ai-context.sh`** (the folder's README, `.gitkeep` and an old chat log are
kept by hand). Never edit those copies — edit `CLAUDE.md` or `.claude/`, then run
`sh scripts/sync-ai-context.sh`. A GitHub check (`.github/workflows/ai-context.yml`) fails if the
copy is out of step (it warns; `alpha` has no required checks, so it cannot block a merge by itself).

## 🧭 The standing rules, in brief

- **Plain, everyday English, always.** No jargon in anything written or said — chat, comments,
  commits, issues, docs, in-app text — even to a technical reader. Explain a technical name the
  first time it is unavoidable.
- **Never write the owner's real name.** Use the GitHub username `Salem874`, everywhere.
- **One handoff: [`.github/HANDOFF.md`](.github/HANDOFF.md).** Update it as the work happens — after
  each piece of work, the moment something important is learned, and before anything long-running.
  Never create a second one, and never write it at the repository root: the root `HANDOFF.md` is the
  dev-team plugin's scratch card and is ignored by git.
- **Deep analysis and planning: Opus, one agent after another** (changed 2026-09-23; it used to be
  Fable first). Building: the cheapest capable model (Sonnet, or Haiku for mechanical edits; Opus when
  genuinely complex). Checking is never done by a weaker model than the building. Fact-gathering may
  run in parallel; judgements may not.
- **Use helper plugins where they fit**, including to suggest further fixes, tweaks, enhancements and
  new features — raised as suggestions, not built unless the owner says so. **The Claude Code dev-team
  plugin stays in use here (decision 18, #243).** Where its own habits differ from this project's
  rules, this project's rules win. Its work never lands on the working branch directly and it never
  pushes: a code-writing skill (orchestrator, iterate, autopilot, migrate) runs on a branch or worktree
  of the plugin's own, and any result worth keeping is brought across afterwards as one ordinary
  GitHub issue and one commit, through the same cross-system review loop as anything else. Review,
  security and docs never write product code here at all — they run only in their report-only setting
  (`remediate=report`, `report-only`, `mode=audit`); ci-medic (`/dev-team-watch-prs`) only with
  `autofix=off` (working-rules.md, rule 5).
- **Every change is reviewed by the other system until a round finds no real problems.** When Codex
  builds something, Claude Code reviews it with a fresh agent; when Claude Code builds it, Codex
  reviews it (`codex review --uncommitted` before a commit, `codex review --base alpha` for the whole
  branch, `codex exec -s read-only "<what to check>"` for a focused review). **On this machine every
  Codex command needs `-c model="gpt-6-astra"`, or Codex refuses with "The 'gpt-6-sol' model is not
  supported when using Codex with a ChatGPT account".** Fix the real findings,
  review again, repeat. A finding you are sure is wrong is recorded with the reason, never "fixed" to
  quiet the reviewer. Record the number of rounds.
- **After each finished piece of work:** verify it yourself (read the real exit codes; PHP runs in
  Docker with the same version as CI — `docker run --rm -v "$PWD":/app -w /app php:8.4-cli php
  tests/run.php`; format only the files you touched; one security read of the diff); the review
  loop; update `.claude/` and then run `sh scripts/sync-ai-context.sh` for `.OpenAI/`; update
  `.github/HANDOFF.md`; **commit and push to the working branch**; update that task's GitHub issue; show a
  progress table.
- **One working branch, one pull request to `alpha` opened later when the owner says so, never
  stacked.** Never force-push, hard-reset or change a remote without an explicit instruction, and no
  other destructive git operation (deleting branches, discarding uncommitted work, rewriting history)
  without a go-ahead. Never push directly to `alpha`, `beta`, `release-candidate` or `main` without an
  explicit instruction. Never merge `main` down into the other branches.
- **Work autonomously; ask every question up front** in one numbered block, then carry on with
  everything not blocked. A decision the owner has taken is final.
- **Reorder and bundle tasks** where that is more efficient, as long as nothing is dropped.
- **Progress tables:** frequent updates as a table of the queued tasks and the status of each.
- **The documentation sweep is a standing task** after each real body of work: every `.md` file, the
  in-app Help section, the assistants' notes, and the API description (`openapi.yaml`, shown by Redoc
  at `/api/docs/` and Swagger UI at `/api/docs/swagger/`, both plain files that work on shared
  hosting).
- **If an AI service or agent runs out — credit, rate limit, outage — hand the work to another
  suitable one** only if the context survives the move (which is why the handoff must be current);
  switch back at the next natural break; try the usual one first at the start of every run; when it
  is back, run a full review of everything done while it was away; a review never changes hands
  silently; write every fallback down.

## ⚠️ Conventions that catch people out

- **No shorthand in any language**: no ternaries (`a ? b : c`), no Elvis (`?:`), no PHP alternative
  syntax (`if: … endif;`), no short tags, no braceless `if`, no one-line arrow functions. Use full
  `if/else` blocks with braces. `??` only when both sides are simple values.
- **MySQLi prepared statements only** — never PDO, never string-built SQL.
- **Every UI string goes through `__('key')`** and is seeded in a translation seed file under
  `web/_sql/seeds/`.
- **No `.php` in any web address** (links, form targets, redirects, background requests).
- **Never pipe a check into `grep` or `tail` and then rely on `&&`** — the pipe hides the real exit
  code.
- **The target host is Dreamhost shared hosting**: no Composer, no background daemons, no cron you
  can assume, no Docker.
