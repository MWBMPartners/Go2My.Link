# D-007 — Automatic upload goes to a folder used only by this service

- **Date:** 2026-11-27
- **Issue:** #158
- **Question:** How does the first version get onto Dreamhost?
- **Answer:** The owner makes a folder used only by this service. Uploads run from GitHub, and only the `main` (live) branch updates the shared program files.
- **Rules out:** Uploading into the folder that also holds the brand-assets website; other branches updating the shared program files; uploading by hand as the normal route.
- **Where it is implemented:** Not yet built.
- **Supersedes:** nothing
