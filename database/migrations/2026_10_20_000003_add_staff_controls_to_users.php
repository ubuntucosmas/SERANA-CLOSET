<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E-a (design §15.1): staff accounts the owner manages in the Studio.
 * Suspension (with why), a hashed till PIN (D12), a one-time password that
 * must be changed at first sign-in, and the last sign-in for the staff list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('role');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
            $table->string('pos_pin')->nullable()->after('suspended_reason');
            $table->boolean('must_change_password')->default(false)->after('pos_pin');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspended_reason', 'pos_pin', 'must_change_password', 'last_login_at']);
        });
    }
};
