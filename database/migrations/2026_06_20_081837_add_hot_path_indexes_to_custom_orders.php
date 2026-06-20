<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            // The M-Pesa callback and the reconcile job look orders up by this on
            // every payment event — the hottest money-path lookup, and unindexed.
            $table->index('mpesa_checkout_id');
            // Drop reserved-counts filter on (drop_id, is_paid).
            $table->index(['drop_id', 'is_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['mpesa_checkout_id']);
            $table->dropIndex(['drop_id', 'is_paid']);
        });
    }
};
