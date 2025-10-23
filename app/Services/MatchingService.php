<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Delivery;

class MatchingService
{
    public function findAvailableDrivers(float $pickupLat, float $pickupLng, int $radiusKm = null): array
    {
        $drivers = Driver::where('is_online', true)
            ->where('is_approved', true)
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->first_name . ' ' . $driver->last_name,
                    'distance' => rand(1, 10) + (rand(0, 9) / 10),
                    'rating' => $driver->average_rating,
                ];
            })
            ->toArray();

        return $drivers;
    }

    public function broadcastNewDelivery(Delivery $delivery): void
    {
        \Illuminate\Support\Facades\Log::info("Broadcast livraison #{$delivery->id}");
    }
}