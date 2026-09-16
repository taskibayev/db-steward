#!/bin/sh
set -eu

backup_directory=${1:-./backups}
mkdir -p "$backup_directory"
backup_file="$backup_directory/db-steward-system-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"

docker compose -f compose.production.yaml exec -T system-mysql sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF -u "$MYSQL_USER" "$MYSQL_DATABASE"' \
  | gzip -9 > "$backup_file"

test -s "$backup_file"
chmod 600 "$backup_file"
echo "System database backup created: $backup_file"
