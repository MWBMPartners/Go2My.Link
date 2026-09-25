# 🤝 HANDOFF — Go2My.Link

> 📍 **Moved here from `.claude/HANDOFF.md` on 2026-09-22** (owner decision 17, #243) — the owner
> wanted the handoff at `.github/HANDOFF.md`, as in the sister project MeedyaDL. The note just
> below is kept as it was written, as the historical record of the *previous* move (from
> the repository root into `.claude/`, on 2026-09-21); it is not being corrected to describe
> this second move.

> 📍 **This file moved from the repository root to `.claude/HANDOFF.md` on 2026-09-21** (the owner
> asked for the handoff in `.claude/`). The root `HANDOFF.md` is now ignored by git: it is only the
> dev-team plugin's scratch "resume card", which the plugin overwrites and tries to commit during its
> runs. Never write the project handoff at the root.

> **Purpose:** durable pick-up point so any session (or a fresh start) can continue
> without re-deriving state. Companion to `docs/LAUNCH_PLAN_2026-07-09.md` (the full
> strategic plan) and `.claude/memory/MEMORY.md` (project memory).
> **Last updated:** 2026-09-25 05:50 (batch 2 done; Codex catch-up retry set for 09:42 — see "This session") · 2026-09-23 21:10 (everything pushed; safe restart point — see START HERE) · 2026-09-21/22 (the LinksPage and tiers programme) · earlier: 2026-09-07 (audit + docs sweep, merged as #201) · 2026-08-04 (`release-candidate` branch cut from `alpha`; four-tier Dependabot + dependency-backport workflow; per-user analytics #165) · earlier: 2026-07-22 (PR merges, Dependabot 3-tier, pricing) · 2026-07-19 (post-recovery conformance audit) · **Working branch:** `feat/2026-09-21-linkspage-and-sweep` (from `alpha`).
> **Status (2026-09-25):** a build programme is under way on the working branch, agreed with
> the owner after a market review of LinksPage against Linktree, Beacons and about twenty other
> "link in bio" services. The finished pieces are listed under START HERE; the next is batch 3
> (SX-196, SX-207, SX-204, SX-210). 677 unit tests and 230 database tests pass. The programme covers: making every tier feature editable in the
> database through a new admin screen (no hard-coded tiers), an environment-aware pricing switch,
> analytics kept for ever as daily counts with IP addresses removed after 90 days, the LinksPage
> features the market expects, and a queue of approved fixes. The owner's 45 decisions are GitHub
> issue #243; the plan is `.claude/programme/build-plan.json`; every item's plan is on its own
> issue. *(Earlier status, still true as background: the July recovery was verified, all 156 issues
> were checked against the code, and PHPStan is an enforced CI gate.)*

---

## ▶️ START HERE — everything a fresh session needs (2026-09-25, 05:50)

> **This section is written so a brand-new session, with no memory of the previous ones, can carry
> on.** Read it, then `.claude/programme/README.md`, then the `progress` section of
> `.claude/programme/build-plan.json`. Older dated sections below are history.

### 🔄 This session (2026-09-25, from about midnight)

Nothing happened between 21:10 on 23 September and this session (no commits, no GitHub activity).

**Done and pushed:** CX-01 (`79d6e11`), SX-198 (`64b3ca5`), SX-203 (`1412af2`) and SX-211
(`eee195b`) — batch 2 is complete — plus commits to the build tools and these notes (`9669b25`,
`2a26601`, `5740c07`, `95a0953`). See the Finished table below. SX-203's new check found one real
offender on its first run: the analytics page's CSV download link ended in `.php` (now the clean
address).

**The Codex catch-up review.** The first attempt, at **00:03**, was refused with a real credit
message: *"You've hit your usage limit … try again at 4:36 AM"*. (The message of `2a26601` says
"about 00:15"; that time is wrong, and a pushed message cannot be edited.) No Go2My.Link session had
used Codex since its last reset, so **another project on the same account used the allowance — it
is shared.** The catch-up **did start at 04:40**, in a separate, throwaway copy of the repository
(a git worktree — `git worktree list` shows it), but **ran out of credit again after about three
minutes**, while still reading the whole diff (*"try again at 9:41 AM"*). It produced no findings.
**A narrower retry is scheduled for 09:42**: product code only (`5340025`, `0255229`, `64b3ca5`,
`79d6e11`, and any later commit touching `web/` or `tests/`), with instructions to look at a few of
the 54 identical guard changes rather than all of them. The notes and build-tool commits stay owed.
Until it has finished, a lock file in the session's scratch folder makes the item reviewers skip
Codex. So **everything built tonight was reviewed only by the Claude stand-in** (a fresh Opus agent
that did not build the change), and every commit says so.
**When it finishes:** check every finding against the code; queue the real ones as fix items
*before* new build work; make sure the lock file is gone; remove the worktree with
`git worktree remove <path>` (never `--force` without looking at it first). If the session ended
before it ran, run it by hand (see "Codex, and what it is owed" below).

**Next in this session:** batch 3 — SX-196 and SX-207 in one workflow run, then SX-204 and SX-210.

**If this session is interrupted:** `git log` shows which items landed. Anything uncommitted in the
working copy belongs to the item that was being built — resume it with `"skipBuild": true` and a
`"startRound"` (see the programme README).

**Changed in the build tools tonight** (the lessons are under "Traps" below):
- `9669b25` — the reviewer now names Codex's model; a lock file replaces a fixed night-time "leave
  Codex alone" window; the finaliser adds its row to the Finished table; shorthand written out.
- `5740c07` — builders are told to keep comments short enough to be certainly true; every workflow
  step survives an agent that fails to report; the test script removes its database volume.

### 📍 Position in one paragraph

Everything is **committed and pushed**; the working copy is clean and the local branch matches
GitHub exactly. The working branch is **`feat/2026-09-21-linkspage-and-sweep`**, cut from `alpha`,
and `git log -1` is always the truth about the latest commit (each finished item adds one). There
is deliberately **no pull request yet** — one pull request to `alpha`, opened when the owner says
so. The finished pieces are listed below. The next items are batch 3: **SX-196**, **SX-207**,
**SX-204** and **SX-210**; `progress.next` in `.claude/programme/build-plan.json` says the same.

### 🗂️ Where everything lives

| What | Where |
| --- | --- |
| The owner's decisions (45 of them) — binding | GitHub issue **#243**, both comments |
| Each item's full build plan | The "Implementation plan" comment on that item's own issue |
| The machine-readable plan, build order and progress | `.claude/programme/build-plan.json` (schema beside it, checked by a unit test) |
| How to run the next batch | `.claude/programme/README.md` |
| The LinksPage programme overview and tier matrix | GitHub issue **#215** (umbrella), with the full batch order as a comment |
| Standing working rules | `.claude/memory/working-rules.md` (Codex reads the copy in `.OpenAI/memory/`) |
| Project memory / file map | `.claude/memory/MEMORY.md` · history log `.claude/HISTORY.md` |

### ✅ Finished this programme (all pushed, in order)

| Commit | Issue | What |
| --- | --- | --- |
| `9a49a70` | #202 | Standing rules written into the repo; Codex given the same context (`AGENTS.md`, `.OpenAI/` copy, sync script, GitHub check). 9 review rounds. |
| `7ef2870` | #216 | LinksPage feature-gate foundation, and the pricing engine's on/off switch fixed — it could never be switched on (three copies of the same fault). |
| `f4914bd` | #217 | Session cookie scoped to the host being visited, so the lnks.page age gate stops looping in production. |
| `81c9264` | #218 | LinksPage save bugs: social links lost, font stored as "0", republish said "not found", unreachable addresses. |
| `fd188e8` | #183 | The pricing tables now install on MariaDB (what the live host runs), plus a new MariaDB import check on GitHub — it passes. |
| `69f4829` | #219 | LinksPages included in data export and account erasure. Found four GDPR gaps: #244, #245, #246, #247. |
| `5340025` | #221, #273 | Avatars and link icons display on public pages; help text translated; `http://` image addresses refused on save. |
| `0255229` | #220 | Form values no longer escaped twice (apostrophes and `&` were being corrupted). |
| `8302613` | #248 | **This handoff moved to `.github/HANDOFF.md`**; owner decisions 16–19 written into the rules. |
| `b4d6b52` | #249 | The dev-team plugin's files moved to `docs/dev-team/` (still tracked); real security policy at `.github/SECURITY.md`; the plugin's command guard no longer runs in every session. |
| `edc469e` | — | Handoff records the Codex situation and the catch-up list. |
| `76ad243` | #242 | Sample names instead of the owner's real name and email in tracked files; `docs/HISTORY_REWRITE_PLAN.md` written (describes only, runs nothing). |
| `3a55fe1` | — | Planning moved to Opus (owner, 2026-09-23); this restart point; the build plan, its schema, the build workflow and the test script moved into `.claude/programme/`, with a unit test that checks the plan against its schema. |
| `9669b25` | — | Build workflow: Codex's model named in reviews; a lock file keeps Codex free for a catch-up review; finaliser writes to this table; shorthand written out. |
| `2a26601` | — | Handoff: Codex out of credit, catch-up scheduled (its message gives the refusal time as "about 00:15"; it was 00:03). |
| `5740c07` | — | Test script removes its database volume (each run had left one behind); builders told to keep comments short and true; workflow steps survive an agent that fails to report. |
| `95a0953` | — | Notes: CX-01 and SX-198 recorded; the shared Codex allowance and the short-comments rule written into the working rules. |
| `79d6e11` | #218 | Every user-facing error message in `web/_functions/linkspage_manage.php` now goes through the translation system, with a new seed file for the wording. CX-01. Review: 8 round(s); reviewers by round: claude-opus-fallback; last round clean. |
| `64b3ca5` | #198 | Every direct-access guard under `web/` now compares real resolved file paths instead of bare file names, so an endpoint no longer gets redirected away by a library file that happens to share its name (the scheduled-jobs endpoint was dead this way). SX-198. Review: 4 round(s); reviewers by round: claude-opus-fallback, claude-opus-fallback, claude-opus-fallback, claude-opus-fallback; last round clean. |
| `1412af2` | #203 | A new automated check now fails the build if any link, form target, redirect or background request in the shipping code points at an address ending in `.php`, and the one place that did (an analytics CSV download link) is now fixed. SX-203. Review: 4 round(s); reviewers by round: claude-opus-fallback, claude-opus-fallback, claude-opus-fallback, claude-opus-fallback; last round clean. |
| `eee195b` | #211 | The lnks.page "coming soon" page no longer shows a live email sign-up form that threw away every address typed into it (it posted to "#", and nothing read `$_POST`); the same form, already commented out on the go2my.link landing page, is replaced with an explanatory comment so nobody brings it back by uncommenting. A new test fails the build if any landing page gains a form again. SX-211. Review: 2 round(s); reviewers by round: claude-opus-fallback, claude-opus-fallback; last round clean. |
| (this commit) | #196 | CI no longer lets the test database silently pick up the wrong collation, and the integration job is no longer advisory; the web installer now checks and corrects a wrong collation before import, refusing with the exact SQL if it cannot; both stored procedures now state the required collation explicitly on every variable-to-column comparison, as a second safeguard; migration 042 fixes an existing database; and DEV_NOTES.md plus the other developer documents now state the required collation. SX-196. Review: 9 round(s); reviewers by round: claude-opus-fallback; last round clean. |

**Test baseline now: 677 unit tests and 230 database tests, all passing** (PHP 8.4 in Docker; MySQL
8.4 with `utf8mb4_unicode_ci`). Run them with `sh .claude/programme/run-tests.sh all`.

### ⏭️ Next actions, in order

1. **The Codex catch-up findings**, if it has run: fix the real ones first, as their own items.
2. **Batch 3:** SX-196 (#196 — the database text setting, "collation", fixed in the GitHub checks,
   the installer and both stored procedures, with a migration and developer notes), SX-207 (#207 —
   the CSS cleaner must remove backslash escapes before its keyword checks; security), SX-204 (#204
   — the info page must keep hyphens and underscores in short codes), SX-210 (#210 — stop
   advertising features that do not exist).
3. **Batches 3 to 18** — the rest: the approved fixes, then the platform work (environment-aware
   pricing switch, the plan-and-pricing admin screens, analytics kept as daily counts), then the
   LinksPage features (hide branding, search and sharing controls, scheduled links, click tracking
   and statistics, password pages, uploads, embeds, templates, email capture, verified badges), then
   the translation pass, caching and the Help page.
4. **The documentation sweep and the final report to the owner**, after the builds. Already noted
   for it: `README.md` says there are 17 seed files (there are 26); LP-23's key names need settling
   first (comment on #232).

### 🔁 Codex, and what it is owed

- **Codex works, but it must be told which model to use on this machine:** add
  `-c model="gpt-6-astra"` to every `codex` command. Without it, it refuses with *"The 'gpt-6-sol'
  model is not supported when using Codex with a ChatGPT account"* — which looks like an outage but
  is not. **Read the message: a credit message names a reset time.** *(Checked 2026-09-25:
  `~/.codex/config.toml` now also names `gpt-6-astra`, so a bare `codex` command works on this Mac.
  Keep the flag anyway — another machine will not have that file.)*
- **Its allowance is small** (about one review per reset) **and shared with every other project on
  the owner's account** — on 2026-09-25 it was already used up at 00:03 although no Go2My.Link
  session had touched it. Never assume it is free: try once, read the reset time, schedule the
  catch-up just after it, and hold the item reviewers off Codex with the workflow's `codexLockFile`.
- **Owed a review: everything after `69f4829`.** The 04:40 attempt on 2026-09-25 produced nothing
  (out of credit after three minutes). The 09:42 retry covers only the product-code commits; the
  notes and build-tool commits remain owed after it. Run it in a separate
  worktree so a build in progress cannot interfere. A written brief works better than a bare
  `--base` review: `codex review -c model="gpt-6-astra" - < brief.txt`, where the brief names the
  commit range, puts product code first, and says to skip line-by-line review of
  `.claude/programme/build-plan.json` and the generated `.OpenAI/` copy.
- **What Codex has already proved:** its one full catch-up review found an untranslated-messages
  fault (now CX-01), and its review of the history-rewrite plan found two faults four Claude review
  rounds had missed — one of which would have silently corrupted ordinary words such as "balanced"
  in every commit of a rewritten history.

### 🧭 Rules changed on 2026-09-23 (owner)

- **Deep analysis and planning now use Opus, one agent at a time** — no longer Fable first. The
  owner's reason: the current Opus is cheaper and at least as effective. Written into
  `.claude/memory/working-rules.md` (rule 4), the root `CLAUDE.md`, `AGENTS.md` and the machine-wide
  `~/.claude/CLAUDE.md`.
- Building stays on Sonnet or Haiku (Opus when genuinely complex); **checking is never done by a
  weaker model than the building**. For these programme items, **do not use Haiku as a builder** (see
  the traps below).
- Fact-gathering may run in parallel; judgements may not (decision 16).

### ⚠️ Traps found the hard way

- **A Haiku builder ignored "do not commit" and made seven unreviewed commits** (2026-09-21, LP-10).
  They were never pushed; with the owner's go-ahead they were folded back into one set of staged
  changes and the item was rebuilt on Sonnet. Two safety locks now exist: `.git/hooks/pre-commit`
  and `.git/hooks/pre-push` refuse unless `G2ML_ALLOW_COMMIT=1` / `G2ML_ALLOW_PUSH=1` is set for that
  one command. **They are local only — a fresh clone will not have them.**
- **The review loop must stop at real problems, not wording.** One documentation item ran nine rounds
  over eight hours on phrasing. A finding blocks a commit only when something is false, contradicts
  another file, breaks a house rule, is a correctness/security/privacy defect, or misses an
  acceptance criterion.
- **Reviewers must not run the database tests** — minutes of silence looks like a stalled agent and
  kills the run. They run the unit tests; the builder and finaliser run everything.
- **The dev-team plugin's command guard misfires** on any command whose *text* contains a push to the
  main branch. Write such text to a file with the editor and run the file. Since `b4d6b52` the guard
  is off between runs (it switches on only when `.dev-team/autopilot.json` exists, which has moved).
- **PHP 8.5 is installed on this Mac** (`/opt/homebrew/bin/php`), but CI uses 8.4 — the lowest the
  live site is meant to run — so test in Docker on `php:8.4-cli`.
- **Long comments grow until they are wrong** (2026-09-25, CX-01). The code was right from the first
  review round, but it took eight rounds, because each fix lengthened the comments (history,
  counts, pointers to other files) and the next round found a new false statement in them. Keep
  comments short enough to be certainly true; review-round history belongs in the commit message,
  never in code. The workflow now tells builders this.
- **A throwaway MySQL container leaves its data behind unless removed with `-v`.** Until `5740c07`
  every full test run left a whole test database in an anonymous Docker volume. After any test run,
  `docker volume ls -q -f dangling=true | wc -l` should be 0. A stopped container named `g2ml-mysql`
  from about two weeks earlier is still on this Mac; it was not created tonight and has been left
  alone until the owner says it can go.

### 🧑‍✈️ Waiting on the owner (nothing is blocked on these; they are choices)

1. **Codex credit.** It manages roughly one review per reset, so most items only ever get the
   stand-in review (a fresh Claude Opus agent that did not build the change). More credit, or a
   second independent system, would restore the cross-checks the rules intend.
2. **The real per-plan numbers** for counted features (scheduled links, uploads, embeds, captures and
   so on). Placeholders are in the plan; the owner sets the real ones in the tier admin once built.
3. **Whether to rewrite git history** to remove the real name from past commits.
   `docs/HISTORY_REWRITE_PLAN.md` is ready; nothing runs without an explicit go-ahead.
4. **Two GitHub settings only the owner can change:** make "Tests (Integration) (ubuntu-latest)" a
   required check on `main` once it passes on the pull request, and switch on private vulnerability
   reporting (Settings → Security).
5. **A question on the handoff's home.** The standing-tasks text says the handoff lives in
   `.claude/`; the owner's answer on 2026-09-22 (decision 17) moved it to `.github/HANDOFF.md`, which
   is where it is now, and what keeps the dev-team plugin from overwriting it. `.claude/HANDOFF.md` is
   a one-line signpost to it. Say if you would rather it moved back.

### 🗓️ 2026-09-07 — session record (merged to `alpha` via pull request #201)

> Kept for the record. Its "in progress" wording is historical — see the correction just above.

**Working branch: `chore/2026-09-07-audit-docs-sweep`**, cut from `alpha` at `9b68393` and
pushed to GitHub. All of this session's work goes onto that one branch. There is deliberately
**no pull request yet** — the owner asked for a single pull request at the end rather than
several open at once, because several open pull requests can clash with each other when they
merge.

**Repository alignment (done).** `git fetch --all --prune` removed one stale remote-tracking
entry, `origin/docs/165-done`, whose branch had already been deleted on GitHub. Local `alpha`
and `main` were already exactly level with their GitHub counterparts (zero commits ahead, zero
behind). No local branch had a deleted upstream, so nothing local needed removing. Remote
branches are `main`, `alpha`, `beta` and `release-candidate`; only `alpha` and `main` exist
locally, which is fine.

**What is running / what is done:**

| Task | State |
|---|---|
| Repository re-alignment with GitHub | ✅ done |
| "Plain, everyday English" standing rule | ✅ done — commit `bd785b6` |
| Deep audit: code, all 164 issues, docs, new-work ideas | 🔄 running (sequential Fable 5 agents) |
| Independent second opinion from a different AI (codex) | 🔄 running |
| Swagger UI browsable API docs | 🔄 files drafted and syntax-checked, not yet placed in the repo |
| GitHub issue sweep | ⏳ waiting on the audit |
| Documentation refresh | ⏳ waiting on the audit |
| Ranked list of proposed new work | ⏳ waiting on the audit |

**Verified independently this session (actually run, not taken from notes):**

| Check | How | Result |
|---|---|---|
| Unit test suite | `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php` | **594 passed, 0 failed** |
| PHP syntax across shipping code | `php -l` on every `.php` outside `_libraries/` and `public_html_legacy/` | **206 files, 0 errors** |
| #93 leaked credential file | `find web -iname 'dbConfig*'` and `git log --all -- '*dbConfig.php'` | file is **gone from disk and absent from git history**; `.gitignore` guards it at lines 29–30. The credential still needs rotating by the owner — that part of #93 is not code. |

⚠️ **PHP is not installed on this machine.** There is no `php` on the PATH. Everything above was
run inside a Docker container (`php:8.3-cli`), which is available. The live target is PHP 8.4+,
so a test that passes here has been proved on 8.3, not on 8.4 — worth knowing before treating a
green run as complete proof.

### 🔴 Three real faults found and filed this session (all verified by running them)

These were not known before. Each was proved, not inferred — the evidence is in the issue.

| # | What is wrong | How bad |
|---|---|---|
| **#198** | **The scheduled-jobs endpoint can never run a job.** `web/_functions/cron.php` opens with the standard "don't run me directly" guard, which compares only file *names*, not folders. The trigger endpoint is `_admin/public_html/cron.php` and the library is `_functions/cron.php` — both named `cron.php`, so the guard mistakes one for the other, redirects to the homepage and stops. Account deletion (#163) and retention (#167) were both closed on the strength of this endpoint. | **Critical.** Nothing breaks today because the jobs ship switched off, but they would not work the moment they are switched on. Deletion has no other route; retention has the slow 1-in-500 fallback. |
| **#196** | **A quarter of the integration tests have been failing in CI, unseen.** The last run on `alpha` reports "182 passed, 25 failed" and is still marked success, because that job is advisory. The cause is that `ci.yml` lets the MySQL container pre-create the database, so our own `CREATE DATABASE ... COLLATE utf8mb4_unicode_ci` does nothing and the wrong collation sticks. Fixing only the collation gives **207 passed, 0 failed**. | **High.** Also a live risk: if the Dreamhost database ends up with a different collation, every short link creation fails. |
| **#197** | **`sp_generateShortCode` hides every database error.** Its `EXIT HANDLER FOR SQLEXCEPTION` turns any fault into a plain "no code available", so the user sees "Failed to generate a unique short code. Please try again." and no log anywhere says why. This is exactly why #196 stayed invisible for weeks. | **High.** Turns a diagnosable fault into an unexplainable one, on the core feature. |

**Also confirmed, not yet filed** (they go in the ranked proposals):

- `web/_functions/api_auth.php:373` — the API key expiry check is written as
  `if ($expiryTimestamp !== false && $expiryTimestamp < time())`. If a stored expiry date cannot
  be read, `strtotime()` returns `false` and **the whole expiry check is skipped**, so the key
  never expires. Every comparable check elsewhere fails the safe way round; `data_rights.php:307`
  shows the correct pattern. The same fail-open copy sits in the API-keys page at
  `_admin/public_html/pages/api-keys/index.php:361`.
- `web/_functions/html_sanitiser.php:455` — the CSS cleaning matches literal words
  (`expression(`, `@import`, `behavior:`, `javascript:`) but browsers also accept them written
  with backslash escapes, so `e\78pression(` survives. Low exploitability today (the feature is
  premium-gated, switched off, and served under `script-src 'none'`), but it is a hole in a
  defence layer on the highest-risk surface in the product.
- **The admin interface sends users to a public GitHub link** for a repository whose files are
  marked proprietary — `_admin/public_html/pages/org/short-domains/index.php:243` and `:496` both
  link to `github.com/MWBMPartners/Go2My.Link/blob/main/docs/CUSTOM_DOMAINS.md`. For a customer
  that link is a dead end.
- **There is no in-app help of any kind.** No help, guide, FAQ or onboarding page anywhere; no
  tooltips; 17 one-line field captions across the whole product; and no Help or Support link in
  either the navigation or the footer.
- **Two API permissions can be granted but do nothing**: `domains:read` and `domains:write` are
  offered on the API-keys page and accepted by the key system, but no route in the API uses them.

### ✅ Independently re-verified this session

| Check | Result |
|---|---|
| Full schema + procedures + seeds import, MySQL 8.4.11 | **0 failures** |
| Integration suite, correct collation | **207 passed, 0 failed** |
| Integration suite, CI's collation | 182 passed, **25 failed** (reproduces CI exactly) |
| Unit suite | 594 passed, 0 failed |
| `php -l`, shipping code | 206 files, 0 errors |

**Swagger UI groundwork already finished (files are in the session scratchpad, not yet in the
repo):** Swagger UI **5.32.15** (Apache-2.0) downloaded and measured against its own bundle. It
turns out to need a *stricter* security policy than the Redoc page already in the repo, not a
looser one — no Web Worker, no CSS-in-JS, no `eval`, and no external font or image host. The
page, its bootstrap script, its `.htaccess` and a dark-mode stylesheet are written and pass
syntax checks.


### 🗓️ 2026-08-04 update (four-tier CI/security + release-candidate)

**Branch flow is now `alpha → beta → release-candidate → main`.** The
**`release-candidate`** pre-production tier was cut from `alpha` (at `f3646d7`).

- **Dependabot + dependency-backport now cover all four tiers.** `#191 → main`
  extended `.github/dependabot.yml` to **four `target-branch` entries**
  (main/alpha/beta/release-candidate) and added
  **`.github/workflows/backport-dependencies.yml`**: on a merged
  dependency/security/infrastructure PR to `main` it cherry-picks the merge
  commit onto each other tier and opens a `backport/pr-<N>/<target>` PR (or a
  tracking issue if the cherry-pick conflicts). This closes the gap that
  Dependabot **security** updates can only ever target the default branch —
  they now fan out to alpha/beta/release-candidate automatically.
- **Same infra synced onto `alpha`** (the real source of truth, to kill
  alpha-vs-main drift): 4-tier `dependabot.yml`, the backport workflow,
  `release-candidate` added to `ci.yml` push triggers, and a **safe
  `release-candidate) → public_html_dev_rc`** channel case in `sftp-deploy.yml`.
  RC is deliberately **left out of the deploy `push:` list** (no auto-deploy)
  and its channel case exists only so a manual `workflow_dispatch` on the RC ref
  can never fall through the default `*)` case onto production `public_html`.
- **Two new owner actions** (see `PRE_LAUNCH_CHECKLIST.md` A6/A7):
  (A6) add repo secret **`BACKPORT_TOKEN`** (fine-grained PAT, `contents:write`
  + `pull-requests:write`) so backport PRs trigger CI; (A7) **`main`'s
  `sftp-deploy.yml` currently maps an armed `release-candidate` push to
  PRODUCTION** (it lists RC in `push:` but has no `release-candidate)` case →
  `*) → public_html`) — apply the RC case to `main` as done on `alpha`, or drop
  RC from `main`'s deploy `push:` list, before ever arming `SFTP_ENABLED`.
- **#165 → alpha (#189):** individually-registered (`[default]`-org) users now
  get **per-user analytics** with a proven data-isolation guarantee (a
  non-owning user's `[default]` aggregate returns 0) + two extra
  ownership-leak fixes in the analytics export + API v1 handlers.
- **CI runner convention codified (#194):** audited every workflow on every tier
  — all jobs already run on `ubuntu-latest` (no windows/macos/self-hosted),
  including both `sftp-deploy.yml` jobs. Recorded the standing rule in
  `.claude/memory/patterns.md` ("CI/CD & GitHub Actions") + a point-of-use note
  in `sftp-deploy.yml` so future workflows stay on `ubuntu-latest`.
- ⚠️ Still true: do **NOT** merge stale `main` down into `alpha`/`beta`/`release-candidate`.

### 🗓️ 2026-07-22 update (automation session)

**Merged to `alpha` this session (in order):** #170 (launch-prep integration) → #176 (CI runs the
528-test unit suite + advisory `mysql:8` integration job; #175 + analytics i18n #164) → #177
(unverified-domain dead-link fix #166) → #179 (**flexible pricing engine**, disabled — #180).
**Merged to `main`:** #171 (combined Dependabot bumps; #168/#169 closed) + #172 (Dependabot 3-tier
+ grouped). **Merged to `beta`:** #173 (first grouped Dependabot PR — proves the 3-tier config works).

- **Pricing engine (#179/#180):** 10-table data-driven model on `alpha` — unlimited tiers/features/
  price structures (PAYG-capped, lifetime, coupons, usage metering), resolver `web/_functions/pricing.php`,
  backfill from legacy columns, `Pricing_Strategy.md`. **Additive & DISABLED** (`billing.pricing_engine_enabled='0'`);
  entitlements.php behaviour byte-unchanged until the owner flips it. Enable pending sign-off (D4).
- **GDPR launch-blockers CLOSED (#182 → alpha):** #163 (deletion execution) + #167 (retention) now
  have a **token-guarded cron endpoint** + jobs library wiring the existing `data_rights.php`
  functions, shipped **fully inert** (all `cron.*`/`gdpr.*`/`retention.*` settings OFF, deletion in
  dry-run). 45 unit + 17 integration tests. **Activation** (enable + turn dry-run off + wire a daily
  trigger) is an owner action — D1/#178. Deletion is endpoint-only; a 1-in-500 `page_init.php`
  fallback runs retention only.
- **#183 MariaDB portability (schema FIXED; CI validation deferred):** `036_pricing_engine.sql` +
  `migrations/020` used a STORED generated column with an implicit string→DATETIME coercion that
  MySQL 8 accepts but MariaDB (Dreamhost's engine) rejects. **Fixed with an explicit
  `CAST('1000-01-01 00:00:00' AS DATETIME)` (#186, green on `mysql:8`).** An attempt to add a
  `mariadb:11` CI leg (#186/#187) was **reverted** — this sandbox runner can't initialise a MariaDB
  service container (`io_uring EPERM`; it reaches "ready" then GitHub tears it down), so `alpha`'s CI
  is back to `mysql:8`-only + green. **#183 stays OPEN** for two remainders: (a) re-add a MariaDB CI
  leg on a capable runner (or start MariaDB as a step, not a GH service), (b) import the full schema
  on the real Dreamhost MariaDB once before cutover. Also landed: **#185** (gitignore agent worktrees).
- **🎉 ALL launch-gating CODE items are now CLOSED:** #163, #164, #165, #166, #167 (+ #159/#162).
  **#165 (#189):** individually-registered (`[default]`-org) users now get analytics scoped to their
  OWN links across dashboard, export, and API — plus two ownership leaks closed (the `?code=`
  drill-down and the API existence-check verified only `orgHandle`); isolation regression test green.
- **Issue review (Fable):** 158 issues, **0 wrongly-closed**. Closed #159/#163/#164/#165/#166/#167/#175;
  filed #178 (scheduling decision), #180 (pricing engine), #183 (MariaDB portability — still open).
- **Still-open next steps (non-launch-blocking):** #153 phpcs conformance (large, mechanical —
  ~9.3k `phpcbf`-auto-fixable + flip the gate; best done by a dedicated agent/clean context) →
  #183 remainder (re-add MariaDB CI on a capable runner + import once on the real Dreamhost MariaDB)
  → post-launch phases (#51–#56, #34–#37 SIGNula, #57–#60 billing) + #139–#144 triage. See
  `PRE_LAUNCH_CHECKLIST.md` for owner decisions D1–D7 + the **cross-project integration contract**
  (CueRCode / SIGNula — repo access declined via add-repo, D6).
- ⚠️ Still true: do **NOT** merge stale `main` down into `alpha`/`beta`.

**State in one line:** the codebase is in good shape and the recovery is verified, but launch is
**no longer** gated only on owner actions — the conformance audit found real code gaps, two of
them GDPR blockers (#162, #163).

⚠️ Do **NOT** merge the stale `main` (it would resurrect the legacy engine + the #93 credential
file).

### ✅ Verified independently this session (not taken on trust)

| Check | Result |
|---|---|
| Conflict markers / rebase artifacts | none |
| `php -l` across 143 files | **0 errors** |
| Unit suite | **519 passed / 0 failed** |
| PHPStan level 5 | **0 errors**, gate enforced (`continue-on-error: false`) |
| `actionlint` | clean |
| Deploy simulation (real lftp 4.9.2, seeded "server") | all live-only files survive; no secret leakage |
| All 156 issues vs actual code | 93 implemented · 34 partial · 22 not implemented · **0 needing reopen** |

### 🔴 NEW launch blockers found by the audit (were untracked)

| # | Blocker |
|---|---|
| **#162** | **GDPR data export cannot be downloaded** — the UI emits `/privacy/export?download=<uid>` but **no code anywhere reads that parameter**. The privacy policy promises this. |
| **#163** | **Account-deletion requests are never executed** — `g2ml_processDataDeletion()` and `g2ml_anonymiseUserData()` have **zero callers**. The policy commits to erasure within 30 days. |
| #167 | Privacy policy publishes retention periods (90-day log purge, etc.) that **nothing enforces** — only the API request log has any retention code. |
| #165 | **Individual (non-org) users get no analytics at all** — the dashboard hard-blocks the `[default]` org, i.e. every individually-registered user. |
| #166 | Short URLs can be minted on an **unverified** custom domain that the resolver then refuses → dead links. (Creation-path mirror of #160.) |
| #164 | Analytics dashboard renders **raw translation keys** on its four KPI tiles + CSV button (keys used but never seeded). |

Issues #162, #163 and #167 share one unanswered question: **how does periodic work run on
Dreamhost shared hosting?** (no assumable cron). Decide that once and all three become tractable.

### ✅ Fixed this session

- **#156** SFTP deploy was broken 100% — lftp parsed the `|` in `--exclude '(^|/)…'` as a pipe operator, aborting all four mirror phases. Failed closed, so nothing was ever uploaded or deleted.
- **#158** (code half) — a dry run showed **47 removals** against live Dreamhost. `--delete` dropped from the Phase-2 mirror; `_auth_keys/`, `.auth/`, `private_html/`, `.dh-diag` excluded.
- **#159** `phpstan.neon` used PHPStan 1.x-only keys, so the pinned 2.2.4 aborted on config while `continue-on-error: true` hid it — **CI had been running zero static analysis**.
- **#160** migrated partner domains landed `pending` and were unroutable (fixed + verified on MariaDB); `.auth/` rename given its installer + deploy + documented server step.
- **#161** `setSetting()` silently downgraded `isSensitive` and stored secrets in **plaintext**.
- 42 issues had every cited commit SHA remapped to live ones (the rebase invalidated them all).

### ♻️ Recovery + other-device reconciliation (2026-07-19)

- Original recovered tip `737c010` was preserved in a verified Git bundle before rewriting.
- All 72 recovered commits were rebased onto remote `launch-prep/2026-07-09` at `52d1b89`;
  the remote tip is an ancestor of the combined branch, so no force-push is required.
- The deployment-workflow resolution keeps both sets of safety work: the other device's
  quoted lftp excludes and additive Phase-2 mirror (#156/#158), plus the recovered,
  fetch-gated, non-deleting GeoIP database deployment (#43).
- The PHPStan resolution keeps the other device's PHPStan 2.x/dynamic-constant repair
  (#159) plus the recovered legacy-tree exclusions and enforced clean Level-5 gate (#76).

### 🧑‍✈️ Owner actions to unblock the A+B launch

1. **Review** the combined `launch-prep/2026-07-09` branch on GitHub (72 recovered commits on top of the latest remote work).
2. **#93** — rotate the leaked legacy DB password on the host; archive `public_html_legacy/`.
   The `dbConfig.php` file is already gone from disk, **but the password rotation is still owed** —
   do not assume it was rotated.
3. **DB migrations at cutover** (existing DB): run **`016`** (custom-HTML gating — else `getOrgTier`
   fails-open and ALL tier gating silently disables) and **`019`** (System-scope settings dedupe)
   **before** deploy; then the **`004`** 480-URL data migration + force-reset the 7 plaintext
   passwords. Do a `dry_run.sql` pass first; pick the cutover window (the real launch event).
4. **Assign paid tiers** to migrated orgs — the Free tier is ENFORCED (`maxLinks=50`,
   `maxCustomDomains=0`), so default-`free` orgs are capped until assigned.
5. **`.auth/` refactor (`98b7909`):** run the 4 `mv`s — 3 per-component `_auth_keys/` → `.auth/`
   dir renames + `dbConfig.php` → `web/_auth_keys/`.
6. **Legal sign-off** on the 5 `{{LEGAL_REVIEW_NEEDED}}` legal docs.
7. **Keep OFF until sign-off:** custom-HTML/WYSIWYG (highest stored-XSS surface) and IP
   geolocation (`analytics.geolocation_enabled` — only after confirming the CI-fetched `.mmdb` landed).

### ❓ Owner decisions still blocking P2+ (see `docs/LAUNCH_PLAN_2026-07-09.md` §11)

- Final **tier naming + currency (GBP)** → reconcile pricing page + DB seeds + gating.
- **SIGNula:** OIDC endpoints/creds; does it broker the other IdPs (collapses #34–37 into one)?
- **Billing** provider keys (Stripe / PayPal / SIGNula).
- Ratify **#149** API Low residuals 2 & 3 (per-org-vs-per-key rate limit; `maxLinks` TOCTOU) → then it closes.

### 🛠️ Dev-buildable next (no owner input — optional, if continuing to build)

- **#153** phpcs conformance (9,694 errors / 1,307 warnings / 148 files; 9,354 phpcbf-auto-fixable) —
  best as ONE dedicated, reviewed `phpcbf` reformat pass, then flip the PHPCS gate. (PHPStan gate already enforced.)
- **#151** expose captured UTM as an analytics dimension · **#152** xlsx analytics export.
- **#127** `orgHandle` immutability vs surrogate-FK migration — needs a design decision first.
- Post-launch: **#51–56** advanced redirects (Phase 9). **#34–37 / #57–60** (SIGNula auth + billing) need owner creds.

---

## 🎯 Mission (this program of work)

Take the Go2My.Link suite to a **secure, robust public launch** and build out the
post-launch roadmap the owner asked for:

1. **Launch A + B** (go2my.link + g2my.link). C stays a coming-soon landing page.
2. **API framework** (#38/#39) that covers **all** product functionality — the hard
   dependency for **CueRCode** dynamic-QR integration.
3. **Partner custom domains** (#91) + a well-documented **DNS/TLS onboarding guide**
   (Dreamhost shared + Cloudflare).
4. **SIGNula.id SSO** sign-in (thin OIDC integration; broader providers via SIGNula).
5. **Premium tiered feature system** (gating + billing).
6. **Component C** (LinksPage) build.
7. A **fresh security/lint sweep** — fix everything found, any severity/age.
8. **OpenAPI/Swagger docs** — thorough, once the API endpoints exist (capstone of P1).

**Working preferences (owner):** the full, current list is
[`.claude/memory/working-rules.md`](../.claude/memory/working-rules.md). In short: deep planning on
**Fable** (one agent after another, not in parallel); implementation on **Sonnet/Haiku**, **Opus
only when necessary**; **one GitHub issue + one commit per piece of work**; **commit and push each
finished piece to the working branch** (changed 2026-09-21 — it used to be "commit, never push");
keep `.claude/`, `.OpenAI/` and this HANDOFF current. *(Out of date, kept for the record: this
section used to say "steer via the dev-team-plugin artifacts `PROJECT.md`, `FEATURES.md`,
`SECURITY.md`, `.dev-team/autopilot.json`". This HANDOFF is the plan of record; those files are
only the plugin's working notes.)*

---

## ✅ Where things actually stand (current — 2026-07-18; code wins over docs)

- **A + B are code-ready for launch.** All 2026-07-09 launch-hardening fixes verified in-tree;
  3 fresh-install P0/P1 blockers found+fixed (#135/#136/#138) + a column-audit sweep (0 P0).
- **Public API: BUILT + security-audited.** #38 framework + #39 endpoints + #40 key-mgmt UI +
  #75 OpenAPI/Redoc at `/api/docs`; a full adversarial audit (`bad789a`) passed (1 Medium fixed).
  **CueRCode integrates now** (#145) via `/api/v1` with a `qr:link` key. #149 Low residuals
  documented, awaiting owner ratification (kept open on purpose).
- **Analytics: BUILT** (#41 data + `/api/v1/analytics` + #42 dashboard, streaming CSV export #44);
  **IP geolocation BUILT** (#43, gated off, CI-fetched `.mmdb`); **UTM capture/forward BUILT**
  (#92, gated off; captured UTM not yet a dashboard dimension — follow-up **#151**).
- **Premium tiers: entitlement gating ENFORCED** (`entitlements.php` #146 — `maxLinks`/API-daily
  (per-org)/domain-cap, fail-open). Pricing-page reconcile still pending (needs owner tier naming/currency).
- **Component C (LinksPage): 6/6 BUILT** (#45/#48/#47/#46/#50/#49). `customHTML`/WYSIWYG is
  premium-gated + kill-switch OFF (needs owner security sign-off before enabling).
- **Custom domains: DONE** (#91 verification + verified-only routing + partner docs); the two-domain
  disconnect **resolved** by deprecating `tblOrgDomains` (GT-6, `migrations/018_deprecate_org_domains.sql`).
- **SIGNula / billing: zero code — need owner** (SIGNula OIDC endpoints/creds; Stripe/PayPal/SIGNula keys).
- **Auth-dir refactor:** per-component `_auth_keys/` → `.auth/` (`98b7909`); owner must run 4 `mv`s
  (3 dir renames + `dbConfig.php` → `web/_auth_keys/`). Shared `web/_auth_keys/` unchanged.
- **✅ 2026-07-18 launch-prep close-out COMPLETE:** ~22 built-but-open issues closed with evidence;
  a hygiene/a11y/db cluster fixed; **PHPStan is now an enforced CI gate** (0 shipping-code errors);
  phpcs conformance deferred to **#153**. Open issues **61 → 28** — all remaining are owner-blocked,
  post-launch-phase, or for-consideration/triage. See "Done this session (2026-07-18)" below.

## 🔴 Genuinely outstanding before an A+B launch (small)

| Item | Owner | Status |
|---|---|---|
| **#135** login blocker (`avatarURL`→`avatarPath`) + login integration test | dev | ✅ **DONE** `13f21c4` (unit 189/0, integ 22/0) |
| **#136** registration blocker (missing `NOT NULL` `username`) + register test | dev | ✅ **DONE** `f7b9e07` (unit 189/0, integ 23/0) |
| **#138** GDPR data-export broken (non-existent columns) | dev | ✅ **DONE** `602573e` fix + `cd2a337` regression test — **CLOSED** |
| Column-audit sweep (find sibling schema/code mismatches) | dev | ✅ **DONE** — 0 P0, 1 P1 (#138). Core flows all verified column-correct. |
| **#93** rotate leaked legacy DB password on host + remove `public_html_legacy/` | **owner (ops)** | pending — the leaked file itself is already gone from disk (deleted outside this repo's work, ~2026-07-10); rotation + dir archival are still owed. **Do not infer the password was rotated.** |
| Migration dry-run + full 480-URL migration; force-reset 7 plaintext passwords | dev + owner | pending |
| **Run migration `019_settings_scope_dedupe.sql`** on any existing DB before deploying (#150) | **owner (ops)** | pending — collapses duplicate System-scope settings rows via a COALESCE generated-column unique key |
| Legal sign-off on 5 `{{LEGAL_REVIEW_NEEDED}}` docs | **owner/legal** | pending |
| ~~Close ~21 fixed-but-open issues with commit refs~~ | dev | ✅ **DONE this session** — ~22 closed with evidence; open issues 61→28 |
| Doc drift: pricing USD→GBP/tier names, API envelope, DNS TXT prefix, MEMORY UTM | dev | queued |
| **#76** phpcs conformance (9,694 errors / 1,307 warnings / 148 files, 9,354 phpcbf-auto-fixable) + flip PHPCS CI gate | dev | tracked in **#153**; #76 stays open for this half (PHPStan half is done + enforced) |
| **#127** `orgHandle` tech-debt decision | dev/owner | queued (triage) |

---

## 🔄 Done this session (2026-07-18) — launch-prep close-out

Branch: **`launch-prep/2026-07-09`**, all committed, **NOT pushed**. **20 commits** landed
(`feab6b1` → `cc3e2e7`). **Open issues 61 → 28.** All dev-buildable, owner-input-free
launch-prep work is now complete.

- **Closed ~22 built-but-open issues with evidence** (code already shipped, GitHub hadn't
  caught up):
  - Phase-7/8 features: #38, #39, #40, #41, #42, #43, #45, #46, #47, #48, #49, #50, #75, #91,
    #92, #135, #136, #137, #145, #146, #147.
  - #120 (doc drift, `fd17a42`); #138 (GDPR export) closed via fix `602573e` + new regression
    test `cd2a337`.
- **Hygiene / a11y / db cleanups — built + closed:**
  - #116 favicon (`1cb9790`), #119 lang/dir (`fc3fac0`), #109 picture fallback (`832dab7`),
    #110 forced-colors (`3ff0fb0`), #112 lint excludes (`241731d`), #115 robots/sitemap
    (`4f4ae38`), #126 remove dead `sp_logActivity` (`3f43c53` + dry_run count fix `2002a66`),
    #118 client-IP helper dedupe (`1b50eef`), #114 branded error pages A/B/C (`4cb068d`),
    #150 System-scope settings dedupe via generated column + migration `019` (`6de4e0e`),
    #128 alias-chain integrity migration checks (`ce7b756`), #44 streaming CSV analytics
    export (`55bd5af`), #117 No-Shorthand house-rule sweep (`3fe0334`).
- **#76 (PHPStan) — HALF done + ENFORCED:** `phpstan.neon` repaired for phpstan 2.x + legacy
  dirs actually excluded (`42bebb1`); the resulting 45 shipping-code level-5 errors resolved
  to 0, root-cause (no `@phpstan-ignore`/baseline/widening) (`cc3e2e7`). **The CI PHPStan step
  is now a hard gate** (`continue-on-error: false`). The phpcs half (9,694 errors / 1,307
  warnings / 148 files, 9,354 auto-fixable via phpcbf) is deferred to new issue **#153**;
  #76 stays open for that half only.
  - **Two real bugs found+fixed while resolving phpstan:** (1) `public_html_landing/index.php`
    — the coming-soon page's logo `alt` text rendered blank because `$siteName` was never
    defined; fixed by defining it (`'Go2My.link'`). (2) `analytics/index.php` — the date-range
    preset ("7/30/90 days") "active" highlighting was silently dead because PHP auto-casts
    the decimal-string preset-label array keys to int, so a string-vs-int compare could never
    match; fixed with an explicit cast.
  - Introduced typed accessors `g2ml_getEnvironment()` / `g2ml_getComponent()` in
    `web/_includes/page_init.php` (root-caused a set of cross-file constant-narrowing false
    positives instead of suppressing them).
- **Left OPEN deliberately:**
  - **#149** — API Low residuals: all fixed or accept-documented; commented, awaiting owner
    ratification of residuals 2 & 3 (per-org vs per-key rate limiting; `maxLinks` TOCTOU).
  - **#76** — stays open for the phpcs half (see above).
- **New follow-up issues filed:** **#151** (expose captured UTM as an analytics dimension,
  from #92), **#152** (xlsx export via PhpSpreadsheet vs native, from #44), **#153** (phpcs
  conformance + flip the PHPCS CI gate, from #76).
- **Correction to the record (discrepancy found during reconciliation):** the leaked legacy
  `web/G2My.Link/public_html_legacy/dbConfig.php` is already **gone from disk** — deleted
  outside this repo's tracked work, roughly 2026-07-10; the rest of the legacy dir remains.
  **#93 stays open** for the actual credential **rotation** + dir archival (owner ops) — do
  **not** infer the password itself was rotated just because the file is gone.
- **New owner deploy note:** run migration **`019_settings_scope_dedupe.sql`** on any existing
  DB before deploying (collapses duplicate System-scope settings rows, adds the COALESCE
  generated-column unique key) — alongside the existing migration-016/geolocation/customHTML/
  tier-assignment notes below. Also: the `sp_logActivity` stored procedure was removed, so a
  correctly-provisioned DB now has **2** stored procedures, not 3 (`dry_run.sql` updated to
  match).
- **Remaining 28 open issues** = owner-blocked (#93 cred rotation, #71 translations, #57–60
  Phase-11 SIGNula billing, #34–37 Phase-10 SIGNula auth), post-launch phases (#51–56 Phase-9
  advanced redirects), for-consideration/owner-triage (#139–144, #149), and dev follow-ups
  (#151, #152, #153, #76 phpcs half, #127 `orgHandle` tech-debt decision).

---

## 🔄 Done this session (2026-07-09 → 07-10)

- Reverted a CI-breaking YAML typo in `.github/workflows/ci.yml` (stray indent on `uses:`).
- Full no-assumptions review of issues/milestones/project + codebase.
- **Fable 5 deep plan** → `docs/LAUNCH_PLAN_2026-07-09.md` (`082c310`).
- Fixed **3 launch-hardening defects** (each: own issue, own commit, tests):
  - **#135** login blocker `avatarURL`→`avatarPath` (`13f21c4`) + `auth_login_test.php`.
  - **#136** registration blocker — auto-derive unique `username` (`f7b9e07`) + `auth_register_test.php`.
  - **#138** GDPR data-export non-existent columns (`602573e`).
- **Column-audit sweep** across all PHP SQL vs schema DDL: **0 P0, 1 P1** (was #138). Every core
  write path (register/login/session/create-URL/redirect/org/installer) verified column-correct
  with matching bind counts — see plan for the CLEAN list.
- Owner locked 4 roadmap decisions (2026-07-09): ship A+B now + plan rest; custom-domain
  verify/routing now + manual TLS first + Cloudflare-for-SaaS later; multi-provider billing
  (Stripe + PayPal + SIGNula); SIGNula as single OIDC broker (collapses #34–37).
- Owner green-lit (2026-07-10) proceeding with ALL dev-side work; ops/legal (their actions) deferred.
- **Closed 21 verified-fixed launch-hardening issues** with evidence (#94–#124 cluster + #113).
- Filed **6 `for consideration` enhancement issues**: #139 password-protected links, #140 rich
  expiry/click-cap, #141 bulk import, #142 branded interstitials, #143 audit-log export, #144 A/B split.
- **Built + security-reviewed the API v1 framework (#38)** — `34453d8`. New: `api_auth.php`,
  `api_ratelimit.php`, `public_html/api/v1/` (front controller + ping/account handlers),
  migration 011 (composite index), seed 015, 28 unit + 16 integration tests. Key auth
  (prefix + sha256/`hash_equals`), scopes, DB rate-limiting, redacted request log, envelope.
  **Passed adversarial review** (see #38 comment); residual pre-auth IP throttle folded into #39.
- **Built API endpoints (#39)** — `0d495c1`. URL CRUD/bulk/list + org read, cursor-paginated,
  BOLA-safe org-scoping (cross-org → generic 404), pre-auth IP backoff. Found+fixed a real #38
  defect (base64url `_` in prefix broke ~1/9 keys). 234 unit / 60 integration green.
- **Built CueRCode wiring (#145)** — `50f2427`. QR-link create (kill-switch + `qr:link` scope +
  UUID-uniqueness 409), re-point, scan attribution (forge-proof); `logActivity()` hot-path INSERT
  extended safely (23=23=23 bind-string verified). 244 unit / 74 integration green.
- **Built OpenAPI/Swagger docs (#75)** — `5d229d7`. OpenAPI 3.1 spec (9 endpoints, 26 schemas,
  authored from the handlers, validated by redocly + openapi-spec-validator) + self-hosted Redoc
  at `/api/docs` (vendored 2.5.3 after catching CVE-2024-57083; directory-scoped CSP; site-wide
  CSP untouched). **Milestone: public API + CueRCode integration + API docs all READY.**

## ⏭️ Immediate next steps (execution queue — see plan §10)

> 📌 **Historical — superseded by "▶️ START HERE" at the top of this file for current next-actions.**
> Kept below as the P0–P4 roadmap record; most P0/P1 items are ✅ done (2026-07-18 close-out).

- **P0:** ✅ finish #135 (done); ✅ close ~22 issues (**done 2026-07-18**, see close-out block above,
  61→28 open); ✅ low a11y/hygiene #114–119 (**done 2026-07-18**); reconcile remaining doc drift +
  stale `main`; (owner) #93 rotation, migration, legal — still pending.
- **P1:** ✅ API framework **#38** (`34453d8`) → ✅ endpoints **#39** (`0d495c1`) → ✅ **CueRCode wiring #145**
  (`50f2427`) → ✅ **OpenAPI/Swagger #75** (`5d229d7`, spec + self-hosted Redoc at `/api/docs`) →
  ✅ **key mgmt UI #40** (`8bc8dff`, create/list/revoke, one-time secret, CSRF, `canManageOrg` authz —
  ⚠️ ordinary members can't mint keys yet) → ✅ **custom domains #91** (`c52429e`, verify + verified-only
  routing + grandfather migration + `docs/CUSTOM_DOMAINS.md`; caught a fresh-install g2my.link 404 bug) →
  ✅ **analytics data #41** (`eee8272`; closed #125) → ✅ **analytics dashboard #42** (`b90b55f`, Chart.js +
  accessible tables, theme-aware). **P1 core is essentially COMPLETE** (API + CueRCode + docs + key UI +
  custom domains + analytics).
- **P1 follow-ups (smaller):** UTM #92 (hot-path capture/forward); geo #43 **needs owner decision** (MaxMind
  GeoLite2 DB ~70MB + license — do NOT auto-build); an API adversarial security cycle over the full surface.
- **P2:** ✅ **entitlement/gating layer #146** (`9522d96`, `entitlements.php` fail-open; enforces
  `maxLinks`/API-daily/domain-cap from `tblSubscriptionTiers`; 317u/109i green). ⚠️ **Free tier now
  ENFORCED** (`maxLinks=50`, `maxCustomDomains=0`) — migrated orgs (default `'free'`) need paid tiers
  assigned or they can't create >50 links / any custom domain. → next: pricing-page reconcile + usage
  meters (needs owner: final tier naming/currency) → SIGNula OIDC (needs owner: endpoints/client creds)
  → multi-provider billing Stripe+PayPal+SIGNula (needs owner: provider keys).
- **P1 follow-ups:** ✅ **UTM #92** (`ea857f1`); ✅ **API adversarial security cycle** (`bad789a` — surface
  well-hardened; 1 Medium fixed (audit-log INSERT length-bound → closed a backoff-bypass + `error_log`
  injection); Low residuals tracked in **#149**, test-flake in **#148**); ✅ **geo #43** (`89fb2e1`, vendored
  pure-PHP MaxMind reader, gated OFF + graceful-no-op, CI-fetched `.mmdb`, country analytics widget).
- **Small tracked cleanups (buildable, low priority):** #148 test-flake; #149 API Low residuals; dormant
  `analytics.geoip_enabled` scaffolding setting (remove). ✅ **GT-6 two-domain-table
  assessment DONE** (2026-07-10) — `tblOrgDomains` deprecated (not unified): confirmed
  zero FKs/seeds/migrations/tests touch it; `org/domains/index.php` no longer accepts
  new rows; `web/_sql/migrations/018_deprecate_org_domains.sql` documents the
  owner-reviewed reconciliation path for any pre-existing rows (none expected —
  no seed/migration ever populated it). unit 502/0, integ 175/0 unchanged.
- **✅ Component C (LinksPage) — 6/6 COMPLETE:** C.1 renderer #45 (`e37134f`) · C.2 mgmt UI #48 (`40181cc`)
  · C.3 template picker + preview #47 (`1dd6634`) · C.4 custom-domain fallback #46 (`99a10c4`) · C.5 age-gate
  #50 (`d68dfc0`) · **C.6 custom-HTML/WYSIWYG #49 (`16984aa`, Opus)** — DOM allowlist sanitiser +
  `script-src 'none'` CSP + premium-gated + kill-switch OFF by default. 480u/161i green.
  Files: `web/Lnks.page/_functions/linkspage_{resolver,renderer}.php`, `web/_functions/{linkspage_manage,html_sanitiser,adult_content}.php`,
  `web/G2My.Link/_functions/linkspage_fallback.php`, `web/Lnks.page/public_html/index.php`, `_admin/.../pages/linkspage/`.
  - 🔴 **C.6 owner actions:** (1) **run migration 016 BEFORE deploying to an existing DB** — else `getOrgTier`
    fails-open (all gating disabled) because it now selects `hasCustomHTML`; (2) **keep custom-HTML OFF
    (default) until a security sign-off** — it's the product's highest XSS surface (raw user HTML).
- **✅ #147 CSRF token-overwrite FIXED** across all 4 affected pages (`83655b1` api-keys/links/org-domains,
  `bd601cd` org/short-domains) — per-row form names namespaced; `security.php` unchanged (single-use intended).
- **P3:** Component C (LinksPage) — large greenfield (renderer/UI/templates/custom-domain fallback/
  age-gate/WYSIWYG — the HTML-upload piece is the highest stored-XSS risk in the whole product).
  - 🎉 **Milestone: public API + CueRCode integration READY.** CueRCode integrates via `/api/v1` with a
    `qr:link`-scoped key. `createShortURL()` accepts `createdVia`/`createdViaAPIKeyUID`/QR columns;
    `logActivity()` carries `scanSource`/`qrCodeExternalID` (bind-string re-verified 23=23=23).
- **P2:** entitlement/gating layer → pricing/usage UI → **SIGNula OIDC** → billing (Stripe or SIGNula).
- **P3:** Component C (#45–50) → advanced redirects (#51–56).
- **P4:** roadmap + `for consideration` enhancement ideas (plan §9).

## ❓ Decisions pending from owner (block P1+ direction — see plan §11)

1. Launch sequencing — recommend **Option A (ship A+B now, fast-follow)**.
2. Does **SIGNula broker** the other IdPs (collapses #34–37 into one OIDC)?
3. **Billing:** Stripe Checkout vs SIGNula-owned billing API?
4. **Custom-domain model:** approve **Cloudflare for SaaS** (scales) vs manual Dreamhost per-domain?
5. Final **tier naming + currency** (GBP) to align DB seeds + pricing page + gating.
6. **Migration cutover window** (the real launch event; force-resets 7 users).
7. **Component C priority** — build now or defer to P3?
