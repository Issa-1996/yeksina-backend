<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Client;
use Illuminate\Console\Command;

class CreateTestDelivery extends Command
{
    protected $signature = 'delivery:create-test';
    protected $description = 'Créer une livraison de test pour la machine à états';

    public function handle()
    {
        $this->info('📦 Création d\'une livraison de test...');

        // Prendre le premier client disponible
        $client = Client::first();

        if (!$client) {
            $this->error('❌ Aucun client trouvé. Créez d\'abord un client.');
            return 1;
        }

        $delivery = Delivery::create([
            'pickup_address' => 'Point E, Dakar',
            'delivery_address' => 'Plateau, Dakar',
            'receiver_name' => 'Test User',
            'receiver_phone' => '771234567',
            'package_description' => 'Colis test machine à états',
            'package_weight' => 2.5,
            'urgency' => 'standard',
            'price' => 3500,
            'status' => 'created', // Statut initial
            'client_id' => $client->id,
            'pickup_lat' => 14.7700,
            'pickup_lng' => -17.4700,
            'delivery_lat' => 14.6700,
            'delivery_lng' => -17.4300,
            'distance_km' => 12.5,
        ]);

        $this->info("✅ Livraison de test créée: #{$delivery->id}");
        $this->line("Client: {$client->first_name} {$client->last_name}");
        $this->line("Statut: {$delivery->status}");
        $this->line("Transitions possibles: " . implode(', ', $delivery->getPossibleTransitions()));

        return 0;
    }
}
