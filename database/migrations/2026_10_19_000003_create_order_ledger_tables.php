<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Phase A1 (design §2.1): order lines (what is owed), the payments
 * ledger (money in and out), and the split status columns on orders. Purely
 * additive; the legacy status/type/is_paid/price_quoted columns are untouched
 * and stay the source of truth until reads cut over (A4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->string('kind', 16)->nullable()->after('type');       // shop | bespoke | drop | pos
            $table->string('garment')->nullable()->after('kind');        // free-text label for bespoke
            $table->string('channel', 16)->nullable()->after('garment'); // online | whatsapp | shop
            $table->string('order_state', 16)->default('open');          // open | expired | cancelled
            $table->string('fulfilment_status', 24)->default('pending');
            $table->string('payment_status', 24)->default('unpaid');     // derived from the ledger
            $table->string('resolution', 24)->default('none');           // none | needs_resolution | amount_mismatch | waiting_restock
            $table->integer('chargeable_total')->default(0);             // whole KES, derived
            $table->integer('amount_paid')->default(0);                  // whole KES, derived
            $table->integer('balance_due')->default(0);                  // whole KES, derived

            $table->index('payment_status');
            $table->index(['order_state', 'created_at']);
            $table->index('fulfilment_status');
            $table->index('kind');
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            // Lines of an unpaid order may go with it when the Studio purges it.
            $table->foreignId('custom_order_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 20); // item | delivery | discount | adjustment | retained_deposit
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount');
            $table->integer('amount'); // signed, whole KES
            $table->string('actor_type', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('reverses_line_id')->nullable()->constrained('order_lines')->nullOnDelete();
            $table->json('meta')->nullable(); // sizes and options as chosen
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            // Money is evidence: the database refuses to delete an order that has any.
            $table->foreignId('custom_order_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);      // payment | refund
            $table->unsignedInteger('amount'); // whole KES, always positive
            $table->string('method', 20);    // mpesa_stk | mpesa_manual | cash | card | bank | credit
            $table->string('external_reference', 100)->nullable();
            $table->foreignId('payment_attempt_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('actor_type', 20); // user | system | safaricom
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['method', 'external_reference']);
            $table->index(['custom_order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_lines');
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['order_state', 'created_at']);
            $table->dropIndex(['fulfilment_status']);
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'garment', 'channel', 'order_state', 'fulfilment_status', 'payment_status', 'resolution', 'chargeable_total', 'amount_paid', 'balance_due']);
        });
    }
};
