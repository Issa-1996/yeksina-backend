<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class FixStateMachine extends Command
{
    protected $signature = 'fix:statemachine';
    protected $description = 'Réparer l\'intégration de la machine à états';

    public function handle()
    {
        $this->info('🔧 RÉPARATION MACHINE À ÉTATS');
        $this->line('---');

        // 1. Vérifier le fichier trait
        $traitPath = app_path('Traits/StateMachine.php');
        $traitExists = file_exists($traitPath);

        $this->line("1. Fichier trait: " . ($traitExists ? '✅ EXISTE' : '❌ MANQUANT'));
        if (!$traitExists) {
            $this->error('   Créez le fichier: app/Traits/StateMachine.php');
            return 1;
        }

        // 2. Vérifier le contenu du trait
        $traitContent = file_get_contents($traitPath);
        $hasNamespace = str_contains($traitContent, 'namespace App\Traits;');
        $hasTraitKeyword = str_contains($traitContent, 'trait StateMachine');

        $this->line("2. Contenu du trait:");
        $this->line("   Namespace correct: " . ($hasNamespace ? '✅ OUI' : '❌ NON'));
        $this->line("   Mot-clé trait: " . ($hasTraitKeyword ? '✅ OUI' : '❌ NON'));

        // 3. Vérifier le modèle Delivery
        $deliveryPath = app_path('Models/Delivery.php');
        $deliveryContent = file_get_contents($deliveryPath);

        $hasUseTrait = str_contains($deliveryContent, 'use App\Traits\StateMachine;');
        $hasUseInClass = str_contains($deliveryContent, 'use HasFactory, StateMachine;');

        $this->line("3. Modèle Delivery:");
        $this->line("   Use du trait: " . ($hasUseTrait ? '✅ OUI' : '❌ NON'));
        $this->line("   Utilisation dans classe: " . ($hasUseInClass ? '✅ OUI' : '❌ NON'));

        // 4. Tester avec une instance
        $this->line("4. Test d'instance:");
        try {
            $delivery = Delivery::first();
            if ($delivery) {
                $traits = class_uses($delivery);
                $hasStateMachine = in_array('App\Traits\StateMachine', $traits);

                $this->line("   Instance créée: ✅ OUI");
                $this->line("   Trait chargé: " . ($hasStateMachine ? '✅ OUI' : '❌ NON'));

                if ($hasStateMachine) {
                    $this->line("   Méthodes disponibles:");
                    $methods = ['getPossibleTransitions', 'canTransitionTo', 'transitionTo'];
                    foreach ($methods as $method) {
                        $exists = method_exists($delivery, $method);
                        $this->line("     {$method}: " . ($exists ? '✅ OUI' : '❌ NON'));
                    }

                    // Test réel
                    $this->line("   Test réel:");
                    $transitions = $delivery->getPossibleTransitions();
                    $this->line("     Transitions: " . implode(', ', $transitions));
                    $this->line("     Peut transitionner vers 'finding_driver': " . ($delivery->canTransitionTo('finding_driver') ? '✅ OUI' : '❌ NON'));
                }
            } else {
                $this->line("   Instance créée: ❌ NON (aucune livraison)");
            }
        } catch (\Exception $e) {
            $this->error("   Erreur: " . $e->getMessage());
        }

        $this->line('---');

        if (!$hasUseTrait || !$hasUseInClass) {
            $this->warn('⚠️  Le modèle Delivery n\'utilise pas correctement le trait.');
            $this->line('Assurez-vous que Delivery.php contient:');
            $this->line('use App\Traits\StateMachine;');
            $this->line('use HasFactory, StateMachine;');
        }

        return 0;
    }
}
