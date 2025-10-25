<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Informations du destinataire
            $table->string('receiver_name')->after('delivery_address');
            $table->string('receiver_phone')->after('receiver_name');
            $table->text('delivery_instructions')->nullable()->after('receiver_phone');

            // Informations de l'expéditeur (optionnel - peut être différent du client connecté)
            $table->string('sender_name')->nullable()->after('delivery_instructions');
            $table->string('sender_phone')->nullable()->after('sender_name');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'receiver_name',
                'receiver_phone',
                'delivery_instructions',
                'sender_name',
                'sender_phone'
            ]);
        });
    }
};
