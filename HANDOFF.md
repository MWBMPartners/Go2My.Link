# 🤝 Go2My.Link — Session Handoff

> Pick-up-where-we-left-off state for the current working branch.
> Companion to [.claude/memory/MEMORY.md](.claude/memory/MEMORY.md) and
> [.claude/HISTORY.md](.claude/HISTORY.md).
>
> **Branch:** `launch-prep/2026-07-09` · **Last updated:** 2026-07-19

---

## 🚨 READ FIRST — possible lost work on another machine

**59 GitHub issues were closed 2026-07-09 → 07-18 citing commits that do not
exist on GitHub or on this machine.** Their closing comments read:

> *"Verified on branch `launch-prep/2026-07-09` (HEAD `feab6b1`)… See HANDOFF.md"*

Referenced commits: `feab6b1`, `13f21c4`, `f7b9e07`, `602573e`, `cd2a337`,
`50f2427`, `bad789a`, `42bebb1` — **none resolve** (`gh api …/commits/feab6b1`
returns `422 No commit found`).

**Independently verified: those fixes are NOT in the code.** Examples:

| Issue | Claimed fixed | Actual tree state |
| --- | --- | --- |
| #135 | Login `avatarURL`→`avatarPath` | `web/_functions/auth.php:219` still selects `avatarURL`; schema (`013_core_users.sql:74`) has `avatarPath` ⇒ **login broken on fresh install** |
| #136 | Registration `username` NOT NULL | `registerUser()` still omits it ⇒ **registration broken** |
| #138 | GDPR export columns | `data_rights.php` still selects `expiresAt` / `deviceType` |
| #38–#44, #75 | Phase 7 API & analytics | `public_html/api/` contains only `consent/` + `create/` |
| #45–#50 | Component C / LinksPage | still the coming-soon page only |

The old branch was **never pushed to origin** (confirmed via `git ls-remote`, the
`[new branch]` push response, and the repo activity log). Nothing was
overwritten — the work, if it exists, is intact on whichever machine wrote it
(`DEV_NOTES.md` indicates macOS is the primary dev machine; this one is a
Raspberry Pi).

### 🔧 Recovery commands — run on the OTHER dev machine

```bash
cd /path/to/Go2My.Link

# 1. Is the branch there?
git branch -a --list '*launch-prep*'
git log --oneline -3 launch-prep/2026-07-09

# 2. If deleted/reset, hunt the reflog and dangling objects
git reflog --all | grep -iE 'launch-prep|feab6b1'
git fsck --lost-found --no-reflogs | head -30
git log --oneline -3 feab6b1

# 3. Rescue to a NON-clashing ref (cannot overwrite anything on origin)
git push origin feab6b1:refs/heads/rescue/launch-prep-feab6b1
#   ...or if it is a live local branch:
git push origin launch-prep/2026-07-09:refs/heads/rescue/launch-prep-feab6b1
```

⚠️ **Do not `git push --force` to `launch-prep/2026-07-09`** — that ref now points
at the re-cut branch. Push to `rescue/…` and the two histories can be reconciled.

**Status: awaiting user recovery attempt.** Until resolved, do **not** re-implement
the 59 issues' work — it may only need a merge.

---

## 📍 Where things stand

| Branch | SHA | Contains |
| --- | --- | --- |
| `main` | `e9a86d9` | Cycle-2 hardening (#133) |
| `alpha` | `40c9291` | main + actionlint (#155) + deploy fix (#156) — via #157 |
| `launch-prep/2026-07-09` | re-cut from `alpha` | + Chunk 1 deploy hardening (#158) |

`main` and `alpha` had **diverged with neither a superset**; PR #157 re-aligned
them. `launch-prep/2026-07-09` did not previously exist on origin and was cut
fresh from `alpha`.

---

## ✅ Completed this session

### 🔀 Branch re-alignment (PR #157, merged to `alpha`)

Conflict in `.github/workflows/sftp-deploy.yml` resolved to **main's** 334-line
Cycle-2 pipeline — alpha's 89-line file was a stub that deployed nothing, and
main's version already used a `vars.SFTP_ENABLED` gate, independently satisfying
#154's intent. Net effect vs `main`: exactly `+ lint.yml`.

Also bumped `actions/setup-node` → v7.0.0 (the change from PR #134, applied to
alpha too) and aligned `lint.yml`'s `actions/checkout` pin to v7.0.0.

### 🐛 #156 — SFTP deploy was broken 100% of the time (CLOSED)

`COMMON_EXCLUDES` / `PHASE2_EXCLUDES` were interpolated **unquoted** into the
generated lftp script. lftp tokenises that script with its own lexer and treats a
bare `|` as a **pipe operator**, so `--exclude (^|/)\.git/` became `--exclude (^`
plus a pipe ⇒ `mirror: regular expression '(^': Unmatched ( or \(` ⇒ all four
mirror phases aborted.

**Fixed** by single-quoting every pattern. Verified with lftp 4.9.2 (the version
the runner installs). Fails closed, so no harm had occurred: destination
untouched, nothing uploaded, nothing pruned.

### 🚨 #158 — deploy would have wiped live server content (OPEN)

With the deploy working, a `workflow_dispatch` **dry run** on `alpha`
([run 29698411013](https://github.com/MWBMPartners/Go2My.Link/actions/runs/29698411013))
revealed **47 removals** against live Dreamhost. Two distinct causes:

- **(A)** A repo dir holding only `.gitkeep` reads as **empty** (because
  `.gitkeep` is excluded *and* `mirror:no-empty-dirs` is set), so `--delete`
  pruned the live remote counterpart: `private_html/`, `public_html_redir/`,
  per-component `_includes/` + `_libraries/`, `Lnks.page/_functions/`.
- **(B)** Paths that exist only on the server: per-component **`_auth_keys/`**
  (installer-written thin includes of the shared `auth_creds.php` — deleting
  these is an **instant total outage**), `_admin/…/pages/errors/`,
  `public_html_landing/img/`, and an entire **live brand-assets site** at
  `SFTP_BASE_PATH` (`/index.php`, `/BrandKit`, `/logos`, `/docs`, `/pwa`, …).

**Chunk 1 fix applied on this branch** (`sftp-deploy.yml`, `docs/DEPLOYMENT.md`):

1. Added directory-level excludes: `'(^|/)_auth_keys/'`, `'(^|/)private_html/'`, `'\.dh-diag$'`
2. **Removed `--delete` from the Phase-2 mirror** (additive only); kept it on the
   three tightly-scoped, repo-owned Phase-1 component web roots
3. Documented both mechanisms in the workflow header
4. Rewrote `docs/DEPLOYMENT.md`'s deployment section as the real runbook
   (it still described VS Code FTP-Sync and called GitHub Actions "Future")

**Verified locally** with lftp 4.9.2 against a seeded "server": all 13 live-only
files survived, credentials untouched, channel roots + shared backend still
deploy correctly. `actionlint` clean.

> 🔒 `vars.SFTP_ENABLED` is currently **`false`** (disarmed).

---

## ⏭️ Next steps

### 👤 Needs the user

| # | Action |
| --- | --- |
| M1 🔴 | **Recover the lost branch** (commands above), or declare it lost |
| M2 🔴 | **#93** — rotate the leaked legacy DB credential; archive `web/G2My.Link/public_html_legacy/` |
| M3 🔴 | **#158 server-side calls:** what does `SFTP_BASE_PATH` actually point at (it doubles as a live brand-site docroot)? Should Phase 2 be gated to `main` only? Then: arm → dry run → **review the removal list** → deploy |
| M4 | Professional legal review of the 5 `{{LEGAL_REVIEW_NEEDED}}` documents |
| M5 | DNS / Dreamhost docroots / SSL / `alpha.` + `beta.` subdomains |
| M6 | Create production DB → run `/install/` → execute `docs/MIGRATION_PLAN.md` → delete `install/` |

### 🤖 Autonomous (blocked on M1 — may already be done on the lost branch)

| Chunk | Work | Model |
| --- | --- | --- |
| 2 | **P0 trio** #135/#136/#138 — login, registration, GDPR export all broken vs shipped schema | sonnet |
| 3 | Tracker reconciliation (verify all 59 closures against the tree) + refresh `MEMORY.md` / `HISTORY.md` / `PROJECT_STATUS.md` / `CHANGELOG.md` | sonnet |
| 4 | #147 CSRF multi-form + #150 `setSetting` NULL-scope duplicates | sonnet |
| 5 | **`phpstan.neon` is invalid for the CI-pinned PHPStan 2.2.4** — the step dies on config and is masked by `continue-on-error: true`, so CI has run **zero** static analysis since the pin. Repair config, fix the 32 level-5 errors, flip the gate (#76) | sonnet |
| 6 | Closed-but-unfixed sweep: #109, #112, #114, #115, #116, #119, #125, #126 | haiku / sonnet |

### 📮 Post-launch (do not schedule now)

#153 phpcs conformance (7,731 errors / 108 files; 7,405 auto-fixable — re-tune
`phpcs.xml` to the house Allman/no-shorthand style *first*, then one isolated
`phpcbf` commit), #71 locales, #139–#144 / #151 / #152 enhancements, #127 / #149
decisions.

---

## 🧰 Environment notes (this machine — Raspberry Pi)

- ✅ PHP 8.4.23 CLI · MySQL server · lftp 4.9.2 · `gh` · `shellcheck` 0.10.0
- ✅ `actionlint` 1.7.12 in the session scratchpad
- ❌ No composer / phpcs / phpstan / parallel-lint installed — the CI-pinned phars
  download and run fine
- 📊 Measured: `php -l` 105/105 clean · unit tests **189/189 pass**

## ⚠️ Known tracker caveats

- Commit trailers like `Closes #156` **do not auto-close** when merged to `alpha`
  rather than the default branch — close such issues manually.
- GitHub fired **no workflow runs** for the PR #157 merge commit on `alpha`
  (no `pr_merge` entry in the repo activity log either) — an event-delivery
  hiccup on GitHub's side. The identical tree had already passed all four checks
  on the PR head, and a later `workflow_dispatch` confirmed Actions works.
