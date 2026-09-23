# 🏗️ `.claude/programme/` — the build programme and the machinery that runs it

This folder exists so that **a completely fresh session can carry on the build**
without the previous session's memory. Everything here is committed on purpose.

> The one handoff is [`.github/HANDOFF.md`](../../.github/HANDOFF.md). Read that
> first: it says where the work has got to. This folder is the *how*.

## What is here

| File | What it is |
| --- | --- |
| `build-plan.json` | Every piece of work in the programme: its plan, what finished means, which files it shares with other items, what it depends on, the order to build in, and what is already done. |
| `build-plan.schema.json` | Describes the shape of that file, with a plain-English description of every field. Checked by `tests/unit/build_plan_schema_test.php`, so a mistake in the plan fails the test suite. |
| `build-workflow.js` | The script that runs one item at a time: build → tests → review until clean → one commit pushed → issue comment. |
| `run-tests.sh` | The checks every item must pass: syntax on changed files, the unit suite on PHP 8.4, and the database suite against a throwaway MySQL 8.4 with the right text setting. |

**The plan is not the only copy.** Every item's plan is also posted as an
"Implementation plan" comment on its own GitHub issue, and the owner's decisions
are GitHub issue #243. If this file and an issue ever disagree, the issue and
#243 win.

## How to carry on the build

1. Read `.github/HANDOFF.md`, then `progress` in `build-plan.json`: it lists what
   is finished, with commit IDs, and what comes next.
2. Pick the next three or four items from `build_order`, skipping anything in
   `progress.done`.
3. Run the workflow with the Claude Code Workflow tool:

   ```text
   scriptPath: .claude/programme/build-workflow.js   (use the full path on disk)
   args: {
     "repo":       "<full path to this repository>",
     "testScript": "<full path>/.claude/programme/run-tests.sh",
     "planFile":   "<full path>/.claude/programme/build-plan.json",
     "items": [ { "key": "CX-01", "issue": 218, "title": "...",
                  "builder_tier": "sonnet", "commit_type": "fix", "scope": "linkspage" } ]
   }
   ```

   Optional per item: `"skipBuild": true` with `"startRound": N` resumes an item
   whose work is already in the working copy (use it after an interrupted run),
   and `"maxRounds": 4` lowers the review-round cap for documentation items.
4. After each run, update `progress` in `build-plan.json` and the handoff.

## Things that will bite you

- **Only the finaliser may commit or push.** A local safety lock
  (`.git/hooks/pre-commit` and `pre-commit`'s sibling `pre-push`, not tracked in
  git) refuses both unless `G2ML_ALLOW_COMMIT=1` / `G2ML_ALLOW_PUSH=1` is set for
  that one command. It exists because a Haiku builder once made seven unreviewed
  commits despite being told not to. Never work around it with `--no-verify`. A
  fresh clone will not have the hooks; copy them across or re-create them.
- **Do not use Haiku as a builder for these items.** Same incident, and it also
  failed to fix the same review finding seven rounds running. Sonnet or Opus.
- **Codex needs its model named on this machine**, or it refuses:
  `codex review --uncommitted -c model="gpt-6-astra"`. A refusal is not proof
  that its credit has run out — read the message.
- **Reviews run only the quick unit tests.** The database suite takes minutes
  with no output, which the runtime mistakes for an agent that has stopped
  responding. The builder and the finaliser still run everything.
- **The review loop stops at real problems, not wording.** A finding blocks a
  commit when something is false, contradicts another file, breaks a house rule,
  is a correctness/security/privacy defect, or misses an acceptance criterion.
  Preferences about phrasing are recorded and do not block. Without that rule a
  documentation item ran nine rounds over eight hours.
