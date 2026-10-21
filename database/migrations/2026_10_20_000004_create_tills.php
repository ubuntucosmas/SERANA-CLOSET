<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E-b (design §15.2): till sessions and their append-only cash
 * movements. One open session per register at a location: open_marker is 1
 * while open and null once closed, under a unique index. Counter orders and
 * till payments carry their session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained();
            $table->string('register', 40);
            $table->boolean('open_marker')->nullable()->default(true);
            $table->string('status', 10)->default('open');
            $table->foreignId('opened_by')->constrained('users');
            $table->timestamp('opened_at');
            $table->integer('opening_float');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->integer('expected_cash')->nullable();
            $table->integer('counted_cash')->nullable();
            $table->integer('variance')->nullable();
            $table->string('variance_reason')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['location_id', 'register', 'open_marker']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('till_session_id')->constrained();
            $table->string('type', 12); // float, sale, refund, payout, pickup
            $table->integer('amount'); // signed: into the drawer is positive
            $table->integer('tendered')->nullable(); // cash handed over, for the receipt's change (never booked)
            $table->foreignId('order_payment_id')->nullable()->constrained();
            $table->foreignId('custom_order_id')->nullable()->constrained();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['till_session_id', 'type']);
        });

        // Counter sales and till payments know their session (the Z report reads them).
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->foreignId('till_session_id')->nullable()->constrained();
            $table->foreignId('sold_by')->nullable()->constrained('users'); // who rang it up
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('till_session_id')->nullable()->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('till_session_id'));
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sold_by');
            $table->dropConstrainedForeignId('till_session_id');
        });
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('till_sessions');
    }
};
