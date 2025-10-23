<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_PICKING_UP = 'picking_up';
    const STATUS_ON_ROUTE = 'on_route';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'driver_id',
        'status',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'estimated_weight',
        'price',
        'security_code',
        'security_code_validated',
        'client_notes',
        'accepted_at',
        'picking_up_at',
        'on_route_at',
        'delivered_at',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
        'estimated_weight' => 'decimal:2',
        'price' => 'decimal:2',
        'security_code_validated' => 'boolean',
        'accepted_at' => 'datetime',
        'picking_up_at' => 'datetime',
        'on_route_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Relation avec Client
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Relation avec Driver
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    // Scopes pour les statuts
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED, 
            self::STATUS_PICKING_UP, 
            self::STATUS_ON_ROUTE
        ]);
    }

    // Méthodes pour changer le statut
    public function markAsAccepted()
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function markAsPickingUp()
    {
        $this->update([
            'status' => self::STATUS_PICKING_UP,
            'picking_up_at' => now(),
        ]);
    }

    public function markAsOnRoute()
    {
        $this->update([
            'status' => self::STATUS_ON_ROUTE,
            'on_route_at' => now(),
        ]);
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    // Vérifier si le code de sécurité est valide
    public function validateSecurityCode($code)
    {
        if ($this->security_code == $code) {
            $this->update(['security_code_validated' => true]);
            return true;
        }
        return false;
    }
}