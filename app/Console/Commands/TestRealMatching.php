<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\MatchingService;

class TestRealMatching extends Command
{
    protected $signature = 'test:realmatching';
    protected $description = 'Tester le matching avec données réelles';

    public function handle()
    {
        $this->info('🎯 TEST MATCHING AVEC DONNÉES RÉELLES');

        // 1. Créer/mettre à jour des positions de test
        $this->setupTestData();

        // 2. Tester avec une livraison réelle
        $delivery = Delivery::first();

        if (!$delivery) {
            $this->error('Aucune livraison trouvée. Créez d abord une livraison.');
            return;
        }

        $matchingService = new MatchingService();
        $results = $matchingService->findDriversForDelivery($delivery);

        $this->info("📊 RÉSULTATS POUR LIVRAISON #{$delivery->id}");
        $this->line("Pickup: {$delivery->pickup_address}");

        foreach ($results as $index => $result) {
            $driver = $result['driver'];
            $distance = $matchingService->calculateRealDistance(
                $driver->current_lat,
                $driver->current_lng,
                $delivery->pickup_lat,
                $delivery->pickup_lng
            );

            $this->line(($index + 1) . ". {$driver->full_name} - Score: {$result['score']} - Distance: {$distance}km");
        }
    }

    private function setupTestData()
    {
        // Positions de test autour de Dakar
        $testPositions = [
            [14.7167, -17.4677], // Centre Dakar
            [14.7700, -17.4700], // Point E
            [14.6700, -17.4300], // Plateau
            [14.7500, -17.5200], // Almadies
            [14.6900, -17.4600], // Fann
        ];

        $drivers = Driver::limit(5)->get();

        foreach ($drivers as $index => $driver) {
            if ($index < count($testPositions)) {
                $driver->updateLocation($testPositions[$index][0], $testPositions[$index][1]);
                $this->info("📍 Position test pour {$driver->full_name}: {$testPositions[$index][0]}, {$testPositions[$index][1]}");
            }
        }
    }
}
