# D-002 — New install moves the old data too

- **Date:** 2026-11-26
- **Issue:** #243
- **Question:** What does the new install start with?
- **Answer:** It must also bring across the existing links and user accounts from the older service (MWlink).
- **Rules out:** Calling the install ready when it only works on an empty database. The data-moving scripts must be tested and ready first.
- **Where it is implemented:** Not yet done. Scripts are in `web/_sql/migrations/`; plan in `docs/MIGRATION_PLAN.md`.
- **Supersedes:** nothing
