# 🔒 Security policy

## ✅ Supported versions

Go2My.Link is a live web service, not a library people install a particular
version of. A report is welcome against anything you can reach on:

- the production sites — **go2my.link**, **g2my.link** and **lnks.page**;
- the code on the repository's **default branch**.

The pre-release branches (`alpha`, `beta`, `release-candidate`) carry work on
its way to the default branch and are not separately supported releases. A
problem you find only on one of them is still welcome through the same two
routes below — just say which branch it is on — so it can be fixed before it
ever reaches the live sites.

## 📮 How to report a vulnerability

Please use one of these two routes. **Please do not open a public GitHub
issue for a security problem** — that would tell everyone about it, including
anyone who might misuse it, before it can be fixed.

1. **GitHub's private vulnerability reporting.** On this repository's
   **Security** tab, use the **"Report a vulnerability"** button. Anyone can
   use it. Your report stays private while it is being worked on — it is
   visible to the repository's maintainers and to anyone they choose to add
   to the draft advisory, and it is not published as a public issue. If a
   security advisory is published afterwards, the maintainers write it, and
   it is normal for a published advisory to describe the vulnerability in
   general terms; tell us in your report if you would like to be credited by
   name or would rather stay anonymous. This is the preferred route. If you
   do not see that button, it has not been switched on yet — please use
   route 2 below.
2. **The in-app security report form (coming soon); until it is live, use the
   contact page at <https://go2my.link/contact> and start the subject with
   "Security".**
   <!-- SR-01 (issue #268) replaces this interim line with the form link once it ships. -->

## 📝 What to include

Whichever route you use, a report is far more useful with:

- what you did, step by step, so it can be reproduced;
- what you expected to happen, and what happened instead;
- which of the three sites or which part of the code it affects;
- how serious you think it is, and why — what an attacker could actually do
  with it;
- your account's own username or ID, if the problem needs a logged-in
  account to show (see the safe harbour below for what "your own account"
  means).

## 🔁 What happens next

These are **targets we aim for, not promises with a guaranteed time**:

- **Acknowledgement within 5 working days** — a person has seen the report
  and is looking at it.
- **A fix, or at least a plan and a timeline, within 30 days** of that
  acknowledgement. Some problems are quick; others need a bigger piece of
  work, and if so we will say so and give a realistic timeline instead of
  going quiet.

## 🛡️ Safe harbour for good-faith research

Testing for a vulnerability is welcome, and treated as authorised, as long as
it stays inside these limits:

- test only against **your own account and your own data** — never try to
  read, change or delete another person's or organisation's data;
- never run anything that could disrupt the service for other people, such
  as a denial-of-service attempt, load testing, or spamming;
- never use social engineering against staff, support, or other users;
- tell us as soon as you find something, rather than continuing to explore
  once you have proved it is real.

Research that stays inside these limits will not lead to a legal claim from
us, even where it technically involves accessing a system without prior
permission for that specific test.

## 🚫 Out of scope

The following are not treated as security reports here:

- problems in **third-party services** we merely use or link to (the payment
  provider, DNS providers, and so on) — please report those to the service
  itself;
- a finding that only works on a **rooted or jailbroken device**, or with
  browser protections deliberately turned off;
- a report that only says "there is a rate limit" or "the rate limit could be
  higher/lower" — rate limits are a deliberate design choice, not a fault, and
  are handled as ordinary feature requests instead.

<!--
  Maintainer note, not part of the published policy: an older, unrelated
  file with the same name (SECURITY.md) lives elsewhere in this repository,
  at docs/dev-team/SECURITY.md. That one is a record of a past internal
  audit from the dev-team plugin's June-July 2026 run, not a way to report
  anything new, and it is not linked from this published policy — see
  docs/dev-team/README.md for what it is.

  A `.php` (or any other language) file extension never appears in a web
  address linked from this policy — see the "No `.php` (or any language
  extension) in any web address" house rule in
  .claude/memory/working-rules.md (restated, in short form, in AGENTS.md).

  Both of the notes above used to be visible on the published page: the
  first as an opening blockquote, the second as a footnote near the bottom.
  Both put a note meant for AI coding assistants and maintainers in front of
  security researchers reading the public Security tab, where it meant
  nothing to them and only delayed them reaching the actual reporting
  routes. Moved here, together, 2026-09-22.
-->
