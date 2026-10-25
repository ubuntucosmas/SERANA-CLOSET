#!/usr/bin/env bash
# Nightly backup: database + uploaded images, checked, copied off this server,
# old copies pruned. Run from cron (deploy/crontab). Restore: deploy/RESTORE.md.
#
#   BACKUP_DIR        local folder (default ~/serana-backups)
#   BACKUP_REMOTE     rclone destination, e.g. r2:serana-backups (rclone config done once)
#   BACKUP_KEEP_DAYS  days to keep, here and off-server (default 30)
#
# Without BACKUP_REMOTE the backup still runs but is recorded as NOT off-server,
# and the Studio keeps warning: a backup on the same disk dies with the server.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/serana-backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"
STATUS="$APP_DIR/storage/app/backup-status.json"
cd "$APP_DIR"
mkdir -p "$BACKUP_DIR"

env_val() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
STAMP="$(date -u +%Y%m%dT%H%MZ)"
DB_FILE="$BACKUP_DIR/serana-db-${STAMP}.sql.gz"
MEDIA_FILE="$BACKUP_DIR/serana-uploads-${STAMP}.tar.gz"

fail() {
    echo "BACKUP FAILED: $1" >&2
    rm -f "$DB_FILE" "$MEDIA_FILE"   # never leave a half-written file that looks like a backup
    printf '{"ok":false,"at":"%s","error":"%s"}\n' "$(date -u +%FT%TZ)" "$1" > "$STATUS.failed"
    exit 1
}

# The app's credentials, in a private file read instead of ~/.my.cnf (which would
# otherwise win over a password given on the command line or in MYSQL_PWD).
CNF="$(mktemp)"; chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' "$(env_val DB_HOST)" "$(env_val DB_PORT)" "$(env_val DB_USERNAME)" "$(env_val DB_PASSWORD)" > "$CNF"

# 1. Database: one consistent snapshot, without locking the shop.
set -o pipefail
mysqldump --defaults-file="$CNF" --single-transaction --quick --routines --triggers "$(env_val DB_DATABASE)" \
    | gzip > "$DB_FILE" || fail "mysqldump"
gzip -t "$DB_FILE" || fail "database archive is corrupt"
zcat "$DB_FILE" | tail -1 | grep -q "Dump completed" || fail "database dump is incomplete"

# 2. Uploaded images (products, gallery, bespoke references, progress photos).
tar -czf "$MEDIA_FILE" -C storage/app public || fail "uploads archive"
tar -tzf "$MEDIA_FILE" > /dev/null || fail "uploads archive is corrupt"

# 3. Off this server.
OFFSITE=false
if [ -n "${BACKUP_REMOTE:-}" ]; then
    rclone copy "$DB_FILE" "$BACKUP_REMOTE/" && rclone copy "$MEDIA_FILE" "$BACKUP_REMOTE/" || fail "copy to $BACKUP_REMOTE"
    rclone check "$BACKUP_DIR" "$BACKUP_REMOTE" --one-way --include "serana-*-${STAMP}.*" > /dev/null 2>&1 || fail "off-server copy does not match"
    rclone delete "$BACKUP_REMOTE" --min-age "${KEEP_DAYS}d" --include "serana-*" || true
    OFFSITE=true
fi

# 4. Retention here.
find "$BACKUP_DIR" -name 'serana-*' -type f -mtime "+${KEEP_DAYS}" -delete

# 5. Record the last good backup (the Studio and serana:preflight read it).
printf '{"ok":true,"at":"%s","database":"%s","database_bytes":%s,"uploads":"%s","offsite":%s,"remote":"%s","keep_days":%s}\n' \
    "$(date -u +%FT%TZ)" "$(basename "$DB_FILE")" "$(stat -c %s "$DB_FILE")" "$(basename "$MEDIA_FILE")" "$OFFSITE" "${BACKUP_REMOTE:-}" "$KEEP_DAYS" > "$STATUS"
rm -f "$STATUS.failed"
echo "Backup OK: $(basename "$DB_FILE"), $(basename "$MEDIA_FILE"), off-server: $OFFSITE"
