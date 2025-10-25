<?php
// app/Services/MatchingService.php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MatchingService
{
    private $searchRadiusKm = 5; // Rayon de recherche en km
    private $minRating = 4.0;   // Note minimale
    private $maxDriversToNotify = 3; // Nombre max de livreurs à notifier

    /**
     * Trouve les meilleurs livreurs pour une livraison
     */
    public function findDriversForDelivery(Delivery $delivery): array
    {
        Log::info('🔍 DÉBUT MATCHING - Livraison: ' . $delivery->id);

        // 1. Récupérer les livreurs éligibles
        $eligibleDrivers = $this->getEligibleDrivers($delivery);

        if (empty($eligibleDrivers)) {
            Log::warning('❌ Aucun livreur éligible trouvé pour livraison: ' . $delivery->id);
            return [];
        }

        Log::info('📊 Livreurs éligibles: ' . count($eligibleDrivers));

        // 2. Calculer les scores pour chaque livreur
        $scoredDrivers = $this->calculateScores($eligibleDrivers, $delivery);

        // 3. Trier par score décroissant
        $rankedDrivers = $this->rankDrivers($scoredDrivers);

        // 4. Prendre les meilleurs
        $topDrivers = array_slice($rankedDrivers, 0, $this->maxDriversToNotify);

        Log::info('✅ MATCHING TERMINÉ - Top livreurs: ' . count($topDrivers));

        return $topDrivers;
    }

    /**
     * Récupère les livreurs éligibles pour une livraison
     */
    private function getEligibleDrivers(Delivery $delivery)
    {
        return Driver::query()
            ->with('user')
            ->approved()
            ->online()
            ->available()
            ->withRecentLocation(10) // seulement positions < 10min
            ->where('average_rating', '>=', $this->minRating)
            ->get()
            ->filter(function ($driver) use ($delivery) {
                return $this->isWithinRadius($driver, $delivery);
            })
            ->values();
    }

    /**
     * Vérifie si le livreur est dans le rayon de recherche
     */
    private function isWithinRadius($driver, $delivery): bool
    {
        if (!$this->hasValidCoordinates($driver, $delivery)) {
            return false; // Exclure si pas de position valide
        }

        $distance = $this->calculateRealDistance(
            $driver->current_lat,
            $driver->current_lng,
            $delivery->pickup_lat,
            $delivery->pickup_lng
        );

        $isWithin = $distance <= $this->searchRadiusKm;

        Log::info("📍 Vérification rayon - Livreur: {$driver->id}, Distance: {$distance}km, Dans rayon: " . ($isWithin ? 'OUI' : 'NON'));

        return $isWithin;
    }

    /**
     * Calcule les scores pour tous les livreurs éligibles
     */
    private function calculateScores($drivers, Delivery $delivery): array
    {
        $scoredDrivers = [];

        foreach ($drivers as $driver) {
            $score = $this->calculateDriverScore($driver, $delivery);

            $scoredDrivers[] = [
                'driver' => $driver,
                'score' => $score,
                'details' => [
                    'rating_score' => $this->calculateRatingScore($driver),
                    'distance_score' => $this->calculateDistanceScore($driver, $delivery),
                    'acceptance_score' => $this->calculateAcceptanceScore($driver),
                    'response_score' => $this->calculateResponseScore($driver)
                ]
            ];
        }

        return $scoredDrivers;
    }

    /**
     * Calcule le score global d'un livreur
     */
    private function calculateDriverScore($driver, $delivery): float
    {
        $weights = [
            'rating' => 0.35,      // 35% - Note du livreur
            'distance' => 0.30,    // 30% - Distance du pickup
            'acceptance' => 0.20,  // 20% - Taux d'acceptation
            'response' => 0.15     // 15% - Temps de réponse
        ];

        $totalScore =
            ($this->calculateRatingScore($driver) * $weights['rating']) +
            ($this->calculateDistanceScore($driver, $delivery) * $weights['distance']) +
            ($this->calculateAcceptanceScore($driver) * $weights['acceptance']) +
            ($this->calculateResponseScore($driver) * $weights['response']);

        return round($totalScore, 2);
    }

    /**
     * Score basé sur la note du livreur
     */
    private function calculateRatingScore($driver): float
    {
        $maxRating = 5.0;
        return $driver->average_rating / $maxRating;
    }

    /**
     * Score basé sur la distance RÉELLE entre livreur et point de ramassage
     */
    private function calculateDistanceScore($driver, $delivery): float
    {
        // Vérifier si on a les coordonnées nécessaires
        if (!$this->hasValidCoordinates($driver, $delivery)) {
            return 0.5; // Score neutre si données manquantes
        }

        // Calculer la distance réelle
        $distance = $this->calculateRealDistance(
            $driver->current_lat,
            $driver->current_lng,
            $delivery->pickup_lat,
            $delivery->pickup_lng
        );

        // Normaliser le score : 0km = 100%, 5km = 0%
        $normalizedScore = $this->normalizeDistanceScore($distance);

        Log::info("📏 Score distance - Livreur: {$driver->id}, Distance: {$distance}km, Score: {$normalizedScore}");

        return $normalizedScore;
    }

    /**
     * Vérifie si les coordonnées sont valides
     */
    private function hasValidCoordinates($driver, $delivery): bool
    {
        return $driver->current_lat && $driver->current_lng &&
            $delivery->pickup_lat && $delivery->pickup_lng;
    }

    /**
     * Calcule la distance RÉELLE en km entre deux points GPS
     */
    private function calculateRealDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        // Convertir les degrés en radians
        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);

        // Différence des coordonnées
        $dLat = $lat2Rad - $lat1Rad;
        $dLng = $lng2Rad - $lng1Rad;

        // Formule de Haversine
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Normalise la distance en score entre 0 et 1
     */
    private function normalizeDistanceScore(float $distance): float
    {
        // 0km = score 1.0, 5km = score 0, >5km = score 0
        $normalized = 1 - min($distance / $this->searchRadiusKm, 1);

        // Arrondir à 2 décimales
        return round($normalized, 2);
    }


    /**
     * Score basé sur le taux d'acceptation
     */
    private function calculateAcceptanceScore($driver): float
    {
        $totalDeliveries = $driver->total_deliveries;
        $acceptedDeliveries = $driver->accepted_deliveries_count ?? $totalDeliveries;

        if ($totalDeliveries === 0) return 0.5; // Score par défaut pour nouveaux

        $acceptanceRate = $acceptedDeliveries / $totalDeliveries;
        return min($acceptanceRate, 1.0);
    }

    /**
     * Score basé sur le temps de réponse (pour l'instant constant)
     */
    private function calculateResponseScore($driver): float
    {
        return 0.7; // À améliorer avec données réelles
    }

    /**
     * Classe les livreurs par score décroissant
     */
    private function rankDrivers(array $scoredDrivers): array
    {
        usort($scoredDrivers, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scoredDrivers;
    }

    /**
     * Méthode utilitaire pour debug
     */
    public function debugMatching(Delivery $delivery): array
    {
        $eligibleDrivers = $this->getEligibleDrivers($delivery);
        $scoredDrivers = $this->calculateScores($eligibleDrivers, $delivery);
        $rankedDrivers = $this->rankDrivers($scoredDrivers);

        return [
            'eligible_count' => count($eligibleDrivers),
            'eligible_drivers' => $eligibleDrivers->pluck('id')->toArray(),
            'ranked_results' => array_map(function ($item) {
                return [
                    'driver_id' => $item['driver']->id,
                    'score' => $item['score'],
                    'details' => $item['details']
                ];
            }, $rankedDrivers)
        ];
    }
}
