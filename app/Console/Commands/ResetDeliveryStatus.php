<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class ResetDeliveryStatus extends Command
{
    protected $signature = 'deliveries:reset-status {id} {status=created}';
    protected $description = 'Réinitialiser le statut d\'une livraison';

    public function handle()
    {
        $delivery = Delivery::find($this->argument('id'));

        if (!$delivery) {
            $this->error('❌ Livraison non trouvée');
            return 1;
        }

        $newStatus = $this->argument('status');
        $allowedStatuses = ['created', 'finding_driver', 'accepted', 'picking_up', 'on_route', 'delivered', 'paid', 'cancelled'];

        if (!in_array($newStatus, $allowedStatuses)) {
            $this->error('❌ Statut non valide. Choisissez parmi: ' . implode(', ', $allowedStatuses));
            return 1;
        }

        $this->info("🔄 Réinitialisation de la livraison #{$delivery->id}");
        $this->line("Ancien statut: {$delivery->status}");
        $this->line("Nouveau statut: {$newStatus}");

        // Réinitialiser les timestamps
        $delivery->update([
            'status' => $newStatus,
            'accepted_at' => null,
            'picked_up_at' => null,
            'delivered_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ]);

        $this->info("✅ Statut réinitialisé avec succès");
        $this->line("Transitions possibles: " . implode(', ', $delivery->getPossibleTransitions()));

        return 0;
    }
}
