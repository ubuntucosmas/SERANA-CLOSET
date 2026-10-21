<?php

use App\Services\StockBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase D1 (design §13.5.1): a variant for every offered size of every
 * product, inactive variants for sizes sold before but no longer offered,
 * and existing holds mapped to a variant and the one location.
 *
 * Rule (as for the ledger backfill): StockBackfill may only write columns
 * that exist at or before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(StockBackfill::class)->run();
    }

    public function down(): void
    {
        // The variants and mapped holds go with the tables in 000001's down().
    }
};
