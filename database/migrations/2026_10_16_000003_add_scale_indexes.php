<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that run on every request or every minute, so they
 * stay fast as the tables grow:
 *  - stock:release-expired looks up held reservations by expiry (the existing
 *    index leads with product_id, which that query doesn't filter on);
 *  - the expiry job and Studio filter orders by status and age.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'stock_reservations_status_expires_at_index');
        });
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'custom_orders_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', fn (Blueprint $table) => $table->dropIndex('stock_reservations_status_expires_at_index'));
        Schema::table('custom_orders', fn (Blueprint $table) => $table->dropIndex('custom_orders_status_created_at_index'));
    }
};
