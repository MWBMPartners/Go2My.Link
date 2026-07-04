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

## 🏠 House rules

Test code follows the same rules as the rest of the codebase: full
`if/else`/`for` with Allman braces, no shorthand notation (no ternary, Elvis,
`||`-default, braceless `if`, short-echo, or short-open-tag), and British
English throughout.
