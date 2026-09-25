# 🧪 Go2My.Link — Test Harness

> A **lightweight, pure-PHP** test harness for Go2My.Link. No Composer, no
> PHPUnit — it runs anywhere PHP runs, including **Dreamhost shared hosting**.
> The first batch of tests are **characterization tests**: they pin down the
> code's *current* behaviour so future refactors are caught, rather than
> asserting aspirational behaviour.

## 📁 Layout

| Path | Purpose |
|---|---|
| `tests/bootstrap.php` | The micro-framework: `test()` registrar, assertion helpers, results collector. No external dependencies. |
| `tests/run.php` | Discovers and runs every `tests/unit/*.php` (**DB-free**). Exits non-zero on any failure so CI can gate. |
| `tests/run_integration.php` | Discovers and runs every `tests/integration/*.php` against a MySQL server from environment variables. **Skips cleanly (exit 0) when no DB is reachable.** |
| `tests/unit/` | DB-free characterization tests for `web/_functions/security.php`. |
| `tests/unit/direct_access_guard_test.php` | Regression test for #198 — proves a library's direct-access guard now tells apart two files that merely share a name, by requiring the library in a real child PHP process with `SCRIPT_FILENAME` set the way Apache would set it. |
| `tests/unit/no_php_in_urls_test.php` | Check for #203 — fails the build when it finds a link, form target, redirect or JavaScript request in the shipping code under `web/` that points at an address ending in `.php`. See "No .php in web addresses" below. |
| `tests/integration/` | DB-backed characterization smoke tests for the redirect hot path (`sp_lookupShortURL`). |

## 🧩 Assertion helpers

Available inside any `test('name', function () { ... })` body:

- `assert_true($condition)` / `assert_false($condition)` — strict boolean check.
- `assert_same($expected, $actual)` — strict `===` equality.
- `assert_not_same($expected, $actual)` — strict `!==` inequality.
- `assert_throws($callback, $expectedClass = null)` — the callback must throw (optionally of a given class).
- `assert_contains($needle, $haystack)` — substring check.

Each accepts an optional final `$message` string for context.

## ▶️ Running the unit suite (no database needed)

```bash
php tests/run.php
```

Prints a `PASS`/`FAIL` line per test and a summary such as `35 passed, 0 failed`.
Exits `0` when everything passes, `1` if anything fails.

The unit runner defines throwaway encryption constants (`ENCRYPTION_SALT`,
`ENCRYPTION_KEY_SECONDARY`) of valid length **before** loading any application
file, and initialises `$_SESSION` as a plain array so the CSRF helpers work
without an active PHP session. It never touches `web/_auth_keys`.

## ▶️ Running the integration suite (needs a MySQL test database)

The integration runner reads its connection details from environment
variables. If it cannot connect, it prints `SKIPPED (no test DB)` and exits
`0` — it never hard-fails merely because a test DB is unconfigured.

| Variable | Meaning | Default |
|---|---|---|
| `G2ML_TEST_DB_SOCKET` | Unix socket path (preferred locally) | *(none)* |
| `G2ML_TEST_DB_HOST` | Host name (used when no socket) | `127.0.0.1` |
| `G2ML_TEST_DB_PORT` | Port (used with a host) | `3306` |
| `G2ML_TEST_DB_NAME` | Database / schema name | `mwtools_Go2MyLink` |
| `G2ML_TEST_DB_USER` | User | `root` |
| `G2ML_TEST_DB_PASS` | Password | *(empty)* |

### One-liner: stand up a throwaway MySQL, import, run, tear down

The MySQL Unix-socket path has a hard length limit (~103 characters), so put
the **socket** in a short directory (e.g. under `/tmp`) even if the data
directory lives elsewhere.

```bash
# 1. Pick a short directory for the socket + a data directory.
WORK="$(mktemp -d /tmp/g2ml_it.XXXX)"
DATADIR="$WORK/data"
SOCKET="$WORK/m.sock"
MYSQLD=/opt/homebrew/opt/mysql/bin/mysqld
MYSQL=/opt/homebrew/opt/mysql/bin/mysql
MYSQLADMIN=/opt/homebrew/opt/mysql/bin/mysqladmin

# 2. Initialise and start (no networking needed; socket only).
"$MYSQLD" --no-defaults --initialize-insecure --datadir="$DATADIR"
"$MYSQLD" --no-defaults --datadir="$DATADIR" --socket="$SOCKET" \
          --pid-file="$WORK/m.pid" > "$WORK/mysqld.log" 2>&1 &
until "$MYSQLADMIN" --no-defaults --socket="$SOCKET" -u root ping >/dev/null 2>&1; do
  sleep 1
done

# 3. Import the schema (in numeric order) then the stored procedures.
for f in web/_sql/schema/*.sql; do
  "$MYSQL" --no-defaults --socket="$SOCKET" -u root < "$f"
done
for f in web/_sql/procedures/*.sql; do
  "$MYSQL" --no-defaults --socket="$SOCKET" -u root < "$f"
done

# 4. Export the DSN and run the integration suite.
export G2ML_TEST_DB_SOCKET="$SOCKET"
export G2ML_TEST_DB_NAME="mwtools_Go2MyLink"
export G2ML_TEST_DB_USER="root"
export G2ML_TEST_DB_PASS=""
php tests/run_integration.php

# 5. Tear down.
"$MYSQLADMIN" --no-defaults --socket="$SOCKET" -u root shutdown
rm -rf "$WORK"
```

The integration tests seed only the minimum they need (the `free`
subscription tier, the `[default]` organisation, and a few `tblShortURLs`
rows) and clean up their own rows on each run, so they are repeatable against
the same database.

## 🔍 Linting

Every PHP file under `tests/` passes `php -l`:

```bash
find tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## 🔒 No .php in web addresses

The owner's standing rule: no link, form target, redirect or background
request anywhere in the product may point at an address ending in `.php`.
It must use the clean address the router registers instead (for example
`/help/api`, never `/help/api/index.php`). Two reasons: it tells a stranger
what the site is built with, and it is often simply broken — many hosting
setups answer "page not found" for any address ending in `.php`, and the
page the link sits on looks completely normal, so nobody notices until
someone clicks it.

`tests/unit/no_php_in_urls_test.php` (#203) checks this automatically, as
part of the normal `php tests/run.php` run and therefore of CI. It reads
every `.php`, `.js`, `.html`, `.htm` and `.htaccess` file under `web/`
(skipping the vendored-library, backup and upload folders, and the top-level
SQL and JSON-Schema folders, none of which are web pages) and looks for five
things: an `href`/`action`/`src`/`formaction` attribute, a PHP
`header('Location: ...')` redirect, a JavaScript `fetch`/`open`/`location`
call, a quoted string starting with `/` and ending in `.php` (a helper
function's own `return` value, say), and an *external* `.htaccess` redirect
(a `RewriteRule` carrying an `R` flag, or a `Redirect`/`RedirectMatch`
line). A plain `RewriteRule` with no `R` flag is an internal, server-side
rewrite the browser never sees (`index.php` behind a clean URL, say) and is
deliberately not flagged, and neither is a quoted `require`/`include` file
path.

**Reading a failure.** Each failing line is printed as `path:line: <address>`
— for example `web/Go2My.Link/public_html/some-page.php:42: /old/page.php`
(a made-up example, not a real finding). Fix it by pointing the link, form,
redirect or request at the clean address the router already registers for
that page, the same way every other link on that page does.

**Adding an allow-list entry.** Occasionally a matched value is not actually
a web address — a code example inside a string literal, say. Add one entry
to the array returned by `_g2mlNoPhpTestAllowList()` in the test file, with
the exact file path, the exact matched text, and a plain-English reason. An
entry covers every occurrence of that exact text in that one file, and
nothing else.

## 🏠 House rules

Test code follows the same rules as the rest of the codebase: full
`if/else`/`for` with Allman braces, no shorthand notation (no ternary, Elvis,
`||`-default, braceless `if`, short-echo, or short-open-tag), and British
English throughout.
