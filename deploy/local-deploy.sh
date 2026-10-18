#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
cd "$repo_root"

target_host="${DEPLOY_HOST:-ssh67.karuhundeveloper.com}"
target_user="${DEPLOY_USER:-hidupjokowi}"
commit="$(git rev-parse HEAD)"

echo "=== LaundryHub Local Deployment ==="
echo "Target: $target_user@$target_host"
echo "Commit: $commit"

echo "Step 1: Building frontend assets with Bun..."
bun run build
test -f public/build/manifest.json

echo "Step 2: Packaging release archive..."
release_dir="$(mktemp -d)"
trap 'rm -rf "$release_dir"' EXIT
git archive "$commit" | tar -x -C "$release_dir"
cp -a public/build "$release_dir/public/build"

archive_tar="$(mktemp).tar.gz"
tar -czf "$archive_tar" -C "$release_dir" .
checksum="$(sha256sum "$archive_tar" | cut -d ' ' -f 1)"
echo "Archive checksum: $checksum"

echo "Step 3: Streaming release to server and activating..."
ssh -o BatchMode=yes "$target_user@$target_host" "deploy $commit $checksum" < "$archive_tar"
rm -f "$archive_tar"

echo "Step 4: Verifying health check on server port 8099..."
status_code="$(ssh -o BatchMode=yes "$target_user@$target_host" "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8099/up" || true)"
echo "HTTP Status on http://127.0.0.1:8099/up: $status_code"

echo "=== Deployment completed successfully! ==="
