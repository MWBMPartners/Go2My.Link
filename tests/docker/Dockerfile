# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.

# =============================================================================
# Go2My.Link — PHP test image
# =============================================================================
#
# A command-line PHP image for running the lint, unit and database tests on
# any PHP version:
#
#   docker build -q -t g2ml-php:8.5-test --build-arg PHP_TAG=8.5-cli - < tests/docker/Dockerfile
#
# Why this is needed: the stock PHP images do not include the "mysqli"
# extension on any version, and the 8.6 release-candidate image does not
# include "mbstring" either. The site needs both, so this adds whichever is
# missing and stops the build if either is still not there.
# =============================================================================

ARG PHP_TAG=8.4-cli
FROM php:${PHP_TAG}

# mbstring is only added when the image lacks it. Building it without its
# regular-expression part needs no extra system libraries.
RUN if ! php -m | grep -qi '^mbstring$'; then \
        docker-php-ext-configure mbstring --disable-mbregex \
        && docker-php-ext-install -j"$(nproc)" mbstring; \
    fi \
    && docker-php-ext-install -j"$(nproc)" mysqli \
    && php -m | grep -qi '^mbstring$' \
    && php -m | grep -qi '^mysqli$'
