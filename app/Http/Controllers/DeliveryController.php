<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Client;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Liste des livraisons (filtrée par rôle)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $deliveries = [];

        if ($user->isClient()) {
            // Client voit ses propres livraisons
            $client = $user->userable;
            $deliveries = Delivery::forClient($client->id)
                ->with('driver')
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($user->isDriver()) {
            // Driver voit ses livraisons acceptées
            $driver = $user->userable;
            $deliveries = Delivery::forDriver($driver->id)
                ->with('client')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Admin voit toutes les livraisons
            $deliveries = Delivery::with(['client', 'driver'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $deliveries
        ]);
    }

    /**
     * Créer une nouvelle livraison (Client seulement)
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est un client
        if (!auth()->user()->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les clients peuvent créer des livraisons.'
            ], 403);
        }

        $request->validate([
            'pickup_address' => 'required|string|max:500',
            'delivery_address' => 'required|string|max:500',
            'package_description' => 'required|string|max:1000',
            'package_weight' => 'required|numeric|min:0.1|max:50',
            'urgency' => 'required|in:low,standard,urgent',
        ]);

        try {
            DB::beginTransaction();

            $client = auth()->user()->userable;

            // Calcul du prix basique
            $basePrice = 1000; // Prix de base
            $weightMultiplier = $request->package_weight * 200; // 200 FCFA par kg
            $urgencyMultiplier = match ($request->urgency) {
                'low' => 1.0,
                'standard' => 1.2,
                'urgent' => 1.5,
                default => 1.2
            };

            $price = ($basePrice + $weightMultiplier) * $urgencyMultiplier;

            $delivery = Delivery::create([
                'pickup_address' => $request->pickup_address,
                'delivery_address' => $request->delivery_address,
                'package_description' => $request->package_description,
                'package_weight' => $request->package_weight,
                'urgency' => $request->urgency,
                'price' => round($price, 2),
                'client_id' => $client->id,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'data' => $delivery->load('client')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accepter une livraison (Driver seulement)
     */
    public function acceptDelivery($id)
    {
        // Vérifier que l'utilisateur est un driver
        if (!auth()->user()->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les drivers peuvent accepter des livraisons.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $delivery = Delivery::pending()->findOrFail($id);
            $driver = auth()->user()->userable;

            // Vérifier si le driver est disponible
            if (!$driver->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être disponible pour accepter une livraison.'
                ], 400);
            }

            // Mettre à jour la livraison
            $delivery->update([
                'driver_id' => $driver->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison acceptée avec succès',
                'data' => $delivery->load(['client', 'driver'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation de la livraison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une livraison spécifique
     */
    public function show($id)
    {
        $user = auth()->user();
        $delivery = Delivery::with(['client', 'driver'])->findOrFail($id);

        // Vérifier les permissions
        if ($user->isClient() && $delivery->client_id !== $user->userable->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette livraison.'
            ], 403);
        }

        if ($user->isDriver() && $delivery->driver_id !== $user->userable->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette livraison.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $delivery
        ]);
    }
}
