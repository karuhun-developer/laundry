#!/usr/bin/env bash
set -euo pipefail
umask 022

root="${1:?Deployment directory is required}"
commit="${2:?Commit is required}"
[[ "$root" == "$HOME/laundry" && "$commit" =~ ^[a-f0-9]{40}$ ]]
[[ "$(id -u)" == 1000 ]]

release="$root/releases/$commit"
test -r "$root/shared/.env"
test -f "$release/public/build/manifest.json"
test ! -e "$release/.env"
ln -s ../../shared/.env "$release/.env"
test -d "$release/storage"
find "$release/storage" -depth -delete
ln -s ../../shared/storage "$release/storage"
mkdir -p "$release/bootstrap/cache"
chmod -R u+rwX,go+rX "$release"
ln -s ../storage/app/public "$release/public/storage"

export DEPLOY_ROOT="$root"
runtime_hash="$(cat "$release/deploy/Dockerfile" "$release/deploy/php.ini" | sha256sum | cut -c1-16)"
export LAUNDRY_RUNTIME_IMAGE="laundry-php:$runtime_hash"
compose=(docker compose --project-directory "$root" --env-file "$root/shared/.env" -f "$release/deploy/compose.yml")

if ! docker image inspect "$LAUNDRY_RUNTIME_IMAGE" > /dev/null 2>&1; then
    docker build -t "$LAUNDRY_RUNTIME_IMAGE" -f "$release/deploy/Dockerfile" "$release"
fi

"${compose[@]}" config --quiet
"${compose[@]}" up -d --wait --wait-timeout 150 db
previous="$(readlink "$root/current" || true)"
activated=false
maintained=false
run=(docker run --rm --user 1000:1000 --network laundry_laundry -v "$root:$root" -w "$release" -e COMPOSER_HOME=/tmp/composer "$LAUNDRY_RUNTIME_IMAGE")

rollback() {
    local status=$?
    trap - EXIT
    if (( status != 0 )); then
        echo "Deployment $commit failed." >&2
        if [[ -n "$previous" ]]; then
            if [[ "$activated" == true ]]; then
                ln -s "$previous" "$root/current.rollback"
                mv -Tf "$root/current.rollback" "$root/current"
            fi
            bash "$root/current/deploy/compose.sh" up -d --force-recreate app worker scheduler web || true
        fi
        if [[ "$maintained" == true ]]; then
            "${run[@]}" php artisan up --no-interaction || true
        fi
    fi
    exit "$status"
}
trap rollback EXIT

"${run[@]}" sh -eu -c '
    COMPOSER_VENDOR_DIR=/tmp/vendor composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
    cp -a /tmp/vendor ./vendor
'
"${run[@]}" composer dump-autoload --no-dev --optimize --no-interaction
"${run[@]}" composer check-platform-reqs --no-dev

if [[ -n "$previous" ]]; then
    mkdir -p "$root/backups"
    chmod 700 "$root/backups"
    "${compose[@]}" exec -T db sh -s <<'MYSQL_BACKUP' | gzip > "$root/backups/before-$commit.sql.gz"
MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --user="$MYSQL_USER" --single-transaction --no-tablespaces "$MYSQL_DATABASE"
MYSQL_BACKUP
    chmod 600 "$root/backups/before-$commit.sql.gz"
fi

"${run[@]}" php artisan down --retry=15 --no-interaction
maintained=true
if [[ -n "$previous" ]]; then
    bash "$root/current/deploy/compose.sh" stop worker scheduler
fi

"${run[@]}" php artisan migrate --force --no-interaction
"${run[@]}" php artisan optimize --no-interaction
"${run[@]}" php artisan queue:restart --no-interaction

ln -s "releases/$commit" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"
activated=true

"${compose[@]}" up -d --force-recreate app worker scheduler web
"${run[@]}" php artisan up --no-interaction
maintained=false

curl --fail --silent --show-error --retry 12 --retry-delay 2 --retry-all-errors \
    -H 'Host: laundry.karuhundeveloper.com' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8099/up > /dev/null
curl --fail --silent --show-error -H 'Host: laundry.karuhundeveloper.com' -H 'X-Forwarded-Proto: https' \
    http://127.0.0.1:8099/ > "$root/uploads/landing-check.html"
grep -q 'LaundryHub' "$root/uploads/landing-check.html"
rm -f "$root/uploads/landing-check.html"

printf '%s\n' "$commit" > "$root/shared/deployed-sha"
printf 'Activated release %s\n' "$commit"
