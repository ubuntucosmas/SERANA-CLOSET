<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch 2 roles: the store's "admin" becomes "owner"; manager and cashier are
 * new staff roles (App\Support\Role). Down maps staff back to the old two-role
 * world without taking access away: owner and manager become admin, cashiers
 * (who had no access before) become clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['owner', 'manager'])->update(['role' => 'admin']);
        DB::table('users')->where('role', 'cashier')->update(['role' => 'client']);
    }
};
