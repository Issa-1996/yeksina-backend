<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Delivery;
use App\Services\MatchingService;

class TestMatching extends Command
{
    protected $signature = 'test:matching {deliveryId} {--debug}';
    protected $description = 'Tester l\'algorithme de matching des livreurs';

    public function handle()
    {
        $deliveryId = $this->argument('deliveryId');
        $debugMode = $this->option('debug');

        $delivery = Delivery::with(['client', 'driver'])->find($deliveryId);

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée avec ID: ' . $deliveryId);
            return 1;
        }

        $this->info('🎯 TEST ALGORITHME DE MATCHING');
        $this->line('Livraison: #' . $delivery->id . ' - ' . $delivery->pickup_address . ' → ' . $delivery->delivery_address);
        $this->line('Prix: ' . $delivery->price . ' FCFA');
        $this->line('---');

        $matchingService = new MatchingService();

        if ($debugMode) {
            $debugInfo = $matchingService->debugMatching($delivery);

            $this->info('🐛 DEBUG INFORMATION:');
            $this->line('Livreurs éligibles: ' . $debugInfo['eligible_count']);
            $this->line('IDs éligibles: ' . implode(', ', $debugInfo['eligible_drivers']));
            $this->line('---');
        }

        $results = $matchingService->findDriversForDelivery($delivery);

        if (empty($results)) {
            $this->error('❌ Aucun livreur trouvé pour cette livraison');
            return 1;
        }

        $this->info('✅ MEILLEURS LIVREURS TROUVÉS:');

        $headers = ['Position', 'Livreur ID', 'Nom', 'Score Total', 'Note', 'Distance', 'Acceptation', 'Réponse'];
        $rows = [];

        foreach ($results as $index => $result) {
            $driver = $result['driver'];
            $details = $result['details'];

            $rows[] = [
                $index + 1,
                $driver->id,
                $driver->first_name . ' ' . $driver->last_name,
                $result['score'],
                round($details['rating_score'] * 5, 1),
                round($details['distance_score'] * 100) . '%',
                round($details['acceptance_score'] * 100) . '%',
                round($details['response_score'] * 100) . '%'
            ];
        }

        $this->table($headers, $rows);

        // Afficher le gagnant
        $bestDriver = $results[0]['driver'];
        $this->info('🏆 LIVREUR RECOMMANDÉ: ' . $bestDriver->full_name . ' (Score: ' . $results[0]['score'] . ')');

        return 0;
    }
}
