<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serana Drops — demand-aggregation pre-orders ("sell-then-make").
 * A drop is a design concept customers reserve (pay) against a target quantity
 * and deadline; it is produced only once enough pieces are reserved.
 *
 * Reservations reuse the custom_orders table (type='drop', drop_id set), so the
 * whole M-Pesa initiate/callback/reconcile/settlement/receipt/tracking stack
 * applies unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drops', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('target_quantity')->default(10);
            $table->dateTime('ends_at')->nullable();
            // draft | live | greenlit | fulfilled | closed
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->foreignId('drop_id')->nullable()->after('type')
                ->constrained('drops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drop_id');
        });
        Schema::dropIfExists('drops');
    }
};
