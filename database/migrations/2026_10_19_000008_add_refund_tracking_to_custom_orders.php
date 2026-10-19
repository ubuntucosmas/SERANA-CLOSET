<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C: why an order owes the customer money and since when, for the
 * Studio Refunds queue. Set by OrderSettlementService::recompute() when the
 * ledger first shows more paid than charged, cleared once it is refunded.
 *
 * Also what kind of payment problem is open (resolution_code: stock_gone,
 * reference_conflict…) and since when, so the overdue job acts only on the
 * problems it may settle and only after the policy's days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('refund_cause')->nullable()->after('resolution_reason');
            $table->timestamp('refund_due_since')->nullable()->after('refund_cause');
            $table->string('resolution_code', 40)->nullable()->after('resolution');
            $table->timestamp('resolution_since')->nullable()->after('resolution_code');
            $table->index(['resolution', 'resolution_since']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['resolution', 'resolution_since']);
            $table->dropColumn(['refund_cause', 'refund_due_since', 'resolution_code', 'resolution_since']);
        });
    }
};
