<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Delivery;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    protected $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Mettre à jour la disponibilité du livreur
     */
    public function updateAvailability(Request $request)
    {
        $request->validate([
            'status' => 'required|in:online,offline'
        ]);

        $driver = auth()->user()->userable;
        $isOnline = $request->status === 'online';

        $driver->update([
            'is_online' => $isOnline,
            'last_online_at' => $isOnline ? now() : $driver->last_online_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => $isOnline ? 'Vous êtes maintenant en ligne.' : 'Vous êtes maintenant hors ligne.',
            'data' => [
                'is_online' => $driver->is_online,
                'last_online_at' => $driver->last_online_at,
            ]
        ]);
    }

    /**
     * Récupérer les nouvelles courses disponibles
     */
    public function getNewDeliveries(Request $request)
    {
        $driver = auth()->user()->userable;

        if (!$driver->is_online) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être en ligne pour voir les nouvelles courses.'
            ], 400);
        }

        // Récupérer la position actuelle du driver (simulée pour l'instant)
        $currentLat = 14.6928; // À remplacer par GPS réel
        $currentLng = -17.4467;

        $availableDeliveries = Delivery::where('status', Delivery::STATUS_PENDING)
            ->whereDoesntHave('driver') // Pas encore attribuée
            ->with(['client.user'])
            ->get()
            ->map(function ($delivery) use ($currentLat, $currentLng) {
                $distance = $this->calculateSimpleDistance(
                    $currentLat, $currentLng,
                    $delivery->pickup_lat, $delivery->pickup_lng
                );

                return [
                    'id' => $delivery->id,
                    'pickup_address' => $delivery->pickup_address,
                    'destination_address' => $delivery->destination_address,
                    'distance' => round($distance, 1),
                    'price' => $delivery->price,
                    'package_type' => $delivery->package_type,
                    'created_at' => $delivery->created_at->format('d M Y H:i'),
                    'client_name' => $delivery->client->full_name ?? 'Client',
                    'estimated_time' => $this->estimateTime($distance),
                ];
            })
            ->sortBy('distance')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $availableDeliveries
        ]);
    }

    /**
     * Récupérer le profil du livreur
     */
    public function getProfile()
    {
        $driver = auth()->user()->userable->load('user');

        return response()->json([
            'success' => true,
            'data' => [
                'driver' => $driver,
                'stats' => [
                    'current_balance' => $driver->current_balance,
                    'total_earnings' => $driver->total_earnings,
                    'total_deliveries' => $driver->total_deliveries,
                    'average_rating' => $driver->average_rating,
                    'is_online' => $driver->is_online,
                ]
            ]
        ]);
    }

    /**
     * Calcul de distance simplifié
     */
    private function calculateSimpleDistance($lat1, $lng1, $lat2, $lng2): float
    {
        return sqrt(pow($lat2 - $lat1, 2) + pow($lng2 - $lng1, 2)) * 111;
    }

    /**
     * Estimation du temps en minutes
     */
    private function estimateTime($distanceKm): int
    {
        return max(3, (int) round($distanceKm * 3));
    }
}