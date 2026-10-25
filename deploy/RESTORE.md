# Backups and restore

For whoever runs the Serana server. Backups run nightly. A restore takes about 15 minutes once the server exists.

## What is backed up

| What | How | Where |
|---|---|---|
| Database (orders, payments, stock, staff, settings, audit) | `deploy/backup.sh`: `mysqldump --single-transaction` (no locking), gzipped, checked for completeness | `~/serana-backups` on the server, and `BACKUP_REMOTE` (off-server) |
| Uploaded images (products, gallery, bespoke references, progress photos) | `deploy/backup.sh`: `storage/app/public` as a tar.gz | Same |
| Application code | Git tags (`deploy/deploy.sh <tag>`) | GitHub |
| `.env` (secrets) | **Not** in the backup: keep a copy in the owner's password manager, updated on every change | Password manager |

Retention: `BACKUP_KEEP_DAYS` (default 30), on the server and off-server.

The last good backup is recorded in `storage/app/backup-status.json`. The Studio home warns if it is missing, older than 26 hours, or was not copied off the server. `php artisan serana:preflight` shows it under "Backups".

## Setting it up (once)

1. Install rclone and configure an off-server destination (e.g. Cloudflare R2, Backblaze B2, S3, or another machine over SFTP): `rclone config`. Name it, e.g. `r2`.
2. Make a bucket or folder for backups, e.g. `serana-backups`. Ideally the key can write but not delete; retention then goes through the provider's lifecycle rules.
3. Run it once by hand: `BACKUP_REMOTE=r2:serana-backups ./deploy/backup.sh`. It must end with `Backup OK: … off-server: true`.
4. Add the cron line from `deploy/crontab` (02:30 nightly).
5. Prove it restores (below, "Restore drill"), and note the date here.

## Restore drill (proves a backup works; never touches the live database)

```bash
# An option file for a MySQL user that may create and drop the scratch database:
printf '[client]\nhost=127.0.0.1\nuser=<admin user>\npassword=<password>\n' > ~/.restore.cnf && chmod 600 ~/.restore.cnf
RESTORE_CNF=~/.restore.cnf ./deploy/restore-drill.sh ~/serana-backups/serana-db-<stamp>.sql.gz ~/serana-backups/serana-uploads-<stamp>.tar.gz
```

It loads the dump into `serana_restore_drill` and compares row counts of the money and stock tables with the live database. It then runs `serana:ledger:verify`, `serana:ledger-check` and `serana:stock:reconcile` against the copy, unpacks the uploads and compares file counts, and drops the copy. Run it on a host where config is not cached (or on a staging copy): the script stops if artisan would read the live database.

**Drill record:**
- 2026-10-22, local ddev (MariaDB 11.8): 227 orders, 121 payments, 313 lines, 89 attempts, 238 stock levels, 285 movements, 72 products, 305 sizes, 18 users, 379 audit rows, 7 tills, 30 cash movements. All matched; ledger 0 differences; ledger check 0 mismatches; stock reconcile passed; 540/540 upload files. The off-server copy was not exercised (no rclone locally).
- Production: _not yet run_.

## Real restore (the server or database is lost)

1. **Stop new orders.** `php artisan down` on the surviving server, or point DNS at a maintenance page.
2. **Server and code.** Provision per `deploy/CLEAN-SLATE-V1-RUNBOOK.md` §1, then `git checkout <the tag that was live>` (the last line of `storage/logs/deploy*`, or ask: it is the latest `v1.*` tag deployed).
3. **Config.** Restore `.env` from the password manager. Check `APP_KEY` is the **same** as before: it decrypts sessions and signed links.
4. **Fetch the latest backup.** `rclone copy r2:serana-backups/serana-db-<latest>.sql.gz .`, and the same for `serana-uploads-<latest>.tar.gz`.
5. **Database.**
   ```bash
   mysql -e "CREATE DATABASE <db> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"   # if it doesn't exist
   zcat serana-db-<latest>.sql.gz | mysql <db>
   php artisan migrate --force   # only if the tag is newer than the backup
   ```
6. **Uploads.** `tar -xzf serana-uploads-<latest>.tar.gz -C storage/app && php artisan storage:link`
7. **Dependencies and caches.** `composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan optimize`
8. **Background jobs.** Install the cron line (`deploy/crontab`), start the queue worker (`deploy/supervisor-serana-worker.conf`), then `php artisan queue:restart`.
9. **Check.** `php artisan serana:ledger:verify`, `php artisan serana:stock:reconcile`, `php artisan serana:preflight` (all green), and `/health` returns `ok`.
10. **Reopen.** `php artisan up`.
11. **Money since the backup.** Orders and M-Pesa payments made after the backup are not in the database. Compare the M-Pesa statement from the backup time onwards with the Studio. Payments Safaricom confirms late still reach the callback. Anything else goes in Studio home → M-Pesa payments to review → "Add a payment from the M-Pesa statement". Count the shop's stock again for anything sold since.
