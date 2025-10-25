<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Services\MatchingService;
use App\Models\Delivery;
use Illuminate\Console\Command;

class CheckAvailableDrivers extends Command
{
    protected $signature = 'drivers:check-available {deliveryId?}';
    protected $description = 'Vérifier les drivers disponibles pour le matching';

    public function handle()
    {
        $deliveryId = $this->argument('deliveryId');
        $delivery = null;

        if ($deliveryId) {
            $delivery = Delivery::find($deliveryId);
            if ($delivery) {
                $this->info("🎯 Vérification pour la livraison #{$delivery->id}");
                $this->line("Pickup: {$delivery->pickup_address}");
                $this->line("Position: {$delivery->pickup_lat}, {$delivery->pickup_lng}");
            }
        }

        $this->info("🚗 DRIVERS DISPONIBLES:");
        $this->line("---");

        // Drivers éligibles
        $eligibleDrivers = Driver::query()
            ->with('user')
            ->approved()
            ->online()
            ->available()
            ->withRecentLocation(10)
            ->where('average_rating', '>=', 4.0)
            ->get();

        if ($eligibleDrivers->isEmpty()) {
            $this->error("❌ Aucun driver éligible trouvé!");
            $this->line("Vérifiez que:");
            $this->line("- Les drivers sont approuvés (is_approved = true)");
            $this->line("- Les drivers sont en ligne (is_online = true)");
            $this->line("- Les drivers sont disponibles (is_available = true)");
            $this->line("- Les drivers ont une position récente (last_location_update < 10min)");
            return 1;
        }

        $this->line("✅ {$eligibleDrivers->count()} drivers éligibles trouvés:");

        $headers = ['ID', 'Nom', 'Véhicule', 'Note', 'Position', 'En ligne', 'Disponible'];
        $rows = [];

        foreach ($eligibleDrivers as $driver) {
            $rows[] = [
                $driver->id,
                $driver->full_name,
                $driver->vehicle_type,
                $driver->average_rating,
                $driver->current_lat && $driver->current_lng ? "{$driver->current_lat}, {$driver->current_lng}" : 'N/A',
                $driver->is_online ? '✅' : '❌',
                $driver->is_available ? '✅' : '❌',
            ];
        }

        $this->table($headers, $rows);

        // Tester le matching si une livraison est spécifiée
        if ($delivery) {
            $this->info("🔍 TEST MATCHING AVEC CES DRIVERS:");

            $matchingService = new MatchingService();
            $results = $matchingService->findAndNotifyDrivers($delivery);

            if (empty($results)) {
                $this->error("❌ Aucun driver sélectionné par le matching!");
            } else {
                $this->line("✅ " . count($results) . " drivers sélectionnés:");

                $matchHeaders = ['Position', 'Driver ID', 'Nom', 'Score Total', 'Note', 'Distance'];
                $matchRows = [];

                foreach ($results as $index => $result) {
                    $driver = $result['driver'];
                    $distance = $matchingService->calculateRealDistance(
                        $driver->current_lat,
                        $driver->current_lng,
                        $delivery->pickup_lat,
                        $delivery->pickup_lng
                    );

                    $matchRows[] = [
                        $index + 1,
                        $driver->id,
                        $driver->full_name,
                        $result['score'],
                        $driver->average_rating,
                        $distance ? round($distance, 2) . ' km' : 'N/A'
                    ];
                }

                $this->table($matchHeaders, $matchRows);
            }
        }

        return 0;
    }
}
