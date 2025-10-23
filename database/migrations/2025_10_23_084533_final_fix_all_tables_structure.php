<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CLIENTS - Vérifier et corriger
        if (Schema::hasTable('clients')) {
            echo "Vérification de la table clients...\n";
            
            // Colonnes manquantes
            if (!Schema::hasColumn('clients', 'first_name')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->string('first_name')->after('id');
                });
                echo "✅ Colonne first_name ajoutée\n";
            }
            
            if (!Schema::hasColumn('clients', 'last_name')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->string('last_name')->after('first_name');
                });
                echo "✅ Colonne last_name ajoutée\n";
            }
            
            if (!Schema::hasColumn('clients', 'phone')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->string('phone')->after('last_name');
                });
                echo "✅ Colonne phone ajoutée\n";
            }
            
            if (!Schema::hasColumn('clients', 'address')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->string('address')->nullable()->after('phone');
                });
                echo "✅ Colonne address ajoutée\n";
            }
        }

        // DRIVERS - Ajouter is_available
        if (Schema::hasTable('drivers') && !Schema::hasColumn('drivers', 'is_available')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->boolean('is_available')->default(true)->after('is_approved');
            });
            echo "✅ Colonne is_available ajoutée aux drivers\n";
        }

        echo "✅ Toutes les corrections sont terminées!\n";
    }

    public function down(): void
    {
        // Ne rien faire - c'est une migration de correction
    }
};