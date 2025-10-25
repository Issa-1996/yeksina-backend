<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class TestStateMachine extends Command
{
    protected $signature = 'test:statemachine {deliveryId}';
    protected $description = 'Tester la machine à états des livraisons';

    public function handle()
    {
        $delivery = Delivery::find($this->argument('deliveryId'));

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée');
            return 1;
        }

        $this->info("🎯 TEST MACHINE À ÉTATS - Livraison #{$delivery->id}");
        $this->line("État actuel: {$delivery->status}");

        // Vérifier que getPossibleTransitions existe
        if (!method_exists($delivery, 'getPossibleTransitions')) {
            $this->error('❌ La méthode getPossibleTransitions n\'existe pas');
            $this->line('Vérifiez que le trait StateMachine est correctement chargé');
            return 1;
        }

        $possibleTransitions = $delivery->getPossibleTransitions();
        $this->line("Transitions possibles: " . ($possibleTransitions ? implode(', ', $possibleTransitions) : 'Aucune'));
        $this->line("---");

        // Test des transitions selon l'état actuel
        $testTransitions = [
            'created' => ['finding_driver', 'cancelled'],
            'finding_driver' => ['accepted', 'cancelled'],
            'accepted' => ['picking_up', 'cancelled'],
            'picking_up' => ['on_route', 'cancelled'],
            'on_route' => ['delivered', 'cancelled'],
            'delivered' => ['paid'],
        ];

        $currentState = $delivery->status;
        $transitionsToTest = $testTransitions[$currentState] ?? [];

        if (empty($transitionsToTest)) {
            $this->warn("⚠️  Aucune transition à tester pour l'état: {$currentState}");
            $this->line("État final: " . ($delivery->isFinalState() ? 'OUI' : 'NON'));
            return 0;
        }

        foreach ($transitionsToTest as $transition) {
            $this->info("Testing: {$currentState} → {$transition}");

            if ($delivery->canTransitionTo($transition)) {
                try {
                    $options = [];
                    if ($transition === 'accepted') {
                        $options = ['driver_id' => 1]; // ID de test
                    }

                    $delivery->transitionTo($transition, $options);
                    $this->line("✅ Transition réussie vers: {$transition}");

                    // Afficher les timestamps mis à jour
                    $timestamps = [];
                    if ($delivery->accepted_at) $timestamps[] = "accepted_at";
                    if ($delivery->picked_up_at) $timestamps[] = "picked_up_at";
                    if ($delivery->delivered_at) $timestamps[] = "delivered_at";

                    if ($timestamps) {
                        $this->line("   Timestamps mis à jour: " . implode(', ', $timestamps));
                    }

                    $currentState = $transition; // Mettre à jour pour le prochain test

                } catch (\Exception $e) {
                    $this->error("💥 Erreur: " . $e->getMessage());
                    break;
                }
            } else {
                $this->error("❌ Transition impossible: {$currentState} → {$transition}");
                $this->line("   Transitions autorisées: " . implode(', ', $delivery->getPossibleTransitions()));
                break;
            }

            sleep(1); // Pause pour voir les logs
        }

        $this->info("---");
        $this->info("🏁 État final: {$delivery->status}");
        $this->info($delivery->isFinalState() ? "✅ État final atteint" : "⚠️  État non final");

        return 0;
    }
}
