#!/usr/bin/env bash
set -euo pipefail
umask 077

root="$HOME/laundry"
[[ "$(id -u)" == 1000 ]]
if [[ -e "$root/shared/.env" ]]; then
    echo 'Production environment already exists; preserving its credentials.'
    exit 0
fi

mkdir -p "$root/releases" "$root/uploads" "$root/bin" "$root/backups" \
    "$root/shared/storage/app/private" "$root/shared/storage/app/public" \
    "$root/shared/storage/framework/cache/data" "$root/shared/storage/framework/sessions" \
    "$root/shared/storage/framework/views" "$root/shared/storage/logs"
chmod 755 "$root" "$root/releases" "$root/shared" "$root/shared/storage" "$root/shared/storage/app" "$root/shared/storage/app/public"
chmod -R u+rwX,go+rX "$root/shared/storage/framework"
chmod 700 "$root/shared/storage/app/private" "$root/backups"

db_password="$(openssl rand -hex 32)"
root_password="$(openssl rand -hex 32)"
app_key="base64:bEXI0mXufrccg6eUER/E9l73u0xfLpF0FCn+8F42uu4="

printf '%s\n' \
    'APP_NAME="LaundryHub"' 'APP_ENV=production' "APP_KEY=$app_key" 'APP_DEBUG=false' \
    'APP_URL=https://laundry.karuhundeveloper.com' 'CAPACITOR_SERVER_URL=https://laundry.karuhundeveloper.com/app' \
    'APP_PORT=8099' 'APP_TIMEZONE=Asia/Jakarta' 'APP_LOCALE=id' 'APP_FALLBACK_LOCALE=en' 'APP_FAKER_LOCALE=id_ID' \
    'DEMO_ACCOUNTS_ENABLED=false' \
    'GITHUB_RELEASE_REPOSITORY=karuhun-developer/laundry' \
    'GITHUB_RELEASE_TOKEN=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' \
    'GITHUB_RELEASE_CACHE_SECONDS=900' \
    'APP_MAINTENANCE_DRIVER=file' 'BCRYPT_ROUNDS=12' \
    'LOG_CHANNEL=stack' 'LOG_STACK=daily' 'LOG_LEVEL=warning' \
    'DB_CONNECTION=mysql' 'DB_HOST=db' 'DB_PORT=3306' 'DB_DATABASE=laundry' 'DB_USERNAME=laundry' \
    "DB_PASSWORD=$db_password" "MYSQL_ROOT_PASSWORD=$root_password" \
    'SESSION_DRIVER=database' 'SESSION_LIFETIME=43200' 'SESSION_ENCRYPT=false' 'SESSION_PATH=/' \
    'SESSION_DOMAIN=laundry.karuhundeveloper.com' \
    'BROADCAST_CONNECTION=log' 'FILESYSTEM_DISK=local' 'QUEUE_CONNECTION=database' \
    'CACHE_STORE=database' 'MAIL_MAILER=log' 'MAIL_FROM_ADDRESS="hello@example.com"' 'MAIL_FROM_NAME="LaundryHub"' \
    'VITE_APP_NAME="LaundryHub"' \
    'FIREBASE_PROJECT_ID=laundry-695f6' \
    'FIREBASE_CREDENTIALS=/home/hidupjokowi/laundry/shared/storage/app/private/firebase-service-account.json' > "$root/shared/.env"

chmod 600 "$root/shared/.env"
echo 'Production environment created with credentials.'
