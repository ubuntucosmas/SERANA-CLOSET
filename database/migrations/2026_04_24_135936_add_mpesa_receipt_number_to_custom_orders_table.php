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
            $table->string('mpesa_receipt_number')->nullable()->after('mpesa_checkout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('mpesa_receipt_number');
        });
    }
};
