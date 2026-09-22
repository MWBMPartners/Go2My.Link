# 🗂️ docs/dev-team/ — the dev-team plugin's old run records

> Moved here from the repository root and from `.dev-team/` on 2026-09-22 (owner decision 19, [issue #243](https://github.com/MWBMPartners/Go2My.Link/issues/243); the move itself is [issue #249](https://github.com/MWBMPartners/Go2My.Link/issues/249)).

## 📋 What these four files are

The **dev-team plugin** is a Claude Code helper that plans, builds, reviews and
documents with teams of AI agents (see
[`.claude/memory/working-rules.md`](../../.claude/memory/working-rules.md), rule
5, for how this project uses it). While it works, it keeps its own running
notes in a fixed set of files. The four kept here are the ones it wrote during
its **June–July 2026** run:

| File | What the plugin used it for |
| --- | --- |
| [`PROJECT.md`](PROJECT.md) | The human-readable narrative of that run: goals, phases, decisions, and a cycle-by-cycle trajectory log. |
| [`FEATURES.md`](FEATURES.md) | The feature-gap ledger its `dev-team-featurefind` skill produced — what the product was missing, scored against the project brief and the open issues at the time, not a fresh, open-ended competitor scan (the file itself says its comparables are category norms from the brief, "not researched fresh"). |
| [`SECURITY.md`](SECURITY.md) | The findings register from that run's security audit — a threat model, an attack-surface map, and a list of findings with their fix state. It reads like a security policy title, but **it is not one** — see the warning below. |
| [`autopilot.json`](autopilot.json) | The machine-readable state file the `dev-team-autopilot` skill reads and writes on every loop iteration — cursors, the work queue, the gate ledger, and the budget. |

## 📌 Why they are kept, and why they are not the live plan

These four files are a **historical record of a finished run**, kept because
`SECURITY.md` especially holds findings and fixes worth being able to look
back on. They record that finished run and are **not kept in step with the
product** any more — a later cycle's work is never copied back over them, and
`autopilot.json` in particular is a snapshot of where that one run's loop
stopped, not a live state file. A later dated note has, on occasion, been
added by hand where leaving the old text would mislead: `SECURITY.md` carries
a 2026-07-19 warning that the attack surface has grown since its snapshot,
and `PROJECT.md`'s opening blockquote says where the live state file has
gone. Those hand-added notes are the exception, not routine upkeep, and they
are **not** where this project's current plan or state lives. For that,
read:

- **[`.github/HANDOFF.md`](../../.github/HANDOFF.md)** — the one live session
  handoff: what is done, in flight, and next.
- **This repository's GitHub issues** — the live work queue, one issue per
  piece of work.

## 📎 Paths quoted inside these files

These four files are a frozen record of a finished run (see above), so their
text is left as the plugin wrote it rather than being rewritten to match the
move. That is not the same as saying nothing in them has ever changed since:
as noted above, a short dated note has been added by hand in
[`PROJECT.md`](PROJECT.md)'s opening blockquote and in
[`SECURITY.md`](SECURITY.md), each time only because leaving the old text in
place would have been actively misleading rather than just dated — never to
bring the file up to date with the product. A few of the four quote a path
such as
`` `docs/AUDIT_2026-06-04.md` `` or
`` `tests/README.md` `` in running prose — those are not markdown links (none
of the four files contain any), just inline code spans, so nothing on GitHub
renders them as clickable and nothing is broken by leaving them. Read every
such path as relative to the **repository root**, which is where it was true
when written and still is now — not relative to this `docs/dev-team/` folder.

## 🔄 Fresh copies the plugin writes

A skill that writes one of these four files writes it at its **original**
location again — `PROJECT.md`, `FEATURES.md` or `SECURITY.md` at the
repository root, or `.dev-team/autopilot.json` — not every skill writes every
one of them on a given run. Since this move, `.gitignore` excludes exactly
those root paths, so a fresh copy is a local scratch file the plugin can read
and write freely, and it is never committed. It does not touch anything in
this folder. If a plugin run is worth keeping, its result is brought across by
hand as an ordinary, reviewed piece of work — never by copying its scratch
files over the ones kept here.

## ⚠️ `SECURITY.md` here is not the vulnerability-reporting policy

`docs/dev-team/SECURITY.md` is the **old findings register** described above —
a look back at what one security audit found and fixed. Anyone who wants to
**report a new vulnerability** should read
**[`.github/SECURITY.md`](../../.github/SECURITY.md)** instead: that is the
file GitHub itself shows on the repository's Security tab, and it names the
two ways to make a report.
