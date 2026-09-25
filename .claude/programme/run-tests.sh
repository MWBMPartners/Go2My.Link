#!/bin/sh
# .claude/programme/run-tests.sh — the checks every build-programme item must
# pass (committed with the programme on 2026-09-23; see the README beside it).
# Runs, in order, and reports each result with its REAL exit code:
#   1. php -l on every PHP file changed against alpha (PHP 8.4 in Docker)
#   2. the unit suite on PHP 8.4 in Docker
#   3. the integration suite against a fresh MySQL 8.4 container created with
#      utf8mb4_unicode_ci (NO MYSQL_DATABASE, so the schema's own CREATE DATABASE
#      decides the collation — the fault behind issue #196), using PHP 8.4 with
#      the mysqli extension (image built once and cached).
# Usage: sh .claude/programme/run-tests.sh [unit|integration|all]   (default all)
set -u
# The repository this script lives in, worked out from the script's own
# location, so a clone anywhere on any machine works and no personal path is
# written into the repository. `pwd -P` resolves any links.
SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd -P)
REPO=$(CDPATH='' cd -- "${SCRIPT_DIR}/../.." && pwd -P)
MODE="${1:-all}"
IMAGE="g2ml-php:8.4-mysqli"
NET="g2ml-test-net"
DB="g2ml-test-mysql"
# The MySQL image keeps its data in a separate storage area (an anonymous
# Docker volume). A plain "docker rm -f" removes the container but LEAVES that
# volume behind, holding a whole test database, and nothing on screen says so.
# Every removal below therefore uses "docker rm -f -v", which takes the volume
# with it. Found 2026-09-25: one night's builds had left seven such volumes,
# each holding mwtools_Go2MyLink (the machine-wide rule on throwaway
# databases in ~/.claude/CLAUDE.md, set 2026-09-24, explains the wider leak).
OVERALL=0
cd "${REPO}" || exit 2

echo "=== 1. php -l on changed PHP files (vs alpha) ==="
CHANGED=$(git diff --name-only --diff-filter=ACMR alpha -- '*.php'; git ls-files --others --exclude-standard -- '*.php')
LINT_FAIL=0
for FILE in ${CHANGED}; do
    if [ -f "${FILE}" ]; then
        OUT=$(docker run --rm -v "${REPO}":/app:ro -w /app php:8.4-cli php -l "${FILE}" 2>&1)
        if [ $? -ne 0 ]; then
            echo "LINT FAIL: ${OUT}"
            LINT_FAIL=1
        fi
    fi
done
if [ "${LINT_FAIL}" -eq 0 ]; then
    echo "lint: OK"
else
    OVERALL=1
fi

if [ "${MODE}" = "unit" ] || [ "${MODE}" = "all" ]; then
    echo "=== 2. unit suite (PHP 8.4) ==="
    docker run --rm -v "${REPO}":/app:ro -w /app php:8.4-cli php tests/run.php > /tmp/g2ml_unit.txt 2>&1
    UNIT_EXIT=$?
    grep -E "^(FAIL|ERROR)" /tmp/g2ml_unit.txt | head -40
    tail -1 /tmp/g2ml_unit.txt
    echo "unit exit=${UNIT_EXIT}"
    if [ "${UNIT_EXIT}" -ne 0 ]; then
        OVERALL=1
    fi
fi

if [ "${MODE}" = "integration" ] || [ "${MODE}" = "all" ]; then
    echo "=== 3. integration suite (MySQL 8.4, utf8mb4_unicode_ci, PHP 8.4 + mysqli) ==="
    if ! docker image inspect "${IMAGE}" >/dev/null 2>&1; then
        printf 'FROM php:8.4-cli\nRUN docker-php-ext-install mysqli\n' | docker build -q -t "${IMAGE}" - >/dev/null
    fi
    docker rm -f -v "${DB}" >/dev/null 2>&1
    docker network create "${NET}" >/dev/null 2>&1
    docker run -d --name "${DB}" --network "${NET}" -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
        mysql:8.4 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
    # Wait for the FINAL server. The image first runs a temporary server (on
    # port 0) to initialise itself, answers pings, then restarts; importing
    # during that window fails with "Can't connect". The final server's
    # "Version: ... port: 3306" log line is the reliable signal.
    TRIES=0
    until docker logs "${DB}" 2>&1 | grep -q "Version:.*port: 3306"; do
        TRIES=$((TRIES + 1))
        if [ "${TRIES}" -gt 120 ]; then
            echo "MySQL did not start"
            # Remove it here too; this exit used to leave the container
            # (and its volume) running until the next test run.
            docker rm -f -v "${DB}" >/dev/null 2>&1
            exit 1
        fi
        sleep 2
    done
    until docker exec "${DB}" mysql -uroot -e "SELECT 1" >/dev/null 2>&1; do
        sleep 1
    done
    IMPORT_FAIL=0
    for DIR in schema procedures seeds; do
        for FILE in $(find "web/_sql/${DIR}" -maxdepth 1 -name "*.sql" | sort); do
            if ! docker exec -i "${DB}" mysql -uroot < "${FILE}" > /tmp/g2ml_import.txt 2>&1; then
                echo "IMPORT FAIL ${FILE}: $(head -3 /tmp/g2ml_import.txt)"
                IMPORT_FAIL=1
            fi
        done
    done
    if [ "${IMPORT_FAIL}" -ne 0 ]; then
        OVERALL=1
    fi
    docker run --rm --network "${NET}" -v "${REPO}":/app:ro -w /app \
        -e G2ML_TEST_DB_HOST="${DB}" -e G2ML_TEST_DB_PORT=3306 -e G2ML_TEST_DB_NAME=mwtools_Go2MyLink \
        -e G2ML_TEST_DB_USER=root -e G2ML_TEST_DB_PASS= \
        "${IMAGE}" php tests/run_integration.php > /tmp/g2ml_integ.txt 2>&1
    INTEG_EXIT=$?
    grep -E "^(FAIL|ERROR)|SKIPPED" /tmp/g2ml_integ.txt | head -40
    tail -1 /tmp/g2ml_integ.txt
    echo "integration exit=${INTEG_EXIT}"
    if [ "${INTEG_EXIT}" -ne 0 ]; then
        OVERALL=1
    fi
    docker rm -f -v "${DB}" >/dev/null 2>&1
fi

echo "=== OVERALL exit=${OVERALL} ==="
exit "${OVERALL}"
