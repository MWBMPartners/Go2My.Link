# D-004 — The owner installs the dev-team plugin

- **Date:** 2026-11-26
- **Issue:** #243 (decision 18)
- **Question:** The dev-team plugin is switched on in this project's settings but not installed for it. How to proceed?
- **Answer:** The owner installs it (`/plugin install dev-team@dev-team`). Until it loads, its written instructions are followed by hand, within this project's rules, and building continues.
- **Rules out:** Pausing work until the plugin is installed.
- **Where it is implemented:** Nothing in the code. Project rules for the plugin: `.claude/memory/working-rules.md`, rule 5.
- **Supersedes:** nothing
