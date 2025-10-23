<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Client;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


/**
 * @OA\Tag(
 *     name="Deliveries",
 *     description="Endpoints de gestion des livraisons"
 * )
 */
class DeliveryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/deliveries",
     *     tags={"Deliveries"},
     *     summary="Liste des livraisons",
     *     description="Récupère la liste des livraisons (filtrée selon le rôle)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des livraisons",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
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
     * @OA\Post(
     *     path="/deliveries",
     *     tags={"Deliveries"},
     *     summary="Créer une livraison",
     *     description="Créer une nouvelle livraison (client seulement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pickup_address", "delivery_address", "package_description", "package_weight", "urgency"},
     *             @OA\Property(property="pickup_address", type="string", example="Point E, Dakar"),
     *             @OA\Property(property="delivery_address", type="string", example="Plateau, Dakar"),
     *             @OA\Property(property="package_description", type="string", example="Documents importants"),
     *             @OA\Property(property="package_weight", type="number", format="float", example=0.5),
     *             @OA\Property(property="urgency", type="string", enum={"low", "standard", "urgent"}, example="standard")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Livraison créée avec succès"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès non autorisé"
     *     )
     * )
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
            'receiver_name' => 'required|string|max:255',
            'receiver_phone' => 'required|string|max:20',
            'delivery_instructions' => 'nullable|string|max:1000',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:20',
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
                'receiver_name' => $request->receiver_name,
                'receiver_phone' => $request->receiver_phone,
                'delivery_instructions' => $request->delivery_instructions,
                'sender_name' => $request->sender_name ?? $client->first_name . ' ' . $client->last_name,
                'sender_phone' => $request->sender_phone ?? $client->phone,
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
