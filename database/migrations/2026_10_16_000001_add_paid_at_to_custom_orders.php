<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the money actually arrived. Before this, "paid this week" counted orders
 * *created* this week, and studio-recorded payments had no date at all (the
 * last edit stood in for it, so a later stage change moved the payment date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('is_paid')->index();
        });

        // Backfill: the first successful M-Pesa attempt, else the last edit (best available).
        DB::table('custom_orders')->where('is_paid', true)->orderBy('id')->each(function ($order) {
            $at = DB::table('payment_attempts')->where('custom_order_id', $order->id)->where('status', 'paid')->min('updated_at');
            DB::table('custom_orders')->where('id', $order->id)->update(['paid_at' => $at ?? $order->updated_at]);
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
