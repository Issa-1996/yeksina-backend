<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Driver;
use App\Models\Client;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\ClientRegisterRequest;
use App\Http\Requests\DriverRegisterRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


/**
 * @OA\Info(
 *     title="Yeksina Delivery API",
 *     version="1.0.0",
 *     description="API pour l'application de livraison Yeksina"
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Serveur local"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints d'authentification"
 * )
 */
class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/auth/driver/register",
     *     tags={"Authentication"},
     *     summary="Inscription d'un driver",
     *     description="Inscription d'un nouveau driver avec upload de photo CNI",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"first_name", "last_name", "birth_date", "address", "phone", "email", "password", "vehicle_type", "license_plate", "cni_photo"},
     *                 @OA\Property(property="first_name", type="string", example="Pape"),
     *                 @OA\Property(property="last_name", type="string", example="Diop"),
     *                 @OA\Property(property="birth_date", type="string", format="date", example="1990-01-01"),
     *                 @OA\Property(property="address", type="string", example="Dakar, Senegal"),
     *                 @OA\Property(property="phone", type="string", example="+221771234567"),
     *                 @OA\Property(property="email", type="string", format="email", example="driver@yeksina.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="password123"),
     *                 @OA\Property(property="vehicle_type", type="string", enum={"voiture", "moto", "camion"}, example="moto"),
     *                 @OA\Property(property="license_plate", type="string", example="DK-1234-AB"),
     *                 @OA\Property(property="cni_photo", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inscription réussie. Votre compte est en attente d'approbation."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string", example="1|xxxxxxxxxxxxxxxx"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function registerDriver(Request $request) // ← Enlevez DriverRegisterRequest temporairement
    {
        try {
            // Validation manuelle temporaire
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'birth_date' => 'required|date',
                'address' => 'required|string|max:500',
                'phone' => 'required|string|unique:drivers,phone',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'vehicle_type' => 'required|in:voiture,moto,camion',
                'license_plate' => 'required|string|max:20',
                'cni_photo' => 'required|image|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // DEBUG: Vérifiez si le fichier arrive
            \Log::info('Fichier reçu:', ['has_file' => $request->hasFile('cni_photo')]);

            if (!$request->hasFile('cni_photo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier CNI est requis.'
                ], 422);
            }

            // 1. Uploader la photo CNI
            $cniPath = $request->file('cni_photo')->store('cni_photos', 'public');

            // 2. Créer le driver
            $driver = Driver::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'phone' => $request->phone,
                'cni_photo_path' => $cniPath,
                'vehicle_type' => $request->vehicle_type,
                'license_plate' => $request->license_plate,
                'is_approved' => false,
                'is_available' => false, // Pas disponible tant qu'approuvé
            ]);

            // 3. Créer l'utilisateur
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'userable_type' => Driver::class,
                'userable_id' => $driver->id,
                'role' => 'driver',
            ]);

            // 4. Créer le token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie. Votre compte est en attente d\'approbation.',
                'data' => [
                    'user' => $user->load('userable'),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage(),
            ], 500);
        }
    }




    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Authentication"},
     *     summary="Connexion utilisateur",
     *     description="Connexion pour tous les types d'utilisateurs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_or_email", "password"},
     *             @OA\Property(property="phone_or_email", type="string", example="admin@yeksina.com"),
     *             @OA\Property(property="password", type="string", format="password", example="admin123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string", example="3|xxxxxxxxxxxxxxxx"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants incorrects"
     *     )
     * )
     */
    public function registerClient(ClientRegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            // 1. Créer le client
            $client = Client::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'address' => $request->address,
            ]);

            // 2. Créer l'utilisateur
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'userable_type' => Client::class,
                'userable_id' => $client->id,
                'role' => 'client',
            ]);

            // 3. Créer le token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription client réussie.',
                'data' => [
                    'user' => $user->load('userable'),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Connexion d'un utilisateur (Driver ou Client)
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone_or_email' => 'required|string',
            'password' => 'required|string',
        ]);

        // Rechercher l'utilisateur par email ou par phone
        $user = User::where('email', $request->phone_or_email)
            ->orWhereHas('userable', function ($query) use ($request) {
                $query->where('phone', $request->phone_or_email);
            })
            ->first();

        // Vérifier si l'utilisateur existe et si le mot de passe est correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Les identifiants sont incorrects.'
            ], 401);
        }

        // Charger la relation userable (Driver ou Client)
        $user->load('userable');

        // Vérifications supplémentaires pour les drivers
        if ($user->isDriver() && !$user->userable->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.'
            ], 403);
        }

        // Révoquer les anciens tokens (optionnel mais recommandé)
        $user->tokens()->delete();

        // Créer un nouveau token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Récupère le profil de l'utilisateur connecté
     */
    public function me()
    {
        $user = auth('api')->user(); // ← CORRECTION ICI
        $profile = null;

        if ($user->isDriver()) {
            $profile = $user->userable;
        } elseif ($user->isClient()) {
            $profile = $user->userable;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                ],
                'profile' => $profile,
            ]
        ]);
    }

    /**
     * Logout utilisateur (Sanctum)
     */
    public function logout(Request $request)
    {
        try {
            // Révoquer le token actuel
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rafraîchir le token (Sanctum)
     */
    public function refresh(Request $request)
    {
        try {
            // Avec Sanctum, on crée simplement un nouveau token
            $user = auth()->user();

            // Révoquer l'ancien token (optionnel)
            $request->user()->currentAccessToken()->delete();

            // Créer un nouveau token
            $newToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token rafraîchi avec succès',
                'data' => [
                    'access_token' => $newToken,
                    'token_type' => 'Bearer',
                    'user' => $user->load('userable')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement du token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formater la réponse avec token
     */
    private function respondWithToken($token)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60, // ← CORRECTION ICI
                'user' => auth('api')->user(),
            ]
        ]);
    }

    /**
     * Préparer les credentials pour la connexion
     */
    private function getCredentials($phoneOrEmail, $password)
    {
        $field = filter_var($phoneOrEmail, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        return [
            $field => $phoneOrEmail,
            'password' => $password,
        ];
    }

    /**
     * Formater la réponse avec token
     */
    // private function respondWithToken($token)
    // {
    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'access_token' => $token,
    //             'token_type' => 'bearer',
    //             'expires_in' => auth()->factory()->getTTL() * 60,
    //             'user' => auth()->user(),
    //         ]
    //     ]);
    // }
}
