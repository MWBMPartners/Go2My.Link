export const meta = {
  name: 'g2ml-build-master-batch',
  description: 'Build planned items one after another: build, test, cross-system review until clean, commit and push, update the issue',
  phases: [
    { title: 'Build', detail: 'one item at a time, cheapest capable model' },
    { title: 'Review', detail: 'Codex first; a fresh Opus agent only if Codex is unavailable; loop until clean' },
    { title: 'Finalise', detail: 'full tests, commit and push to the working branch, issue comment, handoff row' },
  ],
}

const A = args
const REPO = A.repo
const BRANCH = 'feat/2026-09-21-linkspage-and-sweep'
const TESTS = 'sh "' + A.testScript + '" all'
const DEFAULT_MAX_ROUNDS = 6

const COMMON = `
REPOSITORY: "${REPO}" (quote the path — it has spaces). Working branch: ${BRANCH}. Never switch branches.
HOUSE RULES THAT BITE (CLAUDE.md and .claude/memory/patterns.md have the full list — follow them):
- No shorthand in any language: no ternary (a ? b : c), no Elvis (?:), no PHP alternative syntax, no short tags, no braceless if, no one-line arrow functions. Full if/else with braces. Allman braces in web/_functions/; match the surrounding file's brace style in templates. ?? only when both sides are simple values.
- MySQLi prepared statements only. Every UI string through __('key') AND seeded in the translation seed named in the plan. Dark/light mode via existing CSS variables. WCAG 2.1 AA. No ".php" in any web address.
- Comment properly: explain WHY, record what was wrong before and what was rejected, say what the code cannot do. File headers carry path, description, authorship/licence lines like the neighbouring files. Plain, everyday English in comments, commit messages and issue text.
- Schema changes: edit the base schema/seed (fresh installs) AND add the guarded numbered migration (existing databases), exactly as the plan says — CI imports schema, procedures and seeds but never migrations.
- The GATING DESIGN below is binding. Do not invent another gating mechanism.
- Do NOT edit the project handoff (.github/HANDOFF.md, or .claude/HANDOFF.md before it moves), anything in .claude/ or .OpenAI/, the root HANDOFF.md (the dev-team plugin's ignored scratch file) or the dev-team plugin's files — UNLESS your item's plan explicitly says to (the housekeeping items HK-01, HK-02, HK-03 exist to change exactly those). If you change anything in .claude/ or the root CLAUDE.md, run  sh scripts/sync-ai-context.sh  afterwards so .OpenAI/ matches. Do NOT commit, push, stash, reset, rebase or switch branches.
- A local safety lock (.git/hooks/pre-commit and pre-push) refuses every commit and push unless G2ML_ALLOW_COMMIT=1 / G2ML_ALLOW_PUSH=1 is set for that one command. Only the finaliser role may set them. Never use --no-verify, never edit or remove the hooks.
- The dev-team plugin's guard hook blocks any shell command whose TEXT contains a git push to the main branch. You have no reason to push; if a command is blocked for that reason, write the text to a file with the Write tool and run the file.
- TESTS: ${TESTS}
  This runs php -l on changed PHP files, the unit suite on PHP 8.4, and the integration suite against a fresh MySQL 8.4 (utf8mb4_unicode_ci) with PHP 8.4. It prints each result and a final "OVERALL exit=N". Read the real exit codes; never pipe it into grep/tail and rely on &&. The baseline on 2026-09-23 was 652 unit + 229 integration, all passing; each finished item adds its own tests on top.
`

// Codex is told its model by name. Without it, Codex on the owner's Mac
// defaulted to a model the account cannot use and refused every review with
// "The 'gpt-6-sol' model is not supported" — which looked like "out of credit"
// but was not (found 2026-09-23). ~/.codex/config.toml now names the model as
// well, but a fresh machine or a new login would not have that file, so the
// flag stays here too.
const CODEX_REVIEW = 'codex review --uncommitted -c model="gpt-6-astra"'

// Codex's allowance is small (about one review per reset). When a whole-branch
// catch-up review is running, the item reviewers must leave Codex alone, or
// they can use up the allowance while the catch-up is half-way through. The
// lead creates this file before starting a catch-up review and deletes it when
// the review has finished. This replaced a fixed "23:00 to 23:45" window that
// only suited one night's reset time and was wrong by the next day.
// Optional: when args.codexLockFile is not given, reviewers always try Codex.
let CODEX_LOCK = ''
if (A.codexLockFile) {
  CODEX_LOCK = A.codexLockFile
}

// The extra first step a reviewer runs when a lock file was given: check it,
// and skip Codex while it exists. Built here with a plain if, because the
// house rule forbids the "a ? b : c" shorthand in every language.
let CODEX_LOCK_STEP = ''
if (CODEX_LOCK) {
  CODEX_LOCK_STEP = ` EXCEPTION: first run  test -e "${CODEX_LOCK}" && echo CODEX_HELD || echo CODEX_FREE  — if it prints CODEX_HELD, a whole-branch Codex catch-up review is running and has priority for Codex's small allowance: do NOT call Codex; record 'skipped: catch-up review has priority' in codex_status and go to STEP 2B.`
}

const BUILD_RESULT = {
  type: 'object',
  properties: {
    summary: { type: 'string', description: 'Plain English: what was built and why' },
    files_changed: { type: 'array', items: { type: 'string' } },
    tests_added: { type: 'array', items: { type: 'string' } },
    test_output_tail: { type: 'string', description: 'The last lines of the test script output, including every exit code and the OVERALL line' },
    overall_exit: { type: 'integer' },
    deviations_from_plan: { type: 'string', description: 'Anything done differently from the plan and why; empty if none' },
    not_verified: { type: 'string', description: 'What could not be checked (e.g. not tried in a real browser)' },
  },
  required: ['summary', 'files_changed', 'tests_added', 'test_output_tail', 'overall_exit', 'deviations_from_plan', 'not_verified'],
}

const REVIEW_RESULT = {
  type: 'object',
  properties: {
    reviewer_used: { type: 'string', enum: ['codex', 'claude-opus-fallback'] },
    codex_status: { type: 'string', description: 'What happened when Codex was tried (ran fine / usage limit message / other error, quoted briefly)' },
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          severity: { type: 'string', enum: ['high', 'medium', 'low'] },
          file: { type: 'string' },
          line: { type: 'string' },
          problem: { type: 'string' },
          fix: { type: 'string' },
          believed_wrong: { type: 'boolean', description: 'true if you are confident this finding is wrong; explain in problem' },
        },
        required: ['severity', 'file', 'problem', 'fix', 'believed_wrong'],
      },
    },
    optional_notes: { type: 'string', description: 'Wording, phrasing or style preferences you noticed. These do NOT block and do NOT go in findings.' },
    clean: { type: 'boolean', description: 'true when no real (not believed-wrong) finding remains; wording preferences in optional_notes never make it false' },
  },
  required: ['reviewer_used', 'codex_status', 'findings', 'clean'],
}

const FINAL_RESULT = {
  type: 'object',
  properties: {
    commit_sha: { type: 'string' },
    pushed: { type: 'boolean' },
    issue_commented: { type: 'boolean' },
    test_output_tail: { type: 'string' },
    notes: { type: 'string' },
  },
  required: ['commit_sha', 'pushed', 'issue_commented', 'test_output_tail', 'notes'],
}

function itemBrief(item) {
  return `ITEM ${item.key} — GitHub issue #${item.issue}: ${item.title}

YOUR PLAN AND ACCEPTANCE CRITERIA are in the JSON file
  ${A.planFile}
under "items" -> "${item.key}" (fields "plan", "acceptance_criteria", "depends_on", "shared_files_touched"). Read them in full:
  python3 -c "import json;d=json.load(open('${A.planFile}'));i=d['items']['${item.key}'];print(i['plan']);print();print(chr(10).join('- '+c for c in i['acceptance_criteria']))"
The same plan is posted on issue #${item.issue} (gh issue view ${item.issue} --repo MWBMPartners/Go2My.Link --comments). Follow the plan; if the code proves a step wrong, do the right thing and say so in deviations_from_plan.

BINDING DESIGNS: the file's "design" object has three parts — "platform" (tiers, pricing admin, environment switch, feature registry and gating, analytics storage), "linkspage" (LinksPage), "fixes" (fixes and housekeeping). Read the part(s) your item belongs to (PF-* platform; LP-* linkspage AND platform; SX-*/HK-*/SR-* fixes, plus platform where the plan refers to it):
  python3 -c "import json;print(json.load(open('${A.planFile}'))['design']['platform'])"
The OWNER'S DECISIONS are binding and final: gh issue view 243 --repo MWBMPartners/Go2My.Link --comments
Items built earlier are already committed on the branch (git log --oneline -25); build on them, do not redo them.
`
}

async function safeAgent(prompt, opts) {
  try {
    return await agent(prompt, opts)
  } catch (err) {
    let who = 'agent'
    if (opts && opts.label) {
      who = opts.label
    }
    log(who + ' failed: ' + String(err).slice(0, 160))
    return null
  }
}

const results = []

for (const item of A.items) {
  phase('Build')
  log(item.key + ' (#' + item.issue + '): building on ' + item.builder_tier)
  let build = null
  if (item.skipBuild) {
    log(item.key + ': work already in the working tree from an earlier run — going straight to review')
    build = { overall_exit: 0, summary: 'Resumed: built and partly reviewed in an earlier run; changes are already in the working tree.', not_verified: '', deviations_from_plan: '' }
  } else {
  build = await agent(`${COMMON}
ROLE: builder. Read the issue first: gh issue view ${item.issue} --repo MWBMPartners/Go2My.Link
Then read every file the plan names before changing it. Build the item completely, including its tests. Run the TESTS and make them pass. The working tree must end up containing ONLY this item's changes.

${itemBrief(item)}`, { label: 'build ' + item.key, phase: 'Build', model: item.builder_tier, schema: BUILD_RESULT })
  }

  if (!build || build.overall_exit !== 0) {
    log(item.key + ': build did not finish with passing tests — stopping the batch here')
    results.push({ key: item.key, status: 'build_failed', build })
    break
  }

  let round = 0
  let review = null
  let reviewers = []
  let clean = false
  let startRound = 0
  if (item.startRound) {
    startRound = item.startRound
  }
  round = startRound
  let maxRounds = DEFAULT_MAX_ROUNDS
  if (item.maxRounds) {
    maxRounds = item.maxRounds
  }
  while (round < maxRounds + startRound) {
    round = round + 1
    let attempt = 0
    review = null
    // From round 2 on, remind the reviewer where fixes usually go wrong.
    let laterRoundNote = ''
    if (round > 1) {
      laterRoundNote = 'Earlier rounds found problems that the builder says are now fixed; check those fixes did not introduce new problems (this is where fixes usually go wrong).'
    }
    while (!review && attempt < 2) {
    attempt = attempt + 1
    let retryLabel = ''
    if (attempt > 1) {
      retryLabel = ' retry'
    }
    review = await safeAgent(`${COMMON}
ROLE: independent reviewer, round ${round}. You did not build this change. The change is UNCOMMITTED in the working tree (git status / git diff, plus untracked files). Report only — do not edit anything.

STEP 1 — try Codex first (the owner's rule: Claude Code's work is reviewed by Codex).${CODEX_LOCK_STEP}
  Otherwise cd into the repository and run:  ${CODEX_REVIEW}
  (The -c model=... part is required on this machine; without it Codex refuses with a "model is not supported" message that is NOT a credit problem.)
  Give it up to 10 minutes (Bash timeout 600000). If it prints a usage-limit / credit / rate-limit message or fails to run, record that message in codex_status and go to STEP 2B. If it runs, go to STEP 2A.
STEP 2A — Codex ran: relay every Codex finding in findings. For each, check it against the code; if you are confident it is wrong, keep it but set believed_wrong=true and say why. Also add any acceptance criterion below that is plainly not met. reviewer_used = "codex".
STEP 2B — Codex unavailable: do the review yourself, as sceptically as a different AI system would. reviewer_used = "claude-opus-fallback". Check: correctness against the acceptance criteria; security (escaping, SQL, CSRF, access control, the page's orgHandle used for gating); the binding gating design; the house rules above (no shorthand, __() strings seeded, migrations + base schema both updated); tests really exercise the change; nothing outside this item changed. Run ONLY the quick unit tests yourself:  sh "${A.testScript}" unit  (NOT 'all' — the builder and the finaliser run the full suite with the database; a long silent database run can make you look stalled). Report the result.
WHAT COUNTS AS A FINDING (and so blocks the commit): a statement that is FALSE; a contradiction with another file in the repository; a correctness, security or privacy defect; a breach of a house rule (shorthand, a user-facing string not through __() and seeded, a schema change without its migration, and so on); or an acceptance criterion that is plainly not met.
WHAT DOES NOT COUNT (put these in optional_notes, never in findings): wording, phrasing, tone or sentence structure; "this could be clearer/shorter"; asking for more detail or a longer list where the text already says it is not exhaustive; anything you would not insist on before a commit. For comments and docs, check that every statement is TRUE — do not demand an exhaustive list of everything the code does NOT do, because that never converges.
Set clean=true when no real finding remains, even if optional_notes is long.
${laterRoundNote}

${itemBrief(item)}`, { label: 'review ' + item.key + ' r' + round + retryLabel, phase: 'Review', model: 'opus', schema: REVIEW_RESULT })
    if (!review && attempt < 2) {
      log(item.key + ': review round ' + round + ' failed (for example a server overload) — retrying once')
    }
    }
    if (!review) {
      break
    }
    reviewers.push(review.reviewer_used)
    if (review.clean) {
      clean = true
      break
    }
    const real = review.findings.filter(function (finding) {
      return !finding.believed_wrong
    })
    await agent(`${COMMON}
ROLE: builder, fixing review round ${round} for ${item.key}. Fix every finding below that is real. If you are sure one is wrong, do not "fix" it to quiet the reviewer — leave it and explain why in deviations_from_plan. Re-run the TESTS until they pass.

FINDINGS:
${JSON.stringify(real, null, 1)}

${itemBrief(item)}`, { label: 'fix ' + item.key + ' r' + round, phase: 'Build', model: item.builder_tier, schema: BUILD_RESULT })
  }

  if (!clean) {
    log(item.key + ': review not clean after ' + round + ' rounds — stopping the batch, nothing committed for this item')
    results.push({ key: item.key, status: 'review_not_clean', rounds: round, lastReview: review })
    break
  }

  const usedFallback = reviewers.indexOf('claude-opus-fallback') !== -1
  const reviewLine = 'Review: ' + round + ' round(s); reviewers by round: ' + reviewers.join(', ') + '; last round clean.'
  // Text that only appears when a Claude stand-in reviewed at least one round,
  // so the commit message and the issue comment never imply Codex checked it.
  let fallbackLine = ''
  let commitFallbackText = ''
  let issueFallbackText = ''
  if (usedFallback) {
    fallbackLine = 'Codex did not review at least one round (out of usage credit, or held back so a whole-branch catch-up review could use its small allowance), so a fresh Claude Opus agent that did not build this reviewed it instead. That is less independent than a Codex review: this change still needs the Codex catch-up review.'
    commitFallbackText = ' and: "' + fallbackLine + '"'
    issueFallbackText = ', the Codex-unavailable note'
  }
  let commitScope = 'linkspage'
  if (item.scope) {
    commitScope = item.scope
  }

  phase('Finalise')
  const fin = await agent(`${COMMON}
ROLE: finaliser for ${item.key} (issue #${item.issue}). The change is built and has passed review. You now make it permanent. You MAY commit and push in this role, to the working branch only.
1. Run the TESTS. If OVERALL exit is not 0, STOP: do not commit; report why in notes and set commit_sha to "".
2. Update the project handoff, .github/HANDOFF.md: add one row at the bottom of the table under the heading "Finished this programme" (columns: Commit | Issue | What). Commit cell: "(this commit)" — the SHA does not exist yet, and everything must land in ONE commit; the SHA goes in the issue comment. Issue cell: #${item.issue}. What cell: one plain-English sentence on what changed, then "${item.key}. ${reviewLine}". If an EARLIER row in that table still says "(this commit)", replace it with that commit's real short SHA (find it with git log --oneline -15 and the issue number). Change nothing else in the handoff — the lead updates the rest.
3. git add only this item's files plus the handoff file (check git status; nothing unrelated). Commit with a message written to a file, using the safety-lock permission for this one command: G2ML_ALLOW_COMMIT=1 git commit -F <file>. The message is plain English:
   - Title: "${item.commit_type}(${commitScope}): <short plain description> (#${item.issue})" — under ~72 characters.
   - Body: what was wrong or missing, what this changes, and why; what the tests prove; what was NOT verified (for example, not tried in a real browser); "${reviewLine}"${commitFallbackText}.
   - Last lines exactly:
     Refs #${item.issue}

     Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>
   - Never write the owner's real name; the GitHub username is Salem874.
4. Push as its own command, using the safety-lock permission: G2ML_ALLOW_PUSH=1 git push origin ${BRANCH}
5. Comment on the issue (gh issue comment ${item.issue} --repo MWBMPartners/Go2My.Link --body-file <file>) in plain English: what landed, the commit SHA, test results (unit and integration counts), the review line${issueFallbackText}, what was not verified, and "Stays open until the working branch is merged into alpha." Do not close the issue.

${itemBrief(item)}`, { label: 'finalise ' + item.key, phase: 'Finalise', model: 'sonnet', schema: FINAL_RESULT })

  if (!fin || !fin.commit_sha || !fin.pushed) {
    log(item.key + ': finalise did not commit and push — stopping the batch')
    results.push({ key: item.key, status: 'finalise_failed', fin })
    break
  }
  log(item.key + ': committed ' + fin.commit_sha + ' and pushed (' + reviewLine + ')')
  results.push({ key: item.key, status: 'done', commit: fin.commit_sha, rounds: round, reviewers, build_summary: build.summary, not_verified: build.not_verified, deviations: build.deviations_from_plan, notes: fin.notes })
}

return { results }
