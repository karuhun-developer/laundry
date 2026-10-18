#!/usr/bin/env bash
set -euo pipefail

sql_file="${1:-/mnt/c/Users/bayur/Downloads/laundry-karuhundeveloper-com.sql}"
target_host="${DEPLOY_HOST:-ssh67.karuhundeveloper.com}"
target_user="${DEPLOY_USER:-hidupjokowi}"

if [[ ! -r "$sql_file" ]]; then
    echo "SQL dump file not found: $sql_file" >&2
    exit 1
fi

echo "=== LaundryHub Database Import ==="
echo "Source: $sql_file ($(du -h "$sql_file" | cut -f 1))"
echo "Target: $target_user@$target_host"

echo "Reading database credentials from server..."
db_pass="$(ssh -o BatchMode=yes "$target_user@$target_host" "grep '^DB_PASSWORD=' /home/hidupjokowi/laundry/shared/.env | cut -d '=' -f 2-")"
if [[ -z "$db_pass" ]]; then
    echo "Failed to retrieve DB_PASSWORD from /home/hidupjokowi/laundry/shared/.env" >&2
    exit 1
fi

echo "Importing SQL dump into laundry-db-1 container..."
ssh -o BatchMode=yes "$target_user@$target_host" "docker exec -i laundry-db-1 mysql -u laundry -p'$db_pass' laundry" < "$sql_file"

echo "Verifying imported database tables and users count..."
ssh -o BatchMode=yes "$target_user@$target_host" "docker exec -i laundry-db-1 mysql -u laundry -p'$db_pass' laundry -e 'SELECT count(*) AS total_users FROM users; SELECT count(*) AS total_orders FROM orders; SELECT count(*) AS total_branches FROM branches;'"

echo "=== Database import complete! ==="
