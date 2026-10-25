#!/usr/bin/env bash
# Prove a backup restores, WITHOUT touching the live database:
#   RESTORE_CNF=~/.restore.cnf ./deploy/restore-drill.sh <serana-db-*.sql.gz> <serana-uploads-*.tar.gz>
#
#   RESTORE_CNF  a MySQL option file for a user that may CREATE/DROP the scratch database
#   RESTORE_DB   the scratch database (default serana_restore_drill; must contain "restore")
#   KEEP=1       keep the scratch database afterwards
#
# It loads the dump into RESTORE_DB, compares row counts of the money and stock
# tables with the live database, runs the ledger and stock checks against the
# copy, unpacks the uploads and compares the file count. Run it after setting
# up backups, then every few months. Real restore: deploy/RESTORE.md.
set -euo pipefail

DUMP="${1:?Usage: restore-drill.sh <db backup .sql.gz> <uploads .tar.gz>}"
MEDIA="${2:?Usage: restore-drill.sh <db backup .sql.gz> <uploads .tar.gz>}"
CNF="${RESTORE_CNF:?Set RESTORE_CNF to an option file for a user that may create the scratch database}"
RESTORE_DB="${RESTORE_DB:-serana_restore_drill}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"
env_val() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
LIVE_DB="$(env_val DB_DATABASE)"

case "$RESTORE_DB" in *restore*) ;; *) echo "RESTORE_DB must contain 'restore' (got $RESTORE_DB)."; exit 1;; esac
[ "$RESTORE_DB" != "$LIVE_DB" ] || { echo "Refusing: RESTORE_DB is the live database."; exit 1; }

echo "1. Load $(basename "$DUMP") into $RESTORE_DB"
mysql --defaults-file="$CNF" -e "DROP DATABASE IF EXISTS \`$RESTORE_DB\`; CREATE DATABASE \`$RESTORE_DB\`;"
zcat "$DUMP" | mysql --defaults-file="$CNF" "$RESTORE_DB"

echo "2. Compare row counts with the live database (the live one may have moved on since the backup)"
for t in custom_orders order_payments order_lines payment_attempts stock_levels stock_movements products product_variants users audit_logs till_sessions cash_movements; do
    copy="$(mysql --defaults-file="$CNF" -N -e "SELECT COUNT(*) FROM \`$RESTORE_DB\`.\`$t\`")"
    live="$(mysql --defaults-file="$CNF" -N -e "SELECT COUNT(*) FROM \`$LIVE_DB\`.\`$t\`")"
    printf '   %-18s backup %-8s live %s\n' "$t" "$copy" "$live"
done

echo "3. Ledger and stock checks against the copy (as the drill user, who can read it)"
cnf_val() { grep -E "^$1=" "$CNF" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
on_copy() {
    local host; host="$(cnf_val host || true)"
    if [ -n "$host" ]; then export DB_HOST="$host"; fi
    DB_DATABASE="$RESTORE_DB" DB_USERNAME="$(cnf_val user)" DB_PASSWORD="$(cnf_val password)" php artisan "$@"
}
seen="$(on_copy tinker --execute='echo DB::connection()->getDatabaseName();' 2>/dev/null | tail -1)"
[ "$seen" = "$RESTORE_DB" ] || { echo "   Artisan reads '$seen', not the copy (config is cached?). Run php artisan config:clear on a staging copy, or skip this step."; exit 1; }
on_copy serana:ledger:verify
on_copy serana:ledger-check
on_copy serana:stock:reconcile | tail -1

echo "4. Uploads archive"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
tar -xzf "$MEDIA" -C "$TMP"
printf '   files in backup: %s · files now: %s\n' "$(find "$TMP/public" -type f | wc -l)" "$(find storage/app/public -type f | wc -l)"

if [ "${KEEP:-0}" != "1" ]; then
    mysql --defaults-file="$CNF" -e "DROP DATABASE \`$RESTORE_DB\`;"
    echo "Dropped $RESTORE_DB."
fi
echo "Restore drill passed: $(basename "$DUMP") restores and checks clean."
