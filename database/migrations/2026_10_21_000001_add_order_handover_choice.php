<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            // Null preserves the meaning of older orders; never infer pickup.
            $table->string('fulfilment_method', 16)->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations');
            $table->json('fulfilment_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_location_id');
            $table->dropColumn(['fulfilment_method', 'fulfilment_snapshot']);
        });
    }
};
