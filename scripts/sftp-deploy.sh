#!/usr/bin/env bash
#
# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.
#
# =============================================================================
# Go2My.Link — upload web/ to one channel's own folder on the server (#158)
# =============================================================================
#
# Run by .github/workflows/sftp-deploy.yml. Owner set-up steps are in
# docs/DEPLOYMENT.md.
#
# THE LAYOUT ON THE SERVER
#
#   SFTP_BASE_PATH is a folder used only by this service
#   (docs/decisions/D-007-deploy-separate-folder.md). Inside it, every channel
#   has its own complete copy of web/ (D-019-test-sites-own-copy.md):
#
#       branch main              ->  SFTP_BASE_PATH/live/
#       branch alpha             ->  SFTP_BASE_PATH/alpha/
#       branch beta              ->  SFTP_BASE_PATH/beta/
#       branch release-candidate ->  SFTP_BASE_PATH/rc/     (manual run only)
#
#   The release-candidate copy is a test site with its own address
#   (docs/decisions/D-032-release-candidate-own-copy.md).
#
#   Every other ref is refused. Nothing falls back to "live". The code finds
#   its shared folders by climbing up from the file that is running, so each
#   copy only ever reads its own shared code, credentials, uploads and
#   installer lock.
#
# WHAT IT WILL NOT DO
#
#   * It will not connect to a server whose identity is not listed in
#     SFTP_KNOWN_HOSTS.
#   * It will not upload into a folder that lacks the marker file
#     .g2ml-service-folder (so a wrong SFTP_BASE_PATH stops the run).
#   * It deletes server files only inside the folders in OWNED_FOLDERS below.
#
# INPUTS (environment variables)
#   DEPLOY_REF        full git ref, for example refs/heads/main
#   DEPLOY_EVENT      "push" or "workflow_dispatch"
#   DEPLOY_DRY_INPUT  the manual run's dry_run box; only the exact text
#                     "false" turns a manual run into a real upload
#   SFTP_HOST, SFTP_USER, SFTP_BASE_PATH, SFTP_KNOWN_HOSTS   required
#   SFTP_PORT                                                optional (22)
#   SFTP_KEY or SFTP_PASSWORD                                one is required
#
# @package    Go2My.Link
# @subpackage Scripts
# =============================================================================

set -euo pipefail

# Files holding secrets (key, password, command files) are made private. The
# setting is changed back to 022 before the upload, because lftp applies it to
# the permissions of what it puts on the server.
umask 077

MARKER_NAME=".g2ml-service-folder"
MARKER_TEXT="go2mylink-service-folder"

# Folders (relative to web/) that only this upload writes to. Server files that
# are not in the repository ARE REMOVED from these, apart from the names in
# EXCLUDES. Do not add a folder that holds nothing but .gitkeep: it looks empty
# to the upload, which would then remove the server's copy.
OWNED_FOLDERS=(
    "_functions"
    "_includes"
    "_libraries"
    "_schemas"
    "_sql"
    "Go2My.Link/_functions"
    "Go2My.Link/_admin/public_html"
    "Go2My.Link/public_html"
    "G2My.Link/_functions"
    "G2My.Link/public_html"
    "Lnks.page/_functions"
    "Lnks.page/public_html"
)

# Never uploaded and never removed. Each pattern is a regular expression matched
# against the path inside the folder being mirrored; a folder's path ends in "/".
#
# .user.ini and .htaccess are NOT here on purpose: the repository owns them (they
# keep PHP errors off the screen, #319), so the upload replaces any change made
# to them on the server.
EXCLUDES=(
    # Repository clutter
    '\.DS_Store$'
    'Thumbs\.db$'
    '(^|/)\.git/'
    '(^|/)\.github/'
    '\.gitignore$'
    '\.gitkeep$'
    '\.gitattributes$'
    '(^|/)README\.md$'
    '(^|/)DEV_NOTES\.md$'
    '(^|/)CLAUDE\.md$'
    '(^|/)\.vscode/'
    '(^|/)\.idea/'
    # Files and folders that belong to the server
    'auth_creds\.php$'
    '(^|/)_auth_keys/'
    '(^|/)\.auth/'
    '\.install_token$'
    '\.installed$'
    '(^|/)_uploads/'
    '(^|/)_backups/'
    '(^|/)_logs/'
    '(^|/)private_html/'
    '(^|/)\.well-known/'
    '(^|/)\.dh-diag(/|$)'
    '(^|/)\.htpasswd$'
    '(^|/)error_log$'
    '(^|/)php_errors\.log$'
    '(^|/)\.g2ml-service-folder$'
    # Written by this script itself, one per copy
    '(^|/)\.g2ml-channel$'
    # The GeoIP database is uploaded on its own, without removing anything
    '\.mmdb$'
)

fail()
{
    echo "::error::$1"
    exit 1
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${SCRIPT_DIR}/.."

# -----------------------------------------------------------------------------
# Which copy, and is this a rehearsal?
# -----------------------------------------------------------------------------
case "${DEPLOY_REF:-}" in
    refs/heads/main)  CHANNEL="live" ;;
    refs/heads/alpha) CHANNEL="alpha" ;;
    refs/heads/beta)  CHANNEL="beta" ;;
    refs/heads/release-candidate) CHANNEL="rc" ;;
    *) fail "No copy on the server belongs to '${DEPLOY_REF:-}'. Only the branches main, alpha, beta and release-candidate are uploaded." ;;
esac

DRY=""
if [[ "${DEPLOY_EVENT:-}" == "workflow_dispatch" ]]
then
    if [[ "${DEPLOY_DRY_INPUT:-}" != "false" ]]
    then
        DRY="--dry-run"
    fi
elif [[ "${DEPLOY_EVENT:-}" != "push" ]]
then
    fail "Unexpected event '${DEPLOY_EVENT:-}'. Only a push or a manual run uploads."
fi

if [[ "${CHANNEL}" == "rc" && "${DEPLOY_EVENT}" != "workflow_dispatch" ]]
then
    fail "The release-candidate copy is uploaded by a manual run only."
fi

# -----------------------------------------------------------------------------
# Settings. Anything missing or odd stops the run before any connection.
# -----------------------------------------------------------------------------
HOST="${SFTP_HOST:-}"
LOGIN="${SFTP_USER:-}"
PORT="$(printf '%s' "${SFTP_PORT:-}" | tr -d '[:space:]')"
BASE="${SFTP_BASE_PATH:-}"
KEY="${SFTP_KEY:-}"
PASSWORD="${SFTP_PASSWORD:-}"
KNOWN_HOSTS="${SFTP_KNOWN_HOSTS:-}"

if [[ ! "${HOST}" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]]
then
    fail "The secret SFTP_HOST is missing or is not a plain server name."
fi

if [[ ! "${LOGIN}" =~ ^[A-Za-z0-9_][A-Za-z0-9._-]*$ ]]
then
    fail "The secret SFTP_USER is missing or has unexpected characters."
fi

if [[ -z "${PORT}" ]]
then
    PORT="22"
fi

if [[ ! "${PORT}" =~ ^[1-9][0-9]{0,4}$ ]]
then
    fail "The secret SFTP_PORT is not a port number."
fi

if [[ "${PORT}" -gt 65535 ]]
then
    fail "The secret SFTP_PORT is not a port number."
fi

while [[ "${BASE}" == */ ]]
do
    BASE="${BASE%/}"
done

if [[ ! "${BASE}" =~ ^/?[A-Za-z0-9_][A-Za-z0-9._/-]*$ ]]
then
    fail "The secret SFTP_BASE_PATH is missing or is not a plain folder path (letters, numbers, . _ - and /)."
fi

case "/${BASE}/" in
    */../*) fail "The secret SFTP_BASE_PATH must not contain a '..' part." ;;
    */./*)  fail "The secret SFTP_BASE_PATH must not contain a '.' part." ;;
    *//*)   fail "The secret SFTP_BASE_PATH must not contain '//'." ;;
esac

if [[ "${BASE}" =~ ^/home/[^/]+$ ]]
then
    fail "The secret SFTP_BASE_PATH is a whole home folder. Give the folder used only by this service."
fi

if [[ -z "${KNOWN_HOSTS//[[:space:]]/}" ]]
then
    fail "The secret SFTP_KNOWN_HOSTS is missing. It pins the server's identity; see docs/DEPLOYMENT.md."
fi

AUTH="key"
if [[ -z "${KEY//[[:space:]]/}" ]]
then
    AUTH="password"
    if [[ -z "${PASSWORD}" ]]
    then
        fail "Neither SFTP_KEY nor SFTP_PASSWORD is set."
    fi

    case "${PASSWORD}" in
        *'"'* | *\\* | *$'\n'* | *$'\r'*)
            fail "SFTP_PASSWORD contains a double quote, a backslash or a line break, which cannot be passed safely. Use SFTP_KEY, or change the password."
            ;;
    esac
fi

WORK="$(mktemp -d)"
trap 'rm -rf "${WORK}"' EXIT

printf '%s\n' "${KNOWN_HOSTS}" | tr -d '\r' > "${WORK}/known_hosts"

HOST_LOOKUP="${HOST}"
if [[ "${PORT}" != "22" ]]
then
    HOST_LOOKUP="[${HOST}]:${PORT}"
fi

if ! ssh-keygen -F "${HOST_LOOKUP}" -f "${WORK}/known_hosts" > /dev/null 2>&1
then
    fail "SFTP_KNOWN_HOSTS has no entry for the server in SFTP_HOST (and SFTP_PORT). The entry must use exactly the same name; see docs/DEPLOYMENT.md."
fi

# -----------------------------------------------------------------------------
# The SSH command lftp uses. "StrictHostKeyChecking=yes" with our own list, and
# no other list, means an unknown or changed server is refused. -F /dev/null
# ignores any SSH settings file on the machine.
# -----------------------------------------------------------------------------
SSH_COMMAND="ssh -a -x -F /dev/null -o StrictHostKeyChecking=yes"
SSH_COMMAND="${SSH_COMMAND} -o UserKnownHostsFile=${WORK}/known_hosts -o GlobalKnownHostsFile=/dev/null"

if [[ "${AUTH}" == "key" ]]
then
    printf '%s\n' "${KEY}" | tr -d '\r' > "${WORK}/deploy_key"
    SSH_COMMAND="${SSH_COMMAND} -i ${WORK}/deploy_key -o IdentitiesOnly=yes -o BatchMode=yes"
    LOGIN_PASSWORD=""
else
    SSH_COMMAND="${SSH_COMMAND} -o PubkeyAuthentication=no -o NumberOfPasswordPrompts=1"
    LOGIN_PASSWORD="${PASSWORD}"
fi

SSH_COMMAND="${SSH_COMMAND} -p ${PORT}"

# Start an lftp command file: settings, then the connection.
# cmd:fail-exit stops at the first command that fails, so a failed upload fails
# the run instead of passing silently.
write_header()
{
    {
        echo "set cmd:fail-exit yes"
        echo "set net:timeout 20"
        echo "set net:max-retries 3"
        echo "set net:reconnect-interval-base 5"
        echo "set net:reconnect-interval-multiplier 1"
        echo "set sftp:auto-confirm no"
        echo "set sftp:connect-program \"${SSH_COMMAND}\""
        printf 'open -u "%s,%s" sftp://%s:%s\n' "${LOGIN}" "${LOGIN_PASSWORD}" "${HOST}" "${PORT}"
    } > "$1"
}

# -----------------------------------------------------------------------------
# Is this really the service folder? Read the marker file the owner put there.
# -----------------------------------------------------------------------------
write_header "${WORK}/marker.lftp"
{
    echo "get \"${BASE}/${MARKER_NAME}\" -o \"${WORK}/marker\""
    echo "bye"
} >> "${WORK}/marker.lftp"

if ! lftp -f "${WORK}/marker.lftp"
then
    fail "Could not read ${MARKER_NAME} in the folder named by SFTP_BASE_PATH. Either the server's identity or the login was refused, or the marker file or folder is not there. Nothing was uploaded or removed."
fi

if ! grep -qx "${MARKER_TEXT}[[:space:]]*" "${WORK}/marker" 2> /dev/null
then
    fail "${MARKER_NAME} does not contain the line '${MARKER_TEXT}'. Nothing was uploaded or removed."
fi

TARGET="${BASE}/${CHANNEL}"

echo "Copy on the server: ${CHANNEL}"
if [[ -n "${DRY}" ]]
then
    echo "DRY RUN: nothing will be uploaded or removed."
fi

if [[ -n "${GITHUB_OUTPUT:-}" ]]
then
    {
        echo "channel=${CHANNEL}"
        echo "auth=${AUTH}"
        if [[ -n "${DRY}" ]]
        then
            echo "dry=yes"
        else
            echo "dry=no"
        fi
    } >> "${GITHUB_OUTPUT}"
fi

# Once this copy has been installed, stop uploading the installer, so that a
# deleted install/ folder stays deleted. Only the server saying "No such file"
# counts as "not installed". Any other failure (a dropped connection, a refused
# permission) stops the run, because guessing would put the installer back.
write_header "${WORK}/installed.lftp"
{
    echo "get \"${TARGET}/_auth_keys/.installed\" -o \"${WORK}/installed\""
    echo "bye"
} >> "${WORK}/installed.lftp"

INSTALLED="no"
if LC_ALL=C lftp -f "${WORK}/installed.lftp" > "${WORK}/installed.log" 2>&1
then
    INSTALLED="yes"
elif ! grep -q 'Access failed: No such file' "${WORK}/installed.log"
then
    sed -E 's#sftp://[^/ ]*@#sftp://#g' "${WORK}/installed.log"
    fail "Could not tell whether this copy is already installed (see the message above). Nothing was uploaded or removed."
fi

# -----------------------------------------------------------------------------
# Build the upload.
# -----------------------------------------------------------------------------
EXCLUDE_FLAGS=""
for pattern in "${EXCLUDES[@]}"
do
    # Single quotes: lftp reads an unquoted "|" as a pipe (#156).
    EXCLUDE_FLAGS="${EXCLUDE_FLAGS} --exclude '${pattern}'"
done

# Check every owned folder before anything is sent: an empty or missing source
# folder must never be mirrored with removal switched on.
for folder in "${OWNED_FOLDERS[@]}"
do
    if [[ ! -d "web/${folder}" ]]
    then
        fail "web/${folder} is not in the checkout. Nothing was uploaded or removed."
    fi

    if [[ -z "$(find "web/${folder}" -type f ! -name .gitkeep -print -quit)" ]]
    then
        fail "web/${folder} holds no files. Nothing was uploaded or removed."
    fi
done

write_header "${WORK}/deploy.lftp"

# The file that tells the application which copy it is. Only a copy marked
# "live" runs timed jobs and clean-ups.
printf '%s\n' "${CHANNEL}" > "${WORK}/channel"
chmod 644 "${WORK}/channel"

{
    if [[ -z "${DRY}" ]]
    then
        echo "mkdir -p -f \"${TARGET}\""
        # Before any code, so that new code never runs without it.
        echo "put \"${WORK}/channel\" -o \"${TARGET}/.g2ml-channel\""
    fi

    # Step 1: add and update everything under web/. Removes nothing.
    ROOT_FLAGS="${EXCLUDE_FLAGS}"
    if [[ "${INSTALLED}" == "yes" ]]
    then
        ROOT_FLAGS="${ROOT_FLAGS} --exclude '^Go2My\\.Link/public_html/install/'"
    fi
    echo "mirror --reverse --verbose ${DRY} --no-empty-dirs ${ROOT_FLAGS} \"web/\" \"${TARGET}\""

    # Step 2: in the owned folders only, remove what the repository no longer has.
    for folder in "${OWNED_FOLDERS[@]}"
    do
        FOLDER_FLAGS="${EXCLUDE_FLAGS}"
        if [[ "${INSTALLED}" == "yes" && "${folder}" == "Go2My.Link/public_html" ]]
        then
            FOLDER_FLAGS="${FOLDER_FLAGS} --exclude '^install/'"
        fi
        echo "mirror --reverse --delete --verbose ${DRY} --no-empty-dirs ${FOLDER_FLAGS} \"web/${folder}/\" \"${TARGET}/${folder}\""
    done

    # The GeoIP database (#43): only when this run fetched one, and never with
    # removal, so a failed download leaves the server's database alone.
    if [[ -s "web/_libraries/maxminddb/GeoLite2-Country.mmdb" ]]
    then
        echo "mirror --reverse --verbose ${DRY} --no-empty-dirs \"web/_libraries/maxminddb/\" \"${TARGET}/_libraries/maxminddb\""
    fi

    echo "bye"
} >> "${WORK}/deploy.lftp"

if [[ "${INSTALLED}" == "yes" ]]
then
    echo "This copy is already installed: the install/ folder is left alone."
fi

if [[ -n "${DRY}" ]]
then
    echo "A real run also writes ${CHANNEL}/.g2ml-channel containing '${CHANNEL}'."
fi

# From here on, new files on the server must be readable by the web server:
# folders 755, files 644. The command file made above stays private.
umask 022

# The sed removes "user:password@" from any address lftp prints.
if lftp -f "${WORK}/deploy.lftp" 2>&1 \
    | sed -E 's#sftp://[^/ ]*@#sftp://#g' \
    | tee "${WORK}/deploy.log"
then
    STATUS=0
else
    STATUS=$?
fi

echo ""
echo "===== REMOVALS (lines of the log above that remove something) ====="
if ! grep -E '^(rm|rmdir) |^Removing old ' "${WORK}/deploy.log"
then
    echo "(no removal lines found)"
fi
echo "===== END OF REMOVALS ====="

if [[ "${STATUS}" -ne 0 ]]
then
    fail "The upload stopped with an error (lftp exit status ${STATUS}). The copy on the server may be only partly updated."
fi
