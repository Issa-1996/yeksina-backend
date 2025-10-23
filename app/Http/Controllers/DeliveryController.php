<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Http\Requests\CreateDeliveryRequest;
use App\Services\PricingService;
use App\Services\SecurityCodeService;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    protected $pricingService;
    protected $securityCodeService;
    protected $matchingService;

    public function __construct(
        PricingService $pricingService,
        SecurityCodeService $securityCodeService,
        MatchingService $matchingService
    ) {
        $this->pricingService = $pricingService;
        $this->securityCodeService = $securityCodeService;
        $this->matchingService = $matchingService;
    }

    /**
     * Créer une nouvelle livraison
     */
    public function store(CreateDeliveryRequest $request)
    {
        try {
            DB::beginTransaction();

            // Calcul de la distance (simulé pour l'instant)
            $distance = $this->calculateDistance(
                $request->pickup_lat,
                $request->pickup_lng,
                $request->destination_lat,
                $request->destination_lng
            );

            // Calcul du prix
            $price = $this->pricingService->calculatePrice(
                $distance,
                $request->package_type,
                $request->estimated_weight ?? 1
            );

            // Génération du code de sécurité
            $securityCode = $this->securityCodeService->generateCode();

            // Création de la livraison
            $delivery = Delivery::create([
                'client_id' => auth()->user()->userable->id,
                'pickup_address' => $request->pickup_address,
                'pickup_lat' => $request->pickup_lat,
                'pickup_lng' => $request->pickup_lng,
                'destination_address' => $request->destination_address,
                'destination_lat' => $request->destination_lat,
                'destination_lng' => $request->destination_lng,
                'recipient_name' => $request->recipient_name,
                'recipient_phone' => $request->recipient_phone,
                'package_type' => $request->package_type,
                'estimated_weight' => $request->estimated_weight,
                'price' => $price,
                'security_code' => $securityCode,
                'client_notes' => $request->client_notes,
            ]);

            // Broadcast aux livreurs disponibles
            $this->matchingService->broadcastNewDelivery($delivery);

            // Envoi du code par SMS (simulé)
            $this->securityCodeService->sendCodeSMS($request->recipient_phone, $securityCode);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès!',
                'data' => [
                    'delivery' => $delivery,
                    'security_code' => $securityCode, // À retirer en production
                    'distance' => round($distance, 2),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur création livraison: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer les livraisons de l'utilisateur
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Delivery::with(['driver', 'client.user']);

        if ($user->isClient()) {
            $query->where('client_id', $user->userable->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->userable->id);
        }

        // Filtrage par statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Tri par date de création
        $deliveries = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $deliveries
        ]);
    }

    /**
     * Accepter une livraison (côté livreur)
     */
    public function acceptDelivery($id)
    {
        try {
            $delivery = Delivery::findOrFail($id);
            $driver = auth()->user()->userable;

            // Vérifications
            if ($delivery->status !== Delivery::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette livraison n\'est plus disponible.'
                ], 400);
            }

            if (!$driver->is_online) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez être en ligne pour accepter une livraison.'
                ], 400);
            }

            // Attribution au livreur
            $delivery->update([
                'driver_id' => $driver->id,
                'status' => Delivery::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            // Mettre à jour les statistiques du livreur
            $driver->increment('total_deliveries');

            return response()->json([
                'success' => true,
                'message' => 'Livraison acceptée avec succès!',
                'data' => $delivery
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur acceptation livraison: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation de la livraison.'
            ], 500);
        }
    }

    /**
     * Calcul de distance simplifié (à améliorer)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        // Simulation - à remplacer par calcul Haversine
        return sqrt(pow($lat2 - $lat1, 2) + pow($lng2 - $lng1, 2)) * 111; // Approximation
    }
}