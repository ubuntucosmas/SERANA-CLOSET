<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a shop order is being paid: "mpesa" (STK prompt) or "whatsapp" (order
 * sent on WhatsApp, payment confirmed by the studio). Existing orders were all
 * M-Pesa checkouts, so the default keeps them as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('mpesa')->after('is_paid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropColumn('payment_method');
        });
    }
};
