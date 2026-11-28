# D-042 — The CAPTCHA is fixed before launch, allowing the provider's addresses

- **Date:** 2026-11-28
- **Issue:** #334
- **Question:** The "are you human" check is not verified on the sign-in and forgot-password pages, and the site's browser security rules probably block its check box. Fix both before launch, allowing pages to load scripts and frames from the provider's own addresses?
- **Answer:** Fix both. The answer is verified on every protected form; only the chosen provider's addresses are allowed, only on the pages that show the box.
- **Rules out:** Launching without CAPTCHA; allowing provider addresses site-wide or for a provider that is not configured.
- **Where it is implemented:** Not yet built.
- **Supersedes:** nothing
