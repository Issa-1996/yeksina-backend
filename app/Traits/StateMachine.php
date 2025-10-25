<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait StateMachine
{
    protected $stateField = 'status';

    /**
     * Définition des transitions autorisées
     * Format: 'état_actuel' => ['états_suivants_autorisés']
     */
    protected $transitions = [
        'created' => ['finding_driver', 'cancelled'],
        'finding_driver' => ['accepted', 'cancelled', 'no_driver_found'],
        'accepted' => ['picking_up', 'cancelled'],
        'picking_up' => ['on_route', 'cancelled'],
        'on_route' => ['delivered', 'cancelled'],
        'delivered' => ['paid'],
        'paid' => [], // État final
        'cancelled' => [], // État final
        'no_driver_found' => ['finding_driver', 'cancelled'],
    ];

    /**
     * Vérifie si une transition est autorisée
     */
    public function canTransitionTo($newState): bool
    {
        $currentState = $this->{$this->stateField};

        // Vérifier si la transition est autorisée
        $allowedTransitions = $this->transitions[$currentState] ?? [];

        return in_array($newState, $allowedTransitions);
    }

    /**
     * Effectue une transition d'état sécurisée
     */
    public function transitionTo($newState, $options = []): bool
    {
        $currentState = $this->{$this->stateField};

        if (!$this->canTransitionTo($newState)) {
            throw new \Exception(
                "Transition impossible de '{$currentState}' vers '{$newState}'. " .
                    "Transitions autorisées: " . implode(', ', $this->transitions[$currentState] ?? [])
            );
        }

        Log::info("🔄 Transition d'état - {$this->getMorphClass()}:{$this->id} : {$currentState} → {$newState}");

        // Sauvegarder l'ancien état
        $oldState = $currentState;

        // Mettre à jour l'état
        $this->{$this->stateField} = $newState;

        // Déclencher les hooks
        $this->beforeStateTransition($oldState, $newState, $options);
        $this->save();
        $this->afterStateTransition($oldState, $newState, $options);

        return true;
    }

    /**
     * Hook exécuté avant la transition
     */
    protected function beforeStateTransition($oldState, $newState, $options)
    {
        // Méthode à surcharger dans le modèle si nécessaire
        Log::info("BEFORE Transition: {$oldState} → {$newState}", $options);
    }

    /**
     * Hook exécuté après la transition
     */
    protected function afterStateTransition($oldState, $newState, $options)
    {
        // Méthode à surcharger dans le modèle si nécessaire
        Log::info("AFTER Transition: {$oldState} → {$newState}", $options);

        // Déclencher un événement Laravel
        event('state.transitioned', [$this, $oldState, $newState]);
    }

    /**
     * Vérifie si l'état actuel est final
     */
    public function isFinalState(): bool
    {
        return empty($this->transitions[$this->{$this->stateField}] ?? []);
    }

    /**
     * Récupère les états suivants possibles
     */
    public function getPossibleTransitions(): array
    {
        return $this->transitions[$this->{$this->stateField}] ?? [];
    }

    /**
     * Vérifie si l'état actuel correspond à un des états donnés
     */
    public function isInState($states): bool
    {
        $states = is_array($states) ? $states : func_get_args();
        return in_array($this->{$this->stateField}, $states);
    }
}
