<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C Step 0 (owner decision): a booking that should not count is
 * reversed by a 'reversal' ledger row pointing at it (not money out; each
 * payment reversed at most once), or confirmed genuine in payment_reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('reverses_payment_id')->nullable()->unique()->after('payment_attempt_id')
                ->constrained('order_payments')->restrictOnDelete();
        });

        Schema::create('payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->unique()->constrained('order_payments')->restrictOnDelete();
            $table->string('decision', 20); // genuine
            $table->string('actor_type', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reviews');
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_payment_id');
        });
    }
};
