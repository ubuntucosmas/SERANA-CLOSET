<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel Telescope was removed from the project, but its tables stayed behind
 * (44 of 47 MB locally). Debug entries hold full request data, possibly
 * customer details, so they are dropped rather than kept. Safe where the
 * tables never existed. Not reversible: the debug data is intentionally gone,
 * and reinstalling Telescope recreates its own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('telescope_entries_tags');
        Schema::dropIfExists('telescope_entries');
        Schema::dropIfExists('telescope_monitoring');
    }

    public function down(): void
    {
        // Intentionally empty (see above).
    }
};
