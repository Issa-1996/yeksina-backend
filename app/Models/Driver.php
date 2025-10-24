<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'address',
        'phone',  // ← AJOUTEZ CETTE LIGNE
        'cni_photo_path',
        'vehicle_type',
        'license_plate',
        'is_online',
        'last_online_at',
        'current_balance',
        'total_earnings',
        'total_deliveries',
        'average_rating',
        'is_approved',
        'is_available',
        'current_lat',
        'current_lng',
        'last_location_update',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_online' => 'boolean',
        'is_approved' => 'boolean',
        'last_online_at' => 'datetime',
        'current_balance' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'average_rating' => 'decimal:1',
        'current_lat' => 'decimal:8',
        'current_lng' => 'decimal:8',
        'last_location_update' => 'datetime',
    ];


    // Ajouter cette méthode pour mettre à jour la position
    public function updateLocation($latitude, $longitude)
    {
        $this->update([
            'current_lat' => $latitude,
            'current_lng' => $longitude,
            'last_location_update' => now(),
        ]);
    }

    // Scope pour les livreurs avec position récente (< 10 minutes)
    public function scopeWithRecentLocation($query, $minutes = 10)
    {
        return $query->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->where('last_location_update', '>=', now()->subMinutes($minutes));
    }

    // Relation avec User
    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

    // Relation avec Deliveries
    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    // Scope pour les drivers en ligne
    // public function scopeOnline($query)
    // {
    //     return $query->where('is_online', true);
    // }

    // Scope pour les drivers approuvés
    // public function scopeApproved($query)
    // {
    //     return $query->where('is_approved', true);
    // }

    // Scope pour les drivers proches (à implémenter avec PostGIS plus tard)
    public function scopeWithinRadius($query, $lat, $lng, $radiusKm = 5)
    {
        // Pour l'instant, retourne tous les drivers en ligne
        // À améliorer avec calcul de distance géospatiale
        return $query->online();
    }

    // Helper pour le nom complet
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Helper pour vérifier si le driver peut être payé
    public function canWithdraw($amount)
    {
        return $this->current_balance >= $amount;
    }

    // Mettre à jour le solde
    public function updateBalance($amount)
    {
        $this->current_balance += $amount;
        $this->total_earnings += max(0, $amount); // Seulement positif pour les gains
        $this->save();
    }


    // Ajouter ces méthodes dans la classe Driver

    /**
     * Scope pour les drivers en ligne
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope pour les drivers disponibles
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope pour les drivers approuvés
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope pour les drivers proches (à implémenter)
     */
    public function scopeNearby($query, $lat, $lng, $radius = 5)
    {
        // Pour l'instant, retourne tous les drivers
        // À améliorer avec calcul de distance géospatiale
        return $query;
    }
}
