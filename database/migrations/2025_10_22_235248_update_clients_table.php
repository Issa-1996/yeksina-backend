<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si les colonnes existent, sinon les ajouter
        if (!Schema::hasColumn('clients', 'first_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('first_name')->after('id');
            });
        }

        if (!Schema::hasColumn('clients', 'last_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('last_name')->after('first_name');
            });
        }

        if (!Schema::hasColumn('clients', 'phone')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('phone')->after('last_name');
            });
        }

        if (!Schema::hasColumn('clients', 'address')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('address')->nullable()->after('phone');
            });
        }

        // Ajouter l'unicité du phone si nécessaire
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        // Pas besoin de rollback pour cette modification
    }
};