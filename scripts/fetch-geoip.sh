#!/usr/bin/env bash
#
# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.
#
# =============================================================================
# Go2My.Link — Fetch the GeoLite2-Country GeoIP database (#43)
# =============================================================================
#
# Downloads the MaxMind GeoLite2-Country .mmdb database and places it where
# web/_functions/geolocation.php looks for it by default:
#
#     web/_libraries/maxminddb/GeoLite2-Country.mmdb
#
# The database is a licensed, frequently-updated MaxMind binary artefact — it
# is NEVER committed to git (.gitignore excludes *.mmdb). This script
# re-fetches it at DEPLOY time (see .github/workflows/sftp-deploy.yml) so the
# deployed copy always carries MaxMind's current data. It can also be run
# locally by a developer who has their own MaxMind licence key, for manual
# testing against a real database.
#
# ---------------------------------------------------------------------------
# Graceful-failure contract (IMPORTANT — read before changing this script)
# ---------------------------------------------------------------------------
# This script NEVER exits non-zero. Every failure mode — no licence key
# configured, a network error, an invalid key, a corrupt/unexpected archive —
# prints a NOTICE and exits 0. Two independent things make this safe:
#
#   1. geolocation.php's own graceful-degradation behaviour means a missing
#      database is a total runtime no-op (g2ml_geolocationAvailable() checks
#      file existence before any lookup) — so failing this script is never a
#      *correctness* problem for the running application.
#
#   2. The CALLER (sftp-deploy.yml) only uploads the database file when this
#      script actually produced a fresh one at the destination path. A
#      transient MaxMind outage, or an unset licence key, therefore leaves a
#      PREVIOUSLY deployed, working database completely untouched on the
#      server — this script failing "loud" (non-zero) would otherwise make
#      the caller's own error handling the only thing standing between a
#      flaky download and accidentally deleting a working database.
#
# ---------------------------------------------------------------------------
# Usage
# ---------------------------------------------------------------------------
#   MAXMIND_LICENSE_KEY=xxxx scripts/fetch-geoip.sh [destination-file]
#
#   destination-file  Optional. Defaults to
#                      <repo-root>/web/_libraries/maxminddb/GeoLite2-Country.mmdb
#                      (resolved relative to this script's own location, so it
#                      works correctly regardless of the caller's cwd).
#
# The licence key is never echoed, logged, or passed via a command visible in
# `ps` (it only ever appears inside a curl URL argument written directly by
# this script, not via -v/-x tracing) — see the CLAUDE.md standing instruction
# on handling credentialled commands.
#
# @package    Go2My.Link
# @subpackage Scripts
# @since      v1.1.0 — Phase 7 (#43)
#
# 📖 Reference: https://dev.maxmind.com/geoip/updating-databases/#directly-downloading-databases
# =============================================================================

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

DEST_FILE="${1:-${REPO_ROOT}/web/_libraries/maxminddb/GeoLite2-Country.mmdb}"
DEST_DIR="$(dirname "${DEST_FILE}")"

if [[ -z "${MAXMIND_LICENSE_KEY:-}" ]]
then
    echo "NOTICE: MAXMIND_LICENSE_KEY is not set — skipping GeoIP database fetch."
    echo "        geolocation.php remains a total no-op until BOTH a database is"
    echo "        deployed at '${DEST_FILE}' AND the analytics.geolocation_enabled"
    echo "        setting is turned on."
    exit 0
fi

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

ARCHIVE_PATH="${WORK_DIR}/GeoLite2-Country.tar.gz"

# The licence key rides in the query string — never enable -v/-x here, and
# never echo the assembled URL.
if ! curl -sS --fail -L \
    "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=${MAXMIND_LICENSE_KEY}&suffix=tar.gz" \
    -o "${ARCHIVE_PATH}"
then
    echo "NOTICE: GeoLite2-Country download failed (network error or invalid licence key)."
    echo "        Skipping — any previously deployed database is left untouched."
    exit 0
fi

if [[ ! -s "${ARCHIVE_PATH}" ]]
then
    echo "NOTICE: GeoLite2-Country download produced an empty file — skipping."
    exit 0
fi

if ! tar -xzf "${ARCHIVE_PATH}" -C "${WORK_DIR}" 2>/dev/null
then
    echo "NOTICE: GeoLite2-Country archive failed to extract — skipping."
    exit 0
fi

MMDB_PATH="$(find "${WORK_DIR}" -maxdepth 2 -type f -name 'GeoLite2-Country.mmdb' -print -quit)"

if [[ -z "${MMDB_PATH}" || ! -f "${MMDB_PATH}" ]]
then
    echo "NOTICE: GeoLite2-Country.mmdb was not found inside the downloaded archive — skipping."
    exit 0
fi

mkdir -p "${DEST_DIR}"
cp "${MMDB_PATH}" "${DEST_FILE}"

FILE_SIZE="$(du -h "${DEST_FILE}" 2>/dev/null | cut -f1)"
echo "GeoLite2-Country database fetched successfully: ${DEST_FILE} (${FILE_SIZE})"
exit 0
