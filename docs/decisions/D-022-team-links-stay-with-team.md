# D-022 — A team keeps its links when a member leaves

- **Date:** 2026-11-28
- **Issue:** #246
- **Question:** When a member of an organisation with another active member deletes their account, what happens to links they made inside it?
- **Answer:** The links stay with the organisation and keep working, whatever the leaver chose. They are disconnected from the person.
- **Rules out:** Applying the leaver's choice to links of an organisation that still has an active member.
- **Where it is implemented:** `web/_functions/data_rights.php` (commit 302824f).
- **Supersedes:** nothing. Adds to D-017 and D-021
