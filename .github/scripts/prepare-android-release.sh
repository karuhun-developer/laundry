#!/usr/bin/env bash

set -euo pipefail

if [[ $# -ne 3 ]]; then
    echo 'Usage: prepare-android-release.sh <tag> <changelog> <release-notes-output>' >&2
    exit 64
fi

tag_name="$1"
changelog_path="$2"
release_notes_path="$3"

if [[ ! "$tag_name" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
    echo "Tag must match vMAJOR.MINOR.PATCH; received: $tag_name" >&2
    exit 65
fi

if [[ ! -r "$changelog_path" ]]; then
    echo "Changelog is not readable: $changelog_path" >&2
    exit 66
fi

major=$((10#${BASH_REMATCH[1]}))
minor=$((10#${BASH_REMATCH[2]}))
patch=$((10#${BASH_REMATCH[3]}))
version_name="${major}.${minor}.${patch}"

if (( minor > 999 || patch > 999 )); then
    echo 'Minor and patch versions must be between 0 and 999.' >&2
    exit 65
fi

version_code=$((major * 1000000 + minor * 1000 + patch))

if (( version_code < 1 || version_code > 2100000000 )); then
    echo "Android version code is outside the supported range: $version_code" >&2
    exit 65
fi

release_heading_prefix="## [$version_name] - "
release_heading=$(awk -v prefix="$release_heading_prefix" 'index($0, prefix) == 1 { print; exit }' "$changelog_path")

if [[ -z "$release_heading" ]]; then
    echo "CHANGELOG.md must contain: ## [$version_name] - YYYY-MM-DD" >&2
    exit 67
fi

release_date="${release_heading#"$release_heading_prefix"}"

if [[ ! "$release_date" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    echo "Release heading must use an ISO date: $release_heading" >&2
    exit 67
fi

mkdir -p "$(dirname "$release_notes_path")"

awk -v heading="$release_heading" '
    $0 == heading { capture = 1; next }
    capture && /^## / { exit }
    capture {
        if (!started && $0 == "") {
            next
        }

        started = 1
        lines[++count] = $0
    }
    END {
        while (count > 0 && lines[count] == "") {
            count--
        }

        for (line = 1; line <= count; line++) {
            print lines[line]
        }
    }
' "$changelog_path" > "$release_notes_path"

if [[ ! -s "$release_notes_path" ]] || ! grep -q '[^[:space:]]' "$release_notes_path"; then
    echo "Release $version_name must have non-empty notes in CHANGELOG.md." >&2
    exit 67
fi

if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
    {
        echo "version_name=$version_name"
        echo "version_code=$version_code"
        echo "release_date=$release_date"
        echo "release_notes_path=$release_notes_path"
    } >> "$GITHUB_OUTPUT"
fi

echo "Prepared Android release $version_name ($version_code) from $release_date."
