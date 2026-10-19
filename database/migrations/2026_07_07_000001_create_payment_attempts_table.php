<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per STK push. Previously the latest CheckoutRequestID overwrote
 * `custom_orders.mpesa_checkout_id`, so if a customer retried payment and then
 * paid the *earlier* prompt still on their phone, the callback matched nothing
 * and the money was orphaned. Attempts make every push individually
 * addressable: the callback and the reconciler resolve the attempt they're
 * actually about, and the order settles when any one attempt succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_id')->constrained()->cascadeOnDelete();
            $table->string('checkout_request_id')->unique();
            $table->string('phone', 20);
            // Whole KES, as quoted at push time — the amount the callback must match.
            $table->unsignedInteger('amount');
            // pending -> paid | failed (cancelled/timed out/mismatched)
            $table->string('status', 20)->default('pending');
            $table->string('result_code', 10)->nullable();
            $table->string('result_desc')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->timestamps();

            // Reconciler scans pending attempts inside a time window.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
