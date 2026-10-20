<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C Step 0 (owner decision): one M-Pesa code is booked once, whether it
 * arrived by STK callback or was typed in the Studio. reference_scope is
 * computed by the database ('mpesa' for both M-Pesa methods, otherwise the
 * method), so no application code can get it wrong, and the unique index
 * moves from (method, reference) to (scope, reference).
 *
 * Also: orders get a resolution_reason, the human-readable why behind a
 * resolution (a code booked elsewhere, stock gone after a late payment...).
 */
return new class extends Migration
{
    private const SCOPE = "CASE WHEN method IN ('mpesa_stk', 'mpesa_manual') THEN 'mpesa' ELSE method END";

    public function up(): void
    {
        // Refuse loudly rather than half-migrate: list any M-Pesa code already on two methods.
        $clashes = DB::table('order_payments')->whereIn('method', ['mpesa_stk', 'mpesa_manual'])->whereNotNull('external_reference')
            ->select('external_reference')->groupBy('external_reference')->havingRaw('COUNT(*) > 1')->pluck('external_reference');
        if ($clashes->isNotEmpty()) {
            throw new RuntimeException('These M-Pesa codes are booked by both STK and hand; resolve them before migrating: ' . $clashes->implode(', '));
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropUnique(['method', 'external_reference']);
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('reference_scope', 20)->virtualAs(self::SCOPE)->after('method');
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unique(['reference_scope', 'external_reference']);
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('resolution_reason')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('resolution_reason');
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropUnique(['reference_scope', 'external_reference']);
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('reference_scope');
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->unique(['method', 'external_reference']);
        });
    }
};
