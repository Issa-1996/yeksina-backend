<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class DebugStateMachine extends Command
{
    protected $signature = 'debug:statemachine {deliveryId}';
    protected $description = 'Déboguer la machine à états';

    public function handle()
    {
        $delivery = Delivery::find($this->argument('deliveryId'));

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée');
            return 1;
        }

        $this->info("🔍 DÉBOGAGE MACHINE À ÉTATS - Livraison #{$delivery->id}");
        $this->line("---");

        // 1. Vérifier le statut actuel
        $this->line("1. Statut actuel: {$delivery->status}");

        // 2. Vérifier que le trait est chargé
        $traits = class_uses($delivery);
        $this->line("2. Traits chargés: " . implode(', ', $traits));

        $hasStateMachine = in_array('App\Traits\StateMachine', $traits);
        $this->line("   StateMachine chargé: " . ($hasStateMachine ? '✅ OUI' : '❌ NON'));

        // 3. Vérifier les méthodes
        $methods = [
            'getPossibleTransitions',
            'canTransitionTo',
            'transitionTo',
            'isFinalState'
        ];

        $this->line("3. Méthodes disponibles:");
        foreach ($methods as $method) {
            $exists = method_exists($delivery, $method);
            $this->line("   {$method}: " . ($exists ? '✅ OUI' : '❌ NON'));
        }

        // 4. Vérifier les transitions possibles
        if ($hasStateMachine && method_exists($delivery, 'getPossibleTransitions')) {
            $transitions = $delivery->getPossibleTransitions();
            $this->line("4. Transitions possibles: " . implode(', ', $transitions));
        } else {
            $this->error("   Impossible de récupérer les transitions");
        }

        // 5. Tester une transition simple
        if ($hasStateMachine) {
            $this->line("5. Test transition:");
            $testTransition = 'finding_driver';
            $canTransition = $delivery->canTransitionTo($testTransition);
            $this->line("   {$delivery->status} → {$testTransition}: " . ($canTransition ? '✅ AUTORISÉ' : '❌ INTERDIT'));
        }

        $this->line("---");

        if (!$hasStateMachine) {
            $this->error("❌ LE TRAIT STATEMACHINE N'EST PAS CHARGÉ");
            $this->line("Vérifiez que:");
            $this->line("- Le fichier app/Traits/StateMachine.php existe");
            $this->line("- Le namespace est: namespace App\Traits;");
            $this->line("- Delivery.php utilise le trait: use App\Traits\StateMachine;");
        }

        return 0;
    }
}
