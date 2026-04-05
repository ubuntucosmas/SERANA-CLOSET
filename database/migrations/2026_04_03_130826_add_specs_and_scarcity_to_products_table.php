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
        Schema::table('products', function (Blueprint $table) {
            $table->json('specifications')->nullable()->comment('Technical details like GSM, stitch density, etc.');
            $table->integer('batch_limit')->nullable()->default(15)->comment('Scarcity limit for the current drop');
            $table->integer('batch_sold')->nullable()->default(0)->comment('Current count sold in this batch for FOMO logic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
