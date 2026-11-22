#!/bin/sh
# =============================================================================
# scripts/php-lint.sh
# =============================================================================
# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.
#
# WHAT THIS DOES
#
# Checks PHP files for mistakes without running them, and is stricter than a
# plain "php -l". A plain "php -l" prints a warning when a newer PHP version
# has deprecated something, but still says "all fine" (exit 0), so nobody
# notices. This script treats any of these in PHP's output as a failure:
#   Deprecated:  Warning:  Notice:  Fatal error:  Parse error:
#
# USAGE
#
#   sh scripts/php-lint.sh                 # every .php file under web/ and tests/
#   sh scripts/php-lint.sh file1.php ...   # only the files named
#
# With no file names it always checks the repository, whatever folder it is
# started from. File names you pass are used as given (relative to the folder
# you started in). It prints each problem with its file name,
# then one summary line, and exits 1 if there was any problem, otherwise 0.
#
# START-UP MESSAGES. Some PHP set-ups print a message every time PHP starts
# (for example a module that is loaded twice), whatever file is being
# checked. Left alone, that would flag every file. So the script first runs
# PHP once on nothing, with the same flags, and remembers what it printed.
# Any line of a file's output that is exactly one of those lines is ignored.
# A real problem line always names the file, so it can never be identical.
# If PHP cannot even start, the script says so and exits 1.
# =============================================================================

if ! WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/php-lint.XXXXXX")
then
    echo "php-lint: could not make a temporary folder"
    exit 1
fi
trap 'rm -rf "${WORK_DIR}"' EXIT
# Some shells skip the EXIT trap when stopped by Ctrl-C or a "kill", so clean
# up on those too. Running the clean-up twice does no harm.
trap 'rm -rf "${WORK_DIR}"; exit 1' INT TERM

FILE_LIST="${WORK_DIR}/files.txt"
BASELINE_FILE="${WORK_DIR}/baseline.txt"

php -d error_reporting=-1 -d display_errors=1 -r '' > "${BASELINE_FILE}" 2>&1
BASELINE_STATUS=$?

if [ "${BASELINE_STATUS}" -ne 0 ] || grep -Eq '^(PHP )?(Parse error|Fatal error):' "${BASELINE_FILE}"
then
    echo "php-lint: PHP could not start cleanly:"
    sed 's/^/    /' "${BASELINE_FILE}"
    exit 1
fi

IGNORED_COUNT=$(grep -c . "${BASELINE_FILE}")

if [ "$#" -gt 0 ]
then
    for ARGUMENT in "$@"
    do
        printf '%s\n' "${ARGUMENT}"
    done > "${FILE_LIST}"
else
    SCRIPT_DIR=$(dirname "$0")
    if ! cd "${SCRIPT_DIR}/.."
    then
        echo "php-lint: could not find the repository folder from ${SCRIPT_DIR}"
        exit 1
    fi
    find web tests -type f -name '*.php' | sort > "${FILE_LIST}"
fi

FILE_COUNT=0
PROBLEM_COUNT=0

# Reading from a file (not a pipe) keeps the loop in this shell, so the
# counters keep their values after it ends.
while IFS= read -r PHP_FILE
do
    FILE_COUNT=$((FILE_COUNT + 1))
    php -d error_reporting=-1 -d display_errors=1 -l "${PHP_FILE}" > "${WORK_DIR}/output.txt" 2>&1
    STATUS=$?
    # Drop the start-up lines. Only filter when there are some: with an empty
    # list, some versions of grep (BusyBox) print nothing at all. grep exits 1
    # when nothing is left, which is fine.
    if [ -s "${BASELINE_FILE}" ]
    then
        OUTPUT=$(grep -Fxv -f "${BASELINE_FILE}" "${WORK_DIR}/output.txt")
    else
        OUTPUT=$(cat "${WORK_DIR}/output.txt")
    fi
    FOUND=0

    if [ "${STATUS}" -ne 0 ]
    then
        FOUND=1
    elif printf '%s\n' "${OUTPUT}" | grep -Eq '^(PHP )?(Deprecated|Warning|Notice|Fatal error|Parse error):'
    then
        FOUND=1
    fi

    if [ "${FOUND}" -eq 1 ]
    then
        PROBLEM_COUNT=$((PROBLEM_COUNT + 1))
        echo "PROBLEM  ${PHP_FILE}"
        printf '%s\n' "${OUTPUT}" | sed 's/^/    /'
    fi
done < "${FILE_LIST}"

if [ "${FILE_COUNT}" -eq 0 ]
then
    echo "php-lint: no PHP files were found to check"
    exit 1
fi

# Silenced, so a start-up message cannot end up inside the version text.
PHP_VERSION_TEXT=$(php -d display_errors=0 -d log_errors=0 -r 'echo PHP_VERSION;' 2>/dev/null)

if [ "${IGNORED_COUNT}" -eq 0 ]
then
    echo "php-lint: ${FILE_COUNT} files, ${PROBLEM_COUNT} problems (PHP ${PHP_VERSION_TEXT})"
elif [ "${IGNORED_COUNT}" -eq 1 ]
then
    echo "php-lint: ${FILE_COUNT} files, ${PROBLEM_COUNT} problems (PHP ${PHP_VERSION_TEXT}; ignored 1 start-up line)"
else
    echo "php-lint: ${FILE_COUNT} files, ${PROBLEM_COUNT} problems (PHP ${PHP_VERSION_TEXT}; ignored ${IGNORED_COUNT} start-up lines)"
fi

if [ "${PROBLEM_COUNT}" -gt 0 ]
then
    exit 1
fi

exit 0
