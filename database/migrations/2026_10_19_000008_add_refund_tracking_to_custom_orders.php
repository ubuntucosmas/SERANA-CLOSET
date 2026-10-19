<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C: why an order owes the customer money and since when, for the
 * Studio Refunds queue. Set by OrderSettlementService::recompute() when the
 * ledger first shows more paid than charged, cleared once it is refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('refund_cause')->nullable()->after('resolution_reason');
            $table->timestamp('refund_due_since')->nullable()->after('refund_cause');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn(['refund_cause', 'refund_due_since']);
        });
    }
};
