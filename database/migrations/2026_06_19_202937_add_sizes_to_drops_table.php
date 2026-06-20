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
        Schema::table('drops', function (Blueprint $table) {
            // Offered size options (e.g. ["XS","S","M","L","XL"]). Empty = the
            // drop collects free measurements only.
            $table->json('sizes')->nullable()->after('target_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drops', function (Blueprint $table) {
            $table->dropColumn('sizes');
        });
    }
};
