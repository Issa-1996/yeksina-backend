<?php
// database/migrations/2025_10_25_000000_add_current_location_to_drivers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('current_lat', 10, 8)->nullable()->after('is_available');
            $table->decimal('current_lng', 11, 8)->nullable()->after('current_lat');
            $table->timestamp('last_location_update')->nullable()->after('current_lng');

            // Index pour recherches géospatiales
            $table->index(['current_lat', 'current_lng']);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['current_lat', 'current_lng', 'last_location_update']);
        });
    }
};
