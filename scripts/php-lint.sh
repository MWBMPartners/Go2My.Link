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
# =============================================================================

if ! WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/php-lint.XXXXXX")
then
    echo "php-lint: could not make a temporary folder"
    exit 1
fi
trap 'rm -rf "${WORK_DIR}"' EXIT

FILE_LIST="${WORK_DIR}/files.txt"

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
    OUTPUT=$(php -d error_reporting=-1 -d display_errors=1 -l "${PHP_FILE}" 2>&1)
    STATUS=$?
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

PHP_VERSION_TEXT=$(php -r 'echo PHP_VERSION;')
echo "php-lint: ${FILE_COUNT} files, ${PROBLEM_COUNT} problems (PHP ${PHP_VERSION_TEXT})"

if [ "${PROBLEM_COUNT}" -gt 0 ]
then
    exit 1
fi

exit 0
