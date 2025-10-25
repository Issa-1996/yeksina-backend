<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Driver;
use App\Services\MatchingService;
use Illuminate\Console\Command;

class TestIntegratedMatching extends Command
{
    protected $signature = 'test:integrated-matching {deliveryId}';
    protected $description = 'Tester l\'intégration machine à états + matching';

    public function handle()
    {
        $delivery = Delivery::find($this->argument('deliveryId'));

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée');
            return 1;
        }

        $this->info("🎯 TEST INTÉGRATION COMPLÈTE - Livraison #{$delivery->id}");
        $this->line("État actuel: {$delivery->status}");
        $this->line("---");

        // 1. Réinitialiser au début du processus
        if ($delivery->status !== 'created') {
            $this->info("1. 🔄 Réinitialisation de la livraison...");
            $delivery->update([
                'status' => 'created',
                'driver_id' => null,
                'accepted_at' => null,
                'picked_up_at' => null,
                'delivered_at' => null,
                'paid_at' => null,
            ]);
        }

        // 2. Démarrer le matching automatique
        $this->info("2. 🚀 Démarrage matching automatique...");
        try {
            $delivery->transitionTo('finding_driver');
            $this->line("   ✅ Transition vers 'finding_driver' réussie");

            // Le matching se lance automatiquement via afterStateTransition
            sleep(2); // Laisser le temps au matching de s'exécuter

        } catch (\Exception $e) {
            $this->error("   ❌ Erreur: " . $e->getMessage());
            return 1;
        }

        // 3. Vérifier les logs de matching
        $this->info("3. 📊 Vérification des logs...");
        $this->line("   Vérifiez les logs: tail -f storage/logs/laravel.log");
        $this->line("   Vous devriez voir:");
        $this->line("   - '🔍 Début matching automatique pour delivery: {$delivery->id}'");
        $this->line("   - '🔍 DÉBUT MATCHING COMPLET - Livraison: {$delivery->id}'");
        $this->line("   - Les livreurs notifiés avec leurs scores");

        // 4. Tester l'acceptation manuelle (simulée)
        $this->info("4. 🤝 Test d'acceptation manuelle...");

        // Prendre un driver de test
        $testDriver = Driver::approved()->online()->first();
        if ($testDriver) {
            $this->line("   Driver de test: {$testDriver->full_name} (ID: {$testDriver->id})");

            try {
                $delivery->transitionTo('accepted', ['driver_id' => $testDriver->id]);
                $this->line("   ✅ Acceptance réussie - Driver assigné: {$testDriver->id}");
                $this->line("   📍 Timestamp: {$delivery->accepted_at}");
            } catch (\Exception $e) {
                $this->error("   ❌ Erreur acceptation: " . $e->getMessage());
            }
        } else {
            $this->warn("   ⚠️  Aucun driver disponible pour test d'acceptation");
        }

        $this->info("---");
        $this->info("📈 RÉSULTATS FINAUX:");
        $this->info("🏁 État final: {$delivery->status}");
        $this->info("🚗 Driver assigné: " . ($delivery->driver_id ? "OUI (ID: {$delivery->driver_id})" : "NON"));
        $this->info("⏰ Dernière mise à jour: {$delivery->updated_at}");

        $this->info("\n🎉 TEST TERMINÉ - Vérifiez les logs pour les détails du matching!");

        return 0;
    }
}
