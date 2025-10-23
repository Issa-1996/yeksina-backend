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

class AuthController extends Controller
{
    public function registerDriver(DriverRegisterRequest $request)
    {
        try {
            DB::beginTransaction();

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
                'is_approved' => false, // En attente d'approbation
            ]);

            // 3. Créer l'utilisateur
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'userable_type' => Driver::class,
                'userable_id' => $driver->id,
                'role' => 'driver',
            ]);

            // 4. Créer le token d'authentification
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
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }


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
     * Logout utilisateur
     */
    public function logout()
    {
        auth('api')->logout(); // ← CORRECTION ICI

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.'
        ]);
    }

    /**
     * Rafraîchir le token
     */
    public function refresh()
    {
        try {
            $token = auth('api')->refresh(); // ← CORRECTION ICI
            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.'
            ], 401);
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
