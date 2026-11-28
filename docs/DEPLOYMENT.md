# Go2My.Link — Deployment Guide

> Deployment procedures, hosting configuration, and environment setup for the Go2My.Link platform.

## 🚢 Hosting Environment

| Property | Value |
| --- | --- |
| **🏢 Provider** | Dreamhost Shared Hosting |
| **🖥️ PHP version** | PHP 8.4 or newer. Tested on 8.4, 8.5 and 8.6 (8.4 is the minimum). Whether Dreamhost offers 8.6 is not confirmed. |
| **🗄️ MySQL version** | 8.0+ |
| **❌ CLI access** | None (no Composer, npm, or artisan) |
| **📤 File upload** | Automated — GitHub Actions [`sftp-deploy.yml`](../.github/workflows/sftp-deploy.yml) (lftp mirror over SFTP). Manual VS Code FTP-Sync is **not** the deployment path (see 🚀 Deployment Process below). |
| **⚙️ .htaccess** | Supported (`AllowOverride All`) |

### 🖥️ What Dreamhost must offer

Dreamhost must offer PHP 8.4 or newer, with the `mysqli`, `openssl` and `mbstring` extensions and
Argon2id password hashing. Whether Dreamhost offers PHP 8.6 is not confirmed. Choose PHP 8.4 or
8.5 for all three domains in the Dreamhost panel, and choose 8.6 only after you have checked it
yourself (pre-launch checklist A28). If the installer finds a PHP that is a pre-release or newer
than the tested versions, it warns but does not stop (see `docs/INSTALL.md`).

### ✅ What to do on the owner's side after the PHP 8.6 readiness work (#308)

- Apply seed `074_destination_check.sql` to every existing database (checklist A27).
- Leave the setting `redirect.enforce_destination_check` off until you have read the log lines
  that say a link "fails the link check". Switching it on makes short links that fail the
  stricter check stop redirecting.
- After the first pull request, read the names of the new checks on GitHub and add
  "Backend (PHP 8.5)" as a required check (checklist A29). The PHP 8.6 checks are advisory for now.

## 🌐 Domain Configuration

### 📡 DNS Records

| Domain | Type | Points to | Purpose |
| --- | --- | --- | --- |
| 🌐 `go2my.link` | A | Dreamhost IP | Main website (Component A) |
| ⚙️ `admin.go2my.link` | CNAME | go2my.link | Admin dashboard (Component A) |
| 🔗 `g2my.link` | A | Dreamhost IP | Shortlink redirect engine (Component B) |
| 📄 `lnks.page` | A | Dreamhost IP | LinksPage service (Component C) |
| 🧪 `rc.go2my.link`, `rc.admin.go2my.link`, `rc.g2my.link`, `rc.lnks.page` | A | Dreamhost IP | Release-candidate test copy (D-032) |
| 🧪 The same four with `alpha` or `beta` in place of `rc` | A | Dreamhost IP | Alpha and beta test copies |

Add only the test addresses you want. A CNAME to the matching live address also
works for a name below it (`rc.go2my.link` to `go2my.link`). If the domain's DNS
is managed by Dreamhost, adding the address as a website in the panel normally
creates the record; check that it did. Each address also needs its own HTTPS
certificate.

### 🏢 Custom Organisation Domains

Organisations can use custom short domains (e.g., `camsda.link`, `tyney.link`). These require:

1. 🌐 Organisation points their domain's DNS at Go2My.Link (CNAME to `g2my.link`
   for a subdomain, or A/ALIAS to the Dreamhost IP for an apex/root domain)
2. ✅ DNS TXT verification record: `{org.dns_verify_prefix}.{domain}` → a
   **per-domain random verification token** (the code's actual behaviour is
   `_g2ml-verify.{domain}` by default — a prior draft of this doc said the
   value was the organisation handle; it is not, it is `verificationToken`,
   see `addOrgShortDomain()` / `verifyOrgShortDomain()` in
   `web/_functions/org.php`)
3. ⚙️ Domain added in the Dreamhost panel as a hosted domain (manual step
   today — see `docs/CUSTOM_DOMAINS.md` for the full walkthrough and the
   planned Cloudflare-for-SaaS automated path)
4. 🗄️ Domain registered in `tblOrgShortDomains` (short-URL routing domains) —
   **only a `verified` + active short domain is routable** (#91); an
   unverified or unknown Host never falls back to the `[default]` org's
   namespace. (`tblOrgDomains` is a separate, ⚠️ **DEPRECATED (GT-6)** table
   — see `docs/DATABASE.md` — nothing routes off it; do not register a
   domain there expecting it to become routable.)

📖 **Full partner-facing guide:** [`docs/CUSTOM_DOMAINS.md`](CUSTOM_DOMAINS.md).

## 📁 Folder Layout on the Server

Everything lives in one folder used only by this service
([decision D-007](decisions/D-007-deploy-separate-folder.md)). Inside it, each
branch has its own complete copy of `web/`
([decision D-019](decisions/D-019-test-sites-own-copy.md)). The examples below
call the folder `go2mylink`.

```
<home folder>/go2mylink/       <- secret SFTP_BASE_PATH
├── .g2ml-service-folder       <- marker file you create once; uploads stop without it
├── live/                      <- branch main (the live site)
│   ├── .g2ml-channel          <- one word naming the copy, written by the upload
│   ├── _auth_keys/            <- this copy's credentials and install lock (server only)
│   ├── _functions/  _includes/  _libraries/  _schemas/  _sql/  assets/
│   ├── _uploads/  _logs/      <- made by the application when needed (server only)
│   ├── Go2My.Link/
│   │   ├── .auth/             <- this component's credential include (server only)
│   │   ├── _functions/
│   │   ├── public_html/
│   │   └── _admin/public_html/
│   ├── G2My.Link/  (.auth/, _functions/, public_html/)
│   └── Lnks.page/  (.auth/, _functions/, public_html/)
├── alpha/                     <- branch alpha, same shape
├── beta/                      <- branch beta, same shape
└── rc/                        <- branch release-candidate (manual upload only)
```

The code finds its shared folders by climbing up from the file that is running,
so a copy only ever reads its own program files, credentials, uploads and
install lock. Each copy needs its own `_auth_keys/` and `.auth/` folders.

## 🌐 Web Directory for Each Domain

| Domain | Web directory (relative to the home folder) |
| --- | --- |
| `go2my.link` | `go2mylink/live/Go2My.Link/public_html` |
| `admin.go2my.link` | `go2mylink/live/Go2My.Link/_admin/public_html` |
| `g2my.link` | `go2mylink/live/G2My.Link/public_html` |
| `lnks.page` | `go2mylink/live/Lnks.page/public_html` |
| `alpha.go2my.link` | `go2mylink/alpha/Go2My.Link/public_html` |
| `alpha.admin.go2my.link` | `go2mylink/alpha/Go2My.Link/_admin/public_html` |
| `alpha.g2my.link` | `go2mylink/alpha/G2My.Link/public_html` |
| `alpha.lnks.page` | `go2mylink/alpha/Lnks.page/public_html` |
| `beta.go2my.link` | `go2mylink/beta/Go2My.Link/public_html` |
| `rc.go2my.link` | `go2mylink/rc/Go2My.Link/public_html` |
| `rc.admin.go2my.link` | `go2mylink/rc/Go2My.Link/_admin/public_html` |
| `rc.g2my.link` | `go2mylink/rc/G2My.Link/public_html` |
| `rc.lnks.page` | `go2mylink/rc/Lnks.page/public_html` |

Beta's other three addresses follow the same pattern as alpha's
([D-032](decisions/D-032-release-candidate-own-copy.md) gives release-candidate
its own). The test addresses are suggestions: add only the ones you want, each
with its DNS record.

### 🏷️ How the software knows which copy it is

**1. By where the code runs.** A copy in a folder named `alpha`, `beta` or `rc`
is a test copy. Otherwise the file `.g2ml-channel` at the top of the copy (beside
`_functions/`) names it with one word: `live`, `alpha`, `beta` or `rc`.

| Set-up | Where the file comes from |
| --- | --- |
| Automatic upload | Every real upload writes it, before any code |
| New installation with the installer, no file yet | When you press the final button, the installer writes `live` only if the folder is named `live` or you opened it on a live address (`go2my.link`, `www.go2my.link`, and so on). Otherwise it shows a notice and you create the file yourself |
| Installation without the installer, or an existing one | Create `web/.g2ml-channel` containing `live` yourself |
| Local development | Nothing needed to browse the site. To try timed jobs or clean-ups, create `web/.g2ml-channel` containing `live` (git ignores it), only against a local database |

**Only a copy known to be `live` runs timed jobs and clean-ups.** A copy with no
file, or an unreadable or unrecognised one, serves pages normally but removes no
old data: no data clean-up, session clean-up, form-attempt, API log or usage
event pruning, and `/cron` answers "not found" (the server error log says why).
⚠️ On the live site that means account erasure and data retention stop, so check
the file is there. A live address on an unmarked copy writes a "critical" row to
`tblErrorLog` about once a day, titled "This copy is not marked as live: ...".
There is no on-screen warning; check with:

```sql
SELECT `createdAt`, `errorTitle` FROM `tblErrorLog`
 WHERE `errorTitle` LIKE 'This copy is not marked as live%'
 ORDER BY `createdAt` DESC LIMIT 5;
```

**2. By the web address.** On `go2my.link`, `g2my.link`, `lnks.page`,
`localhost` and `*.local`, a part of the name that is exactly `alpha`, `beta` or
`rc` marks a test address (so `rc.admin.go2my.link` and `admin.rc.go2my.link`
both work; `rc-test.go2my.link` does not). **An organisation's own domain is
always live**, even if it starts with `beta.`. A test copy stays a test copy on
any address; a test address also stops timed jobs and clean-ups.

## 🚀 Deployment Process

Deployment is automated by **`.github/workflows/sftp-deploy.yml`** (lftp mirror over
SFTP to Dreamhost). Manual VS Code FTP-Sync is no longer the deployment path.

### 🛠️ Development Workflow

1. ✏️ **Edit locally** and commit to a feature branch
2. 🔀 **Merge to `alpha`** → uploads to the `alpha/` copy on the server
3. 🧪 **Test** on the alpha, beta or release-candidate addresses
4. 🟢 **Promote to `main`** → uploads to the `live/` copy

### 🤖 CI/CD workflows

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| 🔍 `ci.yml` | Push to `main`/`alpha`/`beta`, all PRs | Frontend + Backend gates (PHPStan, PHPCS) |
| 🔍 `php-lint.yml` | Push / PR | PHP syntax validation |
| 🧹 `lint.yml` | Changes under `.github/workflows/**` | `actionlint` workflow linting |
| 🐬 `mariadb-import.yml` | Push / PR touching `web/**` or `tests/**`, plus manual dispatch | MariaDB 11.4: imports schema/procedures/seeds, then runs the database suite on PHP 8.4 (#330) (**advisory, not required — see #183**) |
| 🚢 `sftp-deploy.yml` | Push to `main`/`alpha`/`beta` touching `web/**`, the workflow or its two scripts; manual run | SFTP upload by `scripts/sftp-deploy.sh` (**gated by `vars.SFTP_ENABLED`**) |
| 🏷️ `release.yml` | Tag push | Per-component releases |

### 🔒 Making the integration tests a required check (owner action)

Decision 33 (#243). `.github/workflows/ci.yml`'s `tests-integration` job — the
one that imports the whole schema and runs the integration test suite against
it — stopped being `continue-on-error: true` when #196 was fixed, so it now
genuinely fails a pull request when the suite fails. It is **not yet a
required status check** on the "Protect main branch" ruleset, though — that
is a repository setting only the owner can change, and it should be done
**once the check has passed at least once on this branch's pull request**, so
the first run is not blocking a merge on an untested gate:

1. GitHub → the repository → **Settings** → **Rules** → **Rulesets** →
   **"Protect main branch"** → **Edit**.
2. Under **"Require status checks to pass"**, choose **"Add checks"**.
3. Type `Tests (Integration) (ubuntu-latest)` and select it.
4. **Save changes.**

The name must match **exactly** — GitHub's own menu labels may differ
slightly by the time you do this, but the check name will not. It is the
job's `name: Tests (Integration)` combined with the single-value
`os: [ubuntu-latest]` matrix (see the comment near the top of `ci.yml`);
changing either would silently orphan this required check, so do not rename
the job or drop the matrix without updating the ruleset to match.

### 🔀 Which branch goes where

| Branch | Copy | When |
| --- | --- | --- |
| `main` | `live/` | Every push while armed, or a manual run |
| `alpha` | `alpha/` | Every push while armed, or a manual run |
| `beta` | `beta/` | Every push while armed, or a manual run |
| `release-candidate` | `rc/` | Manual run only |
| anything else | none | The run stops with an error |

Only `main` writes to `live/`.

The `release-candidate` copy is uploaded only when you start a run by hand
([D-032](decisions/D-032-release-candidate-own-copy.md)). The software treats its
`rc` addresses as a test site.

An upload has two parts. First, everything under `web/` is added or updated in
the copy; this part removes nothing. Second, in these folders only, files that
the repository no longer has are removed: `_functions`, `_includes`,
`_libraries`, `_schemas`, `_sql`, each component's `_functions` and
`public_html`, and `Go2My.Link/_admin/public_html`. **Treat those folders as
belonging to the upload: anything you put in them by hand is removed**, unless
its name is in the table under "Files the upload never touches".

### 🚦 Owner set-up: do this once, in this order

Nobody has run these steps against the real server or on GitHub yet. Stop at
anything that does not look as described. In the commands, replace `HOST`,
`USER`, `PORT` and `RUN-ID` with your own values.

#### Step 0 — Switch off, and look for older workflow files

The older workflow file does not check the server's identity, and GitHub runs
the file that is **on the branch or tag being run**. The protection is to give
the upload secrets only to an environment that the older file does not ask for
(Step 4). Start by switching uploads off and listing where older files are:

```bash
gh variable set SFTP_ENABLED --body false --repo MWBMPartners/Go2My.Link
git fetch origin --tags
file=".github/workflows/sftp-deploy.yml"
git for-each-ref --format='%(refname)' refs/remotes/origin refs/tags |
while read -r ref
do
  if git cat-file -e "$ref:$file" 2>/dev/null
  then
    if ! git show "$ref:$file" | grep -q 'environment: sftp-deploy'
    then
      echo "OLD FILE: $ref"
    fi
  fi
done
```

- `main`, `alpha`, `beta` and `release-candidate` must **not** be listed before
  you expect them to upload: bring the new file to them first. (Until then their runs get no
  secrets and fail without uploading.)
- Other branches and tags may stay listed. They are harmless **only once Step 4
  has removed the repository and organisation copies of the secrets**. Do not
  set `SFTP_ENABLED` to `true` before then.

#### Step 1 — Get the server's host key and check it, before connecting

The host key is the server's identity card. Do this before the first sign-in, so
that you never send a password or a file to an unchecked machine.

```bash
ssh-keyscan -t ed25519 HOST > g2ml_known_hosts
ssh-keygen -lf g2ml_known_hosts
```

- Write `HOST` exactly as you will put it in `SFTP_HOST`. If the port is not 22,
  use `ssh-keyscan -p PORT -t ed25519 HOST`; the line must then begin
  `[HOST]:PORT`.
- **Check the fingerprint** (the `SHA256:...` text) against a source other than
  this command, because the command believes whatever answers it. Ask Dreamhost
  support for the fingerprint, or compare with one your computer saved on an
  earlier connection: `ssh-keygen -lF HOST`. If they differ, stop.

#### Step 2 — Make the service folder and its marker file

1. In the Dreamhost panel, note which **user** the websites run as. Use that
   same user for the upload, so that the website can write to its own folders.
2. On your computer, make the marker file:

```bash
printf 'go2mylink-service-folder\n' > .g2ml-service-folder
```

3. Sign in with the checked host key, make a new, empty folder in the home
   folder (here `go2mylink`), and upload the marker into it. It must not be the
   brand-assets website folder, or any other website's folder.

```bash
sftp -o UserKnownHostsFile=g2ml_known_hosts -o StrictHostKeyChecking=yes USER@HOST
sftp> mkdir go2mylink
sftp> cd go2mylink
sftp> put .g2ml-service-folder
sftp> ls -la
sftp> bye
```

(Add `-P PORT` if the port is not 22.) Names that start with a dot are hidden in
many file programs. You do not need to make `live/`, `alpha/`, `beta/` or `rc/`;
the first real upload of each makes it.

#### Step 3 — Make a key used only for uploads

```bash
ssh-keygen -t ed25519 -N '' -C 'go2mylink-deploy' -f g2ml_deploy
ssh-copy-id -i g2ml_deploy.pub -o UserKnownHostsFile=g2ml_known_hosts -o StrictHostKeyChecking=yes USER@HOST
sftp -i g2ml_deploy -o UserKnownHostsFile=g2ml_known_hosts -o StrictHostKeyChecking=yes USER@HOST
```

(If the port is not 22, add `-p PORT` to `ssh-copy-id` and `-P PORT` to
`sftp`.) The last command must sign in without asking for a password. If the user has no
shell access and `ssh-copy-id` fails, add the one line in `g2ml_deploy.pub` to
the file `.ssh/authorized_keys` in the user's home folder with your SFTP program.

#### Step 4 — Create the environment, add its secrets, delete the old copies

**4a. The environment.** GitHub → the repository → **Settings** →
**Environments** → **New environment** → name it exactly `sftp-deploy`. Under
**Deployment branches and tags** choose **Selected branches and tags** and add
the four branches `main`, `alpha`, `beta` and `release-candidate`. Add no tag
rule. If GitHub does
not offer this restriction (it depends on the plan and on whether the repository
is private), **stop** and report it. Check:

```bash
gh api repos/MWBMPartners/Go2My.Link/environments/sftp-deploy/deployment-branch-policies --jq '.branch_policies[].name'
```

It must print `main`, `alpha`, `beta` and `release-candidate` and nothing else. Create the
environment before arming: if a run names an environment that does not exist,
GitHub creates one with no restriction.

**4b. Its secrets** (`gh secret set NAME` asks for the value):

```bash
R=MWBMPartners/Go2My.Link
gh secret set SFTP_HOST --env sftp-deploy --repo $R
gh secret set SFTP_USER --env sftp-deploy --repo $R
gh secret set SFTP_BASE_PATH --env sftp-deploy --repo $R
gh secret set SFTP_KNOWN_HOSTS --env sftp-deploy --repo $R < g2ml_known_hosts
gh secret set SFTP_KEY --env sftp-deploy --repo $R < g2ml_deploy
```

| Name | Where | Value |
| --- | --- | --- |
| `SFTP_HOST` | environment secret | The server name, as used in Step 1 |
| `SFTP_USER` | environment secret | The user from Step 2 |
| `SFTP_BASE_PATH` | environment secret | The folder from Step 2: `go2mylink` (relative to the home folder) or `/home/USER/go2mylink`. Do not use `~`; it is refused |
| `SFTP_KNOWN_HOSTS` | environment secret | The whole contents of `g2ml_known_hosts` |
| `SFTP_KEY` | environment secret | The whole contents of `g2ml_deploy` (the private half) |
| `SFTP_PORT` | environment secret, optional | Only if the port is not 22 |
| `SFTP_PASSWORD` | environment secret, optional | Used only when `SFTP_KEY` is empty. Prefer the key. A password containing `"` or `\` is refused |
| `MAXMIND_LICENSE_KEY` | organisation or repository secret, optional | Country lookup for statistics (checklist A5) |
| `SFTP_ENABLED` | repository **variable** | `false` until Step 5; `true` switches uploads on |

**4c. Delete every other copy of the SFTP secrets.** This is what disarms the
older workflow files. List the repository secrets, then delete each one whose
name begins `SFTP_`:

```bash
gh secret list --repo $R
gh secret delete SFTP_HOST --repo $R
gh secret delete SFTP_USER --repo $R
gh secret delete SFTP_PORT --repo $R
gh secret delete SFTP_BASE_PATH --repo $R
gh secret delete SFTP_KEY --repo $R
gh secret delete SFTP_PASSWORD --repo $R
gh secret delete SFTP_KNOWN_HOSTS --repo $R
gh secret list --repo $R
gh secret list --env sftp-deploy --repo $R
```

(An error for a name that was never there is fine.) Also look in the
**organisation's** Actions secrets for names beginning `SFTP_`, and delete them
or remove this repository's access. The second-last command must then show no
`SFTP_` name. If an old password or key had been stored there, change it on the
server too.

When the secrets are saved, delete `g2ml_deploy` from your computer (or keep it
in a password manager).

#### Step 5 — Arm, then do a dry run

A dry run connects, compares and lists what it would do. It changes nothing.

⚠️ While armed, **any push or merge** to `main`, `alpha` or `beta` that touches
the uploaded files uploads for real. Do not push or merge anything until Step 7
is done.

Start with `alpha`:

```bash
gh variable set SFTP_ENABLED --body true --repo MWBMPartners/Go2My.Link
gh workflow run sftp-deploy.yml --repo MWBMPartners/Go2My.Link --ref alpha -f dry_run=true
gh run list --repo MWBMPartners/Go2My.Link --workflow sftp-deploy.yml --limit 3
```

On the website: **Actions** → **SFTP Deploy** → **Run workflow** → choose the
branch → leave the dry run box **ticked**.

#### Step 6 — Read the log and the removal list

```bash
gh run view RUN-ID --repo MWBMPartners/Go2My.Link --log
gh run view RUN-ID --repo MWBMPartners/Go2My.Link --log | grep -nE '(^|[[:space:]])(rm|rmdir)[[:space:]]|Removing'
```

GitHub hides secret values in logs, so the server name, user and service folder
appear as `***`. In the **Deploy** step check:

- It says `Copy on the server: alpha` (or the copy you expect) and `DRY RUN`,
  and notes that a real run writes `.g2ml-channel`.
- Paths are directly inside the copy. Correct:
  `***/alpha/_functions/security.php` and
  `***/alpha/Go2My.Link/public_html/index.php`. **Wrong:** a folder repeated or
  added, such as `***/alpha/_functions/_functions/...` or `***/alpha/web/...`,
  or a path that does not go through `***/alpha/`.
- The part between `===== REMOVALS` and `===== END OF REMOVALS =====`, and the
  result of the second command above. For a new, empty copy there should be no
  removals. Later there should be only files you know were deleted from the
  repository. The exact wording the upload program uses has not been seen yet,
  so also read the whole log once.

✋ If a path is wrong, or any removal is unfamiliar, or is a credential, upload
or backup, **stop**, switch uploads off (Step 7) and ask for help. Do the same,
without working round it, if a dry run of a brand-new copy fails saying a folder
does not exist, or the run says it could not tell whether the copy is installed.

#### Step 7 — Upload for real, check the files, then disarm

```bash
gh workflow run sftp-deploy.yml --repo MWBMPartners/Go2My.Link --ref alpha -f dry_run=false
gh run watch --repo MWBMPartners/Go2My.Link
gh variable set SFTP_ENABLED --body false --repo MWBMPartners/Go2My.Link
```

Wait for the run to finish before the last command, then read its log as in
Step 6. After the **first alpha upload**, sign in with `sftp` as in Step 2 and
look:

```bash
sftp> ls -la go2mylink/alpha
sftp> ls -la go2mylink/alpha/_functions
sftp> ls -la go2mylink/alpha/Go2My.Link/public_html
```

Folders must show `drwxr-xr-x` and files `-rw-r--r--`. `go2mylink/alpha` must
hold `.g2ml-channel` containing `alpha` (and later `live/` one containing
`live`). There must be no
`go2mylink/alpha/web` and no folder inside itself. If either check fails, stop:
do not upload the other copies.

Then repeat Steps 5 to 7 with `--ref beta` and, when you are ready to launch,
`--ref main`. For the release-candidate copy, use `--ref release-candidate`
whenever you want to upload it; nothing uploads it by itself. Leave `SFTP_ENABLED` at `true` only if you want every push to
upload by itself.

#### Step 8 — Point the domains at their copies

In the Dreamhost panel, open each domain's website settings and set its **web
directory** to the value in
[🌐 Web Directory for Each Domain](#-web-directory-for-each-domain). (The panel's
menu names may differ.) Choose PHP 8.4 or 8.5 and switch HTTPS on.

⚠️ For a live domain this changes what visitors see **at once**. Do it at the
point the [migration plan](MIGRATION_PLAN.md) calls the cutover. Set up the
live copy's credentials (Step 9) in the same sitting; the installer needs a
domain pointing at the copy. Point test addresses only after their copy has its
credentials.

If the panel adds its own placeholder files to a web directory, the next upload
removes them; the dry run lists them first.

#### Step 9 — Give each copy its credentials

Each copy (live, alpha, beta, release-candidate) has its own credential files.
All of them name the **same database**
([D-029](decisions/D-029-test-sites-shared-database.md); release-candidate is a
test site under [D-032](decisions/D-032-release-candidate-own-copy.md) and is set
up the same way).

**The live copy first**, one of:

- **New set-up:** open `https://go2my.link/install/` and follow
  [INSTALL.md](INSTALL.md). The installer writes `_auth_keys/` and each `.auth/`
  inside `live/`. Then delete its `install/` folder; later uploads see the lock
  file and leave `install/` out.
- **Reusing credentials from an older server layout:** as below, copying from
  the old folders.

**Then the test copies (alpha, beta, rc), by copying.** ⚠️ **Never run the installer for a test
copy.** It would work on the shared live database and make new encryption keys,
so the copy could not read the live encrypted data. Instead:

1. Copy the shared `auth_creds.php` to `<copy>/_auth_keys/`, and each
   component's include to `<copy>/<Component>/.auth/auth_creds.php` (older
   servers may call that folder `_auth_keys`). Keep `ENCRYPTION_SALT` and
   `ENCRYPTION_KEY_SECONDARY` unchanged. Set the files to `0600`.
2. Make the lock file `<copy>/_auth_keys/.installed`, containing any one line.
3. Delete `<copy>/Go2My.Link/public_html/install/`.

Do items 2 and 3 straight after the first upload of a test copy, even before it
has an address.

#### ⚠️ Test copies work on live data

Alpha, beta and release-candidate share the live database, and so share its
settings:

- **Never point a timer (scheduled job) at a test copy.** The timed-job switch
  (`cron.enabled`) and token (`cron.dispatch_token`) are the same in every copy.
  A test copy answers "not found" to `/cron` and runs none of the automatic
  clean-ups.
- A test copy can still change live data in every other way: whatever a signed-in
  person does there is real.
- **Test copies send real email** to real people, using the live email settings.

#### ⚠️ Limits of the test copies

- The program files are separate, but **the database is the live one**
  ([D-029](decisions/D-029-test-sites-shared-database.md)). Anything done on a
  test copy changes live data. The decision wants values that behave differently
  on alpha or beta kept in separate fields or tables; **that is not built yet**,
  and its detail is still to be confirmed (tracked in #158). Do not apply a test
  branch's database change without thinking about what the live code will do with it.
- Some links, and the page shown after signing in, still name the live addresses
  (for example `https://admin.go2my.link`), so a test copy can send you to the
  live site.
- The live sign-in cookie (`G2ML_SESSION`) is set for all of `go2my.link`, so a
  browser also sends it to the test addresses. Each test copy uses its own name
  (`G2ML_SESSION_ALPHA`, `_BETA`, `_RC`) and ignores it, but code running on a
  test copy could still read it. Whether the copies share PHP's session storage
  on the server has not been checked.
- Search engines are not told to skip test copies; they get the same pages and
  `robots.txt` as the live site.
- One server user can write to every copy. The separation comes from the
  workflow, so anyone who can change the workflow on `alpha`, `beta` or
  `release-candidate` could reach the live files.

### 🔒 Switching uploads off

```bash
gh variable set SFTP_ENABLED --body false --repo MWBMPartners/Go2My.Link
```

This stops new runs from uploading. A run already going carries on; cancel it
from the **Actions** page if you must, knowing the copy may be left half updated.

### 🔑 If the server's host key changes

The run fails before anything is uploaded. Confirm with Dreamhost that the
change is real, repeat Step 1, and save the new `SFTP_KNOWN_HOSTS` in the
`sftp-deploy` environment.

### 🛡️ Files the upload never touches

Never uploaded and never removed, in every copy:

| Path | Why |
| --- | --- |
| `_auth_keys/`, `.auth/`, `auth_creds.php` | Credentials |
| `.installed`, `.install_token` | Install lock and token |
| `_uploads/`, `_backups/`, `_logs/` | User files, backups and logs |
| `private_html/`, `.well-known/`, `.dh-diag`, `.htpasswd` | Belong to the host (certificate checks live in `.well-known/`) |
| `error_log`, `php_errors.log` | Server error logs |
| `.g2ml-service-folder` | The marker file |
| `.g2ml-channel` | Names the copy; the upload writes it separately and never removes it |
| `*.mmdb` | The country database is uploaded on its own, with no removals, and only when a run downloaded it |
| `.git/`, `.github/`, `.vscode/`, `.idea/`, `.gitignore`, `.gitkeep`, `.gitattributes`, `README.md`, `DEV_NOTES.md`, `CLAUDE.md`, `.DS_Store`, `Thumbs.db` | Repository clutter |

`.user.ini` and `.htaccess` are **not** in this list. The repository owns them
(they keep PHP error messages off the screen), so an upload replaces any change
made on the server. Make server-only PHP changes in the Dreamhost panel instead,
or change the file in the repository.

> 🧪 The patterns in `scripts/sftp-deploy.sh` are single-quoted on purpose:
> lftp treats an unquoted `|` as a pipe
> ([#156](https://github.com/MWBMPartners/Go2My.Link/issues/156)).

## 🔍 Environment Detection

A test copy takes its environment from where it runs; any other copy from the web
address (see
[🏷️ How the software knows which copy it is](#️-how-the-software-knows-which-copy-it-is);
the code is `web/_functions/environment.php`).

| Copy or address | Environment | Debug output for a global administrator | Sign-in cookie |
| --- | --- | --- | --- |
| 🟡 `alpha` copy or test address | Alpha | ✅ Always | `G2ML_SESSION_ALPHA`, Secure over HTTPS |
| 🟠 `beta` copy or test address | Beta | ✅ Always | `G2ML_SESSION_BETA`, Secure over HTTPS |
| 🟣 `rc` copy or test address | Release candidate | ✅ Always | `G2ML_SESSION_RC`, Secure over HTTPS |
| 🖥️ `localhost` / `127.0.0.1` / `*.local` (no port number, no test part) | Local development | ✅ Always | `G2ML_SESSION`, not Secure |
| 🟢 Everything else | Production | 🔸 Only with `?debug=true` in the address | `G2ML_SESSION`, Secure |

### 🐛 Who sees debug output

On **every** address, the debug panel (PHP version, database statements) and
any error details on screen are shown only to a signed-in **global
administrator** ([decision D-011](decisions/D-011-debug-panel-global-admin.md)).
Everyone else sees a normal page. The check is made against the database
whenever debug output is asked for (always on alpha, beta, rc and local addresses;
on the live site only with `?debug=true`), so if the database is down, or an
error happens before the sign-in has been checked, nobody sees any details. Errors are still logged
(`tblErrorLog`, or the server's PHP error log).

The setting lives in `web/_functions/debug_access.php`. Each web root also has
a `.user.ini` (for PHP running as CGI or FastCGI) and `.htaccess` lines (for PHP
inside Apache) that keep errors off the screen from the first line of a request.
PHP may take a few minutes to notice a new or changed `.user.ini`.

### 💻 Debug output in local development

1. 🗄️ Set up a local database and create a global administrator (the installer
   does this).
2. 🔑 Sign in as that administrator. The panel then appears on the next page.
3. 🔢 If your address has a port number (for example `localhost:8000`), the site
   treats it as production: add `?debug=true`. The sign-in cookie is then marked
   "secure", which some browsers refuse over plain `http`.
4. 📋 To read an error that happens before anyone can be recognised (for example
   a broken credentials file), look in the PHP error log. It is never shown on
   the page.

## 🗄️ Database Setup

### 🆕 New Installation

1. 🗄️ Create the MySQL database: `mwtools_Go2MyLink`
2. 🚨 **Check its collation before importing anything** — see below
3. 📋 Import all **17** schema files from `web/_sql/schema/`, in filename order
4. 🔧 Import both stored procedures from `web/_sql/procedures/`
   (`sp_generateShortCode.sql`, `sp_lookupShortURL.sql`)
5. 🌱 Import all **46** seed files from `web/_sql/seeds/`, in order — the
   numbering is not continuous: `001` through `023` run in sequence, then
   twenty-three later additions sit outside that run, `029_linkspage_privacy_translations.sql`,
   `057_form_rate_limit_settings.sql`,
   `058_truthful_marketing_translations.sql`,
   `059_link_destination_translations.sql`,
   `064_linkspage_avatar_icon_translations.sql`,
   `065_linkspage_manage_error_translations.sql`,
   `066_org_members_unknown_action_translation.sql`,
   `067_dashboard_access_translations.sql`,
   `068_small_fixes_translations.sql`,
   `069_home_meta_description_translation.sql`,
   `070_offline_page_translations.sql`,
   `071_form_feedback_translations.sql`,
   `072_accessibility_translations.sql`,
   `073_linkspage_layout_translations.sql`,
   `074_destination_check.sql` and
    `075_linkspage_announcement_translations.sql`,
   `076_shared_g2my_codes_translations.sql`,
   `077_deletion_link_choice_translations.sql`,
   `079_staff_link_switch_translations.sql`,
   `081_dead_link_page_translations.sql`,
   `082_plans_and_pricing_translations.sql`,
   `083_api_plan_access_translations.sql`,
   `084_link_limit_translations.sql` (see the table below for
   what each one adds). Importing "in order" means by filename, so a plain
   `for f in web/_sql/seeds/*.sql` loop picks up all 46 without needing to
   know about the gap in the numbers.

> 📝 Counts as at 2026-11-28: 17 schema files, 2 stored procedures, 46 seeds,
> 26 migrations. Import order is schema → procedures → seeds, which is the
> order `.github/workflows/ci.yml` uses. (Seed `023` and migration `021`, the
> LinksPage feature registry, were added on 2026-09-21; seeds `029`, `058`,
> `059`, `064`, `065`, `066`, `067`, `068`, `069`, `070`, `071`, `072`, `073`, `074`, `075`, `076`, `077`, `079`, `081` and `082` were added later still, outside the 001-023 run —
> see below. Seed `057`, schema `044` and migration `043`, the form
> rate-limit table, were added for #212 — see below. Migration `044` and seed
> `076`, the shared g2my.link codes, were added for #289 — see below. Seed `077` and migration
> `045`, the link choice on account deletion, were added for #246 — see below.
> Seed `079` and migration `046`, the staff screen for switching links off, were
> added for #325 — see below.)
> Migration `048` and seed `081`, the pages for links that do not open, were
> added for #290 — see below.
> Seed `082`, the words about plans and limits, was added for #209.
> Seed `084`, the words about the limit on switched-on links, was added for #327.
> Seed `083`, the plan and role rules for API access, was added for #328.

#### 🚨 The collation check — do this first, every time

```sql
SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'mwtools_Go2MyLink';
```

It must return `utf8mb4_unicode_ci`. If it does not:

```sql
ALTER DATABASE `mwtools_Go2MyLink`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Why.** `web/_sql/schema/000_create_database.sql` asks for the right collation,
but it uses `CREATE DATABASE IF NOT EXISTS`. If the database already exists —
which it will, because on shared hosting you have to create it through the
control panel first — that statement does nothing at all, and the database
keeps whatever collation the host chose.

The tables are unaffected: every `CREATE TABLE` names its own collation. The
**stored procedures** are the problem. A variable declared inside a stored
procedure inherits the database's collation, so when a procedure created from
files older than #196 compares its candidate code against the `shortCode`
column under a different utf8mb4 collation (`utf8mb4_0900_ai_ci`, say),
MySQL refuses with "illegal mix of collations". Before #197, that error was
swallowed by the procedure's own error handler, so the only symptom anyone
saw was:

> Failed to generate a unique short code. Please try again.

Every attempt to create a link with a generated short code fails (a custom
alias is not affected — it is inserted directly and never calls the
procedure). Before #197, nothing was written to any log that explained it;
both procedures now let a real MySQL error reach `dbCallProcedure()`
(`web/_functions/db_query.php`), which logs it. This was measured, not
guessed: with the wrong collation the integration suite scores 182 passed /
25 failed; with the right one, 207 passed / 0 failed. See #196 and #197. The
current procedure files state `COLLATE utf8mb4_unicode_ci` explicitly on
every comparison that needs it (see their own headers) and no longer swallow
a genuine error, which is why re-importing them below still matters even
after the database itself is fixed.

**On an EXISTING database**, instead of the `ALTER DATABASE` above, you can
run `web/_sql/migrations/042_database_collation.sql` — the same statement,
committed as a migration so it is part of the repeatable upgrade path. Either
way, **afterwards re-import both stored procedure files**
(`sp_generateShortCode.sql`, `sp_lookupShortURL.sql`): a stored procedure
keeps the collation that was current when it was created, not the database's
current one, so correcting the database alone does not correct a procedure
already created under the old default.

**Re-import both procedure files on every existing database at least once
(#196), even one whose collation check above already returns
`utf8mb4_unicode_ci`.** The files themselves changed, independently of the
database's collation: every comparison between a variable and a table
column now states `COLLATE utf8mb4_unicode_ci` explicitly, on the variable
side, as a second safeguard. A database that has never re-imported these
two files since #196 does not have that safeguard yet, whatever the
collation check says — the "If it does not" step above only re-imports
them as a side effect of fixing a wrong collation, so a database that was
never wrong needs this done directly.

#### 🌱 Seeds that must also be applied to an EXISTING database

These add user-visible text. Without them the affected screens show translation
key names instead of words:

| Seed | What it adds | Consequence if skipped |
|---|---|---|
| `021_missing_ui_translations.sql` | 5 strings that were used but never translated | The homepage browser tab reads `home.title`; four screen-reader labels read out their key names |
| `022_help_translations.sql` | Every word of the in-app Help section (367 rows) | All five `/help` pages display key names instead of text |
| `029_linkspage_privacy_translations.sql` | 8 strings: the Privacy Policy's new "LinksPage Data" subsection, plus one warning line on the Delete Account page and one list item on the Export page (#219) | The Privacy Policy page shows raw key names (e.g. `legal.privacy_s2_linkspage_title`) instead of English in that subsection; the Delete/Export pages show `delete.warning_linkspages`/`export.includes_linkspages` instead of English |
| `057_form_rate_limit_settings.sql` | The three `security.*_max_per_ip_per_hour` settings that cap registration, contact-form and password-reset attempts (10/5/5 — owner decision 43, #243), plus six texts: the sign-up limit message, the "cannot process this form" message and four lines of the Privacy Policy (#212) | The settings row and its admin-screen description are missing, but `getSetting()` still falls back to each call site's own hard-coded default, so the limits still apply; the refusal messages and the new Privacy Policy lines show key names (for example `register.error_rate_limit` and `legal.privacy_s7_forms`) instead of English |
| `058_truthful_marketing_translations.sql` | 6 strings: corrected wording for the homepage, features and about pages' security and analytics descriptions, so they no longer promise two-factor authentication or SSO and describe geographic data as available "where enabled" rather than for every visitor (#210) | The homepage, features page and about page show `home.feature_secure_desc_v2` and the other five new key names instead of English |
| `059_link_destination_translations.sql` | 3 strings: the dashboard edit-link destination-check error messages (invalid URL, our own short domain, blocked/internal host), now shared with create and the API through one function (#205) | The dashboard edit-link page shows the raw key names `links.error_destination_invalid_url`/`links.error_destination_own_domain`/`links.error_destination_blocked_destination` instead of English |
| `064_linkspage_avatar_icon_translations.sql` | 4 strings: the avatar and per-link icon fields' help text on the LinksPage create/edit pages, plus the two save-time error messages shown when one of those addresses is not https:// (#221, #273) | The two help texts show `linkspage.avatar_url_help`/`linkspage.item_icon_help` instead of English, and a refused save shows `linkspage.avatar_url_https_error`/`linkspage.item_icon_https_error` instead of a readable error |
| `065_linkspage_manage_error_translations.sql` | 41 strings: every OTHER error message the LinksPage admin dashboard's create/edit/delete/reorder/upload backend (`web/_functions/linkspage_manage.php`) can show — reserved/invalid slugs, page and item field validation, the per-plan page limit, the per-page item cap, ownership/not-found checks, and the custom-HTML editor's save/upload errors (CX-01, #218). The two save-time "must be https://" errors for the avatar and per-link icon fields are NOT in this seed — they are in `064` above, since they were already translated before CX-01 | Every one of those messages shows its raw `linkspage.error.*` key name (e.g. `linkspage.error.slug_reserved`) instead of readable English |
| `066_org_members_unknown_action_translation.sql` | 1 string: the Organisation Members page's "Unknown action." message, shown when a posted `action_type` matches none of the CSRF form names the page knows how to build — a branch the #147 fix added, which the page did not have before (#147 review round 2) | The Organisation Members page shows the raw key name `org.members_error_unknown_action` instead of "Unknown action." when this happens |
| `067_dashboard_access_translations.sql` | 10 strings: the "access refused" screen that six dashboard pages (API Keys, Custom Domains, Members, Invite Member, Organisation Settings, Short Domains) now share — a heading, a line of explanation and a button for someone with no organisation yet ("You are not in an organisation yet", "Create an organisation"), a heading and a button for someone who belongs to one but may not manage it ("Access Denied", "Back to Dashboard"), and the five pages' own refusal messages, which used to be English-only (#279) | Those six pages show raw key names such as `access.denied_heading` and `org.members_error_forbidden` instead of English when somebody is refused |
| `068_small_fixes_translations.sql` | 9 strings for the #284 fixes: the new legal hub page at `/legal` (title, description and introduction), the LinksPage editor's "Link deleted." and "Link saved." messages, the "Expired" and "Scheduled" badges on My Links, and My Links' "Prev" and "Next" page buttons (#284) | The `/legal` page shows `legal.hub_title` and the other key names; the LinksPage editor shows `linkspage.item_saved`/`linkspage.item_deleted` after saving or deleting a link; My Links shows `links.status_expired`/`links.status_scheduled` and `links.page_prev`/`links.page_next` |
| `069_home_meta_description_translation.sql` | 1 string: the home page's own search and sharing description ("Shorten long web addresses for free, with no account needed. …"), which used to be the three-word tagline (#282) | The home page's description, in search results and in link previews on social media, reads `home.meta_description` |
| `070_offline_page_translations.sql` | 3 strings: the title, message and "Try again" button of the offline page at /offline on go2my.link and the dashboard, which the service workers show when the network is down (#283) | The offline page shows its built-in English instead (it never shows key names), so it cannot be translated |
| `071_form_feedback_translations.sql` | 5 strings for #286: the "Working…" label buttons show while a form is being sent, the Organisation Members page's new role list label, "Change role" button and its confirmation, and the LinksPage list's confirmation before unpublishing a page (#286) | Busy buttons and the screen-reader announcement say `common.working`; the Members page shows `org.members_change_role_button` and its confirm box shows the key name; the Unpublish confirm box shows `linkspage.unpublish_confirm` |
| `072_accessibility_translations.sql` | 3 strings for #287: the screen-reader notice "(opens in a new tab)" on links that open a new tab, the "Copied!" label of copy buttons, and the end of the cookie banner's link name ("… about our cookie policy") | Screen readers hear `common.opens_new_tab` on new-tab links; copy buttons say `common.copied`; the cookie banner's link is named with the key `cookie.learn_more_suffix` |
| `073_linkspage_layout_translations.sql` | 1 string: "Profile picture", the alt text of an uploaded LinksPage avatar (#287) | Nothing a visitor sees breaks: the renderer falls back to the English words when the translation is missing |
| `074_destination_check.sql` | The setting `redirect.enforce_destination_check` (off) and 3 strings for #308: the refusal page's title and message, and the info page's "destination is not valid" line. The setting decides whether a short link that fails the stricter link check is refused when followed | Nothing a visitor sees breaks: until it is applied the setting counts as off, and the code's own English is used. Apply it before you switch the setting on |
| `075_linkspage_announcement_translations.sql` | 33 strings for #320: the lnks.page home page, and the Lnks.Page mentions on go2my.link (home, features, pricing, help and the footer) | The go2my.link home, features, pricing and help pages, and the footer on every main-site and dashboard page, show key names such as `footer.linkspage`; the lnks.page home page shows its built-in English instead |
| `076_shared_g2my_codes_translations.sql` | 1 string for #289: a paragraph on the Short Links help page saying that each ending on g2my.link is used once by anybody, organisation members included, and that an organisation with its own verified short web address has its own set of endings | Nothing breaks: the help page shows the English written in the page itself |
| `077_deletion_link_choice_translations.sql` | 20 strings for #246: the choice of what happens to short links on the Delete Account page, the sentence describing it (also used in the confirmation email), the corrected warning line and Privacy dashboard card, and two Privacy Policy retention texts (new legal wording, needs the owner's review) | The Delete Account page, the Privacy dashboard and the Privacy Policy show key names instead of words |
| `079_staff_link_switch_translations.sql` | 56 strings for #325: the staff "Find a Short Link" page, the menu entry, the notices an owner sees when staff have switched a link off, and the visitor wording for a disabled link | The staff page and its menu entry show built-in English or a key name; the disabled-link error page and My Links show key names such as `redirect.link_disabled_message` |
| `081_dead_link_page_translations.sql` | 32 strings for #290: the g2my.link pages for a link that does not open or cannot be looked up, the safety check page, and three corrected help paragraphs; also new descriptions for the settings `redirect.fallback_url` and `redirect.fallback_delay` | The g2my.link pages show the English written in the code; the three help paragraphs on the Short Links and Click statistics help pages show key names |
| `082_plans_and_pricing_translations.sql` | 21 strings for #209: the pricing page (the Free plan and "paid plans are coming", no prices), the corrected words about teams, custom domains, the country breakdown and the API's request limits, and the plan-limit messages that no longer ask anybody to upgrade | The pricing, features, about and help pages and the plan-limit messages show key names |
| `083_api_plan_access_translations.sql` | 19 strings for #328 (owner decision D-025): the message given when a plan has no API access or cannot be checked, the API Keys page's notice and empty state, the "Owner has left" and "Owner is not an administrator" key statuses, the refusal to make a key for a non-administrator, and two paragraphs on the API help page | The API Keys page and the API help page show key names such as `apikeys.plan_heading`; the API's own messages fall back to English |
| `084_link_limit_translations.sql` | 8 strings for #327: the pricing and help sentences about the limit on switched-on links (now covering people without an organisation and switching a link back on), and the refusal messages | The pricing page, two help paragraphs and the new limit messages show key names |

All twenty-five are safe to run more than once and safe on a database that already has them.
Twenty-two are plain `INSERT IGNORE`. `057` and `074` write their settings with
`ON DUPLICATE KEY UPDATE`, so a value you chose is kept (their texts are `INSERT IGNORE`).
`081` also rewrites the descriptions of two settings and leaves their values alone.

**Also re-import `004_linkspage_templates.sql` on an existing database (#287), after the new code is
uploaded.** Unlike the files above, it is not INSERT IGNORE: it refreshes the five system LinksPage
templates (their HTML and CSS) by their slug, so they gain a `<main>` landmark, readable colours and
long-word wrapping. Upload the code first: the new templates use two markers (`{{avatarAlt}}`,
`{{themeText}}`) that the old code would leave on the page. Any change made by hand to those five
system templates in the database is replaced; owners' own pages are untouched (they only choose a
template).

#### 🧩 Migration `021` — the LinksPage feature registry (needed on an EXISTING database)

`web/_sql/migrations/021_linkspage_feature_registry.sql` registers every
LinksPage extra (hiding the "Powered by" line, search and sharing controls,
statistics history and so on) and gives each of the four plans a value for it.
A fresh install gets exactly the same rows from seed
`023_linkspage_feature_registry.sql`, so it does not need this file. A
database installed **before** 2026-09-21 does (issue #216).

**Why it matters.** The code checks each LinksPage extra by looking up that
feature's row. When the row is missing, the check answers "no" on purpose, so
skipping this migration quietly takes every LinksPage extra away from every
plan, paid ones included. Short links, redirects and logins are not affected.

Do these three steps, in this order:

1. 🚨 **Before deploying the code, check that the three `billing.*` switches
   are still off.** This release fixes a bug that made these switches
   impossible to turn on. Because of the fix, a stored "on" value (`1`,
   `true`, `yes` or `on`) takes effect the moment the new code is live, so a
   forgotten "on" would quietly switch on the pricing engine or usage
   metering. No admin screen writes these settings, so only a direct database
   edit could have set one, but it takes one query to be sure:

   ```sql
   SELECT settingID, settingScope, settingScopeRef, settingValue
     FROM tblSettings
    WHERE settingID IN ('billing.pricing_engine_enabled',
                        'billing.usage_metering_enabled',
                        'billing.usage_event_log_enabled');
   ```

   Every row should show `settingValue` = `0`. No rows at all is also fine,
   because a missing setting counts as off. If a row is not `0`, ask the owner
   whether it was meant, and set it back to `0` before deploying unless they
   say otherwise. The background is in [DATABASE.md](DATABASE.md), section
   "Feature Registry for LinksPage", under "Before deploying LP-01".

2. **Run `web/_sql/migrations/020_pricing_engine.sql`, even if it has been
   run before.** It creates the tables that `021` writes to. On a database
   that already has those tables, run it anyway, for a second reason: it is
   the only file that corrects the stored description of the master switch
   `billing.pricing_engine_enabled`. The old description says that with the
   switch off "the new tables are completely inert". That stopped being true
   in this release, because the LinksPage checks read three of those tables
   whether the switch is off or on (see [DATABASE.md](DATABASE.md), section
   "Feature Registry for LinksPage"). Migration `021` does not touch settings,
   so it cannot correct it.

   A re-run only adds what is missing and refreshes descriptions. It never
   changes a switch's value or a plan value someone has edited. *(Checked on
   2026-09-21 against MySQL 8.4: re-running `020` on a fully migrated
   database kept every row count the same, left the switch at `0`, kept a
   plan value that had been changed by hand, and replaced the old
   description.)*

   ⚠️ **First, confirm that migration `019` has been applied.** A re-run of
   `020` depends on it:

   ```sql
   SHOW INDEX FROM tblSettings
    WHERE Key_name = 'UQ_setting_scope'
      AND Column_name = 'settingScopeRefKey';
   ```

   One row back means `019` is in place. No rows means it is not. Without
   `019`, the database cannot tell that a platform-wide setting already
   exists, so the re-run adds a second copy of each `billing.*` setting
   instead of updating the first one (seen in the same 2026-09-21 check).
   In that case run `web/_sql/migrations/019_settings_scope_dedupe.sql`
   first, **exactly once** (it is not safe to run twice; see the skip list
   under "Migration (from MWlink)" below), and then `020`.

   **After running `020`, check that the table exists:**

   ```sql
   SHOW TABLES LIKE 'tblTierFeatures';
   ```

   One row back means it is there. ✅ **MariaDB (Dreamhost) — fixed
   2026-09-21, issue #183:** migration `020` used to fail to create
   `tblTierFeatures` on MariaDB 11.4 (its generated column was refused); the
   fix swaps a `CAST` for a plain `TIMESTAMP` literal that both engines
   accept, confirmed by importing the full schema into a throwaway MariaDB
   11.4 container with 0 errors. `.github/workflows/mariadb-import.yml`
   checks the fresh-install form of this table (schema `036`) on MariaDB
   11.4 on every change under `web/` or `tests/`. It does not run migration `020`
   itself, and it is a warning check, not a required one — so a real install
   still needs the manual check above after running `020`. If the table
   is still missing after running `020` on a real install, that install most
   likely predates the #183 fix — pull the latest schema and migration files
   and re-run `020` before continuing. **If the table is still missing after
   running the current `020`** (the one with the `TIMESTAMP` literal, not the
   old `CAST`), stop and tell the owner rather than re-running it again —
   something else is wrong (a foreign-key or permission error, or a MariaDB
   point release nobody has tested), and repeating the same migration will
   not fix it.

3. **Run `web/_sql/migrations/021_linkspage_feature_registry.sql`.** It only
   adds rows, so it is safe to run more than once. A re-run refreshes each
   feature's description and never overwrites a plan value someone has
   changed since.

#### 🧩 Migration `043` — the form rate-limit table (needed on an EXISTING database)

`web/_sql/migrations/043_form_attempts.sql` creates `tblFormAttempts`, the
table registration, the contact form and forgot-password now use to count
attempts per IP address per hour (#212). A fresh install gets the same table
from schema `044_form_attempts.sql`, so it does not need this file. A
database installed before this change (#212) landed does, together with seed
`057_form_rate_limit_settings.sql` above.

**Why it matters.** Without the table, `g2ml_formRateLimitTake()`
(`web/_functions/form_rate_limit.php`) cannot count attempts and refuses every
one ("We cannot process this form just now") — registration, the contact form
and forgot-password all stop working — until this migration has run. See owner action **A30** in
[PRE_LAUNCH_CHECKLIST.md](../PRE_LAUNCH_CHECKLIST.md).

`CREATE TABLE IF NOT EXISTS` makes this migration safe to run more than
once, including on a database that already has the table.

#### 🧩 Migration `044` — organisation links share the g2my.link codes (needed on an EXISTING database)

`web/_sql/migrations/044_shared_g2my_codes.sql` (#289, owner decision D-003)
adds the column `tblShortURLs.sharedShortCode`, a unique key on it and a
check, then fills it in for the links that belong on g2my.link. A fresh
install gets the column, key and check from schema
`020_shorturls_categories_tags.sql`.

**What it fixes.** A member of an organisation with no verified short web
address of its own used to be given a `g2my.link/CODE` address that never
opened, because g2my.link only looked among the default organisation's links.
Now each code on g2my.link is used once across everybody: the default
organisation, people with no organisation, and members of organisations
without their own domain. The click is counted for the organisation that owns
the link, so it sees the link's statistics. An organisation with its own
verified **default** short domain keeps its own set of codes on that domain.

**Which existing links it puts on g2my.link.** Every default-organisation link
first (they are the ones that open there today, so they always keep their
code); then every link of an organisation with no verified, active, default
short domain — active links before disabled ones, oldest first. When two
organisations hold the same code, the first in that order gets it, and the
migration **prints a list of the links it left out**, each beside the link
that holds the code. None of those links opened on g2my.link before either,
so nothing that worked stops working, but each needs a decision: a new code,
or a short domain for its organisation. Links on an organisation's own domain
are untouched and keep opening there.

**Order on an existing database — it matters:**

1. Run `044_shared_g2my_codes.sql`. Read the "links left off g2my.link" list
   it prints (expected: no rows) and the "mismatched codes" count
   (expected: 0).
2. Deploy the new code (the new `createShortURL()` writes the column, so the
   column must exist first).
3. Re-import **both** stored procedures, `sp_generateShortCode.sql` and
   `sp_lookupShortURL.sql`. The new redirect lookup reads the column, so it
   must not be imported before step 1.
4. Run `044` once more. It is safe to repeat: it only fills in rows whose
   column is still empty, and skips a code that is already taken. This picks
   up any default-organisation link the old code created between steps 1
   and 2 (the old code does not fill the column in, so such a link would
   answer "not found" on g2my.link until this step).
5. Apply seed `076_shared_g2my_codes_translations.sql` (one help paragraph).

**Check it worked:** follow one default-organisation link and one link made by
a member of an organisation without its own domain on `g2my.link`; both should
open. Then `SELECT COUNT(*) FROM tblShortURLs WHERE orgHandle = '[default]' AND
sharedShortCode IS NULL;` should be `0`.

It needs no special database privileges: no stored procedure, trigger or
function, just `ALTER TABLE`, `UPDATE` and `SELECT`. Tested on MySQL 8.4 and
MariaDB 11.4.

#### 🧩 Migration `045` — the link choice on deletion requests (needed on an EXISTING database)

`web/_sql/migrations/045_deletion_link_choice.sql` adds the column
`tblDataDeletionRequests.linkChoice`, which records what a person wants done
with their short links when their account is erased (#246, owner decisions
D-017 and D-021). Requests already waiting get `off`. A fresh install gets the
column from schema `034_legal_compliance.sql`, so it does not need this file.

**Run it, and seed `077_deletion_link_choice_translations.sql`, before
uploading the code.** The new code reads and writes the column. Without it the
privacy pages cannot list or record requests and the scheduled erasure job
cannot run. The migration checks for the column first, so it is safe to run
more than once. See owner action **A32** in
[PRE_LAUNCH_CHECKLIST.md](../PRE_LAUNCH_CHECKLIST.md).

#### 🧩 Migration `046` — the staff block on short links (needed on an EXISTING database)

`web/_sql/migrations/046_staff_link_switch.sql` adds the columns
`staffDisabledAt`, `staffDisabledByUserUID`, `staffDisableReason` and
`staffPreviousIsActive` to `tblShortURLs`, and the check
`CK_url_staff_disabled_off`, which refuses any attempt to switch on a link
while staff have it switched off (#325, owner decisions D-023 and D-026). No
existing link changes. A fresh install gets all of it from schema
`020_shorturls_categories_tags.sql`, so it does not need this file.

**Do migration `044` first** (owner action **A33**): the procedure re-imported
below also reads its `sharedShortCode` column. **Then run `046`, re-import
`web/_sql/procedures/sp_lookupShortURL.sql`, seed
`079_staff_link_switch_translations.sql`, and upload the code.** My Links, link
editing and switching off, the link information page, the staff page and account
erasure use the new columns, so without them they fail. The migration checks for
each part first, so it is safe to run more than once. It changes
`tblShortURLs`, so run it at a quiet time. See owner action **A34** in

#### 🔗 Migration `048` — links that do not open show a page (needed on an EXISTING database)

`web/_sql/migrations/048_default_org_dead_link_page.sql` clears the fallback
address `https://go2my.link` that older installs gave the default organisation
(#290, owner decision D-006). While it is there, a short link that does not
exist, has expired or is switched off sends the visitor to the homepage with no
explanation. Any other address an owner set is kept, and no other organisation
is changed. A fresh install does not need it (seed
`002_default_organisation.sql` now stores no address). It is safe to run more
than once. Apply seed `081_dead_link_page_translations.sql` at the same time.
The setting `redirect.fallback_url` is not changed: it is where a bare
g2my.link visit, with no code, goes.

**Check it worked:** open `https://g2my.link/` followed by a code that does not
exist. A "Link Not Found" page should show, with status 404.

See owner action **A36** in
[PRE_LAUNCH_CHECKLIST.md](../PRE_LAUNCH_CHECKLIST.md).

### 🔄 Migration (from MWlink)

> 📋 **See [MIGRATION_PLAN.md](MIGRATION_PLAN.md) for the comprehensive step-by-step migration execution plan**, including pre-migration checklist, DNS cutover, rollback procedures, and post-launch monitoring.

**Summary of migration steps:**

1. 🗄️ Create new database alongside existing `mwtools_mwlink`
2. 📋 Import all 17 schema files in filename order (`000`, `010`–`015`,
   `020`–`021`, `030`–`036`, `044` — numbering is deliberately gapped; there is no
   `001`–`009`)
3. 🔧 Import both stored procedures (`sp_generateShortCode.sql`,
   `sp_lookupShortURL.sql`). ⚠️ **Historical:** `sp_logActivity.sql` was
   **DELETED** — the app now performs a direct `INSERT` into `tblActivityLog`
   instead (`web/_functions/activity_logger.php`); do not look for or try to
   import this file
4. 🌱 Import all 46 seed files in filename order (the list is in step 5 of
   **New Installation** above)
5. 🧪 Run dry-run verification: `web/_sql/dry_run.sql`
6. ▶️ Run migration scripts from `web/_sql/migrations/` in order — `001`–`004`,
   `006`, `008`–`019`, then `048` (`007` is optional, see Step 7; there is no `005`). See
   the 🧭 **fresh-install skip list** immediately below before skipping any of
   them. 🚨 **Then run `044_shared_g2my_codes.sql` straight after `004`, even
   on a fresh install** (#289): `004` leaves the new `sharedShortCode` column
   empty, and until `044` fills it in every migrated link answers "not found"
   on g2my.link. Read the list of left-out links it prints. See
   `docs/MIGRATION_PLAN.md` Step D4a
7. ⏳ Optional: Run activity log migration (`007`) in batches
8. ✅ Verify all 480 URLs resolve correctly
9. ✅ Verify organisation domain mappings — migration `001` now grandfathers
   every pre-existing short domain as `verificationStatus='verified'` (#160),
   so migrated partner domains are routable immediately without re-proving
   DNS ownership
10. 🔀 Switch application to new database
11. 🗑️ Decommission old database after 30-day verification period

#### 🧭 Fresh-install migration skip list

Migrations `009`–`019` each patch a column/index/constraint that is **already
present** in the current `web/_sql/schema/*.sql` files — every one of them
carries a "FRESH INSTALLS DO NOT NEED THIS FILE" header. They exist only to
bring a database **provisioned from an OLDER schema snapshot** up to date.
**Do not assume your target database is fresh** without checking — this
platform's own database has been built up incrementally across Phases 1–8,
not from a single fresh import of today's files:

| Migration | Skip if genuinely fresh? | Notes |
| --- | --- | --- |
| `009_cuercode_qr_integration.sql` | Yes — **and MUST skip** | NOT idempotent: errors if the CueRCode columns already exist |
| `010_org_invite_pending_key.sql` | Yes — **and MUST skip** | NOT idempotent: errors if `pendingKey` already exists |
| `011_api_request_log_index.sql` | Optional | Idempotent — harmless no-op if already applied |
| `012_api_request_log_ip_index.sql` | Optional | Idempotent — harmless no-op if already applied |
| `013_short_domain_verification.sql` | Optional | Idempotent — also grandfathers pre-existing domains to `verified` |
| `014_activitylog_analytics_indexes.sql` | Optional | Idempotent — harmless no-op if already applied |
| `015_org_short_domain_linkspage.sql` | Optional | Idempotent — harmless no-op if already applied |
| `016_linkspage_custom_html.sql` | 🚨 **No — MANDATORY** | Idempotent (safe to run either way), but without it `tblSubscriptionTiers.hasCustomHTML` is missing, `g2ml_getOrgTier()`'s lookup query errors, and the function fails OPEN — **ALL tier gating (link/domain/API limits + feature flags) silently disables for every org** |
| `017_apikey_prefix_unique.sql` | Optional | Idempotent — harmless no-op if already applied |
| `018_deprecate_org_domains.sql` | Optional (Part 1 only) | Part 1 (table `COMMENT`) is idempotent metadata-only; Part 2 is a manual, owner-reviewed reconciliation audit — **never auto-run** |
| `019_settings_scope_dedupe.sql` | 🚨 **No — MANDATORY** | NOT idempotent (forward-only, one-shot; errors if re-applied) — run exactly once. Without it, duplicate System-scope `tblSettings` rows can coexist (MySQL treats NULL as distinct in the old UNIQUE key) and `getSetting()` may read a stale/ambiguous duplicate for platform-wide kill-switches and feature toggles |

> 🧩 **Migrations `020` and `021`** work differently from the ones in this
> table: they add whole tables and rows rather than patching a column. A
> genuinely fresh install does not need either one, because schema `036` and
> seeds `018`, `019` and `023` already contain the same things. An existing
> database needs both, in that order — and needs the `billing.*` switch check
> **before** the code is deployed. See **Migration `021` — the LinksPage
> feature registry** above.

## 🔑 Credentials Setup

1. 📋 Copy the template — `auth_creds.php` itself is gitignored and **absent
   from a fresh clone/deploy** (only the template is tracked):

   ```bash
   cp web/_auth_keys/auth_creds.example.php web/_auth_keys/auth_creds.php
   ```

2. 🗄️ Set database credentials: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
3. 🔐 Set encryption salt: `ENCRYPTION_SALT` (generate a random 64-character hex string)
4. 🔑 Set third-party API keys as needed (reCAPTCHA, Turnstile, OAuth providers)
5. 🔗 Ensure each component's thin include exists —
   `<Comp>/.auth/auth_creds.php` for `Go2My.Link`, `G2My.Link`, and
   `Lnks.page` — each just `require_once`s the shared file above so all three
   sites use the same database and keys. The web installer creates these
   automatically (0700 directory, 0600 file); the automatic upload never
   creates them (see Step 9 of the owner set-up above).

> 🔒 **Security:** `auth_creds.php` (shared) and `.auth/auth_creds.php`
> (per-component, thin) are excluded from git via `.gitignore` and blocked
> from web access via `.htaccess`.

## 🔒 SSL / HTTPS

- ✅ All three domains use HTTPS (Let's Encrypt via Dreamhost)
- ✅ HTTPS is enforced via `.htaccess`:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- ✅ HSTS headers set in `.htaccess`

## 🛡️ Security Headers

Applied via `.htaccess` on all domains:

```apache
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"
```

Content Security Policy (CSP) is configured per-component to allow required CDN sources.

## ⏰ Scheduled Jobs (GDPR Deletion + Retention)

The account-deletion (#163) and data-retention (#167) jobs run from a
token-guarded endpoint, `https://admin.go2my.link/cron`
(`web/Go2My.Link/_admin/public_html/cron.php`), meant to be hit by an
external scheduler — Dreamhost Panel's own cron, cron-job.org, or a GitHub
Actions `schedule:` job. Until #198 was fixed, that endpoint could never run
anything: its shared library (`web/_functions/cron.php`) has the same file
name as the endpoint, and the library's direct-access guard compared file
names only, so the guard mistook the endpoint's own request for someone
opening the library directly and redirected before any job code ran. Every
direct-access guard in the codebase now compares real, resolved file paths
instead, which tells the two files apart.

The endpoint answers "not found" unless the copy is marked live (see "How the
software knows which copy it is") and the address is not a test address,
because test copies share the live database
([D-029](decisions/D-029-test-sites-shared-database.md)). Point the scheduler at
the live address only.

The endpoint itself still ships switched off (`cron.enabled` and
`cron.dispatch_token` both default to off/empty in
`web/_sql/seeds/020_cron_gdpr_settings.sql`), so this fix alone does not turn
anything on. Choosing and wiring the actual external scheduler is #178.

## 📊 Monitoring

- 🐛 **PHP errors:** Logged to `tblErrorLog` (custom error handler)
- 📊 **Activity:** Logged to `tblActivityLog` (all requests)
- 📋 **Server logs:** Dreamhost provides access/error logs via panel
- ⏱️ **Uptime:** External monitoring recommended (e.g., UptimeRobot)

## ⏪ Rollback Procedure

1. 🔍 Identify the issue and affected files
2. 🔄 Revert files via SFTP (restore from `_backups/` or git)
3. 🗄️ If database changes are involved, restore from backup
4. ✅ Verify rollback resolved the issue
5. 📝 Document the incident in `DEV_NOTES.md`

## ✅ Pre-Launch Checklist

- [ ] ✅ All 480 migrated URLs resolve correctly
- [ ] 🌐 Custom organisation domains working
- [ ] 🔒 HTTPS enforced on all domains
- [ ] 🛡️ Security headers in place
- [ ] 🚨 Each copy in use has its own `_auth_keys/` and `<Component>/.auth/`, all naming the same database (Step 9 of the owner set-up)
- [ ] 🚨 #93 — legacy `public_html_legacy/dbConfig.php` credential rotated and file removed from the server
- [ ] 🔒 Private directories (`_auth_keys`, `.auth`, `_includes`, `_functions`) not web-accessible
- [ ] 🐛 Error logging to database working
- [ ] ❌ Debug mode disabled in production
- [ ] 🔒 `auth_creds.php` not accessible via browser
- [ ] 📋 `.gitignore` excludes all sensitive files
- [ ] 🌐 DNS cutover plan documented and tested
- [ ] ⏪ Old service rollback procedure documented

## 📚 Related Documentation

- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- 🗄️ [DATABASE.md](DATABASE.md) — Database schema reference
- 📡 [API.md](API.md) — API endpoint reference
- 🚀 [MIGRATION_PLAN.md](MIGRATION_PLAN.md) — Migration execution plan & launch checklist
- 🌍 [TRANSLATION.md](TRANSLATION.md) — Translation guide
- ♿ [ACCESSIBILITY.md](ACCESSIBILITY.md) — Accessibility standards
