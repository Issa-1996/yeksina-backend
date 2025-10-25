<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Driver;
use App\Services\MatchingService;
use Illuminate\Console\Command;

class TestCompleteDeliveryFlow extends Command
{
    protected $signature = 'test:complete-flow';
    protected $description = 'Tester le flux complet de livraison';

    public function handle()
    {
        $this->info("🚀 TEST FLUX COMPLET DE LIVRAISON");
        $this->line("=== CRÉATION À LIVRAISON ===");

        // 1. Créer une nouvelle livraison
        $this->info("1. 📦 Création livraison...");
        $this->call('delivery:create-test');

        $delivery = Delivery::latest()->first();
        if (!$delivery) {
            $this->error("❌ Échec création livraison");
            return 1;
        }

        $this->line("   ✅ Livraison #{$delivery->id} créée");
        $this->line("   📍 Pickup: {$delivery->pickup_address}");
        $this->line("   🎯 Destination: {$delivery->delivery_address}");

        // 2. Démarrer le matching
        $this->info("2. 🔍 Démarrage matching...");
        try {
            $delivery->transitionTo('finding_driver');
            $this->line("   ✅ Matching démarré");

            // Laisser le temps au matching de s'exécuter
            sleep(3);
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur matching: " . $e->getMessage());
            return 1;
        }

        // 3. Accepter la livraison
        $this->info("3. 🤝 Acceptation livraison...");
        $driver = Driver::approved()->online()->available()->first();

        if ($driver) {
            try {
                $delivery->transitionTo('accepted', ['driver_id' => $driver->id]);
                $this->line("   ✅ Acceptée par: {$driver->full_name}");
                $this->line("   🕒 Acceptée à: {$delivery->accepted_at}");
            } catch (\Exception $e) {
                $this->error("   ❌ Erreur acceptation: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->error("   ❌ Aucun driver disponible");
            return 1;
        }

        // 4. Simuler le parcours de livraison
        $this->info("4. 🚚 Parcours de livraison...");

        $deliverySteps = [
            'picking_up' => '🎯 Récupération du colis',
            'on_route' => '🚗 En route vers destination',
            'delivered' => '🏁 Colis livré',
            'paid' => '💰 Paiement effectué'
        ];

        foreach ($deliverySteps as $state => $description) {
            try {
                $delivery->transitionTo($state);
                $this->line("   ✅ {$description}");
                sleep(1); // Pause réaliste

            } catch (\Exception $e) {
                $this->error("   ❌ {$description}: " . $e->getMessage());
                break;
            }
        }

        // 5. Résumé final
        $this->info("5. 📊 RÉSUMÉ FINAL");
        $this->line("---");
        $this->line("📦 Livraison #{$delivery->id}");
        $this->line("🚗 Driver: {$driver->full_name}");
        $this->line("🏁 Statut final: {$delivery->status}");
        $this->line("💰 Prix: {$delivery->price} FCFA");

        // Timestamps
        $this->line("⏰ Chronologie:");
        if ($delivery->accepted_at) $this->line("   Acceptée: {$delivery->accepted_at->format('H:i:s')}");
        if ($delivery->picked_up_at) $this->line("   Récupérée: {$delivery->picked_up_at->format('H:i:s')}");
        if ($delivery->delivered_at) $this->line("   Livrée: {$delivery->delivered_at->format('H:i:s')}");
        if ($delivery->paid_at) $this->line("   Payée: {$delivery->paid_at->format('H:i:s')}");

        $this->line("---");
        $this->info("🎉 FLUX COMPLET TESTÉ AVEC SUCCÈS!");
        $this->line("Vérifiez les logs pour les détails du matching:");
        $this->line("Get-Content storage/logs/laravel.log -Tail 30");

        return 0;
    }
}
