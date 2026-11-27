# D-010 — The owner supplies an export of the old database

- **Date:** 2026-11-27
- **Issue:** #243
- **Question:** How can the data-moving scripts be tested against the old MWlink database?
- **Answer:** The owner exports the old database to a file on their Mac, outside the project folder. It is tested only on a throwaway local database and never uploaded.
- **Rules out:** Calling the data move ready after testing on invented data only.
- **Where it is implemented:** Waiting for the export file. Adds detail to D-002.
- **Supersedes:** nothing
