<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    private $baseUrl = 'https://nominatim.openstreetmap.org/search';

    public function geocodeAddress(string $address): ?array
    {
        try {
            // USER-AGENT OBLIGATOIRE pour Nominatim
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Yeksina-Delivery-App/1.0 (contact@yeksina.com)',
                    'Accept' => 'application/json'
                ])
                ->get($this->baseUrl, [
                    'q' => $address . ', Dakar, Sénégal',
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1
                ]);

            Log::info('Geocoding response', [
                'address' => $address,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return [
                        'latitude' => (float) $data[0]['lat'],
                        'longitude' => (float) $data[0]['lon'],
                        'display_name' => $data[0]['display_name']
                    ];
                }
            }

            // FALLBACK: Coordonnées fixes pour Dakar
            return $this->getFallbackCoordinates($address);

        } catch (\Exception $e) {
            Log::error('Erreur géocodage: ' . $e->getMessage());
            return $this->getFallbackCoordinates($address);
        }
    }

    /**
     * Fallback avec coordonnées fixes pour Dakar
     */
    private function getFallbackCoordinates(string $address): array
    {
        $fallbackCoords = [
            'point e' => [14.7700, -17.4700],
            'plateau' => [14.6700, -17.4300],
            'almadies' => [14.7500, -17.5200],
            'mermoz' => [14.7100, -17.4700],
            'grand dakar' => [14.7200, -17.4500],
            'fann' => [14.6900, -17.4600],
            'ouakam' => [14.7300, -17.4900],
            'yoff' => [14.7500, -17.4800]
        ];

        $addressLower = strtolower($address);
        
        foreach ($fallbackCoords as $key => $coords) {
            if (str_contains($addressLower, $key)) {
                return [
                    'latitude' => $coords[0],
                    'longitude' => $coords[1],
                    'display_name' => $address . ' (Fallback)'
                ];
            }
        }

        // Fallback par défaut: Centre de Dakar
        return [
            'latitude' => 14.7167,
            'longitude' => -17.4677,
            'display_name' => $address . ' (Centre Dakar)'
        ];
    }

    /**
     * Calculer la distance entre deux points (en km) - Formule Haversine
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return round($earthRadius * $c, 2);
    }

    /**
     * Calculer le prix basé sur la distance
     */
    public function calculatePriceByDistance(float $distance, float $weight, string $urgency): float
    {
        $basePrice = 1000; // Prix de base
        $pricePerKm = 500; // 500 FCFA par km
        $weightMultiplier = $weight * 200; // 200 FCFA par kg
        
        $urgencyMultiplier = match($urgency) {
            'low' => 1.0,
            'standard' => 1.2,
            'urgent' => 1.5,
            default => 1.2
        };

        $distancePrice = $distance * $pricePerKm;
        $total = ($basePrice + $distancePrice + $weightMultiplier) * $urgencyMultiplier;
        
        return round($total, 2);
    }
}