<?php

use App\Services\LedgerBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch 2 Phase A2: build lines, ledger and split columns for existing orders
 * from their legacy fields (design §2.3; rules in App\Services\LedgerBackfill).
 *
 * Down empties the new tables and resets the new columns. The legacy columns
 * are never modified, so they remain correct on their own. Rolling back after
 * Phase A3 also drops ledger rows written since; the legacy columns are
 * dual-written, so nothing the old code reads is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LedgerBackfill::class)->all();
    }

    public function down(): void
    {
        // Rollback only: bypasses the append-only models on purpose.
        DB::table('order_payments')->delete();
        DB::table('order_lines')->delete();
        DB::table('custom_orders')->update([
            'kind' => null, 'garment' => null, 'channel' => null,
            'order_state' => 'open', 'fulfilment_status' => 'pending',
            'payment_status' => 'unpaid', 'resolution' => 'none',
            'chargeable_total' => 0, 'amount_paid' => 0, 'balance_due' => 0,
        ]);
    }
};
