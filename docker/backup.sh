#!/bin/sh

set -eu
umask 077

cd "$(dirname "$0")/.."

docker_env_file=${APP_ENV_FILE:-.env.docker}
if [ ! -f "$docker_env_file" ]; then
    echo "File environment Docker tidak ditemukan: $docker_env_file" >&2
    exit 1
fi

backup_dir=${BACKUP_DIR:-backups}
mkdir -p "$backup_dir"
backup_time=$(date -u +%Y%m%dT%H%M%SZ)
sql_backup=$(mktemp "$backup_dir/affiliatehub-$backup_time.XXXXXX.sql")
storage_backup="${sql_backup%.sql}-storage.tar.gz"

cleanup_partial_backup() {
    if [ "${backup_finished:-no}" != yes ]; then
        rm -f -- "$sql_backup" "$storage_backup"
    fi
}
trap cleanup_partial_backup EXIT

docker compose --env-file "$docker_env_file" -f compose.yaml exec -T app \
    sh -c 'MYSQL_PWD="$DB_PASSWORD" exec mysqldump --single-transaction --quick --no-tablespaces -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE"' \
    > "$sql_backup"

tar -C storage -czf "$storage_backup" .
backup_finished=yes

echo "Backup database: $sql_backup"
echo "Backup berkas: $storage_backup"
