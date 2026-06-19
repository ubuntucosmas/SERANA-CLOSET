<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock reservations decouple "held during an unpaid checkout" from "actually
 * sold". Limited-batch availability is batch_limit - batch_sold - active holds.
 * Holds expire (released by a scheduler) so abandoned carts can't drain a batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            // held -> committed (paid) | released (expired/failed)
            $table->string('status')->default('held');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
