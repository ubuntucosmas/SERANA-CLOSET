<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the money and stock code relies on from the database. The ledger is
 * read with plain reads after the order lock; on MariaDB 11.6+ with
 * innodb_snapshot_isolation on, a stale read can't be acted on (error 1020,
 * retried). Without it (older MariaDB, MySQL, or the setting off), two
 * refunds or two late payments racing can each read a stale ledger and both
 * go through (ConcurrencyTest proves both directions). SC-055 tracks making
 * those reads locking so the setting stops mattering.
 */
final class DatabaseSafety
{
    /** True/false on MySQL-family databases; null where it doesn't apply (SQLite has no concurrent writers). */
    public static function snapshotIsolationOn(): ?bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mariadb', 'mysql'], true)) {
            return null;
        }
        try {
            return (int) DB::selectOne('SELECT @@SESSION.innodb_snapshot_isolation AS v')->v === 1;
        } catch (\Illuminate\Database\QueryException) {
            return false; // the variable doesn't exist: MariaDB before 11.6, or MySQL
        }
    }
}
