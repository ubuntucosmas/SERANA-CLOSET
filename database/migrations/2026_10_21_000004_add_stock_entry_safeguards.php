<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0);
        });
        Schema::create('stock_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fingerprint', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->string('action', 16);
            $table->string('reference', 100)->nullable();
            $table->string('reason', 255);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUuid('stock_operation_id')->nullable()->constrained('stock_operations');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('stock_operation_id'));
        Schema::dropIfExists('stock_operations');
        Schema::table('stock_levels', fn (Blueprint $table) => $table->dropColumn('version'));
    }
};
