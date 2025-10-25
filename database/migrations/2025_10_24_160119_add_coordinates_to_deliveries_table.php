<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Coordonnées point de ramassage
            $table->decimal('pickup_lat', 10, 8)->nullable()->after('pickup_address');
            $table->decimal('pickup_lng', 11, 8)->nullable()->after('pickup_lat');
            
            // Coordonnées point de livraison
            $table->decimal('delivery_lat', 10, 8)->nullable()->after('delivery_address');
            $table->decimal('delivery_lng', 11, 8)->nullable()->after('delivery_lat');
            
            // Distance calculée
            $table->decimal('distance_km', 8, 2)->nullable()->after('delivery_lng');
            
            // Index pour les recherches géospatiales
            $table->index(['pickup_lat', 'pickup_lng']);
            $table->index(['delivery_lat', 'delivery_lng']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_lat', 
                'pickup_lng',
                'delivery_lat',
                'delivery_lng', 
                'distance_km'
            ]);
        });
    }
};