<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverController extends Controller
{
    /**
     * Récupérer le profil du driver connecté
     */
    public function getProfile()
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux drivers.'
            ], 403);
        }

        $driver = $user->userable->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'driver' => $driver,
                'stats' => $this->getDriverStats($driver->id)
            ]
        ]);
    }

    /**
     * Mettre à jour la disponibilité du driver
     */
    public function updateAvailability(Request $request)
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux drivers.'
            ], 403);
        }

        $request->validate([
            'available' => 'required|boolean'
        ]);

        $driver = $user->userable;
        $driver->update(['is_available' => $request->available]);

        return response()->json([
            'success' => true,
            'message' => $request->available ?
                'Vous êtes maintenant disponible pour les livraisons.' :
                'Vous êtes maintenant indisponible.',
            'data' => [
                'is_available' => $driver->is_available
            ]
        ]);
    }

    /**
     * Récupérer les nouvelles livraisons disponibles
     */
    public function getNewDeliveries()
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux drivers.'
            ], 403);
        }

        $driver = $user->userable;

        // Vérifier si le driver est disponible
        if (!$driver->is_available) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être disponible pour voir les nouvelles livraisons.'
            ], 400);
        }

        // Récupérer les livraisons en attente
        $newDeliveries = Delivery::pending()
            ->with('client')
            ->whereDoesntHave('driver') // Pas encore assignées
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $newDeliveries
        ]);
    }

    /**
     * Récupérer les statistiques du driver
     */
    private function getDriverStats($driverId)
    {
        return [
            'total_deliveries' => Delivery::forDriver($driverId)->count(),
            'completed_deliveries' => Delivery::forDriver($driverId)->where('status', 'delivered')->count(),
            'pending_deliveries' => Delivery::forDriver($driverId)->whereIn('status', ['accepted', 'picked_up', 'in_transit'])->count(),
            'total_earnings' => Delivery::forDriver($driverId)->where('status', 'delivered')->sum('price'),
        ];
    }

    /**
     * Mettre à jour le statut d'une livraison
     */
    public function updateDeliveryStatus(Request $request, $deliveryId)
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux drivers.'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:picked_up,in_transit,delivered,cancelled'
        ]);

        $driver = $user->userable;
        $delivery = Delivery::forDriver($driver->id)->findOrFail($deliveryId);

        try {
            DB::beginTransaction();

            $statusData = ['status' => $request->status];

            // Mettre à jour les timestamps selon le statut
            switch ($request->status) {
                case 'picked_up':
                    $statusData['picked_up_at'] = now();
                    break;
                case 'delivered':
                    $statusData['delivered_at'] = now();
                    break;
            }

            $delivery->update($statusData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Statut de livraison mis à jour avec succès.',
                'data' => $delivery->load('client')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer l'historique des livraisons du driver
     */
    public function getDeliveryHistory()
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux drivers.'
            ], 403);
        }

        $driver = $user->userable;
        $deliveries = Delivery::forDriver($driver->id)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deliveries
        ]);
    }
}
