<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverLocationController extends Controller
{
    /**
     * Mettre à jour la position GPS du livreur
     */
    public function updateLocation(Request $request)
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux livreurs.'
            ], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        try {
            $driver = $user->userable;
            $driver->updateLocation($request->latitude, $request->longitude);

            Log::info("📍 Position mise à jour - Livreur: {$driver->id}, Lat: {$request->latitude}, Lng: {$request->longitude}");

            return response()->json([
                'success' => true,
                'message' => 'Position mise à jour avec succès.',
                'data' => [
                    'latitude' => $driver->current_lat,
                    'longitude' => $driver->current_lng,
                    'last_update' => $driver->last_location_update
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur mise à jour position: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la position.'
            ], 500);
        }
    }

    /**
     * Récupérer la position actuelle du livreur
     */
    public function getCurrentLocation()
    {
        $user = auth()->user();

        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux livreurs.'
            ], 403);
        }

        $driver = $user->userable;

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => $driver->current_lat,
                'longitude' => $driver->current_lng,
                'last_update' => $driver->last_location_update,
                'is_online' => $driver->is_online,
                'is_available' => $driver->is_available
            ]
        ]);
    }
}
