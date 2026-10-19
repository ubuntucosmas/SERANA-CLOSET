<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Batch 2 Phase B: a bespoke order's deposit, set by owner or manager (null = the policy share). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->unsignedInteger('deposit_override')->nullable()->after('balance_due'); // whole KES
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('deposit_override');
        });
    }
};
