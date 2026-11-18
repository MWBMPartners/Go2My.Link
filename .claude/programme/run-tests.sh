#!/bin/sh
# .claude/programme/run-tests.sh — the checks every build-programme item must
# pass (committed with the programme on 2026-09-23; see the README beside it).
# Reworked for issue #308 so it tests more than one PHP version.
#
# For each PHP version in the list, it runs, in order, and reports each result
# with its REAL exit code:
#   1. the strict PHP lint (scripts/php-lint.sh) on every PHP file under web/
#      and tests/, inside that version's image. Chosen over the old "changed
#      files only" loop because it also catches deprecation warnings, which
#      are the whole point of testing newer versions, and it takes seconds.
#   2. the unit suite (tests/run.php)
#   3. the database suite (tests/run_integration.php) against ONE fresh MySQL
#      8.4 container created with utf8mb4_unicode_ci (NO MYSQL_DATABASE, so the
#      schema's own CREATE DATABASE decides the collation — the fault behind
#      issue #196). Before each version the database is dropped and re-imported
#      (schema, procedures, seeds) so every version starts from a clean state.
#
# PHP VERSIONS
#   G2ML_PHP_VERSIONS  versions to test, separated by spaces.
#                      Default: "8.4 8.5 8.6-rc". The Docker image tag is the
#                      version plus "-cli" (8.6-rc gives php:8.6-rc-cli).
#   G2ML_PHP_ADVISORY  versions that are run and REPORTED but do not change
#                      the final result. Default: "8.6-rc" (a release candidate
#                      may fail for reasons outside our code). Versions not in
#                      this list are counted in OVERALL.
#   To promote 8.6 later: in G2ML_PHP_VERSIONS change "8.6-rc" to "8.6" (once
#   the php:8.6-cli image exists) and make the advisory list empty
#   (G2ML_PHP_ADVISORY="" or change the default below).
#   Quick check on one version:
#     G2ML_PHP_VERSIONS=8.4 sh .claude/programme/run-tests.sh unit
#
# OTHER SETTINGS
#   G2ML_DOCKER_EXTRA_ARGS  extra words put on every "docker run" that runs
#                           PHP (it is deliberately not quoted, so it splits
#                           into words). Use it where host names do not
#                           resolve, for example in a cloud container:
#                           G2ML_DOCKER_EXTRA_ARGS="--add-host example.com:93.184.215.14"
#
# One test image per version, g2ml-php:<version>-test, is built once from
# tests/docker/Dockerfile and then reused.
#
# Usage: sh .claude/programme/run-tests.sh [unit|integration|all]   (default all)
# The last line is "=== OVERALL exit=N ===" (N is 0 only if every counted
# version passed), unless the script cannot even find the repository (exit 2).
set -u
# The repository this script lives in, worked out from the script's own
# location, so a clone anywhere on any machine works and no personal path is
# written into the repository. `pwd -P` resolves any links.
SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd -P)
REPO=$(CDPATH='' cd -- "${SCRIPT_DIR}/../.." && pwd -P)
MODE="${1:-all}"
PHP_VERSIONS="${G2ML_PHP_VERSIONS:-8.4 8.5 8.6-rc}"
ADVISORY_VERSIONS="${G2ML_PHP_ADVISORY-8.6-rc}"
EXTRA_ARGS="${G2ML_DOCKER_EXTRA_ARGS:-}"
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

# Says yes (exit 0) when the version is in the advisory list.
is_advisory() {
    case " ${ADVISORY_VERSIONS} " in
        *" $1 "*)
            return 0
            ;;
    esac
    return 1
}

# Records that a step failed for a version. A failure in an advisory version
# is printed but does not change OVERALL.
record_failure() {
    if is_advisory "$1"; then
        echo "NOTE: PHP $1 is advisory, so this failure is reported but not counted"
    else
        OVERALL=1
    fi
}

# Builds the test image for a version if it is not already there. Says yes
# (exit 0) when the image is ready.
ensure_image() {
    if docker image inspect "g2ml-php:$1-test" >/dev/null 2>&1; then
        return 0
    fi
    echo "building image g2ml-php:$1-test (first time only)"
    if docker build -q -t "g2ml-php:$1-test" --build-arg "PHP_TAG=$1-cli" - < tests/docker/Dockerfile >/dev/null; then
        return 0
    fi
    echo "IMAGE BUILD FAILED for PHP $1"
    return 1
}

READY_VERSIONS=""
for V in ${PHP_VERSIONS}; do
    if is_advisory "${V}"; then
        echo "PHP ${V}: advisory (reported, not counted in OVERALL)"
    else
        echo "PHP ${V}: counted in OVERALL"
    fi
    if ensure_image "${V}"; then
        READY_VERSIONS="${READY_VERSIONS} ${V}"
    else
        record_failure "${V}"
    fi
done

for V in ${READY_VERSIONS}; do
    IMAGE="g2ml-php:${V}-test"
    echo "=== lint, every PHP file under web/ and tests/ (PHP ${V}) ==="
    # shellcheck disable=SC2086
    docker run --rm ${EXTRA_ARGS} -v "${REPO}":/app:ro -w /app "${IMAGE}" sh scripts/php-lint.sh > "/tmp/g2ml_lint_${V}.txt" 2>&1
    LINT_EXIT=$?
    grep -E "^PROBLEM" "/tmp/g2ml_lint_${V}.txt" | head -40
    tail -1 "/tmp/g2ml_lint_${V}.txt"
    echo "lint exit=${LINT_EXIT}"
    if [ "${LINT_EXIT}" -ne 0 ]; then
        record_failure "${V}"
    fi
done

if [ "${MODE}" = "unit" ] || [ "${MODE}" = "all" ]; then
    for V in ${READY_VERSIONS}; do
        IMAGE="g2ml-php:${V}-test"
        echo "=== unit suite (PHP ${V}) ==="
        # shellcheck disable=SC2086
        docker run --rm ${EXTRA_ARGS} -v "${REPO}":/app:ro -w /app "${IMAGE}" php tests/run.php > "/tmp/g2ml_unit_${V}.txt" 2>&1
        UNIT_EXIT=$?
        grep -E "^(FAIL|ERROR|DEPRECATED|(PHP )?(Warning|Notice|Fatal error|Deprecated):)" "/tmp/g2ml_unit_${V}.txt" | head -40
        tail -1 "/tmp/g2ml_unit_${V}.txt"
        echo "unit exit=${UNIT_EXIT}"
        if [ "${UNIT_EXIT}" -ne 0 ]; then
            record_failure "${V}"
        fi
    done
fi

if [ "${MODE}" = "integration" ] || [ "${MODE}" = "all" ]; then
    echo "=== database suite setup (MySQL 8.4, utf8mb4_unicode_ci) ==="
    docker rm -f -v "${DB}" >/dev/null 2>&1
    docker network create "${NET}" >/dev/null 2>&1
    # If the run is stopped (Ctrl-C or kill), remove the MySQL container, its
    # storage volume and the network first. Exit code 130 is the usual one
    # for "stopped by Ctrl-C"; it is non-zero, so nobody reads it as a pass.
    trap 'docker rm -f -v "${DB}" >/dev/null 2>&1; docker network rm "${NET}" >/dev/null 2>&1; exit 130' INT TERM
    docker run -d --name "${DB}" --network "${NET}" -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
        mysql:8.4 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
    # Wait for the FINAL server. The image first runs a temporary server (on
    # port 0) to initialise itself, answers pings, then restarts; importing
    # during that window fails with "Can't connect". The final server's
    # "Version: ... port: 3306" log line is the reliable signal.
    # If MySQL never becomes ready, DB_VERSIONS is emptied so the loop below
    # runs nothing, OVERALL becomes 1, and the script still cleans up and
    # prints its OVERALL line.
    DB_VERSIONS="${READY_VERSIONS}"
    TRIES=0
    until docker logs "${DB}" 2>&1 | grep -q "Version:.*port: 3306"; do
        TRIES=$((TRIES + 1))
        if [ "${TRIES}" -gt 120 ]; then
            echo "MySQL did not start"
            DB_VERSIONS=""
            OVERALL=1
            break
        fi
        sleep 2
    done
    if [ -n "${DB_VERSIONS}" ]; then
        TRIES=0
        until docker exec "${DB}" mysql -uroot -e "SELECT 1" >/dev/null 2>&1; do
            TRIES=$((TRIES + 1))
            if [ "${TRIES}" -gt 120 ]; then
                echo "MySQL stopped answering after it said it was ready"
                DB_VERSIONS=""
                OVERALL=1
                break
            fi
            sleep 1
        done
    fi
    for V in ${DB_VERSIONS}; do
        IMAGE="g2ml-php:${V}-test"
        echo "=== database suite (PHP ${V}) ==="
        # Start each version from a clean database. The schema file creates
        # the database itself, so the collation still comes from the server.
        docker exec "${DB}" mysql -uroot -e "DROP DATABASE IF EXISTS mwtools_Go2MyLink" > "/tmp/g2ml_import_${V}.txt" 2>&1
        IMPORT_FAIL=0
        for DIR in schema procedures seeds; do
            for FILE in $(find "web/_sql/${DIR}" -maxdepth 1 -name "*.sql" | sort); do
                if ! docker exec -i "${DB}" mysql -uroot < "${FILE}" > "/tmp/g2ml_import_${V}.txt" 2>&1; then
                    echo "IMPORT FAIL ${FILE}: $(head -3 "/tmp/g2ml_import_${V}.txt")"
                    IMPORT_FAIL=1
                fi
            done
        done
        if [ "${IMPORT_FAIL}" -ne 0 ]; then
            record_failure "${V}"
        fi
        # shellcheck disable=SC2086
        docker run --rm ${EXTRA_ARGS} --network "${NET}" -v "${REPO}":/app:ro -w /app \
            -e G2ML_TEST_DB_HOST="${DB}" -e G2ML_TEST_DB_PORT=3306 -e G2ML_TEST_DB_NAME=mwtools_Go2MyLink \
            -e G2ML_TEST_DB_USER=root -e G2ML_TEST_DB_PASS= \
            "${IMAGE}" php tests/run_integration.php > "/tmp/g2ml_integ_${V}.txt" 2>&1
        INTEG_EXIT=$?
        grep -E "^(FAIL|ERROR|DEPRECATED|(PHP )?(Warning|Notice|Fatal error|Deprecated):)|SKIPPED" "/tmp/g2ml_integ_${V}.txt" | head -40
        tail -1 "/tmp/g2ml_integ_${V}.txt"
        echo "integration exit=${INTEG_EXIT}"
        if [ "${INTEG_EXIT}" -ne 0 ]; then
            record_failure "${V}"
        fi
    done
    docker rm -f -v "${DB}" >/dev/null 2>&1
    docker network rm "${NET}" >/dev/null 2>&1
    trap - INT TERM
fi

echo "=== OVERALL exit=${OVERALL} ==="
exit "${OVERALL}"
