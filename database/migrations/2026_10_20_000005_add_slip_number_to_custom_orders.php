<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E-g (design §15.5): a sale written on a paper slip during an outage
 * is entered later under its slip number, once. The unique index is the
 * backstop for two people entering the same slip at the same moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('slip_number', 50)->nullable()->unique()->after('sold_by');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropUnique(['slip_number']);
            $table->dropColumn('slip_number');
        });
    }
};
