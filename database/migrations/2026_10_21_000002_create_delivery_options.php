<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D2 (design §13.7): how an order reaches the customer, and what it
 * costs. The shop starts with collection at its first location and a quote
 * on WhatsApp for anywhere else; the owner adds zones with their fees.
 * Options are deactivated, never deleted: orders keep pointing at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 16); // pickup | zone | quote
            $table->unsignedInteger('fee')->default(0); // whole KES
            $table->unsignedInteger('free_over')->nullable(); // item subtotal at or over which a zone is free
            $table->string('eta_text', 120)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations'); // pickup only
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->foreignId('delivery_option_id')->nullable()->constrained('delivery_options');
        });

        $shop = DB::table('locations')->orderBy('sort')->orderBy('id')->value('id');
        DB::table('delivery_options')->insert([
            ['name' => 'Collect from the shop', 'type' => 'pickup', 'fee' => 0, 'free_over' => null, 'eta_text' => 'When tracking says it is ready',
                'location_id' => $shop, 'active' => true, 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Outside our zones — quote on WhatsApp', 'type' => 'quote', 'fee' => 0, 'free_over' => null, 'eta_text' => null,
                'location_id' => null, 'active' => true, 'sort' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_option_id');
        });
        Schema::dropIfExists('delivery_options');
    }
};
