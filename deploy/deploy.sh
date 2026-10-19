#!/usr/bin/env bash
# Deploy a tagged release on the server: ./deploy/deploy.sh v1.0.0
#
# Order matters: back up first, never migrate without a backup, and finish
# with serana:preflight, which fails loudly if anything is misconfigured.
# Rollback: git checkout <previous tag>, restore the backup printed below.
set -euo pipefail

TAG="${1:?Usage: deploy/deploy.sh <release tag>, e.g. v1.0.0}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/serana-backups}"
cd "$APP_DIR"

git fetch --tags --quiet
git rev-parse --verify --quiet "refs/tags/${TAG}" >/dev/null || { echo "No such tag: ${TAG}"; exit 1; }
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "The server copy has local edits. Deploys come from tags only; resolve them first."; exit 1
fi
echo "Current release: $(git describe --tags --always) → deploying ${TAG}"

# 1. Backup (database + uploads). Read DB credentials from .env.
mkdir -p "$BACKUP_DIR"
STAMP="$(date +%F-%H%M)"
env_val() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
MYSQL_PWD="$(env_val DB_PASSWORD)" mysqldump --single-transaction --routines --triggers \
    -h "$(env_val DB_HOST)" -P "$(env_val DB_PORT)" -u "$(env_val DB_USERNAME)" "$(env_val DB_DATABASE)" \
    | gzip > "$BACKUP_DIR/serana-db-${STAMP}.sql.gz"
[ -s "$BACKUP_DIR/serana-db-${STAMP}.sql.gz" ] || { echo "Database backup is empty: stopping before any change."; exit 1; }
tar -czf "$BACKUP_DIR/serana-uploads-${STAMP}.tar.gz" -C storage/app public 2>/dev/null || true
echo "Backups: $BACKUP_DIR/serana-*-${STAMP}.*  (copy them off this server)"

# 2. Code and dependencies.
php artisan down --retry=60
trap 'php artisan up' EXIT
git checkout --quiet "$TAG"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund && npm run build

# 3. Database, caches, workers.
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan queue:restart

php artisan up
trap - EXIT
echo "Live on ${TAG}. Checking the server:"
php artisan serana:preflight
