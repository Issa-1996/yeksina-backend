<?php

namespace App\Services;

class PricingService
{
    private $basePrice = 500;
    private $pricePerKm = 100;
    private $weightBase = 2;

    private $packageMultipliers = [
        'documents' => 1.0,
        'food' => 1.2,
        'merchandise' => 1.5,
    ];

    public function calculatePrice(float $distance, string $packageType, float $weight = 1): float
    {
        if (!array_key_exists($packageType, $this->packageMultipliers)) {
            $packageType = 'documents';
        }

        $distancePrice = $distance * $this->pricePerKm;
        $packageMultiplier = $this->packageMultipliers[$packageType];
        $weightMultiplier = max(1, $weight / $this->weightBase);
        
        $finalPrice = $this->basePrice + ($distancePrice * $packageMultiplier * $weightMultiplier);
        $finalPrice = ceil($finalPrice / 100) * 100;
        
        return (float) $finalPrice;
    }
}