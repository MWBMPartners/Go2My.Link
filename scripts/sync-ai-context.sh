#!/bin/sh
# =============================================================================
# scripts/sync-ai-context.sh
# =============================================================================
# Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
# All rights reserved.
#
# This source code is proprietary and confidential.
# Unauthorised copying, modification, or distribution is strictly prohibited.
#
# WHAT THIS DOES
#
# Keeps the Codex copy of the project notes (.OpenAI/) identical to the Claude
# notes (the root CLAUDE.md and .claude/). Two AI coding tools work on this
# repository and they are meant to follow the same rules and know the same
# facts, but each one only reads its own folder:
#
#   - Claude Code reads the root CLAUDE.md, which points into .claude/.
#   - Codex reads the root AGENTS.md, which points into .OpenAI/.
#
# The Claude side is the one people edit. This script copies it across:
#
#   CLAUDE.md            ->  .OpenAI/CONTEXT.md   (with a short "do not edit" header)
#   .claude/memory/*.md  ->  .OpenAI/memory/*.md  (exact copies; stale copies removed)
#   .claude/HISTORY.md   ->  .OpenAI/HISTORY.md   (exact copy)
#
# HISTORY.md is copied as well because the memory files link to it with the
# relative path ../HISTORY.md — without the copy, that link would point at
# nothing when read from .OpenAI/memory/.
#
# HOW TO USE IT
#
#   sh scripts/sync-ai-context.sh           refresh .OpenAI/ from the Claude side
#   sh scripts/sync-ai-context.sh --check   change nothing; exit 1 if .OpenAI/ is
#                                           out of step (used by the GitHub check
#                                           in .github/workflows/ai-context.yml)
#
# WHY A SCRIPT AND A CHECK, NOT "REMEMBER TO COPY IT"
#
# A sister project (MeedyaDL) kept its Codex copy up to date by hand, and the
# two drifted badly — at one point the Codex copy told people to run a command
# the project had banned. A copy that nothing checks is a copy that goes stale
# without anyone noticing. So the copying is done by this script, and the
# GitHub check turns red if somebody forgets to run it.
#
# WHAT IT CANNOT DO
#
#   - It copies one way only (Claude side -> Codex side). An edit made directly
#     in .OpenAI/ is overwritten by the next run. Edit the Claude side.
#   - It does not touch the root AGENTS.md. That file is a short, hand-written
#     summary for Codex and has to be updated by hand when a rule it summarises
#     changes (the standing rules say so).
#   - It only handles .md files in .claude/memory/. Other file types there are
#     ignored.
#   - On a Mac, renaming a memory file where only the capital letters change
#     (Patterns.md -> patterns.md) is not noticed: the Mac treats both names as
#     the same file, so the old spelling stays in .OpenAI/memory/ and --check
#     still passes there (GitHub's Linux check would fail). Rename the copy by
#     hand with `git mv` in that case.
#   - The GitHub check is a warning, not a gate. `alpha` has no required
#     checks, and this check cannot simply be made required: its path filters
#     mean it does not run on unrelated pull requests, which would then wait
#     for ever for a result.
# =============================================================================

set -eu

# -----------------------------------------------------------------------------
# Work out where the repository is, wherever the script is run from.
# -----------------------------------------------------------------------------
# `pwd -P` resolves symbolic links, so the same folder always gives the same
# answer. CDPATH is blanked so `cd` cannot jump somewhere unexpected.
SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd -P)
REPO_ROOT=$(CDPATH='' cd -- "${SCRIPT_DIR}/.." && pwd -P)

CLAUDE_ENTRY="${REPO_ROOT}/CLAUDE.md"
CLAUDE_MEMORY="${REPO_ROOT}/.claude/memory"
CLAUDE_HISTORY="${REPO_ROOT}/.claude/HISTORY.md"
OPENAI_DIR="${REPO_ROOT}/.OpenAI"

MODE="write"
if [ "$#" -gt 0 ]; then
    if [ "$1" = "--check" ]; then
        MODE="check"
    else
        printf 'Usage: sh scripts/sync-ai-context.sh [--check]\n' >&2
        exit 2
    fi
fi

# -----------------------------------------------------------------------------
# Refuse to run if the Claude side is missing, rather than "syncing" nothing
# and reporting success.
# -----------------------------------------------------------------------------
if [ ! -f "${CLAUDE_ENTRY}" ]; then
    printf 'error: %s does not exist — is this the Go2My.Link repository?\n' "${CLAUDE_ENTRY}" >&2
    exit 1
fi
if [ ! -d "${CLAUDE_MEMORY}" ]; then
    printf 'error: %s does not exist.\n' "${CLAUDE_MEMORY}" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# Build what .OpenAI/ SHOULD contain in a temporary folder first.
# -----------------------------------------------------------------------------
# Building it separately means check mode and write mode share exactly the
# same logic: check mode compares the temporary folder with .OpenAI/, write
# mode copies it over. There is no second copy of the rules to drift.
STAGING=$(mktemp -d)
trap 'rm -rf "${STAGING}"' EXIT
mkdir -p "${STAGING}/memory"

# CONTEXT.md = a short header + the root CLAUDE.md, unchanged. The header is
# needed because CLAUDE.md's links are written relative to the repository
# root, and would look broken to a reader who does not know that.
{
    printf '<!-- GENERATED FILE — do not edit. Copied from the root CLAUDE.md by scripts/sync-ai-context.sh. -->\n'
    printf '<!-- Links below are relative to the REPOSITORY ROOT, not to this .OpenAI/ folder. Edit CLAUDE.md instead. -->\n'
    printf '\n'
    cat "${CLAUDE_ENTRY}"
} > "${STAGING}/CONTEXT.md"

# Memory files: exact copies.
for SOURCE_FILE in "${CLAUDE_MEMORY}"/*.md; do
    # When the folder has no .md files, the pattern stays as literal text;
    # skip that case instead of copying a file called "*.md".
    if [ ! -e "${SOURCE_FILE}" ]; then
        continue
    fi
    cp "${SOURCE_FILE}" "${STAGING}/memory/$(basename "${SOURCE_FILE}")"
done

# Refuse to go on if there were no memory files at all. An empty folder
# almost certainly means something went wrong (a bad checkout, a wrong
# folder), and carrying on would delete every Codex copy and report success —
# exactly the "sync nothing and say OK" failure the checks above exist to
# prevent.
MEMORY_COUNT=0
for STAGED_FILE in "${STAGING}/memory"/*.md; do
    if [ -e "${STAGED_FILE}" ]; then
        MEMORY_COUNT=$((MEMORY_COUNT + 1))
    fi
done
if [ "${MEMORY_COUNT}" -eq 0 ]; then
    printf 'error: no .md files found in %s — refusing to sync.\n' "${CLAUDE_MEMORY}" >&2
    exit 1
fi

if [ -f "${CLAUDE_HISTORY}" ]; then
    cp "${CLAUDE_HISTORY}" "${STAGING}/HISTORY.md"
fi

# -----------------------------------------------------------------------------
# Compare only the generated parts of .OpenAI/.
# -----------------------------------------------------------------------------
# .OpenAI/ also holds files that are NOT generated (README.md, .gitkeep and an
# older logo-design chat log). Those are left alone and never compared.
compare_generated() {
    STATUS=0
    for GENERATED in CONTEXT.md HISTORY.md; do
        if [ -f "${STAGING}/${GENERATED}" ]; then
            if ! cmp -s "${STAGING}/${GENERATED}" "${OPENAI_DIR}/${GENERATED}"; then
                printf 'out of step: .OpenAI/%s\n' "${GENERATED}"
                STATUS=1
            fi
        else
            # The original is gone (for example .claude/HISTORY.md was
            # deleted), so a copy left in .OpenAI/ is stale.
            if [ -f "${OPENAI_DIR}/${GENERATED}" ]; then
                printf 'leftover (original was deleted): .OpenAI/%s\n' "${GENERATED}"
                STATUS=1
            fi
        fi
    done
    # Every expected memory file must exist and match...
    for EXPECTED in "${STAGING}/memory"/*.md; do
        if [ ! -e "${EXPECTED}" ]; then
            continue
        fi
        NAME=$(basename "${EXPECTED}")
        if ! cmp -s "${EXPECTED}" "${OPENAI_DIR}/memory/${NAME}"; then
            printf 'out of step: .OpenAI/memory/%s\n' "${NAME}"
            STATUS=1
        fi
    done
    # ...and there must be no leftover memory file whose original was deleted.
    if [ -d "${OPENAI_DIR}/memory" ]; then
        for PRESENT in "${OPENAI_DIR}/memory"/*.md; do
            if [ ! -e "${PRESENT}" ]; then
                continue
            fi
            NAME=$(basename "${PRESENT}")
            if [ ! -f "${STAGING}/memory/${NAME}" ]; then
                printf 'leftover (original was deleted): .OpenAI/memory/%s\n' "${NAME}"
                STATUS=1
            fi
        done
    fi
    return "${STATUS}"
}

if [ "${MODE}" = "check" ]; then
    if compare_generated; then
        printf 'OK: .OpenAI/ matches CLAUDE.md and .claude/.\n'
        exit 0
    fi
    printf '\nThe Codex copy in .OpenAI/ is out of step with the Claude notes.\n' >&2
    printf 'Fix: run  sh scripts/sync-ai-context.sh  and commit the result.\n' >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# Write mode: copy the files built in the temporary folder into .OpenAI/.
# -----------------------------------------------------------------------------
mkdir -p "${OPENAI_DIR}/memory"

# Remove memory copies whose original has been deleted, so the mirror never
# keeps a fact the Claude side has dropped.
for PRESENT in "${OPENAI_DIR}/memory"/*.md; do
    if [ ! -e "${PRESENT}" ]; then
        continue
    fi
    NAME=$(basename "${PRESENT}")
    if [ ! -f "${STAGING}/memory/${NAME}" ]; then
        rm -f "${PRESENT}"
        printf 'removed leftover .OpenAI/memory/%s\n' "${NAME}"
    fi
done

cp "${STAGING}/CONTEXT.md" "${OPENAI_DIR}/CONTEXT.md"
if [ -f "${STAGING}/HISTORY.md" ]; then
    cp "${STAGING}/HISTORY.md" "${OPENAI_DIR}/HISTORY.md"
else
    # Same rule as the memory files: no original, no copy.
    if [ -f "${OPENAI_DIR}/HISTORY.md" ]; then
        rm -f "${OPENAI_DIR}/HISTORY.md"
        printf 'removed leftover .OpenAI/HISTORY.md\n'
    fi
fi
COPIED=0
for EXPECTED in "${STAGING}/memory"/*.md; do
    if [ ! -e "${EXPECTED}" ]; then
        continue
    fi
    cp "${EXPECTED}" "${OPENAI_DIR}/memory/$(basename "${EXPECTED}")"
    COPIED=$((COPIED + 1))
done

printf 'Refreshed .OpenAI/ from the Claude notes: CONTEXT.md, %d memory file(s), and HISTORY.md if it exists.\n' "${COPIED}"
