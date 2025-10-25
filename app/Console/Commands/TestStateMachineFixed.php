<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class TestStateMachineFixed extends Command
{
    protected $signature = 'test:statemachine-fixed {deliveryId}';
    protected $description = 'Tester la machine à états (version corrigée)';

    public function handle()
    {
        $delivery = Delivery::find($this->argument('deliveryId'));

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée');
            return 1;
        }

        $this->info("🎯 TEST MACHINE À ÉTATS - Livraison #{$delivery->id}");
        $this->line("État actuel: {$delivery->status}");
        $this->line("Transitions possibles: " . implode(', ', $delivery->getPossibleTransitions()));
        $this->line("---");

        // Flux de test complet selon l'état initial
        $testFlows = [
            'created' => ['finding_driver', 'accepted', 'picking_up', 'on_route', 'delivered', 'paid'],
            'finding_driver' => ['accepted', 'picking_up', 'on_route', 'delivered', 'paid'],
            'accepted' => ['picking_up', 'on_route', 'delivered', 'paid'],
            'picking_up' => ['on_route', 'delivered', 'paid'],
            'on_route' => ['delivered', 'paid'],
            'delivered' => ['paid'],
        ];

        $currentState = $delivery->status;

        if (!isset($testFlows[$currentState])) {
            $this->error("❌ Aucun flux de test défini pour l'état: {$currentState}");
            return 1;
        }

        $flowToTest = $testFlows[$currentState];
        $successCount = 0;

        foreach ($flowToTest as $targetState) {
            $this->info("Testing: {$currentState} → {$targetState}");

            if ($delivery->canTransitionTo($targetState)) {
                try {
                    $options = [];
                    if ($targetState === 'accepted') {
                        $options = ['driver_id' => 1]; // ID de test
                    }

                    $delivery->transitionTo($targetState, $options);
                    $this->line("✅ Transition réussie");
                    $successCount++;

                    // Mettre à jour l'état courant pour le prochain test
                    $currentState = $targetState;
                } catch (\Exception $e) {
                    $this->error("💥 Erreur: " . $e->getMessage());
                    break;
                }
            } else {
                $this->warn("⚠️  Transition non autorisée (normal à ce stade)");
                $this->line("   Raison: {$currentState} ne peut pas passer directement à {$targetState}");
                $this->line("   Essayons la prochaine transition...");
            }

            sleep(1); // Pause pour voir les logs
        }

        $this->info("---");
        $this->info("📊 RÉSULTATS:");
        $this->info("✅ {$successCount} transitions réussies");
        $this->info("🏁 État final: {$delivery->status}");
        $this->info("📅 Dernier timestamp: {$delivery->updated_at}");

        // Vérifier les timestamps mis à jour
        $timestamps = [];
        if ($delivery->accepted_at) $timestamps[] = "accepté: {$delivery->accepted_at}";
        if ($delivery->picked_up_at) $timestamps[] = "récupéré: {$delivery->picked_up_at}";
        if ($delivery->delivered_at) $timestamps[] = "livré: {$delivery->delivered_at}";
        if ($delivery->paid_at) $timestamps[] = "payé: {$delivery->paid_at}";

        if ($timestamps) {
            $this->info("⏰ Timestamps: " . implode(', ', $timestamps));
        }

        return 0;
    }
}
