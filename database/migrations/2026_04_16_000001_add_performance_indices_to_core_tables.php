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
            // Index for high-speed slug lookups (SEO & Detail pages)
            if (!Schema::hasColumn('products', 'slug')) return;
            $table->index('slug');
            
            // Index for category filtering in Shop
            if (!Schema::hasColumn('products', 'category_id')) return;
            $table->index('category_id');
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            // Index for Studio Dashboard filtering
            if (!Schema::hasColumn('custom_orders', 'status')) return;
            $table->index('status');
            
            // Index for user history and profile lookups
            if (!Schema::hasColumn('custom_orders', 'user_id')) return;
            $table->index('user_id');
            
            // Index for rapid Artisan communication lookup
            if (!Schema::hasColumn('custom_orders', 'whatsapp_number')) return;
            $table->index('whatsapp_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['category_id']);
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['whatsapp_number']);
        });
    }
};
