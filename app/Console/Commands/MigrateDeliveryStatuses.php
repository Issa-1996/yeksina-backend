<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class MigrateDeliveryStatuses extends Command
{
    protected $signature = 'deliveries:migrate-statuses';
    protected $description = 'Migrer les anciens statuts vers le nouveau système';

    public function handle()
    {
        $this->info('🔄 Migration des statuts des livraisons...');

        $count = Delivery::migrateOldStatuses();

        $this->info("✅ {$count} livraisons migrées avec succès");
        $this->line('Ancien statut: pending → Nouveau statut: created');

        // Afficher un exemple
        $delivery = Delivery::first();
        if ($delivery) {
            $this->line("---");
            $this->line("Exemple - Livraison #{$delivery->id}:");
            $this->line("Statut: {$delivery->status}");
            $this->line("Transitions possibles: " . implode(', ', $delivery->getPossibleTransitions()));
        }

        return 0;
    }
}