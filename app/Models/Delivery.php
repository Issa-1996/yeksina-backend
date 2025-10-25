<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

use App\Traits\StateMachine;

class Delivery extends Model
{
    use HasFactory, StateMachine; // ✅ StateMachine ici

    protected $fillable = [
        'pickup_address',
        'delivery_address',
        'receiver_name',        // ← AJOUT
        'receiver_phone',       // ← AJOUT
        'delivery_instructions', // ← AJOUT
        'sender_name',          // ← AJOUT
        'sender_phone',         // ← AJOUT
        'package_description',
        'package_weight',
        'urgency',
        'price',
        'status', // ← IMPORTANT: doit être dans fillable
        'status',
        'client_id',
        'driver_id',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'paid_at', // ← NOUVEAU: timestamp pour paiement
        'cancelled_at', // ← NOUVEAU: timestamp pour annulation
        'cancelled_by', // ← NOUVEAU: qui a annulé (client/driver/system)
        'cancellation_reason', // ← NOUVEAU: raison d'annulation
        'pickup_lat',      // ← AJOUT
        'pickup_lng',      // ← AJOUT  
        'delivery_lat',    // ← AJOUT
        'delivery_lng',    // ← AJOUT
        'distance_km',     // ← AJOUT
    ];

    protected $casts = [
        'package_weight' => 'decimal:2',
        'price' => 'decimal:2',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'delivery_lat' => 'decimal:8',
        'delivery_lng' => 'decimal:8',
        'distance_km' => 'decimal:2',
    ];

    // Constantes pour les statuts
    const STATUS_CREATED = 'created';
    const STATUS_FINDING_DRIVER = 'finding_driver';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_PICKING_UP = 'picking_up';
    const STATUS_ON_ROUTE = 'on_route';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_DRIVER_FOUND = 'no_driver_found';

    // Constantes pour les annulations
    const CANCELLED_BY_CLIENT = 'client';
    const CANCELLED_BY_DRIVER = 'driver';
    const CANCELLED_BY_SYSTEM = 'system';


    /**
     * Relation avec le client
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relation avec le driver
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Scope pour les livraisons en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_CREATED);
    }

    /**
     * Scope pour les livraisons actives
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_FINDING_DRIVER,
            self::STATUS_ACCEPTED,
            self::STATUS_PICKING_UP,
            self::STATUS_ON_ROUTE
        ]);
    }

    /**
     * Scope pour les livraisons terminées
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DELIVERED,
            self::STATUS_PAID
        ]);
    }

    /**
     * Scope pour les livraisons annulées
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Vérifie si la livraison peut être acceptée
     */
    public function canBeAccepted(): bool
    {
        return $this->canTransitionTo(self::STATUS_ACCEPTED);
    }

    /**
     * Vérifie si la livraison peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return $this->canTransitionTo(self::STATUS_CANCELLED);
    }


    /**
     * Annule la livraison avec raison
     */
    public function cancel($cancelledBy, $reason = null): bool
    {
        return $this->transitionTo(self::STATUS_CANCELLED, [
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason
        ]);
    }

    /**
     * Convertit les anciens statuts 'pending' vers 'created'
     */
    public static function migrateOldStatuses()
    {
        $updated = self::where('status', 'pending')->update(['status' => self::STATUS_CREATED]);
        Log::info("🔄 {$updated} livraisons migrées de 'pending' vers 'created'");
        return $updated;
    }

    /**
     * Hook exécuté après chaque transition d'état
     */
    protected function afterStateTransition($oldState, $newState, $options)
    {
        Log::info("🎯 Delivery {$this->id} : {$oldState} → {$newState}");

        // Mettre à jour les timestamps selon le nouvel état
        $this->updateTimestampsForState($newState);

        // Exécuter les actions spécifiques à chaque état
        $this->executeStateActions($newState, $options);

        // Sauvegarder les modifications
        $this->save();

        // Déclencher les événements métier
        $this->triggerBusinessEvents($oldState, $newState);
    }

    /**
     * Met à jour les timestamps selon l'état
     */
    private function updateTimestampsForState($state)
    {
        $timestampMap = [
            self::STATUS_ACCEPTED => 'accepted_at',
            self::STATUS_PICKING_UP => 'picked_up_at',
            self::STATUS_DELIVERED => 'delivered_at',
            self::STATUS_PAID => 'paid_at',
            self::STATUS_CANCELLED => 'cancelled_at',
        ];

        if (isset($timestampMap[$state]) && !$this->{$timestampMap[$state]}) {
            $this->{$timestampMap[$state]} = now();
        }
    }

    /**
     * Exécute les actions spécifiques à chaque état
     */
    private function executeStateActions($state, $options)
    {
        switch ($state) {
            case self::STATUS_FINDING_DRIVER:
                $this->startDriverMatching();
                break;

            case self::STATUS_ACCEPTED:
                $this->onAccepted($options['driver_id'] ?? null);
                break;

            case self::STATUS_DELIVERED:
                $this->onDelivered();
                break;

            case self::STATUS_PAID:
                $this->onPaid();
                break;

            case self::STATUS_CANCELLED:
                $this->onCancelled(
                    $options['cancelled_by'] ?? null,
                    $options['cancellation_reason'] ?? null
                );
                break;
        }
    }

    /**
     * Actions lors de la livraison
     */
    private function onDelivered()
    {
        Log::info("🏁 Livraison {$this->id} livrée avec succès");

        // Générer le code de sécurité (si pas déjà fait)
        // TODO: Implémenter le système de codes de sécurité

        // Notifier le client de la livraison
        // TODO: Notification push
    }


    /**
     * Actions lors du paiement
     */
    private function onPaid()
    {
        Log::info("💰 Paiement effectué pour livraison: {$this->id}");

        // Crediter le livreur (moins commission)
        if ($this->driver) {
            $commissionRate = 0.15; // 15% de commission
            $driverEarnings = $this->price * (1 - $commissionRate);

            $this->driver->updateBalance($driverEarnings);

            Log::info("💳 Driver {$this->driver->id} crédité de: {$driverEarnings} FCFA");
        }
    }

    /**
     * Actions lors de l'annulation
     */
    private function onCancelled($cancelledBy, $reason)
    {
        $this->cancelled_by = $cancelledBy;
        $this->cancellation_reason = $reason;

        Log::info("❌ Livraison {$this->id} annulée par: {$cancelledBy}, raison: {$reason}");

        // Appliquer les politiques d'annulation
        $this->applyCancellationPolicy($cancelledBy);

        // Notifications
        // TODO: Notifier l'autre partie (client ou driver)
    }

    /**
     * Applique la politique d'annulation
     */
    private function applyCancellationPolicy($cancelledBy)
    {
        // Politiques simples - à affiner selon vos règles métier
        $policies = [
            self::CANCELLED_BY_CLIENT => [
                'before_accepted' => 'no_penalty',
                'after_accepted' => 'small_penalty'
            ],
            self::CANCELLED_BY_DRIVER => [
                'penalty' => 'rating_impact'
            ],
            self::CANCELLED_BY_SYSTEM => [
                'penalty' => 'none'
            ]
        ];

        Log::info("📋 Application politique annulation pour: {$cancelledBy}");
    }

    /**
     * Déclenche les événements métier
     */
    private function triggerBusinessEvents($oldState, $newState)
    {
        // Événement générique de changement d'état
        event(new \App\Events\DeliveryStatusChanged($this, $oldState, $newState));

        // Événements spécifiques
        if ($newState === self::STATUS_ACCEPTED) {
            event(new \App\Events\DeliveryAccepted($this));
        }

        if ($newState === self::STATUS_DELIVERED) {
            event(new \App\Events\DeliveryDelivered($this));
        }
    }


    /**
     * Actions lors de l'acceptation par un livreur
     */
    private function onAccepted($driverId)
    {
        if ($driverId) {
            $this->driver_id = $driverId;
        }

        // Notifier le client
        Log::info("✅ Livraison {$this->id} acceptée par driver: {$this->driver_id}");

        // TODO: Notification push au client
    }

    /**
     * Démarre la recherche de livreur
     */
    private function startDriverMatching()
    {
        Log::info("🔍 Début matching pour delivery: {$this->id}");

        // Lancer le matching asynchrone
        dispatch(function () {
            $matchingService = new \App\Services\MatchingService();
            $matchedDrivers = $matchingService->findDriversForDelivery($this);

            if (empty($matchedDrivers)) {
                // Aucun livreur trouvé après un certain temps
                $this->transitionTo(self::STATUS_NO_DRIVER_FOUND);
            }
        });
    }

    /**
     * Scope pour les livraisons d'un driver
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope pour les livraisons d'un client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
