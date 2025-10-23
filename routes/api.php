<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DriverController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques
Route::prefix('auth')->group(function () {
    Route::post('driver/register', [AuthController::class, 'registerDriver']);
    Route::post('client/register', [AuthController::class, 'registerClient']);
    Route::post('login', [AuthController::class, 'login']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Routes Admin (avec middleware admin) (à protéger avec un middleware admin plus tard)
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/drivers/pending', [AdminController::class, 'getPendingDrivers']);
        Route::get('/drivers/approved', [AdminController::class, 'getApprovedDrivers']);
        Route::post('/drivers/{id}/approve', [AdminController::class, 'approveDriver']);
        Route::delete('/drivers/{id}/reject', [AdminController::class, 'rejectDriver']);
    });

    // Livraisons
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::post('/', [DeliveryController::class, 'store']);
        Route::post('{id}/accept', [DeliveryController::class, 'acceptDelivery']);
    });

    // Livreurs
    Route::prefix('driver')->group(function () {
        Route::get('profile', [DriverController::class, 'getProfile']);
        Route::post('availability', [DriverController::class, 'updateAvailability']);
        Route::get('new-deliveries', [DriverController::class, 'getNewDeliveries']);
    });
});

// Route de test
Route::get('/test', function () {
    return response()->json([
        'message' => 'API Yeksina fonctionne! 🚀',
        'timestamp' => now(),
    ]);
});
