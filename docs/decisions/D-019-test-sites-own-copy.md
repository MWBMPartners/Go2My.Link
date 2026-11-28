# D-019 — Alpha and beta each get their own full copy of the program files

- **Date:** 2026-11-28
- **Issue:** #158
- **Question:** How can the alpha test site work when only the live branch updates the shared program files?
- **Answer:** Alpha and beta each get a complete separate copy inside the service folder and never touch the live site's files.
- **Rules out:** Test sites sharing or updating the live program files; having no test site.
- **Where it is implemented:** Not yet built.
- **Supersedes:** nothing. Adds to D-007
