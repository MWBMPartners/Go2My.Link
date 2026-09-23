# 🕓 Go2My.Link — Git History Rewrite Plan

> **DRAFT — nothing here has been run.** This document describes a plan, not a
> completed action. Every command below is written so it can be reviewed and
> copied when the time comes; none of it has been executed against this
> repository or any clone of it.

## 📋 Document Info

| Property | Value |
| --- | --- |
| **📅 Written** | 2026-09-22 |
| **🔗 Related issue** | [#242 — the owner's real name and email in tracked files](https://github.com/MWBMPartners/Go2My.Link/issues/242) (stays open until this step is carried out) |
| **✅ Decision** | Decision 15, issue #243 |
| **📄 See also** | [PRE_LAUNCH_CHECKLIST.md](../PRE_LAUNCH_CHECKLIST.md), [.github/HANDOFF.md](../.github/HANDOFF.md) |

## 📋 (a) Why, and what decision 15 says

Issue #242 found the owner's real name and email address inside six tracked
files (see the table in the issue). One of them, `.claude/README.md`, was
already fixed by a separate, earlier piece of work on this branch, committed
before this document was written. The other four —
`PRE_LAUNCH_CHECKLIST.md`, `docs/TRANSLATION.md`, `web/_functions/i18n.php`
and `tests/unit/avatar_test.php` — are fixed by the same change that adds
this document: the name and email were replaced with a placeholder name and
an `example.com` address. The sixth file, `.claude/settings.local.json`, is
deliberately left exactly as it is — see below.

Both fixes only change what a fresh clone sees **today**. Neither one
touches anything already in git's history: every earlier version of those
files, going back to when each line was first written, still holds the real
name and, in one file, the real email address. Git history cannot be edited
quietly. Once a commit has been pushed and anyone has pulled it, a copy of
it exists on their machine too, so "fixing" history is really *replacing*
history everywhere it has spread — which is why the owner's standing rule
(see `~/.claude/CLAUDE.md`) treats it as a deliberate, disruptive operation
that is never done without an explicit decision.

**Decision 15 (issue #243)** is that decision: git history **will** be
rewritten, but only as a **separate step, after the working branch that
carries this plan has been merged**, and only once the owner has been asked
**once more, immediately before it is run** — this document existing is not
that approval. `.claude/settings.local.json` stays excluded from the rewrite
in the same way it stays excluded from the current-files fix — but the
reason is not that it is untracked. It IS tracked: it was deliberately added
to tracking on 2026-07-20, and `.gitignore` on the branches that carry the
file lists it anyway (a path listed in `.gitignore` has no effect once the
file is already tracked — checked directly: `git ls-files` finds it and
`git check-ignore` reports nothing for it on this branch). Decision 14 is
that it stays tracked, and stays exactly as it is, on purpose. That is also
why section (e) below has to skip this one path commit by commit, rather
than relying on it never having been in the repository at all.

## 🔒 (b) Preconditions — all of these before the rewrite is run

- [ ] Every open pull request against this repository is either merged or
      closed. A rewrite changes the commit IDs of everything on every
      branch it touches; a pull request open across the rewrite would be
      comparing against commits that no longer exist on the target branch.
- [ ] Nobody has unpushed local work anywhere. Any commit that exists only
      on someone's machine and not on GitHub will not be included in the
      rewrite and will silently vanish (from the rewritten history's point
      of view) the moment that person's next push is rejected and they
      re-clone.
- [ ] A full mirror backup exists and is kept **offline** (see the exact
      command in section (c)) — the one and only way back if anything goes
      wrong (see section (i)).
- [ ] The GitHub branch protection rule that blocks force-pushes is
      temporarily lifted **by the owner**, for the one push in section (g),
      and restored immediately afterwards. Nobody else can do this step;
      it is a repository-settings change, not a git command.

## 🛠️ (c) Tool: git-filter-repo, run locally, never on the server

The rewrite uses [`git-filter-repo`](https://github.com/newren/git-filter-repo),
not the older `git filter-branch` (which the tool's own documentation warns
against for exactly this kind of content rewrite — it is slow and easy to
get subtly wrong) and not BFG Repo-Cleaner. BFG does have a way to leave a
named file out of a text replacement (`--filter-content-excluding` /
`-fe`), so that is not the reason it is rejected here — the real reason is
that BFG's exclusion matches on the file's bare **name**, not its full
**path**, so there is no way to tie it to the exact tracked path
`.claude/settings.local.json` specifically (as opposed to any other file
that happened to be called `settings.local.json` anywhere in the tree); BFG
also protects the most recent commit on the `HEAD` branch by default — its
`--protect-blobs-from` option defaults to `HEAD` (checked directly against
BFG's own source, `CLIConfig.scala`: `protectBlobsFromRevisions: Set[String]
= Set("HEAD")`), so it would need every affected ref listed by hand to reach
history the way this rewrite has to. `git-filter-repo`'s
`--file-info-callback` (section (e) below) matches on the exact path
instead, which is what this rewrite needs.

Install a recent version — **2.47.0 or later**, because that is the release
that added the `--file-info-callback` hook this plan relies on (checked
directly against the tool's own source at each tagged release: neither
2.38.0 nor 2.45.0 contains any mention of `file-info-callback` or
`file_info`; 2.47.0 is the first tag that does):

```bash
pip install --user "git-filter-repo>=2.47"
```

The rewrite runs on a **fresh mirror clone**, on **the owner's own
computer**, never against the live GitHub repository directly and never
from a CI runner or any shared machine:

```bash
# A mirror clone carries every branch, tag and ref — a normal clone does not.
git clone --mirror git@github.com:MWBMPartners/Go2My.Link.git g2ml-history-rewrite.git

# The offline backup mentioned in the preconditions above — a second,
# separate copy, made BEFORE filter-repo touches anything, kept somewhere
# that is not this working copy (a different disk, or unplugged storage).
cp -R g2ml-history-rewrite.git g2ml-history-rewrite-BACKUP.git
```

## 🔒 (d) The replacement list — kept OUTSIDE the repository

`git-filter-repo` needs to be told what to replace. That list must never
itself be committed anywhere — doing so would just write the real name and
email into a new tracked file, undoing the whole point of this exercise. It
lives on disk, outside any git working copy, for example:

```
~/.config/g2ml-history-rewrite/replacements.txt
```

Its format (using `<REAL_EMAIL>`, `<REAL_FULL_NAME>` and `<REAL_SURNAME>`
here as placeholders for what the real file actually contains — the real
email address and real names found by issue #242):

```
<REAL_EMAIL>==>owner@example.com
<REAL_FULL_NAME>==>Salem874
<REAL_SURNAME>==>Salem874
```

⚠️ **Never put the first name on its own in this file.** `git-filter-repo`'s
replacement rules match **plain substrings**, with no word boundaries, so a
bare first name rewrites it wherever those letters appear inside ordinary
words, silently corrupting unrelated content throughout history. This is not
hypothetical: this project's own files contain the words `balanced` (in the
privacy text in `web/_sql/seeds/010_phase6_translations.sql`) and the icon
class `fa-balance-scale` (on the cookies page). With the first name alone in
the list, the rewrite would turn them into `baSalem874d` and
`fa-baSalem874-scale`, breaking the page's styling and the wording of a legal
page — and it would do it quietly, in every commit, where nobody would think
to look. (Found by Codex's review of this document on 2026-09-22; four
earlier review rounds missed it.)

So: replace the **full name** and the **surname**, which are distinctive
enough to be safe, and the **email address**, which is exact. The first name
on its own is handled differently: search for it (section (j) shows how to do
that without printing it), decide case by case whether each hit is really the
owner's name rather than part of a word, and list only the exact surrounding
strings that must change — for example `<REAL_FIRST> <REAL_SURNAME>` or
`<REAL_FIRST>'s` — as their own lines in this same file.

Before running the rewrite for real, prove the list is safe on the practice
copy: run it, then `git grep -c "Salem874"` across the rewritten history and
read every file it names. Anything that is not a name, an author line or an
email address means a rule is matching too widely, and the list has to be
narrowed before the real run.

**The email line has to come before the name line, and this order is not
cosmetic.** `git-filter-repo` applies these rules strictly in the order they
appear in the file — checked directly against the tool's own source (tag
v2.47.0): `FilteringOptions.get_replace_text()` just appends each rule to a
plain list, with no sorting and no "longest match first" handling, and
`FileInfoValueHelper.apply_replace_text()` walks that same list in that same
order, doing a plain `contents.replace(literal, replacement)` for each one in
turn. The owner's real first name is a substring of the owner's real email
address. If the name rule ran first, it would rewrite the name *inside* the
email address before the email rule ever got a chance to match — the address
would come out with the owner's username glued onto the front and the real
mail domain still attached to it, which reads exactly like a working address
because it very nearly is one. Putting the longer, more specific email rule
first means it consumes the whole address before the name rule can touch any
part of it. `git-filter-repo`'s `--replace-text` option reads a file in
exactly this `old==>new` form. Every case-variant the original issue found
(see #242's table) needs its own line, because the replacement is a literal
match, not case-insensitive — and every one of those name-variant lines goes
**below** every email line, for the same reason: a name variant sitting above
an email line would swallow the address in the same way.

## 🔒 (e) Keeping `.claude/settings.local.json` unchanged

`--replace-text` on its own applies to the contents of **every** file in
every commit — there is no built-in "skip this one path" option. That is
the wrong tool for this repository, because the owner's decision (14 and
15, issue #243) is that `.claude/settings.local.json` stays exactly as it
is, in the current tree **and** across the whole rewritten history.

The fix is `--file-info-callback`, a small Python function `git-filter-repo`
calls once per file, per commit, before it decides what to do with that
file's contents. Getting this callback's shape right matters more than
anything else in this document, because it is the one piece nobody can
safely improvise on the day — so this section was checked directly against
`git-filter-repo`'s own source (tag v2.47.0) rather than written from
memory. **An earlier draft of this section had the wrong shape entirely** —
a four-argument `(filename, commit_metadata, blob, replacements)` signature
returning a bare `blob`, with the body doing `blob.replace(old, new)`
directly. That does not match the real tool and would fail immediately if
copied on the day: the real callback receives `blob_id` (an internal
content identifier), not the file's actual bytes, so calling `.replace()`
on it would be a string replace on a hash, and returning a single value
instead of the required 3-tuple `(filename, mode, blob_id)` is rejected
outright. That wrong version is recorded here so nobody re-introduces it
later; the correct one is below.

The real signature is `def file_info_callback(filename, mode, blob_id,
value):`, and it must return the tuple `(filename, mode, blob_id)`. To read
or change a file's contents, the callback goes through `value`: reading is
`value.get_contents_by_identifier(blob_id)`, and writing back changed
content is `value.insert_file_with_contents(new_contents)`, which returns a
new `blob_id` — an unchanged `blob_id` means git-filter-repo writes that
file back byte-for-byte, preserving its history and blob hashes. The literal
replacements themselves are **not** hand-rolled in the callback; the
callback calls `value.apply_replace_text(contents)`, which is the same
logic `--replace-text` uses, reading its rules from the replacements file
in section (d). That means this plan runs `git-filter-repo` with **both**
`--replace-text <replacements file>` **and** `--file-info-callback
<callback file>` together — the two are not alternatives, and combining
them is the supported way to get "apply these replacements, except in this
one file": `--file-info-callback` is documented as incompatible only with
`--stdin`, `--blob-callback` and `--filename-callback`, not with
`--replace-text`. (`--file-info-callback` takes the callback's function
**body** — or a file containing one — not a full `def` line; the `def`
line below is shown only so the outline reads as a complete function.)

Supplying a file-info callback has a side effect that has to be undone by
hand, and missing it would be serious: it would silently corrupt every
binary file in the repository. Checked directly against `git-filter-repo`'s
own source (tag v2.47.0), and the two protections work differently from
each other, not the same way:

- With `--replace-text` alone, git-filter-repo still streams every blob's
  contents through `_tweak_blob()` so it can run the replacement — but that
  function skips a blob outright, unchanged, when its first 8 KB contains a
  zero byte (git's own test for "this is binary data"). That skip is
  guarded by `and not self._file_info_callback`, so supplying a callback
  switches it off.
- Separately, `_setup_input()` decides whether to run `git fast-export`
  with `--no-data` at all — skipping blob contents from the export
  entirely — and a file-info callback being present is one of the
  conditions that **turns `--no-data` on**, not off: with `--replace-text`
  and no callback, git-filter-repo needs blob contents to do the text
  replacement, so it fetches them; once a callback is supplied,
  git-filter-repo hands content-fetching over to the callback itself and
  asks `git fast-export` not to include blob data at all.

So the practical effect of adding the callback is the opposite of a hole
opening up in blob handling: it is git-filter-repo stepping back and
making the callback the *only* place any file's contents — text or
binary — are read and possibly rewritten. That is exactly why the callback
below has to redo the zero-byte check itself, using `value.is_binary(contents)`
— which runs the identical check — before it calls `apply_replace_text()`.
Skipping this would mean the callback swaps bytes inside this repository's
203 tracked binary files too (the BrandKit PNG icons, fonts and so on): a
PNG's chunk lengths and checksums are computed over its exact bytes, so
changing any of them produces a corrupted image that nothing in section
(j)'s checklist would catch.

The plan is to pass a callback whose body reads, in outline (again with
`<REAL_NAME>` / `<REAL_EMAIL>` standing in for the real values, which
`--replace-text` loads from the replacements file in section (d) rather
than having them typed into this document or into the callback itself):

```python
def file_info_callback(filename, mode, blob_id, value):
    # filename arrives as bytes; compare against the exact tracked path.
    if filename == b'.claude/settings.local.json':
        # This file is excluded from the rewrite entirely — decision 14/15
        # (issue #243) is that it stays exactly as it is, in every commit,
        # not just the current tree. Returning blob_id unchanged means
        # git-filter-repo writes this file back byte-for-byte, so its own
        # history (and its own blob hashes, for commits that only touch
        # this file) is preserved.
        return (filename, mode, blob_id)

    contents = value.get_contents_by_identifier(blob_id)

    # --replace-text never rewrites binary files on its own (see the
    # explanation above this code block) — a zero byte anywhere in the
    # first 8 KB is git's own test for "this is binary". Supplying this
    # callback switches that automatic skip off, so it has to be repeated
    # here, or the BrandKit PNGs and fonts would have bytes swapped inside
    # them and come out corrupted. value.is_binary() runs the identical
    # check. contents can also be None for a blob git-filter-repo has no
    # data for; that is left unchanged too, for the same reason.
    if contents is None or value.is_binary(contents):
        return (filename, mode, blob_id)

    # Every other, non-binary file gets the ordinary literal replacements.
    # These come from --replace-text (see the replacements file in section
    # (d)), not from anything hard-coded here — apply_replace_text() is the
    # same matching logic --replace-text runs on its own. This callback
    # adds two things around that shared logic: the one-file exception
    # above, and the binary-file skip just above, which restores what
    # --replace-text provides for free when it is used without a callback.
    new_contents = value.apply_replace_text(contents)
    if new_contents != contents:
        blob_id = value.insert_file_with_contents(new_contents)
    return (filename, mode, blob_id)
```

`git-filter-repo` is invoked with this callback **and** `--replace-text`
together, against the mirror clone from section (c) — never
`--replace-text` alone, because that has no way to leave one file out, and
never the callback alone, because `apply_replace_text()` is what actually
reads the replacements file.

**Commit author and committer names are not touched by this plan.** Decision
14 treats the git identity configured on each contributor's own machine as
a local fact, not something written *into* the repository, so it is out of
scope here — unless the owner separately asks for it, in which case the
right tool is `git-filter-repo --mailmap <file>`, which rewrites the author
and committer identity recorded on each commit using a standard
[`.mailmap`](https://git-scm.com/docs/git-check-mailmap) file, also kept
outside the repository. That is a different, larger decision (it changes
every commit's authorship metadata, not file contents) and is not part of
this plan unless it is asked for.

## 🔗 (f) Commit-ID remapping — docs and GitHub both quote old IDs

Rewriting history gives every affected commit a new ID (its SHA-1 hash
changes, because the hash covers the file contents the rewrite just
changed). `git-filter-repo` itself keeps a record of the mapping at
`$GIT_DIR/filter-repo/commit-map` — one line per commit, old ID then new
ID. In the **bare mirror clone** this plan uses (section (c)), `$GIT_DIR`
is the clone directory itself, because a bare repository has no separate
`.git` subdirectory — so on the day this is `g2ml-history-rewrite.git/
filter-repo/commit-map`, **not** `.git/filter-repo/commit-map` (that path
does not exist in a mirror clone at all).

Two places in this project already quote short commit IDs and would be
pointing at commits that no longer exist once the rewrite runs. This plan
describes a follow-up script for both — **not written into the repository**,
kept as a local script the owner runs by hand, because it is a one-time
tool for the day of the rewrite, not part of the ongoing codebase:

1. **Tracked documentation.** `.claude/HISTORY.md`, `.github/HANDOFF.md`,
   every file under `docs/*.md`, `CHANGELOG.md` (if the project has one by
   then) and `PRE_LAUNCH_CHECKLIST.md` are searched for anything that looks
   like a 7–40 character hexadecimal commit ID. Each one found is looked up
   in `commit-map` and rewritten to its new ID. A **short** ID (fewer than
   40 characters) is only rewritten when it matches **exactly one** entry
   in the map — an ambiguous short ID is left alone and reported, not
   guessed at. Because `.claude/HISTORY.md` is one of those files, and
   `.OpenAI/HISTORY.md` is a generated copy of it, run
   `sh scripts/sync-ai-context.sh` after the rewriting and include what it
   changes in the same commit — otherwise the Codex copy keeps the old
   commit IDs and the "AI Context Mirror" check on GitHub fails. (Codex's
   review of this document raised this on 2026-09-22.) The result lands in a
   single follow-up commit, made after the rewritten history has already
   been pushed (section (g)), so this commit exists only in the new history
   and never needs rewriting itself.

2. **GitHub issues and pull requests.** Every issue, pull request body and
   comment **written by the account `Salem874`** (the account the owner
   and this project's automation both write as) is listed with `gh api`,
   paginated so nothing is missed on a repository this size. Each one is
   searched for a quoted commit ID using the same hex-ID pattern as above.
   For every match, the script first prints a **dry-run diff** — the exact
   text before and after — so the owner can read every proposed change
   before anything is sent. Only once that has been reviewed does the
   script `PATCH` the issue or comment through the GitHub API to swap the
   old ID for the new one. Nothing is patched automatically without that
   review step.

## 🚀 (g) Push — by explicit owner instruction only

Once the rewrite has been run and checked (section (j)), the new history
replaces what is on GitHub.

`git-filter-repo` deliberately removes the `origin` remote from the mirror
clone once it finishes rewriting — its own documentation says this is on
purpose, so that nobody pushes a rewritten history back to the original
repository by accident. Re-adding the remote is therefore not a leftover
step but the deliberate, one-time act of choosing to do exactly that, on
the day, with the owner's go-ahead:

```bash
# git-filter-repo removed this remote on purpose when it finished rewriting,
# so that a rewritten mirror can never be pushed back by accident. Adding it
# back here IS the deliberate decision to publish the rewrite — do this line
# only once everything in section (j) that runs before the push is ready.
git remote add origin git@github.com:MWBMPartners/Go2My.Link.git

git push --force --mirror origin
```

This is a force-push of **every** ref in the mirror — every branch and
every tag — which is exactly why the branch-protection force-push block
has to be lifted first (precondition in section (b)) and exactly why this
step is never run automatically or as part of any other piece of work. It
runs once, on the owner's own instruction, on the day the owner has agreed
to it — not as a consequence of writing or approving this document.

After the push, **every existing clone is stale**, including any clone on
CI runners, other computers, or other AI sessions' working copies. The only
correct next step for each of them is to re-clone from scratch; trying to
`git pull` a rewritten history into an old clone produces a tangle of
conflicting history that is not worth untangling.

## ⚠️ (h) What this cannot clean up

This plan removes the name and email from every commit reachable from a
branch or tag once the push in (g) has completed. It cannot reach two other
places copies persist:

- **GitHub's own cached views.** GitHub keeps commits reachable from closed
  pull requests' merge refs, and caches diffs and file views, for some time
  after the branches that pointed at them are gone. Actually removing that
  requires a support request to GitHub — see their
  ["Private Information Removal Policy"](https://docs.github.com/en/site-policy/content-removal-policies/github-private-information-removal-policy)
  (GitHub renamed this policy; the older "sensitive data removal" name and
  URL no longer resolve) — and is a separate step the owner would need to
  raise directly with GitHub, not something this repository's tooling can
  do.
- **Forks.** Anyone who forked the repository before the rewrite keeps
  their own copy of the old history, on their own account, and nothing run
  against `MWBMPartners/Go2My.Link` reaches it. There is no general
  technical fix for this; it is a reason the current-files fix (issue #242)
  matters on its own, even before history is ever rewritten.

## ⏪ (i) Rollback

If anything about the rewrite turns out wrong — a file that should have
been left alone was changed, a commit that should exist is missing, the
push in (g) went to the wrong place — the recovery is the offline mirror
backup made in section (c), and only that backup:

```bash
# From the untouched backup, force-push the original history straight back.
cd g2ml-history-rewrite-BACKUP.git
git push --force --mirror origin
```

This is why the backup is made **before** `git-filter-repo` runs and kept
**offline** (not just in another folder on the same disk) — so a mistake in
the rewrite itself cannot also destroy the only way back from it.

## ✅ (j) Checklist to run immediately after the push

- [ ] Repeat the step-3 search from the current-files fix — the same
      `git grep` commands issue #242's fix used — against **every branch**
      of the freshly re-cloned repository. `git grep <pattern> <branch>`
      only looks at that branch's tip, so on its own this proves just one
      thing: that no branch was missed out of the rewrite entirely. It does
      **not** prove history itself is clean — the current files were
      already fixed by the earlier change that added this document, before
      any rewrite runs, so a tip-only search would pass even if the
      rewrite silently did nothing.
- [ ] Search **every reachable commit**, not just branch tips, on the same
      freshly re-cloned repository — this is the check that actually
      covers history, which is the entire purpose of the rewrite. Use the
      same shell variables the step-3 search used (`$F` for the first
      name, `$S` for the surname), but a shell variable only keeps the
      real name and email out of the **search pattern** — it does nothing
      about the **output** of the command that runs it. Step 3 stayed safe
      by adding `-q` (quiet: prints nothing, just an exit code); the two
      commands below need the same kind of care, because run as plain
      content searches they would print the very thing this checklist item
      exists to rule out:
      ```bash
      # Pass/fail check, safe to run as-is: -q stops at the first match
      # anywhere in the history and prints nothing but the exit code.
      # 1 (nothing found) is the expected, passing result.
      git grep -q -I -i -w "$F" $(git rev-list --all) -- ':!.claude/settings.local.json'
      echo $?
      ```
      If that ever comes back `0` (a match exists somewhere) and the
      commit needs identifying so it can be investigated, use `-l`
      instead of `-q` — it lists the matching commit and file path, never
      the line itself:
      ```bash
      git grep -l -I -i -w "$F" $(git rev-list --all) -- ':!.claude/settings.local.json'
      ```
      The pickaxe framing of the same search is `git log --all --oneline
      -S"$F" -- ':!.claude/settings.local.json'` — it needs the identical
      pathspec exclusion for the identical reason (without it, the search
      would also match the commits that touch `.claude/settings.local.json`,
      the one file this plan deliberately leaves holding the real values),
      and it must never be run with `-p`: `-p` prints the full patch body
      of every matching commit, which is exactly the real name or email
      this whole exercise exists to keep off the screen. `--oneline`
      prints only each matching commit's hash and subject line. Repeat
      all of this for `$S` and for `mwmail`. Whatever the result, if a
      command ever has to show more than a bare hash or file path —
      for example while chasing down a match that should not exist — pipe
      it through the same masking `sed -E "s/$F/<NAME>/Ig"` step 1 used,
      before it reaches a terminal or a log. Confirm the name, the
      surname and the email address are gone from every reachable commit
      except `.claude/settings.local.json`, which is deliberately excluded
      (see section (e)).
- [ ] Confirm CI is green on a fresh clone of the default branch — the
      rewrite must not have altered any file's content outside the
      intended replacements, and a fresh clone building successfully is
      the most direct proof of that.
- [ ] Read the commit-ID remap script's report from section (f) in full,
      confirming every quoted commit ID in the tracked docs and in GitHub
      issues/pull requests was either updated or explicitly reported as
      ambiguous and left alone — nothing silently wrong.
