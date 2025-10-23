<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les colonnes manquantes
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
    }

    public function down(): void
    {
        // Ne rien faire en rollback pour éviter de perdre des données
    }
};