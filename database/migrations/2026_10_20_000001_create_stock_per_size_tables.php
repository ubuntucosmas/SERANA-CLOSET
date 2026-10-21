<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase D1 (design §13.1): stock per size and location, beside the old
 * product-level model. Nothing reads these tables until STOCK_V2 is on.
 *
 * stock_levels.on_hand can never be negative. Laravel's schema builder has
 * no CHECK, so it's raw SQL: a CHECK constraint on MariaDB, and BEFORE
 * INSERT/UPDATE triggers on SQLite (which can't add a constraint to an
 * existing table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('fulfils_online')->default(true);
            $table->boolean('accepts_pickup')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('part', 10); // single, top, bottom
            $table->string('size', 30);
            $table->string('sku', 40)->unique();
            $table->string('barcode', 13)->nullable()->unique(); // set right after insert (it uses the id)
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'part', 'size']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->integer('on_hand')->default(0);
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->foreignId('custom_order_id')->nullable()->constrained();
            // A sale is written once per hold and size it commits (idempotent, also when the cutover
            // replays an old product-level hold of a set as its top and bottom).
            $table->foreignId('stock_reservation_id')->nullable()->constrained();
            $table->uuid('transfer_id')->nullable()->index();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['product_variant_id', 'location_id']);
            $table->unique(['stock_reservation_id', 'product_variant_id']);
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->after('product_variant_id')->constrained();
            // The bottom of a set: per size, a set is held as two rows (top and bottom), but
            // the old product-level model must count the set once, by its first part.
            $table->boolean('second_part')->default(false)->after('location_id');
            $table->index(['product_variant_id', 'location_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_stock')->default(false)->after('batch_sold');
        });
        // D8: a product with a batch limit is stocked; one without is made to order.
        DB::table('products')->whereNotNull('batch_limit')->update(['track_stock' => true]);

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER stock_levels_on_hand_{$event} BEFORE {$event} ON stock_levels
                    WHEN NEW.on_hand < 0 BEGIN SELECT RAISE(ABORT, 'stock_levels.on_hand must not be negative'); END");
            }
        } else {
            DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_on_hand_not_negative CHECK (on_hand >= 0)');
        }

        // The one shop (design D2): online orders and pickup both come from here.
        $address = DB::table('theme_settings')->where('key', 'contact_address')->value('value');
        DB::table('locations')->insert(['name' => 'Serana Studio', 'address' => $address ?: null, 'fulfils_online' => true,
            'accepts_pickup' => true, 'sort' => 0, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // MariaDB doesn't roll back DDL: drop in dependency order (foreign keys before the
        // index they use, then columns, then tables), so a failure never leaves half a schema.
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS stock_levels_on_hand_{$event}");
            }
        }
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['location_id']);
        });
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id', 'location_id', 'status']);
            $table->dropColumn(['product_variant_id', 'location_id', 'second_part']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('track_stock'));
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('locations');
    }
};
