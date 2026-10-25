<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-Pesa money that arrived but matches no order (a callback for a prompt we
 * never recorded, or a payment the owner found only on the statement). It is
 * never discarded: it waits for the owner to match it to an order or mark it
 * for refund, and stays listed until one of those is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmatched_payments', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20); // callback | statement
            $table->string('method', 20); // mpesa_stk (a prompt's callback) | mpesa_manual (seen on the statement)
            $table->string('checkout_request_id', 100)->nullable()->unique();
            $table->string('receipt', 30)->nullable()->unique(); // the M-Pesa code
            $table->unsignedInteger('amount');
            $table->string('phone', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('state', 20)->default('needs_review')->index(); // needs_review | matched | refund_required | refunded
            $table->foreignId('custom_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_payment_id')->nullable()->constrained('order_payments')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmatched_payments');
    }
};
