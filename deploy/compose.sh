#!/usr/bin/env bash
set -euo pipefail
DEPLOY_ROOT="${DEPLOY_ROOT:-$HOME/laundry}"
export DEPLOY_ROOT
runtime_hash="$(cat "$DEPLOY_ROOT/current/deploy/Dockerfile" "$DEPLOY_ROOT/current/deploy/php.ini" | sha256sum | cut -c1-16)"
export LAUNDRY_RUNTIME_IMAGE="laundry-php:$runtime_hash"
exec docker compose --project-directory "$DEPLOY_ROOT" --env-file "$DEPLOY_ROOT/shared/.env" -f "$DEPLOY_ROOT/current/deploy/compose.yml" "$@"
