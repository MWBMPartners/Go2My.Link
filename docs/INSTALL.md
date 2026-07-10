# 🧰 Go2My.Link — Installation Guide

> A browser-based installer stands up a fresh Go2My.Link deployment for **all
> three components** (Go2My.Link, G2My.Link, Lnks.page), which share one
> database and a single server-wide credentials file.

## 📋 Prerequisites

- PHP **8.4+** with the `mysqli`, `openssl`, and `mbstring` extensions, and
  Argon2id password hashing (`PASSWORD_ARGON2ID`).
- An **empty MySQL/MariaDB database** already created in your hosting panel
  (on Dreamhost: *Goodies → MySQL Databases*). Note its host (e.g.
  `mysql.yourdomain.com`), name, user, and password.
- The repository deployed so that `web/_auth_keys/` is **writable** by PHP and
  **outside** every public web root (it already sits above each
  `public_html/`).

## 🚀 Running the installer

1. Deploy the codebase (the installer lives at
   `web/Go2My.Link/public_html/install/`).
2. Browse to **`https://go2my.link/install/`**. The installer **requires HTTPS**
   (it refuses to accept a password over plain HTTP) and is **proof-of-control
   gated**: on first load it writes a one-time token to
   `web/_auth_keys/.install_token` on the server, which you must read off disk
   and paste into the wizard — so nobody who cannot read the server filesystem
   can drive it.
3. Work through the wizard:
   1. **Requirements** — environment checks, then paste the installer token.
   2. **Database** — enter the connection details; the installer tests them
      live.
   3. **Import** — imports every schema table, stored procedure, and seed file
      into your database (`CREATE TABLE IF NOT EXISTS`, so it is safe to re-run).
   4. **Admin** — create the system-wide **GlobalAdmin** account.
   5. **Finish** — the installer **generates fresh encryption keys**, writes the
      shared `web/_auth_keys/auth_creds.php`, ensures each component includes it,
      and locks itself.

The installer writes **one** shared credentials file
(`web/_auth_keys/auth_creds.php`); each component's
`<Component>/.auth/auth_creds.php` simply includes it, so all three sites
use the same database and keys automatically.

## 🔒 After installation (do this immediately)

1. **Delete the entire `install/` directory** from the web root.
2. Confirm `web/_auth_keys/auth_creds.php` is outside the web root and not
   world-readable (the installer writes it `0600`).
3. Sign in to the admin dashboard at **`https://admin.go2my.link/`** with the
   GlobalAdmin account you created.
4. Configure third-party keys (Turnstile/reCAPTCHA, OAuth) from **Admin →
   Settings**, or add them to `auth_creds.php`.
5. 🛡️ **Behind a reverse proxy?** Only then, define the OPTIONAL
   `TRUSTED_PROXIES` constant in `auth_creds.php` (comma-separated proxy
   IPs/CIDRs). Left undefined (the Dreamhost default), `g2ml_getClientIP()`
   ignores forged `X-Forwarded-For` / `X-Real-Ip` headers and uses
   `REMOTE_ADDR`, so audit logging and per-IP rate limits cannot be spoofed.

> ⚠️ The installer refuses to run once `web/_auth_keys/.installed` exists. To
> re-run it intentionally, remove that lock file first — only on a host you
> control.

## 🧯 Manual / fallback setup

If you cannot use the web installer (e.g. you prefer importing SQL via
phpMyAdmin or the Dreamhost panel), see [`docs/MIGRATION_PLAN.md`](MIGRATION_PLAN.md)
and [`docs/DATABASE.md`](DATABASE.md). After importing `web/_sql/schema/*`,
`web/_sql/procedures/*`, and `web/_sql/seeds/*`, copy
`web/_auth_keys/auth_creds.php`, fill in the DB constants, and generate the two
encryption keys with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # run twice
```
