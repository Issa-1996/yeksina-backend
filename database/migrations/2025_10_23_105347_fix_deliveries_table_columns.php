<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier et corriger les noms de colonnes
        if (Schema::hasTable('deliveries')) {
            
            // Si delivery_adress existe sans 's', la renommer
            if (Schema::hasColumn('deliveries', 'delivery_adress') && !Schema::hasColumn('deliveries', 'delivery_address')) {
                Schema::table('deliveries', function (Blueprint $table) {
                    $table->renameColumn('delivery_adress', 'delivery_address');
                });
                echo "✅ Colonne delivery_adress renommée en delivery_address\n";
            }
            
            // Si pickup_adress existe sans 's', la renommer
            if (Schema::hasColumn('deliveries', 'pickup_adress') && !Schema::hasColumn('deliveries', 'pickup_address')) {
                Schema::table('deliveries', function (Blueprint $table) {
                    $table->renameColumn('pickup_adress', 'pickup_address');
                });
                echo "✅ Colonne pickup_adress renommée en pickup_address\n";
            }
            
            // Ajouter les colonnes manquantes si nécessaire
            if (!Schema::hasColumn('deliveries', 'pickup_address')) {
                Schema::table('deliveries', function (Blueprint $table) {
                    $table->string('pickup_address')->after('id');
                });
            }
            
            if (!Schema::hasColumn('deliveries', 'delivery_address')) {
                Schema::table('deliveries', function (Blueprint $table) {
                    $table->string('delivery_address')->after('pickup_address');
                });
            }
        }
    }

    public function down(): void
    {
        // Ne rien faire pour éviter de casser la structure
    }
};