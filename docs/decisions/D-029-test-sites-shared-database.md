# D-029 — Test sites share the live database, with separate fields or tables where behaviour differs

- **Date:** 2026-11-28
- **Issue:** #158
- **Question:** Should alpha and beta use their own database?
- **Answer:** In the owner's words: "Shared data but where possible (for feature behaviour diffs. Create way to differentiate those fields for alpha/beta as separate fields/tables?". One shared database; where alpha or beta need to behave differently, keep their values apart from the live ones.
- **Rules out:** A separate test database as the arrangement.
- **Where it is implemented:** Not yet built; the detail is to be confirmed with the owner.
- **Supersedes:** nothing. Adds to D-019
