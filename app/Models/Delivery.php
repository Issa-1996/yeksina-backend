<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

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
        'status',
        'client_id',
        'driver_id',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
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
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'delivery_lat' => 'decimal:8',
        'delivery_lng' => 'decimal:8',
        'distance_km' => 'decimal:2',
    ];

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
        return $query->where('status', 'pending');
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
