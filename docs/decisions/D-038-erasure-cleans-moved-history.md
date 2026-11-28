# D-038 — Erasing a moved person removes their identifiers from old history

- **Date:** 2026-11-28
- **Issue:** #247
- **Question:** Old history is copied exactly (D-018). What happens to a moved person's old values when they are erased?
- **Answer:** Erasure strips their username, old key, IP address and browser details from their old rows, as for new ones. The move still copies everything.
- **Rules out:** Keeping old identifiers after erasure.
- **Where it is implemented:** Not yet built.
- **Supersedes:** nothing. Limits what D-018 keeps after erasure
