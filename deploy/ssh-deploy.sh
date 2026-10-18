#!/usr/bin/env bash
set -euo pipefail
umask 077

root="$HOME/laundry"
read -r verb commit checksum extra <<< "${SSH_ORIGINAL_COMMAND:-}"
if [[ "$verb" != deploy || ! "$commit" =~ ^[a-f0-9]{40}$ || ! "$checksum" =~ ^[a-f0-9]{64}$ || -n "$extra" ]]; then
    echo 'Only a verified release deployment is allowed.' >&2
    exit 1
fi

mkdir -p "$root/uploads" "$root/releases"
exec 9> "$root/deploy.lock"
flock -w 300 9

archive="$root/uploads/$commit.tar.gz"
cat > "$archive"
printf '%s  %s\n' "$checksum" "$archive" | sha256sum --check --status

if [[ -e "$root/releases/$commit" ]]; then
    if [[ "$(cat "$root/shared/deployed-sha" 2>/dev/null || true)" == "$commit" ]]; then
        rm -f "$archive"
        echo 'This commit is already active.'
        exit 0
    fi
    mv "$root/releases/$commit" "$root/uploads/failed-$commit-$(date +%s)"
fi

if tar -tzf "$archive" | grep -Eq '(^/|(^|/)\.\.(/|$))'; then
    echo 'Invalid archive path.' >&2
    exit 1
fi

mkdir -m 755 "$root/releases/$commit"
tar --extract --gzip --file "$archive" --directory "$root/releases/$commit" --no-same-owner --no-same-permissions
test -f "$root/releases/$commit/deploy/activate.sh"
test -f "$root/releases/$commit/public/build/manifest.json"
bash "$root/releases/$commit/deploy/activate.sh" "$root" "$commit"
rm -f "$archive"
