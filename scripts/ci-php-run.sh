#!/bin/sh
# =============================================================================
# scripts/ci-php-run.sh
# =============================================================================
# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.
#
# WHAT THIS DOES
#
# Runs one command either on the PHP that is installed on the machine, or
# inside a Docker image that has the wanted PHP in it. The CI workflow
# (.github/workflows/ci.yml) uses it so that the PHP 8.6 checks can run on the
# official PHP 8.6.0 image while the other checks keep running on the PHP the
# setup-php action installs. The same step then works on every PHP version.
#
# WHY: the pinned setup-php action still treats PHP 8.6 as an unfinished
# "nightly" build, so it may not give us the 8.6.0 release. The image is built
# from tests/docker/Dockerfile (the official php:<version>-cli image plus the
# mbstring and mysqli extensions).
#
# USAGE
#
#   sh scripts/ci-php-run.sh <command> [arguments...]
#
#   Examples:
#     sh scripts/ci-php-run.sh php tests/run.php
#     sh scripts/ci-php-run.sh sh scripts/php-lint.sh
#
# SETTINGS (environment variables)
#
#   G2ML_CI_IMAGE        The Docker image to run the command in. When it is
#                        empty or not set, the command runs directly on the
#                        machine's own PHP.
#   G2ML_CI_DOCKER_ARGS  Extra words for "docker run" (optional; split into
#                        words on purpose). Handy where some host names do
#                        not resolve, for example:
#                        G2ML_CI_DOCKER_ARGS="--add-host example.com:93.184.215.14"
#
# In the image the command runs in /app, which is the current folder. The
# container shares the machine's network (--network host), so it can reach a
# database service that listens on 127.0.0.1. It runs as the same user as
# the caller, so the files it writes in the workspace belong to that user.
# The database settings (G2ML_TEST_DB_*) are passed through when they are set.
#
# Exit status: that of the command (or 2 when no command is given).
# =============================================================================

set -eu

if [ "$#" -eq 0 ]
then
    echo "Usage: sh scripts/ci-php-run.sh <command> [arguments...]" >&2
    exit 2
fi

if [ -n "${G2ML_CI_IMAGE-}" ]
then
    # The extra words are split on purpose, so the shellcheck rule against an
    # unquoted variable does not apply here.
    # shellcheck disable=SC2086
    exec docker run --rm --network host \
        --user "$(id -u):$(id -g)" \
        -e G2ML_TEST_DB_HOST -e G2ML_TEST_DB_PORT -e G2ML_TEST_DB_NAME \
        -e G2ML_TEST_DB_USER -e G2ML_TEST_DB_PASS \
        ${G2ML_CI_DOCKER_ARGS-} \
        -v "${PWD}:/app" -w /app \
        "${G2ML_CI_IMAGE}" "$@"
fi

exec "$@"
