<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Ajouter les nouveaux champs pour la machine à états
            $table->timestamp('paid_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->enum('cancelled_by', ['client', 'driver', 'system'])->nullable()->after('cancelled_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');

            // S'assurer que le champ status existe et a les bonnes valeurs
            $table->string('status', 50)->default('created')->change();

            // Index pour les recherches par statut
            $table->index(['status', 'created_at']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['driver_id', 'status']);
        });
    }
};
