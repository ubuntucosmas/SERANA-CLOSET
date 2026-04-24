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
        Schema::table('custom_orders', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->string('mpesa_checkout_id')->nullable()->after('is_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_orders', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('mpesa_checkout_id');
        });
    }
};
