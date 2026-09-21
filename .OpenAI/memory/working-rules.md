# 🧭 Standing working rules — Go2My.Link

> **Who this is for:** any AI coding assistant working on this repository (Claude Code, Codex,
> or whatever comes next) and any person working alongside them.
> **Why it lives in the repository:** the owner keeps the same rules machine-wide in
> `~/.claude/CLAUDE.md` (which `~/.codex/AGENTS.md` links to), but that file only exists on the
> owner's own computers. This copy travels with the code, so the rules still apply on a fresh
> clone, a cloud session, or another tool.
> **Last revised:** 2026-09-21, from the owner's instructions that day, and corrected the same day
> after an independent review found a wrong claim about the dev-team plugin and several sub-rules
> that had been left out. Nothing that was already a standing rule has been removed — the older
> project rules are listed at the end.
> **Codex sees this file** through the `.OpenAI/memory/` copy, refreshed by
> `scripts/sync-ai-context.sh`. Edit this file, never the copy.

---

## 1. 🗣️ Plain, everyday English — in everything

No technical jargon when feeding back or explaining, even to a technical reader. Jargon confuses
capable developers too. Write the way you would explain something to a capable colleague who does
not work on this system: ordinary words, short sentences, the "why" as well as the "what", and a
plain explanation the first time a technical name is unavoidable.

This covers chat replies, progress updates, code comments, commit messages, issue and pull request
text, every `.md` file, the in-app help, and any text a user sees. It changes how work is
*explained*, never how careful the work is. The full rule, with examples, is in
[patterns.md → Plain, Everyday English](patterns.md).

When reporting, say plainly what is finished, what is not, what was not checked, and what went
wrong.

## 2. 👤 Never write the owner's real name

Use the GitHub username **`Salem874`** everywhere: commit messages, issue and pull request text,
code comments, documentation, handoff notes, anything sent to an outside service. A private
repository can become public, and git history cannot be quietly corrected afterwards.

If the real name has already been written down somewhere, **say so plainly** rather than quietly
correcting it. For commits that have already been pushed, fixing it means rewriting history — that is
a deliberate decision for the owner, never done on the quiet.

## 3. 🤝 Keep the handoff document current — as you go

The one handoff for this project is **[`.claude/HANDOFF.md`](../../.claude/HANDOFF.md)**. There is exactly one.
Do not create a second copy anywhere else "for convenience" — two copies drift apart and then nobody
knows which is true.

*Why it lives in `.claude/` and not at the repository root (moved 2026-09-21, as the owner asked):*
the dev-team plugin writes its own short "resume card" at `HANDOFF.md` in the repository **root**,
replacing whatever is there, and commits it during its runs. With the project's handoff at the root,
every plugin run would have wiped it. Now the root `HANDOFF.md` is listed in `.gitignore`, so it is
only the plugin's scratch file: an ordinary `git add` will not pick it up (only a forced `git add -f`
would — never do that for it), and the plugin never writes anything under `.claude/`. Until this
change is merged, the root file is still tracked on `alpha`. **Never write the project handoff at the
root.**

Update it:

- after each piece of work, before starting the next;
- the moment something is learned that would change how somebody continues — a wrong assumption,
  a trap, a decision, an approach tried and rejected;
- before starting anything long-running, so an interruption in the middle loses nothing.

It must carry what a replacement needs: what is being attempted and why, what is established, which
files matter, **what was tried and rejected**, what is verified versus assumed, and what to do next.
Interruptions are never scheduled — whatever is written down at that moment is all the next session
gets.

## 4. 🧠 How to think, plan and build

- **Think hard first.** The owner's word for this is "ultrathink". Use the planning and
  orchestration features the tool offers (in Claude Code: workflows and agents) — the owner has
  opted in.
- **Deep analysis and deep planning use the strongest reasoning model, one agent at a time, in
  sequence — never several in parallel**, because each planning step should see what the one
  before it established. Today in Claude Code that is **Fable**; if Fable is unavailable, fall back
  to **Opus**, say so, and **try Fable again on every later planning run** (limits reset).
- **Building uses the cheapest model that can do the job well:** Sonnet or Haiku, whichever fits
  (Haiku for purely mechanical edits). **Opus only when the build is genuinely complex.**
- **Checking is never done by a weaker model than the building** — in Claude Code, never below
  Opus.
- The aim is **GIRFT — Get It Right First Time**: spend effort where judgement is needed and save it
  where it is not. Verify before asserting; prefer designs that refuse when unsure over ones that
  guess.

## 5. 🔌 Use the helper plugins where they fit

Use helper plugins where they fit — to plan, build, review, document and **suggest** further fixes,
tweaks, enhancements and new features. For the dev-team plugin on this repository that currently means
analysis, reviews, audits and suggestions only (see below). Anything outside the task in hand is
raised as a suggestion (an issue, or a line in the report) — not built unless the owner says so.

**A helper must not create a second handoff or a competing plan.** Where a helper writes its own
handoff or plan files, switch that off where it can be, and keep those files out of the repository
(for example with `.gitignore`), so the project's own handoff stays the only one.

- **Where the dev-team plugin fits here — and where it does not.** Use it for analysis, reviews,
  audits and suggestions, and record what it finds as issues. Several of those skills **fix and commit
  by default**, so always give them their report-only setting:
  - review: `remediate=report` (its default is to fix);
  - security: `report-only` (its default is `fix=auto`; it may still create a branch of its own — see
    the "after any plugin run" point below);
  - docs: `mode=audit` (otherwise it guesses a writing mode);
  - ci-medic (`/dev-team-watch-prs`): `autofix=off` (its default, `autofix=safe`, pushes);
  - featurefind has no fix step, but it writes `FEATURES.md` and also updates the "Proposed-Features
    ledger" section of `PROJECT.md`, which exists here — check `git status` afterwards (below).

  **Do not use its code-writing skills on this repository for now** (orchestrator, iterate, autopilot,
  docs in a writing mode, review or security without the report-only setting, migrate, and ci-medic
  with fixes switched on). They commit on a branch of their
  own at every checkpoint — whatever `auto-commit` says — with no issue number, no type prefix and no
  cross-system review, and ci-medic pushes straight onto an open pull request's branch. Bringing that
  work onto the working branch safely took a procedure that kept failing review in new ways
  (2026-09-21, six review rounds), so building is done by the project's own agents instead (rule 4).
  If a code-writing skill is ever genuinely needed, raise it with the owner first. *(This is a
  judgement about where the plugin fits, recorded for the owner to confirm or overturn.)*
- **Its settings file: `.dev-team/config.yml`.** Read it first. It points the plugin at `alpha`
  (without it, the plugin would use GitHub's default branch, `main`), builds with Sonnet and checks
  with Opus, and switches off the plugin's extra per-task handoff rewrite and per-task commit. It
  cannot stop the plugin's checkpoint commits. (Until this change is merged, `alpha` itself does not
  have the file.)
- **Its handoff card cannot reach the project's handoff.** The plugin's card goes to the root
  `HANDOFF.md`, which git ignores (rule 3); the project's handoff is `.claude/HANDOFF.md`. If the
  plugin reports that `HANDOFF.md` is ignored, that is expected: never force it in with `git add -f`.
- **Deep analysis and planning stay outside the plugin.** The `economy` setting, which is the only one
  that builds with Sonnet, also moves the plugin's own reasoning agents from Fable down to Opus, and the
  plugin's "debate" step runs several planning agents at once. Both clash with rule 4. So do deep
  analysis and planning with Fable agents one after another, outside the plugin, and do not use the
  plugin's debate step for planning.
- **Before any plugin run, commit your own work and check `git status` is clean; after it, look at
  `git status` and `git log`.** Even its analysis skills may update its own notes files (`PROJECT.md`,
  `FEATURES.md`, `SECURITY.md`, `VERIFICATION.md`, `verdict.json`, `MIGRATION.md`,
  `.dev-team/autopilot.json`). Keep those changes deliberately, as a normal reviewed commit, or put a
  file back with `git restore -- <file>` — allowed by rule 12 **only if that file had no uncommitted
  changes before the run** (`git restore` puts back the whole file, and people edit `SECURITY.md` by
  hand); otherwise undo the plugin's part by hand. If the run made commits or a branch of its own, stop
  and tell the owner rather than pushing or deleting anything.
- **Autopilot cannot start a fresh run in this repository as it stands.** `.dev-team/autopilot.json`
  (tracked in git) records the June 2026 run as finished, and autopilot reads that file first: when it
  says "finished", autopilot only reports and stops.
- **The plugin's own plan and state files** — `PROJECT.md`, `FEATURES.md` and `SECURITY.md` at the
  repository root, and `.dev-team/autopilot.json` (its other files, such as `VERIFICATION.md` and
  `MIGRATION.md`, are not in the repository today) — are tracked in git from earlier runs. Under the
  rule above they belong outside the repository. Because they hold useful history (`SECURITY.md`
  especially), *how* to move them out — stop tracking them, or move the useful parts under `docs/` — is
  being confirmed with the owner (raised 2026-09-21). Until then, `.claude/HANDOFF.md` is the plan
  of record and those files are only the plugin's working notes. (The `.gitignore` comment that says
  "autopilot.json IS tracked" will be corrected once that is decided.)
- **Its safety guard is always on here, and can misfire.** The plugin's guard checks every shell
  command for pushes to the main branch whenever `.dev-team/autopilot.json` exists — and because that
  file is tracked, it exists in every session, run or no run. It has blocked a harmless command just
  because the command's text contained the words "push" and "main". If that happens, write the text to a
  file with the editor (in Claude Code, the Write tool — not a shell `echo` or heredoc, whose text the
  guard still sees) and run the file; never switch the guard off to get round it. (Removing the stale
  `autopilot.json`, part of the owner decision above, would stop the guard running outside real runs.)

## 6. 🔁 Cross-system code review, until a round comes back clean

Every change is reviewed by a **different** system from the one that built it: Claude Code's work
by Codex, Codex's work by Claude Code (a fresh agent that did not build it). The reviewer must never
be the builder.

- Before a commit: `codex review --uncommitted`. For the whole working branch:
  `codex review --base alpha`. A focused review with custom instructions:
  `codex exec -s read-only "<what to check>"`.
- **The loop:** run the review, read every finding, fix the real ones automatically, run the review
  again, and **stop only when a round finds no real problems.** Record how many rounds it took (in
  the commit message and the handoff).
- A finding you are sure is wrong does not keep the loop going and is never "fixed" just to quiet
  the reviewer — write down why it is wrong and move on.
- If the usual reviewer is unavailable, see rule 13: say so, use the most independent reviewer
  available, name it, and treat the change as not fully reviewed until the usual reviewer has
  caught up.

## 7. ✅ Steps after each piece of work

One piece of work = one GitHub issue and one commit. When it is finished:

1. **Verify it yourself.** Run the checks and read their real exit codes — never pipe a check into
   `grep` or `tail` and then rely on `&&`, because the pipe hides the real result. PHP 8.5 is
   installed on the owner's Mac through Homebrew (found 2026-09-21; older notes say it is not), but CI
   uses PHP 8.4 — the lowest version the live site is meant to run — so run the tests in Docker with
   that version:
   `docker run --rm -v "$PWD":/app -w /app php:8.4-cli php tests/run.php`. Format only the files
   you touched (the tree has older formatting drift, tracked in #153 — do not reformat unrelated
   files). Read the diff once with security in mind.
2. **Review loop** (rule 6).
3. **Update the Claude memory and context** in `.claude/` (`memory/MEMORY.md`, the topic files,
   `HISTORY.md`, and the root `CLAUDE.md` when a house rule changes).
4. **Update the Codex memory and context** in `.OpenAI/` by running
   `sh scripts/sync-ai-context.sh` (it copies the files across; never edit the copies by hand), and
   update the root `AGENTS.md` if a rule it summarises has changed.
5. **Update the handoff** ([`.claude/HANDOFF.md`](../../.claude/HANDOFF.md)).
6. **Commit and push to the working branch** — the one branch that will later be merged into
   `alpha` by a single pull request. The commit title starts with its type (`feat:`, `fix:`,
   `docs:` …), the message ends with any closing lines the project or tool requires (for example the
   co-author line), and it uses `Salem874`, never a real name. *(Changed 2026-09-21: this used to say "commit,
   don't push — the owner pushes manually". The owner now wants each piece pushed to the working
   branch.)*
7. **Update that task's GitHub issue individually**: what landed and the commit; keep it on project
   board #4 (say so if that fails rather than skipping quietly); close it once the work has merged;
   open follow-up issues for anything found and not done.
8. **Show the progress table** (rule 11).

## 8. 📚 The documentation sweep is a standing task

After each real body of work, before its pull request is opened, update thoroughly: every `.md`
file; the in-app help (the Help section under `web/Go2My.Link/public_html/pages/help/` and its
translation strings); and the memory and context files for every assistant (`.claude/`, `.OpenAI/`,
`CLAUDE.md`, `AGENTS.md`).

This project has a public API, so also update the OpenAPI description
(`web/Go2My.Link/public_html/api/openapi.yaml`) whenever the API changes, **and check it against the
code in every documentation sweep** even when nothing seems to have changed (the 2026-09-07 sweep found
`docs/API.md` describing routes that did not exist). It is published two ways,
both self-hosted as plain files so they work on shared hosting with no Docker or other container
software: **Redoc** (a readable manual) at `/api/docs/` and **Swagger UI** (a try-it-out console) at
`/api/docs/swagger/`. Keep both working.

## 9. 🔀 Order and bundle the work sensibly

The order of tasks in a brief is a suggestion. Reorder and combine where that is more efficient —
one documentation pass after several related fixes, one review round over two small changes — as
long as nothing is dropped and the progress table shows what was bundled.

## 10. 🤖 Work autonomously; raise every question up front

Do all the queued work without pausing unless a decision or approval is genuinely needed from the
owner. When it is, ask **at the start**, in one numbered block: what is being asked, why it matters,
the recommended answer, and what will be done meanwhile — worded as simply as possible. Then carry on
with everything that is not blocked. A decision the owner has already taken is final.

## 11. 📊 Progress tables

Give frequent updates as a table of the queued tasks, one row per task:

| # | Task | Issue | Status | Notes |
| --- | --- | --- | --- | --- |
| 1 | Plain-English name | #nnn | Done — pushed `abc1234` | review: 2 rounds, last clean |

Status words: **Queued · In progress · In review · Blocked (say on what) · Done (say the commit) ·
Dropped (say why)**. Show it at least after each finished piece and whenever the queue changes.

## 12. 🌿 One working branch, one pull request, never stacked

- All work goes to **one working branch** cut from `alpha`. Commit and push each finished piece
  there.
- **One pull request to `alpha`, opened later, when the owner says so.** Never open a second pull
  request against the same base while one is open, and never build a pull request on top of another
  pull request's branch — two open pull requests race each other: the moment one merges, the other is
  built on an out-of-date copy of `alpha` and may carry a conflict that neither one's checks could
  see.
- When several pull requests are combined into one, and only once the combining pull request is
  open: close each original with a comment naming its replacement, and delete that original's
  branch after checking its changes really are on the combining branch — only in this project's own
  repository, and never a release branch or the combining branch. Closing an automated update (a
  Dependabot pull request, for example) can stop it being offered again, so if the combining pull
  request is abandoned, reopen the originals.
- Branch flow: `alpha → beta → release-candidate → main`. **Never merge `main` down** into the other
  branches. *Why:* in July 2026 a stale local copy of `main` still held the old legacy engine and the
  leaked-credential file, and merging it down would have brought them back. Today's `main` no longer
  holds either (checked 2026-09-21: its only commits missing from `alpha` are Dependabot and CI
  changes), but the flow only works in one direction, so the rule stands.
- **Never force-push, hard-reset, or change a remote without an explicit instruction**, and never
  push directly to `alpha`, `beta`, `release-candidate` or `main` without one — work reaches them only
  by merging a pull request. (Branch protection often blocks force-pushes but not a plain push, so do
  not rely on GitHub to stop a mistake.)
- **Never run any other destructive git operation without an explicit go-ahead** — deleting a branch,
  discarding uncommitted work (`git checkout -- .`, `git clean`), rebasing, or rewriting history. The
  standing exceptions:
  - deleting the branches of replaced pull requests, as described above;
  - deleting a local branch whose GitHub copy has already been merged and deleted (the repository
    re-alignment step) — with `git branch -d` only, which refuses if the branch holds unmerged work,
    never `git branch -D` (this project has lost work to an unpushed branch before);
  - putting back uncommitted changes that a dev-team skill made to its own notes files
    (`git restore -- <file>`, rule 5) — only when that file had no uncommitted changes before the
    plugin ran, because `git restore` puts back the whole file.

## 13. 🔄 When one AI service runs out: hand over, and hand back

This rule deliberately names no particular tool. Today it means Claude Code and Codex, and within one
tool it means falling back from one model or agent to another. Tomorrow it may mean something else.

- **When to hand over:** the service or agent refuses the work — out of credit, a spend cap, rate
  limited, a quota used up, or an outage — and retrying once has already failed for a reason that
  will not fix itself. Not merely because something is slow, or because another system might do it
  better — that is a different decision.
- **Only if the work survives the move:** hand over only when enough context can go with it — what is
  being attempted, what is established, which files matter, what was tried and rejected, and how the
  result will be checked. That is only possible because the handoff document (rule 3) is kept up to
  the minute. If the context cannot go with it, say plainly that the work is blocked and why.
- **Why this is reasonably safe:** every change is already checked by a different system from the one
  that built it (rule 6), which catches differences in habit between services. If that review is
  skipped, the safety argument disappears with it.
- **Go back promptly.** A fallback is a detour. Return at the next natural break, and **always try the
  usual service first at the start of each new run**, even if it failed last time — limits reset.
- **When the usual service is back, run a FULL review of everything done while it was away** — the
  whole body of work, not just the last change — and do this often rather than once at the end. It is
  a real review to the usual standard, **checking each point against the code rather than taking the
  reviewer's word**.
- **A review never changes hands silently.** If the usual reviewer cannot run, say so in the report and
  the commit message, use the most independent reviewer available (a different model, or a fresh agent
  with no memory of building the thing) and name it, and include the change in the catch-up review.
- **Write down every fallback** — in the commit message, the handoff and the progress report — so the
  catch-up review knows what to look at and a pattern of repeated fallbacks gets noticed.

---

## 📌 Older project standing rules (still in force)

These were standing rules before 2026-09-21 and are unchanged:

- **GitHub project board #4** (MWBMPartners organisation) is kept current; every piece of work has an
  issue; issues are closed with commit or pull request references and evidence.
- **No shorthand notation** in any language (no ternaries, no alternative PHP syntax, full braces) —
  see [patterns.md](patterns.md).
- **Lint everything** and fix every error, warning and recommendation.
- **MySQLi prepared statements only**; InnoDB with `utf8mb4_unicode_ci`; sensitive values encrypted.
- **Never write or commit real credentials.**
- **WCAG 2.1 AA** built in (issues use the `compliance` label); **every UI string through `__('key')`**;
  **dark and light mode** via `data-bs-theme`.
- **No `.php` (or any language extension) in any web address** — links, form targets, redirects and
  background requests use the clean address. Where a project has an automated check suite, add a
  check for this; this project does not have one yet — tracked in **#203**.
- **Comment code properly in every language** — explain why, record what was tried and rejected, say
  what the code cannot do. File headers carry the path, a description, and the usual authorship and
  licence lines. JSON has no comment syntax, so never put `//` in a `.json` file: explain it in its
  schema (`description` on every property, `$comment` for maintainers). Where inline notes are really
  needed, use a `.jsonc` file or a `_comment` key by convention, and say which is being done.
- **Every JSON and XML file the project produces or consumes gets a schema** kept beside it, and the
  validation is wired into something that runs.
- **`.gitignore` is kept current** as new tools are introduced.
- **CI jobs run on `ubuntu-latest`** and actions are pinned by commit — see patterns.md.
- **`.md` files use the emoji vocabulary** in patterns.md.
